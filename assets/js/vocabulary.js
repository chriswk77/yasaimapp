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
