<?php
/**
 * WHAT ONE KNOWS OF A SPECIES ALREADY — the finds of it with a picture that
 * one may see, and the numbers its pages on the web go by.
 *
 * It answers the find's „Bestimmung" (pages/identify.php,
 * api/v1/species_finds.php): for a find named „Boletus edulis" the finds of
 * that species — one's own, the co-owned, the shared ones — as tiles, and
 * beside them the Internet-Links (includes/web_links.php).
 *
 * IN THE CORE SINCE 24.11.2026 („Links und die Funde könnten wir quasi
 * immer anzeigen"). Until then the two functions lived in a module that
 * shows them beside its own content, so whoever had no right to that module
 * saw neither — although nothing here is the module's: the finds are the
 * Fundbrowser's own stock, the names come from the taxonomy tables every
 * installation carries, and the links are written from the name. A module
 * that shows the same asks here.
 */

declare(strict_types=1);

/**
 * WHICH NAME A FIND'S TITLE ASKS FOR. The title is free text — „Boletus
 * edulis", „Inocybe cf. geophylla", „Scutellinia sp.", „Russula" —, the
 * links and the finds want a name: the species where the title names one
 * (an open mark „cf." or „aff." before the epithet is passed over: the
 * pages of the species one compares with are exactly what one wants then),
 * else the genus alone. A title that does not begin like a genus asks for
 * nothing.
 *
 * @return array{name: string, species: bool}
 */
function species_asked(string $title): array
{
    $words = preg_split('~\s+~u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($words === [] || !preg_match('~^\p{Lu}[\p{Ll}-]{2,}$~u', $words[0])) {
        return ['name' => '', 'species' => false];
    }
    $genus = $words[0];
    $i = 1;
    while (isset($words[$i]) && in_array(mb_strtolower($words[$i]), ['cf.', 'cf', 'aff.', 'aff'], true)) {
        $i++;
    }
    $epithet = $words[$i] ?? '';
    // „sp.", „spec.", „agg.", „s. l." and an author's name are not an epithet.
    if (!preg_match('~^[\p{Ll}-]{3,}$~u', $epithet) || in_array($epithet, ['spec', 'agg', 'sensu', 'var', 'subsp', 'ssp'], true)) {
        return ['name' => $genus, 'species' => false];
    }
    $name = $genus . ' ' . $epithet;
    // „Genus epithet var. x" stays whole — the variety is the find's name.
    if (isset($words[$i + 2]) && in_array($words[$i + 1], ['var.', 'f.', 'subsp.', 'ssp.'], true)
        && preg_match('~^[\p{Ll}-]{3,}$~u', $words[$i + 2])) {
        $name .= ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
    }
    return ['name' => $name, 'species' => true];
}

/**
 * Is this word a genus the lists know? For a title that names no species —
 * „Russula", „Scutellinia sp." — the genus is what is asked for; but a
 * title is free text, and „Brandfläche" or „Parkplatz" begin like a genus
 * too. Links to those would only be noise under the find.
 */
