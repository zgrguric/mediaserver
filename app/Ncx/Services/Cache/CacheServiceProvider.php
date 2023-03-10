<?php

namespace Ncx\Services\Cache;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class CacheServiceProvider implements ServiceProviderInterface
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $container)
    {
        $cache = new Cache($container);
        $container['cache'] = function ($c) use ($cache) {return $cache;};
    }
}
