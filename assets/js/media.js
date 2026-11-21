/**
 * THE MEDIA A MICROGRAPH IS TAKEN IN — water, lyes, iodine reagents,
 * dyes, metal salts. The maintainer's own list, dictated on 04.11.2026 („bei
 * vielen die chemischen Abkürzungen … H2O für Wasser … KOH 3 % … BWB …
 * beim Überfahren können wir ja ausschreiben, was es ist"), like the
 * measuring tool's layer names: mycological vocabulary, the deliberate
 * exception to the neutral-content rule.
 *
 * THE KEY IS WHAT IS STORED (migration 0187, map_cloud_files.media and
 * map_photos.media, and the sidecar beside the picture) — stable and
 * untranslated, so a word may change and a language be added without a
 * picture's record changing. The server checks only the shape of a key
 * (photo_media_clean), so a new entry here needs nothing there. NEVER
 * RENAME A KEY: pictures carry it.
 *
 * `short` is the label on the tick — the chemical formula where there is
 * one, the established short form (BWB, KR) or the name itself; no made-up
 * abbreviation nobody knows. `name` is written out under the pointer, for
 * whoever does not read the short form.
 *
 * COMMON AND FURTHER (04.11.2026: „da das so viele Medien sind
 * und ich eigentlich viele gar nicht oft brauche … in gängige und in
 * weitere aufteilen"): `common` stands in sight, the rest under „Weitere".
 * Within each, the sidebar's order: water and lyes, the iodine reagents,
 * the dyes, the metal salts.
 */
import { t } from './i18n.js';

export const MEDIA = [
    { key: 'h2o',             short: 'H₂O',            name: () => t('Wasser'), common: true },
    { key: 'koh_3',           short: 'KOH 3 %',        name: () => t('Kalilauge 3 %'), common: true },
    { key: 'melzer',          short: 'Melzer',         name: () => t('Melzers Reagenz'), common: true },
    { key: 'lugol',           short: 'Lugol',          name: () => t('Lugolsche Lösung'), common: true },
    { key: 'cotton_blue',     short: 'BWB',            name: () => t('Baumwollblau'), common: true },
    { key: 'congo_h2o',       short: 'KR H₂O',         name: () => t('Kongorot in Wasser'), common: true },
    { key: 'nh3',             short: 'NH₃',            name: () => t('Ammoniak') },
    { key: 'koh_40', short: 'KOH 40 %', name: () => t('Kalilauge 40 %') },
    { key: 'naoh_40', short: 'NaOH 40 %', name: () => t('Natronlauge 40 %') },
    { key: 'naocl', short: 'NaOCl', name: () => t('Natriumhypochlorit 12,5 %') },
    { key: 'hcl_3',           short: 'HCl 3 %',        name: () => t('Salzsäure 3 %') },
    { key: 'lactoglycerol',   short: 'Lactoglycerol',  name: () => t('Lactoglycerol') },
    { key: 'lactophenol', short: 'Lactophenol', name: () => t('Lactophenol') },
    { key: 'chloral_hydrate', short: 'Chloralhydrat', name: () => t('Chloralhydrat') },
    { key: 'l4', short: 'L4', name: () => t('L4 (nach Clémençon)') },
    { key: 'gsm', short: 'GSM', name: () => t('GSM (nach Clémençon)') },
    { key: 'glamalc', short: 'Glamalc', name: () => t('Glamalc') },
    { key: 'ethanol_glycerol', short: 'Ethanolglycerin', name: () => t('Ethanolglycerin') },
    { key: 'immersion_oil', short: 'Immersionsöl', name: () => t('Immersionsöl') },
    { key: 'baral',           short: 'Baral',          name: () => t('Baral-Lösung (Lugol nach Baral)') },
    { key: 'sulfovanillin', short: 'SV', name: () => t('Sulfovanillin') },
    { key: 'congo_nh3',       short: 'KR NH₃',         name: () => t('Kongorot in Ammoniak') },
    { key: 'congo_sds', short: 'KR SDS', name: () => t('Kongorot in SDS') },
    { key: 'cresyl_blue',     short: 'Kresylblau',     name: () => t('Brillantkresylblau') },
    { key: 'chlorazol_black', short: 'Chlorazolschwarz', name: () => t('Chlorazolschwarz E') },
    { key: 'sudan_4',         short: 'Sudan IV',       name: () => t('Sudan IV') },
    { key: 'phloxine_b',      short: 'Phloxin B',      name: () => t('Phloxin B') },
    { key: 'methyl_orange',   short: 'Methylorange',   name: () => t('Methylorange') },
    { key: 'toluidine_blue',  short: 'Toluidinblau',   name: () => t('Toluidinblau') },
    { key: 'methylene_blue',  short: 'Methylenblau',   name: () => t('Methylenblau') },
    { key: 'giemsa', short: 'Giemsa', name: () => t('Giemsa-Lösung') },
    { key: 'methyl_violet',   short: 'Methylviolett',  name: () => t('Methylviolett') },
    { key: 'patent_blue',     short: 'Patentblau',     name: () => t('Patentblau') },
    { key: 'carbol_fuchsin',  short: 'Carbolfuchsin',  name: () => t('Carbolfuchsin') },
    { key: 'malachite_green', short: 'Malachitgrün',   name: () => t('Malachitgrün') },
    { key: 'trypan_blue',     short: 'Trypanblau',     name: () => t('Trypanblau') },
    { key: 'erythrosine',     short: 'Erythrosin',     name: () => t('Erythrosin') },
    { key: 'acetocarmine',    short: 'Karminessig',    name: () => t('Karminessigsäure') },
    { key: 'india_ink',       short: 'Tusche',         name: () => t('China-Tusche') },
    { key: 'iron_mordant',    short: 'Eisenbeize',     name: () => t('Eisenbeize') },
];

