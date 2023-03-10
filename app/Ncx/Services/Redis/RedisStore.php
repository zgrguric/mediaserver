<?php

namespace Ncx\Services\Redis;
//Redis class name is already reserved by Redis lib
class RedisStore
{
    protected $container;
    protected $redis;
    protected $settings;
    private $prefix;
    //private $prefix; //cache prefix

    public function __construct($container)
    {
      $this->container = $container;
      $this->settings = $this->container->get('settings');
      $this->setPrefix($this->settings['cache']['prefix']);
    }

    /**
    * Connect to redis once when called and remember connection.
    * @return void
    */
    public function connection()
    {
      if ($this->redis)
        return $this->redis;
      //https://github.com/phpredis/phpredis/blob/develop/INSTALL.markdown
  		//za xampp instaliran x86 thread safe https://windows.php.net/downloads/pecl/releases/redis/4.1.1/php_redis-4.1.1-7.2-ts-vc15-x86.zip
  		//https://pecl.php.net/package/redis/4.1.1/windows
  		$this->redis = new \Redis();
      try{
        $this->redis->connect($this->settings['redis']['host'], $this->settings['redis']['port']);
      } catch(\RedisException $e){
        //throw new \Exception('Unable to connect to redis: '.$e->getMessage());
        die('Unable to connect to redis: '.$e->getMessage());
        return null;
      }

  		//if password is set then authenticate
  		if ($this->settings['redis']['auth'])
  			$this->redis->auth($this->settings['redis']['auth']);
      return $this->redis;
    }

    public function disconnect()
    {
      $this->redis = null;
    }

    /**
    * Get \Redis instance.
    * @return \Redis
    */
    public function getRedis()
    {
      $this->connect();
      return $this->redis;
    }

    /**
     * Set the cache key prefix.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setPrefix($prefix)
    {
        $this->prefix = ! empty($prefix) ? $prefix.':' : '';
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key)
    {
        $value = $this->connection()->get($this->prefix.$key);

        return ! is_null($value) ? $this->unserialize($value) : null;
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param  array  $keys
     * @return array
     */
    public function many(array $keys)
    {
        $results = [];

        $values = $this->connection()->mget(array_map(function ($key) {
            return $this->prefix.$key;
        }, $keys));

        foreach ($values as $index => $value) {
            $results[$keys[$index]] = ! is_null($value) ? $this->unserialize($value) : null;
        }

        return $results;
    }

    /**
     * Store an item in the cache for a given number of minutes.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $minutes
     * @return void
     */
    public function put($key, $value, $minutes)
    {
        $this->connection()->setex(
            $this->prefix.$key, (int) max(1, $minutes * 60), $this->serialize($value)
        );
    }

    /**
     * Store multiple items in the cache for a given number of minutes.
     *
     * @param  array  $values
     * @param  float|int  $minutes
     * @return void
     */
    public function putMany(array $values, $minutes)
    {
        $this->connection()->multi();

        foreach ($values as $key => $value) {
            $this->put($key, $value, $minutes);
        }

        $this->connection()->exec();
    }


    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $minutes
     * @return bool
     */
    public function add($key, $value, $minutes)
    {
        $lua = "return redis.call('exists',KEYS[1])<1 and redis.call('setex',KEYS[1],ARGV[2],ARGV[1])";

        return (bool) $this->connection()->eval(
            $lua, 1, $this->prefix.$key, $this->serialize($value), (int) max(1, $minutes * 60)
        );
    }


    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->connection()->incrby($this->prefix.$key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->connection()->decrby($this->prefix.$key, $value);
    }


    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value)
    {
        $this->connection()->set($this->prefix.$key, $this->serialize($value));
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        return (bool) $this->connection()->del($this->prefix.$key);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $this->connection()->flushdb();
        return true;
    }

    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) ? $value : serialize($value);
    }

    /**
     * Unserialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }
}
