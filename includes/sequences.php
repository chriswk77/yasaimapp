<?php
/**
 * DNA sequences at a find — the second kind of voucher
 * (22.09.2026). Migration 0112.
 *
 * WHY A VOUCHER AND NOT A FIELD: the model of 11.09.2026 draws one line
 * through everything that happens to a find — a BELEG is a thing you made
 * and it is yours (a picture, its photographer), a BEARBEITUNG is work on
 * somebody else's statement and makes no owner (a corrected title, a note).
 * A sequence sits on that line: paid for, produced, referenced by an
 * accession number — a voucher —, but typed in as text. Typed in as a
 * field it would be a Bearbeitung, and whoever sequenced a friend's find
 * would lose it with the friendship. So it is a record of its own with an
 * author, and the author becomes a CO-OWNER exactly as a photographer does
 * (point_owner_add): he keeps the find. Only he deletes his sequence.
 *
 * WHY TEXT AND NOT A PICTURE OF THE RESULT („eine Gensequenz
 * ist Text, und Text können wir später verarbeiten und weiter auswerten,
 * ein Bild eben nicht"): the bases are stored normalised — upper case,
 * IUPAC letters and gaps, nothing else — so that whatever compares,
 * exports or looks them up later finds one shape. What does not pass
 * that check is refused, never stored quietly.
 *
 * WHO MAY HANG ONE ON A FIND: the same doors as for a picture
 * (point_attach_scope) — the holder, a co-owner, a member with the
 * delete_photos right (every member of a joint list has it), the
 * excursion leader. Deliberately no right of its own: whoever is trusted
 * with the pictures is trusted with the sequence, and a new entry in
 * SHARE_RIGHTS would cost every share dialog a checkbox.
 */

declare(strict_types=1);

require_once __DIR__ . '/point_owners.php';

/** The markers offered first — free text stays possible. */
const SEQUENCE_MARKERS = ['ITS', 'LSU', 'SSU', 'RPB1', 'RPB2', 'TEF1', 'mtSSU', 'COI'];

/** IUPAC nucleotide letters; a gap is allowed too. */
const SEQUENCE_LETTERS = 'ACGTURYKMSWBDHVN';

function sequences_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_point_sequences');
}

/**
 * FASTA or bare bases → one entry per sequence.
 *
 * A FASTA file with several records yields several entries, each with
 * its header as label. Bare bases without a header are one entry with
 * an empty label. Line numbers and whitespace (a GenBank-style listing)
 * are stripped; any other letter is a refusal with the letter named.
 *
 * @return array<array{label:string, bases:string}>
 */
function sequence_parse(string $text): array
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        throw new InvalidArgumentException(t('Es ist keine Sequenz angekommen.'));
    }
    $entries = [];
    $label   = '';
    $buffer  = '';
    $flush = static function () use (&$entries, &$label, &$buffer): void {
        $bases = sequence_normalise($buffer);
        if ($bases !== '') {
            $entries[] = ['label' => $label, 'bases' => $bases];
        }
        $label  = '';
        $buffer = '';
    };
    foreach (explode("\n", $text) as $line) {
        if (str_starts_with($line, '>')) {
            $flush();
            $label = trim(mb_substr(substr($line, 1), 0, 200));
            continue;
        }
        if (str_starts_with($line, ';')) {
            continue; // the old FASTA comment line
        }
        $buffer .= $line;
    }
    $flush();
    if ($entries === []) {
        throw new InvalidArgumentException(t('Es ist keine Sequenz angekommen.'));
    }
    return $entries;
}

/** Upper case, without whitespace and digits; every remaining letter checked. */
function sequence_normalise(string $raw): string
{
    $bases = strtoupper((string) preg_replace('/[\s\d]+/u', '', $raw));
    if ($bases === '') {
        return '';
    }
    if (preg_match('/[^' . SEQUENCE_LETTERS . '\-]/', $bases, $m) === 1) {
        throw new InvalidArgumentException(t('Das ist keine Sequenz: „%s" ist kein Basen-Zeichen.', $m[0]));
    }
    return $bases;
}

