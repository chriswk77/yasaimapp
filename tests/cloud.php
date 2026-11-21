<?php
/**
 * Tests the cloud accesses.
 *
 *   php tests/cloud.php
 *
 * WHAT IS REALLY TESTED HERE is a single promise: the secret does not
 * leave cloud.php. An OAuth token opens not this account but the user's
 * ENTIRE cloud storage — including everything that has nothing to do with
 * this application. If it ever slipped into an array that goes out as
 * JSON, that would be the most expensive bug this project can have, and
 * you would not be able to tell by looking at anyone.
 *
 * That is why the rule is not just written down as a comment but as a
 * test: cloud_connections() must not even read the column along.
 *
 * SINCE pCLOUD IS WIRED UP, a second promise sits beside it, and it fails just
 * as silently: the data centre is RECOGNISED, not trusted. pCloud names the
 * API host as a query parameter on the way back from consent — and a query
 * parameter is whatever the browser carries. The very next thing the code does
 * is POST the app secret to that host. A hole there hands the secret to a
 * stranger, and nothing about it looks wrong.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nThe provider list\n";
check(cloud_provider_valid('pcloud'), 'pCloud is provided for');
check(!cloud_provider_valid('dropbox'), 'what is not in the list is not valid');
check(!cloud_provider_valid(''), 'and empty even less so');
foreach (CLOUD_PROVIDERS as $code => $a) {
    check(isset($a['name'], $a['kind'], $a['ready'], $a['hint']),
        "„$code\" is fully described");
    check(in_array($a['kind'], ['oauth', 'key'], true),
        "„$code\" has a known connection kind");
}

echo "\nThe target folder is cleaned\n";
check(cloud_folder_clean('/Karte/Belege/') === 'Karte/Belege',
    'leading and trailing slashes fall away');
check(cloud_folder_clean('Karte\\Belege') === 'Karte/Belege',
    'the Windows slash too becomes the ordinary one');
check(cloud_folder_clean('Karte//Belege') === 'Karte/Belege',
    'double slashes collapse');
check(cloud_folder_clean('') === '', 'empty stays empty — that is the root');
check(cloud_folder_clean('   ') === '', 'and whitespace is empty too');
// NOBODY GETS TO CLIMB OUT. A ".." in the target folder led out of the
// chosen folder — less severe on one's own storage than on the server,
// but it is still not what anyone means.
check(cloud_folder_clean('Karte/../..') === 'Karte',
    'two dots do not lead upwards');
check(!str_contains(cloud_folder_clean("Karte\x00/Belege"), "\x00"),
    'control characters fall out');
check(mb_strlen(cloud_folder_clean(str_repeat('a', 400))) <= 255,
    'too long is shortened — the column holds 255');

echo "\nThe levels of the filing\n";
// The division sits in the taxonomy under exactly the name the folders
// carry — that is the reason the path can be derived at all. (The level
// checks that stood before this one died with cloud_levels(), 28.08.2026:
// the display table left the tab, and the real path builders are held
// against their own checks further down.)
check(!table_exists($pdo, 'map_tax_dgfm') || (int) $pdo->query(
    'SELECT COUNT(*) FROM map_tax_dgfm WHERE class = "Basidiomycota"'
)->fetchColumn() > 0,
    'the division "Basidiomycota" stands as such in map_tax_dgfm.class');

if (!cloud_ready($pdo)) {
    echo "\n(The rest skipped — migration 0053 is not applied here yet.)\n";


echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
    exit($failed === 0 ? 0 : 1);
}

echo "\nThe data centre is recognised, not believed\n";
// SEE PCLOUD_HOSTS: pCloud runs two data centres with their own addresses, and
// an account lives in exactly one. Which one is stated on the way back — as a
// query parameter, so as something a stranger can set. What is not in the list
// counts as absent, and then the documented US default applies.
check(pcloud_host('eapi.pcloud.com') === 'eapi.pcloud.com',
    'the European data centre stays put');
check(pcloud_host('api.pcloud.com') === 'api.pcloud.com',
    'the American one stays put');
check(pcloud_host('EAPI.PCLOUD.COM') === 'eapi.pcloud.com',
    'upper case is flattened');
foreach (['boese.example', 'api.pcloud.com.boese.example', 'https://eapi.pcloud.com',
          'eapi.pcloud.com:8080', '', null] as $attack) {
    check(pcloud_host($attack) === 'api.pcloud.com',
        sprintf('"%s" is rejected and falls back to the default', (string) $attack));
}

echo "\nWithout credentials in config.php no button\n";
// A connect button that fails on missing configuration is worse than none:
// one tries it three times before believing it is not one's own fault.
check(cloud_provider_usable('gdrive') === false,
    'what the code cannot do yet is not usable');
check(cloud_app('gibtsnicht') === null,
    'an unknown provider has no credentials');

echo "\nThe vault: set up through the interface\n";
// THE KEY LIES IN A FILE AND NOT IN THE DATABASE, and that is the whole
// point: next to the encrypted rows it would protect nothing — a stolen dump
// would yield lock and key together. The test therefore checks two things:
// setting up through the interface works without touching config.php, and a
// second save does NOT replace the key. It would leave every already stored
// token unreadable, and the connections would look intact until first use.
$vaultPath = cloud_vault_path();
$vaultBefore = is_file($vaultPath) ? file_get_contents($vaultPath) : null;

if ($vaultBefore !== null) {
    echo "  skipped: a vault already sits here, it stays untouched\n";
} else {
    cloud_vault_write(['key' => base64_encode(random_bytes(32)),
                            'pcloud' => ['client_id' => 'PRUEF', 'client_secret' => 'GEHEIM']]);
    $firstKey = cloud_vault()['key'];

    check(is_file($vaultPath), 'the file comes into being');
    check(str_starts_with((string) file_get_contents($vaultPath), '<?php'),
        'it is PHP — with a failing lock it gives nothing out, unlike JSON');
    check(!str_contains($vaultPath, '/config'), 'it does not lie in config.php');
    check(cloud_app('pcloud') !== null, 'without config.php the provider is usable');
    check(cloud_key() !== null, 'and the key stands ready');

    // Second save, different credentials, the same key.
    $t = cloud_vault();
    $t['key'] ??= base64_encode(random_bytes(32));
    $t['pcloud'] = ['client_id' => 'ANDERS', 'client_secret' => 'AUCH'];
    cloud_vault_write($t);

    check(cloud_vault()['key'] === $firstKey,
        'THE KEY IS NEVER REPLACED — otherwise all accesses would be unreadable');
    check(cloud_app('pcloud')['client_id'] === 'ANDERS',
        'the credentials can still be changed');

    unlink($vaultPath);
    $GLOBALS['__cloud_vault'] = null;
    check(!is_file($vaultPath), 'cleaned up');
}

echo "\nThe secret lies encrypted in the column\n";
// THE PROMISE THIS KEEPS, and the one it does not: a stolen dump, a backup at
// the host, an SQL injection get noise. Whoever operates the installation has
// database AND config.php and can decrypt — there is no cryptography against
// the operator. That is why the test checks the reachable part: nothing
// readable stands in the column, and it only comes back with the right key.
$keyBefore = $GLOBALS['config']['cloud']['key'] ?? null;
$GLOBALS['config']['cloud']['key'] = base64_encode(random_bytes(32));

$plaintext = 'TOKEN-' . bin2hex(random_bytes(6));
$encrypted = cloud_encrypt($plaintext);

check(!str_contains($encrypted, $plaintext),
    'the stored value does not contain the plaintext');
check(str_starts_with($encrypted, CLOUD_MARK),
    'it carries the mark by which it is recognised');
check(cloud_decrypt($encrypted) === $plaintext,
    'with the key it comes back');
check(cloud_encrypt($plaintext) !== $encrypted,
    'encrypted twice gives two different values — the nonce works');

// Manipulated rather than merely unreadable: both procedures authenticate, so
// a changed byte fails instead of yielding rubbish.
check(cloud_decrypt(substr($encrypted, 0, -6) . 'AAAAAA') === null,
    'a tampered value yields nothing, not rubbish');

$foreign = $GLOBALS['config']['cloud']['key'];
$GLOBALS['config']['cloud']['key'] = base64_encode(random_bytes(32));
check(cloud_decrypt($encrypted) === null,
    'with a foreign key it stays locked');

// The house rule: the code is live before anyone edits config.php. Rows from
// that window are plaintext and must stay readable.
check(cloud_decrypt('ALT-OHNE-MARKE') === 'ALT-OHNE-MARKE',
    'old stock without the mark stays readable');

/* THE VAULT HAS TO BE EMPTIED TOO, and that this was missing only showed
   once somebody actually set a cloud up on the machine running the tests.
   cloud_key() asks config.php FIRST and the vault SECOND — that is the
   whole point of the setup through the interface. Clearing only the config
   therefore did not produce "no key at all"; it produced "the key from the
   vault", and the assertion below then failed on an installation that works
   perfectly.

   An empty array and not null: null makes cloud_vault() read the file from
   disk again, which is exactly what has to be kept out here. */
