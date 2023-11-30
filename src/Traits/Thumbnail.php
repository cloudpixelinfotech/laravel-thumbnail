<?php

namespace CloudPixel\Thumbnail\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use \SplFileInfo;

trait Thumbnail
{
    public function makeThumbnail($fieldname = 'image', $preset = 'default', $custom = [])
    {
        if (! empty(request()->$fieldname) || $custom['image']) {
            /* ------------------------------------------------------------------- */

            $image_file = $custom['image'] ?? request()->file($fieldname); // Retriving Image File
            $extension = $this->image_info($image_file)->extension; //Retriving Image extension
            $imageStoreNameOnly = $this->image_info($image_file)->imageStoreNameOnly; //Making Image Store name

            /* ------------------------------------------------------------------- */

            /* ----------------------------------------Parent Image Upload----------------------------------------- */
            $this->uploadImage($fieldname, $preset, $custom); // Upload Parent Image
            /* --------------------------------------------------------------------------------------------- */
            if (config('thumbnail.thumbnail', true)) {
                $thumbnails = false;
                $thumbnails = $custom['thumbnails'] ?? config('thumbnail.thumbnails') ?? false; // Grab Thumbnails
                $storage = $custom['storage'] ?? config('thumbnail.presets.' . $preset . '.destination.path', 'uploads') ?? false; // Grab Storage Info
                if ($thumbnails) {
                    /* -----------------------------------------Custom Thumbnails------------------------------------------------- */
                    $this->makeCustomThumbnails($image_file, $imageStoreNameOnly, $extension, $storage, $thumbnails);
                /* -------------------------------------------------------------------------------------------------- */
                } else {
                    /* ---------------------------------------Default Thumbnails--------------------------------------- */
                    $this->makeDefaultThumbnails($image_file, $extension, $storage, $imageStoreNameOnly, $preset);
                    /* ------------------------------------------------------------------------------------------------ */
                }
            }
        }
    }

    // Make Image
    private function makeImg($image_file, $name, $location, $preset, $width, $height, $quality)
    {
        $image = $image_file->storeAs($location, $name, config('thumbnail.presets.' . $preset . '.destination.disk', 'public')); // Thumbnail Storage Information
        $img = Image::cache(function ($cached_img) use ($image_file, $width, $height) {
            return $cached_img->make($image_file->getRealPath())->fit($width, $height);
        }, config('thumbnail.image_cached_time', 10), true); //Storing Thumbnail
        $img->save(Storage::disk(config('thumbnail.presets.' . $preset . '.destination.disk', 'public'))->path($image), $quality); //Storing Thumbnail
    }

    // Make Custom Thumbnail
    private function makeCustomThumbnails($image_file, $imageStoreNameOnly, $extension, $storage, $thumbnails)
    {
        foreach ($thumbnails as $size => $thumbnail) {
            $customthumbnail = $imageStoreNameOnly.'-'.str_replace('-', '', $size).'.'.$extension; // Making Thumbnail Name
            $this->makeImg(
                $image_file,
                $customthumbnail,
                $storage . DIRECTORY_SEPARATOR . $size,
                $preset,
                (int) $thumbnail['width'],
                (int) $thumbnail['height'],
                (int) $thumbnail['quality']
            );
        }
    }

    // Make Default Thumbnail
    private function makeDefaultThumbnails($image_file, $extension, $storage, $imageStoreNameOnly, $preset)
    {
        $thumbnails = config('thumbnail.presets.' . $preset . '.thumbnails');
        if ($thumbnails) {
            foreach ($thumbnails as $size => $thumbnail) {
                $customthumbnail = $imageStoreNameOnly.'-'.str_replace('-', '', $size).'.'.$extension; // Making Thumbnail Name
                $this->makeImg(
                    $image_file,
                    $customthumbnail,
                    $storage . DIRECTORY_SEPARATOR . $size,
                    $preset,
                    (int) $thumbnail['width'],
                    (int) $thumbnail['height'],
                    (int) $thumbnail['quality']
                );
            }
        }

        /* ------------------------------------------------------------------------------------- */
    }