/** The row as the interface reads it — with the author's name on it. */
function sequence_prepare(array $z): array
{
    return [
        'id'         => (int) $z['id'],
        'point_id'   => (int) $z['point_id'],
        'user_id'    => (int) $z['user_id'],
        'author'     => (string) ($z['author'] ?? ''),
        'marker'     => (string) $z['marker'],
        'label'      => (string) $z['label'],
        'bases'      => (string) $z['bases'],
        'length'     => (int) $z['length'],
        'lab'        => (string) $z['lab'],
        'sample_no'  => (string) $z['sample_no'],
        'accession'  => (string) $z['accession'],
        'primers'    => (string) $z['primers'],
        'note'       => (string) ($z['note'] ?? ''),
        'created_at' => (string) $z['created_at'],
    ];
}

/**
 * The sequences of the given points, point id → rows. NOT account-bound
 * (unlike photos_for_points): whoever sees the find sees its vouchers,
 * each with the sequencer's name.
 *
 * @return array<int, array<int, array>>
 */
function sequences_for_points(PDO $pdo, array $pointIds): array
{
    $pointIds = array_values(array_unique(array_map('intval', $pointIds)));
    if ($pointIds === [] || !sequences_ready($pdo)) {
        return [];
    }
    $space = implode(',', array_fill(0, count($pointIds), '?'));
    $stmt  = $pdo->prepare(
        'SELECT s.*, ' . sql_display_name() . ' AS author
           FROM map_point_sequences s
           JOIN map_users u ON u.id = s.user_id
          WHERE s.point_id IN (' . $space . ')
          ORDER BY s.created_at, s.id'
    );
    $stmt->execute($pointIds);
    $out = [];
    foreach ($stmt as $z) {
        $out[(int) $z['point_id']][] = sequence_prepare($z);
    }
    return $out;
}

/** One sequence by id, or null. */
function sequence_load(PDO $pdo, int $id): ?array
{
    if (!sequences_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT s.*, ' . sql_display_name() . ' AS author
           FROM map_point_sequences s JOIN map_users u ON u.id = s.user_id
          WHERE s.id = ?'
    );
    $stmt->execute([$id]);
    $z = $stmt->fetch();
    return $z === false ? null : sequence_prepare($z);
}

/** The text fields of a sequence, trimmed and cut to their columns. */
function sequence_meta(array $data): array
{
    $cut = static fn(string $key, int $max): string => mb_substr(trim((string) ($data[$key] ?? '')), 0, $max);
    return [
        'marker'    => $cut('marker', 40),
        'lab'       => $cut('lab', 120),
        'sample_no' => $cut('sample_no', 80),
        'accession' => $cut('accession', 80),
        'primers'   => $cut('primers', 120),
        'note'      => $cut('note', 5000),
    ];
}

/**
 * Hang sequences on a find. $data['text'] is FASTA or bare bases; the
 * rest are the columns. Every FASTA record becomes a row of its own,
 * with its header as label; a bare sequence takes $data['label'].
 *
 * @return array the created rows, prepared
 */
