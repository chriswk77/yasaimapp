<?php
/**
 * The web addresses of a species — „Internet-Links", grouped
 * (10.10.2026; where they are shown is decided later, the first place he
 * named is the result of a key in „Bestimmung").
 *
 * TWO HALVES, the way Pilz-Link built them:
 *
 *   WRITTEN FROM THE NAME AND THE KEYS — search engines, forums, the GBIF
 *   map and gallery, pilze-deutschland.de through the DGfM number, Google
 *   Scholar, NCBI. Pure address templates (Pilz-Link's app/linkquellen.php,
 *   ported); a template whose placeholder stays empty drops out.
 *
 *   STORED — the portrait pages and distribution maps Pilz-Link gathered
 *   on some thirty sites, imported from its export into map_web_links
 *   (migration 0166, Administration → Artlinks). They carry the sites' own
 *   ids and, above all, the knowledge which species HAS a page there.
 *
 * NOT TAKEN OVER: the live look-ups (iNaturalist, Wikipedia, SwissFungi,
 * observation.org …). Each would send the species' name to a stranger's
 * server on every opening, and none works in the woods without reception.
 * Pilz-Link's cached Wikipedia pages come along with the stored half.
 *
 * THE GROUPS AND THEIR ORDER are Pilz-Link's (renderLinksContent): Suche,
 * Verbreitungskarte, Porträt, Wissenschaftlich, DNA, Weitere.
 */

declare(strict_types=1);

/** The groups, in the order they are shown. */
const WEB_LINK_CATEGORIES = ['Suche', 'Verbreitungskarte', 'Porträt', 'Wissenschaftlich', 'DNA', 'Weitere'];

/**
 * The templates. Placeholders: {art} „Genus epithet", {art_plus} with +,
 * {art_enc} url-encoded, {art_quoted_enc} in quotes and url-encoded,
 * {art_hashtag} genusepithet in lower case, {gbif_id}, {dgfm_orgid}.
 *
 * pilze-deutschland.de: its organism id is „10" followed by the DGfM
 * number (pdDeutschlandKarte in Pilz-Link — „bisher immer"). Pilz-Link
 * checked the page live first; here it is offered without asking, the same
 * reasoning as above.
 */
const WEB_LINK_TEMPLATES = [
    'Suche' => [
        ['Google',        'https://www.google.com/search?q={art_quoted_enc}'],
        ['Google-Bilder', 'https://www.google.com/search?tbm=isch&q={art_quoted_enc}'],
        ['Instagram',     'https://www.instagram.com/explore/tags/{art_hashtag}/'],
        ['Facebook',      'https://www.facebook.com/search/top?q={art_quoted_enc}'],
        ['DGfM-Forum',    'https://forum.dgfm-ev.de/wcf/search/?q={art_enc}'],
        ['Pilzforum.eu',  'https://www.pilzforum.eu/search/?q={art_plus}'],
        ['Mycoquébec',    'https://www.mycoquebec.org/bas.php?tag={art_enc}'],
        ['Pilze Pilze',   'https://www.google.com/search?q={art_quoted_enc}+site:pilzepilze.de'],
        ['BioLib',        'https://www.biolib.cz/en/formsearch/?action=execute&searcharea=1&string={art_plus}'],
    ],
    'Verbreitungskarte' => [
        ['GBIF-Karte',         'https://www.gbif.org/occurrence/map?taxon_key={gbif_id}'],
        ['Pilze Deutschlands', 'https://www.pilze-deutschland.de/organismen/{dgfm_orgid}'],
    ],
    'Porträt' => [
        ['GBIF-Galerie', 'https://www.gbif.org/occurrence/gallery?taxon_key={gbif_id}'],
    ],
    'Wissenschaftlich' => [
        ['Google Scholar', 'https://scholar.google.com/scholar?q={art_plus}'],
    ],
    'DNA' => [
        ['NCBI', 'https://www.ncbi.nlm.nih.gov/nuccore/?term={art_plus}'],
    ],
];

/** Migration 0166 — the code goes live before it. */
function web_links_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_web_links');
}

/** Genus and epithet of a name — the binomial the templates speak. */
function web_links_binomial(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return implode(' ', array_slice($words, 0, 2));
}

