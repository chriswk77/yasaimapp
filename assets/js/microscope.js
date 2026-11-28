/**
 * Mikroskopieren — the microscope camera's live picture in a window, its
 * stills whole into the find's cloud folder, and the objective that gives
 * every still its scale (24.10.2026).
 *
 * THE CAMERA IS NO WEBCAM. A BMS U3CMOS10000KPA speaks its maker's own
 * protocol, not UVC, so getUserMedia never sees it. The MicroCam app (its
 * own repository, ~/Documents/yasimapp-microcam, built by its own session)
 * drives it through the maker's SDK and serves it on 127.0.0.1:47811 of the
 * Mac it hangs on — a menu-bar app installed per Mac, because the maintainer
 * carries the camera between the iMac and the MacBook. This page therefore
 * talks to whichever Mac the browser runs on, never to our server. The
 * contract, from this side:
 *
 *   GET  /api/status          state, settings, ranges, still size — does
 *                             not switch the camera on
 *   GET  /api/stream.mjpg     the live picture, for an <img crossorigin>
 *   GET  /api/snapshot.jpg    one still at the sensor's full 3584 × 2746
 *   GET  /api/events          Server-Sent Events: stats ~4/s, dark, light,
 *                             settled, state — keeps the camera on while open
 *   POST /api/settings        a part of the settings → the new status
 *   POST /api/whitebalance    one white balance, once
 *
 * MicroCam answers only the origins it knows (wkpilz.de with and without
 * www, localhost and 127.0.0.1 on 80 and 8888); every other gets 403.
 *
 * THE STILL, NEVER A FRAME OF THE STREAM. A scale preset is µm per pixel of
 * one grid; the still always has the sensor's grid, the stream whatever
 * preview size is set. A frame cut out of the video would carry a third.
 *
 * FIREFOX ASKS FIRST. Since Local Network Access, a page on the internet
 * that calls 127.0.0.1 gets a one-time question per site, and until it is
 * answered the request neither fails nor finishes — it hangs. So the hint
 * comes after five seconds while the request goes on waiting: once Allow
 * is clicked, the same request carries on, and the picture comes without a
 * second click. A refusal on the other hand fails AT ONCE, exactly like a
 * MicroCam that is not running — one message has to cover both.
 */

import { t } from './i18n.js';
import { settings } from './settings.js';
import { confirm, prompt } from './dialogs.js';
import { startStack } from './focusstack.js';
import { icon, iconHtml } from './icons.js';
import { MEDIA, MEDIA_INFO, mediaAlphabetical } from './media.js';
import { scaleBarLength, scaleLabel, rectDims, valueLines } from './measurestats.js';
import * as imageZoom from './imagezoom.js';
import { readCamera, stampJpeg } from './exifstamp.js';
import { cpuCanvas, canvasJpeg } from './cpucanvas.js';

const MICROCAM = 'http://127.0.0.1:47811';
// How long a silent request may hang before the permission hint shows.
const QUESTION_AFTER = 5000;
// How often a moving slider reaches MicroCam (sendSoon).
const SEND_EVERY = 80;
// A still takes about two seconds at MicroCam; this is the patience for a
// camera that was just opened and still has to start up.
const STILL_PATIENCE = 30000;
// After „light", the stable state normally follows within a second or two.
// Should it not come — a scene that never settles —, the question comes
// anyway.
const SETTLED_PATIENCE = 4000;
/* HOW BLACK A TURN OF THE REVOLVER IS, in mean luma 0–255
   (04.11.2026: „der Objektivwechselerkenner … doch etwas zu fein").
   Measured that morning at his microscope with the lamp up: four real
   turns read 13.3–13.6 — the sensor's own black, the light path shut —
   while the false ones read 33–37: a picture that was overexposed (254
   at the shortest exposure) dipping when auto exposure swung back, and a
   shadow. The camera's own rule (a fifth of the light before) let both
   through, a fifth of 254 being 51. A dark phase only counts once it got
   below this line; above it the question stays away. */
const TURN_BLACK = 22;

/** The MicroCam address of a path. */
function camUrl(path) {
    return MICROCAM + path;
}

/**
 * A request to MicroCam that tells „hangs" from „refused".
 *
 * @param {string} path
 * @param {RequestInit} init
 * @param {{signal: AbortSignal, onSilent?: () => void}} opts  onSilent is
 *        called once when nothing has come back after QUESTION_AFTER — the
 *        request itself goes on waiting.
 */
async function camFetch(path, init, { signal, onSilent } = {}) {
    const timer = onSilent ? setTimeout(onSilent, QUESTION_AFTER) : null;
    try {
        return await fetch(camUrl(path), { ...init, signal, mode: 'cors', credentials: 'omit' });
    } finally {
        clearTimeout(timer);
    }
}

/**
 * Ask MicroCam how it stands — the window's first question, and the one
 * the tab Einstellungen → Fotos → Mikroskop-Kamera asks (microscopesettings.js).
 * One reading of the answers for both, so the two cannot tell a user two
 * different things about the same program.
 *
 * @returns {Promise<{kind: 'ok'|'refused'|'forbidden'|'error', status?: object}>}
 *          refused: nothing answers, or the browser was told no;
 *          forbidden: MicroCam answers, but not to this page
 */
export async function askMicroCam({ signal, onSilent } = {}) {
    let answer;
    try {
        answer = await camFetch('/api/status', {}, { signal, onSilent });
    } catch (e) {
        if (e.name === 'AbortError') throw e;
        return { kind: 'refused' };
    }
    if (answer.status === 403) return { kind: 'forbidden' };
    if (!answer.ok) return { kind: 'error' };
    try {
        const status = await answer.json();
        /* Remembered on the device: here a camera program has answered.
           The settings tab then asks by itself when it opens — and only
           then, because in Firefox the first question to 127.0.0.1 brings
           a permission prompt, which nobody without a microscope should
           meet just for looking at a tab. */
        settings.set('microscopeSeen', true);
        return { kind: 'ok', status };
    } catch {
        return { kind: 'error' };
    }
}

// ---------------------------------------------------------------------------
// The camera of THIS computer (24.10.2026)
//
// SET UP ONCE PER COMPUTER, in Einstellungen → Fotos → Mikroskop-Kamera, and
// remembered on the device, not on the account: the camera hangs on this
// computer's USB. The maintainer has MicroCam on the iMac and the MacBook and sets
// up each once; a phone or a stranger's computer never shows the button.
//
// TWO WAYS TO A CAMERA. A camera program on 127.0.0.1 (MicroCam and its
// kin, built from the guide), or — where the microscope camera is a plain
// webcam, as some are — the browser itself, with no program at all. For a
// layman the second is the easy one: plug in, allow the camera, done.
// ---------------------------------------------------------------------------

/**
 * How this computer reaches its microscope camera — 'program', 'webcam',
 * or null when it was never set up. A device that met MicroCam before the
 * setup existed (microscopeSeen, 24.10.2026) counts as set up with it.
 */
export function microscopeSource() {
    const source = settings.get('microscopeSource', null);
    if (source === 'program' || source === 'webcam') return source;
    return settings.get('microscopeSeen', false) === true ? 'program' : null;
}

/** The webcam chosen for the microscope: {deviceId, label}, or null. */
export function microscopeWebcam() {
    const w = settings.get('microscopeWebcam', null);
    return w && typeof w.deviceId === 'string' && w.deviceId !== '' ? w : null;
}

/** Remember how this computer reaches its camera (the settings tab). */
export function setMicroscopeSource(source, webcam = null) {
    settings.set('microscopeSource', source);
    settings.set('microscopeWebcam', source === 'webcam' ? webcam : null);
}

/** „Auf diesem Computer vergessen" — the button goes until set up anew. */
export function forgetMicroscope() {
    settings.set('microscopeSource', null);
    settings.set('microscopeWebcam', null);
    settings.set('microscopeSeen', false);
}

/**
 * Open a webcam at the largest picture it offers.
 *
 * THE LARGEST, because a webcam's still IS a frame of its video here:
 * Firefox has no way to release a webcam's own photo (ImageCapture is
 * Chrome's). Asked for its largest mode every time, the camera gives the
 * same grid every time — and a scale preset holds for a grid. A camera
 * whose stills are larger than its video loses that difference on this
 * way; the guide sends such a one to a camera program.
 *
 * AND THEN THE SAME SIZE EVERY TIME (the MicroCam session's rule, 24.10.2026:
 * „request a fixed resolution and save that size"). The setup remembers
 * the size the largest mode came in, and every later opening asks for
 * exactly that — a browser left to choose again might pick another mode,
 * and a mode that crops differently would make every preset measure
 * wrong. Should the camera deliver something else all the same, the
 * window says so (connectWebcam).
 *
 * @param {?string} deviceId  null for whichever the browser picks first
 * @param {?{width: number, height: number}} size  the size set up, or null
 *        for the largest the camera has
 * @returns {Promise<MediaStream>}
 */
export function openWebcam(deviceId, size = null) {
    return navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
            ...(deviceId ? { deviceId: { exact: deviceId } } : {}),
            width: { ideal: size?.width > 0 ? size.width : 8192 },
            height: { ideal: size?.height > 0 ? size.height : 8192 },
        },
    });
}

/** Every camera the browser knows (labels only once a camera was allowed). */
export async function listWebcams() {
    const all = await navigator.mediaDevices.enumerateDevices();
    return all.filter((d) => d.kind === 'videoinput')
        .map((d, i) => ({ deviceId: d.deviceId, label: d.label || t('Kamera %d', i + 1) }));
}

/**
 * The revolver detector for a webcam — what MicroCam does in the program,
 * done in the browser on the live picture, with MicroCam's numbers (the
 * guide writes them down): the mean luma of a small copy four times a
 * second; dark below max(12, a fifth of the last calm value); light again
 * at max(24, three times the darkest); calm after three readings within
 * max(3, 8 %) of each other, or after five seconds. It starts as MicroCam
 * does, settling, and the first calm reading is marked initial.
 *
 * @param {HTMLVideoElement} video
 * @param {(event: string, data: object) => void} emit
 * @returns {() => void} stop
 */
function watchBrightness(video, emit) {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 48;
    const g = canvas.getContext('2d', { willReadFrequently: true });
    let mode = 'settling';
    let initial = true;
    let calm = null;
    let darkest = 255;
    let darkSince = 0;
    let settlingSince = performance.now();
    let readings = [];

    const tick = () => {
        if (video.readyState < 2) return;
        g.drawImage(video, 0, 0, canvas.width, canvas.height);
        const px = g.getImageData(0, 0, canvas.width, canvas.height).data;
        let sum = 0;
        for (let i = 0; i < px.length; i += 4) sum += 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
        const b = Math.round(10 * sum / (px.length / 4)) / 10;
        const now = performance.now();
        step(b, now);
        /* REPORTED AFTER THE DECISION, as MicroCam does: the reading that
           turns the picture dark already says dark. */
        emit('stats', { state: 'streaming', brightness: b, dark: mode === 'dark' });
    };

    const step = (b, now) => {
        if (mode === 'bright') {
            if (b < Math.max(12, 0.2 * calm)) {
                mode = 'dark';
                darkest = b;
                darkSince = now;
                emit('dark', { brightness: b });
            } else {
                calm = 0.8 * calm + 0.2 * b;
            }
        } else if (mode === 'dark') {
            darkest = Math.min(darkest, b);
            if (b >= Math.max(24, 3 * darkest)) {
                mode = 'settling';
                settlingSince = now;
                readings = [];
                emit('light', { brightness: b, darkMs: Math.round(now - darkSince) });
            }
        } else {
            if (b < 12) {
                if (initial) emit('dark', { brightness: b });
                mode = 'dark';
                darkest = b;
                darkSince = now;
                initial = false;
                return;
            }
            readings = [...readings, b].slice(-3);
            const mean = readings.reduce((a, x) => a + x, 0) / readings.length;
            const calmNow = readings.length === 3
                && Math.max(...readings) - Math.min(...readings) <= Math.max(3, 0.08 * mean);
            if (calmNow || now - settlingSince > 5000) {
                mode = 'bright';
                calm = b;
                emit('settled', { brightness: b, darkMs: darkSince ? Math.round(settlingSince - darkSince) : 0, initial });
                initial = false;
            }
        }
    };
    const timer = setInterval(tick, 250);
    return () => clearInterval(timer);
}

/** µs as a reader says it — „350 ms", „1,2 s", „0,5 ms". */
function readableExposure(us) {
    const ms = us / 1000;
    if (ms >= 1000) return t('%s s', (ms / 1000).toLocaleString('de-DE', { maximumFractionDigits: 1 }));
    return t('%s ms', ms.toLocaleString('de-DE', { maximumFractionDigits: ms < 10 ? 1 : 0 }));
}

/**
 * Mount the window on its button.
 *
 * @param {object} host
 * @param {HTMLButtonElement} host.button  „Mikroskopieren" in the Belege
 * @param {() => object[]} host.presets    the account's scale presets
 *        (start.photo_scales: id, name, um_per_px, width, device_id)
 * @param {() => object[]} host.devices    the account's devices, the empty
 *        ones included (start.scale_devices: id, name, migration 0183)
 * @param {(file: File, scale: ?object, report: (text: string) => void, media: ?string[]) => Promise<void>} host.save
 *        asks what the still shows and starts its upload; resolves once the
 *        question is answered, the upload carries on behind it. media: the
 *        ticked keys of media.js, or null
 * @param {() => boolean} host.cloudReady  may the browser upload into a cloud
 * @param {(list: object[]) => void} host.setPresets  the presets after a
 *        calibration in the window, for every place that reads them
 * @param {() => boolean} host.microShrink  does a micrograph go up shrunk
 * @param {() => {line: string, seam: string}} host.lineColours  the
 *        account's measuring colours — a line in a picture wears them
 * @param {?object} host.areas  the page around the window, where it has
 *        the find's areas (the Fundbrowser; pages/finds.php, finds.js):
 *          inPage      true: the window is one of those areas — opened IN
 *                      the page with show(), its head not drawn
 *          bind(api)   the page takes { aside(), end() } to step the
 *                      window aside, and to end the camera when the find
 *                      is left
 *          ended()     the camera ended and the window closed for good
 *          show(area, pointId), keyOffered()   for a window that is NOT in
 *                      the page but stands over one with areas
 *        Null where the page has none (the map) — the window is the
 *        full-screen one, and steps aside for whatever lies beneath it
 * @param {() => {colour: string, seam: string, font: number}} host.scaleStyle
 *        the account's scale bar dress — the bar over the live picture
 *        wears it, as the photo window's does
 */
