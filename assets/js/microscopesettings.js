/**
 * Einstellungen → Fotos → Mikroskop-Kamera: setting THIS computer up with
 * its microscope camera (24.10.2026) — the door to the button
 * „Mikroskopieren" (microscope.js says why it is the device's, not the
 * account's).
 *
 * TWO WAYS TO A CAMERA, as in the window. A camera program answers on
 * 127.0.0.1 („Kamera-Programm suchen"; askMicroCam reads the answer for
 * the window and for this tab alike), or the camera is a webcam the
 * browser sees itself („Als Webcam einrichten"): every camera it knows,
 * the picture of the chosen one, and „Diese Kamera nehmen".
 *
 * ASKED BY ITSELF ONLY WHERE A PROGRAM IS SET UP on this device. The
 * first question to 127.0.0.1 brings the browser's permission prompt
 * (Local Network Access); somebody without a microscope who merely opens
 * the tab must not meet it. A webcam is never switched on by looking at
 * the tab either — its light would come on.
 *
 * SELF-ATTACHING like calibrate.js: the administration renders its tab
 * panels lazily, so the section is not there when this module runs.
 */

import { t } from './i18n.js';
import {
    askMicroCam, microscopeSource, microscopeWebcam, setMicroscopeSource,
    forgetMicroscope, openWebcam, listWebcams,
} from './microscope.js';

