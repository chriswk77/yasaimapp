<?php
/**
 * Marry the German list with GBIF and COL. Command line only.
 *
 *   php admin/tax_link.php [--really]
 *
 * Three lists carry the same fungi under partly different names. So that a
 * find point under "Boletus aereus" is also found when a synonym is later
 * searched for, it must be settled per DGfM entry which entry in the other
 * lists is meant — and which name is valid today.
 *
 * Each list is searched in three ways, in this order; the earlier one is
 * always the stronger:
 *
 *   1. name           The name stands there literally. The clear case.
 *   2. *_synonym      It stands there only as a synonym; the link then goes
 *                     to the corresponding accepted name.
 *   3. dgfm_synonym   It does not stand there at all, but another name of
 *                     the same fungus from the German list does — for GBIF
 *                     after the author step, see there.
 *
 * IN CASE OF CONFLICT, COL WINS. GBIF's backbone has stood still since
 * August 2023; of the names both lists know, 3,977 have changed status
 * since. For a find of today, today's name counts. GBIF stays nonetheless,
 * because it links more synonyms (202,235 against 185,474) and thus more
 * often builds a bridge at all.
 *
 * What is ambiguous is not hidden: 'kandidaten_*' records how many entries
 * matched the name. More than one means identical names between different
 * fungi — then the assignment is a suggestion.
 *
 * Comparison happens over the name without the author. Comparing authors as
 * well sounds more precise but fails on spelling: "(Pers. : Fr.) P. Karst."
 * and "(Pers.) P.Karst." mean the same author.
 *
 * The computation happens in the database, not in PHP — 400,000 names in
 * one array blow up the memory.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tax_authors.php';

$really = in_array('--really', $argv, true) || in_array('--wirklich', $argv, true);
$z = static fn($n): string => number_format((int) $n, 0, ',', '.');

printf("%s\n\n", $really ? 'Verknüpfen' : 'PROBELAUF — wird am Ende zurückgenommen');

// DELETE instead of TRUNCATE: only that way can the dry run be rolled back.
$pdo->beginTransaction();
$pdo->exec('DELETE FROM map_tax_links');

// --- GBIF: names that stand there literally -------------------------------
//
// Sorted accepted before synonym, so that with identical names the valid
// entry wins.

echo "  GBIF: Namen vergleichen …\n";
$pdo->exec(
    "INSERT INTO map_tax_links (taxnr, gbif_key, gbif_species_key, gbif_via, gbif_candidates)
     SELECT taxnr, gbif_key, species_key,
            IF(is_synonym = 1, 'gbif_synonym', 'name'), LEAST(total, 65535)
       FROM (
            SELECT d.taxnr, g.gbif_key, g.species_key, g.is_synonym,
                   ROW_NUMBER() OVER (PARTITION BY d.taxnr ORDER BY g.is_synonym, g.gbif_key) AS space,
                   COUNT(*)     OVER (PARTITION BY d.taxnr)                                    AS total
              FROM map_tax_dgfm d
              JOIN map_tax_gbif g ON g.canonical = d.binomial
             WHERE d.binomial IS NOT NULL AND d.binomial <> ''
       ) t
      WHERE space = 1"
);

/* --- GBIF: the same name, spelled the other work's way ------------------
 *
 * THE TWO WORKS WRITE INFRASPECIFIC NAMES DIFFERENTLY. The DGfM keeps the
 * rank marker in its binomial („Rhodocollybia butyracea f. asema"), GBIF
 * drops it from its canonical („Rhodocollybia butyracea asema"). Measured
 * on 25.08.2026: 2452 DGfM names carry a marker — var. 2035 times, f.
 * 373, subsp. 44 — and of 397511 GBIF rows not one does. The literal
 * comparison above therefore CANNOT match a single one of them.
 *
 * What that cost: of the 2452 infraspecific names 709 stood without a
 * GBIF link, 29 percent, against 6 percent among the ordinary binomials —
 * five times as many, and the difference is exactly this dot. The gap is
 * not cosmetic: the link table is what lets synonyms find each other, so
 * a find recorded under a forma stayed alone.
 *
 * Its own pass rather than an OR in the join above: an OR over two
 * equalities on canonical costs the index on a join of 39 000 against
 * 397 000 rows. This way both passes look up an indexed value, and the
 * literal spelling keeps its precedence — this one only takes what pass
 * one left over. The same shape the German-synonymy pass below already
 * has. */