export function mountMicroscope({ button, presets, devices, setDevices, save, cloudReady, setPresets, microShrink, lineColours, scaleStyle,
                                  areas = null }) {
    const box = document.getElementById('microscope-window');
    if (!button || !box) return { open() {} };

    /* ONLY AT A DESK. The camera hangs on a computer's USB, and a phone or
       a tablet has none to ask — the button there could only ever say that
       it cannot work. A fine pointer that can hover is a computer (a
       trackpad counts); a finger alone is not.

       AND ONLY WHERE IT CAN WORK (24.10.2026: „Kann ja
       eigentlich erst erscheinen, wenn eine Cloud verbunden ist"): this
       computer set up with a camera, and a cloud the browser may upload
       into — without one a micrograph would land as a 1000-pixel copy on
       the server, worthless for measuring. Scale presets are NOT a
       condition: a picture without a scale is still a picture, and the
       window can calibrate. Whoever looks for the button finds the way to
       it in the settings tab. */
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return { open() {} };
    if (microscopeSource() === null || !cloudReady()) return { open() {} };
    button.hidden = false;

    const el = (id) => document.getElementById(id);
    const live      = el('microscope-live');
    const video     = el('microscope-video');
    const stillImg  = el('microscope-still');
    const lineCanvas = el('microscope-calibrate-canvas');
    const calFrame  = el('microscope-cal-frame');
    /* Zoomed and moved like a photo in the photo window — at 8× the
       micrometer's lines are thick enough to hit their middles. The line
       follows every step (drawLine). */
    const calZoom   = imageZoom.bind(calFrame, stillImg, { changed: () => drawLine() });
    const message   = el('microscope-message');
    const msgText   = el('microscope-message-text');
    const msgDetail = el('microscope-message-detail');
    const retry     = el('microscope-retry');
    const ask       = el('microscope-ask');
    const askChoices = el('microscope-ask-choices');
    const objectives = el('microscope-objectives');
    const deviceField = el('microscope-devices-field');
    const deviceButtons = el('microscope-devices');
    const manageButton = el('microscope-manage');
    const manageBox = el('microscope-manage-window');
    const manageList = el('microscope-manage-list');
    const manageError = el('microscope-manage-error');
    const flipH = el('microscope-flip-h');
    const flipV = el('microscope-flip-v');
    const keepSeries = el('microscope-keep-series');
    const noPresets = el('microscope-objectives-none');
    const light     = el('microscope-light');
    const mediaCommon = el('microscope-media');
    const mediaMore = el('microscope-media-more');
    const mediaToggle = el('microscope-media-toggle');
    // The same choice under „Welches Objektiv?" (openAsk).
    const askMedia = el('microscope-ask-media');
    const askMediaMore = el('microscope-ask-media-more');
    const askMediaToggle = el('microscope-ask-media-toggle');
    const mediumInfo = el('microscope-medium-info');
    const auto      = el('microscope-auto');
    const target    = el('microscope-target');
    const exposure  = el('microscope-exposure');
    const gain      = el('microscope-gain');
    const take      = el('microscope-take');
    const progress  = el('microscope-progress');

    /* Everything a session of the window holds — gone with the window. */
    let session = null;
    /* The camera's picture is in sight — no message stands over the stage (say). */
    let picturing = false;
    /* A calibration is running: the objective question keeps quiet — the
       slide is being changed for the micrometer, and that blacks out too. */
    let calibrating = false;
    /* Quick measuring's state is laid out further down (the quick
       measuring); setObjective and stop may run before it is — they clear
       it only once it stands. */
    let quickReady = false;

    /* THE OBJECTIVE IS THE DEVICE'S, not the account's: it is the position
       of the revolver on the microscope beside this computer. Kept like the
       base map (settings.js), so the next opening starts where the last
       one ended — the slide may have changed, the objective mostly not. */
    let objectiveId = settings.get('microscopeObjective', null);
    /* AND SO IS THE DEVICE (migration 0183): the camera hangs on ONE
       microscope, so this computer remembers which — the lab microscope
       at home, the travel one on the laptop — and offers its objectives
       only. Shown in the head from one device on (04.11.2026),
       so the window always says whose objectives these are. */
    let deviceId = settings.get('microscopeDevice', null);

    function deviceList() {
        return devices?.() ?? [];
    }

    /** The device the objectives belong to — null without any. */
    function currentDevice() {
        const list = deviceList();
        if (list.length === 0) return null;
        return list.find((d) => d.id === deviceId) ?? list[0];
    }

    function setDevice(id) {
        deviceId = id;
        settings.set('microscopeDevice', id);
        drawObjectives();
        queueScaleBar();
    }

    /** The devices in the head — the chosen one marked. While a
     *  calibration runs they rest: which device it is for is asked in the
     *  calibration itself, and a switch above it would change its answer
     *  behind its back. */
    function drawDevices() {
        const list = deviceList();
        deviceField.hidden = list.length === 0;
        manageButton.disabled = calibrating;
        deviceButtons.innerHTML = '';
        if (list.length === 0) return;
        const chosen = currentDevice();
        for (const d of list) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.textContent = d.name;
            b.classList.toggle('is-active', d.id === chosen.id);
            b.setAttribute('aria-pressed', String(d.id === chosen.id));
            b.disabled = calibrating;
            b.addEventListener('click', () => setDevice(d.id));
            deviceButtons.append(b);
        }
    }

    /**
     * The presets in revolver order — weakest objective first, which is
     * the largest µm per pixel. The account sorts them by name (4x, 10x,
     * 40x, 100x); here the number decides, so „Öl 100x" stands at the end
     * whatever it is called. Only the chosen device's: another
     * microscope's 40x would be another camera's pixels.
     */
    function revolver() {
        const device = currentDevice();
        return [...(presets() ?? [])]
            .filter((p) => p.um_per_px > 0 && (device === null || p.device_id === device.id))
            .sort((a, b) => b.um_per_px - a.um_per_px);
    }

    function currentPreset() {
        return revolver().find((p) => p.id === objectiveId) ?? null;
    }

    function setObjective(id) {
        // Quick measures are micrometres of the objective they were made in.
        if (id !== objectiveId) quickClear();
        objectiveId = id;
        settings.set('microscopeObjective', id);
        drawObjectives();
        queueScaleBar();
    }

    /* THE MEDIUM IS THE SLIDE'S, and the slide lies under THIS microscope —
       remembered by this computer like the objective, and standing until
       it is changed: one slide gives many photos. Whatever is ticked rides
       with every photo and every stack (save, below). */
    let mediaChosen = (settings.get('microscopeMedia', []) ?? []).filter((k) => MEDIA.some((m) => m.key === k));
    let mediaOpen = settings.get('microscopeMediaOpen', false) === true;
    /* THE QUESTION FOLDS FOR ITSELF (20.11.2026: „wenn ich
       aufklappe, klappt sich auch gleichzeitig das Medium in der Sidebar
       auf. Das ist zu viel"). The TICKS are one choice in two places; the
       fold is each place's own view of it. The question's is not
       remembered: it opens folded every time (openAsk) — one answers it in
       a moment, and a long list left open would stand over the picture at
       every change of objective. */
    let askMediaOpen = false;

    function currentMedia() {
        return mediaChosen.length > 0 ? [...mediaChosen] : null;
    }

    function toggleMedium(key) {
        mediaChosen = mediaChosen.includes(key) ? mediaChosen.filter((k) => k !== key) : [...mediaChosen, key];
        // In the list's order, so „H₂O + KOH 3 %" reads the same every time.
        mediaChosen = MEDIA.map((m) => m.key).filter((k) => mediaChosen.includes(k));
        settings.set('microscopeMedia', mediaChosen);
        drawMedia();
    }

    /** The common media in sight; the further ones under „Weitere" — a
     *  ticked one among them stays in sight while it is folded. */
    function drawMedia() {
        // Beside the picture, and under the objective's question — one choice, two places.
        drawMediaInto(mediaCommon, mediaMore, mediaToggle, mediaOpen, true);
        drawMediaInto(askMedia, askMediaMore, askMediaToggle, askMediaOpen, false);
    }
    /** `info`: the panel that explains a medium opens beside the sidebar —
     *  only for the sidebar's buttons; over the question it would lie on
     *  the picture one is reading the objective from. */
    function drawMediaInto(mediaCommon, mediaMore, mediaToggle, mediaOpen, info) {
        mediaCommon.innerHTML = '';
        mediaMore.innerHTML = '';
        // The common ones in their chemists' order, „Weitere" alphabetically (media.js, mediaAlphabetical).
        const ordered = [...MEDIA.filter((m) => m.common), ...MEDIA.filter((m) => !m.common).sort(mediaAlphabetical)];
        for (const m of ordered) {
            const on = mediaChosen.includes(m.key);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small microscope-medium' + (on ? ' is-on' : '');
            b.setAttribute('role', 'checkbox');
            b.setAttribute('aria-checked', String(on));
            /* No title: the panel says the full name and more, beside the
               sidebar (showMediumInfo); a browser tooltip would stand on
               top of it. */
            if (info) {
                b.setAttribute('aria-describedby', 'microscope-medium-info');
                b.addEventListener('mouseenter', () => showMediumInfo(m, b));
                b.addEventListener('focus', () => showMediumInfo(m, b));
                b.addEventListener('mouseleave', hideMediumInfo);
                b.addEventListener('blur', hideMediumInfo);
            } else {
                // No panel here: the full name as the plain tooltip.
                b.title = m.name();
            }
            const box = document.createElement('span');
            box.className = 'list-row-box';
            if (on) box.append(icon('check'));
            const word = document.createElement('span');
            word.textContent = m.short;
            b.append(box, word);
            b.addEventListener('click', () => toggleMedium(m.key));
            (m.common || (on && !mediaOpen) ? mediaCommon : mediaMore).append(b);
        }
        mediaMore.hidden = !mediaOpen;
        mediaToggle.textContent = mediaOpen ? t('Weniger') : t('Weitere');
        mediaToggle.setAttribute('aria-expanded', String(mediaOpen));
    }

    /** The panel for one medium: its full name, what it is for, then per
     *  group what it shows there, and what to take care of. */
    function showMediumInfo(m, anchor) {
        const info = MEDIA_INFO[m.key]?.() ?? {};
        mediumInfo.innerHTML = '';
        const head = document.createElement('h3');
        head.textContent = m.name();
        if (m.short !== m.name()) {
            const short = document.createElement('span');
            short.textContent = ' · ' + m.short;
            head.append(short);
        }
        mediumInfo.append(head);
        const groups = [['use', null], ['russula', 'Russula'], ['cortinarius', 'Cortinarius'],
            ['agaricus', 'Agaricus'], ['asco', t('Ascomyceten')], ['caution', t('Vorsicht')]];
        const any = groups.some(([key]) => info[key]?.length);
        for (const [key, label] of groups) {
            const lines = info[key] ?? [];
            if (lines.length === 0) continue;
            const block = document.createElement('div');
            if (key === 'caution') block.className = 'microscope-medium-caution';
            // „Allgemein" only where a group follows — alone it names nothing.
            const heading = label ?? (groups.some(([k]) => k !== 'use' && k !== 'caution' && info[k]?.length) ? t('Allgemein') : null);
            if (heading) {
                const h = document.createElement('h4');
                h.textContent = heading;
                block.append(h);
            }
            for (const line of lines) {
                const p = document.createElement('p');
                p.textContent = line;
                block.append(p);
            }
            mediumInfo.append(block);
        }
        if (!any) return;
        mediumInfo.hidden = false;
        // At the tick's height, but whole inside the window's body.
        const body = mediumInfo.parentElement.getBoundingClientRect();
        const at = anchor.getBoundingClientRect().top - body.top;
        mediumInfo.style.top = Math.max(0, Math.min(at, body.height - mediumInfo.offsetHeight)) + 'px';
    }

    function hideMediumInfo() {
        mediumInfo.hidden = true;
    }

    mediaToggle.addEventListener('click', () => {
        mediaOpen = !mediaOpen;
        settings.set('microscopeMediaOpen', mediaOpen);
        drawMedia();
    });
    askMediaToggle.addEventListener('click', () => {
        askMediaOpen = !askMediaOpen;
        drawMedia();
    });

    /** The objectives beside the picture — the chosen one marked. */
    function drawObjectives() {
        drawDevices();
        const list = revolver();
        objectives.innerHTML = '';
        noPresets.hidden = list.length > 0;
        objectives.hidden = list.length === 0;
        if (list.length === 0) return;
        /* „Ohne Maßstab" is a real answer: a picture taken for its colour,
           not to be measured. Without it a wrong objective would be the
           only way to take one. */
        for (const p of [...list, null]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.textContent = p ? p.name : t('Ohne Maßstab');
            const chosen = (p?.id ?? null) === (currentPreset()?.id ?? null);
            b.classList.toggle('is-active', chosen);
            b.setAttribute('aria-pressed', String(chosen));
            b.addEventListener('click', () => setObjective(p?.id ?? null));
            objectives.append(b);
        }
    }

    /* DEVICES AND OBJECTIVES, RENAMED AND DELETED IN ONE WINDOW behind the
       gear at the head's end (04.11.2026: „zusammen in ein
       Button … wo ich meine Geräte sehe, wo ich sie umbenennen kann, wo
       ich darunter unter jedem Gerät das jeweilige Objektiv sehe").
       Overwriting an objective is a calibration under its name, and making
       one is the calibration itself — so this window only renames and
       deletes. Deleting says first what will be lost; pictures keep their
       scale, the stamp carries its own numbers (photo_scale_forget). What
       goes wrong is said INSIDE the window, a line under the head — the
       progress line lies behind it. */

    async function act(action, id, name = undefined) {
        manageError.hidden = true;
        try {
            const { request } = await import('./api.js');
            const answer = await request('calibration.php?action=' + action,
                { method: 'POST', body: JSON.stringify({ id, name }) });
            setPresets(answer.scales ?? []);
            if (Array.isArray(answer.devices)) setDevices?.(answer.devices);
            return true;
        } catch (e) {
            manageError.textContent = e?.message || t('Das hat nicht geklappt.');
            manageError.hidden = false;
            return false;
        }
    }

    /** Ask for a new name; null when cancelled or unchanged. */
    async function askName(title, text, current) {
        const name = (await prompt({ title, text, value: current, button: t('Umbenennen') }))?.trim();
        return name && name !== current ? name : null;
    }

    /** A button with nothing but a symbol in it. */
    function symbolButton(symbol, label, danger, onClick) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = danger ? 'button danger small' : 'button subtle small';
        b.title = label;
        b.setAttribute('aria-label', label);
        b.innerHTML = iconHtml(symbol);
        b.addEventListener('click', onClick);
        return b;
    }

    /** After a change: the window anew, and the head and the sidebar. */
    function manageChanged() {
        drawManage();
        drawObjectives();
        queueScaleBar();
    }

    async function renameDevice(d) {
        const name = await askName(t('Gerät umbenennen'), t('Wie soll das Gerät heißen?'), d.name);
        if (name && await act('device_rename', d.id, name)) manageChanged();
    }

    async function forgetDevice(d) {
        const own = (presets() ?? []).filter((p) => p.device_id === d.id);
        const text = own.length === 0
            ? t('Das Gerät „%s" wird gelöscht.', d.name)
            : own.length === 1
                ? t('Das Gerät „%s" und sein Objektiv „%s" werden gelöscht. Fotos, die schon einen Maßstab davon bekommen haben, behalten ihn.', d.name, own[0].name)
                : t('Das Gerät „%s" und seine %d Objektive werden gelöscht. Fotos, die schon einen Maßstab davon bekommen haben, behalten ihn.', d.name, own.length);
        if (!await confirm({ title: t('„%s" löschen?', d.name), text, button: t('Löschen'), danger: true })) return;
        if (!await act('device_forget', d.id)) return;
        // The head falls back to the first device left, or to none.
        if (deviceId === d.id || !deviceList().some((x) => x.id === deviceId)) setDevice(deviceList()[0]?.id ?? null);
        if (own.some((p) => p.id === objectiveId)) setObjective(null);
        manageChanged();
    }

    async function renamePreset(p) {
        const name = await askName(t('Objektiv umbenennen'), t('Wie soll das Objektiv heißen?'), p.name);
        if (name && await act('preset_rename', p.id, name)) manageChanged();
    }

    async function forgetPreset(p, d) {
        if (!await confirm({
            title: t('„%s" löschen?', p.name),
            text: d
                ? t('Die Eichung von „%s" am Gerät „%s" wird gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.', p.name, d.name)
                : t('Die Eichung von „%s" wird gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.', p.name),
            button: t('Löschen'),
            danger: true,
        })) return;
        if (!await act('preset_forget', p.id)) return;
        if (objectiveId === p.id) setObjective(null);
        manageChanged();
    }

    /** One table per device: its name in the head with pencil and bucket,
     *  its objectives beneath in revolver order, each with its own. */
    function drawManage() {
        manageList.innerHTML = '';
        const list = deviceList();
        if (list.length === 0) {
            const none = document.createElement('p');
            none.className = 'hint-text small';
            none.textContent = t('Noch kein Gerät. Es entsteht beim ersten Eichen.');
            manageList.append(none);
            return;
        }
        const lang = document.documentElement.lang || 'de';
        for (const d of list) {
            const frame = document.createElement('div');
            frame.className = 'table-frame';
            const table = document.createElement('table');
            table.className = 'list';
            const head = table.createTHead().insertRow();
            for (const text of [d.name, t('Mikrometer je Pixel')]) {
                const th = document.createElement('th');
                th.textContent = text;
                head.append(th);
            }
            const tools = document.createElement('th');
            tools.className = 'button-cell';
            tools.append(symbolButton('pencil', t('Gerät umbenennen'), false, () => renameDevice(d)),
                symbolButton('delete', t('Gerät löschen'), true, () => forgetDevice(d)));
            head.append(tools);
            const body = table.createTBody();
            const own = (presets() ?? []).filter((p) => p.device_id === d.id)
                .sort((a, b) => b.um_per_px - a.um_per_px);
            for (const p of own) {
                const row = body.insertRow();
                row.insertCell().textContent = p.name;
                row.insertCell().textContent = p.um_per_px.toLocaleString(lang, { minimumFractionDigits: 4, maximumFractionDigits: 4 });
                const cell = row.insertCell();
                cell.className = 'button-cell';
                cell.append(symbolButton('pencil', t('Objektiv umbenennen'), false, () => renamePreset(p)),
                    symbolButton('delete', t('Objektiv löschen'), true, () => forgetPreset(p, d)));
            }
            if (own.length === 0) {
                const cell = body.insertRow().insertCell();
                cell.colSpan = 3;
                cell.className = 'hint-text small';
                cell.textContent = t('Noch keine Objektive für dieses Gerät.');
            }
            frame.append(table);
            manageList.append(frame);
        }
    }

    manageButton.addEventListener('click', () => {
        if (calibrating) return;
        manageError.hidden = true;
        drawFlips();
        drawManage();
        manageBox.showModal();
    });

    /* THE CALIBRATION'S STILL STANDS, whatever the camera does
       (03.11.2026: „wenn die Kamera in der Zeit dann abstürzt, kann ich noch
       nicht mal fertig eichen"). The line is drawn on a picture already
       taken, and saving needs no camera — so while the still is up, a
       camera message waits instead of covering it, and is shown once the
       calibration ends or a new photo is asked for. */
    let heldSay = null;
    const stillUp = () => calibrating && calFile !== null;

    /** The stage's message instead of the picture. null hides it. */
    function say(text, detail = '', canRetry = false) {
        if (stillUp()) {
            heldSay = text === null ? null : [text, detail, canRetry];
            return;
        }
        message.hidden = text === null;
        /* The window says nothing only while the picture runs (startLive,
           the webcam's play) — every trouble is a sentence. The dot on
           „Mikroskop" asks this, not the session (markLive). */
        picturing = text === null;
        markLive();
        msgText.textContent = text ?? '';
        msgDetail.textContent = detail;
        msgDetail.hidden = detail === '';
        retry.hidden = !canRetry;
        if (text !== null) {
            live.hidden = true;
            video.hidden = true;
            el('microscope-too-bright').hidden = true;
        }
        take.disabled = text !== null;
    }

    function report(text) {
        progress.textContent = text;
    }

    // --- The connection --------------------------------------------------------

    async function connect() {
        stop();
        /* last: the newest stats; answered: the question of this dark phase
           is settled; pending: settings not sent yet; sending: on the way;
           stream and unwatch: the webcam's, when it is one. */
        const s = { abort: new AbortController(), events: null, last: null,
                    answered: false, settleTimer: null, sendTimer: null, pending: null, sending: false,
                    ranges: null, still: null, stream: null, unwatch: null, recovering: false, turnBlack: null };
        session = s;
        say(t('Verbinde mit der Kamera …'));
        light.hidden = microscopeSource() === 'webcam';
        if (microscopeSource() === 'webcam') {
            connectWebcam(s);
            return;
        }

        let asked;
        try {
            asked = await askMicroCam({
                signal: s.abort.signal,
                onSilent: () => {
                    if (session !== s) return;
                    say(t('Der Browser fragt nach Zugriff auf Geräte in diesem Netzwerk – bitte Erlauben.'),
                        t('Die Frage steht oben links bei der Adressleiste. Sie kommt nur einmal. Danach erscheint das Bild von selbst.'));
                },
            });
        } catch {
            return;   // the window was closed
        }
        if (session !== s) return;
        if (asked.kind === 'refused') {
            say(t('Das Kamera-Programm antwortet nicht.'),
                t('Starte das Programm, das deine Kamera bereitstellt. Bei der BMS-Kamera am Mac ist das MicroCam, es steht dann oben in der Menüleiste. Falls der Browser den Zugriff auf Geräte in diesem Netzwerk abgelehnt hat: Klicke links in der Adressleiste auf das Schloss und erlaube ihn.'),
                true);
            return;
        }
        if (asked.kind === 'forbidden') {
            say(t('Das Kamera-Programm lässt diese Seite nicht zu.'),
                t('Es arbeitet nur mit wkpilz.de zusammen. Öffne YASAIMAPP dort.'), true);
            return;
        }
        if (asked.kind === 'error') {
            say(t('Das Kamera-Programm meldet einen Fehler.'), '', true);
            return;
        }
        const status = asked.status;
        s.ranges = status.ranges ?? null;
        s.still = status.still ?? null;
        liveZoomOn(live);
        // The camera's name for the pictures this window draws itself (stamped below).
        if (typeof status.model === 'string' && status.model !== '') s.camera = { model: status.model };
        drawLight(status.settings ?? {});
        listen(s);
        showState(s, status.state);
    }

    /**
     * What the camera's state means for the stage. The live picture is
     * asked for only once there is something to show — an <img> on a
     * camera that is not there would only turn into a broken frame.
     */
    function showState(s, state) {
        if (session !== s) return;
        if (state === 'absent') {
            // Unplugged by hand: whatever was turned meanwhile, it asks again.
            s.recovering = false;
            say(t('Die Kamera ist nicht angesteckt.'),
                t('Stecke die Mikroskop-Kamera an diesen Computer. Das Bild kommt dann von selbst.'));
        } else if (state === 'stalled') {
            say(t('Die Kamera ist belegt.'),
                t('Wahrscheinlich ist das Programm des Herstellers noch offen, bei BMS ist das BMS Pix 3. Schließe es, dann kommt das Bild.'));
        } else if (state === 'frozen') {
            /* HUNG UP, AND ONLY THE PLUG HEALS IT (03.11.2026, measured on
               the maintainer's M1 Pro): the camera froze on a weak USB port after
               frames had come. MicroCam's software replug resets the USB
               link but not the camera's power — afterwards it opened and
               never sent a picture again, which then read as „belegt". So
               MicroCam no longer tries by default, and the window does not
               promise that it comes back by itself. „frozen" stands until
               the camera leaves the bus; a stall after it is a freeze too,
               never „held by another program". */
            live.removeAttribute('src');
            say(t('Die Kamera ist hängen geblieben.'),
                t('Stecke sie ab, warte kurz und stecke sie wieder an. Das Bild kommt dann von selbst.'));
        } else if (state === 'reconnecting') {
            /* MicroCam reconnects by itself — where its software replug is
               switched on. The picture is dropped, so the stream is asked
               anew once it streams again (the old request may have died with
               the camera), and the revolver did not turn: the first stable
               picture after it is no reason to ask for the objective
               (settled, below). */
            s.recovering = true;
            live.removeAttribute('src');
            say(t('Die Kamera verbindet sich gerade neu.'),
                t('Das Bild kommt gleich von selbst wieder. Kommt es nicht, stecke die Kamera einmal ab und wieder an.'));
        } else if (state === 'error') {
            say(t('Das Kamera-Programm meldet einen Fehler.'),
                t('Stecke die Kamera einmal ab und wieder an.'), true);
        } else {
            startLive(s);
        }
    }

    function startLive(s) {
        if (session !== s) return;
        if (!live.getAttribute('src')) {
            live.onload = () => {
                if (session !== s) return;
                say(null);
                live.hidden = false;
            };
            live.onerror = () => {
                if (session !== s) return;
                live.removeAttribute('src');
                say(t('Die Verbindung zum Kamera-Programm ist abgerissen.'), '', true);
            };
            /* The rate the preview needs, not the camera's best: at 15 the
               picture follows the slide smoothly, and the Mac has room for
               the still beside it. */
            live.src = camUrl('/api/stream.mjpg?fps=15');
        } else if (live.complete && live.naturalWidth > 0) {
            say(null);
            live.hidden = false;
        }
    }

    /**
     * The camera's own news. EventSource reconnects by itself (MicroCam
     * asks for two seconds), so a MicroCam restarted under an open window
     * comes back without a click.
     */
    function listen(s) {
        const events = new EventSource(camUrl('/api/events'));
        s.events = events;
        const data = (e) => { try { return JSON.parse(e.data); } catch { return {}; } };
        const on = news(s);
        for (const name of ['stats', 'state', 'dark', 'light', 'settled']) {
            events.addEventListener(name, (e) => on(name, data(e)));
        }
    }

    /**
     * A webcam, straight in the browser. Its picture in the <video>, its
     * revolver detector here (watchBrightness), its still a frame of the
     * largest mode it has; no light controls — the browser does not hand a
     * webcam's exposure out, so the camera steers itself.
     */
    async function connectWebcam(s) {
        const chosen = microscopeWebcam();
        let stream;
        try {
            stream = await openWebcam(chosen?.deviceId ?? null, chosen?.width > 0 ? chosen : null);
        } catch (e) {
            if (session !== s) return;
            if (e.name === 'NotAllowedError') {
                say(t('Der Browser darf die Kamera nicht benutzen.'),
                    t('Klicke links in der Adressleiste auf das Kamera-Zeichen oder das Schloss und erlaube die Kamera.'), true);
            } else {
                say(t('Die Webcam ist nicht angesteckt.'),
                    t('Stecke die Mikroskop-Kamera an diesen Computer und versuche es erneut.'), true);
            }
            return;
        }
        if (session !== s) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }
        s.stream = stream;
        video.srcObject = stream;
        liveZoomOn(video);
        orientVideo();
        // A webcam's only name is the one the system gives it.
        const named = stream.getVideoTracks()[0]?.label || microscopeWebcam()?.label || '';
        if (named) s.camera = { model: named };
        video.onplaying = () => {
            if (session !== s) return;
            s.still = { width: video.videoWidth, height: video.videoHeight };
            /* Another size than the one set up: the presets were made in
               that one. Said, not refused — a picture is still a picture. */
            if (chosen?.width > 0 && (chosen.width !== video.videoWidth || chosen.height !== video.videoHeight)) {
                report(t('Die Webcam liefert %d × %d statt %d × %d Pixel. Deine Vorlagen messen damit vielleicht falsch – richte die Webcam neu ein.',
                    video.videoWidth, video.videoHeight, chosen.width, chosen.height));
            }
            say(null);
            video.hidden = false;
        };
        video.play().catch(() => {});
        const on = news(s);
        s.unwatch = watchBrightness(video, on);
        // Unplugged under an open window: the track ends.
        stream.getVideoTracks()[0]?.addEventListener('ended', () => {
            if (session === s) say(t('Die Webcam ist nicht angesteckt.'), '', true);
        });
    }

    /**
     * What the camera reports — from the program's event stream or from
     * the webcam's detector, the same five kinds of news.
     */
    function news(s) {
        return (name, d) => {
            if (session !== s) return;
            if (name === 'stats') onStats(s, d);
            else if (name === 'state') showState(s, d.state);
            else if (name === 'dark') onDark(s, d);
            else if (name === 'light') onLight(s);
            else if (name === 'settled') settled(s, d);
        };
    }

    function onStats(s, d) {
        s.last = d;
        /* Only the lamp can help here (MicroCam: anti-flicker keeps the
           exposure at 10 ms or more, the gain is at its least). Not over a
           calibration, whose own line stands in the same place. */
        el('microscope-too-bright').hidden = d.tooBright !== true || calibrating;
        // A dark phase that only now sinks to black is a turn after all.
        if (d.dark && s.turnBlack === false && d.brightness < TURN_BLACK) {
            s.turnBlack = true;
            if (revolver().length > 0 && !calibrating && !stacking) openAsk();
        }
        /* Switched elsewhere — in MicroCam's menu, or a manual value that
           turned auto off there — the box follows. Not while a change of
           this window is still on its way. */
        if (typeof d.autoExposure === 'boolean' && d.autoExposure !== auto.checked && !s.pending && !s.sending) {
            auto.checked = d.autoExposure;
            lightSteering();
        }
        /* Under auto exposure time and gain are only shown, never pushed —
           so they follow the camera even while the brightness is being
           dragged (09.11.2026: they lagged until the hand let
           go). By hand they wait, or a frame from before the change would
           pull the slider back under the finger. */
        if (auto.checked || (!s.pending && !s.sending)) drawLive(d);
    }

    function onDark(s, d) {
        s.answered = false;
        clearTimeout(s.settleTimer);
        // Only a black one is a turn (TURN_BLACK); a dip or a shadow is not.
        s.turnBlack = !(d?.brightness >= TURN_BLACK);
        if (!s.turnBlack) return;
        // Not while calibrating: the slide is changed for the micrometer.
        // Nor while a stack runs: a lamp flickering then is no turn.
        if (revolver().length > 0 && !calibrating && !stacking) openAsk();
    }

    function onLight(s) {
        clearTimeout(s.settleTimer);
        s.settleTimer = setTimeout(() => settled(s, s.last ?? {}), SETTLED_PATIENCE);
    }

    /**
     * The light is back and stable. Asked here at the latest — after the
     * camera's first picture, or when the dark was too short to be seen;
     * already answered during the dark, nothing more is asked.
     */
    function settled(s, d) {
        clearTimeout(s.settleTimer);
        if (session !== s || s.answered || revolver().length === 0 || calibrating || stacking) return;
        // The light back after a dark that never got black: no turn.
        const black = s.turnBlack;
        s.turnBlack = null;
        if (!d?.initial && black === false) return;
        // Back from a freeze MicroCam healed itself — the objective stands.
        if (d?.initial && s.recovering) {
            s.recovering = false;
            return;
        }
        /* THE CAMERA'S FIRST STABLE PICTURE after switching on comes as
           „settled" too, marked initial, with no dark before it — AND IT
           ASKS, every time (26.10.2026: „nachdem die Kamera aus
           ist, brauche ich wieder ein Fenster, wo ich beim Neustart der
           Kamera das Objektiv auswählen muss"). For a day it asked only on
           a computer never told an objective and otherwise let the last
           one stand — but the camera goes off 45 seconds after the window
           closes, and whatever was turned in between nobody saw. A window
           opened again while the camera still runs gets no initial
           „settled", and the last objective stands, rightly. */
        if (ask.hidden) openAsk();
    }

    /**
     * „Welches Objektiv?" over the picture — every objective the same,
     * NONE PRESELECTED (25.10.2026: „da wird ein Objektiv
     * vorausgewählt. Das will ich nicht … Man soll selbst auswählen").
     * A guess from the light stood here as the loud button for a day:
     * from the exposure before and after the turn, the next objective in
     * that direction. The camera cannot know which objective sits in the
     * path, and a suggestion that is often wrong is worse than none — the
     * eye reads the loud button as the answer.
     */
    function openAsk() {
        const s = session;
        if (!s) return;
        ask.classList.remove('is-missing');
        ask.hidden = false;
        // Folded at every opening (askMediaOpen says why).
        if (askMediaOpen) {
            askMediaOpen = false;
            drawMedia();
        }
        askChoices.innerHTML = '';
        for (const p of revolver()) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.textContent = p.name;
            b.addEventListener('click', () => {
                setObjective(p.id);
                closeAsk();
            });
            askChoices.append(b);
        }
    }

    /* THE QUESTION'S CLICKS ARE NO TAPS ON THE PICTURE (20.11.2026): it lies
       on the stage, and the zoom there reads two clicks in quick succession
       as a double tap — two media ticked one after the other, and the
       picture jumped to threefold. With one click that closed the question
       it never showed. */
    ask.addEventListener('click', (e) => e.stopPropagation());

    function closeAsk() {
        ask.hidden = true;
        ask.classList.remove('is-missing');
        if (session) {
            session.answered = true;
            clearTimeout(session.settleTimer);
        }
    }

    function stop() {
        const s = session;
        session = null;
        markLive();
        quickClear();
        // The next opening starts fitted.
        liveZoom?.reset();
        el('microscope-too-bright').hidden = true;
        mediumInfo.hidden = true;
        if (!s) return;
        s.abort.abort();
        s.events?.close();
        s.unwatch?.();
        // A webcam's light goes out only when every track is stopped.
        s.stream?.getTracks().forEach((track) => track.stop());
        video.onplaying = null;
        video.srcObject = null;
        video.hidden = true;
        clearTimeout(s.settleTimer);
        clearTimeout(s.sendTimer);
        /* THE STREAM ENDS ONLY WHEN THE <img> LETS GO OF IT — a hidden
           picture goes on loading. MicroCam closes the camera 45 seconds
           after the last viewer. */
        live.onload = live.onerror = null;
        live.removeAttribute('src');
        live.hidden = true;
        ask.hidden = true;
        /* A stack held while the window closes: it ends unsaved, and the
           stage is put back for the next opening. */
        if (press) {
            clearTimeout(press.timer);
            clearInterval(press.grabber);
            press.stack?.cancel();
            press = null;
        }
        stacking = false;
        badge.hidden = true;
        stackView.hidden = true;
        liveLabel.hidden = true;
        live.classList.remove('is-inset');
        video.classList.remove('is-inset');
    }

    retry.addEventListener('click', connect);

    // --- The picture's orientation ---------------------------------------------

    /* MIRRORED LIKE THE EYEPIECE (09.11.2026: „wenn ich im
       Okular nach rechts wandere, möchte ich natürlich auch im Live-Bild
       nach rechts wandern"). Depending on how the camera sits on the
       phototube, the picture runs against the stage's knobs. Set once and
       done, so it lives behind the gear, not in the sidebar.
       WITH MicroCAM the camera mirrors itself (hflip / vflip in its
       settings, kept across restarts by MicroCam): live picture, stack and
       still come out alike and nothing here has to turn them. WITH A
       WEBCAM nobody else can: the <video> is mirrored by a transform, and
       every frame drawn from it — the still, the stack — through
       drawOriented(), so what is saved matches what was seen. That one
       lives in this computer's settings. */
    const camFlip = { h: false, v: false };
    function webcamFlip() {
        const f = settings.get('microscopeWebcamFlip', null);
        return { h: f?.h === true, v: f?.v === true };
    }
    function currentFlip() {
        return microscopeSource() === 'webcam' ? webcamFlip() : camFlip;
    }
    /* The mirroring as a transform — written AFTER the live picture's zoom
       (liveZoom below), so it turns the picture in place and a drag still
       moves it the way the hand goes. The classes carry it into the small
       corner picture while a stack grows (pointwindow.css). */
    function videoFlipTail() {
        const f = webcamFlip();
        return f.h || f.v ? `scale(${f.h ? -1 : 1}, ${f.v ? -1 : 1})` : '';
    }
    function orientVideo() {
        const f = webcamFlip();
        video.classList.toggle('is-flip-h', f.h);
        video.classList.toggle('is-flip-v', f.v);
        if (liveZoom?.target === video) liveZoom.refresh();
        else video.style.transform = videoFlipTail();
    }

    /* THE LIVE PICTURE ZOOMS WITH THE WHEEL (15.11.2026: „das
       Live-Bild vielleicht auch zoomen, mit dem Mausrad etwas heranzoomen
       und wieder wegzoomen, so wie bei den anderen Zoom-Werkzeugen, dass es
       sich dann einpasst in den Rahmen und kurz einrastet"). The viewers'
       own zoom (imagezoom.js): the wheel round the pointer, a drag pans,
       zooming out stops at the fitted, centred picture — the catch he asked
       for —, a double click and Y fit it again. Bound to the picture that
       shows the live view — MicroCam's <img> or the webcam's <video> —, the
       growing stack's canvas riding along (mirror), so a stack is watched
       at the magnification one focused at. Asleep while a calibration's
       still lies over the stage: that still zooms on its own. */
    let liveZoom = null;
    function liveZoomOn(target) {
        if (liveZoom?.target === target) return;
        liveZoom?.release();
        liveZoom = imageZoom.bind(el('microscope-stage'), target, {
            mirror: [stackView],
            tail: () => (target === video ? videoFlipTail() : ''),
            active: () => !calibrating && view === 'microscope',
            changed: () => queueScaleBar(),
        });
        liveZoom.target = target;
    }
    function drawFlips() {
        const f = currentFlip();
        flipH.checked = f.h;
        flipV.checked = f.v;
        // MicroCam's own setting can only be changed while it answers.
        flipH.disabled = flipV.disabled = microscopeSource() !== 'webcam' && !session;
    }
    /** Draw a webcam frame turned as the live picture shows it. */
    function drawOriented(g, src, w, h) {
        const f = webcamFlip();
        g.save();
        g.translate(f.h ? w : 0, f.v ? h : 0);
        g.scale(f.h ? -1 : 1, f.v ? -1 : 1);
        g.drawImage(src, 0, 0, w, h);
        g.restore();
    }
    function setFlip() {
        const next = { h: flipH.checked, v: flipV.checked };
        if (microscopeSource() === 'webcam') {
            settings.set('microscopeWebcamFlip', next);
            orientVideo();
            return;
        }
        camFlip.h = next.h;
        camFlip.v = next.v;
        sendSoon({ hflip: next.h, vflip: next.v });
    }
    flipH.addEventListener('change', setFlip);
    flipV.addEventListener('change', setFlip);

    /* THE SERIES BEHIND A STACK, KEPT ON REQUEST (13.11.2026). The measure
       that weighs the frames was changed that evening on drawn focus series
       and failed on the first real tissue — round cells throw bright
       crescents out of focus that no drawing had. What a stack should do
       can only be worked out on the frames it really gets, so with the tick
       in the gear's window (settings microscopeKeepSeries, per computer)
       every frame that goes into a stack is kept as a JPEG, and when the
       stack is done the browser saves ONE file: a plain tar — a dozen lines
       to write, no library, opened by every system — with the frames in
       order and the result. Nothing is uploaded anywhere. */
    keepSeries.checked = settings.get('microscopeKeepSeries', false) === true;
    keepSeries.addEventListener('change', () => settings.set('microscopeKeepSeries', keepSeries.checked));

    /** A tar archive of [{name, blob}] — ustar headers, each file padded to 512 bytes. */
    async function tarOf(files) {
        const parts = [];
        const text = new TextEncoder();
        for (const f of files) {
            const head = new Uint8Array(512);
            const put = (value, at) => head.set(text.encode(value), at);
            put(f.name.slice(0, 99), 0);
            put('0000644\0', 100);
            put('0000000\0', 108);
            put('0000000\0', 116);
            put(f.blob.size.toString(8).padStart(11, '0') + '\0', 124);
            put(Math.floor(Date.now() / 1000).toString(8).padStart(11, '0') + '\0', 136);
            put('        ', 148);                 // the checksum's own field counts as blanks
            put('0', 156);
            put('ustar\0' + '00', 257);
            let sum = 0;
            for (const byte of head) sum += byte;
            put(sum.toString(8).padStart(6, '0') + '\0 ', 148);
            parts.push(head, f.blob, new Uint8Array((512 - f.blob.size % 512) % 512));
        }
        parts.push(new Uint8Array(1024));
        return new Blob(parts, { type: 'application/x-tar' });
    }

    /** Save a stack's frames and its result as one file — the browser's download. */
    async function seriesSave(frames, result) {
        try {
            const kept = (await Promise.all(frames)).filter(Boolean);
            const files = kept.map((blob, i) => ({ name: `bild-${String(i + 1).padStart(3, '0')}.jpg`, blob }));
            if (result) files.push({ name: 'stapel.jpg', blob: result });
            const address = URL.createObjectURL(await tarOf(files));
            const link = document.createElement('a');
            link.href = address;
            link.download = t('Fokusserie %s', moment()) + '.tar';
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(address), 60000);
        } catch (e) {
            console.warn('Series not saved:', e);
        }
    }

    // --- The light --------------------------------------------------------------

    /* The exposure slider runs on a logarithmic scale: from 0.1 ms to two
       seconds is a factor of twenty thousand, and on a straight scale
       everything below a tenth of a second would be the first millimetre. */
    function exposureRange() {
        const [lo, hi] = session?.ranges?.exposureUs ?? [100, 2000000];
        return [Math.max(1, lo), Math.max(lo + 1, hi)];
    }
    function exposureToSlider(us) {
        const [lo, hi] = exposureRange();
        return Math.round(1000 * Math.log(Math.max(lo, Math.min(hi, us)) / lo) / Math.log(hi / lo));
    }
    function sliderToExposure(v) {
        const [lo, hi] = exposureRange();
        return Math.round(lo * Math.pow(hi / lo, v / 1000));
    }

    /** The live numbers while auto exposure steers — sliders follow, untouched. */
    function drawLive(d) {
        if (document.activeElement === exposure || document.activeElement === gain) return;
        if (d.exposureUs > 0) {
            exposure.value = String(exposureToSlider(d.exposureUs));
            el('microscope-exposure-value').textContent = readableExposure(d.exposureUs);
        }
        if (d.gain > 0) {
            gain.value = String(d.gain);
            el('microscope-gain-value').textContent = t('×%s', (d.gain / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }
    }

    function drawLight(set) {
        light.disabled = false;
        if (typeof set.hflip === 'boolean') camFlip.h = set.hflip;
        if (typeof set.vflip === 'boolean') camFlip.v = set.vflip;
        drawFlips();
        const r = session?.ranges ?? {};
        if (Array.isArray(r.target)) { target.min = r.target[0]; target.max = r.target[1]; }
        if (Array.isArray(r.gain))   { gain.min = r.gain[0];   gain.max = r.gain[1]; }
        auto.checked = set.autoExposure !== false;
        // Never under the hand: an answer arriving mid-drag is already old.
        if (set.target != null && document.activeElement !== target) {
            target.value = String(set.target);
            el('microscope-target-value').textContent = String(set.target);
        }
        drawLive(set);
        lightSteering();
    }

    /* WHAT STEERS IS WHAT CAN BE MOVED: under auto exposure the brightness
       it aims at, by hand time and gain. The other two show what is
       happening and stand still. */
    function lightSteering() {
        target.disabled = !auto.checked;
        exposure.disabled = auto.checked;
        gain.disabled = auto.checked;
    }

    /** Send a part of the settings while the hand moves: at most one
        request on its way, the newest values ride the next one.
        NOT A DEBOUNCE (09.11.2026: „extrem zeitverzögert … erst
        wenn ich den Regler loslasse"): a timer that started over with
        every input event sent nothing as long as the slider moved, so the
        camera — and with it time and gain under auto exposure — only
        answered once the hand stood still. Now a drag reaches MicroCam
        every SEND_EVERY milliseconds, and the last position always
        arrives. */
    function sendSoon(part) {
        const s = session;
        if (!s) return;
        s.pending = { ...(s.pending ?? {}), ...part };
        if (!s.sendTimer && !s.sending) s.sendTimer = setTimeout(() => sendNow(s), SEND_EVERY);
    }
    async function sendNow(s) {
        s.sendTimer = null;
        const body = s.pending;
        if (!body || session !== s) return;
        s.pending = null;
        s.sending = true;
        try {
            const answer = await camFetch('/api/settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            }, { signal: s.abort.signal });
            if (!answer.ok) throw new Error(String(answer.status));
            const status = await answer.json();
            if (session === s && !s.pending) drawLight(status.settings ?? {});
        } catch (e) {
            if (e.name !== 'AbortError') report(t('Die Einstellung kam beim Kamera-Programm nicht an.'));
        } finally {
            s.sending = false;
            // What came in while this one travelled goes next.
            if (s.pending && session === s) s.sendTimer = setTimeout(() => sendNow(s), SEND_EVERY);
        }
    }

    auto.addEventListener('change', () => {
        lightSteering();
        sendSoon({ autoExposure: auto.checked });
    });
    target.addEventListener('input', () => {
        el('microscope-target-value').textContent = target.value;
        sendSoon({ target: Number(target.value) });
    });
    exposure.addEventListener('input', () => {
        const us = sliderToExposure(Number(exposure.value));
        el('microscope-exposure-value').textContent = readableExposure(us);
        sendSoon({ exposureUs: us });
    });
    gain.addEventListener('input', () => {
        el('microscope-gain-value').textContent = t('×%s', (Number(gain.value) / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        sendSoon({ gain: Number(gain.value) });
    });

    /* THE WHITE BALANCE IS A DEED, NOT A STATE (26.10.2026: „der
       Weißabgleich wird nicht pro Objektiv oder pro Kamera-Session
       gespeichert, sondern es kommt komplett drauf an, welche Reagenz ich
       unterm Mikroskop habe … muss ich ständig machen. Und der darf nicht
       irgendwie so ein Dauerding sein"). Congo red, cotton blue, Melzer —
       each stain wants it anew. So nothing stays behind after the press:
       the button lets go of the focus (its ring read as a mark that it was
       done), and the word that it worked goes after a few seconds. */
    let balanceWord = null;
    el('microscope-whitebalance').addEventListener('click', async (e) => {
        e.currentTarget.blur();
        const s = session;
        if (!s) return;
        clearTimeout(balanceWord);
        report(t('Weißabgleich …'));
        try {
            const answer = await camFetch('/api/whitebalance', { method: 'POST' }, { signal: s.abort.signal });
            if (!answer.ok) throw new Error(String(answer.status));
            report(t('Weißabgleich gesetzt.'));
            const said = progress.textContent;
            balanceWord = setTimeout(() => { if (progress.textContent === said) report(''); }, 4000);
        } catch (err) {
            if (err.name !== 'AbortError') report(t('Der Weißabgleich ging nicht.'));
        }
    });

    // --- The still ---------------------------------------------------------------

    /**
     * The scale of a still taken through this objective — the same shape
     * the photo window stamps from a preset (a synthetic line of 1000 px,
     * photowindow.js), so the picture carries it to every viewer, the
     * preset's name as provenance.
     *
     * THE PRESET'S OWN WIDTH AS THE REFERENCE, not the still's. A preset
     * is µm per pixel at the width it was calibrated at, and measuring
     * converts by the ratio of the shown width to this one
     * (measurestats.js, measureUmPerPx). Calibrated on a still of this very
     * camera, the two are the same 3584; calibrated on a shrunk copy or a
     * binned preview of it, the ratio carries the factor over instead of
     * measuring wrong by it. A preset typed in by hand has no width — then
     * it is taken to mean the still.
     */
    function scaleFor(preset) {
        if (!preset) return null;
        return {
            line:   [[0, 0], [1000, 0]],
            um:     1000 * preset.um_per_px,
            corner: 'br',
            name:   preset.name,
            width:  preset.width || session?.still?.width || undefined,
            // Unseen, for correcting a wrong calibration later (photos.php).
            preset_id: preset.id,
            device_id: preset.device_id ?? undefined,
        };
    }

    /**
     * One still from the camera, as a JPEG file named by its moment — or
     * null when none came.
     *
     * FROM A PROGRAM: its own still at the sensor's full size (never a
     * frame of the stream — see the head of this file). FROM A WEBCAM: a
     * frame of the largest mode it runs in, the one grid it always has
     * (openWebcam).
     */
    async function takeStill(s) {
        let blob = null;
        if (s.stream) {
            if (!(video.videoWidth > 0)) return null;
            // On the processor, not the graphics card (cpucanvas.js — an old iMac's drew black).
            const { canvas, g } = cpuCanvas(video.videoWidth, video.videoHeight);
            drawOriented(g, video, canvas.width, canvas.height);
            blob = await canvasJpeg(canvas, 0.92);
            if (blob) blob = await stamped(s, blob, { captured: true, to: { w: canvas.width, h: canvas.height } });
            canvas.width = canvas.height = 0;
        } else {
            /* Its own abort: closing the window ends it, and so does a
               camera that does not deliver within the patience — without
               taking the live picture down with it. */
            const still = new AbortController();
            const quit = () => still.abort();
            s.abort.signal.addEventListener('abort', quit);
            const watch = setTimeout(quit, STILL_PATIENCE);
            try {
                const answer = await camFetch('/api/snapshot.jpg?quality=0.92', {}, { signal: still.signal });
                if (answer.ok) blob = await answer.blob();
            } catch {
                blob = null;
            } finally {
                clearTimeout(watch);
                s.abort.signal.removeEventListener('abort', quit);
            }
        }
        if (!blob || session !== s) return null;
        const file = stillFile(blob, t('Mikroskop %s', moment()));
        /* MicroCam's still names its camera itself (Make, Model) — the most
           exact name there is, so the stacks after it take it over. */
        if (!s.stream) {
            const named = await readCamera(file);
            if (named.model) s.camera = { make: named.make, model: named.model };
        }
        return file;
    }

    /* EVERY MICROGRAPH NAMES ITS CAMERA (13.11.2026: „Das
       Kameramodell sollten wir immer nennen. Also die Mikroskopkamera,
       falls wir da einen Namen haben"). MicroCam's still carries Make and
       Model from the camera program; but a STACK and a WEBCAM'S STILL are
       drawn on a canvas here, and a canvas writes a bare JPEG — no camera,
       no moment. They are stamped like a shrunk picture (exifstamp.js):
       the camera's name — MicroCam's status `model`, refined by the last
       still's Make/Model; for a webcam the name the system gives it —, the
       moment of the shot, and a note what the file is. The held copy reads
       the camera back from there into map_photos.camera, the cloud file
       carries it in its EXIF. A note that cannot be written never holds
       the picture back. */
    async function stamped(s, blob, info) {
        try {
            const now = new Date();
            const two = (n) => String(n).padStart(2, '0');
            const taken = `${now.getFullYear()}:${two(now.getMonth() + 1)}:${two(now.getDate())}`
                + ` ${two(now.getHours())}:${two(now.getMinutes())}:${two(now.getSeconds())}`;
            const bytes = stampJpeg(new Uint8Array(await blob.arrayBuffer()), {
                ...(s.camera ?? {}),
                taken,
                when: now,
                originalName: '',
                from: info.to,
                ...info,
            });
            return new Blob([bytes], { type: 'image/jpeg' });
        } catch (e) {
            console.warn('No camera written into the micrograph:', e);
            return blob;
        }
    }

    /** The moment as a file name wants it — „2026-10-26 18-05-29". */
    function moment() {
        const now = new Date();
        const two = (n) => String(n).padStart(2, '0');
        return `${now.getFullYear()}-${two(now.getMonth() + 1)}-${two(now.getDate())}`
            + ` ${two(now.getHours())}-${two(now.getMinutes())}-${two(now.getSeconds())}`;
    }

    function stillFile(blob, name) {
        return new File([blob], name + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
    }

    /** One photo — the short press. */
    async function shoot() {
        const s = session;
        if (!s) return;
        take.disabled = true;
        report(t('Foto wird aufgenommen …'));
        const preset = currentPreset();
        const file = await takeStill(s);
        if (!file) {
            if (session === s) {
                report(t('Das Foto kam nicht von der Kamera.'));
                take.disabled = false;
            }
            return;
        }
        report('');
        try {
            await save(file, scaleFor(preset), report, currentMedia());
        } finally {
            if (session === s) take.disabled = false;
        }
    }

    // --- The stack: the shutter held --------------------------------------------
    //
    // A SHORT PRESS IS A PHOTO, A HELD ONE A STACK (26.10.2026: „wenn
    // man länger auf Space drückt, automatisch ein Stack"). Held, the window
    // collects live frames while the fine focus is turned through the slide,
    // and on letting go makes one picture of them (focusstack.js). The photo
    // of a short press therefore comes on letting go, not on pressing — only
    // then is it known that the press was short.
    //
    // LIVE FRAMES, NOT STILLS (his call): a still takes MicroCam about two
    // seconds, and turning the focus would leave a handful of planes; a live
    // frame comes as fast as the exposure allows. It is the same field of
    // view at a lower grid, so the scale holds — the preset converts by the
    // width, as for every shrunk picture. ONLY THE RESULT IS KEPT (his
    // call too): twenty frames a stack would fill every find's folder.

    const HOLD_MS = 350;      // what is longer than a press
    const STACK_EDGE = 2048;  // a webcam's 4K frames are made smaller first
    const GRAB_MS = 90;       // how often a new frame is looked for
    const badge = el('microscope-stack-badge');
    const stackView = el('microscope-stack-view');
    /* „Live" on the small picture while a stack grows — the big one is the
       stack, and taken for the live picture it seemed to lag (Finn). */
    const liveLabel = el('microscope-live-label');
    let press = null;
    let stacking = false;

    function pressStart() {
        const s = session;
        if (!s || press || take.hidden || take.disabled) return;
        /* AN OPEN QUESTION IS ANSWERED FIRST. A still taken while the
           objective is unclear would carry a scale nobody chose — the
           question turns red where it stands. */
        if (!ask.hidden) {
            ask.classList.add('is-missing');
            return;
        }
        press = { s, timer: setTimeout(() => beginStack(press), HOLD_MS) };
    }

    function pressEnd() {
        const p = press;
        press = null;
        if (!p) return;
        clearTimeout(p.timer);
        if (p.stack) finishStack(p);
        else if (session === p.s) shoot();
    }

    function beginStack(p) {
        if (!p || press !== p || session !== p.s) return;
        const source = p.s.stream ? video : live;
        const w0 = p.s.stream ? video.videoWidth : live.naturalWidth;
        const h0 = p.s.stream ? video.videoHeight : live.naturalHeight;
        if (!(w0 > 0 && h0 > 0)) return;   // no picture yet: it stays a photo
        const f = Math.min(1, STACK_EDGE / Math.max(w0, h0));
        const w = Math.round(w0 * f), h = Math.round(h0 * f);
        p.canvas = document.createElement('canvas');
        p.canvas.width = w;
        p.canvas.height = h;
        p.g = p.canvas.getContext('2d', { willReadFrequently: true });
        /* THE LIVE PREVIEW OF THE STACK (27.10.2026: „wenn man
           nichts sieht … weiß man ja nicht, wann man aufhören muss. Wann man
           quasi die Spore durchgesteckt hat"). The stack as it stands comes
           back with every frame and fills the stage; the live frame moves
           into the corner. One stops turning when the last part of the
           spore has turned sharp — not „to be safe" a few planes later. */
        stackView.width = w;
        stackView.height = h;
        p.view = stackView.getContext('2d');
        p.liveEl = p.s.stream ? video : live;
        p.stack = startStack(w, h, {
            onPreview: (picture) => {
                if (!p.view) return;
                p.view.putImageData(picture, 0, 0);
                if (stackView.hidden) {
                    stackView.hidden = false;
                    p.liveEl.classList.add('is-inset');
                    liveLabel.hidden = false;
                }
            },
        });
        p.last = null;
        // The frames themselves, where the series is to be kept (seriesSave).
        p.series = keepSeries.checked ? [] : null;
        p.preset = currentPreset();
        stacking = true;
        clearTimeout(badgeTimer);
        badge.hidden = false;
        badge.textContent = stackCount(0);
        p.grabber = setInterval(() => { p.pending ??= grab(p, source).finally(() => { p.pending = null; }); }, GRAB_MS);
    }

    function stackCount(n) {
        return n === 1 ? t('Stapel: 1 Bild') : t('Stapel: %d Bilder', n);
    }

    /* A frame into the stack — a NEW one: the picture is looked at more
       often than it changes, and the same frame twice would only cost time.
       Skipped, too, while the worker still reckons the last one.

       FROM A CAMERA PROGRAM, ITS OWN SINGLE FRAME, not the live picture
       (26.10.2026: held, the stack stayed at one picture). The
       live picture is an MJPEG stream in an <img>, and Firefox hands a
       canvas the stream's FIRST frame however many have followed — every
       later one then looked like a duplicate. Chrome draws the current one,
       which is why the first measurement passed. /api/frame.jpg is the
       newest frame as a picture of its own, the same in every browser; a
       new frame is told by its size, before anything is decoded. A webcam's
       <video> has no such trouble and is drawn directly. */
    async function grab(p, source) {
        if (p.stack.busy()) return;
        if (!p.s.stream) {
            let blob;
            try {
                const answer = await camFetch('/api/frame.jpg', {}, { signal: p.s.abort.signal });
                if (!answer.ok) return;
                blob = await answer.blob();
            } catch {
                return;
            }
            if (blob.size === p.lastSize) return;
            p.lastSize = blob.size;
            const bitmap = await createImageBitmap(blob);
            p.g.drawImage(bitmap, 0, 0, p.canvas.width, p.canvas.height);
            bitmap.close();
            const data = p.g.getImageData(0, 0, p.canvas.width, p.canvas.height);
            if (p.stack.add(data)) {
                badge.textContent = stackCount(p.stack.frames());
                p.series?.push(blob);          // the camera program's own JPEG, untouched
            }
            return;
        }
        drawOriented(p.g, source, p.canvas.width, p.canvas.height);
        const data = p.g.getImageData(0, 0, p.canvas.width, p.canvas.height);
        const step = Math.max(4, Math.floor(data.data.length / 4 / 2000)) * 4;
        const sample = [];
        for (let i = 0; i < data.data.length; i += step) sample.push(data.data[i + 1]);
        if (p.last) {
            let diff = 0;
            for (let i = 0; i < sample.length; i++) diff += Math.abs(sample[i] - p.last[i]);
            if (diff / sample.length < 0.5) return;
        }
        p.last = sample;
        // Before add(): it hands the pixels to the worker; the canvas still holds the frame.
        const kept = p.series ? new Promise((resolve) => p.canvas.toBlob(resolve, 'image/jpeg', 0.95)) : null;
        if (p.stack.add(data)) {
            badge.textContent = stackCount(p.stack.frames());
            if (kept) p.series.push(kept);
        }
    }

    let badgeTimer = null;

    /** The stage as it was: the live frame back in its place. */
    function endPreview(p) {
        p.view = null;
        stackView.hidden = true;
        liveLabel.hidden = true;
        p.liveEl?.classList.remove('is-inset');
    }

    async function finishStack(p) {
        clearInterval(p.grabber);
        // A frame on its way still counts.
        await p.pending;
        const s = p.s;
        const count = p.stack.frames();
        if (count < 2) {
            p.stack.cancel();
            endPreview(p);
            stacking = false;
            /* SAID ON THE PICTURE, where the eye is — under the button it
               was read past, and letting go seemed to do nothing. */
            badge.textContent = t('Zu wenige Bilder für einen Stapel. Halte länger und dreh dabei am Feintrieb.');
            badgeTimer = setTimeout(() => { badge.hidden = true; }, 5000);
            return;
        }
        take.disabled = true;
        badge.textContent = t('Stapel aus %d Bildern wird gerechnet …', count);
        let file = null;
        try {
            const picture = await p.stack.finish();
            // The big picture becomes the finished stack — see below.
            p.view?.putImageData(picture, 0, 0);
            // On the processor, as every picture that is stored (cpucanvas.js).
            const { canvas, g } = cpuCanvas(picture.width, picture.height);
            g.putImageData(picture, 0, 0);
            let blob = await canvasJpeg(canvas, 0.92);
            if (blob) blob = await stamped(s, blob, { layers: count, to: { w: canvas.width, h: canvas.height } });
            canvas.width = canvas.height = 0;
            if (p.series?.length) seriesSave(p.series, blob);
            /* THE NAME SAYS IT IS A STACK, and of how many — for a voucher
               that is part of the truth about the picture. „stacked <n>"
               untranslated, at the very end: the cloud's renaming finds
               the token there and keeps „stacked" in every later name
               (includes/cloud_names.php, CLOUD_NAMES_STACKED). */
            if (blob) file = stillFile(blob, t('Mikroskop %s', moment()) + ' stacked ' + count);
        } catch (e) {
            console.warn('Stack not made:', e);
        } finally {
            stacking = false;
            badge.hidden = true;
        }
        if (session !== s) return;
        if (!file) {
            endPreview(p);
            report(t('Der Stapel ließ sich nicht rechnen.'));
            take.disabled = false;
            return;
        }
        report('');
        /* THE STACK STAYS ON THE STAGE UNTIL IT IS ANSWERED (Finn's test,
           28.10.2026: on letting go „plötzlich eine ganz andere Ansicht").
           The live picture coming back big between the growing stack and
           the subject window showed another plane altogether, for a moment
           — it read as a jump. Now the eye goes from the growing stack to
           the finished one, and the live picture returns once it is saved
           or discarded. */
        try {
            await save(file, scaleFor(p.preset), report, currentMedia());
        } finally {
            endPreview(p);
            if (session === s) take.disabled = false;
        }
    }

    /* THE SHUTTER BUTTON KNOWS THE HOLD TOO — with the mouse, as with the
       space bar. Its click (Enter on the focused button) stays a photo; the
       click that follows a pointer's press is that press, already done. */
    let pointerAt = 0;
    take.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        pointerAt = performance.now();
        take.setPointerCapture?.(e.pointerId);
        pressStart();
    });
    take.addEventListener('pointerup', pressEnd);
    take.addEventListener('pointercancel', pressEnd);
    take.addEventListener('click', () => {
        if (performance.now() - pointerAt < 1500) return;
        pressStart();
        pressEnd();
    });
    // Letting go outside the browser — the window loses the focus — ends it.
    window.addEventListener('blur', pressEnd);

    // --- Calibrating an objective ------------------------------------------------
    //
    // THE STAGE MICROMETER UNDER THE LENS, AND THE PRESET MADE HERE
    // (24.10.2026). The same measurement as in Einstellungen → Fotos →
    // Maßstab-Vorlagen (calibrate.js) — a line over a known stretch of the
    // scale, its true length — but the picture comes straight from the
    // camera, in the grid every later still will have, and nobody looks for
    // a file. Two steps: the live picture to set the scale sharp, then the
    // still to draw on. Saved like there: the picture first into the cloud's
    // „Kalibrierung" folder, the preset only once it arrived.

    const calField   = el('microscope-objectives-field');
    const calPanel   = el('microscope-calibrate');
    const calStep    = el('microscope-cal-step');
    const calName    = el('microscope-cal-name');
    const calDeviceField = el('microscope-cal-device');
    const calDevices = el('microscope-cal-devices');
    const calNewDevice = el('microscope-cal-newdevice');
    const calNewDeviceName = el('microscope-cal-newdevice-name');

    /* The calibration's device: an id, 'new', or null where the account
       has none yet — then the server makes „Mikroskop" as it always did. */
    let calDevice = null;

    /** The objectives of the device the calibration is for — what a name
     *  could replace. A new device has none. */
    function calPresets() {
        if (calDevice === 'new') return [];
        if (calDevice === null) return revolver();
        return (presets() ?? []).filter((p) => p.um_per_px > 0 && p.device_id === calDevice);
    }

    /** The name suggestions under the field: the chosen device's objectives. */
    function calNames() {
        const names = el('microscope-cal-names');
        names.innerHTML = '';
        for (const p of calPresets()) {
            const o = document.createElement('option');
            o.value = p.name;
            names.append(o);
        }
    }

    /** „Für welches Gerät eichst du?" — every device, the head's ticked,
     *  and „Neues Gerät" with its name field. */
    function drawCalDevices() {
        const list = deviceList();
        calDeviceField.hidden = list.length === 0;
        calDevice = currentDevice()?.id ?? null;
        calDevices.innerHTML = '';
        calNewDevice.hidden = true;
        calNewDeviceName.value = '';
        if (list.length === 0) return;
        for (const choice of [...list.map((d) => ({ value: d.id, name: d.name })), { value: 'new', name: t('Neues Gerät') }]) {
            const row = document.createElement('label');
            row.className = 'choice-row';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'microscope-cal-device';
            radio.checked = choice.value === calDevice;
            radio.addEventListener('change', () => {
                calDevice = choice.value;
                calNewDevice.hidden = calDevice !== 'new';
                if (calDevice === 'new') calNewDeviceName.focus();
                calNames();
                calReadiness();
            });
            const label = document.createElement('span');
            label.textContent = choice.name;
            row.append(radio, label);
            calDevices.append(row);
        }
    }
    const calLength  = el('microscope-cal-length');
    const calPixels  = el('microscope-cal-pixels');
    const calShoot   = el('microscope-cal-shoot');
    const calAgain   = el('microscope-cal-again');
    const calCancel  = el('microscope-cal-cancel');
    const calSave    = el('microscope-cal-save');
    const calFields  = el('microscope-cal-fields');
    const calBanner  = el('microscope-cal-banner');

    /* The still drawn on, its address, and the line's two ends in the
       still's own pixels. */
    let calFile = null;
    let calUrl = null;
    let calPoints = [];

    function calLength_um() {
        const value = Number(String(calLength.value).trim().replace(',', '.'));
        const unit = Number(box.querySelector('input[name="microscope-cal-unit"]:checked')?.value ?? 1);
        return value > 0 && Number.isFinite(value) ? value * unit : null;
    }

    function calLinePx() {
        if (calPoints.length < 2) return 0;
        const [[x1, y1], [x2, y2]] = calPoints;
        return Math.hypot(x2 - x1, y2 - y1);
    }

    /** The line over the picture: which click is due, or that the line stands. */
    function calClickSay() {
        calBanner.hidden = calFile === null;
        calBanner.textContent = [
            t('Klicke im Bild auf den Anfang einer Strecke der Skala.'),
            t('Klicke jetzt auf das Ende der Strecke.'),
            t('Strecke gesetzt. Ein weiterer Klick fängt sie neu an.'),
        ][calPoints.length];
    }

    /** Ready to save: a line, a length, a name. Loud only then. */
    function calReadiness() {
        const px = calLinePx();
        calPixels.textContent = px > 0 ? t('Strecke im Bild: %d Pixel', Math.round(px)) : '';
        const ready = calFile !== null && px >= 1 && calLength_um() !== null && calName.value.trim() !== ''
            && (calDevice !== 'new' || calNewDeviceName.value.trim() !== '');
        calSave.classList.toggle('subtle', !ready);
    }

    function showLivePicture() {
        calFrame.hidden = true;
        if (heldSay) {
            const held = heldSay;
            heldSay = null;
            say(...held);
            return;
        }
        if (session?.stream) video.hidden = false;
        else if (live.getAttribute('src')) live.hidden = false;
    }

    function calibrateStart() {
        if (!session || !message.hidden) return;
        calibrating = true;
        queueScaleBar();
        ask.hidden = true;
        calField.hidden = true;
        el('microscope-media-field').hidden = true;
        light.hidden = true;
        calPanel.hidden = false;
        take.hidden = true;
        /* NEVER PREFILLED (03.11.2026: „Ich habe gerade meinen
           100-mal aus Versehen überschrieben. Weil 100-mal drin stand").
           The chosen objective's name stood here, and a name that exists
           is calibrated anew — so one save over a different lens replaced
           a good preset. Empty, the name is asked for every time; the
           list under the field still offers the existing ones, and saving
           over one asks first (calibrateSave). */
        calName.value = '';
        drawCalDevices();
        drawDevices();
        calNames();
        calLength.value = '';
        calLive();
    }

    /** Step one: the live picture, to set the micrometer's scale sharp. */
    function calLive() {
        calFile = null;
        calPoints = [];
        if (calUrl) URL.revokeObjectURL(calUrl);
        calUrl = null;
        stillImg.removeAttribute('src');
        showLivePicture();
        calStep.textContent = t('Lege das Objektmikrometer unter das Mikroskop und stelle seine Skala scharf. Dann klicke auf „Foto für die Eichung".');
        calShoot.hidden = false;
        calAgain.hidden = true;
        calFields.hidden = true;
        calClickSay();
        calReadiness();
    }

    /** Step two: the still, to draw the line on. */
    async function calShootStill() {
        const s = session;
        if (!s) return;
        calShoot.disabled = true;
        report(t('Foto wird aufgenommen …'));
        const file = await takeStill(s);
        calShoot.disabled = false;
        if (session !== s || !calibrating) return;
        if (!file) {
            report(t('Das Foto kam nicht von der Kamera.'));
            return;
        }
        report('');
        calFile = file;
        calUrl = URL.createObjectURL(file);
        stillImg.onload = () => {
            calFrame.hidden = false;
            calZoom.reset();
            drawLine();
        };
        stillImg.src = calUrl;
        calStep.textContent = t('Je länger die Strecke im Bild, desto genauer der Maßstab. Mit dem Mausrad vergrößerst du das Bild, mit gedrückter Maustaste verschiebst du es. Trage unten ein, wie lang die Strecke wirklich ist.');
        calShoot.hidden = true;
        calAgain.hidden = false;
        calFields.hidden = false;
        calClickSay();
        calReadiness();
    }

    /* Where the still stands on the canvas — contained, letterboxed (the
       arithmetic of object-fit: contain) AND zoomed and moved: the box is
       read from the screen, where the transform has already been applied,
       so one formula holds at any magnification. */
    function stillBox() {
        const iw = stillImg.naturalWidth, ih = stillImg.naturalHeight;
        const box = stillImg.getBoundingClientRect();
        const canvasAt = lineCanvas.getBoundingClientRect();
        const scale = Math.min(box.width / iw, box.height / ih);
        return {
            scale,
            dx: box.left - canvasAt.left + (box.width - iw * scale) / 2,
            dy: box.top - canvasAt.top + (box.height - ih * scale) / 2,
        };
    }

    function drawLine() {
        const ratio = window.devicePixelRatio || 1;
        lineCanvas.width = Math.round(lineCanvas.clientWidth * ratio);
        lineCanvas.height = Math.round(lineCanvas.clientHeight * ratio);
        const g = lineCanvas.getContext('2d');
        g.setTransform(ratio, 0, 0, ratio, 0, 0);
        g.clearRect(0, 0, lineCanvas.clientWidth, lineCanvas.clientHeight);
        if (!stillImg.naturalWidth || calPoints.length === 0) return;
        const { scale, dx, dy } = stillBox();
        const at = ([x, y]) => [dx + x * scale, dy + y * scale];
        /* IN THE MEASURING COLOURS, NOT THE ACCENT — a line in a picture
           wears the account's line and its seam, as every measure in the
           photo window does; the seam makes it read on a bright field and
           a dark one alike, and a micrometer is both. */
        const colours = lineColours();
        g.lineCap = 'round';
        for (const [width, colour] of [[5, colours.seam], [2, colours.line]]) {
            g.lineWidth = width;
            g.strokeStyle = colour;
            g.beginPath();
            const [ax, ay] = at(calPoints[0]);
            g.moveTo(ax, ay);
            if (calPoints[1]) g.lineTo(...at(calPoints[1]));
            else g.lineTo(ax + 0.01, ay);
            g.stroke();
            for (const p of calPoints) {
                const [x, y] = at(p);
                g.beginPath();
                g.arc(x, y, width + 2, 0, 2 * Math.PI);
                g.stroke();
            }
        }
    }

    /* A click sets the start, the next the end, a third starts anew. A
       drag moves the zoomed picture instead (imagezoom.js) and ends in a
       click too — one that moved is no point. */
    let calDownAt = null;
    calFrame.addEventListener('pointerdown', (e) => { calDownAt = [e.clientX, e.clientY]; });
    calFrame.addEventListener('click', (e) => {
        if (!stillImg.naturalWidth) return;
        if (calDownAt && Math.hypot(e.clientX - calDownAt[0], e.clientY - calDownAt[1]) > 4) return;
        const r = lineCanvas.getBoundingClientRect();
        const { scale, dx, dy } = stillBox();
        const x = (e.clientX - r.left - dx) / scale;
        const y = (e.clientY - r.top - dy) / scale;
        if (x < 0 || y < 0 || x > stillImg.naturalWidth || y > stillImg.naturalHeight) return;
        calPoints = calPoints.length >= 2 ? [[x, y]] : [...calPoints, [x, y]];
        drawLine();
        calClickSay();
        calReadiness();
    });

    function calibrateEnd() {
        calibrating = false;
        drawDevices();
        queueScaleBar();
        calPanel.hidden = true;
        calBanner.hidden = true;
        calField.hidden = false;
        el('microscope-media-field').hidden = false;
        light.hidden = microscopeSource() === 'webcam';
        take.hidden = false;
        calFile = null;
        calPoints = [];
        if (calUrl) URL.revokeObjectURL(calUrl);
        calUrl = null;
        stillImg.removeAttribute('src');
        showLivePicture();
    }

    async function calibrateSave() {
        const name = calName.value.trim();
        const um = calLength_um();
        const px = calLinePx();
        /* What is missing is said where the eye is — in this window, not
           as a toast behind it. */
        if (!calFile || px < 1) {
            report(t('Klicke zuerst Anfang und Ende der Strecke im Bild an.'));
            return;
        }
        if (um === null) {
            report(t('Die wahre Länge der Strecke fehlt noch.'));
            calLength.focus();
            return;
        }
        if (name === '') {
            report(t('Gib dem Objektiv einen Namen, zum Beispiel 40x.'));
            calName.focus();
            return;
        }
        const newDevice = calDevice === 'new' ? calNewDeviceName.value.trim() : '';
        if (calDevice === 'new') {
            if (newDevice === '') {
                report(t('Gib dem neuen Gerät einen Namen.'));
                calNewDeviceName.focus();
                return;
            }
            const known = deviceList().find((d) => d.name.trim().toLowerCase() === newDevice.toLowerCase());
            if (known) {
                report(t('Das Gerät „%s" gibt es schon. Wähle es in der Liste aus.', known.name));
                calNewDeviceName.focus();
                return;
            }
        }
        // The server matches a name as it is; asked here without regard to case.
        const same = calPresets().find((p) => p.name.trim().toLowerCase() === name.toLowerCase());
        if (same && !await confirm({
            title: t('„%s" gibt es schon', same.name),
            text: t('Die Eichung von „%s" wird durch diese ersetzt. Willst du das?', same.name),
            button: t('Ersetzen'),
            danger: true,
        })) {
            calName.focus();
            return;
        }
        calSave.disabled = true;
        report(t('Eichbild wird in der Cloud abgelegt …'));
        try {
            const [{ request }, { sendToCloud, describe, shrunk, SHRINK_EDGE }] =
                await Promise.all([import('./api.js'), import('./cloudsend.js')]);
            const width = stillImg.naturalWidth;
            const height = stillImg.naturalHeight;
            /* MEASURED ON THE WHOLE, STORED AS THE MICRO SWITCH SAYS — the
               rule of calibrate.js: the line counts the camera's pixels, and
               only the archived copy may be the shrunk one. */
            let file = new File([calFile], name + '.jpg', { type: 'image/jpeg', lastModified: calFile.lastModified });
            if (microShrink() && Math.max(width, height) > SHRINK_EDGE) {
                const bitmap = await createImageBitmap(calFile);
                file = await shrunk(bitmap, file, file.name);
                bitmap.close();
            }
            const goal = await request('calibration.php?action=target', { method: 'POST', body: describe(file) });
            const up = await sendToCloud(goal, file);
            const answer = await request('calibration.php?action=remember', {
                method: 'POST',
                body: JSON.stringify({
                    name, line_px: px, length: um, unit: '1', width, height,
                    fileid: String(up.fileid), provider: goal.provider || 'pcloud',
                    // Into the device the calibration asked for (0183), or a new one.
                    device: typeof calDevice === 'number' ? calDevice : null,
                    new_device: newDevice,
                }),
            });
            setPresets(answer.scales ?? []);
            if (Array.isArray(answer.devices)) setDevices?.(answer.devices);
            const deviceIs = answer.device_id ?? null;
            const fresh = (answer.scales ?? []).find((p) => p.name === name.slice(0, 100)
                && (deviceIs === null || p.device_id === deviceIs));
            calibrateEnd();
            /* The head follows the calibration: its device is the one this
               camera now looks through, its objective the one just made. */
            if (deviceIs !== null) setDevice(deviceIs);
            if (fresh) setObjective(fresh.id);
            else drawObjectives();
            report(t('„%s" ist geeicht. Das Eichbild liegt in deiner Cloud im Ordner „Kalibrierung".', name));
        } catch (e) {
            console.warn('Calibration not saved:', e);
            report(e?.message || t('Das Eichbild ist nicht in der Cloud angekommen. Die Vorlage ist nicht gespeichert.'));
        } finally {
            calSave.disabled = false;
        }
    }

    el('microscope-calibrate-start').addEventListener('click', calibrateStart);
    calShoot.addEventListener('click', calShootStill);
    calAgain.addEventListener('click', calLive);
    calCancel.addEventListener('click', () => {
        calibrateEnd();
        report('');
    });
    calSave.addEventListener('click', calibrateSave);
    for (const field of [calName, calLength, calNewDeviceName]) field.addEventListener('input', calReadiness);
    for (const radio of box.querySelectorAll('input[name="microscope-cal-unit"]')) radio.addEventListener('change', calReadiness);
    // The stage changes size with the window; the line follows.
    new ResizeObserver(() => { if (!calFrame.hidden) drawLine(); }).observe(lineCanvas);

    // --- The scale bar ---------------------------------------------------------

    /* THE CHOSEN OBJECTIVE'S SCALE OVER THE LIVE PICTURE
       (29.10.2026: „einen Maßstabsbalken eingeblendet … wie beim
       Fotobetrachter"). The same bar, the same 1-2-5 rule
       (measurestats.js) and the account's dress — one sees how large a
       spore is before the shutter falls, and the photo will carry the same
       scale. Only where it is true: no bar without an objective, none
       while the question „Welches Objektiv?" stands (the revolver just
       turned, the old scale is a guess), none while a calibration makes a
       new one. It sits in the PICTURE's corner, not the stage's — the
       picture stands letterboxed, and a bar on the empty ground would
       float beside it. While a stack grows, the live picture takes the
       lower right corner, so the bar steps to the left. */
    const stage = el('microscope-stage');
    const scaleBar = el('microscope-scalebar');
    const scaleBarLine = scaleBar.querySelector('.photo-scalebar-line');
    const scaleBarLabel = el('microscope-scalebar-label');
    {
        const dress = scaleStyle();
        scaleBar.style.color = dress.colour;
        scaleBar.style.fontSize = Math.round(dress.font * 0.9) + 'px';
        scaleBar.style.setProperty('--scalebar-seam', dress.seam);
        scaleBarLine.style.background = dress.colour;
    }

    /** The picture that stands big on the stage, with its own pixel size —
     *  null when none does. */
    function bigPicture() {
        if (!stackView.hidden) return { w: stackView.width, h: stackView.height };
        if (!video.hidden && !video.classList.contains('is-inset')) return { w: video.videoWidth, h: video.videoHeight };
        if (!live.hidden && !live.classList.contains('is-inset')) return { w: live.naturalWidth, h: live.naturalHeight };
        return null;
    }

    function drawScaleBar() {
        const preset = currentPreset();
        const picture = bigPicture();
        if (!box.open || calibrating || !preset || !message.hidden || !ask.hidden
            || !picture || !(picture.w > 0 && picture.h > 0)) {
            scaleBar.hidden = true;
            return;
        }
        // Contained, letterboxed — the arithmetic of object-fit: contain.
        const cw = stage.clientWidth, ch = stage.clientHeight;
        const k = Math.min(cw / picture.w, ch / picture.h);
        /* THE ZOOM (liveZoom): the picture magnified round the stage's
           middle and shifted; the bar measures the magnified picture and
           stands in the corner of what of it is in sight. */
        const z = liveZoom?.state() ?? { s: 1, tx: 0, ty: 0 };
        const shownWidth = picture.w * k * z.s, shownHeight = picture.h * k * z.s;
        const x0 = cw / 2 + z.tx - shownWidth / 2, y0 = ch / 2 + z.ty - shownHeight / 2;
        const seenLeft = Math.max(0, x0), seenRight = Math.min(cw, x0 + shownWidth);
        const seenBottom = Math.min(ch, y0 + shownHeight);
        /* The FIELD in µm is what all the camera's pictures share — the
           still, the binned preview and the stack's smaller canvas show the
           same slide, only in more or fewer pixels. The preset counts at
           its own width (scaleFor says why), else at the still's. */
        const fieldUm = preset.um_per_px * (preset.width || session?.still?.width || picture.w);
        const best = scaleBarLength(shownWidth / fieldUm, Math.min((seenRight - seenLeft) * 0.4, 280));
        if (best === null) {
            scaleBar.hidden = true;
            return;
        }
        scaleBarLine.style.width = Math.round(best.px) + 'px';
        scaleBarLabel.textContent = scaleLabel(best.um);
        const gap = 12;
        const left = !stackView.hidden;
        scaleBar.style.left = left ? (seenLeft + gap) + 'px' : '';
        scaleBar.style.right = left ? '' : (cw - seenRight + gap) + 'px';
        scaleBar.style.bottom = (ch - seenBottom + gap) + 'px';
        scaleBar.hidden = false;
    }

    /* Redrawn whenever the stage changes what it shows — a picture shown
       or hidden, the inset taken or left, the question put or answered —
       which the stage's own attributes tell, so no code path that switches
       one of them has to remember the bar. The bar's own switch is not
       listened to, or it would wake itself for ever. */
    let barQueued = false;
    function queueScaleBar() {
        if (barQueued) return;
        barQueued = true;
        requestAnimationFrame(() => { barQueued = false; drawScaleBar(); quickDraw(); });
    }
    new MutationObserver((records) => {
        if (records.some((r) => r.target !== scaleBar)) queueScaleBar();
    }).observe(stage, { subtree: true, attributes: true, attributeFilter: ['hidden', 'class'] });
    new ResizeObserver(queueScaleBar).observe(stage);

    // --- Quick measuring on the live picture --------------------------------

    /* A LINE AND LENGTH × BREADTH, KEPT NOWHERE (16.11.2026: „Die
       Webcam soll zwei temporäre Messmethoden erhalten … am Live-Bild mal
       kurz irgendwas gemessen und den Messspeicher … wieder gelöscht und
       irgendwo anderes was gemessen. Ganz einfach für zwischendurch mal
       schnell was nachschauen"). A rail in the picture's upper left corner:
       the statistic's switch on top — the statistic stands beside it, in
       that corner („weil die Statistik dann auch oben links in der Ecke
       angezeigt werden soll") —, the two tools, the bucket at the foot.

       IN MICROMETRES OF THE SLIDE, not in pixels: the points are kept as
       µm from the picture's upper left corner, through the objective's
       scale, so the binned preview, the full still and a growing stack —
       pictures of the same field in different pixel counts — all draw
       them in the same place, and a length is a length without converting.
       Another objective makes them meaningless: they go (setObjective),
       and with the window's session (stop).

       THE CLICKS ARE THE PHOTO WINDOW'S: a line from its start to its end,
       length × breadth as four points — the axis, then the outermost point
       on each side of it — and the tool stays in hand for the next, as
       measuring comes in series. Escape or a right click puts it down. The
       wheel still zooms; panning goes to the middle button while a tool is
       in hand, the left one places points. */
    const quickSvg = el('microscope-measures');
    const quickRail = el('microscope-measure-rail');
    const quickStats = el('microscope-measure-stats');
    const quickBanner = el('microscope-measure-banner');
    const quickButton = (name) => quickRail.querySelector(`[data-quick="${name}"]`);
    let quickTool = null;           // 'line' | 'rect' | null
    let quickPoints = [];           // the measure being set, µm
    let quickHover = null;          // the pointer, µm, while one is set
    const quickMeasures = [];       // {type, points}, µm
    let quickStatsShown = settings.get('microscopeQuickStats', true) !== false;

    /** Where the big picture stands on the stage and how many of its
     *  screen pixels make a micrometre — null where nothing may be measured. */
    function quickFrame() {
        const preset = currentPreset();
        const f = stageFrame();
        if (!f || !preset) return null;
        const fieldUm = preset.um_per_px * (preset.width || session?.still?.width || f.picture.w);
        return { x0: f.x0, y0: f.y0, pxPerUm: f.w / fieldUm };
    }
    /** Where the big picture stands on the stage, in the stage's pixels —
     *  null while none stands there or something else has the stage (a
     *  calibration, a message, the objective's question). No scale needed:
     *  the core area's circle goes by the picture alone. */
    function stageFrame() {
        const picture = bigPicture();
        if (!box.open || calibrating || view !== 'microscope' || !message.hidden || !ask.hidden
            || !picture || !(picture.w > 0 && picture.h > 0)) return null;
        // The scale bar's arithmetic (drawScaleBar): contained, zoomed round the middle, shifted.
        const cw = stage.clientWidth, ch = stage.clientHeight;
        const k = Math.min(cw / picture.w, ch / picture.h);
        const z = liveZoom?.state() ?? { s: 1, tx: 0, ty: 0 };
        const w = picture.w * k * z.s, h = picture.h * k * z.s;
        return { x0: cw / 2 + z.tx - w / 2, y0: ch / 2 + z.ty - h / 2, w, h, picture };
    }
    function quickUm(e, f) {
        const r = stage.getBoundingClientRect();
        return [(e.clientX - r.left - f.x0) / f.pxPerUm, (e.clientY - r.top - f.y0) / f.pxPerUm];
    }
    const quickNumber = (v) => v.toLocaleString('de-DE', { maximumFractionDigits: 1 });

    /** A length × breadth's corners from its four points: the axis a→b,
     *  the sides through c and d (the photo window's rectangle). */
    function quickCorners([a, b, c, d]) {
        const ax = b[0] - a[0], ay = b[1] - a[1];
        const len = Math.hypot(ax, ay) || 1;
        const nx = -ay / len, ny = ax / len;
        const o1 = (c[0] - a[0]) * nx + (c[1] - a[1]) * ny;
        const o2 = (d[0] - a[0]) * nx + (d[1] - a[1]) * ny;
        const at = (p, o) => [p[0] + nx * o, p[1] + ny * o];
        return [at(a, o1), at(b, o1), at(b, o2), at(a, o2)];
    }
    // µm per „pixel" is one: the points are micrometres already (rectDims' scale).
    const QUICK_SCALE = { line: [[0, 0], [1, 0]], um: 1 };
    const quickLength = ([a, b]) => Math.hypot(b[0] - a[0], b[1] - a[1]);

    function quickDraw() {
        const f = quickFrame();
        const there = f !== null;
        // The rail stands with the picture; what measures, with the scale.
        const frame = stageFrame();
        quickRail.hidden = frame === null;
        for (const n of quickRail.querySelectorAll('[data-needs-scale]')) n.hidden = !there;
        zoneDraw(frame);
        stage.classList.toggle('is-measuring', there && quickTool !== null);
        for (const name of ['line', 'rect']) quickButton(name).classList.toggle('is-active', quickTool === name);
        const stats = quickButton('stats');
        stats.classList.toggle('is-active', quickStatsShown);
        const statsWord = quickStatsShown ? t('Statistik ausblenden') : t('Statistik zeigen');
        stats.title = statsWord;
        stats.setAttribute('aria-label', statsWord);
        quickButton('clear').disabled = quickMeasures.length === 0 && quickPoints.length === 0;
        quickBanner.hidden = !there || quickTool === null;
        if (!quickBanner.hidden) {
            quickBanner.textContent = quickTool === 'line'
                ? t('Klicke auf den Anfang, dann auf das Ende.')
                : t('Vier Punkte: erst die Längsachse (2), dann die Breite (2) – je den äußersten Punkt links und rechts der Achse.');
        }
        quickStatsDraw(there);
        if (!there) {
            measureLayer.innerHTML = '';
            return;
        }
        const dress = scaleStyle();
        const at = ([x, y]) => [f.x0 + x * f.pxPerUm, f.y0 + y * f.pxPerUm];
        const pts = (list) => list.map((p) => at(p).map((v) => v.toFixed(1)).join(',')).join(' ');
        const seamed = (tag, attrs, width = 1.6) =>
            `<${tag} ${attrs} fill="none" stroke="${dress.seam}" stroke-width="${width + 2}" stroke-linejoin="round"/>`
            + `<${tag} ${attrs} fill="none" stroke="${dress.colour}" stroke-width="${width}" stroke-linejoin="round"/>`;
        const words = (p, text) => {
            const [x, y] = at(p);
            return `<text x="${x.toFixed(1)}" y="${y.toFixed(1)}" text-anchor="middle" font-size="13" font-weight="600"
                fill="${dress.colour}" stroke="${dress.seam}" stroke-width="3" paint-order="stroke">${text}</text>`;
        };
        let inner = '';
        for (const m of quickMeasures) {
            if (m.type === 'line') {
                inner += seamed('polyline', `points="${pts(m.points)}"`);
                const [a, b] = m.points;
                inner += words([(a[0] + b[0]) / 2, (a[1] + b[1]) / 2 - 10 / f.pxPerUm], `${quickNumber(quickLength(m.points))} µm`);
            } else {
                const c = quickCorners(m.points);
                inner += seamed('polygon', `points="${pts(c)}"`);
                const d = rectDims({ type: 'rect', points: m.points }, QUICK_SCALE);
                const top = c.reduce((best, p) => (p[1] < best[1] ? p : best));
                const mid = [c.reduce((s, p) => s + p[0], 0) / 4, top[1] - 8 / f.pxPerUm];
                if (d) inner += words(mid, `${quickNumber(d.L)} × ${quickNumber(d.B)} µm`);
            }
        }
        // The measure being set, with the pointer as its next point.
        const set = quickHover ? [...quickPoints, quickHover] : quickPoints;
        if (set.length >= 2) {
            if (quickTool === 'rect' && set.length >= 3) {
                // The axis stays while the side rides along — as in the photo window (fourClickGuide).
                inner += seamed('polyline', `points="${pts(set.slice(0, 2))}"`, 1.2);
                inner += seamed('polygon', `points="${pts(quickCorners([...set, set[set.length - 1]].slice(0, 4)))}"`, 1.2);
            } else {
                inner += seamed('polyline', `points="${pts(set.slice(0, 2))}"`, 1.2);
                if (quickTool === 'line') {
                    const [a, b] = set;
                    inner += words([(a[0] + b[0]) / 2, (a[1] + b[1]) / 2 - 10 / f.pxPerUm], `${quickNumber(quickLength(set))} µm`);
                }
            }
        }
        for (const p of quickPoints) {
            const [x, y] = at(p);
            inner += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="3" fill="${dress.colour}" stroke="${dress.seam}" stroke-width="1.5"/>`;
        }
        measureLayer.innerHTML = inner;
    }

    // --- The core area: a circle one sets for oneself ------------------------

    /* A RED DASHED CIRCLE OVER THE LIVE PICTURE (22.11.2026: „ich habe nur
       einen Kernbereich, den ich benutzen kann. Der Rest hat außen extrem
       starke chromatische Aberration … so eine kleine rot gestrichelten
       Kreis irgendwo setzen … verschieben, in der Größe ändern, dass ich
       für mich so sehe, okay, in diesem Bereich kann ich Sporen oder
       Messungen vornehmen"). Camera and objective that do not suit each
       other draw cleanly in the middle only; the circle marks that middle,
       so a spore is brought INTO it before it is photographed or measured.

       A MARK FOR THE EYE, NOTHING ELSE: it is in no photo, crops nothing,
       and no measure asks it. It lies on the page over the picture.

       IN FRACTIONS OF THE PICTURE — the centre of its width and height,
       the radius of its width — because the fault is the optics' and sits
       in the field: the binned preview, the still and a growing stack show
       the same field in different pixel counts, and the zoom moves the
       circle with the picture.

       PER OBJECTIVE, WITH THE LAST ONE SET AS THE START: how far the clean
       middle reaches differs from objective to objective. An objective
       that has no circle of its own shows the one set last, so whoever
       needs only one never notices; whoever moves it under another
       objective has given that one its own. Kept by this computer, like
       the objective: the microscope stands at it.

       THE LINE MOVES IT, THE SQUARE ON IT SIZES IT — the middle stays
       free, it is where one looks. Neither catches the pointer while a
       measuring tool is in hand. */
    const ZONE_COLOUR = '#ff1f1f';   // the picture's red, as free measuring's (photowindow.js)
    const ZONE_START = { x: 0.5, y: 0.5, r: 0.25 };
    const SVG_SPACE = 'http://www.w3.org/2000/svg';
    const zoneLayer = document.createElementNS(SVG_SPACE, 'g');
    zoneLayer.setAttribute('class', 'microscope-zone');
    const measureLayer = document.createElementNS(SVG_SPACE, 'g');
    quickSvg.append(zoneLayer, measureLayer);
    let zoneKept = settings.get('microscopeZone', null) ?? {};
    let zoneShown = zoneKept.on === true;
    let zoneDrag = null;            // { kind, startX, startY, from, now } while one is dragged
    const zoneValid = (z) => z && [z.x, z.y, z.r].every((v) => Number.isFinite(v)) && z.r > 0;
    const zoneKey = () => String(objectiveId ?? 'none');
    function zoneNow() {
        const own = zoneKept.by?.[zoneKey()];
        return zoneValid(own) ? own : zoneValid(zoneKept.last) ? zoneKept.last : ZONE_START;
    }
    function zoneKeep(z = null) {
        zoneKept = {
            on: zoneShown,
            last: z ?? zoneKept.last ?? null,
            by: z ? { ...(zoneKept.by ?? {}), [zoneKey()]: z } : (zoneKept.by ?? {}),
        };
        settings.set('microscopeZone', zoneKept);
    }
    function zoneDraw(frame) {
        const button = quickButton('zone');
        button.classList.toggle('is-active', zoneShown);
        const word = zoneShown ? t('Kernbereich ausblenden') : t('Kernbereich zeigen');
        button.title = word;
        button.setAttribute('aria-label', word);
        zoneLayer.classList.toggle('is-locked', quickTool !== null);
        if (!frame || !zoneShown) {
            zoneLayer.innerHTML = '';
            return;
        }
        const z = zoneDrag?.now ?? zoneNow();
        const cx = frame.x0 + z.x * frame.w, cy = frame.y0 + z.y * frame.h, r = z.r * frame.w;
        const at = `cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="${r.toFixed(1)}" fill="none"`;
        // The size grip on the lower right of the line; away while a tool is in hand.
        const gx = cx + r * Math.SQRT1_2, gy = cy + r * Math.SQRT1_2;
        zoneLayer.innerHTML = `<circle ${at} stroke="${ZONE_COLOUR}" stroke-width="1.6" stroke-dasharray="9 6"/>`
            + `<circle class="microscope-zone-grab" data-zone="move" ${at} stroke="transparent" stroke-width="14"/>`
            + (quickTool === null
                ? `<rect class="microscope-zone-grab" data-zone="size" x="${(gx - 5.5).toFixed(1)}" y="${(gy - 5.5).toFixed(1)}"
                    width="11" height="11" fill="${ZONE_COLOUR}" stroke="#fff" stroke-width="1.2"/>`
                : '');
    }
    /* IN THE CAPTURE PHASE, like the measuring tools' clicks below: the
       zoom on the same stage must not take the press for a pan, nor the
       release for a tap. The drag listens on the window — the circle is
       redrawn at every move, and the element the press landed on is gone
       by then. */
    stage.addEventListener('pointerdown', (e) => {
        const kind = e.target.closest?.('[data-zone]')?.dataset.zone;
        if (!kind || e.button !== 0 || quickTool !== null) return;
        const frame = stageFrame();
        if (!frame) return;
        e.stopPropagation();
        e.preventDefault();
        zoneDrag = { kind, startX: e.clientX, startY: e.clientY, from: zoneNow(), now: zoneNow() };
        const move = (ev) => {
            const f = stageFrame();
            if (!f || !zoneDrag) return;
            const from = zoneDrag.from;
            if (zoneDrag.kind === 'move') {
                // The centre stays in the picture, so the circle cannot be lost beyond its edge.
                zoneDrag.now = {
                    x: Math.min(1, Math.max(0, from.x + (ev.clientX - zoneDrag.startX) / f.w)),
                    y: Math.min(1, Math.max(0, from.y + (ev.clientY - zoneDrag.startY) / f.h)),
                    r: from.r,
                };
            } else {
                const rect = stage.getBoundingClientRect();
                const dx = ev.clientX - rect.left - (f.x0 + from.x * f.w);
                const dy = ev.clientY - rect.top - (f.y0 + from.y * f.h);
                zoneDrag.now = { x: from.x, y: from.y, r: Math.min(1, Math.max(0.03, Math.hypot(dx, dy) / f.w)) };
            }
            zoneDraw(f);
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            const set = zoneDrag?.now;
            zoneDrag = null;
            if (set) zoneKeep({ x: +set.x.toFixed(4), y: +set.y.toFixed(4), r: +set.r.toFixed(4) });
            zoneDraw(stageFrame());
            // The click that follows the release is no tap on the picture.
            zoneReleased = true;
            setTimeout(() => { zoneReleased = false; }, 0);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    }, true);
    let zoneReleased = false;
    stage.addEventListener('click', (e) => {
        if (zoneReleased || e.target.closest?.('[data-zone]')) e.stopPropagation();
    }, true);

    /** The statistic in the upper left corner: the lines' span and mean,
     *  length × breadth as the photo window states it (valueLines). */
    function quickStatsDraw(there) {
        const lines = quickMeasures.filter((m) => m.type === 'line').map((m) => quickLength(m.points));
        const rows = quickMeasures.filter((m) => m.type === 'rect')
            .map((m) => rectDims({ type: 'rect', points: m.points }, QUICK_SCALE)).filter(Boolean)
            .map((d) => ({ L: d.L, B: d.B, Q: d.L / d.B }));
        quickStats.hidden = !there || !quickStatsShown || (lines.length === 0 && rows.length === 0);
        if (quickStats.hidden) return;
        quickStats.textContent = '';
        const add = (tag, text, quiet = false) => {
            const n = document.createElement(tag);
            n.textContent = text;
            if (quiet) n.className = 'is-quiet';
            quickStats.append(n);
        };
        if (lines.length > 0) {
            add('b', t('Strecke'));
            if (lines.length === 1) {
                add('div', `${quickNumber(lines[0])} µm`);
            } else {
                add('div', `${quickNumber(Math.min(...lines))} – ${quickNumber(Math.max(...lines))} µm`);
                add('div', `Ø ${quickNumber(lines.reduce((s, v) => s + v, 0) / lines.length)} µm · N = ${lines.length}`, true);
            }
        }
        if (rows.length > 0) {
            add('b', t('Länge × Breite'));
            for (const line of valueLines(rows)) add('div', line.text, line.quiet);
        }
    }

    function quickArm(tool) {
        quickTool = quickTool === tool ? null : tool;
        quickPoints = [];
        quickHover = null;
        quickDraw();
    }
    function quickClear() {
        if (!quickReady) return;
        quickMeasures.length = 0;
        quickPoints = [];
        quickHover = null;
        quickDraw();
    }
    quickRail.addEventListener('click', (e) => {
        // The rail's clicks are no taps on the picture: the zoom's double tap would fit it.
        e.stopPropagation();
        const name = e.target.closest('[data-quick]')?.dataset.quick;
        if (name === 'stats') {
            quickStatsShown = !quickStatsShown;
            settings.set('microscopeQuickStats', quickStatsShown);
            quickDraw();
        } else if (name === 'zone') {
            zoneShown = !zoneShown;
            zoneKeep();
            quickDraw();
        } else if (name === 'line' || name === 'rect') {
            quickArm(name);
        } else if (name === 'clear') {
            quickClear();
        }
    });
    /* IN THE CAPTURE PHASE, before the zoom on the same stage hears it: with
       a tool in hand the left button places a point and pans nothing, and
       its clicks are no double tap. The middle button still pans. */
    stage.addEventListener('pointerdown', (e) => {
        if (quickTool === null || e.button !== 0 || e.target.closest?.('.microscope-measure-rail, button, input')) return;
        const f = quickFrame();
        if (!f) return;
        e.stopPropagation();
        e.preventDefault();
        quickPoints.push(quickUm(e, f));
        const need = quickTool === 'line' ? 2 : 4;
        if (quickPoints.length >= need) {
            quickMeasures.push({ type: quickTool, points: quickPoints });
            quickPoints = [];
            quickHover = null;
        }
        quickDraw();
    }, true);
    stage.addEventListener('click', (e) => {
        if (quickTool !== null && !e.target.closest?.('.microscope-measure-rail')) e.stopPropagation();
    }, true);
    stage.addEventListener('pointermove', (e) => {
        if (quickTool === null || quickPoints.length === 0) return;
        const f = quickFrame();
        if (!f) return;
        quickHover = quickUm(e, f);
        quickDraw();
    });
    // A right click puts the tool down — the photo window's gesture.
    stage.addEventListener('contextmenu', (e) => {
        if (quickTool === null) return;
        e.preventDefault();
        quickArm(quickTool);
    });
    // Escape: first the measure being set, then the tool — never the window.
    document.addEventListener('keydown', (e) => {
        if (!box.open || e.key !== 'Escape' || quickTool === null) return;
        e.preventDefault();
        e.stopPropagation();
        if (quickPoints.length > 0) {
            quickPoints = [];
            quickHover = null;
            quickDraw();
        } else {
            quickArm(quickTool);
        }
    }, true);
    quickReady = true;
    // The first frame brings the picture's size.
    live.addEventListener('load', queueScaleBar);
    video.addEventListener('loadedmetadata', queueScaleBar);

    /* THE SPACE BAR RELEASES THE SHUTTER (26.10.2026: „mit Space
       soll man auch aufnehmen auslösen") — the eye stays at the eyepiece or
       on the picture, the hand on the stage. While a calibration waits for
       its photo, it takes that one. Never in a field one types into, and
       never a held key's repeats: one press, one picture. Taken over from
       the focused control too, so a ticked box or a pressed objective does
       not take the press for itself. */
    /* ON THE WHOLE PAGE, NOT ON THE WINDOW: when the focused control goes
       away — the objective's button with its question — the focus falls
       back to the page, and a listener on the window would hear nothing
       more (measured, 26.10.2026). So the page listens while the window is
       open, and leaves the press to any window lying over it (the subject
       window, the shrink warning). */
    document.addEventListener('keydown', (e) => {
        if (!box.open || view !== 'microscope' || e.key !== ' ' || e.repeat || e.ctrlKey || e.metaKey || e.altKey) return;
        const target = e.target;
        const inDialog = target instanceof Element ? target.closest('dialog') : null;
        if (inDialog && inDialog !== box) return;
        if (target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement
            || (target instanceof HTMLInputElement && !['checkbox', 'radio', 'range', 'button'].includes(target.type))) {
            return;
        }
        e.preventDefault();
        /* And the control that had the focus lets go of it — pressed from
           the keyboard, its ring would stay standing as if it meant
           something. */
        if (target instanceof HTMLElement && target !== box) target.blur();
        if (calibrating) {
            if (!calShoot.hidden && !calShoot.disabled) calShoot.click();
            return;
        }
        // Held, a stack; let go early, a photo (the stack, above).
        pressStart();
    });
    document.addEventListener('keyup', (e) => {
        if (e.key === ' ' && press) {
            e.preventDefault();
            pressEnd();
        }
    });

    box.addEventListener('close', () => {
        /* The event comes a moment after close() — a window opened again
           in between belongs to a new session, which must live. */
        if (box.open) return;
        // Stepped aside for the page behind: the session lives on.
        if (view === 'aside') return;
        if (calibrating) calibrateEnd();
        stop();
        endViews();
        report('');
        areas?.ended?.();
    });

    // --- Fund · Mikroskop · Schlüssel -------------------------------------------

    /* ONE CLICK BETWEEN THE THREE, AND THE CAMERA RUNS ON
       (05.11.2026: „ich muß ständig dazwischen wechseln beim schlüsseln und
       der weg wie es heute geht ist zu umständlich" — every way led over
       another page, and leaving the window ended the camera's session and
       asked for the objective again).

       THE WINDOW STEPS ASIDE, IT DOES NOT CLOSE. „Fund" and „Schlüssel"
       both mean the page behind: the find's pictures with the viewer and
       everything the page does with them, or the key in its frame there
       (pages/finds.php). The dialog is closed for that — a modal one lets
       nothing behind it be touched — and the close handler above knows the
       difference: the session, the stream and the objective stay. Only the
       arrow and Escape end the camera.

       UNTIL 24.11.2026 THE KEY STOOD INSIDE THIS WINDOW, in a frame of its
       own, and died with it: the window closed, the key's page was thrown
       away and loaded anew the next time. It moved into the page („den
       Schlüssel direkt aus dem Mikroskop brauchen wir dann nicht mehr … wir
       switchen einfach nur hin und her"), and with it went the message
       listener and the confirmed species' way through this file — the page
       hears the key itself now (finds.js, areas).

       Space shoots only while the window is in front; stepped aside it is
       the page's own key. */
    let view = 'microscope';    // or 'aside'
    let pointNow = 0;           // the find the window works for
    const views = el('microscope-views');
    const backBar = el('microscope-return');

    /* AN AREA OF THE PAGE, WHERE THE PAGE HAS AREAS (24.11.2026: „momentan
       ist das Mikroskop ja ein Overlay-Modal. Das könnten wir dann quasi in
       den Inhalt packen, wie den Schlüssel"). In the Fundbrowser the find's
       bar with the row Fund · Mikroskop · Schlüssel is to stay the same in
       all three; a full-screen window over it brought a second head with a
       second row. So there the <dialog> stands inside the page (finds.js
       moves it) and is opened with show() — no backdrop, nothing made
       inert, the find's bar above it alive. Everything else is the same
       window: the same session, the same shutter, the same stepping aside.

       What the head held besides the name becomes the column's heading:
       the device with its gear, at the top.

       THE CAMERA ENDS WITH THE FIND, NOT BY A BUTTON. The arrow that ended
       it is gone with the head, and a „Kamera aus" stood in its place for
       an hour; asked whether it is needed, the maintainer chose the rule
       instead (24.11.2026: „die Kamera geht sie aus, wenn ich den Fund
       verlasse. Dann könnten wir ja so ein automatisches Ausmachen und
       bräuchten den Button nicht"). The page calls end() when the find is
       left (finds.js); between the find's areas the camera runs on. That
       also settles what a camera does at the NEXT find: it is switched on
       there anew, and its pictures are that find's. On the map nothing
       changes — there the arrow ends it. */
    const inPage = areas?.inPage === true;
    const raise = () => (inPage ? box.show() : box.showModal());
    if (inPage) {
        const top = el('microscope-side-top');
        top.append(box.querySelector('.microscope-head-end'));
        top.hidden = false;
    }

    /* THE SIGN THAT THE CAMERA RUNS while the window stands aside: a class
       on the body, and the stylesheet paints it on the switch row's
       „Mikroskop" (app.css) — the row is the page's, and so is the sign.
       ONLY WHILE A PICTURE COMES (28.11.2026: „kam die Nachricht, das
       Kameraprogramm antwortet nicht. Dennoch wird mir aber beim
       Verlassen des Mikroskops ein grüner Punkt … angezeigt"). It asked
       for the session alone, and a session stands from the first try on —
       through „antwortet nicht", „nicht angesteckt" and every other
       sentence. Now it asks whether the stage shows the camera. */
    function markLive() {
        document.body.classList.toggle('is-microscope-live', session !== null && picturing && view === 'aside');
    }

    /**
     * Aside for the rest of the page — the find itself, or its key.
     *
     * @param {?string} to  the area the page is to show; null when the page
     *        asked for this itself and knows where it is going
     * @returns {boolean} false while a calibration holds the window
     */
    function stepAside(to = null) {
        if (calibrating) return false;
        if (view === 'aside' || !box.open) return true;
        view = 'aside';
        backBar.hidden = false;
        hideMediumInfo();
        markLive();
        box.close();
        if (to !== null) areas?.show?.(to, pointNow);
        return true;
    }

    /** In front again, as it was left. */
    function comeBack() {
        if (view !== 'aside') return;
        view = 'microscope';
        backBar.hidden = true;
        markLive();
        if (!box.open) raise();
        queueScaleBar();
    }

    /**
     * The camera's end, asked for by the page — the find is being left.
     * AT ONCE, not through the close event: that one comes a moment after
     * close(), and by then the next find may stand in the page. The
     * handler above runs afterwards all the same and finds nothing left
     * to do.
     */
    function end() {
        if (calibrating) calibrateEnd();
        stop();
        endViews();
        report('');
        if (box.open) box.close();
        areas?.ended?.();
    }
    areas?.bind?.({ aside: () => stepAside(), end });

    /** The window closed for good: nothing stands aside any more. */
    function endViews() {
        view = 'microscope';
        backBar.hidden = true;
        markLive();
    }

    views.addEventListener('click', (e) => {
        const b = e.target.closest('[data-area]');
        if (b && b.dataset.area !== 'microscope') stepAside(b.dataset.area);
    });
    /* THE WAY BACK ASKS THE BUTTON, NOT THE WINDOW: „Mikroskopieren" knows
       which find stands in the page by now (photogallery.js), and open()
       below takes that find over. Where it cannot answer — no find chosen
       — the window comes back for the find it had. */
    el('microscope-return-button').addEventListener('click', () => {
        if (!button.hidden && !button.disabled) button.click();
        if (view === 'aside') comeBack();
    });

    /** The find in the window's head — and whether its key can be reached from here. */
    function setPoint(title, pointId) {
        // The species itself, large in the head (gallery_windows.php) — no „An „…"" around it any more.
        el('microscope-point').textContent = title || t('Ohne Titel');
        pointNow = pointId;
        // The third button only where the page behind has a key area for this find.
        const keyButton = views.querySelector('[data-area="key"]');
        if (keyButton) keyButton.hidden = !(pointId > 0 && areas?.keyOffered?.());
    }

    /**
     * Open the window for a find.
     *
     * @param {string} title  the find's title — the window's heading
     */
    function open(title, pointId = 0) {
        /* „Mikroskop" pressed while the window stands aside: it comes back
           as it was — the camera's session lives, and a fresh start would
           ask for the objective again.

           FOR WHICHEVER FIND STANDS IN THE PAGE BY NOW (24.11.2026: „wenn
           die Kamera an ist und ich wechsle zu einem anderen Fund, wird
           dann ab da zu diesem Fund gespeichert"). Before, another find
           chosen meanwhile ended the session and began a new one — or,
           through the bar at the bottom, led back to the OLD find, whose
           name stood in the head while the page showed another. The find
           is the page's to say; the camera only follows. A still already
           on its way keeps the find it was taken for (photogallery.js). */
        if (view === 'aside' && session) {
            setPoint(title, pointId);
            comeBack();
            return;
        }
        endViews();
        setPoint(title, pointId);
        report('');
        drawObjectives();
        drawMedia();
        light.disabled = true;
        raise();
        connect();
    }

    return { open };
}
