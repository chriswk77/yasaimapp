/**
 * THE MICROSCOPE'S WORDS, SHOWN INTERNATIONAL (23.11.2026: „ich finde
 * Nationalitäten dumm. Wir sollten international denken … Kann man bei den
 * Motivnamen nicht eine Übersetzungstabelle machen? … Die [Makronamen]
 * würde ich national machen. Die Mikronamen international", and to the
 * question about the files: „Variante 1, Dateinamen bleiben deutsch").
 * Plates travel through Europe; a „Cheilozystiden" on one is a word only
 * Germans read, „Cheilocystidia" everybody who reads a key.
 *
 * DISPLAY ONLY. What is stored stays German — the picture names, the
 * measuring layers, parts and marks on every measure, the plate's blocks,
 * the cloud's file names (cloud_names.php) — exactly as the app's own
 * catalogs keep the German sentence as the key. So nothing is migrated,
 * nothing renamed in a cloud, and a word can be changed here tomorrow
 * without touching a single row. Every place that SHOWS one of these words
 * goes through `vocab()`; every place that compares, stores or names a
 * file keeps the German word. Where a person types or picks a word, it
 * goes back through `vocabStored()`, so „Spores" typed is „Sporen" kept.
 *
 * WHAT STAYS NATIONAL: the macro picture names (Belegfoto, Hauptmotiv …,
 * the woods' words; a later English catalog may take them through t()) —
 * and so „Sklerotium", which stands in BOTH lists as one stored word: a
 * gallery head cannot tell a macro sclerotium from a micro one, and the
 * same picture must not be named two ways —,
 * the tool words „Beschriftung" and „Ausschnitte", and the plate's
 * Fundangaben. A word somebody made up stands as it was typed.
 *
 * THE SHORT FORM (22.11.2026, plateparts.js labelWord): the plate's label
 * may stand abbreviated — Cheilo, Pleuro, Caulo, Pileo. „Pileipellis" has
 * none; the German „HDS" was the short form of a German word.
 *
 * A TWIN: includes/vocabulary.php carries the same table for what the
 * server writes (the find report); tests/vocabulary.php holds the two
 * line for line. A word changes in both files.
 */

import { t } from './i18n.js';

// The German word as it is stored → [international, short form].
const WORDS = {
    // Picture names (photos.php, PHOTO_TITLES_MICRO) and measuring layers (photowindow.js, MEASURE_COLUMNS).
    'Übersichtsschnitt': ['Overview section'],
    'Sporen': ['Spores'],
    'Konidien': ['Conidia'],
    'Basidien': ['Basidia'],
    'Cheilozystiden': ['Cheilocystidia', 'Cheilo'],
    'Pleurozystiden': ['Pleurocystidia', 'Pleuro'],
    'Kaulozystiden': ['Caulocystidia', 'Caulo'],
    'Pileozystiden': ['Pileocystidia', 'Pileo'],
    'HDS (Hutdeckschicht)': ['Pileipellis'],
    'Velum Hut': ['Velum pileus'],
    'Velum Stiel': ['Velum stipe'],
    'Paraphysen': ['Paraphyses'],
    'Randhaare': ['Marginal hairs'],
    'Schnallen': ['Clamps'],
    'Schnallen Hymenium': ['Clamps hymenium'],
    'Schnallen Hut': ['Clamps pileus'],
    'Schnallen Stiel': ['Clamps stipe'],
    'Hyphen hymenial': ['Hyphae hymenial'],
    'Hyphen Kontext': ['Hyphae context'],
    'Hyphen Stiel': ['Hyphae stipe'],
    'Hyphen Hutdeckschicht': ['Hyphae pileipellis'],
    'Myzel': ['Mycelium'],
    'Lamellen': ['Lamellae'],
    'Poren': ['Pores'],
    // Retired layers that older measures still carry (photowindow.js, MEASURE_COLUMNS says why they went).
    'Generative Hyphen': ['Generative hyphae'],
    'Skeletthyphen': ['Skeletal hyphae'],
    'Bindehyphen': ['Binding hyphae'],
    'Hyphen Trama': ['Hyphae trama'],
    // Parts (photowindow.js, MEASURE_PARTS).
    'Keimporus': ['Germ pore'],
    'Ornamenthöhe': ['Ornament height'],
    'Öltropfen': ['Guttule'],
    'Sterigmenlänge': ['Sterigma length'],
    'Kopfbreite': ['Head width'],
    'Halsbreite': ['Neck width'],
    'Ektalexcipulum': ['Ectal excipulum'],
    'Medullarexcipulum': ['Medullary excipulum'],
    // Marks (photowindow.js, CYSTIDIA_KINDS and HYPHA_KINDS).
    'Chrysozystide': ['Chrysocystidium'],
    'Gloeozystide': ['Gloeocystidium'],
    'Lamprozystide': ['Lamprocystidium'],
    'Generative Hyphe': ['Generative hypha'],
    'Skeletthyphe': ['Skeletal hypha'],
    'Bindehyphe': ['Binding hypha'],
};

