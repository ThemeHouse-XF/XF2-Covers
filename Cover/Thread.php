<?php

namespace ThemeHouse\Covers\Cover;

use XF;
use XF\Mvc\Entity\Entity;

/**
 * Class Thread
 * @package ThemeHouse\Covers\Cover
 */
class Thread extends AbstractHandler
{
    /**
     * @return string
     */
    public function getContentId()
    {
        return 'thread_id';
    }

    /**
     * @return string
     */
    public function getContentTitle()
    {
        return 'title';
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return 'thread';
    }

    /**
     * @return string
     */
    public function getContentRoute()
    {
        return 'threads';
    }

    /**
     * @param Entity $entity
     * @param null $error
     * @return bool
     */
    public function canEditCover(Entity $entity, &$error = null)
    {
        if ($entity->discussion_type == 'resource') {
            return false;
        }

        if (XF::options()->thcovers_allowContentOwnersToEdit) {
            /** @var XF\Entity\Thread $entity */
            return $entity->canEdit() || ($entity->user_id === XF::visitor()->user_id);
        }

        return parent::canEditCover($entity, $error);
    }

    /**
     * @return mixed|string
     */
    protected function getPermissionPrefix()
    {
        return 'thread_';
    }
}