/**
 * The written half — the templates filled in for this species.
 *
 * @return array<string, array<array{label:string, url:string}>>
 */
function web_links_generated(string $name, ?int $gbifKey, ?int $dgfmTaxnr): array
{
    $art = web_links_binomial($name);
    if ($art === '') {
        return [];
    }
    $vars = [
        '{art}'            => $art,
        '{art_plus}'       => str_replace(' ', '+', $art),
        '{art_enc}'        => rawurlencode($art),
        '{art_quoted_enc}' => rawurlencode('"' . $art . '"'),
        '{art_hashtag}'    => rawurlencode(mb_strtolower(str_replace(' ', '', $art))),
        '{gbif_id}'        => $gbifKey !== null && $gbifKey > 0 ? (string) $gbifKey : '',
        '{dgfm_orgid}'     => $dgfmTaxnr !== null && $dgfmTaxnr > 0 ? '10' . $dgfmTaxnr : '',
    ];
    $out = [];
    foreach (WEB_LINK_TEMPLATES as $category => $templates) {
        foreach ($templates as [$label, $template]) {
            // A placeholder without a value drops the source, as in Pilz-Link.
            foreach ($vars as $key => $value) {
                if ($value === '' && str_contains($template, $key)) {
                    continue 2;
                }
            }
            $out[$category][] = ['label' => $label, 'url' => strtr($template, $vars)];
        }
    }
    return $out;
}

/**
 * The stored half — every row of map_web_links for this species, by
 * GBIF key, DGfM number or, failing both, by name. Several pages of one
 * site come as one entry with its sub-links (Interhias Makro/Mikro,
 * Wikipedia's languages).
 *
 * @return array<string, array<array{label:string, url?:string, multi?:array}>>
 */
function web_links_stored(PDO $pdo, string $name, ?int $gbifKey, ?int $dgfmTaxnr): array
{
    if (!web_links_ready($pdo)) {
        return [];
    }
    /* THE SYNONYMS' KEYS COUNT TOO (the Pilz-Link session, 10.10.2026):
       the export files a link under the name Pilz-Link knew it by — the
       accepted one where it could, otherwise a synonym. Asked with today's
       name, „Imleria badia", the pages filed under „Xerocomus badius" have
       to come along: every GBIF synonym whose parent is the asked key, and
       every DGfM number our bridge (map_tax_links) ties to any of them. */
    [$gbifKeys, $dgfmNumbers] = web_links_key_family($pdo, $gbifKey, $dgfmTaxnr);
    $where = [];
    $values = [];
    if ($gbifKeys !== []) {
        $where[] = 'gbif_key IN (' . implode(',', array_fill(0, count($gbifKeys), '?')) . ')';
        $values = array_merge($values, $gbifKeys);
    }
    if ($dgfmNumbers !== []) {
        $where[] = 'dgfm_taxnr IN (' . implode(',', array_fill(0, count($dgfmNumbers), '?')) . ')';
        $values = array_merge($values, $dgfmNumbers);
    }
    $art = web_links_binomial($name);
    if ($art !== '') {
        // By name only for rows that carry neither key — otherwise a
        // homonym's page would join (a genus name exists in two kingdoms).
        $where[] = '(name = ? AND gbif_key IS NULL AND dgfm_taxnr IS NULL)';
        $values[] = $art;
    }
    if ($where === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT DISTINCT source, category, label, sublabel, url FROM map_web_links
                            WHERE ' . implode(' OR ', $where) . ' ORDER BY category, label, sublabel, url');
    $stmt->execute($values);

    $grouped = [];
    foreach ($stmt->fetchAll() as $z) {
        $grouped[(string) $z['category']][(string) $z['label']][] = $z;
    }
    $out = [];
    foreach ($grouped as $category => $byLabel) {
        foreach ($byLabel as $label => $rows) {
            if (count($rows) === 1) {
                $out[$category][] = ['label' => $label . ($rows[0]['sublabel'] !== '' ? ' (' . $rows[0]['sublabel'] . ')' : ''),
                                     'url' => (string) $rows[0]['url']];
                continue;
            }
            $multi = [];
            foreach ($rows as $i => $r) {
                $multi[] = ['label' => $r['sublabel'] !== '' ? (string) $r['sublabel'] : (string) ($i + 1), 'url' => (string) $r['url']];
            }
            $out[$category][] = ['label' => $label, 'multi' => $multi];
        }
    }
    return $out;
}

/**
 * The asked keys and their synonyms' — GBIF synonyms point at their
 * accepted taxon through parent_key (keys.php reads them the same way), and
 * map_tax_links ties DGfM numbers to GBIF keys.
 *
 * @return array{0: int[], 1: int[]} GBIF keys, DGfM numbers
 */
function web_links_key_family(PDO $pdo, ?int $gbifKey, ?int $dgfmTaxnr): array
{
    $gbif = $gbifKey !== null && $gbifKey > 0 ? [$gbifKey] : [];
    $dgfm = $dgfmTaxnr !== null && $dgfmTaxnr > 0 ? [$dgfmTaxnr] : [];
    $links = table_exists($pdo, 'map_tax_links');
    if ($gbif === [] && $dgfm !== [] && $links) {
        $q = $pdo->prepare('SELECT COALESCE(gbif_species_key, gbif_key) FROM map_tax_links WHERE taxnr = ? AND gbif_key IS NOT NULL');
        $q->execute($dgfm);
        $gbif = array_map('intval', array_filter($q->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($gbif !== [] && table_exists($pdo, 'map_tax_gbif')) {
        $space = implode(',', array_fill(0, count($gbif), '?'));
        $q = $pdo->prepare("SELECT gbif_key FROM map_tax_gbif WHERE is_synonym = 1 AND parent_key IN ($space)");
        $q->execute($gbif);
        $gbif = array_values(array_unique(array_merge($gbif, array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)))));
    }
    if ($gbif !== [] && $links) {
        $space = implode(',', array_fill(0, count($gbif), '?'));
        $q = $pdo->prepare("SELECT DISTINCT taxnr FROM map_tax_links WHERE gbif_key IN ($space) OR gbif_species_key IN ($space)");
        $q->execute(array_merge($gbif, $gbif));
        $dgfm = array_values(array_unique(array_merge($dgfm, array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)))));
    }
    return [$gbif, $dgfm];
}

