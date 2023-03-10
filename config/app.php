<?php
return [

  //display errors and dev inspector
  'displayerrors' => (getenv('APP_DEBUG') === 'true' ? true : false),


  'name' => getenv('APP_NAME'),
  'url'  => getenv('APP_URL'),
  'cdn_url' => getenv('CDN_URL'),
  'domain'  => getenv('APP_DOMAIN'),
  'env'  => getenv('APP_ENV'),
  'version' => '1.0.2',

  'lang' => 'en', //default language
  'timezone' => 'Europe/Zagreb',

  'datetimeformats' => [
    'short_date' => 'd. m. Y.'
  ],
  //nginx or httpd
  //determines video stream type, use nginx for production
  'webserver' => getenv('APP_WEBSERVER'),

];
