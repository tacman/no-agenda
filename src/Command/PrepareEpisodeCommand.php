<?php

namespace App\Command;

use App\Crawling\EpisodeProcessor;
use App\Repository\EpisodeRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'prepare', description: 'Prepare an episode for publication')]
class PrepareEpisodeCommand extends Command
{
    public function __construct(
        private readonly EpisodeRepository $episodeRepository,
        private readonly EpisodeProcessor $episodeProcessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDefinition([
            new InputArgument('episode', InputArgument::OPTIONAL, 'The episode code to crawl'),
            new InputOption('all', null, InputOption::VALUE_NONE, 'Prepare every unpublished episode -- run synchronously in this process, no messenger queue/worker involved'),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        if ($input->getOption('all')) {
            $episodes = $this->episodeRepository->findBy(['published' => false]);

            if (!$episodes) {
                $style->success('No unpublished episodes found.');

                return Command::SUCCESS;
            }

            $failures = 0;

            foreach ($episodes as $episode) {
                $style->writeln(sprintf('Preparing episode %s...', $episode->getCode()));

                try {
                    $this->episodeProcessor->prepare($episode);
                } catch (\Throwable $exception) {
                    $failures++;
                    $style->warning(sprintf('Failed to prepare episode %s: %s', $episode->getCode(), $exception->getMessage()));
                }
            }

            if ($failures > 0) {
                $style->warning(sprintf('Prepared %d episode(s), %d failure(s).', count($episodes) - $failures, $failures));
            } else {
                $style->success(sprintf('Prepared %d episode(s).', count($episodes)));
            }

            return Command::SUCCESS;
        }

        if (!$code = $input->getArgument('episode')) {
            $style->error('Specify an episode code or use --all.');

            return Command::INVALID;
        }

        $episode = $this->episodeRepository->findOneByCode($code);

        if (!$episode) {
            $style->warning(sprintf('Invalid episode code: %s', $code));

            return Command::INVALID;
        }

        $this->episodeProcessor->prepare($episode);

        return Command::SUCCESS;
    }
}
