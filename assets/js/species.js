/**
 * Species search in the browser, from individually obtainable name packages.
 *
 * WHY PACKAGES
 * Whoever maps only lichens gains nothing from 39,248 fungus names. The
 * fungus list is 560 kB packed, the lichen list 36 kB — and both are fetched
 * over the same mobile connection that is scarce in the woods anyway. So you
 * choose in the administration what concerns you; here is how that works
 * technically.
 *
 * WHERE THE PACKAGES LIE
 * In the browser's cache storage, not in localStorage. The former is meant
 * for megabytes of text, works concurrently instead of blocking — and it is
 * the same storage the map takes its tiles from.
 *
 * WHICH PACKAGES ARE THERE IS RECORDED NOWHERE SEPARATELY. It follows from
 * what lies in the storage. A second set of books about it would drift apart
 * sooner or later — you clear the storage in the browser, and the list keeps
 * claiming everything is there.
 *
 * SEARCHING WORKS AS ON THE SERVER, so the suggestions do not differ
 * depending on whether the packages are already there: fragments from three
 * characters up, each must occur as a word start, in the scientific or the
 * German name. The order follows the server's keys one by one — see
 * searchSpeciesLocally().
 */

import { api } from './api.js';
import { t } from './i18n.js';
import { settings } from './settings.js';
import { deviceTakeover } from './legacy.js';

const STORE = 'species';
const MIN_CHARS = 3;
const PER_PAGE = 50;

/**
 * Whose statement counts when a name stands in several packages.
 *
 * From back to front: what stands further right wins.
 *
 * GBIF stands leftmost because it knows the least about a name — no German
 * name, no threat classification. It is the breadth, not the depth. And
 * between the two Germany lists, for the 377 names that stand in both, the
 * lichen list counts: it comes from the lichenologists and has the
 * classification for exactly this taxon.
 *
 * A SYNONYM IS NO STATEMENT AGAINST A SPECIES: a valid name yields only to a
 * valid one, whatever the order — see loadSpeciesList().
 *
 * The server has the same rule — see species_branch() in includes/species.php.
 * If it stood in only one of the two places, you would see one thing online
 * and another offline.
 */
const PRECEDENCE = ['faeu', 'gbif', 'fungi', 'lichens', 'plants', 'animals'];

let list = null;      // [{name, author, german, rl, target, search}] across all packages
let loading = null;      // loading in progress, so it does not start twice
let directory = null;

/**
 * The parameter that names a package — ONE place, and that is the point.
 *
 * It stood written out twice: packageUrl() wrote '?package=', and
 * storedPackages() read 'paket' out of the very same address. The English
 * rename had translated the writing side and not the reading one.
 *
 * That did not merely make the lookup fail. Whatever storedPackages()
 * cannot name it takes for an old deposit and DELETES — so every downloaded
 * package was thrown away the next time the list was looked at, and the
 * search stood there empty in the forest with the data supposedly on the
 * device. A constant cannot drift apart from itself.
 */
const PACKAGE_PARAM = 'package';

/**
 * Which groups a package serves — and thereby what switches it on and off.
 *
 * The settings do not offer packages but SUBJECTS: fungi, lichens, plants.
 * GBIF is a fungus list, so it hangs on the same switch as the German one —
 * the rule of 19.08.2026: no fungi means neither of the two.
 */
export const PACKAGE_GROUP = {
    fungi:    'fungi',
    gbif:     'fungi',
    lichens: 'lichens',
    plants: 'plants',
};

/** Which subject a package belongs to — else the package name is the subject. */
export function groupOf(identifier) {
    return PACKAGE_GROUP[identifier] ?? identifier;
}

