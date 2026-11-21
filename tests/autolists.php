<?php
/**
 * Tests the auto lists (migration 0052) — lists that follow a saved
 * Organisieren filter.
 *
 *   php tests/autolists.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE FILTER COMPUTES LIKE organize.js. autolist_matches is the server half
 *   of matches() — sources as branches, search in the title, time range,
 *   undated. If the two drift apart, Organisieren shows a different set than the
 *   list afterwards carries.
 *
 *   THE CATCH-UP. Every point change (create, change, restore) must update
 *   the membership — in when the filter matches, out when it no longer
 *   does. And with a stand bump, otherwise no device learns of it.
 *
 *   THE OHNE STABILITY. "ohne" means "in no ordinary list" — the auto list
 *   itself does not count. Otherwise an auto list with an ohne source
 *   would take its points back out on the next computation and pump
 *   forever.
 *
 *   THE SIEVES. Filling by hand is impossible (point_lists_set), and a
 *   save WITHOUT the auto list does not strip the membership off.
 *   No sublists, no moving into a branch, no merging.
 *
 *   SOURCE GONE, TIME RANGE, RELOCATION. Deleting a source list and moving
 *   within the tree recompute the contents.
 *
 *   THE FIND NUMBER. point_haystack() takes it in, but only what the two
 *   selects hand it — and for a month they handed it none, so „CW-2026"
 *   matched in the browser and not here. Walked on both paths, in full
 *   and per point.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/find_number.php';
require_once __DIR__ . '/../includes/organize_content.php';
require_once __DIR__ . '/../includes/share_content.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!autolists_ready($pdo)) {
    echo "Migration 0052 missing — without the column there is nothing to check.\n";
    exit(1);
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-auto@pruefung.local', '', 'PRUEF', 'Auto', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

try {
    $listsOf = static function (int $pointId) use ($pdo): array {
        $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ? ORDER BY list_id');
        $stmt->execute([$pointId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    };

    // ONE SOURCE LIST SINCE THE MAPPEN (0168): it once was a branch, Pilze
    // with Röhrlinge below; both names stay for the one list.
    $boletes   = list_create($pdo, $userId, ['name' => 'PRUEF Pilze']);
    $mushrooms = $boletes;
    $lichens   = list_create($pdo, $userId, ['name' => 'PRUEF Flechten']);

    // Berlin, not Hesse: no DGM1 fetch that slows the test down.
    $boletus = point_create($pdo, $userId, ['lat' => 52.51, 'lon' => 13.40,
        'title' => 'Boletus edulis', 'lists' => [$boletes], 'found_at' => '2026-08-01 10:00:00']);
    $amanita = point_create($pdo, $userId, ['lat' => 52.52, 'lon' => 13.41,
        'title' => 'Amanita muscaria', 'lists' => [$boletes], 'found_at' => '2025-05-01 09:00:00']);
    $usnea   = point_create($pdo, $userId, ['lat' => 52.53, 'lon' => 13.42,
        'title' => 'Usnea filipendula', 'lists' => [$lichens], 'found_at' => '2026-08-02 12:00:00']);

    echo "Creation and initial filling:\n";
    [$autoId, $n] = autolist_create($pdo, $userId, 'PRUEF Auto 2026', organize_autofilter($pdo, $userId, [
        'list' => (string) $mushrooms, 'search' => '', 'from' => '2026-01-01', 'to' => '', 'no_time' => '',
    ]));
    check($n === 1 && $listsOf($boletus) === [$boletes, $autoId],
        'the source list counts, the time range sieves — only the 2026 bolete is in');
    check(!in_array($autoId, $listsOf($amanita), true),
        'the 2025 one stays out');
    check(!in_array($autoId, $listsOf($usnea), true),
        'the lichen too — wrong list');

    echo "Catching up on point changes:\n";
    point_update($pdo, $userId, $amanita, ['found_at' => '2026-06-01 09:00:00']);
    check(in_array($autoId, $listsOf($amanita), true),
        'if the find time moves into the filter window, the point comes in');
    $stateBefore = $pdo->query("SELECT touched_at FROM map_points WHERE id = $usnea")->fetchColumn();
    point_update($pdo, $userId, $usnea, ['lists' => [$boletes]]);
    check(in_array($autoId, $listsOf($usnea), true),
        'if the point switches into a source list, it comes in');
    check($pdo->query("SELECT touched_at FROM map_points WHERE id = $usnea")->fetchColumn() !== $stateBefore,
        'and its stand advances — otherwise no device would learn of it');
    point_update($pdo, $userId, $usnea, ['lists' => [$lichens]]);
    check(!in_array($autoId, $listsOf($usnea), true),
        'if it switches back out, the membership vanishes too');

    $newPoint = point_create($pdo, $userId, ['lat' => 52.54, 'lon' => 13.43,
        'title' => 'Suillus grevillei', 'lists' => [$boletes], 'found_at' => '2026-08-10 08:00:00']);
    check(in_array($autoId, $listsOf($newPoint), true),
        'a NEW point falls into the filter at once — the everyday path of every import');

    point_delete($pdo, $userId, $newPoint);
    point_restore($pdo, $userId, $newPoint);
    check(in_array($autoId, $listsOf($newPoint), true),
        'trash and back — the membership is right again afterwards');

    echo "Filling by hand is impossible:\n";
    check(count(organize_lists_apply($pdo, $userId, [$usnea], [$autoId], [])) === 0
          && !in_array($autoId, $listsOf($usnea), true),
        'the list dialog bounces off — an auto list is no target');
    point_update($pdo, $userId, $boletus, ['lists' => [$boletes, $lichens]]);
    check(in_array($autoId, $listsOf($boletus), true),
        'a save WITHOUT the auto list does not strip the membership off (point_lists_set preserves it)');
    check(count(organize_lists_apply($pdo, $userId, [$boletus], [], [$autoId, $lichens])) === 1
          && in_array($autoId, $listsOf($boletus), true),
        'removal does not touch it either — only the ordinary list goes');

    echo "The term rows compute like matchTerms in filter.js:\n";
    // "a~" means contains, "n~" does not contain — the same string that
    // writeTerms() builds on the other side.
    [$searchAuto] = autolist_create($pdo, $userId, 'PRUEF Boletus-Suche', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => 'a~boletus', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    check(in_array($searchAuto, $listsOf($boletus), true)
          && !in_array($searchAuto, $listsOf($amanita), true),
        'contains matches, case-insensitively — and empty sources mean "all points"');

    // DOES NOT CONTAIN, the new option: everything except the bolete.
    [$withoutBoletus] = autolist_create($pdo, $userId, 'PRUEF Ohne Boletus', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => 'n~boletus', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    check(!in_array($withoutBoletus, $listsOf($boletus), true)
          && in_array($withoutBoletus, $listsOf($amanita), true),
        'does-not-contain inverts the condition');

    // TWO ROWS APPLY AND-COMBINED.
    [$both] = autolist_create($pdo, $userId, 'PRUEF Beides', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => 'a~us|n~boletus', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    check(!in_array($both, $listsOf($boletus), true)
          && in_array($both, $listsOf($amanita), true),
        'both rows must match — "us" yes, "boletus" no');

    // COMMA MEANS OR WITHIN A ROW — the same split matchTerms makes in the
    // browser; whoever changes one side changes both (tests/filter.mjs).
    [$either] = autolist_create($pdo, $userId, 'PRUEF Oder', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => 'a~amanita, usnea', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    check(in_array($either, $listsOf($amanita), true)
          && in_array($either, $listsOf($usnea), true)
          && !in_array($either, $listsOf($boletus), true),
        'comma is OR: one alternative suffices, whoever carries neither stays out');
    [$neither] = autolist_create($pdo, $userId, 'PRUEF Keines', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => 'n~amanita, usnea', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    check(!in_array($neither, $listsOf($amanita), true)
          && !in_array($neither, $listsOf($usnea), true)
          && in_array($neither, $listsOf($boletus), true),
        'does-not-contain with comma: none of the alternatives may appear');
    list_delete($pdo, $userId, $either);
    list_delete($pdo, $userId, $neither);

    // THE DRAWN AREAS — the place filter, computed here by
    // point_in_polygon exactly as areaMatches/inOutline compute it in the
    // browser. The square encloses only the bolete at 52.51/13.40.
    $square = json_encode([[[52.505, 13.395], [52.515, 13.395],
                            [52.515, 13.405], [52.505, 13.405]]]);
    [$inArea] = autolist_create($pdo, $userId, 'PRUEF Ort', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => '', 'from' => '', 'to' => '', 'no_time' => '',
        'areas' => $square,
    ]));
    check(in_array($inArea, $listsOf($boletus), true)
          && !in_array($inArea, $listsOf($amanita), true)
          && !in_array($inArea, $listsOf($usnea), true),
        'the drawn area keeps only what lies inside it');
    list_delete($pdo, $userId, $inArea);

    // REGION AND MAP SHEET — the place's other answers (mtbMatches /
    // regionMatches in filter.js are the counterparts). The test points
    // carry no region (no lookup runs here), so a set region condition
    // must exclude them all: whoever cannot answer "where?" does not
    // answer "in". The sheet is real, computed at creation (mtb.php):
    // 3446/344 and /342 for the boletes, /413 for the lichen.
    [$inRegion] = autolist_create($pdo, $userId, 'PRUEF Region', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => '', 'from' => '', 'to' => '', 'no_time' => '',
        'regions' => 'THU',
    ]));
    check(!in_array($inRegion, $listsOf($boletus), true)
          && !in_array($inRegion, $listsOf($usnea), true),
        'a region condition excludes points whose region is not known');
    [$inSheet] = autolist_create($pdo, $userId, 'PRUEF Blatt', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => '', 'from' => '', 'to' => '', 'no_time' => '',
        'mtb' => '3446 / 34',
    ]));
    check(in_array($inSheet, $listsOf($boletus), true)
          && in_array($inSheet, $listsOf($amanita), true)
          && !in_array($inSheet, $listsOf($usnea), true),
        'the quarter prefix takes its sixteenths, leaves the other quarter out — whitespace does not count');
    list_delete($pdo, $userId, $inRegion);
    list_delete($pdo, $userId, $inSheet);

    // ACROSS ALL FIELDS, not just the title: the search term sits in the
    // NOTIZ. Exactly that is what the old Organisieren search could not do.
    point_update($pdo, $userId, $usnea, ['note' => 'PRUEF Fichtenstumpf am Wegrand']);
    [$noteAuto] = autolist_create($pdo, $userId, 'PRUEF Notizsuche', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => 'a~fichtenstumpf', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    check(in_array($noteAuto, $listsOf($usnea), true),
        'the term sits in the NOTIZ and is found nonetheless — the haystack counts');
    check(!in_array($noteAuto, $listsOf($boletus), true),
        'and whoever has it nowhere stays out');
    list_delete($pdo, $userId, $withoutBoletus);
    list_delete($pdo, $userId, $both);
    list_delete($pdo, $userId, $noteAuto);
    $noTimePoint = point_create($pdo, $userId, ['lat' => 52.56, 'lon' => 13.45,
        'title' => 'PRUEF Boletus ohne Zeit', 'lists' => [], 'found_at' => null]);
    [$timelessAuto] = autolist_create($pdo, $userId, 'PRUEF Zeitlos', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => '', 'from' => '2020-01-01', 'to' => '', 'no_time' => '1',
    ]));
    check(in_array($timelessAuto, $listsOf($noTimePoint), true)
          && !in_array($timelessAuto, $listsOf($boletus), true),
        'no_time displaces the time range — exactly the backfill list the checkbox exists for');
    list_delete($pdo, $userId, $searchAuto);
    list_delete($pdo, $userId, $timelessAuto);
    point_delete($pdo, $userId, $noTimePoint);

    /* THE FIND NUMBER, ON BOTH PATHS (26.09.2026). point_haystack() takes
       the stored number in, or the „Y-" mark where none is stored — but
       only what the select hands it. autolists_compute named no
       find_number, so every find wore its mark even with a number stored;
       autolists_refresh named not even the id, so a find had no number at
       all. The browser saw the number the whole time. autolist_create
       walks the full path, point_create and point_update the per-point
       one. */
    echo "The find number counts as in the browser:\n";
    $numbered = point_create($pdo, $userId, ['lat' => 52.57, 'lon' => 13.46,
        'title' => 'PRUEF Mit Nummer', 'lists' => [], 'find_number' => 'PRUEF-2026-0042']);
    $unnumbered = point_create($pdo, $userId, ['lat' => 52.58, 'lon' => 13.47,
        'title' => 'PRUEF Ohne Nummer', 'lists' => []]);
    [$numberAuto] = autolist_create($pdo, $userId, 'PRUEF Fundnummer', organize_autofilter($pdo, $userId, [
        'list' => '', 'search' => 'a~PRUEF-2026', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    check(in_array($numberAuto, $listsOf($numbered), true)
          && !in_array($numberAuto, $listsOf($unnumbered), true),
        'the stored number is found — „PRUEF-2026" takes PRUEF-2026-0042 and nothing else');

    // Both marks as alternatives of ONE row: the unnumbered find must come
    // in by its own, the numbered one must not — its number replaces the
    // mark, exactly as in pointHaystack().
    [$markAuto] = autolist_create($pdo, $userId, 'PRUEF Kennung', organize_autofilter($pdo, $userId, [
        'list' => '', 'from' => '', 'to' => '', 'no_time' => '',
        'search' => 'a~' . find_number_fallback($unnumbered) . ', ' . find_number_fallback($numbered),
    ]));
    check(in_array($markAuto, $listsOf($unnumbered), true),
        'a find without a stored number is found by its „Y-" mark');
    check(!in_array($markAuto, $listsOf($numbered), true),
        'a find with one is not — the number replaces the mark instead of standing beside it');

    point_update($pdo, $userId, $numbered, ['note' => 'PRUEF wieder gespeichert']);
    point_update($pdo, $userId, $unnumbered, ['note' => 'PRUEF wieder gespeichert']);
    check(in_array($numberAuto, $listsOf($numbered), true)
          && in_array($markAuto, $listsOf($unnumbered), true),
        'saved again, both stay in — the per-point path reads number and id as well');
    $later = point_create($pdo, $userId, ['lat' => 52.59, 'lon' => 13.48,
        'title' => 'PRUEF Später', 'lists' => [], 'find_number' => 'PRUEF-2026-0043']);
    check(in_array($numberAuto, $listsOf($later), true),
        'a numbered find born later falls in at once — the path of every import with a Fundnummer column');
    list_delete($pdo, $userId, $numberAuto);
    list_delete($pdo, $userId, $markAuto);
    foreach ([$numbered, $unnumbered, $later] as $id) {
        point_delete($pdo, $userId, $id);
    }

    echo "The ohne source stays stable:\n";
    [$unsortedAuto, $n] = autolist_create($pdo, $userId, 'PRUEF Unsortiert', organize_autofilter($pdo, $userId, [
        'list' => 'none', 'search' => '', 'from' => '', 'to' => '', 'no_time' => '',
    ]));
    $solo = point_create($pdo, $userId, ['lat' => 52.55, 'lon' => 13.44,
        'title' => 'PRUEF Solo', 'lists' => []]);
    check(in_array($unsortedAuto, $listsOf($solo), true),
        'a list-less point falls in');
    autolists_compute($pdo, $userId);
    check(in_array($unsortedAuto, $listsOf($solo), true),
        'and STAYS in on the next computation — the auto list itself does not count as a list');
    point_update($pdo, $userId, $solo, ['lists' => [$lichens]]);
    check(!in_array($unsortedAuto, $listsOf($solo), true),
        'sorted in no longer means without — the point leaves');

    echo "No auto list as the source of an auto list:\n";
    $filter = organize_autofilter($pdo, $userId, [
        'list' => (string) $autoId, 'search' => '', 'from' => '', 'to' => '', 'no_time' => '',
    ]);
    check($filter['sources'] === [],
        'organize_autofilter sieves it out — A feeds B feeds A would be a ring');

    echo "Nothing under it, no merging:\n";
    $thrown = 0;
    try { list_update($pdo, $userId, $autoId, ['parent_id' => $mushrooms]); } catch (InvalidArgumentException) { $thrown++; }
    try { list_create($pdo, $userId, ['name' => 'PRUEF Kind', 'parent_id' => $autoId]); } catch (InvalidArgumentException) { $thrown++; }
    try { organize_merge($pdo, $userId, $lichens, $autoId); } catch (InvalidArgumentException) { $thrown++; }
    try { organize_merge($pdo, $userId, $autoId, $lichens); } catch (InvalidArgumentException) { $thrown++; }
    check($thrown === 4,
        'nothing under a list at all, and no merging — in either direction');

    echo "Source gone:\n";
    point_update($pdo, $userId, $boletus, ['lists' => [$boletes]]);
    list_delete($pdo, $userId, $mushrooms);
    check(!in_array($autoId, $listsOf($boletus), true),
        'if the source itself falls, the points lose their basis — the auto list empties');

    echo "Hanging into containers stays allowed:\n";
    $choice = array_values(array_filter(share_list_selection($pdo, $userId),
        static fn(array $l): bool => $l['id'] === $autoId));
    check(($choice[0]['auto'] ?? false) === true,
        'the auto list is up for choice in the hang-in window, labelled as automatic');

    echo "Deleting cleans up:\n";
    point_update($pdo, $userId, $solo, ['lists' => []]);   // list-less again → back in
    check(in_array($unsortedAuto, $listsOf($solo), true), 'pre-check: the point hangs in again');
    list_delete($pdo, $userId, $unsortedAuto);
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_point_lists WHERE list_id = $unsortedAuto")->fetchColumn() === 0,
        'with the auto list its assignments vanish — as with every list');
} finally {
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}


/* THE HEIGHT BAND — the same cases as in tests/filter.mjs, and that is
   the point of them: the two implementations cannot import from each
   other, so only a pair of tests holds them together
   (26.08.2026). Whoever changes the rule in one half and not the other
   sees it here.

   Called at the bare level, without the database: autolist_matches wants
   a haystack, times, lists and the place bundle — the elevation travels
   in that bundle, and everything else stays neutral. */
echo "\nThe height band (twin of tests/filter.mjs):\n";
$band = static function (?int $from, ?int $to, $metres): bool {
    return autolist_matches(
        autolist_filter_read(['elevation_from' => $from, 'elevation_to' => $to]),
        'egal', null, null, [], [], [], ['elevation_m' => $metres]);
};

check($band(0, ELEVATION_MAX, 300), 'the full band lets everything through');
check($band(0, ELEVATION_MAX, null), 'and a point without an elevation too');

check($band(300, 900, 300), 'the lower edge belongs in');
check($band(300, 900, 900), 'the upper edge belongs in');
check(!$band(300, 900, 299), 'one metre below does not');
check(!$band(300, 900, 901), 'one metre above does not');

check(!$band(300, 900, null), 'without an elevation a point drops out of an active band');
check(!$band(300, 900, ''), 'an empty elevation counts as none');
check(!$band(300, 900, 'hoch'), 'and nonsense counts as none too');

check($band(1000, ELEVATION_MAX, 2800),
    'above the scale still counts — the upper end means „and above"');
check($band(ELEVATION_MAX, ELEVATION_MAX, 3000),
    'both handles at the ceiling: everything above it');
check(!$band(ELEVATION_MAX, ELEVATION_MAX, 2400), 'and nothing below it');

/* THE CEILING STANDS IN TWO FILES and cannot be shared — this line is
   what notices when one of them moves. The counterpart is the check
   „the step is a hundred metres" in tests/filter.mjs. */
check(ELEVATION_MAX === 2500, 'the ceiling is 2500 m, as in assets/js/filter.js');


/* THE SYSTEMATIC RANKS — the same cases as in tests/filter.mjs, and that
   is their point: the two implementations cannot import from each other,
   so only a pair of tests holds them together (26.08.2026). */
echo "\nThe systematic ranks (twin of tests/filter.mjs):\n";
$boletus = ['kingdom' => 'Fungi', 'phylum' => 'Basidiomycota', 'class' => 'Agaricomycetes',
            'order' => 'Boletales', 'family' => 'Boletaceae', 'genus' => 'Boletus'];
$quercus = ['kingdom' => 'Plantae', 'phylum' => 'Tracheophyta', 'class' => 'Magnoliopsida',
            'order' => 'Fagales', 'family' => 'Fagaceae', 'genus' => 'Quercus'];

check(taxonomy_matches([], $boletus), 'nothing asked lets everything through');
check(taxonomy_matches([], null), 'and a point without ranks too');

check(taxonomy_matches(['family' => 'Boletaceae'], $boletus), 'the family hits');
check(!taxonomy_matches(['family' => 'Amanitaceae'], $boletus), 'a different family does not');
check(taxonomy_matches(['family' => 'boletaceae'], $boletus), 'case does not matter');

check(taxonomy_matches(['kingdom' => 'Fungi', 'family' => 'Boletaceae'], $boletus), 'two that hold');
check(!taxonomy_matches(['kingdom' => 'Plantae', 'family' => 'Boletaceae'], $boletus),
    'one that does not is enough to hold the point back');

check(taxonomy_matches(['kingdom' => 'Plantae'], $quercus), 'a plant is found by its kingdom');
check(!taxonomy_matches(['kingdom' => 'Fungi'], $quercus), 'and not by a foreign one');

check(!taxonomy_matches(['family' => 'Boletaceae'], null),
    'without ranks a point drops out of an active condition');
check(!taxonomy_matches(['kingdom' => 'Fungi'], ['kingdom' => null, 'family' => 'Boletaceae']),
    'and a rank that is null counts as no answer');

check(implode(',', array_keys(TAXONOMY_RANKS)) === 'kingdom,phylum,class,order,family,genus',
    'the six ranks, top down — the same words as in assets/js/filter.js');

/* THE GENUS REACHES FURTHER THAN THE RANKS ABOVE IT: it stands on the
   point, everything above is looked up and can fail. A genus the backbone
   does not know still comes back — with nothing but itself. */
check(taxonomy_matches(['genus' => 'Boletus'], ['genus' => 'Boletus']),
    'a genus without ranks above it is still found by its genus');

/* AND THE WHOLE WAY THROUGH, through autolist_filter_read and
   autolist_matches: a stored filter has to keep meaning what the table
   showed. */
$durch = static function (array $ranks, $pointRanks): bool {
    return autolist_matches(
        autolist_filter_read(['taxonomy' => $ranks]),
        'egal', null, null, [], [], [], ['taxonomy' => $pointRanks]);
};
check($durch(['family' => 'Boletaceae'], $boletus), 'through the stored filter: the family hits');
check(!$durch(['family' => 'Boletaceae'], $quercus), 'and a foreign one does not');
check($durch([], $quercus), 'an empty condition asks nothing');
check(!$durch(['kingdom' => 'Fungi'], null), 'an unresolvable genus drops out');


/* GLEICHNAMIGE GATTUNGEN ÜBER DIE REICHE (the maintainer fragte danach,
   26.08.2026). Botany and zoology keep separate registers, so a genus name
   can stand in two kingdoms — measured: 2575 accepted names do, and 16 of
   the 211 in the local stock. Resolved by NAME alone, Abronia the plant
   gets the ranks of Abronia the alligator lizard, and nothing shows it.

   The source the name was picked from settles it. These checks hold that
   fast — and the last one records what happens when there is no source at
   all, because that case is a genuine limit, not a defect. */
echo "\nGenus names that stand in two kingdoms:\n";
if (taxonomy_ready($pdo)) {
    $rang = static function (string $genus, string $source, string $rank) use ($pdo): ?string {
        $r = taxonomy_of_genera($pdo, [['genus' => $genus, 'source' => $source]]);
        return $r[taxonomy_key($genus, $source)][$rank] ?? null;
    };
    check($rang('Abronia', 'plants', 'kingdom') === 'Plantae',
        'Abronia from the plant list is a plant');
    check($rang('Abronia', 'plants', 'family') === 'Nyctaginaceae',
        'and gets the family of the sand verbena, not of the lizard');
    check($rang('Cladonia', 'lichens', 'kingdom') === 'Fungi',
        'a lichen is a fungus — the lichen list means Fungi');
    check($rang('Boletus', 'fungi', 'family') === 'Boletaceae',
        'and the DGfM list means Fungi');

    /* WITHOUT A SOURCE THE LIKELY KINGDOMS ARE ASKED FIRST
       (26.08.2026: „Chromista können wir entfernen"). 67 accepted
       Chromista genera share a name with a fungal genus, and a freely
       typed title brings no register — so Fungi, then Plantae, then
       Animalia get the first refusal. This is a mushroom app. */
    check($rang('Abronia', '', 'kingdom') !== null,
        'without a source something still comes back');
    check($rang('Acanthocystis', '', 'kingdom') === 'Fungi',
        'a name that is both a fungus and a chromist resolves to the fungus');
    check($rang('Arnaudiella', '', 'kingdom') === 'Fungi', 'and so does the next of them');

    /* THE OOMYCOTA STAY, KINGDOM AND ALL (26.08.2026: „Wenn
       das phytopathogene Pilze sind, dann müssen wir das lassen").
       Chromista was struck out for half a day and that was wrong — the
       downy mildews, Phytophthora and the white rusts live there, and
       they are mapped. The DGfM is building a portal of its own for
       exactly these. */
    check($rang('Phytophthora', '', 'kingdom') === 'Chromista',
        'a phytopathogen keeps its kingdom');
    check($rang('Phytophthora', '', 'phylum') === 'Oomycota', 'and its phylum');
    check($rang('Peronospora', '', 'family') === 'Peronosporaceae', 'and its family');

    /* THE PREFERENCE IS THE PHYLUM, NOT THE KINGDOM, and Bremia is why.
       The name is a downy mildew AND a gall midge; asking Fungi, then
       Plantae, then Animalia handed it to the midge. Preferring the whole
       of Chromista instead would have dragged 87 plant and 307 animal
       names along — the Oomycota overlap with everything else in exactly
       ten. */
    check($rang('Bremia', '', 'phylum') === 'Oomycota',
        'Bremia is the downy mildew, not the gall midge');
    check($rang('Turdus', '', 'kingdom') === 'Animalia',
        'and an animal that is only an animal stays one');
} else {
    echo "  (übersprungen — kein GBIF-Rückgrat in dieser Datenbank)\n";
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
