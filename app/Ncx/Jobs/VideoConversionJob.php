<?php
namespace Ncx\Jobs;
use Ncx\Queue\BaseJob;
use Ncx\Models\Video;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use Ncx\Filesystem\FilesystemManager;
use Symfony\Component\Process\Process;

class VideoConversionJob extends BaseJob
{
  protected $toFormat;
  protected $videoID;
  protected $storagePath; //storage path to disk
  public $tries = 3;
  public $timeout = 300; //300s (10min)
  protected $meta;

  public function __construct($videoID,$storagePath,$toFormat)
  {
    $this->videoID = $videoID;
    $this->toFormat = $toFormat; //mp4 or webm
    $this->storagePath = $storagePath;
  }

  private function _getFreshVideoMeta()
  {
    $this->meta = Video::select('pth','cs1','cs2')->find($this->videoID);
    return $this->meta;
  }

  private function _getVideoFormatConversionStatus()
  {
    if ($this->toFormat == 'mp4')
      return $this->meta->cs1;
    else
      return $this->meta->cs2;
  }

  private function _setVideoFormatConversionStatus(int $status)
  {
    if ($this->toFormat == 'mp4')
      $this->meta->cs1 = $status;
    else
      $this->meta->cs2 = $status;
  }


  public function handle()
  {
    $this->_getFreshVideoMeta();
    if(!$this->meta)
      return;



    if (!is_file($this->storagePath.DS.$this->meta->pth))
      throw new \Exception('Reference file not found on disk');

    $pathWithoutExt = substr($this->meta->pth, 0, strrpos($this->meta->pth, "."));
	  $logFile = $this->storagePath.DS.$pathWithoutExt.'_'.$this->toFormat.'.txt';

    //file exists on disk, iether conversion is undegoing and ffmpeg is filling the file or conversion completed long time ago
    //stop this job
    if (is_file($this->storagePath.DS.$pathWithoutExt.'.'.$this->toFormat))
    {
      if($this->_getVideoFormatConversionStatus() == 1)
      {
        //it is currently converting but job is queued somehow, fix database meta to status:2 (completed)
        $this->_setVideoFormatConversionStatus(2);
        $this->_saveMeta();
        return;
      }
      elseif($this->_getVideoFormatConversionStatus() == 0) //failed conversion
      {
        //conversion has perviously failed, but file exists on disk, delete it
        @unlink($this->storagePath.DS.$pathWithoutExt.'.'.$this->toFormat);
        @unlink($this->storagePath.DS.$pathWithoutExt.'.'.$this->toFormat.'.txt');
      }
    }

    //let it be known converting is started
    $this->_setVideoFormatConversionStatus(1);
    //todo clear cache
    $this->_saveMeta();

    //send purge varnish request to API info (/api/video.$this->toFormat/info)
	$purgeApiUrl = config('app')['url'].'/api/video/'.$this->meta['id'].'.'.$this->toFormat.'/info';
    $this->_varnishXPurge($purgeApiUrl,false);

  	if ($this->toFormat == 'webm')
  	{
      //https://codelabs.developers.google.com/codelabs/vp9-video/index.html?index=..%2F..index#5
  		$cmd = config('video')['ffmpeg']['ffmpeg.binaries'].' -i '.$this->storagePath.DS.$this->meta->pth.
  		' -b:v 750k -quality good -speed 4 -crf 33 -c:v libvpx-vp9 -c:a libopus '.
  		$this->storagePath.DS.$pathWithoutExt.'.'.$this->toFormat.' 1> '.$logFile.' 2>&1';
  	}
  	elseif($this->toFormat == 'mp4')
  	{
  		$cmd = config('video')['ffmpeg']['ffmpeg.binaries'].' -i '.$this->storagePath.DS.$this->meta->pth.
  		' -c:v libx264 -ar 22050 -preset ultrafast -vf pad="width=ceil(iw/2)*2:height=ceil(ih/2)*2:color=black","scale=\'-1\':\'min(540,ih)\'" '.
  		$this->storagePath.DS.$pathWithoutExt.'.'.$this->toFormat .' 1> '.$logFile.' 2>&1';
  	}
  	else
  	{
  		throw new \Exception('Unsupported destination format ['.$this->toFormat.']');
  	}

  	$process = new Process($cmd);
  	$process->setTimeout($this->timeout-10); //300s - 10s
  	try {
  		$process->mustRun();
  		$process->wait();
  	}
  	//catch all exceptions from process, including ProcessFailedException and ProcessTimedOutException
  	catch (\Exception $e)
  	{
  		if(is_file($logFile)) unlink($logFile);
  		//echo $e->getMessage();
  		throw $e;
  	}

  	if(is_file($logFile)) unlink($logFile);

    //check if file is saved to disk
    //dd($this->storagePath.DS.$pathWithoutExt.'.'.$this->toFormat);
    if (!is_file($this->storagePath.DS.$pathWithoutExt.'.'.$this->toFormat))
    {
	  if(is_file($logFile)) unlink($logFile);
      throw new \Exception('Video converting completed but file not found on disk');
    }

    //save to database status=2 (processed)
    $this->_setVideoFormatConversionStatus(2);

    //if source file is not mp4 then delete xy file and set main to mp4
    if ($this->toFormat == 'mp4')
    {
      //delete original file (with mp4 check)
  	  if(is_file($this->storagePath.DS.$pathWithoutExt.'.mp4'))
  	  {
    		unlink($this->storagePath.DS.$this->meta->pth);
    		//TODO unlink on other locations
    		$this->meta->ext = 'mp4';
    		$this->meta->pth = $pathWithoutExt.'.mp4';
  	  }
    }
  	//save changed status or other
    $this->_saveMeta();

	//send purge varnish request to video url (/video/asdadasdasd.webm)
	$purgeFullUrl = config('app')['url'].'/video/'.$this->meta['id'].'.'.$this->toFormat;
    $this->_varnishXPurge($purgeFullUrl,false);
  }

