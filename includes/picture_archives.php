<?php
/**
 * PICTURE ARCHIVES — everybody's older pictures (01.11.2026,
 * migration 0181).
 *
 * Whoever kept fungus pictures before YASAIMAPP shares the folder in his
 * cloud and pastes the link under Verwaltung → Bildarchive. The
 * server reads the folder, works out which species every picture shows and
 * keeps that reading (map_archive_pictures). The owner and his FRIENDS see
 * the pictures in the Bestimmung, tab „Funde", below the real finds
 * („Ältere Bilder"); later the Fundbrowser. A GALLERY, NOT FINDS: nothing
 * here writes a point, and the folder is read, never changed.
 *
 * THE LINK, NOT THE CONNECTED CLOUD. Every connection is fenced to an app
 * folder (gdrive.php: „THE SCOPE IS THE FENCE") and cannot see a library
 * that lay there before; widening the fence would remove exactly what makes
 * a stolen token harmless. So the library comes in by its share link —
 * pCloud only, for now; Google Drive and MagentaCLOUD (Nextcloud) are the
 * next stages. The link opens every picture behind it, so it is kept
 * encrypted (cloud_encrypt) and no page ever gets it back.
 *
 * NOTHING ABOUT THE FOLDER IS KEPT FOR GOOD. The maintainer's cloud copy is a
 * backup of his local disk, renamed there and synced up; anybody's folder
 * may change. A scan therefore replaces an archive's rows whole — daily by
 * cron, and at once when the owner adds the archive, presses „Neu
 * einlesen" or names what was not recognised. What survives a scan is the
 * owner's word on names (map_archive_names).
 *
 * HOW A SPECIES IS FOUND — not from one person's layout (
 * „mit meiner Ordnerstruktur muss nicht gleich bei anderen Leuten so
 * aussehen … irgendwo im Dateinamen oder im Ordner wird schon die Art
 * stehen. Vielleicht halt abgekürzt … oder eventuell sogar nur deutsche
 * Namen"). Every folder name, outermost first, and failing all of them the
 * file name, is held against the names the app knows (DGfM, lichens, GBIF):
 *
 *   „Clitocybe odora"          the full name — also an old one, the
 *                              Bestimmung bridges synonyms when it asks
 *   „C. odora" under „Clitocybe"  the genus from a folder above it
 *   „Ockertäubling"            a German name, the DGfM's first one
 *
 * THE FIRST FOLDER THAT NAMES A SPECIES DECIDES, and what lies below it
 * belongs to it — the maintainer's file names were written by a program and may
 * name another species than the folder they sit in („die Art richtig
 * einsortiert, aber der Dateiname falsch"). A genus alone, „spec.", „cf."
 * and „aff." are no sure species and stay out without a word.
 *
 * WHAT CANNOT BE PLACED IS LISTED FOR THE OWNER: a name that looks like
 * one but is unknown („Mycena scripicola"), and a folder holding pictures
 * that names nothing. He writes the species beside it once, or ticks it
 * away, and that word holds for every archive he has and every later scan.
 * A tool to describe a whole folder layout („Baukasten") waits until an
 * archive shows it is needed.
 *
 * A FIND is the deepest folder whose name begins with a day („2021-07-17
 * Kirn"), per species; pictures with no such folder are grouped by the day
 * their file names carry.
 */

declare(strict_types=1);

require_once __DIR__ . '/offline_map.php';
require_once __DIR__ . '/cloud.php';

/** Picture files that are read — what pCloud previews and a browser shows. */
const ARCHIVE_PICTURE_PATTERN = '~\.(jpe?g|png|webp)$~i';

/** At most so many unplaced names are kept per archive — the owner reads them, nobody reads a thousand. */
const ARCHIVE_UNRESOLVED_KEPT = 400;

function archives_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_picture_archives')
        && table_exists($pdo, 'map_archive_pictures')
        && table_exists($pdo, 'map_archive_names');
}

/**
 * A pasted link → what the server needs of it. Only pCloud's own addresses
 * are taken: the server asks pCloud's API with the code and NEVER the
 * address somebody typed, so no link can send it to a host of his choosing.
 *
 * @return ?array{provider: string, code: string, api: string}
 */
function archive_link_parse(string $link): ?array
{
    $host = strtolower((string) parse_url(trim($link), PHP_URL_HOST));
    if (!preg_match('~(^|\.)pcloud\.(link|com)$~', $host)) {
        return null;
    }
    $publink = offline_map_publink(trim($link));
    return $publink === null ? null : ['provider' => 'pcloud'] + $publink;
}

/** An archive's link, decrypted and parsed — null where it no longer reads. */
function archive_link(array $archive): ?array
{
    $plain = cloud_decrypt((string) $archive['link']);
    return $plain === null ? null : archive_link_parse($plain);
}

/** The owner's archives, newest last. */
function archives_of(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM map_picture_archives WHERE user_id = ? ORDER BY id');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- who sees what -----------------------------------------------------------

/*
 * THE OWNER DECIDES PER FRIEND (02.11.2026, migration 0184):
 * whether he sees the pictures, whether with their find spots, and which
 * genera and species stay hidden from him — for all the owner's archives
 * at once. A friend without a row of his own gets the owner's answer for
 * „Neue Freunde", the row whose viewer is the owner himself. Before 0184
 * every friend sees everything, and 0182's tick hides the spots of all.
 *
 * WHY GENERA AND SPECIES, AND NOT EDIBLE FUNGI OR THE RED LIST: what one
 * keeps back is work not yet published („Doktoranden … wollen ihre
 * aktuellen Funde und Mikroskopfotos nicht unbedingt teilen, bevor sie
 * nicht die Veröffentlichung durchhaben"), and that is a genus or a
 * species. A Steinpilz picture harms nobody; where it grew is the spots
 * tick.
 */

/** Whether migration 0184 has run. */
function archive_viewers_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_archive_viewers');
}

/** A stored filter as the rules compare it: {genera: [], species: []}, the names as the owner wrote them. */
function archive_hidden_read(mixed $stored): array
{
    $d = is_string($stored) ? json_decode($stored, true) : $stored;
    $list = static fn($v) => array_values(array_unique(array_filter(array_map(
        static fn($n) => trim((string) $n), is_array($v) ? $v : []), static fn($n) => $n !== '')));
    return ['genera' => $list($d['genera'] ?? null), 'species' => $list($d['species'] ?? null)];
}

/**
 * The owners whose archives this account may see, each with what holds for
 * it: owner id → {spots: bool, hidden: {genera, species}}. Itself always,
 * with everything; a friend by the row his owner wrote for this account,
 * failing one by that owner's „Neue Freunde", failing that: sees, with
 * spots, nothing hidden.
 *
 * @return array<int, array{spots: bool, hidden: array{genera: list<string>, species: list<string>}}>
 */
