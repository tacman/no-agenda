<?php

declare(strict_types=1);

namespace App\Ai;

use App\Entity\Episode;
use App\Entity\EpisodeSegment;
use Doctrine\ORM\EntityManagerInterface;
use Survos\AiWorkflowBundle\Entity\Subject;
use Survos\AiWorkflowBundle\Task\TaskRunner;
use Survos\ClaimsBundle\Entity\ClaimRun;

/** Project the existing ai-workflow result; timing and claims remain owned by TaskRunner. */
final readonly class EpisodeSummaryRunner
{
    public function __construct(private TaskRunner $runner, private EntityManagerInterface $em) {}

    public function run(Episode|EpisodeSegment $entity, string $text, array $context = []): void
    {
        $subject = self::subjectFor($entity, $text, $context);
        $subjectId = $subject->getWorkflowSubjectId();
        $subject->workflowLocked = $entity->workflowLocked;
        $subject->pendingSteps = $entity->pendingSteps;
        $name = $entity->pendingSteps['ai_task'][0] ?? throw new \LogicException('No pending AI task.');
        $criteria = ['subjectType' => 'episode', 'subjectId' => $subjectId, 'source' => $name.'@1.0'];
        $runs = $this->em->getRepository(ClaimRun::class);
        $previousRun = $runs->findOneBy($criteria, ['createdAt' => 'DESC']);
        $this->runner->runNext($subject, 'ai_task');
        $this->em->flush();
        $run = $runs->findOneBy($criteria, ['createdAt' => 'DESC']);
        if ($run === null || $run === $previousRun) {
            throw new \RuntimeException('AI task failed; see ai-workflow logs for '.$subjectId.'. Pending work retained.');
        }
        if ($name === EpisodeSummaryTask::TASK) {
            $summary = trim((string) ($run->response['dense_summary'] ?? ''));
            if ($summary === '') { throw new \UnexpectedValueException('AI returned an empty summary.'); }
            if ($entity instanceof Episode && count(preg_split('/\s+/u', $summary, flags: PREG_SPLIT_NO_EMPTY)) < 80) {
                throw new \UnexpectedValueException('Episode synthesis is too short to be a substantive overview; pending task retained.');
            }
            $entity->denseSummary = $summary;
            if ($entity instanceof EpisodeSegment) { $entity->runSubjectId = $subjectId; }
        }
        foreach (['observe', 'analyze'] as $phase) {
            while ($next = $subject->shiftPendingStep($phase)) { $subject->addPendingStep($next, 'ai_task'); }
            unset($subject->pendingSteps[$phase]);
        }
        $entity->pendingSteps = $subject->pendingSteps;
    }

    public static function subjectFor(Episode|EpisodeSegment $entity, ?string $text = null, array $context = []): Subject
    {
        $episode = $entity instanceof EpisodeSegment ? $entity->episode : $entity;
        $subject = new Subject('episode', $episode->getId().':'.($entity instanceof EpisodeSegment ? $entity->id : 'summary'));
        if ($entity instanceof EpisodeSegment) {
            $text ??= $entity->source['text'];
            $context += ['segmentId' => $entity->id, 'chapter' => $entity->source['chapter_title'] ?? null,
                'start' => $entity->source['start'], 'end' => $entity->source['end']];
        }
        $subject->data = ['title' => $episode->getName(), 'episode' => $episode->getCode(), 'text' => $text] + $context;
        return $subject;
    }
}
