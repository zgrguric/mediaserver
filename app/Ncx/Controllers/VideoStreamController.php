<?php
namespace Ncx\Controllers;
use Ncx\Controllers\BaseController;
use Psr\Container\ContainerInterface;
use Ncx\Http\Request;
use Slim\Http\Response;
use Ncx\Filesystem\FilesystemManager;
use Intervention\Image\ImageManager;
use Ncx\Models\Video;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

class VideoStreamController extends BaseController
{
  private $filesystem;
  private $storagePath;
  private $config;
  private $config_app;
  private $config_cache;
  private $config_photo;
  private $requestedExtension;
  private $cache;

  const VIDEO_NOT_FOUND = null;
  const VIDEO_FILE_NOT_FOUND = 4;
  const VIDEO_NOT_PROCESSED = 5;
  const VIDEO_PLAYABLE = 1;
  const VIDEO_CONVERTING = 2;
  const VIDEO_CONVERSION_QUEUED = 3;

  /**
   * RegisterController constructor.
   *
   * @param \Interop\Container\ContainerInterface $container
   */
  public function __construct(ContainerInterface $container)
  {
      parent::__construct($container);

      $this->filesystem = new FilesystemManager($container);
      $this->storagePath = $this->filesystem->disk()->getPath();
      $this->config_app = config('app');
      $this->config = config('video');
      $this->config_photo = config('photo');
      $this->config_cache = config('cache');
      $this->cache = $container->get('cache');
  }

  /**
  * Checks video and conversion status for requested extension, returns results.
  * - id
  * - internal_path
  * - original_ext
  * - requested_ext
  * -
  * @return Response json
  */
  private function _getInfo($meta,Response $response)
  {
      $video = $this->getVideo($meta,false);
      $data = [
          'id' => $meta->_id,
          'internal_path' => $meta->pth,
          'original_ext' => $meta->ext,
          'requested_ext' => $this->requestedExtension,
          'status' => $video['status'],
          'processing' => [
            'status' => 'unknown',
            'progress' => 0,
            'duration' => null,
            'currtime' => null,
          ]
        ];
      if($video['status'] === self::VIDEO_CONVERTING)
      {
        $conversionInfo = $this->fetchVideoConversionProgressFromLog($meta,$this->requestedExtension);
        $data['processing'] = [
            'status' => 'processing',
            'progress' => $conversionInfo['progress'],
            'duration' => $conversionInfo['duration'],
            'currtime' => $conversionInfo['currtime']
        ];
      }
      elseif($video['status'] === self::VIDEO_CONVERSION_QUEUED)
        $data['processing']['status'] = 'queued';
      elseif($video['status'] === self::VIDEO_PLAYABLE)
      {
        $data['processing']['status'] = 'processed';
        $data['processing']['progress'] = 100;
      }
      elseif($video['status'] === self::VIDEO_NOT_PROCESSED)
        $data['processing']['status'] = 'notprocessed';

      if ($data['processing']['status'] == 'processing')
      {
        //nocache revalidate
        //purge varnish request is sent from conversion job
		$response = $response
	   // ->withHeader('Accept-Ranges', 'bytes')
		->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
		->withHeader('Pragma', 'no-cache')
		->withHeader('Expires', '0')
		->withHeader('Last-Modified', 'Fri, 03 Mar 2004 06:32:31 GMT')
		->withHeader('X-Generator', 'Media Server (c) 2019')
		->withHeader('Keep-Alive', 'timeout=5, max=100')
	  ;
      }
	  else
	  {
		$response = $response
	   // ->withHeader('Accept-Ranges', 'bytes')
		->withHeader('Cache-Control', 'public, max-age=30')
		->withHeader('Expires', date("D, d M Y H:i:s", time()+30).' GMT')
		->withHeader('Last-Modified', 'Fri, 03 Mar 2004 06:32:31 GMT')
		->withHeader('X-Generator', 'Media Server (c) 2019')
		->withHeader('Keep-Alive', 'timeout=5, max=100')
	  ;

	  }


      return $response->withJson($data);
  }

  /**
  * This should stream a video.
  */
  public function info(Request $request, Response $response, $args)
  {
    $args['ext'] = strtolower($args['ext']);
    $this->requestedExtension = $args['ext'];
    $validate = $this->validate($request,$args);

    if ($validate['response'])
      return $validate['response'];

    return $this->_getInfo($validate['meta'],$response);
  }

