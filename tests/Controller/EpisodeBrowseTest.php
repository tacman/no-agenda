<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EpisodeBrowseTest extends WebTestCase
{
    public function testArchiveWorksWithPostgresqlBooleans(): void
    {
        $client = static::createClient();
        $client->request('GET', '/podcast');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Guardrails', $client->getResponse()->getContent());
    }

    public function testSearchRendersEpisodeCards(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.search-episode');
        self::assertSelectorExists('input[type="search"]');
        self::assertSelectorExists('a[href="/listen/1598"]');
    }
    public function testSearchFiltersOnPostgresql(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?query=Guardrails');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.search-episode');
        self::assertSelectorTextContains('.search-episode', 'Guardrails');
    }
}