function archive_sight(PDO $pdo, int $viewerId): array
{
    $none = ['genera' => [], 'species' => []];
    $sight = [$viewerId => ['spots' => true, 'hidden' => $none]];
    require_once __DIR__ . '/friends.php';
    $friends = friends_ready($pdo) ? friend_ids($pdo, $viewerId) : [];
    if ($friends === []) {
        return $sight;
    }
    $in = implode(',', array_fill(0, count($friends), '?'));
    if (!archive_viewers_ready($pdo)) {
        // 0182's tick, one per archive, hid the spots from every friend.
        $hiding = [];
        if (archives_ready($pdo) && column_exists($pdo, 'map_picture_archives', 'hide_gps')) {
            $q = $pdo->prepare("SELECT DISTINCT user_id FROM map_picture_archives WHERE hide_gps = 1 AND user_id IN ($in)");
            $q->execute($friends);
            $hiding = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        foreach ($friends as $f) {
            $sight[$f] = ['spots' => !in_array($f, $hiding, true), 'hidden' => $none];
        }
        return $sight;
    }
    $q = $pdo->prepare("SELECT o.id,
                               COALESCE(v.sees, d.sees, 1) AS sees,
                               COALESCE(v.spots, d.spots, 1) AS spots,
                               IF(v.owner_id IS NULL, d.hidden, v.hidden) AS hidden
                          FROM map_users o
                          LEFT JOIN map_archive_viewers v ON v.owner_id = o.id AND v.viewer_id = ?
                          LEFT JOIN map_archive_viewers d ON d.owner_id = o.id AND d.viewer_id = o.id
                         WHERE o.id IN ($in)");
    $q->execute([$viewerId, ...$friends]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ((int) $r['sees'] === 1) {
            $sight[(int) $r['id']] = ['spots' => (int) $r['spots'] === 1, 'hidden' => archive_hidden_read($r['hidden'])];
        }
    }
    return $sight;
}

/**
 * Does the owner keep this species back from this viewer? By its genus (the
 * first word) or the species (the first two), without regard to case — the
 * name as the archive stores it, lower case.
 */
function archive_name_hidden(array $hidden, string $name): bool
{
    $words = preg_split('/\s+/u', mb_strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($words === []) {
        return false;
    }
    $species = isset($words[1]) ? $words[0] . ' ' . $words[1] : '';
    $whole = implode(' ', $words);
    foreach ($hidden['genera'] ?? [] as $g) {
        if (mb_strtolower($g) === $words[0]) return true;
    }
    foreach ($hidden['species'] ?? [] as $sp) {
        $sp = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $sp)));
        // A working name is more than two words, and „Inocybe sp. nov. 1"
        // must not hide „Inocybe sp. nov. 2" — so it is compared whole.
        if (substr_count($sp, ' ') > 1) {
            if ($whole === $sp || str_starts_with($whole, $sp . ' ')) return true;
        } elseif ($species !== '' && $sp === $species) {
            return true;
        }
    }
    return false;
}

/** A file name without the coordinates archive_file_facts() reads out of it. */
function archive_file_without_gps(string $file): string
{
    return trim(preg_replace(['~\[\s*-?\d+\.\d+\s*,\s*-?\d+\.\d+\s*\]~', '~\s{2,}~'], ['', ' '], $file));
}

/** data/picture_archives/<id> — the kept small previews, behind data/'s „deny all". */
function archive_directory(int $archiveId): string
{
    $dir = PROJECT_PATH . '/data/picture_archives/' . $archiveId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        /* The path goes to the log, never to the user: it is the server's
           inside (error_for_user() in helpers.php). */
        error_log('Karte: Verzeichnis lässt sich nicht anlegen: ' . $dir);
        throw new RuntimeException(t('Ein Ordner auf dem Server lässt sich nicht anlegen.'));
    }
    return $dir;
}

// --- reading names ------------------------------------------------------------

/**
 * A day at the start of a text, or anywhere in it with $anywhere — the
 * spellings found in folder and file names: 2021-07-17, 2021_07_17,
 * 17.07.2021, and the camera's 20210717 („IMG_20210717_101500").
 *
 * @return ?array{0: string, 1: string} the day as Y-m-d, and what follows it
 */
function archive_date_in(string $text, bool $anywhere = false): ?array
{
    $start = $anywhere ? '(?<!\d)' : '^\s*';
    $patterns = [
        "~{$start}((?:19|20)\\d{2})[-_.](\\d{2})[-_.](\\d{2})(?!\\d)(.*)$~u" => [1, 2, 3],
        "~{$start}(\\d{2})\\.(\\d{2})\\.((?:19|20)\\d{2})(?!\\d)(.*)$~u"      => [3, 2, 1],
        "~{$start}((?:19|20)\\d{2})(\\d{2})(\\d{2})(?!\\d)(.*)$~u"           => [1, 2, 3],
    ];
    foreach ($patterns as $pattern => [$y, $m, $d]) {
        if (preg_match($pattern, $text, $hit) && checkdate((int) $hit[$m], (int) $hit[$d], (int) $hit[$y])) {
            return [sprintf('%04d-%02d-%02d', $hit[$y], $hit[$m], $hit[$d]), $hit[4]];
        }
    }
    return null;
}

/**
 * What a file name says besides the species — its day, and where it was
 * written the way the maintainer's renaming program writes it, place, picture
 * label and coordinate:
 *
 *   Pluteus leoninus 2019-08-20 DE,HE, Wiesbaden, Rabengrund 8 Spore.jpg
 *   Sarcoscypha austriaca 2020-03-01 DE,HE, Stockstadt [49.770721, 8.433610] 2.jpg
 *
 * A camera's „IMG_20210717_101500" gives its day and nothing else.
 *
 * @return array{date: ?string, place: string, label: string, lat: ?float, lon: ?float}
 */
function archive_file_facts(string $file): array
{
    $stem = preg_replace('~\.[^.]+$~', '', $file);
    $out = ['date' => null, 'place' => '', 'label' => '', 'lat' => null, 'lon' => null];
    if (preg_match('~\[\s*(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)\s*\]~', $stem, $c)) {
        $out['lat'] = (float) $c[1];
        $out['lon'] = (float) $c[2];
        $stem = str_replace($c[0], ' ', $stem);
    }
    $date = archive_date_in($stem, true);
    if ($date === null) {
        return $out;
    }
    $out['date'] = $date[0];
    // Only a day standing free between words is followed by a place; behind
    // „IMG_20210717" comes a clock time.
    if (!preg_match('~^\s+(.*)$~u', $date[1], $free)) {
        return $out;
    }
    $rest = trim($free[1]);
    // The running number closes the name, and a word after it says what the picture shows („Spore", „cheilo").
    if (preg_match('~^(.*?)\s*(?<!\S)(\d{1,3})(?:\s+(\D.*))?$~u', $rest, $n)) {
        $rest = $n[1];
        $out['label'] = trim($n[3] ?? '');
    }
    // „DE,HE, " — country and state, which the page does not need to repeat.
    $out['place'] = trim(preg_replace('~^[A-Z]{1,3}(?:\s*,\s*[A-Z]{1,3})?\s*,\s*~', '', $rest), " ,");
    return $out;
}

