<?php
/**
 * The microscope's words, shown international — the server's half of
 * assets/js/vocabulary.js, which says what and why (23.11.2026). A
 * DELIBERATE TWIN: the find report is written here and cannot call the
 * browser. tests/vocabulary.php holds the two tables line for line; a
 * word changes in both files. DISPLAY ONLY — nothing stored, no file name.
 */

declare(strict_types=1);

// The German word as it is stored → [international, short form].
const VOCABULARY = [
    // Picture names (photos.php, PHOTO_TITLES_MICRO) and measuring layers (photowindow.js, MEASURE_COLUMNS).
    'Übersichtsschnitt' => ['Overview section'],
    'Sporen' => ['Spores'],
    'Konidien' => ['Conidia'],
    'Basidien' => ['Basidia'],
    'Cheilozystiden' => ['Cheilocystidia', 'Cheilo'],
    'Pleurozystiden' => ['Pleurocystidia', 'Pleuro'],
    'Kaulozystiden' => ['Caulocystidia', 'Caulo'],
    'Pileozystiden' => ['Pileocystidia', 'Pileo'],
    'HDS (Hutdeckschicht)' => ['Pileipellis'],
    'Velum Hut' => ['Velum pileus'],
    'Velum Stiel' => ['Velum stipe'],
    'Paraphysen' => ['Paraphyses'],
    'Randhaare' => ['Marginal hairs'],
    'Schnallen' => ['Clamps'],
    'Schnallen Hymenium' => ['Clamps hymenium'],
    'Schnallen Hut' => ['Clamps pileus'],
    'Schnallen Stiel' => ['Clamps stipe'],
    'Hyphen hymenial' => ['Hyphae hymenial'],
    'Hyphen Kontext' => ['Hyphae context'],
    'Hyphen Stiel' => ['Hyphae stipe'],
    'Hyphen Hutdeckschicht' => ['Hyphae pileipellis'],
    'Myzel' => ['Mycelium'],
    'Lamellen' => ['Lamellae'],
    'Poren' => ['Pores'],
    // Retired layers that older measures still carry (photowindow.js, MEASURE_COLUMNS says why they went).
    'Generative Hyphen' => ['Generative hyphae'],
    'Skeletthyphen' => ['Skeletal hyphae'],
    'Bindehyphen' => ['Binding hyphae'],
    'Hyphen Trama' => ['Hyphae trama'],
    // Parts (photowindow.js, MEASURE_PARTS).
    'Keimporus' => ['Germ pore'],
    'Ornamenthöhe' => ['Ornament height'],
    'Öltropfen' => ['Guttule'],
    'Sterigmenlänge' => ['Sterigma length'],
    'Kopfbreite' => ['Head width'],
    'Halsbreite' => ['Neck width'],
    'Ektalexcipulum' => ['Ectal excipulum'],
    'Medullarexcipulum' => ['Medullary excipulum'],
    // Marks (photowindow.js, CYSTIDIA_KINDS and HYPHA_KINDS).
    'Chrysozystide' => ['Chrysocystidium'],
    'Gloeozystide' => ['Gloeocystidium'],
    'Lamprozystide' => ['Lamprocystidium'],
    'Generative Hyphe' => ['Generative hypha'],
    'Skeletthyphe' => ['Skeletal hypha'],
    'Bindehyphe' => ['Binding hypha'],
];

/** A stored word as it is shown: international where the table knows it, else as it was typed. */
function vocab(?string $word): string
{
    static $forms = null;
    if ($forms === null) {
        $forms = [];
        foreach (VOCABULARY as $german => $f) {
            $forms[mb_strtolower($german)] = $f;
        }
    }
    $word = trim((string) $word);
    return $forms[mb_strtolower($word)][0] ?? $word;
}
