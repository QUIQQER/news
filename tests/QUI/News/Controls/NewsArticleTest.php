<?php

namespace QUITests\News\Controls;

use PHPUnit\Framework\TestCase;
use QUI\News\Controls\NewsArticle;

class NewsArticleTest extends TestCase
{
    public function testConstructorSetsExpectedDefaultAttributes(): void
    {
        $Control = new NewsArticle([]);

        $this->assertSame('default', $Control->getAttribute('layout'));
        $this->assertSame('', $Control->getAttribute('headerImage'));
        $this->assertSame('default', $Control->getAttribute('metaVisibility'));
        $this->assertTrue($Control->getAttribute('showMoreNews'));
        $this->assertSame(2, $Control->getAttribute('moreAmount'));
        $this->assertTrue($Control->getAttribute('showMoreNewsDate'));
        $this->assertFalse($Control->getAttribute('showMoreNewsTime'));
        $this->assertSame('default', $Control->getAttribute('moreTemplate'));
    }

    public function testMetaVisibilityShowAllShowsAuthorAndDate(): void
    {
        $Control = $this->createExposedControl([
            'metaVisibility' => 'showAll'
        ]);

        $this->assertTrue($Control->exposeShowMeta());
        $this->assertTrue($Control->exposeShowAuthor());
        $this->assertTrue($Control->exposeShowDate());
    }

    public function testMetaVisibilityShowAuthorShowsOnlyAuthor(): void
    {
        $Control = $this->createExposedControl([
            'metaVisibility' => 'showAuthor'
        ]);

        $this->assertTrue($Control->exposeShowMeta());
        $this->assertTrue($Control->exposeShowAuthor());
        $this->assertFalse($Control->exposeShowDate());
    }

    public function testMetaVisibilityShowDateShowsOnlyDate(): void
    {
        $Control = $this->createExposedControl([
            'metaVisibility' => 'showDate'
        ]);

        $this->assertTrue($Control->exposeShowMeta());
        $this->assertFalse($Control->exposeShowAuthor());
        $this->assertTrue($Control->exposeShowDate());
    }

    public function testMetaVisibilityHideHidesMetaCompletely(): void
    {
        $Control = $this->createExposedControl([
            'metaVisibility' => 'hide'
        ]);

        $this->assertFalse($Control->exposeShowMeta());
        $this->assertFalse($Control->exposeShowAuthor());
        $this->assertFalse($Control->exposeShowDate());
    }

    public function testMetaVisibilityDefaultShowsOnlyDate(): void
    {
        $Control = $this->createExposedControl([
            'metaVisibility' => 'default'
        ]);

        $this->assertTrue($Control->exposeShowMeta());
        $this->assertFalse($Control->exposeShowAuthor());
        $this->assertTrue($Control->exposeShowDate());
    }

    private function createExposedControl(array $attributes): NewsArticle
    {
        return new class ($attributes) extends NewsArticle {
            public function exposeShowMeta(): bool
            {
                return $this->showMeta();
            }

            public function exposeShowAuthor(): bool
            {
                return $this->showAuthor();
            }

            public function exposeShowDate(): bool
            {
                return $this->showDate();
            }
        };
    }
}
