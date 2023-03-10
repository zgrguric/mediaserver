<?php
declare(strict_types=1);
//http://lzakrzewski.com/2016/02/integration-testing-with-slim/
use PHPUnit\Framework\TestCase;
use Ncx\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;
use Slim\Http\UploadedFile;
use Ncx\Controllers\VideoStreamController;

class VideoAccessibilityTest extends TestCase
{
    public function testUnknownVideoReturns404NotFound(): void
    {
        global $container;
        $videoStreamController = new VideoStreamController($container);
        $environment = Environment::mock([
              'REQUEST_METHOD'  => 'GET',
              'REQUEST_URI'     => '/video/someunknownid.mp4',
              'QUERY_STRING'    => ''
            ]
          );
        $request = Request::createFromEnvironment($environment);
        $response = $videoStreamController->stream($request,new \Slim\Http\Response(),['ext' => 'mp4','id' => 'someunknownid']);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