echo "  GBIF: Unterarten in der anderen Schreibweise …\n";
$pdo->exec(
    "INSERT INTO map_tax_links (taxnr, gbif_key, gbif_species_key, gbif_via, gbif_candidates)
     SELECT taxnr, gbif_key, species_key,
            IF(is_synonym = 1, 'gbif_synonym', 'name'), LEAST(total, 65535)
       FROM (
            SELECT d.taxnr, g.gbif_key, g.species_key, g.is_synonym,
                   ROW_NUMBER() OVER (PARTITION BY d.taxnr ORDER BY g.is_synonym, g.gbif_key) AS space,
                   COUNT(*)     OVER (PARTITION BY d.taxnr)                                    AS total
              FROM map_tax_dgfm d
              JOIN map_tax_gbif g ON g.canonical = REPLACE(REPLACE(REPLACE(REPLACE(
                       d.binomial, ' var. ', ' '), ' f. ', ' '), ' subsp. ', ' '), ' ssp. ', ' ')
             WHERE d.binomial IS NOT NULL AND d.binomial <> ''
               AND d.binomial REGEXP ' (var|f|subsp|ssp)\\\\. '
               AND NOT EXISTS (SELECT 1 FROM map_tax_links x WHERE x.taxnr = d.taxnr)
       ) t
      WHERE space = 1"
);

// --- COL: the same mechanics -----------------------------------------------
//
// Without helper tables: CREATE TEMPORARY TABLE and ALTER commit the
// transaction immediately in MySQL, and then the dry run can no longer be
// rolled back — it would be a lie. Hence the selection sits directly in the
// insert.
//
// An entry can already have a row from the GBIF pass; ON DUPLICATE KEY
// UPDATE then supplements it instead of creating a second one.

echo "  COL: Namen vergleichen …\n";
$pdo->exec(
    "INSERT INTO map_tax_links (taxnr, col_id, col_species_id, col_via, col_candidates)
     SELECT taxnr, col_id, accepted_id, col_via, col_candidates FROM (
            SELECT d.taxnr, c.col_id,
                   COALESCE(c.accepted_id, c.col_id) AS accepted_id,
                   IF(c.accepted_id IS NOT NULL, 'col_synonym', 'name') AS col_via,
                   LEAST(COUNT(*) OVER (PARTITION BY d.taxnr), 65535)     AS col_candidates,
                   ROW_NUMBER() OVER (PARTITION BY d.taxnr
                                      ORDER BY c.accepted_id IS NOT NULL, c.col_id) AS space
              FROM map_tax_dgfm d
              JOIN map_tax_col c ON c.canonical = d.binomial
             WHERE d.binomial IS NOT NULL AND d.binomial <> ''
     ) t
      WHERE space = 1
         ON DUPLICATE KEY UPDATE
            col_id         = VALUES(col_id),
            col_species_id     = VALUES(col_species_id),
            col_via        = VALUES(col_via),
            col_candidates = VALUES(col_candidates)"
);

/* STILL THE LOWEST KEY HERE, and still before the author step, knowingly:
 * the GBIF pass behind that step says why both are a chance, but nothing
 * in the map reads a COL link — col_* and valid_name serve this script's
 * own report. Whoever puts COL to use takes the GBIF pass's rule along. */
