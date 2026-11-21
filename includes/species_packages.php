<?php
/**
 * The name lists as individually downloadable packages.
 *
 * WHY SEPARATE AND NOT ONE PACKAGE
 * Whoever maps only lichens gains nothing from 39,248 fungus names — and
 * whoever roams the local forest even less from 373,000 worldwide ones. The
 * three packages lie two orders of magnitude apart (35 kB, 547 kB, 3.8 MB);
 * combined, every download would cost as much as the most expensive part,
 * and on the phone in the forest that is no small matter. So you choose.
 *
 * WHAT COMES BY ITSELF AND WHAT DOES NOT — measured, and the line runs
 * between the three German lists and GBIF (19.08.2026):
 *
 *   Pilze      39 248 names     2.4 MB raw, around 0.8 over the wire
 *   Flechten    2 767 names     0.2 MB
 *   Pflanzen    5 824 names     0.4 MB
 *   GBIF      373 139 names    20.0 MB raw, around 6.6 over the wire
 *
 * The three together are about a megabyte on the line. Asking about that is
 * more of an imposition than the download: whoever records finds in Germany
 * wants those names, and a question they answer with yes every time is a
 * question not worth asking. They therefore come along and keep themselves
 * current.
 *
 * GBIF COMES ALONG TOO, and for a reason that outweighs its size: it is the
 * reference for synonymy in this application. The cloud folders already
 * file by GBIF's species concept — a synonym goes under the name that is
 * valid today (see cloud.php). If the suggestion list knew a different set
 * of names than the folders, the same find would be called one thing while
 * typing and another on the drive. One source of truth, or none.
 *
 * WHAT IT COSTS, measured rather than guessed: 6.6 MB over the wire, and
 * 54 MB of heap once the search holds its 373 139 names in memory — 151
 * bytes per entry, measured in the browser with exactly the shape species.js
 * builds. On a phone that is a lot but not a wall; the limit measured here
 * was 4 GB, and a mid-range phone still grants several hundred megabytes.
 *
 * SHOULD IT EVER BECOME ONE, there is exactly one honest way out, and it is
 * NOT the obvious one. "Ask GBIF only when the German lists have nothing"
 * suggests itself and is wrong: whoever types a synonym that a German list
 * happens to know something similar to would never get GBIF's answer, and
 * with it not the valid name either. That is not a stopgap, it is the
 * reference quietly ceasing to be one — and a reference that holds most of
 * the time is worth less than none, because nobody can tell the cases apart.
 * (19.08.2026.)
 *
 * The way out is therefore not to ask GBIF less, but to hold it differently:
 * on the device and searchable without lying in memory as a whole — an index
 * by name prefix in IndexedDB instead of one array of 373 139 objects. Same
 * answers, same synonymy, a different drawer. That is real machinery and
 * more than the problem warrants today; it is written down so the next
 * person does not reach for the cheap version.
 *
 * The same holds for switching it off in the management area: it does not
 * merely cost names, it costs the agreement with the cloud folders — offline
 * at least. With a connection the server still answers.
 *
 * All four can still be switched off in the management area, in both
 * directions.
 *
 * Without a package the application keeps searching via the server — exactly
 * as before, just with reception.
 *
 * THE PACKAGES HERE ARE THE ONLY PLACE that states what exists. The listing
 * for the management area, the delivery and the search in the browser all
 * read from here. A new package — mosses, say, or beetles — then needs only
 * one entry.
 *
 * EACH PACKAGE BRINGS ITS OWN QUERIES instead of a table shape being assumed
 * here. The three are in fact built differently: the two Germany lists carry
 * synonym and valid name in one table with sipnr, GBIF references via
 * parent_key and knows neither German names nor threat status. A common
 * shape would have bent GBIF — and the next dataset would bend differently.
 */

declare(strict_types=1);

/**
 * Layout of the delivered data.
 *
 * Sits at the front of every version number. Whoever changes the format
 * bumps it — then the versions stored in the browsers expire instead of
 * being read wrongly.
 *
 * 3 SINCE 21.09.2026, AND THE LAYOUT DID NOT CHANGE: the GBIF names carry
 * their rank marker again (species_gbif_name_sql). A stored package with
 * the bare canonicals is not read wrongly, but it spells the names
 * differently from the server, and a synonym's target no longer matches
 * a row by name once the two are mixed. The row count — the other half
 * of the version — stays the same, so only this number can expire it.
 */
const SPECIES_PACKAGE_FORMAT = 3;

/**
 * What exists.
 *
 * data delivers per row: name, author, german, rl, target. If target is set,
 * the row is a synonym and points to the currently valid name.
 *
 * stats delivers rows and chars — the size is estimated from them, see
 * species_package_rev().
 */
