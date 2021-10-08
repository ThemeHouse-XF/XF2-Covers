<?php

namespace ThemeHouse\Covers\Service\Cover;

use ThemeHouse\Covers\Entity\Cover;
use XF;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

/**
 * Class Deleter
 * @package ThemeHouse\Covers\Service\Cover
 */
class Deleter extends AbstractService
{
    /**
     * @var Cover
     */
    protected $cover;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var bool
     */
    protected $alert = false;
    /**
     * @var string
     */
    protected $alertReason = '';

    /**
     * Deleter constructor.
     * @param App $app
     * @param Cover $cover
     */
    public function __construct(App $app, Cover $cover)
    {
        parent::__construct($app);
        $this->setCover($cover);
    }

    /**
     * @return Cover
     */
    public function getCover()
    {
        return $this->cover;
    }

    /**
     * @param Cover $cover
     */
    public function setCover(Cover $cover)
    {
        $this->cover = $cover;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     */
    public function setUser(User $user = null)
    {
        $this->user = $user;
    }

    /**
     * @param $alert
     * @param null $reason
     */
    public function setSendAlert($alert, $reason = null)
    {
        $this->alert = (bool)$alert;
        if ($reason !== null) {
            $this->alertReason = $reason;
        }
    }

    /**
     * @return bool
     * @throws PrintableException
     */
    public function delete()
    {
        $user = $this->user ?: XF::visitor();

        $cover = $this->cover;

        if ($cover->cover_date) {
            /** @var Image $service */
            $service = $this->service(
                'ThemeHouse\Covers:Cover\Image',
                $cover->content_id,
                $cover->content_type,
                $cover->CoverUser
            );
            $service->deleteCoverImage();
        }

        $wasVisible = $cover->cover_state == 'visible';

        $cover->bulkSet([
            'cover_image' => [],
            'cover_styling' => [],
            'cover_date' => 0,
            'cover_state' => 'deleted',
            'cover_preset' => 0
        ]);

        $result = $cover->save();

        if ($result && $wasVisible && $this->alert && $cover->cover_user_id != $user->user_id) {
            /** @var \ThemeHouse\Covers\Repository\Cover $coverRepo */
            $coverRepo = $this->repository('ThemeHouse\Covers:Cover');
            $coverRepo->sendModeratorActionAlert($cover, 'delete', $this->alertReason);
        }

        return $result;
    }
}
