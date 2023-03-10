<?php
// DIC configuration
/** @var Pimple\Container $container */
#use Ncx\Middleware\OptionalAuth;
#use League\Fractal\Manager;
#use League\Fractal\Serializer\ArraySerializer;


//laravel translator
/*
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
*/

/*if ($settings['app']['env'] == 'local') //only on DEV!
{}*/

$container = $app->getContainer();
// Error Handler
$container['errorHandler'] = function ($c) {
    return new \Ncx\Exceptions\ErrorHandler($c['settings']['app']['displayerrors']);
};

if($settings['app']['displayerrors'])
{
  $container['phpErrorHandler'] = function ($c) {
      return function ($request, $response, $error) use ($c) {
          return $response->withStatus(500)
              ->withHeader('Content-Type', 'text/html')
              ->write('PHP error (debug mode is enabled) <pre>'.$error.'</pre>');
      };
  };
}

$container['request'] = function ($container) {
    //replace standard Request with our implementation
    return  \Ncx\Http\Request::createFromEnvironment($container['environment']);
};
// App Service Providers
$container->register(new \Ncx\Services\Database\EloquentServiceProvider());
//$container->register(new \Ncx\Services\Database\MongodbServiceProvider());
//$container->register(Jenssegers\Mongodb\MongodbServiceProvider::class);
#$container->register(new \Ncx\Services\Object\ObjectLoaderServiceProvider());
$container->register(new \Ncx\Services\Redis\RedisServiceProvider());
$container->register(new \Ncx\Services\Cache\CacheServiceProvider());
#$container->register(new \Ncx\Services\User\UserLoaderServiceProvider());
#$container->register(new \Ncx\Services\Auth\AuthServiceProvider());
#$container->register(new \Ncx\Services\Translator\TranslatorServiceProvider());


//  view renderer
/*$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    //dd($settings);
    return new Slim\Views\PhpRenderer($settings['blade_template_path']);
};*/

// Register Blade View helper
/*$container['view'] = function ($c) {
    $s = $c->get('settings')['renderer'];
    return new \Slim\Views\Blade(
        $s['blade_template_path'],
        $s['blade_cache_path']
    );
};*/

// monolog TODO
$container['logger'] = function ($c) {
    dd('see dependecies.php logger',$c);
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};
// Jwt Middleware
/*$container['jwt'] = function ($c) {
    $jwt_settings = $c->get('settings')['jwt'];
    $jwt_settings['error'] = function ($response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    };
    return new Tuupola\Middleware\JwtAuthentication($jwt_settings);
};*/

/*$container['optionalAuth'] = function ($c) {
  return new OptionalAuth($c);
};*/
// Request Validator
/*$container['validator'] = function ($c) {
    \Respect\Validation\Validator::with('\\Ncx\\Validation\\Rules');
    return new \Ncx\Validation\Validator();
};*/

// Fractal
/*$container['fractal'] = function ($c) {
    $manager = new Manager();
    $manager->setSerializer(new ArraySerializer());
    return $manager;
};*/
/*
$container['flash'] = function ($c) {
    return new \Ncx\Validation\NcxFlash();
};
*/
