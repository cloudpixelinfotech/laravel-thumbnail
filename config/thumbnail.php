<?php

return [

    /*
    |--------------------------------------------------------------------------
    |  Thumbnail Feature
    |--------------------------------------------------------------------------
    |
    | This option defines whether to use the Package's Thumbnail feature or not
    | Default option is true
    |
    */
    'thumbnail' => true,

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Qualities
    |--------------------------------------------------------------------------
    |
    | These options are the default post image and its thumbnail quality
    |
    |
    */

    'image_quality' => 80,

    /*
    |--------------------------------------------------------------------------
    | Default Image Fit Size
    |--------------------------------------------------------------------------
    |
    | These options are the default post image height and width fit size
    |
    |
    */

    'img_width'  => 1000,
    'img_height' => 800,

    /*
    |--------------------------------------------------------------------------
    | Image and Thumbnail presets.
    |--------------------------------------------------------------------------
    |
    | Thumbnail settings are grouped in presets.
    | So that you can have different settings for e.g. profile and album pictures.
    |
    */
    
    'presets' => [
        'default' => [
            /**
             * Store the generated images here.
             *
             * Note: Every preset needs a unique path.
             */
            'destination' => ['disk' => 'public', 'path' => 'uploads'],
            'thumbnails' => [
                'medium' => [
                    'width' => 800,
                    'height' => 600,
                    'quality' => 60
                ],
                'small' => [
                    'width' => 400,
                    'height' => 300,
                    'quality' => 30
                ]
            ]
        ],

        //add more presets e.g. "avatar".
        'profile' => [
            'destination' => ['disk' => 'public', 'path' => 'uploads/profile'],
            'thumbnails' => [
                'medium' => [
                    'width' => 800,
                    'height' => 600,
                    'quality' => 60
                ],
                'small' => [
                    'width' => 400,
                    'height' => 300,
                    'quality' => 30
                ]
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Cached Time (Minutes)
    |--------------------------------------------------------------------------
    |
    | Option for setting image cached time
    |
    |
    */

    'image_cached_time' => 10,
];
