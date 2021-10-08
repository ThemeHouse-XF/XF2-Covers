<?php

namespace ThemeHouse\Covers\Import\Importer;

use ThemeHouse\Covers\Entity\Cover;
use ThemeHouse\Covers\Service\Cover\Image;
use XF;
use XF\Import\Importer\AbstractImporter;
use XF\Import\StepState;
use XF\PrintableException;
use XF\Service\User\ProfileBanner;
use XF\Util\File;

/**
 * Class THCovers
 * @package ThemeHouse\Covers\Import\Importer
 */
class THCovers extends AbstractImporter
{
    /**
     * @return array
     */
    public static function getListInfo()
    {
        return [
            'target' => '[TH] Covers',
            'source' => 'ThemeHouse Covers'
        ];
    }

    /**
     * @param array $vars
     * @return string
     */
    public function renderBaseConfigOptions(array $vars)
    {
        return $this->app->templater()->renderTemplate('admin:thcovers_import_config', $vars);
    }

    /**
     * @param array $baseConfig
     * @param array $errors
     * @return bool
     */
    public function validateBaseConfig(array &$baseConfig, array &$errors)
    {
        return true;
    }

    /**
     * @param array $vars
     * @return string
     */
    public function renderStepConfigOptions(array $vars)
    {
        return $this->app->templater()->renderTemplate('admin:thcovers_import_step_config', $vars);
    }

    /**
     * @param array $steps
     * @param array $stepConfig
     * @param array $errors
     * @return bool
     */
    public function validateStepConfig(array $steps, array &$stepConfig, array &$errors)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canRetainIds()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function resetDataForRetainIds()
    {
        return false;
    }

    /**
     * @return array
     */
    public function getSteps()
    {
        return [
            'profileCovers' => ['title' => 'Profile Covers'],
            'resourceCovers' => ['title' => 'Resource Covers']
        ];
    }

    /**
     * @return bool|null
     */
    public function getStepEndProfileCovers()
    {
        return $this->db()->fetchOne('SELECT max(user_id) FROM xf_user');
    }

    /**
     * @param StepState $state
     * @param array $stepConfig
     * @param $maxTime
     * @return StepState
     */
    public function stepProfileCovers(StepState $state, array $stepConfig, $maxTime)
    {
        // TODO: CHANGE TO XF COVERS IMPORT
        $limit = 15;

        $users = $this->db()->fetchAll("
            SELECT *
            FROM xf_user_profile
            WHERE user_id > ? AND user_id <= ?
            ORDER BY user_id
            LIMIT {$limit}
        ", [
            $state->startAfter,
            $state->end
        ]);

        /** @var \ThemeHouse\Covers\Repository\Cover $repository */
        $repository = XF::repository('ThemeHouse\Covers:Cover');

        foreach ($users as $user) {
            if (!$user['profile_cover_image'] || $user['profile_cover_state'] !== 'visible') {
                $state->imported++;
                $state->startAfter = $user['user_id'];
                continue;
            }

            $filePath = $repository->getAbstractedCustomCoverPath('profile', $user['user_id'], 'l');
            $tempFile = File::copyAbstractedPathToTempFile($filePath);
            $newFilePath = $repository->getAbstractedCustomCoverPath('user', $user['user_id'], 'o');
            File::copyFileToAbstractedPath($tempFile, $newFilePath);

            /** @var ProfileBanner $coverService */
            $coverService = $this->app->service('XF:ProfileBanner');
            $coverService->setImage($tempFile);
            $coverService->setPosition($user['profile_cover_crop_y']);
            $coverService->updateBanner();
            unlink($tempFile);

            $state->imported++;
            $state->startAfter = $user['user_id'];
        }

        if ($state->startAfter === $state->end) {
            return $state->complete();
        }

        return $state;
    }

    /**
     * @param StepState $state
     * @param array $stepConfig
     * @param $maxTime
     * @return StepState
     * @throws PrintableException
     */
    public function stepResourceCovers(StepState $state, array $stepConfig, $maxTime)
    {
        $limit = 15;

        $resources = $this->db()->fetchAll("
            SELECT *
            FROM xf_rm_resource
            WHERE resource_id > ? AND resource_id <= ?
            ORDER BY resource_id
            LIMIT {$limit}
        ", [
            $state->startAfter,
            $state->end
        ]);

        /** @var \ThemeHouse\Covers\Repository\Cover $repository */
        $repository = XF::repository('ThemeHouse\Covers:Cover');

        foreach ($resources as $resource) {
            /** @var Cover $cover */
            $cover = XF::em()->create('ThemeHouse\Covers:Cover');

            if (!$resource['resource_cover_image'] || $resource['resource_cover_state'] !== 'visible') {
                $state->imported++;
                $state->startAfter = $resource['resource_id'];
                continue;
            }

            $filePath = $repository->getAbstractedCustomCoverPath('resource', $resource['resource_id'], 'l');
            $tempFile = File::copyAbstractedPathToTempFile($filePath);
            $newFilePath = $repository->getAbstractedCustomCoverPath('resource', $resource['resource_id'], 'o');
            File::copyFileToAbstractedPath($tempFile, $newFilePath);
            unlink($tempFile);

            $cover->content_type = 'resource';
            $cover->content_id = $resource['resource_id'];
            $cover->cover_date = $resource['resource_cover_date'];

            /** @var Image $coverService */
            $coverService = XF::service('ThemeHouse\Covers:Cover\Image', $resource['resource_id'], 'resource');
            $coverService->setImageFromExisting();
            $imageDetails = $coverService->updateCoverImage();

            $imageDetails += [
                'cropX' => $resource['resource_cover_crop_x'],
                'cropY' => $resource['resource_cover_crop_y']
            ];

            $cover->cover_image = $imageDetails;
            $cover->cover_styling = [
                'background_color' => $resource['resource_cover_color']
            ];
            $cover->save();

            $state->imported++;
            $state->startAfter = $resource['resource_id'];
        }

        if ($state->startAfter === $state->end) {
            return $state->complete();
        }

        return $state;
    }

    /**
     * @return int
     */
    public function getStepEndResourceCovers()
    {
        return $this->db()->fetchOne('SELECT max(resource_id) FROM xf_rm_resource') ?: 0;
    }

    /**
     * @param array $stepsRun
     * @return array
     */
    public function getFinalizeJobs(array $stepsRun)
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getBaseConfigDefault()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getStepConfigDefault()
    {
        return [];
    }

    /**
     * @return bool
     */
    protected function doInitializeSource()
    {
        return true;
    }
}
