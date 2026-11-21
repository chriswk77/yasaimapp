<?php
/**
 * Read the data tables of the Red List Centre.
 *
 * The BfN publishes its Red Lists all in the same cut: an archive,
 * containing per organism group a spreadsheet "02_Datentabelle_RL_…" with
 * the same column names. Lichens and ferns and flowering plants differ only
 * in which columns are filled — the plants carry German names, the lichens
 * do not.
 *
 * WHY THE SCRIPT READS THE SPREADSHEET FILE ITSELF
 * The convenient way would have been to convert it once by hand into
 * separated values and then import those. But then the list would hang on an
 * intermediate step nobody can retrace — and at the next release you would
 * wonder with which manual moves the file actually came to be. A spreadsheet
 * is a ZIP with XML inside; PHP can do that out of the box. With that, one
 * command leads from the source into the database.
 */

declare(strict_types=1);

/**
 * Read a spreadsheet as rows of cell values.
 *
 * Only as much of the format as these files need: the shared string table
 * and the first worksheet. Empty cells are skipped, which is why the column
 * sits in the cell reference ("C7") and must be taken from there — otherwise
 * the values slip as soon as nothing is entered somewhere.
 *
 * @return list<array<int,string>>
 */
function bfn_sheet_read(string $xlsx): array
{
    $zip = new ZipArchive();
    if ($zip->open($xlsx) !== true) {
        throw new RuntimeException('Tabellenblatt nicht lesbar: ' . $xlsx);
    }

    $texts = [];
    $raw = $zip->getFromName('xl/sharedStrings.xml');
    if ($raw !== false) {
        $xml = new SimpleXMLElement($raw);
        foreach ($xml->si as $si) {
            // An entry can fall apart into sections when parts are formatted
            // differently. Put together, they yield the text.
            $texts[] = $si->t !== null && count($si->r) === 0
                ? (string) $si->t
                : implode('', array_map('strval', iterator_to_array($si->xpath('.//*[local-name()="t"]') ?: [])));
        }
    }

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) {
        throw new RuntimeException('Keine Tabellenseite in ' . $xlsx);
    }

    $rows = [];
    $xml = new SimpleXMLElement($sheet);
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $cellRef = (string) $c['r'];                       // e.g. "C7"
            $column  = 0;
            foreach (str_split(rtrim($cellRef, '0123456789')) as $b) {
                $column = $column * 26 + (ord($b) - 64);
            }

            $type = (string) $c['t'];
            if ($type === 's') {
                $value = $texts[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = implode('', array_map('strval', iterator_to_array($c->xpath('.//*[local-name()="t"]') ?: [])));
            } else {
                $value = (string) $c->v;
            }

            $cells[$column - 1] = trim($value);
        }
        if ($cells === []) continue;
        ksort($cells);
        $rows[] = $cells + array_fill(0, max(array_keys($cells)) + 1, '');
    }

    return $rows;
}

/**
 * Split up "Cladonia rangiferina (L.) Weber ex F. H. Wigg.".
 *
 * The split comes after the second word — genus and epithet are the name,
 * the rest is the author. The special case sits in the same line: in
 * "Caloplaca arnoldii (Wedd.) Zahlbr. ex Ginzb. subsp. arnoldii" the
 * subspecies suffix stands BEHIND the author. It is taken out and appended
 * to the name, otherwise the subspecies would be named like the species and
 * the two would collapse into one.
 *
 * @return array{0:string,1:string,2:string} Name without author, genus, author
 */
