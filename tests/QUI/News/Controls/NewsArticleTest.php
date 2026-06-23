<?php

namespace QUITests\News\Controls;

use PHPUnit\Framework\TestCase;
use QUI\News\Controls\NewsArticle;
use QUI\Projects\Project;

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

    public function testEmptyLayoutUsesProjectDefault(): void
    {
        $Control = $this->createExposedControl(
            ['layout' => ''],
            ['news.settings.article.defaultLayout' => 'imageFirst']
        );

        $this->assertSame('imageFirst', $Control->exposeResolveLayout());
    }

    public function testExplicitLayoutOverridesProjectDefault(): void
    {
        $Control = $this->createExposedControl(
            ['layout' => 'default'],
            ['news.settings.article.defaultLayout' => 'imageFirst']
        );

        $this->assertSame('default', $Control->exposeResolveLayout());
    }

    public function testInvalidProjectDefaultFallsBackToDefaultLayout(): void
    {
        $Control = $this->createExposedControl(
            ['layout' => ''],
            ['news.settings.article.defaultLayout' => 'unknown']
        );

        $this->assertSame('default', $Control->exposeResolveLayout());
    }

    public function testEmptyShowMoreNewsUsesProjectDefault(): void
    {
        $Control = $this->createExposedControl(
            ['showMoreNews' => ''],
            ['news.settings.article.showMoreNews' => '0']
        );

        $this->assertFalse($Control->exposeResolveShowMoreNews());
    }

    public function testExplicitShowMoreNewsOverridesProjectDefault(): void
    {
        $Control = $this->createExposedControl(
            ['showMoreNews' => '1'],
            ['news.settings.article.showMoreNews' => '0']
        );

        $this->assertTrue($Control->exposeResolveShowMoreNews());
    }

    public function testApplyLayoutVariablesSetsCustomWidthAndGapVariables(): void
    {
        $Control = $this->createExposedControl([], [
            'news.settings.article.layout.default.sectionGap' => 'large',
            'news.settings.article.layout.default.imageWidthMode' => 'custom',
            'news.settings.article.layout.default.headerImageMaxWidth' => 640,
            'news.settings.article.layout.default.headerBackground' => '#ffffff'
        ]);

        $Control->exposeApplyLayoutVariables('default');
        $styles = $Control->exposeStyles();

        $this->assertSame('clamp(2.5rem, 4vw, 3.5rem)', $styles['--_q-controlConf-sectionGap']);
        $this->assertSame('640px', $styles['--_q-controlConf-headerImage-maxWidth']);
        $this->assertSame('#ffffff', $styles['--_q-controlConf-header-background']);
    }

    public function testApplyLayoutVariablesSetsTitleSizeForNormalPreset(): void
    {
        $Control = $this->createExposedControl([], [
            'news.settings.article.layout.default.titleSize' => 'normal'
        ]);

        $Control->exposeApplyLayoutVariables('default');
        $styles = $Control->exposeStyles();

        $this->assertSame('var(--qui-fs-1, revert)', $styles['--_q-controlConf-title-size']);
    }

    public function testCenteredHeaderAppliesOnlyToDefaultLayout(): void
    {
        $Control = $this->createExposedControl([], [
            'news.settings.article.layout.default.centerHeader' => '1'
        ]);

        $this->assertTrue($Control->exposeIsCenteredHeader('default'));
        $this->assertFalse($Control->exposeIsCenteredHeader('imageFirst'));
    }

    private function createExposedControl(array $attributes, array $config = []): NewsArticle
    {
        $Project = new class ($config) extends Project {
            public function __construct(private array $config)
            {
            }

            public function getConfig(bool|string $name = false): mixed
            {
                if ($name === false) {
                    return null;
                }

                return $this->config[$name] ?? null;
            }
        };

        $Site = $this->createStub(\QUI\Interfaces\Projects\Site::class);
        $Site->method('getProject')->willReturn($Project);

        $attributes['Site'] = $Site;

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

            public function exposeResolveLayout(): string
            {
                return $this->resolveLayout();
            }

            public function exposeResolveShowMoreNews(): bool
            {
                return $this->resolveShowMoreNews();
            }

            public function exposeApplyLayoutVariables(string $layout): void
            {
                $this->applyLayoutVariables($layout);
            }

            public function exposeIsCenteredHeader(string $layout): bool
            {
                return $this->isCenteredHeader($layout);
            }

            public function exposeStyles(): array
            {
                return $this->styles;
            }
        };
    }
}
