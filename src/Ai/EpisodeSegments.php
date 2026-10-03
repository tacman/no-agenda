<?php

declare(strict_types=1);

namespace App\Ai;

use App\Entity\Episode;
use App\Entity\EpisodeSegment;
use Doctrine\ORM\EntityManagerInterface;
use Benlipp\SrtParser\Parser;

/** Publisher chapters contain bounded, caption-aligned segments for summaries and retrieval. */
final class EpisodeSegments
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function prepare(Episode $episode): void
    {
        if (!$episode->hasTranscript()) {
            throw new \RuntimeException('Episode '.$episode->getCode().' has no local transcript yet.');
        }
        $captions = (new Parser())->loadString(file_get_contents($episode->getTranscriptPath()))->parse();
        $chapters = $episode->hasChapters()
            ? json_decode(file_get_contents($episode->getChaptersPath()), true, flags: JSON_THROW_ON_ERROR)['chapters'] ?? []
            : [];
        $segments = $this->split($captions, $chapters, (string) $episode->getCode());
        if ($segments === []) { throw new \RuntimeException('The transcript contains no usable text.'); }
        foreach ($segments as $position => $source) {
            if ($this->em->find(EpisodeSegment::class, $source['id']) !== null) { continue; }
            $id = $source['id'];
            unset($source['id'], $source['summary']);
            $this->em->persist(new EpisodeSegment($id, $episode, $position, $source));
        }
        $episode->denseSummary = null;
    }

    /** A caption crossing a chapter boundary belongs to the chapter where it starts. */
    public function split(array $captions, array $chapters, string $episodeCode): array
    {
        $chapters = array_values(array_filter($chapters, static fn (array $c) => isset($c['startTime']) && is_numeric($c['startTime']) && $c['startTime'] >= 0));
        usort($chapters, static fn (array $a, array $b) => $a['startTime'] <=> $b['startTime']);
        $segments = [];
        $segment = null;
        $chapterIndex = -1;
        foreach ($captions as $captionIndex => $caption) {
            $text = trim(strip_tags($caption->text));
            if ($text === '') { continue; }
            while (isset($chapters[$chapterIndex + 1]) && $chapters[$chapterIndex + 1]['startTime'] <= $caption->startTime) {
                ++$chapterIndex;
            }
            $chapter = $chapters[$chapterIndex] ?? null;
            $chapterId = $chapter === null ? null : $episodeCode.':chapter:'.$chapter['startTime'];
            if ($segment !== null && ($segment['chapter_id'] !== $chapterId || mb_strlen($segment['text']) + 1 + mb_strlen($text) > 6000)) {
                $segments[] = $segment;
                $segment = null;
            }
            $segment ??= [
                'chapter_id' => $chapterId,
                'chapter_title' => $chapter['title'] ?? null,
                'chapter_start' => $chapter === null ? null : (float) $chapter['startTime'],
                'chapter_source' => $chapter === null ? null : 'publisher',
                'start' => $caption->startTime, 'end' => $caption->endTime,
                'caption_start' => $captionIndex, 'caption_end' => $captionIndex,
                'text' => '', 'summary' => null,
            ];
            $segment['text'] .= ($segment['text'] === '' ? '' : ' ').$text;
            $segment['end'] = $caption->endTime;
            $segment['caption_end'] = $captionIndex;
        }
        if ($segment !== null) { $segments[] = $segment; }
        foreach ($segments as &$item) {
            $item['text_hash'] = hash('sha256', $item['text']);
            $item['id'] = substr(hash('sha256', json_encode([$episodeCode, $item['start'], $item['end'], $item['text_hash']])), 0, 24);
        }
        return $segments;
    }
}