/**
 * THE GROUPS THIS DEVICE KEEPS FOLLOW FROM THE LISTS
 * (12.11.2026, migration 0195). Until then one choice per device —
 * Einstellungen → Arten, fungi for whoever never chose — decided which
 * names were suggested in every species list and which packages lay on
 * the phone. Now each list says what it holds, and the device keeps what
 * the lists it can write into need: the groups of its own and its shared
 * species lists, and the fixed groups of „Substrat / Wirt" and
 * „Begleitvegetation" (find_fields.php). map.js works that out once the
 * lists stand (speciesNeedsSync) and hands it here; it is kept, so a
 * start without network knows it before anything is loaded.
 *
 * NULL IS „NOT KNOWN YET", never „all" or „none": nothing is fetched for
 * it and nothing taken away — a device that has not seen its lists must
 * not throw away the names it will need in the woods.
 */
const NEEDED_KEY = 'speciesGroupsNeeded';
let needed = null;

export function neededGroups() {
    if (needed) return needed;
    const kept = settings.get(NEEDED_KEY, null);
    return Array.isArray(kept) ? kept : null;
}

/** The lists' groups, from map.js. True when they changed — then the caller loads anew. */
export function setNeededGroups(groups) {
    const next = [...new Set(groups)].sort();
    const before = neededGroups();
    needed = next;
    settings.set(NEEDED_KEY, next);
    const changed = !before || before.join(',') !== next.join(',');
    if (changed) list = null;
    return changed;
}

/** What a list's title suggests — its groups, fungi where it never chose (the old default). */
export function listGroups(list) {
    return Array.isArray(list?.species_groups) ? list.species_groups : ['fungi'];
}

/**
 * „Schon gefundene Arten zuerst vorschlagen" (until 25.09.2026 „Bereits
 * kartierte Arten zuerst") — begun as an experiment on 19.09.2026 („dass
 * der Artenvorschlag priorisiert wird: Arten, die auf der Tour schon mal
 * vorkamen, ganz oben; Arten, die in dem Monat über alle Jahre hinweg
 * gefunden wurden, als zweite Stufe; großflächig das Gebiet, 100 km; und
 * nach Häufigkeit"). A switch of this device like the groups above — ON
 * by default since the same evening („mach das mal
 * standardmäßig bei allen Benutzern an"), and PART OF THE PROGRAM since
 * 21.09.2026 („das hat wirklich super funktioniert — das ist kein Versuch
 * mehr"); whoever wants the bare reference switches it off. The ranking itself lives in map.js
 * (familiarSpecies).
 */
export function familiarFirst() {
    return settings.get('familiarFirst', true) !== false;
}

export function setFamiliarFirst(on) {
    settings.set('familiarFirst', on === true);
}

/** The address under which a package is stored. */
function packageUrl(identifier) {
    return api.url('species_list') + '?' + PACKAGE_PARAM + '=' + encodeURIComponent(identifier);
}

/** A name without its sensu qualifier — „Armillaria mellea agg." → „Armillaria mellea".
 *  The same six shapes species_qualifier_sql() reads off the DGfM list. */
function bareName(name) {
    return name.replace(/\s+(?:agg\.|Gruppe|s\. str\.|s\. l\.|(?:non )?ss\. .*)$/u, '');
}

/**
 * A text as the server's LIKE compares it: accents off.
 *
 * ITS COLUMNS ARE utf8mb4_unicode_ci, and that collation ignores accents:
 * „Röhrling" and „rohrling" are one word there. Here they were not, and
 * whoever typed „rohrling" in the woods found nothing, while at home the
 * server offered 37 Röhrlinge (26.09.2026). NFD takes an accent off its
 * letter. Æ, ø and ł stay themselves — letters of their own there as well,
 * measured.
 *
 * TWO COMPARISONS, NOT ONE. LIKE goes letter by letter and knows no
 * expansion: 'Rotfuß' LIKE 'rotfuss%' is false there, so ß stays ß here —
 * this is what the sort keys use. The full-text index, which decides what
 * is found at all, does expand; that is fold() below.
 *
 * Only a text with something outside ASCII takes the long way: 9,728 of
 * 725,000 names and German names, and the list is built on the phone.
 */