echo "  COL: Rest über die deutsche Synonymie …\n";
$pdo->exec(
    "INSERT INTO map_tax_links (taxnr, col_id, col_species_id, col_via, col_candidates)
     SELECT taxnr, col_id, col_species_id, 'dgfm_synonym', 1 FROM (
            SELECT d.taxnr, v.col_id, v.col_species_id,
                   ROW_NUMBER() OVER (PARTITION BY d.taxnr ORDER BY v.col_id) AS space
              FROM map_tax_dgfm d
              JOIN map_tax_dgfm         s ON s.sipnr = d.sipnr AND s.taxnr <> d.taxnr
              JOIN map_tax_links v ON v.taxnr = s.taxnr AND v.col_id IS NOT NULL
             WHERE d.binomial IS NOT NULL AND d.binomial <> ''
               AND NOT EXISTS (SELECT 1 FROM map_tax_links x
                                WHERE x.taxnr = d.taxnr AND x.col_id IS NOT NULL)
     ) t
      WHERE space = 1
         ON DUPLICATE KEY UPDATE
            col_id         = VALUES(col_id),
            col_species_id     = VALUES(col_species_id),
            col_via        = 'dgfm_synonym',
            col_candidates = 1"
);

// --- Decide ambiguous assignments via the author --------------------------
//
// A species name comes with author and year; without them a name is
// ambiguous. Agaricus campestris L. and Agaricus campestris Schaeff. are
// different fungi, and exactly such cases went through above with
// kandidaten > 1.
//
// The year barely helps here: mycological names do not cite it under the
// nomenclature rules. Measured, 6.6% of the fungi at GBIF carry a year, at
// COL under one percent — only the DGfM list has it almost throughout.
// That leaves the author.
//
// The comparison is not literal. "(Pers. : Fr.) P. Karst." and "(Pers.)
// P.Karst." mean the same author; the sanctioning citation ": Fr." is
// carried only by the DGfM. Hence boiled down to letters and then checked
// whether one is contained in the other.

echo "  Mehrdeutige über den Autor entscheiden …\n";

$rows = $pdo->query(
    "SELECT v.taxnr, d.binomial, d.author, v.gbif_key, v.col_id,
            v.gbif_candidates, v.col_candidates
       FROM map_tax_links v
       JOIN map_tax_dgfm d ON d.taxnr = v.taxnr
      WHERE v.gbif_candidates > 1 OR v.col_candidates > 1"
)->fetchAll(PDO::FETCH_ASSOC);

$fetchGbif = $pdo->prepare(
    'SELECT gbif_key, species_key, author, is_synonym FROM map_tax_gbif WHERE canonical = ?'
);
$fetchCol = $pdo->prepare(
    'SELECT col_id, COALESCE(accepted_id, col_id) AS epithet, author, accepted_id
       FROM map_tax_col WHERE canonical = ?'
);
$setGbif = $pdo->prepare(
    'UPDATE map_tax_links SET gbif_key = ?, gbif_species_key = ? WHERE taxnr = ?'
);
$setCol = $pdo->prepare(
    'UPDATE map_tax_links SET col_id = ?, col_species_id = ? WHERE taxnr = ?'
);
$setMatch = $pdo->prepare('UPDATE map_tax_links SET author_matches = ? WHERE taxnr = ?');

$confirmed = $corrected = $unclear = 0;

foreach ($rows as $e) {
    $verdict = null;

    // GBIF
    if ((int) $e['gbif_candidates'] > 1) {
        $fetchGbif->execute([$e['binomial']]);
        foreach ($fetchGbif->fetchAll(PDO::FETCH_ASSOC) as $k) {
            if (authorMatches($e['author'], $k['author']) === true) {
                if ((int) $k['gbif_key'] !== (int) $e['gbif_key']) {
                    $setGbif->execute([$k['gbif_key'], $k['species_key'], $e['taxnr']]);
                    $corrected++;
                }
                $verdict = 1;
                break;
            }
        }
    }

    // COL
    if ((int) $e['col_candidates'] > 1) {
        $fetchCol->execute([$e['binomial']]);
        foreach ($fetchCol->fetchAll(PDO::FETCH_ASSOC) as $k) {
            if (authorMatches($e['author'], $k['author']) === true) {
                if ($k['col_id'] !== $e['col_id']) {
                    $setCol->execute([$k['col_id'], $k['epithet'], $e['taxnr']]);
                    $corrected++;
                }
                $verdict = 1;
                break;
            }
        }
    }

    if ($verdict === 1) { $confirmed++; } else { $unclear++; }
    $setMatch->execute([$verdict, $e['taxnr']]);
}

