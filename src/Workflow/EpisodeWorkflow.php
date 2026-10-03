<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Ai\EpisodeSegments;
use App\Ai\EpisodeSummaryRunner;
use App\Ai\EpisodeSummaryTask;
use App\Entity\Episode;
use App\Crawling\EpisodeChaptersCrawler;
use App\Crawling\EpisodeTranscriptCrawler;
use App\Entity\EpisodeSegment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

final readonly class EpisodeWorkflow
{
    public function __construct(private EpisodeSegments $segments, private EpisodeSummaryRunner $runner, private EntityManagerInterface $em, private EpisodeChaptersCrawler $chapters, private EpisodeTranscriptCrawler $transcripts) {}

    #[AsTransitionListener(EpisodeFlow::WORKFLOW_NAME, EpisodeFlow::TRANSITION_PREPARE)]
    public function prepare(TransitionEvent $event): void
    {
        $episode = $this->episode($event);
        if (!$episode->hasChapters() && $episode->getChaptersUri()) { $this->chapters->crawl($episode); }
        if (!$episode->hasTranscript() && $episode->getTranscriptUri()) { $this->transcripts->crawl($episode); }
        $this->segments->prepare($episode);
        $episode->pendingSteps[EpisodeFlow::TRANSITION_AI_TASK] = [EpisodeSummaryTask::TASK];
        // Segment postPersist/postFlush kickoff is supplied by state-bundle.
    }

    #[AsGuardListener(EpisodeFlow::WORKFLOW_NAME, EpisodeFlow::TRANSITION_AI_TASK)]
    public function waitForSegments(GuardEvent $event): void
    {
        $segments = $this->em->getRepository(EpisodeSegment::class)->findBy(['episode' => $event->getSubject()]);
        if ($segments === []) { $event->setBlocked(true, 'Prepare transcript segments first.'); return; }
        foreach ($segments as $segment) {
            if ($segment->marking !== EpisodeSegmentFlow::PLACE_COMPLETE || !$segment->denseSummary) {
                $event->setBlocked(true, 'Waiting for all segment summaries.');
                return;
            }
        }
    }

    #[AsTransitionListener(EpisodeFlow::WORKFLOW_NAME, EpisodeFlow::TRANSITION_AI_TASK)]
    public function summarize(TransitionEvent $event): void
    {
        $episode = $this->episode($event);
        $segments = $this->em->getRepository(EpisodeSegment::class)->findBy(['episode' => $episode], ['position' => 'ASC']);
        $text = implode("\n\n", array_map(static fn (EpisodeSegment $s) => ($s->source['chapter_title'] ?? 'Transcript')." [".$s->source['start']."–".$s->source['end']." seconds]\n".$s->denseSummary, $segments));
        $this->runner->run($episode, $text);
    }

    private function episode(TransitionEvent $event): Episode
    {
        $subject = $event->getSubject();
        return $subject instanceof Episode ? $subject : throw new \LogicException('Expected an Episode.');
    }
}