/**
 * The animal groups by which one picks one's field.
 *
 * IN ONE PLACE, and everything derives from it: the packages here, the
 * search branches in species.php and the switches in the species tab.
 * Whoever adds a group changes this table and nothing else — the same rule
 * as for the symbols and icons, and for the same reason: three lists
 * claiming the same thing drift apart.
 *
 * THE CLASSES ARE THE SOURCES' OWN, not our invention. Both animal lists
 * carry them in 'group_name' — Fauna Europaea from its parent chain, the
 * Stuttgart list likewise. Counted across both together (19.08.2026):
 * Insecta 212,648, Arachnida 24,161, Gastropoda 14,026, Aves 12,717,
 * Mammalia 1,915, Reptilia 548.
 *
 * "Übrige" is deliberately a catch basin and not an enumeration: worms,
 * crustaceans, millipedes, rotifers and four dozen further classes,
 * together around 30,000 names. Whoever seriously maps one of them gets a
 * row of their own — until then a list of fifty switches would be
 * unusable.
 *
 * THE PACK FACTOR IS EACH GROUP'S OWN, measured on 24.09.2026 — how much
 * of the counted characters goes over the wire, see species_package_rev().
 * Until then all nine shared one 0.335, the mean of the two lists' old
 * factors, and two groups stood outside the test's ten per cent: the
 * molluscs are half synonyms, and a synonym's author is counted but never
 * delivered — a fifth of their count is air (0.290); the mammals are a
 * small list with hardly any such air (0.378). No other way of counting
 * brings all nine within reach of one factor — the best one tried,
 * counting only what travels plus a price per row, still left groups six
 * per cent off. A new group measures its own with tests/species.php.
 *
 * REVISION 1 (26.09.2026) for the six groups whose Fauna Europaea
 * synonyms lost their „(null)" in migration 0135 — 11,678 names changed,
 * no row came or went, so only this counter tells a device to fetch the
 * package anew (species_package_rev). Reptiles, amphibians and fish had
 * none and keep their edition. The counter went live AFTER the migration
 * had run, in a push of its own: devices refresh four seconds after every
 * map start, and one that fetched the new number with the old names in
 * between would have kept them.
 *
 * REVISION 2, the same evening, for the four whose subspecies got their
 * authors back in migration 0136 — insects, arachnids, molluscs and the
 * other animals; „(Lasiocampa) quercus quercus (Linnaeus, 1758)" became
 * „(Linnaeus, 1758)". Pushed after the migration for the same reason.
 *
 * ONE MORE, still that evening, for every group but the amphibians: where
 * both lists carry a name the Stuttgart row now travels first, and a
 * Fauna Europaea subgenus travels without the author it would lend its
 * genus (the 'data' query below, species_branch() in species.php). No
 * migration this time — the new content comes with the code, so the
 * counter went up in the same push.
 */
const ANIMAL_GROUPS = [
    'insects'    => ['name' => 'Insekten',       'form' => 'insect',   'pack_factor' => 0.335, 'revision' => 3, 'classes' => ['Insecta', 'Entognatha']],
    'arachnids'     => ['name' => 'Spinnentiere',   'form' => 'spider',   'pack_factor' => 0.334, 'revision' => 3, 'classes' => ['Arachnida']],
    'molluscs'  => ['name' => 'Weichtiere',     'form' => 'snail', 'pack_factor' => 0.290, 'revision' => 3, 'classes' => ['Gastropoda', 'Bivalvia', 'Cephalopoda', 'Polyplacophora', 'Scaphopoda']],
    // The bird of prey stands for all birds (25.09.2026: „für
    // die Vögel als Standardsymbol den Greifvogel, nicht den Singvogel");
    // 'bird' stays the songbird a list may still choose (shapes.php).
    'birds'      => ['name' => 'Vögel',          'form' => 'bird_of_prey', 'pack_factor' => 0.344, 'revision' => 2, 'classes' => ['Aves']],
    'mammals' => ['name' => 'Säugetiere',     'form' => 'deer',   'pack_factor' => 0.378, 'revision' => 2, 'classes' => ['Mammalia']],
    'reptiles'   => ['name' => 'Reptilien',      'form' => 'lizard',  'pack_factor' => 0.324, 'revision' => 1, 'classes' => ['Reptilia']],
    'amphibians' => ['name' => 'Amphibien',      'form' => 'frog',    'pack_factor' => 0.337, 'classes' => ['Amphibia']],
    'fish'      => ['name' => 'Fische',         'form' => 'fish',    'pack_factor' => 0.361, 'revision' => 1, 'classes' => ['Actinopterygii', 'Chondrichthyes', 'Osteichthyes', 'Petromyzonti', 'Myxini', 'Sarcopterygii']],
    'other_animals'  => ['name' => 'Übrige Tiere',   'form' => 'worm',     'pack_factor' => 0.357, 'revision' => 3, 'classes' => []],
];

/**
 * Groups that went, and the groups that took their place.
 *
 * 'herptiles' → 'reptiles' + 'amphibians' (24.09.2026:
 * „Amphibien und Reptilien ist schon was anderes"). The group „Reptilien
 * und Lurche" held both classes — they are two, and a herpetologist who
 * maps only amphibians should not drag in every lizard. A device that chose
 * the old group sends it until it has loaded the new code once (species.js
 * translates the stored choice); the search expands it here, so such a
 * device gets both halves instead of nothing.
 */
const SPECIES_GROUP_SUCCESSORS = ['herptiles' => ['reptiles', 'amphibians']];

/** Group keys with retired ones replaced by their successors. */
function species_groups_current(array $groups): array
{
    $out = [];
    foreach ($groups as $group) {
        foreach (SPECIES_GROUP_SUCCESSORS[$group] ?? [$group] as $key) {
            $out[$key] = true;
        }
    }
    return array_keys($out);
}

/**
 * The three non-animal subjects — name and shape as in ANIMAL_GROUPS.
 *
 * THEY USED TO BE SPELLED OUT IN THE SETTINGS TAB while the eight animal
 * ones came from ANIMAL_GROUPS. As long as only the tab needed them,
 * nobody noticed. Since the suggestion list shows the same shapes, it
 * would have been two sets of the same knowledge — and the second one
 * would have sat in JavaScript, where no test reaches it.
 */
