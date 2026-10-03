<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\EpisodeSegments;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class EpisodeSegmentsTest extends TestCase
{
    public function testChaptersPreserveEveryCaptionAndStableSegmentIds(): void
    {
        $splitter = new EpisodeSegments($this->createMock(EntityManagerInterface::class));
        $captions = [
            (object) ['startTime' => 0, 'endTime' => 5, 'text' => 'Introduction'],
            (object) ['startTime' => 5, 'endTime' => 12, 'text' => 'Crossing the boundary'],
            (object) ['startTime' => 12, 'endTime' => 15, 'text' => 'Second topic'],
        ];
        $chapters = [['startTime' => 10, 'title' => 'Second'], ['startTime' => 0, 'title' => 'First']];
        $segments = $splitter->split($captions, $chapters, '1907');
        self::assertCount(2, $segments);
        self::assertSame('Introduction Crossing the boundary', $segments[0]['text']);
        self::assertSame('Second', $segments[1]['chapter_title']);
        self::assertSame('1907:chapter:10', $segments[1]['chapter_id']);
        self::assertSame($segments, $splitter->split($captions, $chapters, '1907'));
        $captions[2]->text = 'Corrected source';
        self::assertNotSame($segments[1]['id'], $splitter->split($captions, $chapters, '1907')[1]['id']);
    }

    public function testLongChaptersSplitWithoutInventingChapterMarkers(): void
    {
        $splitter = new EpisodeSegments($this->createMock(EntityManagerInterface::class));
        $captions = [
            (object) ['startTime' => 0, 'endTime' => 10, 'text' => str_repeat('a', 4000)],
            (object) ['startTime' => 10, 'endTime' => 20, 'text' => str_repeat('b', 4000)],
        ];
        $segments = $splitter->split($captions, [['startTime' => 0, 'title' => 'Long chapter']], '1907');
        self::assertCount(2, $segments);
        self::assertSame($segments[0]['chapter_id'], $segments[1]['chapter_id']);
        self::assertSame(8000, array_sum(array_map(static fn ($s) => strlen($s['text']), $segments)));
        self::assertNull($splitter->split($captions, [], '1907')[0]['chapter_id']);
    }
}