$vault = $GLOBALS['__cloud_vault'] ?? null;
$GLOBALS['__cloud_vault'] = [];

$GLOBALS['config']['cloud']['key'] = '';
check(cloud_key() === null, 'without an entry and without a vault there is no key');
$error = null;
try { cloud_encrypt('x'); } catch (RuntimeException $e) { $error = $e->getMessage(); }
check($error !== null,
    'without a key NOTHING is stored — better an error than plaintext');

$GLOBALS['config']['cloud']['key'] = base64_encode(random_bytes(16));
check(cloud_key() === null, 'a too-short key counts as none');

$GLOBALS['config']['cloud']['key'] = $foreign;
$GLOBALS['__cloud_vault'] = $vault;

echo "\nConnecting and disconnecting\n";
$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-cloud@pruefung.local', '', 'PRUEF', 'Cloud', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

try {
    check(cloud_connections($pdo, $userId) === [], 'a new account has no accesses');

    cloud_connect($pdo, $userId, 'pcloud', [
        'account'     => 'pruef@pruefung.local',
        'folder'    => '/Karte/Belege/',
        'region'       => 'eapi.pcloud.com',
        'secret' => 'GEHEIM-NUR-FUER-DIE-PRUEFUNG',
    ]);
    $connections = cloud_connections($pdo, $userId);
    check(count($connections) === 1, 'after connecting one stands there');
    check(($connections['pcloud']['account'] ?? null) === 'pruef@pruefung.local',
        'the account is stated — one must see which one hangs');
    check(($connections['pcloud']['folder'] ?? null) === 'Karte/Belege',
        'the folder is stored cleaned');
    // Without this the connection works — until the first call goes to the
    // wrong data centre and answers "login failed" with a valid token.
    check(!column_exists($pdo, 'map_cloud_accounts', 'region')
        || ($connections['pcloud']['region'] ?? null) === 'eapi.pcloud.com',
        'the data centre is carried along');

    // THE ACTUAL PROMISE.
    check(!array_key_exists('secret', $connections['pcloud']),
        'THE SECRET IS NOT INCLUDED — cloud_connections does not even read the column');
    $inColumn = (string) $pdo->query(
        'SELECT secret FROM map_cloud_accounts WHERE user_id = ' . $userId
    )->fetchColumn();
    check(!str_contains($inColumn, 'GEHEIM-NUR-FUER-DIE-PRUEFUNG'),
        'AND IT DOES NOT STAND IN THE COLUMN EITHER — a dump yields nothing');
    check(!str_contains(json_encode($connections), 'GEHEIM-NUR-FUER-DIE-PRUEFUNG'),
        'and it appears nowhere else in the result either');

    check(cloud_secret($pdo, $userId, 'pcloud') === 'GEHEIM-NUR-FUER-DIE-PRUEFUNG',
        'whoever fetches it explicitly gets it');
    check(cloud_secret($pdo, $userId, 'gdrive') === null,
        'for a provider without an access there is none');

    // ONE ACCESS PER PROVIDER: the second connect replaces instead of adding alongside.
    cloud_connect($pdo, $userId, 'pcloud', [
        'account' => 'zweitkonto@pruefung.local', 'folder' => 'Anders', 'secret' => 'NEU',
    ]);
    $connections = cloud_connections($pdo, $userId);
    check(count($connections) === 1, 'a second connect does not create a second one');
    check(($connections['pcloud']['account'] ?? null) === 'zweitkonto@pruefung.local',
        'but replaces the old one');
    check(cloud_secret($pdo, $userId, 'pcloud') === 'NEU',
        'secret included');

    $error = null;
    try {
        cloud_connect($pdo, $userId, 'dropbox', ['account' => 'x']);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
    check($error !== null, 'an unknown provider is rejected');

    check(cloud_disconnect($pdo, $userId, 'pcloud') === true, 'disconnecting reports success');
    check(cloud_connections($pdo, $userId) === [], 'and afterwards none is left');
    check(cloud_disconnect($pdo, $userId, 'pcloud') === false,
        'a second disconnect honestly reports that nothing was there');

    // The dump must not take the table along — a token opens someone
    // else's storage. The same rule as for the login tokens.
    require_once __DIR__ . '/../includes/dump.php';
    check(isset(DUMP_EXCLUDED['map_cloud_accounts']),
        'the database dump leaves the cloud accesses out');
} finally {
    // Accesses go with the account via ON DELETE CASCADE.
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo "\nDer Pfad einer Ablage\n";
// THE RULES, decided on 17.08.2026 after two wrong guesses:
// division only for fungi, second letter counted from genera that ALREADY
// HAVE A FOLDER (not from the reference list), find day plus municipality.
$fungus = ['kingdom' => 'Fungi (Pilze)', 'group' => 'Fungi', 'division' => 'Basidiomycota',
           'genus' => 'Amanita', 'species' => 'muscaria',
           'found_at' => '2026-08-14 09:12:00', 'municipality' => 'Frankfurt am Main'];

check(implode('/', cloud_path_for($fungus)) ===
    'Arten/Fungi (Pilze)/Fungi/Basidiomycota/A/Amanita/A. muscaria/2026-08-14 Frankfurt am Main',
    'Pilz mit Abteilung, einstufiger Buchstabe');

check(implode('/', cloud_path_for($fungus, true)) ===
    'Arten/Fungi (Pilze)/Fungi/Basidiomycota/A/Am/Amanita/A. muscaria/2026-08-14 Frankfurt am Main',
    'und mit zweitem Buchstaben — klein geschrieben, wie von Hand');

$lichen = ['kingdom' => 'Fungi (Pilze)', 'group' => 'Lichenes', 'division' => null,
           'genus' => 'Cladonia', 'species' => 'fimbriata',
           'found_at' => '2026-03-02 10:00:00', 'municipality' => 'Wiesbaden'];
check(implode('/', cloud_path_for($lichen)) ===
    'Arten/Fungi (Pilze)/Lichenes/C/Cladonia/C. fimbriata/2026-03-02 Wiesbaden',
    'Flechte: nach der Gruppe kommt sofort das Alphabet');

check(cloud_path_for(['kingdom' => 'R', 'group' => 'G', 'genus' => 'Xylaria', 'species' => '',
                      'found_at' => '2026-09-01'])[4] === 'Xylaria',
    'nur bis zur Gattung bestimmt: dort steht die Gattung, keine leere Ebene');

$ohneOrt = cloud_path_for(['kingdom' => 'R', 'group' => 'G', 'genus' => 'Boletus',
    'species' => 'edulis', 'found_at' => '2026-10-05', 'municipality' => '']);
check(end($ohneOrt) === '2026-10-05',
    'ohne Gemeinde bleibt der Tag allein stehen');

// THE REGRESSION THIS TEST EXISTS FOR. cloud_folder_clean() treats the slash
// as a separator — right for a target folder, wrong for a single name. A
// species "cf/aeruginea" opened an extra level and the find lay one folder
// deeper than every other one.
$slash = cloud_path_for(['kingdom' => 'R', 'group' => 'G', 'division' => 'A',
    'genus' => 'Russula', 'species' => 'cf/aeruginea', 'found_at' => '2026-08-01']);
check(count($slash) === 8, 'ein Schrägstrich im Artnamen macht KEINE zusätzliche Ebene');
check(in_array('R. cf aeruginea', $slash, true), 'er wird zum Leerzeichen');

// Nobody climbs out — the same promise as for the target folder.
foreach (['..', '../..', 'Amanita/../../..'] as $attack) {
    $t = cloud_path_for(['kingdom' => 'R', 'group' => 'G', 'genus' => $attack,
                         'species' => 'x', 'found_at' => '2026-01-01']);
    check(!in_array('..', $t, true) && !in_array('.', $t, true),
        sprintf('"%s" führt nicht aus dem Ordner heraus', $attack));
}

check(cloud_path_for(['genus' => '']) === [],
    'ohne Gattung gibt es keinen Pfad — ein Fund ohne Bestimmung wird nicht abgelegt');

// THE SPLIT IS THE POINT, not a convenience: above the letter everything is
// fixed, below it too — only the letter's shape depends on what already lies
// in the cloud. Handing back one flat list would force the caller to FIND the
// letter again, and that goes wrong the day a group is called "A".
$split = cloud_path_parts(['kingdom' => 'Fungi (Pilze)', 'group' => 'A',
    'division' => 'Basidiomycota', 'genus' => 'Amanita', 'species' => 'muscaria',
    'found_at' => '2026-08-14', 'municipality' => 'Frankfurt am Main']);
check($split['above'] === ['Arten', 'Fungi (Pilze)', 'A', 'Basidiomycota'],
    'was über dem Buchstaben liegt, bleibt beisammen — auch wenn eine Gruppe "A" heisst');
check($split['letter'] === 'A', 'der Buchstabe steht für sich, nicht gesucht');
check($split['below'][0] === 'Amanita', 'darunter beginnt es mit der Gattung');

check(cloud_path_parts(['genus' => ''])['letter'] === '',
    'ohne Gattung gibt es keinen Buchstaben und damit keinen Pfad');

echo "\nDer Ordner folgt dem heute gültigen Namen (GBIF)\n";
// THE FILING GOES BY THE CURRENT NAME. Of the DGfM synonyms with an accepted
// counterpart, 29 percent carry a different GENUS — filing by the recorded
// name would put the same fungus under two genera, depending on which name
// one happened to use that day.
if (!table_exists($pdo, 'map_tax_gbif') || !table_exists($pdo, 'map_tax_links')) {
    echo "  übersprungen: die Taxonomie ist hier nicht eingespielt\n";
} else {
    $synonyme = $pdo->query(
        'SELECT d.taxnr, d.genus, d.epithet, b.genus AS valid
           FROM map_tax_dgfm d
           JOIN map_tax_links v ON v.taxnr = d.taxnr
           JOIN map_tax_gbif g ON g.gbif_key = v.gbif_key
           JOIN map_tax_gbif b ON b.gbif_key = g.parent_key
          WHERE g.is_synonym = 1 AND g.genus <> b.genus LIMIT 3'
    )->fetchAll();

    if ($synonyme === []) {
        echo "  übersprungen: kein Synonym mit abweichender Gattung gefunden\n";
    } else {
        $folder = [];
        foreach ($synonyme as $syn) {
            $punkt = ['extra_species' => json_encode(['' => [
                          $syn['genus'] . ' ' . $syn['epithet'] => 'fungi:' . $syn['taxnr']]]),
                      'municipality' => 'Wiesbaden', 'found_at' => '2026-08-17'];
            $find = cloud_find_from_point($pdo, 1, $punkt);
            check($find !== null && $find['genus'] === $syn['valid'],
                sprintf('"%s" wird unter "%s" abgelegt', $syn['genus'], $syn['valid']));
            if ($find !== null) {
                $folder[$find['genus'] . ' ' . $find['species']] = true;
            }
        }
        // The point of the whole thing: several recorded names, one folder.
        check(count($folder) < count($synonyme) || count($synonyme) === 1,
            'mehrere erfasste Namen führen in denselben Ordner');
    }

    // The division comes along from GBIF, under the same name the folders
    // carry. Without it a synonym would land one level too high — measured:
    // 15,808 of 39,248 DGfM rows carry no division of their own, and every
    // one of them is a synonym.
    $withDivision = $pdo->query(
        'SELECT d.taxnr, d.genus, d.epithet FROM map_tax_dgfm d
           JOIN map_tax_links v ON v.taxnr = d.taxnr
          WHERE (d.class IS NULL OR d.class = "") AND d.genus <> "" LIMIT 1'
    )->fetch();
    if ($withDivision) {
        $punkt = ['extra_species' => json_encode(['' => [
                      $withDivision['genus'] . ' ' . $withDivision['epithet']
                          => 'fungi:' . $withDivision['taxnr']]]),
                  'found_at' => '2026-08-17'];
        $find = cloud_find_from_point($pdo, 1, $punkt);
        check($find !== null && ($find['division'] ?? '') !== '',
            'ein Synonym ohne eigene Abteilung bekommt sie aus GBIF');
    }
}

check(cloud_find_from_point($pdo, 1, ['extra_species' => '']) === null,
    'ohne bestimmte Art gibt es keinen Fund und keinen Ordner');
check(cloud_find_from_point($pdo, 1, ['extra_species' => json_encode(['' => ['Irgendwas' => 'unbekannt:1']])]) === null,
    'eine unbekannte Quelle wird nicht geraten');

echo "\nDie Schwelle für den zweiten Buchstaben\n";
check(cloud_letter_threshold() === 12, 'ohne Einstellung sind es zwölf');
$GLOBALS['__cloud_vault'] = ['letter_threshold' => 30];
check(cloud_letter_threshold() === 30, 'aus dem Tresor kommt der eingestellte Wert');
$GLOBALS['__cloud_vault'] = ['letter_threshold' => 0];
check(cloud_letter_threshold() === 2, 'unter zwei wird nicht gegangen');
$GLOBALS['__cloud_vault'] = null;

echo "\nWhat can be filed, and where\n";

/* THE REGRESSION THAT COST THE WHOLE BUTTON. cloud_find_from_point() reads
   'extra_species', and point_prepare() — the shape the browser gets — does
   not carry that field. api/v1/cloud.php handed exactly that array in, so
   'filable' came back false on every point there is, however well
   determined the find was. Nothing looked broken; the button was simply
   never there.

   The test therefore asks the two sides against each other: whatever
   cloud_find_from_point() reads out of a point must be a COLUMN of
   map_points, not an invention of the prepared shape. */
$columns = array_column($pdo->query('SHOW COLUMNS FROM map_points')->fetchAll(), 'Field');
preg_match_all(
    "/\\\$point\\[\\s*'([a-z_]+)'\\s*\\]/",
    file_get_contents(__DIR__ . '/../includes/cloud.php'),
    $hits
);
$read = array_values(array_unique($hits[1]));
check($read !== [], 'the rule reads fields off the point at all');
foreach ($read as $field) {
    check(in_array($field, $columns, true),
        "'$field' is a column of map_points — so the raw row has it");
}

/* And the endpoint has to hand in that raw row. point_load() returns the
   prepared shape; whoever puts it back is repeating the bug. */
$endpoint = file_get_contents(__DIR__ . '/../api/v1/cloud.php');
check(!preg_match('/\\$point\\s*=\\s*point_load\\s*\\(/', $endpoint),
    'api/v1/cloud.php does not fetch the point through point_load()');
check(str_contains($endpoint, 'SELECT * FROM map_points'),
    'it reads the raw row instead');

/* An open determination is filable — that is the whole point of the
   storage: whoever cannot name the species is precisely the one with
   micrographs worth keeping. The wording of 18.08.2026: inside the
   genus folder Scutellinia a subfolder Scutellinia spec., and the date
   folders in that. */
foreach (['spec.', 'sp.', 'spp', 'indet.', 'SPEC'] as $open) {
    check(cloud_epithet_open($open), "'$open' is no species");
}
foreach (['edulis', 'muscaria', 'sulcata'] as $real) {
    check(!cloud_epithet_open($real), "'$real' is one");
}

$open = cloud_path_for([
    'kingdom' => 'Fungi (Pilze)', 'group' => 'Fungi', 'division' => 'Ascomycota',
    'genus' => 'Scutellinia', 'species' => '',
    'found_at' => '2026-08-18', 'municipality' => 'Wiesbaden',
]);
check($open === ['Arten', 'Fungi (Pilze)', 'Fungi', 'Ascomycota', 'S', 'Scutellinia',
                 'Scutellinia spec.', '2026-08-18 Wiesbaden'],
    'without an epithet: Scutellinia / Scutellinia spec. / day');

$named = cloud_path_for([
    'kingdom' => 'Fungi (Pilze)', 'group' => 'Fungi', 'division' => 'Ascomycota',
    'genus' => 'Scutellinia', 'species' => 'flammea',
    'found_at' => '2026-08-18', 'municipality' => 'Wiesbaden',
]);
check($named[6] === 'S. flammea', 'with one it stays abbreviated');
check(array_slice($open, 0, 6) === array_slice($named, 0, 6),
    'and both hang under the same genus folder');

/* The two routes into the same folder. A genus that has a reference and one
   that only stands in the title must not end up in different places —
   otherwise one find is split across two folders. */
if (table_exists($pdo, 'map_tax_gbif') && table_exists($pdo, 'map_tax_dgfm')) {
    $viaName = cloud_find_from_point($pdo, 0, [
        'title' => 'Scutellinia spec.', 'extra_species' => null,
        'found_at' => '2026-08-18', 'municipality' => 'Wiesbaden',
    ]);
    check($viaName !== null, 'a find without any reference can be filed');
    check(($viaName['genus'] ?? '') === 'Scutellinia', 'the genus comes out of the name');
    check(($viaName['species'] ?? 'x') === '', 'and "spec." does not become an epithet');
    check(($viaName['division'] ?? '') === 'Ascomycota',
        'the division comes from GBIF, by the genus alone');

    $unknown = cloud_find_from_point($pdo, 0, [
        'title' => 'Irgendwas Unbekanntes', 'extra_species' => null,
        'found_at' => '2026-08-18', 'municipality' => 'Wiesbaden',
    ]);
    check($unknown === null, 'a name in no list stays unfilable — no guessed folder');
}

echo "\nGoogle Drive — the second storage\n";
require_once __DIR__ . '/../includes/gdrive.php';

/* WITHOUT A NETWORK AND WITHOUT A GOOGLE APP — what is tested here is the
   shape of what leaves the house, because every one of these details
   fails SILENTLY out there: a consent URL without access_type=offline
   yields a connection that dies within the hour and looks healthy until
   then, and an unescaped quote in a Drive search finds nothing and
   reports "no folder" instead of an error. */

// A vault only for this run: the cache in $GLOBALS is the documented
// seam (cloud_vault reads through it), nothing touches the disk.
$GLOBALS['__cloud_vault'] = [
    'key'    => base64_encode(random_bytes(32)),
    'gdrive' => ['client_id' => 'test-client', 'client_secret' => 'test-secret'],
];

$consent = gdrive_consent_url('STATE123');
check(str_starts_with($consent, GDRIVE_CONSENT . '?'), 'consent starts at Google');
check(str_contains($consent, 'access_type=offline'),
    'access_type=offline — without it no refresh token comes at all');
check(str_contains($consent, 'prompt=consent'),
    'prompt=consent — without it only the very first consent brings one');
check(str_contains($consent, 'state=STATE123'), 'the state travels along');
check(str_contains($consent, rawurlencode(GDRIVE_SCOPE)),
    'the scope is drive.file — the fence around the own folder');
check(!str_contains($consent, 'test-secret'),
    'the app secret does NOT stand in the consent URL');

echo "\nThe secret bundle\n";
$packed = cloud_tokens_pack('ACCESS', 'REFRESH');
$parsed = cloud_tokens_parse($packed);
check($parsed !== null && $parsed['access'] === 'ACCESS' && $parsed['refresh'] === 'REFRESH',
    'pack and parse are a round trip');
check(cloud_tokens_parse('einfach-ein-string') === null,
    'a plain pCloud-style token is not mistaken for a bundle');
check(cloud_tokens_parse(null) === null && cloud_tokens_parse('') === null,
    'nothing stays nothing');
check(cloud_tokens_parse('{"access":"x","refresh":""}') === null,
    'a bundle without a refresh half counts as unusable');

echo "\nThe search escaping\n";
check(gdrive_quote("Funde") === "'Funde'", 'a plain name is only quoted');
check(gdrive_quote("O'Brien") === "'O\\'Brien'",
    'a quote inside is escaped — unescaped it finds nothing, silently');
check(gdrive_quote('a\\b') === "'a\\\\b'", 'and the backslash itself too');

echo "\nOneDrive — the third storage\n";
require_once __DIR__ . '/../includes/onedrive.php';

/* The same discipline as at Google: the shape of what leaves the house,
   without a network and without a registered app. */
$GLOBALS['__cloud_vault']['onedrive'] = [
    'client_id' => 'ms-client', 'client_secret' => 'ms-secret',
];

$consent = onedrive_consent_url('STATE456');
check(str_starts_with($consent, ONEDRIVE_CONSENT . '?'), 'consent starts at Microsoft');
check(str_contains($consent, 'offline_access'),
    'offline_access — Microsofts word for "and a refresh token, please"');
check(str_contains($consent, 'state=STATE456'), 'the state travels along');
check(str_contains($consent, rawurlencode('Files.ReadWrite.AppFolder')),
    'the scope is the app folder — the fence, third shape');
check(!str_contains($consent, 'ms-secret'),
    'the app secret does NOT stand in the consent URL');
check(str_contains($consent, '/common/'),
    'the common endpoint — personal accounts and work accounts both land there');

echo "\nThe item addressing\n";
check(onedrive_item('approot') === '/me/drive/special/approot',
    "the sentinel becomes the special folder — no fetched id needed");
check(onedrive_item('01ABC!123') === '/me/drive/items/01ABC%21123',
    'a real id is escaped into the path');

echo "\nS3 — the fourth storage, the first with keys\n";
require_once __DIR__ . '/../includes/s3.php';

/* THE SIGNING KEY AGAINST AMAZON'S PUBLISHED EXAMPLE. Signature v4 is
   exact or dead: a derivation that is wrong answers 403
   SignatureDoesNotMatch at every provider, naming nothing. Amazon's
   documentation derives for the secret "wJalrXUtnFEMI…", 20150830,
   us-east-1, iam — and prints the result. A derivation that cannot
   reproduce the printed example is wrong in a way no live request would
   ever explain. */
check(bin2hex(s3_signing_key('wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY',
        '20150830', 'us-east-1', 'iam'))
    === 'c4afb1cc5771d871763a393e44b703571b55cc28424d1a5e86da6ed3c154a4b9',
    'the signing key reproduces Amazons documented example');

/* THE PRESIGNED ADDRESS AGAINST AMAZON'S PUBLISHED EXAMPLE, for the same
   reason as the key above: the query-string signature S3 pictures are
   fetched by (and later uploaded by) is right to the last digit or a 403
   that names nothing. Amazon's page on query parameters signs a GET of
   test.txt in examplebucket, 24 May 2013, one day, and prints the
   signature. Virtual-host style there, path-style in our requests — the
   arithmetic is the same, which is why host and path are parameters. */
echo "\nThe presigned address\n";
$presigned = s3_presign_path('examplebucket.s3.amazonaws.com', '/test.txt', 'AKIAIOSFODNN7EXAMPLE',
    'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'us-east-1', 'GET', 86400,
    gmmktime(0, 0, 0, 5, 24, 2013));
check(str_ends_with($presigned,
        '&X-Amz-Signature=aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404'),
    'the presigned GET reproduces Amazons documented signature');
check(str_starts_with($presigned, '/test.txt?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential='
        . 'AKIAIOSFODNN7EXAMPLE%2F20130524%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20130524T000000Z'),
    'and its query stands in the canonical order, the credential encoded');

/* WHERE THE APP LAYS ITS OWN PREVIEWS at S3, which draws none: under its
   folder in „.vorschau/<edge>/", the picture's path mirrored behind it. */
echo "\nThe S3 previews\n";
check(s3_preview_key('YASAIMAPP/', 'YASAIMAPP/Fungi/A/Amanita/IMG 1.jpg', 320)
        === 'YASAIMAPP/.vorschau/320/Fungi/A/Amanita/IMG 1.jpg',
    'the small preview mirrors the picture under .vorschau/320');
check(s3_preview_key('YASAIMAPP/', 'YASAIMAPP/Fungi/A/Amanita/IMG 1.jpg', 2000)
        === 'YASAIMAPP/.vorschau/2048/Fungi/A/Amanita/IMG 1.jpg',
    'any larger edge asks for the large one — there are two, not a range');
check(s3_preview_key('YASAIMAPP/', 'anderswo/IMG 1.jpg', 320) === 'YASAIMAPP/.vorschau/320/anderswo/IMG 1.jpg',
    'a key outside the app folder is mirrored whole');

/* DRIVE'S THUMBNAILS are sized by the „=s…" at the end of their link. */
echo "\nThe Drive previews\n";
check(gdrive_thumb_sized('https://lh3.googleusercontent.com/drive-storage/AJQW=s220', 2048)
        === 'https://lh3.googleusercontent.com/drive-storage/AJQW=s2048',
    'the edge in the link is replaced');
check(gdrive_thumb_sized('https://lh3.googleusercontent.com/x', 320) === 'https://lh3.googleusercontent.com/x=s320',
    'a link without one gets one');

echo "\nThe key encoding\n";
check(s3_encode_key('Datensicherung/Funde 2026-08.csv')
    === 'Datensicherung/Funde%202026-08.csv',
    'the space becomes %20, the slash stays the separator');
check(s3_encode_key('a+b (2).csv') === 'a%2Bb%20%282%29.csv',
    'plus and parentheses are encoded — each of them a silent 403 otherwise');

echo "\nThe endpoint cleaning\n";
check(s3_endpoint_clean('https://s3.example.com/') === 's3.example.com',
    'a pasted URL is reduced to its host');
check(s3_endpoint_clean(' S3.Example.COM ') === 's3.example.com',
    'case and stray spaces fall away');

echo "\nThe key bundle\n";
$bundle = s3_bundle_parse(s3_bundle_pack('s3.example.com', 'eu-central-1',
    'pilze', 'KEYID', 'SECRET'));
check($bundle !== null && $bundle['bucket'] === 'pilze' && $bundle['secret'] === 'SECRET',
    'pack and parse are a round trip');
check(s3_bundle_parse('{"endpoint":"x","region":"y","bucket":"z","key_id":"k"}') === null,
    'a bundle missing a piece counts as unusable');
check(s3_bundle_parse('einfach-ein-string') === null && s3_bundle_parse(null) === null,
    'nothing stays nothing');

echo "\nThe storage seam\n";
check(storage_root(['provider' => 'gdrive', 'connection' => []]) === 'root',
    "Drive's top is 'root'");
check(storage_root(['provider' => 'onedrive', 'connection' => []]) === 'approot',
    "OneDrive's is the approot sentinel");
check(storage_root(['provider' => 's3', 'connection' => []]) === '',
    "S3's is the empty prefix — there are no folders, only keys");
check(storage_root(['provider' => 'pcloud', 'connection' => []]) === 0,
    "pCloud's stays 0 — the app folder of a private app");
/* The one seam operation that is PURE for S3: a prefix springs into being
   with its first object, so ensure is string work and must cost no
   request — the sweep calls it every night. */
check(storage_folder_ensure(['provider' => 's3', 'token' => [], 'connection' => []],
        '', 'Datensicherung') === ['folderid' => 'Datensicherung/', 'created' => false],
    'ensuring an S3 folder is pure string work, no request');

unset($GLOBALS['__cloud_vault']);

echo "\nThe app's own folder (0120)\n";
check(cloud_has_own_home('gdrive') && cloud_has_own_home('s3')
      && !cloud_has_own_home('pcloud') && !cloud_has_own_home('onedrive'),
    'the app makes its own folder at Drive and S3 only — pCloud and OneDrive give one');
check(cloud_home_resolve($pdo, 0, ['provider' => 'pcloud', 'connection' => []]) === 0
      && cloud_home_resolve($pdo, 0, ['provider' => 'onedrive', 'connection' => []]) === 'approot',
    'at pCloud and OneDrive everything goes into the provider\'s app folder');
check(cloud_home_resolve($pdo, 0, ['provider' => 's3', 'token' => [], 'connection' => []]) === 'YASAIMAPP/',
    'at S3 into the prefix „YASAIMAPP/"');
check(storage_folder_ensure(['provider' => 's3', 'token' => [], 'connection' => []], 'YASAIMAPP/', 'Datensicherung')['folderid']
      === 'YASAIMAPP/Datensicherung/', 'and the backup inside it');
check(cloud_home_clean('  Meine   Funde ') === 'Meine Funde', 'a typed name: blanks tidied');
check(cloud_home_clean('Pilze/Karte') === 'Pilze Karte', 'a slash is no level — one folder, one name');
check(cloud_home_clean('Kar:te*?') === 'Karte', 'what file systems refuse goes');
$refused = false;
try { cloud_home_clean(' ./ '); } catch (InvalidArgumentException) { $refused = true; }
check($refused, 'nothing left over is refused, not saved as an empty name');

if (cloud_home_ready($pdo)) {
    // A throwaway connection — its secret decrypts to nothing, so any
    // provider asked would fail: a message proves nobody was asked.
    $pdo->exec("DELETE FROM map_users WHERE email = 'pruef-heim@pruefung.local'");
    $pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
                VALUES ('pruef-heim@pruefung.local', '', 'PRUEF', 'Heim', 'user', NOW())");
    $h = (int) $pdo->lastInsertId();
    try {
        $pdo->prepare("INSERT INTO map_cloud_accounts (user_id, provider, secret, created_at, updated_at)
                       VALUES (?, 'gdrive', 'x', NOW(), NOW())")->execute([$h]);
        check(cloud_home_name($pdo, $h) === 'YASAIMAPP', 'a new Drive connection keeps everything in „YASAIMAPP"');
        check(cloud_home_rename($pdo, $h, 'YASAIMAPP') === t('Der Ordner heißt schon „%s".', 'YASAIMAPP'),
            'the same name again changes nothing');
        check(cloud_home_rename($pdo, $h, ' Pilz/Karte ') === t('Gespeichert — der Ordner entsteht beim nächsten Durchgang als „%s".', 'Pilz Karte')
              && cloud_home_name($pdo, $h) === 'Pilz Karte',
            'before the first sweep a new name is only stored — no provider asked');
        $pdo->prepare("UPDATE map_cloud_accounts SET provider = 's3', backup_at = NOW() WHERE user_id = ?")->execute([$h]);
        check(str_contains(cloud_home_rename($pdo, $h, 'Neu'), 'Der alte Ordner bleibt liegen'),
            'S3 after a sweep: a new prefix, and the message says the old one stays');
        check(cloud_home_resolve($pdo, $h, ['provider' => 's3', 'token' => [], 'connection' => []]) === 'Neu/',
            'and the next sweep writes under the new prefix');
        $pdo->prepare("UPDATE map_cloud_accounts SET provider = 'pcloud' WHERE user_id = ?")->execute([$h]);
        $refused = false;
        try { cloud_home_rename($pdo, $h, 'Anders'); } catch (InvalidArgumentException) { $refused = true; }
        check($refused, 'at pCloud there is nothing to rename — the app folder is the provider\'s');
    } finally {
        $pdo->prepare('DELETE FROM map_cloud_accounts WHERE user_id = ?')->execute([$h]);
        $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$h]);
    }
} else {
    echo "  (migration 0120 not applied — the stored name is not tested)\n";
}

/* NO WAY THAT LEADS TO NOTHING (storage_prune_empty, 03.10.2026). The walk
   deletes folders in somebody's cloud, so what must be proven is where it
   STOPS: at the first folder that still holds something, at the app's own
   folder, at the backup's and the calibration's. pCloud is played here in
   memory — the https wrapper answers pcloud_call() as the provider would,
   including its two refusals (2005 gone, 2006 not empty) and its habit of
   calling the app folder 0 while the storey below names its true number. */
require_once __DIR__ . '/fake_pcloud.php';

echo "\nNo way that leads to nothing\n";
$side = static fn(string $n): array => ['id' => $n, 'name' => $n, 'folder' => false];
check(storage_stray_sidecars([$side('a.jpg'), $side('a.jpg.messwerte.json')]) === [],
    'a sidecar beside its picture stays');
check(storage_stray_sidecars([$side('b.jpg'), $side('a.jpg.messwerte.json')]) === ['a.jpg.messwerte.json'],
    'a sidecar whose picture is gone is a stray');
check(storage_stray_sidecars([['id' => 'f', 'name' => 'a.jpg.messwerte.json', 'folder' => true]]) === [],
    'a FOLDER of that name is nobody\'s sidecar');
check(storage_stray_sidecars([$side('.messwerte.json'), $side('notizen.json')]) === [],
    'a bare suffix and a JSON of the user\'s own are left alone');

FakePcloud::$folders = [
    100 => ['', null],
    150 => ['Datensicherung', 100],
    160 => ['Kalibrierung', 100],
    101 => ['Fungi', 100],
    102 => ['Basidiomycota', 101],
    110 => ['A', 102],
    103 => ['B', 102],
    104 => ['Boletus', 103],
    105 => ['Boletus edulis', 104],
    106 => ['2026-10-03 Lohr', 105],
];
FakePcloud::$files = [
    900 => ['IMG_1.jpg', 106],
    901 => ['IMG_1.jpg.messwerte.json', 106],
    902 => ['IMG_0.jpg.messwerte.json', 106],   // the old kind, left behind before 26.09.2026
    910 => ['Amanita.jpg', 110],
    911 => ['Amanita 2.jpg', 110],
    912 => ['Weg.jpg.messwerte.json', 110],
    950 => ['Funde 2026-10.csv', 150],
    960 => ['10x.jpg', 160],
];
$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    storage_picture_delete($pdo, 0, $fake, 900);
    check(!isset(FakePcloud::$files[900], FakePcloud::$files[901], FakePcloud::$files[902]),
        'the picture goes, its sidecar and an old stray one with it');
    check(!array_intersect_key(FakePcloud::$folders, array_flip([103, 104, 105, 106])),
        'the find\'s folder, the species, the genus and the letter go — all empty now');
    check(isset(FakePcloud::$folders[102], FakePcloud::$folders[110], FakePcloud::$folders[101]),
        'the division stays: its other letter still holds pictures');

    storage_picture_delete($pdo, 0, $fake, 910);
    check(!isset(FakePcloud::$files[912]) && isset(FakePcloud::$files[911], FakePcloud::$folders[110]),
        'a folder that keeps a picture stays — the stray sidecar in it goes anyway');

    FakePcloud::$calls = [];
    storage_picture_delete($pdo, 0, $fake, 911);
    check(!array_intersect_key(FakePcloud::$folders, array_flip([101, 102, 110])),
        'the last picture of the tree takes the whole way up with it');
    check(isset(FakePcloud::$folders[100], FakePcloud::$folders[150], FakePcloud::$folders[160]),
        'the app folder stays, and the backup and the calibration beside it');
    check(in_array('listfolder 0 nofiles', FakePcloud::$calls, true)
          && !in_array('deletefolder 100', FakePcloud::$calls, true)
          && !in_array('listfolder 100', FakePcloud::$calls, true),
        'the walk asked for the app folder\'s true number and stopped there, never knocking at it');

    storage_picture_delete($pdo, 0, $fake, 960);
    check(isset(FakePcloud::$folders[160]), 'an empty „Kalibrierung" stays — it is no way to a find');

    storage_picture_delete($pdo, 0, $fake, 999);
    check(true, 'a picture that is already gone ends quietly');
} finally {
    stream_wrapper_restore('https');
}

