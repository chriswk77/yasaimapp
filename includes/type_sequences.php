<?php
/**
 * The sequences of a species' TYPE MATERIAL from GenBank
 * (15.10.2026: „können wir für eine Art die Typus- oder Neotypus-Sequenz
 * ermitteln und diese gleich mit anzeigen, auch wenn man noch nichts
 * hochgeladen hat … und ein Download der Typussequenz").
 *
 * WHERE THEY COME FROM: NCBI's nucleotide database and its filter „sequence
 * from type" (E-utilities: esearch, esummary; efetch for the FASTA). Every
 * such record names, in its type_material qualifier, WHAT it is the type of
 * — „epitype of Cantharellus cibarius", „holotype of Boletus edulis var.
 * ochraceus". Measured 15.10.2026: Cantharellus cibarius gave 8 records, the
 * epitype BIO-Fungi 10986 with 28S and tef-1 among them; Boletus edulis one,
 * the holotype of a variety.
 *
 * ONLY THE TYPE OF EXACTLY THIS NAME: the organism filter of GenBank takes
 * the varieties along, and a variety's holotype is not the species' type. A
 * record stays when the name after „… of" is the name asked for; a find
 * named as the variety asks for the variety and gets its type.
 *
 * ONLY WHAT THE SUBMITTERS MARKED: many species have no record flagged as
 * type — then the list is empty, and the window shows nothing rather than a
 * block that says nothing.
 *
 * KEPT ON THE SERVER, a file per name in data/cache/type_sequences, for 30
 * days (a failure for ten minutes): NCBI allows three requests a second without
 * a key, and the tab must open at once on the next visit. Nothing about the
 * user goes to NCBI — the name of a species and the tool's name, no more.
 */

declare(strict_types=1);

const TYPE_SEQUENCES_EUTILS  = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/';
const TYPE_SEQUENCES_KEEP    = 30 * 86400;
const TYPE_SEQUENCES_RETRY   = 600;
const TYPE_SEQUENCES_TIMEOUT = 12;

/* The order a type ranks in: the name-bearing one first, its stand-ins after. */
const TYPE_SEQUENCES_KINDS = ['holotype', 'epitype', 'neotype', 'lectotype', 'isotype', 'isoepitype',
                              'isoneotype', 'isolectotype', 'syntype', 'paratype', 'ex-type', 'type'];

/**
 * The name GenBank is asked for: the binomial at the head of a find's
 * species, with an infraspecific rank and epithet where it stands („var.
 * ochraceus"); author, „cf.", „s. l." and German names stay out. Null where
 * the title does not begin with a scientific binomial.
 */
function type_sequences_name(string $title): ?string
{
    $title = trim(preg_replace('/\s+/u', ' ', $title));
    if (preg_match('/^([A-Z][a-z]+) (?:(?:cf|aff)\.? )?([a-z][a-z-]+)(?: (var|subsp|f|forma)\.? ([a-z][a-z-]+))?/u', $title, $m) !== 1) {
        return null;
    }
    $name = $m[1] . ' ' . $m[2];
    if (!empty($m[3]) && !empty($m[4])) {
        $rank = $m[3] === 'forma' ? 'f' : $m[3];
        $name .= ' ' . $rank . '. ' . $m[4];
    }
    return $name;
}

/**
 * One request to the E-utilities; null where it did not answer. ONCE MORE
 * AFTER A BREATH: NCBI answers a fourth request within a second with 429, and
 * two finds opened quickly ask four times — measured 15.10.2026, the second
 * name of a quick pair came back empty.
 */
function type_sequences_get(string $tool, array $query): ?string
{
    $answer = type_sequences_get_once($tool, $query);
    if ($answer === null) {
        usleep(700000);
        $answer = type_sequences_get_once($tool, $query);
    }
    return $answer;
}

function type_sequences_get_once(string $tool, array $query): ?string
{
    $url = TYPE_SEQUENCES_EUTILS . $tool . '?' . http_build_query($query + ['tool' => 'yasaimapp']);
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => TYPE_SEQUENCES_TIMEOUT, 'ignore_errors' => true,
                   'header' => "User-Agent: YASAIMAPP (type sequences)\r\n"],
    ]));
    if ($response === false) {
        return null;
    }
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    return $status === 200 ? $response : null;
}

