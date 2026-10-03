<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Ai\EpisodeSummaryRunner;
use App\Entity\Episode;
use App\Entity\EpisodeSegment;
use Doctrine\ORM\EntityManagerInterface;
use Survos\StateBundle\Message\TransitionMessage;
use Survos\StateBundle\Service\AsyncQueueLocator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

final readonly class EpisodeSegmentWorkflow
{
    public function __construct(private EpisodeSummaryRunner $runner, private EntityManagerInterface $em, private MessageBusInterface $bus, private AsyncQueueLocator $queues) {}

    #[AsTransitionListener(EpisodeSegmentFlow::WORKFLOW_NAME, EpisodeSegmentFlow::TRANSITION_AI_TASK)]
    public function summarize(TransitionEvent $event): void
    {
        $segment = $event->getSubject();
        assert($segment instanceof EpisodeSegment);
        if (($event->getContext()['batched'] ?? false) && $segment->aiBatchId !== null) {
            $segment->workflowLocked = true;
            return;
        }
        $this->runner->run($segment, $segment->source['text'], [
            'segmentId' => $segment->id, 'chapter' => $segment->source['chapter_title'],
            'start' => $segment->source['start'], 'end' => $segment->source['end'],
        ]);
    }

    #[AsCompletedListener(EpisodeSegmentFlow::WORKFLOW_NAME, EpisodeSegmentFlow::TRANSITION_AI_DONE)]
    public function checkEpisode(CompletedEvent $event): void
    {
        $segment = $event->getSubject();
        assert($segment instanceof EpisodeSegment);
        // Persist completion before another worker tests the parent's readiness guard.
        $this->em->flush();
        $message = new TransitionMessage($segment->episode->getId(), Episode::class, EpisodeFlow::TRANSITION_AI_TASK, EpisodeFlow::WORKFLOW_NAME);
        $this->bus->dispatch($message, [...$this->queues->stamps($message), new DispatchAfterCurrentBusStamp()]);
    }
}
