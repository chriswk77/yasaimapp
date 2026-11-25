<?php
/**
 * THE FUNDBUCH — one's released finds, handed to a small package on one's
 * own homepage (migration 0202, api/v1/publication.php).
 *
 * WHERE IT COMES FROM. The first public find book was built into this
 * installation: its pages read this database and this code directly and
 * therefore could stand only on the same server. On 25.11.2026 the question
 * was how OTHER users get one on their own homepage, and where the pictures
 * lie then: „Kann sich die externe Seite dann die Fotos aus der Cloud
 * holen? Über unseren Server, der den Schlüssel aufbewahrt …" — and the
 * call: „dann bau mal als Paket um und wir testen es dann direkt bei WK
 * Pilz."
 *
 * THE PACKAGE FETCHES, THIS SERVER HANDS OUT ONCE. A find book is a folder
 * of a few PHP files on anybody's webspace, without a database. It asks
 * this file for the list of released finds and for each plate page it does
 * not hold yet; this server fetches that page from the owner's cloud —
 * only it holds the cloud's key —, makes it ready and passes it through.
 * From then on the page lies on the owner's webspace: his pictures on his
 * site, under his Impressum, and no visitor of his ever reaches this
 * server. THREE ALTERNATIVES WERE DISCARDED: hosting everybody's find book
 * here (the pictures would lie on this server — against „no cloud, no
 * copy" —, every page view would land here, and the operator would be the
 * host of other people's public pages); relaying each picture per visitor
 * (slow, and the clouds throttle it); public cloud links in the page (they
 * expire or are blocked). NOTHING IS KEPT HERE on the way: the bytes pass
 * through the request („ein Auslieferungsordner … danach werden die Bilder
 * wieder gelöscht" was the first thought; passing through needs no folder
 * and leaves nothing to sweep).
 *
 * ITS OWN KEY. The package carries a key in a file on a web server, so it
 * is NOT a device token (map_tokens is the whole account): it reads what
 * its owner released and nothing else. Only its hash is kept; the key is
 * shown once, in the settings (publication_content.php).
 *
 * WHAT IS PUBLIC — decided here, once, for every find book:
 *   - a find of the owner, not in the trash, whose plate he RELEASED in
 *     the find's head (plate_publish.php) and whose other photographers he
 *     confirmed;
 *   - with a species reference on its title — which leaves out open names,
 *     „cf.", „s. l.", „spec." (20.11.2026: „so lassen") — and a
 *     „Bestimmungssicherheit" that says sicher (the same positive list the
 *     picture stamps use);
 *   - THE PLATE IS THE PICTURE (20.11.2026: „zeig mir nur die Tafeln und
 *     nur Funde mit Tafeln"): it carries the edits already, drawn by the
 *     plate editor; no single photo goes out;
 *   - of the place: the municipality, state, country and the
 *     Messtischblatt with its QUARTER, about 5.5 × 5.5 km (21.11.2026:
 *     „Das macht die DGFM auch mit ihren Funden. Das passt schon an
 *     Grobheit") — never the finer parts, never a coordinate. What a PLATE
 *     prints is its maker's choice and goes out as it is (20.11.2026:
 *     „nicht aussortieren. Das ist dann bewusst gewählt").
 *
 * A CONTRACT: the package is a client on somebody else's server and cannot
 * be redeployed with a push. Fields are added, never renamed; `v` says the
 * version.
 */

declare(strict_types=1);

require_once __DIR__ . '/plate_publish.php';

const PUBLICATION_VERSION = 1;
/** Long edge of a tile, and the most a plate page keeps — it is text and lines, so it stays large. */
const PUBLICATION_EDGE_SMALL = 720;
const PUBLICATION_EDGE_LARGE = 2600;
const PUBLICATION_JPEG_QUALITY = 84;
/**
 * Part of every picture's name. Raised when the way a picture is made
 * changes, so every find book fetches its pictures anew.
 */
const PUBLICATION_FORMAT = 1;

