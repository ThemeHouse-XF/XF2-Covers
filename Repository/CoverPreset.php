<?php

namespace ThemeHouse\Covers\Repository;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Repository\Style;

/**
 * Class CoverPreset
 * @package ThemeHouse\Covers\Repository
 */
class CoverPreset extends Repository
{
    /**
     * @return Entity[]
     */
    public function getCoverPresetList()
    {
        $coverPresets = $this->findCoverPresetsForList()->fetch();

        return $coverPresets->toArray();
    }

    /**
     * @return Finder
     */
    public function findCoverPresetsForList()
    {
        return $this->finder('ThemeHouse\Covers:CoverPreset')
            ->setDefaultOrder('display_order', 'ASC');
    }

    /**
     * @return Finder
     */
    public function findCoverPresetCategories()
    {
        return $this->finder('ThemeHouse\Covers:CoverPresetCategory')
            ->setDefaultOrder('display_order', 'ASC');
    }

    /**
     * @return array
     */
    public function rebuildCoverPresetCache()
    {
        $cache = $this->getCoverPresetCacheData();
        XF::registry()->set('coverPresets', $cache);

        /** @var Style $styleRepo */
        $styleRepo = $this->repository('XF:Style');
        $styleRepo->updateAllStylesLastModifiedDate();

        return $cache;
    }

    /**
     * @return array
     */
    public function getCoverPresetCacheData()
    {
        $coverPresets = $this->finder('ThemeHouse\Covers:CoverPreset')
            ->order(['display_order'])
            ->fetch();

        $cache = [];

        foreach ($coverPresets as $coverPresetId => $coverPreset) {
            /** @var \ThemeHouse\Covers\Entity\CoverPreset $coverPreset */
            $coverPreset = $coverPreset->toArray();

            $cache[$coverPresetId] = $coverPreset;
        }

        return $cache;
    }
}
