<?php
/**
 * The DGfM's four lists — Speisepilze, uneinheitlich beurteilter
 * Speisewert, Giftpilze, Verantwortungsarten — as marks on a fungus.
 *
 * 17.09.2026: „Diese vier Listen würde ich gerne auch
 * ausfiltern." Two questions, two filter groups: the RESPONSIBILITY is a
 * conservation statement and sits in the Gefährdung group beside the Red
 * List; the FOOD VALUE is its own group (Speisewert) with three marks that
 * a species may carry several of at once — Morchella is an edible mushroom
 * with a warning, inconsistently rated, and poisonous raw, all in the
 * DGfM's own words.
 *
 * NOT ON THE POINT, ON THE SPECIES — the same reasoning as for the
 * endangerment (species_endangerment in species.php): the lists get
 * reissued, and a find from last year has to show this year's list.
 * species_dgfm_marks() looks the marks up per title species at load time.
 *
 * THE MATCH IS BY NAME, because the lists carry none of the DGfM's own
 * taxon numbers. dgfm_marks_import() reads data/dgfm/marks.csv (written by
 * admin/dgfm_lists.mjs from the PDFs) and resolves every row once, into
 * map_dgfm_marks: a species by its binomial — through a synonym row to the
 * accepted taxon —, a „spp." row by its genus, and what fits neither is
 * kept with matched = 'none' so the administration can show it.
 */

declare(strict_types=1);

/**
 * The four lists in the order the filters show them; the German word is
 * what the user reads (filter checkboxes, point window, export).
 */
const DGFM_LISTS = [
    'edible'         => 'Speisepilz',
    'inconsistent'   => 'uneinheitlich beurteilt',
    'poisonous'      => 'Giftpilz',
    'responsibility' => 'Verantwortungsart',
];

/** The three food-value marks — the checkboxes of the Speisewert group. */
const DGFM_EDIBILITY = ['edible', 'inconsistent', 'poisonous'];

/**
 * The DGfM's spelling → the reference's, where the two disagree and the
 * species is the same (the maintainer handed the unmatched list back on
 * 17.09.2026). Three kinds: the list writes an older name (Amanitopsis
 * for Amanita, Lycoperdon utriformis for utriforme), a different gender
 * ending (Inosperma calamistrata / calamistratum), or plainly a typo
 * (Tubera, Meripilu, Xerocomelllus). What is NOT here stays unmatched on
 * purpose: Amanita smithiana, Trogia venenata, Russula subnigricans,
 * Paralepistopsis acromelalga and Auricularia cornea are not in the
 * German reference because they do not grow here — the DGfM lists them
 * for the import trade and the traveller, and an alias would invent a
 * taxon. Keyed by the name as the CSV's species column carries it
 * (agg./s. l. already dropped).
 */
const DGFM_NAME_ALIASES = [
    'Agaricus silvaticus'       => 'Agaricus sylvaticus',
    'Agaricus silvicola'        => 'Agaricus sylvicola',
    'Amanitopsis ceciliae'      => 'Amanita ceciliae',
    'Amanitopsis crocea'        => 'Amanita crocea',
    'Amanitopsis fulva'         => 'Amanita fulva',
    'Amanitopsis vaginata'      => 'Amanita vaginata',
    'Cathathelasma imperiale'   => 'Catathelasma imperiale',
    'Hericium erinaceum'        => 'Hericium erinaceus',
    'Lycoperdon utriformis'     => 'Lycoperdon utriforme',
    'Melanolecua cognata'       => 'Melanoleuca cognata',
    'Meripilu giganteus'        => 'Meripilus giganteus',
    'Mycentinis alliaceus'      => 'Mycetinis alliaceus',
    'Psathrella spadiceogrisea' => 'Psathyrella spadiceogrisea',
    'Tubera aestivum'           => 'Tuber aestivum',
    'Tubera brumale'            => 'Tuber brumale',
    'Tubera macrosporum'        => 'Tuber macrosporum',
    'Xerocomelllus cisalpinus'  => 'Xerocomellus cisalpinus',
    'Inosperma calamistrata'    => 'Inosperma calamistratum',
    'Inosperma maculata'        => 'Inosperma maculatum',
    'Pseudosperma rimosa'       => 'Pseudosperma rimosum',
    'Entoloma chalybaeum'       => 'Entoloma chalybeum',
];

function dgfm_marks_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_dgfm_marks');
}

