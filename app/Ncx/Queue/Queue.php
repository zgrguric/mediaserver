<?php
/**
* @author Zvjezdan Grguric
*
*/
namespace Ncx\Queue;
use Ncx\Queue\QueueInterface;
use Illuminate\Database\Capsule\Manager as DB;

//database queue!
class Queue implements QueueInterface
{
    private $connection = 'media';
    private $queueName = 'default';

    /**
    * Gets next job
    * @return nullable Job
    */
    public function get()
    {
      //load jobs from presistant storage
      $data = DB::connection($this->connection)
        ->table('jobs')
        ->select('id','payload','attempts')
        ->where('queue',$this->getName())
        ->orderBy('available_at','asc')
        ->first();

      if (!$data)
        return null;

      //make sure we are working with object
      if(is_array($data))
        $data = (object)$data;

      //if mongo queue then create id from _id
      if (!isset($data->id) && isset($data->_id)) {
        $storageDriver = 'mongodb';
        $jobID = (string)$data->_id;
      }
      else {
        $storageDriver = 'mysql';
        $jobID = $data->id;
      }

      $job = unserialize($data->payload);

      $job->setCurrentAttempt(((int)$data->attempts+1));
      $job->setStorageDriver($storageDriver);
      $job->setJobID($jobID);
      return $job;

    }

    public function getName()
    {
      return $this->queueName;
    }

    public function setName($name)
    {
      $this->queueName = $name;
    }

    /**
    * Delete job from presistant database (jobs table)
    */
    public function processed($job)
    {
      $id = $job->getId();

      if (!$id)
        throw new \Exception('Job processed ID not provided');

      if($job->getStorageDriver() == 'mongodb')
        $identifier = '_id';
      else
        $identifier = 'id';
      DB::connection($this->connection)
        ->table('jobs')
        ->where($identifier,$id)
        ->delete();
    }

    /**
    * Log error, or write error to failed_jobs table
    *
    */
    public function failed($job,$exception)
    {
      $job->failed($exception); //do job error logging

      if ($job->attempt >= $job->tries) {
        //TODO log error here
        DB::connection($this->connection)
          ->table('failed_jobs')
          ->insert([
            'connection' => $this->connection,
            'queue' => $this->getName(),
            'payload' => serialize(clone $job),
            'exception' => $exception,
            'failed_at' => \Carbon\Carbon::now()
          ]);
        $this->processed($job); //finish the job
      }
      else {

        if($job->getStorageDriver() == 'mongodb')
          $identifier = '_id';
        else
          $identifier = 'id';

        //increment attempt and presist
        DB::connection($this->connection)
          ->table('jobs')
          ->where($identifier,$job->getId())
          ->update(['attempts'=>$job->attempt]);
        //
        if ($job->retryAfter > 0)
          sleep($job->retryAfter);
      }
    }

}