/**
 * ALPHABETICALLY, BY THE LABEL ONE READS (15.11.2026: „schön
 * wäre, wenn Weitere und die Medien im Dropdown alphabetisch sortiert
 * wären" — he had looked for Erythrosin among the dyes' order and not
 * found it). For the lists one searches in: „Weitere" in the microscope's
 * sidebar, and both groups of the picture's „Medium" dropdown. The order of
 * MEDIA itself stays the one the keys are stored and joined in
 * (mediaLabel), so a picture's label does not change with it.
 */
export const mediaAlphabetical = (a, b) => a.short.localeCompare(b.short, 'de', { sensitivity: 'base', numeric: true });

/**
 * WHAT EACH MEDIUM IS FOR — shown in the panel that opens beside the
 * sidebar when the pointer rests on a medium (04.11.2026: „was
 * man damit färben kann oder wofür das Medium gedacht ist … für die
 * Großgruppen Russula, Cortinarien und restliche … Agaricus“). Drawn from
 * his table (~/Documents/Myko/Chemikalien.ods: Anwendung, Basidiomyceten
 * all / Russula / Cortinarius, Ascomyceten, Gefahrenhinweise), sentences
 * made plain, and completed where the use is textbook — Melzer hiding a
 * hemiamyloid reaction, India ink as a negative stain, carbol fuchsin and
 * hydrochloric acid at Russula's primordial hyphae. Only the microscope's
 * side: a reaction on the fruit body is named as such and no more.
 * Agaricus has no line: its Schäffer reaction (aniline and nitric acid) is
 * one on the fruit body, and the window takes micrographs. PURE MACRO-
 * CHEMISTRY IS NOT IN THE LIST AT ALL (04.11.2026: „reine
 * Makrochemie nehmen wir wieder raus") — silver nitrate and iron(II)
 * sulphate went; his table ticks them for „Makro" only. And so did
 * iron(III) chloride (Pöder and Moser's orellanine test is a spot on filter
 * paper, read by eye) and KOH 20 % (chrysocystidia show in 3 % or ammonia,
 * the iodine pretreatment takes 3–5 %; the strong lye is for spot tests on
 * the fruit body) — asked and agreed the same day. KOH 40 % stays: soluble
 * walls in polypores are read under the microscope.
 *
 * Sections in the panel's order: use (for all), russula, cortinarius,
 * agaricus, asco, caution — each a list of sentences, a missing one
 * simply not shown.
 */
