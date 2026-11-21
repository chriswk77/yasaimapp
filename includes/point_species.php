<?php
/**
 * Write a point's species into their own table.
 *
 * DERIVED, NEVER ENTERED. Everything here comes from map_points — from the
 * title, from extra and from extra_species. The table is a second view of
 * existing data, not a second place where anything is stored. That is why it
 * may be thrown away and rebuilt at any time; that is also why it is never
 * the source for a display, only for questions like "all Boletus".
 *
 * WHERE THE NAMES COME FROM — two places, and both are needed:
 *
 *   THE TITLE, when one of the point's lists carries is_species. That is
 *   where the find itself is written. Leaving it out would mean the table
 *   could not be used for the main case.
 *
 *   FIELDS with is_species — host plant, accompanying vegetation. A point can
 *   have both at once: a fungus in the title, its host plant in the field.
 *
 * THE FIELDS ARE INHERITED. Whoever sits in a sublist also has the fields
 * of all lists above it; is_species on the title is inherited from above the
 * same way. That has to hold here exactly as it does in the interface,
 * otherwise species from sublists vanish silently.
 */

declare(strict_types=1);

/** Is the table from migration 0036 in place yet? */
function point_species_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_point_species', 'point_id');
}

/**
 * Update the derived rows without anything being able to break in the process.
 *
 * SAVING A POINT MUST NEVER FAIL ON THIS. This table is derived — it is
 * convenient, not necessary. If the derivation goes wrong, the point is
 * saved anyway, and the backfill in the administration rebuilds the rows.
 * The other way around — a point that fails to save because of a helper
 * table — would be the worse trade, and clearly so.
 */
function point_species_refresh(PDO $pdo, int $userId, int $pointId): void
{
    try {
        point_species_set($pdo, $userId, $pointId);
    } catch (Throwable $e) {
        error_log('Arten zu Punkt ' . $pointId . ' nicht abgeleitet: ' . $e->getMessage());
    }
}

/**
 * The reference of the TITLE — resolved by the server, not by the browser.
 *
 * WHY THE SERVER DOES THIS ALONE. The reference ("fungi:49097") is what the
 * red-list rating and the later consolidation hang on. Three ways lead a
 * species name into a title, and only one of them could ever deliver a
 * reference: the "Meine Pilze" import looks the name up itself; picking from
 * the suggestion list sent NO source for the title (the browser records
 * sources per data-field, and the title has none); and moving a point into a
 * species list in the organize tab touches the title not at all. Measured on
 * 23.08.2026: even a title chosen from the suggestion list stored NULL.
 * Worse, editing an imported point DELETED its reference — the browser sends
 * the extra block without the title, and species_references_preserve drops
 * what no longer stands in its field.
 *
 * So the rule is now: THE TITLE'S REFERENCE BELONGS TO THE SERVER. After
 * every create, update and backfill this runs and rewrites the '' block of
 * extra_species from what is actually true: point in a species branch, name
 * standing in the title, name found in the name lists — reference. The
 * browser needs to know nothing about it, and no path can be forgotten,
 * because they all end in point_create/point_update.
 *
 * THE SOURCE ORDER is that of the import (fungi first, then the two BfN
 * lists, GBIF last): the curated German reference works carry the red-list
 * ratings, GBIF is the catch-all. An existing reference for a name that
 * still stands in the title is KEPT, not re-resolved — the import may have
 * chosen deliberately, and re-resolving would silently overrule it.
 *
 * A name the lists do not know gets no reference — "Steinpilz am
 * Buchenrand" and "Scutellinia sp." resolve nowhere, and that is the
 * correct answer, not a failure. UNLESS IT IS A WORKING NAME the holder
 * may use (working_names.php, migration 0189): „Inocybe 'violacea' ad
 * int." gets „working:<id>". After the lists, so a listed name always
 * wins — a working name cannot be coined for one anyway.
 */
function title_references_sync(PDO $pdo, int $userId, int $pointId): bool
{
    if (!column_exists($pdo, 'map_points', 'extra_species')) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT title, extra_species FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $point = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$point) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $isSpecies = point_species_fields($pdo, $stmt->fetchAll(PDO::FETCH_COLUMN))['title'];

    $references = json_decode((string) ($point['extra_species'] ?? ''), true);
    $references = is_array($references) ? $references : [];
    $old = $references[''] ?? [];
    $old = is_array($old) ? $old : [];

    $new = [];
    if ($isSpecies) {
        foreach (species_entries((string) $point['title']) as $name) {
            if (isset($old[$name]) && is_string($old[$name]) && title_reference_alive($pdo, $old[$name])) {
                $new[$name] = $old[$name];
                continue;
            }
            foreach (['fungi', 'lichens', 'plants', 'gbif'] as $source) {
                $value = species_reference($pdo, $name, $source);
                if ($value !== null) {
                    $new[$name] = $value;
                    break;
                }
            }
            if (!isset($new[$name])) {
                require_once __DIR__ . '/working_names.php';
                if (($value = working_name_reference($pdo, $userId, $name)) !== null) {
                    $new[$name] = $value;
                }
            }
        }
    }

    // == and not ===: it compares key-value pairs regardless of order, and
    // the order is meaningless here anyway (MySQL normalizes JSON keys).
    if ($new == $old) {
        return false;
    }

    if ($new === []) {
        unset($references['']);
    } else {
        $references[''] = $new;
    }
    $pdo->prepare('UPDATE map_points SET extra_species = ? WHERE id = ?')
        ->execute([$references === [] ? null : json_encode($references, JSON_UNESCAPED_UNICODE), $pointId]);

    // The rating travels inside the point payload — without the bump no
    // other device would ever learn of it (same line of reasoning as in
    // autolists_refresh).
    if (column_exists($pdo, 'map_points', 'touched_at')) {
        $pdo->prepare('UPDATE map_points SET touched_at = CURRENT_TIMESTAMP(6) WHERE id = ?')
            ->execute([$pointId]);
    }
    return true;
}

