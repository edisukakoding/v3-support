<?php

namespace Esikat\Helper\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tembung\Tembung;

class MotivasiCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('motivasi')
            ->setDescription('Menampilkan kata-kata motivasi.')
            ->setHelp('Command ini akan mencetak Kata kata motiviasi untuk kamu.');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(Tembung::say());
        
        return Command::SUCCESS;
    }
}