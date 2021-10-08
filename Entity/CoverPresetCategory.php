<?php

namespace ThemeHouse\Covers\Entity;

use XF;
use XF\Api\Result\EntityResult;
use XF\Entity\Phrase;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * Class CoverPresetCategory
 * @package ThemeHouse\Covers\Entity
 *
 * @property integer cover_preset_category_id
 * @property integer display_order
 *
 * @property Phrase|string title
 *
 * @property ArrayCollection|CoverPreset[] Presets
 * @property Phrase MasterTitle
 */
class CoverPresetCategory extends Entity
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_covers_preset_category';
        $structure->shortName = 'ThemeHouse\Covers:CoverPresetCategory';
        $structure->primaryKey = 'cover_preset_category_id';
        $structure->columns = [
            'cover_preset_category_id' => ['type' => self::UINT, 'autoIncrement' => true, 'api' => true],
            'display_order' => ['type' => self::UINT, 'default' => 10, 'api' => true]
        ];

        $structure->getters = [
            'title' => true
        ];
        $structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'th_covers_cover_preset_category.', '$cover_preset_category_id']
                ]
            ],
            'Presets' => [
                'entity' => 'ThemeHouse\Covers:CoverPreset',
                'type' => self::TO_MANY,
                'conditions' => 'cover_preset_category_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

    /**
     * @return XF\Phrase
     */
    public function getTitle()
    {
        return XF::phrase($this->getPhraseName());
    }

    /**
     * @return string
     */
    public function getPhraseName()
    {
        return 'th_covers_cover_preset_category.' . $this->cover_preset_category_id;
    }

    /**
     * @return mixed|null|Phrase
     */
    public function getMasterPhrase()
    {
        $phrase = $this->MasterTitle;
        if (!$phrase) {
            /** @var Phrase $phrase */
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function () {
                return $this->getPhraseName();
            }, 'save');
            $phrase->language_id = 0;
            $phrase->addon_id = '';
        }

        return $phrase;
    }

    /**
     * @throws PrintableException
     */
    protected function _postDelete()
    {
        $phrase = $this->MasterTitle;
        if ($phrase) {
            $phrase->delete();
        }
    }

    /**
     * @param EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @noinspection PhpUndefinedFieldInspection
     */
    protected function setupApiResultData(
        EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        $result->title = $this->title;

    }
}