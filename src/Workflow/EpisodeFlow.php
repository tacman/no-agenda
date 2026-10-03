<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Episode;
use Survos\StateBundle\Attribute\Place;
use Survos\StateBundle\Attribute\Transition;
use Survos\StateBundle\Attribute\Workflow;

/** Episode lifecycle; individual AI tasks live in the persisted queue, not in the graph. */
#[Workflow(supports: [Episode::class], name: self::WORKFLOW_NAME)]
final class EpisodeFlow
{
    public const string WORKFLOW_NAME = 'episode';

    #[Place(initial: true, info: 'Episode registered', next: [self::TRANSITION_PREPARE])]
    public const string PLACE_NEW = 'new';

    #[Place(info: 'Source material prepared; run the dynamic AI task queue', next: [self::TRANSITION_AI_TASK, self::TRANSITION_AI_DONE])]
    public const string PLACE_AI_READY = 'ai_ready';

    #[Place(info: 'Analysis complete and ready for display')]
    public const string PLACE_COMPLETE = 'complete';

    #[Transition(from: self::PLACE_NEW, to: self::PLACE_AI_READY, info: 'Prepare transcript, chapters and initial tasks', async: true)]
    public const string TRANSITION_PREPARE = 'prepare';

    #[Transition(
        from: self::PLACE_AI_READY,
        to: self::PLACE_AI_READY,
        info: 'Run one pending AI task; tasks may append further tasks',
        guard: "subject.pendingCount('ai_task') > 0 and not subject.workflowLocked",
        async: true,
    )]
    public const string TRANSITION_AI_TASK = 'ai_task';

    #[Transition(
        from: self::PLACE_AI_READY,
        to: self::PLACE_COMPLETE,
        info: 'Publish completed analysis once the task queue is empty',
        guard: "subject.pendingCount('ai_task') == 0 and not subject.workflowLocked",
        async: true,
    )]
    public const string TRANSITION_AI_DONE = 'ai_done';
}
