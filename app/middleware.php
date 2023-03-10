<?php
// Application middleware
// e.g: $app->add(new \Slim\Csrf\Guard);
use Slim\Http\Request;
use Slim\Http\Response;


/************************************
 * Register MIDDLEWARE to $container
 *************************************/



// Sets redis session handler
/*$container['sessionMiddleware'] = function ($c) {
  return new \Ncx\Middleware\SessionRedis($c->get('settings'), $c->get('redis'));
};*/

// Check if user is logged in, if not redirect to frontpage


/************************************
 * Add MIDDLEWARE to $app
 *************************************/

# error handling
//$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware);
$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware($app));



$app->add(function (Request $request, Response $response, callable $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));
        if($request->getMethod() == 'GET') {
            return $response->withRedirect((string)$uri, 301);
        }
        else {
            return $next($request->withUri($uri), $response);
        }
    }
    return $next($request, $response);
});

$app->add(function (Request $req, Response $res, callable $next) {
    $response = $next($req, $res);
  //  dd($this->get('settings')['cors']);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $this->get('settings')['cors'])
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Cache-Control')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$container['jwtMiddleware'] = function ($c) {
  return new \Ncx\Middleware\JwtAuthorization($c);
};
$jwtAuthorized = $app->getContainer()->get('jwtMiddleware');

//$app->add($app->getContainer()->get('jwt'));
//add sessionMiddleware to ALL requests
//inits session ONLY if correct cookie is set, so it is safe to enable on ALL requests
//$app->add($app->getContainer()->get('sessionMiddleware'));

//test:
//$app->add(new \Slim\Csrf\Guard);
