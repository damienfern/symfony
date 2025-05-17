<?php

namespace Symfony\Component\AssetMapper\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsCommand('assets:dev-serve', description: 'Send an SSE Message every time a file is changed')]
class AssetNotifyChangeCommand extends Command
{
    public function __construct(
        private HubInterface $hub
    ) {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            // configure an argument
            ->addArgument('filePath', InputArgument::REQUIRED, 'The absolute path of the file changed.')
            // ...
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('filePath');
        if (!file_exists($filePath)) {
            $output->writeln("<comment>File does not exist: $filePath, skipped</comment>");
            return Command::FAILURE;
        }

        // Check if the file is a JS file
        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'js') {
            $output->writeln("<comment>File is not a JS file: $filePath, skipped</comment>");
            return Command::FAILURE;
        }

        // get versioned path from server

        $this->hub->publish(new Update(
            topics: 'symfony-hmr',
            data: json_encode([
                'filePath' => $filePath,
            ]),
            private: false,
        ));

        return Command::SUCCESS;
    }

}
