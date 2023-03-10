<?php
namespace Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigClear extends Command
{
  protected function configure()
  {
      $this
        // the name of the command (the part after "bin/console")
        ->setName('config:clear')

        // the short description shown while running "php bin/console list"
        ->setDescription('Delete configuration cache file.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp('This command deletes configuration cache file: boostrap/cache/config.php')
      ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $f = ROOT.'bootstrap'.DS.'cache'.DS.'config.php';
    if (is_file($f)){
        unlink($f);
    }
    $output->writeLn("<info>Configuration cache cleared!</info>");

  }
}