const key = (word) => String(word ?? '').trim().toLowerCase();
const FORMS = new Map(Object.entries(WORDS).map(([german, forms]) => [key(german), forms]));
const BACK = new Map(Object.entries(WORDS).map(([german, [shown]]) => [key(shown), german]));

/** A stored word as it is shown: international where the table knows it, else as it was typed. */
export function vocab(word) {
    return FORMS.get(key(word))?.[0] ?? String(word ?? '').trim();
}

/** Whether a stored word has a short form for the plate's label. */
export const vocabShortens = (word) => (FORMS.get(key(word))?.length ?? 0) > 1;

/** A stored word as the plate's label writes it: short where asked and there is one, else as vocab(). */
export function vocabLabel(word, short) {
    const forms = FORMS.get(key(word));
    if (!forms) return String(word ?? '').trim();
    return short && forms[1] ? forms[1] : forms[0];
}

/**
 * The German word to keep for what a person typed or picked: an
 * international word goes back to its stored one, spelled as the stock
 * spells it; anything else stays as typed.
 */
export function vocabStored(typed) {
    const word = String(typed ?? '').trim();
    return BACK.get(key(word)) ?? word;
}

/** A name made of words joined by „ · " („Sporen · Keimporus", „Sporen · Auswertung"), each word shown as vocab() shows it. */
export const vocabPhrase = (name) => String(name ?? '').split(' · ').map(vocab).join(' · ');

/* --- THE PLATE SPEAKS ENGLISH ----------------------------------------------
 *
 * 25.11.2026: „Da ich weitestgehend eine englischsprachige Tafel will, können
 * wir die deutschen Makrobezeichnungen, wenn wir sie als Label in der Tafel
 * anzeigen … übersetzt anzeigen lassen, und auch in der Verteilung steht ja
 * Breite und Länge, das dann bitte auch in Englisch."
 *
 * ONLY ON THE PLATE. In the app the macro names stay national (the head of
 * this file says why), and the photo window's own distribution keeps the
 * app's language; a plate is the one thing that travels, so it alone wears
 * English whatever the page speaks. Stored is still the German word — the
 * plate only draws it differently.
 *
 * THE MACRO NAMES (photos.php, PHOTO_TITLES_MACRO): in the words of an
 * English description — „Habit" for the find in its place, „Hymenophore"
 * for the underside, whether gills, tubes or spines. A word the table does
 * not know (one of one's own) stands as typed, as everywhere. „Velum",
 * „Detail" and „Habitat" are the same word in both languages and need no
 * row; „Sklerotium" gets its Latin form here, where nobody has to tell a
 * macro picture from a micro one.
 */