function species_genus_known(PDO $pdo, string $genus): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM map_tax_gbif WHERE canonical = ? AND taxon_rank = 'GENUS' LIMIT 1");
    $stmt->execute([$genus]);
    if ($stmt->fetchColumn() !== false) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_tax_dgfm WHERE genus = ? LIMIT 1');
    $stmt->execute([$genus]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Every name a species goes by, lower case: the name itself, the DGfM
 * group it belongs to (its synonyms there) and every GBIF record whose
 * species it is. A name that is itself accepted is not followed into a
 * homonym (Helvella crispa Bull. = Craterellus undulatus).
 *
 * @return array<string, true>
 */
function species_names(PDO $pdo, string $name): array
{
    $name = trim(preg_replace('~\s+~', ' ', $name));
    $names = [mb_strtolower($name) => true];
    $stmt = $pdo->prepare('SELECT DISTINCT d2.binomial FROM map_tax_dgfm d1 JOIN map_tax_dgfm d2 ON d2.sipnr = d1.sipnr WHERE d1.binomial = ?');
    $stmt->execute([$name]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $n) $names[mb_strtolower((string) $n)] = true;
    $species = species_gbif_keys($pdo, $name);
    if ($species !== []) {
        $in = implode(',', array_fill(0, count($species), '?'));
        /* The other way round as well: a synonym whose name another species
           bears as its accepted name is that species' name. Craterellus
           undulatus counts „Helvella crispa" (Bull.) among its synonyms —
           a find titled so is an elfin saddle all the same. */
        $stmt = $pdo->prepare("SELECT DISTINCT s.canonical FROM map_tax_gbif s
                                WHERE (s.species_key IN ($in) OR s.gbif_key IN ($in) OR (s.is_synonym = 1 AND s.parent_key IN ($in)))
                                  AND s.taxon_rank IN ('SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM')
                                  AND NOT EXISTS (SELECT 1 FROM map_tax_gbif o WHERE o.canonical = s.canonical AND o.is_synonym = 0
                                                     AND COALESCE(o.species_key, o.gbif_key) NOT IN ($in))");
        $stmt->execute(array_merge($species, $species, $species, $species));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $n) $names[mb_strtolower((string) $n)] = true;
    }
    return $names;
}

/**
 * The GBIF species a name stands for — its own record where the name is
 * accepted, else what its synonym records point at.
 *
 * @return list<int>
 */
function species_gbif_keys(PDO $pdo, string $name): array
{
    $stmt = $pdo->prepare("SELECT COALESCE(species_key, IF(is_synonym = 1, parent_key, gbif_key)) AS species, is_synonym
                             FROM map_tax_gbif WHERE canonical = ? AND taxon_rank IN ('SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM')");
    $stmt->execute([$name]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $own = array_filter($records, static fn($r) => !(int) $r['is_synonym']);
    return array_values(array_unique(array_filter(array_map('intval', array_column($own ?: $records, 'species')))));
}

/**
 * The numbers a species' pages on the web go by (web_links() takes them):
 * its GBIF species and its DGfM group. Many stored links hang on these and
 * not on the name — without them only the searches would come. Null where a
 * list does not know the name; a genus has neither.
 *
 * @return array{gbif: ?int, dgfm: ?int}
 */
function species_numbers(PDO $pdo, string $name): array
{
    $name = trim(preg_replace('~\s+~', ' ', $name));
    $stmt = $pdo->prepare('SELECT DISTINCT sipnr FROM map_tax_dgfm WHERE binomial = ?');
    $stmt->execute([$name]);
    $sips = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    return ['gbif' => species_gbif_keys($pdo, $name)[0] ?? null, 'dgfm' => $sips[0] ?? null];
}

/**
 * THE FINDS OF A SPECIES, AS TILES (28.10.2026: „die Art …, die wir selbst
 * schon mit Bild versehen haben, also bearbeitete Fundpunkte und auch die
 * Fundpunkte von Freunden, die diese Art mit Bild haben … Kacheln mit den
 * Bildern … beim Draufklicken in den Fundbrowser").
 *
 * THE FUNDBROWSER'S STOCK, NOT A SECOND ROAD: finds_overview() already
 * answers which finds one may see — one's own, the co-owned, the shares
 * with their per-recipient filters, the hints, the excursions — with both
 * kinds of picture in the gallery's order. Here only the finds with a
 * picture whose TITLE names the species under any of its names: the find's
 * title is its species (point_species_keys says why), and a find titled
 * with a synonym („Pseudocraterellus sinuosus") belongs to the species as
 * much as one titled with today's name. A title with „cf." or „aff." after
 * the genus is not a sure find of it and stays out.
 *
 * @return list<array{id:int, title:string, number:string, found_at:?string, own:bool, owner:?string, thumb:string, pictures:int}>
 */
function species_finds(PDO $pdo, int $userId, string $name): array
{
    require_once __DIR__ . '/finds.php';
    $names = species_names($pdo, $name);
    $out = [];
    foreach (finds_overview($pdo, $userId) as $f) {
        if ($f['pictures'] === []) continue;
        // „Genus epithet" and, where it says so, „Genus epithet var. x" — the author and what follows are not the name.
        if (!preg_match('~^(\S+ \S+)(?: ((?:var|f|subsp|ssp)\. \S+))?~u', trim($f['title']), $m)) continue;
        $binomial = mb_strtolower($m[1]);
        $full = isset($m[2]) ? $binomial . ' ' . mb_strtolower($m[2]) : null;
        if (!isset($names[$binomial]) && ($full === null || !isset($names[$full]))) continue;
        $out[] = [
            'id' => $f['id'], 'title' => $f['title'], 'number' => $f['number'], 'found_at' => $f['found_at'],
            'own' => $f['own'], 'owner' => $f['owner'], 'thumb' => $f['pictures'][0]['thumb'], 'pictures' => count($f['pictures']),
        ];
    }
    return $out;
}

/**
 * The older pictures of the species — out of one's own and one's friends'
 * picture archives (includes/picture_archives.php) —, beneath the finds in
 * the same answer. Nothing there may take the finds down with it.
 *
 * @return list<array>
 */
function species_older_pictures(PDO $pdo, int $userId, string $name): array
{
    require_once __DIR__ . '/picture_archives.php';
    try {
        return archives_ready($pdo) ? archives_for($pdo, $userId, species_names($pdo, $name)) : [];
    } catch (Throwable $e) {
        error_log('picture archives: ' . $e->getMessage());
        return [];
    }
}
