<?php

namespace App\MessageHandler;

use App\Crawling\CrawlingProcessor;
use App\Message\Crawl;
use App\Repository\EpisodeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CrawlHandler implements MessageHandlerInterface
{
    public function __construct(
        private readonly EpisodeRepository $episodeRepository,
        private readonly CrawlingProcessor $crawlingProcessor,
    ) {}

    #[AsMessageHandler]
    public function __invoke(Crawl $message): void
    {
        $episode = $message->episodeCode ? $this->episodeRepository->findOneByCode($message->episodeCode) : null;

        $result = $this->crawlingProcessor->crawl($message->data, $episode, $message->lastModifiedAt, $message->initializedAt);

        if (null !== $result->exception) {
            throw $result->exception;
        }
    }
}
