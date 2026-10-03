<?php

declare(strict_types=1);

namespace App\Entity;

use App\Ai\EpisodeSummaryTask;
use App\Workflow\EpisodeSegmentFlow;
use Doctrine\ORM\Mapping as ORM;
use Survos\AiWorkflowBundle\Traits\PendingStepsInterface;
use Survos\AiWorkflowBundle\Traits\PendingStepsTrait;
use Survos\StateBundle\Traits\MarkingInterface;
use Survos\StateBundle\Traits\MarkingTrait;

#[ORM\Entity]
#[ORM\Table(name: 'na_episode_segment')]
#[ORM\Index(columns: ['episode_id', 'position'])]
class EpisodeSegment implements MarkingInterface, PendingStepsInterface, \Stringable
{
    use MarkingTrait;
    use PendingStepsTrait;

    public const string WORKFLOW = EpisodeSegmentFlow::WORKFLOW_NAME;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $denseSummary = null;

    #[ORM\Column(options: ['default' => false])]
    public bool $workflowLocked = false;

    #[ORM\Column(length: 64, nullable: true)]
    public ?string $runSubjectId = null;

    #[ORM\Column(nullable: true)]
    public ?int $aiBatchId = null;

    public function __construct(
        #[ORM\Id, ORM\Column(length: 24)] public string $id,
        #[ORM\ManyToOne(targetEntity: Episode::class), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] public Episode $episode,
        #[ORM\Column] public int $position,
        /** Source timestamps, caption indices, chapter metadata and immutable transcript text. */
        #[ORM\Column(type: 'json')] public array $source,
    ) {
        $this->marking = EpisodeSegmentFlow::PLACE_AI_READY;
        $this->pendingSteps[EpisodeSegmentFlow::TRANSITION_AI_TASK] = [EpisodeSummaryTask::TASK];
    }

    public function getId(): string { return $this->id; }
    public function __toString(): string { return $this->episode->getCode().' · '.($this->source['chapter_title'] ?? 'Segment '.($this->position + 1)); }
}
