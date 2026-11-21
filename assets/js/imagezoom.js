/**
 * Into an image with two fingers.
 *
 * WHY NO CANVAS. The obvious route would be a <canvas> into which you paint
 * the image and redraw on every gesture. The gesture math — that is, the
 * actual work — is exactly the same as here; nothing would be gained, and
 * it would cost:
 *
 *   A canvas needs its buffer in real device pixels. On a phone with triple
 *   density that is, for a 2048 image, over 6000 px edge length, so a good
 *   150 MB — on the same device that is about to take the next photo.
 *
 *   And every zoom step would be a redraw on the main thread. A transform,
 *   by contrast, is pushed by the graphics card without a single pixel
 *   being recomputed.
 *
 *   Besides, an <img> remains an image: It can be long-pressed and saved,
 *   it has an alt text, and it loads progressively.
 *
 * POINTER EVENTS INSTEAD OF TOUCH EVENTS. pointer* applies to finger, mouse
 * and pen alike — the same math serves the phone in the forest and the
 * computer at the desk. touch-action: none is the precondition: Without
 * that declaration the browser takes the gesture for itself and pans the
 * page instead of handing it here.
 */

const MAX_SCALE = 8;

/**
 * HOW FAR TWO FINGERS MUST CHANGE THEIR DISTANCE before it counts as a
 * pinch and not as a two-finger pan.
 *
 * Without this every two-finger drag zoomed a little as well
 * (09.09.2026: „kann ich das Bild nicht verschieben, ohne dass dabei
 * gleichzeitig gezoomt wird"). And it must be so: two fingers never keep
 * their distance to the pixel, so distance/startDistance is never exactly
 * 1, and a magnification one did not ask for creeps through the whole
 * drag. One finger was always free of it — but on a picture one reaches
 * for with two.
 *
 * 14 px is the wobble of a hand, not the beginning of a pinch. Whoever
 * really pinches has passed it before the fingers have moved a centimetre.
 */
const PINCH_DEADZONE = 14;

/**
 * HOW FAR PAST ITS OWN EDGE THE PICTURE MAY BE PUSHED, as a share of the
 * shorter side of the frame.
 *
 * The bound used to be the picture's edge: it could be moved until its
 * border met the frame's, never further. That is tidy and it hides the
 * one thing one sometimes needs to see — a border of the picture lies
 * exactly under the scale bar or the aiming hint, both of which rest in
 * the corners, and there is no way to move it out from under them
 * (09.09.2026: „dass man außenrum um das Bild noch einen
 * schwarzen Rand hat … sodass man die Ränder des Bildes im sichtbaren
 * Bereich hat und dass dort dann nicht zufällig der Maßstab sitzt").
 *
 * A third of the shorter side clears any corner badge with room to spare.
 * The way back is one double tap, which fits the picture again — so the
 * slack can never strand anybody.
 */
const OVERPAN = 0.35;

/**
 * Attach zooming and panning to an image.
 *
 * @param {HTMLElement} frame  the area the image sits in
 * @param {HTMLImageElement} image
 * @param {object} [options]
 * @param {HTMLElement|HTMLElement[]} [options.mirror]  an element (or several) that receives the SAME
 *        transform on every change — the measuring overlay rides the zoom
 *        this way (photowindow.js, stage one of the measuring tool): laid exactly
 *        over the image's resting box and mirrored, its contents live in
 *        image coordinates without a line of conversion.
 * @param {() => number} [options.minScale]  how far below „fitted" (1) one may zoom out, asked
 *        each time — „Bearbeiten" lets the picture shrink so an ellipse far
 *        beyond its edge can be reached (07.11.2026); 1 everywhere else
 * @param {() => string} [options.tail]  a transform written AFTER the zoom's, on the image
 *        only — the microscope's webcam picture mirrored (microscope.js): last
 *        in the chain it turns the picture in place, so a drag still moves
 *        it the way the hand goes
 * @param {() => boolean} [options.active]  asked at every gesture; false leaves it to
 *        whatever else listens — the microscope's live picture while a
 *        calibration's still, with a zoom of its own, lies over it
 * @param {() => void} [options.changed]  called after every applied change
 *        — the scale bar recomputes its round length from stufe().
 * @returns {{reset: () => void, release: () => void, refresh: () => void}}
 */
