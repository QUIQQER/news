<?php

namespace QUITests\News\Controls;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Bricks\Brick;
use QUI\News\Controls\Author;
use QUI\Projects\Site;

class AuthorTest extends TestCase
{
    #[DataProvider('layouts')]
    public function testRendersPlainTextAndOptionalFields(string $layout): void
    {
        $Control = new Author([
            'Site' => $this->createSite([
                'name' => 'Özlem <script>alert("name")</script> Şahin',
                'description' => "First <b>line</b>\nSecond line",
                'additionalInfo' => 'Writer & editor'
            ]),
            'title' => 'About <author>',
            'authorLayout' => $layout
        ]);
        $html = $Control->create();

        $this->assertStringContainsString('__card--' . $layout, $html);
        $this->assertStringContainsString('About &lt;author&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString("First &lt;b&gt;line&lt;/b&gt;\nSecond line", $html);
        $this->assertStringContainsString('Writer &amp; editor', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('ÖŞ', $html);
        $this->assertStringNotContainsString('<h1', $html);
    }

    public static function layouts(): iterable
    {
        yield ['left'];
        yield ['topLeft'];
        yield ['top'];
        yield ['topTextLeft'];
        yield ['compact'];
    }

    public function testEditorListsAllLayoutsAndKeepsTheDefault(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 4) . '/bricks.xml');
        $setting = $xml->xpath('//setting[@name="authorLayout"]')[0];
        $values = [];

        foreach ($setting->option as $option) {
            $values[] = (string)$option['value'];
        }

        $this->assertSame(['left', 'topLeft', 'top', 'topTextLeft', 'compact'], $values);
        $this->assertSame('left', (string)$setting->defaultValue);
        $this->assertSame('left', (new Author())->getAttribute('authorLayout'));
        $this->assertSame('brick.author.layout.description', (string)$setting->description->locale['var']);
    }

    public function testEmptyFieldsDoNotCreateElementsAndInvalidLayoutFallsBack(): void
    {
        $Control = new Author([
            'Site' => $this->createSite(['name' => 'Michael']),
            'authorLayout' => 'invalid'
        ]);
        $html = $Control->create();

        $this->assertStringNotContainsString('__description', $html);
        $this->assertStringNotContainsString('__info', $html);
        $this->assertStringNotContainsString('control-header', $html);
        $this->assertStringContainsString('__card--left', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testEmptyNameAndUnsupportedPageHaveNoWrapperOrTitle(): void
    {
        $Control = new Author([
            'Site' => $this->createSite(['name' => " \u{00a0} "]),
            'title' => 'About'
        ]);
        $this->assertSame('', $Control->create());

        $Control->setAttribute('Site', $this->createSite(['name' => 'Guest'], 'quiqqer/blog:types/entry'));
        $this->assertSame('', $Control->create());
    }

    public function testReusedControlResolvesTheCurrentPageAndClearsPreviousColors(): void
    {
        $Control = new Author([
            'Site' => $this->createSite(['name' => 'First Writer']),
            'initialsBackground' => ['type' => 'color', 'light' => '#aabbcc', 'dark' => '#112233'],
            'initialsColor' => ['type' => 'palette', 'id' => 'primary']
        ]);
        $first = $Control->create();
        $this->assertStringContainsString('light-dark(#aabbcc, #112233)', $first);
        $this->assertStringContainsString('var(--qui-project-color-primary)', $first);

        $Control->setAttributes([
            'Site' => $this->createSite(['name' => 'Second Writer']),
            'initialsBackground' => '',
            'initialsColor' => 'red;position:fixed'
        ]);
        $second = $Control->create();
        $this->assertStringContainsString('Second Writer', $second);
        $this->assertStringNotContainsString('First Writer', $second);
        $this->assertStringNotContainsString('--_q-controlConf-', $second);
    }

    #[DataProvider('colors')]
    public function testSupportsStoredColorForms(mixed $value, string $expected): void
    {
        $Control = new Author([
            'Site' => $this->createSite(['name' => 'Guest']),
            'initialsBackground' => $value
        ]);
        $this->assertStringContainsString('--_q-controlConf-initialsBackground:' . $expected, $Control->create());
    }

    public static function colors(): iterable
    {
        yield ['#abcdef', '#abcdef'];
        yield ['transparent', 'transparent'];
        yield ['{"type":"palette","id":"primary"}', 'var(--qui-project-color-primary)'];
        yield [(object)['type' => 'color', 'light' => '#ffffff', 'dark' => '#000000'], 'light-dark(#ffffff, #000000)'];
    }

    public function testRegistrationDisablesTheSharedHtmlCache(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 4) . '/bricks.xml');
        $this->assertSame('0', (string)$xml->bricks->brick['cacheable']);
        $this->assertSame(Author::class, ltrim((string)$xml->bricks->brick['control'], '\\'));
        $Control = new Author(['cacheable' => true]);
        $this->assertFalse($Control->getAttribute('cacheable'));
    }

    public function testBrickRuntimeKeepsPageSpecificAuthorsOutOfItsHtmlCache(): void
    {
        foreach (['First Writer', 'Second Writer'] as $name) {
            $Brick = new Brick([
                'id' => 999999,
                'type' => '\\' . Author::class,
                'Site' => $this->createSite(['name' => $name]),
                'cacheable' => 1
            ]);

            $this->assertFalse($Brick->getAttribute('cacheable'));
            $this->assertStringContainsString($name, $Brick->create());
        }
    }

    private function createSite(array $guest, string $type = 'quiqqer/news:types/news-article'): Site
    {
        $attributes = [
            'type' => $type,
            'c_user' => '',
            'quiqqer.news.settings.guestAuthor.enable' => true
        ];

        foreach ($guest as $key => $value) {
            $attributes['quiqqer.news.settings.guestAuthor.' . $key] = $value;
        }

        $Site = $this->createMock(Site::class);
        $Site->method('getAttribute')->willReturnCallback(static fn ($key) => $attributes[$key] ?? '');
        return $Site;
    }
}
