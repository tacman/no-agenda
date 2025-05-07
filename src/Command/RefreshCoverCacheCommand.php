<?php

namespace App\Command;

use App\Repository\EpisodeRepository;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'refresh-cover-cache', description: 'Refreshes the public cache of episode covers')]
class RefreshCoverCacheCommand extends Command
{
    public function __construct(
        private readonly EpisodeRepository $episodeRepository,
        private readonly FilterManager $filterManager,
        private readonly FilterService $filterService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->episodeRepository->findAll() as $episode) {
            if (!$episode->getCoverUri()) {
                continue;
            }

            $this->resolveCoverCache($episode->getCode());
        }

        return Command::SUCCESS;
    }

    private function resolveCoverCache(string $code): void
    {
        $filters = array_keys($this->filterManager->getFilterConfiguration()->all());

        foreach ($filters as $filter) {
            $this->filterService->bustCache("{$code}.png", $filter);
            $this->filterService->getUrlOfFilteredImage("{$code}.png", $filter);
        }
    }
}
