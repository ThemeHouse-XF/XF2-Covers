<?php

namespace ThemeHouse\Covers\Admin\Controller;

use ThemeHouse\Covers\Repository\CoverPreset;
use ThemeHouse\Covers\Service\Cover\Deleter;
use ThemeHouse\Covers\Service\Cover\Editor;
use ThemeHouse\Covers\Service\Cover\Image;
use XF;
use XF\Admin\Controller\AbstractController;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\Reroute;
use XF\Mvc\Reply\View;
use XF\PrintableException;
use XF\Util\Color;

/**
 * Class Cover
 * @package ThemeHouse\Covers\Admin\Controller
 */
class Cover extends AbstractController
{
    /**
     * @return Redirect|Reroute|View
     * @throws Exception
     * @throws \Exception
     */
    public function actionIndex()
    {
        $linkFilters = [];

        $page = $this->filterPage();
        $perPage = 20;

        if ($this->request->exists('delete_covers') && count($this->filter('cover_ids', 'array-uint'))) {
            return $this->rerouteController(__CLASS__, 'mass-delete');
        }

        /** @var \ThemeHouse\Covers\Repository\Cover $coverRepo */
        $coverRepo = $this->getCoverRepo();

        $coverFinder = $coverRepo->findCoversForList()->limitByPage($page, $perPage);

        if ($contentType = $this->filter('content_type', 'str')) {
            $coverFinder->where('content_type', $contentType);
            $linkFilters['content_type'] = $contentType;
        }

        if ($username = $this->filter('username', 'str')) {
            /** @var User $user */
            $user = $this->finder('XF:User')->where('username', $username)->fetchOne();
            if ($user) {
                $coverFinder->where('cover_user_id', $user->user_id);
                $linkFilters['username'] = $user->username;
            }
        }

        if ($start = $this->filter('start', 'datetime')) {
            $coverFinder->where('cover_date', '>', $start);
            $linkFilters['start'] = $start;
        }

        if ($end = $this->filter('end', 'datetime')) {
            $coverFinder->where('cover_date', '<', $end);
            $linkFilters['end'] = $end;
        }

        if ($linkFilters && $this->isPost()) {
            return $this->redirect($this->buildLink('covers', null, $linkFilters), '');
        }

        $total = $coverFinder->total();
        $this->assertValidPage($page, $perPage, $total, 'covers');

        $covers = $coverFinder->fetch();
        foreach ($covers as $key => $cover) {
            if (!$cover->Content) {
                $covers->offsetUnset($key);
                /** @var \ThemeHouse\Covers\Entity\Cover $cover */
                $cover->delete();
            }
        }

        $viewParams = [
            'covers' => $covers,
            'handlers' => $coverRepo->getCoverHandlers(),

            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,

            'linkFilters' => $linkFilters,

            'datePresets' => XF::language()->getDatePresets()
        ];
        return $this->view('ThemeHouse\Covers:Cover\Listing', 'thcovers_list', $viewParams);
    }

    /**
     * @return Repository
     */
    protected function getCoverRepo()
    {
        return $this->repository('ThemeHouse\Covers:Cover');
    }

    /**
     * @return Redirect|View
     * @throws PrintableException
     */
    public function actionMassDelete()
    {
        $coverIds = $this->filter('cover_ids', 'array-uint');
        $covers = $this->finder('ThemeHouse\Covers:Cover')->where('cover_id', '=', $coverIds)->fetch();

        if (!$covers->count()) {
            return $this->redirect($this->buildLink('covers'));
        }

        if ($this->isPost() && $this->filter('confirm', 'bool')) {
            foreach ($covers as $cover) {
                /** @var \ThemeHouse\Covers\Entity\Cover $cover */
                $cover->delete();
            }

            return $this->redirect($this->buildLink('covers'));
        }

        return $this->view('ThemeHouse\Covers:MassDelete', 'thcovers_cover_bulk_delete', [
            'covers' => $covers
        ]);
    }


    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws Exception
     * @throws PrintableException
     */
    public function actionPreset(ParameterBag $params)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        /** @noinspection PhpUndefinedFieldInspection */
        $cover = $this->assertCoverExists($params->cover_id);

