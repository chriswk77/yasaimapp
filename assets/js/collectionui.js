/**
 * Aufsammlungen in the point window (migration 0174, includes/collections.php;
 * 15.10.2026). The same window serves the phone's map and the
 * Fundbrowser at the desk (pointwindow.js), so this is mounted by it and
 * knows nothing of either host beyond what it is handed.
 *
 * ON AN AUFSAMMLUNG: its number and whether the culture runs above the
 * title, the two culture days beside the find time — which says „Gesammelt
 * am" here —, and the species that grew from it with „Art hinzufügen".
 *
 * ON A SPECIES FROM ONE: „Aus Kultur" and the way back to the Aufsammlung,
 * the day it was determined with the days since collecting, and the find
 * time and place as plain text — they are the Aufsammlung's, and the server
 * keeps them in step (a frame would promise an entry the save ignores).
 */

import { request } from './api.js';
import { confirm } from './dialogs.js';
import { t } from './i18n.js';
import { CERTAINTY_FIELD } from './identification.js';
import { personName } from './names.js';

const el = (id) => document.getElementById(id);

/** The days between two dates (YYYY-MM-DD…), or null. */
function daysBetween(from, to) {
    if (!from || !to) return null;
    const a = Date.parse(String(from).slice(0, 10) + 'T00:00:00Z');
    const b = Date.parse(String(to).slice(0, 10) + 'T00:00:00Z');
    return Number.isFinite(a) && Number.isFinite(b) ? Math.round((b - a) / 86400000) : null;
}

/** 2026-09-14 → 14.09.2026, the way the app writes a day. */
function day(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value ?? ''));
    return m ? `${m[3]}.${m[2]}.${m[1]}` : '';
}

/* THE AUFSAMMLUNG'S STRICT COPIES on every species from it — the twin of
   COLLECTION_COPIED_FIELDS in includes/collections.php, which writes them
   and ignores whatever a species sends for them; the window shows them as
   text (pointwindow.js, applyDetailRights). The substrate is not among them:
   a fungus on a fungus names its own host (18.10.2026). */
export const COLLECTION_COPIED_FIELDS = ['Finder', 'Begleitvegetation'];

/**
 * saveFirst — saves what the window holds unsaved, true when it went
 * through; converted(point) — lays the server's answer into the map's
 * state and refills the window; isForeign(id) — whether the point is
 * somebody else's. All three are the point window's (pointwindow.js).
 */
