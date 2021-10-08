<?php

namespace ThemeHouse\Covers\ImageOptimizerHandler;

use ThemeHouse\Covers\Service\Cover\Image;
use ThemeHouse\ImageOptimizer\ContentHandler\AbstractHandler;
use ThemeHouse\ImageOptimizer\Entity\Status;
use XF\App;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;
use XF\Util\File;

/**
 * Class Cover
 * @package ThemeHouse\Covers\ImageOptimizerHandler
 */
class Cover extends AbstractHandler
{
    /**
     * @var \ThemeHouse\Covers\Entity\Cover
     */
    protected $content;

    /**
     * @var \ThemeHouse\Covers\Repository\Cover
     */
    protected $repo;

    /**
     * Cover constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->repo = $this->repository('ThemeHouse\Covers:Cover');
    }

    /**
     * @param Status $status
     * @return mixed
     */
    public function getFileExtensionForStatus(Status $status)
    {
        $this->status = $status;
        $this->content = $this->fetchContent();

        $fileName = $this->getImagePath($this->content);
        $pathInfo = pathinfo($fileName);

        return $pathInfo['extension'];
    }

    /**
     * @param Status|null $status
     * @return \ThemeHouse\Covers\Entity\Cover|null
     */
    protected function fetchContent(Status $status = null)
    {
        if (!$status) {
            $status = $this->getStatus();
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->em()->find('ThemeHouse\Covers:Cover', $status->content_id);
    }

    /**
     * @param Entity|null $content
     * @return string
     */
    public function getImagePath(Entity $content = null)
    {
        if (!$content) {
            $content = $this->content;
        }

        return $this->repo->getAbstractedCustomCoverPath($content->content_type, $content->content_id, 'o');
    }

    /**
     * @param Entity|null $content
     * @return bool
     */
    public function hasImage(Entity $content = null)
    {
        if (!$content) {
            $content = $this->content;
        }

        return !empty($content->cover_image);
    }

    /**
     * @param Status $status
     * @return bool|string
     */
    public function getAdminFullImageUrl(Status $status)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        $cover = $this->fetchContent($status);
        if (!$cover) {
            return false;
        }

        return $this->repo->getCoverUrl($cover->content_type, $cover->content_id, $cover->cover_image, 'o');
    }

    /**
     * @param Status $status
     * @return bool|string
     */
    public function getAdminThumbnailUrl(Status $status)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        $cover = $this->fetchContent($status);
        if (!$cover) {
            return false;
        }

        return $this->repo->getCoverUrl($cover->content_type, $cover->content_id, $cover->cover_image, 's');
    }

    /**
     * @return bool|mixed|null
     * @throws PrintableException
     * @throws PrintableException
     */
    protected function _finalizeOptimization()
    {
        if ($this->finalizedImageData['new_size'] >= $this->status->original_size) {
            return null;
        }

        $newImagePath = $this->finalizedImageData['temp_path'];

        /** @var Image $coverService */
        $coverService = $this->service('ThemeHouse\Covers:Cover\Image', $this->content->content_id,
            $this->content->content_type, $this->content->CoverUser);
        $coverService->setSkipOptimization(true);

        if (!$coverService->setImage($newImagePath)) {
            return false;
        }

        if (!$coverService->updateCoverImage()) {
            return false;
        }

        return true;
    }

    /**
     * @param $lastId
     * @param $amount
     * @return mixed|ArrayCollection
     */
    protected function contentIdsInRange($lastId, $amount)
    {
        $finder = $this->finder('ThemeHouse\Covers:Cover')
            ->where('cover_id', '>', $lastId)
            ->order('cover_id');

        return $finder->fetch($amount);
    }

    /**
     * @param Entity $entity
     * @return array|mixed
     */
    protected function standardizeEntityForStatus(Entity $entity)
    {
        /** @var \ThemeHouse\Covers\Entity\Cover $entity */
        $tempImage = File::copyAbstractedPathToTempFile($this->repo->getAbstractedCustomCoverPath($entity->content_type,
            $entity->content_id, 'o'));
        return [
            'content_type' => 'cover',
            'content_id' => $entity->cover_id,
            'original_size' => filesize($tempImage),
        ];
    }
}