    /* Image Upload Process Info */
    private function image_info($image_file)
    {
        $filenamewithextension = $image_file->getClientOriginalName(); //Retriving Full Image Name
        $raw_filename = pathinfo($filenamewithextension, PATHINFO_FILENAME); //Retriving Image Raw Filename only
        $filename = $this->validImageName($raw_filename); // Retrive Filename
        $extension = $image_file->getClientOriginalExtension(); //Retriving Image extension
        $imageStoreNameOnly = $filename.'-'.time(); //Making Image Store name
        $imageStoreName = $filename.'-'.time().'.'.$extension; //Making Image Store name

        $image_info['filenamewithextension'] = $filenamewithextension;
        $image_info['raw_filename'] = $raw_filename;
        $image_info['filename'] = $filename;
        $image_info['extension'] = $extension;
        $image_info['imageStoreNameOnly'] = $imageStoreNameOnly;
        $image_info['imageStoreName'] = $imageStoreName;

        return json_decode(json_encode($image_info));
    }

    // Upload Parent Image
    public function uploadImage($fieldname = 'image', $preset = 'default', $custom = [])
    {
        $image_file = $custom['image'] ?? request()->file($fieldname); // Retriving Image File
        $img = $custom['image'] ?? request()->$fieldname;
        $imageStoreName = $this->image_info($image_file)->imageStoreName;
        $storage_path = $custom['storage'] ?? config('thumbnail.presets.' . $preset . '.destination.path', 'uploads');
        if ($img->storeAs($storage_path, $imageStoreName, config('thumbnail.presets.' . $preset . '.destination.disk', 'public'))) {
            $this->update([
                $fieldname => $imageStoreName, // Storing Parent Image
            ]);
        }

        $image = Image::cache(function ($cached_img) use ($image_file, $custom) {
            return $cached_img->make($image_file->getRealPath())->fit($custom['width'] ?? config('thumbnail.img_width', 1000), $custom['height'] ?? config('thumbnail.img_height', 800)); //Parent Image Interventing
        }, config('thumbnail.image_cached_time', 10), true);
        $image->save(Storage::disk(config('thumbnail.presets.' . $preset . '.destination.disk'))->path($storage_path . '/' . $this->getRawOriginal($fieldname)), $custom['quality'] ?? config('thumbnail.image_quality', 80)); // Parent Image Locating Save
    }

    // Thumbnail Path
    public function thumbnail($fieldname = 'image', $preset = 'default', $size = null, $byLocation = false)
    {
        return $this->imageDetail($fieldname, $preset, $size, $byLocation)->path;
    }

    /* Checking Image Existance */
    private function imageExists($image)
    {
        return file_exists($image->getRealPath());
    }

    // Checking Image's Thumbnail Existance
    public function hasThumbnail($fieldname = 'image', $size = null)
    {
        return $this->imageDetail($fieldname, $size)->property->has_thumbnail;
    }

    // Thumbnail Count
    public function thumbnailCount($fieldname = 'image', $size = null)
    {
        return $this->hasThumbnail($fieldname, $size) ? $this->imageDetail($fieldname, $size)->property->thumbnail_count : 0;
    }