/**
 * The genus an abbreviation „C. odora" stands for: the nearest folder above
 * it that is one capitalised word with the same first letter — over a
 * subgenus (Telamonia under Cortinarius) and the letter folders. A known
 * genus wins over an unknown word.
 */
function archive_genus_above(array $above, string $initial, array $lexicon): ?string
{
    $fallback = null;
    for ($i = count($above) - 1; $i >= 0; $i--) {
        $word = $above[$i];
        if (!str_starts_with($word, $initial) || !preg_match('~^[A-Z][a-zë]{2,}$~u', $word)) {
            continue;
        }
        if (isset($lexicon['genera'][mb_strtolower($word)])) {
            return $word;
        }
        $fallback ??= $word;
    }
    return $fallback;
}

/** The epithets that mean „some species of the genus". */
function archive_genus_only(string $epithet): bool
{
    return in_array(rtrim(mb_strtolower($epithet), '.'), ['spec', 'sp', 'spp', 'species', 'indet'], true);
}

/**
 * What one folder or file name says about the species:
 *
 *   ['species', 'Clitocybe odora']   a species the app knows, or the owner named
 *   ['genus', null]                  a genus alone, „spec.", „cf.", „aff." — no sure species
 *   ['unknown', 'Mycena scripicola'] looks like a name, but nobody knows it
 *   ['ignored', null]                the owner ticked it away
 *   null                             nothing name-like
 *
 * $lexicon: species (lower → name), genera (lower → true), german (lower →
 * name); $names: the owner's word (lower written → species, '' = leave out).
 */
function archive_name_in(string $text, array $above, array $lexicon, array $names): ?array
{
    $text = trim(preg_replace('~^_+\s*~u', '', trim($text)));
    if ($text === '') {
        return null;
    }
    $own = static function (string $written) use ($names): ?array {
        $key = mb_strtolower($written);
        if (!array_key_exists($key, $names)) return null;
        return $names[$key] === '' ? ['ignored', null] : ['species', $names[$key]];
    };
    if (($said = $own($text)) !== null) {
        return $said;
    }
    // A working name, the whole text or its start („Inocybe sp. nov. 1 2021-07-17").
    $lower = mb_strtolower($text);
    foreach ($lexicon['working'] ?? [] as $written => $working) {
        if ($lower === $written || str_starts_with($lower, $written . ' ')) {
            return ['species', $working];
        }
    }
    // „_spec." under „Phellinus" — the genus is the folder above.
    if (preg_match('~^(?:spec|sp|spp|indet)\b\.?~iu', $text)) {
        return ['genus', null];
    }
    $known = static fn(string $name): ?string => $lexicon['species'][mb_strtolower($name)] ?? null;
    $infra = '(?:\s+((?:var|f|subsp|ssp)\.)\s*([a-zë-]+))?';

    // „C. odora" — the genus from a folder above.
    if (preg_match("~^([A-Z])\\.\\s*([a-zë-]+\\.?){$infra}~u", $text, $m)) {
        if (archive_genus_only($m[2]) || in_array($m[2], ['cf.', 'aff.', 'cf', 'aff'], true)) {
            return ['genus', null];
        }
        $genus = archive_genus_above($above, $m[1], $lexicon);
        $name = ($genus ?? $m[1] . '.') . ' ' . $m[2] . (isset($m[4]) && $m[4] !== '' ? ' ' . $m[3] . ' ' . $m[4] : '');
        return $own($name) ?? (($k = $known($name)) !== null ? ['species', $k] : ['unknown', $name]);
    }
    // „Clitocybe odora", „Russula cf. vesca", „Mycena galopus var. candida".
    if (preg_match("~^([A-Z][a-zë]+)\\s+((?:cf|aff)\\.?\\s+)?([a-zë-]+\\.?){$infra}(?![\\pL])~u", $text, $m)) {
        $genusKnown = isset($lexicon['genera'][mb_strtolower($m[1])]);
        if ($m[2] !== '') {
            return $genusKnown ? ['genus', null] : null;
        }
        if (archive_genus_only($m[3])) {
            /* „Inocybe sp. nov.", „Inocybe sp. 1", „Inocybe sp. 'violacea'"
               — not a genus alone but a species somebody is working on.
               Listed for the owner instead of dropped without a word: he
               names it there, a working name or the species it became. */
            if ($genusKnown && preg_match('~^\S+\s+(?:spec|sp)\.?\s+(?:nov\b|\d|[\'"‚‘’“])~iu', $text)) {
                return ['unknown', trim(preg_split('~\s+(?:19|20)\d{2}[-_.]\d{2}[-_.]\d{2}~u', $text)[0])];
            }
            return $genusKnown ? ['genus', null] : null;
        }
        $name = $m[1] . ' ' . $m[3] . (isset($m[5]) && $m[5] !== '' ? ' ' . $m[4] . ' ' . $m[5] : '');
        if (($said = $own($name)) !== null) return $said;
        if (($k = $known($name)) !== null) return ['species', $k];
        // With the variety unknown, the species it belongs to.
        if (isset($m[5]) && $m[5] !== '' && ($k = $known($m[1] . ' ' . $m[3])) !== null) return ['species', $k];
        // Two words of which the first is no genus are just two words („Wald bei Kirn").
        if ($genusKnown) return ['unknown', $name];
    }
    // A German name — the whole text, or what stands before a day or a number.
    $lead = trim(preg_split('~[\d(\[]~u', $text)[0]);
    foreach (array_unique([$text, $lead]) as $candidate) {
        if (($k = $lexicon['german'][mb_strtolower($candidate)] ?? null) !== null) {
            return ['species', $k];
        }
    }
    // A genus alone.
    if (isset($lexicon['genera'][mb_strtolower($lead)])) {
        return ['genus', null];
    }
    return null;
}

/**
 * Every name a tree might use — what the lexicon has to be asked about, so
 * that one query per kind answers a whole archive instead of one per
 * folder.
 *
 * @return array{binomials: array<string, true>, words: array<string, true>}
 */
