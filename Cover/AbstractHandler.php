<?php

namespace ThemeHouse\Covers\Cover;

use XF;
use XF\Entity\User;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Phrase;

/**
 * Class AbstractHandler
 * @package ThemeHouse\Covers\Cover
 */
abstract class AbstractHandler
{
    /**
     * @var
     */
    protected $content;

    /**
     * @return mixed
     */
    abstract public function getContentId();

    /**
     * @return mixed
     */
    abstract public function getContentTitle();

    /**
     * @param $id
     * @return null|ArrayCollection|Entity
     */
    public function getContent($id)
    {
        return $this->content = XF::app()->findByContentType($this->getContentType(), $id, $this->getEntityWith());
    }

    /**
     * @param $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return mixed
     */
    abstract public function getContentType();

    /**
     * @return array
     */
    public function getEntityWith()
    {
        return [];
    }

    /**
     * @return null|Entity
     */
    public function getDefaultCover()
    {
        $finder = XF::finder('ThemeHouse\Covers:CoverPreset');

        $finder->whereSql("FIND_IN_SET('{$this->getContentType()}', default_for)");

        return $finder->fetchOne();
    }

    /**
     * @param bool $content
     * @param string $prefix
     * @param array $params
     * @return mixed|string
     */
    public function getContentUrl($content = false, $prefix = '', array $params = [])
    {
        if (!$content) {
            $content = $this->content;
        }

        return XF::app()->router(($prefix == 'nopath' ? '' : (!empty($prefix) ? $prefix . ':' : '') . 'public'))->buildLink(
            $this->getContentRoute(),
            $content,
            $params
        );
    }

    /**
     * @return mixed
     */
    abstract public function getContentRoute();

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->content->user_id;
    }

    /**
     * @param Entity $entity
     * @param null $error
     * @return bool
     */
    public function canViewCover(Entity $entity, &$error = null)
    {
        if (method_exists($entity, 'canView')) {
            return $entity->canView($error);
        }

        return true;
    }

    /**
     * @param Entity $entity
     * @param null $error
     * @return bool
     */
    public function canEditCover(Entity $entity, &$error = null)
    {
        if (method_exists($entity, 'canEdit')) {
            return $entity->canEdit($error);
        }

        return true;
    }

    /**
     * @param Entity $entity
     * @param null $error
     * @return bool
     */
    public function canDeleteCover(Entity $entity, &$error = null)
    {
        if (method_exists($entity, 'canEdit')) {
            return $entity->canEdit($error);
        }

        return true;
    }

    /**
     * @return Phrase
     */
    public function getContentTypePhrase()
    {
        return XF::app()->getContentTypePhrase($this->getContentType());
    }

    /**
     * @param $permission
     * @param User|null $user
     * @return bool
     */
    public function hasCoverPermission($permission, User $user = null)
    {
        if (!$user) {
            $user = XF::visitor();
        }

        if ($permission !== 'view' && $this->content->user_id !== $user->user_id) {
            return $user->hasPermission('th_cover', 'edit_all');
        }

        return $user->hasPermission($this->getPermissionGroup(), $this->getPermissionPrefix() . $permission);
    }

    /**
     * @return string
     */
    protected function getPermissionGroup()
    {
        return 'th_cover';
    }

    /**
     * @return mixed
     */
    abstract protected function getPermissionPrefix();
}