/* „BEARBEITUNG" (10.11.2026): the sidecars lie one storey down in the
   find's folder. The tidying looks into it, the writer writes into it, a
   deletion finds a sidecar in either place, and the one-time job carries
   the old ones down. */
echo "\nThe sidecars in „Bearbeitung“\n";
require_once __DIR__ . '/../includes/photo_cloud.php';
require_once __DIR__ . '/../includes/sidecar_move.php';
$sub = static function (int $parent): ?int {
    foreach (FakePcloud::$folders as $id => [$n, $p]) {
        if ($p === $parent && $n === SIDECAR_FOLDER) {
            return $id;
        }
    }
    return null;
};
FakePcloud::reset([
    100 => ['', null],
    104 => ['Boletus', 100],
    105 => ['Boletus edulis', 104],
    106 => ['2026-10-03 Lohr', 105],
    107 => [SIDECAR_FOLDER, 106],
], [
    900 => ['01 Hauptmotiv.jpg', 106],
    901 => ['02 Sporen.jpg', 106],
    902 => ['01 Hauptmotiv.jpg.messwerte.json', 107],
    903 => ['Weg.jpg.messwerte.json', 107],          // a stray down there
    904 => ['02 Sporen.jpg.messwerte.json', 106],    // an old one, beside its picture
]);
stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    $found = storage_sidecars_of($fake, 106, '01 Hauptmotiv.jpg');
    check(count($found) === 1 && (int) $found[0]['fileid'] === 902 && !$found[0]['old'], 'a sidecar is found down in „Bearbeitung“');
    $found = storage_sidecars_of($fake, 106, '02 Sporen.jpg');
    check(count($found) === 1 && (int) $found[0]['fileid'] === 904 && $found[0]['old'], 'and an old one beside its picture');

    measure_sidecar_write($fake, '901', ['um_per_px' => 1.0, 'width' => 100], [], 100, 80, 'Sporen');
    check(in_array('02 Sporen.jpg.messwerte.json', FakePcloud::names(107), true),
        'the writer writes into „Bearbeitung“, under the picture\'s name');

    sidecar_move_folder($fake, '106');
    check(FakePcloud::names(106) === ['01 Hauptmotiv.jpg', '02 Sporen.jpg'] && !isset(FakePcloud::$files[904]),
        'the job: the old one goes — a newer lies down there already');
    check(json_decode(FakePcloud::$bytes[array_search(['02 Sporen.jpg.messwerte.json', 107], FakePcloud::$files, true)] ?? '{}', true)['title'] === 'Sporen',
        'and the newer one is the one that stays');

    FakePcloud::$files[905] = ['03 Lamellen.jpg', 106];
    FakePcloud::$files[906] = ['03 Lamellen.jpg.messwerte.json', 106];
    FakePcloud::$files[907] = ['Fort.jpg.messwerte.json', 106];
    $n = sidecar_move_folder($fake, '106');
    check($n === 2 && FakePcloud::$files[906] === ['03 Lamellen.jpg.messwerte.json', 107] && !isset(FakePcloud::$files[907]),
        'the job: an old one alone is MOVED down unchanged, a stray beside is deleted');
    check(sidecar_move_folder($fake, '106') === 0, 'a folder already tidy costs one listing and changes nothing');

    measure_sidecar_write($fake, '905', null, [], null, null);
    check(!isset(FakePcloud::$files[906]), 'reset: the sidecar goes from „Bearbeitung“');

    storage_picture_delete($pdo, 0, $fake, 900);
    check(!isset(FakePcloud::$files[903]) && isset(FakePcloud::$folders[107]),
        'the tidying drops a stray inside „Bearbeitung“ — the folder stays while a sidecar of a picture is in it');
    storage_sidecar_delete($fake, 106, '01 Hauptmotiv.jpg');
    storage_picture_delete($pdo, 0, $fake, 905);
    storage_picture_delete($pdo, 0, $fake, 901);
    check(!array_intersect_key(FakePcloud::$folders, array_flip([104, 105, 106, 107])),
        'the last picture takes „Bearbeitung“, the find\'s folder and the way up with it');

    // Only a sidecar left in „Bearbeitung“: deleting it takes the folder along.
    FakePcloud::reset([100 => ['', null], 106 => ['Tag', 100], 107 => [SIDECAR_FOLDER, 106]],
        [900 => ['a.jpg', 106], 901 => ['a.jpg.messwerte.json', 107]]);
    storage_sidecar_delete($fake, 106, 'a.jpg');
    check(!isset(FakePcloud::$folders[107]) && isset(FakePcloud::$folders[106]),
        'an emptied „Bearbeitung“ goes — no empty folder per reset picture');
} finally {
    stream_wrapper_restore('https');
}
check(storage_folder_delete_empty(['provider' => 'gdrive', 'token' => 'x', 'connection' => [], 'host' => null], 'f') === false,
    'at Google Drive no folder is deleted — the app cannot see what the user laid in by hand');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
