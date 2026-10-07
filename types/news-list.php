<?php

/**
 * Declare "global" variables for PHPStan and IDEs
 *
 * @var \QUI\Interfaces\Projects\Site $Site
 * @var \QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 */

use QUI\News\Utils\EntryData;

if (
    isset($_REQUEST['sheet']) && is_numeric($_REQUEST['sheet']) && (int)$_REQUEST['sheet'] > 1
    || isset($_REQUEST['limit'])
) {
    $Site->setAttribute('meta.robots', 'noindex,follow');
}

/**
 * News List
 */

$ChildrenList = new QUI\Controls\ChildrenList([
    'ownJsonLd' => false,
    'showTitle' => false,
    'showContent' => false,
    'showImages' => $Site->getAttribute('quiqqer.settings.news.showImages'),
    'showHeader' => $Site->getAttribute('quiqqer.settings.news.showHeader'),
    'showShort' => $Site->getAttribute('quiqqer.settings.news.showShort'),
    'showCreator' => $Site->getAttribute('quiqqer.settings.news.showCreator'),
    'showDate' => $Site->getAttribute('quiqqer.settings.news.showDate'),
    'showTime' => $Site->getAttribute('quiqqer.settings.news.showTime'),
    'Site' => $Site,
    'where' => [
        'type' => [
            'type'  => 'IN',
            'value' => [
                'quiqqer/news:types/news-entry',
                'quiqqer/news:types/news-article'
            ]
        ]
    ],
    'limit' => $Site->getAttribute('quiqqer.settings.news.max'),
    'itemtype' => "https://schema.org/ItemList",
    'child-itemtype' => "https://schema.org/NewsArticle",
    'display' => $Site->getAttribute('quiqqer.settings.news.template'),
    'cardLayout' => $Site->getAttribute('quiqqer.settings.news.cards.layout'),
    'cardColumns' => $Site->getAttribute('quiqqer.settings.news.cards.columns'),
    'cardColumnsTablet' => $Site->getAttribute('quiqqer.settings.news.cards.columnsTablet'),
    'cardColumnsMobile' => $Site->getAttribute('quiqqer.settings.news.cards.columnsMobile'),
    'cardImageFit' => $Site->getAttribute('quiqqer.settings.news.cards.imageFit'),
    'cardAspectRatio' => $Site->getAttribute('quiqqer.settings.news.cards.aspectRatio'),
    'cardGap' => $Site->getAttribute('quiqqer.settings.news.cards.gap'),
    'mediaImagePosition' => $Site->getAttribute('quiqqer.settings.news.media.imagePosition'),
    'mediaImageWidth' => $Site->getAttribute('quiqqer.settings.news.media.imageWidth'),
    'filter' => $Site->getAttribute('quiqqer.settings.news.filter'),
    'tags' => $Site->getAttribute('quiqqer.settings.news.tags'),
    'parentInputList' => false,
    'pinnedAttribute' => 'quiqqer.settings.news.pinned',
    'pinnedOrder' => 'release_from DESC'
]);

$ChildrenList->addEvent('onMetaList', function (
    QUI\Controls\ChildrenList $ChildrenList,
    QUI\Interfaces\Projects\Site $Site,
    QUI\Controls\Utils\MetaList $MetaList
) {
    $MetaList->add('headline', $Site->getAttribute('title'));
    $MetaList->add('datePublished', EntryData::getDisplayDate($Site));
    $dateModified = QUI\Utils\StructuredData::getModificationDate(
        $Site->getAttribute('c_date'),
        $Site->getAttribute('e_date')
    );

    if ($dateModified !== null) {
        $MetaList->add('dateModified', $dateModified);
    }
    $MetaList->add('mainEntityOfPage', $Site->getUrlRewrittenWithHost());

    try {
        // author
        $User = QUI::getUsers()->get($Site->getAttribute('c_user'));

        $MetaList->add('author', $User->getName());
    } catch (QUI\Exception $Exception) {
        QUI\System\Log::writeException($Exception);
    }

    // publisher
    $Publisher = new QUI\Controls\Utils\MetaList\Publisher();
    $Publisher->importFromProject($Site->getProject());
    $MetaList->add('publisher', $Publisher);

    $MetaList->add('image', EntryData::getAbsoluteImageUrl($Site));
});

// Prepare the list before the page head; the site type owns the page graph.
$childrenListHtml = $ChildrenList->create();

try {
    $ListJsonLd = $ChildrenList->getJsonLd();

    if ($ListJsonLd !== null) {
        $Template->getJsonLd()->setJsonLdNode('newsList', $ListJsonLd->getJsonLdData());
    }
} catch (QUI\Exception $Exception) {
    QUI\System\Log::addWarning($Exception->getMessage());
}

$Engine->assign([
    'childrenListHtml' => $childrenListHtml,
    'ChildrenList' => $ChildrenList
]);
