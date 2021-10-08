<?php

namespace ThemeHouse\Covers\XF\SubContainer;

/**
 * Class Import
 * @package ThemeHouse\Covers\XF\SubContainer
 */
class Import extends XFCP_Import
{
    /**
     *
     */
    public function initialize()
    {
        $initialize = parent::initialize();

        $importers = $this->container('importers');

        $this->container['importers'] = function () use ($importers) {
            $importers[] = 'ThemeHouse\Covers:THCovers';
            return $importers;
        };

        return $initialize;
    }
}
