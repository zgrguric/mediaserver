<?php
namespace Ncx\Controllers;
use Psr\Container\ContainerInterface;
use Ncx\Validation\Validator;



class BaseController
{
    /**
     * @var  Psr\Container\ContainerInterface
     */
    protected $container;
    protected $router;

    /**
     * BaseController constructor.
     *
     * @param Psr\Container\ContainerInterface; $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->router = $container->router;
    }


    protected function abort($code,$message,$request = null,$forcexhr = false)
    {
      $isxhr = false;
      if (!$request)
        $isxhr = false;
      elseif ($request->isXhr())
        $isxhr = true;

      $response = new \Slim\Http\Response;
      $response = $response->withStatus($code);

      if ($forcexhr)
        $isxhr = true;

      if ($isxhr)
      {
        $responseParams = ['error' => $code];
        if ($message)
          $responseParams['msg'] = $message;
        return $response->withJson($responseParams,$code);
      }
      $body = $response->getBody();
      $body->write($code.' - '.$message);
      return $response->withBody($body)->withHeader('Content-type', 'text/html; charset=UTF-8');
    }
}