function sequence_create(PDO $pdo, int $userId, int $pointId, array $data): array
{
    if (!sequences_ready($pdo)) {
        throw new RuntimeException(t('Sequenzen brauchen die Migration 0112 — sie ist noch nicht eingespielt.'));
    }
    $holder = point_attach_scope($pdo, $userId, $pointId);
    if ($holder === null) {
        throw new InvalidArgumentException(t('Punkt nicht gefunden.'));
    }
    /* ONLY ON A FIND (02.10.2026: „die Sequenzen nur bei
       Punkten anbieten, die auch in einer Artenliste liegen, weil wir da
       wissen, dass es Lebewesen sind — und keine Parkplätze oder
       Campingplätze"). The point window offers none elsewhere; this says
       the same to a request that comes another way. Asked of the BRANCH,
       as the protected area asks it (protected_area_record): any list the
       point hangs in, the holder's or a co-owner's filing, with a species
       list anywhere above it. Sequences already hanging on a point that
       left its species list stay — only new ones are refused. */
    require_once __DIR__ . '/point_species.php';
    $lists = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $lists->execute([$pointId]);
    if (!point_species_fields($pdo, array_map('intval', $lists->fetchAll(PDO::FETCH_COLUMN)))['title']) {
        throw new InvalidArgumentException(t('Sequenzen gibt es nur bei Funden in einer Artenliste.'));
    }
    /* NOT ON AN AUFSAMMLUNG (15.11.2026: „eine Aufsammlung zu
       sequenzieren, egal was es ist, ob es ein Grashalm ist, ein Insekt
       oder eine Hinterlassenschaft … das kommt nicht vor"). The box of
       dung or the moist chamber is the substrate, not an organism; the
       sequence belongs to the species find that grew out of it. Asked
       only where the column exists (migration 0174). */
    if (column_exists($pdo, 'map_points', 'is_collection')) {
        $box = $pdo->prepare('SELECT is_collection FROM map_points WHERE id = ?');
        $box->execute([$pointId]);
        if ((int) $box->fetchColumn() === 1) {
            throw new InvalidArgumentException(t('Eine Aufsammlung bekommt keine Sequenz — sie gehört zum Fund der Art, die daraus gewachsen ist.'));
        }
    }
    $entries = sequence_parse((string) ($data['text'] ?? ''));
    $meta    = sequence_meta($data);
    $label   = mb_substr(trim((string) ($data['label'] ?? '')), 0, 200);

    $insert = $pdo->prepare(
        'INSERT INTO map_point_sequences
            (point_id, user_id, marker, label, bases, length, lab, sample_no, accession, primers, note, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $ids = [];
    foreach ($entries as $e) {
        $insert->execute([
            $pointId, $userId, $meta['marker'],
            $e['label'] !== '' ? $e['label'] : $label,
            $e['bases'], strlen($e['bases']),
            $meta['lab'], $meta['sample_no'], $meta['accession'], $meta['primers'],
            $meta['note'] === '' ? null : $meta['note'],
        ]);
        $ids[] = (int) $pdo->lastInsertId();
    }

    /* THE SEQUENCER BECOMES A CO-OWNER, like the photographer
       (point_owners.php, 11.09.2026): the find stays his, whatever
       happens to the friendship or the share. */
    if ($holder !== $userId) {
        point_owner_add($pdo, $pointId, $userId);
    }
    // The point is touched so every device learns of the new voucher.
    $pdo->prepare('UPDATE map_points SET updated_at = NOW() WHERE id = ?')->execute([$pointId]);

    $rows = sequences_for_points($pdo, [$pointId])[$pointId] ?? [];
    return array_values(array_filter($rows, static fn(array $s): bool => in_array($s['id'], $ids, true)));
}

/** The author edits the columns of his sequence — never the bases: a changed voucher is a new voucher. */
function sequence_update(PDO $pdo, int $userId, int $id, array $data): bool
{
    if (!sequences_ready($pdo)) {
        return false;
    }
    $meta = sequence_meta($data);
    $stmt = $pdo->prepare(
        'UPDATE map_point_sequences
            SET marker = ?, label = ?, lab = ?, sample_no = ?, accession = ?, primers = ?, note = ?, updated_at = NOW()
          WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
        $meta['marker'], mb_substr(trim((string) ($data['label'] ?? '')), 0, 200),
        $meta['lab'], $meta['sample_no'], $meta['accession'], $meta['primers'],
        $meta['note'] === '' ? null : $meta['note'],
        $id, $userId,
    ]);
    return $stmt->rowCount() > 0;
}

/** Only the sequencer deletes his sequence. */
function sequence_delete(PDO $pdo, int $userId, int $id): bool
{
    if (!sequences_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT point_id FROM map_point_sequences WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $pointId = $stmt->fetchColumn();
    if ($pointId === false) {
        return false;
    }
    $pdo->prepare('DELETE FROM map_point_sequences WHERE id = ?')->execute([$id]);
    $pdo->prepare('UPDATE map_points SET updated_at = NOW() WHERE id = ?')->execute([(int) $pointId]);
    return true;
}

/** The sequence as FASTA — header from the label, else marker and accession, 70 letters a line. */
function sequence_fasta(array $s): string
{
    $head = $s['label'] !== '' ? $s['label'] : trim($s['marker'] . ' ' . $s['accession']);
    return '>' . ($head === '' ? t('Sequenz') : $head) . "\n" . rtrim(chunk_split($s['bases'], 70, "\n")) . "\n";
}
