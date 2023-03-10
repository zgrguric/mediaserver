<?php

namespace Ncx\Services\Redis;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class RedisServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $c)
    {
        $redis = new RedisStore($c);
        $c['redis'] = function ($c) use ($redis) {return $redis;};
    }
}
