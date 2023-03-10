<?php

namespace Ncx\Services\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
#use Illuminate\Support\Facades\Schema;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
//use Illuminate\Database\Connection;
use Jenssegers\Mongodb\Connection as MongoConnection;


class EloquentServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     * Konekcije se pisu u manager klasu ali se ne inicijaliziraju
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        $capsule = new Capsule();

        $config = $pimple['settings']['database'];

        //init default connection

        $capsule->addConnection($config['connections']['media'],'media');
        $capsule->getDatabaseManager()->extend('media', function ($config, $name) {
            $config['name'] = $name;
            return new MongoConnection($config);
        });

        //Event dispatcher for Eloquent
        $capsule->setEventDispatcher(new Dispatcher());

        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();

        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $capsule->bootEloquent();

        $pimple['db'] = function ($c) use ($capsule) {return $capsule;};
    }


}
