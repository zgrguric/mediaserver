<?php
declare(strict_types=1);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// Define root path
defined('DS') ?: define('DS', DIRECTORY_SEPARATOR);
defined('ROOT') ?: define('ROOT', dirname(__DIR__) . DS);

require ROOT.'vendor'.DS.'autoload.php';

//Init the settings
$configFile = ROOT.'bootstrap'.DS.'cache'.DS.'config.php';
if (is_file($configFile)) {
  $settings = require $configFile;
  unset($configFile);
}
else {
  // Load .env file
  if (file_exists(ROOT . '.env')) {
      $dotenv = new Dotenv\Dotenv(ROOT);
      $dotenv->load();
  }
  else
    die('MediaServer is not installed, .env file is missing');

  $settings = [];
  $_dir = scandir(ROOT . 'config/');
  foreach($_dir as $_f)
  {
    if (ends_with($_f,'.php'))
    {
      $_f_arr = require ROOT . 'config/'.$_f;
      $settings = array_merge_recursive($settings,[basename($_f,'.php') => $_f_arr]);
      unset($_f_arr);
      unset($_f);
    }
  }
  unset($_f_arr);
  unset($_f);
  unset($_dir);
}

date_default_timezone_set($settings['app']['timezone']);
$app = new \Slim\App(['settings' => $settings]);

// Set up dependencies
require ROOT . 'app/dependencies.php';
// Register middleware
require ROOT . 'app/middleware.php';
// Register routes
require ROOT . 'app/routes.php';