/* ONLY WHAT IS SURELY NAMED (20.11.2026, after a bare genus stood on the
   page: „steht bestimmt auf unbestimmt statt auf makroskopisch,
   mikroskopisch oder genetisch sicher"). A genus resolves to a species
   reference as well, so the reference alone let it through; the find's own
   „Bestimmungssicherheit" is what says it is done. */
const PUBLICATION_CERTAIN_FIELD = 'Bestimmungssicherheit';
const PUBLICATION_CERTAIN_LEVELS = ['makroskopisch sicher', 'mikroskopisch sicher', 'genetisch sicher'];

const PUBLICATION_STATE_NAMES = [
    'BAW' => 'Baden-Württemberg', 'BAY' => 'Bayern', 'BLN' => 'Berlin',
    'BRB' => 'Brandenburg', 'BRM' => 'Bremen', 'HAM' => 'Hamburg',
    'HES' => 'Hessen', 'MVP' => 'Mecklenburg-Vorpommern', 'NDS' => 'Niedersachsen',
    'NRW' => 'Nordrhein-Westfalen', 'RLP' => 'Rheinland-Pfalz', 'SAL' => 'Saarland',
    'SAC' => 'Sachsen', 'SAH' => 'Sachsen-Anhalt', 'SLH' => 'Schleswig-Holstein',
    'THU' => 'Thüringen',
];
const PUBLICATION_COUNTRY_NAMES = [
    'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz', 'IT' => 'Italien',
    'FR' => 'Frankreich', 'NL' => 'Niederlande', 'BE' => 'Belgien', 'LU' => 'Luxemburg',
    'DK' => 'Dänemark', 'PL' => 'Polen', 'CZ' => 'Tschechien', 'ES' => 'Spanien',
    'PT' => 'Portugal', 'SE' => 'Schweden', 'NO' => 'Norwegen', 'FI' => 'Finnland',
    'SI' => 'Slowenien', 'HR' => 'Kroatien', 'GB' => 'Vereinigtes Königreich',
];

// ---------------------------------------------------------------------------
// The registration — whose find book stands where
// ---------------------------------------------------------------------------

function publication_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_publications') && plate_publish_ready($pdo);
}

