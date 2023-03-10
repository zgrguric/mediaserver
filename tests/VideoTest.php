<?php
declare(strict_types=1);
//http://lzakrzewski.com/2016/02/integration-testing-with-slim/
use PHPUnit\Framework\TestCase;
use Ncx\Controllers\UploadController;
use Ncx\Controllers\VideoStreamController;
use Ncx\Http\Request;;
use Slim\Http\Response;
use Slim\Http\Environment;
use Slim\Http\UploadedFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Ncx\Models\Video;
use Ncx\Filesystem\FilesystemManager;
use Illuminate\Database\Capsule\Manager as DB;

class VideoTest extends TestCase
{
		public function __construct($name = null, array $data = [], $dataName = '')
		{
			parent::__construct($name,$data,$dataName);
			global $container;
		}

		public function testConfigIsNotCached(): void
		{
			$configFile = ROOT.'bootstrap'.DS.'cache'.DS.'config.php';
			$this->assertFileNotExists($configFile);
		}

		/**
     * @depends testConfigIsNotCached
     */
    public function testPrepareSampleVideos(): void
    {
      $source = ROOT.'tests'.DS.'resources'.DS.'videos'.DS.'video1.mov';
      $dest = ROOT.'storage'.DS.'tests'.DS.'video1.mov';

			if(is_file($dest))
				@unlink($dest);

			//delete test video from database
			$video = Video::where('hsh','731f09145a1ea9ec9dad689de6fa0358')->first();
			if($video)
				$video->delete();

      if(!\copy($source,$dest))
        $this->assertTrue(false,'Unable to copy '.$source.' to '.$dest);

			//delete all jobs from queue 'conversiontest', clean slate
			DB::connection('media')
        ->table('jobs')
        ->where('queue','conversiontest')
        ->delete();

      $this->assertTrue(true);
    }

