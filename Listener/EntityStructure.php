<?php

namespace ThemeHouse\Covers\Listener;

use Exception;
use ThemeHouse\Covers\Repository\CoverHandler;
use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;

/**
 * Class EntityStructure
 * @package ThemeHouse\Covers\Listener
 */
class EntityStructure
{
    /**
     * @param Manager $em
     * @param Structure $structure
     * @throws Exception
     * @deprecated
     */
    public static function coverEntityHandlers(Manager $em, Structure &$structure)
    {
        self::entityStructure($em, $structure);
    }

    /**
     * @param Manager $em
     * @param Structure $structure
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     * @throws Exception
     */
    public static function entityStructure(Manager $em, Structure &$structure)
    {
        /** @var CoverHandler $coverRepo */
        $coverRepo = XF::Repository('ThemeHouse\Covers:CoverHandler');
        if (isset($structure->contentType) && $coverRepo->getCoverHandler($structure->contentType)) {
            $structure->relations['ThCover'] = [
                'entity' => 'ThemeHouse\Covers:Cover',
                'type' => Entity::TO_ONE,
                'conditions' => [
                    ['content_type', '=', $structure->contentType],
                    ['content_id', '=', '$' . $structure->primaryKey]
                ],
                'key' => 'content_id'
            ];
        }
    }
}
