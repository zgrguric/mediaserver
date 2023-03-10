<?php
namespace Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCache extends Command
{
  protected function configure()
  {
      $this
        // the name of the command (the part after "bin/console")
        ->setName('config:cache')

        // the short description shown while running "php bin/console list"
        ->setDescription('Regenerate configuration cache file.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp('This command takes all configuration variables and creates compiled php file in boostrap/cache/config.php')
      ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {

    if (file_exists(ROOT . '.env')) {
        $dotenv = new \Dotenv\Dotenv(ROOT);
        $dotenv->load();
    }
    else
      die('.env file is missing');

    $f = ROOT.'bootstrap'.DS.'cache'.DS.'config.php';


    $settings = [];
    $_dir = scandir(ROOT . 'config/');
    foreach($_dir as $_f)
    {
      if (ends_with($_f,'.php'))
      {

        $_f_arr = require ROOT . 'config/'.$_f;
        $settings = array_merge_recursive($settings,[basename($_f,'.php') => $_f_arr]);
        unset($_f_arr);
        unset($_f);
      }
    }
    unset($_f_arr);
    unset($_f);
    unset($_dir);

    if (is_file($f)){
        $output->writeLn("<info>Configuration cache cleared!</info>");
        unlink($f);
    }

    file_put_contents($f,'<?php return '.var_export($settings, true).';');
    $output->writeLn("<info>Configuration cache generated!</info>");
  }
}