    /**
     * @depends testPrepareSampleVideos
     */
    public function testVideoUpload() : array
    {
      $videoPath = ROOT.'storage'.DS.'tests'.DS.'video1.mov';
      global $container;
      $uploadController = new UploadController($container);
      $environment = Environment::mock([
            'REQUEST_METHOD'  => 'POST',
            'REQUEST_URI'     => '/upload/abc',
            'QUERY_STRING'    => '',
            'slim.files' => ['file' => new UploadedFile($videoPath, 'video1.mov', 'video/quicktime', filesize($videoPath))]
          ]
        );
      $request = Request::createFromEnvironment($environment);
      $request = $request->withHeader('Authorization', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjJ9.kFZ0DihEWfF1njtVZ0qKqn988jrpSt1TDevXtBl0FPk');

      //$request = $request->withUploadedFiles([]);
      $uploadresponse = $uploadController->upload($request,new \Slim\Http\Response(),['identifier' => 'abc']);
      $this->assertEquals(200, $uploadresponse->getStatusCode());
      $responseBody = (string)$uploadresponse->getBody();
      $responseJson = json_decode($responseBody,true);
      if ($responseJson === null)
        $this->assertTrue(false);
      $this->assertTrue(true);
      return $responseJson;
    }

    /**
     * @depends testVideoUpload
     */
    public function testJsonVideoInfoIsCorrectForMultipleFormats($data): array
    {

      $this->FetchJsonVideoInfo('mov',[
        'status' => null, //invalid input ext, status is null
        'processing' => [
          'status' => 'unknown'
        ]
      ],$data);

      $this->FetchJsonVideoInfo('3gp',[
        'status' => null, //invalid input ext, status is null
        'processing' => [
          'status' => 'unknown'
        ]
      ],$data);

      $this->FetchJsonVideoInfo('mp4',[
        'status' => 5, //at this point it is not queued
        'processing' => [
          'status' => 'notprocessed'
        ]
      ],$data);
      $this->FetchJsonVideoInfo('webm',[
        'status' => 5, //at this point it is not queued
        'processing' => [
          'status' => 'notprocessed'
        ]
      ],$data);
      return $data;
    }

    /**
     * @depends testJsonVideoInfoIsCorrectForMultipleFormats
     */
    public function testConversionQueueToMp4($data) : array
    {
      $mp4Uri =  substr($data['url'],0,-4).'.mp4';

      global $container;
      $videoStreamController = new VideoStreamController($container);
      $environment = Environment::mock([
            'REQUEST_METHOD'  => 'GET',
            'REQUEST_URI'     => $mp4Uri,
            'QUERY_STRING'    => ''
          ]
        );
      $request = Request::createFromEnvironment($environment);
      $streamResponse = $videoStreamController->stream($request,new \Slim\Http\Response(),['ext' => 'mp4','id' => $data['id']]);
      $responseBody = (string)$streamResponse->getBody();
	  	$this->assertEquals(404, $streamResponse->getStatusCode());
      //since we uploaded .mov first thig that it should do is start conversion to mp4
      $this->assertEquals('404 - Video conversion queued', $responseBody);
      return $data;
    }

    /**
     * @depends testConversionQueueToMp4
     */
    public function testJsonVideoInfoIsCorrectForQueuedMp4($data): array
    {

      $this->FetchJsonVideoInfo('mp4',[
        'status' => 3, //VIDEO_CONVERSION_QUEUED
        'processing' => [
          'status' => 'queued'
        ]
      ],$data);

      //webm should be untouched
      $this->FetchJsonVideoInfo('webm',[
        'status' => 5, //VIDEO_NOT_PROCESSED
        'processing' => [
          'status' => 'notprocessed'
        ]
      ],$data);

      return $data;
    }

		/**
     * @depends testJsonVideoInfoIsCorrectForQueuedMp4
		 * @group large
     */
    public function testConversionJobToMp4ExecutedSuccessfully($data) : array
    {
			//Run process 'php artisan queue:run --queue=conversiontest'
			$process = new Process('php artisan queue:run --queue=conversiontest --limit=1');

			try {
			    $process->mustRun();
					$output = $process->getOutput();
					$this->assertStringContainsString("Processed", $output);
			} catch (ProcessFailedException $exception) {
			    throw $exception;
			}
			return $data;
    }

		/**
     * @depends testConversionJobToMp4ExecutedSuccessfully
     */
    public function testConvertedFileIsStoredOnDiskAndOriginalDeleted($data) : array
    {
			global $container;
			$filesystem = new FilesystemManager($container);
			$storagePath = $filesystem->disk()->getPath();
			$video = Video::find($data['id']);

			#check video data
			$videoData = $video->toArray();

			$this->assertEquals([
				'_id' => $video->_id,
				 'hsh' => "731f09145a1ea9ec9dad689de6fa0358",
				 'pth' => $video->pth,
				 'sze' => 3284257,
				 'w' => 640,
				 'h' => 480,
				 'dur' => 85.5,
				 'ext' => "mp4",
				 'c_at' => $videoData['c_at'],
				 'cs1' => 2,
				 'cnt' => 1,
				 'queue' => 'conversiontest',
				 'uids' => ['2_abc'],

			],$videoData);

			#end check video data
			$fullFilepath = $storagePath.DS.$video->pth;
			$this->assertFileExists($fullFilepath);

			$fullFilepathMov = substr($storagePath.DS.$video->pth,0,-4).'.mov'; //replace .mp4 with .mov
			$this->assertFileNotExists($fullFilepathMov);

			# change data from mov to mp4 becouse conversion deleted mov and set main file to mp4
			$data['filename'] = substr($data['filename'],0,-4).'.mp4';
			$data['url'] = substr($data['url'],0,-4).'.mp4';

			return $data;
    }

    /**
     * @depends testConvertedFileIsStoredOnDiskAndOriginalDeleted
     */
    public function testConversionQueueToWebm($data) : array
    {
      global $container;
      $videoStreamController = new VideoStreamController($container);
      $environment = Environment::mock([
            'REQUEST_METHOD'  => 'GET',
            'REQUEST_URI'     => substr($data['url'],0,-4).'.webm',
            'QUERY_STRING'    => ''
          ]
        );
      $request = Request::createFromEnvironment($environment);
      $streamResponse = $videoStreamController->stream($request,new \Slim\Http\Response(),['ext' => 'webm','id' => $data['id']]);
      $responseBody = (string)$streamResponse->getBody();
	  	$this->assertEquals(404, $streamResponse->getStatusCode());
      //first thing that it should do is start conversion to webm
      $this->assertEquals('404 - Video conversion queued', $responseBody);
      return $data;
    }

    /**
     * @depends testConversionQueueToWebm
     */
    public function testJsonVideoInfoIsCorrectForQueuedWebm($data): array
    {
      $this->FetchJsonVideoInfo('webm',[
        'status' => 3, //VIDEO_CONVERSION_QUEUED
				'original_ext' => 'mp4',
        'processing' => [
          'status' => 'queued'
        ]
      ],$data);

      //webm should be converted from mov at this point
      $this->FetchJsonVideoInfo('mp4',[
        'status' => 1, //VIDEO_PLAYABLE
				'original_ext' => 'mp4',
        'processing' => [
          'status' => 'processed',
					'progress' => 100
        ]
      ],$data);

      return $data;
    }

		/**
     * @depends testJsonVideoInfoIsCorrectForQueuedWebm
		 * @group large
     */
    public function testConversionJobToWebmExecutedSuccessfully($data) : array
    {
			return $this->testConversionJobToMp4ExecutedSuccessfully($data);
    }

		/**
     * @depends testConversionJobToWebmExecutedSuccessfully
     */
    public function testConvertedWebmFileIsStoredOnDisk($data) : array
    {
			global $container;
			$filesystem = new FilesystemManager($container);
			$storagePath = $filesystem->disk()->getPath();
			$video = Video::find($data['id']);
			#end check video data
			$fullFilepath = substr($storagePath.DS.$video->pth,0,-4).'.webm';
			$this->assertFileExists($fullFilepath);

			return $data;
    }

		/**
		 * @depends testConvertedWebmFileIsStoredOnDisk
		 */
		public function testVideoIsDeleted($data) : void
		{
			global $container;
			$video = Video::find($data['id']);
			$uploadController = new UploadController($container);
			$environment = Environment::mock([
						'REQUEST_METHOD'  => 'GET',
						'REQUEST_URI'     => config('app')['url'].'/delete/video/'.$data['id'].'/abc',
						'QUERY_STRING'    => ''
					]
				);
		  $request = Request::createFromEnvironment($environment);
			$request = $request->withHeader('Authorization', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjJ9.kFZ0DihEWfF1njtVZ0qKqn988jrpSt1TDevXtBl0FPk');
			$dataResponse = $uploadController->delete($request,new \Slim\Http\Response(),['id' => $data['id'], 'mediatype' => 'video','identifier' => 'abc']);

			$filesystem = new FilesystemManager($container);
			//original
			$path = $filesystem->disk()->getPath();
			$this->assertFileNotExists($path.DS.$video->pth);
			//webm
			$fullFilepathWebm = substr($path.DS.$video->pth,0,-4).'.webm'; //replace .mp4 with .mov
			$this->assertFileNotExists($fullFilepathWebm);
			//echo $fullFilepathMov;
			//mp4

			$videoCheck = Video::where('_id',$data['id'])->count();
			$this->assertTrue($videoCheck == 0);

		}

    # PRIVATE

    private function FetchJsonVideoInfo($requestedExtension,array $expectedResponseOverride,$data): void
    {
      //$jsonUri =  $data['url'].'?info';dd($jsonUri);
			$jsonUri = '/api/video/'.$data['id'].'.'.$requestedExtension;
		//
      global $container;
      $videoStreamController = new VideoStreamController($container);
      $environment = Environment::mock([
            'REQUEST_METHOD'  => 'GET',
            'REQUEST_URI'     => $jsonUri,
            'QUERY_STRING'    => ''
          ]
        );

      $request = Request::createFromEnvironment($environment);
      $streamResponse = $videoStreamController->info($request,new \Slim\Http\Response(),['ext' => $requestedExtension,'id' => $data['id']]);
      $responseBody = (string)$streamResponse->getBody();

      $this->assertEquals(200, $streamResponse->getStatusCode());
      $responseJson = json_decode($responseBody,true);

      $expectedResponse = [
        'id' => $data['id'],
        'internal_path' => date('Y').DS.date('m').DS.date('d').DS.$data['filename'],
        'original_ext' => 'mov',
        'requested_ext' => $requestedExtension,
        'status' => 3,
        'processing' => [
          'status' => 'queued',
          'progress' => 0,
          'duration' => null,
          'currtime' => null
        ]
      ];
      $expectedResponseMerged = array_replace_recursive($expectedResponse, $expectedResponseOverride);
      $this->assertEquals($expectedResponseMerged,$responseJson);
    }


}
