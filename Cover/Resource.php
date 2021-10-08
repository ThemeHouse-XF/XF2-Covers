<?php

namespace ThemeHouse\Covers\Cover;

use XF;
use XF\Mvc\Entity\Entity;
use XFRM\Entity\ResourceItem;


/**
 * Class Resource
 * @package ThemeHouse\Covers\Cover
 */
class Resource extends AbstractHandler
{
    /**
     * @return string
     */
    public function getContentId()
    {
        return 'resource_id';
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
        return 'resource';
    }

    /**
     * @return string
     */
    public function getContentRoute()
    {
        return 'resources';
    }

    /**
     * @param Entity $entity
     * @param null $error
     * @return bool
     */
    public function canEditCover(Entity $entity, &$error = null)
    {
        if (XF::options()->thcovers_allowContentOwnersToEdit) {
            /** @var ResourceItem $entity */
            return $entity->canEdit() || ($entity->user_id === XF::visitor()->user_id);
        }

        return parent::canEditCover($entity, $error);
    }

    /**
     * @return mixed|string
     */
    protected function getPermissionPrefix()
    {
        return 'resource_';
    }
}
