<?php

namespace App\Command;

use App\Crawling\CrawlingResult;
use App\Entity\Episode;
use Symfony\Component\Console\Style\StyleInterface;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'enqueue', description: 'Enqueues a crawling job')]
class EnqueueCommand extends CrawlCommand
{
    #[\Override]
    protected function preCrawl(): void {}

    #[\Override]
    protected function postCrawl(array $results, StyleInterface $style): void {}

    #[\Override]
    protected function crawl(string $data, StyleInterface $style): ?CrawlingResult
    {
        $this->crawlingProcessor->enqueue($data);

        $style->success(sprintf('Enqueued crawling of %s.', $data));

        return null;
    }

    #[\Override]
    protected function crawlEpisode(string $data, Episode $episode, StyleInterface $style): ?CrawlingResult
    {
        $this->crawlingProcessor->enqueue($data, $episode);

        $style->success(sprintf('Enqueued crawling of %s for episode %s.', $data, $episode->getCode()));

        return null;
    }
}