function unaccent(text) {
    const lower = text.toLowerCase();
    return /[^\x00-\x7f]/.test(lower) ? lower.normalize('NFD').replace(/\p{M}+/gu, '') : lower;
}

/**
 * A text as the server's full-text index compares it: accents off, and ß
 * and œ spelled out as the collation expands them — there „Ruß" is „russ",
 * and even „rotfus" finds „Rotfuß" (measured, 26.09.2026).
 */
function fold(text) {
    const plain = unaccent(text);
    return /[ßœ]/.test(plain) ? plain.replace(/ß/g, 'ss').replace(/œ/g, 'oe') : plain;
}

/**
 * Search text of a row: both names folded (fold), and every run of what is
 * not a letter or a digit one space — the words the server's full-text
 * index knows. Until 26.09.2026 only the hyphen parted words here, and a
 * word behind a bracket, a quote or the hybrid sign stayed hidden offline:
 * „Queradriger Rötling (kleinsporige Varietät)", „"Samtkappe"", „Quercus
 * ×streimii" — some 1,300 rows.
 */
function searchText(name, german) {
    const text = fold(name + ' ' + (german || ''));
    return /[^a-z0-9 ]/.test(text) ? text.replace(/[^\p{L}\p{N}]+/gu, ' ') : text;
}

// --------------------------------------------------------------- Directory

/**
 * What packages exist.
 *
 * Without a network this cannot be answered — then only what lies in the
 * storage remains. That is why on error this returns null and not an empty
 * list: "I do not know" is something other than "there are none".
 */
export async function packageDirectory(fresh = false) {
    if (directory && !fresh) return directory;
    try {
        const answer = await api.get('species_packages');
        directory = answer.packages;
        return directory;
    } catch (e) {
        console.warn('Paketverzeichnis nicht erreichbar:', e);
        return directory;
    }
}

/** Which packages lie on this device — including the stored edition. */
export async function storedPackages() {
    await deviceTakeover;
    if (!('caches' in window)) return new Map();

    const store = await caches.open(STORE);
    const have = new Map();

    for (const request of await store.keys()) {
        const identifier = new URL(request.url).searchParams.get(PACKAGE_PARAM);

        // What does not belong to a package goes away. This hits the old
        // deposit without a package parameter: it dates from the time when
        // there was only one list. Its tag no longer matches, it would
        // never refresh again and would lie in the storage forever as dead
        // freight.
        if (!identifier) {
            await store.delete(request);
            continue;
        }

        const answer = await store.match(request);
        const mark = answer?.headers.get('ETag') || '';
        have.set(identifier, { version: mark.replace(/^"?species-[^-]+-|"$/g, '') });
    }
    return have;
}

// ----------------------------------------------------------------- Settings

/**
 * Whether a package is fetched and refreshed by itself.
 *
 * The setting belongs to the DEVICE, not the account: on the phone in the
 * woods you want the names offline, at the desk with a network it makes no
 * difference. Only what deviates from the server's default is stored — if
 * the server changes its default, that takes effect on all devices that
 * have set nothing.
 */
/**
 * The key under which "keep current" is remembered — ONE place, like
 * PACKAGE_PARAM above and for the same reason.
 *
 * legacy.js renames these keys on every start from 'artenpaket-auto-' to
 * 'species-package-auto-' and removes the old one. This file kept reading
 * and writing the old name, so the setting never survived a restart: the
 * user switched it, the next start moved it away, and the default applied
 * again. Nothing complained — a setting that quietly falls back to its
 * default looks exactly like a user who never changed it.
 */
const AUTO_KEY = 'species-package-auto-';

export function readAutomatic(identifier, fallback) {
    try {
        const raw = localStorage.getItem(AUTO_KEY + identifier);
        return raw === null ? fallback : raw === '1';
    } catch {
        return fallback;
    }
}