const NON_ANIMAL_GROUPS = [
    'fungi'    => ['name' => 'Pilze',    'form' => 'mushroom'],
    'lichens' => ['name' => 'Flechten', 'form' => 'lichen'],
    'plants' => ['name' => 'Pflanzen', 'form' => 'plant'],
];

/** The shape of a subject — the drawing from shapes.php. */
function species_group_shape(string $group_name): string
{
    return NON_ANIMAL_GROUPS[$group_name]['form'] ?? ANIMAL_GROUPS[$group_name]['form'] ?? 'circle';
}

/**
 * Every subject's shape and name, keyed by group — for the browser, which
 * draws a suggestion out of a STORED package without the server (the
 * server's answer carries form and group per hit; the package holds only
 * the source). One table, the same two consts, so a new group appears on
 * both sides at once.
 */
function species_group_shapes(): array
{
    $out = [];
    foreach (NON_ANIMAL_GROUPS + ANIMAL_GROUPS as $key => $group) {
        $out[$key] = ['form' => $group['form'], 'name' => t($group['name'])];
    }
    return $out;
}

/**
 * Every package's origin — the list and its date, as Einstellungen → Arten
 * shows them — keyed by identifier, for the star behind „aktueller Name
 * zu" in the suggestion list (21.09.2026: „aus welcher Liste,
 * und das Datum — einfach den Stand der Datenbank, was wir hier anzeigen").
 */
function species_origins(): array
{
    $out = [];
    foreach (species_packages() as $identifier => $package) {
        $out[$identifier] = $package['origin'];
    }
    return $out;
}

/** The name of a subject, as the user reads it. The German name in the
 *  const is the t() key — the catalogs translate it at this read site. */
function species_group_label(string $group_name): string
{
    return t(NON_ANIMAL_GROUPS[$group_name]['name'] ?? ANIMAL_GROUPS[$group_name]['name'] ?? $group_name);
}

/**
 * German labels for the sources' animal classes.
 *
 * FOR READING ONLY, not for the mapping — that runs over the scientific
 * names in ANIMAL_GROUPS. This is what the species tab enumerates under
 * „Übrige Tiere", so it does not read "Secernentea, Adenophorea,
 * Eurotatoria" there with nobody knowing whether that concerns them
 * (19.08.2026).
 *
 * Where a class is missing, its scientific name stands in — that is honest
 * and at least look-up-able.
 */
const ANIMAL_CLASSES_GERMAN = [
    'Secernentea'     => 'Fadenwürmer',
    'Adenophorea'     => 'Fadenwürmer',
    'Diplopoda'       => 'Doppelfüßer',
    'Chilopoda'       => 'Hundertfüßer',
    'Malacostraca'    => 'Höhere Krebse',
    'Maxillopoda'     => 'Ruderfußkrebse',
    'Ostracoda'       => 'Muschelkrebse',
    'Branchiopoda'    => 'Kiemenfußkrebse',
    'Eurotatoria'     => 'Rädertierchen',
    'Trematoda'       => 'Saugwürmer',
    'Cestoda'         => 'Bandwürmer',
    'Monogenea'       => 'Hakensaugwürmer',
    'Platyhelminthes' => 'Plattwürmer',
    'Oligochaeta'     => 'Wenigborster',
    'Hirudinea'       => 'Egel',
    'Polychaeta'      => 'Vielborster',
    'Eutardigrada'    => 'Bärtierchen',
    'Gastrotricha'    => 'Bauchhärlinge',
    'Anthozoa'        => 'Blumentiere',
    'Hydrozoa'        => 'Hydrozoen',
    'Scyphozoa'       => 'Schirmquallen',
    'Asteroidea'      => 'Seesterne',
    'Echinoidea'      => 'Seeigel',
    'Entognatha'      => 'Sackkiefler',
];

/**
 * What a group really holds — counted, not claimed.
 *
 * For „Übrige Tiere" this is the only answer worth anything: a catch
 * basin that does not name itself is a switch nobody dares to press.
 *
 * @return array<int, array{name: string, total: int}>
 */
function species_group_contents(PDO $pdo, string $key, int $atMost = 8): array
{
    if (!table_exists($pdo, 'map_tax_faeu') || !table_exists($pdo, 'map_tax_animals')) {
        return [];
    }
    $where = species_group_where($key, 't');
    $rows = $pdo->query(
        "SELECT group_name, SUM(n) AS n FROM (
           SELECT group_name, COUNT(*) AS n FROM map_tax_faeu t WHERE $where GROUP BY group_name
           UNION ALL
           SELECT group_name, COUNT(*) FROM map_tax_animals t WHERE $where GROUP BY group_name
         ) x GROUP BY group_name ORDER BY n DESC"
    )->fetchAll();

    // SUMMED BY THE GERMAN NAME, not by the class: the roundworms stand
    // in the sources as Secernentea AND Adenophorea, and „Fadenwürmer"
    // twice in a row looks like a mistake.
    $sum = [];
    foreach ($rows as $r) {
        $class = (string) ($r['group_name'] ?? '');
        if ($class === '') {
            continue;
        }
        // The German label is the t() key; a class without one passes t()
        // unchanged (scientific names are never catalog keys).
        $name = t(ANIMAL_CLASSES_GERMAN[$class] ?? $class);
        $sum[$name] = ($sum[$name] ?? 0) + (int) $r['n'];
    }
    arsort($sum);

    $out = [];
    $rest = 0;
    foreach ($sum as $name => $n) {
        if (count($out) < $atMost) {
            $out[] = ['name' => $name, 'total' => $n];
        } else {
            $rest += $n;
        }
    }
    if ($rest > 0) {
        $out[] = ['name' => t('weitere'), 'total' => $rest];
    }
    return $out;
}

