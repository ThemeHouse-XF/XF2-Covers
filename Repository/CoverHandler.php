<?php

namespace ThemeHouse\Covers\Repository;

use Exception;
use InvalidArgumentException;
use ThemeHouse\Covers\Cover\AbstractHandler;
use XF;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;

/**
 * Class CoverHandler
 * @package ThemeHouse\Covers\Repository
 */
class CoverHandler extends Repository
{
    /**
     * @var null
     */
    protected $coverHandlers = null;

    /**
     * CoverHandler constructor.
     * @param Manager $em
     * @param $identifier
     * @throws Exception
     */
    public function __construct(Manager $em, $identifier)
    {
        parent::__construct($em, $identifier);
        $this->getCoverHandlers();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getCoverHandlersList()
    {
        $handlers = $this->getCoverHandlers();

        $list = [];
        foreach ($handlers as $contentType => $handler) {
            $list[$contentType] = $handler['object']->getTitle();
        }

        return $list;
    }

    /**
     * @return AbstractHandler[]
     * @throws Exception
     */
    public function getCoverHandlers()
    {
        if ($this->coverHandlers === null) {
            $handlers = [];

            $cache = $this->app()->container('addon.cache');
            foreach (XF::app()->getContentTypeField('cover_handler_class') as $contentType => $handlerClass) {
                if ($contentType == 'resource' && !isset($cache['XFRM'])) {
                    continue;
                }

                if (class_exists($handlerClass)) {
                    $eclass = XF::extendClass($handlerClass);
                    $handlers[$contentType] = [
                        'oclass' => $handlerClass,
                        'eclass' => $eclass,
                        'object' => new $eclass($contentType)
                    ];
                }
            }

            $this->coverHandlers = $handlers;
        }

        return $this->coverHandlers;
    }

    /**
     * @param string $type
     * @param bool $throw
     *
     * @return AbstractHandler
     * @throws Exception
     */
    public function getCoverHandler($type, $throw = false)
    {
        $cache = $this->app()->container('addon.cache');
        if ($type === 'resource' && !isset($cache['XFRM'])) {
            return null;
        }

        $handlerClass = null;
        if (isset($this->coverHandlers[$type])) {
            $handlerClass = $this->coverHandlers[$type]['oclass'];
        }

        if (!$handlerClass) {
            if ($throw) {
                throw new InvalidArgumentException("No cover handler for '$type'");
            }

            return null;
        }

        if (!class_exists($handlerClass)) {
            if ($throw) {
                throw new InvalidArgumentException("Cover handler for '$type' does not exist: $handlerClass");
            }

            return null;
        }

        $handlerClass = XF::extendClass($handlerClass);

        if (!isset($this->coverHandlers[$type])) {
            $eclass = XF::extendClass($handlerClass);
            $this->coverHandlers[$type] = [
                'oclass' => $handlerClass,
                'eclass' => $eclass,
                'object' => new $eclass($type)
            ];
        }

        return $this->coverHandlers[$type]['object'];
    }
}