/** Migration 0109 — the 'subset' kind of a genus row. */
function dgfm_marks_subset_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        $q = $pdo->query("SHOW COLUMNS FROM map_dgfm_marks LIKE 'matched'");
        $z = $q ? $q->fetch() : false;
        $ready = $z !== false && str_contains((string) $z['Type'], "'subset'");
    }
    return $ready;
}

/**
 * Does a genus row mean the WHOLE genus, or only part of it?
 *
 * 18.09.2026: „Russula nigricans ist kein Giftpilz, es ist
 * sogar ein Speisepilz" — and Russula ionochlora the same. The poisonous
 * list closes a genus with „Russula spp.", and the remark says what it
 * means: „Scharfe und bittere Arten". The first cut read every „spp." as
 * the whole genus and made every mild Russula poisonous.
 *
 * THE REMARK DECIDES, and the words are the DGfM's: a remark that names
 * a part — „weitere verdächtige Arten", „verschiedene Arten verdächtig",
 * „scharfe und bittere Arten", „Cortinarien mit grünen, gelben … Pigmenten",
 * „kleine Schirmlinge mit bräunlichen und roten Pigmenten" — narrows the
 * row to species the list does not name, and a mark from it would be an
 * invention. „Alle Arten sind verdächtig" widens it, and a remark about
 * the effect („roh sehr giftig", „häufig individuell gastrointestinale
 * Beschwerden") does not narrow. So: „Arten" without „alle" in front, or a
 * „mit" that describes a look, means part. The rows that fall on either
 * side stand in the administration, so a person can see the sorting.
 *
 * @return 'genus'|'subset'
 */
function dgfm_genus_scope(?string $remark): string
{
    $r = mb_strtolower(trim((string) $remark));
    if ($r === '') {
        return 'genus';
    }
    if (preg_match('/\balle arten\b/u', $r)) {
        return 'genus';
    }
    if (preg_match('/\barten\b/u', $r) || preg_match('/\bmit\b/u', $r)) {
        return 'subset';
    }
    return 'genus';
}

/**
 * The state of the marks table in one short string — for the bucket
 * fingerprints (bucket_derived_stamp in bucket.php).
 *
 * THE MARKS ARE DERIVED, AND A FINGERPRINT HASHES THE POINTS. On
 * 17.09.2026 the maintainer's device fetched its buckets between the
 * deployment and his „Listen einlesen": the points arrived without marks,
 * the table was still empty. Afterwards nothing about the points moved,
 * every fingerprint still matched, and the map never fetched again —
 * the marks stood on the server and not in his point window, and no
 * reload could change that. So the fingerprint carries this stamp: a
 * new import (DELETE and INSERT, the ids run on) moves it, every bucket
 * of every device counts as stale once, and the next start fetches them.
 *
 * Read once per request.
 */
function dgfm_marks_stamp(PDO $pdo, bool $refresh = false): string
{
    static $stamp = null;
    if ($stamp !== null && !$refresh) {
        return $stamp;
    }
    if (!dgfm_marks_ready($pdo)) {
        return $stamp = 'none';
    }
    $z = $pdo->query('SELECT COUNT(*) AS n, COALESCE(MAX(id), 0) AS top FROM map_dgfm_marks')->fetch();
    return $stamp = 'dgfm' . (int) $z['n'] . ':' . (int) $z['top'];
}

/** Where the CSV lives — the repository's copy, deployed with the code. */
function dgfm_marks_csv(): string
{
    return dirname(__DIR__) . '/data/dgfm/marks.csv';
}

/**
 * Read the CSV and rebuild the table.
 *
 * ALWAYS EVERYTHING: the table is fully derived from the CSV and the
 * reference, there is nothing in it that is not stored elsewhere, and a
 * new issue of a list replaces the old one whole.
 *
 * @return array{rows: int, matched: int, genera: int, subsets: int, aliased: int, unmatched: string[], lists: array<string, array{rows:int, matched:int, source_date:?string}>}
 */