/** The classes falling to a named group — for the rest: all remaining ones. */
function species_group_classes(string $key): array
{
    return ANIMAL_GROUPS[$key]['classes'] ?? [];
}

/** An SQL expression bounding the rows of one animal group. */
function species_group_where(string $key, string $alias = 'a'): string
{
    $own = species_group_classes($key);
    if ($own !== []) {
        return $alias . '.group_name IN (' . implode(', ', array_map(
            static fn(string $k): string => "'" . str_replace("'", "''", $k) . "'", $own
        )) . ')';
    }

    // The catch basin: everything falling to no named group — and
    // expressly the nameless too, for without a class it would otherwise
    // slip through every check and be findable nowhere.
    $named = [];
    foreach (ANIMAL_GROUPS as $g) {
        foreach ($g['classes'] as $k) {
            $named[] = "'" . str_replace("'", "''", $k) . "'";
        }
    }
    return '(' . $alias . '.group_name IS NULL OR ' . $alias . '.group_name NOT IN (' . implode(', ', $named) . '))';
}

/**
 * What GBIF carries in names fit for recording a find. Genera and families
 * are not among them: nobody writes "Cortinarius" into the find log and is
 * done with it. Shared by the package and the count below.
 */
function species_gbif_rank(string $alias = 'g'): string
{
    return "$alias.taxon_rank IN ('species','subspecies','variety','form') AND $alias.canonical IS NOT NULL";
}

