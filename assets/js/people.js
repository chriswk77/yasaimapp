/**
 * THE PEOPLE ONE HAS WRITTEN BEFORE, suggested under Finder and Bestimmer
 * (24.11.2026: „Bei der Eingabe von Finder und Bestimmer kann sich da
 * gemerkt werden, was ich schon einmal eingegeben habe … Oft sind es ja
 * die gleichen Personen, die im Verein sind").
 *
 * NOTHING IS KEPT FOR IT. The memory is the finds themselves: the server
 * hands the values of one's own finds over with the page
 * (person_names_written in includes/person_names.php), and the finds the
 * page holds add what was typed since — so a name saved a minute ago is
 * offered at once, offline too, and a name nobody uses any more fades with
 * its last find instead of living on in a list nobody can weed.
 *
 * ONE PERSON PER SUGGESTION. A field may name several — „Volker Maier und
 * Sebastian Schröder" — and is split at „und", „&", „+", „;" and „/". NOT AT
 * THE COMMA: „Schröder, Sebastian" is one person written surname first, the
 * way the DGfM delivery writes it (mykis.php, mykis_person). The suggestion
 * answers the person being typed — the text after the last such joint — and
 * a pick replaces only that piece, so the second finder is added the same
 * way as the first.
 *
 * THE SAME LIST AS THE SPECIES NAMES (app.css, .species-suggestions), hung
 * into the field's box: it looks and behaves as the lists under the title
 * and the taxonomy filter do, and the Fundbrowser's rule that it hangs under
 * its field and not over it reaches it too. Chosen on pointerdown, so the
 * caret stays in the field.
 */

/* THE LIST THAT STANDS OPEN, one at a time — and ONE listener for all
   fields: drawExtraFields draws them anew at every change of the list
   choice, and a listener per field would stay behind on the document for
   every field long gone. CLOSED BY A TAP BESIDE IT, NOT ON BLUR — the reason
   the species list gives (pointwindow.js, closeSpecies): a finger reaching
   into the list may take the caret along, and the list would be gone before
   it arrived. */
let current = null;
document.addEventListener('pointerdown', (e) => {
    if (current && e.target !== current.input && !current.list.contains(e.target)) current.close();
});

/** Where one person ends and the next begins — never at the comma (see the head). */
const JOINT = /\s+(?:und|and|&|\+)\s+|\s*[;/]\s*/giu;

/** A name as the matching compares it: lower case, umlauts written out, one space. */
const fold = (s) => String(s ?? '').toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/\s+/g, ' ').trim();

/* ONE PERSON IN EITHER ORDER: „Schröder, Sebastian" and „Sebastian
   Schröder" are counted together and offered in the spelling written most. */
const personKey = (name) => fold(name).split(/[\s,]+/).filter(Boolean).sort().join(' ');

/** The people a field's value names, each trimmed. */
const peopleIn = (value) => String(value ?? '').split(JOINT).map(p => p.trim()).filter(p => p.length > 1);

/**
 * The people on offer, most written first.
 *
 * @param {Array<[string, number]>} written  the server's [value, count] (start.people)
 * @param {string[]} here                     the values of the finds this page holds
 * @returns {string[]}
 */
export function peopleOffers(written, here) {
    // Per page source the counts, per person the larger of the two: on the
    // map the page holds every own find, and adding would count each twice.
    const tally = (pairs) => {
        const out = new Map();   // folded name → Map(spelling → count)
        for (const [value, n] of pairs) {
            for (const person of peopleIn(value)) {
                const key = personKey(person);
                const spellings = out.get(key) ?? new Map();
                spellings.set(person, (spellings.get(person) ?? 0) + n);
                out.set(key, spellings);
            }
        }
        return out;
    };
    const server = tally(written ?? []);
    const page = tally((here ?? []).map(v => [v, 1]));
    const people = [];
    for (const key of new Set([...server.keys(), ...page.keys()])) {
        const spellings = new Map(server.get(key) ?? []);
        for (const [s, n] of page.get(key) ?? []) spellings.set(s, Math.max(spellings.get(s) ?? 0, n));
        // The spelling written most often is the one offered.
        const [name] = [...spellings].sort((a, b) => b[1] - a[1])[0];
        const count = [...spellings.values()].reduce((a, b) => a + b, 0);
        people.push({ name, count });
    }
    return people.sort((a, b) => b.count - a.count || a.name.localeCompare(b.name)).map(p => p.name);
}