export function mountCollection({ state, selectPoint, status, saveFirst = async () => true,
                                  converted = () => {}, isForeign = () => true }) {
    const line      = el('d-collection-line');
    const lineText  = el('d-collection-text');
    const openBack  = el('d-collection-open');
    const culture   = el('d-culture');
    const cultured  = el('d-cultured');
    const ended     = el('d-culture-ended');
    const idField   = el('d-identified-field');
    const identified = el('d-identified');
    const idHint    = el('d-identified-hint');
    const speciesField = el('d-collection-species-field');
    const speciesList  = el('d-collection-species');
    const addButton    = el('d-collection-add');
    const toFindButton = el('d-collection-to-find');
    const foundInput   = el('d-found');
    const foundLabel   = document.querySelector('label[for="d-found"]');
    const foundField   = foundInput?.closest('.field');
    const coordInput   = el('d-coord');
    const coordEdit    = el('d-coord-edit');

    if (!line || !culture || !idField || !speciesField) {
        // A page without the markup — nothing to do, and nothing to break.
        return { fill() {}, state: () => '', collect: () => ({}), isCollection: () => false, isSpecies: () => false };
    }

    let current = null;

    /** Open another find: in the Fundbrowser by its address, on the map in the window. */
    function openPoint(id) {
        if (document.getElementById('finds')) {
            /* IN PLACE WHERE THE PAGE KNOWS THE FIND (18.10.2026:
               „beim Wechsel von Aufsammlung zum Aufsammlungsfund wird mir immer
               angezeigt, dass die Verbindung nicht steht … letztendlich wird
               es dann doch geladen"). The reload cut off the requests still on
               their way — the species list, the autosave —, and each reported
               „Keine Verbindung" in the moment before the page went. The
               Fundbrowser opens a find it knows itself (finds.js, finds-open);
               only a species made just now is not among the finds it loaded
               with the page, and for that one the page still loads anew. */
            const asked = new CustomEvent('finds-open', { detail: { id, tab: 'text' }, cancelable: true });
            if (!document.dispatchEvent(asked)) return;
            location.hash = `fund=${id}&tab=text`;
            location.reload();
            return;
        }
        selectPoint(id);
    }

    function setReadOnly(input, on) {
        if (!input) return;
        input.readOnly = on;
        input.closest('.field')?.classList.toggle('is-read', on);
    }

    async function drawSpecies(point) {
        speciesList.replaceChildren();
        if (!(point.id > 0)) {
            addButton.disabled = true;
            addButton.title = t('Erst wenn die Aufsammlung gespeichert ist');
            return;
        }
        addButton.disabled = false;
        addButton.title = '';
        let answer;
        try {
            answer = await request(`collections.php?id=${point.id}`);
        } catch {
            return;   // without a connection the list waits for the next opening
        }
        if (current?.id !== point.id) return;
        const rows = answer?.species ?? [];
        /* THE WAY BACK FROM A MISTAKE (14.11.2026): a mushroom
           mapped as an Aufsammlung. Only one's own — co-owned and shared
           ones are foreign to the window (foreignPoint) — and only while
           nothing grew from it: a sample with species WAS a sample. The
           server holds the same two rules (collection_to_find). */
        if (toFindButton) toFindButton.hidden = rows.length > 0 || !pointOwn(point);
        if (rows.length === 0) {
            const li = document.createElement('li');
            li.className = 'person-row';
            li.innerHTML = `<span class="person-name hint-text">${t('Noch keine Art bestimmt.')}</span>`;
            speciesList.append(li);
            return;
        }
        for (const s of rows) {
            /* THE NAME IS TEXT, THE WAY IN A BUTTON at the row's end
               (18.10.2026: the row's name as a bare text button
               was „einfach nur grau", no button of the system) — the
               grammar of every person row: name and facts on the left, the
               button at the right end. */
            const li = document.createElement('li');
            li.className = 'person-row';
            const name = document.createElement('span');
            name.className = 'person-name';
            name.textContent = s.title || t('Ohne Art');
            const facts = [];
            if (s.find_number) facts.push(s.find_number);
            const after = daysBetween(point.found_at, s.identified_at);
            if (s.identified_at) {
                facts.push(after !== null
                    ? t('bestimmt %s, nach %d Tagen', day(s.identified_at), after)
                    : t('bestimmt %s', day(s.identified_at)));
            }
            if (!s.own) facts.push(t('von %s', personName(s.owner)));
            if (facts.length > 0) {
                const data = document.createElement('span');
                data.className = 'item-data';
                data.textContent = facts.join(' · ');
                name.append(data);
            }
            const open = document.createElement('button');
            open.type = 'button';
            open.className = 'button subtle small person-buttons';
            open.textContent = t('Fund öffnen');
            open.addEventListener('click', () => openPoint(s.id));
            li.append(name, open);
            speciesList.append(li);
        }
    }

    addButton.addEventListener('click', async () => {
        const point = current;
        if (!point?.is_collection || !(point.id > 0)) return;
        addButton.disabled = true;
        try {
            /* STRAIGHT TO THE SERVER, not through the queue: a species is
               added at home, weeks after the collecting, and the window has
               to open the find the server made — place, day and finder are
               the Aufsammlung's, the server sets them. */
            const answer = await request('points.php', {
                method: 'POST',
                body: JSON.stringify({
                    collection_id: point.id, title: '',
                    lat: point.lat, lon: point.lon,
                    lists: point.lists ?? [],
                    // An open find until a name is chosen, as every new find
                    // in a species list (map.js, createPoint).
                    extra: { [CERTAINTY_FIELD]: 'unbestimmt' },
                    client_id: crypto.randomUUID(),
                }),
            });
            if (answer?.id && !document.getElementById('finds')) state.points.set(answer.id, answer);
            status(t('Art angelegt. Trag den Namen ein, wenn du sie bestimmt hast.'));
            if (answer?.id) openPoint(answer.id);
        } catch (error) {
            status(error.isNetworkError ? t('Arten lassen sich nur mit Verbindung anlegen.') : error.message, true);
        } finally {
            addButton.disabled = false;
        }
    });

    /** Whether this Aufsammlung is the viewer's own — co-owned and shared ones are not. */
    function pointOwn(point) {
        return !isForeign(point.id);
    }

    toFindButton?.addEventListener('click', async () => {
        const point = current;
        if (!point?.is_collection || !(point.id > 0)) return;
        const yes = await confirm({
            title: t('In Fund umwandeln?'),
            text: t('Aus der Aufsammlung %s wird ein normaler Fund. Ort, Datum, Felder und Bilder bleiben. Die Nummer %s wird nicht noch einmal vergeben.',
                point.collection_number ?? '', point.collection_number ?? ''),
            button: t('Umwandeln'),
        });
        if (!yes) return;
        toFindButton.disabled = true;
        try {
            // What the window holds unsaved goes first — the refill would drop it.
            if (!(await saveFirst())) return;
            const fresh = await request(`collections.php?id=${point.id}&what=find`, { method: 'POST', body: '{}' });
            status(t('Die Aufsammlung ist jetzt ein Fund. Trag die Art ein.'));
            if (document.getElementById('finds')) {
                // The Fundbrowser loaded its finds with the page.
                location.hash = `fund=${point.id}&tab=text`;
                location.reload();
                return;
            }
            converted(fresh);
        } catch (error) {
            status(error.isNetworkError ? t('Umwandeln geht nur mit Verbindung.') : error.message, true);
        } finally {
            toFindButton.disabled = false;
        }
    });

    openBack.addEventListener('click', () => {
        if (current?.collection_id) openPoint(current.collection_id);
    });

    identified.addEventListener('input', () => drawIdentifiedHint());
    function drawIdentifiedHint() {
        const after = daysBetween(current?.found_at, identified.value);
        idHint.hidden = after === null;
        idHint.textContent = after === null ? '' : t('%d Tage nach dem Sammeln', after);
    }

    function fill(point) {
        current = point;
        const isCollection = !!point.is_collection;
        const fromCollection = !isCollection && point.collection_id != null;

        line.hidden = !isCollection && !fromCollection;
        culture.hidden = !isCollection;
        speciesField.hidden = !isCollection;
        // Shown by drawSpecies once it knows nothing grew from it.
        if (toFindButton) toFindButton.hidden = true;
        idField.hidden = !fromCollection;
        openBack.hidden = !fromCollection;

        if (foundLabel) foundLabel.textContent = isCollection || fromCollection ? t('Gesammelt am') : t('Fundzeit');
        // A species' day and place are its Aufsammlung's — plain text.
        setReadOnly(foundInput, fromCollection);
        if (coordEdit) coordEdit.hidden = fromCollection || coordEdit.hidden;
        if (coordInput && fromCollection) coordInput.readOnly = true;

        if (isCollection) {
            const runs = point.culture_ended_at ? t('Kultur beendet')
                : point.cultured_at ? t('in Kultur') : t('noch nicht in Kultur');
            lineText.textContent = `${t('Aufsammlung %s', point.collection_number ?? '')} · ${runs}`;
            cultured.value = point.cultured_at ?? '';
            ended.value = point.culture_ended_at ?? '';
            drawSpecies(point);
        } else if (fromCollection) {
            const parent = state.points.get(point.collection_id) ?? state.sharedPoints?.get(point.collection_id);
            lineText.textContent = t('Aus Kultur');
            openBack.textContent = parent?.collection_number
                ? t('Aufsammlung %s öffnen', parent.collection_number) : t('Zur Aufsammlung');
            identified.value = point.identified_at ?? '';
            drawIdentifiedHint();
        }
    }

    /** For the window's unsaved check. */
    function stateNow() {
        return JSON.stringify([cultured.value, ended.value, identified.value]);
    }

    /** What the save sends of 0174 — empty means „no day". */
    function collect(point) {
        if (point?.is_collection) {
            return { cultured_at: cultured.value || null, culture_ended_at: ended.value || null };
        }
        if (point?.collection_id != null) return { identified_at: identified.value || null };
        return {};
    }

    return {
        fill, state: stateNow, collect,
        isCollection: () => !!current?.is_collection,
        isSpecies: () => current?.collection_id != null && !current?.is_collection,
    };
}

