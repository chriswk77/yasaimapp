<?php
/**
 * The systematic ranks above the genus — kingdom, phylum, class, order,
 * family (26.08.2026).
 *
 * WHY IT HANGS ON THE GENUS AND NOT ON THE NAME. A find carries a species
 * name, and its genus is the first word of it — that much is already stored
 * beside every species (map_point_species.genus, written by
 * species_genus()). Everything above the genus is the same for every
 * species in it, so ONE lookup per genus answers it for all of them: 195
 * genera instead of 618 species rows in the local stock, and the ratio only
 * improves as the collection grows.
 *
 * WHY GBIF AND NOT THE GERMAN LISTS. Only the GBIF backbone carries the
 * hierarchy, and only since 26.08.2026 does it carry it for every kingdom —
 * before that the import filtered on Fungi, and Quercus, Fagus, Turdus and
 * Apis were simply absent (measured). The German Red Lists have no ranks at
 * all above the genus: map_tax_lichens, map_tax_plants and map_tax_animals
 * hold nothing but a group name.
 *
 * ACCEPTED FIRST, AND THAT IS NOT COSMETIC. A genus name can stand several
 * times in the backbone — Bufo has four rows, and one of them is DOUBTFUL
 * with every rank key empty. Whoever takes the first row the database
 * happens to return gets a toad without a family. The order below asks for
 * the accepted one first, then for a non-synonym, then for whichever
 * actually has a family.
 */

declare(strict_types=1);

/**
 * The ranks, in the order one reads them — key as it travels, German as it
 * is shown.
 *
 * The keys are the column names of the resolution below and at the same
 * time the field names in the stored filter of an auto list. One word from
 * end to end, no translation table in between.
 */
const TAXONOMY_RANKS = [
    'kingdom' => 'Reich',
    'phylum'  => 'Stamm',
    'class'   => 'Klasse',
    'order'   => 'Ordnung',
    'family'  => 'Familie',
    /* THE GENUS CAME LAST, AND ONLY AFTER AN OBJECTION
       (26.08.2026). It was left out on the grounds that the title of every
       find already carries it and the text rows above therefore answer
       the question — true, and beside the point.

       What the text row cannot do is OFFER. It matches a substring, so
       „Boletus" also finds Pseudoboletus, and it never says which genera
       are in the stock at all. As a rank it is a picked list, it narrows
       the ranks above it, and choosing one fills them all in.

       AND IT REACHES FURTHER THAN THE RANKS ABOVE IT. The genus stands on
       the point itself (map_point_species.genus); everything above is
       looked up in the backbone and can fail. Measured: 225 genera in the
       local stock, 212 resolvable — thirteen finds can be filtered by
       genus that no other rank can reach. */
    'genus'   => 'Gattung',
];

/** Is there a backbone to ask at all? */
function taxonomy_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = (bool) $pdo->query("SHOW TABLES LIKE 'map_tax_gbif'")->fetchColumn();
    }
    return $ready;
}

/**
 * Which kingdom a name source can only ever mean.
 *
 * A GENUS NAME IS NOT UNIQUE ACROSS THE KINGDOMS, and not rarely either:
 * measured on 26.08.2026, 2575 accepted genus names stand in more than one
 * — Abronia, Abrus, Acanthella are animals AND plants, because botany and
 * zoology keep separate registers. Sixteen of the 211 genera in the local
 * stock are of that kind. Resolved by name alone, a plant find could be
 * handed an animal's family, and nothing would show it.
 *
 * The source the name was taken from settles most of it: whoever picked
 * from the DGfM reference list picked a fungus, whoever picked from the
 * lichen list likewise, from the plant list a plant. Only a title typed
 * freely, with no source at all, stays ambiguous — and there the honest
 * answer is the one the backbone prefers.
 */
const TAXONOMY_KINGDOM_OF_SOURCE = [
    'fungi'   => 'Fungi',
    'lichens' => 'Fungi',
    'plants'  => 'Plantae',
];

/** The key under which a resolved genus is filed — name AND source. */
function taxonomy_key(string $genus, string $source = ''): string
{
    return $source . '|' . $genus;
}

/**
 * The ranks for a list of genus names, each with the source it came from.
 *
 * @param array $entries list of ['genus' => string, 'source' => string]
 * @return array<string, array{kingdom:?string, phylum:?string, class:?string,
 *                             order:?string, family:?string}>
 *         keyed by taxonomy_key()
 */