export function bind(frame, image, options = {}) {
    const on = () => options.active?.() !== false;
    // The state: scale and translation in screen points, measured from the
    // center of the frame.
    let s = 1, tx = 0, ty = 0;
    // The floor, asked anew each time: below 1 only where the caller allows it.
    const floor = () => Math.min(1, Math.max(.05, Number(options.minScale?.()) || 1));

    // The fingers currently down, by their pointer id.
    const pointers = new Map();

    // The state at the start of a gesture. Everything is computed from here,
    // not carried forward step by step — otherwise rounding errors add up
    // over a long gesture into visible drifting.
    let start = null;

    function apply() {
        const transform = `translate(${tx}px, ${ty}px) scale(${s})`;
        const tail = options.tail?.() ?? '';
        image.style.transform = tail ? `${transform} ${tail}` : transform;
        for (const m of [].concat(options.mirror ?? [])) m.style.transform = transform;
        // Only claim the gesture in the magnified state. At scale 1 a swipe
        // should keep doing what it otherwise does.
        frame.classList.toggle('is-zoomed', s > 1.01);
        options.changed?.();
        if (!quiet) options.moved?.();
    }
    /* moved, unlike changed, only for what the viewer DID — a wheel, a drag,
       a button —, never for reset() or setView(): whoever remembers the
       view from it (the Fundbrowser, per picture) must not have every new
       picture's reset write 1 over the zoom it is about to get back. */
    let quiet = false;

    /** So the image cannot be pushed OUT of the frame — but far enough
     *  that its own edges can be brought INTO it. */
    function clamp() {
        const r = frame.getBoundingClientRect();

        // offsetWidth/offsetHeight, NOT getBoundingClientRect.
        //
        // The difference decides everything here: The box from
        // getBoundingClientRect contains the transform currently hanging on
        // the image — and at computation time that is still the OLD one,
        // because apply() only comes afterwards. Divide that by the NEW
        // scale and you get a size that never existed; on a jump from 1 to
        // 3 the bound came out as flat zero, and every translation was
        // immediately pressed back to the center. You zoomed, and the image
        // jumped away.
        //
        // offsetWidth is the size from the layout, untouched by transforms
        // — so exactly what is needed here.
        const b = image.offsetWidth;
        const h = image.offsetHeight;

        /* THE SLACK, AND ONLY WHILE MAGNIFIED. Fitted, the picture is
           whole and sits in the middle; there is nothing to move out from
           under anything, and the recentring further down would undo it
           in the same breath. Magnified, the slack is what lets a border
           come clear of the scale bar — see OVERPAN. */
        const slack = s > 1.001 ? Math.min(r.width, r.height) * OVERPAN : 0;

        const overX = Math.max(0, (b * s - r.width)  / 2) + slack;
        const overY = Math.max(0, (h * s - r.height) / 2) + slack;

        tx = Math.min(overX, Math.max(-overX, tx));
        ty = Math.min(overY, Math.max(-overY, ty));
    }

    /** A point on the screen, measured from the center of the frame. */
    function fromCenter(x, y) {
        const r = frame.getBoundingClientRect();
        return [x - (r.left + r.width / 2), y - (r.top + r.height / 2)];
    }

    /** Midpoint and distance of the fingers that are down. */
    function grip() {
        const points = [...pointers.values()];
        if (points.length === 1) {
            return { x: points[0].x, y: points[0].y, distance: 0 };
        }
        const [a, b] = points;
        return {
            x: (a.x + b.x) / 2,
            y: (a.y + b.y) / 2,
            distance: Math.hypot(a.x - b.x, a.y - b.y),
        };
    }

    function beginGesture() {
        const g = grip();
        /* pinching stays false until the dead zone is crossed — and it is
           reset with every new gesture, including the one that begins
           when a finger is lifted from two. */
        start = { ...g, s, tx, ty, pinching: false };
    }

    /**
     * Bind the pointer to the frame — or then again not.
     *
     * THE try IS NOT ORNAMENT. setPointerCapture throws when no pointer is
     * currently active for the id, and an error at this spot flies out of
     * the event handler BEFORE the gesture has even begun. Afterwards
     * nothing is broken and yet everything is dead: no zoom, no panning, no
     * message. The capture is a convenience — it makes sure a finger that
     * wanders over the edge keeps reporting to us. Without it the gesture
     * works, just not beyond the edge. That is the better failure.
     */
    function capturePointer(id, release = false) {
        try {
            release ? frame.releasePointerCapture?.(id) : frame.setPointerCapture?.(id);
        } catch { /* then without */ }
    }

    function onDown(e) {
        if (!on()) return;
        /* A press on a BUTTON is never a pan. Not cosmetics: capturing
           the pointer redirects the coming click onto the frame, and the
           button under the finger never hears it — the cutout offer's
           buttons (photowindow.js frameOffer) went dead exactly this way,
           measured 30.08.2026. The same for a FIELD over the picture
           (the box's size, photowindow.js boxPanelBuild): captured, it
           would never take the caret where the finger put it. */
        if (e.target.closest?.('button, input')) return;
        /* The MIDDLE button is panning too (29.08.2026), like
           in every drawing tool — the generic pointer logic below pans
           with any button already; what has to go is the browser's own
           middle-drag autoscroll, hence the preventDefault. */
        if (e.button === 1) e.preventDefault();
        // Only what begins on the image. A grab next to it should still be
        // able to close the window.
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        capturePointer(e.pointerId);
        beginGesture();
    }

    function onMove(e) {
        if (!pointers.has(e.pointerId) || !start) return;
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        const g = grip();

        /* TWO FINGERS ARE NOT AUTOMATICALLY A PINCH. Until they have
           really changed their distance (PINCH_DEADZONE), they are a pan
           and the scale stands still. */
        let newScale = start.s;
        if (pointers.size >= 2 && start.distance > 0) {
            if (!start.pinching
                && Math.abs(g.distance - start.distance) >= PINCH_DEADZONE) {
                start.pinching = true;
                /* RE-ANCHORED AT THE CROSSING, or the magnification would
                   jump by exactly the distance the dead zone had just
                   swallowed. From here the ratio counts from where the
                   pinch was recognised. */
                start.distance = g.distance;
            }
            if (start.pinching) {
                newScale = start.s * (g.distance / start.distance);
                newScale = Math.min(MAX_SCALE, Math.max(floor(), newScale));
            }
        }

        // THE POINT UNDER THE FINGERS MUST STAY PUT. Without this the image
        // always pulls toward the center while zooming, and you chase after
        // the spot you actually wanted to look at.
        //
        //   m = t + s · p     with p as place in the image, from its center
        //   hence  t_neu = m - (m - t_alt) · (s_neu / s_alt)
        const [mx0, my0] = fromCenter(start.x, start.y);
        const [mx1, my1] = fromCenter(g.x, g.y);
        const f = newScale / start.s;

        tx = mx0 - (mx0 - start.tx) * f + (mx1 - mx0);
        ty = my0 - (my0 - start.ty) * f + (my1 - my0);
        s  = newScale;

        // At scale 1 the image belongs in the center — otherwise it would
        // stand askew after zooming out. Smaller still, it stays there too.
        if (s <= 1.001) { if (s > .999) s = 1; tx = 0; ty = 0; }

        clamp();
        apply();
        e.preventDefault();
    }

    function onUp(e) {
        // Not ours — this hangs on the window and hears every finger in
        // the document.
        if (!pointers.has(e.pointerId)) return;
        pointers.delete(e.pointerId);
        capturePointer(e.pointerId, true);
        // Start over with the remaining finger: Whoever lifts one of two
        // wants to keep panning, not jump.
        if (pointers.size > 0) beginGesture();
        else start = null;
    }

    /** Double tap: switch between whole and threefold. */
    let lastTap = 0;
    function onTap(e) {
        if (!on()) return;
        // A double click into a field selects a word there; it zooms nothing.
        if (e.target.closest?.('input')) return;
        const now = e.timeStamp;
        const isDouble = now - lastTap < 320;
        lastTap = isDouble ? 0 : now;
        if (!isDouble) return;

        if (s > 1.01) {
            s = 1; tx = 0; ty = 0;
        } else {
            const [mx, my] = fromCenter(e.clientX, e.clientY);
            s = 3;
            // The same math as above, with t_alt = 0.
            tx = mx - mx * s;
            ty = my - my * s;
        }
        clamp();
        apply();
        e.preventDefault();
    }

    /**
     * On the computer the mouse wheel and the touchpad — the same math,
     * another trigger.
     *
     * THE STEP FOLLOWS THE MOVEMENT, NOT THE EVENT (04.10.2026,
     * on his MacBook: „extrem schnell, womit man überhaupt nicht dahin
     * kommt, wo man will"). It used to be 15 per cent per event: right for
     * a mouse wheel, which sends one event per notch — but a touchpad sends
     * dozens of small ones for the same stroke of two fingers, and a pinch
     * arrives as a stream of wheel events with ctrlKey set, so a gentle
     * gesture went straight to eightfold. Now each event zooms by its
     * deltaY: 2 to the power of the distance, the scale d3-zoom has worn
     * on every trackpad for years — a pixel of scrolling is worth 0.002, a
     * pixel of pinching ten times that, a line (Firefox's mouse wheel)
     * 0.05. A notch of a mouse (about 100) still makes some 15 per cent.
     */
    function onWheel(e) {
        if (!on()) return;
        if (!e.ctrlKey && Math.abs(e.deltaY) < 1) return;
        const [mx, my] = fromCenter(e.clientX, e.clientY);
        const previous = s;
        const unit = e.deltaMode === 1 ? 0.05 : e.deltaMode === 2 ? 1 : 0.002;
        // One event never more than doubles or halves — a mouse that
        // reports a whole page at once must not jump to the end.
        const step = Math.min(1, Math.max(-1, -e.deltaY * unit * (e.ctrlKey ? 10 : 1)));
        s = Math.min(MAX_SCALE, Math.max(floor(), s * 2 ** step));

        const f = s / previous;
        tx = mx - (mx - tx) * f;
        ty = my - (my - ty) * f;
        if (s <= 1.001) { if (s > .999) s = 1; tx = 0; ty = 0; }

        clamp();
        apply();
        e.preventDefault();
    }

    /* THE BROWSER'S OWN IMAGE DRAG MUST DIE HERE, and CSS cannot kill it
       everywhere: -webkit-user-drag: none (map.css) holds in Chromium and
       does NOT EXIST in Firefox — there a mouse drag on a zoomed image
       showed the grab hand and then dragged the PHOTO as a file instead
       of panning it (live, 28.08.2026; the pane's Chromium
       never reproduced it — a measurement only holds in the browser it
       was made in). dragstart is the one cross-browser gate. */
    const onDragStart = (e) => e.preventDefault();
    frame.addEventListener('dragstart', onDragStart);

    frame.addEventListener('pointerdown', onDown);
    frame.addEventListener('pointermove', onMove);

    /* THE LIFTING HANGS ON THE WINDOW, IN THE CAPTURE PHASE, and that is
     * the whole difference between a pan and a pan that zooms.
     *
     * WHAT HAPPENED (09.09.2026: „kann ich das Bild nicht
     * verschieben, ohne dass dabei gleichzeitig gezoomt wird … mit einem
     * Finger", after a few measures had been drawn). One finger cannot
     * zoom here — the scale hangs on the distance between two. Unless a
     * second one is still in the list. And one was.
     *
     * WHILE A MEASURE HANDLE IS BEING DRAGGED, EVERY LIFT IS SWALLOWED.
     * photowindow.js listens for pointerup on this same frame in the CAPTURE
     * phase and stops the event dead as long as its drag runs. Measured
     * on the spot: a capture listener that calls stopPropagation keeps
     * the bubble listener on the SAME element from ever running — with
     * the event's target a child of it AND with the frame itself as the
     * target.
     *
     * THE DRAGGING FINGER IS NOT THE ONE THAT GETS STUCK: photowindow.js stops
     * its pointerDOWN too, so this module never even sees it. It is any
     * OTHER finger that touches the picture while the drag runs — a palm,
     * the thumb of the holding hand, a second tap — and lifts before the
     * drag ends. That one this module HAS registered, its lift falls into
     * the same swallow, and it stays in the map for good. The NEXT
     * one-finger drag then counts two: distance changed, scale followed.
     *
     * Reproduced on 09.09.2026, both ways: with the old code that
     * sequence turned a plain one-finger drag into a jump from scale 1 to
     * 1.54, with this one the scale stands still.
     *
     * On the window and capturing, nothing inside can swallow it any
     * more. It hears every finger in the document, hence the guard at the
     * head of onUp. It also fixes the older, smaller case the line below
     * was written for: a finger lifted outside the frame when the pointer
     * capture did not take.
     *
     * An aborted pointer — phone call, notification, gesture taken over by
     * the operating system — comes through the same door. */
    window.addEventListener('pointerup', onUp, true);
    window.addEventListener('pointercancel', onUp, true);
    frame.addEventListener('click', onTap);
    frame.addEventListener('wheel', onWheel, { passive: false });

    /* Y FITS THE PICTURE INTO ITS FRAME — in every viewer
       (11.11.2026: „bei sämtlichen Bildbetrachtern, also überall, wo ich die
       Fotos sehe, also beim Messen und im Bearbeitungsmodus, soll mir über
       den Shortcut Y immer das Bild füllend angezeigt werden"). HERE, where
       every viewer's zoom is made — the photo window in both its modes, the
       key's figures, the calibration images, the microscope's still —, so
       none of them has to remember it and the next one has it unasked.

       ONLY THE VIEWER ONE SEES: several are bound on a page, most of them
       in windows that are closed; a frame that is not laid out stays
       silent. AND NEVER WHILE ONE TYPES — a „y" belongs to the field it is
       typed into, and a species name has its share of them. */
    const onKey = (e) => {
        if (e.key !== 'y' && e.key !== 'Y') return;
        if (e.metaKey || e.ctrlKey || e.altKey || e.defaultPrevented) return;
        /* Typing is a field that takes letters. A slider is a field too, and
           it keeps the focus after it was dragged — the key must work then,
           or it would fail exactly where one has just been working. */
        const at = e.target;
        if (at?.isContentEditable || /^(TEXTAREA|SELECT)$/.test(at?.tagName ?? '')
            || (at?.tagName === 'INPUT' && !/^(range|checkbox|radio|button|submit|reset|color|file)$/.test(at.type))) return;
        if (frame.checkVisibility ? !frame.checkVisibility() : frame.offsetParent === null) return;
        /* THE KEY IS OURS, AND THE BROWSER IS TOLD SO (11.11.2026:
           „bei mir schlägt Firefox noch eine Suche vor, wenn ich Y drücke …
           Y soll nur YASAIMAPP gehören"). Firefox's „Suchen beim Tippen"
           takes every letter typed outside a field for the start of a search
           on the page and opens its bar; a key whose default is prevented it
           leaves alone. Only where a viewer took it — elsewhere a y is the
           browser's as before. */
        e.preventDefault();
        /* HELD DOWN, THE KEY REPEATS — and each repeat is claimed too, only
           not acted on again (11.11.2026: „wenn ich länger auf Y
           bleibe, wird mir ein Sonderzeichen eingeblendet, ein Y mit so
           einem Strich drüber, und darauf springt die Firefox-Suche an").
           A Mac's press-and-hold offers the accented letters (ÿ) to whatever
           takes the repeats; let through unclaimed, they reached the input
           system, its menu opened, and Firefox's search took what it typed. */
        if (!e.repeat) zoomTo(1);
    };
    window.addEventListener('keydown', onKey);

    /**
     * Zoom from a control instead of from a gesture.
     *
     * AROUND THE CENTRE OF THE FRAME, because a button has no place under
     * it. A finger and the wheel keep the point beneath them still; a
     * button can only keep still what everybody is looking at, and that is
     * the middle.
     *
     * The same arithmetic as the wheel — written once more rather than
     * shared, because the wheel needs the pointer position and this one
     * must not have it. Squeezing both into one function would mean a
     * parameter that is meaningless in half the calls.
     */
    function zoomTo(target) {
        const previous = s;
        s = Math.min(MAX_SCALE, Math.max(floor(), target));

        const f = s / previous;
        tx *= f;
        ty *= f;
        if (s <= 1.001) { if (s > .999) s = 1; tx = 0; ty = 0; }

        clamp();
        apply();
    }

    return {
        /** Multiply the magnification — for a plus and a minus button. */
        scaleBy(factor) {
            zoomTo(s * factor);
        },
        /** Set it outright — for "original size". */
        scaleTo(target) {
            zoomTo(target);
        },
        /**
         * Move the magnified picture by screen points — for a caller that
         * pans without a drag (the photo window's edge, 13.11.2026). Kept
         * inside the same bounds as a drag; true while it still moved,
         * false once it stands at the bound.
         */
        panBy(dx, dy) {
            if (s <= 1.001) return false;
            const was = [tx, ty];
            tx += dx;
            ty += dy;
            clamp();
            if (tx === was[0] && ty === was[1]) return false;
            apply();
            return true;
        },
        /** Write the transform again — after what `tail` returns has changed. */
        refresh() {
            quiet = true;
            apply();
            quiet = false;
        },
        /** Magnification and shift in screen points from the frame's middle. */
        state() {
            return { s, tx, ty };
        },
        /** What it currently stands at. 1 means fitted into the frame. */
        stufe() {
            return s;
        },
        /** The ceiling, so a control can tell whether there is room left. */
        maxScale() {
            return MAX_SCALE;
        },
        /**
         * Magnification and place, to be handed back to setView() later —
         * the Fundbrowser keeps them per find (03.11.2026). The
         * place as a share of the magnified picture, not in screen points:
         * a window of another size must still show the same spot.
         */
        view() {
            const b = image.offsetWidth || 1, h = image.offsetHeight || 1;
            return { s, x: tx / (b * s), y: ty / (h * s) };
        },
        /** Stand at a view() again — once the picture has its layout size. */
        setView(v) {
            if (!v || !(v.s > 1.001)) return;
            s = Math.min(MAX_SCALE, v.s);
            tx = (Number(v.x) || 0) * image.offsetWidth * s;
            ty = (Number(v.y) || 0) * image.offsetHeight * s;
            pointers.clear();
            start = null;
            clamp();
            quiet = true;
            apply();
            quiet = false;
        },
        reset() {
            s = 1; tx = 0; ty = 0;
            pointers.clear();
            start = null;
            quiet = true;
            apply();
            quiet = false;
        },
        release() {
            frame.removeEventListener('dragstart', onDragStart);
            frame.removeEventListener('pointerdown', onDown);
            frame.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp, true);
            window.removeEventListener('pointercancel', onUp, true);
            frame.removeEventListener('click', onTap);
            frame.removeEventListener('wheel', onWheel);
            window.removeEventListener('keydown', onKey);
        },
    };
}
