<?php

namespace ThemeHouse\Covers\Pub\Controller;

use Exception;
use ThemeHouse\Covers\Cover\AbstractHandler;
use ThemeHouse\Covers\Repository\CoverHandler;
use ThemeHouse\Covers\Repository\CoverPreset;
use ThemeHouse\Covers\Service\Cover\Deleter;
use ThemeHouse\Covers\Service\Cover\Editor;
use ThemeHouse\Covers\Service\Cover\Image;
use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;
use XF\Util\Color;

/**
 * Class Cover
 * @package ThemeHouse\Covers\Pub\Controller
 */
class Cover extends AbstractController
{
    /** @var \ThemeHouse\Covers\Entity\Cover */
    protected $cover;

    /** @var AbstractHandler */
    protected $coverHandler;

    /**
     * @param ParameterBag $params
     * @return Error|Redirect|View
     * @throws PrintableException
     * @throws Exception
     */
    public function actionImage(ParameterBag $params)
    {
        $visitor = XF::visitor();

        $error = null;
        if (!$this->cover->canSetImage($error)) {
            return $this->noPermission($error);
        }

        if ($this->isPost()) {
            /** @var Image $coverImageService */
            $coverImageService = $this->service('ThemeHouse\Covers:Cover\Image', $params['content_id'],
                $params['content_type']);
            $coverImageType = $this->filter('cover_image_type', 'str');
            $message = '';
            $coverDetails = [];

            if ($coverImageType == 'custom') {
                $upload = $this->request->getFile('upload', false);
                if (!empty($upload)) {
                    if (!$this->cover->canUploadImage($error)) {
                        return $this->noPermission($error);
                    }

                    if (!$coverImageService->setImageFromUpload($upload)) {
                        return $this->error($coverImageService->getError());
                    }
                }

                $coverImageUrl = $this->filter('cover_image_url', 'str');
                if (!empty($coverImageUrl)) {
                    if (!$this->cover->canDownloadImage($error)) {
                        return $this->noPermission($error);
                    }

                    if (!$coverImageService->downloadImage($coverImageUrl)) {
                        return $this->error($coverImageService->getError());
                    }
                }

                if (!$coverImageService->validateImageSet()) {
                    return $this->noPermission(XF::phrase('thcovers_no_image_specified'));
                }

                $coverImageDetails = $coverImageService->updateCoverImage();
                if (!$coverImageDetails) {
                    return $this->error(XF::phrase('thcovers_new_cover_could_not_be_processed'));
                }

                $coverDetails = $coverDetails + $coverImageDetails;

                $message = XF::phrase('upload_completed_successfully');
            }

            $editor = $this->setupCoverEdit($coverDetails);
            $errors = null;
            if (!$editor->validate($errors)) {
                return $this->error($errors);
            }

            $editor->save();

            if ($this->filter('_xfWithData', 'bool')) {
                $reply = $this->redirect($this->coverHandler->getContentUrl(false, '', ['th_coversInit' => 1]),
                    $message);

                $reply->setJsonParams([
                    'userId' => $visitor->user_id,
                    'contentId' => $params['content_id'],
                    'contentType' => $params['content_type'],
                    'defaultCovers' => ($visitor->getAvatarUrl('s') === null),
                ]);

                return $reply;
            } else {
                return $this->redirect($this->coverHandler->getContentUrl(false, '', ['th_coversInit' => 1]));
            }
        } else {
            $viewParams = [
                'cover' => $this->cover,
                'contentId' => $params['content_id'],
                'contentType' => $params['content_type'],
                'maxSize' => $this->getCoverRepo()->getCoverSizeMap()['m'],
            ];

            return $this->view('ThemeHouse\Covers:Cover\Image', 'thcovers_cover_image', $viewParams);
        }
    }

    /**
     * @param array $coverDetails
     * @return Editor
     */
    protected function setupCoverEdit(array $coverDetails = [])
    {
        /** @var Editor $coverEditorService */
        $coverEditorService = $this->service('ThemeHouse\Covers:Cover\Editor', $this->cover);
        $coverEditorService->setDefaults();
        $coverEditorService->setCoverDetails($coverDetails);

        return $coverEditorService;
    }