function taxonomy_of_genera(PDO $pdo, array $entries): array
{
    $wanted = [];
    foreach ($entries as $entry) {
        // A bare string stays allowed — that is the case with no source.
        $genus  = trim((string) (is_array($entry) ? ($entry['genus'] ?? '') : $entry));
        $source = is_array($entry) ? trim((string) ($entry['source'] ?? '')) : '';
        if ($genus !== '') {
            $wanted[taxonomy_key($genus, $source)] = [$genus, $source];
        }
    }
    if ($wanted === [] || !taxonomy_ready($pdo)) {
        return [];
    }

    /* GROUPED BY KINGDOM, because the constraint goes into the SQL. Most
       stocks have one or two groups, so this is one query per group at
       worst — and usually exactly one. */
    $byKingdom = [];
    foreach ($wanted as $key => [$genus, $source]) {
        $byKingdom[TAXONOMY_KINGDOM_OF_SOURCE[$source] ?? ''][$key] = $genus;
    }

    $out = [];
    foreach ($byKingdom as $kingdom => $group) {
        if ($kingdom !== '') {
            $out += taxonomy_resolve($pdo, $group, $kingdom);
            continue;
        }

        /* WITHOUT A SOURCE, ASK WHAT THIS APP IS ABOUT FIRST.
           
           A freely typed title brings no register with it, and the
           backbone then answers with whichever row it happens to prefer.
           Measured on 26.08.2026: 67 accepted Chromista genera carry the
           same name as a fungal genus, 307 as an animal one. The maintainer saw
           the result of that as „Chromista" in his kingdom list.
           
           NOT BY EXCLUDING A KINGDOM. Chromista was struck out for half a
           day, and that was wrong — the OOMYCOTA live there, and they are
           phytopathogens one maps: Phytophthora, Peronospora, Plasmopara,
           Albugo. („Wenn das phytopathogene Pilze sind, dann müssen wir
           das lassen.") The DGfM is building a portal of its own for
           exactly these.
           
           SO THE PREFERENCE IS THE PHYLUM, NOT THE KINGDOM. Fungi first,
           then the Oomycota, then plants and animals. Preferring the whole
           of Chromista would have dragged 87 plant and 307 animal names
           with it; the Oomycota overlap with everything else in exactly
           TEN names. That is the difference between a rule and a
           sledgehammer — and it is what makes Bremia the downy mildew
           instead of Bremia the gall midge. */
        $rest = $group;
        foreach ([['kingdom', 'Fungi'], ['phylum', 'Oomycota'],
                  ['kingdom', 'Plantae'], ['kingdom', 'Animalia']] as [$rank, $likely]) {
            if ($rest === []) {
                break;
            }
            $found = taxonomy_resolve($pdo, $rest, $likely, $rank);
            // Only what really came back with ranks counts as answered —
            // taxonomy_resolve seeds every key with the bare genus.
            foreach ($found as $key => $ranks) {
                // Answered means: the constrained rank really came back.
                if (($ranks[$rank] ?? null) !== null) {
                    $out[$key] = $ranks;
                    unset($rest[$key]);
                }
            }
        }
        if ($rest !== []) {
            $out += taxonomy_resolve($pdo, $rest, '');
        }
    }
    return $out;
}

/**
 * One query for one constraint — a kingdom or a phylum.
 *
 * @param array<string, string> $group key => genus name
 * @param string $only  the value the constrained rank must have, '' for none
 * @param string $rank  'kingdom' or 'phylum'
 */
