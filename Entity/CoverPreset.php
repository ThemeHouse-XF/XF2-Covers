<?php

namespace ThemeHouse\Covers\Entity;

use Exception;
use ThemeHouse\Covers\Repository\CoverHandler;
use XF;
use XF\Api\Result\EntityResult;
use XF\Entity\Phrase;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * Class CoverPreset
 * @package ThemeHouse\Covers\Entity
 *
 * @property integer cover_preset_id
 * @property array cover_image
 * @property array cover_styling
 * @property array user_criteria
 * @property integer display_order
 * @property boolean enabled
 * @property array default_for
 * @property integer cover_preset_category_id
 *
 * @property Phrase|string title
 *
 * @property CoverPresetCategory PresetCategory
 * @property Phrase MasterTitle
 */
class CoverPreset extends Entity
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_covers_preset';
        $structure->shortName = 'ThemeHouse\Covers:CoverPreset';
        $structure->primaryKey = 'cover_preset_id';
        $structure->columns = [
            'cover_preset_id' => ['type' => self::UINT, 'autoIncrement' => true, 'api' => true],
            'cover_image' => ['type' => self::JSON_ARRAY, 'default' => [], 'api' => false],
            'cover_styling' => ['type' => self::JSON_ARRAY, 'default' => [], 'api' => true],
            'cover_preset_category_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
            'user_criteria' => [
                'type' => self::JSON_ARRAY,
                'default' => [],
                'verify' => 'verifyUserCriteria',
                'api' => false
            ],
            'display_order' => ['type' => self::UINT, 'default' => 10, 'api' => true],
            'enabled' => ['type' => self::BOOL, 'default' => true, 'api' => false],
            'default_for' => ['type' => self::LIST_COMMA, 'default' => [], 'api' => false]
        ];

        $structure->getters = [
            'title' => true,
            'default_names' => true,
            'css' => true,
        ];

        $structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'th_covers_cover_preset.', '$cover_preset_id']
                ]
            ],
            'PresetCategory' => [
                'type' => self::TO_ONE,
                'entity' => 'ThemeHouse\Covers:CoverPresetCategory',
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
        return 'th_covers_cover_preset.' . $this->cover_preset_id;
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
     * @return array
     * @throws Exception
     */
    public function getDefaultNames()
    {
        $defaults = $this->default_for;

        $handlers = $this->getCoverHandlerRepo()->getCoverHandlers();

        $phrases = [];
        foreach ($defaults as $default) {
            if (!empty($handlers[$default])) {
                $phrases[] = $handlers[$default]['object']->getContentTypePhrase();
            }
        }

        return $phrases;
    }

    public function getCss()
    {
        $css = [
            // 'background-position-x: center;',
        ];

        if (!empty($this->cover_image))
        {
            if (!empty($this->cover_image['banner_position_y']))
            {
                $css[] = "background-position-y: {$this->cover_image['banner_position_y']}%;";
            }
            if (!empty($this->cover_image['url']))
            {
                $css[] = "background-image: url('{$this->cover_image['url']}');";
            }
        }

        return implode('', $css);
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
     * @throws PrintableException
     */
    protected function _postDelete()
    {
        $phrase = $this->MasterTitle;
        if ($phrase) {
            $phrase->delete();
        }
        $this->rebuildCoverPresetCache();
    }

    /**
     *
     */
    protected function rebuildCoverPresetCache()
    {
        $repo = $this->getCoverPresetRepo();

        XF::runOnce('coverPresetCache', function () use ($repo) {
            $repo->rebuildCoverPresetCache();
        });
    }

    /**
     * @return \ThemeHouse\Covers\Repository\CoverPreset
     */
    protected function getCoverPresetRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\Covers:CoverPreset');
    }

    /**
     * @param $criteria
     * @return bool
     */
    protected function verifyUserCriteria(&$criteria)
    {
        $userCriteria = $this->app()->criteria('XF:User', $criteria);
        $criteria = $userCriteria->getCriteria();
        return true;
    }

    /**
     *
     */
    protected function _postSave()
    {
        $this->rebuildCoverPresetCache();
    }

    /**
     * @param EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return EntityResult
     * @noinspection PhpUndefinedFieldInspection
     */
    protected function setupApiResultData(
        EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        if (!empty($this->cover_image['url'])) {
            $image = $this->cover_image['url'];

            if (!preg_match('/^https?:\/\//', $image)) {

                if (strpos('/', $image) === 0) {
                    $image = XF::options()->boardUrl . $image;
                } else {
                    $baseUrl = join('/', array_slice(explode('/', XF::options()->boardUrl), 0, 3));
                    $image = $baseUrl . $image;
                }
            }

            $result->cover_image = $image;
        }

        $result->title = $this->title;

        return $result;
    }
}
