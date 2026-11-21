/**
 * Einstellungen → Fotos → Export: how a developed picture leaves the app
 * (08.11.2026: „bei Einstellungen, Fotos, und hier bei einem
 * neuen Tab Copyright oder Export … mehrere Copyright-Designs dort
 * konfigurieren … mit Schriftart, Schriftgröße dann Transparenz", and the
 * author and the logo „mit rein").
 *
 * THREE BLOCKS, by weight (CLAUDE.md, „a page is sorted by weight"): the
 * author and the rights line, the file name, the watermark templates. The
 * templates are a list — one chosen at a time, edited beside a preview that
 * draws with the very function the export draws with (exportmark.js), so
 * what one sees here is what the file gets.
 *
 * THE WHOLE STYLE IS SAVED AT ONCE through api/v1/measure_style.php
 * {export: …}; the save button is quiet until something changed. A logo is
 * uploaded the moment it is picked (api/v1/export_logo.php) — drawn by the
 * browser into a PNG of at most 1024 px first — and lives in the template
 * once saved; one never saved is tidied away by the server.
 *
 * SELF-ATTACHING like microscopesettings.js: the administration renders its
 * tab panels lazily, so the section is not there when this module runs.
 */

import { t } from './i18n.js';
import { request } from './api.js';
import { toast } from './toast.js';
import { drawMark, loadLogo, placeholderButtons } from './exportmark.js';
import { iconHtml } from './icons.js';
import { confirm } from './dialogs.js';

const PLACES = ['tl', 'tc', 'tr', 'ml', 'mc', 'mr', 'bl', 'bc', 'br'];
const LOGO_EDGE = 1024;

/** A new template — the server's EXPORT_MARK_DEFAULTS, the name counted on. */
function markNew(n) {
    return {
        id: 'm' + Date.now().toString(36).slice(-6) + Math.floor(Math.random() * 36).toString(36),
        name: t('Vorlage %d', n),
        hint: { on: true, text: '© {Urheber} {Jahr}', font: 'sans', place: 'br', size: 2.5, color: 'auto', opacity: 85, shadow: true },
        grid: { on: false, text: '', font: 'sans', angle: -30, gap: 150, size: 4, color: 'white', opacity: 15 },
        logo: { on: false, file: '', place: 'bl', size: 15, opacity: 80 },
    };
}

/* --- Small builders ----------------------------------------------------- */

function el(tag, attrs = {}, ...kids) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else n.setAttribute(k, v);
    }
    n.append(...kids.filter((k) => k !== null && k !== undefined));
    return n;
}

/* ONE LINE PER SETTING AT THE DESK (08.11.2026: „vieles
   einzeilig machen. Also das Label vorne dran, und hinten dran dann das
   Inputfeld. Jedenfalls auf dem Desktop ist da der Platz mehr als
   ausreichend"). Every builder makes the same row — the caption in a
   column of its own, the control beside it, answers side by side — and
   the stylesheet stacks it again on the phone (.export-row, app.css). */
let rowSeq = 0;
function row(caption, control, { group = false } = {}) {
    const id = `export-row-${++rowSeq}`;
    const label = group
        ? el('span', { class: 'export-row-label', id, text: caption })
        : el('label', { class: 'export-row-label', for: id, text: caption });
    if (group) {
        control.setAttribute('role', control.getAttribute('role') ?? 'group');
        control.setAttribute('aria-labelledby', id);
    } else {
        (control.matches('input, select') ? control : control.querySelector('input, select'))?.setAttribute('id', id);
    }
    return el('div', { class: 'export-row' }, label, control);
}

/** A text field with its caption. */
function textField(label, value, onInput, placeholder = '', { placeholders = false } = {}) {
    const input = el('input', { type: 'text', placeholder });
    input.value = value ?? '';
    input.addEventListener('input', () => onInput(input.value));
    // A text that takes placeholders gets their buttons beneath it (exportmark.js).
    return row(label, placeholders ? el('div', { class: 'export-row-control' }, input, placeholderButtons(input)) : input);
}

/** A question with its answers as radios, all in sight — side by side. */
function radios(question, name, answers, value, onPick) {
    const set = el('div', { class: 'export-choices', role: 'radiogroup' });
    for (const [v, words] of answers) {
        const radio = el('input', { type: 'radio', name, value: v });
        radio.checked = v === value;
        radio.addEventListener('change', () => onPick(v));
        set.append(el('label', { class: 'choice-row' }, radio, el('span', { text: words })));
    }
    return row(question, set, { group: true });
}

