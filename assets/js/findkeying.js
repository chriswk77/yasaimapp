/**
 * The find's side of keying a find (14.10.2026) — what the
 * identification module calls when it walks a key FOR a find (its page,
 * opened with ?find=…). The walk is stored at the find, one per person
 * (api/v1/keyings.php, includes/point_keyings.php); a confirmed species
 * becomes the find's „Art".
 *
 * A CONTRACT WITH A MODULE'S CODE: its script imports this one
 * dynamically and only in a find's context, and relies on exactly these
 * three exports and what they return. Whoever changes them changes the
 * other side too. keyingBlocks() at the end is a second contract, with
 * the plates (plates.js, through finddetails.js findDetails → keyings).
 *
 * NO FURTHER IMPORTS: finddetails.js imports this module, and with it the
 * map, the plate editor and the plate print — a heavy or page-bound
 * import here would land in all of them. api.js is the one it may carry.
 */

import { api, request } from './api.js';

/**
 * Every walk at the find: { ready, point: {id, title, number}, mine:
 * {walk, confirmed_name, …} | null, others: [{user_id, name, walk, …}] }.
 */
export function load(pointId) {
    return request(`keyings.php?point=${encodeURIComponent(pointId)}`);
}

/**
 * Store one's own walk at the find (the shape the module's script writes).
 * options go to fetch — { keepalive: true } when the page is being left.
 */
export function save(pointId, walk, options = {}) {
    return request('keyings.php', { method: 'POST', body: JSON.stringify({ point: pointId, walk }), ...options });
}

/**
 * A species confirmed on the walk: the walk is stored with the name, and
 * the name becomes the find's „Art" — through the ordinary change of a
 * point, so every right holds as in the point window (one's own find, a
 * co-owned one, a share's right to the title). The last confirmed species
 * wins; confirming the one that stands already changes nothing. The
 * certainty is left alone (the maintainer: there are macroscopic keys too).
 *
 * @returns {Promise<{taken: boolean}>} taken: the find's „Art" is that name
 *   now (it was, or it was written); false when the walk was stored but the
 *   find's name could not be changed (no right to it).
 */
export async function confirm(pointId, walk, name) {
    const answer = await request('keyings.php', {
        method: 'POST', body: JSON.stringify({ point: pointId, walk, confirmed: name }),
    });
    const title = String(answer?.point?.title ?? '').trim();
    if (title === String(name).trim()) return { taken: true };
    try {
        const saved = await api.patch('points', pointId, { title: name });
        return { taken: String(saved?.title ?? name).trim() === String(name).trim() };
    } catch {
        return { taken: false };
    }
}

/**
 * THE KEY WAYS AS A PLATE SHOWS THEM (09.11.2026) — built from
 * point.keyings, which api/v1/finds.php delivers with the find
 * (point_keyings_plate: confirmed ways only, every keyer's). Synchronous on
 * purpose: a plate draws from the find object, in the editor and in print.
 *
 * A CONTRACT WITH plates.js (the Fundbrowser session): one block per way,
 * { key_id, source, book, key, title: 'Buch · Schlüssel', path:
 * '1a → 2b → 7a → Art', keys: [titles of the keys passed, in order],
 * path_by_key: [{ key_id, title, path }], result, remark, mine, by }. key_id is the key the
 * way ends in and the handle a plate item keeps; remark is the remarks of
 * the keys the way passed through, joined by a blank line, '' when none.
 * Only one's own ways unless { others: true }.
 */
export function keyingBlocks(point, { others = false } = {}) {
    const list = Array.isArray(point?.keyings) ? point.keyings : [];
    return list
        .filter((w) => w && (others || w.mine))
        .map((w) => {
            const book = String(w.book ?? '');
            const key = String(w.key ?? '');
            const label = (s) => String(s.label ?? '') + (s.sure === false ? '?' : '');
            const steps = (w.steps ?? []).map(label);
            const result = String(w.result ?? '');
            /* THE WAY BY KEY — „Hauptschlüssel: 1a → 5b · Teilschlüssel B:
               1a → 3b → Art" (09.11.2026, Gröger 2). Consecutive
               steps in one key form one run; the result closes the last. */
            const chain = (w.keys ?? []).map((k) => ({ key_id: k.key_id, title: String(k.title ?? '') }));
            const titleOf = (id) => chain.find((k) => k.key_id === id)?.title ?? '';
            const runs = [];
            for (const s of w.steps ?? []) {
                const id = s.key_id ?? null;
                if (!runs.length || runs.at(-1).key_id !== id) runs.push({ key_id: id, title: titleOf(id), steps: [] });
                runs.at(-1).steps.push(label(s));
            }
            if (result && runs.length) runs.at(-1).steps.push(result);
            return {
                key_id: w.key_id ?? null,
                source: w.source ?? null,
                book,
                key,
                title: [book, key].filter(Boolean).join(' · '),
                path: [...steps, ...(result ? [result] : [])].join(' → '),
                keys: chain.map((k) => k.title),
                path_by_key: runs.map((r) => ({ key_id: r.key_id, title: r.title, path: r.steps.join(' → ') })),
                result,
                remark: (w.remarks ?? []).map((r) => String(r.text ?? '').trim()).filter(Boolean).join('\n\n'),
                mine: !!w.mine,
                by: String(w.by ?? ''),
            };
        });
}
