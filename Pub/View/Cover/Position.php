<?php

namespace ThemeHouse\Covers\Pub\View\Cover;

use XF\Mvc\View;

/**
 * Class Position
 * @package ThemeHouse\Covers\Pub\View\Cover
 */
class Position extends View
{
    /**
     * @return array
     */
    public function renderJson()
    {
        $cover = $this->params['cover'];

        $templater = $this->renderer->getTemplater();
        $target = $templater->func('thcovers_cover_class', [$cover, true]);

        return [
            'target' => $target,
            'html' => $this->renderTemplate(
                $this->templateName,
                $this->params
            )
        ];
    }
}