export const MEDIA_INFO = {
    h2o: () => ({
        use: [t('Das neutrale Medium: Farben und Pigmente bleiben, wie sie sind.'), t('Sporen misst man meist in Wasser oder in KOH 3 %.')],
    }),
    koh_3: () => ({
        use: [t('Weicht Exsikkate auf und macht Gewebe durchsichtig. Frisches Gewebe lässt sich danach gut quetschen.'), t('Kann Feines zerstören, etwa die Kristallschöpfe an Zystiden.')],
        cortinarius: [t('Das Ornament der braunen Sporen tritt deutlicher hervor.')],
        asco: [t('Vorbehandlung für die Iodprobe: Wird die Ascusspitze erst nach KOH blau, ist sie hemiamyloid.')],
        caution: [t('Ätzend. Greift Glas an – auch das Objektiv.')],
    }),
    melzer: () => ({
        use: [t('Starkes Iodreagenz mit Chloralhydrat. Amyloid heißt graublau bis schwarzviolett, dextrinoid braun bis weinrot.')],
        russula: [t('Färbt das Sporenornament – Warzen, Grate, Netz –, so wie es die Schlüssel beschreiben. Ebenso bei Lactarius.')],
        asco: [t('Färbt Ascusspitze und Paraphysenspitzen.'), t('Eine hemiamyloide Reaktion kann darin verborgen bleiben – dafür Lugol nehmen.')],
        caution: [t('Giftig und krebserregend (Chloralhydrat).')],
    }),
    lugol: () => ({
        use: [t('Schwaches Iodreagenz. Amyloid heißt graublau bis schwarzviolett, dextrinoid braun bis weinrot.')],
        russula: [t('Färbt das Sporenornament.')],
        asco: [t('Färbt Ascusspitze und Paraphysenspitzen.'), t('Ohne KOH blau: euamyloid. Erst nach KOH blau: hemiamyloid.')],
    }),
    baral: () => ({
        use: [t('Iodreagenz mittlerer Stärke. Amyloid heißt graublau bis schwarzviolett, dextrinoid braun bis weinrot.')],
        russula: [t('Färbt das Sporenornament.')],
        asco: [t('Färbt Ascusspitze und Paraphysenspitzen.'), t('Ohne KOH blau: euamyloid. Erst nach KOH blau: hemiamyloid.')],
    }),
    cotton_blue: () => ({
        use: [t('Färbt das Zellplasma, bei Erwärmen auch Wände und Ornamente.'), t('Cyanophile Sporen färben sich blau, etwa bei Boletus, Cystolepiota, Hygrophoropsis, Lepiota, Lepista und Rhodocybe.')],
        russula: [t('Färbt die Gloeozystiden.')],
        asco: [t('Färbt das Sporenornament.')],
    }),
    congo_h2o: () => ({
        use: [t('Färbt die Zellwände rot: Hyphen, Schnallen, Basidien und Zystiden heben sich ab.'), t('Hält etwa drei Monate. Mit destilliertem Wasser lässt es sich auffrischen.')],
        caution: [t('Giftig und krebserregend.')],
    }),
    congo_nh3: () => ({
        use: [t('Färbt die Zellwände rot: Hyphen, Schnallen, Basidien und Zystiden heben sich ab.'), t('Mit Ammoniak lässt es sich auffrischen.')],
        caution: [t('Giftig und krebserregend.')],
    }),
    nh3: () => ({
        use: [t('Weicht Exsikkate auf, 2- bis 3-prozentig verdünnt. Kann Strukturen zerstören.'), t('Färbt Chrysozystiden, etwa bei Hypholoma, Pholiota und Stropharia.')],
        cortinarius: [t('Färbt das Sporenornament.')],
        caution: [t('Ätzend. Die Dämpfe reizen die Atemwege.')],
    }),
    hcl_3: () => ({
        russula: [t('Nach Carbolfuchsin: Die Säure entfärbt das Präparat, nur die Inkrustationen der Primordialhyphen bleiben rot.')],
        caution: [t('Ätzend.')],
    }),
    lactoglycerol: () => ({
        use: [t('Untersuchungsmedium, das kaum eintrocknet – gut für Präparate, die liegen bleiben.'), t('Nicht mit Kongorot mischen: Es fällt aus.')],
    }),
    cresyl_blue: () => ({
        use: [t('Metachromatische Sporen färben sich rötlich violett, etwa bei Macrolepiota, Leucocoprinus und Leucoagaricus.'), t('Färbt das Zellplasma.')],
        asco: [t('In Lactoglycerin: Vakuolen der Paraphysen (Mollisia) und die Sporenkörperchen von Orbilia.')],
        caution: [t('Ätzend.')],
    }),
    chlorazol_black: () => ({
        use: [t('Färbt die Zellwände (Chitin) schwarz. So findet man Pilze auch in fremdem Gewebe, etwa in Pflanzen.')],
        caution: [t('Krebserregend.')],
    }),
    sudan_4: () => ({
        use: [t('Färbt nur Fette: Öltropfen in Sporen und Hyphen werden rot.')],
        caution: [t('Ätzend, erbgutverändernd.')],
    }),
    phloxine_b: () => ({
        use: [t('Färbt das Zellplasma rot. Hilft, reife von unreifen Sporen zu unterscheiden.')],
    }),
    methyl_orange: () => ({
        use: [t('Farbstoff, auch als pH-Anzeiger.')],
        caution: [t('Giftig.')],
    }),
    toluidine_blue: () => ({
        use: [t('Färbt gelatinöses Gewebe, etwa bei Lactarius, und Oleiferen (ölhaltige Hyphen).'), t('Metachromatische Sporen färben sich rötlich violett, etwa bei Macrolepiota, Leucocoprinus und Leucoagaricus.')],
    }),
    methylene_blue: () => ({
        use: [t('Färbt die Zellkerne. So findet man Pilze auch in fremdem Gewebe.')],
        caution: [t('Giftig.')],
    }),
    methyl_violet: () => ({
        use: [t('Farbstoff für Wände und Plasma.')],
        caution: [t('Giftig und krebserregend.')],
    }),
    patent_blue: () => ({
        use: [t('Färbt Chrysozystiden, etwa bei Hypholoma, Pholiota und Stropharia.')],
    }),
    carbol_fuchsin: () => ({
        use: [t('Färbt Inkrustationen.')],
        russula: [t('Färbt die Primordialhyphen. Danach mit verdünnter Salzsäure entfärben: Nur ihre Inkrustationen bleiben rot.')],
        caution: [t('Ätzend und krebserregend.')],
    }),
    malachite_green: () => ({
        use: [t('Farbstoff.')],
        caution: [t('Giftig.')],
    }),
    trypan_blue: () => ({
        use: [t('Färbt die Zellwände blau.')],
        caution: [t('Krebserregend.')],
    }),
    erythrosine: () => ({
        use: [t('Färbt das Zellplasma rot.')],
    }),
    acetocarmine: () => ({
        use: [t('Färbt die Zellkerne.'), t('Mit Eisenbeize: siderophile Körnchen in den Basidien, etwa bei Lyophyllum, Tephrocybe, Calocybe und Nyctalis.')],
        caution: [t('Ätzend.')],
    }),
    iron_mordant: () => ({
        use: [t('Mit Karminessigsäure: siderophile Körnchen in den Basidien, etwa bei Lyophyllum, Tephrocybe, Calocybe und Nyctalis. Aufwendig und für die Bestimmung nur von geringem Gewicht.')],
    }),
    india_ink: () => ({
        use: [t('Macht Schleimhüllen sichtbar: Der Hintergrund wird dunkel, die Hülle um Sporen oder Hyphen bleibt hell.')],
    }),
    sulfovanillin: () => ({
        use: [t('Färbt Lactiferen (Milchsafthyphen), etwa bei Lactarius, Lentinellus und Fistulina, und Pseudozystiden.'), t('Nur frisch anmischen: Es hält wenige Stunden.')],
        russula: [t('Färbt die Zystiden: Makrozystiden werden (blau)grau, das Eiweiß im Plasma kräftig rosa.')],
        caution: [t('Giftig und stark ätzend (Schwefelsäure).')],
    }),
    chloral_hydrate: () => ({
        use: [t('Untersuchungsmedium und Aufheller, besonders beim Erwärmen.')],
        caution: [t('Giftig und krebserregend. Falsch dosiert lebensgefährlich.')],
    }),
    lactophenol: () => ({
        use: [t('Untersuchungsmedium.')],
        caution: [t('Giftig, ätzend und erbgutverändernd.')],
    }),
    l4: () => ({
        use: [t('Untersuchungsmedium nach Clémençon. Weicht Exsikkate auf. Enthält KOH.')],
        caution: [t('Giftig und ätzend. Greift Glas an – auch das Objektiv.')],
    }),
    gsm: () => ({
        use: [t('Untersuchungsmedium nach Clémençon aus Glycerin, Natronlauge und Methylcellosolve. Weicht Exsikkate auf, ohne Strukturen zu zerstören.')],
        caution: [t('Giftig und stark ätzend.')],
    }),
    glamalc: () => ({
        use: [t('Untersuchungsmedium. Weicht Exsikkate auf, ohne Strukturen zu zerstören.')],
        caution: [t('Ätzend. Die Dämpfe reizen die Atemwege.')],
    }),
    ethanol_glycerol: () => ({
        use: [t('Untersuchungsmedium. Weicht Exsikkate auf.')],
    }),
    immersion_oil: () => ({
        use: [t('Für das Ölobjektiv, meist 100×: ein Tropfen zwischen Deckglas und Objektiv.'), t('Auch als Medium für wasserabweisende Sporen, etwa bei Bovista und Lycoperdon.')],
    }),
    koh_40: () => ({
        use: [t('Zeigt lösliche Wände bei Porlingen und Rindenpilzen (Aphyllophorales).'), t('Färbt die Spinulae einiger Hymenochaetaceae, etwa bei Phellinus und Onnia.')],
        caution: [t('Ätzend. Greift Glas an – auch das Objektiv.')],
    }),
    naoh_40: () => ({
        use: [t('Weicht Exsikkate auf, kann aber Strukturen zerstören.')],
        caution: [t('Ätzend.')],
    }),
    naocl: () => ({
        use: [t('Aufheller: bleicht dunkles Gewebe.')],
        caution: [t('Stark ätzend.')],
    }),
    congo_sds: () => ({
        use: [t('Färbt die Zellwände rot: Hyphen, Schnallen, Basidien und Zystiden heben sich ab.'), t('Fällt in KOH aus – nicht mischen.')],
        caution: [t('Giftig und krebserregend.')],
    }),
    giemsa: () => ({
        use: [t('Färbt die Zellkerne.')],
        caution: [t('Giftig.')],
    }),
};

const BY_KEY = new Map(MEDIA.map((m) => [m.key, m]));

/** A stored key as its entry — null for one this list no longer knows. */
export function medium(key) {
    return BY_KEY.get(key) ?? null;
}

/** The short labels of stored keys, in the list's order: „H₂O + KOH 3 %".
 *  An unknown key stands as itself rather than vanishing from a plate.
 *  WRITTEN OUT where `full` asks (08.11.2026, on a plate:
 *  „Vanillin wird ja als SV abgekürzt … die Option Medium ausschreiben"):
 *  „Wasser + Kalilauge 3 %" — a short form like SV is known at the
 *  microscope, not always to whoever reads the plate. */
export function mediaLabel(keys, full = false) {
    if (!Array.isArray(keys) || keys.length === 0) return '';
    const known = MEDIA.filter((m) => keys.includes(m.key)).map((m) => (full ? m.name() : m.short));
    const unknown = keys.filter((k) => !BY_KEY.has(k));
    return [...known, ...unknown].join(' + ');
}
