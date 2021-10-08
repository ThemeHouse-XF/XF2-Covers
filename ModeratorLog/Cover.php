<?php

namespace ThemeHouse\Covers\ModeratorLog;

use Exception;
use ThemeHouse\Covers\Repository\CoverHandler;
use XF;
use XF\Entity\ModeratorLog;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XFRM\Entity\ResourceItem;

/**
 * Class Cover
 * @package ThemeHouse\Covers\ModeratorLog
 */
class Cover extends AbstractHandler
{
    /**
     * @param Entity $content
     * @param $action
     * @param User $actor
     * @return bool
     */
    public function isLoggable(Entity $content, $action, User $actor)
    {
        switch ($action) {
            case 'edit':
                /** @var Thread|ResourceItem|User $content */
                if ($actor->user_id == $content->user_id) {
                    return false;
                }
        }

        return parent::isLoggable($content, $action, $actor);
    }

    /**
     * @param ModeratorLog $log
     * @return null|string|string[]|Phrase
     */
    public function getContentTitle(ModeratorLog $log)
    {
        return XF::phrase('th_x_cover_covers', [
            'content_type' => XF::app()->stringFormatter()->censorText($log->content_title_)
        ]);
    }

    /**
     * @param Entity $content
     * @param $field
     * @param $newValue
     * @param $oldValue
     * @return array|bool|string
     */
    protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
    {
        switch ($field) {
            case 'cover_date':
                return 'edit';

            case 'cover_state':
                if ($newValue == 'visible' && $oldValue == 'moderated') {
                    return 'approve';
                } else {
                    if ($newValue == 'visible' && $oldValue == 'deleted') {
                        return 'undelete';
                    } else {
                        if ($newValue == 'deleted') {
                            /** @var XF\Entity\DeletionLog $deletionLog */
                            /** @var Thread $content */
                            $deletionLog = $content->DeletionLog;
                            $reason = $deletionLog ? $deletionLog->delete_reason : '';
                            return ['delete_soft', ['reason' => $reason]];
                        } else {
                            if ($newValue == 'moderated') {
                                return 'unapprove';
                            }
                        }
                    }
                }

                break;
        }

        return false;
    }

    /**
     * @param ModeratorLog $log
     * @param Entity $content
     * @throws Exception
     */
    protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
    {
        /** @var CoverHandler $coverRepo */
        $coverRepo =  XF::Repository('ThemeHouse\Covers:CoverHandler');
        /** @var \ThemeHouse\Covers\Cover\AbstractHandler $coverHandler */
        $coverHandler = $coverRepo->getCoverHandler(
            $content->getEntityContentType(),
            true
        );
        /** @noinspection PhpUndefinedFieldInspection */
        /** @var \ThemeHouse\Covers\Entity\Cover $cover */
        $cover = $content->ThCover;

        $log->content_user_id = $cover->cover_user_id;
        $log->content_username = $cover->CoverUser->username;
        $log->content_type = $content->getEntityContentType();
        $log->content_id = $content->getEntityId();
        $log->content_url = $coverHandler->getContentUrl($content, 'nopath');
        $log->discussion_content_type = 'thread';
        $log->discussion_content_id = 0;
    }
}
