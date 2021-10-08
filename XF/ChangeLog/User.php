<?php

namespace ThemeHouse\Covers\XF\ChangeLog;

/**
 * Class User
 * @package ThemeHouse\Covers\XF\ChangeLog
 */
class User extends XFCP_User
{
    /**
     * @return array
     */
    protected function getFormatterMap()
    {
        $map = parent::getFormatterMap();
        $map['thcovers_cover_date'] = 'formatDateTime';
        return $map;
    }
}