/** A slider with its value beside it. */
function slider(question, { min, max, step = 1, unit = '' }, value, onInput) {
    const input = el('input', { type: 'range', min: String(min), max: String(max), step: String(step) });
    input.value = String(value);
    const shown = el('output', { text: `${String(value).replace('.', ',')}${unit}` });
    input.addEventListener('input', () => {
        shown.textContent = `${input.value.replace('.', ',')}${unit}`;
        onInput(Number(input.value));
    });
    return row(question, el('div', { class: 'export-slider' }, input, shown));
}

/** Nine places, laid out as the picture's corners, edges and middle. */
function placeGrid(question, value, onPick) {
    const grid = el('div', { class: 'export-places' });
    const names = {
        tl: t('oben links'), tc: t('oben Mitte'), tr: t('oben rechts'),
        ml: t('Mitte links'), mc: t('Mitte'), mr: t('Mitte rechts'),
        bl: t('unten links'), bc: t('unten Mitte'), br: t('unten rechts'),
    };
    for (const p of PLACES) {
        const b = el('button', { type: 'button', class: 'button subtle small' + (p === value ? ' is-active' : ''),
            title: names[p], 'aria-label': names[p], 'aria-pressed': String(p === value) });
        b.append(el('span', { class: 'export-place-dot' }));
        b.addEventListener('click', () => {
            for (const o of grid.children) {
                o.classList.toggle('is-active', o === b);
                o.setAttribute('aria-pressed', String(o === b));
            }
            onPick(p);
        });
        grid.append(b);
    }
    return row(question, grid, { group: true });
}

/** A tick that is a setting of its own, in the control's column. */
function tickRow(words, checked, onChange) {
    const tick = el('input', { type: 'checkbox' });
    tick.checked = checked;
    tick.addEventListener('change', () => onChange(tick.checked));
    return el('div', { class: 'export-row' }, el('span', { class: 'export-row-label' }),
        el('label', { class: 'choice-row' }, tick, el('span', { text: words })));
}

/** A part's head: its tick, which switches it on — the rest folds away while off. */
function partHead(title, on, onToggle) {
    const tick = el('input', { type: 'checkbox' });
    tick.checked = on;
    tick.addEventListener('change', () => onToggle(tick.checked));
    return el('label', { class: 'export-part-head' }, tick, el('span', { text: title }));
}

/* --- The sample picture for the preview ------------------------------------
   Light above and dark below, so „automatisch" shows both of its inks; a
   few soft shapes so the grid has something to cover. Drawn, not loaded:
   no picture of anybody's is shown on a settings page. */
function drawSample(g, W, H) {
    const sky = g.createLinearGradient(0, 0, 0, H);
    sky.addColorStop(0, '#d9e3d2');
    sky.addColorStop(.55, '#8fa37a');
    sky.addColorStop(.56, '#4b3a2a');
    sky.addColorStop(1, '#2a2119');
    g.fillStyle = sky;
    g.fillRect(0, 0, W, H);
    // A mushroom, roughly — cap and stem.
    g.fillStyle = '#e9dcc0';
    g.fillRect(W * .46, H * .42, W * .08, H * .3);
    g.fillStyle = '#8a5a35';
    g.beginPath();
    g.ellipse(W * .5, H * .43, W * .17, H * .11, 0, Math.PI, 0);
    g.fill();
}

/* --- The tab -------------------------------------------------------------- */

