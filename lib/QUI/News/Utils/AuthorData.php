<?php

namespace QUI\News\Utils;

use Normalizer;
use QUI;
use QUI\Interfaces\Projects\Site;
use QUI\Projects\Media\Utils as MediaUtils;

/** Page-owned author profile data, independent of the card layout. */
class AuthorData
{
    /**
     * @return array{name:string, avatar:mixed, initials:string, description:string, additionalInfo:string}|null
     */
    public static function resolve(Site $Site): ?array
    {
        if (
            !in_array($Site->getAttribute('type'), [
                'quiqqer/news:types/news-entry',
                'quiqqer/news:types/news-article'
            ], true)
        ) {
            return null;
        }

        $guest = EntryData::getGuestAuthorSettings($Site);
        $author = EntryData::resolveAuthorData($Site, $guest);
        $name = self::normalizeName($author['author'] ?? '');

        if ($name === '') {
            return null;
        }

        $freeGuest = $guest['enabled'] && !$guest['user'] && self::normalizeName($guest['name']) !== '';

        return [
            'name' => $name,
            'avatar' => $author['avatar'],
            'initials' => self::getInitials($name),
            'description' => $freeGuest ? trim((string)$guest['description']) : '',
            'additionalInfo' => $freeGuest ? self::normalizeName((string)$guest['additionalInfo']) : ''
        ];
    }

    public static function normalizeName(string $name): string
    {
        $name = preg_replace('/[\p{Z}\s]+/u', ' ', $name);
        return trim($name ?? '');
    }

    public static function getInitials(string $name): string
    {
        $name = self::normalizeName($name);

        if ($name === '') {
            return '';
        }

        $parts = explode(' ', $name);
        preg_match('/^\X/u', $parts[0], $first);
        $initials = $first[0] ?? '';

        if (count($parts) > 1) {
            preg_match('/^\X/u', $parts[count($parts) - 1], $last);
            $initials .= $last[0] ?? '';
        }

        $initials = mb_strtoupper($initials, 'UTF-8');
        return Normalizer::normalize($initials, Normalizer::FORM_C) ?: $initials;
    }

    public static function getAvatarUrl(mixed $avatar, int $size): string
    {
        if (!MediaUtils::isMediaUrl($avatar)) {
            return '';
        }

        try {
            $Image = MediaUtils::getImageByUrl($avatar);

            if (!$Image->isActive() || !is_file($Image->getFullPath())) {
                return '';
            }

            return $Image->getSizeCacheUrl($size, $size);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addInfo($Exception->getMessage());
            return '';
        }
    }
}
