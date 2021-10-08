<?php

namespace ThemeHouse\Covers\Service\Cover;

use InvalidArgumentException;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use LogicException;
use RuntimeException;
use ThemeHouse\Covers\Repository\Cover;
use ThemeHouse\ImageOptimizer\ImageOptimizer;
use XF;
use XF\App;
use XF\Entity\User;
use XF\Http\Upload;
use XF\PrintableException;
use XF\Repository\Ip;
use XF\Service\AbstractService;
use XF\Util\File;

/**
 * Class Image
 * @package ThemeHouse\Covers\Service\Cover
 */
class Image extends AbstractService
{
    /**
     * @var
     */
    protected $contentId;
    /**
     * @var
     */
    protected $contentType;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var bool
     */
    protected $logIp = true;

    /**
     * @var
     */
    protected $fileName;

    /**
     * @var
     */
    protected $width;

    /**
     * @var
     */
    protected $height;

    /**
     * @var
     */
    protected $cropX;
    /**
     * @var
     */
    protected $cropY;

    /**
     * @var
     */
    protected $type;

    /**
     * @var null
     */
    protected $error = null;

    /**
     * @var array
     */
    protected $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];

    /**
     * @var bool
     */
    protected $skipOptimization = false;

    /**
     * Image constructor.
     * @param App $app
     * @param $contentId
     * @param $contentType
     * @param null $user
     */
    public function __construct(App $app, $contentId, $contentType, $user = null)
    {
        parent::__construct($app);

        $this->contentId = $contentId;
        $this->contentType = $contentType;

        $this->setUser($user);
    }

    /**
     * @param $user
     */
    public function setUser($user)
    {
        if (!$user instanceof User) {
            $user = XF::visitor();
        }

        $this->user = $user;
    }

    /**
     * @param bool $skipOptimization
     */
    public function setSkipOptimization($skipOptimization = true)
    {
        $this->skipOptimization = $skipOptimization;
    }

    /**
     * @param $logIp
     */
    public function logIp($logIp)
    {
        $this->logIp = $logIp;
    }

    /**
     * @return null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param Upload $upload
     * @return bool
     */
    public function setImageFromUpload(Upload $upload)
    {
        $upload->requireImage();

        $upload->setMaxFileSize(XF::options()->thcovers_coverMaxFileSize * 1024);

        if (!$upload->isValid($errors)) {
            $this->error = reset($errors);
            return false;
        }

        return $this->setImage($upload->getTempFile());
    }

    /**
     * @param $fileName
     * @return bool
     */
    public function setImage($fileName)
    {
        if (!$this->validateImageAsCover($fileName, $error)) {
            $this->error = $error;
            $this->fileName = null;
            return false;
        }

        $this->fileName = $fileName;
        return true;
    }

    /**
     * @param $fileName
     * @param null $error
     * @return bool
     */
    public function validateImageAsCover($fileName, &$error = null)
    {
        $error = null;

        if (!file_exists($fileName)) {
            throw new InvalidArgumentException("Invalid file '$fileName' passed to cover service");
        }

        if (!is_readable($fileName)) {
            throw new InvalidArgumentException("'$fileName' passed to cover service is not readable");
        }

        $imageInfo = getimagesize($fileName);
        if (!$imageInfo) {
            $error = XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $type = $imageInfo[2];
        if (!in_array($type, $this->allowedTypes)) {
            $error = XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        if (!$this->app->imageManager()->canResize($width, $height)) {
            $error = XF::phrase('uploaded_image_is_too_big');
            return false;
        }

        $coversRepository = $this->getCoverRepository();
        $dimensionContraints = $coversRepository->getDimensionConstraints();
        if ($width < $dimensionContraints['min'][0] || $height < $dimensionContraints['min'][1]) {
            $error = XF::phrase(
                'thcovers_please_provide_larger_dimension_image',
                ['width' => $dimensionContraints['min'][0], 'height' => $dimensionContraints['min'][1]]
            );
            return false;
        }

//        if ($width > $dimensionContraints['max'][0] || $height > $dimensionContraints['max'][1]) {
//            $error = \XF::phrase(
//                'thcovers_please_provide_smaller_dimension_image',
//                ['width' => $dimensionContraints['max'][0], 'height' => $dimensionContraints['max'][1]]
//            );
//            return false;
//        }

        // Aspect ratio checker
        $aspectRatio = $this->getAspectRatio($width, $height);
        if (($width / $aspectRatio) <= ($height / $aspectRatio)) {
            $error = XF::phrase('thcovers_please_provide_an_image_whose_width_longer_than_height');
            return false;
        }

        $this->width = $width;
        $this->height = $height;
        $this->type = $type;

        return true;
    }

    /**
     * @return Cover
     */
    protected function getCoverRepository()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:Cover');
    }

    /**
     * @param $width
     * @param $height
     * @return mixed
     */
    public function getAspectRatio($width, $height)
    {
        return ($height == 0) ? $width : $this->getAspectRatio($height, $width % $height);
    }

    /**
     * @return bool
     */
    public function setImageFromExisting()
    {
        $coversRepository = $this->getCoverRepository();
        $path = $coversRepository->getAbstractedCustomCoverPath($this->contentType, $this->contentId, 'o');
        if (!$this->app->fs()->has($path)) {
            throw new InvalidArgumentException("Cover image appears to be missing in ($path)");
        }

        $tempFile = File::copyAbstractedPathToTempFile($path);
        return $this->setImage($tempFile);
    }

    /**
     * @return array
     */
    public function getCrop()
    {
        return [$this->cropX, $this->cropY];
    }

    /**
     * @param $coverImageUrl
     * @return bool
     */
    public function downloadImage($coverImageUrl)
    {
        $validator = $this->app->validator('Url');
        $validator->coerceValue($coverImageUrl);
        if (!$validator->isValid($coverImageUrl)) {
            $this->error = XF::phrase('thcovers_provided_url_is_not_valid');
            return false;
        }

        $tempFile = File::getTempFile();

        if ($this->app->http()->reader()->get($coverImageUrl, [], $tempFile)) {
            if (filesize($tempFile) > XF::options()->thcovers_coverMaxFileSize * 1024) {
                $this->error = XF::phrase('uploaded_file_is_too_large');
                return false;
            }

            if (!$this->setImage($tempFile)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function validateImageSet()
    {
        return !empty($this->fileName);
    }

    /**
     * @return array
     * @throws PrintableException
     * @throws PrintableException
     */
    public function updateCoverImage()
    {
        if (!$this->fileName) {
            throw new LogicException("No source file for cover set");
        }

        $imageManager = $this->app->imageManager();

        $outputFiles = [];
        $baseFile = $this->fileName;

        $coversRepository = $this->getCoverRepository();
        $sizeMap = $coversRepository->getCoverSizeMap();

        $sizes = [];
        if ($this->skipOptimization) {
            unset($sizeMap['o']);
            $sizes['o'] = [$this->width, $this->height];
            $outputFiles['o'] = $baseFile;
        }

        foreach ($sizeMap as $code => $size) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($maxWidth, $maxHeight) = $size;
            if (isset($outputFiles[$code])) {
                continue;
            }

            $image = $imageManager->imageFromFile($baseFile);
            if (!$image) {
                continue;
            }

            $newImage = $image->resize($maxWidth);
            $sizes[$code] = [$newImage->getWidth(), $newImage->getHeight()];

            $newTempFile = tempnam(File::getTempDir(), 'xf');
            if ($newTempFile && $image->save($newTempFile)) {
                $outputFiles[$code] = $newTempFile;
            } else {
                if ($newTempFile) {
                    @unlink($newTempFile);
                }
            }
            unset($image);
        }

        if (count($outputFiles) != count($coversRepository->getCoverSizeMap())) {
            foreach ($outputFiles as $file) {
                if ($file != $this->fileName) {
                    @unlink($file);
                }
            }

            throw new RuntimeException("Failed to save image to temporary file; check internal_data/data permissions");
        }

        foreach ($outputFiles as $code => $file) {
            $dataFile = $coversRepository->getAbstractedCustomCoverPath($this->contentType, $this->contentId, $code);
            File::copyFileToAbstractedPath($file, $dataFile);
        }

        foreach ($outputFiles as $file) {
            if ($file != $this->fileName) {
                @unlink($file);
            }
        }

        $coverDetails = [
            'cover_image' => [
                'type' => 'custom',
                'sizes' => $sizes,
                'date' => XF::$time
            ],
        ];

        if ($this->logIp) {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('update', $ip);
        }

        if (!$this->skipOptimization) {
            $cover = XF::finder('ThemeHouse\Covers:Cover')
                ->where('content_type', $this->contentType)
                ->where('content_id', $this->contentId)
                ->fetchOne();

            if ($cover && !empty(XF::app()->container('addon.cache')['ThemeHouse/ImageOptimizer'])) {
                ImageOptimizer::optimize('cover', $cover);
            }
        }

        return $coverDetails;
    }

    /**
     * @param $action
     * @param $ip
     */
    protected function writeIpLog($action, $ip)
    {
        /** @var Ip $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipRepo->logIp($this->user->user_id, $ip, $this->contentType, $this->contentId, 'cover_' . $action);
    }

    /**
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function createOSizeCoverImageFromL()
    {
        $coversRepository = $this->getCoverRepository();
        $l = $coversRepository->getAbstractedCustomCoverPath($this->contentType, $this->contentId, 'l');
        $o = $coversRepository->getAbstractedCustomCoverPath($this->contentType, $this->contentId, 'o');
        $fs = $this->app->fs();

        if (!$fs->has($l) || $fs->has($o)) {
            return;
        }

        $fs->rename($l, $o);

        $imageManager = $this->app->imageManager();

        $lSize = $coversRepository->getCoverSizeMap()['l'];

        $tempFile = File::copyAbstractedPathToTempFile($o);
        $image = $imageManager->imageFromFile($tempFile);
        // temp file has O image content

        $image->resizeShortEdge($lSize, true);
        $image->crop(
            $lSize,
            $lSize,
            floor(($image->getWidth() - $lSize) / 2),
            floor(($image->getHeight() - $lSize) / 2)
        );

        $image->save($tempFile);
        // temp file has L image content

        File::copyFileToAbstractedPath($tempFile, $l);
        @unlink($tempFile);
    }

    /**
     * @return bool
     */
    public function deleteCoverImage()
    {
        $this->deleteCoverImageFiles();

        if ($this->logIp) {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('delete', $ip);
        }

        return true;
    }

    /**
     *
     */
    protected function deleteCoverImageFiles()
    {
        $coversRepository = $this->getCoverRepository();
        foreach ($coversRepository->getCoverSizeMap() as $code => $size) {
            File::deleteFromAbstractedPath($coversRepository->getAbstractedCustomCoverPath(
                $this->contentType,
                $this->contentId,
                $code
            ));
        }
    }

    /**
     * @return bool
     */
    public function deleteCoverImageForContentDelete()
    {
        $this->deleteCoverImageFiles();

        return true;
    }
}
