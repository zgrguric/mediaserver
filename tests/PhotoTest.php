<?php
declare(strict_types=1);

//http://lzakrzewski.com/2016/02/integration-testing-with-slim/
use PHPUnit\Framework\TestCase;
use Ncx\Controllers\UploadController;
use Ncx\Controllers\PhotoStreamController;
use Ncx\Http\Request;;
use Slim\Http\Response;
use Slim\Http\Environment;
use Slim\Http\UploadedFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Ncx\Models\Photo;
use Ncx\Filesystem\FilesystemManager;
use Illuminate\Database\Capsule\Manager as DB;

class PhotoTest extends TestCase
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
    public function testPrepareSamplePhotos(): void
    {
      $source = ROOT.'tests'.DS.'resources'.DS.'photos'.DS.'photo1.jpg';
      $dest = ROOT.'storage'.DS.'tests'.DS.'photo1.jpg';

			if(is_file($dest))
				@unlink($dest);

			//delete test video from database
			$photo = Photo::where('hsh','e848c70e4a52bf3391bbb38858d4d371')->first();
			if($photo)
				$photo->delete();

      if(!\copy($source,$dest))
        $this->assertTrue(false,'Unable to copy '.$source.' to '.$dest);

      $this->assertTrue(true);
    }

		/**
     * @depends testPrepareSamplePhotos
     */
		 public function testPhotoUpload() : array
     {
       $photoPath = ROOT.'storage'.DS.'tests'.DS.'photo1.jpg';
       global $container;
       $uploadController = new UploadController($container);
       $environment = Environment::mock([
             'REQUEST_METHOD'  => 'POST',
             'REQUEST_URI'     => '/upload/abc',
             'QUERY_STRING'    => '',
             'slim.files' => ['file' => new UploadedFile($photoPath, 'photo1.jpg', 'image/jpeg', filesize($photoPath))]
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
			* @depends testPhotoUpload
			*/
		 public function testPhotoUploadResponseIsCorrect($data) : array
		 {
			 $this->assertThat($data['size'], $this->logicalAnd(
					 $this->isType('int'),
					 $this->greaterThan(0)
			 ));

			 $this->assertEquals([
				 'filename' => $data['filename'],
				 'ext' => 'jpg',
					'id' => $data['id'],
				 'size' => $data['size'], //different on win and different on linux
				 //'size' => 367975,
				 'url' => config('app')['url'].'/photo/full/'.$data['id'].'.jpg',
				 'thumb_url' => config('app')['url'].'/photo/thumb/'.$data['id'].'.jpg',
				 'identifier' => 'abc',
				],$data);
			 return $data;
		 }

		  /**
       * @depends testPhotoUploadResponseIsCorrect
       */
 		  public function testPhotoUpload2($data) : array
			{
			  $photoPath = ROOT.'storage'.DS.'tests'.DS.'photo1.jpg';
			  global $container;
			  $uploadController = new UploadController($container);
			  $environment = Environment::mock([
			        'REQUEST_METHOD'  => 'POST',
			        'REQUEST_URI'     => '/upload/abc2',
			        'QUERY_STRING'    => '',
			        'slim.files' => ['file' => new UploadedFile($photoPath, 'photo1.jpg', 'image/jpeg', filesize($photoPath))]
			      ]
			    );
			  $request = Request::createFromEnvironment($environment);
			  $request = $request->withHeader('Authorization', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjJ9.kFZ0DihEWfF1njtVZ0qKqn988jrpSt1TDevXtBl0FPk');

			  //$request = $request->withUploadedFiles([]);
			  $uploadresponse = $uploadController->upload($request,new \Slim\Http\Response(),['identifier' => 'abc2']);
			  $this->assertEquals(200, $uploadresponse->getStatusCode());
			  $responseBody = (string)$uploadresponse->getBody();
			  $responseJson = json_decode($responseBody,true);
			  if ($responseJson === null)
			    $this->assertTrue(false);
			  $this->assertTrue(true);
			  return $responseJson;
			}

		/**
		 * @depends testPhotoUpload2
		 */
		public function testPhotoUploadResponseIsCorrect2($data) : array
		{
			$this->assertThat($data['size'], $this->logicalAnd(
				 $this->isType('int'),
				 $this->greaterThan(0)
			));

			$this->assertEquals([
			 'filename' => $data['filename'],
			 'ext' => 'jpg',
			 'id' => $data['id'],
			 'size' => $data['size'], //different on win and different on linux
			 //'size' => 367975,
			 'url' => config('app')['url'].'/photo/full/'.$data['id'].'.jpg',
			 'thumb_url' => config('app')['url'].'/photo/thumb/'.$data['id'].'.jpg',
			 'identifier' => 'abc2',
			],$data);
			return $data;
		}

		/**
		 * @depends testPhotoUploadResponseIsCorrect2
		 */
		public function testPhotoIsDeletedOnlyForIdentifier2($data) : array
		{
			global $container;
			$photo = Photo::find($data['id']);
			$uploadController = new UploadController($container);
			$environment = Environment::mock([
						'REQUEST_METHOD'  => 'GET',
						'REQUEST_URI'     => config('app')['url'].'/delete/photo/'.$data['id'].'/abc2',
						'QUERY_STRING'    => ''
					]
				);
		  $request = Request::createFromEnvironment($environment);
			$request = $request->withHeader('Authorization', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjJ9.kFZ0DihEWfF1njtVZ0qKqn988jrpSt1TDevXtBl0FPk');
			$dataResponse = $uploadController->delete($request,new \Slim\Http\Response(),['id' => $data['id'], 'mediatype' => 'photo','identifier'=>'abc2']);


			$filesystem = new FilesystemManager($container);
			$path = $filesystem->disk()->getPath().DS.$photo->pth;
			$this->assertFileExists($path);

			//todo check on backups

			/*$photo = Photo::find($data['id']);
			$photo->delete();*/

			$photoCheck = Photo::where('_id',$data['id'])->count();
			$this->assertTrue($photoCheck > 0);
			return $data;
		}




		/**
		 * @depends testPhotoIsDeletedOnlyForIdentifier2
		 */
	 public function testPhotoOriginalIsAccessible($data) : array
	 {
		 $url = $data['url'];
		 global $container;
		 $photoStreamController = new PhotoStreamController($container);
		 $environment = Environment::mock([
					 'REQUEST_METHOD'  => 'GET',
					 'REQUEST_URI'     => $url,
					 'QUERY_STRING'    => ''
				 ]
			 );

		 $request = Request::createFromEnvironment($environment);
		 $dataResponse = $photoStreamController->full($request,new \Slim\Http\Response(),['ext' => 'jpg','id' => $data['id']]);
		 //$responseBody = (string)$dataResponse->getBody();
		 $this->assertEquals(200, $dataResponse->getStatusCode());
		 return $data;
	 }

	 /**
		* @depends testPhotoOriginalIsAccessible
		*/
	public function testPhotoThumbIsAccessible($data) : array
	{
		$url = $data['thumb_url'];
		global $container;
		$photoStreamController = new PhotoStreamController($container);
		$environment = Environment::mock([
					'REQUEST_METHOD'  => 'GET',
					'REQUEST_URI'     => $url,
					'QUERY_STRING'    => ''
				]
			);
		$request = Request::createFromEnvironment($environment);

		//supress Headers already sent temporarily, its a known warning that wont happen outside unit testing.
		error_reporting(E_ALL ^ E_WARNING);
		$dataResponse = $photoStreamController->thumb($request,new \Slim\Http\Response(),['ext' => 'jpg','id' => $data['id'],'type' => 'thumb']);
		error_reporting(E_ALL);
		$this->assertEquals(200, $dataResponse->getStatusCode());
	  return $data;
	}

	/**
	 * @depends testPhotoThumbIsAccessible
	 */
	public function testPhotoIsDeletedPermanently($data) : void
	{
		global $container;
		$photo = Photo::find($data['id']);
		$uploadController = new UploadController($container);
		$environment = Environment::mock([
					'REQUEST_METHOD'  => 'GET',
					'REQUEST_URI'     => config('app')['url'].'/delete/photo/'.$data['id'].'/abc',
					'QUERY_STRING'    => ''
				]
			);
	  $request = Request::createFromEnvironment($environment);
		$request = $request->withHeader('Authorization', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjJ9.kFZ0DihEWfF1njtVZ0qKqn988jrpSt1TDevXtBl0FPk');
		$dataResponse = $uploadController->delete($request,new \Slim\Http\Response(),['id' => $data['id'], 'mediatype' => 'photo','identifier'=>'abc']);


		$filesystem = new FilesystemManager($container);
		$path = $filesystem->disk()->getPath().DS.$photo->pth;
		$this->assertFileNotExists($path);

		//todo check on backups

		/*$photo = Photo::find($data['id']);
		$photo->delete();*/

		$photoCheck = Photo::where('_id',$data['id'])->count();
		$this->assertTrue($photoCheck == 0);
	}


}