function taxonomy_resolve(PDO $pdo, array $group, string $only, string $rank = 'kingdom'): array
{
    $names = array_values(array_unique($group));

    /* THE KINGDOM HAS NO COLUMN OF ITS OWN in the backbone — phylum_key,
       class_key, order_key and family_key exist, kingdom_key does not. It
       is reached over the PHYLUM's parent, and that is exactly what the
       last join does. Measured against Boletus, Quercus, Turdus and Apis:
       Fungi, Plantae, Animalia, Animalia. */
    /* THE KINGDOM CONSTRAINT AS ITS OWN STRING, not spliced into the
       query with a ternary: the first attempt left the closing bracket
       standing when there was no constraint, and MySQL answered with a
       syntax error over twelve lines. Built separately, the query reads
       the same in both cases. */
    $onlyIn = '';
    if ($only !== '' && $rank === 'kingdom') {
        $onlyIn = " AND EXISTS (
                        SELECT 1 FROM map_tax_gbif p2
                          JOIN map_tax_gbif k2 ON k2.gbif_key = p2.parent_key
                                              AND k2.taxon_rank = 'KINGDOM'
                         WHERE p2.gbif_key = g2.phylum_key
                           AND k2.canonical = " . $pdo->quote($only) . ")";
    } elseif ($only !== '' && $rank === 'phylum') {
        // The phylum sits one join closer — no parent to walk up to.
        $onlyIn = " AND EXISTS (
                        SELECT 1 FROM map_tax_gbif p2
                         WHERE p2.gbif_key = g2.phylum_key
                           AND p2.canonical = " . $pdo->quote($only) . ")";
    }

    $stmt = $pdo->prepare(
        "SELECT s.genus,
                k.canonical AS kingdom, p.canonical AS phylum, c.canonical AS class,
                o.canonical AS `order`, f.canonical AS family
           FROM (SELECT ? AS genus" . str_repeat(' UNION ALL SELECT ?', count($names) - 1) . ") s
           JOIN map_tax_gbif g ON g.gbif_key = (
                SELECT g2.gbif_key FROM map_tax_gbif g2
                 WHERE g2.canonical = s.genus AND g2.taxon_rank = 'genus'" . $onlyIn . "
                 ORDER BY (g2.status = 'ACCEPTED') DESC,
                          g2.is_synonym ASC,
                          (g2.family_key IS NOT NULL) DESC
                 LIMIT 1)
           LEFT JOIN map_tax_gbif f ON f.gbif_key = g.family_key
           LEFT JOIN map_tax_gbif o ON o.gbif_key = g.order_key
           LEFT JOIN map_tax_gbif c ON c.gbif_key = g.class_key
           LEFT JOIN map_tax_gbif p ON p.gbif_key = g.phylum_key
           LEFT JOIN map_tax_gbif k ON k.gbif_key = p.parent_key AND k.taxon_rank = 'KINGDOM'"
    );
    $stmt->execute($names);

    /* EVERY INPUT GETS AN ANSWER, even one the backbone does not know:
       the GENUS is always there — it stands on the point, not in the
       backbone — and it alone is worth filtering by. Only the ranks above
       it can come back empty. */
    $out = [];
    foreach ($group as $key => $genus) {
        $out[$key] = array_fill_keys(array_keys(TAXONOMY_RANKS), null);
        $out[$key]['genus'] = $genus;
    }

    foreach ($stmt->fetchAll() as $row) {
        foreach ($group as $key => $genus) {
            if ($genus !== (string) $row['genus']) {
                continue;
            }
            foreach (array_keys(TAXONOMY_RANKS) as $rank) {
                if ($rank === 'genus') {
                    continue;   // stays what the point says
                }
                $value = trim((string) ($row[$rank] ?? ''));
                $out[$key][$rank] = $value === '' ? null : $value;
            }
        }
    }
    return $out;
}

/**
 * Does the point's genus satisfy the chosen ranks?
 *
 * THE TWIN IS taxonomyMatches IN assets/js/filter.js, and both are held
 * against the same cases (tests/filter.mjs and tests/autolists.php). An
 * EMPTY condition asks nothing; a set one demands an exact match, and a
 * genus we cannot resolve therefore drops out — the same rule the height
 * band follows for a point without an elevation.
 *
 * @param array $conditions rank => name, empty values ignored
 * @param array|null $ranks  what taxonomy_of_genera returned for this genus
 */
function taxonomy_matches(array $conditions, ?array $ranks): bool
{
    $asked = [];
    foreach (array_keys(TAXONOMY_RANKS) as $rank) {
        $value = trim((string) ($conditions[$rank] ?? ''));
        if ($value !== '') {
            $asked[$rank] = $value;
        }
    }
    if ($asked === []) {
        return true;
    }
    if ($ranks === null) {
        return false;
    }
    foreach ($asked as $rank => $value) {
        if (strcasecmp((string) ($ranks[$rank] ?? ''), $value) !== 0) {
            return false;
        }
    }
    return true;
}
