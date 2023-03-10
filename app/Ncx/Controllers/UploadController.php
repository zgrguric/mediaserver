<?php

namespace Ncx\Controllers;

use Ncx\Controllers\BaseController;
use Psr\Container\ContainerInterface;
use Ncx\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Capsule\Manager as DB;
use Respect\Validation\Validator as v;
use Ncx\Filesystem\FilesystemManager;
use Ncx\Models\Photo;
use Ncx\Models\Video;
use Intervention\Image\ImageManager;

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;


//https://github.com/keithweaver/python-aws-s3

class UploadController extends BaseController
{
    /** @var \Illuminate\Database\Capsule\Manager */
    #protected $db;
    /** @var \League\Fractal\Manager */
    #protected $fractal;
    private $filesystem;
    private $config_upload;
    private $config_video;
    private $config_photo;

    /**
     * RegisterController constructor.
     *
     * @param \Interop\Container\ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->cache = $container->get('cache');
        $this->filesystem = new FilesystemManager($container);
        $this->config_upload = config('upload');
        $this->config_video = config('video');
        $this->config_photo = config('photo');
    }

    /**
    * Upload file.
    */
    public function upload(Request $request, Response $response, $args)
    {
      //  return $this->abort(500,'Invalid request',$request);
      //  return $this->abort(404,'Requested object not found',$request);

      $identifier = array_get($args,'identifier');
      if(!$identifier)
        return $this->abort(415,'Invalid parameters (code:1)',$request);


      $jwt_data = jwt_data($request);

      if (!isset($jwt_data['uid']))
        return $this->abort(500,'Invalid authorization data (code:1)',$request);
      if (!$jwt_data['uid'])
        return $this->abort(500,'Invalid authorization data (code:2)',$request);
      $uid = $jwt_data['uid'];

      //$inputs = $request->getParams();
      //dd($inputs);

      $uploadedFiles = $request->getUploadedFiles();
      if (!isset($uploadedFiles['file']))
        return $this->abort(500,'Request file not sent',$request);

      if ($uploadedFiles['file']->getError() !== UPLOAD_ERR_OK)
        return $this->abort(500,'Invalid upload',$request);

      $mediatype_unsecure = $uploadedFiles['file']->getClientMediaType();
      $ext = pathinfo($uploadedFiles['file']->getClientFilename(), PATHINFO_EXTENSION);
      $ext = strtolower($ext);

      //if file extension is not whitelisted throw 415 error
      if (!in_array($ext,$this->config_upload['allowed_photo_ext']) && !in_array($ext,$this->config_upload['allowed_video_ext']))
        return $this->abort(415,'Unsupported Media Type (code:1)',$request);

      if (in_array($ext,$this->config_upload['allowed_video_ext']))
        $methodname = 'handler_video';
      else
        $methodname = 'handler_photo';
      return $this->$methodname($request,$response,$uploadedFiles,$uid,$identifier);
    }


    /**
    * Takes list of uids (<uid>_<identifier>) and removes uid/identifier combo.
    * If its last in the list removed we will permanently delete file.
    * @param array $uids - uids column from database this is array
    * @param string $uid - user ID
    * @param string $identifier - this is identifier sent when creating file
    * @return array [
    *   'uids' => curated array - use this array to update if permanently_deletable is false.
    *   'deletable' => (boolean)If uid is found permission to delete is granted or denied.
    *   'permanently_deletable' => (boolean)Can delete from disk, otherwise just update.
    * ]
    */
    private function checkUidForDeletion(array $uids, $uid, $identifier)
    {
      $r = ['uids' => $uids,'deletable' => false, 'permanently_deletable' => false];

      foreach($uids as $k => $uidsdata)
      {
        if($uidsdata === $uid.'_'.$identifier)
        {
          unset($uids[$k]);
          $r['deletable'] = true;
          break;
        }
      }
      $r['uids'] = $uids;

      if($r['deletable'] && empty($r['uids']))
      {
        $r['permanently_deletable'] = true;
      }
      return $r;
    }