/**
 * Hang the suggestions on a field.
 *
 * @param {HTMLInputElement} input
 * @param {() => string[]} offers  the people on offer, asked anew at each keystroke
 */
export function bindPeople(input, offers) {
    const list = document.createElement('ul');
    list.className = 'species-suggestions';
    list.hidden = true;
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    let rows = [];
    let cursor = -1;
    const self = { input, list, close: () => close() };

    const close = () => {
        list.hidden = true;
        list.innerHTML = '';
        rows = [];
        cursor = -1;
        input.setAttribute('aria-expanded', 'false');
        if (current === self) current = null;
    };

    /** The person being typed: where it starts in the value, and its words so far. */
    const piece = () => {
        const text = input.value.slice(0, input.selectionStart ?? input.value.length);
        let start = 0;
        for (const m of text.matchAll(JOINT)) start = m.index + m[0].length;
        return { start, typed: text.slice(start) };
    };

    const pick = (name) => {
        const { start } = piece();
        const rest = input.value.slice(input.selectionStart ?? input.value.length);
        // What stood after the caret stays only where it is the next person.
        const after = rest.search(JOINT) >= 0 ? rest.slice(rest.search(JOINT)) : '';
        input.value = input.value.slice(0, start) + name + after;
        const caret = start + name.length;
        input.setSelectionRange(caret, caret);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        close();
    };

    const open = () => {
        if (input.readOnly || input.disabled) { close(); return; }
        const { typed } = piece();
        const wanted = fold(typed);
        if (wanted === '') { close(); return; }
        // Who the field names already is not offered a second time.
        const named = new Set(peopleIn(input.value).map(personKey));
        const hits = [];
        for (const name of offers()) {
            const f = fold(name);
            if (named.has(personKey(name))) continue;
            // At the start of the name or of any of its words: „Schr" finds „Sebastian Schröder".
            const whole = f.startsWith(wanted);
            if (!whole && !f.split(/[\s,.-]+/).some(w => w.startsWith(wanted))) continue;
            hits.push({ name, whole });
            if (hits.length >= 50) break;
        }
        // The name that starts with the typed letters before the one with a word that does.
        hits.sort((a, b) => Number(b.whole) - Number(a.whole));
        list.innerHTML = '';
        rows = [];
        cursor = -1;
        if (hits.length === 0) { close(); return; }
        for (const { name } of hits) {
            const row = document.createElement('li');
            row.textContent = name;
            row.addEventListener('pointerdown', (e) => {
                e.preventDefault();   // the caret stays in the field
                pick(name);
            });
            list.append(row);
            rows.push(row);
        }
        // Into the field's box at the moment of showing: drawExtraFields builds
        // the box after the field is bound.
        if (list.parentElement !== (input.closest('.field') ?? input.parentElement)) {
            (input.closest('.field') ?? input.parentElement)?.append(list);
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        if (current && current.input !== input) current.close();
        current = self;
    };

    const move = (step) => {
        if (rows.length === 0) return;
        rows[cursor]?.classList.remove('is-active');
        cursor = (cursor + step + rows.length) % rows.length;
        rows[cursor].classList.add('is-active');
        rows[cursor].scrollIntoView({ block: 'nearest' });
    };

    input.addEventListener('input', open);
    input.addEventListener('keydown', (e) => {
        if (list.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter' && cursor >= 0) { e.preventDefault(); pick(rows[cursor].textContent); }
        // Only the list closes — not the window around it.
        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
    });
    // Another field taking the focus closes it (the tap beside it: at the head).
    input.addEventListener('blur', () => {
        setTimeout(() => { if (current === self && document.activeElement !== input && document.activeElement !== document.body) close(); }, 0);
    });
}
