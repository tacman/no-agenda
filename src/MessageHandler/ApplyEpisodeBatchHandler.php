<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Ai\EpisodeBatchSubmitter;
use App\Ai\EpisodeSummaryRunner;
use App\Ai\EpisodeSummaryTask;
use App\Entity\EpisodeSegment;
use App\Workflow\EpisodeSegmentFlow;
use Doctrine\ORM\EntityManagerInterface;
use Survos\ClaimsBundle\Service\ClaimIngestor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;
use Tacman\AiBatch\Entity\AiBatch;
use Tacman\AiBatch\Message\ApplyBatchResultsMessage;
use Tacman\AiBatch\Model\BatchResult;

#[AsMessageHandler]
final readonly class ApplyEpisodeBatchHandler
{
    public function __construct(private EntityManagerInterface $em, private EpisodeSummaryTask $task,
        private ClaimIngestor $claims, private Registry $workflows) {}

    public function __invoke(ApplyBatchResultsMessage $message): void
    {
        $batch = $this->em->find(AiBatch::class, $message->aiBatchId);
        if (!$batch || $batch->status === 'applied' || ($batch->meta['kind'] ?? null) !== EpisodeBatchSubmitter::KIND) { return; }
        $contents = file_get_contents($batch->savedResultPath);
        if (!hash_equals($batch->meta['resultArchive']['sha256'], hash('sha256', $contents))) {
            throw new \RuntimeException('Batch archive checksum mismatch.');
        }
        $results = [];
        foreach (explode("\n", $contents) as $line) {
            if (trim($line) === '') { continue; }
            $result = BatchResult::fromProviderLine($batch->provider, json_decode($line, true, flags: JSON_THROW_ON_ERROR));
            if (!isset($results[$result->customId]) || $result->success) { $results[$result->customId] = $result; }
        }
        foreach ($batch->meta['segments'] as $id) {
            $segment = $this->em->find(EpisodeSegment::class, $id);
            if (!$segment || $segment->aiBatchId !== $batch->id) { continue; }
            $result = $results[$id] ?? null;
            try {
                if (!$result?->success || $result->body === null) {
                    throw new \UnexpectedValueException($result?->error ?? 'No result returned for this segment.');
                }
                $subject = EpisodeSummaryRunner::subjectFor($segment);
                $parsed = $this->task->batchResult($subject, $result->body);
                $summary = trim((string) ($parsed->meta?->response['dense_summary'] ?? ''));
                if ($summary === '') { throw new \UnexpectedValueException('Empty batch summary.'); }
            } catch (\UnexpectedValueException|\JsonException $e) {
                $batch->meta['errors'][$id] = $e->getMessage();
                // Retain the task for an explicit retry; never launch an automatic paid retry.
                $segment->workflowLocked = false;
                $segment->aiBatchId = null;
                $this->em->flush();
                continue;
            }
            // Keep the local run and each provider batch independently reviewable.
            $this->claims->record(null, 'episode', $subject->getWorkflowSubjectId(),
                $batch->task.'@batch-'.$batch->id, $parsed->claims, $parsed->meta);
            $this->claims->flush();
            $segment->denseSummary = $summary;
            $segment->runSubjectId = $subject->getWorkflowSubjectId();
            $segment->shiftPendingStep('ai_task');
            $segment->workflowLocked = false;
            $segment->aiBatchId = null;
            ++$batch->appliedCount;
            $this->workflows->get($segment, EpisodeSegmentFlow::WORKFLOW_NAME)
                ->apply($segment, EpisodeSegmentFlow::TRANSITION_AI_DONE, ['cascade' => 'none']);
            $this->em->flush();
        }
        $batch->status = 'applied';
        $this->em->flush();
    }
}