/** The account's find book, or null. */
function publication_of(PDO $pdo, int $userId): ?array
{
    if (!publication_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_publications WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/** The find book a key opens, or null — and the moment it asked is noted. */
function publication_by_key(PDO $pdo, string $key): ?array
{
    if ($key === '' || !publication_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_publications WHERE key_hash = ?');
    $stmt->execute([hash('sha256', $key)]);
    return $stmt->fetch() ?: null;
}

/**
 * Where this account's finds go public, as the find's head names it
 * („example.org") — or null: he has no find book, and nothing about
 * publishing is shown to him.
 */
function publication_site_label(PDO $pdo, ?array $user): ?string
{
    if ($user === null) {
        return null;
    }
    $book = publication_of($pdo, (int) $user['id']);
    return $book !== null ? publication_host((string) $book['site_url']) : null;
}

/** The site's host as the find's head names it („example.org"). */
function publication_host(string $siteUrl): string
{
    return preg_replace('/^www\./', '', (string) parse_url($siteUrl, PHP_URL_HOST));
}

/**
 * A homepage's address as it is kept: https://host/path/ — with the scheme
 * added where it was left out and one slash at the end, so „impressum.php"
 * can be hung on. Throws InvalidArgumentException for the user.
 */
function publication_url(string $typed): string
{
    $url = trim($typed);
    if ($url !== '' && !preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    $p = parse_url($url);
    if ($p === false || empty($p['host']) || !str_contains($p['host'], '.') || isset($p['query']) || isset($p['fragment'])
        || isset($p['user']) || strlen($url) > 250) {
        throw new InvalidArgumentException(t('Das ist keine Adresse einer Homepage. Ein Beispiel: https://beispiel.de/'));
    }
    return strtolower($p['scheme']) . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '')
        . rtrim($p['path'] ?? '', '/') . '/';
}

/**
 * Set up the account's find book, or give it a new address. A NEW KEY is
 * minted the first time and whenever $newKey asks — the old one stops
 * working that moment. Returns the key where one was minted, else null:
 * it cannot be shown again later, only its hash is kept.
 */
function publication_save(PDO $pdo, int $userId, string $typedUrl, bool $newKey = false): ?string
{
    $url = publication_url($typedUrl);
    $has = publication_of($pdo, $userId);
    if ($has !== null && !$newKey) {
        $pdo->prepare('UPDATE map_publications SET site_url = ? WHERE user_id = ?')->execute([$url, $userId]);
        return null;
    }
    $key = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO map_publications (user_id, site_url, key_hash, created_at) VALUES (?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE site_url = VALUES(site_url), key_hash = VALUES(key_hash)')
        ->execute([$userId, $url, hash('sha256', $key)]);
    return $key;
}

/** The find book ends: the key stops working. What lies on the homepage stays there — it is the owner's webspace. */
function publication_remove(PDO $pdo, int $userId): void
{
    $pdo->prepare('DELETE FROM map_publications WHERE user_id = ?')->execute([$userId]);
}

// ---------------------------------------------------------------------------
// What is public
// ---------------------------------------------------------------------------

/**
 * The released finds of an account, read from the database alone — no
 * cloud. Each plate page carries the name its files have at the find book
 * (`k`, a hash of WHICH page in WHICH state and under WHICH credit: a
 * redrawn plate, a changed addition makes a new name, so no visitor's
 * browser keeps the old picture — and nothing in it says where the page
 * lies in the cloud) and the id to ask this server for it (`id`).
 *
 * @return array{finds: list<array>, notes: list<string>}
 */
function publication_plan(PDO $pdo, int $userId, string $siteUrl): array
{
    require_once __DIR__ . '/species.php';
    require_once __DIR__ . '/point_keyings.php';

    $notes = [];
    $q = $pdo->prepare(
        'SELECT p.id, p.title, p.found_at, p.municipality, p.region, p.country, p.mtb, p.find_number, p.extra_species, p.extra, p.note, p.mtb_quadrant, p.elevation_m, p.protected_area
           FROM map_points p
          WHERE p.user_id = ? AND p.deleted_at IS NULL
            AND EXISTS (SELECT 1 FROM map_plates pl JOIN map_plate_images i ON i.plate_id = pl.id
                         WHERE pl.point_id = p.id AND pl.user_id = p.user_id)'
    );
    $q->execute([$userId]);
    $points = [];
    foreach ($q as $row) {
        $points[(int) $row['id']] = $row;
    }
    if ($points === []) {
        return ['finds' => [], 'notes' => ['Keine Funde mit Tafel gefunden.']];
    }
    $ids = array_keys($points);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    /* ONLY WHAT WAS RELEASED, UNDER ITS LICENCE (plate_publish.php). Asked
       anew on every call, so a friend's picture added after the release
       takes the plate out until he is confirmed. */
    $rights = [];
    $q = $pdo->prepare("SELECT * FROM map_plates WHERE user_id = ? AND point_id IN ($in)");
    $q->execute([$userId, ...$ids]);
    foreach ($q as $plate) {
        $state = plate_publish_state($pdo, $plate);
        $point = $points[(int) $plate['point_id']];
        if (!$state['shown']) {
            $waiting = array_map(static fn(array $o): string => $o['name'],
                array_filter($state['others'], static fn(array $o): bool => !$o['cleared']));
            $notes[] = sprintf('%s „%s": %s', publication_find_label($point), trim((string) $point['title']), !$state['published']
                ? 'Tafel nicht freigegeben.'
                : 'Tafel wartet auf das Einverständnis von ' . implode(', ', $waiting) . '.');
            continue;
        }
        $rights[(int) $plate['id']] = publication_rights($state);
    }

    // The plate pages, in the plate's own order.
    $plates = [];
    $q = $pdo->prepare("SELECT pl.id AS plate_id, pl.point_id, i.page, i.fileid, i.hash, i.width, i.height, i.updated_at
                          FROM map_plates pl JOIN map_plate_images i ON i.plate_id = pl.id
                         WHERE pl.user_id = ? AND pl.point_id IN ($in)
                         ORDER BY pl.id, i.page");
    $q->execute([$userId, ...$ids]);
    foreach ($q as $r) {
        $right = $rights[(int) $r['plate_id']] ?? null;
        if ($right === null) {
            continue;
        }
        $plates[(int) $r['point_id']][] = [
            'id' => 't' . $r['plate_id'] . '-' . $r['page'],
            'k'  => publication_picture_key($r, $right, $siteUrl),
            'w'  => (int) $r['width'],
            'h'  => (int) $r['height'],
            'rights' => $right,
        ];
    }

    // German names in one sweep.
    $refs = [];
    foreach ($points as $id => $p) {
        $species = json_decode((string) ($p['extra_species'] ?? ''), true);
        $ref = is_array($species[''] ?? null) ? (string) (array_values($species[''])[0] ?? '') : '';
        $points[$id]['ref'] = $ref;
        $extra = json_decode((string) ($p['extra'] ?? ''), true);
        $points[$id]['certainty'] = trim((string) (is_array($extra) ? ($extra[PUBLICATION_CERTAIN_FIELD] ?? '') : ''));
        if ($ref !== '') {
            $refs[] = $ref;
        }
    }
    $german = $refs === [] ? [] : species_german_names($pdo, array_values(array_unique($refs)));

    $finds = [];
    foreach ($points as $id => $p) {
        $title = trim((string) $p['title']);
        if (($plates[$id] ?? []) === []) {
            continue;
        }
        if ($p['ref'] === '') {
            $notes[] = sprintf('%s „%s": kein Artbezug — nicht veröffentlicht.', publication_find_label($p), $title);
            continue;
        }
        if (!in_array($p['certainty'], PUBLICATION_CERTAIN_LEVELS, true)) {
            $notes[] = sprintf('%s „%s": Bestimmungssicherheit „%s" — nicht veröffentlicht.',
                publication_find_label($p), $title, $p['certainty'] === '' ? 'leer' : $p['certainty']);
            continue;
        }
        $day = substr((string) ($p['found_at'] ?? ''), 0, 10);
        $country = strtoupper(trim((string) ($p['country'] ?? '')));
        $sheet = trim((string) ($p['mtb'] ?? ''));
        $inGrid = $country === 'DE' && preg_match('/^\d{4}$/', $sheet) === 1;
        $finds[] = [
            'id'      => publication_find_label($p),
            'name'    => $title,
            'german'  => $german[$p['ref']] ?? '',
            'genus'   => strtok($title, ' ') ?: $title,
            'date'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : '',
            'place'   => trim((string) ($p['municipality'] ?? '')),
            'state'   => PUBLICATION_STATE_NAMES[strtoupper(trim((string) ($p['region'] ?? '')))] ?? '',
            'country' => PUBLICATION_COUNTRY_NAMES[$country] ?? $country,
            'certainty' => $p['certainty'],
            // The SHEET, four digits — and only in Germany, where the grid exists.
            'mtb'     => $inGrid ? $sheet : '',
            // Its QUARTER (1 NW, 2 NE, 3 SW, 4 SE) — the first digit of the stored quadrant, never the finer ones.
            'quarter' => $inGrid && preg_match('/^([1-4])/', trim((string) ($p['mtb_quadrant'] ?? '')), $m) ? $m[1] : '',
            'details' => publication_find_details($p),
            'keyings' => publication_find_keyings($pdo, $userId, (int) $id),
            'plates'  => array_map(static fn(array $pg): array => ['id' => $pg['id'], 'k' => $pg['k'], 'w' => $pg['w'], 'h' => $pg['h']], $plates[$id]),
            // One plate per find and maker (plates.php), so one credit per find.
            'rights'  => $plates[$id][0]['rights'],
        ];
    }
    usort($finds, static fn(array $a, array $b): int => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    return ['finds' => $finds, 'notes' => $notes];
}

/** A plate's credit as the find book shows it and the file carries it. */
function publication_rights(array $state): array
{
    return [
        'by'      => $state['credit'],
        'licence' => $state['licence'],
        'url'     => $state['licence_url'],
        'note'    => $state['note'],
    ];
}

/** The name of a page's files at the find book — see publication_plan. $page: a row of map_plate_images. */
function publication_picture_key(array $page, array $rights, string $siteUrl): string
{
    return substr(hash('sha256', implode('|', [
        'fundbuch', PUBLICATION_FORMAT, $page['plate_id'], $page['page'], $page['fileid'], $page['hash'], $page['updated_at'],
        json_encode($rights, JSON_UNESCAPED_UNICODE), $siteUrl,
    ])), 0, 20);
}

/* EVERYTHING THE FIND SAYS, BUT WHERE IT LIES (20.11.2026: „Finder und
   Bestimmer mit angeben. Einfach alles, was wir haben. Auch Schlüsselwege.
   Jegliche Information, die da drin steht"). Every field of the find as
   it is kept in `extra` — the fixed ones in the order the point
   window shows them, then whatever a list adds of its own — and the
   height, the protected area, the note. THE COORDINATE STAYS HOME, and so
   does the MTB quadrant: what this file adds of the place is the
   municipality and the sheet, as decided when the site began. The
   „Bestimmungssicherheit" is not repeated here; it has its own row. */
const PUBLICATION_DETAIL_ORDER = ['Finder', 'Bestimmer', 'Anzahl / Menge', 'Substrat / Wirt', 'Begleitvegetation',
    'Geruch', 'Geschmack', 'Sporenpulverfarbe', 'Beleg-Nr.', 'Beleg aufbewahrt', 'Bemerkung'];

/** @return list<array{0: string, 1: string, 2?: string}> label, value, and for the spore print its colour */
function publication_find_details(array $p): array
{
    $extra = json_decode((string) ($p['extra'] ?? ''), true);
    $extra = is_array($extra) ? $extra : [];
    unset($extra[PUBLICATION_CERTAIN_FIELD]);
    $text = static function ($v): string {
        if (is_bool($v)) return $v ? 'ja' : 'nein';
        if (is_array($v)) return implode(', ', array_filter(array_map(static fn($x) => is_scalar($x) ? trim((string) $x) : '', $v)));
        return is_scalar($v) ? trim((string) $v) : '';
    };
    $out = [];
    $keys = [...array_intersect(PUBLICATION_DETAIL_ORDER, array_keys($extra)), ...array_diff(array_keys($extra), PUBLICATION_DETAIL_ORDER)];
    foreach ($keys as $key) {
        $value = $text($extra[$key]);
        if ($value === '') continue;
        // The spore print is stored as „group: words #rrggbb" (findtraits.js) — the words, and the colour as a square.
        if ($key === 'Sporenpulverfarbe' && preg_match('/^(.+?):\s*(.*?)\s*(#[0-9a-f]{6})$/i', $value, $m)) {
            $out[] = [(string) $key, trim($m[2]) !== '' ? trim($m[2]) : trim($m[1]), strtoupper($m[3])];
            continue;
        }
        $out[] = [(string) $key, $value];
    }
    if ($p['elevation_m'] !== null && $p['elevation_m'] !== '') {
        $out[] = ['Höhe', (int) round((float) $p['elevation_m']) . ' m'];
    }
    if (trim((string) ($p['protected_area'] ?? '')) !== '') {
        $out[] = ['Schutzgebiet', trim((string) $p['protected_area'])];
    }
    if (trim((string) ($p['note'] ?? '')) !== '') {
        $out[] = ['Notiz', trim((string) $p['note'])];
    }
    return $out;
}

/**
 * THE KEY WAYS — the confirmed ones, as a plate gets them
 * (point_keyings_plate): book, key, the couplets walked („1a → 4b"), the
 * result and the remarks written on the way. Numbers and names only, no
 * text of the book. Another keyer's way carries his name.
 */
function publication_find_keyings(PDO $pdo, int $userId, int $pointId): array
{
    if (!function_exists('point_keyings_plate')) return [];
    try {
        $ways = point_keyings_plate($pdo, $userId, $pointId);
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($ways as $w) {
        $keys = array_values(array_filter(array_map(static fn($k) => trim((string) ($k['title'] ?? '')), (array) ($w['keys'] ?? []))));
        $out[] = [
            'book'    => trim((string) ($w['book'] ?? '')),
            'keys'    => $keys !== [] ? $keys : array_values(array_filter([trim((string) ($w['key'] ?? ''))])),
            'steps'   => array_values(array_map(static fn($s) => trim((string) ($s['label'] ?? '')) . (empty($s['sure']) ? '?' : ''), (array) ($w['steps'] ?? []))),
            'result'  => trim((string) ($w['result'] ?? '')),
            'remarks' => array_values(array_filter(array_map(static fn($r) => trim((string) ($r['text'] ?? '')), (array) ($w['remarks'] ?? [])))),
            'by'      => empty($w['mine']) ? trim((string) ($w['by'] ?? '')) : '',
        ];
    }
    return $out;
}

/** The find's public label — its number („Y-00033"), or a neutral one built from the id. */
function publication_find_label(array $p): string
{
    $n = trim((string) ($p['find_number'] ?? ''));
    return $n !== '' ? $n : 'fund-' . (int) $p['id'];
}

// ---------------------------------------------------------------------------
// One plate page, made ready and passed through
// ---------------------------------------------------------------------------

/**
 * One released plate page for the find book: fetched from the owner's
 * cloud, made ready, returned — and kept nowhere here.
 *
 * THE LARGE ONE AS IT LIES IN THE CLOUD (25.11.2026: „nimm die Datei
 * unverändert aus der Cloud"). A page is drawn at 200 dpi, 1654 × 2339,
 * under PUBLICATION_EDGE_LARGE — fitting it would change no pixel count and
 * only compress it a second time, which softens small print. So a JPEG that
 * needs no shrinking and no turning goes out byte for byte in its picture
 * data, only without its metadata blocks (publication_jpeg_plain). The
 * small one is shrunk for the tiles. Both carry the names and the licence
 * (publication_xmp).
 *
 * @param string $id  „t<plate>-<page>" as the plan names it
 * @return array{k: string, large: string, small: string}|null  null: no such released page of this account
 */
function publication_picture(PDO $pdo, int $userId, string $siteUrl, string $id): ?array
{
    if (!preg_match('/^t(\d+)-(\d+)$/', $id, $m)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_plates WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $m[1], $userId]);
    $plate = $stmt->fetch();
    if ($plate === false) {
        return null;
    }
    $state = plate_publish_state($pdo, $plate);
    if (!$state['shown']) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT i.*, i.plate_id FROM map_plate_images i WHERE i.plate_id = ? AND i.page = ?');
    $stmt->execute([(int) $m[1], (int) $m[2]]);
    $page = $stmt->fetch();
    if ($page === false) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT title, found_at FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([(int) $plate['point_id'], $userId]);
    $point = $stmt->fetch();
    if ($point === false) {
        return null;
    }

    require_once __DIR__ . '/cloud.php';
    $storage = cloud_storage_for($pdo, $userId, (string) ($page['provider'] ?: 'pcloud'));
    if ($storage === null) {
        throw new RuntimeException('Die Cloud ist nicht verbunden.');
    }
    $bytes = storage_file_bytes($storage, (string) $page['fileid'], 120_000_000);

    $rights = publication_rights($state);
    $xmp = publication_xmp($rights, trim((string) $point['title']), substr((string) $point['found_at'], 0, 4), $siteUrl);
    ['large' => $large, 'small' => $small] = publication_picture_make($bytes, $xmp);
    return ['k' => publication_picture_key($page, $rights, $siteUrl), 'large' => $large, 'small' => $small];
}

/**
 * The two files of a picture from its original bytes.
 *
 * @return array{large: string, small: string}
 */
function publication_picture_make(string $bytes, string $xmp): array
{
    // thumb_upright, picture_icc and jpeg_with_icc; PICTURE_DECODE_PIXELS.
    require_once __DIR__ . '/thumbs.php';
    require_once __DIR__ . '/picture_fence.php';
    @ini_set('memory_limit', '1024M');
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info) || $info[0] < 1) {
        throw new RuntimeException('Kein lesbares Bild.');
    }
    $mime = (string) $info['mime'];
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Bildformat ' . $mime . ' wird nicht verarbeitet.');
    }
    if ($info[0] * $info[1] > PICTURE_DECODE_PIXELS) {
        throw new RuntimeException('Das Bild ist zu groß zum Verkleinern.');
    }
    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        throw new RuntimeException('Das Bild ließ sich nicht öffnen.');
    }
    $image = thumb_upright($image, $bytes, $mime);
    $icc = picture_icc($bytes, $mime);

    $asIs = $mime === 'image/jpeg' && max($info[0], $info[1]) <= PUBLICATION_EDGE_LARGE
        && publication_jpeg_upright($bytes) ? publication_jpeg_plain($bytes) : null;

    $large = publication_image_fit($image, PUBLICATION_EDGE_LARGE);
    imagedestroy($image);
    $small = publication_image_fit($large, PUBLICATION_EDGE_SMALL);
    $out = [];
    foreach (['large' => $large, 'small' => $small] as $size => $img) {
        if ($size === 'large' && $asIs !== null) {
            $jpeg = $asIs;
        } else {
            ob_start();
            imageinterlace($img, true);
            imagejpeg($img, null, PUBLICATION_JPEG_QUALITY);
            $jpeg = (string) ob_get_clean();
            if ($icc !== null) {
                $jpeg = jpeg_with_icc($jpeg, $icc);
            }
        }
        $out[$size] = $xmp !== '' ? publication_jpeg_with_xmp($jpeg, $xmp) : $jpeg;
    }
    imagedestroy($small);
    imagedestroy($large);
    return $out;
}

/**
 * WHOSE IT IS AND UNDER WHAT LICENCE, INSIDE THE FILE (25.11.2026). A plate
 * saved from a find book and passed on keeps its names and its licence, as
 * the licence asks of everybody who passes it on. XMP, the block every
 * picture program reads: the photographers as creators, a rights line, the
 * licence's address, and — as Creative Commons provides for it — where to
 * ask for more: the find book's Impressum („dass kommerzielle Nutzung
 * nicht von vornherein ausgeschlossen ist, sondern ich darüber halt
 * Bescheid wissen will und das dann von Fall zu Fall selbst freigeben
 * will"). No place, no coordinate.
 */
function publication_xmp(array $rights, string $title, string $year, string $siteUrl): string
{
    $x = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $names = implode(', ', $rights['by']);
    $more = $siteUrl . 'impressum.php#lizenz';
    $terms = $rights['licence'] . ' — ' . $rights['url'] . '. Kommerzielle Nutzung auf Anfrage: ' . $more
        . ($rights['note'] !== '' ? '. ' . $rights['note'] : '');
    $creators = implode('', array_map(static fn(string $n): string => '<rdf:li>' . $x($n) . '</rdf:li>', $rights['by']));
    return '<?xpacket begin="' . "\u{FEFF}" . '" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/" xmlns:cc="http://creativecommons.org/ns#">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . $x($title) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq>' . $creators . '</rdf:Seq></dc:creator>'
        . '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">' . $x('© ' . ($year !== '' ? $year . ' ' : '') . $names . ' · ' . $rights['licence']) . '</rdf:li></rdf:Alt></dc:rights>'
        . '<xmpRights:Marked>True</xmpRights:Marked>'
        . '<xmpRights:WebStatement>' . $x($rights['url']) . '</xmpRights:WebStatement>'
        . '<xmpRights:UsageTerms><rdf:Alt><rdf:li xml:lang="x-default">' . $x($terms) . '</rdf:li></rdf:Alt></xmpRights:UsageTerms>'
        . '<cc:license rdf:resource="' . $x($rights['url']) . '"/>'
        . '<cc:attributionName>' . $x($names) . '</cc:attributionName>'
        . '<cc:morePermissions rdf:resource="' . $x($more) . '"/>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
}

/** An XMP packet into a JPEG, right after its JFIF block (or its start) — the place readers look first. */
function publication_jpeg_with_xmp(string $jpeg, string $xmp): string
{
    $segment = "http://ns.adobe.com/xap/1.0/\0" . $xmp;
    if (strlen($segment) + 2 > 0xFFFF || substr($jpeg, 0, 2) !== "\xFF\xD8") {
        return $jpeg;
    }
    $at = 2;
    if (substr($jpeg, 2, 2) === "\xFF\xE0") {
        $at = 4 + ((ord($jpeg[4]) << 8) | ord($jpeg[5]));
    }
    return substr($jpeg, 0, $at) . "\xFF\xE1" . pack('n', strlen($segment) + 2) . $segment . substr($jpeg, $at);
}

/**
 * A JPEG without its metadata, the picture data untouched — or null when
 * the file does not parse as the plain marker sequence it should be (then
 * the caller re-encodes). KEPT: APP0 (JFIF, the dpi), APP2 (the colour
 * profile, or the colours change), APP14 (Adobe, says how the colours are
 * coded) and everything that IS the picture — tables, frame, scan. GONE:
 * APP1 (EXIF and XMP, where a GPS position lives), the other APPn and
 * comments. A file turned by an EXIF orientation never comes here
 * (publication_jpeg_upright), since without its EXIF it would stand on its side.
 */
function publication_jpeg_plain(string $bytes): ?string
{
    $n = strlen($bytes);
    if ($n < 4 || substr($bytes, 0, 2) !== "\xFF\xD8") {
        return null;
    }
    $out = "\xFF\xD8";
    $i = 2;
    while ($i + 4 <= $n) {
        if ($bytes[$i] !== "\xFF") {
            return null;
        }
        $marker = ord($bytes[$i + 1]);
        if ($marker === 0xFF) {          // fill byte before a marker
            $i++;
            continue;
        }
        if ($marker === 0xDA) {          // start of scan: the rest is the picture
            return $out . substr($bytes, $i);
        }
        $len = (ord($bytes[$i + 2]) << 8) | ord($bytes[$i + 3]);
        if ($len < 2 || $i + 2 + $len > $n) {
            return null;
        }
        $isMeta = $marker === 0xFE || ($marker >= 0xE1 && $marker <= 0xEF && $marker !== 0xE2 && $marker !== 0xEE);
        if (!$isMeta) {
            $out .= substr($bytes, $i, 2 + $len);
        }
        $i += 2 + $len;
    }
    return null;
}

/** Whether a JPEG stands as stored — no EXIF orientation that turns or mirrors it (thumb_upright's question). */
function publication_jpeg_upright(string $bytes): bool
{
    if (!function_exists('exif_read_data')) {
        return true;    // then thumb_upright turns nothing either
    }
    $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
    return (int) ($exif['Orientation'] ?? 1) <= 1;
}

/** A copy fitted into $edge — or the picture itself when it is already smaller. */
function publication_image_fit(GdImage $image, int $edge): GdImage
{
    $w = imagesx($image);
    $h = imagesy($image);
    $scale = min(1.0, $edge / max($w, $h));
    $tw = max(1, (int) round($w * $scale));
    $th = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($tw, $th);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $image, 0, 0, 0, 0, $tw, $th, $w, $h);
    return $out;
}