/** Which gene a record holds, read off its title — the markers fungi are sequenced at. */
function type_sequences_marker(string $title): string
{
    $t = strtolower($title);
    return match (true) {
        str_contains($t, 'internal transcribed spacer') || preg_match('/\bits[12]?\b/', $t) === 1 => 'ITS',
        str_contains($t, '28s') || str_contains($t, 'large subunit') => 'LSU (28S)',
        str_contains($t, '18s') || str_contains($t, 'small subunit') => 'SSU (18S)',
        str_contains($t, 'elongation factor') || str_contains($t, 'tef') => 'tef-1',
        str_contains($t, 'polymerase ii second') || str_contains($t, 'rpb2') => 'rpb2',
        str_contains($t, 'polymerase ii largest') || str_contains($t, 'rpb1') => 'rpb1',
        str_contains($t, 'tubulin') => 'β-Tubulin',
        str_contains($t, 'mitochondrial') => 'mtDNA',
        default => '',
    };
}

/**
 * Ask GenBank — the type records of exactly this name, best first.
 *
 * @return ?list<array{accession: string, kind: string, of: string, marker: string,
 *                     title: string, length: int, voucher: string, country: string}>
 *         null where NCBI could not be asked
 */
function type_sequences_fetch(string $name): ?array
{
    $search = type_sequences_get('esearch.fcgi', [
        'db' => 'nuccore', 'retmode' => 'json', 'retmax' => 100,
        'term' => '"' . $name . '"[Organism] AND sequence_from_type[Filter]',
    ]);
    $ids = $search === null ? null : (json_decode($search, true)['esearchresult']['idlist'] ?? null);
    if (!is_array($ids)) {
        return null;
    }
    if ($ids === []) {
        return [];
    }
    $summary = type_sequences_get('esummary.fcgi', ['db' => 'nuccore', 'retmode' => 'json', 'id' => implode(',', $ids)]);
    $result = $summary === null ? null : (json_decode($summary, true)['result'] ?? null);
    if (!is_array($result)) {
        return null;
    }
    $wanted = mb_strtolower($name);
    $out = [];
    foreach ($result['uids'] ?? [] as $uid) {
        $r = $result[$uid] ?? null;
        if (!is_array($r)) {
            continue;
        }
        $fields = array_combine(
            explode('|', (string) ($r['subtype'] ?? '')),
            array_pad(explode('|', (string) ($r['subname'] ?? '')), count(explode('|', (string) ($r['subtype'] ?? ''))), '')
        ) ?: [];
        $type = trim((string) ($fields['type_material'] ?? ''));
        // „epitype of Cantharellus cibarius" — the kind, and of which name.
        if (preg_match('/^(\S+)\s+of\s+(.+)$/iu', $type, $m) !== 1) {
            continue;
        }
        $of = trim(preg_replace('/\s+/u', ' ', $m[2]));
        if (mb_strtolower($of) !== $wanted) {
            continue;
        }
        $out[] = [
            'accession' => (string) ($r['accessionversion'] ?? $r['caption'] ?? ''),
            'kind'      => mb_strtolower($m[1]),
            'of'        => $of,
            'marker'    => type_sequences_marker((string) ($r['title'] ?? '')),
            'title'     => (string) ($r['title'] ?? ''),
            'length'    => (int) ($r['slen'] ?? 0),
            'voucher'   => (string) ($fields['specimen_voucher'] ?? $fields['culture_collection'] ?? $fields['strain'] ?? ''),
            'country'   => (string) ($fields['country'] ?? ''),
        ];
    }
    // The name-bearing type first, then by marker (ITS before the others), then by accession.
    $rank = static fn(array $s): array => [
        ($i = array_search($s['kind'], TYPE_SEQUENCES_KINDS, true)) === false ? 99 : $i,
        $s['marker'] === 'ITS' ? 0 : ($s['marker'] === '' ? 2 : 1),
        $s['marker'], $s['accession'],
    ];
    usort($out, static fn(array $a, array $b): int => $rank($a) <=> $rank($b));
    return $out;
}