export function writeAutomatic(identifier, on, fallback) {
    try {
        if (on === fallback) localStorage.removeItem(AUTO_KEY + identifier);
        else localStorage.setItem(AUTO_KEY + identifier, on ? '1' : '0');
    } catch { /* private mode — then the default just applies */ }
}

// -------------------------------------------------------------- Obtaining

/**
 * Fetch and store a package.
 *
 * WHY THE END IS CHECKED
 * The server sends the package line by line, and the header is long gone
 * when it runs out of time halfway through. What arrives then is truncated
 * JSON with status 200 — stored, everything would look fine, and only when
 * searching in the woods would it emerge that half the names are missing.
 * Hence: if the response does not end in "]}", it is incomplete and gets
 * thrown out again.
 *
 * The check runs on the stream, not the finished text — 22 MB as a string
 * would be avoidable ballast on the phone.
 *
 * @returns {Promise<number>} transferred bytes
 */
export async function getPackage(identifier) {
    const url = packageUrl(identifier);
    // no-cache, not default: otherwise the browser answers from its own
    // cache for a day, and "Aktualisieren" stored the old edition once
    // more.
    const answer = await fetch(url, {
        cache: 'no-cache',
        headers: { Accept: 'application/json' },
    });
    if (!answer.ok) throw new Error('HTTP ' + answer.status);

    const store = await caches.open(STORE);
    const copy = answer.clone();
    await store.put(url, answer);

    let bytes = 0;
    let tail = '';
    const reader = copy.body.getReader();
    for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        bytes += value.length;
        tail = new TextDecoder().decode(value.slice(-2));
    }

    if (tail !== ']}') {
        await store.delete(url);
        throw new Error(t('Das Paket kam unvollständig an.'));
    }

    list = null;                       // rebuild on the next search
    return bytes;
}

/** Take a package off the device. */
export async function removePackage(identifier) {
    const store = await caches.open(STORE);
    await store.delete(packageUrl(identifier));
    list = null;
}

// ----------------------------------------------------------------- Search

/**
 * Merge all stored packages into one search list.
 *
 * If a package that is supposed to come automatically is missing, it is
 * fetched — but only with a network and only on the side: an error here
 * must never hold up the input. Searching then simply goes via the server.
 */
/**
 * Lay on the device what the lists need, and take away what they no longer
 * do — WITHOUT building the search list (12.11.2026: the
 * packages come at once, not at the first typed name: „wenn ich im Wald
 * stehe und keinen Empfang habe, würde mich das ärgern"). map.js calls this
 * as soon as the lists stand and whenever they change (speciesNeedsSync);
 * building the list of hundreds of thousands of names stays with the first
 * search, it is the expensive part on a phone.
 */
let syncing = null;
export function syncPackages() {
    if (syncing) return syncing;
    syncing = (async () => {
        if (!('caches' in window)) return;
        const have = await storedPackages();

        /* Fetch now what is supposed to come by itself and is still
           missing — and take away what nobody wants any more.

           THE SUBJECT DECIDES, NOT THE PACKAGE. Whoever takes lichens off
           every list should not have to think about which file that is;
           the mapping sits in PACKAGE_GROUP. Removing happens here, where
           the lists' need (neededGroups, local storage) and the packages
           (the cache) meet on every device. */
        const groups = neededGroups();
        for (const [identifier] of groups === null ? [] : [...have]) {
            if (groups.includes(groupOf(identifier))) continue;
            try {
                await removePackage(identifier);
                have.delete(identifier);
            } catch { /* then it stays lying and costs only space */ }
        }
        // Not known yet: nothing fetched (neededGroups says why).
        if (groups === null) return;
        const all = await packageDirectory();
        for (const p of all ?? []) {
            if (!groups.includes(groupOf(p.identifier))) continue;
            if (have.has(p.identifier) || !readAutomatic(p.identifier, p.automatic)) continue;
            try {
                await getPackage(p.identifier);
            } catch (e) {
                console.warn('Paket ' + p.identifier + ' nicht ladbar:', e);
            }
        }
    })().catch((e) => console.warn('Artenpakete nicht abgleichbar:', e)).finally(() => { syncing = null; });
    return syncing;
}

