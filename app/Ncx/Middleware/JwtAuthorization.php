<?php
namespace Ncx\Middleware;
use Illuminate\Database\Capsule\Manager as DB;
use Firebase\JWT\JWT;

class JwtAuthorization
{

    private $container;

    public function __construct($c)
    {
        $this->container = $c;
    }

    /**
     * Check if logged in cookie is set, if not redirect to frontpage.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
      if ($request->hasHeader('Authorization'))
      {
          $sent_jwt_token = $request->getHeaderLine('Authorization');

          if (empty($sent_jwt_token))
            return $response->withStatus(401)->withJson(['error' => 'unauthorized','message' => 'Token is empty']);
          $settings = config('jwt');
          try{

            $decoded = JWT::decode($sent_jwt_token, $settings['secret'], array('HS256'));
          } catch (\Exception $e) {
            return $response->withStatus(401)->withJson(['error' => 'unauthorized','message' => $e->getMessage()]);
            //return $response->withStatus(401)->withJson(['error' => 'unauthorized','message' => 'Invalid signature']);
          }
      }
      return $next($request, $response);
    }

}
