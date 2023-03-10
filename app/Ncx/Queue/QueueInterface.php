<?php
namespace Ncx\Queue;
interface QueueInterface
{
    /**
     * Get an event job from the queue
     *
     * @return EventJob
     */
    public function get();
    /**
     * Get queue name
     *
     * @return string Queue name
     */
    public function getName();
    /**
     * Set queue name
     *
     * @return string Queue name
     */
    public function setName($name);
    /**
     * Marks the given job as processed
     *
     * @param mixed $job Job
     * @return void
     */
    public function processed($job);
}
