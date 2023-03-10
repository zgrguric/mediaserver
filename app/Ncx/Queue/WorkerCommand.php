<?php
namespace Ncx\Queue;

use Ncx\Queue\QueueInterface;
use Disque\Queue\JobInterface;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class WorkerCommand extends Command
{
    /**
     * Disque queue
     *
     * @var QueueInterface
     */
    protected $queue;

    /**
     * Output stream
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Do not process more than these number of jobs
     *
     * @var int
     */
    private $limit = 0;

	//private $max_timeout = 240;

    /**
     * Wether to allow any further job processing
     *
     * @var bool
     */
    private $allowJobs = true;

	private $job; //currently loaded Job

    /**
     * Create instance
     * 1
     * @param QueueInterface $queue Queue
     */
    public function __construct(QueueInterface $queue)
    {
        parent::__construct();
        $this->queue = $queue;
    }

    /**
     * Configure command
     * 2
     * @return void
     */
    protected function configure()
    {

        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'If set, do not process more than these number of jobs. Set to 0 for no limit. Defaults to 0',
            0
        );
    }

    /**
     * Initializes the command just after the input has been validated.
     * 3
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {

        $this->output = $output;
        $this->setProcessTitle('[worker] ' . $this->getName());

        //dodano
        if (function_exists('pcntl_async_signals'))
          pcntl_async_signals(true);
        //dodano end
        declare(ticks = 30);
        if ($this->supportsAsyncSignals())
        {
          foreach ([SIGTERM] as $signal) {
            // We will register a signal handler for the alarm signal so that we can kill this
            // process if it is running too long because it has frozen. This uses the async
            // signals supported in recent versions of PHP to accomplish it conveniently.
            if (!pcntl_signal($signal, [$this, 'signal'])) {
                throw new RuntimeException("Could not register signal {$signal}");
            }
            $this->out("Registered for signal {$signal}", OutputInterface::VERBOSITY_VERY_VERBOSE);
          }
          //TODO mozemo staviti timeout na ovaj nacin za pojedini job
          //pcntl_alarm(120); //120 seconds max execution time until signal dies off (z.g.)



        }

    }

    /**
     * Determine if "async" signals are supported.
     * Uzeto s laravela, tako da moze raditi na windowsima. PCNTL je required za ovo!
     * @return bool
     */
    protected function supportsAsyncSignals()
    {
        return extension_loaded('pcntl');
    }

    /**
     * Signal handler. Needs to be public
     *
     * @param string $signal Signal
     * @return void
     */
    public function signal($signal)
    {
        switch ($signal) {
            case SIGTERM:
                $this->out('Received signal to shutdown...', OutputInterface::VERBOSITY_VERBOSE);
                $this->allowJobs = false;
                break;
        }
    }

    /**
     * Register the worker timeout handler.
     *
     * @param  \Illuminate\Contracts\Queue\Job|null  $job
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @return void
     */
    protected function registerTimeoutHandler($job)
    {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, function () {

			       throw new \Exception('Job has timed out');
        });

        pcntl_alarm($job->timeout);
    }

    /**
    * When job finishes (sucess or exception), remove pnctl alarm
    *
    */
    protected function removeTimeoutHandler()
    {
      pcntl_alarm(0);
    }

    /**
     * Kill the process. (laravel)
     *
     * @param  int  $status
     * @return void
     */
    public function kill($status = 0) //not used yet
    {
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }
        exit($status);
    }

    /**
     * Executes the current command.
     * 4
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $limit = $input->getOption('limit');
        if (!is_numeric($limit) || $limit < 0) {
            throw new InvalidArgumentException('Limit should be set to the maximum number of jobs to process, or 0 for no limit');
        }

        $this->limit = $limit ? (int) $limit : 0;

        $jobs = 0;
        $queueName = $this->queue->getName();

        $this->out("Waiting for jobs on queue [<info>{$queueName}</info>]");

        while ($this->allowJobs) {
            $this->job = $this->queue->get();
            if (!$this->job) //none jobs are found in queue
            {
              sleep(7); //sleep for 7 seconds and check queue for a job
              continue;
            }

            if ($this->supportsAsyncSignals())
                $this->registerTimeoutHandler($this->job);

            $className = get_class($this->job);
            $this->out("<comment>Processing #{$this->job->getId()}</comment>: ".$className);
            $this->out("Job #{$this->job->getId()}: " . json_encode($this->job->getBody()), OutputInterface::VERBOSITY_VERY_VERBOSE);
            try {
				//echo 'start work'."\n";
                //todo add timeout somehow with pcntl_alarm ? $job->timeout
                $this->work($this->job);
				//echo 'work done'."\n";
                $this->queue->processed($this->job); //job is done remove from presistance
				//echo 'job is processed'."\n";
                $this->out("<info>Processed  #{$this->job->getId()}:</info> ".$className);

            } catch (Exception $e) {
                $this->out('<error>Failed     #'. $this->job->getId().':</error> [' . $className . '] ' . $e->getMessage());
                $this->out("ERROR Stacktrace: \n\t" . trim(str_replace("\n", "\n\t", $e->getTraceAsString())), OutputInterface::VERBOSITY_VERY_VERBOSE);
                $this->queue->failed($this->job,$e); //will remove from presistance if tries reached
            }
            if ($this->supportsAsyncSignals())
              $this->removeTimeoutHandler();
            $jobs++;
            if ($this->limit > 0 && $jobs === $this->limit) {
                $this->allowJobs = false;
            }
            usleep(50000);
        }
        $this->out("TOTAL jobs processed: {$jobs}");
    }

    /**
     * Output the given string using the given log level
     *
     * @param string $text Text to output
     * @param int $level Log level of message
     */
    protected function out($text, $level = OutputInterface::VERBOSITY_NORMAL)
    {
        if ($this->output->isQuiet() || $level > $this->output->getVerbosity()) {
            return;
        }

        $this->output->writeln('[' . date('Y-m-d H:i:s') . '] ' . $text);
    }

    /**
     * Work on a job
     *
     * @param JobInterface $job Job
     * @return void
     */
    abstract protected function work(\Ncx\Queue\BaseJob $job);
}
