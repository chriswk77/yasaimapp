/**
 * The window of Organisieren → Fundnummern — many find numbers at once
 * (26.09.2026: „das mehrfach umbenennen der Fundnummern"). The
 * markup stands in includes/organize_content.php, the arithmetic in
 * numbering.js; this wires the one to the other.
 *
 * NOTHING IS PRESELECTED („vorwählen würde ich hier überhaupt nichts"):
 * every question the choice raises is the user's to answer, and the
 * window says how many there are. A question stands only where it has
 * something to decide — the restart only for a dated pattern, the scope
 * only when some chosen finds carry a number and some do not, the order
 * only when the table is sorted otherwise, the old numbers only when some
 * are about to go, the new finds only for a new pattern. „Nummern
 * vergeben" with one open marks it red inside the window; the server
 * refuses the same once more (organize_numbers_apply).
 *
 * Its own module and not more lines in organize.js: that file is long
 * enough, and the numbering is a thing of its own.
 */

import { t } from './i18n.js';
import { findNumberFallback, nextFreeNumber, periodUnit } from './findnumber.js';
import { readStart, planNumbers, drawMany, findDate } from './numbering.js';

const QUESTIONS = ['mode', 'restart', 'scope', 'order', 'old', 'adopt'];
const COUNT_WORDS = () => ['', '', t('Zwei'), t('Drei'), t('Vier'), t('Fünf'), t('Sechs')];

let ctx = null;       // the current opening's data
let missingShown = false;
let wired = false;

const el = (id) => document.getElementById(id);
/* Oldest first, a find without a find time last — and among equals the
   one recorded first, so the order never depends on the table's whim. */
const byTime = (a, b) => {
    const x = findDate(a.found_at), y = findDate(b.found_at);
    if (!x || !y) return x === y ? a.id - b.id : (x ? -1 : 1);
    return x - y || a.id - b.id;
};
const dialog = () => el('organize-number-dialog');
const group = (key) => dialog().querySelector(`[data-question="${key}"]`);
const answer = (key) => dialog().querySelector(`input[name="number-${key}"]:checked`)?.value;

/**
 * Open the window.
 *
 * @param {object} o
 * @param {Array<{id: number, title: string, number: ?string, found_at: ?string}>} o.finds
 *        the chosen own finds, in the table's order
 * @param {string[]} o.outside  titles of chosen points that get no number
 * @param {Map<string, string>} o.taken  lower-cased number → title, of own finds outside the choice
 * @param {?object} o.series  the account's series (find_number_series), null without a pattern
 * @param {?{label: string, dir: number}} o.sorted  the table's sort, when it is not the find time's
 * @param {function(object): void} o.done  gets the answers to send
 */
export function openNumbers(o) {
    ctx = o;
    wire();
    missingShown = false;
    for (const input of dialog().querySelectorAll('input[type=radio]')) input.checked = false;
    el('organize-number-start').value = '';
    for (const key of QUESTIONS) markMissing(key, false);
    el('organize-number-next').hidden = !ctx.series;
    draw();
    dialog().showModal();
    dialog().focus();
}