function setUp(section) {
    if (section.dataset.bound) return;
    section.dataset.bound = '1';

    const el = (id) => document.getElementById(id);
    const step    = el('microscope-setup-camera');
    const state   = el('microscope-camera-state');
    const detail  = el('microscope-camera-detail');
    const check   = el('microscope-camera-check');
    const webcamStart = el('microscope-webcam-start');
    const forget  = el('microscope-camera-forget');
    const choice  = el('microscope-webcam-choice');
    const list    = el('microscope-webcam-list');
    const preview = el('microscope-webcam-preview');
    const takeCam = el('microscope-webcam-take');
    const result  = el('microscope-setup-result');
    const cloud   = section.dataset.cloud === '1';
    let running = null;
    let stream = null;

    function say(text, more = '') {
        state.textContent = text;
        detail.textContent = more;
        detail.hidden = more === '';
    }

    /* The camera point's mark, the „vergessen" button, and the sentence
       under the three points — what the setup has opened, or what still
       stands in the way. */
    function drawSetup() {
        const source = microscopeSource();
        step.classList.toggle('is-done', source !== null);
        forget.hidden = source === null;
        result.textContent = source !== null && cloud
            ? t('Fertig. Bei jedem Fund steht unter Belege jetzt „Mikroskopieren".')
            : t('„Mikroskopieren" erscheint bei den Funden, sobald Kamera und Cloud eingerichtet sind.');
        result.classList.toggle('is-done', source !== null && cloud);
    }

    function stopPreview() {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        preview.srcObject = null;
    }

    /* What the camera itself is doing — the same states the window knows,
       in the same words. */
    const CAMERA = {
        absent:       () => t('Die Kamera ist nicht angesteckt.'),
        stalled:      () => t('Die Kamera ist belegt. Wahrscheinlich ist das Programm des Herstellers noch offen, bei BMS ist das BMS Pix 3.'),
        error:        () => t('Die Kamera meldet einen Fehler. Stecke sie einmal ab und wieder an.'),
        reconnecting: () => t('Die Kamera verbindet sich gerade neu.'),
        frozen:       () => t('Die Kamera ist hängen geblieben. Stecke sie ab, warte kurz und stecke sie wieder an.'),
    };

    /** Look for a camera program — and set the computer up with it when one answers. */
    async function look() {
        running?.abort();
        const ask = new AbortController();
        running = ask;
        check.disabled = true;
        say(t('Sehe nach …'));
        let asked;
        try {
            asked = await askMicroCam({
                signal: ask.signal,
                onSilent: () => say(t('Der Browser fragt nach Zugriff auf Geräte in diesem Netzwerk – bitte Erlauben.'),
                    t('Die Frage steht oben links bei der Adressleiste. Sie kommt nur einmal.')),
            });
        } catch {
            return;
        } finally {
            if (running === ask) check.disabled = false;
        }
        if (running !== ask) return;
        if (asked.kind === 'refused') {
            say(t('Auf diesem Computer antwortet kein Kamera-Programm.'),
                t('Läuft das Programm, und der Browser hat den Zugriff auf Geräte in diesem Netzwerk abgelehnt? Dann klicke links in der Adressleiste auf das Schloss und erlaube ihn. Ist deine Kamera eine Webcam, nimm „Als Webcam einrichten".'));
        } else if (asked.kind === 'forbidden') {
            say(t('Das Kamera-Programm lässt diese Seite nicht zu.'),
                t('Es arbeitet nur mit wkpilz.de zusammen. Öffne YASAIMAPP dort.'));
        } else if (asked.kind === 'error') {
            say(t('Das Kamera-Programm meldet einen Fehler.'));
        } else {
            setMicroscopeSource('program');
            const s = asked.status ?? {};
            const program = [s.app || t('Kamera-Programm'), s.version].filter(Boolean).join(' ');
            const camera = s.model
                ? (s.still?.width > 0
                    ? t('Kamera: %s, Fotos mit %d × %d Pixeln.', s.model, s.still.width, s.still.height)
                    : t('Kamera: %s.', s.model))
                : '';
            say(t('Kamera: eingerichtet mit %s.', program), [camera, CAMERA[s.state]?.() ?? ''].filter(Boolean).join(' '));
        }
        drawSetup();
    }

    /** The webcam's state as it is remembered — without switching it on. */
    function showWebcam() {
        const w = microscopeWebcam();
        say(t('Kamera: eingerichtet als Webcam „%s".', w?.label ?? t('Kamera')));
    }

    async function webcamChoice() {
        say(t('Sehe nach …'));
        stopPreview();
        /* ASKED ONCE FOR ANY CAMERA FIRST: until the browser may use a
           camera, it names none — the list would be „Kamera 1, Kamera 2". */
        try {
            stream = await openWebcam(null);
        } catch (e) {
            say(e.name === 'NotAllowedError'
                ? t('Der Browser darf die Kamera nicht benutzen.')
                : t('Dieser Computer hat keine Webcam, die der Browser sieht.'),
                e.name === 'NotAllowedError'
                    ? t('Klicke links in der Adressleiste auf das Kamera-Zeichen oder das Schloss und erlaube die Kamera.')
                    : t('Dann braucht deine Kamera ein Programm. Die Anleitung weiter unten hilft dabei.'));
            return;
        }
        const cams = await listWebcams();
        const current = stream.getVideoTracks()[0]?.getSettings().deviceId ?? cams[0]?.deviceId;
        list.innerHTML = '';
        for (const cam of cams) {
            const row = document.createElement('label');
            row.className = 'choice-row';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'microscope-webcam';
            radio.value = cam.deviceId;
            radio.dataset.label = cam.label;
            radio.checked = cam.deviceId === current;
            radio.addEventListener('change', () => showPreview(cam.deviceId));
            const name = document.createElement('span');
            name.textContent = cam.label;
            row.append(radio, name);
            list.append(row);
        }
        preview.srcObject = stream;
        preview.play().catch(() => {});
        choice.hidden = false;
        say(t('Wähle die Kamera, die am Mikroskop steckt. Ihr Bild steht darunter.'));
    }

    async function showPreview(deviceId) {
        stopPreview();
        try {
            stream = await openWebcam(deviceId);
            preview.srcObject = stream;
            preview.play().catch(() => {});
        } catch {
            say(t('Diese Kamera lässt sich gerade nicht öffnen.'));
        }
    }

    takeCam.addEventListener('click', () => {
        const chosen = list.querySelector('input[name="microscope-webcam"]:checked');
        if (!chosen) return;
        const settings = stream?.getVideoTracks()[0]?.getSettings() ?? {};
        /* The size it runs in now — the largest it has — is the size it is
           asked for from here on (openWebcam). */
        setMicroscopeSource('webcam', { deviceId: chosen.value, label: chosen.dataset.label,
            width: settings.width ?? 0, height: settings.height ?? 0 });
        stopPreview();
        choice.hidden = true;
        showWebcam();
        if (settings.width > 0) {
            detail.textContent = t('Fotos mit %d × %d Pixeln.', settings.width, settings.height);
            detail.hidden = false;
        }
        drawSetup();
    });

    check.addEventListener('click', look);
    webcamStart.addEventListener('click', webcamChoice);
    forget.addEventListener('click', () => {
        forgetMicroscope();
        stopPreview();
        choice.hidden = true;
        say(t('Kamera: noch nicht eingerichtet.'));
        drawSetup();
    });

    /* The tab's panel is shown by taking away `hidden` — the moment to
       ask, where a program is set up; hidden again, a running webcam
       preview goes out. */
    function whenShown() {
        if (section.hidden) {
            stopPreview();
            choice.hidden = true;
            return;
        }
        const source = microscopeSource();
        if (source === 'program') look();
        else if (source === 'webcam') showWebcam();
        drawSetup();
    }
    let shown = !section.hidden;
    new MutationObserver(() => {
        if (section.hidden !== !shown) {
            shown = !section.hidden;
            whenShown();
        }
    }).observe(section, { attributes: true, attributeFilter: ['hidden'] });
    drawSetup();
    if (shown) whenShown();
}

new MutationObserver(() => {
    const section = document.getElementById('section-microscope-camera');
    if (section !== null) setUp(section);
}).observe(document.body, { childList: true, subtree: true });
