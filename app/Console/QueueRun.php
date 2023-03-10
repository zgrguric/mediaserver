<?php
//https://github.com/mariano/slim-sample-app
namespace Console;
use Disque\Queue\JobInterface;
use Domain\Event\EventInterface;
#use Infrastructure\Queue\EventJob;
use Ncx\Queue\BaseJob;
use Ncx\Queue\WorkerCommand;
use Ncx\Queue\QueueInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Ncx\Queue\Queue;

class QueueRun extends WorkerCommand
{
    /**
     * Create instance
     *
     * @param QueueInterface $queue Queue
     */
    public function __construct()
    {
        $queue = new Queue;
        parent::__construct($queue);
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption(
            'queue',
            null,
            InputOption::VALUE_OPTIONAL,
            'Set queue name to run. Defaults to default',
            'default'
        );
        $this->setName('queue:run')
            ->setDescription('Process event jobs');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
      $queueName = $input->getOption('queue');
      $this->queue->setName($queueName);
      parent::initialize($input,$output);
    }

    /**
     * Work on a job
     *
     * @param JobInterface $job Job
     * @return void
     */
    protected function work(\Ncx\Queue\BaseJob $job)
    {
        if (!($job instanceof BaseJob)) {
            throw new InvalidArgumentException('Not an BaseJob extended Job object');
        }
        $job->handle();
    }
}
