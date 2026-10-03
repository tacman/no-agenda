<?php

declare(strict_types=1);

namespace App\Ai;

use Survos\AiWorkflowBundle\Task\AbstractPromptTask;
use Survos\AiWorkflowBundle\Task\AnalysisTaskInterface;
use Survos\AiWorkflowBundle\Task\AsTask;
use Survos\AiWorkflowBundle\Task\BatchableTaskInterface;
use Survos\AiWorkflowBundle\Task\TaskResult;
use Survos\DataContracts\Workflow\ContextSubjectInterface;
use Survos\DataContracts\Workflow\WorkflowSubjectInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTask('Summarize a bounded episode segment, or synthesize its completed segment summaries.', self::class)]
final class EpisodeSummaryTask extends AbstractPromptTask implements AnalysisTaskInterface, BatchableTaskInterface
{
    public const string TASK = 'episode_summary';
    public const string BATCH_MODEL = 'mistral-small-2603';

    public function batchProvider(): string { return 'mistral'; }
    public function supportsBatch(WorkflowSubjectInterface $subject): bool { return $this->supports($subject); }
    public function batchRequest(WorkflowSubjectInterface $subject): array
    {
        $request = $this->chatBatchRequest($subject);
        $request['body']['model'] = self::BATCH_MODEL;
        return $request;
    }
    public function batchResult(WorkflowSubjectInterface $subject, array $responseBody): TaskResult
    {
        return $this->chatBatchResult($subject, $responseBody);
    }

    public function __construct(#[Autowire(service: 'ai.agent.episode')] ?AgentInterface $agent = null)
    {
        parent::__construct($agent);
    }

    protected function inputs(WorkflowSubjectInterface $subject): array
    {
        $text = $subject instanceof ContextSubjectInterface ? ($subject->getWorkflowContext()['text'] ?? null) : null;
        return is_string($text) && trim($text) !== '' ? ['text' => $text] : [];
    }

    protected function responseFormatClass(): string { return DenseSummaryResult::class; }
    protected function systemPromptTemplate(): string { return 'ai/episode_summary/system.html.twig'; }
    protected function userPromptTemplate(): string { return 'ai/episode_summary/user.html.twig'; }
}