function species_packages(): array
{
    // For the DGfM name with its qualifier (species_qualified_name_sql) —
    // inside the function, because species.php includes this file too.
    require_once __DIR__ . '/species.php';
    $gbifRank = species_gbif_rank();

    $packages = [
        'fungi' => [
            'name'        => t('Pilze Deutschlands'),
            'origin'      => t('Referenzliste der Deutschen Gesellschaft für Mykologie'),
            'text'        => t('Groß-, Klein- und Schleimpilze Deutschlands, mit deutschen Namen und Gefährdungseinstufung.'),
            'automatic' => true,
            'pack_factor'  => 0.350,
            // Revision 1: the names carry their sensu qualifier (13.09.2026);
            // revision 2, the same evening: GBIF's names where the link has
            // one — see species_package_rev() for why this counter exists.
            'revision'    => 2,
            /* THE COUNT IS WHAT THE PACKAGE DELIVERS, not the table's rows:
               since 0106 the DGfM name of a renamed species travels as a
               synonym of its GBIF name (one row more per rename), and a
               synonym whose own name IS the GBIF name stays out (one row
               less). tests/species.php holds the version's count against
               the delivered rows, and it must. Cheap: index joins, no
               package built. */
            'stats' => static function (PDO $pdo): string {
                if (!species_links_ready($pdo)) {
                    return "SELECT COUNT(*) AS row_count,
                                   SUM(LENGTH(binomial) + LENGTH(COALESCE(author, ''))
                                     + LENGTH(COALESCE(german_name, ''))
                                     + LENGTH(COALESCE(red_list_de, ''))) AS chars
                              FROM map_tax_dgfm";
                }
                $shown = species_qualified_name_sql('a', 'lka');
                $own   = species_own_name_sql('t');
                return "SELECT (SELECT COUNT(*) FROM map_tax_dgfm)
                             + (SELECT COUNT(*) FROM map_tax_links WHERE gbif_name IS NOT NULL)
                             - (SELECT COUNT(*) FROM map_tax_dgfm t
                                  JOIN map_tax_dgfm a ON a.taxnr = t.sipnr
                                  LEFT JOIN map_tax_links lka ON lka.taxnr = a.taxnr
                                 WHERE t.taxnr <> t.sipnr AND $own = $shown) AS row_count,
                               (SELECT SUM(LENGTH(binomial) + LENGTH(COALESCE(author, ''))
                                     + LENGTH(COALESCE(german_name, ''))
                                     + LENGTH(COALESCE(red_list_de, ''))) FROM map_tax_dgfm) AS chars";
            },
            // The name with its sensu qualifier (species_qualifier_sql):
            // „Xerocomellus chrysenteron agg." and „… s. str." are two
            // rows, and delivered as two names they stay two.
            'data' => static function (PDO $pdo): string {
                /* GBIF's name where the link has one (0106): a valid row is
                   delivered under it, a synonym points at it — and the
                   DGfM's own name of a renamed species travels as a
                   synonym of its GBIF name, so „Boletus torosus" still
                   finds „Imperator torosus" offline. A synonym whose own
                   name IS the GBIF name adds nothing and stays out. */
                $link  = species_links_ready($pdo) ? 'lka' : '';
                $joins = $link === '' ? '' : ' LEFT JOIN map_tax_links lka ON lka.taxnr = a.taxnr
                                               LEFT JOIN map_tax_gbif gba ON gba.gbif_key = lka.gbif_species_key';
                $shown = species_qualified_name_sql('a', $link);
                $own   = species_own_name_sql('t');
                // The author belongs to the name: GBIF's where the name is GBIF's.
                $author = $link === '' ? 't.author'
                    : 'CASE WHEN t.taxnr = t.sipnr AND lka.gbif_name IS NOT NULL THEN gba.author ELSE t.author END';
                return "SELECT name, author, german, rl, target FROM (
                          SELECT CASE WHEN t.taxnr = t.sipnr THEN $shown ELSE $own END AS name,
                                 $author AS author, t.german_name AS german, t.red_list_de AS rl,
                                 CASE WHEN t.taxnr <> t.sipnr THEN $shown END AS target,
                                 t.binomial AS sort_name
                            FROM map_tax_dgfm t
                            JOIN map_tax_dgfm a ON a.taxnr = t.sipnr {$joins}
                          UNION ALL
                          SELECT " . species_own_name_sql('a') . ", NULL, NULL, NULL, $shown, a.binomial
                            FROM map_tax_dgfm a {$joins}
                           WHERE a.taxnr = a.sipnr AND lka.gbif_name IS NOT NULL
                        ) x
                        WHERE target IS NULL OR target <> name
                        ORDER BY sort_name";
            },
        ],
        'lichens' => [
            'name'        => t('Flechten Deutschlands'),
            'origin'      => t('Rote Liste der Flechten Deutschlands (Wirth u. a. 2011), BfN; deutsche Namen aus GBIF'),
            'text'        => t('Flechten, flechtenbewohnende und flechtenähnliche Pilze Deutschlands, mit Gefährdungseinstufung. Deutsche Namen gibt es nur für einen Teil der Arten — für Flechten sind kaum welche üblich.'),
            'automatic' => true,
            // Revision 1 (25.09.2026): the German names from GBIF, written
            // into the SAME rows by migration 0132 — the row count stays, so
            // only this counter tells a device to fetch the package anew
            // (admin/lichens_german.php made the migration).
            'revision'    => 1,
            'pack_factor'  => 0.352,
            'stats' => "SELECT COUNT(*) AS row_count,
                               SUM(LENGTH(binomial) + LENGTH(COALESCE(author, ''))
                                 + LENGTH(COALESCE(german_name, ''))
                                 + LENGTH(COALESCE(red_list_de, ''))) AS chars
                          FROM map_tax_lichens",
            'data' => "SELECT t.binomial AS name, t.author, t.german_name AS german,
                               t.red_list_de AS rl,
                               CASE WHEN t.taxnr <> t.sipnr THEN a.binomial END AS target
                          FROM map_tax_lichens t
                          JOIN map_tax_lichens a ON a.taxnr = t.sipnr
                         ORDER BY t.binomial",
        ],
        'plants' => [
            'name'        => t('Pflanzen Deutschlands'),
            'origin'      => t('Rote Liste der Farn- und Blütenpflanzen Deutschlands (Metzing u. a. 2018), BfN'),
            // NO MENTION OF FUNGI HERE, and that is the map's rule, not a
            // matter of taste: it carries no subject in names, placeholders
            // or defaults. "Host plant, mycorrhizal partner, accompanying
            // vegetation" stood here and made plants into an accessory of
            // somebody else's hobby. Whoever wants to map plants maps
            // plants (19.08.2026).
            'text'        => t('Bäume, Sträucher, Kräuter und Farne Deutschlands — mit deutschen Namen für 95 % der Arten.'),
            'automatic' => true,
            // Revision 1 (26.09.2026): the aggregates, sensu qualifiers,
            // subgenera and hybrids that stood in the author now stand in
            // the name (migration 0137) — same rows, other names, so only
            // this counter tells a device to fetch the package anew. Pushed
            // after the migration had run, for the reason ANIMAL_GROUPS gives.
            'revision'    => 1,
            'pack_factor'  => 0.336,
            'stats' => "SELECT COUNT(*) AS row_count,
                               SUM(LENGTH(binomial) + LENGTH(COALESCE(author, ''))
                                 + LENGTH(COALESCE(german_name, ''))
                                 + LENGTH(COALESCE(red_list_de, ''))) AS chars
                          FROM map_tax_plants",
            'data' => "SELECT t.binomial AS name, t.author, t.german_name AS german,
                               t.red_list_de AS rl,
                               CASE WHEN t.taxnr <> t.sipnr THEN a.binomial END AS target
                          FROM map_tax_plants t
                          JOIN map_tax_plants a ON a.taxnr = t.sipnr
                         ORDER BY t.binomial",
        ],
        'gbif' => [
            'name'        => t('Pilznamen weltweit (GBIF)'),
            'origin'      => t('GBIF-Backbone, Stand 28.08.2023'),
            'text'        => t('Alle Pilznamen der Welt samt Synonymen — für Funde, die in keiner Deutschlandliste stehen, und für alte Namen, unter denen man eine Art noch kennt. Ohne deutsche Namen und ohne Gefährdungseinstufung; die stehen in den Deutschlandlisten.'),
            'automatic' => true,
            'pack_factor'  => 0.333,
            'stats' => "SELECT COUNT(*) AS row_count,
                               SUM(LENGTH(g.canonical) + LENGTH(COALESCE(g.author, ''))) AS chars
                          FROM map_tax_gbif g
                         WHERE $gbifRank",
            // The synonym points to the valid name via parent_key. The
            // LEFT JOIN is allowed: if it points into the void or at a rank
            // above species, target stays empty and the row stands as an
            // ordinary name — better than a reference nobody can follow.
            // The name with its rank marker put back (species_gbif_name_sql),
            // for the target as well — the browser resolves a target by name.
            'data' => "SELECT " . species_gbif_name_sql('g') . " AS name, g.author, NULL AS german, NULL AS rl,
                               CASE WHEN g.is_synonym = 1 AND a.is_synonym = 0
                                         AND a.taxon_rank IN ('species','subspecies','variety','form')
                                    THEN " . species_gbif_name_sql('a') . " END AS target
                          FROM map_tax_gbif g
                          LEFT JOIN map_tax_gbif a ON a.gbif_key = g.parent_key
                         WHERE $gbifRank
                         ORDER BY g.canonical",
        ],
    ];

    /* THE ANIMAL GROUPS ARE GENERATED, not written out. Nine groups
       times two sources would be eighteen nearly identical queries — and at
       the first change, eighteen places of which one catches sixteen.

       EVERY GROUP DRAWS FROM BOTH LISTS: the Stuttgart one brings the
       German names, Fauna Europaea the completeness. The sorting is such
       that for the same name the row WITH a German name comes first — when
       merging, the browser keeps the first (see 'seen' in species.js), and
       so the German name wins without a precedence list having to be
       maintained anywhere. */
    foreach (ANIMAL_GROUPS as $key => $group_name) {
        $where = species_group_where($key, 't');

        $packages[$key] = [
            'name'        => t($group_name['name']),
            'origin'      => t('Museum für Naturkunde Stuttgart (CC-BY 4.0) und Fauna Europaea, DOI 10.15468/ymk1bx (CC-BY-SA 4.0)'),
            'text'        => t('Wissenschaftliche Namen aus Europa, deutsche Namen wo es welche gibt.'),
            'automatic' => true,
            // The group's own, measured (ANIMAL_GROUPS); 0.335 is a first
            // guess for a new one until the test has measured it.
            'pack_factor'  => $group_name['pack_factor'] ?? 0.335,
            // Counted up where what travels changed without the row count
            // moving (ANIMAL_GROUPS says when and why).
            'revision'    => $group_name['revision'] ?? 0,
            'stats' => "SELECT SUM(row_count) AS row_count, SUM(chars) AS chars FROM (
                          SELECT COUNT(*) AS row_count,
                                 SUM(LENGTH(binomial) + LENGTH(COALESCE(author, ''))
                                   + LENGTH(COALESCE(german_name, ''))) AS chars
                            FROM map_tax_animals t WHERE $where
                          UNION ALL
                          SELECT COUNT(*),
                                 SUM(LENGTH(binomial) + LENGTH(COALESCE(
                                     CASE WHEN taxon_rank = 'subgenus' THEN NULL ELSE author END, '')))
                            FROM map_tax_faeu t WHERE $where
                        ) x",
            /* THE STUTTGART ROW FIRST where both lists carry a name, and a
               Fauna Europaea subgenus without its author (26.09.2026). The
               browser keeps a name's first valid row, and among rows
               without a German name the order was chance — so a genus
               showed Fauna Europaea's author on one device and none on the
               next, while the server followed the newer list. And a
               subgenus sits under its genus's bare name there, so its
               author would pass for the genus's; species_branch() in
               species.php says the rest. */
            'data' => "SELECT name, author, german, rl, target FROM (
                          SELECT t.binomial AS name, t.author, t.german_name AS german,
                                 NULL AS rl,
                                 CASE WHEN t.taxnr <> t.sipnr THEN a.binomial END AS target,
                                 0 AS from_faeu
                            FROM map_tax_animals t
                            JOIN map_tax_animals a ON a.taxnr = t.sipnr
                           WHERE $where
                          UNION ALL
                          SELECT t.binomial, CASE WHEN t.taxon_rank = 'subgenus' THEN NULL ELSE t.author END,
                                 NULL, NULL,
                                 CASE WHEN t.taxnr <> t.sipnr THEN a.binomial END,
                                 1
                            FROM map_tax_faeu t
                            JOIN map_tax_faeu a ON a.taxnr = t.sipnr
                           WHERE $where
                        ) x
                        ORDER BY name, (german IS NULL), from_faeu",
        ];
    }

    return $packages;
}

