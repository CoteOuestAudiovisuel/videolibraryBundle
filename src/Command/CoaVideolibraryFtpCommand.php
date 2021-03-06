<?php

namespace Coa\VideolibraryBundle\Command;

use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class CoaVideolibraryFtpCommand extends Command
{
    protected static $defaultName = 'coa:videolibrary:ftp';
    protected static $defaultDescription = 'commande de synchronisation des fichiers upload par ftp';
    private CoaVideolibraryService $coaVideolibrary;

    public function __construct(string $name = null, CoaVideolibraryService $coaVideolibrary)
    {
        parent::__construct($name);
        $this->coaVideolibrary = $coaVideolibrary;
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Synchronisation Upload video par FTP");
        $this->coaVideolibrary->FtpSync();
        $io->success("Fin de l'operation de synchronisation FTP");
        return Command::SUCCESS;
    }
}
