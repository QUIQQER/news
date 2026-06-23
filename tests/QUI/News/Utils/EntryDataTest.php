<?php

namespace QUITests\News\Utils;

use PHPUnit\Framework\TestCase;
use QUI\News\Utils\EntryData;

class EntryDataTest extends TestCase
{
    public function testGetDisplayDatePrefersReleaseDate(): void
    {
        $Site = $this->createSite([
            'release_from' => '2026-06-20 12:00:00',
            'c_date' => '2026-06-19 08:00:00'
        ]);

        $this->assertSame('2026-06-20 12:00:00', EntryData::getDisplayDate($Site));
    }

    public function testGetDisplayDateFallsBackToCreationDate(): void
    {
        $Site = $this->createSite([
            'release_from' => '0000-00-00 00:00:00',
            'c_date' => '2026-06-19 08:00:00'
        ]);

        $this->assertSame('2026-06-19 08:00:00', EntryData::getDisplayDate($Site));
    }

    public function testResolveAuthorDataUsesGuestNameWhenEnabledWithoutUser(): void
    {
        $Site = $this->createSite([
            'c_user' => false
        ]);

        $result = EntryData::resolveAuthorData($Site, [
            'enabled' => true,
            'name' => 'Gastautor'
        ]);

        $this->assertSame('Gastautor', $result['author']);
    }

    public function testGetDisplayImagePrefersGivenHeaderImage(): void
    {
        $Site = $this->createSite([
            'image_site' => '/media/site-image.jpg'
        ]);

        $this->assertSame('/media/header-image.jpg', EntryData::getDisplayImage($Site, '/media/header-image.jpg'));
    }

    public function testGetDisplayImageFallsBackToSiteImage(): void
    {
        $Site = $this->createSite([
            'image_site' => '/media/site-image.jpg'
        ]);

        $this->assertSame('/media/site-image.jpg', EntryData::getDisplayImage($Site));
    }

    public function testGetDisplayImageSuppressesFontAwesomeIcons(): void
    {
        $Site = $this->createSite([
            'image_site' => 'fa fa-newspaper-o'
        ]);

        $this->assertSame('', EntryData::getDisplayImage($Site));
    }

    public function testGetMoreEntriesReturnsReversedPreviousAndForwardNext(): void
    {
        $Site = new class {
            public function previousSiblings(int $amount): array
            {
                return ['first', 'second'];
            }

            public function nextSiblings(int $amount): array
            {
                return ['third', 'fourth'];
            }
        };

        $result = EntryData::getMoreEntries($Site, 2);

        $this->assertSame(['second', 'first'], $result['previous']);
        $this->assertSame(['third', 'fourth'], $result['next']);
    }

    public function testGetTagsReturnsDecodedTagList(): void
    {
        $Site = $this->createSite([
            'quiqqer.tags.tagList' => '["Tag A","Tag B"]'
        ]);

        $this->assertSame(['Tag A', 'Tag B'], EntryData::getTags($Site));
    }

    public function testGetTagsReturnsEmptyArrayForInvalidData(): void
    {
        $Site = $this->createSite([
            'quiqqer.tags.tagList' => false
        ]);

        $this->assertSame([], EntryData::getTags($Site));
    }

    private function createSite(array $attributes): object
    {
        return new class ($attributes) {
            public function __construct(private array $attributes)
            {
            }

            public function getAttribute(string $name): mixed
            {
                return $this->attributes[$name] ?? false;
            }
        };
    }
}