function bfn_names_split(string $full): array
{
    $parts = preg_split('/\s+/u', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) return ['', '', ''];

    $name = [array_shift($parts)];
    // A subgenus, section or series stands where the epithet would —
    // „Rubus sect. Corylifolii Lindl.": marker and name are the name. Taken
    // as epithet, „sect." left „Rubus sect." by „Corylifolii Lindl." (21
    // rows until 26.09.2026, migration 0137). So does a hybrid sign that
    // stands free — „Sorbus x ambigua", five of them; „Aster ×salignus"
    // carries it on the epithet and never needed this.
    if (in_array($parts[0] ?? '', ['subg.', 'sect.', 'ser.', 'x', '×'], true) && isset($parts[1])) {
        $name[] = array_shift($parts);
        $name[] = array_shift($parts);
    } elseif ($parts !== []) {
        $name[] = array_shift($parts);   // epithet
    }

    $author = [];
    for ($i = 0; $i < count($parts); $i++) {
        if (in_array($parts[$i], ['subsp.', 'ssp.', 'var.', 'f.', 'nothosubsp.'], true) && isset($parts[$i + 1])) {
            $name[] = $parts[$i] === 'ssp.' ? 'subsp.' : $parts[$i];
            $name[] = $parts[++$i];
            continue;
        }
        /* THE AGGREGATE IS NAMED, NOT AUTHORED (26.09.2026). „Arum
           maculatum agg." came out as „Arum maculatum" by „agg." — 229
           plant aggregates beside their species of the same binomial, and
           the two collapsed into one suggestion whose fields came from
           either row, while offline the first row took the name. The DGfM
           list names its aggregates the same way (species_qualifier_sql);
           migration 0137 cleaned the stock. */
        if ($parts[$i] === 'agg.') {
            $name[] = 'agg.';
            continue;
        }
        $author[] = $parts[$i];
    }

    // The sensu qualifier stands BEHIND the author, set off by a comma —
    // „Barbarea vulgaris L., s. l." beside „… R. Br., s. str." — and belongs
    // to the name as „agg." does. Only with the comma or alone: „(Ach.) S. L.
    // F. Mey." is a lichenologist, not a qualifier.
    $authorText = implode(' ', $author);
    if (preg_match('/^(?:(.+),\s*)?(s\. l\.|s\. str\.)$/u', $authorText, $m)) {
        $name[] = $m[2];
        $authorText = $m[1];
    }

    return [implode(' ', $name), $name[0], $authorText];
}

/**
 * Fan out collective rows.
 *
 * The BfN sometimes assesses several subspecies in one row and writes them
 * one after another with a hash sign — where from the second name on the
 * species epithet is left out, because it follows from the first:
 *
 *   Armeria maritima subsp. bottendorfensis#Armeria subsp. halleri#…
 *
 * Unsplit, that would sit in the list as a 117-character monster that nobody
 * finds and that names no taxon. Split, it is four names that really exist —
 * each with the same classification, since they were assessed together after
 * all.
 *
 * Fragments consisting only of the genus are dropped: "Opegrapha varia
 * var. varia#Opegrapha" names no taxon in its second part.
 *
 * Affects 21 of 5,798 plant rows and 2 of 2,735 lichen rows.
 *
 * @return list<string>
 */
