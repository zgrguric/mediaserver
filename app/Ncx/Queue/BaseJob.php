<?php
/**
* @author Zvjezdan Grguric
*/

namespace Ncx\Queue;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;

class BaseJob
{
  /**
   * The number of times the job may be attempted.
   *
   * @var int
   */
  public $tries = 3;

  /**
   * Current attempt
   *
   * @var int
   */
  public $attempt = 0;

  /**
   * The number of seconds the job can run before timing out.
   *
   * @var int
   */
  public $timeout = 120;

  /**
  * The number of seconds to wait before retrying the job.
  *
  * @var int
  */
  public $retryAfter = 3;

  protected $jobID;

  /**
  * Driver of storage where jobs queue is stored.
  * Possible values: mysql | mongodb
  *
  * @var int
  */
  protected $storageDriver = 'mysql';

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    //handle something here
  }

  /**
   * The job failed to process.
   *
   * @param  Exception  $exception
   * @return void
   */
  public function failed(Exception $exception)
  {
      // Send user notification of failure, etc...
  }

  public function setStorageDriver($driver)
  {
    $this->storageDriver = $driver;
  }

  public function getStorageDriver()
  {
    return $this->storageDriver;
  }

  /**
  * Unique job ID
  * Id of next job must be different form previous.
  * For tracking tries
  * Best to get ID from database
  * @return mixed string or int
  */
  public function getId()
  {
    //some unique job identifier
    return $this->jobID;
  }

  public function getBody()
  {
    return 'this is description';
  }

  public function getName()
  {
    return 'this is job name';
  }

  /*public function getEvent()
  {
    return 'this is event';
  }*/

  public function setCurrentAttempt($attempt)
  {
    $this->attempt = $attempt;
  }

  public function setJobID($id)
  {
    $this->jobID = $id;
  }


  public function dispatch($queue = 'default')
  {
    $data = DB::connection('media')->table('jobs')->insert(
      [
        'queue' => $queue,
        'payload' => serialize($this),
        'attempts' => 0,
        'reserved_at' => null,
        'created_at' => time(),
        'available_at' => time(),
      ]
    );
  }
}
