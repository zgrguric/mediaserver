<?php
/**
* @package Objects
* @author Zvjezdan Grguric
**/

namespace Ncx\Filesystem;

abstract class FilesystemDriverAbstract implements FilesystemInterface
{
  protected $dirCHMOD = '0770';  //0750
  protected $fileCHMOD = '0660'; //0640

	/**
	* 750 for directories
	* 640 for files
	*/
  public function createDirIfDoesNotExist($path)
  {
    if (!is_dir($path))
      @mkdir($path,octdec($this->dirCHMOD),true);
  }
}