/**
 * Does a kept reference still point at something? The lists' do — they are
 * never emptied under a find. A working name can be deleted, and then the
 * find's reference must go with it, or it would name a folder and a species
 * that no longer exist. The friends a working name was shared with keep
 * their reference when the sharing ends: their finds are theirs.
 */
function title_reference_alive(PDO $pdo, string $reference): bool
{
    if (!str_starts_with($reference, 'working:')) {
        return true;
    }
    require_once __DIR__ . '/working_names.php';
    $id = substr($reference, 8);
    return ctype_digit($id) && working_names_ready($pdo) && working_name($pdo, (int) $id) !== null;
}

/**
 * The same, but unable to fail — for the save paths, where a derived datum
 * must never hold up the point (same contract as point_species_refresh).
 */
function title_references_refresh(PDO $pdo, int $userId, int $pointId): bool
{
    try {
        return title_references_sync($pdo, $userId, $pointId);
    } catch (Throwable $e) {
        error_log('Titel-Verweis zu Punkt ' . $pointId . ' nicht aufgelöst: ' . $e->getMessage());
        return false;
    }
}

/**
 * The genus for a name.
 *
 * FROM THE NAME LIST, WHEN POSSIBLE. The taxonomy tables carry it as a
 * column of its own — that is the reliable answer, along with every special
 * case it contains.
 *
 * OTHERWISE THE FIRST WORD, but only if the name looks like a scientific
 * one at all: capital letter at the start. "Buche" would otherwise yield
 * the genus "Buche", and the genus list would fill up with German names,
 * substrates and typos. Better no genus than an invented one.
 *
 * The hybrid cross is stripped: "×Cupressocyparis" is the genus
 * "Cupressocyparis", and a character in front does not make it a different one.
 */
