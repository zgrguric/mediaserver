<?php
/**
* @package Objects
* @author Zvjezdan Grguric
**/

namespace Ncx\Filesystem;


class FilesystemManager
{

  protected $config;
  protected $diskLocalStore = [];

  public function __construct($c)
  {
    $this->config = $c->get('settings');
  }

  public function disk($name = null)
  {
    if ($name === null)
      $name = $this->config['filesystem']['default'];

    //reuse
    if (isset($this->diskLocalStore[$name]))
      return $this->diskLocalStore[$name];

    //create new driver instance
    $namespacedClassName = 'Ncx\\Filesystem\\Driver\\'.$this->config['filesystem']['disks'][$name]['driver'];
    $diskConfig = $this->config['filesystem']['disks'][$name];
    $this->diskLocalStore[$name] = new $namespacedClassName($name,$diskConfig);
    return $this->diskLocalStore[$name];
  }
}
