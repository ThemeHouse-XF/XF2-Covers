<?php

namespace ThemeHouse\Covers\Api\Controller;

use Exception;
use ThemeHouse\Covers\Entity\Cover;
use ThemeHouse\Covers\Repository\CoverHandler;
use ThemeHouse\Covers\Service\Cover\Deleter;
use ThemeHouse\Covers\Service\Cover\Editor;
use ThemeHouse\Covers\Service\Cover\Image;
use XF;
use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Util\Color;

/**
 * Class Covers
 * @package ThemeHouse\Covers\Api\Controller
 */
class Covers extends AbstractController
{
    /**
     * @var
     */
    protected $coverHandler;

    /**
     * @param ParameterBag $params
     * @return Cover|ApiResult
     * @throws XF\Mvc\Reply\Exception
     */
    public function actionGet(ParameterBag $params)
    {
        $cover = $this->assertViewableCover($params['content_type'], $params['content_id']);
        return $this->apiResult([
            'cover' => $cover->toApiResult()
        ]);
    }

    /**
     * @param $contentType
     * @param $contentId
     * @param array $with
     * @return Cover
     * @throws XF\Mvc\Reply\Exception
     * @throws Exception
     * @throws Exception
     */
    protected function assertViewableCover($contentType, $contentId, $with = [])
    {
        if (!$contentType) {
            throw $this->exception($this->notFound("Provided cover must defined a content type in its structure"));
        }

        if (!$contentId) {
            throw $this->exception($this->notFound("No content ID provided for {$contentType} cover."));
        }

        $this->coverHandler = $this->getCoverHandlerRepo()->getCoverHandler($contentType, true);

        $entity = $this->coverHandler->getContent($contentId);

        if (!$entity) {
            throw $this->exception($this->notFound("No entity found for $contentType with ID $contentId"));
        }

        $cover = $this->setupDefaultCover($entity);

        if (!$cover) {
            throw $this->exception($this->notFound("No cover found for $contentType with ID $contentId"));
        }

        if (XF::isApiCheckingPermissions() && !$cover->canView($error)) {
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
     * @return Cover|null
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

    /**
     * @param ParameterBag $params
     * @return ApiResult|Error
     * @throws XF\Mvc\Reply\Exception
     * @throws Exception
     */
    public function actionDelete(ParameterBag $params)
    {
        $cover = $this->assertViewableCover($params['content_type'], $params['content_id']);

        if (XF::isApiCheckingPermissions() && !$cover->canDelete($error)) {
            return $this->error($error);
        }

        /** @var Deleter $deleter */
        $deleter = $this->service('ThemeHouse\Covers:Cover\Deleter', $cover);
        $deleter->delete();

        return $this->apiSuccess();
    }

    /**
     * @param ParameterBag $params
     * @return ApiResult|Error
     * @throws Exception
     */
    public function actionPut(ParameterBag $params)
    {
        $cover = $this->assertViewableCover($params['content_type'], $params['content_id']);

        $coverDetails = [];

        /** @var Image $coverImageService */
        $coverImageService = $this->service('ThemeHouse\Covers:Cover\Image', $params['content_id'],
            $params['content_type']);


        if ($this->filter('with_image', 'bool') && $imageUrl = $this->filter('image_url', 'str')) {
            if (!$cover->canDownloadImage($error)) {
                return $this->noPermission($error);
            }

            if (!$coverImageService->downloadImage($imageUrl)) {
                return $this->error($coverImageService->getError());
            }

            if (!$coverImageService->validateImageSet()) {
                return $this->noPermission(XF::phrase('thcovers_no_image_specified'));
            }

            $coverImageDetails = $coverImageService->updateCoverImage();
            if (!$coverImageDetails) {
                return $this->error(XF::phrase('thcovers_new_cover_could_not_be_processed'));
            }

            $coverDetails += $coverImageDetails;

            $crop = $this->filter([
                'cropX' => 'float',
                'cropY' => 'float',
            ]);
        }

        if(isset($crop)) {
            $coverDetails['cover_image']['cropX'] = $crop['cropX'];
            $coverDetails['cover_image']['cropY'] = $crop['cropY'];
        }

        if ($backgroundColor = $this->filter('background_color', 'str')) {
            if ($backgroundColor && !Color::isValidColor($backgroundColor)) {
                throw $this->errorException(XF::phrase('thcovers_invalid_color'));
            }
            $coverDetails['cover_styling'] = ['background_color' => $backgroundColor];
        } else {
            $coverDetails['cover_styling'] = [];
        }

        $coverDetails['cover_preset_id'] = $this->filter('preset_id', 'uint');

        $editor = $this->setupCoverEdit($coverDetails);

        $errors = null;
        if (!$editor->validate($errors)) {
            return $this->error($errors);
        }

        $editor->save();

        return $this->apiSuccess();
    }

    /**
     * @param $cover
     * @param array $coverDetails
     * @return Editor
     */
    protected function setupCoverEdit($cover, array $coverDetails = [])
    {
        /** @var Editor $coverEditorService */
        $coverEditorService = $this->service('ThemeHouse\Covers:Cover\Editor', $cover);
        $coverEditorService->setDefaults();
        $coverEditorService->setCoverDetails($coverDetails);

        return $coverEditorService;
    }

    /**
     * @param $action
     * @param ParameterBag $params
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertApiScopeByRequestMethod('cover');
    }

    /**
     * @return \ThemeHouse\Covers\Repository\Cover
     */
    protected function getCoverRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:Cover');
    }
}
