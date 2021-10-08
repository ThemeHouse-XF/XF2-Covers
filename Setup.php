<?php

namespace ThemeHouse\Covers;

use ThemeHouse\Covers\Entity\Cover;
use XF;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\PrintableException;
use XF\Service\User\ProfileBanner;
use XF\Util\File;

/**
 * Class Setup
 * @package ThemeHouse\Covers
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     *
     */
    public function installStep1()
    {
        $schemaManager = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $closure) {
            $schemaManager->createTable($tableName, $closure);
        }
    }

    /**
     * @return array
     */
    public function getTables()
    {
        $tables = [];

        $tables['xf_th_covers_cover'] = function (Create $table) {
            $table->addColumn('cover_id', 'int')->autoIncrement();
            $table->addColumn('content_id', 'int');
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('cover_user_id', 'int');
            $table->addColumn('cover_preset', 'int')->setDefault(0);
            $table->addColumn('cover_image', 'mediumblob');
            $table->addColumn('cover_styling', 'mediumblob');
            $table->addColumn('cover_date', 'int')->setDefault(0);
            $table->addColumn('cover_state', 'enum')->values(['visible', 'moderated', 'deleted'])
                ->setDefault('visible');
            $table->addKey(['content_type', 'content_id', 'cover_user_id'], 'content_type_id_cover_user_id');
            $table->addKey(['cover_user_id', 'content_type', 'content_id'], 'cover_user_content_type_id');
            $table->addKey(['cover_user_id', 'cover_date'], 'cover_user_id_cover_date');
            $table->addKey('cover_date', 'cover_date');
            $table->addKey('cover_preset', 'cover_preset');
        };

        $tables['xf_th_covers_preset'] = function (Create $table) {
            $table->addColumn('cover_preset_id', 'int')->autoIncrement();
            $table->addColumn('cover_image', 'mediumblob');
            $table->addColumn('cover_styling', 'mediumblob');
            $table->addColumn('user_criteria', 'mediumblob');
            $table->addColumn('display_order', 'int')->setDefault(1);
            $table->addColumn('enabled', 'bool')->setDefault(1);
            $table->addColumn('default_for', 'mediumblob');
            $table->addColumn('cover_preset_category_id', 'int')->setDefault(0);
        };

        $tables['xf_th_covers_preset_category'] = function (Create $table) {
            $table->addColumn('cover_preset_category_id', 'int')->autoIncrement();
            $table->addColumn('display_order', 'int')->setDefault(10);
            $table->addPrimaryKey('cover_preset_category_id');
        };

        return $tables;
    }

    /**
     * @throws Exception
     */
    public function installStep2()
    {
        $this->db()->query('ALTER TABLE xf_th_covers_preset_category AUTO_INCREMENT=1');
    }

    /**
     * @param array $stateChanges
     */
    public function postInstall(array &$stateChanges)
    {
        if ($this->applyDefaultPermissions()) {
            // since we're running this after data imports, we need to trigger a permission rebuild
            // if we changed anything
            $this->app->jobManager()->enqueueUnique(
                'permissionRebuild',
                'XF:PermissionRebuild',
                [],
                false
            );
        }
    }

    /**
     * @param null $previousVersion
     * @return bool
     */
    protected function applyDefaultPermissions($previousVersion = null)
    {
        $applied = false;

        if (!$previousVersion) {
            $this->applyGlobalPermission('th_cover', 'user_view');
            $this->applyGlobalPermission('th_cover', 'user_edit');
            $this->applyGlobalPermission('th_cover', 'user_uploadImage');
            $this->applyGlobalPermission('th_cover', 'user_downloadImage');
            $this->applyGlobalPermission('th_cover', 'user_positionImage');
            $this->applyGlobalPermission('th_cover', 'user_style');
            $this->applyGlobalPermission('th_cover', 'user_preset');
            $this->applyGlobalPermission('th_cover', 'user_delete');

            $this->applyGlobalPermission('th_cover', 'thread_view');
            $this->applyGlobalPermission('th_cover', 'thread_edit');
            $this->applyGlobalPermission('th_cover', 'thread_uploadImage');
            $this->applyGlobalPermission('th_cover', 'thread_downloadImage');
            $this->applyGlobalPermission('th_cover', 'thread_positionImage');
            $this->applyGlobalPermission('th_cover', 'thread_style');
            $this->applyGlobalPermission('th_cover', 'thread_preset');
            $this->applyGlobalPermission('th_cover', 'thread_delete');

            $this->applyGlobalPermission('th_cover', 'resource_view');
            $this->applyGlobalPermission('th_cover', 'resource_edit');
            $this->applyGlobalPermission('th_cover', 'resource_uploadImage');
            $this->applyGlobalPermission('th_cover', 'resource_downloadImage');
            $this->applyGlobalPermission('th_cover', 'resource_positionImage');
            $this->applyGlobalPermission('th_cover', 'resource_style');
            $this->applyGlobalPermission('th_cover', 'resource_preset');
            $this->applyGlobalPermission('th_cover', 'resource_delete');

            $applied = true;
        }

        return $applied;
    }

    /**
     *
     */
    public function upgrade1000131Step1()
    {
        $this->schemaManager()->renameTable('xf_thcover_cover', 'xf_th_covers_cover');
        $this->schemaManager()->renameTable('xf_thcover_preset', 'xf_th_covers_preset');
    }

    /**
     * @throws PrintableException
     */
    public function upgrade1000231Step1()
    {
        $this->db()->beginTransaction();
        $presets = $this->db()->fetchAll('SELECT * FROM xf_th_covers_preset');

        foreach ($presets as $preset) {
            $title = XF::em()->create('XF:Phrase');
            $title->bulkSet([
                'addon_id' => '',
                'language_id' => 0,
                'title' => 'th_covers_cover_preset.' . $preset['cover_preset_id'],
                'phrase_text' => $preset['title']
            ]);
            $title->save();
        }

        $this->db()->commit();

        $this->app->jobManager()->enqueueUnique('languageRebuild', 'XF:Atomic', [
            'execute' => ['XF:PhraseRebuild', 'XF:TemplateRebuild']
        ]);
    }

    /**
     *
     */
    public function upgrade1000231Step2()
    {
        $this->schemaManager()->alterTable('xf_th_covers_preset', function (Alter $table) {
            $table->addColumn('cover_preset_category_id', 'int')->setDefault(0);
            $table->dropColumns(['title']);
        });
    }

    /**
     * @throws Exception
     */
    public function upgrade1000231Step3()
    {
        $this->schemaManager()->createTable('xf_th_covers_preset_category', function (Create $table) {
            $table->addColumn('cover_preset_category_id', 'int')->autoIncrement();
            $table->addColumn('display_order', 'int')->setDefault(10);
            $table->addPrimaryKey('cover_preset_category_id');
        });

        $this->db()->query('ALTER TABLE xf_th_covers_preset_category AUTO_INCREMENT=1');
    }

    // ############################# FINAL UPGRADE ACTIONS #############################

    /**
     * @param array $stepParams
     */
    public function upgrade1000231Step4(array $stepParams)
    {
        $position = empty($stepParams[0]) ? 0 : $stepParams[0];
        $this->entityColumnsToJson('ThemeHouse\Covers:Cover', ['cover_image', 'cover_styling'], $position, $stepParams);
    }

    /**
     * @param array $stepData
     * @return array|bool
     * @throws PrintableException
     */
    public function upgrade1010011Step1(array $stepData)
    {
        $position = empty($stepData[0]) ? 0 : $stepData[0];

        $db = $this->db();

        if (!isset($stepData['max'])) {
            $stepData['max'] = $db->fetchOne("SELECT MAX(cover_id) FROM xf_th_covers_cover");
        }

        $ids = $db->fetchAllColumn($db->limit('
            SELECT
                cover_id
            FROM
                xf_th_covers_cover
            WHERE
                cover_id > ?
            
        ', 10), [$position]);

        if (!$ids) {
            return true;
        }

        $em = $this->app()->em();
        /** @var Repository\Cover $repo */
        $repo = $this->app()->repository('ThemeHouse\Covers:Cover');

        $next = 0;
        foreach ($ids as $id) {
            $next = $id;
            /** @var Cover $cover */
            $cover = $em->find('ThemeHouse\Covers:Cover', $id);
            if ($cover) {
                if ($cover->content_type != 'user') {
                    continue;
                }

                $user = $em->find('XF:User', $cover->content_id);

                $coverFile = $repo->getAbstractedCustomCoverPath($cover->content_type, $cover->content_id, 'l');

                if ($user) {
                    try {
                        $tempFile = File::copyAbstractedPathToTempFile($coverFile);
                    } catch (\Throwable $e) {
                        $tempFile = false;
                    }

                    if ($tempFile) {
                        /** @var ProfileBanner $coverService */
                        $coverService = $this->app()->service('XF:User\ProfileBanner', $user);
                        $coverService->setImage($tempFile);
                        $coverService->updateBanner();
                        unlink($tempFile);
                        $cover->delete(false);
                        File::deleteFromAbstractedPath($coverFile);
                    }
                }
            }
        }

        return [
            $next,
            $next . ' / ' . $stepData['max'],
            $stepData
        ];
    }

    // ############################# UNINSTALL #############################

    /**
     * @param array $stepParams
     */
    public function upgrade1000231Step5(array $stepParams)
    {
        $position = empty($stepParams[0]) ? 0 : $stepParams[0];
        $this->entityColumnsToJson('ThemeHouse\Covers:CoverPreset', ['cover_image', 'cover_styling', 'user_criteria'],
            $position, $stepParams);
    }

    /**
     * @param $previousVersion
     * @param array $stateChanges
     */
    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if ($this->applyDefaultPermissions($previousVersion)) {
            // since we're running this after data imports, we need to trigger a permission rebuild
            // if we changed anything
            $this->app->jobManager()->enqueueUnique(
                'permissionRebuild',
                'XF:PermissionRebuild',
                [],
                false
            );
        }
    }

    // ############################# TABLE / DATA DEFINITIONS #############################

    /**
     *
     */
    public function uninstallStep1()
    {
        $schemaManager = $this->schemaManager();

        foreach (array_keys($this->getTables()) as $tableName) {
            $schemaManager->dropTable($tableName);
        }
    }

    /**
     * @throws Exception
     */
    public function uninstallStep2()
    {
        $this->db()->query("DELETE FROM xf_phrase WHERE title LIKE 'th_covers_cover_preset.%'");
        $this->db()->query("DELETE FROM xf_phrase WHERE title LIKE 'th_covers_cover_preset_category.%'");

        $this->app->jobManager()->enqueueUnique('languageRebuild', 'XF:Atomic', [
            'execute' => ['XF:PhraseRebuild', 'XF:TemplateRebuild']
        ]);
    }
}