export async function loadSpeciesList() {
    if (list) return list;
    if (loading) return loading;

    loading = (async () => {
        try {
            if (!('caches' in window)) return null;

            await syncPackages();
            const have = await storedPackages();
            if (have.size === 0) return null;

            const store = await caches.open(STORE);
            const combined = [];
            /* A VALID NAME YIELDS ONLY TO A VALID NAME, A SYNONYM ONLY TO
               ANOTHER PACKAGE (26.09.2026). Three books: `seen` holds every
               name so far and the bare form of a qualified one, with the
               package that brought it — a synonym yields to that where
               another package put it there. A valid name yields only to an
               earlier valid row of the same name (`validNames`), and to the
               bare form of a valid qualified one from an EARLIER package
               (`validBare`).

               There was once only `seen`, and whichever row came first took
               the name. The fungi package is sorted by the DGfM binomial
               alone, so the misapplied „Lactarius pyrogalus ss. auct." (what
               authors called L. pyrogalus is L. circellatus) came before the
               Hasel-Milchling itself, its bare name counted as seen, and the
               species was gone offline — and „Lactarius hortensis", its
               synonym, pointed at nothing. Thirteen fungi went like that,
               seventeen to a synonym row of their very own name („Agrocybe
               molesta" → A. dura came before the species A. molesta), four
               to a lichen synonym, and 426 animals and some 3,700 GBIF names
               the same way. The server never had the rule: its fungi branch
               yields only to a VALID lichen name, GBIF only to the names
               shown and their bare forms (species_search).

               „First valid row wins" stays, and within a package too: an
               animal package carries the Stuttgart list and Fauna Europaea
               side by side, and the row with the German name comes first.
               The bare form blocks only a later package, because the DGfM
               list itself carries „Hygrocybe mucronella agg." AND
               „Hygrocybe mucronella" as two valid taxa.

               ONE NAME FOR TWO TAXA IS A LIST'S OWN STATEMENT, and a synonym
               keeps it within its package since the same day: Fauna
               Europaea's „Phalaena quercus" is a name of Lasiocampa quercus
               and — cleaned of its „(null)" by migration 0135 — of its
               nominate subspecies too, and the server offers both. Taking
               the first such row dropped the other: some 2,400 pairs of the
               fungi, lichens and animals before the cleaning (GBIF's
               homonyms beside them), 3,500 more after it — and which one
               went, the species or the subspecies, was the sort's chance.
               Only another package's word on the name counts against a
               synonym, as before. */
            const seen = new Map();          // any name, and a qualified one's bare form -> package
            const validNames = new Map();    // valid name -> its entry
            const validBare = new Map();     // bare form of a valid qualified name -> package

            // In order of precedence, descending: what comes first stays.
            // Unknown packages last — they are newer than this file and
            // must not displace anything known.
            const ordered = [...have.keys()].sort(
                (a, b) => (PRECEDENCE.indexOf(b) + 1 || 0) - (PRECEDENCE.indexOf(a) + 1 || 0)
            );

            for (const identifier of ordered) {
                const answer = await store.match(packageUrl(identifier));
                if (!answer) continue;
                const data = await answer.json();

                let last = null;
                for (const row of data.species) {
                    const [name, author, german, rl, target] = row;
                    // The same name for the same taxon once more — two
                    // authors of one combination, mostly GBIF's — says
                    // nothing new. The package is sorted by name, so the twin
                    // stands right before it (7,959 of 8,495, measured on
                    // 26.09.2026), and no book of every pair is needed.
                    const repeat = target && last !== null && last[0] === name && last[4] === target;
                    last = row;
                    /* THE BARE NAME COUNTS AS SEEN TOO (13.09.2026): the DGfM
                       list delivers „Xerocomellus chrysenteron agg." and
                       „… s. str.", GBIF the bare „Xerocomellus chrysenteron"
                       — the same species a third time, without German name
                       and category. What the Germany list knows under a
                       qualifier is not a gap GBIF has to fill. The server
                       does the same (species_search). */
                    const bare = bareName(name);
                    if (target) {
                        if (repeat) continue;
                        const claimed = seen.get(name);
                        if (claimed !== undefined && claimed !== identifier) continue;
                    } else {
                        const kept = validNames.get(name);
                        if (kept) {
                            /* The first valid row stays — and where it names
                               no author, the row of its own package that
                               yields lends its own (26.09.2026). An animal
                               package brings the Stuttgart row first, and
                               the Stuttgart list names no author for most of
                               its genera; Fauna Europaea does, and the server
                               takes it just so (species_branch). Only within
                               a package: a fungus that yields to a lichen
                               name lends the lichen nothing there either. */
                            if (!kept.author && author && kept.source === identifier) kept.author = author;
                            continue;
                        }
                        const holder = validBare.get(name);
                        if (holder !== undefined && holder !== identifier) continue;
                        if (bare !== name && !validBare.has(bare)) validBare.set(bare, identifier);
                    }
                    if (!seen.has(name)) seen.set(name, identifier);
                    if (bare !== name && !seen.has(bare)) seen.set(bare, identifier);
                    // Which package the name comes from is also its source —
                    // the server needs that to turn it into the stable
                    // reference.
                    // `to` is filled below; named here so that every entry
                    // has the same shape from the start.
                    const entry = { name, author, german, rl, target, to: null, source: identifier,
                                    search: searchText(name, german) };
                    if (!target) validNames.set(name, entry);
                    combined.push(entry);
                }
            }

            /* EVERY SYNONYM FINDS ITS TARGET ONCE, HERE — not on every
               keystroke (26.09.2026). searchSpeciesLocally() built this
               name table anew for each search: 73 of 99 ms per keystroke on
               a desktop with the fungi and GBIF, 367,000 names to find the
               few a synonym points at, while nothing in it changes between
               two keystrokes. Kept as a table it would hold some 13 MB on
               the phone for good; so each synonym takes its target along as
               `to`, and the table goes once the list stands.

               THE VALID ROW WHERE A NAME HAS ONE: a name may stand twice,
               as a species and as a synonym of another („Agrocybe molesta",
               see above), and a plain map keeps the last row written. A
               synonym row stands in only where no valid row carries the
               name, as it always did — a target the bare-name rule kept
               out, a synonym of a synonym. */
            const byName = new Map();
            for (const a of combined) {
                const held = byName.get(a.name);
                if (!held || (held.target && !a.target)) byName.set(a.name, a);
            }
            for (const a of combined) {
                if (a.target) a.to = byName.get(a.target) ?? null;
            }

            list = combined;
            list.packages = [...have.keys()];
            return list;
        } catch (e) {
            // Do not swallow tacitly. During construction exactly that cost
            // half an hour: the list never arrived, and nothing said why.
            // Searching continues regardless — just via the server.
            console.warn('Artenliste nicht ladbar:', e);
            return null;
        } finally {
            loading = null;
        }
    })();

    return loading;
}

