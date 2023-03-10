<?php
/**
* @see https://github.com/PHP-FFMpeg/PHP-FFMpeg
*/
return [
  //Dirname as defined in nginx internal media block
  'nginx_media_dirname' => getenv('VIDEO_NGINX_DIRNAME'),
  'queue' => getenv('VIDEO_QUEUE'),

  'ffmpeg' => [
    #ffmpeg
    'ffmpeg.threads'   => 12,
    'ffmpeg.timeout'   => 300,
    'ffmpeg.binaries'  => getenv('VIDEO_FFMPEG_PATH'),

   #ffprobe
   'ffprobe.timeout'  => 30,
   'ffprobe.binaries' => getenv('VIDEO_FFPROBE_PATH'),
  ],
];
