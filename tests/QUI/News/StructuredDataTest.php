<?php

namespace QUITests\News;

use PHPUnit\Framework\TestCase;
use QUI\Interfaces\Template\EngineInterface;
use QUI\News\Controls\NewsArticle;
use QUI\News\Utils\EntryData;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Template;
use QUI\Utils\JsonLd;

class StructuredDataTest extends TestCase
{
    public function testArticleUsesTypedAuthorAndPublisherAndValidDates(): void
    {
        $Site = $this->createSite();
        $JsonLd = EntryData::createJsonLd($Site, 'Ada');
        $data = $JsonLd->getJsonLdData();
        $this->assertSame(JsonLd::class, get_class($JsonLd));
        $this->assertSame('NewsArticle', $data['@type']);
        $this->assertSame('https://example.org/news/post#newsarticle', $data['@id']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Ada'], $data['author']);
        $this->assertSame('Organization', $data['publisher']['@type']);
        $this->assertSame('https://cdn.example.org/news.png', $data['image']);
        $this->assertSame('2026-10-06', substr($data['datePublished'], 0, 10));
        $this->assertArrayNotHasKey('dateModified', $data);
        $this->assertSame(1, substr_count($JsonLd->getJsonLdSchema(), '</script>'));
    }

    public function testModificationDateAndHeaderImageArePreserved(): void
    {
        $Site = $this->createSite(['e_date' => '2026-10-07 12:00:00']);
        $data = EntryData::createJsonLd($Site, null, 'https://cdn.example.org/header.png')->getJsonLdData();
        $this->assertArrayNotHasKey('author', $data);
        $this->assertSame('2026-10-07', substr($data['dateModified'], 0, 10));
        $this->assertSame('https://cdn.example.org/header.png', $data['image']);
    }

    public function testDisabledControlDoesNotBuildOrRegisterJsonLd(): void
    {
        $Page = \QUI::getTemplateManager()->getJsonLd();
        $before = $Page->getJsonLdNodes();
        $Control = new class (['ownJsonLd' => false]) extends NewsArticle {
            public int $calls = 0;

            public function getJsonLd(): JsonLd
            {
                $this->calls++;
                $JsonLd = new JsonLd();
                $JsonLd->set('type', 'NewsArticle');
                return $JsonLd;
            }

            public function renderOwnJsonLd(): string
            {
                return $this->getOwnJsonLd();
            }
        };
        $this->assertSame('', $Control->renderOwnJsonLd());
        $this->assertSame(0, $Control->calls);
        $this->assertSame($before, $Page->getJsonLdNodes());
        $Control->setAttribute('ownJsonLd', true);
        $this->assertStringContainsString('application/ld+json', $Control->renderOwnJsonLd());
        $this->assertSame(1, $Control->calls);
        $this->assertSame($before, $Page->getJsonLdNodes());
        $this->assertTrue((new NewsArticle())->getAttribute('ownJsonLd'));
    }

    public function testLegacyEntryRegistersArticleInPageGraph(): void
    {
        $this->checkSiteType('news-entry');
    }

    public function testArticleSiteTypeRegistersArticleInPageGraph(): void
    {
        $this->checkSiteType('news-article');
    }

    private function checkSiteType(string $type): void
    {
        $Site = $this->createSite();
        $Project = $Site->getProject();
        $Engine = $this->createMock(EngineInterface::class);
        $Template = new Template();
        $Page = $Template->getJsonLd();
        $Page->set('type', 'WebPage');
        $Page->set('@id', 'https://example.org/news/post#webpage');
        $Page->set('publisher', ['@id' => 'https://example.org/#organization']);
        $Page->setJsonLdNode('breadcrumb', ['@type' => 'BreadcrumbList']);

        require __DIR__ . '/../../../types/' . $type . '.php';

        $article = $Page->getJsonLdNode('newsArticle');
        $this->assertNotNull($article);
        $this->assertSame('NewsArticle', $article['@type']);
        $this->assertSame(['@id' => 'https://example.org/news/post#newsarticle'], $Page->get('mainEntity'));
        $this->assertSame(['@id' => $Page->get('@id')], $article['mainEntityOfPage']);
        $this->assertSame('https://example.org/#organization', $article['publisher']['@id']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Guest writer'], $article['author']);
        $this->assertTrue($Page->hasJsonLdNode('breadcrumb'));
        $this->assertSame([], $Template->getExtendHeader());
    }

    private function createSite(array $overrides = []): Site
    {
        $Project = $this->createMock(Project::class);
        $Project->method('getConfig')->willReturnCallback(static fn ($name) => match ($name) {
            'publisher' => 'Example Publisher',
            'publisher_url' => 'https://example.org/',
            'news.settings.entry.more.amount' => 0,
            default => ''
        });
        $attributes = $overrides + [
            'title' => 'News &amp; </script>',
            'short' => 'Summary',
            'release_from' => '0000-00-00 00:00:00',
            'c_date' => '2026-10-06 12:00:00',
            'e_date' => '0000-00-00 00:00:00',
            'image_site' => 'https://cdn.example.org/news.png',
            'c_user' => false,
            'quiqqer.settings.news.guestAuthor.enable' => true,
            'quiqqer.settings.news.guestAuthor.name' => 'Guest writer',
            'quiqqer.news.settings.guestAuthor.enable' => true,
            'quiqqer.news.settings.guestAuthor.name' => 'Guest writer'
        ];
        $Site = $this->createMock(Site::class);
        $Site->method('getAttribute')->willReturnCallback(static fn ($name) => $attributes[$name] ?? '');
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getUrlRewrittenWithHost')->willReturn('https://example.org/news/post');
        $Site->method('previousSiblings')->willReturn([]);
        $Site->method('nextSiblings')->willReturn([]);
        return $Site;
    }
}
