<?php
return [
  /*
  |--------------------------------------------------------------------------
  | Database Connections
  |--------------------------------------------------------------------------
  |
  | Here are each of the database connections setup for application.
  |
  */

  'connections' => [
    'media' => [
      'driver'   => 'mongodb',
      'host'     => getenv('DB_CONNECTION_MEDIA_HOST'),
      'port'     => getenv('DB_CONNECTION_MEDIA_PORT'),
      'database' => getenv('DB_CONNECTION_MEDIA_DATABASE'),
      'username' => getenv('DB_CONNECTION_MEDIA_USERNAME'),
      'password' => getenv('DB_CONNECTION_MEDIA_PASSWORD'),
      'options'  => [
          'database' => 'admin' // sets the authentication database required by mongo 3
      ]
    ]
  ]
];
