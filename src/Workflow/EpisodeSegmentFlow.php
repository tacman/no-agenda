<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\EpisodeSegment;
use Survos\StateBundle\Attribute\Place;
use Survos\StateBundle\Attribute\Transition;
use Survos\StateBundle\Attribute\Workflow;

#[Workflow(supports: [EpisodeSegment::class], name: self::WORKFLOW_NAME)]
final class EpisodeSegmentFlow
{
    public const string WORKFLOW_NAME = 'episode_segment';

    #[Place(initial: true, info: 'Run the segment AI task queue', next: [self::TRANSITION_AI_TASK, self::TRANSITION_AI_DONE])]
    public const string PLACE_AI_READY = 'ai_ready';

    #[Place(info: 'Segment analysis ready for episode synthesis and retrieval')]
    public const string PLACE_COMPLETE = 'complete';

    #[Transition(from: self::PLACE_AI_READY, to: self::PLACE_AI_READY, async: true, batch: 29,
        guard: "subject.pendingCount('ai_task') > 0 and not subject.workflowLocked")]
    public const string TRANSITION_AI_TASK = 'ai_task';

    #[Transition(from: self::PLACE_AI_READY, to: self::PLACE_COMPLETE, async: true,
        guard: "subject.pendingCount('ai_task') == 0 and not subject.workflowLocked")]
    public const string TRANSITION_AI_DONE = 'ai_done';
}