function archive_candidates(array $root): array
{
    $binomials = [];
    $words = [];
    $walk = static function (array $folder, array $above) use (&$walk, &$binomials, &$words): void {
        foreach ($folder['contents'] ?? [] as $item) {
            $name = trim(preg_replace('~^_+\s*~u', '', (string) ($item['name'] ?? '')));
            $isFolder = !empty($item['isfolder']);
            if (!$isFolder && !preg_match(ARCHIVE_PICTURE_PATTERN, $name)) continue;
            // The variety as the DGfM writes it, „Mycena galopus var. candida".
            $infra = static fn(array $m, int $at): string => !empty($m[$at + 1]) ? ' ' . $m[$at] . ' ' . $m[$at + 1] : '';
            if (preg_match('~^([A-Z][a-zë]+)(?:\s+(?:(?:cf|aff)\.?\s+)?([a-zë-]+)(?:\s+((?:var|f|subsp|ssp)\.)\s*([a-zë-]+))?)?~u', $name, $m)) {
                $words[mb_strtolower($m[1])] = true;
                if (!empty($m[2])) {
                    $binomials[mb_strtolower($m[1] . ' ' . $m[2])] = true;
                    if (($x = $infra($m, 3)) !== '') $binomials[mb_strtolower($m[1] . ' ' . $m[2] . $x)] = true;
                }
            }
            if (preg_match('~^([A-Z])\.\s*([a-zë-]+)(?:\s+((?:var|f|subsp|ssp)\.)\s*([a-zë-]+))?~u', $name, $m)) {
                for ($i = count($above) - 1; $i >= 0; $i--) {
                    if (str_starts_with($above[$i], $m[1]) && preg_match('~^[A-Z][a-zë]{2,}$~u', $above[$i])) {
                        $binomials[mb_strtolower($above[$i] . ' ' . $m[2])] = true;
                        if (($x = $infra($m, 3)) !== '') $binomials[mb_strtolower($above[$i] . ' ' . $m[2] . $x)] = true;
                    }
                }
            }
            if ($isFolder) $walk($item, [...$above, $name]);
        }
    };
    $walk($root, []);
    return ['binomials' => $binomials, 'words' => $words];
}

/**
 * The names the app knows that a tree uses: species and their varieties
 * (DGfM, lichens, GBIF), genera, and every German name of the DGfM and the
 * lichen list (the first of several, „Duftende Tramete, Waldmeister-T." —
 * the abbreviated others are no name anybody writes on a folder).
 *
 * @return array{species: array<string, string>, genera: array<string, true>, german: array<string, string>}
 */