printf("    %s mehrdeutig, davon %s über den Autor bestätigt, %s davon umgehängt\n",
    $z(count($rows)), $z($confirmed), $z($corrected));
printf("    %s remain open — no author matched\n", $z($unclear));

/* WHICH OTHER NAME LENDS ITS LINK (26.09.2026). This pass once took the
 * link of whichever name of the group had the LOWEST GBIF key — a chance
 * that decided nothing as long as all names of a group lead to one GBIF
 * species (144 of the 175 valid DGfM species linked this way), and
 * everything where they do not: the DGfM's Leucocoprinus leucothites went
 * to its synonym Leucoagaricus subcretaceus, a species of its own at GBIF,
 * and since 0106 the map offered the Rosablättriger Egerlingsschirmling
 * under that name. Thelephora fuliginea stood as Botryobasidium
 * ramosissimum, another family. Measured on 26.09.2026: 13 valid species
 * came out under the name of another species, 5 under one of the species
 * their synonyms disagree on.
 *
 * NOW: a name with the same final epithet first — a homotypic one,
 * Leucoagaricus leucothites for Leucocoprinus leucothites, the same type
 * in another genus (hyphens aside: „luteo-ornatus" and „luteoornatus" are
 * one epithet). Where there is none, the species all the other names agree
 * on. Where they disagree, NO LINK: the DGfM's own name stands, which is
 * right, while any pick among two species is a guess that shows. The same
 * rule for synonym rows — the valid row is one of their group.
 *
 * AND ONLY AFTER THE AUTHOR HAS SPOKEN. The pass stood before the author
 * step below, which corrects the link of an ambiguous name — so it lent
 * keys their own rows gave up a moment later: Fomitopsis quercina kept
 * GBIF's separate „Trametes quercina" (8401106) while its synonym row of
 * that name had long moved to 2543245, Daedalea quercina. Here the COL
 * passes have already made rows for many of these names, so the GBIF
 * columns are filled into them (ON DUPLICATE KEY), as the COL passes do.
 *
 * migrations/0140_gbif_links_through_synonyms.sql mends the stock with
 * this rule and is its twin. */
echo "  GBIF: Rest über die deutsche Synonymie …\n";
$pdo->exec(
    "INSERT INTO map_tax_links (taxnr, gbif_key, gbif_species_key, gbif_via, gbif_candidates)
     SELECT taxnr, gbif_key, species_key, 'dgfm_synonym', 1 FROM (
     SELECT c.taxnr, g.gbif_key, g.species_key
       FROM (
            SELECT d.taxnr,
                   COUNT(DISTINCT CASE WHEN REPLACE(SUBSTRING_INDEX(s.binomial, ' ', -1), '-', '')
                                          = REPLACE(SUBSTRING_INDEX(d.binomial, ' ', -1), '-', '')
                                       THEN v.gbif_species_key END) AS homotypic_species,
                   MIN(CASE WHEN REPLACE(SUBSTRING_INDEX(s.binomial, ' ', -1), '-', '')
                               = REPLACE(SUBSTRING_INDEX(d.binomial, ' ', -1), '-', '')
                            THEN v.gbif_key END) AS homotypic_key,
                   COUNT(DISTINCT v.gbif_species_key) AS species,
                   MIN(v.gbif_key) AS any_key
              FROM map_tax_dgfm d
              JOIN map_tax_dgfm         s ON s.sipnr = d.sipnr AND s.taxnr <> d.taxnr
              JOIN map_tax_links v ON v.taxnr = s.taxnr AND v.gbif_key IS NOT NULL
                   AND v.gbif_via IN ('name', 'gbif_synonym')
             WHERE d.binomial IS NOT NULL AND d.binomial <> ''
               AND NOT EXISTS (SELECT 1 FROM map_tax_links x WHERE x.taxnr = d.taxnr AND x.gbif_key IS NOT NULL)
             GROUP BY d.taxnr
       ) c
       JOIN map_tax_gbif g ON g.gbif_key = CASE
                WHEN c.homotypic_species = 1 THEN c.homotypic_key
                WHEN c.homotypic_species = 0 AND c.species = 1 THEN c.any_key
            END
     ) z
         ON DUPLICATE KEY UPDATE
            gbif_key         = VALUES(gbif_key),
            gbif_species_key = VALUES(gbif_species_key),
            gbif_via         = 'dgfm_synonym',
            gbif_candidates  = 1"
);