  /**
  * Stream a video.
  * @return Response
  */
  public function stream(Request $request, Response $response, $args)
  {
    $args['ext'] = strtolower($args['ext']);
    $this->requestedExtension = $args['ext'];
    $validate = $this->validate($request,$args);

    if ($validate['response'])
      return $validate['response'];
    $meta = $validate['meta'];

    $video = $this->getVideo($meta);
    if($video['status'] === self::VIDEO_NOT_FOUND)
      return $this->abort(404,'Resource not found (code:1)');
    elseif($video['status'] === self::VIDEO_FILE_NOT_FOUND)
      return $this->abort(404,'Resource file not found');
    elseif($video['status'] === self::VIDEO_CONVERSION_QUEUED)
      return $this->abort(404,'Video conversion queued');
    elseif($video['status'] === self::VIDEO_CONVERTING)
      return $this->abort(404,'Video is converting use /api/... to get more info');

    $fullFilepath = $this->storagePath.DS.$meta->pth;

    if ($this->requestedExtension == 'webm'){
        $content_type = 'video/webm';
    }
    else
    {
        $content_type = 'video/mp4';
    }

    $response = $response
     // ->withHeader('Accept-Ranges', 'bytes')
      ->withHeader('Content-type', $content_type)
      ->withHeader('Cache-Control', 'public, max-age='.$this->config_cache['ttl']['videohttp'])
      ->withHeader('Expires', date("D, d M Y H:i:s", time()+$this->config_cache['ttl']['videohttp']).' GMT')
      ->withHeader('Last-Modified', 'Fri, 03 Mar 2004 06:32:31 GMT')

      ->withHeader('X-Generator', 'Media Server (c) 2019')
      ->withHeader('Content-Length', (string)(filesize($fullFilepath)))
      ->withHeader('Content-Disposition', 'inline; filename='.$meta->id.'.'.$this->requestedExtension)
      ->withHeader('Content-Transfer-Encoding', 'binary')
      ->withHeader('Keep-Alive', 'timeout=5, max=100')
      ->withHeader('X-Accel-Redirect','/'.$this->config['nginx_media_dirname'].'/'.$meta->pth)
    ;
    if ($this->config_app['webserver'] == 'nginx')
      return $response;

    $stream = new \Ncx\Http\VideoStream($fullFilepath,$this->requestedExtension);
    $stream->start();//this exists process with 'exit;'

  }

  /**
  * Returns conversion status from database meta data, returns correct field for requested extension.
  * @return int
  */
  private function _getVideoFormatConversionStatus($meta)
  {
    if ($this->requestedExtension == 'mp4')
      return $meta->cs1;
    else
      return $meta->cs2;
  }

  /**
  * Locally sets variable to meta object.
  * @return META
  */
  private function _setVideoFormatConversionStatus($meta,int $status)
  {
    if ($this->requestedExtension == 'mp4')
       $meta->cs1 = $status;
    else
       $meta->cs2 = $status;
    return $meta;
  }

  /**
  *
  * @return array ['status', 'model']
  */
  private function getVideo($meta,$writeToQueue = true) : array
  {
    if ($this->requestedExtension === null)
        throw new \Exception('requestedExtension is not set prior calling getVideo');

    $r = [
      'status' => self::VIDEO_NOT_FOUND,
      'model' => null
    ];

    //check if reference file exist on disk
    if (!is_file($this->storagePath.DS.$meta->pth))
    {
      $r['status'] = self::VIDEO_FILE_NOT_FOUND;
      return $r;
    }
    //requested extension is invalid, return NOT FOUND
    if($this->requestedExtension != 'webm' && $this->requestedExtension != 'mp4')
      return $r;

    //request is mp4, we got mp4 serve it, same goes for webm
    if ($meta->ext == $this->requestedExtension)
    {
      $r['status'] = self::VIDEO_PLAYABLE;
      $r['model'] = $meta;
      return $r;
    }
    //request is webm or mp4 and we have other type, start conversion
    if($this->requestedExtension == 'webm' || $this->requestedExtension == 'mp4')
    {
      //check if we have .webm or .mp4 converted file
      $pth = substr($meta->pth, 0, strrpos($meta->pth, ".")).'.'.$this->requestedExtension;

      if(is_file($this->storagePath.DS.$pth))
      {
        //file exists on disk and meta status is 2 (completed) can play
        if ($this->_getVideoFormatConversionStatus($meta) == 2)
        {
          $meta->pth = $pth;
          $r['status'] = self::VIDEO_PLAYABLE;
          $r['model'] = $meta;
          return $r;
        }
        else
        {
          $r['status'] = self::VIDEO_CONVERTING;
          $r['model'] = $meta;
          return $r;
        }
      }
      else //file does not exist
      {

        if ($this->_getVideoFormatConversionStatus($meta) == 1)
        {
          $r['status'] = self::VIDEO_CONVERSION_QUEUED; //queued but not started because file does not exists on disk yet
          $r['model'] = $meta;
          return $r;
        }

        if ($writeToQueue)
        {
            $selectedQueue = $this->config['queue'];
            if (!empty($meta->queue))
              $selectedQueue = $meta->queue;
            $meta = $this->_setVideoFormatConversionStatus($meta,1); //(converting (queued))
            $meta->queue = $selectedQueue;
            //conversion needed
            $job = new \Ncx\Jobs\VideoConversionJob((string)$meta->_id,$this->storagePath,$this->requestedExtension);
            $job->dispatch($selectedQueue);
            $meta->save(); //save status:1
            $this->clearCache($meta->id);
            $r['status'] = self::VIDEO_CONVERSION_QUEUED;
        }
        else
            $r['status'] = self::VIDEO_NOT_PROCESSED;

        $r['model'] = $meta;
        return $r;
      }
    }
    return $r; //incompatible format
  }