/**
 * How many sequences GenBank holds of the name at all, type or not — what
 * the window says where no type was flagged (15.10.2026, at
 * Flammula alnicola: nothing at all looked like „no sequence at all", while
 * GenBank holds 604 of it). Null where NCBI did not answer.
 */
function type_sequences_count(string $name): ?int
{
    $search = type_sequences_get('esearch.fcgi', [
        'db' => 'nuccore', 'retmode' => 'json', 'retmax' => 0, 'term' => '"' . $name . '"[Organism]',
    ]);
    $count = $search === null ? null : (json_decode($search, true)['esearchresult']['count'] ?? null);
    return is_numeric($count) ? (int) $count : null;
}

/** Where the answer for a name is kept. */
function type_sequences_cache_file(string $name): string
{
    return PROJECT_PATH . '/data/cache/type_sequences/' . sha1(mb_strtolower($name)) . '.json';
}

/**
 * The type sequences of a name — from the server's own store while it is
 * fresh, otherwise from GenBank.
 *
 * @return array{name: string, sequences: list<array>, all: ?int, ok: bool}
 *         all: how many sequences of the name GenBank holds, type or not;
 *         ok false: GenBank could not be asked and nothing was kept
 */
function type_sequences_for(string $name): array
{
    $file = type_sequences_cache_file($name);
    $kept = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    // A record from before the count was kept is asked anew.
    if (is_array($kept) && isset($kept['until']) && $kept['until'] > time() && array_key_exists('all', $kept)) {
        return ['name' => $name, 'sequences' => $kept['sequences'] ?? [], 'all' => $kept['all'],
                'ok' => (bool) ($kept['ok'] ?? true)];
    }
    $sequences = type_sequences_fetch($name);
    $all = $sequences !== null ? type_sequences_count($name) : null;
    $ok = $sequences !== null;
    if (!$ok && is_array($kept)) {
        // NCBI silent — what was kept stays good a little longer.
        $sequences = $kept['sequences'] ?? [];
        $all = $kept['all'] ?? null;
        $ok = true;
    }
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0775, true);
    }
    @file_put_contents($file, json_encode([
        'name'      => $name,
        'until'     => time() + ($sequences === null ? TYPE_SEQUENCES_RETRY : TYPE_SEQUENCES_KEEP),
        'ok'        => $ok,
        'all'       => $all,
        'sequences' => $sequences ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return ['name' => $name, 'sequences' => $sequences ?? [], 'all' => $all, 'ok' => $ok];
}

/**
 * TODAY'S NAME OF A FIND'S SPECIES after GBIF (15.10.2026: „frag
 * auch mit dem heutigen Namen nach GBIF") — the chain the cloud archive
 * files by (cloud_find_from_point: the title's DGfM reference, through
 * map_tax_links to GBIF, a synonym to its accepted name), so GenBank is asked
 * under both where a find still carries an older name. Null where the find
 * is out of sight or names no species; the binomial, never an infraspecific
 * rank (GBIF's accepted name there is the species).
 */
function type_sequences_today(PDO $pdo, int $userId, int $pointId): ?string
{
    require_once __DIR__ . '/point_owners.php';
    require_once __DIR__ . '/cloud.php';
    if ($pointId <= 0 || point_view_scope($pdo, $userId, $pointId) === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    try {
        $find = cloud_find_from_point($pdo, (int) $row['user_id'], $row);
    } catch (Throwable) {
        return null;
    }
    $genus = trim((string) ($find['genus'] ?? ''));
    $species = trim((string) ($find['species'] ?? ''));
    return $genus !== '' && $species !== '' ? $genus . ' ' . $species : null;
}

/** The FASTA of one record, as GenBank gives it; null where it did not answer. */
function type_sequences_fasta(string $accession): ?string
{
    if (preg_match('/^[A-Z]{1,6}_?\d{5,9}(\.\d+)?$/', $accession) !== 1) {
        return null;
    }
    $fasta = type_sequences_get('efetch.fcgi', ['db' => 'nuccore', 'id' => $accession, 'rettype' => 'fasta', 'retmode' => 'text']);
    return $fasta !== null && str_starts_with(ltrim($fasta), '>') ? $fasta : null;
}

// ---------------------------------------------------------------------------
// UNITE — the species hypotheses of the ITS (15.10.2026: „nimm
// auch UNITE mit dazu")
// ---------------------------------------------------------------------------

/*
 * WHAT UNITE GIVES: a species hypothesis (SH) is a cluster of ITS sequences
 * that stand for one species; at the default distance of 1.5 % one of them
 * is set by a curator as the REFERENCE sequence (or, where nobody set one,
 * one is the representative). That is what one compares one's own ITS with.
 *
 * THE WAY THROUGH PLUTOF, read off UNITE's own pages (their API is barely
 * documented; measured 15.10.2026 with Cantharellus cibarius): the taxon by
 * its name (taxonomy/taxonnodes/search), its SHs of the current version at
 * 1.5 % with the most sequences first (public/dshclusters/search), an SH by
 * its code for its DOI (public/dshclusters), the DOI's record
 * (public/dois/<id>) and the JSON attached to it, which names the
 * reference sequence with its INSD accession. Cantharellus cibarius →
 * SH0870750.10FU, 238 sequences, reference LC085408 set by Urmas Kõljalg.
 *
 * NOTHING AT 1.5 % IS AN ANSWER, NOT A FAULT. Flammula alnicola (a synonym
 * there of Pholiota alnicola) has in version 10 only two singletons at
 * 1.0 and 0.5 % and no DOI to them; the 1.5 % cluster its sequences fall
 * into carries another name. The page says „keine Artenhypothese" then,
 * rather than offering a cluster of some other name as this species'.
 *
 * THE JSON IS LARGE (0.7 MB for that SH: every sequence of the cluster), so
 * only the two largest SHs of a name are opened, and the answer is kept with
 * GenBank's for a month.
 */

const UNITE_API = 'https://api.plutof.ut.ee/v1/';
const UNITE_VERSION = 10;
const UNITE_THRESHOLD = '1.5';
const UNITE_SH_OPENED = 2;

/** One JSON answer of PlutoF (or of the file store it points to); null where it did not come. */
function unite_get(string $url, int $timeout = TYPE_SEQUENCES_TIMEOUT): ?array
{
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true,
                   'header' => "Accept: application/json, application/vnd.api+json\r\nUser-Agent: YASAIMAPP (type sequences)\r\n"],
    ]));
    if ($response === false) {
        return null;
    }
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m) && (int) $m[1] !== 200) {
            $status = (int) $m[1];
        } elseif (preg_match('#^HTTP/\S+ 200#', $line)) {
            $status = 200;
        }
    }
    if (($status ?? 0) !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

/**
 * The SHs of a name, largest first, each with its reference (or
 * representative) sequence.
 *
 * @return ?list<array{code: string, sequences: int, designated: bool, doi: string,
 *                     accession: ?string, kind: ?string, set_by: ?string, from_type: bool}>
 *         null where UNITE could not be asked
 */
function unite_fetch(string $name): ?array
{
    /* THE TAXON BY ITS NAME — and where UNITE keeps the name as a synonym,
       its accepted name: the SHs hang on that one (measured: „Flammula
       alnicola" is a synonym there, its SHs stand under Pholiota alnicola). */
    $taxon = null;
    for ($hop = 0, $wanted = $name; $hop < 2 && $taxon === null; $hop++) {
        $nodes = unite_get(UNITE_API . 'taxonomy/taxonnodes/search/?' . http_build_query(['name' => $wanted]));
        if ($nodes === null) {
            return null;
        }
        $next = null;
        foreach ($nodes['results'] ?? [] as $r) {
            if (mb_strtolower(trim((string) ($r['taxon_name'] ?? ''))) !== mb_strtolower($wanted) || !empty($r['is_deleted'])) {
                continue;
            }
            if (!empty($r['is_synonym']) && !empty($r['valid_name'])) {
                // The accepted name's binomial, without its author.
                $next = implode(' ', array_slice(preg_split('/\s+/u', trim((string) $r['valid_name'])) ?: [], 0, 2));
            } else {
                $taxon = (int) $r['id'];
            }
            break;
        }
        if ($taxon === null && $next === null) {
            return [];
        }
        $wanted = $next ?? $wanted;
    }
    if ($taxon === null) {
        return [];
    }
    $found = unite_get(UNITE_API . 'public/dshclusters/search/?' . http_build_query([
        'taxon' => $taxon, 'version' => UNITE_VERSION, 'threshold' => UNITE_THRESHOLD, 'ordering' => '-sequence_count',
    ]));
    if ($found === null) {
        return null;
    }
    $out = [];
    foreach (array_slice($found['data'] ?? [], 0, UNITE_SH_OPENED) as $sh) {
        $code = (string) ($sh['attributes']['name'] ?? '');
        if ($code === '') {
            continue;
        }
        $entry = [
            'code'       => $code,
            'sequences'  => (int) ($sh['attributes']['sequence_count'] ?? 0),
            'designated' => !empty($sh['attributes']['designators']),
            'doi'        => 'https://doi.org/10.15156/BIO/' . $code,
            'accession'  => null, 'kind' => null, 'set_by' => null, 'from_type' => false,
        ];
        // The SH by its code names its DOI; the DOI's record carries the JSON with the reference.
        $one = unite_get(UNITE_API . 'public/dshclusters/?' . http_build_query(['code' => $code]));
        $doiId = $one['data'][0]['relationships']['doi']['data']['id'] ?? null;
        $doi = $doiId ? unite_get(UNITE_API . 'public/dois/' . rawurlencode((string) $doiId) . '/') : null;
        $json = null;
        foreach ($doi['data']['attributes']['media'] ?? [] as $media) {
            if (($media['content_type'] ?? '') === 'application/json' && !empty($media['url'])) {
                $json = unite_get((string) $media['url'], 30);
            }
        }
        $hypothesis = $json['species_hypotheses'] ?? [];
        foreach (['reference_sequence' => 'reference', 'representative_sequence' => 'representative'] as $field => $kind) {
            $seq = $hypothesis[$field] ?? null;
            if (!is_array($seq)) {
                continue;
            }
            foreach ($seq['accnos'] ?? [] as $acc) {
                if (($acc['database'] ?? '') === 'INSD' && !empty($acc['accno'])) {
                    $entry['accession'] = (string) $acc['accno'];
                    break;
                }
            }
            $entry['kind'] = $kind;
            // A person's name only — „automatically by the program" is what UNITE writes where nobody chose.
            $by = trim((string) ($seq['set_by'] ?? ''));
            $entry['set_by'] = $by !== '' && stripos($by, 'automatically') === false ? $by : null;
            $entry['from_type'] = !empty($seq['derived_from_type']);
            break;
        }
        $out[] = $entry;
    }
    return $out;
}

/** The SHs of a name — kept on the server for a month like GenBank's answer. */
function unite_for(string $name): array
{
    $file = PROJECT_PATH . '/data/cache/unite/' . sha1(mb_strtolower($name)) . '.json';
    $kept = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (is_array($kept) && ($kept['until'] ?? 0) > time()) {
        return ['hypotheses' => $kept['hypotheses'] ?? [], 'ok' => (bool) ($kept['ok'] ?? true)];
    }
    $hypotheses = unite_fetch($name);
    $ok = $hypotheses !== null;
    if (!$ok && is_array($kept)) {
        $hypotheses = $kept['hypotheses'] ?? [];
        $ok = true;
    }
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0775, true);
    }
    @file_put_contents($file, json_encode([
        'name' => $name, 'until' => time() + ($hypotheses === null ? TYPE_SEQUENCES_RETRY : TYPE_SEQUENCES_KEEP),
        'ok' => $ok, 'hypotheses' => $hypotheses ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return ['hypotheses' => $hypotheses ?? [], 'ok' => $ok];
}
