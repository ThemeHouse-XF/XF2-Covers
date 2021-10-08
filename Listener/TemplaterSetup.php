<?php

namespace ThemeHouse\Covers\Listener;

use Exception;
use ThemeHouse\Covers\Cover\AbstractHandler;
use ThemeHouse\Covers\Entity\Cover;
use ThemeHouse\Covers\Entity\CoverPreset;
use ThemeHouse\Covers\Repository\CoverHandler;
use XF;
use XF\Container;
use XF\Mvc\Entity\Entity;
use XF\Template\Templater;

/**
 * Class TemplaterSetup
 * @package ThemeHouse\Covers\Listener
 */
class TemplaterSetup
{
    /**
     * @var
     */
    protected static $templater;

    /**
     * @var
     */
    protected static $preset;

    /**
     * @deprecated
     * @param Container $container
     * @param Templater $templater
     */
    public static function run(Container $container, Templater &$templater)
    {
        self::templaterSetup($container, $templater);
    }

    /**
     * @param Container $container
     * @param Templater $templater
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    public static function templaterSetup(Container $container, Templater &$templater)
    {
        self::$templater = $templater;

        $templater->addFunction(
            'thcovers_get_cover',
            ['\ThemeHouse\Covers\Listener\TemplaterSetup', 'fnGetCover']
        );
        $templater->addFunction(
            'thcovers_cover_class',
            ['\ThemeHouse\Covers\Listener\TemplaterSetup', 'fnCoverClass']
        );
        $templater->addFunction(
            'thcovers_cover_styling',
            ['\ThemeHouse\Covers\Listener\TemplaterSetup', 'fnCoverStyling']
        );
        $templater->addFunction(
            'thcovers_cover_preset',
            ['\ThemeHouse\Covers\Listener\TemplaterSetup', 'fnCoverPreset']
        );
        $templater->addFunction(
            'thcovers_cover_url',
            ['\ThemeHouse\Covers\Listener\TemplaterSetup', 'fnCoverUrl']
        );
    }

    /**
     * @param Templater $templater
     * @param $escape
     * @param Entity $entity
     * @return mixed
     */
    public static function fnGetCover(Templater $templater, &$escape, Entity $entity)
    {
        return $entity->getRelationOrDefault('ThCover', true);
    }

    /**
     * @param Templater $templater
     * @param $escape
     * @param Entity $preset
     * @return string
     */
    public static function fnCoverPreset(Templater $templater, &$escape, Entity $preset)
    {
        $escape = true;

        $styling = '';

        /** @var CoverPreset $preset */
        if ($preset->cover_image['url']) {
            $styling .= "background-image: url('{$preset->cover_image['url']}'); ";
        }

        foreach ($preset->cover_styling as $property => $value) {
            if ($value) {
                $styling .= str_replace('_', '-', $property) . ': ' . $value . '; ';
            }
        }

        return $styling;
    }

    /**
     * @param Templater $templater
     * @param $escape
     * @param Entity $entity
     * @param bool $json
     * @return bool|string
     * @throws Exception
     */
    public static function fnCoverClass(Templater $templater, &$escape, Entity $entity, $json = false)
    {
        $escape = true;

        $cover = self::isValidCover($entity);

        $default = null;

        if (!$cover || $cover->cover_state === 'deleted') {
            /** @var CoverHandler $coverRepo */
            $coverRepo = XF::repository('ThemeHouse\Covers:CoverHandler');
            /** @var AbstractHandler $handler */
            $handler = $coverRepo
                ->getCoverHandler($entity->getEntityContentType());

            $default = $handler->getDefaultCover();
        }

        if (!$default) {
            if (!$cover = self::isValidCover($entity)) {
                return false;
            }

            if (!$cover || !$cover->isVisibleToVisitor()) {
                return false;
            }

            if ($json) {
                return '.cover .cover-' . $cover->content_type . ' .cover-' . $cover->content_type . '-' . $cover->content_id . (!empty($cover->cover_image) ? ' .cover-hasImage' : '');
            }

            return 'cover cover-' . $cover->content_type . ' cover-' . $cover->content_type . '-' . $cover->content_id . (!empty($cover->cover_image) ? ' cover-hasImage ' : '') . ($cover->cover_state === 'moderated' ? ' cover-moderated' : '');
        } else {
            return 'cover cover-' . $entity->getEntityContentType() . (!empty($cover->cover_image) ? ' cover-hasImage' : '');
        }
    }

