/**
 * THE PICTURE WINDOW for pictures one only looks at (includes/figure_window.php
 * prints it): a book's figures and portraits in Bestimmung, and since
 * 02.11.2026 the older pictures of the picture archives in the Fundbrowser's
 * „Bildarchiv". It moved out of the identification module's script that day, so the two pages share one
 * window instead of a copy that drifts. What stands under a picture is the
 * caller's to say (`caption`); the window does the rest.
 *
 * A picture is {files: [url], thumb?, original?, photo?, …}: the first file
 * is shown large and the original follows it (followOriginal), the thumb
 * (or the file) stands in the strip.
 *
 * PAPER FOR FIGURES, THE THEME FOR PHOTOGRAPHS (06.11.2026: „der
 * Hintergrund ist weiß … bitte ins Theme"). A book's figure is drawn on
 * white and keeps its paper; a photograph (`photo: true` — the picture
 * archives' older pictures) has no paper, and white around it only glares.
 * The window wears `is-photo` while one is shown, the strip marks each.
 *
 * AND A STAGE OF THEIR OWN (13.11.2026): a set of photographs
 * opens the window as the photo stage — the whole window, black, the strip
 * along the bottom and two arrows on the picture, which show while the
 * mouse moves (`is-awake`) and go again two seconds after it rests; over
 * the strip or an arrow they stay. The line about the picture comes and
 * goes with them; at rest only the picture and the bar remain. A finger
 * wakes them with a touch. Of the
 * zoom only „1:1" and „eingepasst" stand there, in the bar at the top.
 *
 * WITH ITS NEIGHBOURS (17.10.2026: „falls es mehrere Bilder gibt,
 * eine kleine Vorschauleiste links, wo man zwischen den einzelnen Bildern
 * umschalten kann"): the pictures that belong together stand as small ones on
 * the left; a click or the arrow keys switch.
 *
 * ZOOM IN THE PICTURE WINDOW (18.10.2026), the photo window's
 * module: wheel and touchpad, two fingers, dragging, a double click for whole
 * or threefold — and the footer's buttons for whoever reaches for a button.
 * Scale 1 of the module is the picture FITTED into the window; the footer's
 * number speaks in the picture's own pixels instead, because that is the
 * question one has („ob du die Bilder eins zu eins anzeigst") — fitted, a
 * book page stands at perhaps 40 %. A small picture the window already
 * stretches past its pixels cannot go down to 100 % (the module never
 * shrinks below fitted), so „Originalgröße" rests there.
 */

import * as imageZoom from './imagezoom.js';

const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/**
 * Wire the window up once per page.
 *
 * @param {HTMLDialogElement} dialog the window figure_window_render() printed
 * @param {{caption: (picture: object) => string, exportPicture?: (picture: object) => void}} host
 *        the line under a picture, as HTML; and where the page can export a photograph
 *        (archiveexport.js), the photo stage's „Exportieren" hands it the picture shown
 * @returns {{open: (pictures: object[], at?: number) => void}}
 */