/**
 * Are the stored editions still the current ones?
 *
 * Costs one bodyless request per package: the server answers with 304 as
 * long as nothing has changed. If it fails — no network — the existing
 * edition stays valid. An outdated name list is infinitely better than none
 * at all.
 *
 * Only for packages that are already there and are to be kept current. This
 * function NEVER fetches something new — otherwise, via the "refresh"
 * detour, exactly the download would come about that the default is meant
 * to prevent.
 *
 * @returns {Promise<string[]>} which packages were renewed
 */
export async function refreshSpeciesList() {
    if (!('caches' in window)) return [];

    const refreshed = [];
    try {
        const store = await caches.open(STORE);
        const all = await packageDirectory(true);
        if (!all) return refreshed;

        const groups = neededGroups();
        for (const p of all) {
            // A package for a subject nobody records is not refreshed — it
            // is on its way out anyway (see loadSpeciesList).
            if (groups !== null && !groups.includes(groupOf(p.identifier))) continue;
            const old = await store.match(packageUrl(p.identifier));
            if (!old) continue;
            if (!readAutomatic(p.identifier, p.automatic)) continue;

            const mark = old.headers.get('ETag');
            if (mark === '"species-' + p.identifier + '-' + p.version + '"') continue;

            // cache: 'no-cache' forces the check-back with the server.
            // Without it the browser answers the request from its own cache
            // for a day — the response carries Cache-Control: max-age=86400
            // — with the OLD content and status 200, that is. The refresh
            // then stored the old edition once more and reported success.
            const answer = await fetch(packageUrl(p.identifier), {
                cache: 'no-cache',
                headers: { Accept: 'application/json', ...(mark ? { 'If-None-Match': mark } : {}) },
            });
            if (answer.status === 304 || !answer.ok) continue;

            await store.put(packageUrl(p.identifier), answer);
            list = null;
            refreshed.push(p.identifier);
        }
    } catch (e) {
        // Offline is not an error — everything else is.
        if (navigator.onLine) console.warn('Artenpakete nicht auffrischbar:', e);
    }
    return refreshed;
}