    /**
     * @return \ThemeHouse\Covers\Repository\Cover
     */
    protected function getCoverRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:Cover');
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws PrintableException
     * @throws XF\Mvc\Reply\Exception
     * @throws Exception
     */
    public function actionStyle(ParameterBag $params)
    {
        $error = null;
        if (!$this->cover->canStyle($error)) {
            return $this->noPermission($error);
        }

        if ($this->isPost()) {
            $this->coverStylingProcess()->run();
            return $this->redirect($this->coverHandler->getContentUrl());
        } else {
            $viewParams = [
                'cover' => $this->cover,
                'contentId' => $params['content_id'],
                'contentType' => $params['content_type'],
            ];

            return $this->view('ThemeHouse\Covers:Cover\Style', 'thcovers_cover_style', $viewParams);
        }
    }

    /**
     * @return FormAction
     * @throws XF\Mvc\Reply\Exception
     */
    protected function coverStylingProcess()
    {
        $form = $this->formAction();

        if ($this->filter('delete', 'bool')) {
            $input = [
                'cover_styling' => [],
                'cover_state' => $this->cover->cover_image ? 'visible' : 'deleted'
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
                'cover_styling' => $empty ? [] : ['background_color' => $bgColor],
                'cover_state' => $empty ? ($this->cover->cover_image ? 'visible' : 'deleted') : 'visible'
            ];
        }

        $form->basicEntitySave($this->cover, $input);

        return $form;
    }