/**
 * Both halves together, in the groups' order — what a page shows.
 *
 * @return array<array{category:string, links:array}>
 */
function web_links(PDO $pdo, string $name, ?int $gbifKey = null, ?int $dgfmTaxnr = null): array
{
    // Without a DGfM number the bridge may know one — pilze-deutschland.de needs it.
    if (($dgfmTaxnr === null || $dgfmTaxnr <= 0) && $gbifKey !== null && $gbifKey > 0 && table_exists($pdo, 'map_tax_links')) {
        $q = $pdo->prepare('SELECT taxnr FROM map_tax_links WHERE gbif_key = ? OR gbif_species_key = ? ORDER BY gbif_key = ? DESC LIMIT 1');
        $q->execute([$gbifKey, $gbifKey, $gbifKey]);
        $found = $q->fetchColumn();
        $dgfmTaxnr = $found === false ? null : (int) $found;
    }
    $generated = web_links_generated($name, $gbifKey, $dgfmTaxnr);
    $stored    = web_links_stored($pdo, $name, $gbifKey, $dgfmTaxnr);
    $categories = array_values(array_unique(array_merge(WEB_LINK_CATEGORIES, array_keys($stored))));
    $out = [];
    foreach ($categories as $category) {
        $links = array_merge($generated[$category] ?? [], $stored[$category] ?? []);
        if ($links !== []) {
            $out[] = ['category' => $category, 'links' => $links];
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// The import of Pilz-Link's export
// ---------------------------------------------------------------------------

/** The columns the export carries, in any order — the header line names them. */
const WEB_LINKS_COLUMNS = ['gbif_key', 'dgfm_taxnr', 'wiss_name', 'quelle', 'kategorie', 'label', 'unterlabel', 'url'];

/**
 * Take an export in — a CSV (UTF-8, comma, header line), plain or gzip.
 *
 * IT REPLACES THE SOURCES THE FILE NAMES, not the whole table (10.10.2026):
 * Pilz-Link sent its two broken sources, CEMA and Bender, as a second file
 * after the main one, and a later update may bring one site alone. Every
 * site that appears in the file is emptied the moment its first row is
 * read and filled from the file; every other site keeps what it has.
 *
 * In ONE transaction: half a file would leave some species with old links
 * and others with none.
 *
 * @return array{rows:int, skipped:int, sources:string[]}
 */
function web_links_import(PDO $pdo, string $path): array
{
    if (!web_links_ready($pdo)) {
        throw new RuntimeException(t('Die Artlinks brauchen die Migration 0166 — sie ist noch nicht eingespielt.'));
    }
    $gz = str_ends_with(strtolower($path), '.gz') || bin2hex((string) file_get_contents($path, false, null, 0, 2)) === '1f8b';
    $h  = $gz ? gzopen($path, 'rb') : fopen($path, 'rb');
    if ($h === false) {
        throw new RuntimeException(t('Die Datei lässt sich nicht öffnen.'));
    }
    // escape '' — a backslash in a URL is a character, not an escape.
    $read = static fn() => $gz
        ? (($line = gzgets($h)) === false ? false : str_getcsv($line, ',', '"', ''))
        : fgetcsv($h, null, ',', '"', '');

    $head = $read();
    if (!is_array($head)) {
        throw new InvalidArgumentException(t('Die Datei ist leer.'));
    }
    $head = array_map(static fn($c): string => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $c))), $head);
    $at = array_flip($head);
    foreach (['quelle', 'kategorie', 'label', 'url'] as $needed) {
        if (!isset($at[$needed])) {
            throw new InvalidArgumentException(t('In der Kopfzeile fehlt die Spalte „%s".', $needed));
        }
    }

    $rows = 0;
    $skipped = 0;
    $pdo->beginTransaction();
    $seen = [];
    try {
        $empty = $pdo->prepare('DELETE FROM map_web_links WHERE source = ?');
        $batch = [];
        $flush = static function () use ($pdo, &$batch): void {
            if ($batch === []) {
                return;
            }
            $pdo->prepare('INSERT INTO map_web_links (gbif_key, dgfm_taxnr, name, source, category, label, sublabel, url) VALUES '
                . implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, ?, ?)')))
                ->execute(array_merge(...$batch));
            $batch = [];
        };
        $cell = static fn(array $r, string $col): string => trim((string) ($r[$at[$col] ?? -1] ?? ''));
        while (($r = $read()) !== false) {
            if ($r === [null] || $r === []) {
                continue;
            }
            $url = $cell($r, 'url');
            $gbif = (int) $cell($r, 'gbif_key');
            $dgfm = (int) $cell($r, 'dgfm_taxnr');
            $name = web_links_binomial($cell($r, 'wiss_name'));
            if (!preg_match('~^https?://~i', $url) || ($gbif <= 0 && $dgfm <= 0 && $name === '')) {
                $skipped++;
                continue;
            }
            $source = mb_substr($cell($r, 'quelle'), 0, 40);
            if (!isset($seen[$source])) {
                $flush();
                $empty->execute([$source]);
                $seen[$source] = true;
            }
            $batch[] = [$gbif > 0 ? $gbif : null, $dgfm > 0 ? $dgfm : null, mb_substr($name, 0, 190),
                        $source, mb_substr($cell($r, 'kategorie') ?: 'Weitere', 0, 40),
                        mb_substr($cell($r, 'label'), 0, 80), mb_substr($cell($r, 'unterlabel'), 0, 40), mb_substr($url, 0, 700)];
            $rows++;
            if (count($batch) >= 500) {
                $flush();
            }
        }
        $flush();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    } finally {
        $gz ? gzclose($h) : fclose($h);
    }
    return ['rows' => $rows, 'skipped' => $skipped, 'sources' => array_keys($seen)];
}

/** How many links are stored, from how many sites — for the tab. */
function web_links_stock(PDO $pdo): array
{
    if (!web_links_ready($pdo)) {
        return ['rows' => 0, 'sources' => []];
    }
    $sources = $pdo->query('SELECT label, COUNT(*) AS n FROM map_web_links GROUP BY label ORDER BY n DESC')->fetchAll();
    return ['rows' => array_sum(array_map(static fn(array $z): int => (int) $z['n'], $sources)), 'sources' => $sources];
}
