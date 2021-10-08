<?php

namespace ThemeHouse\Covers\Admin\Controller;

use ThemeHouse\Covers\Entity\CoverPreset;
use ThemeHouse\Covers\Entity\CoverPresetCategory;
use ThemeHouse\Covers\Repository\CoverHandler;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\Toggle;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;
use XF\PrintableException;

/**
 * Class CoverPresets
 * @package ThemeHouse\Covers\Admin\Controller
 */
class CoverPresets extends AbstractController
{
    /**
     * @return View
     */
    public function actionIndex()
    {
        $coverPresets = $this->getCoverPresetRepo()->findCoverPresetsForList()->fetch()->groupBy('cover_preset_category_id');
        $categories = $this->getCoverPresetRepo()->findCoverPresetCategories()->fetch();

        $viewParams = [
            'coverPresets' => $coverPresets,
            'categories' => $categories
        ];

        return $this->view('ThemeHouse\Covers:CoverPreset\Listing', 'thcovers_cover_preset_list', $viewParams);
    }

    /**
     * @return \ThemeHouse\Covers\Repository\CoverPreset
     */
    protected function getCoverPresetRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:CoverPreset');
    }

    /**
     * @param ParameterBag $params
     * @return View
     * @throws Exception
     * @throws \Exception
     */
    public function actionEdit(ParameterBag $params)
    {
        $coverPreset = $this->assertCoverPresetExists($params['cover_preset_id']);
        return $this->coverPresetAddEdit($coverPreset);
    }

    /**
     * @param string $id
     * @param array|string|null $with
     * @param null|string $phraseKey
     *
     * @return CoverPreset
     * @throws Exception
     */
    protected function assertCoverPresetExists($id, $with = null, $phraseKey = null)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->assertRecordExists('ThemeHouse\Covers:CoverPreset', $id, $with, $phraseKey);
    }

    /**
     * @param CoverPreset $coverPreset
     * @return View
     * @throws \Exception
     */
    public function coverPresetAddEdit(CoverPreset $coverPreset)
    {
        $userCriteria = $this->app->criteria('XF:User', $coverPreset->user_criteria);
        $categories = $this->getCoverPresetRepo()->findCoverPresetCategories()->fetch();
        $coverRepo = $this->getCoverHandlerRepo();

        $viewParams = [
            'coverPreset' => $coverPreset,
            'categories' => $categories,
            'userCriteria' => $userCriteria,
            'handlers' => $coverRepo->getCoverHandlers(),
        ];

        return $this->view('ThemeHouse\Covers:CoverPreset\Edit', 'thcovers_cover_preset_edit', $viewParams);
    }

    /**
     * @return CoverHandler
     */
    protected function getCoverHandlerRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:CoverHandler');
    }

    /**
     * @return View
     * @throws \Exception
     */
    public function actionAdd()
    {
        /** @var CoverPreset $coverPreset */
        $coverPreset = $this->em()->create('ThemeHouse\Covers:CoverPreset');

        return $this->coverPresetAddEdit($coverPreset);
    }

    /**
     * @param ParameterBag $params
     * @return Redirect
     * @throws Exception
     * @throws PrintableException
     */
    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params['cover_preset_id']) {
            $coverPreset = $this->assertCoverPresetExists($params['cover_preset_id']);
        } else {
            $coverPreset = $this->em()->create('ThemeHouse\Covers:CoverPreset');
        }

        $previousImage = $coverPreset->cover_image;

        $this->coverPresetSaveProcess($coverPreset)->run();

        $newImage = $coverPreset->cover_image;

        if (!empty($newImage['url']) && $newImage['url'] !== ($previousImage['url'] ?? null))
        {
            // default new images to 50%
            $newImage['banner_position_y'] = '50';
            $coverPreset->cover_image = $newImage;
            $coverPreset->save();
            // redirect to positioner
            $url = $this->buildLink('cover-presets/edit', $coverPreset) . '#cover-preset-styling';
        }
        else
        {
            $url = $this->buildLink('cover-presets');
        }

        return $this->redirect($url);
    }

    /**
     * @param CoverPreset $coverPreset
     * @return FormAction
     */
    protected function coverPresetSaveProcess(CoverPreset $coverPreset)
    {
        $entityInput = $this->filter([
            'cover_image' => 'array',
            'cover_styling' => 'array',
            'display_order' => 'str',
            'enabled' => 'bool',
            'user_criteria' => 'array',
            'cover_preset_category_id' => 'int'
        ]);

        $defaults = $this->filter('defaults', 'array');
        $entityInput['default_for'] = $defaults;

        $form = $this->formAction();
        $form->basicEntitySave($coverPreset, $entityInput);

        $masterPhrase = $coverPreset->getMasterPhrase();
        $phraseInput = ['phrase_text' => $this->filter('title', 'str')];
        $form->basicEntitySave($masterPhrase, $phraseInput);

        if ($defaults) {
            $form->complete(function () use ($defaults, $coverPreset) {
                $finder = $this->finder('ThemeHouse\Covers:CoverPreset');

                $queries = [];

                foreach ($defaults as $default) {
                    $queries[] = "FIND_IN_SET('{$default}', default_for)";
                }

                $finder
                    ->where('cover_preset_id', '<>', $coverPreset->cover_preset_id)
                    ->whereSql(implode(' OR ', $queries));

                $coverPresets = $finder->fetch();

                foreach ($coverPresets as $coverPreset) {
                    /** @var CoverPreset $coverPreset */
                    $coverPreset->default_for = array_diff($coverPreset->default_for, $defaults);
                    $coverPreset->save();
                }
            });
        }

        return $form;
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws PrintableException
     * @throws Exception
     */
    public function actionDelete(ParameterBag $params)
    {
        $coverPreset = $this->assertCoverPresetExists($params['cover_preset_id']);

        if ($this->isPost()) {
            $coverPreset->delete();
            return $this->redirect($this->buildLink('cover-presets'));
        } else {
            $viewParams = [
                'coverPreset' => $coverPreset
            ];

            return $this->view('ThemeHouse\Covers:CoverPreset\Delete', 'thcovers_cover_preset_delete', $viewParams);
        }
    }

    /**
     * @return Redirect|View
     */
    public function actionSort()
    {
        $coverPresets = $this->getCoverPresetRepo()->findCoverPresetsForList()->fetch();
        $coverPresetsGrouped = $coverPresets->groupBy('cover_preset_category_id');
        $categories = $this->getCoverPresetRepo()->findCoverPresetCategories()->fetch();

        if ($this->isPost()) {
            foreach ($this->filter('presets', 'array-json-array') AS $presetCategory) {
                $lastOrder = 0;
                foreach ($presetCategory AS $key => $presetValue) {
                    $lastOrder += 10;

                    /** @var CoverPreset $preset */
                    $preset = $coverPresets[$presetValue['id']];
                    $preset->display_order = $lastOrder;
                    $preset->cover_preset_category_id = $presetValue['parent_id'];
                    $preset->saveIfChanged();
                }
            }

            return $this->redirect($this->buildLink('cover-presets'));
        } else {
            $viewParams = [
                'coverPresets' => $coverPresetsGrouped,
                'categories' => $categories
            ];
            return $this->view('ThemeHouse\Covers:CoverPreset\Sort', 'thcovers_cover_preset_sort', $viewParams);
        }
    }

    /**
     * @return Message
     */
    public function actionToggle()
    {
        /** @var Toggle $plugin */
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('ThemeHouse\Covers:CoverPreset', 'enabled');
    }

    /**
     * @return View
     */
    public function actionCategoryAdd()
    {
        /** @var CoverPresetCategory $category */
        $category = $this->em()->create('ThemeHouse\Covers:CoverPresetCategory');
        return $this->categoryAddEdit($category);
    }

    /**
     * @param CoverPresetCategory $category
     * @return View
     */
    public function categoryAddEdit(CoverPresetCategory $category)
    {
        $viewParams = [
            'category' => $category
        ];

        return $this->view('ThemeHouse\Covers:CoverPreset\Category\Edit', 'th_covers_preset_category_edit',
            $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return View
     * @throws Exception
     */
    public function actionCategoryEdit(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $category = $this->assertPresetCategoryExists($params->cover_preset_category_id);
        return $this->categoryAddEdit($category);
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return CoverPresetCategory
     * @throws Exception
     */
    protected function assertPresetCategoryExists($id, $with = null, $phraseKey = null)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->assertRecordExists('ThemeHouse\Covers:CoverPresetCategory', $id, $with, $phraseKey);
    }

    /**
     * @param ParameterBag $params
     * @return Redirect
     * @throws Exception
     * @throws PrintableException
     */
    public function actionCategorySave(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        if ($params->cover_preset_category_id) {
            /** @noinspection PhpUndefinedFieldInspection */
            $category = $this->assertPresetCategoryExists($params->cover_preset_category_id);
        } else {
            $category = $this->em()->create('ThemeHouse\Covers:CoverPresetCategory');
        }

        $this->categorySaveProcess($category)->run();

        return $this->redirect($this->buildLink('cover-presets'));
    }

    /**
     * @param CoverPresetCategory $category
     * @return FormAction
     */
    protected function categorySaveProcess(CoverPresetCategory $category)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'display_order' => 'str'
        ]);
        $form->basicEntitySave($category, $input);

        $masterPhrase = $category->getMasterPhrase();
        $phraseInput = ['phrase_text' => $this->filter('title', 'str')];
        $form->basicEntitySave($masterPhrase, $phraseInput);

        return $form;
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws PrintableException
     * @throws Exception
     */
    public function actionCategoryDelete(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $category = $this->assertPresetCategoryExists($params->cover_preset_category_id);

        if ($this->isPost()) {
            $category->delete();
            return $this->redirect($this->buildLink('cover-presets'));
        } else {
            $viewParams = [
                'category' => $category
            ];

            return $this->view('ThemeHouse\Covers:CoverPreset\Category\Delete', 'thcovers_cover_preset_category_delete',
                $viewParams);
        }
    }

    /**
     * @param $action
     * @param ParameterBag $params
     * @throws Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('thCovers');
    }
}
