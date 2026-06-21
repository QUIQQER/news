<?php

namespace QUI\News\Controls;

use QUI;
use QUI\News\Utils\EntryData;

use function file_exists;

class NewsArticle extends QUI\Control
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'layout' => 'default',
            'headerImage' => '',
            'metaVisibility' => 'default',
            'showMoreNews' => true,
            'moreAmount' => 2,
            'showMoreNewsDate' => true,
            'showMoreNewsTime' => false,
            'moreTemplate' => 'default',
            'guestAuthorEnable' => false,
            'guestAuthorUser' => false,
            'guestAuthorName' => '',
            'guestAuthorAvatar' => '',
            'Site' => false
        ]);

        $this->addCSSClass('quiqqer-news-articleControl');
        $this->addCSSFile(dirname(__FILE__) . '/NewsArticle.css');

        parent::__construct($attributes);
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Site = $this->getSite();
        $layout = (string)$this->getAttribute('layout');

        if ($layout === '') {
            $layout = 'default';
        }

        $template = dirname(__FILE__) . '/NewsArticle.' . $layout . '.html';

        if (!file_exists($template)) {
            $layout = 'default';
            $template = dirname(__FILE__) . '/NewsArticle.' . $layout . '.html';
        }

        $authorData = EntryData::resolveAuthorData($Site, [
            'enabled' => (bool)$this->getAttribute('guestAuthorEnable'),
            'user' => $this->getAttribute('guestAuthorUser'),
            'name' => $this->getAttribute('guestAuthorName'),
            'avatar' => $this->getAttribute('guestAuthorAvatar')
        ]);

        $displayImage = EntryData::getDisplayImage(
            $Site,
            (string)$this->getAttribute('headerImage')
        );

        $MetaList = EntryData::createMetaList($Site, $authorData['author'], $displayImage);
        $moreEntries = EntryData::getMoreEntries($Site, (int)$this->getAttribute('moreAmount'));
        $moreTemplate = dirname(__FILE__) . '/NewsArticle.more.' . $this->getAttribute('moreTemplate') . '.html';

        if (!file_exists($moreTemplate)) {
            $moreTemplate = dirname(__FILE__) . '/NewsArticle.more.default.html';
        }

        $Template = $Engine->getTemplateVariable('Template');

        if ($Template instanceof QUI\Template) {
            try {
                $Template->extendHeader($MetaList->getJsonLdSchema());
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }

        $Engine->assign([
            'Site' => $Site,
            'MetaList' => $MetaList,
            'date' => EntryData::getDisplayDate($Site),
            'author' => $authorData['author'],
            'headerImage' => $displayImage,
            'showMeta' => $this->showMeta(),
            'showAuthor' => $this->showAuthor(),
            'showDate' => $this->showDate(),
            'showMoreNews' => (bool)$this->getAttribute('showMoreNews'),
            'showMoreNewsDate' => (bool)$this->getAttribute('showMoreNewsDate'),
            'showMoreNewsTime' => (bool)$this->getAttribute('showMoreNewsTime'),
            'previousSiblings' => $moreEntries['previous'],
            'nextSiblings' => $moreEntries['next'],
            'tags' => EntryData::getTags($Site),
            'moreTemplate' => $moreTemplate,
            'layout' => $layout
        ]);

        return $Engine->fetch($template);
    }

    /**
     * @return QUI\Interfaces\Projects\Site
     */
    protected function getSite()
    {
        return $this->getAttribute('Site');
    }

    /**
     * @return bool
     */
    protected function showMeta(): bool
    {
        return $this->showAuthor() || $this->showDate();
    }

    /**
     * @return bool
     */
    protected function showAuthor(): bool
    {
        return match ((string)$this->getAttribute('metaVisibility')) {
            'showAll', 'showAuthor' => true,
            'showDate', 'hide' => false,
            default => false
        };
    }

    /**
     * @return bool
     */
    protected function showDate(): bool
    {
        return match ((string)$this->getAttribute('metaVisibility')) {
            'showAll', 'showDate', 'default' => true,
            'showAuthor', 'hide' => false,
            default => false
        };
    }
}