/*
 * WHAT EACH SUBJECT HOLDS — COUNTED, NOT CLAIMED (13.09.2026:
 * „bitte noch eine Anzahl, wie viel wissenschaftliche Namen, wie viel
 * Synonyme und wie viel deutsche Namen jeweils in der Liste ist"). Per
 * source list, because a subject may draw from two — fungi from the DGfM
 * list and GBIF, every animal group from Stuttgart and Fauna Europaea —
 * and the two overlap: summed, the same species would count twice, and a
 * figure that counts twice says nothing.
 *
 * A NAME is a valid one (its own sipnr, or not a synonym at GBIF), a
 * SYNONYM points at one, and a GERMAN NAME is counted on the valid rows
 * only — the German lists hang it on the synonym rows as well, and it is
 * one name per species, not one per spelling.
 *
 * CACHED IN A FILE, keyed by the tables' row counts. Measured (13.09.2026):
 * the sums over GBIF's 960 000 rows take 380 ms here, and the settings
 * tab is not the place to pay that on every opening; the six COUNT(*)
 * that key the cache take a tenth of it. The row count is the same
 * version the packages go by (species_package_rev): the tables are only
 * ever replaced as a whole. Whatever cannot be written is simply
 * recounted next time — a missing cache costs time, never a wrong number.
 *
 * AND KEYED BY THE GROUPS THEMSELVES. The figures are per subject, and a
 * subject can change without any table changing: on 24.09.2026
 * „Reptilien und Lurche" split into reptiles and amphibians. Keyed by
 * row counts alone, the cache went on answering with the old subjects,
 * and the two new switches stood there without figures — until some list
 * was reimported, which may be months.
 *
 * @return array<string, list<array{source: string, names: int, synonyms: int, german: int}>>
 *         per subject key (the same keys the switches carry), one entry
 *         per source list, in the order the subject names them
 */
