<?php

namespace ThemeHouse\Covers\Repository;

use Exception;
use ThemeHouse\Covers\Cover\AbstractHandler;
use XF;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Cover
 * @package ThemeHouse\Covers\Repository
 */
class Cover extends Repository
{
    /**
     * @return array
     */
    public function getDimensionConstraints()
    {
        return [
            'min' => [350, 150]
        ];
    }

    /**
     * @param $contentType
     * @param $contentId
     * @param $coverImage
     * @param $sizeCode
     * @param bool $canonical
     * @return mixed|null
     */
    public function getCoverUrl($contentType, $contentId, $coverImage, $sizeCode, $canonical = false)
    {
        $sizeMap = $this->getCoverSizeMap();
        if (!isset($sizeMap[$sizeCode])) {
            // Always fallback to 's' in the event of an unknown size (e.g. 'xs', 'xxs' etc.)
            $sizeCode = 's';
        }

        if (!empty($coverImage)) {
            $group = floor($contentId / 1000);
            $coverDate = $coverImage['date'];
            return $this->app()->applyExternalDataUrl(
                "covers/{$contentType}/{$sizeCode}/{$group}/{$contentId}.jpg?{$coverDate}",
                $canonical
            );
        } else {
            return null;
        }
    }

    /**
     * @return array
     */
    public function getCoverSizeMap()
    {
        return [
            'o' => [2560, 1440],
            'l' => [1200, 700],
            'm' => [700, 500],
            's' => [350, 150]
        ];
    }

    /**
     * @param $contentType
     * @param $contentId
     * @param $sizeCode
     * @return string
     */
    public function getAbstractedCustomCoverPath($contentType, $contentId, $sizeCode)
    {
        return sprintf(
            'data://covers/%s/%s/%d/%d.jpg',
            $contentType,
            $sizeCode,
            floor($contentId / 1000),
            $contentId
        );
    }

    /**
     * @param $cover
     * @param $string
     * @param $alertReason
     */
    public function sendModeratorActionAlert($cover, $string, $alertReason)
    {
        // TODO
    }

    /**
     * @return Finder
     */
    public function findCoversForList()
    {
        $finder = $this->finder('ThemeHouse\Covers:Cover');

        $finder->with('CoverUser');

        $finder->where('cover_state', '<>', 'deleted');

        $finder->setDefaultOrder('cover_date', 'desc');

        return $finder;
    }

    /**
     * @return AbstractHandler[]
     * @throws Exception
     */
    public function getCoverHandlers()
    {
        $handlers = [];

        foreach (XF::app()->getContentTypeField('cover_handler_class') as $contentType => $handlerClass) {
            if (class_exists($handlerClass)) {
                $handlerClass = XF::extendClass($handlerClass);
                $handlers[$contentType] = new $handlerClass($contentType);
            }
        }

        return $handlers;
    }
}
