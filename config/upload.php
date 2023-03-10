<?php

return [
  #Photo
  'allowed_photo_size_mb' => '5',
  'allowed_photo_mimetypes' => [
    'image/jpeg',
    'image/bmp',
    'image/gif',
    'image/svg+xml',
    'image/png'
  ],
  'allowed_photo_ext' => ['jpg','jpeg','gif','png'],


  #Video
  'allowed_video_size_mb' => '50',
  'allowed_video_mimetypes' => [
    'video/mp4',
    'video/wma',
    'video/avi',
    'video/mpeg',
    'video/mp4',
    'video/quicktime',
    'video/ogg',
    'video/x-msvideo',
    'video/3gpp',
    'video/3gpp2',
    'video/x-ms-asf',
    'video/x-flv',
    'application/octet-stream',
    //'video/mkv',
  ],
  'allowed_video_ext' => ['mp4', 'wma', 'wmv', 'avi', 'flv', 'mpeg', 'mpg', 'qt', 'mov', 'ogv', '3gp', '3g2'
    //,'mkv'
  ],

];