function archive_lexicon(PDO $pdo, array $candidates, int $ownerId = 0): array
{
    $species = [];
    $genera = [];
    foreach (array_chunk(array_keys($candidates['binomials']), 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        foreach ([
            "SELECT binomial FROM map_tax_dgfm WHERE binomial IN ($in)",
            "SELECT binomial FROM map_tax_lichens WHERE binomial IN ($in)",
            "SELECT canonical FROM map_tax_gbif WHERE canonical IN ($in) AND taxon_rank IN ('SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM')",
        ] as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $n) $species[mb_strtolower((string) $n)] ??= (string) $n;
        }
    }
    foreach (array_chunk(array_keys($candidates['words']), 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        foreach ([
            "SELECT DISTINCT genus FROM map_tax_dgfm WHERE genus IN ($in)",
            "SELECT DISTINCT genus FROM map_tax_lichens WHERE genus IN ($in)",
            "SELECT canonical FROM map_tax_gbif WHERE canonical IN ($in) AND taxon_rank = 'GENUS'",
        ] as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $n) $genera[mb_strtolower((string) $n)] = true;
        }
    }
    $german = [];
    // The accepted name first, so a German name lands on it and not on a synonym.
    foreach (['SELECT german_name, binomial FROM map_tax_dgfm WHERE german_name <> \'\' ORDER BY taxnr = sipnr DESC',
              'SELECT german_name, binomial FROM map_tax_lichens WHERE german_name <> \'\''] as $sql) {
        foreach ($pdo->query($sql, PDO::FETCH_NUM) as [$g, $b]) {
            foreach (array_map('trim', explode(',', (string) $g)) as $i => $one) {
                if ($one === '' || ($i > 0 && str_contains($one, '.'))) continue;
                $german[mb_strtolower($one)] ??= (string) $b;
            }
        }
    }
    /* THE OWNER'S WORKING NAMES (working_names.php) — species no list
       knows, his own and those colleagues share with him. A folder
       „Inocybe sp. nov. 1" is then that species, and not a genus alone. */
    $working = [];
    if ($ownerId > 0) {
        require_once __DIR__ . '/working_names.php';
        foreach (working_names_visible($pdo, $ownerId) as $w) {
            $working[mb_strtolower($w['name'])] = $w['name'];
        }
        // The longest first, so „… sp. nov. 12" is not read as „… sp. nov. 1".
        uksort($working, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    }
    return ['species' => $species, 'genera' => $genera, 'german' => $german, 'working' => $working];
}

/** The owner's word on names, lower written → species ('' = leave out). */
function archive_names(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT written, species FROM map_archive_names WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * The reading of a tree: every picture under a species, with its find, and
 * what could not be placed. Pure — the lexicon and the owner's names come
 * in, so tests/picture_archives.php can hold it to made-up trees.
 *
 * @return array{pictures: list<array>, unresolved: list<array{written: string, kind: string, pictures: int, path: string}>,
 *               species: int, unplaced: int, genus_only: int}
 */
function archive_index_from_tree(array $root, array $lexicon, array $names): array
{
    $pictures = [];
    $unresolved = [];
    $genusOnly = 0;
    $unplaced = 0;

    /* $state: species — decided above; dated — the deepest day folder's
       path and name; last — the deepest folder that said something
       ('genus' or ['unknown', name]); named — the deepest folder that is
       no day, for the list of folders that name nothing. */
    $walk = static function (array $folder, array $path, array $state) use (&$walk, &$pictures, &$unresolved, &$genusOnly, &$unplaced, $lexicon, $names): void {
        foreach ($folder['contents'] ?? [] as $item) {
            $name = (string) ($item['name'] ?? '');
            if (!empty($item['isfolder'])) {
                $next = $state;
                $date = archive_date_in($name);
                if (preg_match('~^(19|20)\d{2}$~', trim($name))) {
                    // A year folder („D. venosa / 2018 / 2018-04-12 Kühkopf") only sorts — it says nothing itself.
                } elseif ($date !== null) {
                    $next['dated'] = ['path' => implode('/', [...$path, $name]), 'date' => $date[0], 'place' => trim($date[1], " ,-_")];
                } elseif ($state['species'] === null) {
                    /* A folder ticked away („weglassen") is not skipped whole: it
                       only stops asking. „Haareis" holds „Exidiopsis effusa
                       (Haareis) …", and that file still names its species. */
                    $said = archive_name_in($name, $path, $lexicon, $names);
                    if ($said !== null && $said[0] === 'species') {
                        $next['species'] = $said[1];
                        $next['species_path'] = implode('/', [...$path, $name]);
                    } else {
                        // Only the deepest named folder speaks for the pictures below it: „Mycena / Sammlung 2" is a folder to name, not a genus.
                        $next['last'] = $said;
                    }
                    $next['named'] = ['written' => $name, 'path' => implode('/', [...$path, $name])];
                }
                $walk($item, [...$path, $name], $next);
                continue;
            }
            if (!isset($item['fileid']) || !preg_match(ARCHIVE_PICTURE_PATTERN, $name)) {
                continue;
            }
            $stem = preg_replace('~\.[^.]+$~', '', $name);
            $species = $state['species'];
            $speciesPath = $state['species_path'] ?? implode('/', $path);
            if ($species === null) {
                $said = archive_name_in($stem, $path, $lexicon, $names);
                if ($said !== null && $said[0] === 'ignored') continue;
                if ($said !== null && $said[0] === 'species') {
                    $species = $said[1];
                    $speciesPath = implode('/', $path);
                } else {
                    // A folder's word goes before the file's: the maintainer's program may have misnamed the file.
                    $why = $state['last'] ?? $said;
                    if ($why !== null && $why[0] === 'genus') {
                        $genusOnly++;
                        continue;
                    }
                    if ($why !== null && $why[0] === 'ignored') {
                        continue;
                    }
                    $unplaced++;
                    $entry = $why !== null && $why[0] === 'unknown'
                        ? ['written' => $why[1], 'kind' => 'name', 'path' => $state['named']['path'] ?? implode('/', $path)]
                        : ($state['named'] !== null
                            ? ['written' => $state['named']['written'], 'kind' => 'folder', 'path' => $state['named']['path']]
                            : ['written' => $stem, 'kind' => 'file', 'path' => implode('/', [...$path, $name])]);
                    $key = mb_strtolower($entry['written']);
                    $unresolved[$key] ??= $entry + ['pictures' => 0];
                    $unresolved[$key]['pictures']++;
                    continue;
                }
            }
            $facts = archive_file_facts($name);
            if ($state['dated'] !== null) {
                $findKey = $species . '|' . $state['dated']['path'];
                $date = $state['dated']['date'];
                $place = $state['dated']['place'];
            } else {
                $findKey = $species . '|' . $speciesPath . '|' . ($facts['date'] ?? '');
                $date = $facts['date'];
                $place = $facts['place'];
            }
            $pictures[] = [
                'file_id' => (string) $item['fileid'],
                'name' => mb_strtolower($species),
                'find_key' => sha1($findKey),
                'find_date' => $date,
                'place' => mb_substr($place, 0, 255),
                'lat' => $facts['lat'],
                'lon' => $facts['lon'],
                'file' => mb_substr($stem, 0, 500),
                'label' => mb_substr($facts['label'], 0, 255),
            ];
        }
    };
    $walk($root, [], ['species' => null, 'dated' => null, 'last' => null, 'named' => null]);

    // A find takes a coordinate any of its pictures carries.
    $where = [];
    foreach ($pictures as $p) {
        if ($p['lat'] !== null) $where[$p['find_key']] ??= [$p['lat'], $p['lon']];
    }
    foreach ($pictures as &$p) {
        [$p['lat'], $p['lon']] = $where[$p['find_key']] ?? [null, null];
    }
    unset($p);

    usort($unresolved, static fn($a, $b) => $b['pictures'] <=> $a['pictures'] ?: strnatcasecmp($a['written'], $b['written']));
    return [
        'pictures' => $pictures,
        'unresolved' => array_slice(array_values($unresolved), 0, ARCHIVE_UNRESOLVED_KEPT),
        'species' => count(array_unique(array_column($pictures, 'name'))),
        'unplaced' => $unplaced,
        'genus_only' => $genusOnly,
    ];
}

// --- the provider -------------------------------------------------------------

/**
 * The shared folder's whole tree, as pCloud's showpublink gives it: one
 * answer for the whole folder (measured 01.11.2026: 14 MB for 11,423
 * pictures in 0.27 s of parsing; pCloud stops listing a share at about
 * 51,000 entries — measured with another share of that size).
 */
function archive_tree(array $link): array
{
    [$status, $body] = offline_map_http('https://' . $link['api'] . '/showpublink?code=' . rawurlencode($link['code']));
    $d = json_decode($body, true);
    unset($body);
    if ($status !== 200 || !is_array($d) || ($d['result'] ?? -1) !== 0 || !isset($d['metadata'])) {
        $code = is_array($d) ? (int) ($d['result'] ?? 0) : 0;
        throw new RuntimeException(in_array($code, [7001, 7002], true)
            ? t('pCloud kennt diesen Link nicht. Vielleicht wurde die Freigabe beendet.')
            : t('pCloud zeigt den Ordner gerade nicht (%s).', is_array($d) ? (string) ($d['error'] ?? $code) : "HTTP $status"));
    }
    return $d['metadata'];
}

/**
 * Where one picture can be had. 'small' and 'large' are pCloud's own
 * previews — measured 01.11.2026: 320 px in 0.7 s and 29 KB, 2048 px in
 * half a second and 670 KB, where the original weighs 9 MB on average —
 * drawn anew by pCloud, so a viewer's browser decodes pCloud's bytes and
 * not the uploader's (includes/picture_fence.php). 'full' is the
 * original, for whoever wants the spores at their own resolution: up to
 * three of the download hosts pCloud names, and only pCloud's own.
 *
 * @return list<array{url: string, headers: string}> for cloud_deliver()
 */
function archive_addresses(array $link, string $fileId, string $size): array
{
    $api = 'https://' . $link['api'];
    $code = rawurlencode($link['code']);
    $file = rawurlencode($fileId);
    if ($size !== 'full') {
        $edge = $size === 'small' ? '320x320' : '2048x2048';
        return [['url' => "$api/getpubthumb?code=$code&fileid=$file&size=$edge", 'headers' => '']];
    }
    // NO REFERER: getpublinkdownload refuses one (offline_map_file_url).
    [$status, $body] = offline_map_http("$api/getpublinkdownload?code=$code&fileid=$file");
    $d = json_decode($body, true);
    if ($status !== 200 || !is_array($d) || ($d['result'] ?? -1) !== 0 || empty($d['hosts'])) {
        throw new ProviderRefused(
            sprintf('pCloud gibt das Bild nicht heraus: %s',
                is_array($d) ? (string) ($d['error'] ?? $d['result'] ?? '') : "HTTP $status"),
            t('pCloud gibt das Bild nicht heraus (%s).', is_array($d) ? (string) ($d['result'] ?? '?') : "HTTP $status")
        );
    }
    $out = [];
    foreach (array_slice($d['hosts'], 0, 3) as $host) {
        if (preg_match('~^[a-z0-9.-]+\.pcloud\.com$~i', (string) $host)) {
            $out[] = ['url' => 'https://' . $host . $d['path'], 'headers' => ''];
        }
    }
    return $out;
}

/**
 * The kept small preview — fetched once, then served from the disk. ONLY
 * A PICTURE IS KEPT: what comes back is held against the picture formats
 * by its first bytes (picture_type) before it is written.
 */
function archive_small(array $archive, array $link, string $fileId): string
{
    $path = archive_directory((int) $archive['id']) . '/' . sha1($fileId);
    if (!is_file($path)) {
        [$status, $bytes] = offline_map_http(archive_addresses($link, $fileId, 'small')[0]['url']);
        if ($status !== 200 || picture_type($bytes) === null) {
            throw new RuntimeException(t('pCloud gibt keine Vorschau heraus (HTTP %d)', $status));
        }
        file_put_contents($path . '.tmp', $bytes, LOCK_EX);
        rename($path . '.tmp', $path);
    }
    return $path;
}

// --- scanning -----------------------------------------------------------------

/**
 * Read one archive anew and replace its rows. A failed read leaves the old
 * rows standing and says why on the archive; the pictures stay visible
 * while pCloud has a bad hour.
 */
function archive_scan(PDO $pdo, array $archive): array
{
    $id = (int) $archive['id'];
    try {
        $link = archive_link($archive) ?? throw new RuntimeException(t('Der gespeicherte Link lässt sich nicht mehr lesen.'));
        $tree = archive_tree($link);
        $reading = archive_index_from_tree($tree, archive_lexicon($pdo, archive_candidates($tree), (int) $archive['user_id']), archive_names($pdo, (int) $archive['user_id']));
    } catch (RuntimeException $e) {
        $pdo->prepare("UPDATE map_picture_archives SET status = 'failed', error = ?, scanned_at = NOW() WHERE id = ?")
            ->execute([mb_substr(error_for_user($e), 0, 500), $id]);
        throw $e;
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM map_archive_pictures WHERE archive_id = ?')->execute([$id]);
        foreach (array_chunk($reading['pictures'], 500) as $chunk) {
            $rows = [];
            $values = [];
            foreach ($chunk as $p) {
                $rows[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                array_push($values, $id, $p['file_id'], $p['name'], $p['find_key'], $p['find_date'], $p['place'], $p['lat'], $p['lon'], $p['file'], $p['label']);
            }
            $pdo->prepare('INSERT INTO map_archive_pictures (archive_id, file_id, name, find_key, find_date, place, lat, lon, file, label)
                           VALUES ' . implode(', ', $rows))->execute($values);
        }
        $pdo->prepare("UPDATE map_picture_archives SET status = 'ready', error = NULL, title = ?, pictures = ?, species = ?,
                              unplaced = ?, unresolved = ?, scanned_at = NOW() WHERE id = ?")
            ->execute([mb_substr((string) ($tree['name'] ?? ''), 0, 255), count($reading['pictures']), $reading['species'],
                       $reading['unplaced'], json_encode($reading['unresolved'], JSON_UNESCAPED_UNICODE), $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $reading;
}

/** Attach a new archive and read it at once. */
function archive_add(PDO $pdo, int $userId, string $raw): array
{
    $link = archive_link_parse($raw);
    if ($link === null) {
        throw new InvalidArgumentException(t('Das ist kein Freigabe-Link von pCloud. Er sieht so aus: https://e.pcloud.link/publink/show?code=…'));
    }
    foreach (archives_of($pdo, $userId) as $a) {
        if ((archive_link($a)['code'] ?? null) === $link['code']) {
            throw new InvalidArgumentException(t('Diesen Ordner hast du schon angehängt.'));
        }
    }
    $pdo->prepare("INSERT INTO map_picture_archives (user_id, provider, link, created_at) VALUES (?, ?, ?, NOW())")
        ->execute([$userId, $link['provider'], cloud_encrypt(trim($raw))]);
    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM map_picture_archives WHERE id = ?');
    $stmt->execute([$id]);
    return archive_scan($pdo, $stmt->fetch(PDO::FETCH_ASSOC));
}

/** Detach an archive: its reading and its kept previews go, the folder in the cloud stays as it is. */
function archive_remove(PDO $pdo, int $userId, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM map_picture_archives WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    $dir = PROJECT_PATH . '/data/picture_archives/' . $id;
    foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
    @rmdir($dir);
    return true;
}

/**
 * The owner's word on names, saved: written → species, '' = leave out; a
 * row whose field was emptied and not ticked away is forgotten again.
 *
 * @param array<string, ?string> $words written → species ('' = leave out, null = forget)
 */
function archive_names_save(PDO $pdo, int $userId, array $words): void
{
    $set = $pdo->prepare('INSERT INTO map_archive_names (user_id, written, species) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE species = VALUES(species)');
    $forget = $pdo->prepare('DELETE FROM map_archive_names WHERE user_id = ? AND written = ?');
    foreach ($words as $written => $species) {
        $written = mb_substr(mb_strtolower(trim((string) $written)), 0, 255);
        if ($written === '') continue;
        if ($species === null) {
            $forget->execute([$userId, $written]);
            continue;
        }
        $species = trim(preg_replace('~\s+~', ' ', $species));
        // A working name (working_names.php) is a species too, in whatever words it was coined.
        if ($species !== '' && ($working = archive_working_name($pdo, $userId, $species)) !== null) {
            $set->execute([$userId, $written, $working]);
            continue;
        }
        if ($species !== '' && !preg_match('~^[A-Z][a-zë-]+ [a-zë-]+(?: (?:var|f|subsp)\. [a-zë-]+)?$~u', $species)) {
            throw new InvalidArgumentException(t('„%s" ist kein Artname. Schreib ihn so: Gattung und Art, zum Beispiel „Mycena galopus".', $species));
        }
        $set->execute([$userId, $written, $species]);
    }
}

/** The owner's working name written so, in its own spelling — or null. */
function archive_working_name(PDO $pdo, int $userId, string $typed): ?string
{
    require_once __DIR__ . '/working_names.php';
    $wanted = mb_strtolower(trim($typed));
    foreach (working_names_visible($pdo, $userId) as $w) {
        if (mb_strtolower($w['name']) === $wanted) return $w['name'];
    }
    return null;
}

// --- seeing -------------------------------------------------------------------

/**
 * The older finds of a species this account may see — its own archives' and
 * its friends'. $names are keys_species_names()' answer: the name and all
 * it was or is called, lower case.
 *
 * @return list<array{archive: int, owner: ?string, date: ?string, place: string, lat: ?float, lon: ?float, pictures: list<array>}>
 */
function archives_for(PDO $pdo, int $userId, array $names): array
{
    if ($names === []) {
        return [];
    }
    $sight = archive_sight($pdo, $userId);
    $owners = array_keys($sight);
    $names = array_keys($names);
    $stmt = $pdo->prepare('SELECT p.*, a.user_id, u.first_name, u.last_name, u.email
                             FROM map_archive_pictures p
                             JOIN map_picture_archives a ON a.id = p.archive_id
                             JOIN map_users u ON u.id = a.user_id
                            WHERE a.user_id IN (' . implode(',', array_fill(0, count($owners), '?')) . ')
                              AND p.name IN (' . implode(',', array_fill(0, count($names), '?')) . ')');
    $stmt->execute([...$owners, ...$names]);
    $finds = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r = archive_row_shown($r, $userId, $sight);
        if ($r === null) continue;
        $key = $r['archive_id'] . '|' . $r['find_key'];
        $finds[$key] ??= [
            'archive' => (int) $r['archive_id'],
            'owner' => (int) $r['user_id'] === $userId ? null : user_display_name($r),
            'date' => $r['find_date'],
            'place' => (string) $r['place'],
            'lat' => $r['lat'] === null ? null : (float) $r['lat'],
            'lon' => $r['lon'] === null ? null : (float) $r['lon'],
            'pictures' => [],
        ];
        $finds[$key]['pictures'][] = ['id' => (string) $r['file_id'], 'file' => (string) $r['file'], 'label' => (string) $r['label']];
    }
    foreach ($finds as &$find) {
        usort($find['pictures'], static fn($a, $b) => strnatcasecmp($a['file'], $b['file']));
    }
    unset($find);
    $out = array_values($finds);
    // Newest first, as the finds above them; a group without a day last.
    usort($out, static fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
    return $out;
}

/**
 * The owner's answers, for the sub-tab „Freunde": „Neue Freunde" and every
 * friend by name, each as it holds now — a friend without a row of his own
 * shows „Neue Freunde"'s answer, and 'own' says whether he has one.
 *
 * @return array{new: array, friends: list<array{id: int, name: string, sees: bool, spots: bool, hidden: array, own: bool}>}
 */
function archive_audience(PDO $pdo, int $ownerId): array
{
    $rows = $pdo->prepare('SELECT viewer_id, sees, spots, hidden FROM map_archive_viewers WHERE owner_id = ?');
    $rows->execute([$ownerId]);
    $answers = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $answers[(int) $r['viewer_id']] = ['sees' => (int) $r['sees'] === 1, 'spots' => (int) $r['spots'] === 1,
                                           'hidden' => archive_hidden_read($r['hidden'])];
    }
    $new = $answers[$ownerId] ?? ['sees' => true, 'spots' => true, 'hidden' => ['genera' => [], 'species' => []]];
    require_once __DIR__ . '/friends.php';
    $ids = friends_ready($pdo) ? friend_ids($pdo, $ownerId) : [];
    $friends = [];
    if ($ids !== []) {
        $q = $pdo->prepare('SELECT id, first_name, last_name, email FROM map_users
                             WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $q->execute($ids);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $id = (int) $u['id'];
            $friends[] = ['id' => $id, 'name' => user_display_name($u), 'own' => isset($answers[$id])] + ($answers[$id] ?? $new);
        }
    }
    usort($friends, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return ['new' => $new, 'friends' => $friends];
}

/**
 * One answer saved — $friendId null is „Neue Freunde". $change carries any
 * of sees, spots (bool) and hidden ({genera, species}); the rest stays.
 *
 * „NEUE FREUNDE" REACHES FORWARD ONLY. Before it changes, every friend
 * without a row of his own gets one carrying the answer he has had until
 * now — or unticking „Neue Freunde sehen die Bilder" would quietly take
 * them from every friend one already has.
 *
 * @return array the answer as stored now
 */
function archive_audience_set(PDO $pdo, int $ownerId, ?int $friendId, array $change): array
{
    $audience = archive_audience($pdo, $ownerId);
    $write = $pdo->prepare('INSERT INTO map_archive_viewers (owner_id, viewer_id, sees, spots, hidden) VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE sees = VALUES(sees), spots = VALUES(spots), hidden = VALUES(hidden)');
    $store = static function (int $viewer, array $a) use ($write, $ownerId): void {
        $hidden = $a['hidden']['genera'] === [] && $a['hidden']['species'] === [] ? null
            : json_encode($a['hidden'], JSON_UNESCAPED_UNICODE);
        $write->execute([$ownerId, $viewer, (int) $a['sees'], (int) $a['spots'], $hidden]);
    };
    if ($friendId === null) {
        foreach ($audience['friends'] as $f) {
            if (!$f['own']) $store($f['id'], $f);
        }
        $answer = $audience['new'];
        $viewer = $ownerId;
    } else {
        $found = array_values(array_filter($audience['friends'], static fn($f) => $f['id'] === $friendId));
        if ($found === []) {
            throw new InvalidArgumentException(t('Mit dieser Person bist du nicht mehr befreundet.'));
        }
        $answer = $found[0];
        $viewer = $friendId;
    }
    foreach (['sees', 'spots'] as $key) {
        if (array_key_exists($key, $change)) $answer[$key] = (bool) $change[$key];
    }
    if (array_key_exists('hidden', $change)) {
        $answer['hidden'] = archive_hidden_read($change['hidden']);
    }
    $answer = ['sees' => $answer['sees'], 'spots' => $answer['spots'], 'hidden' => $answer['hidden']];
    $store($viewer, $answer);
    return $answer;
}

/**
 * A typed genus or species as a filter keeps it, in its proper spelling —
 * or null when nothing would ever match it. Known is what the owner's own
 * archives hold, or failing that the DGfM's list: a doctoral student may
 * hide a species before the folder with its pictures is even attached. A
 * species is its first two words, the way the filter compares
 * (archive_name_hidden): „Boletus edulis var. arenarius" hides Boletus edulis.
 */
function archive_hidden_name(PDO $pdo, int $ownerId, string $typed, bool $species): ?string
{
    // A working name stands whole — „Inocybe sp. nov. 1" is not „Inocybe sp.".
    if ($species && ($working = archive_working_name($pdo, $ownerId, $typed)) !== null) {
        return $working;
    }
    $words = preg_split('/\s+/u', mb_strtolower(trim($typed)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($words === [] || ($species ? count($words) < 2 : count($words) !== 1)) {
        return null;
    }
    $wanted = $species ? $words[0] . ' ' . $words[1] : $words[0];
    $q = $pdo->prepare('SELECT 1 FROM map_archive_pictures p JOIN map_picture_archives a ON a.id = p.archive_id
                         WHERE a.user_id = ? AND (p.name = ? OR p.name LIKE ?) LIMIT 1');
    $q->execute([$ownerId, $wanted, addcslashes($wanted, '%_\\') . ' %']);
    if ($q->fetchColumn() !== false) {
        return mb_strtoupper(mb_substr($wanted, 0, 1)) . mb_substr($wanted, 1);
    }
    require_once __DIR__ . '/mykis.php';
    return table_exists($pdo, 'map_tax_dgfm') ? mykis_rule_name($pdo, $typed, $species) : null;
}

/**
 * Does this viewer see the archive's pictures without their coordinates?
 * Whoever is not its owner and whose owner said „ohne Fundorte" for him.
 * $sight is archive_sight()'s answer for the viewer.
 */
function archive_hides_gps(array $archive, int $viewerId, array $sight): bool
{
    $owner = (int) $archive['user_id'];
    return $owner !== $viewerId && !($sight[$owner]['spots'] ?? false);
}

/**
 * A picture's row as this viewer may see it — or null where he may not: an
 * owner he does not see, or a genus or species that owner keeps back from
 * him. Where the owner hides his find spots, without lat/lon and without
 * the coordinates in the file name. Every path that hands archive rows to a
 * browser runs them through here — the Bestimmung, the Fundbrowser's
 * overview and its finds; the pictures themselves through old_picture.php.
 * $row needs user_id and name.
 */
function archive_row_shown(array $row, int $viewerId, array $sight): ?array
{
    $owner = (int) $row['user_id'];
    if ($owner === $viewerId) {
        return $row;
    }
    if (!isset($sight[$owner]) || archive_name_hidden($sight[$owner]['hidden'], (string) ($row['name'] ?? ''))) {
        return null;
    }
    if (archive_hides_gps($row, $viewerId, $sight)) {
        $row['lat'] = null;
        $row['lon'] = null;
        if (isset($row['file'])) {
            $row['file'] = archive_file_without_gps((string) $row['file']);
        }
    }
    return $row;
}

/**
 * EVERY OLDER FIND ONE MAY SEE, across all species — the Fundbrowser's
 * „Bildarchiv" (02.11.2026: „Mach Variante 1, nenn es
 * Bildarchiv"), for whoever has no keys to reach them through. A gallery,
 * not finds: nothing here ever becomes a point.
 *
 * One entry per species, archive and find (find_key may gather several
 * species, as one folder of a day does), with its first picture for the
 * tile and the number of the rest — the pictures themselves come with
 * archive_find() when the find is opened, or 3,000 finds would carry their
 * 11,000 pictures at once. The species is told as one writes it, the genus
 * capitalised; it is stored lower case for the matching.
 *
 * @return list<array{archive: int, key: string, name: string, owner: ?string, date: ?string, place: string, lat: ?float, lon: ?float, cover: string, pictures: int}>
 */
function archives_overview(PDO $pdo, int $userId): array
{
    $sight = archive_sight($pdo, $userId);
    $owners = array_keys($sight);
    $stmt = $pdo->prepare('SELECT p.archive_id, p.file_id, p.name, p.find_key, p.find_date, p.place, p.lat, p.lon, p.file,
                                  a.user_id, u.first_name, u.last_name, u.email
                             FROM map_archive_pictures p
                             JOIN map_picture_archives a ON a.id = p.archive_id
                             JOIN map_users u ON u.id = a.user_id
                            WHERE a.user_id IN (' . implode(',', array_fill(0, count($owners), '?')) . ')');
    $stmt->execute($owners);
    $finds = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r = archive_row_shown($r, $userId, $sight);
        if ($r === null) continue;
        $key = $r['archive_id'] . '|' . $r['find_key'] . '|' . $r['name'];
        if (!isset($finds[$key])) {
            $finds[$key] = [
                'archive' => (int) $r['archive_id'],
                'key' => (string) $r['find_key'],
                'name' => mb_strtoupper(mb_substr((string) $r['name'], 0, 1)) . mb_substr((string) $r['name'], 1),
                'owner' => (int) $r['user_id'] === $userId ? null : user_display_name($r),
                'date' => $r['find_date'],
                'place' => (string) $r['place'],
                'lat' => $r['lat'] === null ? null : (float) $r['lat'],
                'lon' => $r['lon'] === null ? null : (float) $r['lon'],
                'cover' => (string) $r['file_id'],
                'pictures' => 0,
                'first' => (string) $r['file'],
            ];
        }
        $finds[$key]['pictures']++;
        // The tile shows the first picture in the files' own order, as the find's strip will.
        if (strnatcasecmp((string) $r['file'], $finds[$key]['first']) < 0) {
            $finds[$key]['first'] = (string) $r['file'];
            $finds[$key]['cover'] = (string) $r['file_id'];
        }
    }
    $out = array_map(static function ($f) { unset($f['first']); return $f; }, array_values($finds));
    // Newest first, as the Fundbrowser lists; a find without a day last.
    usort($out, static fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']) ?: strcmp($a['name'], $b['name']));
    return $out;
}

/** How many older finds this account may see — the Fundbrowser offers „Bildarchiv" only where there are any. */
function archives_count(PDO $pdo, int $userId): int
{
    $sight = archive_sight($pdo, $userId);
    $owners = array_keys($sight);
    // The hidden genera and species are names, so they are counted out here and not in SQL.
    $stmt = $pdo->prepare('SELECT DISTINCT p.archive_id, p.find_key, p.name, a.user_id
                             FROM map_archive_pictures p
                             JOIN map_picture_archives a ON a.id = p.archive_id
                            WHERE a.user_id IN (' . implode(',', array_fill(0, count($owners), '?')) . ')');
    $stmt->execute($owners);
    $n = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (archive_row_shown($r, $userId, $sight) !== null) $n++;
    }
    return $n;
}

/**
 * One older find's pictures, in the files' order — or null where the
 * archive is not one this account may see (archive_viewer). The species is
 * given as the overview told it; matched without regard to case.
 *
 * @return ?list<array{id: string, file: string, label: string}>
 */
function archive_find(PDO $pdo, int $userId, int $archiveId, string $findKey, string $name): ?array
{
    $owner = $pdo->prepare('SELECT a.user_id FROM map_picture_archives a WHERE a.id = ?');
    $owner->execute([$archiveId]);
    $archive = $owner->fetch(PDO::FETCH_ASSOC);
    $sight = archive_sight($pdo, $userId);
    if ($archive === false || archive_row_shown($archive + ['name' => mb_strtolower($name)], $userId, $sight) === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT file_id, file, label, name FROM map_archive_pictures
                            WHERE archive_id = ? AND find_key = ? AND name = ?');
    $stmt->execute([$archiveId, $findKey, mb_strtolower($name)]);
    $pictures = array_map(static function ($r) use ($archive, $userId, $sight) {
        $r = archive_row_shown($r + $archive, $userId, $sight);
        return ['id' => (string) $r['file_id'], 'file' => (string) $r['file'], 'label' => (string) $r['label']];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    usort($pictures, static fn($a, $b) => strnatcasecmp($a['file'], $b['file']));
    return $pictures;
}

/** The cron's job: every archive anew once a day, the least recently read first, within the time it has. */
function archives_run(PDO $pdo, float $deadline): array
{
    $ids = $pdo->query("SELECT id FROM map_picture_archives
                         WHERE scanned_at IS NULL OR scanned_at < NOW() - INTERVAL 20 HOUR
                         ORDER BY scanned_at IS NOT NULL, scanned_at")->fetchAll(PDO::FETCH_COLUMN);
    $done = 0;
    $failed = 0;
    $pictures = 0;
    foreach ($ids as $i => $id) {
        if ($i > 0 && microtime(true) >= $deadline) break;
        $stmt = $pdo->prepare('SELECT * FROM map_picture_archives WHERE id = ?');
        $stmt->execute([$id]);
        try {
            $pictures += count(archive_scan($pdo, $stmt->fetch(PDO::FETCH_ASSOC))['pictures']);
            $done++;
        } catch (RuntimeException $e) {
            error_log('picture archive ' . $id . ': ' . $e->getMessage());
            $failed++;
        }
    }
    $parts = [];
    if ($done > 0) $parts[] = $done . ' Bildarchiv(e) gelesen, ' . $pictures . ' Bilder';
    if ($failed > 0) $parts[] = $failed . ' nicht erreichbar';
    return ['parts' => $parts, 'open' => count($ids) - $done - $failed];
}
