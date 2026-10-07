<?php

/**
 * Declare "global" variables for PHPStan and IDEs
 * @var QUI\Projects\Project $Project
 * @var \QUI\Interfaces\Projects\Site $Site
 * @var \QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 */

use QUI\News\Utils\EntryData;

$a = $Project->getConfig('news.settings.entry.showTitle');
// default
$enableDateAndCreator = true;
$showCreator = false;
$showDate = false;

if ($Project->getConfig('news.settings.entry.showCreator')) {
    $showCreator = $Project->getConfig('news.settings.entry.showCreator');
}

if ($Project->getConfig('news.settings.entry.showDate')) {
    $showDate = $Project->getConfig('news.settings.entry.showDate');
}

switch ($Site->getAttribute('quiqqer.settings.news.entry.dateAndCreator')) {
    case 'showAll':
        $showCreator = true;
        $showDate = true;
        break;

    case 'showCreator':
        // hide date
        $showCreator = true;
        $showDate = false;
        break;

    case 'showDate':
        // hide author
        $showDate = true;
        $showCreator = false;
        break;

    case 'hide':
        // disable date and author
        $enableDateAndCreator = false;
        break;
}

if (!$showCreator && !$showDate) {
    $enableDateAndCreator = false;
}

/**
 * Author
 */
$quiqqerUser = $Site->getAttribute('c_user');
$userName = null;
$userAvatar = null;

// guest author enabled?
if ($Site->getAttribute('quiqqer.settings.news.guestAuthor.enable')) {
    $guestUser = $Site->getAttribute('quiqqer.settings.news.guestAuthor.quiqqerUser');
    $guestName = $Site->getAttribute('quiqqer.settings.news.guestAuthor.name');
    $guestAvatar = $Site->getAttribute('quiqqer.settings.news.guestAuthor.avatar');

    if ($guestUser) {
        $quiqqerUser = $guestUser;
    } elseif ($guestName) {
        $userName = $guestName;
        $quiqqerUser = null;

        if ($guestAvatar) {
            $userAvatar = $guestAvatar;
        }
    }
}

if ($quiqqerUser) {
    try {
        $User = QUI::getUsers()->get($quiqqerUser);
        $userName = $User->getName();
        $Engine->assign('author', $User->getName());
    } catch (QUI\Exception $Exception) {
        QUI\System\Log::addInfo($Exception->getMessage(), [
            'project' => $Project->getName(),
            'lang' => $Project->getLang(),
            'site' => $Site->getId()
        ]);
        $Engine->assign('author', null);
    }
} else {
    $Engine->assign('author', $userName);
}

try {
    $ArticleJsonLd = EntryData::createJsonLd($Site, $userName);
    $PageJsonLd = $Template->getJsonLd();
    $webPageId = $PageJsonLd->get('@id');

    if (!empty($webPageId)) {
        $ArticleJsonLd->set('mainEntityOfPage', ['@id' => $webPageId]);
    }

    $publisher = $ArticleJsonLd->getJsonLdData()['publisher'] ?? [];
    $pagePublisher = $PageJsonLd->get('publisher');

    if (is_array($pagePublisher) && !empty($pagePublisher['@id'])) {
        $publisher['@id'] = $pagePublisher['@id'];
        $ArticleJsonLd->set('publisher', $publisher);
    }

    $PageJsonLd->set('mainEntity', ['@id' => $ArticleJsonLd->get('@id')]);
    $PageJsonLd->setJsonLdNode('newsArticle', $ArticleJsonLd->getJsonLdData());
} catch (QUI\Exception $Exception) {
    QUI\System\Log::addWarning($Exception->getMessage());
}

/**
 * More news entries
 */
$amountOfSiblings = $Project->getConfig('news.settings.entry.more.amount');
$moreEntriesShowDate = $Project->getConfig('news.settings.entry.show_date');
$moreEntriesShowTime = $Project->getConfig('news.settings.entry.show_time');
// Reverse since the sorting/ordering in previous siblings is reversed
$previousSiblings = \array_reverse($Site->previousSiblings($amountOfSiblings));
$nextSiblings = $Site->nextSiblings($amountOfSiblings);

$Engine->assign([
    'enableDateAndCreator' => $enableDateAndCreator,
    'showCreator' => $showCreator,
    'showDate' => $showDate,
    'showFurtherNewsDate' => $moreEntriesShowDate,
    'showFurtherNewsTime' => $moreEntriesShowTime,
    'previousSiblings' => $previousSiblings,
    'nextSiblings' => $nextSiblings,
    'showTitle' => $Project->getConfig('news.settings.entry.showTitle'),
    'showDescription' => $Project->getConfig('news.settings.entry.showDescription')
]);
