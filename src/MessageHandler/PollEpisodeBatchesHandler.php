<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Ai\EpisodeBatchSubmitter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Tacman\AiBatch\Entity\AiBatch;
use Tacman\AiBatch\Message\ApplyBatchResultsMessage;
use Tacman\AiBatch\Message\PollBatchesMessage;
use Tacman\AiBatch\Service\BatchClients;

/** Uses ai-batch-bundle's scheduled message, as Mediary does. */
#[AsMessageHandler]
final readonly class PollEpisodeBatchesHandler
{
    public function __construct(private EntityManagerInterface $em, private BatchClients $clients,
        private MessageBusInterface $bus, private Filesystem $files,
        #[Autowire('%kernel.project_dir%/var/ai-batch')] private string $archiveDir) {}

    public function __invoke(PollBatchesMessage $message): void
    {
        foreach ($this->em->getRepository(AiBatch::class)->findBy(['status' => ['submitted', 'processing', 'completed', 'failed']]) as $batch) {
            if (($batch->meta['kind'] ?? null) !== EpisodeBatchSubmitter::KIND) { continue; }
            if ($batch->savedResultPath === null) {
                $client = $this->clients->get($batch->provider);
                $job = $client->checkBatch($batch->providerBatchId);
                $batch->applyProviderStatus($job->status, $job->completedCount, $job->failedCount, $job->outputFileId, $job->errorFileId);
                $this->em->flush();
                if (!$job->isTerminal()) { continue; }
                $lines = [];
                foreach ($client->fetchResults($job) as $result) {
                    $lines[] = json_encode($result->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
                if (count($lines) < $job->completedCount + $job->failedCount) {
                    throw new \RuntimeException('Incomplete batch download; retry the poll.');
                }
                $contents = implode("\n", $lines);
                $path = $this->archiveDir.'/'.$batch->id.'.jsonl';
                $this->files->dumpFile($path, $contents);
                $hash = hash('sha256', $contents);
                if (!hash_equals($hash, hash_file('sha256', $path))) {
                    throw new \RuntimeException('Batch archive read-back failed.');
                }
                $batch->savedResultPath = $path;
                $batch->meta['resultArchive'] = ['sha256' => $hash, 'lines' => count($lines), 'bytes' => strlen($contents)];
                $this->em->flush();
            }
            $this->bus->dispatch(new ApplyBatchResultsMessage($batch->id));
        }
    }
}
