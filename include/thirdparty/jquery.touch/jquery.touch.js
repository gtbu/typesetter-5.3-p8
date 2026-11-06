/* jquery.touch v1.1.0 | (c) @ajlkn | github.com/ajlkn/jquery.touch | MIT licensed */

/* jquery.touch v1.1.0 | (c) @ajlkn | github.com/ajlkn/jquery.touch | MIT licensed */

(function($) {

    var $document = $(document),
        dragTarget = null,
        dropTargetElement = null;

    var defaultSettings = {
        useTouch: true,
        useMouse: true,
        trackDocument: false,
        trackDocumentNormalize: false,
        noClick: false,
        dragThreshold: 10,
        dragDelay: 200,
        swipeThreshold: 30,
        tapDelay: 250,
        tapAndHoldDelay: 500,
        delegateSelector: null,
        dropFilter: false,
        dropFilterTraversal: true,
        coordinates: 'page',
        preventDefault: {
            drag: false,
            swipe: false,
            tap: false
        }
    };

    function touch($element, $sourceElement, settings) {

        var t = this;

        t.settings = settings;
        t.$element = $element;
        t.$sourceElement = $sourceElement;
        t.inTap = false;
        t.inTapAndHold = false;
        t.inDrag = false;
        t.tapStart = null;
        t.dragStart = null;
        t.timerTap = null;
        t.timerTapAndHold = null;
        t.mouseDown = false;
        t.x = null;
        t.y = null;
        t.ex = null;
        t.ey = null;
        t.xStart = null;
        t.yStart = null;
        t.exStart = null;
        t.eyStart = null;
        t.taps = 0;
        t.started = false;
        t.ended = false;
    }

    touch.prototype.uses = function(x) {

        var events = $._data(this.$sourceElement[0], 'events');

        if (!events) {
            return false;
        }

        var has = function(name) {
            return Object.prototype.hasOwnProperty.call(events, name);
        };

        switch (x) {

            case 'swipe':
                return has('swipe')
                    || has('swipeUp')
                    || has('swipeDown')
                    || has('swipeLeft')
                    || has('swipeRight');

            case 'drag':
                return has('drag')
                    || has('dragStart')
                    || has('dragEnd');

            case 'tapAndHold':
            case 'doubleTap':
                return has(x);

            case 'tap':
                return has('tap')
                    || has('doubleTap')
                    || has('tapAndHold');
        }

        return false;
    };

    touch.prototype.cancel = function(mouseDown) {

        var t = this;

        t.taps = 0;
        t.inTap = false;
        t.inTapAndHold = false;
        t.inDrag = false;
        t.tapStart = null;
        t.dragStart = null;
        t.xStart = null;
        t.yStart = null;
        t.exStart = null;
        t.eyStart = null;

        if (mouseDown) {
            t.mouseDown = false;
        }
    };

    touch.prototype.doStart = function(event, x, y) {

        var t = this,
            offset = t.$element.offset();

        event.stopPropagation();

        if (
            (t.uses('drag') && t.settings.preventDefault.drag(t)) ||
            (t.uses('swipe') && t.settings.preventDefault.swipe(t)) ||
            (t.uses('tap') && t.settings.preventDefault.tap(t))
        ) {
            event.preventDefault();
        }

        if (t.uses('tapAndHold')) {
            t.$element
                .css('-webkit-tap-highlight-color', 'rgba(0,0,0,0)')
                .css('-webkit-touch-callout', 'none')
                .css('-webkit-user-select', 'none');
        }

        t.x = x;
        t.y = y;
        t.ex = x - offset.left;
        t.ey = y - offset.top;
        t.tapStart = Date.now();

        clearTimeout(t.timerTap);

        t.timerTap = setTimeout(function() {

            if (t.inTap && t.taps > 0) {

                t.$element.trigger(
                    t.taps == 2 ? 'doubleTap' : 'tap',
                    {
                        taps: t.taps,
                        x: t.x,
                        y: t.y,
                        ex: t.ex,
                        ey: t.ey,
                        duration: Date.now() - t.tapStart,
                        event: event
                    }
                );

                t.cancel();
            }

            t.timerTap = null;

        }, t.settings.tapDelay);

        if (t.uses('tapAndHold')) {

            clearTimeout(t.timerTapAndHold);

            t.timerTapAndHold = setTimeout(function() {

                if (t.inTap) {

                    t.$element.trigger(
                        'tapAndHold',
                        {
                            x: t.x,
                            y: t.y,
                            ex: t.ex,
                            ey: t.ey,
                            duration: Date.now() - t.tapStart,
                            event: event
                        }
                    );

                    t.cancel();
                }

                t.timerTapAndHold = null;
                t.inTapAndHold = true;

            }, t.settings.tapAndHoldDelay);
        }

        t.inTap = true;
    };

    touch.prototype.doMove = function(event, x, y) {

        var t = this,
            offset = t.$element.offset(),
            diff = (Math.abs(t.x - x) + Math.abs(t.y - y)) / 2,
            e,
            s;

        event.stopPropagation();

        if (
            (t.uses('swipe') && t.settings.preventDefault.swipe(t)) ||
            (t.uses('drag') && t.settings.preventDefault.drag(t))
        ) {
            event.preventDefault();
        }

        if (diff > 2) {
            clearTimeout(t.timerTapAndHold);
        }

        if (t.inDrag && dragTarget == t) {

            t.$element.trigger(
                'drag',
                {
                    x: x,
                    y: y,
                    ex: x - offset.left,
                    ey: y - offset.top,
                    start: {
                        x: t.xStart,
                        y: t.yStart,
                        ex: t.exStart,
                        ey: t.eyStart
                    },
                    event: event,
                    exStart: t.exStart,
                    eyStart: t.eyStart
                }
            );

            t.$element.css('pointer-events', 'none');

            if (t.$element.css('position') == 'fixed') {
                e = document.elementFromPoint(
                    x - $document.scrollLeft(),
                    y - $document.scrollTop()
                );
            } else {
                e = document.elementFromPoint(x, y);
            }

            t.$element.css('pointer-events', '');

            if (e) {

                if (t.settings.dropFilter !== false) {

                    s = typeof t.settings.dropFilter;

                    switch (s) {

                        case 'string':

                            if (t.settings.dropFilterTraversal) {

                                while (e) {
                                    if ($(e).is(t.settings.dropFilter)) {
                                        break;
                                    }

                                    e = e.parentElement;
                                }

                            } else if (!$(e).is(t.settings.dropFilter)) {
                                e = null;
                            }

                            break;

                        case 'function':

                            if (t.settings.dropFilterTraversal) {

                                while (e) {
                                    if (t.settings.dropFilter(t.$element[0], e) === true) {
                                        break;
                                    }

                                    e = e.parentElement;
                                }

                            } else if (t.settings.dropFilter(t.$element[0], e) === false) {
                                e = null;
                            }

                            break;

                        default:
                        case 'boolean':

                            if (t.settings.dropFilter === true) {

                                while (e.parentElement != t.$element[0].parentElement) {

                                    e = e.parentElement;

                                    if (!e) {
                                        e = null;
                                        break;
                                    }
                                }
                            }

                            break;
                    }
                }

                if (e === t.$element[0]) {
                    e = null;
                }
            }

            if (dropTargetElement && dropTargetElement !== e) {

                t.$element.trigger(
                    'dragLeave',
                    {
                        element: dropTargetElement,
                        event: event
                    }
                );

                dropTargetElement = null;
            }

            if (!dropTargetElement && e) {

                dropTargetElement = e;

                t.$element.trigger(
                    'dragEnter',
                    {
                        element: dropTargetElement,
                        event: event
                    }
                );
            }

            if (dropTargetElement) {

                offset = $(dropTargetElement).offset();

                t.$element.trigger(
                    'dragOver',
                    {
                        element: dropTargetElement,
                        event: event,
                        x: x,
                        y: y,
                        ex: x - offset.left,
                        ey: y - offset.top
                    }
                );
            }

        } else if (diff > t.settings.dragThreshold) {

            if (Date.now() - t.tapStart < t.settings.dragDelay) {
                t.cancel();
                return;
            }

            t.cancel();

            t.inDrag = true;
            t.dragStart = Date.now();
            t.xStart = x;
            t.yStart = y;
            t.exStart = x - offset.left;
            t.eyStart = y - offset.top;

            if (t.uses('drag') && t.settings.preventDefault.drag(t)) {
                event.preventDefault();
            }

            t.$element.trigger(
                'dragStart',
                {
                    x: t.xStart,
                    y: t.yStart,
                    ex: t.exStart,
                    ey: t.eyStart,
                    event: event
                }
            );

            dragTarget = t;
        }
    };

    touch.prototype.doEnd = function(event, x, y) {

        var t = this,
            offset = t.$element.offset(),
            dx = Math.abs(t.x - x),
            dy = Math.abs(t.y - y),
            distance,
            velocity,
            duration;

        event.stopPropagation();

        if (t.inTap) {

            clearTimeout(t.timerTapAndHold);
            t.taps++;

            if (
                !t.timerTap ||
                (t.taps == 1 && !t.uses('doubleTap')) ||
                (t.taps == 2 && t.uses('doubleTap'))
            ) {

                t.$element.trigger(
                    t.taps == 2 ? 'doubleTap' : 'tap',
                    {
                        taps: t.taps,
                        x: t.x,
                        y: t.y,
                        ex: t.ex,
                        ey: t.ey,
                        duration: Date.now() - t.tapStart,
                        event: event
                    }
                );

                t.cancel();
            }

        } else if (t.inDrag) {

            if (dropTargetElement) {

                offset = $(dropTargetElement).offset();

                t.$element.trigger(
                    'drop',
                    {
                        element: dropTargetElement,
                        event: event,
                        x: x,
                        y: y,
                        ex: x - offset.left,
                        ey: y - offset.top
                    }
                );

                dropTargetElement = null;
            }

            duration = Date.now() - t.dragStart;
            distance = Math.sqrt(
                Math.pow(Math.abs(t.x - x), 2) +
                Math.pow(Math.abs(t.y - y), 2)
            );
            velocity = distance / duration;

            t.$element.trigger(
                'dragEnd',
                {
                    start: {
                        x: t.x,
                        y: t.y,
                        ex: t.ex,
                        ey: t.ey
                    },
                    end: {
                        x: x,
                        y: y,
                        ex: x - offset.left,
                        ey: y - offset.top
                    },
                    distance: distance,
                    duration: duration,
                    velocity: velocity,
                    event: event
                }
            );

            dragTarget = null;

            if (dx > t.settings.swipeThreshold || dy > t.settings.swipeThreshold) {

                t.$element.trigger(
                    'swipe',
                    {
                        distance: distance,
                        duration: duration,
                        velocity: velocity,
                        event: event
                    }
                );

                if (dx > dy) {

                    velocity = dx / duration;

                    if (x < t.x) {
                        t.$element.trigger(
                            'swipeLeft',
                            {
                                distance: dx,
                                duration: duration,
                                velocity: velocity,
                                event: event
                            }
                        );
                    } else {
                        t.$element.trigger(
                            'swipeRight',
                            {
                                distance: dx,
                                duration: duration,
                                velocity: velocity,
                                event: event
                            }
                        );
                    }

                } else if (dy > dx) {

                    velocity = dy / duration;

                    if (y < t.y) {
                        t.$element.trigger(
                            'swipeUp',
                            {
                                distance: dy,
                                duration: duration,
                                velocity: velocity,
                                event: event
                            }
                        );
                    } else {
                        t.$element.trigger(
                            'swipeDown',
                            {
                                distance: dy,
                                duration: duration,
                                velocity: velocity,
                                event: event
                            }
                        );
                    }
                }
            }

            t.inDrag = false;

        } else if (t.inTapAndHold) {

            clearTimeout(t.timerTapAndHold);

            t.$element.trigger(
                'tapAndHoldEnd',
                {
                    x: t.x,
                    y: t.y,
                    event: event
                }
            );

            t.inTapAndHold = false;
        }
    };

    $.fn.touch = function(userSettings) {

        var $this = $(this);

        if (this.length > 1) {

            for (var i = 0; i < this.length; i++) {
                $.touch($(this[i]), userSettings);
            }

        } else if (this.length == 1) {
            $.touch($this, userSettings);
        }

        return $this;
    };

    $.fn.enableTouch = function(userSettings) {
        return $(this).touch(userSettings);
    };

    $.touch = function($this, userSettings) {

        var settings = {};

        settings = $.extend(settings, defaultSettings);
        settings = $.extend(settings, userSettings);

        if (typeof settings.preventDefault.drag != 'function') {
            settings.preventDefault.drag = settings.preventDefault.drag === true
                ? function() { return true; }
                : function() { return false; };
        }

        if (typeof settings.preventDefault.swipe != 'function') {
            settings.preventDefault.swipe = settings.preventDefault.swipe === true
                ? function() { return true; }
                : function() { return false; };
        }

        if (typeof settings.preventDefault.tap != 'function') {
            settings.preventDefault.tap = settings.preventDefault.tap === true
                ? function() { return true; }
                : function() { return false; };
        }

        if (settings.noClick) {
            $this.on('click', function(event) {
                event.preventDefault();
            });
        }

        if (settings.useTouch) {

            var onTouchStart = function(event) {

                var $element = $(this),
                    touch = getTouch($element, $this, settings);

                touch.started = true;

                touch.doStart(
                    event,
                    event.originalEvent.touches[0][settings.coordinates + 'X'],
                    event.originalEvent.touches[0][settings.coordinates + 'Y']
                );

                setTimeout(function() {
                    touch.started = false;
                }, 1000);
            };

            $this.on('touchstart', onTouchStart);

            if (settings.delegateSelector) {
                $this.on('touchstart', settings.delegateSelector, onTouchStart);
            }

            var onTouchMove = function(event) {

                var $element = $(this),
                    touch = getTouch($element, $this, settings),
                    x = event.originalEvent.touches[0][settings.coordinates + 'X'],
                    y = event.originalEvent.touches[0][settings.coordinates + 'Y'];

                if (
                    touch.settings.trackDocument &&
                    touch.settings.trackDocumentNormalize
                ) {
                    var pos = fixPos(touch, x, y);
                    x = pos.x;
                    y = pos.y;
                }

                touch.doMove(event, x, y);
            };

            $this.on('touchmove', onTouchMove);

            if (settings.delegateSelector) {
                $this.on('touchmove', settings.delegateSelector, onTouchMove);
            }

            var onTouchEnd = function(event) {

                var $element = $(this),
                    touch = getTouch($element, $this, settings);

                touch.ended = true;

                var pos = fixPos(
                    touch,
                    event.originalEvent.changedTouches[0][settings.coordinates + 'X'],
                    event.originalEvent.changedTouches[0][settings.coordinates + 'Y']
                );

                touch.doEnd(event, pos.x, pos.y);

                setTimeout(function() {
                    touch.ended = false;
                }, 1000);
            };

            $this.on('touchend', onTouchEnd);

            if (settings.delegateSelector) {
                $this.on('touchend', settings.delegateSelector, onTouchEnd);
            }
        }

        if (settings.useMouse) {

            var onMouseDown = function(event) {

                var $element = $(this),
                    touch = getTouch($element, $this, settings);

                if (touch.started) {
                    return false;
                }

                touch.mouseDown = true;

                touch.doStart(
                    event,
                    event[settings.coordinates + 'X'],
                    event[settings.coordinates + 'Y']
                );
            };

            $this.on('mousedown', onMouseDown);

            if (settings.delegateSelector) {
                $this.on('mousedown', settings.delegateSelector, onMouseDown);
            }

            var onMouseMove = function(event) {

                var $element = $(this),
                    touch = getTouch($element, $this, settings);

                if (touch.mouseDown) {
                    touch.doMove(
                        event,
                        event[settings.coordinates + 'X'],
                        event[settings.coordinates + 'Y']
                    );
                }
            };

            $this.on('mousemove', onMouseMove);

            if (settings.delegateSelector) {
                $this.on('mousemove', settings.delegateSelector, onMouseMove);
            }

            var onMouseUp = function(event) {

                var $element = $(this),
                    touch = getTouch($element, $this, settings);

                if (touch.ended) {
                    return false;
                }

                $document.triggerHandler('mouseup', event);

                touch.doEnd(
                    event,
                    event[settings.coordinates + 'X'],
                    event[settings.coordinates + 'Y']
                );

                touch.mouseDown = false;
            };

            $this.on('mouseup', onMouseUp);

            if (settings.delegateSelector) {
                $this.on('mouseup', settings.delegateSelector, onMouseUp);
            }
        }

        if (!settings.trackDocument) {

            $this.on('mouseleave', function(event) {

                var $element = $(this),
                    touch = getTouch($element, $this, settings);

                touch.doEnd(
                    event,
                    event[settings.coordinates + 'X'],
                    event[settings.coordinates + 'Y']
                );

                touch.mouseDown = false;
            });
        }
    };

    function getTouch($element, $sourceElement, userSettings) {

        var element = $element[0];

        if (typeof element._touch == 'undefined') {
            element._touch = new touch($element, $sourceElement, userSettings);
        }

        return element._touch;
    }

    function fixPos(t, x, y) {

        var offset = t.$element.offset(),
            width = t.$element.width(),
            height = t.$element.height(),
            nx = Math.min(Math.max(x, offset.left), offset.left + width),
            ny = Math.min(Math.max(y, offset.top), offset.top + height);

        return {
            x: nx,
            y: ny
        };
    }

    $document
        .on('mousemove', function(event) {

            var t = dragTarget;

            if (
                t &&
                t.settings.useMouse &&
                t.mouseDown &&
                t.settings.trackDocument
            ) {

                var x = event[t.settings.coordinates + 'X'],
                    y = event[t.settings.coordinates + 'Y'];

                if (t.settings.trackDocumentNormalize) {
                    var pos = fixPos(t, x, y);
                    x = pos.x;
                    y = pos.y;
                }

                t.doMove(event, x, y);
            }
        })
        .on('mouseup', function(event, previousEvent) {

            var t = dragTarget;

            if (
                t &&
                t.settings.useMouse &&
                t.settings.trackDocument
            ) {

                if (typeof previousEvent !== 'undefined') {
                    event = previousEvent;
                }

                if (!((t.settings.coordinates + 'X') in event)) {
                    return;
                }

                var x = event[t.settings.coordinates + 'X'],
                    y = event[t.settings.coordinates + 'Y'];

                if (t.settings.trackDocumentNormalize) {
                    var pos = fixPos(t, x, y);
                    x = pos.x;
                    y = pos.y;
                }

                t.doEnd(event, x, y);
                t.mouseDown = false;
            }
        });

})(jQuery);