function bfn_names_fan_out(string $full): array
{
    if (!str_contains($full, '#')) return [trim($full)];

    $parts  = array_values(array_filter(array_map('trim', explode('#', $full)), 'strlen'));
    if ($parts === []) return [];

    $out        = [$parts[0]];
    $firstParts = preg_split('/\s+/u', $parts[0], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $epithet    = $firstParts[1] ?? '';

    foreach (array_slice($parts, 1) as $part) {
        $t = preg_split('/\s+/u', $part, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($t) < 3) continue;                    // only the genus
        if ($epithet !== '' && in_array($t[1], ['subsp.', 'ssp.', 'var.', 'f.'], true)) {
            array_splice($t, 1, 0, $epithet);           // species epithet back
        }
        $out[] = implode(' ', $t);
    }
    return $out;
}

/** Read the rank off the name. */
function bfn_rank(string $name): string
{
    if (str_contains($name, ' subsp. ')) return 'Unterart';
    if (str_contains($name, ' var. '))   return 'Varietät';
    if (str_contains($name, ' f. '))     return 'Form';
    return 'Art';
}

/**
 * Turn an archive of the Red List Centre into rows for the database.
 *
 * Valid names get running numbers, the synonyms behind them — their sipnr
 * points to the valid name. The same shape as in the DGfM list, so that
 * search and delivery do not look different per dataset.
 *
 * @param callable(string): ?string $groupFor File name in the archive →
 *                                              group, or null to skip
 * @return array{rows: list<array<string,mixed>>, valid: int, empty: int,
 *               per_group: array<string,int>}
 */
function bfn_archive_read(string $zipPath, callable $groupFor): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Das Archiv lässt sich nicht öffnen.');
    }

    $valid      = [];
    $synonyms   = [];
    $perGroup   = [];
    $number     = 0;
    $emptyCount = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = (string) $zip->getNameIndex($i);
        $group = $groupFor($entry);
        if ($group === null) continue;

        // The spreadsheets sit inside the archive and are archives
        // themselves. A ZipArchive cannot look inside a ZipArchive, so
        // write it out once.
        $temp = tempnam(sys_get_temp_dir(), 'bfn') . '.xlsx';
        file_put_contents($temp, $zip->getFromIndex($i));
        $rows = bfn_sheet_read($temp);
        unlink($temp);

        $header = array_flip(array_map('strval', array_shift($rows)));
        if (!isset($header['Name'])) {
            throw new RuntimeException("Spalte \"Name\" fehlt in $entry");
        }
        $get = static fn(array $z, string $column): string
            => isset($header[$column]) ? trim((string) ($z[$header[$column]] ?? '')) : '';

        // THE STABLE IDENTIFIER, under two names. The lichen list carries it
        // as "UUID des Taxons", the plant list as "Publikations-Id des
        // Taxons" — the same thing, and both times a UUID. Without it there
        // would be no reference that survives a re-import: taxnr is assigned
        // by this script itself, sequentially, and it shifts in the process.
        $getUuid = static function (array $z) use ($header, $get): string {
            foreach (['UUID des Taxons', 'Publikations-Id des Taxons'] as $column) {
                $value = $get($z, $column);
                if ($value !== '') return $value;
            }
            return '';
        };

        $before = $number;
        foreach ($rows as $z) {
            $full = $get($z, 'Name');
            if ($full === '') { $emptyCount++; continue; }

            $firstNumber = null;
            foreach (bfn_names_fan_out($full) as $single) {
                [$name, $genus, $author] = bfn_names_split($single);
                if ($name === '') continue;
                $number++;
                $firstNumber ??= $number;
                $valid[] = [
                    'taxnr'    => $number,
                    'uuid'     => $getUuid($z),
                    'sipnr'    => $number,
                    'taxon_rank'     => bfn_rank($name),
                    'binomial' => $name,
                    'genus'  => $genus,
                    'epithet'      => trim(substr($name, strlen($genus))),
                    'author'    => $author,
                    'german'  => $get($z, 'Deutscher Name'),
                    'rl'       => $get($z, 'RL Kat.'),
                    'status'   => $get($z, 'Status'),
                    'group'   => $group,
                ];
            }

            // The synonym hangs on the row, hence on the first of the names.
            $syn = $get($z, 'Synonyme');
            if ($syn !== '' && $firstNumber !== null) {
                $synonyms[] = ['full' => $syn, 'target' => $firstNumber, 'group' => $group];
            }
        }
        $perGroup[$group] = ($perGroup[$group] ?? 0) + ($number - $before);
    }
    $zip->close();

    if ($valid === []) {
        throw new RuntimeException('Keine Datentabelle im Archiv gefunden.');
    }

    // Synonyms behind the valid names, so that the numbers stay stable.
    // They too can be collective entries — with the plants it is in fact
    // exclusively the synonyms in which the hash sign shows up.
    $rows = $valid;
    foreach ($synonyms as $s) {
        foreach (bfn_names_fan_out($s['full']) as $single) {
            [$name, $genus, $author] = bfn_names_split($single);
            if ($name === '') continue;
            $number++;
            $rows[] = [
                'taxnr'    => $number,
                // Synonym rows have no entry of their own in the source,
                // hence no identifier either. References always go to the
                // valid name anyway.
                'uuid'     => '',
                'sipnr'    => $s['target'],
                'taxon_rank'     => bfn_rank($name),
                'binomial' => $name,
                'genus'  => $genus,
                'epithet'      => trim(substr($name, strlen($genus))),
                'author'    => $author,
                'german'  => '',
                'rl'       => '',
                'status'   => '',
                'group'   => $s['group'],
            ];
        }
    }

    return [
        'rows'    => $rows,
        'valid'   => count($valid),
        'empty'      => $emptyCount,
        'per_group' => $perGroup,
    ];
}

/**
 * Write the rows that were read into a table.
 *
 * Empty becomes NULL — but with an explicit comparison. With ?: the threat
 * category "0" (extinct or lost) would vanish, because the string "0" is
 * falsy for PHP. That affected 171 species among the lichens and only
 * surfaced when checking against the source file.
 */
function bfn_write(PDO $pdo, string $table, array $rows): void
{
    $pdo->exec("TRUNCATE TABLE $table");

    $stmt = $pdo->prepare(
        "INSERT INTO $table
            (taxnr, uuid, sipnr, taxon_rank, binomial, genus, epithet, author,
             german_name, red_list_de, status, group_name, search_text)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $emptyToNull = static fn(string $w): ?string => $w === '' ? null : $w;

    $pdo->beginTransaction();
    foreach ($rows as $z) {
        $stmt->execute([
            $z['taxnr'], $emptyToNull($z['uuid']), $z['sipnr'], $z['taxon_rank'], $z['binomial'], $z['genus'],
            $emptyToNull($z['epithet']), $emptyToNull($z['author']),
            $emptyToNull($z['german']), $emptyToNull($z['rl']),
            $emptyToNull($z['status']), $z['group_name'],
            str_replace('-', ' ', trim($z['binomial'] . ' ' . $z['german'])),
        ]);
    }
    $pdo->commit();
}