// --- Settle the valid name — COL takes precedence --------------------------

echo "  Gültigen Namen bestimmen (COL vor GBIF) …\n";
$pdo->exec(
    "UPDATE map_tax_links v
        LEFT JOIN map_tax_col  c ON c.col_id   = v.col_species_id
        LEFT JOIN map_tax_gbif g ON g.gbif_key = v.gbif_species_key
        JOIN map_tax_dgfm      d ON d.taxnr    = v.taxnr
        LEFT JOIN map_tax_dgfm a ON a.taxnr    = d.sipnr
        SET v.valid_name = COALESCE(c.canonical, g.canonical, a.binomial, d.binomial),
            v.valid_source = CASE
                WHEN c.canonical IS NOT NULL THEN 'col'
                WHEN g.canonical IS NOT NULL THEN 'gbif'
                ELSE 'dgfm' END"
);

// --- GBIF's name, where it is an honest replacement (migration 0106) ----------
//
// The twin of the statement in migrations/0106_gbif_namen.sql — the column
// has to be filled again after every re-link, and the rule must not drift
// between the two. The migration's comment carries the four conditions.

if (column_exists($pdo, 'map_tax_links', 'gbif_name')) {
    echo "  GBIF-Namen bestimmen (eindeutig, Art-Rang, Autor widerspricht nicht) …\n";
    $pdo->exec('UPDATE map_tax_links SET gbif_name = NULL');
    // The grouped derived table is materialised once (a GROUP BY cannot be
    // merged into the outer query), so this is one pass, not one per row.
    $pdo->exec(
        "UPDATE map_tax_links l
           JOIN map_tax_dgfm d ON d.taxnr = l.taxnr AND d.taxnr = d.sipnr
           JOIN map_tax_gbif g ON g.gbif_key = l.gbif_species_key AND g.is_synonym = 0
                AND g.taxon_rank IN ('SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM')
           JOIN (SELECT COALESCE(g2.canonical, d2.binomial) AS shown, COUNT(*) AS n
                   FROM map_tax_dgfm d2
                   LEFT JOIN map_tax_links l2 ON l2.taxnr = d2.taxnr AND (l2.author_matches IS NULL OR l2.author_matches = 1)
                   LEFT JOIN map_tax_gbif g2 ON g2.gbif_key = l2.gbif_species_key AND g2.is_synonym = 0
                        AND g2.taxon_rank IN ('SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM')
                  WHERE d2.taxnr = d2.sipnr
                  GROUP BY shown) s ON s.shown = g.canonical AND s.n = 1
            SET l.gbif_name = g.canonical
          WHERE (l.author_matches IS NULL OR l.author_matches = 1)
            AND g.canonical <> d.binomial"
    );
    /* AND NOT THE NAME ANOTHER SPECIES FALLS BACK ON (26.09.2026). The
       count above sees every species under the GBIF name it WOULD get —
       not under the DGfM name it keeps when it gets none. So Helvella
       fallax came as „Helvella atra", GBIF's name for it, while the DGfM's
       own Helvella atra, the Schwarze Lorchel, kept that very name for
       want of one of its own: two species behind one suggestion, and the
       search showed only one of them. The one such case of 26.09.2026;
       migrations/0141_gbif_name_not_another_species.sql is the twin. */
    $pdo->exec(
        "UPDATE map_tax_links l
           JOIN (SELECT DISTINCT l3.taxnr
                   FROM map_tax_links l3
                   JOIN map_tax_dgfm d3 ON d3.taxnr = l3.taxnr
                   JOIN map_tax_dgfm o ON o.binomial = l3.gbif_name AND o.taxnr = o.sipnr AND o.taxnr <> d3.taxnr
                   LEFT JOIN map_tax_links lo ON lo.taxnr = o.taxnr
                  WHERE l3.gbif_name IS NOT NULL AND lo.gbif_name IS NULL) b ON b.taxnr = l.taxnr
            SET l.gbif_name = NULL"
    );
    printf("    %s Namen von GBIF\n", $z((int) $pdo->query('SELECT COUNT(*) FROM map_tax_links WHERE gbif_name IS NOT NULL')->fetchColumn()));
}