    /* Image Details */
    public function imageDetail($fieldname = 'image', $preset = 'default', $size = null, $byLocation = false)
    {
        $image = $this->getRawOriginal($fieldname);
        $extension = \File::extension($image);
        $name = basename($image, '.'.$extension);
        $image_fullname = isset($size) ? $name.'-'.(string) $size.'.'.$extension : $name.'.'.$extension;
        $disk_path = Storage::disk(config('thumbnail.presets.' . $preset . '.destination.disk'), 'public');
        $upload_path = config('thumbnail.presets.' . $preset . '.destination.path', 'uploads');
        $path = $disk_path->url($upload_path);
        if ($size != null) {
            $upload_path = $upload_path . DIRECTORY_SEPARATOR . $size;
            $path = $path . DIRECTORY_SEPARATOR . $size;
        }
        
        $image_files = array();
        if (File::exists($disk_path->path($upload_path) . DIRECTORY_SEPARATOR . $image_fullname)) {
            array_push($image_files, new SplFileInfo($disk_path->path($upload_path) . DIRECTORY_SEPARATOR . $image_fullname));
        }

        $thumbnails = array_keys(config('thumbnail.presets.' . $preset . '.thumbnails'));
        if (!empty($thumbnails) && $size == null) {
            foreach($thumbnails as $key => $thumbnail) {
                $file_exists = File::exists($disk_path->path($upload_path) . DIRECTORY_SEPARATOR . $thumbnail . DIRECTORY_SEPARATOR . $name . '-' . $thumbnail . '.' . $extension);
                if ($file_exists) {
                    array_push($image_files, new SplFileInfo($disk_path->path($upload_path) . DIRECTORY_SEPARATOR . $thumbnail . DIRECTORY_SEPARATOR . $name . '-' . $thumbnail . '.' . $extension));
                }
            }
        }
        
        $images_property = $size == null ? $this->imageProperty($image_files, $name) : [];
        $image_detail = [
            'image'     => $image,
            'name'      => $name,
            'fullname'  => $image_fullname,
            'extension' => $extension,
            'path'      => $path . DIRECTORY_SEPARATOR . $image_fullname,
            'directory' => $disk_path->path($upload_path),
            'location'  => $disk_path->path($upload_path . DIRECTORY_SEPARATOR . $image_fullname),
            'property'  => $images_property,
        ];
        
        return json_decode(json_encode($image_detail));
    }

    // Image Property
    private function imageProperty($image_files, $parent_name)
    {
        $images_property = [];
        $thumbnails_property = [];
        $thumbnail_count = 0;
        foreach ($image_files as $image) {
            if (strpos(basename($image), '-') === false) {
                continue;
            }

            $image_partition = explode('-', basename($image));
            if (isset($image_partition[0]) && isset($image_partition[1])) {
                $parent_thumbnail_name = $image_partition[0].'-'.$image_partition[1];
                if ($parent_name == $parent_thumbnail_name) {
                    $thumbnail_count++;
                    $thumbnail_exists = $this->imageExists($image);
                    if (isset($image_partition[2])) {
                        $thumbnails_property['image'] = $image->getFilename() ?? null;
                        $thumbnails_property['real_name'] = $image_partition[0];
                        $thumbnails_property['size'] = $image->getSize();
                        $thumbnails_property['created_date'] = isset($image_partition[1]) ? date('Y/m/d H:i:s', (int) $image_partition[1]) : null;
                        $thumbnails_property['directory'] = $image->getPath();
                        $thumbnails_property['location'] = $image->getRealPath();
                        $images_property['has_thumbnail'] = $thumbnail_exists || $this->imageExists($image);
                        $images_property['thumbnail_count'] = $thumbnail_count;
                        $thumbnails[] = $thumbnails_property;
                        $images_property['thumbnails'] = $thumbnails;
                    }
                } elseif ($image->getBasename('.' . $image->getExtension()) == $parent_name) {
                    $images_property['has_thumbnail'] = ($thumbnail_exists ?? false);
                    $images_property['real_name'] = $image_partition[0];
                    $images_property['size'] = $image->getSize();
                    $images_property['directory'] = $image->getPath();
                    $images_property['location'] = $image->getRealPath();
                } else {
                }
            }
        }

        return $images_property;
    }

    // Hard Delete
    public function hardDelete($fieldname = 'image', $preset = 'default', $size = null): void
    {
        $image_detail = $this->imageDetail($fieldname, $preset, $size);
        if (File::exists($image_detail->location)) {
            if (!empty($image_detail->property)) {
                if ($image_detail->property->has_thumbnail) {
                    foreach ($image_detail->property->thumbnails as $thumbnail) {
                        File::exists($thumbnail->location) ? File::delete($thumbnail->location) : '';
                    }
                }
            }
            File::exists($image_detail->location) ? File::delete($image_detail->location) : false;
        }
    }

    // Hard Delete with Parent
    public function hardDeleteWithParent($fieldname = 'image', $preset = 'default', $size = null): void
    {
        $this->hardDelete($fieldname, $preset, $size);
        $this->delete();
    }

    // Valid Image Name
    private function validImageName($name)
    {
        return strtolower(str_replace([' ', '-', '$', '<', '>', '&', '{', '}', '*', '\\', '/', ':'.';', ',', "'", '"'], '_', trim($name)));
    }
}