/** The input's words without accents (unaccent) — every one, the short ones too. */
function words(input) {
    return unaccent(input).split(/[^\p{L}\p{N}]+/u).filter(Boolean);
}

/** Split the input into fragments — like species_expression() on the server. */
function fragments(input) {
    return words(input).filter(s => s.length >= MIN_CHARS);
}

/** Does the fragment occur as a word start? */
function matches(search, fragment) {
    if (search.startsWith(fragment)) return true;
    return search.includes(' ' + fragment);
}

/**
 * Search in the stored packages.
 *
 * @returns {{hits: Array, total: number}|null} null when nothing is there
 */
export function searchSpeciesLocally(input, offset = 0, groups = null) {
    if (!list) return null;
    /* ONLY THE FIELD'S GROUPS (12.11.2026): the list holds every package
       the device keeps, the field asks for its own — a fungi list's title
       the fungi, „Begleitvegetation" the plants. Null: all. */
    const wanted = groups ? new Set(groups) : null;

    const parts = fragments(input);
    if (parts.length === 0) return { hits: [], total: 0 };

    const firstFragment = parts[0];
    // The whole input as one piece, the short words included — what
    // species_expression() hands the server for its first key.
    const whole = words(input).join(' ');
    // Found as the full-text index finds, with ß and œ spelled out (fold);
    // the keys below compare the unexpanded parts, as LIKE does.
    const tokens = parts.map(fold);
    /* Does the input SPELL a name out — two words or more, each the start
       of the name's word in the same place, none of the name's left over?
       The server's first key; species_search() says why („Marasmiellus
       ramealis" put its variety above Collybiopsis ramealis). Asked of the
       name an entry was found under, the fungi's without their qualifier,
       as the server asks the DGfM's bare binomial. */
    const spelling = whole.includes(' ') ? whole.split(' ') : null;
    const spells = (entry) => {
        const own = unaccent(entry.source === 'fungi' ? bareName(entry.name) : entry.name).split(' ');
        return own.length === spelling.length && spelling.every((w, i) => own[i].startsWith(w));
    };
    const spelled = new Set();
    const raw = [];

    for (const a of list) {
        if (wanted && !wanted.has(groupOf(a.source))) continue;
        let fits = true;
        for (const t of tokens) {
            if (!matches(a.search, t)) { fits = false; break; }
        }
        if (fits) raw.push(a);
    }

    // A synonym yields the valid name — resolved once when the list was
    // built (loadSpeciesList, `to`). If that already stands in the list
    // anyway, it would otherwise be in there twice.
    const done = new Map();

    for (const a of raw) {
        const target = a.target ? a.to : a;
        if (!target) continue;
        // Every entry that leads here counts, not only the one kept — the
        // server takes MAX() over all rows of a name.
        if (spelling && spells(a)) spelled.add(target.name);
        // A direct hit beats one via an old name: whoever types the valid
        // name should not be told what it is the current name of.
        if (!done.has(target.name) || (a === target && done.get(target.name).via)) {
            /* THE SERVER'S KEYS, ONE BY ONE AND IN ITS ORDER — the ORDER BY
               of species_search() in includes/species.php: the whole input
               as the start of the scientific name ("Boletus ed"), either
               name starting with the first fragment, the scientific one
               before the German one ("quer" means the oak, not the
               Queradriger Rötling), the German one after that ("sal
               weide"), the direct hit, the alphabet. No score: the server
               sorts by none either.

               THEY DRIFTED APART ONCE, for six weeks. On 09.08.2026 the
               server learned that a German name must not overtake the
               scientific one, and to weigh the whole input; this side kept
               the two keys of that morning, German first. Offline "quer"
               put the German "Quer…" names above every oak — the very case
               the server had been fixed for — and "Boletus ed" had Boletus
               aereus on top. Online it showed too: the list drawn from here
               stands until the server answers, and the SECOND page comes
               from here even with a network (the scroll handler in map.js),
               so it went on from a place the server's first page never
               had — names twice, others never. Whoever changes a key here
               changes it there.

               GBIF AFTER ALL OF THEM, before any key (26.09.2026): the server
               tops up with GBIF only behind the Germany lists, which carry
               the species concept that governs here, the German names and
               the categories — GBIF is the breadth for what they do not
               know. Sorted in among them, a GBIF name that merely began
               with the input went ahead of every Germany species: on
               „schwa" Schwanniomyces capriottii stood above them all, and
               elsewhere GBIF names pushed the DGfM's own species off the
               first page. Of 400 inputs only 249 had the same first ten
               online and offline. What counts is the suggestion's source:
               an old GBIF name that leads to a DGfM species is a DGfM
               suggestion. */
            // As the server's LIKE compares — „rohr" starts „Röhrling",
            // „rotfuss" does not start „Rotfuß" (unaccent).
            const name = unaccent(target.name);
            const scientific = name.startsWith(firstFragment);
            const germanStart = unaccent(target.german || '').startsWith(firstFragment);
            /* THE FIELD NAMES ARE THE SERVER'S — species_search() in
               includes/species.php. drawSpecies() renders both answers with
               one hand; a field named differently here simply disappears
               from the screen, and exactly that happened to 'german' and
               'via' when only this side got translated. */
            done.set(target.name, {
                name: target.name,
                author: target.author,
                german: target.german,
                red_list: target.rl,
                source: target.source ?? null,
                via: a === target ? null : a.name,
                _gbif: target.source === 'gbif' ? 1 : 0,
                _whole: name.startsWith(whole) ? 0 : 1,
                _start: (scientific || germanStart) ? 0 : 1,
                _scientific: scientific ? 0 : 1,
                _german: germanStart ? 0 : 1,
            });
        }
    }

    for (const hit of done.values()) hit._spelled = spelled.has(hit.name) ? 0 : 1;

    const all = [...done.values()].sort((x, y) =>
        x._gbif - y._gbif ||
        x._spelled - y._spelled ||
        x._whole - y._whole ||
        x._start - y._start ||
        x._scientific - y._scientific ||
        x._german - y._german ||
        (x.via ? 1 : 0) - (y.via ? 1 : 0) ||
        x.name.localeCompare(y.name));

    return {
        hits: all.slice(offset, offset + PER_PAGE)
            .map(({ _gbif, _spelled, _whole, _start, _scientific, _german, ...rest }) => rest),
        total: all.length,
    };
}
