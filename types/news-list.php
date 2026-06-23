<?php

/**
 * Declare "global" variables for PHPStan and IDEs
 *
 * @var \QUI\Interfaces\Projects\Site $Site
 * @var \QUI\Interfaces\Template\EngineInterface $Engine
 */

use QUI\Projects\Media\Utils as MediaUtils;

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
    'filter' => $Site->getAttribute('quiqqer.settings.news.filter'),
    'tags' => $Site->getAttribute('quiqqer.settings.news.tags'),
    'parentInputList' => false
]);

$ChildrenList->addEvent('onMetaList', function (
    QUI\Controls\ChildrenList $ChildrenList,
    QUI\Interfaces\Projects\Site $Site,
    QUI\Controls\Utils\MetaList $MetaList
) {
    $MetaList->add('headline', $Site->getAttribute('title'));
    $MetaList->add('datePublished', $Site->getAttribute('release_from'));
    $MetaList->add('dateModified', $Site->getAttribute('e_date'));
    $MetaList->add('mainEntityOfPage', $Site->getUrlRewritten());

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

    // image
    $image = $Site->getAttribute('image_site');

    if (\strpos($image, 'fa-') !== false) {
        $image = '';
    }

    if (MediaUtils::isMediaUrl($image)) {
        try {
            $Image = MediaUtils::getImageByUrl($image);
            $image = $Image->getSizeCacheUrl();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $image = '';
        }
    }

    // use default
    if (empty($image)) {
        try {
            $Placeholder = $Site->getProject()->getMedia()->getPlaceholderImage();

            if ($Placeholder) {
                $image = $Placeholder->getSizeCacheUrl();
            }
        } catch (QUI\Exception $Exception) {
        }
    }

    $MetaList->add('image', $image);
});

$Engine->assign([
    'ChildrenList' => $ChildrenList
]);
