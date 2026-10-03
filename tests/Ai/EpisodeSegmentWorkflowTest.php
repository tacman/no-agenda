<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\EpisodeSummaryTask;
use App\Entity\Episode;
use App\Entity\EpisodeSegment;
use App\Workflow\EpisodeFlow;
use App\Workflow\EpisodeSegmentFlow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Registry;

final class EpisodeSegmentWorkflowTest extends KernelTestCase
{
    public function testFlushQueuesSegmentAndParentWaitsForEverySummary(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $episode = $em->getRepository(Episode::class)->findOneBy(['code' => '1598']);
            self::assertNotNull($episode);
            $episode->marking = EpisodeFlow::PLACE_AI_READY;
            $episode->pendingSteps = ['ai_task' => [EpisodeSummaryTask::TASK]];
            $segment = new EpisodeSegment('workflowtest000000000001', $episode, 0, ['text' => 'Test source', 'start' => 0, 'end' => 10, 'chapter_title' => 'Test']);
            $em->persist($segment);
            $em->flush();
            self::assertSame(1, (int) $connection->fetchOne("SELECT count(*) FROM messenger_messages WHERE queue_name='episode.segment.ai.task' AND body LIKE :id", ['id' => '%'.$segment->id.'%']));
            $workflow = self::getContainer()->get(Registry::class)->get($episode, EpisodeFlow::WORKFLOW_NAME);
            self::assertFalse($workflow->can($episode, EpisodeFlow::TRANSITION_AI_TASK));
            $segment->marking = EpisodeSegmentFlow::PLACE_COMPLETE;
            self::assertFalse($workflow->can($episode, EpisodeFlow::TRANSITION_AI_TASK), 'Completion without a summary must not unlock synthesis.');
            $segment->denseSummary = 'Completed summary.';
            $em->flush();
            self::assertTrue($workflow->can($episode, EpisodeFlow::TRANSITION_AI_TASK));
            $segment->marking = EpisodeSegmentFlow::PLACE_AI_READY;
            $segment->pendingSteps = [];
            $segmentFlow = self::getContainer()->get(Registry::class)->get($segment, EpisodeSegmentFlow::WORKFLOW_NAME);
            $segmentFlow->apply($segment, EpisodeSegmentFlow::TRANSITION_AI_DONE, ['cascade' => 'none']);
            self::assertSame(EpisodeSegmentFlow::PLACE_COMPLETE, $segment->marking);
            self::assertGreaterThan(0, (int) $connection->fetchOne("SELECT count(*) FROM messenger_messages WHERE queue_name='episode.ai.task'"));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }
}
