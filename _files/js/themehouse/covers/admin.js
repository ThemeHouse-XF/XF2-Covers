!function($, window, document, _undefined)
{
    "use strict";
    XF.THCoversPresetPositioner = XF.Element.newHandler({

        options: {},

        $banner: null,
        $value: null,
        y: 0,

        ns: 'THCoversPresetPositioner',
        dragging: false,
        scaleFactor: 1,

        init: function()
        {
            var $banner = this.$target;

            console.log($banner.css('background-image'));

            this.$banner = $banner;
            $banner.css({
                'touch-action': 'none',
                'cursor': 'move'
            });

            this.$value = $banner.find('.js-bannerPosY');

            this.initDragging();

            var t = this;

            // refresh when changing tabs
            $(document.body).on('xf:layout', function()
            {
                var yPos = $banner.css('background-position-y');
                if (yPos)
                {
                    t.$value.val(parseFloat(yPos));
                }

                t.stopDragging();
                $banner.off('.' + t.ns);
                t.initDragging();
            });
        },

        initDragging: function()
        {
            var ns = this.ns,
                $banner = this.$banner,
                imageUrl = $banner.css('background-image'),
                image = new Image(),
                t = this;

            imageUrl = imageUrl.replace(/^url\(["']?(.*?)["']?\)$/i, '$1');
            if (!imageUrl)
            {
                return;
            }

            image.onload = function()
            {
                var setup = function()
                {
                    // scaling makes pixel-based pointer movements map to percentage shifts
                    var displayScale = image.width ? $banner.width() / image.width : 1;
                    t.scaleFactor = 1 / (image.height * displayScale / 100);

                    $banner.on('mousedown.' + ns + ' touchstart.' + ns, XF.proxy(t, 'dragStart'));
                };

                console.log($banner);

                if ($banner.width() > 0)
                {
                    console.log('setting up');
                    setup();
                }
                else
                {
                    // it's possible for this to be triggered when the banner container has been hidden,
                    // so only allow this to be triggered again once we know the banner is visible
                    $banner.one('mouseover.' + ns + ' touchstart.' + ns, setup);
                }
            };
            image.src = XF.canonicalizeUrl(imageUrl);
        },

        dragStart: function(e)
        {
            e.preventDefault();

            var oe = e.originalEvent,
                ns = this.ns;

            if (oe.touches)
            {
                this.y = oe.touches[0].clientY;
            }
            else
            {
                this.y = oe.clientY;

                if (oe.button > 0)
                {
                    // probably a right click or similar
                    return;
                }
            }

            this.dragging = true;

            $(window)
                .on('mousemove.' + ns + ' touchmove.' + ns, XF.proxy(this, 'dragMove'))
                .on('mouseup.' + ns + ' touchend.' + ns, XF.proxy(this, 'dragEnd'));
        },

        dragMove: function(e)
        {
            if (this.dragging)
            {
                e.preventDefault();

                var oe = e.originalEvent,
                    existingPos = parseFloat(this.$banner.css('background-position-y')),
                    newY, newPos;

                if (oe.touches)
                {
                    newY = oe.touches[0].clientY;
                }
                else
                {
                    newY = oe.clientY;
                }

                newPos = existingPos + (this.y - newY) * this.scaleFactor;
                newPos = Math.min(Math.max(0, newPos), 100);

                this.$banner.css('background-position-y', newPos + '%');
                this.$value.val(newPos);
                this.y = newY;
            }
        },

        dragEnd: function(e)
        {
            this.stopDragging();
        },

        stopDragging: function()
        {
            if (this.dragging)
            {
                $(window).off('.' + this.ns);

                this.y = 0;
                this.dragging = false;
            }
        }
    });

    XF.Element.register('thcovers-preset-positioner', 'XF.THCoversPresetPositioner');
}
(jQuery, window, document);