    public function delete(Request $request, Response $response, $args)
    {
      $identifier = array_get($args,'identifier');
      if(!$identifier)
        return $this->abort(415,'Invalid parameters (code:1)',$request);

      $jwt_data = jwt_data($request); //get and decode Authorization header jwt token
      if (!isset($jwt_data['uid']))
        return $this->abort(500,'Invalid authorization data (code:1)',$request);
      if (!$jwt_data['uid'])
        return $this->abort(500,'Invalid authorization data (code:2)',$request);
      $uid = $jwt_data['uid'];



      if(array_get($args,'id') && array_get($args,'mediatype'))
      {
        if ($args['mediatype'] == 'photo')
        {
          //load photo if found delete it
          $photo = Photo::where('_id',array_get($args,'id'))->first();
          if($photo)
          {
            if(!$photo->uids)
              return $response->withJson([
                'id' => array_get($args,'id'),
                'action' => 'delete',
                'executed' => false,
                'reason' => 'Identifiers (uids) not set'
                ],200);

            $check = $this->checkUidForDeletion($photo->uids,$uid,$identifier);

            if(!$check['deletable'])
              return $response->withJson([
                'id' => array_get($args,'id'),
                'action' => 'delete',
                'executed' => false,
                'reason' => 'Not owner'
                ],200);

            if($check['permanently_deletable'])
            {
              //delete from disk
              if($this->deletePhotoFromDisk($photo))
              {
                $photo->delete();
                return $response->withJson([
                  'id' => array_get($args,'id'),
                  'action' => 'delete',
                  'executed' => true,
                  'reason' => 'Is owner'
                  ],200);
              }
              else
              {
                return $response->withJson([
                  'id' => array_get($args,'id'),
                  'action' => 'delete',
                  'executed' => false,
                  'reason' => 'Error deleting from disk'
                  ],200);
              }
            }
            else
            {
              $photo->uids = $check['uids'];
              $photo->save();
              return $response->withJson([
                'id' => array_get($args,'id'),
                'action' => 'delete',
                'executed' => true,
                'reason' => 'Is owner'
                ],200);
            }
          }
          else
            return $this->abort(404,'Resource not found',$request);
        }
        elseif ($args['mediatype'] == 'video')
        {
          //load photo if found delete it
          $video = Video::where('_id',array_get($args,'id'))->first();
          if($video)
          {

            if(!$video->uids)
              return $response->withJson([
                'id' => array_get($args,'id'),
                'action' => 'delete',
                'executed' => false,
                'reason' => 'Identifiers (uids) not set'
                ],200);

            $check = $this->checkUidForDeletion($video->uids,$uid,$identifier);

            if(!$check['deletable'])
              return $response->withJson([
                'id' => array_get($args,'id'),
                'action' => 'delete',
                'executed' => false,
                'reason' => 'Not owner'
                ],200);


            if($check['permanently_deletable'])
            {
              //delete from disk
              if($this->deleteVideoFromDisk($video))
              {
                $video->delete();
                return $response->withJson([
                  'id' => array_get($args,'id'),
                  'action' => 'delete',
                  'executed' => true,
                  'reason' => 'Is owner'
                  ],200);
              }
              else
              {
                return $response->withJson([
                  'id' => array_get($args,'id'),
                  'action' => 'delete',
                  'executed' => false,
                  'reason' => 'Error deleting from disk'
                  ],200);
              }
            }
            else
            {
              $video->uids = $check['uids'];
              $video->save();
              return $response->withJson([
                'id' => array_get($args,'id'),
                'action' => 'delete',
                'executed' => true,
                'reason' => 'Is owner'
                ],200);
            }
          }
          else
            return $this->abort(404,'Resource not found',$request);
        }
      }
      return $this->abort(415,'Invalid parameters (code:2)',$request);
    }