function species_genus(PDO $pdo, string $name, string $source, string $ident): string
{
    $fromTable = [
        'fungi'    => ['map_tax_dgfm',     'taxnr'],
        'lichens' => ['map_tax_lichens', 'uuid'],
        'plants' => ['map_tax_plants', 'uuid'],
        'gbif'     => ['map_tax_gbif',     'gbif_key'],
    ];

    // A working name (working_names.php) brings its genus with it — one the lists know.
    if ($source === 'working' && ctype_digit($ident)) {
        require_once __DIR__ . '/working_names.php';
        if (($w = working_name($pdo, (int) $ident)) !== null) {
            return mb_substr((string) $w['genus'], 0, 120);
        }
    }

    if ($ident !== '' && isset($fromTable[$source])) {
        [$table, $column] = $fromTable[$source];
        if (column_exists($pdo, $table, 'genus') && column_exists($pdo, $table, $column)) {
            /* THE GENUS FOLLOWS THE NAME (0106): a DGfM row offered under
               GBIF's name — Boletus torosus as Imperator torosus — is filed
               under GBIF's genus, or the tree would sort the find under a
               genus its title does not carry. Only where the row has a
               gbif_name; a synonym row keeps its own genus. */
            $sql = "SELECT genus FROM `$table` WHERE `$column` = ? LIMIT 1";
            if ($table === 'map_tax_dgfm' && column_exists($pdo, 'map_tax_links', 'gbif_name')) {
                $sql = "SELECT COALESCE(g.genus, d.genus) FROM map_tax_dgfm d
                          LEFT JOIN map_tax_links l ON l.taxnr = d.taxnr AND l.gbif_name IS NOT NULL
                          LEFT JOIN map_tax_gbif g ON g.gbif_key = l.gbif_species_key
                         WHERE d.taxnr = ? LIMIT 1";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ident]);
            $value = trim((string) $stmt->fetchColumn());
            if ($value !== '') {
                return mb_substr($value, 0, 120);
            }
        }
    }

    /* THE HYBRID MARKER, AND ONLY THE MARKER (25.08.2026). ltrim with
       the character set '×xX ' stood here and ate the FIRST LETTER of
       every name beginning with X: „Xerocomus spec." became „erocomus",
       failed the capital-letter test below and came out with no genus at
       all. Measured on the day it was found: 298 names in the DGfM list
       and 3.724 in the GBIF backbone begin with X — Xylaria,
       Xeromphalina, Xerula, Xerocomus —, and every one of them lost its
       genus silently. ltrim is the wrong tool for this: it takes a SET
       of characters and keeps eating; it does not know a word.

       What is genuinely a marker: the multiplication sign, attached or
       loose („×Cupressocyparis", „× Cupressocyparis"), and the Latin
       letter standing ALONE as its substitute („x Cupressocyparis").
       Attached to a word, the x belongs to the word. */
    $bare  = (string) preg_replace('/^\s*(?:×\s*|[xX]\s+)/u', '', trim($name));
    $first = preg_split('/\s+/u', $bare)[0] ?? '';
    $first = trim($first, ".,;:()[]");
    if ($first === '' || mb_strtoupper(mb_substr($first, 0, 1)) !== mb_substr($first, 0, 1)) {
        return '';
    }
    return mb_substr($first, 0, 120);
}

/**
 * A point's fields that carry species — and whether its title is one.
 *
 * From its lists alone: since the Mappen (0168) a list carries its fields
 * and its species flag itself, nothing comes from above any more.
 *
 * @param int[] $listIds
 * @return array{title: bool, fields: string[]}
 */
function point_species_fields(PDO $pdo, array $listIds): array
{
    $listIds = array_values(array_unique(array_map('intval', $listIds)));
    if ($listIds === []) {
        return ['title' => false, 'fields' => []];
    }

    $all = $listIds;
    $space = implode(',', array_fill(0, count($all), '?'));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM map_lists WHERE id IN ($space) AND is_species = 1");
    $stmt->execute($all);
    $title = (int) $stmt->fetchColumn() > 0;

    $fields = [];
    if (column_exists($pdo, 'map_list_fields', 'is_species')) {
        $stmt = $pdo->prepare("SELECT DISTINCT name FROM map_list_fields WHERE list_id IN ($space) AND is_species = 1");
        $stmt->execute($all);
        $fields = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // With the species-list checkbox come the find fields — among them two
    // that carry species names themselves (substrate / host, accompanying
    // vegetation). They belong in here just like dedicated is_species fields,
    // otherwise every find's host plant would be missing from the species
    // table.
    if ($title) {
        require_once __DIR__ . '/find_fields.php';
        $fields = array_values(array_unique(array_merge(find_field_species_names(), $fields)));
    }

    return ['title' => $title, 'fields' => $fields];
}

/**
 * Redetermine and write a point's species.
 *
 * ALWAYS AS A WHOLE: delete first, then rewrite. Reconciling one by one
 * would be more code for the same effect, and any bug in it would leave
 * names on points where they no longer belong — exactly the kind of residue
 * you never notice.
 *
 * @return int Number of rows written
 */
function point_species_set(PDO $pdo, int $userId, int $pointId): int
{
    if (!point_species_ready($pdo)) {
        return 0;
    }

    $withCollections = column_exists($pdo, 'map_points', 'is_collection');
    $stmt = $pdo->prepare('SELECT title, extra, extra_species' . ($withCollections ? ', is_collection' : '')
        . ' FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $point = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare('DELETE FROM map_point_species WHERE point_id = ?')->execute([$pointId]);
    if (!$point) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $where = point_species_fields($pdo, $stmt->fetchAll(PDO::FETCH_COLUMN));

    $extra   = json_decode((string) ($point['extra'] ?? ''), true);
    $references = json_decode((string) ($point['extra_species'] ?? ''), true);
    $extra   = is_array($extra) ? $extra : [];
    $references = is_array($references) ? $references : [];

    // Field name => raw text. The title under the empty name.
    $sources = [];
    // An Aufsammlung's title names the sample („Aufsammlung A-2026-07"),
    // never a species — its substrate field still counts (collections.php).
    if ($where['title'] && empty($point['is_collection'])) {
        $sources[''] = (string) $point['title'];
    }
    foreach ($where['fields'] as $field) {
        if (isset($extra[$field])) {
            $sources[$field] = (string) $extra[$field];
        }
    }

    $insert = $pdo->prepare(
        'INSERT INTO map_point_species (user_id, point_id, field, name, genus, source, identifier)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE genus = VALUES(genus), source = VALUES(source), identifier = VALUES(identifier)'
    );

    $count = 0;
    foreach ($sources as $field => $text) {
        foreach (species_entries($text) as $name) {
            $name = mb_substr($name, 0, 190);

            // The reference, if one was picked while typing:
            // "plants:uuid" or "gbif:12345".
            $raw = (string) ($references[$field][$name] ?? '');
            $source = $ident = '';
            if ($raw !== '' && str_contains($raw, ':')) {
                [$source, $ident] = explode(':', $raw, 2);
            }

            $insert->execute([
                $userId, $pointId, $field, $name,
                species_genus($pdo, $name, $source, $ident),
                mb_substr($source, 0, 20), mb_substr($ident, 0, 64),
            ]);
            $count++;
        }
    }

    return $count;
}
