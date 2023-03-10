<?php

namespace Ncx\Controllers;
use Ncx\Controllers\BaseController;
use Psr\Container\ContainerInterface;
use Ncx\Http\Request;
use Slim\Http\Response;
use Ncx\Filesystem\FilesystemManager;
use Intervention\Image\ImageManager;
use Ncx\Models\Photo;


class PhotoStreamController extends BaseController
{
  private $filesystem;
  private $storagePath;
  private $config;
  private $config_cache;
  private $cache;
  /**
   * RegisterController constructor.
   *
   * @param \Interop\Container\ContainerInterface $container
   */
  public function __construct(ContainerInterface $container)
  {
      parent::__construct($container);
      $this->filesystem = new FilesystemManager($container);

      $disk = $this->filesystem->disk();
      $this->storagePath = $disk->getPath();
      $this->config = config('photo');
      $this->config_cache = config('cache');
      $this->cache = $container->get('cache');
  }

  //private function errorresponse()


  public function full(Request $request, Response $response, $args)
  {
    $validate = $this->validate($request,$args);
    if ($validate['response'])
      return $validate['response'];
    $response = $response
      ->withHeader('Content-type', $this->config['content_type_map'][$validate['meta']->ext])
      ->withHeader('cache-control', 'public, max-age='.$this->config_cache['ttl']['photohttp'].', immutable')
      ->withHeader('Expires', date("D, d M Y H:i:s", time()+$this->config_cache['ttl']['photohttp']).' GMT')
      ->withHeader('Last-Modified', 'Fri, 03 Mar 2004 06:32:31 GMT')
      ->withHeader('Accept-Ranges', 'bytes')
      ->withHeader('X-Generator', 'NCX Media Server (c) 2019')
    ;
    $body = $response->getBody();
    $fileFullPath = $this->storagePath.DS.$validate['meta']->pth;
    if(!is_file($fileFullPath))
      return $this->abort(500,'Resource not found on disk');

    $body->write(file_get_contents($fileFullPath));

    return $response;
  }

  public function thumb(Request $request, Response $response, $args)
  {
    $type = array_get($args,'type');
    if (!$type || ($type && !isset($this->config['image_sizes'][$type])))
      return $this->abort(404,'Invalid style type');

    $validate = $this->validate($request,$args);
    if ($validate['response'])
      return $validate['response'];

    $response = $response
      ->withHeader('Content-type', $this->config['content_type_map'][$validate['meta']->ext])
      ->withHeader('Cache-Control', 'public, max-age='.$this->config_cache['ttl']['photohttp'])
      ->withHeader('Expires', date("D, d M Y H:i:s", time()+$this->config_cache['ttl']['photohttp']).' GMT')
      ->withHeader('Last-Modified', 'Fri, 03 Mar 2004 06:32:31 GMT')
      ->withHeader('Accept-Ranges', 'bytes')
      ->withHeader('X-Generator', 'NCX Media Server (c) 2019')
    ;
    $body = $response->getBody();
    $fileFullPath = $this->storagePath.DS.$validate['meta']->pth;
    if(!is_file($fileFullPath))
      	return $this->abort(500,'Resource not found on disk');
    #Resize using Intervention Image
    $imageManager = new ImageManager(['driver' => $this->config['driver']]);

    $image = $imageManager->make($fileFullPath);
    if (isset($this->config['image_sizes'][$type]['fit']) && $this->config['image_sizes'][$type]['fit'])
      $image->fit($this->config['image_sizes'][$type]['w'], $this->config['image_sizes'][$type]['h'], function ($constraint) {$constraint->upsize();});
    else
      $image->resize($this->config['image_sizes'][$type]['w'], $this->config['image_sizes'][$type]['h'], function ($constraint) {$constraint->aspectRatio();});

    $body->write($image->response($validate['meta']->ext,$this->config['quality']));
    return $response;
  }

  /**
  * Looks up in mongo database by $id and returns data.
  * Cached
  * @return nullable array
  */
  private function fetchImageInfo($id)
  {
    $cache_key = 'photo:'.$id;
    $p = $this->cache->driver()->get($cache_key);
    if ($p)
      return $p;
    $p = Photo::select('pth','sze','ext')->find($id);
    $this->cache->driver()->put($cache_key,$p,$this->config_cache['ttl']['photometa']);
    return $p;
  }


  /**
  * Validates request and args
  * @return array [response,meta]
  **/
  private function validate(Request $request,$args) : array
  {
    $r = [
      'response' => null,
      'meta' => null
    ];
    if(!isset($args['id']) || !isset($args['ext']))
    {
      $r['response'] = $this->abort(404,'Resource not found (code:1)');
      return $r;
    }


    $id = $args['id'];
    $ext = $args['ext'];

    if (empty($id) || empty($ext))
    {
      $r['response'] = $this->abort(404,'Resource not found (code:2)');
      return $r;
    }

    #get image info from mongo database
    $imageMeta = $this->fetchImageInfo($id);
    if (!$imageMeta)
    {
      $r['response'] =  $this->abort(404,'Resource not found (code:3)');
      return $r;
    }

    if (!isset($imageMeta->pth))
    {
      $r['response'] =  $this->abort(404,'Resource metadata invalid');
      return $r;
    }

    $ext = strtolower($imageMeta->ext);
    $contentTypes = $this->config['content_type_map'];
    if(!isset($contentTypes[$ext]))
    {
      $r['response'] = $this->abort(404,'Resource content type is blocked');
      return $r;
    }

    $r['meta'] = $imageMeta;
    return $r;

  }

}
