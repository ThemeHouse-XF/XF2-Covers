<?php

namespace ThemeHouse\Covers\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Entity\Thread;
use XF\Mvc\Entity\Entity;

/**
 * Class Cover
 * @package ThemeHouse\Covers\ApprovalQueue
 */
class Cover extends AbstractHandler
{
    /**
     * @param \ThemeHouse\Covers\Entity\Cover $cover
     */
    public function actionApprove(\ThemeHouse\Covers\Entity\Cover $cover)
    {
        $this->quickUpdate($cover, 'cover_state', 'visible');
    }

    /**
     * @param \ThemeHouse\Covers\Entity\Cover $cover
     */
    public function actionDelete(\ThemeHouse\Covers\Entity\Cover $cover)
    {
        $this->quickUpdate($cover, 'cover_state', 'deleted');
    }

    /**
     * @param Entity|Thread $content
     * @param null $error
     * @return bool
     */
    protected function canActionContent(Entity $content, &$error = null)
    {
        return $content->canApproveUnapprove($error);
    }
}