    /**
     * @param $entity
     * @return bool|Cover
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    protected static function isValidCover(
        $entity
    ) {
        if ($entity instanceof Cover) {
            return $entity;
        }

        if (!empty($entity->ThCover) && $entity->ThCover instanceof Cover
            && $entity->ThCover->canView() && !$entity->ThCover->isInsert()) {
            return $entity->ThCover;
        }

        return false;
    }

    /**
     * @param Templater $templater
     * @param $escape
     * @param Entity $entity
     * @param string $sizeCode
     * @param bool $canonical
     * @return mixed|null
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public static function fnCoverUrl(
        Templater $templater,
        &$escape,
        Entity $entity,
        $sizeCode = 'l',
        $canonical = false
    ) {
        $cover = self::isValidCover($entity);

        if (!$cover) {
            return null;
        }

        return $cover_url = self::getCoverRepo()->getCoverUrl(
            $cover->content_type,
            $cover->content_id,
            $cover->cover_image,
            $sizeCode,
            $canonical
        );
    }

    /**
     * @return \ThemeHouse\Covers\Repository\Cover
     */
    protected static function getCoverRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return XF::Repository('ThemeHouse\Covers:Cover');
    }

    /**
     * @param Templater $templater
     * @param $escape
     * @param Entity $entity
     * @param string $sizeCode
     * @param bool $canonical
     * @return string
     * @throws Exception
     */
    public static function fnCoverStyling(
        Templater $templater,
        &$escape,
        Entity $entity,
        $sizeCode = 'l',
        $canonical = false
    ) {
        $escape = true;
        $cover = self::isValidCover($entity);

        $default = null;

        if (!$cover || $cover->cover_state === 'deleted') {
            /** @var CoverHandler $coverRepo */
            $coverRepo = XF::repository('ThemeHouse\Covers:CoverHandler');
            /** @var AbstractHandler $handler */
            $handler = $coverRepo
                ->getCoverHandler($entity->getEntityContentType());

            $default = $handler->getDefaultCover();
        }

        if (!$default) {
            /** @var Cover $cover */
            if (!$cover) {
                return false;
            }

            if (!$cover->isVisibleToVisitor()) {
                return false;
            }

            $preset = $cover->CoverPreset;
        } else {
            if (!$cover || !$cover->isVisibleToVisitor()) {
                $cover = null;
            }

            $preset = $default;
        }

        $styling = '';

        /* User defined Cover */
        if ($cover && $cover->cover_image) {
            if (isset($cover->cover_image['cropY'])) {
                $styling .= "background-position-y: {$cover->cover_image['cropY']}%; ";
            }

            if ($cover_url = self::getCoverRepo()->getCoverUrl(
                $cover->content_type,
                $cover->content_id,
                $cover->cover_image,
                $sizeCode,
                $canonical
            )) {
                $styling .= "background-image: url('{$cover_url}'); ";
            }
        } else {
            /* Preset Cover */
            if ($preset && !empty($preset->cover_image['url'])) {
                $styling .= $preset->css;
            }
        }

        /* User defined Cover */
        if ($cover && $cover->cover_styling) {
            foreach ($cover->cover_styling as $property => $value) {
                if ($value) {
                    $styling .= str_replace('_', '-', $property) . ': ' . $value . '; ';
                }
            }
        } else {
            /* Preset Cover */
            if ($preset && $preset->cover_styling) {
                foreach ($preset->cover_styling as $property => $value) {
                    if ($value) {
                        $styling .= str_replace('_', '-', $property) . ': ' . $value . '; ';
                    }
                }
            }
        }


        return $styling;
    }
}
