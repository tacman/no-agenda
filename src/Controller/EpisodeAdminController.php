<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Episode;
use Benlipp\SrtParser\Parser;
use App\Entity\EpisodeSegment;
use Doctrine\ORM\EntityManagerInterface;
use Survos\ClaimsBundle\Entity\ClaimRun;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EpisodeAdminController extends AbstractController
{
    #[Route('/admin/episodes', name: 'episode_admin', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/index.html.twig');
    }
    #[Route('/admin/episodes/{code}', name: 'episode_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['code' => 'code'])] Episode $episode, EntityManagerInterface $em): Response
    {
        $chapters = $episode->hasChapters()
            ? json_decode(file_get_contents($episode->getChaptersPath()), true, flags: JSON_THROW_ON_ERROR)['chapters'] ?? []
            : [];
        $runs = $em->getRepository(ClaimRun::class)->createQueryBuilder('r')
            ->where('r.subjectType = :type')
            ->andWhere('r.subjectId = :id OR r.subjectId LIKE :prefix')
            ->setParameter('type', 'episode')
            ->setParameter('id', (string) $episode->getId())
            ->setParameter('prefix', $episode->getId().':%')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()->getResult();
        $segmentRuns = [];
        foreach ($runs as $run) {
            $key = str_contains($run->subjectId, ':') ? $run->subjectId : $run->source;
            $index = substr($key, strrpos($key, ':') + 1);
            if (ctype_digit($index)) {
                $segmentRuns[(int) $index] ??= $run;
            }
        }
        $segmentEntities = $em->getRepository(EpisodeSegment::class)->findBy(['episode' => $episode], ['position' => 'ASC']);
        $analysis = ['segments' => array_map(static fn (EpisodeSegment $s) => $s->source + ['id' => $s->id, 'summary' => $s->denseSummary, 'run_subject_id' => $s->runSubjectId, 'marking' => $s->marking], $segmentEntities)];
        foreach ($analysis['segments'] as $index => $segment) {
            if (!isset($segment['run_subject_id'])) { continue; }
            foreach ($runs as $run) {
                if ($run->subjectId === $segment['run_subject_id']) { $segmentRuns[$index] = $run; break; }
            }
        }
        $captions = $episode->hasTranscript()
            ? (new Parser())->loadString(file_get_contents($episode->getTranscriptPath()))->parse()
            : [];
        $transcripts = [];
        foreach ($analysis['segments'] as $index => $segment) {
            $transcripts[$index] = array_filter($captions, static fn ($caption) => $caption->startTime >= $segment['start'] && $caption->startTime < $segment['end']);
        }
        return $this->render('admin/episode_show.html.twig', [
            'episode' => $episode,
            'chapters' => $chapters,
            'analysis' => $analysis,
            'pendingTasks' => $episode->pendingCount('ai_task') + array_sum(array_map(static fn (EpisodeSegment $s) => $s->pendingCount('ai_task'), $segmentEntities)),
            'transcripts' => $transcripts,
            'runs' => $runs,
            'segmentRuns' => $segmentRuns,
        ]);
    }
}