export function mountFigureWindow(dialog, host) {
    const strip = dialog.querySelector('#keys-figure-strip');
    const image = dialog.querySelector('#keys-figure-image');
    const caption = dialog.querySelector('#keys-figure-caption');
    const scaleShown = dialog.querySelector('#keys-figure-scale');
    let set = [];
    let at = 0;

    const zoom = imageZoom.bind(dialog.querySelector('#keys-figure-frame'), image, { changed: showScale });

    /** How many screen pixels one picture pixel takes while fitted (0 before it loaded). */
    function fit() {
        const { naturalWidth: w, naturalHeight: h, offsetWidth: bw, offsetHeight: bh } = image;
        return w && h ? Math.min(bw / w, bh / h) : 0;
    }

    function showScale() {
        const f = fit();
        scaleShown.textContent = f ? `${Math.round(f * zoom.stufe() * 100)} %` : '';
        // The footer's buttons and the photo stage's bar carry the same data-zoom — all of them follow.
        const off = (how, disabled) => { for (const b of dialog.querySelectorAll(`[data-zoom="${how}"]`)) b.disabled = disabled; };
        off('original', !f || f >= 1 || 1 / f > zoom.maxScale());
        off('in', zoom.stufe() >= zoom.maxScale() - .001);
        off('out', zoom.stufe() <= 1.001);
        off('fit', zoom.stufe() <= 1.001);
    }
    image.addEventListener('load', showScale);

    dialog.addEventListener('click', e => {
        const step = e.target.closest('[data-figure-step]');
        if (step) { showAt(at + Number(step.dataset.figureStep)); return; }
        if (e.target.closest('[data-figure-export]')) { host.exportPicture?.(set[at]); return; }
        const b = e.target.closest('[data-zoom]');
        if (!b) return;
        const how = b.dataset.zoom;
        if (how === 'in') zoom.scaleBy(1.5);
        if (how === 'out') zoom.scaleBy(1 / 1.5);
        if (how === 'fit') zoom.reset();
        if (how === 'original' && fit()) zoom.scaleTo(1 / fit());
    });
    dialog.addEventListener('close', () => zoom.reset());

    /* A CLICK BESIDE THE WINDOW CLOSES IT (23.10.2026: „soll dieses
       Modal auch geschlossen werden, wenn man außerhalb klickt"). Outside is
       where the dialog element itself is hit — its form fills it, so that is
       the backdrop. Only where the press BEGAN there too: a drag that moves the
       zoomed picture and lets go beside the window ends in a click on the
       dialog as well, and must not close it. */
    let pressOutside = false;
    dialog.addEventListener('pointerdown', e => { pressOutside = e.target === dialog; });
    dialog.addEventListener('click', e => {
        if (e.target === dialog && pressOutside) dialog.close();
        pressOutside = false;
    });

    /* THE ORIGINAL FOLLOWS THE PREVIEW (13.11.2026: „das, was
       der Viewer anzeigt, ist nicht das Original … da würde ich gleich das
       Original laden"). A picture with an `original` shows its preview at
       once — the archives' is pCloud's 2048 px rendering — and once it has
       stood half a second, the original is fetched behind it and takes its
       place the moment it is whole; one leafing through quickly fetches no
       original he does not look at. The picture's box does not change, so
       neither does the view: a zoom set on the preview stands on the
       original, and „1:1" now means the original's own pixels. */
    let follow = 0;
    function followOriginal(f, shown) {
        clearTimeout(follow);
        if (!f.original || f.original === f.files[0]) return;
        follow = setTimeout(() => {
            const full = new Image();
            full.addEventListener('load', () => {
                if (set[at] === f && image.src === shown) image.src = f.original;
            }, { once: true });
            full.src = f.original;
        }, 500);
    }
    dialog.addEventListener('close', () => clearTimeout(follow));

    function showAt(i) {
        at = (i + set.length) % set.length;
        const f = set[at];
        // Back to whole BEFORE the next picture comes, or it would open at the
        // previous one's magnification and place.
        zoom.reset();
        image.src = f.files[0];
        followOriginal(f, image.src);
        caption.innerHTML = host.caption(f);
        for (const b of strip.querySelectorAll('[data-figure-at]')) {
            b.classList.toggle('is-active', Number(b.dataset.figureAt) === at);
        }
        strip.querySelector('.is-active')?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    /* AWAKE WHILE THE MOUSE MOVES: the strip and the arrows come, and go two
       seconds after it rests — unless it rests on them, where one is about
       to click. A touch wakes them as well; there is no hovering finger. */
    let sleep = 0;
    function wake() {
        if (!dialog.classList.contains('is-photo')) return;
        dialog.classList.add('is-awake');
        clearTimeout(sleep);
        sleep = setTimeout(function rest() {
            if (dialog.querySelector('.keys-figure-strip:hover, .keys-figure-arrow:hover, .keys-figure-bar:hover, .keys-figure-caption a:hover')) {
                sleep = setTimeout(rest, 2000);
                return;
            }
            dialog.classList.remove('is-awake');
        }, 2000);
    }
    dialog.addEventListener('pointermove', e => { if (e.pointerType === 'mouse') wake(); });
    dialog.addEventListener('pointerdown', wake);
    dialog.addEventListener('close', () => { clearTimeout(sleep); dialog.classList.remove('is-awake'); });

    strip.addEventListener('click', e => {
        const b = e.target.closest('[data-figure-at]');
        if (b) showAt(Number(b.dataset.figureAt));
    });
    /* The zoom by wheel and fingers, Y fits (imagezoom.js) — the keys + − 0
       went on 11.11.2026: the right hand's, where the system of shortcuts.js
       keeps the left one free for the keys and the right one on the mouse. */
    dialog.addEventListener('keydown', e => {
        if (set.length < 2) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); showAt(at + 1); }
        if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); showAt(at - 1); }
    });

    return {
        /** Show `pictures`, the one at `start` large and all of them in the strip. */
        open(pictures, start = 0) {
            set = pictures;
            // The stage is the set's, not one picture's: it must not jump while one leafs through.
            dialog.classList.toggle('is-photo', set.length > 0 && set.every(f => f.photo));
            dialog.classList.toggle('is-single', set.length < 2);
            dialog.querySelector('[data-figure-export]').hidden = !host.exportPicture || !dialog.classList.contains('is-photo');
            strip.hidden = set.length < 2;
            strip.innerHTML = set.length < 2 ? '' : set.map((f, i) =>
                `<button type="button" class="keys-figure-thumb${f.photo ? ' is-photo' : ''}" data-figure-at="${i}"><img src="${esc(f.thumb ?? f.files[0])}" alt="" loading="lazy"></button>`).join('');
            showAt(Math.max(0, Math.min(start, set.length - 1)));
            if (!dialog.open) dialog.showModal();
            wake();
        },
    };
}
