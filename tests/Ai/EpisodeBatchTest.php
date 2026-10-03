<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\EpisodeBatchSubmitter;
use App\Ai\EpisodeSummaryTask;
use App\Entity\Episode;
use App\Entity\EpisodeSegment;
use App\MessageHandler\ApplyEpisodeBatchHandler;
use App\MessageHandler\PollEpisodeBatchesHandler;
use App\Workflow\EpisodeSegmentFlow;
use Doctrine\ORM\EntityManagerInterface;
use Survos\StateBundle\Event\BatchTransitionEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Registry;
use Tacman\AiBatch\Contract\BatchCapablePlatformInterface;
use Tacman\AiBatch\Entity\AiBatch;
use Tacman\AiBatch\Message\ApplyBatchResultsMessage;
use Tacman\AiBatch\Message\PollBatchesMessage;
use Tacman\AiBatch\Model\BatchJob;
use Tacman\AiBatch\Model\BatchResult;
use Tacman\AiBatch\Service\BatchClients;

final class EpisodeBatchTest extends KernelTestCase
{
    public function testBatchLocksArchivesAppliesAndReplaysWithoutAnotherRequest(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->getConnection()->beginTransaction();
        $archive = sys_get_temp_dir().'/episode-batch-test-'.bin2hex(random_bytes(5));
        try {
            $episode = $em->getRepository(Episode::class)->findOneBy(['code' => '1598']);
            $segments = [];
            foreach (['one', 'two'] as $i => $id) {
                $s = new EpisodeSegment('batch-test-'.$id, $episode, $i, ['text' => 'Transcript about radio.', 'start' => $i * 10, 'end' => ($i + 1) * 10, 'chapter_title' => 'Radio']);
                $s->denseSummary = 'Existing local result.';
                $em->persist($s);
                $segments[] = $s;
            }
            $em->flush();
            $client = $this->createMock(BatchCapablePlatformInterface::class);
            $client->expects(self::once())->method('submitBatch')->with(self::callback(function (array $requests): bool {
                self::assertCount(2, $requests);
                self::assertSame(EpisodeSummaryTask::BATCH_MODEL, $requests[0]->model);
                return true;
            }), self::anything())->willReturn(new BatchJob('test-job', 'in_progress', 'mistral'));
            $clients = new BatchClients(new ServiceLocator(['mistral' => fn () => $client]));
            $submit = new EpisodeBatchSubmitter($container->get(EpisodeSummaryTask::class), $clients, $em);
            $event = new BatchTransitionEvent('episode_segment', 'ai_task', $segments);
            $submit($event);
            $submit($event); // Already attached to a job: no duplicate submission.
            $batch = $em->find(AiBatch::class, $segments[0]->aiBatchId);
            $registry = $container->get(Registry::class);
            foreach ($segments as $s) {
                $registry->get($s, 'episode_segment')->apply($s, 'ai_task', ['batched' => true, 'cascade' => 'none']);
                self::assertTrue($s->workflowLocked);
                self::assertSame('Existing local result.', $s->denseSummary);
            }
            $client->expects(self::once())->method('checkBatch')->willReturn(new BatchJob('test-job', 'completed', 'mistral', totalCount: 2, completedCount: 1, failedCount: 1));
            $client->expects(self::once())->method('fetchResults')->willReturn([
                BatchResult::fromMistralLine(['custom_id' => $segments[0]->id, 'response' => ['status_code' => 200, 'body' => [
                    'model' => EpisodeSummaryTask::BATCH_MODEL, 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"dense_summary":"A new summary of the radio discussion."}']]],
                    'usage' => ['prompt_tokens' => 42, 'completion_tokens' => 12],
                ]]]),
                BatchResult::fromMistralLine(['custom_id' => $segments[1]->id, 'error' => ['message' => 'Test failure', 'code' => 'test']]),
            ]);
            $poll = new PollEpisodeBatchesHandler($em, $clients, $container->get(MessageBusInterface::class), new Filesystem(), $archive);
            $poll(new PollBatchesMessage());
            self::assertSame('applied', $batch->status);
            self::assertSame(1, $batch->appliedCount);
            self::assertSame('complete', $segments[0]->marking);
            self::assertFalse($segments[0]->workflowLocked);
            self::assertSame('A new summary of the radio discussion.', $segments[0]->denseSummary);
            self::assertSame('Existing local result.', $segments[1]->denseSummary);
            self::assertFalse($segments[1]->workflowLocked);
            self::assertSame(1, $segments[1]->pendingCount('ai_task'));
            self::assertArrayHasKey($segments[1]->id, $batch->meta['errors']);
            self::assertFileExists($batch->savedResultPath);
            $container->get(ApplyEpisodeBatchHandler::class)(new ApplyBatchResultsMessage($batch->id));
            $poll(new PollBatchesMessage());
            self::assertSame(1, $batch->appliedCount);
        } finally {
            $em->getConnection()->rollBack();
            $em->clear();
            (new Filesystem())->remove($archive);
        }
    }
}