function setUp(section) {
    if (section.dataset.bound) return;
    section.dataset.bound = '1';
    const root = document.getElementById('export-settings');
    const start = window.START_DATA ?? {};
    const baseUrl = start.baseUrl ?? '';
    const defaultName = section.dataset.defaultName ?? '';
    // A working copy — the stored one changes only when saved.
    const stored = start.measure_style?.export ?? {};
    const style = structuredClone({
        artist: stored.artist || defaultName,
        copyright: stored.copyright ?? '© {Urheber} {Jahr}',
        owner_in_file: stored.owner_in_file ?? true,
        file_name: stored.file_name || '{Motiv} bearbeitet',
        marks: stored.marks ?? [],
    });
    let chosen = style.marks[0]?.id ?? null;
    let dirty = false;

    const save = el('button', { type: 'button', class: 'button subtle', text: t('Speichern') });
    const changed = () => {
        dirty = true;
        save.classList.remove('subtle');
    };

    // The values the preview fills its placeholders with — an example find.
    const sampleValues = () => ({
        Urheber: defaultName, Jahr: String(new Date().getFullYear()),
        Art: 'Boletus edulis', Datum: '14.08.2026', Fundnummer: 'Y-3578', Motiv: 'Hauptmotiv', Ort: 'Arenshausen',
    });

    /* The preview: drawn anew on every change, a frame later, so a slider
       dragged does not draw thirty times a second for nothing. */
    const preview = el('canvas', { class: 'export-preview', width: '900', height: '600' });
    let drawSoon = 0;
    function drawPreview() {
        cancelAnimationFrame(drawSoon);
        drawSoon = requestAnimationFrame(async () => {
            const mark = style.marks.find((m) => m.id === chosen);
            const logo = mark?.logo?.on ? await loadLogo(baseUrl, mark.logo.file) : null;
            const g = preview.getContext('2d', { willReadFrequently: true });
            g.clearRect(0, 0, preview.width, preview.height);
            drawSample(g, preview.width, preview.height);
            if (mark) drawMark(g, preview.width, preview.height, mark, sampleValues(), logo);
        });
    }

    /* NO AUTHOR AND NO RIGHTS LINE HERE any more (08.11.2026).
       The author is the PICTURE's — its photographer, or who the upload
       named (photogallery.js, askAuthor) — and never the exporter's: a
       name fixed in the settings would have put one's own name into a
       friend's photo. What goes into the file is only that name („einfach
       nur der Urhebername"); what shows on the picture is the template's
       notice. One sentence says so, where the fields stood. */
    const author = el('p', { class: 'hint-text',
        text: t('Der Urheber kommt immer vom Bild: bei Belegfotos der, der sie aufgenommen hat, bei Cloud-Aufnahmen der, den du beim Hochladen angegeben hast. Sein Name steht in jedem exportierten Bild in der Datei. Den Fundort schreibt YASAIMAPP nie hinein.') });
    const placeholders = el('p', { class: 'hint-text small export-placeholders',
        text: t('Unter jedem Text setzen Knöpfe Angaben ein, die beim Export ausgefüllt werden. „+ Art“ zum Beispiel setzt die Art des Funds ein, „+ Urheber“ den Namen dessen, der das Foto gemacht hat. Was in geschweiften Klammern steht, wird beim Export ersetzt.') });

    /* --- The templates: one tab each -------------------------------------
       (08.11.2026: „für jede Vorlage einen eigenen Untertab").
       The house's tab row (.tab, [role="tab"]), drawn here because the
       templates are, and marked data-tabs="row": on the phone it wraps
       instead of becoming a chooser menu — tabs.js wires the bars a
       fragment brings at its arrival, and this one comes after. „Neue
       Vorlage" stands at the row's end; „Vorlage löschen" at the foot of
       its own tab, with the template it deletes in sight. */
    const bar = el('div', { class: 'tab export-mark-tabs', role: 'tablist', 'aria-label': t('Wasserzeichen-Vorlagen'), 'data-tabs': 'row' });
    const editor = el('div', { class: 'export-mark-editor', role: 'tabpanel' });
    const empty = el('p', { class: 'hint-text', text: t('Noch keine Vorlage. Mit „Neue Vorlage“ legst du die erste an.') });
    const add = el('button', { type: 'button', class: 'button subtle small export-mark-add' });
    add.innerHTML = iconHtml('plus');
    add.append(t('Neue Vorlage'));
    add.addEventListener('click', () => {
        if (style.marks.length >= 12) {
            toast(t('Mehr als zwölf Vorlagen gehen nicht.'), 'error');
            return;
        }
        const m = markNew(style.marks.length + 1);
        style.marks.push(m);
        chosen = m.id;
        changed();
        drawList();
        drawEditor();
    });

    function drawList() {
        bar.textContent = '';
        empty.hidden = style.marks.length > 0;
        for (const m of style.marks) {
            const tab = el('button', { type: 'button', role: 'tab', text: m.name || t('Vorlage'),
                'aria-selected': String(m.id === chosen), tabindex: m.id === chosen ? '0' : '-1' });
            tab.dataset.mark = m.id;
            tab.addEventListener('click', () => { chosen = m.id; drawList(); drawEditor(); });
            bar.append(tab);
        }
        bar.append(add);
    }

    async function removeMark(m) {
        if (!await confirm({
            title: t('Vorlage löschen'),
            text: t('Die Vorlage „%s“ wird gelöscht. Bilder, die du damit schon exportiert hast, bleiben, wie sie sind.', m.name),
            button: t('Löschen'),
            danger: true,
        })) return;
        const at = style.marks.indexOf(m);
        style.marks = style.marks.filter((o) => o !== m);
        chosen = (style.marks[at] ?? style.marks[at - 1])?.id ?? null;
        changed();
        drawList();
        drawEditor();
    }

    async function logoPicked(file, mark, say) {
        if (!file) return;
        say.textContent = t('Wird hochgeladen …');
        try {
            const img = new Image();
            const at = URL.createObjectURL(file);
            await new Promise((resolve, reject) => { img.onload = resolve; img.onerror = reject; img.src = at; });
            URL.revokeObjectURL(at);
            const k = Math.min(1, LOGO_EDGE / Math.max(img.naturalWidth, img.naturalHeight));
            const c = el('canvas');
            c.width = Math.max(1, Math.round(img.naturalWidth * k));
            c.height = Math.max(1, Math.round(img.naturalHeight * k));
            const g = c.getContext('2d');
            g.imageSmoothingQuality = 'high';
            g.drawImage(img, 0, 0, c.width, c.height);
            const png = await new Promise((resolve) => c.toBlob(resolve, 'image/png'));
            const body = new FormData();
            body.append('logo', png, 'logo.png');
            const answer = await request('export_logo.php', { method: 'POST', body });
            mark.logo.file = answer.file;
            mark.logo.on = true;
            changed();
            drawEditor();
        } catch (e) {
            say.textContent = e.message || t('Das Logo ließ sich nicht lesen.');
        }
    }

    function drawEditor() {
        editor.textContent = '';
        const mark = style.marks.find((m) => m.id === chosen);
        if (!mark) {
            editor.hidden = true;
            return;
        }
        editor.hidden = false;
        const on = (fn) => (v) => { fn(v); changed(); drawPreview(); };
        const fonts = [['sans', t('schlicht')], ['serif', t('mit Serifen')], ['mono', t('Schreibmaschine')]];
        const colors = [['auto', t('automatisch')], ['white', t('weiß')], ['black', t('schwarz')]];

        const controls = el('div', { class: 'export-mark-controls' });
        controls.append(textField(t('Name der Vorlage'), mark.name, (v) => {
            mark.name = v;
            changed();
            const tab = bar.querySelector(`[role="tab"][data-mark="${CSS.escape(mark.id)}"]`);
            if (tab) tab.textContent = v || t('Vorlage');
        }));

        // The notice.
        const hint = el('fieldset', { class: 'export-part' + (mark.hint.on ? '' : ' is-off') },
            partHead(t('Hinweis'), mark.hint.on, (v) => { mark.hint.on = v; hint.classList.toggle('is-off', !v); changed(); drawPreview(); }),
            el('p', { class: 'hint-text small', text: t('Ein kurzer Text in einer Ecke, zum Beispiel „© Anna Muster 2026“.') }),
            textField(t('Text'), mark.hint.text, on((v) => { mark.hint.text = v; }), '', { placeholders: true }),
            radios(t('Schrift'), `hint-font-${mark.id}`, fonts, mark.hint.font, on((v) => { mark.hint.font = v; })),
            placeGrid(t('Wo'), mark.hint.place, on((v) => { mark.hint.place = v; })),
            slider(t('Größe'), { min: 1, max: 10, step: .5, unit: ' %' }, mark.hint.size, on((v) => { mark.hint.size = v; })),
            radios(t('Farbe'), `hint-color-${mark.id}`, colors, mark.hint.color, on((v) => { mark.hint.color = v; })),
            slider(t('Deckkraft'), { min: 5, max: 100, unit: ' %' }, mark.hint.opacity, on((v) => { mark.hint.opacity = v; })),
        );
        hint.append(tickRow(t('Weicher Schatten, damit der Text auf jedem Grund lesbar bleibt'), mark.hint.shadow, on((v) => { mark.hint.shadow = v; })));

        // The protective grid.
        const grid = el('fieldset', { class: 'export-part' + (mark.grid.on ? '' : ' is-off') },
            partHead(t('Schutzgitter'), mark.grid.on, (v) => { mark.grid.on = v; grid.classList.toggle('is-off', !v); changed(); drawPreview(); }),
            el('p', { class: 'hint-text small', text: t('Ein fast durchsichtiger Text, schräg und immer wieder über das ganze Bild. So kann man das Bild ansehen, aber kaum weiterverwenden.') }),
            textField(t('Text'), mark.grid.text, on((v) => { mark.grid.text = v; }), t('leer: der Text des Hinweises'), { placeholders: true }),
            radios(t('Schrift'), `grid-font-${mark.id}`, fonts, mark.grid.font, on((v) => { mark.grid.font = v; })),
            slider(t('Winkel'), { min: -90, max: 90, unit: '°' }, mark.grid.angle, on((v) => { mark.grid.angle = v; })),
            slider(t('Abstand'), { min: 50, max: 400, step: 10, unit: ' %' }, mark.grid.gap, on((v) => { mark.grid.gap = v; })),
            slider(t('Größe'), { min: 1, max: 15, step: .5, unit: ' %' }, mark.grid.size, on((v) => { mark.grid.size = v; })),
            radios(t('Farbe'), `grid-color-${mark.id}`, colors, mark.grid.color, on((v) => { mark.grid.color = v; })),
            slider(t('Deckkraft'), { min: 3, max: 60, unit: ' %' }, mark.grid.opacity, on((v) => { mark.grid.opacity = v; })),
        );

        // The logo.
        const fileInput = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp', hidden: '' });
        const logoSay = el('p', { class: 'hint-text small export-row-note' });
        const pickLogo = el('button', { type: 'button', class: 'button subtle small',
            text: mark.logo.file ? t('Anderes Logo wählen') : t('Logo wählen') });
        pickLogo.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => logoPicked(fileInput.files?.[0], mark, logoSay));
        const logoShown = el('div', { class: 'export-logo-shown' });
        if (mark.logo.file) {
            loadLogo(baseUrl, mark.logo.file).then((img) => { if (img) logoShown.append(img.cloneNode()); });
        }
        const logo = el('fieldset', { class: 'export-part' + (mark.logo.on ? '' : ' is-off') },
            partHead(t('Logo'), mark.logo.on, (v) => { mark.logo.on = v; logo.classList.toggle('is-off', !v); changed(); drawPreview(); }),
            el('p', { class: 'hint-text small', text: t('Dein Logo als Bild, am besten ein PNG mit durchsichtigem Hintergrund.') }),
            row(t('Bild'), el('div', { class: 'button-row export-logo-row' }, logoShown, pickLogo, fileInput), { group: true }),
            logoSay,
            placeGrid(t('Wo'), mark.logo.place, on((v) => { mark.logo.place = v; })),
            slider(t('Größe'), { min: 5, max: 50, unit: ' %' }, mark.logo.size, on((v) => { mark.logo.size = v; })),
            slider(t('Deckkraft'), { min: 10, max: 100, unit: ' %' }, mark.logo.opacity, on((v) => { mark.logo.opacity = v; })),
        );

        const remove = el('button', { type: 'button', class: 'button subtle small danger' });
        remove.innerHTML = iconHtml('delete');
        remove.append(t('Vorlage löschen'));
        remove.addEventListener('click', () => removeMark(mark));
        controls.append(hint, grid, logo, el('div', { class: 'button-row export-mark-remove' }, remove));
        const look = el('div', { class: 'export-mark-look' }, preview,
            el('p', { class: 'hint-text small', text: t('Vorschau an einem Beispielbild. Größen gelten als Anteil der langen Bildkante, darum sieht es bei jeder Exportgröße gleich aus.') }));
        editor.append(controls, look);
        drawPreview();
    }

    save.addEventListener('click', async () => {
        save.disabled = true;
        try {
            const answer = await request('measure_style.php', { method: 'POST', body: JSON.stringify({ export: style }) });
            // The page's own picture viewer exports with what was just saved.
            if (start.measure_style && answer.measure_style?.export) start.measure_style.export = answer.measure_style.export;
            dirty = false;
            save.classList.add('subtle');
            toast(t('Gespeichert.'));
        } catch (e) {
            toast(e.message || t('Das ließ sich nicht speichern.'), 'error');
        } finally {
            save.disabled = false;
        }
    });
    window.addEventListener('beforeunload', (e) => {
        if (dirty) e.preventDefault();
    });

    root.textContent = '';
    root.append(
        author,
        placeholders,
        el('div', { class: 'settings-group' },
            el('h3', { class: 'list-label', text: t('Wasserzeichen') }),
            el('p', { class: 'hint-text', text: t('Beim Exportieren wählst du eine dieser Vorlagen oder keine. Jede kann einen Hinweis, ein Schutzgitter und ein Logo haben, jedes für sich an- und ausschaltbar.') }),
            bar,
            empty,
            editor),
        el('div', { class: 'button-row right' }, save),
    );
    drawList();
    drawEditor();
}

new MutationObserver(() => {
    const section = document.getElementById('section-export-marks');
    if (section !== null) setUp(section);
}).observe(document.body, { childList: true, subtree: true });
const already = document.getElementById('section-export-marks');
if (already) setUp(already);
