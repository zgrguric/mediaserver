<?php
declare(strict_types=1);
//http://lzakrzewski.com/2016/02/integration-testing-with-slim/
use PHPUnit\Framework\TestCase;
use Ncx\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;
use Slim\Http\UploadedFile;
use Ncx\Controllers\PhotoStreamController;

class PhotoAccessibilityTest extends TestCase
{
    public function testUnknownPhotoReturns404NotFound(): void
    {
        global $container;
        $photoStreamController = new PhotoStreamController($container);
        $environment = Environment::mock([
              'REQUEST_METHOD'  => 'GET',
              'REQUEST_URI'     => '/photo/original/someunknownid.jpg',
              'QUERY_STRING'    => ''
            ]
          );
        $request = Request::createFromEnvironment($environment);
        $response = $photoStreamController->full($request,new \Slim\Http\Response(),['ext' => 'jpg','id' => 'someunknownid']);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
