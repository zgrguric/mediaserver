<?php

return [
  'default' => getenv('FILESYSTEM_DISK_DEFAULT'),
  'disks' => [
      'local' => [
          'driver' => 'Local',
          'root' => getenv('FILESYSTEM_DISK_LOCAL_PATH'),
          'backup' => [
            0 => getenv('FILESYSTEM_DISK_LOCAL_PATH_BACKUP1'),
            1 => getenv('FILESYSTEM_DISK_LOCAL_PATH_BACKUP2'),
            2 => getenv('FILESYSTEM_DISK_LOCAL_PATH_BACKUP3'),
            3 => getenv('FILESYSTEM_DISK_LOCAL_PATH_BACKUP4'),
          ],
          'url' => getenv('APP_URL'),
      ],
      /*'s3' => [
          'driver' => 'S3',
          'key' => getenv('AWS_ACCESS_KEY_ID'),
          'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
          'region' => getenv('AWS_DEFAULT_REGION'),
          'bucket' => getenv('AWS_BUCKET'),
          //required
          //consider '/m/img/',
          'url' => getenv('AWS_URL'),
      ],*/
  ],
];
