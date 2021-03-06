<?php
namespace WapplerSystems\Videos\Resource\Rendering;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class VideoTagRenderer
 */
class VideoTagRenderer implements FileRendererInterface
{
    /**
     * Mime types that can be used in the HTML Video tag
     *
     * @var array
     */
    protected $possibleMimeTypes = ['video/mp4', 'video/webm', 'video/ogg', 'application/ogg'];

    /**
     * Returns the priority of the renderer
     * This way it is possible to define/overrule a renderer
     * for a specific file type/context.
     * For example create a video renderer for a certain storage/driver type.
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return int
     */
    public function getPriority()
    {
        return 1;
    }

    /**
     * Check if given File(Reference) can be rendered
     *
     * @param FileInterface $file File or FileReference to render
     * @return bool
     */
    public function canRender(FileInterface $file)
    {
        return in_array($file->getMimeType(), $this->possibleMimeTypes, true);
    }

    /**
     * Render for given File(Reference) HTML output
     *
     * @param FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options controls = TRUE/FALSE (default TRUE), autoplay = TRUE/FALSE (default FALSE), loop = TRUE/FALSE (default FALSE)
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     * @return string
     */
    public function render(FileInterface $file, $width, $height, array $options = [], $usedPathsRelativeToCurrentScript = false)
    {

        // If autoplay isn't set manually check if $file is a FileReference take autoplay from there
        if (!isset($options['autoplay']) && $file instanceof FileReference) {
            $autoplay = $file->getProperty('autoplay');
            if ($autoplay !== null) {
                $options['autoplay'] = $autoplay;
            }
        }

        $attributes = [];
        if ((int)$width > 0) {
            $attributes[] = 'width="' . (int)$width . '"';
        }
        if ((int)$height > 0) {
            $attributes[] = 'height="' . (int)$height . '"';
        }
        if (!isset($options['controls']) || !empty($options['controls'])) {
            $attributes[] = 'controls';
        }
        if (!empty($options['autoplay'])) {
            $attributes[] = 'autoplay';
        }
        if (!empty($options['muted'])) {
            $attributes[] = 'muted';
        }
        if (!empty($options['loop'])) {
            $attributes[] = 'loop';
        }

        if (!empty($options['poster'])) {
            $attributes[] = 'poster="'.$options['poster'].'"';
        }

        if ($file->getOriginalFile()->getProperty('poster')) {
            /** @var FileRepository $fileRepository */
            $fileRepository = GeneralUtility::makeInstance(FileRepository::class);

            $fileObjects = $fileRepository->findByRelation('sys_file_metadata', 'poster', $file->getOriginalFile()->_getMetaData()['uid']);

            if (isset($fileObjects[0])) {
                /** @var FileReference $posterFile */
                $posterFile = $fileObjects[0];
                $attributes[] = 'poster="' . $posterFile->getPublicUrl() . '"';
            }

        }

        /* TODO: make it configurable */
        $attributes[] = 'oncontextmenu="return false;"';

        $tracks = '';
        if ($file->getOriginalFile()->getProperty('tracks')) {

            /** @var FileRepository $fileRepository */
            $fileRepository = GeneralUtility::makeInstance(FileRepository::class);

            $fileObjects = $fileRepository->findByRelation('sys_file_metadata', 'tracks', $file->getOriginalFile()->_getMetaData()['uid']);

            /** @var FileReference $fileObject */
            foreach ($fileObjects as $key => $fileObject) {

                $trackLanguage = $fileObject->getProperty('track_language');
                $trackType = $fileObject->getProperty('track_type');
                $languageTitle = LocalizationUtility::translate('language.default', 'videos');
                $isoCode = $GLOBALS['TSFE']->sys_language_isocode;

                if ($trackLanguage > 0) {
                    /** @var QueryBuilder $queryBuilder */
                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');
                    $languageRecord = $queryBuilder
                        ->select('*')
                        ->from('sys_language')
                        ->where(
                            $queryBuilder->expr()->eq(
                                'uid',
                                $queryBuilder->createNamedParameter($trackLanguage, \PDO::PARAM_INT)
                            )
                        )
                        ->execute()
                        ->fetch();

                    if ($languageRecord) {
                        $languageTitle = $languageRecord['title'];
                        $isoCode = $languageRecord['language_isocode'];
                    }
                }

                $tracks .= '<track label="'.$languageTitle.'" kind="'.($trackType ?: 'subtitles').'" srclang="'.$isoCode.'" src="' . $fileObject->getPublicUrl() . '">';
            }
        }


        foreach (['class', 'dir', 'id', 'lang', 'style', 'title', 'accesskey', 'tabindex', 'onclick'] as $key) {
            if (!empty($options[$key])) {
                $attributes[] = $key . '="' . htmlspecialchars($options[$key]) . '"';
            }
        }
        $attributes[] = 'data-setup="{}"';

        return sprintf(
            '<video%s><source src="%s" type="%s">%s</video>',
            empty($attributes) ? '' : ' ' . implode(' ', $attributes),
            htmlspecialchars($file->getPublicUrl($usedPathsRelativeToCurrentScript)),
            $file->getMimeType(),
            $tracks
        );
    }
}
