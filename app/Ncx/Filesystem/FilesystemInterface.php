<?php
/**
* @package Objects
* @author Zvjezdan Grguric
**/

namespace Ncx\Filesystem;
interface FilesystemInterface
{
    public function putFileAs($path,\Slim\Http\UploadedFile $uploadedFile,$filename);
    public function delete($path,$filename);
    public function fileExists($path,$filename);
    public function backupFile($path,$filename);
    public function getPath();

}