    #HANDLERS
    /**
    * Upload object image (thumbnail)
    * @return nullable json
    */
    private function handler_photo(Request $request, Response $response, $uploadedFiles, $uid, $identifier)
    {
      //validate mimetype
      if (!$this->fileCheckMimeType($uploadedFiles,$this->config_upload['allowed_photo_mimetypes']))
        return $this->abort(415,'Unsupported Media Type (code:2)',$request);

      $name = date('His').uniqid();
      $path = date('Y').DS.date('m').DS.date('d');

      if ($uploadedFiles['file']->getError() === UPLOAD_ERR_OK)
      {
        //calculate image hash before resize to reject it early
        $hash = hash_file('md5',$uploadedFiles['file']->file);

        //use already stored hashed image.
        $existing = Photo::where('hsh',$hash)->first();
        if($existing)
        {
          $existing_count = $existing->cnt?$existing->cnt:1;
          $existing->cnt = $existing_count+1;
          $existing->uids = \is_array($existing->uids) ? \array_unique(\array_merge($existing->uids,[$uid.'_'.$identifier])):[$uid.'_'.$identifier];
        //  $existing->uids = $existing->uids ? array_merge($existing->uids,$uid):[$uid];
          $existing->save();
          $existing_pathinfo = \pathinfo($existing->pth);
          # send response
          $domain = config('app')['url'];
          return $response->withJson([
            'filename' => $existing_pathinfo['basename'],
            'ext' => $existing->ext,
            'id' => $existing->id,
            'size' => $existing->sze,
            'url' => $domain.'/photo/full/'.$existing->id.'.'.$existing->ext,
            'thumb_url' => $domain.'/photo/thumb/'.$existing->id.'.'.$existing->ext,
            'identifier' => $identifier
            ],200);
        }


        $ext = strtolower(pathinfo($uploadedFiles['file']->getClientFilename(), PATHINFO_EXTENSION));

        ##
        #Init Intervention Image
        $imageManager = new ImageManager(['driver' => $this->config_photo['driver']]);
        #Init Disk
        $disk = $this->filesystem->disk();
        $storagePath = $disk->getPath().DS.$path;
        $fullFilepath = $storagePath . DS . $name.'.'.$ext;
        $disk->createDirIfDoesNotExist($storagePath);

        #Get image from Stream
        $image = $imageManager
          ->make($uploadedFiles['file']->getStream());

        if ($image->width() < $this->config_photo['restrict_size_min'] || $image->height() < $this->config_photo['restrict_size_min'])
        {
          return $this->abort(406,'Image too small',$request);
        }

        if ($image->width() > $this->config_photo['resize']['w'])
        {
          $image = $image->resize($this->config_photo['resize']['w'], null,function ($constraint) {$constraint->aspectRatio();});
        }

        if ($image->height() > $this->config_photo['resize']['h'])
        {
          $image = $image->resize(null,$this->config_photo['resize']['h'],function ($constraint) {$constraint->aspectRatio();});
        }

        $image = $image->save($fullFilepath);

        $image_width = $image->width();
        $image_height = $image->height();
        $image_size = $image->filesize();
        unset($image); //free memory

        try {
          $f = $disk->backupFile($path,$name.'.'.$ext);
        } catch (\Throwable $e) {
          throw $e;
          return $this->abort(500,'Unable to backup file',$request);
        }

        ##

        # TODO-s: get width and height, validate mime type, do some resizing etc.

        # store to database
        $photo = new Photo;
        //$photo->uid = $uid;
        $photo->hsh = $hash;
        $photo->pth = $path.DS.$name.'.'.$ext;
        $photo->sze = $image_size;
        $photo->w = $image_width;
        $photo->h = $image_height;
        $photo->ext = $ext;
        $photo->c_at = new \MongoDB\BSON\UTCDateTime(new \DateTime('now'));
        $photo->cnt = 1;
        $photo->uids = [$uid.'_'.$identifier];
        $photo->save();

        # send response
        return $response->withJson([
          'filename' => $f['filename'],
          'ext' => $ext,
          'id' => $photo->id,
          'size' => $image_size,
          'url' => $f['config']['url'].'/photo/full/'.$photo->id.'.'.$ext,
          'thumb_url' => $f['config']['url'].'/photo/thumb/'.$photo->id.'.'.$ext,
          'identifier' => $identifier
          ],200);
      }
      return $this->abort(500,'Invalid upload',$request);
    }