function wire() {
    if (wired) return;
    wired = true;
    const form = dialog().querySelector('form');
    form.addEventListener('change', draw);
    el('organize-number-start').addEventListener('input', () => {
        dialog().querySelector('input[name="number-mode"][value="from"]').checked = true;
        draw();
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const plan = compute();
        if (plan.open.length > 0 || plan.startBroken) {
            missingShown = true;
            for (const key of plan.open) markMissing(key, true);
            draw();
            const first = plan.open[0] ? group(plan.open[0]) : el('organize-number-start');
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        if (plan.made === 0) return;
        dialog().close();
        ctx.done({
            points: plan.finds.map((f) => f.id).join(','),
            mode: plan.mode,
            start: el('organize-number-start').value.trim(),
            restart: answer('restart') ?? '',
            scope: plan.scope,
            old: answer('old') ?? '',
            adopt: answer('adopt') ?? '',
        });
    });
    form.querySelector('.dialog-footer .subtle').addEventListener('click', () => dialog().close());
}

function markMissing(key, on) {
    group(key).classList.toggle('is-missing', on);
    group(key).querySelector('.question-missing').hidden = !on;
}

/** Everything the answers so far decide — the questions, the order, the plan. */
function compute() {
    const { series, taken } = ctx;
    const mode = answer('mode');
    const start = mode === 'from' ? readStart(series?.pattern ?? null, el('organize-number-start').value) : null;
    const pattern = start?.kind === 'pattern' ? start : null;
    const numbered = ctx.finds.filter((f) => f.number).length;
    const mixed = numbered > 0 && numbered < ctx.finds.length;

    const asks = {
        mode: true,
        restart: !!pattern && pattern.unit !== null,
        scope: mode !== 'remove' && mixed,
        order: mode !== 'remove' && !!ctx.sorted,
        old: false,
        adopt: !!pattern,
    };
    const scope = mode === 'remove' || !mixed ? 'all' : answer('scope');
    const order = asks.order ? answer('order') : 'time';
    const finds = order === 'table' ? [...ctx.finds] : [...ctx.finds].sort(byTime);

    const open = () => QUESTIONS.filter((key) => asks[key] && !answer(key));
    const startBroken = mode === 'from' && (!start || !!start.error);
    const ready = !startBroken && open().every((key) => key === 'old' || key === 'adopt');
    const plan = ready
        ? planNumbers({ series, finds, taken, mode, start, restart: answer('restart') === 'yes', scope })
        : { rows: finds.map((f) => ({ id: f.id, state: 'open', number: null, conflict: null })), counter: null, period: null };
    const lost = plan.rows.filter((r, i) => r.state === 'new' && finds[i].number);
    // Asked as soon as it is clear that numbers will be replaced — not
    // only once the table has them, or it would pop up under the hand.
    asks.old = !!mode && numbered > 0 && (mode === 'remove' || scope === 'all') && (!ready || lost.length > 0);
    return { mode, start, pattern, scope, finds, asks, plan, lost, ready, startBroken, open: open(),
             made: plan.rows.filter((r) => r.state === 'new').length };
}

function draw() {
    const c = compute();
    const { series, taken } = ctx;
    const { mode, start, pattern, finds, plan } = c;

    // --- the answers' own words -------------------------------------------
    if (series) {
        const waiting = finds.filter((f) => !f.number).sort(byTime);
        const examples = drawMany(series, [...taken.keys(), ...finds.map((f) => f.number).filter(Boolean)],
            waiting.length > 0 ? waiting.slice(0, 2).map((f) => f.found_at) : [null, null]).numbers;
        const unit = series.restart ? periodUnit(series.pattern) : null;
        el('organize-number-next-sub').textContent = unit === 'Tag' ? t('Wie beim Kartieren. Jeder Fundtag zählt für sich: %s …', examples.join(', '))
            : unit === 'Monat' ? t('Wie beim Kartieren. Jeder Monat zählt für sich: %s …', examples.join(', '))
            : unit === 'Jahr' ? t('Wie beim Kartieren. Jedes Jahr zählt für sich: %s …', examples.join(', '))
            : t('Wie beim Kartieren: %s …', examples.join(', '));
    }
    const from = el('organize-number-from-sub');
    from.classList.toggle('is-error', !!start?.error);
    if (!start) {
        from.textContent = t('Tippe die erste Nummer, etwa neu-01. Oder ein Muster wie unter Einstellungen → Fundnummer, etwa CW-JJJJ-NNNN.');
    } else if (start.error) {
        from.textContent = start.error;
    } else if (pattern) {
        const first = String(pattern.first).padStart(pattern.width, '0');
        const has = (key) => pattern.pattern.includes(key);
        const parts = [
            has('JJJJ') ? t('JJJJ wird das Fundjahr') : has('JJ') ? t('JJ wird das Fundjahr') : null,
            has('MM') ? t('MM der Monat') : null,
            has('TT') ? t('TT der Tag') : null,
        ].filter(Boolean);
        from.textContent = parts.length > 0
            ? t('Ein Muster: %s, die laufende Nummer beginnt bei %s.', parts.join(', '), first)
            : t('Ein Muster: Die laufende Nummer beginnt bei %s.', first);
    } else {
        const make = (n) => start.head + String(n).padStart(start.width, '0') + start.tail;
        const typed = el('organize-number-start').value.trim();
        from.textContent = t('Die weiteren zählen hoch: %s, %s …', make(start.first + 1), make(start.first + 2))
            + (start.own && make(start.first) !== typed ? ' ' + t('Gespeichert wird %s.', make(start.first)) : '');
    }
    const withNumber = finds.find((f) => f.number) ?? finds[0];
    el('organize-number-remove-sub').textContent =
        t('Die Funde tragen dann wieder ihre Nummer von YASAIMAPP, etwa %s.', findNumberFallback(withNumber?.id ?? 1));

    if (c.asks.restart) {
        const first = String(pattern.first).padStart(pattern.width, '0');
        const words = {
            Tag:   [t('Beginnt die laufende Nummer jeden Tag wieder bei %s?', first), t('Ja, jeden Tag neu'), t('Nein, sie läuft über die Tage durch')],
            Monat: [t('Beginnt die laufende Nummer jeden Monat wieder bei %s?', first), t('Ja, jeden Monat neu'), t('Nein, sie läuft über die Monate durch')],
            Jahr:  [t('Beginnt die laufende Nummer jedes Jahr wieder bei %s?', first), t('Ja, jedes Jahr neu'), t('Nein, sie läuft über die Jahre durch')],
        }[pattern.unit];
        el('organize-number-restart-question').textContent = words[0];
        el('organize-number-restart-yes').textContent = words[1];
        el('organize-number-restart-no').textContent = words[2];
    }
    const without = finds.filter((f) => !f.number).length;
    el('organize-number-scope-missing').textContent = t('Nur die ohne eigene Nummer (%d)', without);
    el('organize-number-scope-all').textContent = t('Alle gewählten Funde (%d)', finds.length);
    if (ctx.sorted) {
        el('organize-number-order-table').textContent = ctx.sorted.dir === 1
            ? t('Wie die Tabelle sortiert ist: nach „%s", aufsteigend', ctx.sorted.label)
            : t('Wie die Tabelle sortiert ist: nach „%s", absteigend', ctx.sorted.label);
    }
    const lostNumbers = c.lost.map((r) => finds[c.plan.rows.indexOf(r)].number);
    const example = lostNumbers[0] ?? finds.find((f) => f.number)?.number ?? '';
    el('organize-number-old-note').textContent = (lostNumbers.length === 0 ? t('Funde mit eigener Nummer bekommen eine neue.')
            : lostNumbers.length === 1 ? t('Ein Fund verliert seine Nummer %s.', lostNumbers[0])
            : t('%d Funde verlieren ihre Nummer, etwa %s.', lostNumbers.length, lostNumbers[0]))
        + ' ' + t('Steht die alte auf einem Belegzettel, findet die Suche den Fund über die Notiz weiter.');
    el('organize-number-old-note-answer').textContent =
        t('In die Notiz schreiben, etwa „%s"', t('Frühere Fundnummer: %s', example));

    // What stays and what comes: the numbers after the plan.
    const after = [...taken.keys(), ...plan.rows.map((r, i) => r.state === 'new' ? r.number : finds[i].number).filter(Boolean)];
    if (pattern) {
        const next = nextFreeNumber({ pattern: pattern.pattern, counter: 1, restart: answer('restart') === 'yes', period: null },
            after, new Date()).text;
        el('organize-number-adopt-yes').textContent = series
            ? t('Ja. Das Muster ersetzt %s unter Einstellungen → Fundnummer. Der nächste neue Fund bekommt %s.', series.pattern, next)
            : t('Ja. Neue Funde bekommen dann Nummern nach diesem Muster, der nächste %s.', next);
        el('organize-number-adopt-no').textContent = series
            ? t('Nein. Neue Funde zählen weiter nach %s.', series.pattern)
            : t('Nein. Neue Funde bekommen weiter keine eigene Nummer.');
    }

    // --- which questions stand, counted and numbered ------------------------
    const asked = QUESTIONS.filter((key) => c.asks[key]);
    for (const key of QUESTIONS) {
        group(key).hidden = !c.asks[key];
        if (!c.open.includes(key)) markMissing(key, false);
    }
    asked.forEach((key, i) => { group(key).querySelector('.question-no').textContent = (i + 1) + '.'; });
    el('organize-number-intro').textContent = asked.length === 1
        ? t('Eine Frage beantwortest du selbst. Danach zeigt die Tabelle jede neue Nummer, bevor etwas gespeichert wird.')
        : t('%s Fragen beantwortest du selbst. Danach zeigt die Tabelle jede neue Nummer, bevor etwas gespeichert wird.', COUNT_WORDS()[asked.length]);

    // --- the table ------------------------------------------------------------
    const body = el('organize-number-rows');
    body.replaceChildren();
    plan.rows.forEach((r, i) => {
        const f = finds[i];
        const tr = document.createElement('tr');
        tr.className = { new: 'is-new', taken: 'is-taken', kept: 'is-kept', undated: 'is-kept' }[r.state] ?? '';
        const cell = (text, cls) => {
            const td = document.createElement('td');
            td.textContent = text;
            if (cls) td.className = cls;
            tr.append(td);
        };
        const when = findDate(f.found_at);
        cell(when ? when.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : t('ohne Fundzeit'), 'number-when');
        cell(f.title || t('Ohne Titel'));
        cell(f.number ?? findNumberFallback(f.id), 'number-old');
        cell(r.state === 'new' ? (r.number ?? findNumberFallback(f.id))
            : r.state === 'taken' ? t('%s trägt schon „%s" – bleibt', r.number, r.conflict)
            : r.state === 'undated' ? t('bleibt – ohne Fundzeit fehlt der Nummer ihr Datum')
            : r.state === 'kept' ? t('bleibt') : '–', 'number-new');
        body.append(tr);
    });

    // --- what happens, said before it happens -----------------------------------
    const lines = [];
    const say = (text, kind = '') => lines.push([text, kind]);
    const made = plan.rows.filter((r) => r.state === 'new');
    const takenRows = plan.rows.filter((r) => r.state === 'taken');
    if (mode === 'from' && start?.error) {
        say(start.error, 'is-error');
    } else if (mode === 'from' && !start) {
        say(t('Tippe oben die erste Nummer oder ein Muster ein.'), missingShown ? 'is-error' : 'is-quiet');
    } else if (!c.ready) {
        say(missingShown ? t('Bei den rot markierten Fragen fehlt noch deine Antwort.')
            : (asked.length === 1 ? t('Beantworte zuerst die Frage oben.') : t('Beantworte zuerst die Fragen oben.')),
            missingShown ? 'is-error' : 'is-quiet');
    } else {
        if (mode === 'remove') {
            say(made.length === 0 ? t('Keiner der gewählten Funde hat eine eigene Nummer.')
                : made.length === 1 ? t('Ein Fund verliert seine eigene Nummer. Er trägt dann wieder seine Nummer von YASAIMAPP.')
                : t('%d Funde verlieren ihre eigene Nummer. Sie tragen dann wieder ihre Nummer von YASAIMAPP.', made.length));
        } else if (made.length === 0) {
            say(t('Keiner der gewählten Funde bekommt eine neue Nummer.'));
        } else {
            const ranged = !(mode === 'next' && series?.restart) && !(pattern && answer('restart') === 'yes');
            say(made.length === 1 ? t('Ein Fund bekommt die Nummer %s.', made[0].number)
                : ranged ? t('%d Funde bekommen eine Nummer, von %s bis %s.', made.length, made[0].number, made.at(-1).number)
                : t('%d Funde bekommen eine Nummer.', made.length));
        }
        if (takenRows.length === 1) say(t('%s trägt schon „%s". Diesen Fund lasse ich aus.', takenRows[0].number, takenRows[0].conflict), 'is-warning');
        if (takenRows.length > 1) say(t('%d dieser Nummern tragen schon andere Funde. Diese Funde lasse ich aus.', takenRows.length), 'is-warning');
        if (c.lost.length > 0 && mode !== 'remove') {
            say(c.lost.length === 1 ? t('Ein Fund verliert dabei seine bisherige Nummer: %s.', lostNumbers[0])
                : t('%d Funde verlieren dabei ihre bisherige Nummer.', c.lost.length), 'is-warning');
        }
        if (answer('old') === 'note' && c.lost.length > 0) say(t('Die bisherigen Nummern kommen in die Notiz.'), 'is-quiet');
        // Where the series stands afterwards — the counter never goes back.
        if (pattern && answer('adopt') === 'yes') {
            say(t('Unter Einstellungen → Fundnummer steht danach %s.', pattern.pattern), 'is-quiet');
        } else if (series && mode === 'from' && (pattern || !start.own)) {
            say(t('Das ist nicht dein Muster %s, sondern eine eigene Reihe. Deine Reihe zählt davon unberührt weiter.', series.pattern), 'is-quiet');
        } else if (series) {
            const moved = { ...series, counter: plan.counter ?? series.counter, period: plan.period ?? series.period };
            const next = drawMany(moved, after, [null]).numbers[0];
            const before = drawMany(series, [...taken.keys(), ...finds.map((f) => f.number).filter(Boolean)], [null]).numbers[0];
            say(next === before ? t('Deine Reihe zählt danach wie bisher bei %s weiter.', next)
                : t('Danach zählt deine Reihe bei %s weiter.', next), 'is-quiet');
        }
        if (c.open.length > 0 && missingShown) say(t('Bei den rot markierten Fragen fehlt noch deine Antwort.'), 'is-error');
    }
    if (ctx.outside.length > 0) {
        say(ctx.outside.length === 1
            ? t('„%s" bekommt keine Nummer: Nummern gibt es nur für eigene Funde aus Artenlisten.', ctx.outside[0])
            : t('%d der gewählten Punkte bekommen keine Nummer: Nummern gibt es nur für eigene Funde aus Artenlisten.', ctx.outside.length),
            'is-quiet');
    }
    el('organize-number-summary').replaceChildren(...lines.map(([text, kind]) => {
        const p = document.createElement('p');
        p.textContent = text;
        if (kind) p.className = kind;
        return p;
    }));

    const ok = el('organize-number-ok');
    const decided = c.ready && c.open.length === 0;
    const n = made.length;
    ok.disabled = decided && n === 0;
    ok.textContent = !decided ? t('Nummern vergeben')
        : n === 0 ? t('Nichts zu ändern')
        : mode === 'remove' ? (n === 1 ? t('Eine Nummer entfernen') : t('%d Nummern entfernen', n))
        : (n === 1 ? t('Eine Nummer vergeben') : t('%d Nummern vergeben', n));
}