    /**
     * @return Error|Redirect
     * @throws PrintableException
     * @throws Exception
     */
    public function actionPosition()
    {
        $error = null;
        if (!$this->cover || !$this->cover->canPositionImage($error)) {
            return $this->noPermission($error);
        }

        if (!$this->isPost()) {
            return $this->noPermission();
        }

        $coverDetails['cover_image'] = $this->cover->cover_image;

        $crop = $this->filter([
            'cropX' => 'float',
            'cropY' => 'float',
        ]);

        $coverDetails['cover_image']['cropX'] = $crop['cropX'];
        $coverDetails['cover_image']['cropY'] = $crop['cropY'];

        $editor = $this->setupCoverEdit($coverDetails);
        $errors = null;
        if (!$editor->validate($errors)) {
            return $this->error($errors);
        }

        $editor->save();

        return $this->redirect($this->coverHandler->getContentUrl(), XF::phrase('thcovers_cover_position_successful'));
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws PrintableException
     * @throws Exception
     */
    public function actionDelete(ParameterBag $params)
    {
        $error = null;
        if (!$this->cover->canDelete($error)) {
            return $this->noPermission($error);
        }

        if ($this->isPost()) {
            /** @var Deleter $deleter */
            $deleter = $this->service('ThemeHouse\Covers:Cover\Deleter', $this->cover);
            $deleter->delete();

            return $this->redirect($this->coverHandler->getContentUrl());
        } else {
            $viewParams = [
                'cover' => $this->cover,
                'contentType' => $params['content_type'],
                'contentId' => $params['content_id']
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
    public function actionPreset(ParameterBag $params)
    {
        $error = null;
        if (!$this->cover->canUsePreset($error)) {
            return $this->noPermission($error);
        }

        /** @var CoverPreset $presetRepo */
        $presetRepo = $this->getCoverPresetRepo();

        $presets = $presetRepo->findCoverPresetsForList()->fetch();
        $categories = $presetRepo->findCoverPresetCategories()->fetch();

        $visitor = XF::visitor();
        foreach ($presets as $key => $preset) {
            $userCriteria = $this->app()->criteria('XF:User', $preset->user_criteria);
            if (!$userCriteria->isMatched($visitor)) {
                unset($presets[$key]);
                continue;
            }
        }

        $csrfValid = true;
        if ($visitor->user_id) {
            $csrfValid = $this->validateCsrfToken($this->filter('t', 'str'));
        }

        if ($this->request->exists('cover_preset_id') && $csrfValid) {
            $presetId = $this->filter('cover_preset_id', 'int');

            if ($presetId != 0) {
                if (!$presets->offsetExists($presetId)) {
                    return $this->noPermission();
                }

                if (!$this->cover->isInsert()) {
                    /** @var Deleter $deleter */
                    $deleter = $this->service('ThemeHouse\Covers:Cover\Deleter', $this->cover);
                    $deleter->delete();
                }
            }

            $this->cover->cover_preset = $presetId;
            $this->cover->cover_state = $presetId || $this->cover->cover_image || $this->cover->cover_styling ? 'visible' : 'deleted';
            $this->cover->save();

            return $this->redirect($this->coverHandler->getContentUrl());
        } else {
            $viewParams = [
                'presets' => $presets->groupBy('cover_preset_category_id'),
                'categories' => $categories,
                'contentId' => $params['content_id'],
                'contentType' => $params['content_type'],
            ];

            return $this->view('ThemeHouse\Covers:Cover\Preset', 'thcovers_cover_preset', $viewParams);
        }
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
     * @return Redirect|View
     * @throws PrintableException
     */
    public function actionApprove(ParameterBag $params)
    {
        if (!XF::visitor()->hasPermission('th_cover', 'approveUnapprove')) {
            return $this->noPermission();
        }

        if ($this->isPost()) {
            $this->cover->cover_state = $this->cover->cover_state == 'moderated' ? 'visible' : 'moderated';
            $this->cover->save();

            return $this->redirect($this->getDynamicRedirect($this->coverHandler->getContentUrl($params['content_id'])));
        } else {
            $viewParams = [
                'cover' => $this->cover
            ];

            return $this->view('ThemeHouse\Covers:Cover\Approve', 'th_covers_approve', $viewParams);
        }
    }

    /**
     * @param $action
     * @param ParameterBag $params
     * @throws XF\Mvc\Reply\Exception
     * @throws Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->cover = $this->assertCover($params['content_type'], $params['content_id']);
        $this->coverHandler = $this->getCoverHandlerRepo()->getCoverHandler($params['content_type'], true);
    }

    /**
     * @param $contentType
     * @param $contentId
     * @return mixed|null|Entity
     * @throws XF\Mvc\Reply\Exception
     * @throws Exception
     */
    protected function assertCover($contentType, $contentId)
    {
        if (!$contentType) {
            throw $this->exception($this->notFound());
            //throw $this->exception($this->notFound("Provided cover must defined a content type in its structure"));
        }

        if (!$contentId) {
            throw $this->exception($this->notFound());
            //throw $this->exception($this->notFound("No content ID provided for {$contentType} cover."));
        }

        $this->coverHandler = $this->getCoverHandlerRepo()->getCoverHandler($contentType, false);

        if (!$this->coverHandler) {
            throw $this->exception($this->notFound());
        }

        $entity = $this->coverHandler->getContent($contentId);

        if (!$entity) {
            throw $this->exception($this->notFound());
            //throw $this->exception($this->notFound("No entity found for $contentType with ID $contentId"));
        }

        $cover = $this->setupDefaultCover($entity);

        if (!$cover) {
            throw $this->exception($this->notFound());
            //throw $this->exception($this->notFound("No cover found for $contentType with ID $contentId"));
        }

        if (!$cover->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $cover;
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
     * @param Entity $entity
     * @return \ThemeHouse\Covers\Entity\Cover|null
     */
    protected function setupDefaultCover(Entity $entity)
    {
        if (empty($entity->ThCover) && !$entity->getEntityId()) {
            return null;
        }

        if (empty($entity->ThCover)) {
            $cover = $this->em()->create('ThemeHouse\Covers:Cover');
            $cover->bulkSet([
                'content_type' => $entity->getEntityContentType(),
                'content_id' => $entity->getEntityId()
            ]);
        } else {
            $cover = $entity->ThCover;
        }

        return $cover;
    }
}