    private function handler_video(Request $request, Response $response, $uploadedFiles, $uid, $identifier)
    {
      //validate mimetype
      if (!$this->fileCheckMimeType($uploadedFiles,$this->config_upload['allowed_video_mimetypes']))
        return $this->abort(415,'Unsupported Media Type (code:3)',$request);

      $name = date('His').uniqid();
      $path = date('Y').DS.date('m').DS.date('d');

      if ($uploadedFiles['file']->getError() === UPLOAD_ERR_OK)
      {
        //calculate image hash before resize to reject it early
        $hash = hash_file('md5',$uploadedFiles['file']->file);

        //Check hash and reject upload, use already stored hashed video.
        $existingVideo = Video::where('hsh',$hash)->first();
        if ($existingVideo)
        {
          $existing_count = $existingVideo->cnt?$existingVideo->cnt:1;
          $existingVideo->cnt = $existing_count+1;
          //$existingVideo->uids = \is_array($existingVideo->uids) ? \array_unique(\array_merge($existingVideo->uids,[$uid])):[$uid];
          $existingVideo->uids = \is_array($existingVideo->uids) ? \array_unique(\array_merge($existingVideo->uids,[$uid.'_'.$identifier])):[$uid.'_'.$identifier];
          $existingVideo->save();
          # send response for existing video
          $domain = config('app')['url'];
          return $response->withJson([
            'filename' => basename($existingVideo->pth),
            'id' => $existingVideo->id,
            //'path' => $f['path'],
            'url' => $domain.'/video/'.$existingVideo->id.'.'.$existingVideo->ext,
            'thumb_url' => $domain.'/video/'.$existingVideo->id.'/thumb.jpg',
            'identifier' => $identifier
            ],200);
        }
        //END check

        try {
          $f = $this->storeUploadedFile($path,$uploadedFiles['file'],$name);
        } catch (\Throwable $e) {
          throw $e;
          return $this->abort(500,'Unable to upload due to upload error (code:1)',$request);
        }

        $disk = $this->filesystem->disk();
        $storagePath = $disk->getPath().DS.$path;
        $fullFilepath = $storagePath . DS . $f['filename'];

        //Init FFPROBE
        $ffprobe = \FFMpeg\FFProbe::create(config('video')['ffmpeg']);
        $isValid = $ffprobe->isValid($fullFilepath);

        //validate video using ffprobe
        if (!$isValid)
          return $this->abort(500,'Unable to upload due to upload error (code:2)',$request);

        ## EXTRACTING DIMENSIONS
        $dimensions = $ffprobe->streams($fullFilepath) // extracts streams informations
            ->videos()                      // filters video streams
            ->first()->getDimensions();
        ## EXTRACTING DIMENSIONS END

        ## EXTRACTING DURATION (seconds)
        $duration = $ffprobe->format($fullFilepath)->get('duration');


        //$ffmpeg = FFMpeg::create(config('video')['ffmpeg']);
        //$videoInstance = $ffmpeg->open($fullFilepath);


        /*$video//->save(new \FFMpeg\Format\Video\X264(), 'export-x264.mp4')
              ->save(new \FFMpeg\Format\Video\WMV(), 'export-wmv.wmv')
              ->save(new \FFMpeg\Format\Video\WebM(), 'export-webm.webm');*/
        //$video->frame(TimeCode::fromSeconds(10))->save($storagePath.DS.'frame.jpg');

        ## EXTRACTING SCREENSHOT START

        //$video = $ffmpeg->open($fullFilepath);
        //$video->frame(TimeCode::fromSeconds(10))->save($storagePath.DS.'frame.jpg');
        ## EXTRACTING SCREENSHOT END

        /*$format = new \FFMpeg\Format\Video\X264();
        $format->on('progress', function ($video, $format, $percentage) {
            echo "$percentage % transcoded";
        });

        $format
            ->setKiloBitrate(1000)
            ->setAudioChannels(2)
            ->setAudioKiloBitrate(256);

        $videoInstance->save($format, 'video.avi');*/

        ##
        # store to database
        $video = new Video;
        //$video->uid = $uid;
        $video->hsh = $hash;
        $video->pth = $path.DS.$f['filename'];
        $video->sze = $f['data']->getSize();
        $video->w = $dimensions->getWidth();
        $video->h = $dimensions->getHeight();
        $video->dur = round($duration,2);
        $video->ext = $f['extension'];
        $video->c_at = new \MongoDB\BSON\UTCDateTime(new \DateTime('now'));
        $video->cnt = 1;
        $video->uids = [$uid.'_'.$identifier];
        $video->save();

        # send response
        return $response->withJson([
          'filename' => $f['filename'],
          'id' => $video->id,
          //'path' => $f['path'],
          'url' => $f['config']['url'].'/video/'.$video->id.'.'.$f['extension'],
          'thumb_url' => $f['config']['url'].'/video/'.$video->id.'/thumb.jpg',
          'identifier' => $identifier
          ],200);
      }
      return $this->abort(500,'Invalid upload',$request);
    }