        /** @var CoverPreset $presetRepo */
        $presetRepo = $this->getCoverPresetRepo();

        $presets = $presetRepo->findCoverPresetsForList()->fetch();

        $csrfValid = $this->validateCsrfToken($this->filter('t', 'str'));

        if ($this->request->exists('cover_preset_id') && $csrfValid) {
            $presetId = $this->filter('cover_preset_id', 'int');

            if ($presetId != 0) {
                if (!$presets->offsetExists($presetId)) {
                    return $this->noPermission();
                }

                /** @var Deleter $deleter */
                $deleter = $this->service('ThemeHouse\Covers:Cover\Deleter', $cover);
                $deleter->delete();
            }

            $cover->cover_preset = $presetId;

            $cover->cover_state = $presetId || $cover->cover_image || $cover->cover_styling ? 'visible' : 'deleted';

            $cover->save();

            if ($cover->cover_state === 'visible') {
                return $this->redirect($this->buildLink('covers/view', $cover));
            } else {
                return $this->redirect($this->buildLink('covers'));
            }
        } else {
            $viewParams = [
                'presets' => $presets,
                'cover' => $cover
            ];

            return $this->view('ThemeHouse\Covers:Cover\Preset', 'thcovers_cover_preset', $viewParams);
        }
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return Entity
     * @throws Exception
     */
    protected function assertCoverExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\Covers:Cover', $id, $with, $phraseKey);
    }

    /**
     *
     * @return \ThemeHouse\Covers\Repository\Cover
     */
    protected function getCoverPresetRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:CoverPreset');
    }

    /**
     * @param ParameterBag $params
     * @return Error|Redirect|View
     * @throws PrintableException
     * @throws Exception
     */
    public function actionImage(ParameterBag $params)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        /** @noinspection PhpUndefinedFieldInspection */
        $cover = $this->assertCoverExists($params->cover_id);

        /** @var \ThemeHouse\Covers\Repository\Cover $coverRepo */
        $coverRepo = $this->getCoverRepo();

        if ($this->isPost()) {
            /** @var Image $coverImageService */
            $coverImageService = $this->service('ThemeHouse\Covers:Cover\Image', $cover->content_id,
                $cover->content_type);
            $coverImageType = $this->filter('cover_image_type', 'str');
            $coverDetails = [];

            if ($coverImageType == 'custom') {
                $upload = $this->request->getFile('upload', false);
                if (!empty($upload)) {
                    if (!$coverImageService->setImageFromUpload($upload)) {
                        return $this->error($coverImageService->getError());
                    }
                }

                $coverImageUrl = $this->filter('cover_image_url', 'str');
                if (!empty($coverImageUrl)) {
                    if (!$coverImageService->downloadImage($coverImageUrl)) {
                        return $this->error($coverImageService->getError());
                    }
                }

                $coverImageDetails = $coverImageService->updateCoverImage();
                if (!$coverImageDetails) {
                    return $this->error(XF::phrase('thcovers_new_cover_could_not_be_processed'));
                }

                $coverDetails = $coverDetails + $coverImageDetails;
            }

            $editor = $this->setupCoverEdit($cover, $coverDetails);
            $errors = null;
            if (!$editor->validate($errors)) {
                return $this->error($errors);
            }

            $editor->save();

            $redirect = $this->redirect($this->buildLink('covers/view', $cover));
            if ($this->filter('_xfWithData', 'bool')) {
                $covers = [];
                $coverCodes = array_keys($coverRepo->getCoverSizeMap());
                foreach ($coverCodes as $code) {
                    $covers[$code] = $this->app->templater()->func('thcovers_cover', [$cover, $code]);
                }
                $redirect->setJsonParam('covers', $covers);
            }
            return $redirect;
        } else {
            $viewParams = [
                'cover' => $cover,
                'maxSize' => $coverRepo->getCoverSizeMap()['m'],
            ];

            return $this->view('ThemeHouse\Covers:Cover\Image', 'thcovers_cover_image', $viewParams);
        }
    }

    /**
     * @param \ThemeHouse\Covers\Entity\Cover $cover
     * @param array $coverDetails
     * @return Editor
     */
    protected function setupCoverEdit(\ThemeHouse\Covers\Entity\Cover $cover, array $coverDetails = [])
    {
        /** @var Editor $coverEditorService */
        $coverEditorService = $this->service('ThemeHouse\Covers:Cover\Editor', $cover);
        $coverEditorService->setDefaults();
        $coverEditorService->setCoverDetails($coverDetails);

        return $coverEditorService;
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws PrintableException
     * @throws Exception
     */
    public function actionDelete(ParameterBag $params)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        /** @noinspection PhpUndefinedFieldInspection */
        $cover = $this->assertCoverExists($params->cover_id);

        if ($this->isPost()) {
            /** @var Deleter $deleter */
            $deleter = $this->service('ThemeHouse\Covers:Cover\Deleter', $cover);
            $deleter->delete();

            return $this->redirect($this->buildLink('covers'));
        } else {
            $viewParams = [
                'cover' => $cover
            ];

            return $this->view('ThemeHouse\Covers:Cover\Delete', 'thcovers_cover_delete', $viewParams);
        }
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws PrintableException
     * @throws Exception
     */
    public function actionStyle(ParameterBag $params)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        /** @noinspection PhpUndefinedFieldInspection */
        $cover = $this->assertCoverExists($params->cover_id);

        if ($this->isPost()) {
            $this->coverStylingProcess($cover)->run();
            return $this->redirect($this->buildLink('covers', $cover));
        } else {
            $viewParams = [
                'cover' => $cover
            ];

            return $this->view('ThemeHouse\Covers:Cover\Style', 'thcovers_cover_style', $viewParams);
        }
    }

    /**
     * @param \ThemeHouse\Covers\Entity\Cover $cover
     * @return FormAction
     * @throws Exception
     */
    protected function coverStylingProcess(\ThemeHouse\Covers\Entity\Cover $cover)
    {
        $form = $this->formAction();

        if ($this->filter('delete', 'bool')) {
            $input = [
                'cover_styling' => [],
                'cover_state' => $cover->cover_image ? 'visible' : 'deleted'
            ];
        } else {
            $empty = true;

            $bgColor = $this->filter('background_color', 'str');

            if ($bgColor) {
                if (!Color::isValidColor($bgColor)) {
                    throw $this->errorException(XF::phrase('thcovers_invalid_color'));
                }
                $empty = false;
            }

            $input = [
                'cover_styling' => [
                    'background_color' => $bgColor
                ],
                'cover_state' => $empty ? ($cover->cover_image ? 'visible' : 'deleted') : 'visible'
            ];
        }

        $form->basicEntitySave($cover, $input);

        return $form;
    }

    /**
     * @param ParameterBag $params
     * @return Error|Redirect
     * @throws PrintableException
     * @throws Exception
     */
    public function actionPosition(ParameterBag $params)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        /** @noinspection PhpUndefinedFieldInspection */
        $cover = $this->assertCoverExists($params->cover_id);

        if (!$this->isPost()) {
            return $this->noPermission();
        }

        $coverDetails['cover_image'] = $cover->cover_image;

        $crop = $this->filter([
            'cropX' => 'float',
            'cropY' => 'float',
        ]);

        $coverDetails['cover_image']['cropX'] = $crop['cropX'];
        $coverDetails['cover_image']['cropY'] = $crop['cropY'];

        $editor = $this->setupCoverEdit($cover, $coverDetails);
        $errors = null;
        if (!$editor->validate($errors)) {
            return $this->error($errors);
        }

        $editor->save();

        return $this->redirect($this->buildLink('covers', $cover));
    }

    /**
     * @param ParameterBag $params
     * @return View
     * @throws Exception
     */
    public function actionView(ParameterBag $params)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        /** @noinspection PhpUndefinedFieldInspection */
        $cover = $this->assertCoverExists($params->cover_id, ['CoverUser']);

        $viewParams = [
            'cover' => $cover,
            'entity' => $cover->Content
        ];

        return $this->view('ThemeHouse\Covers:Cover\View', 'thcovers_cover_view', $viewParams);
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