function species_subject_counts(PDO $pdo): array
{
    $tables = ['map_tax_dgfm', 'map_tax_lichens', 'map_tax_plants', 'map_tax_gbif',
               'map_tax_animals', 'map_tax_faeu'];
    $key = [];
    foreach ($tables as $t) {
        $key[$t] = table_exists($pdo, $t) ? (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() : -1;
    }
    $key['groups'] = md5(json_encode(array_map(static fn(array $g): array => $g['classes'], ANIMAL_GROUPS)));
    /* AND BY THE PACKAGES' REVISIONS (25.09.2026). The lichens' German
       names came as UPDATEs — same rows, same count —, and the cache,
       keyed by row counts alone, went on saying „no German names". A
       revision is exactly the mark for „what is delivered changed without
       the table growing", so it keys the figures as well. */
    $key['revisions'] = array_map(static fn(array $p): int => (int) ($p['revision'] ?? 0), species_packages());

    $file = PROJECT_PATH . '/data/species_counts.json';
    $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (is_array($cached) && ($cached['key'] ?? null) === $key && is_array($cached['subjects'] ?? null)) {
        return $cached['subjects'];
    }

    $german = static function (string $table, string $source) use ($pdo, $key): ?array {
        if ($key[$table] < 0) {
            return null;
        }
        $z = $pdo->query(
            "SELECT SUM(taxnr = sipnr) AS names, SUM(taxnr <> sipnr) AS synonyms,
                    SUM(taxnr = sipnr AND german_name <> '') AS german
               FROM $table")->fetch();
        return ['source' => $source, 'names' => (int) $z['names'],
                'synonyms' => (int) $z['synonyms'], 'german' => (int) $z['german']];
    };

    $subjects = [];
    $subjects['fungi'] = array_values(array_filter([$german('map_tax_dgfm', t('DGfM'))]));
    if ($key['map_tax_gbif'] >= 0) {
        $z = $pdo->query(
            'SELECT SUM(g.is_synonym = 0) AS names, SUM(g.is_synonym = 1) AS synonyms
               FROM map_tax_gbif g WHERE ' . species_gbif_rank())->fetch();
        $subjects['fungi'][] = ['source' => t('GBIF'), 'names' => (int) $z['names'],
                                'synonyms' => (int) $z['synonyms'], 'german' => 0];
    }
    $subjects['lichens'] = array_values(array_filter([$german('map_tax_lichens', t('BfN'))]));
    $subjects['plants']  = array_values(array_filter([$german('map_tax_plants', t('BfN'))]));

    /* THE ANIMALS IN TWO PASSES, NOT SIXTEEN: one GROUP BY per source
       table gives every class its three figures, and the groups are
       summed from the classes here — the same membership
       species_group_where() writes into SQL, read the other way round. */
    $byClass = [];
    foreach (['map_tax_animals' => t('Stuttgart'), 'map_tax_faeu' => t('Fauna Europaea')] as $table => $source) {
        if ($key[$table] < 0) {
            continue;
        }
        $rows = $pdo->query(
            "SELECT group_name, SUM(taxnr = sipnr) AS names, SUM(taxnr <> sipnr) AS synonyms,
                    SUM(taxnr = sipnr AND german_name <> '') AS german
               FROM $table GROUP BY group_name")->fetchAll();
        foreach ($rows as $r) {
            $byClass[$table][(string) ($r['group_name'] ?? '')] = $r;
        }
        $byClass[$table]['__source'] = $source;
    }
    $named = [];
    foreach (ANIMAL_GROUPS as $g) {
        foreach ($g['classes'] as $class) {
            $named[$class] = true;
        }
    }
    foreach (ANIMAL_GROUPS as $groupKey => $group) {
        $subjects[$groupKey] = [];
        foreach ($byClass as $table => $classes) {
            $sum = ['source' => $classes['__source'], 'names' => 0, 'synonyms' => 0, 'german' => 0];
            foreach ($classes as $class => $r) {
                if ($class === '__source') {
                    continue;
                }
                // A named group takes its classes; the catch basin takes
                // every class no named group claims, the nameless included.
                $mine = $group['classes'] !== []
                    ? in_array($class, $group['classes'], true)
                    : !isset($named[$class]);
                if (!$mine) {
                    continue;
                }
                $sum['names']    += (int) $r['names'];
                $sum['synonyms'] += (int) $r['synonyms'];
                $sum['german']   += (int) $r['german'];
            }
            $subjects[$groupKey][] = $sum;
        }
    }

    @file_put_contents($file, json_encode(['key' => $key, 'subjects' => $subjects], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $subjects;
}

/**
 * The three figures as one line — „23.440 wissenschaftliche Namen ·
 * 15.808 Synonyme · 5.923 deutsche Namen"; a list that carries none of
 * a kind leaves that part out, and a list with nothing at all says so.
 */
function species_counts_line(array $c): string
{
    if ($c['names'] + $c['synonyms'] === 0) {
        return t('Für diese Gruppe gibt es noch keine Namen.');
    }
    $n = static fn(int $v): string => number_format($v, 0, ',', '.');
    $parts = [t('%s wissenschaftliche Namen', $n($c['names']))];
    if ($c['synonyms'] > 0) {
        $parts[] = t('%s Synonyme', $n($c['synonyms']));
    }
    if ($c['german'] > 0) {
        $parts[] = t('%s deutsche Namen', $n($c['german']));
    }
    return implode(' · ', $parts);
}

/** One package, or null. The key comes from outside and is never inserted into SQL. */
function species_package(string $identifier): ?array
{
    return species_packages()[$identifier] ?? null;
}

function species_package_or_error(string $identifier): array
{
    $p = species_package($identifier);
    if ($p === null) {
        throw new InvalidArgumentException(t('Unbekanntes Paket: %s', $identifier));
    }
    return $p;
}

/**
 * State of a package: version, row count, estimated size.
 *
 * The row count suffices as a version identifier: the tables are only ever
 * replaced as a whole — the import scripts empty them and refill them — and
 * a different list has a different length. WITH ONE ADDITION (13.09.2026):
 * a package's own 'revision', counted up when what is DELIVERED changes
 * without the table changing — the fungi package began delivering names
 * with their sensu qualifier that day, from the same 39 248 rows, and a
 * device holding the old copy would have kept the two Xerocomellus
 * chrysenteron as one for ever. Per package, not the format: the format
 * would fetch GBIF's seven megabytes onto every phone for a change that
 * touched 800 kB.
 *
 * THE SIZE IS ESTIMATED, from the length of the names times a measured
 * factor. The detour pays off, because the exact number would mean building
 * the whole package and throwing it away again — for GBIF that is 21 MB and
 * two and a half seconds, just to label one row in a list. How well the
 * estimate hits is checked by tests/species.php against the real delivery.
 *
 * @return array{version: string, rows: int, bytes: int}
 */
function species_package_rev(PDO $pdo, string $identifier): array
{
    $package = species_package_or_error($identifier);
    $stats = is_callable($package['stats']) ? ($package['stats'])($pdo) : $package['stats'];
    $z = $pdo->query($stats)->fetch();

    return [
        'version' => SPECIES_PACKAGE_FORMAT . '-' . (int) $z['row_count']
            . (($package['revision'] ?? 0) > 0 ? '-r' . (int) $package['revision'] : ''),
        'rows'  => (int) $z['row_count'],
        'bytes'   => (int) round((int) $z['chars'] * $package['pack_factor']),
    ];
}

/**
 * The contents of a package, row by row.
 *
 * KEPT TERSE, because every field label would otherwise travel along a
 * hundred thousand times. So instead of objects, fields in fixed order:
 *
 *   valid name  [name, author, German name, Red List]
 *   synonym     [name, null, null, null, valid name]
 *
 * Compared with named fields that saves a good half.
 *
 * With GBIF the synonyms are the majority of the rows and exactly what makes
 * a name search usable: whoever knows a species under an old name still
 * lands at the currently valid one.
 *
 * @return Generator<list<string|null>>
 */
function species_package_rows(PDO $pdo, string $identifier): Generator
{
    $package = species_package_or_error($identifier);

    // The fungi package writes its query with the database in hand (the
    // link column may not exist yet); the others are plain strings.
    $sql = is_callable($package['data']) ? ($package['data'])($pdo) : $package['data'];
    foreach ($pdo->query($sql) as $z) {
        yield $z['target'] !== null && $z['target'] !== ''
            ? [$z['name'], null, null, null, $z['target']]
            : [$z['name'], $z['author'] ?: null, $z['german'] ?: null, $z['rl'] ?: null];
    }
}

/**
 * Deliver a package — in chunks and compressed.
 *
 * TWO REASONS WHY THIS CANNOT SIMPLY BE json_response():
 *
 *   MEMORY  Building the GBIF package as a PHP array bursts 128 MB, and
 *           128 MB is exactly what a shared server provides. Emitted row by
 *           row it stays at 30 MB.
 *
 *   SIZE    Without compressing ourselves, 21 MB go over the wire instead of
 *           3.8. Whether the server compresses on its own depends on its
 *           configuration — measured: here it does not. A size figure in the
 *           management area that depends on a server setting we cannot see
 *           would be guessed, not known.
 */
function species_package_deliver(PDO $pdo, string $identifier, string $revision, bool $compress): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Vary: Accept-Encoding');

    $gz = null;
    if ($compress) {
        header('Content-Encoding: gzip');
        $gz = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
    }

    // Whatever else the server buffers has to go — otherwise everything ends
    // up sitting in memory at once again after all.
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    $buffer = '{"package":' . json_encode($identifier)
            . ',"version":' . json_encode($revision)
            . ',"species":[';
    $first = true;

    $emit = static function (string $chunk, bool $final = false) use (&$gz): void {
        echo $gz === null
            ? $chunk
            : deflate_add($gz, $chunk, $final ? ZLIB_FINISH : ZLIB_NO_FLUSH);
    };

    foreach (species_package_rows($pdo, $identifier) as $row) {
        $buffer .= ($first ? '' : ',') . json_encode($row, JSON_UNESCAPED_UNICODE);
        $first = false;

        // One megabyte at a time: small enough for memory, big enough that
        // compressing pays off.
        if (strlen($buffer) > 1 << 20) {
            $emit($buffer);
            $buffer = '';
        }
    }

    $emit($buffer . ']}', true);
}