  /**
  * @return Response image or null
  **/
  public function thumb(Request $request, Response $response, $args)
  {
    $args['ext'] = 'jpg';
    $this->requestedExtension = $args['ext'];
    $validate = $this->validate($request,$args);
    if ($validate['response'])
      return $validate['response'];

    $meta = $validate['meta'];
    $dur_half = round($meta->dur/2);
    if ($meta->dur < $dur_half)
      $dur_half = $meta->dur;

    $ffmpeg = FFMpeg::create($this->config['ffmpeg']);
    $video = $ffmpeg->open($this->storagePath.DS.$meta->pth);
    $base64frame = $video->frame(TimeCode::fromSeconds($dur_half))->save(null, false, true);

    $imageManager = new ImageManager(['driver' => $this->config_photo['driver']]);
    $image = $imageManager->make($base64frame)->resize($this->config_photo['thumb_size_width'], null,function ($constraint) {$constraint->aspectRatio();});

    $response = $response
      ->withHeader('Content-type', 'image/jpeg')
      ->withHeader('Cache-Control', 'public, max-age='.$this->config_cache['ttl']['photohttp'])
      ->withHeader('Expires', date("D, d M Y H:i:s", time()+$this->config_cache['ttl']['photohttp']).' GMT')
      ->withHeader('Last-Modified', 'Fri, 03 Mar 2004 06:32:31 GMT')
      ->withHeader('Accept-Ranges', 'bytes')
      ->withHeader('X-Generator', 'Media Server (c) 2019')
    ;
    $body = $response->getBody();
    $body->write($image->response('jpg',$this->config['thumb_quality']));
    return $response;
  }

  /**
  * Looks up in mongo database by $id and returns data.
  * Without cache: 3.69MB, with cache 5.2MB because DB connection initalization
  * @return nullable array
  */
  private function fetchVideoInfo($id)
  {
    $cache_key = 'video:'.$id;
    $p = $this->cache->driver()->get($cache_key);
    if ($p) //3,18 MB dd(format_bytes(memory_get_usage()));
      return $p;
    $p = Video::select('pth','ext','cs1','cs2','queue')->find($id);
    $this->cache->driver()->put($cache_key,$p,$this->config_cache['ttl']['videometa']);
    return $p;//4,33 MB
  }

  private function clearCache($id)
  {
    $this->cache->driver()->forget('video:'.$id); //clear cache
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
    $ext = strtolower($args['ext']);
    if (empty($id) || empty($ext))
    {
      $r['response'] = $this->abort(404,'Resource not found (code:2)');
      return $r;
    }

    #get image info from mongo database
    $meta = $this->fetchVideoInfo($id);

    if (!$meta)
    {
      $r['response'] =  $this->abort(404,'Resource not found (code:3)');
      return $r;
    }

    if (!isset($meta->pth))
    {
      $r['response'] =  $this->abort(404,'Resource metadata invalid');
      return $r;
    }

    /*$ext2 = strtolower($meta->ext);
    $contentTypes = $this->config['content_type_map'];
    if(!isset($contentTypes[$ext2]))
    {
      $r['response'] = $this->abort(404,'Resource content type is blocked');
      return $r;
    }*/

    $r['meta'] = $meta;
    return $r;
  }

  /**
  * - progress float (rounded to max 3 decimals)
  * - duration nullable float
  * - currtime nullable float
  * @return array ['progress','duration','currtime']
  */
  private function fetchVideoConversionProgressFromLog($video,$ext) : array
  {
    $r = ['progress' => 0,'duration' => null,'currtime' => null];

    $pathWithoutExt = substr($video->pth, 0, strrpos($video->pth, "."));
    $logPath = $this->storagePath.DS.$pathWithoutExt.'_'.$ext.'.txt';
    if(!is_file($logPath))
        return $r;

    $content = file_get_contents($logPath);
    # get duration of source
    preg_match("/Duration: (.*?), start:/", $content, $matches);
    $rawDuration = $matches[1];
    # rawDuration is in 00:00:00.00 format. This converts it to seconds.
    $ar = array_reverse(explode(":", $rawDuration));

    $duration = floatval($ar[0]);
    if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
    if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;
    $r['duration'] = $duration;

    # get the current time
    preg_match_all("/time=(.*?) bitrate/", $content, $matches);

     $last = array_pop($matches);
    # this is needed if there is more than one match
    if (is_array($last)) {
        $last = array_pop($last);
    }

    $curTimear = array_reverse(explode(":", $last));
    $curTime = floatval($curTimear[0]);

    if (!empty($curTimear[1])) $curTime += intval($curTimear[1]) * 60;
    if (!empty($curTimear[2])) $curTime += intval($curTimear[2]) * 60 * 60;
    $r['currtime'] = $curTime;
    $r['progress'] = round((($curTime/$duration)*100),3);
    return $r;
  }
}