function dgfm_marks_import(PDO $pdo, ?string $path = null): array
{
    $path ??= dgfm_marks_csv();
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('CSV nicht lesbar: ' . $path);
    }
    $header = fgetcsv($handle, 0, ';', '"', '');
    $expected = ['list', 'name', 'genus', 'species', 'german', 'value', 'remark', 'syndrome', 'source_date'];
    if ($header !== $expected) {
        fclose($handle);
        throw new RuntimeException('Kopfzeile passt nicht: ' . implode(', ', (array) $header));
    }

    // The binomial resolves to the ACCEPTED taxon: a synonym row's sipnr
    // points at it, an accepted row's sipnr is its own number. Accepted
    // rows first, so a name that is both (it happens) takes the accepted
    // one. The lookup on load joins the same way, from the other side.
    $bySpecies = $pdo->prepare(
        "SELECT taxnr, sipnr FROM map_tax_dgfm WHERE binomial = ?
          ORDER BY (taxon_rank = 'Art') DESC, taxnr LIMIT 1"
    );
    $byGenus = $pdo->prepare('SELECT 1 FROM map_tax_dgfm WHERE genus = ? LIMIT 1');
    $insert  = $pdo->prepare(
        'INSERT INTO map_dgfm_marks
            (list, name, species, genus, taxnr, matched, german, value, remark, syndrome, source_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $blank = static fn(mixed $v): ?string => trim((string) $v) === '' ? null : trim((string) $v);

    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM map_dgfm_marks');
    $out = ['rows' => 0, 'matched' => 0, 'genera' => 0, 'subsets' => 0, 'aliased' => 0, 'unmatched' => [], 'lists' => []];
    foreach (DGFM_LISTS as $list => $_) {
        $out['lists'][$list] = ['rows' => 0, 'matched' => 0, 'source_date' => null];
    }

    while (($z = fgetcsv($handle, 0, ';', '"', '')) !== false) {
        if (count($z) < 9 || !isset(DGFM_LISTS[$z[0]])) {
            continue;
        }
        [$list, $name, $genus, $species, $german, $value, $remark, $syndrome, $sourceDate] = $z;
        $genus   = $blank($genus);
        $species = $blank($species);
        $taxnr   = null;
        if ($genus !== null) {
            $byGenus->execute([$genus]);
            $matched = $byGenus->fetchColumn() !== false ? 'genus' : 'none';
            // Part of the genus only — kept, shown, and out of every lookup.
            // Before migration 0109 the column cannot say so; the row then
            // counts as 'none' rather than as the whole genus.
            if ($matched === 'genus' && dgfm_genus_scope($remark) === 'subset') {
                $matched = dgfm_marks_subset_ready($pdo) ? 'subset' : 'none';
            }
        } elseif ($species !== null) {
            // The reference's spelling where the DGfM's differs (the
            // table above); the row keeps the DGfM's in `species`.
            $lookup = DGFM_NAME_ALIASES[$species] ?? $species;
            $bySpecies->execute([$lookup]);
            $row = $bySpecies->fetch();
            if ($row) {
                $taxnr   = (int) $row['sipnr'] > 0 ? (int) $row['sipnr'] : (int) $row['taxnr'];
                $matched = 'species';
                if ($lookup !== $species) {
                    $out['aliased']++;
                }
            } else {
                $matched = 'none';
            }
        } else {
            continue;
        }
        $insert->execute([
            $list, $name, $species, $genus, $taxnr, $matched,
            $blank($german), $blank($value), $blank($remark), $blank($syndrome),
            preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $sourceDate) ? $sourceDate : null,
        ]);
        $out['rows']++;
        $out['lists'][$list]['rows']++;
        $out['lists'][$list]['source_date'] ??= $blank($sourceDate);
        if ($matched === 'none') {
            $out['unmatched'][] = $name;
        } elseif ($matched === 'subset') {
            $out['subsets']++;
        } else {
            $out['matched']++;
            $out['lists'][$list]['matched']++;
            if ($matched === 'genus') {
                $out['genera']++;
            }
        }
    }
    $pdo->commit();
    fclose($handle);
    dgfm_marks_by_name($pdo, true);   // the name fallback reads the new rows
    dgfm_marks_stamp($pdo, true);     // and the bucket fingerprints move
    $out['unmatched'] = array_values(array_unique($out['unmatched']));
    return $out;
}

/**
 * The marks of the given fungi — for the point's title species.
 *
 * @param string[] $identifiers "fungi:49097", … — other sources are
 *        passed over, the lists are the DGfM's and speak of German fungi.
 * @return array<string, list<array{list:string, value:?string, remark:?string, syndrome:?string, genus:bool}>>
 *         identifier => marks in DGFM_LISTS order, at most one per list
 */
