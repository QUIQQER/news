<?php

/**
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

$NewsArticle = new QUI\News\Controls\NewsArticle([
    'ownJsonLd' => false,
    'layout' => $Site->getAttribute('quiqqer.news.settings.newsArticle.layout'),
    'headerImage' => $Site->getAttribute('quiqqer.news.settings.newsArticle.headerImage'),
    'metaVisibility' => $Site->getAttribute('quiqqer.news.settings.meta.visibility'),
    'showMoreNews' => $Site->getAttribute('quiqqer.news.settings.more.enabled'),
    'moreAmount' => $Site->getAttribute('quiqqer.news.settings.more.amount'),
    'showMoreNewsDate' => $Site->getAttribute('quiqqer.news.settings.more.showDate'),
    'showMoreNewsTime' => $Site->getAttribute('quiqqer.news.settings.more.showTime'),
    'moreTemplate' => $Site->getAttribute('quiqqer.news.settings.more.template'),
    'guestAuthorEnable' => $Site->getAttribute('quiqqer.news.settings.guestAuthor.enable'),
    'guestAuthorUser' => $Site->getAttribute('quiqqer.news.settings.guestAuthor.quiqqerUser'),
    'guestAuthorName' => $Site->getAttribute('quiqqer.news.settings.guestAuthor.name'),
    'guestAuthorAvatar' => $Site->getAttribute('quiqqer.news.settings.guestAuthor.avatar'),
    'Site' => $Site
]);

try {
    $ArticleJsonLd = $NewsArticle->getJsonLd();
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

$Engine->assign('NewsArticle', $NewsArticle);