const PLATE_MACRO = {
    'Belegfoto': 'Voucher',
    'Hauptmotiv': 'Habit',
    'Kollektion': 'Collection',
    'Studio': 'Studio',
    'Größenvergleich': 'Scale',
    'Standort': 'Site',
    'Hutoberseite': 'Pileus',
    'Unterseite': 'Hymenophore',
    'Schnittbild': 'Section',
    'Stielbasis': 'Stipe base',
    'Makrochemie': 'Macrochemistry',
    'Sporenpulver': 'Spore print',
    'Makrochemie SPP': 'Macrochemistry spore print',
    'Exsikkat': 'Exsiccatum',
    'Sklerotium': 'Sclerotium',
};
const PLATE_FORMS = new Map(Object.entries(PLATE_MACRO).map(([german, shown]) => [key(german), shown]));

/** A picture name or layer as the PLATE's label writes it: the micro words as vocabLabel(), the macro words in English. */
export function plateWord(word, short) {
    return FORMS.has(key(word)) ? vocabLabel(word, short) : (PLATE_FORMS.get(key(word)) ?? String(word ?? '').trim());
}

/* The plate's own sentences — the distribution's axes and spread line, a
   cystidium's parts among the values, the counts' lines, the details'
   words (25.11.2026: „mach alles englisch"). Anything else goes through
   the app's catalog as before (t()). FINDER AND BESTIMMER ARE „leg." AND
   „det.", as herbarium labels write them the world over — not a
   word-for-word „Finder", which an English reader takes for a tool. The
   VALUES stay as written: a date, a place, a remark are the find's own. */
const PLATE_SENTENCES = {
    'Länge µm': 'Length µm',
    'Breite µm': 'Width µm',
    'N = %d · Streuung Länge ± %d %%, Breite ± %d %% · Ausreißer %d': 'N = %d · spread length ± %d %%, width ± %d %% · outliers %d',
    'Streuung Länge ± %d %%, Breite ± %d %% · Ausreißer %d': 'Spread length ± %d %%, width ± %d %% · outliers %d',
    'Köpfchen': 'Capitulum',
    'Hals': 'Neck',
    'Kopf': 'Head',
    // The values' brackets and the names of a row of cutouts or counts.
    'ohne Merkmal': 'unmarked',
    'Zählungen': 'Counts',
    'Ausschnitte': 'Cutouts',
    // The counts: sectors and frames.
    'Lamellen': 'Lamellae',
    'Lamelletten': 'Lamellulae',
    'Quadrat %s · Anzahl %d': 'Square %s · count %d',
    'Rechteck %s · Anzahl %d': 'Rectangle %s · count %d',
    'je cm²': 'per cm²',
    'je mm²': 'per mm²',
    'je 10 × 10 µm': 'per 10 × 10 µm',
    // The details (Fundangaben), as the plate prints their words.
    'Fundnummer': 'Coll. no.',
    'Funddatum': 'Date',
    'Fundort': 'Locality',
    'Höhe': 'Elevation',
    'Finder': 'leg.',
    'Bestimmer': 'det.',
    'Bestimmungssicherheit': 'Certainty',
    'Anzahl / Menge': 'Abundance',
    'Substrat / Wirt': 'Substrate / host',
    'Begleitvegetation': 'Associated vegetation',
    'Geruch': 'Smell',
    'Geschmack': 'Taste',
    'Sporenpulver': 'Spore print',
    'Beleg-Nr.': 'Voucher no.',
    'Bemerkung': 'Remark',
    'Notiz': 'Note',
};

/** t() for what the plate draws: its own English where PLATE_SENTENCES has it, the app's catalog otherwise. */
export function plateT(text, ...args) {
    const english = PLATE_SENTENCES[text];
    if (english === undefined) return t(text, ...args);
    let auto = 0;
    return english.replace(/%%|%[sd]/g, (whole) => (whole === '%%' ? '%' : String(args[auto++] ?? '')));
}
