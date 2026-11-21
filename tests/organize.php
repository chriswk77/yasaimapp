<?php
/**
 * Tests the Organisieren (Mein Bereich → Organisieren) — the server half.
 *
 *   php tests/organize.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE DELTAS INSTEAD OF THE FINISHED SET. The list dialog sends add
 *   and remove — computed per point on ITS assignment. A point that
 *   additionally hangs in a list the selection does not share keeps it.
 *   That is the one rule of the organizer; if it fell, every mass change
 *   would flatten the mixed assignments.
 *
 *   UNCHANGED POINTS STAY UNTOUCHED — no stand bump, no updated_at:
 *   otherwise every device would load points on the next sync on which
 *   nothing happened.
 *
 *   THE SIEVES: parent lists (headings, not containers), foreign lists
 *   and foreign points do not go through.
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
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/organize_content.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-ordnen@pruefung.local', '', 'PRUEF', 'Organisieren', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-ordnen2@pruefung.local', '', 'PRUEF', 'Fremd', 'user', NOW())"
)->execute();
$foreignId = (int) $pdo->lastInsertId();

try {
    $forest  = list_create($pdo, $userId, ['name' => 'PRUEF Wald']);
    $beeches = list_create($pdo, $userId, ['name' => 'PRUEF Buchen']);
    $oaks    = list_create($pdo, $userId, ['name' => 'PRUEF Eichen']);
    $species = list_create($pdo, $userId, ['name' => 'PRUEF Arten', 'is_species' => true]);
    $foreignList = list_create($pdo, $foreignId, ['name' => 'PRUEF Fremd']);

    // Berlin, not Hesse: no DGM1 fetch that slows the test down.
    $p1 = point_create($pdo, $userId, ['lat' => 52.51, 'lon' => 13.40,
        'title' => 'Boletus edulis', 'lists' => [$beeches]]);
    $p2 = point_create($pdo, $userId, ['lat' => 52.52, 'lon' => 13.41,
        'title' => 'PRUEF Zwei', 'lists' => [$beeches, $oaks]]);
    $p3 = point_create($pdo, $userId, ['lat' => 52.53, 'lon' => 13.42,
        'title' => 'PRUEF Drei', 'lists' => []]);
    $foreignPoint = point_create($pdo, $foreignId, ['lat' => 52.54, 'lon' => 13.43,
        'title' => 'PRUEF Fremdpunkt', 'lists' => [$foreignList]]);

    $listsOf = static function (int $pointId) use ($pdo): array {
        $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ? ORDER BY list_id');
        $stmt->execute([$pointId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    };

    echo "IDs from the form:\n";
    check(organize_ids('3, 1,x,2,,1') === [3, 1, 2],
        'the comma list is sieved and deduplicated, junk falls out');

    echo "The deltas — every point keeps what the selection does not share:\n";
    // Since the container checkmarks make partial calls, applying returns
    // the IDS of the changed points, no longer the bare count.
    $n = count(organize_lists_apply($pdo, $userId, [$p1, $p2, $p3], [$species], [$beeches]));
    check($n === 3, 'three points changed');
    check($listsOf($p1) === [$species],
        'out of the one list, into the other');
    $expected = [$oaks, $species]; sort($expected);
    check($listsOf($p2) === $expected,
        'the Eichen assignment SURVIVES — remove takes only the named list, never the rest');
    check($listsOf($p3) === [$species],
        'a point without a list also gets the new one');

    $inSpecies = (int) $pdo->query(
        "SELECT COUNT(*) FROM map_point_species WHERE point_id = $p1"
    )->fetchColumn();
    check($inSpecies > 0,
        'the species table is kept in step — the path goes through point_update, not past it');

    echo "The unchanged stays untouched:\n";
    $changedBefore = $pdo->query("SELECT updated_at FROM map_points WHERE id = $p2")->fetchColumn();
    sleep(1);   // updated_at has second resolution — otherwise a bump would be invisible
    $n = count(organize_lists_apply($pdo, $userId, [$p1, $p2, $p3], [$species], [$beeches]));
    check($n === 0, 'the same job once more changes nothing');
    check($pdo->query("SELECT updated_at FROM map_points WHERE id = $p2")->fetchColumn() === $changedBefore,
        'no stand bump without a change — otherwise every device would reload the unchanged');

    echo "The sieves:\n";
    $n = count(organize_lists_apply($pdo, $userId, [$p3], [$foreignList], []));
    check($n === 0 && $listsOf($p3) === [$species],
        'a foreign list does not go through');
    $n = count(organize_lists_apply($pdo, $userId, [$foreignPoint], [$species], []));
    check($n === 0 && $listsOf($foreignPoint) === [$foreignList],
        'a foreign point stays untouched');

    echo "Trash:\n";
    $n = organize_to_trash($pdo, $userId, [$p3, $foreignPoint]);
    check($n === 1, 'only the own point moves — the foreign one does not count');
    check($pdo->query("SELECT deleted_at FROM map_points WHERE id = $p3")->fetchColumn() !== null,
        'the point lies in the trash, not in the void');
    check(organize_to_trash($pdo, $userId, [$p3]) === 0,
        'into the trash a second time does not work — it already lies there');
    check(organize_lists_apply($pdo, $userId, [$p3], [$oaks], []) === [],
        'a point in the trash is no prey for the list dialog');

    echo "Setting a field (stage 2):\n";
    $n = organize_field_set($pdo, $userId, [$p1, $p2], 'Finder', 'PRUEF Sammler', false);
    $extra = json_decode((string) $pdo->query("SELECT extra FROM map_points WHERE id = $p1")->fetchColumn(), true);
    check($n === 2 && ($extra['Finder'] ?? null) === 'PRUEF Sammler',
        'the find field sits in the extra block of both — one key changed, the rest untouched');
    check(organize_field_set($pdo, $userId, [$p1, $p2], 'Finder', 'PRUEF Sammler', false) === 0,
        'the same value once more changes nothing — no bump without a change, for the field too');

    $n = organize_field_set($pdo, $userId, [$p1], 'Finder', '', true);
    $extra = json_decode((string) $pdo->query("SELECT extra FROM map_points WHERE id = $p1")->fetchColumn(), true);
    check($n === 1 && !isset($extra['Finder']),
        'clearing takes the key out instead of leaving an empty husk');

    $n = organize_field_set($pdo, $userId, [$p1, $p2], 'title', 'Amanita muscaria', false);
    check($n === 2
          && $pdo->query("SELECT COUNT(*) FROM map_points WHERE id IN ($p1, $p2) AND title = 'Amanita muscaria'")->fetchColumn() == 2,
        'the title — your "species name everywhere" — is set on all');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_point_species WHERE point_id = $p1")->fetchColumn() > 0,
        'the species table knows the new name — the path goes through point_update');

    $n = organize_field_set($pdo, $userId, [$p1], 'note', 'PRUEF Notiz', false);
    check($n === 1
          && $pdo->query("SELECT note FROM map_points WHERE id = $p1")->fetchColumn() === 'PRUEF Notiz',
        'the note can be set uniformly');
    organize_field_set($pdo, $userId, [$p1], 'note', '', true);
    check($pdo->query("SELECT note FROM map_points WHERE id = $p1")->fetchColumn() === null,
        'and cleared again — to null, not to empty text');

    $thrown = 0;
    try { organize_field_set($pdo, $userId, [$p1], 'title', '', true); } catch (InvalidArgumentException) { $thrown++; }
    try { organize_field_set($pdo, $userId, [$p1], 'Finder', '', false); } catch (InvalidArgumentException) { $thrown++; }
    check($thrown === 2,
        'the title cannot be cleared, and an empty value without the clear checkmark is rejected');

    echo "Merging (stage 2):\n";
    $target2 = list_create($pdo, $userId, ['name' => 'PRUEF Sammelziel']);
    $p4 = point_create($pdo, $userId, ['lat' => 52.55, 'lon' => 13.44,
        'title' => 'PRUEF Vier', 'lists' => [$beeches]]);
    $p5 = point_create($pdo, $userId, ['lat' => 52.56, 'lon' => 13.45,
        'title' => 'PRUEF Fünf', 'lists' => [$beeches, $target2]]);

    $n = organize_merge($pdo, $userId, $beeches, $target2);
    check($n === 2, 'both points of the source have moved');
    check($listsOf($p4) === [$target2] && $listsOf($p5) === [$target2],
        'whoever already hung in the target is not assigned twice');
    check($pdo->query("SELECT deleted_at FROM map_lists WHERE id = $beeches")->fetchColumn() !== null,
        'the source is gone');

    $thrown = 0;
    try { organize_merge($pdo, $userId, $foreignList, $target2); } catch (InvalidArgumentException) { $thrown++; }
    try { organize_merge($pdo, $userId, $target2, $foreignList); } catch (InvalidArgumentException) { $thrown++; }
    try { organize_merge($pdo, $userId, $target2, $target2); } catch (InvalidArgumentException) { $thrown++; }
    check($thrown === 3,
        'only own list into own list, and never into itself — everything else is rejected');

    $n = organize_merge($pdo, $userId, $species, $target2);
    check($n === 2
          && $pdo->query("SELECT deleted_at FROM map_lists WHERE id = $species")->fetchColumn() !== null,
        'a species list dissolves too — it is empty after the move, the lock no longer takes hold');

    echo "The species-list lock protects the collection, not the empty shell:\n";
    $emptySpecies = list_create($pdo, $userId, ['name' => 'PRUEF Leere Arten', 'is_species' => true]);
    list_delete($pdo, $userId, $emptySpecies);
    check($pdo->query("SELECT deleted_at FROM map_lists WHERE id = $emptySpecies")->fetchColumn() !== null,
        'an empty species list can be deleted — the conclusion after clearing out an import list');

    $fullSpecies = list_create($pdo, $userId, ['name' => 'PRUEF Volle Arten', 'is_species' => true]);
    $p6 = point_create($pdo, $userId, ['lat' => 52.57, 'lon' => 13.46,
        'title' => 'PRUEF Sechs', 'lists' => [$fullSpecies]]);
    $thrown = false;
    try { list_delete($pdo, $userId, $fullSpecies); } catch (InvalidArgumentException) { $thrown = true; }
    check($thrown, 'with finds it stays protected, as ever');

    point_delete($pdo, $userId, $p6);
    list_delete($pdo, $userId, $fullSpecies);
    check($pdo->query("SELECT deleted_at FROM map_lists WHERE id = $fullSpecies")->fetchColumn() !== null,
        'points in the trash do not count — otherwise a withdrawn import could never be cleared away');

    echo "Orphans on list deletion — Ohne Liste instead of a catch-all automatism:\n";
    $countLists = static fn(): int => (int) $pdo->query(
        "SELECT COUNT(*) FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL"
    )->fetchColumn();

    $solo = list_create($pdo, $userId, ['name' => 'PRUEF Solo']);
    $p8 = point_create($pdo, $userId, ['lat' => 52.59, 'lon' => 13.48,
        'title' => 'PRUEF Acht', 'lists' => [$solo]]);
    $before = $countLists();
    $r = list_delete($pdo, $userId, $solo);
    check($r['points'] === 1 && $r['catch_all'] === null && $listsOf($p8) === [],
        'the orphan simply becomes listless — no catch-all list "(gelöscht …)" any more');
    check($countLists() === $before - 1,
        'and the list stock really shrinks instead of growing on deletion');

    $byHand = list_create($pdo, $userId, ['name' => 'Ohne Liste']);
    $solo2  = list_create($pdo, $userId, ['name' => 'PRUEF Solo 2']);
    $p9 = point_create($pdo, $userId, ['lat' => 52.60, 'lon' => 13.49,
        'title' => 'PRUEF Neun', 'lists' => [$solo2]]);
    $r = list_delete($pdo, $userId, $solo2);
    check($r['catch_all'] === $byHand && $listsOf($p9) === [$byHand],
        'a BY-HAND created "Ohne Liste" is the declared catch-all list — the orphans move in');


    /* -----------------------------------------------------------------
       THE WIRE BETWEEN BROWSER AND FORM.

       This is where the Organisieren was broken, and nothing noticed: the
       hidden fields in the markup had been translated (add, remove,
       field, value, clear), organize.js went on writing into hinzu, weg,
       field, value, leeren. querySelector hands back null, writing .value
       on it throws, the loop breaks off BEFORE form.submit() — and since
       the dialog had already closed, the page simply stood there. Two of
       the four mass actions were dead that way, and the only report was
       the maintainer's on 21.08.2026: "und weiter wurde mir auch nichts
       gespeichert".

       Neither half was wrong on its own, which is why every test up to
       here stayed green: the server half is checked above, the browser
       half has no server in it. ONLY THE JOINT CAN BE MEASURED, so it is
       measured — the page is really rendered and really parsed, rather
       than a regular expression believing something about the markup.
       ----------------------------------------------------------------- */
    echo "The wire — every key organize.js writes has a field in the markup:\n";

    ob_start();
    organize_render($pdo, $userId, ['message' => null, 'error' => [], 'state' => []]);
    $html = (string) ob_get_clean();

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
    libxml_clear_errors();

    /* form id -> the names it actually offers */
    $formFields = [];
    foreach ($doc->getElementsByTagName('form') as $form) {
        $id = $form->getAttribute('id');
        if ($id === '') continue;
        $names = [];
        foreach (['input', 'select', 'textarea'] as $tag) {
            foreach ($form->getElementsByTagName($tag) as $field) {
                if ($field->hasAttribute('name')) $names[] = $field->getAttribute('name');
            }
        }
        $formFields[$id] = $names;
    }
    check(isset($formFields['organize-lists-form']),
        'the rendered page really does hold the forms — otherwise this test would prove nothing');

    /* Every submitForm(el('…'), { … }) call from the browser file. The
       object literals there hold no braces of their own, so the lazy
       match to the first } is enough — should that ever change, the
       missing key falls out here as a false alarm and not as silence. */
    $js  = (string) file_get_contents(__DIR__ . '/../assets/js/organize.js');
    $php = (string) file_get_contents(__DIR__ . '/../includes/organize_content.php');
    preg_match_all(
        "/submitForm\(\s*el\('([a-z0-9-]+)'\)\s*(?:,\s*\{(.*?)\}\s*)?\)/s",
        $js, $calls, PREG_SET_ORDER);
    check(count($calls) >= 4, 'all four mass actions are found in organize.js (' . count($calls) . ')');

    foreach ($calls as $call) {
        $formId = $call[1];
        check(isset($formFields[$formId]), "the form „$formId\" exists in the markup");

        // Every call sends the selection along, submitForm sees to that.
        $keys = ['points'];
        if (($call[2] ?? '') !== '') {
            /* A KEY STANDS AFTER THE BRACE OR AFTER A COMMA, never merely
               at the start of a line: the first version of this test hung
               itself on the line start and thereby overlooked exactly the
               key whose absence had broken the removing — „remove" sat on
               the same line as „add". A guard that reads only tidily set
               source text guards the tidiness, not the thing.

               The colon may be missing: { action, list: … } is the short
               form for { action: action, … } and would otherwise slip
               through just as quietly. */
            preg_match_all('/(?:^|,)\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(?=[:,]|\s*$)/',
                $call[2], $found);
            check($found[1] !== [], "keys can be read out of the call to „$formId\"");
            $keys = array_merge($keys, $found[1]);
        }
        foreach (array_unique($keys) as $key) {
            check(in_array($key, $formFields[$formId] ?? [], true),
                "„$formId\" offers the field „$key\"");
        }
    }

    /* THE ACTION VOCABULARY IS THE SECOND CONTRACT of the same wire, and
       until 21.08.2026 the switch had no default: an action nobody knew
       fell through and was never heard from again. Now it throws — and
       here it is checked that no name organize.js sends ever has to. */
    echo "The actions — every name the browser sends has a case:\n";
    preg_match_all("/submitTree\('([a-z_]+)'/", $js, $tree);
    preg_match_all('/name="action" value="([a-z_]+)"/', $html, $fixed);
    $actions = array_values(array_unique(array_filter(array_merge($tree[1], $fixed[1]))));
    check(count($actions) >= 7, 'the actions are found (' . implode(', ', $actions) . ')');
    foreach ($actions as $action) {
        check(str_contains($php, "case '$action':"),
            "organize_process knows the action „$action\"");
    }

    /* THE THIRD WIRE IN THIS AREA IS THE PAYLOAD. #organize-data is the
       only way the browser learns anything at all here — and it was half
       translated: 'points' and 'find_fields' beside 'lists', 'geteilt',
       'fremdpunkte', 'vorwahl'. A key renamed on one side only would not
       throw; startOrganize would simply destructure undefined and the
       page would stay empty in places. So it is read out of the really
       rendered page, not believed. */
    echo "The payload — what startOrganize unpacks is really in the JSON:\n";
    preg_match('/id="organize-data"[^>]*>(.*?)<\/script>/s', $html, $block);
    $payload = json_decode(html_entity_decode($block[1] ?? '', ENT_QUOTES, 'UTF-8'), true);
    check(is_array($payload), 'the data block is valid JSON');

    preg_match('/function startOrganize\(\{(.*?)\}\)/s', $js, $signature);
    preg_match_all('/(?:^|,)\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(?=[:,=]|\s*$)/',
        $signature[1] ?? '', $unpacked);
    check(count($unpacked[1]) >= 5,
        'the signature is read (' . implode(', ', $unpacked[1]) . ')');
    foreach ($unpacked[1] as $key) {
        check(array_key_exists($key, (array) $payload),
            "the JSON carries „$key\"");
    }

    /* THE FOURTH WIRE RUNS THROUGH THE STYLESHEET, and it is the quietest
       of them all. The tri-state checkbox draws its picture from is-full /
       is-partial, never from the native checked — that one lies, because
       preventDefault() restores it after the event. But the generic rules
       in app.css weigh input[type="checkbox"]:checked::after = (0,2,1),
       and a bare .organize-dialog-check.is-full::after only (0,2,0). A
       rule without the input prefix therefore LOSES against the restored
       check and the component paints someone else's state. That is what
       broke taking a check out (21.08.2026), and nothing but
       arithmetic would ever have noticed. */
    echo "The stylesheet — every rule of the box outweighs the generic ones:\n";
    $css = (string) file_get_contents(__DIR__ . '/../assets/css/app.css');
    preg_match_all('/^([^\n{]*\.organize-dialog-check[^\n{]*)\{/m', $css, $selectors);
    check(count($selectors[1]) >= 5,
        'the rules are found (' . count($selectors[1]) . ')');
    foreach ($selectors[1] as $selector) {
        $selector = trim($selector);
        // The base rule sets no picture and needs no weight; everything
        // that speaks about :checked or a state class does.
        if (!str_contains($selector, ':checked') && !str_contains($selector, '.is-')) continue;
        check(str_starts_with($selector, 'input'),
            "„$selector\" carries the input prefix");
    }

    /* And the same joint one step further: what the browser sends must be
       what organize_process reads. A field that exists in the markup and
       is read nowhere would be just as quiet. */
    preg_match_all("/\\\$_POST\['([a-z_]+)'\]/", $php, $read);
    foreach (['add', 'remove', 'add_container', 'add_container_original',
              'remove_container', 'field', 'value', 'clear',
              'mode', 'start', 'restart', 'scope', 'old', 'adopt'] as $key) {
        check(in_array($key, $read[1], true),
            "organize_process reads „$key\"");
    }
    /* ALL PICTURES FOR THE BILDER COLUMN (30.09.2026) — voucher photos and
       cloud recordings in the gallery's one order. The column knew only
       map_photos, and a find's pictures are mostly recordings. */
    if (photos_ready($pdo) && table_exists($pdo, 'map_cloud_files')
        && column_exists($pdo, 'map_cloud_files', 'sort_order')) {
        echo "The Bilder column sees the recordings too:\n";
        $pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at)
                       VALUES (?, 51.3, 9.9, "Bilderprobe", NOW(), NOW())')->execute([$userId]);
        $picturePoint = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, width, height, bytes, sort_order, created_at)
                       VALUES (?, ?, "probe.jpg", 10, 10, 10, 1, NOW())')->execute([$userId, $picturePoint]);
        $photoRow = (int) $pdo->lastInsertId();
        $cloudRows = [];
        foreach ([2 => 'spaet', 0 => 'frueh'] as $place => $name) {
            $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, sort_order, uploaded_at)
                           VALUES (?, ?, "pcloud", ?, ?, 10, ?, NOW())')
                ->execute([$userId, $picturePoint, "probe-$name", "$name.jpg", $place]);
            $cloudRows[$name] = (int) $pdo->lastInsertId();
        }
        $got = organize_pictures($pdo, [$picturePoint, $picturePoint + 999999]);
        check(($got[$picturePoint] ?? null) === ["cloud:{$cloudRows['frueh']}", "photo:$photoRow", "cloud:{$cloudRows['spaet']}"],
            'photos and recordings come in the gallery\'s order, as keys');
        check(!isset($got[$picturePoint + 999999]), 'a point without pictures is left out');
        $pdo->exec("DELETE FROM map_cloud_files WHERE point_id = $picturePoint");
        $pdo->exec("DELETE FROM map_photos WHERE point_id = $picturePoint");
        $pdo->exec("DELETE FROM map_points WHERE id = $picturePoint");
    }
} finally {
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
    $pdo->exec("DELETE FROM map_users WHERE id = $foreignId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
