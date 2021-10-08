<?php

namespace ThemeHouse\Covers\Service\Cover;

use ThemeHouse\Covers\Entity\Cover;
use XF;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

/**
 * Class Editor
 * @package ThemeHouse\Covers\Service\Cover
 */
class Editor extends AbstractService
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
    protected $validated = false;

    /**
     * @var bool
     */
    protected $alert = false;
    /**
     * @var string
     */
    protected $alertReason = '';

    /**
     * Editor constructor.
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
        $this->cover->cover_user_id = $user->user_id;
    }

    /**
     *
     */
    public function setDefaults()
    {
        $this->setUser(XF::visitor());
    }

    /**
     * @param array $coverDetails
     */
    public function setCoverDetails(array $coverDetails = [])
    {
        foreach ($coverDetails as $column => $value) {
            $this->cover->$column = $value;
        }
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
     * @return Cover
     * @throws PrintableException
     */
    public function save()
    {
        if (!$this->validated) {
            $this->validate();
        }

        $cover = $this->cover;
        $visitor = XF::visitor();

        $db = $this->db();
        $db->beginTransaction();

        $result = $cover->save(true, false);

        if ($result && $cover->cover_state == 'visible' && $this->alert && $cover->cover_user_id != $visitor->user_id) {
            /** @var \ThemeHouse\Covers\Repository\Cover $coverRepo */
            $coverRepo = $this->repository('ThemeHouse\Covers:Cover');
            $coverRepo->sendModeratorActionAlert($cover, 'edit', $this->alertReason);
        }

        $db->commit();

        return $cover;
    }

    /**
     * @param array $errors
     * @return bool
     */
    public function validate(&$errors = [])
    {
        $this->validated = true;

        $this->finalSetup();

        $success = $this->cover->preSave();
        if (!$success) {
            $errors = $this->cover->getErrors();
        }

        return $success;
    }

    /**
     *
     */
    protected function finalSetup()
    {
        $this->cover->cover_state = $this->cover->getNewCoverState();
    }
}
