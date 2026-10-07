<?php

namespace QUI\News\Utils;

use QUI;
use QUI\Controls\Utils\MetaList;
use QUI\Controls\Utils\MetaList\Publisher;
use QUI\Projects\Media\Image;
use QUI\Projects\Media\Utils as MediaUtils;
use QUI\Utils\JsonLd;
use QUI\Utils\StructuredData;

use function array_reverse;
use function is_array;
use function is_string;
use function json_decode;
use function strpos;

class EntryData
{
    /**
     * @param QUI\Interfaces\Projects\Site $Site
     * @return string
     */
    public static function getDisplayDate($Site): string
    {
        $releaseFrom = (string)$Site->getAttribute('release_from');

        if ($releaseFrom !== '' && $releaseFrom !== '0000-00-00 00:00:00') {
            return $releaseFrom;
        }

        return (string)$Site->getAttribute('c_date');
    }

    /**
     * @param QUI\Interfaces\Projects\Site $Site
     * @param array{
     *     enabled?: bool,
     *     user?: mixed,
     *     name?: mixed,
     *     avatar?: mixed
     * } $guestAuthor
     * @return array{author:?string, avatar:mixed}
     */
    public static function resolveAuthorData($Site, array $guestAuthor = []): array
    {
        $quiqqerUser = $Site->getAttribute('c_user');
        $userName = null;
        $userAvatar = null;

        if (!empty($guestAuthor['enabled'])) {
            $guestUser = $guestAuthor['user'] ?? null;
            $guestName = $guestAuthor['name'] ?? null;
            $guestAvatar = $guestAuthor['avatar'] ?? null;

            if ($guestUser) {
                $quiqqerUser = $guestUser;
            } elseif ($guestName) {
                $userName = (string)$guestName;
                $quiqqerUser = null;

                if ($guestAvatar) {
                    $userAvatar = $guestAvatar;
                }
            }
        }

        if ($quiqqerUser) {
            try {
                $User = QUI::getUsers()->get($quiqqerUser);

                return [
                    'author' => $User->getName(),
                    'avatar' => $userAvatar
                ];
            } catch (QUI\Exception $Exception) {
                $Project = $Site->getProject();

                QUI\System\Log::addInfo($Exception->getMessage(), [
                    'project' => $Project->getName(),
                    'lang' => $Project->getLang(),
                    'site' => $Site->getId()
                ]);
            }
        }

        return [
            'author' => $userName,
            'avatar' => $userAvatar
        ];
    }

    /**
     * @param QUI\Interfaces\Projects\Site $Site
     * @param string $preferredImage
     * @return string
     */
    public static function getDisplayImage($Site, string $preferredImage = ''): string
    {
        $image = $preferredImage;

        if ($image === '') {
            $image = (string)$Site->getAttribute('image_site');
        }

        if (strpos($image, 'fa-') !== false) {
            return '';
        }

        return $image;
    }

    /**
     * @param QUI\Interfaces\Projects\Site $Site
     * @param string $preferredImage
     * @return string
     */
    public static function getAbsoluteImageUrl($Site, string $preferredImage = ''): string
    {
        $image = self::getDisplayImage($Site, $preferredImage);
        $host = QUI::getRequest()->getHost();
        $scheme = QUI::getRequest()->getScheme();

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        if (str_starts_with($image, '//')) {
            return $scheme . ':' . $image;
        }

        if (MediaUtils::isMediaUrl($image)) {
            try {
                $Image = MediaUtils::getImageByUrl($image);

                return $scheme . '://' . $host . $Image->getSizeCacheUrl();
            } catch (QUI\Exception) {
            }
        }

        if (str_starts_with($image, '/')) {
            return $scheme . '://' . $host . $image;
        }

        try {
            $Placeholder = $Site->getProject()->getMedia()->getPlaceholderImage();

            if ($Placeholder instanceof Image) {
                return $scheme . '://' . $host . $Placeholder->getSizeCacheUrl();
            }
        } catch (QUI\Exception) {
        }

        return '';
    }

    /**
     * @deprecated Use createJsonLd() for structured data output.
     * @param QUI\Interfaces\Projects\Site $Site
     */
    public static function createMetaList($Site, ?string $author = null, string $preferredImage = ''): MetaList
    {
        $MetaList = new MetaList();
        self::populateJsonLd($MetaList, $Site, $author, $preferredImage);
        return $MetaList;
    }

    /**
     * @param QUI\Interfaces\Projects\Site $Site
     */
    public static function createJsonLd($Site, ?string $author = null, string $preferredImage = ''): JsonLd
    {
        $JsonLd = new JsonLd();
        self::populateJsonLd($JsonLd, $Site, $author, $preferredImage);
        return $JsonLd;
    }

    /**
     * @param QUI\Interfaces\Projects\Site $Site
     */
    private static function populateJsonLd(JsonLd $JsonLd, $Site, ?string $author, string $preferredImage): void
    {
        $JsonLd->set('type', 'NewsArticle');
        $JsonLd->set('@id', $Site->getUrlRewrittenWithHost() . '#newsarticle');
        $JsonLd->add('headline', $Site->getAttribute('title'));
        $JsonLd->add('description', $Site->getAttribute('short'));
        $JsonLd->add('datePublished', self::getDisplayDate($Site));
        $dateModified = StructuredData::getModificationDate(
            $Site->getAttribute('c_date'),
            $Site->getAttribute('e_date')
        );

        if ($dateModified !== null) {
            $JsonLd->set('dateModified', $dateModified);
        }

        $JsonLd->add('mainEntityOfPage', $Site->getUrlRewrittenWithHost());

        if ($author) {
            $JsonLd->add('author', $author);
        }

        $Publisher = new Publisher();
        $Publisher->importFromProject($Site->getProject());
        $JsonLd->add('publisher', $Publisher);

        $imageAbsolutePath = self::getAbsoluteImageUrl($Site, $preferredImage);

        if ($imageAbsolutePath !== '') {
            $JsonLd->add('image', $imageAbsolutePath);
        }
    }

    /**
     * @param QUI\Interfaces\Projects\Site $Site
     * @param int $amount
     * @return array{previous: array<int, mixed>, next: array<int, mixed>}
     */
    public static function getMoreEntries($Site, int $amount): array
    {
        return [
            'previous' => array_reverse($Site->previousSiblings($amount)),
            'next' => $Site->nextSiblings($amount)
        ];
    }

    /**
     * @param QUI\Interfaces\Projects\Site $Site
     * @return array<int, string>
     */
    public static function getTags($Site): array
    {
        $tags = $Site->getAttribute('quiqqer.tags.tagList');

        if (is_string($tags)) {
            $tags = json_decode($tags, true);
        }

        if (!is_array($tags)) {
            return [];
        }

        return $tags;
    }
}
