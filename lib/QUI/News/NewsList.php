<?php

/**
 * This file contains \QUI\News\NewsList
 */

namespace QUI\News;

use QUI;

/**
 * News list helper class
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class NewsList
{
    /**
     * @param QUI\Projects\Site\Edit $Site
     */
    public static function onSiteSaveBefore($Site): void
    {
        if ($Site->getAttribute('type') !== 'quiqqer/news:types/news-list') {
            return;
        }

        if ($Site->getAttribute('order') === false || $Site->getAttribute('order') === '') {
            $Site->setAttribute('order_type', 'release_from DESC');
        }
    }
}
