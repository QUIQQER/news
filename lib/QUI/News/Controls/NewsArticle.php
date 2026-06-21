<?php

namespace QUI\News\Controls;

use QUI;
use QUI\News\Utils\EntryData;

use function file_exists;
use function in_array;

class NewsArticle extends QUI\Control
{
    protected const ALLOWED_LAYOUTS = [
        'default',
        'imageFirst'
    ];

    protected const TITLE_SIZE_PRESETS = [
        'muchSmaller' => 'clamp(1.5rem, 1vw + 1rem, 2rem)',
        'smaller' => 'clamp(1.75rem, 1.5vw + 1rem, 2.5rem)',
        'normal' => 'var(--qui-fs-1, revert)',
        'larger' => 'clamp(2.5rem, 3vw + 1rem, 3.5rem)',
        'muchLarger' => 'clamp(3rem, 4vw + 1rem, 4.5rem)',
    ];

    protected const SECTION_GAP_PRESETS = [
        'small' => 'clamp(1rem, 2vw, 1.5rem)',
        'normal' => '2rem',
        'large' => 'clamp(2.5rem, 4vw, 3.5rem)',
        'extraLarge' => 'clamp(3rem, 5vw, 5rem)',
    ];

    protected const IMAGE_FIT_PRESETS = [
        'cover' => 'cover',
        'contain' => 'contain',
    ];

    protected const IMAGE_WIDTH_MODES = [
        'auto',
        'content',
        'custom',
    ];

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
        $layout = $this->resolveLayout();

        $template = dirname(__FILE__) . '/NewsArticle.' . $layout . '.html';

        if (!file_exists($template)) {
            $layout = 'default';
            $template = dirname(__FILE__) . '/NewsArticle.' . $layout . '.html';
        }

        $this->applyLayoutVariables($layout);

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
            'centerHeader' => $this->isCenteredHeader($layout),
            'showHeaderImageBlurBackground' => $this->showHeaderImageBlurBackground($layout),
            'showMeta' => $this->showMeta(),
            'showAuthor' => $this->showAuthor(),
            'showDate' => $this->showDate(),
            'showMoreNews' => $this->resolveShowMoreNews(),
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
     * @return string
     */
    protected function resolveLayout(): string
    {
        $layout = (string)$this->getAttribute('layout');

        if ($layout !== '' && in_array($layout, self::ALLOWED_LAYOUTS, true)) {
            return $layout;
        }

        $projectLayout = (string)$this->getSite()->getProject()->getConfig('news.settings.article.defaultLayout');

        if (in_array($projectLayout, self::ALLOWED_LAYOUTS, true)) {
            return $projectLayout;
        }

        return 'default';
    }

    /**
     * @param string $layout
     * @return void
     */
    protected function applyLayoutVariables(string $layout): void
    {
        $Project = $this->getSite()->getProject();
        $prefix = 'news.settings.article.layout.' . $layout . '.';

        $sectionGap = (string)$Project->getConfig($prefix . 'sectionGap');

        if (isset(self::SECTION_GAP_PRESETS[$sectionGap])) {
            $this->setCustomVariable('sectionGap', self::SECTION_GAP_PRESETS[$sectionGap]);
        }

        $titleSize = (string)$Project->getConfig($prefix . 'titleSize');

        if (isset(self::TITLE_SIZE_PRESETS[$titleSize])) {
            $this->setCustomVariable('title-size', self::TITLE_SIZE_PRESETS[$titleSize]);
        }

        $maxHeight = (int)$Project->getConfig($prefix . 'headerImageMaxHeight');

        if ($maxHeight > 0) {
            $this->setCustomVariable('headerImage-height', $maxHeight . 'px');
        }

        $imageFit = (string)$Project->getConfig($prefix . 'imageFit');

        if (isset(self::IMAGE_FIT_PRESETS[$imageFit])) {
            $this->setCustomVariable('headerImage-fit', self::IMAGE_FIT_PRESETS[$imageFit]);
        }

        $headerImageVariables = self::getHeaderImageLayoutVariables(
            (string)$Project->getConfig($prefix . 'imageWidthMode'),
            (int)$Project->getConfig($prefix . 'headerImageMaxWidth'),
            (string)$Project->getConfig($prefix . 'headerBackground'),
            $this->showHeaderImageBlurBackground($layout)
        );

        foreach ($headerImageVariables as $name => $value) {
            $this->setCustomVariable($name, $value);
        }
    }

    /**
     * @param string $layout
     * @return bool
     */
    protected function isCenteredHeader(string $layout): bool
    {
        if ($layout !== 'default') {
            return false;
        }

        return $this->getSite()->getProject()->getConfig(
            'news.settings.article.layout.default.centerHeader'
        ) === '1';
    }

    /**
     * @param string $layout
     * @return bool
     */
    protected function showHeaderImageBlurBackground(string $layout): bool
    {
        return $this->getSite()->getProject()->getConfig(
            'news.settings.article.layout.' . $layout . '.headerImageBlurBackground'
        ) === '1';
    }

    /**
     * @return bool
     */
    protected function resolveShowMoreNews(): bool
    {
        $value = $this->getAttribute('showMoreNews');

        if ($value === '' || $value === null) {
            return self::toBool(
                $this->getSite()->getProject()->getConfig('news.settings.article.showMoreNews'),
                true
            );
        }

        return self::toBool($value, true);
    }

    /**
     * @param mixed $value
     * @param bool $default
     * @return bool
     */
    protected static function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === '' || $value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === '0' || $value === 0) {
            return false;
        }

        if ($value === '1' || $value === 1) {
            return true;
        }

        return (bool)$value;
    }

    /**
     * @param string $widthMode
     * @param int $maxWidth
     * @param string $headerBackground
     * @param bool $showBlurBackground
     * @return array<string, string>
     */
    protected static function getHeaderImageLayoutVariables(
        string $widthMode,
        int $maxWidth,
        string $headerBackground,
        bool $showBlurBackground
    ): array {
        $variables = [];

        if (!in_array($widthMode, self::IMAGE_WIDTH_MODES, true)) {
            $widthMode = 'auto';
        }

        switch ($widthMode) {
            case 'content':
                $variables['headerImage-width'] = '100%';
                $variables['headerImage-maxWidth'] = 'var(--qui-page-contentSize, 100%)';
                break;

            case 'custom':
                $variables['headerImage-width'] = '100%';

                if ($maxWidth > 0) {
                    $variables['headerImage-maxWidth'] = $maxWidth . 'px';
                }
                break;

            case 'auto':
            default:
                $variables['headerImage-width'] = '100%';
                $variables['headerImage-maxWidth'] = '100%';
        }

        if ($headerBackground !== '' && !$showBlurBackground) {
            $variables['header-background'] = $headerBackground;
        }

        return $variables;
    }

    /**
     * @param string $name
     * @param string $value
     * @return void
     */
    protected function setCustomVariable(string $name, string $value): void
    {
        if ($name === '' || $value === '') {
            return;
        }

        $this->setStyle('--_q-controlConf-' . $name, $value);
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
