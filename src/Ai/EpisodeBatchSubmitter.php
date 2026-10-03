<?php

declare(strict_types=1);

namespace App\Ai;

use App\Entity\EpisodeSegment;
use App\Workflow\EpisodeSegmentFlow;
use Doctrine\ORM\EntityManagerInterface;
use Survos\StateBundle\Attribute\AsBatchTransitionListener;
use Survos\StateBundle\Event\BatchTransitionEvent;
use Tacman\AiBatch\Entity\AiBatch;
use Tacman\AiBatch\Model\BatchRequest;
use Tacman\AiBatch\Service\BatchClients;

/** Mediary's batch-transition pattern: submit once, then lock until the scheduler lands results. */
final readonly class EpisodeBatchSubmitter
{
    public const string KIND = 'episode_segments';

    public function __construct(private EpisodeSummaryTask $task, private BatchClients $clients, private EntityManagerInterface $em) {}

    #[AsBatchTransitionListener(EpisodeSegmentFlow::WORKFLOW_NAME, EpisodeSegmentFlow::TRANSITION_AI_TASK)]
    public function __invoke(BatchTransitionEvent $event): void
    {
        $segments = $requests = [];
        foreach ($event->subjects as $segment) {
            assert($segment instanceof EpisodeSegment);
            if ($segment->aiBatchId !== null) { continue; }
            if (($segment->pendingSteps['ai_task'][0] ?? null) !== EpisodeSummaryTask::TASK) {
                $event->release($segment);
                continue;
            }
            $request = $this->task->batchRequest(EpisodeSummaryRunner::subjectFor($segment));
            $requests[] = BatchRequest::raw($segment->id, $request['endpoint'], $request['body']);
            $segments[] = $segment;
        }
        if ($requests === []) { return; }

        // A refused batch stays pending: never silently switch a paid batch to sync calls.
        $job = $this->clients->get($this->task->batchProvider())->submitBatch($requests, [
            'metadata' => ['app' => 'no-agenda', 'kind' => self::KIND, 'task' => EpisodeSummaryTask::TASK],
        ]);
        $batch = new AiBatch();
        $batch->provider = $this->task->batchProvider();
        $batch->task = EpisodeSummaryTask::TASK;
        $batch->requestCount = count($requests);
        $batch->markSubmitted($job->id, $job->inputFileId ?? '');
        $batch->meta = ['kind' => self::KIND, 'model' => $requests[0]->model,
            'segments' => array_map(static fn (EpisodeSegment $s) => $s->id, $segments),
            'requests' => array_map(static fn (BatchRequest $r) => $r->toMistralLine(), $requests)];
        $this->em->persist($batch);
        $this->em->flush();
        foreach ($segments as $segment) { $segment->aiBatchId = $batch->id; }
        $this->em->flush();
    }
}