    /**
    * Backups already existing file to all disk backup locations.
    */
    private function backupFile($diskPath,$filename)
    {
      $disk = $this->filesystem->disk();
    }

    /**
     * Moves the uploaded file to the upload directory and assigns it a unique name
     * to avoid overwriting an existing uploaded file.
     *
     * @param string $directory directory to which the file is moved
     * @param string $path without first and last trailing slash
     * @param string $filename without extenstion
     * @param UploadedFile $uploaded file uploaded file to move
     * @return array FilesystemManager response data or exception
     */
    private function storeUploadedFile($path,\Slim\Http\UploadedFile $uploadedFile,$rawfilename = null)
    {
      $disk = $this->filesystem->disk();
      $extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
      $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
      if (!$rawfilename)
        $filename = sprintf('%s.%0.8s', $basename, $extension);
      else
        $filename = $rawfilename.'.'.$extension;


      //this will override any file with same path/filename
      $storedFileData = $disk->putFileAs($path,$uploadedFile,$filename);
      //dd($storedFileData,$path,$uploadedFile,$filename);
      $storedFileData['extension'] = $extension;
      if ($storedFileData['success'])
        return $storedFileData;
      else
        throw new \Exception($storedFileData['errormsg']);
    }

    /**
    * Checks if mime type is in allowed mime types.
    * @return bool
    */
    private function fileCheckMimeType($uploadedFiles,array $allowedMimeTypes) : bool
    {
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      if (false === array_search($finfo->file($uploadedFiles['file']->file),$allowedMimeTypes,true))
        return false;
      return true;
    }

    /**
    * Deletes files from disk only
    * @return boolean
    */
    private function deletePhotoFromDisk(Photo $photo)
    {
      $disk = $this->filesystem->disk();
      $path = \pathinfo($photo->pth);
      return $disk->delete($path['dirname'],$path['basename']);
    }

    /**
    * Deletes files from disk only
    * @return boolean
    */
    private function deleteVideoFromDisk(Video $video)
    {
      $disk = $this->filesystem->disk();
      $path = \pathinfo($video->pth);

      $del = $disk->delete($path['dirname'],$path['filename'].'.webm');
      if($del)
        $del = $disk->delete($path['dirname'],$path['filename'].'.mp4');
      if($del)
        $del = $disk->delete($path['dirname'],$path['basename']);
      return $del;
    }
}
