<?php

namespace QUITests\News\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\News\Utils\AuthorData;
use QUI\Projects\Media\Image;
use QUI\Projects\Media\Utils as MediaUtils;
use QUI\Projects\Site;
use QUI\Users\Manager;
use QUI\Interfaces\Users\User;

class AuthorDataTest extends TestCase
{
    #[DataProvider('names')]
    public function testInitialsRespectGraphemesAndNameParts(string $name, string $expected): void
    {
        $this->assertSame($expected, AuthorData::getInitials($name));
    }

    public static function names(): iterable
    {
        yield ['Anika Monika Müller', 'AM'];
        yield ['Anna-Lena Müller', 'AM'];
        yield ['Michael', 'M'];
        yield ['Özlem Şahin', 'ÖŞ'];
        yield ["  O\u{0308}zlem\u{00a0}\u{2003}S\u{0327}ahin  ", 'ÖŞ'];
        yield ['李 小龍', '李小'];
        yield ["\u{00a0}\t\n", ''];
    }

    #[DataProvider('authorCases')]
    public function testPageAuthorPriority(
        string $type,
        bool $enabled,
        string $selected,
        string $guestName,
        ?string $lookup,
        ?string $expected
    ): void {
        $previousUsers = QUI::$Users;
        $Users = $this->createMock(Manager::class);
        $User = $this->createMock(User::class);
        $User->method('getName')->willReturn('User name');
        $User->method('getAttribute')->willReturnCallback(static fn ($key) => $key === 'avatar' ? 'user-avatar' : '');
        QUI::$Users = $Users;

        if ($lookup === null) {
            $Users->expects($this->never())->method('get');
        } elseif ($lookup === 'deleted') {
            $Users->expects($this->once())->method('get')->with('deleted')
                ->willThrowException(new QUI\Users\Exception('Missing', 404));
        } else {
            $Users->expects($this->once())->method('get')->with($lookup)->willReturn($User);
        }

        $prefix = $type === 'news-entry' ? 'quiqqer.settings.news.guestAuthor.' : 'quiqqer.news.settings.guestAuthor.';
        $attributes = [
            'type' => 'quiqqer/news:types/' . $type,
            'c_user' => 'creator',
            $prefix . 'enable' => $enabled,
            $prefix . 'quiqqerUser' => $selected,
            $prefix . 'name' => $guestName,
            $prefix . 'avatar' => 'guest-avatar',
            $prefix . 'description' => "First line\nSecond line",
            $prefix . 'additionalInfo' => 'Guest info'
        ];
        $Site = $this->createMock(Site::class);
        $Site->method('getAttribute')->willReturnCallback(static fn ($key) => $attributes[$key] ?? '');

        try {
            $data = AuthorData::resolve($Site);
            $this->assertSame($expected, $data['name'] ?? null);

            if ($data !== null) {
                $freeGuest = $enabled && $selected === '' && AuthorData::normalizeName($guestName) !== '';
                $this->assertSame($freeGuest ? 'guest-avatar' : 'user-avatar', $data['avatar']);
                $this->assertSame($freeGuest ? "First line\nSecond line" : '', $data['description']);
                $this->assertSame($freeGuest ? 'Guest info' : '', $data['additionalInfo']);
            }
        } finally {
            QUI::$Users = $previousUsers;
        }
    }

    public static function authorCases(): iterable
    {
        foreach (['news-entry', 'news-article'] as $type) {
            yield "$type creator" => [$type, false, 'selected', 'Guest', 'creator', 'User name'];
            yield "$type selected" => [$type, true, 'selected', 'Guest', 'selected', 'User name'];
            yield "$type free guest" => [$type, true, '', 'Guest', null, 'Guest'];
            yield "$type empty guest" => [$type, true, '', '', 'creator', 'User name'];
            yield "$type whitespace guest" => [$type, true, '', "\u{00a0}  ", 'creator', 'User name'];
            yield "$type deleted selection" => [$type, true, 'deleted', 'Guest', 'deleted', null];
        }

        yield 'unsupported' => ['news-list', true, '', 'Guest', null, null];
    }

    public function testAUserWithoutUsableNameHasNoCardAndDoesNotLoadAPlaceholder(): void
    {
        $previousUsers = QUI::$Users;
        $Users = $this->createMock(Manager::class);
        $User = $this->createMock(User::class);
        $User->method('getName')->willReturn("\u{00a0} ");
        $User->expects($this->never())->method('getAvatar');
        $Users->method('get')->willReturn($User);
        QUI::$Users = $Users;
        $attributes = [
            'type' => 'quiqqer/news:types/news-entry',
            'c_user' => 'creator'
        ];
        $Site = $this->createMock(Site::class);
        $Site->method('getAttribute')->willReturnCallback(static fn ($key) => $attributes[$key] ?? '');

        try {
            $this->assertNull(AuthorData::resolve($Site));
        } finally {
            QUI::$Users = $previousUsers;
        }
    }

    #[DataProvider('invalidAvatars')]
    public function testInvalidAvatarsUseInitialsInsteadOfAnExternalOrGenericImage(mixed $avatar): void
    {
        $this->assertSame('', AuthorData::getAvatarUrl($avatar, 192));
    }

    public static function invalidAvatars(): iterable
    {
        yield [''];
        yield [null];
        yield ['javascript:alert(1)'];
        yield ['https://example.org/avatar.jpg'];
        yield [['invalid']];
    }

    #[DataProvider('storedAvatars')]
    public function testOnlyActiveExistingImagesProduceAnAvatarUrl(bool $active, bool $fileExists): void
    {
        $avatar = 'image.php?project=author-test&id=999999';
        $cacheUrl = '/media/cache/author-test/avatar.png';
        $Image = $this->createMock(Image::class);
        $Image->method('isActive')->willReturn($active);
        $Image->method('getFullPath')->willReturn($fileExists ? __FILE__ : __FILE__ . '.missing');
        $Image->expects($active && $fileExists ? $this->once() : $this->never())
            ->method('getSizeCacheUrl')->with(192, 192)->willReturn($cacheUrl);

        $Cache = new \ReflectionProperty(MediaUtils::class, 'urlItemCache');
        $previousCache = $Cache->getValue();
        $Cache->setValue(null, [$avatar => $Image]);

        try {
            $this->assertSame($active && $fileExists ? $cacheUrl : '', AuthorData::getAvatarUrl($avatar, 192));
        } finally {
            $Cache->setValue(null, $previousCache);
        }
    }

    public static function storedAvatars(): iterable
    {
        yield 'active image' => [true, true];
        yield 'inactive image with existing source' => [false, true];
        yield 'active image with missing source' => [true, false];
    }
}