// --- Report ----------------------------------------------------------------

$total = (int) $pdo->query(
    "SELECT COUNT(*) FROM map_tax_dgfm WHERE binomial IS NOT NULL AND binomial <> ''"
)->fetchColumn();

$share = static fn(int $n): string => sprintf('%5.1f %%', $n / max($total, 1) * 100);

echo "\n  Verknüpft mit …\n";
foreach (['gbif_key' => 'GBIF', 'col_id' => 'COL'] as $column => $label) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM map_tax_links WHERE $column IS NOT NULL")->fetchColumn();
    printf("    %-22s %9s  %s\n", $label, $z($n), $share($n));
}
$both = (int) $pdo->query('SELECT COUNT(*) FROM map_tax_links WHERE gbif_key IS NOT NULL AND col_id IS NOT NULL')->fetchColumn();
$onlyCol = (int) $pdo->query('SELECT COUNT(*) FROM map_tax_links WHERE gbif_key IS NULL AND col_id IS NOT NULL')->fetchColumn();
printf("    %-22s %9s  %s\n", 'beide', $z($both), $share($both));
printf("    %-22s %9s  %s\n", 'nur COL', $z($onlyCol), $share($onlyCol));

$linked = (int) $pdo->query('SELECT COUNT(*) FROM map_tax_links')->fetchColumn();
printf("    %-22s %9s  %s\n", 'gar nicht', $z($total - $linked), $share($total - $linked));

echo "\n  Gültiger Name stammt aus …\n";
foreach ($pdo->query("SELECT valid_source q, COUNT(*) n FROM map_tax_links
                       GROUP BY valid_source ORDER BY n DESC") as $r) {
    printf("    %-22s %9s\n", $r['q'], $z($r['n']));
}

// Where the two lists disagree, COL now counts — that is the heart of the
// change and deserves to be reported.
$disagreeing = (int) $pdo->query(
    "SELECT COUNT(*) FROM map_tax_links v
       JOIN map_tax_col  c ON c.col_id   = v.col_species_id
       JOIN map_tax_gbif g ON g.gbif_key = v.gbif_species_key
      WHERE c.canonical <> g.canonical"
)->fetchColumn();
printf("\n  DGfM-Einträge, bei denen COL und GBIF verschiedene gültige Namen nennen: %s\n", $z($disagreeing));

$ambiguous = (int) $pdo->query('SELECT COUNT(*) FROM map_tax_links WHERE col_candidates > 1 OR gbif_candidates > 1')->fetchColumn();
printf("  mehrdeutige Zuordnungen: %s\n", $z($ambiguous));

if ($really) {
    $pdo->commit();
    printf("\n  %s Verknüpfungen geschrieben\n", $z($linked));
} else {
    $pdo->rollBack();
    echo "\nZurückgenommen. Zum Schreiben mit --really erneut aufrufen.\n";
}
