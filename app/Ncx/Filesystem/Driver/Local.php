<?php
/**
* @package Objects
* @author Zvjezdan Grguric
**/
namespace Ncx\Filesystem\Driver;
use Ncx\Filesystem\FilesystemDriverAbstract;

class Local extends FilesystemDriverAbstract
{
  private $config;
  private $diskName;

  public function __construct($diskName,$config)
  {
      $this->diskName = $diskName;
      $this->config = $config;
  }
  /**
  *
  * @param string $path
  * @param \Slim\Http\UploadedFile $uploadedFile
  * @param string $filename
  **/
  public function putFileAs($path,\Slim\Http\UploadedFile $uploadedFile,$filename)
  {

    $storage_path = $this->config['root'].DS.$path;
    $this->createDirIfDoesNotExist($storage_path);
    $full_file_path = $storage_path . DS . $filename;
    //delete old file if exists
    $this->delete($path,$filename);
    try {
      $uploadedFile->moveTo($full_file_path);
    } catch (\Throwable $e) {
       return $this->formatResponse($filename,$path,null,$e->getMessage(). ' ('.$full_file_path.')');
    }
	  chmod($full_file_path, octdec($this->fileCHMOD));

    //save to backups
    return $this->backupFile($path,$filename,$uploadedFile);
    //return $this->formatResponse($filename,$path,$uploadedFile);
  }


  public function backupFile($path,$filename,$response = null)
  {
    $storage_path = $this->config['root'].DS.$path;
    $full_file_path = $storage_path . DS . $filename;
    //save to backups
    foreach($this->config['backup'] as $backuppath)
    {
      if ($backuppath && $backuppath != 'false' && $backuppath != 'true')
      {
          $this->createDirIfDoesNotExist($backuppath.DS.$path);
          if (!copy($full_file_path, $backuppath.DS.$path.DS.$filename)) {
            return $this->formatResponse($filename,$path,null,sprintf('Error moving uploaded file %s to %s', $full_file_path, $backuppath.DS.$path.DS.$filename));
          }
		      chmod($backuppath.DS.$path.DS.$filename, octdec($this->fileCHMOD));
      }
    }
    return $this->formatResponse($filename,$path,$response,null);
  }

  /**
  * Format response from this driver, this should be (except data) same everywhere.
  */
  private function formatResponse($filename,$path,$response = null,$error = null)
  {
    return [
      'success' => ($error == null),
      'errormsg' => $error,
      'filename' => $filename,
      'path' => $path,//path within storage disk
      'driver' => $this->config['driver'],
      'disk' => $this->diskName,
      'config' => $this->config,
      'data' => $response
    ];
  }

  public function getPath()
  {
    return $this->config['root'];
  }

  public function fileExists($path,$filename,$rootPath = null)
  {
    $root = !$rootPath ? $this->config['root'] : $rootPath;
    return is_file($root.DS.$path.DS.$filename);
  }

  /**
  * If any of files from backup fails to delete this will return false.
  * @return boolean
  */
  public function delete($path,$filename,$rootPath = null)
  {
    $root = !$rootPath ? $this->config['root'] : $rootPath;
    $full_file_path = $root.DS.$path.DS.$filename;
    if ($this->fileExists($path,$filename,$root))
    {
      if(@unlink($full_file_path) !== true ){
        return false;
        //throw new Exception('Could not delete file: ' . $path);
      }
    }
    if(!$rootPath)
    {
      foreach($this->config['backup'] as $backuppath)
      {
        if ($backuppath && $backuppath != 'false' && $backuppath != 'true')
        {
          if (!$this->delete($path,$filename,$backuppath))
            return false;
        }
      }
    }
    return true;
  }
}
