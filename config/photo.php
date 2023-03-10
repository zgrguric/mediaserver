<?php

return [
  /*
  |--------------------------------------------------------------------------
  | Image Driver
  |--------------------------------------------------------------------------
  |
  | Intervention Image supports "GD Library" and "Imagick" to process images
  | internally. You may choose one of them according to your PHP
  | configuration. By default PHP's "GD Library" implementation is used.
  |
  | Supported: "gd", "imagick"
  |
  */
  'driver' => getenv('PHOTO_DRIVER'),
  'resize' => ['w' => 1024, 'h' => 1024],
  'restrict_size_min' => 64,

  //'thumb_size_width' => 100,
  'quality' => 75, //compression quality 1-100

  'content_type_map' => [
    //file ext => content-type
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'png'   => 'image/png'
  ],

  'image_sizes' => [
    'original'  => ['w'=>1024,'h'=>null],
    'large'     => ['w'=>1024,'h'=>1024,'fit' =>'crop'],
    'small'     => ['w'=>680,'h'=>680,'fit' =>'crop'],
    'thumb'     => ['w'=>100,'h'=>null], //width only, keeps aspect ratio
    'th'        => ['w'=>135,'h'=>135,'fit' =>'crop'],
    'md'        => ['w'=>72,'h'=>72,'fit' =>'crop'],
    'tiny'      => ['w'=>64,'h'=>64,'fit' =>'crop'],
  ],

];
