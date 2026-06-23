<?php

/**
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

$NewsArticle = new QUI\News\Controls\NewsArticle([
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

$Engine->assign('NewsArticle', $NewsArticle);
