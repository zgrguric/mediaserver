<?php

namespace Ncx\Services\Cache;

class Cache
{
    protected $driver;

    public function __construct($container)
    {
      $this->container = $container;
      $this->driver = $container->get('redis');
    }


    public function driver()
    {
      return $this->driver;
    }
}