  private function _saveMeta()
  {
    $this->meta->save();
    //clear cache of video meta from redis
    cache()->driver()->forget('video:'.$this->meta->id); //clear cache
  }

  /**
   * The job failed to process.
   *
   * @param  Exception  $exception
   * @return void
   */
  public function failed(\Exception $e)
  {
    //save do database status=0 failed conversion
    $this->_setVideoFormatConversionStatus(0);
    $this->_saveMeta();
  }

  private function _varnishXPurge($url,$debug = false)
  {
    $urlParsed = parse_url($url);
    $header = [
      "Host: ".$urlParsed['host'], // IMPORTANT
      "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
      "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3",
      "Accept-Encoding: gzip,deflate,sdch",
      "Accept-Language: it-IT,it;q=0.8,en-US;q=0.6,en;q=0.4",
      "Cache-Control: max-age=0",
      "Connection: keep-alive",
    ];
    $curlOptionList = [
      CURLOPT_URL                     => $url,
      CURLOPT_HTTPHEADER              => $header,
      CURLOPT_CUSTOMREQUEST           => "PURGE",
      CURLOPT_VERBOSE                 => $debug,
      CURLOPT_RETURNTRANSFER          => true,
      CURLOPT_NOBODY                  => true,
      CURLOPT_CONNECTTIMEOUT_MS       => 2000,
    ];

    $fd = false;
    if( $debug == true ) {
        print "\n---- Purge Output -----\n";
        $fd = fopen("php://output", 'w+');
        $curlOptionList[CURLOPT_VERBOSE] = true;
        $curlOptionList[CURLOPT_STDERR]  = $fd;
    }
    $curlHandler = curl_init();
    curl_setopt_array( $curlHandler, $curlOptionList );
    curl_exec( $curlHandler );
    curl_close( $curlHandler );
    if( $fd !== false ) {
        fclose( $fd );
    }
  }
}