function species_dgfm_marks(PDO $pdo, array $identifiers): array
{
    if (!dgfm_marks_ready($pdo)) {
        return [];
    }
    // The rows without a taxon come along under their name — see
    // dgfm_marks_by_name(); point_dgfm_marks() reads them off the title.
    $out = dgfm_marks_by_name($pdo);

    $ids = [];
    foreach ($identifiers as $identifier) {
        $parts = explode(':', (string) $identifier, 2);
        if (count($parts) === 2 && $parts[0] === 'fungi' && ctype_digit($parts[1])) {
            $ids[(int) $parts[1]] = true;
        }
    }
    if ($ids === []) {
        return $out;
    }
    $ids   = array_keys($ids);
    $space = implode(',', array_fill(0, count($ids), '?'));
    // A species row hangs on the accepted taxon: the row's own number, or
    // the one its sipnr points at (a synonym's row reaches its accepted
    // name that way). BOTH are tried — an accepted row's sipnr is its own
    // number in the reference as imported here, but nothing guarantees
    // it on every installation, and a join that trusted it alone would
    // hand back nothing without a word. A genus row hangs on the genus
    // of the row itself. Two ways to the same join, one statement.
    $stmt = $pdo->prepare(
        "SELECT t.taxnr AS identifier, m.list, m.value, m.remark, m.syndrome,
                (m.taxnr IS NULL) AS by_genus
           FROM map_tax_dgfm t
           JOIN map_dgfm_marks m
             ON (m.taxnr IS NOT NULL AND m.taxnr IN (t.taxnr, t.sipnr))
             OR (m.taxnr IS NULL AND m.genus IS NOT NULL AND m.matched = 'genus' AND m.genus = t.genus)
          WHERE t.taxnr IN ($space)"
    );
    $stmt->execute($ids);

    $found = [];
    foreach ($stmt as $z) {
        $key  = 'fungi:' . $z['identifier'];
        $list = (string) $z['list'];
        $mark = [
            'list'     => $list,
            'value'    => $z['value'] === '' ? null : $z['value'],
            'remark'   => $z['remark'] === '' ? null : $z['remark'],
            'syndrome' => $z['syndrome'] === '' ? null : $z['syndrome'],
            'genus'    => (bool) $z['by_genus'],
        ];
        // At most one mark per list, and the species' own row beats the
        // genus claim: „Russula spp. — scharfe und bittere Arten" must not
        // overwrite what the list says about one particular Russula.
        $have = $found[$key][$list] ?? null;
        if ($have === null || ($have['genus'] && !$mark['genus'])) {
            $found[$key][$list] = $mark;
        }
    }

    foreach ($found as $key => $byList) {
        $marks = [];
        foreach (array_keys(DGFM_LISTS) as $list) {
            if (isset($byList[$list])) {
                $marks[] = $byList[$list];
            }
        }
        $out[$key] = $marks;
    }
    return $out;
}

/**
 * The rows the reference does not know, keyed by their name — „name:Trogia
 * venenata" — so a find can carry them BY ITS TITLE.
 *
 * 17.09.2026, on Paralepistopsis acromelalga: not yet recorded
 * in Germany, „auf jeden Fall bald zu erwarten" — and asked whether a
 * find of it would show as poisonous: „Ja, bau das so ein." A species
 * absent from the reference has no taxon number for a mark to hang on,
 * whether the title was typed or picked from GBIF. So the unmatched rows
 * — and ONLY those; the 446 resolved ones stay on their number, where
 * synonyms and renamings keep working — match the scientific name at the
 * head of the title instead (dgfm_title_key). The day the reference
 * gains the species, the row resolves on the next import and this
 * fallback lets go of it by itself.
 *
 * Six rows today; read once per request.
 *
 * @return array<string, list<array>>  'name:<Genus epithet>' => marks
 */
function dgfm_marks_by_name(PDO $pdo, bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $cache = [];
    $stmt = $pdo->query(
        "SELECT species, list, value, remark, syndrome FROM map_dgfm_marks
          WHERE matched = 'none' AND species IS NOT NULL ORDER BY id"
    );
    $byName = [];
    foreach ($stmt as $z) {
        $byName[(string) $z['species']][(string) $z['list']] ??= [
            'list'     => (string) $z['list'],
            'value'    => $z['value'] === '' ? null : $z['value'],
            'remark'   => $z['remark'] === '' ? null : $z['remark'],
            'syndrome' => $z['syndrome'] === '' ? null : $z['syndrome'],
            'genus'    => false,
        ];
    }
    foreach ($byName as $name => $byList) {
        $marks = [];
        foreach (array_keys(DGFM_LISTS) as $list) {
            if (isset($byList[$list])) {
                $marks[] = $byList[$list];
            }
        }
        $cache['name:' . $name] = $marks;
    }
    return $cache;
}

/**
 * The scientific name at the head of a title — „Genus epithet" — or null.
 * Whatever follows (author, „cf.", a German name in brackets) is not
 * part of the key; a title that does not begin with a binomial has none.
 */
