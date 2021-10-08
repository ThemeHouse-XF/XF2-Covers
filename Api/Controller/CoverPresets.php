<?php

namespace ThemeHouse\Covers\Api\Controller;

use ThemeHouse\Covers\Repository\Cover;
use ThemeHouse\Covers\Repository\CoverPreset;
use XF;
use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\ParameterBag;

/**
 * Class CoverPresets
 * @package ThemeHouse\Covers\Api\Controller
 */
class CoverPresets extends AbstractController
{
    /**
     * @return ApiResult
     */
    public function actionGet()
    {

        /** @var CoverPreset $presetRepo */
        $presetRepo = $this->getCoverPresetRepo();

        $presets = $presetRepo->findCoverPresetsForList()->fetch();
        $categories = $presetRepo->findCoverPresetCategories()->fetch();

        if (XF::isApiCheckingPermissions()) {
            $visitor = XF::visitor();
            foreach ($presets as $key => $preset) {
                $userCriteria = $this->app()->criteria('XF:User', $preset->user_criteria);
                if (!$userCriteria->isMatched($visitor)) {
                    unset($presets[$key]);
                    continue;
                }
            }
        }

        return $this->apiResult([
            'presets' => $presets,
            'categories' => $categories
        ]);
    }

    /**
     *
     * @return Cover
     */
    protected function getCoverPresetRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:CoverPreset');
    }

    /**
     * @param $action
     * @param ParameterBag $params
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertApiScopeByRequestMethod('cover_preset');
    }
}
