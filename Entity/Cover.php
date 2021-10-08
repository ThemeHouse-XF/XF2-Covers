<?php

namespace ThemeHouse\Covers\Entity;

use Exception;
use ThemeHouse\Covers\Cover\AbstractHandler;
use ThemeHouse\Covers\Repository\CoverHandler;
use XF;
use XF\Api\Result\EntityResult;
use XF\Entity\ApprovalQueue;
use XF\Entity\DeletionLog;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * Class Cover
 * @package ThemeHouse\Covers\Entity
 *
 * @property integer cover_id
 * @property integer content_id
 * @property string content_type
 * @property integer cover_user_id
 * @property integer cover_preset
 * @property array cover_image
 * @property array cover_styling
 * @property integer cover_date
 * @property integer cover_state
 *
 * @property User CoverUser
 * @property CoverPreset CoverPreset
 * @property DeletionLog DeletionLog
 * @property ApprovalQueue ApprovalQueue
 * @property Entity Content
 */
class Cover extends Entity
{
    /**
     * @var string
     */
    protected $coverColorPattern = '/^(\#[\da-f]{3}|\#[\da-f]{6}|rgba\(((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*,\s*){2}((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*)(,\s*(0\.\d+|1))\)|hsla\(\s*((\d{1,2}|[1-2]\d{2}|3([0-5]\d|60)))\s*,\s*((\d{1,2}|100)\s*%)\s*,\s*((\d{1,2}|100)\s*%)(,\s*(0\.\d+|1))\)|rgb\(((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*,\s*){2}((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*)|hsl\(\s*((\d{1,2}|[1-2]\d{2}|3([0-5]\d|60)))\s*,\s*((\d{1,2}|100)\s*%)\s*,\s*((\d{1,2}|100)\s*%)\))$/';

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_covers_cover';
        $structure->shortName = 'ThemeHouse\Covers:Cover';
        $structure->contentType = 'cover';
        $structure->primaryKey = 'cover_id';
        $structure->columns = [
            'cover_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true, 'api' => false],
            'content_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
            'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true, 'api' => true],
            'cover_user_id' => [
                'type' => self::UINT,
                'required' => true,
                'default' => XF::visitor()->user_id,
                'api' => true
            ],
            'cover_preset' => ['type' => self::UINT, 'required' => true, 'default' => 0, 'api' => true],
            'cover_image' => ['type' => self::JSON_ARRAY, 'default' => [], 'api' => false],
            'cover_styling' => ['type' => self::JSON_ARRAY, 'default' => [], 'api' => true],
            'cover_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
            'cover_state' => [
                'type' => self::STR,
                'default' => 'visible',
                'allowedValues' => ['visible', 'moderated', 'deleted'],
                'api' => false
            ],
        ];
        $structure->getters = [
            'Content' => true
        ];
        $structure->behaviors = [
            'XF:ChangeLoggable' => [
                'contentType' => 'user',
                'contentIdColumn' => 'cover_user_id',
                'optIn' => true
            ]
        ];
        $structure->relations = [
            'CoverUser' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$cover_user_id']
                ],
                'primary' => true,
                'api' => true
            ],
            'CoverPreset' => [
                'entity' => 'ThemeHouse\Covers:CoverPreset',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['cover_preset_id', '=', '$cover_preset']
                ],
                'primary' => true,
                'api' => true
            ],
            'DeletionLog' => [
                'entity' => 'XF:DeletionLog',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['content_type', '=', 'cover'],
                    ['content_id', '=', '$cover_id']
                ],
                'primary' => true
            ],
            'ApprovalQueue' => [
                'entity' => 'XF:ApprovalQueue',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['content_type', '=', 'cover'],
                    ['content_id', '=', '$cover_id']
                ],
                'primary' => true
            ]
        ];
        $structure->options = [
            'log_moderator' => true
        ];

        return $structure;
    }

    /**
     * @return null
     * @throws Exception
     */
    public function getContentTypePhrase()
    {
        $handler = $this->getHandler();
        return $handler ? $handler->getContentTypePhrase() : null;
    }

    /**
     * @return AbstractHandler
     * @throws Exception
     */
    public function getHandler()
    {
        return $this->getCoverHandlerRepo()->getCoverHandler($this->content_type);
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
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canView(&$error = null)
    {
        $handler = $this->getHandler();
        if (!$handler || !$handler->hasCoverPermission('view')) {
            $error = XF::phrase('thcovers_no_view_permissions');
            return false;
        }

        $handler = $this->getHandler();
        $content = $this->Content;

        if ($handler && $content) {
            return $handler->canViewCover($content, $error);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isVisibleToVisitor()
    {
        return $this->cover_state === 'visible' || ($this->cover_state === 'moderated' && (XF::visitor()->hasPermission('th_cover',
                        'approveUnapprove') || XF::visitor()->user_id == $this->cover_user_id));
    }

    /**
     * @param null $error
     * @return bool
     */
    public function canApproveUnapprove(&$error = null)
    {
        if (empty($this->cover_image)) {
            return false;
        }

        $visitor = XF::visitor();

        return ($visitor->user_id && $visitor->hasPermission('th_cover', 'approveUnapprove'));
    }

    /**
     * @return string
     */
    public function getNewCoverState()
    {
        $visitor = XF::visitor();

        if ($visitor->user_id && $visitor->hasPermission('th_cover', 'approveUnapprove')) {
            return 'visible';
        }

        if ($this->isChanged('cover_image') && !$visitor->hasPermission('general', 'submitWithoutApproval')) {
            if (!empty($this->cover_image)) {
                return 'moderated';
            }
        }

        return 'visible';
    }

    /**
     * @param Entity|null $content
     */
    public function setContent(Entity $content = null)
    {
        $this->_getterCache['Content'] = $content;
    }

    /**
     * @return array
     */
    public function getChangeLogEntries()
    {
        $changes = [];

        if ($this->isUpdate() && $this->isChanged('cover_date') && $this->content_type == 'user') {
            $changes['thcovers_cover_date'] = [$this->getExistingValue('cover_date'), $this->cover_date];
        }

        return $changes;
    }

    /**
     *
     */
    protected function _preSave()
    {
        $this->cover_date = XF::$time;
    }

    /**
     * @throws PrintableException
     * @throws Exception
     */
    protected function _postSave()
    {
        $approvalChange = $this->isStateChanged('cover_state', 'moderated');
        $deletionChange = $this->isStateChanged('cover_state', 'deleted');

        if ($this->isUpdate()) {
            if ($deletionChange === 'leave' && $this->DeletionLog) {
                $this->DeletionLog->delete();
            }

            if ($approvalChange === 'leave' && $this->ApprovalQueue) {
                $this->ApprovalQueue->delete();
            }
        }

        if ($approvalChange === 'enter' && $this->content_id && $this->cover_id) {
            /** @var ApprovalQueue $approvalQueue */
            $approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
            $approvalQueue->content_date = $this->cover_date;
            $approvalQueue->save();
        } else {
            if ($deletionChange === 'enter' && $this->content_id && $this->cover_id && !$this->DeletionLog) {
                /** @var DeletionLog $delLog */
                $delLog = $this->getRelationOrDefault('DeletionLog', false);
                $delLog->setFromVisitor();
                $delLog->save();
            }
        }

        if ($this->isUpdate() && $this->getOption('log_moderator')) {
            $this->app()->logger()->logModeratorChanges('cover', $this->getContent());
        }
    }

    /**
     * @return null|Entity
     * @throws Exception
     */
    public function getContent()
    {
        $handler = $this->getHandler();
        return $handler ? $handler->getContent($this->content_id) : null;
    }

    /**
     * @throws PrintableException
     * @throws Exception
     */
    protected function _preDelete()
    {
        if ($this->cover_state == 'visible') {
            $this->cover_state = 'deleted';
        }

        if ($this->cover_state == 'deleted' && $this->DeletionLog) {
            $this->DeletionLog->delete();
        }

        if ($this->cover_state == 'moderated' && $this->ApprovalQueue) {
            $this->ApprovalQueue->delete();
        }

        if($this->content_type !== 'user') {
            if ($this->getOption('log_moderator') && $this->Content) {
                $this->app()->logger()->logModeratorAction('cover', $this->getContent(), 'deleted');
            }
        }
    }

    /**
     * @param EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return EntityResult
     * @throws Exception
     * @noinspection PhpUndefinedFieldInspection
     */
    protected function setupApiResultData(
        EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        $coverRepo = $this->getCoverRepo();

        if ($this->cover_image) {
            $result->cover_urls = [
                's' => $coverRepo->getCoverUrl($this->content_type, $this->content_id, $this->cover_image, 's', true),
                'm' => $coverRepo->getCoverUrl($this->content_type, $this->content_id, $this->cover_image, 'm', true),
                'l' => $coverRepo->getCoverUrl($this->content_type, $this->content_id, $this->cover_image, 'l', true),
                'o' => $coverRepo->getCoverUrl($this->content_type, $this->content_id, $this->cover_image, 'o', true)
            ];
        }

        $result->Content = $this->Content;

        $result->can_edit = $this->canEdit();
        $result->can_delete = $this->canDelete();
        $result->can_position_image = $this->canPositionImage();
        $result->can_upload_image = $this->canUploadImage();
        $result->can_use_preset = $this->canUsePreset();
        $result->can_download_image = $this->canDownloadImage();
        $result->can_set_image = $this->canSetImage();
        $result->can_style = $this->canStyle();

        return $result;
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
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canEdit(&$error = null)
    {
        $handler = $this->getHandler();
        $handler->setContent($this->Content);
        if (!$handler || !$handler->hasCoverPermission('edit')) {
            $error = XF::phrase('thcovers_no_edit_permissions');
            return false;
        }

        $handler = $this->getHandler();
        $content = $this->Content;

        if ($handler && $content) {
            return $handler->canEditCover($content, $error);
        }

        return false;
    }

    /**
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canDelete(&$error = null)
    {
        $handler = $this->getHandler();
        $handler->setContent($this->Content);
        if (!$handler || !$handler->hasCoverPermission('delete')) {
            $error = XF::phrase('thcovers_no_delete_permissions');
            return false;
        }

        if (empty($this->cover_image) && empty($this->cover_styling)) {
            $error = XF::phrase('thcovers_only_custom_can_be_deleted');
            return false;
        }

        $handler = $this->getHandler();
        $content = $this->Content;

        if ($handler && $content) {
            return $handler->canDeleteCover($content, $error);
        }

        return false;
    }

    /**
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canPositionImage(&$error = null)
    {
        $handler = $this->getHandler();
        if (!$handler || !$handler->hasCoverPermission('positionImage')) {
            $error = XF::phrase('thcovers_no_position_permissions');
            return false;
        }

        if (empty($this->cover_image)) {
            $error = XF::phrase('thcovers_only_custom_can_be_positioned');
            return false;
        }

        return true;
    }

    /**
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canUploadImage(&$error = null)
    {
        $handler = $this->getHandler();
        if (!$handler || !$handler->hasCoverPermission('uploadImage')) {
            $error = XF::phrase('thcovers_no_upload_image_permissions');
            return false;
        }

        return true;
    }

    /**
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canUsePreset(&$error = null)
    {
        $handler = $this->getHandler();

        if (!$handler || !$handler->hasCoverPermission('preset')) {
            $error = XF::phrase('thcovers_no_preset_permissions');
            return false;
        }

        return true;
    }

    /**
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canDownloadImage(&$error = null)
    {
        $handler = $this->getHandler();
        if (!$handler || !$handler->hasCoverPermission('downloadImage')) {
            $error = XF::phrase('thcovers_no_download_image_permissions');
            return false;
        }

        return true;
    }

    /**
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canSetImage(&$error = null)
    {
        if ($this->canUploadImage($error) || $this->canDownloadImage($error)) {
            return true;
        }

        return false;
    }

    /**
     * @param null $error
     * @return bool
     * @throws Exception
     */
    public function canStyle(&$error = null)
    {
        $handler = $this->getHandler();
        if (!$handler || !$handler->hasCoverPermission('style')) {
            $error = XF::phrase('thcovers_no_style_permissions');
            return false;
        }

        return true;
    }
}