function dgfm_title_key(?string $title): ?string
{
    if ($title === null) {
        return null;
    }
    return preg_match('/^\s*([A-Z][a-z]+)\s+([a-z][a-z-]+)/u', $title, $m) === 1 ? $m[1] . ' ' . $m[2] : null;
}

/**
 * The marks of a point — those of its title species, first come first
 * kept per list, and for a species the reference does not know those of
 * the name at the head of the title. The twin of point_endangerment() in
 * data.php, with that one addition.
 *
 * @param array<string, list<array>> $marksOf  from species_dgfm_marks()
 */
function point_dgfm_marks(mixed $extraSpecies, array $marksOf, ?string $title = null): array
{
    $out  = [];
    $seen = [];
    $take = static function (array $marks) use (&$out, &$seen): void {
        foreach ($marks as $mark) {
            if (!isset($seen[$mark['list']])) {
                $seen[$mark['list']] = true;
                $out[] = $mark;
            }
        }
    };
    foreach (point_species_keys($extraSpecies) as $identifier) {
        $take($marksOf[$identifier] ?? []);
    }
    // The rows without a taxon, by the name in the title (dgfm_marks_by_name).
    $key = dgfm_title_key($title);
    if ($key !== null) {
        $take($marksOf['name:' . $key] ?? []);
    }
    return $out;
}

/**
 * A measurement for the administration: does the chain work on THIS
 * installation? Boletus edulis looked up at the reference and through the
 * marks, and the count of finds whose title species carries any mark —
 * the same joins the points take on load. Built on 17.09.2026, when the
 * live server's table was full and the point window showed nothing, and
 * nothing on the page said which half was silent.
 *
 * @return array{sample: string, marked: int, referenced: int}
 */
function dgfm_marks_probe(PDO $pdo): array
{
    $sample = '';
    $q = $pdo->query("SELECT taxnr FROM map_tax_dgfm WHERE binomial = 'Boletus edulis' AND taxon_rank = 'Art' LIMIT 1");
    $taxnr = $q->fetchColumn();
    if ($taxnr !== false) {
        $marks  = species_dgfm_marks($pdo, ['fungi:' . $taxnr]);
        $sample = dgfm_marks_words($marks['fungi:' . $taxnr] ?? []);
    }
    $referenced = 0;
    $marked     = 0;
    if (table_exists($pdo, 'map_point_species')) {
        $referenced = (int) $pdo->query(
            "SELECT COUNT(DISTINCT a.point_id) FROM map_point_species a
              WHERE a.field = '' AND a.source = 'fungi' AND a.identifier <> ''"
        )->fetchColumn();
        $marked = (int) $pdo->query(
            "SELECT COUNT(DISTINCT a.point_id) FROM map_point_species a
               JOIN map_tax_dgfm t ON t.taxnr = a.identifier
               JOIN map_dgfm_marks m
                 ON (m.taxnr IS NOT NULL AND m.taxnr IN (t.taxnr, t.sipnr))
                 OR (m.taxnr IS NULL AND m.genus IS NOT NULL AND m.matched = 'genus' AND m.genus = t.genus)
              WHERE a.field = '' AND a.source = 'fungi' AND a.identifier <> ''"
        )->fetchColumn();
    }
    return ['sample' => $sample, 'marked' => $marked, 'referenced' => $referenced];
}

/**
 * The marks as words — „Speisepilz, Giftpilz, Verantwortungsart !!" — for
 * the CSV column and the find report.
 *
 * @param list<array{list:string, value:?string}> $marks
 */
function dgfm_marks_words(array $marks): string
{
    $words = [];
    foreach ($marks as $mark) {
        $word = DGFM_LISTS[$mark['list']] ?? $mark['list'];
        if (($mark['value'] ?? null) !== null) {
            $word .= ' ' . $mark['value'];
        }
        $words[] = $word;
    }
    return implode(', ', $words);
}

/**
 * Does the point pass the two DGfM conditions of a filter?
 *
 * The server half of edibilityMatches() and responsibilityMatches() in
 * assets/js/filter.js — the auto lists ask it. Empty edibility means all;
 * chosen marks are OR-linked like the Red List categories; the
 * responsibility hook is an AND beside them.
 *
 * @param string[] $edibility      chosen marks out of DGFM_EDIBILITY
 * @param list<array{list:string}> $marks  the point's, from point_dgfm_marks()
 */
function dgfm_marks_match(array $edibility, bool $responsibility, array $marks): bool
{
    $lists = array_column($marks, 'list');
    if ($edibility !== [] && array_intersect($edibility, $lists) === []) {
        return false;
    }
    if ($responsibility && !in_array('responsibility', $lists, true)) {
        return false;
    }
    return true;
}
