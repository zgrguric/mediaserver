<?php

namespace Ncx\Controllers;
use Ncx\Controllers\BaseController;
use Psr\Container\ContainerInterface;
use Ncx\Http\Request;
use Slim\Http\Response;


class NcxController extends BaseController
{
  /**
   * RegisterController constructor.
   *
   * @param \Interop\Container\ContainerInterface $container
   */
  public function __construct(ContainerInterface $container)
  {
      parent::__construct($container);

  }

  public function index(Request $request, Response $response, $args)
  {
    echo 'index';
  }

}
