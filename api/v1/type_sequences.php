<?php
/**
 * The type sequences of a species from GenBank and its species hypotheses
 * from UNITE (includes/type_sequences.php says what and why).
 *
 *   GET type_sequences.php?name=Cantharellus cibarius[&point=5]  the type records of that name —
 *                                                         and of today's name (GBIF) of the find's species
 *   GET type_sequences.php?fasta=KR677539.1                 one record as a FASTA file to save
 *
 * READ ONLY. The name is the find's species as it stands; the server takes
 * the binomial at its head (type_sequences_name).
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/type_sequences.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

$accession = trim((string) ($_GET['fasta'] ?? ''));
if ($accession !== '') {
    $fasta = type_sequences_fasta($accession);
    if ($fasta === null) {
        json_error(t('GenBank hat die Sequenz gerade nicht geliefert.'), 502);
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $accession) . '.fasta"');
    echo $fasta;
    exit;
}

$name = type_sequences_name((string) ($_GET['name'] ?? ''));
if ($name === null) {
    json_response(['name' => null, 'today' => null, 'sequences' => [], 'all' => null, 'ok' => true, 'unite' => null]);
}
/* AND UNDER TODAY'S NAME (GBIF) where the find carries an older one — a
   variety asks under its own name only, its species' type is not its type. */
$today = null;
if (!preg_match('/ (var|subsp|f)\. /', $name)) {
    $today = type_sequences_today($pdo, (int) $me['id'], (int) ($_GET['point'] ?? 0));
    if ($today !== null && mb_strtolower($today) === mb_strtolower($name)) {
        $today = null;
    }
}
$answer = type_sequences_for($name);
if ($today !== null) {
    $more = type_sequences_for($today);
    $seen = array_column($answer['sequences'], 'accession');
    foreach ($more['sequences'] as $s) {
        if (!in_array($s['accession'], $seen, true)) {
            $answer['sequences'][] = $s;
        }
    }
    $answer['ok'] = $answer['ok'] && $more['ok'];
    $answer['all_today'] = $more['all'];
}
$answer['today'] = $today;
/* UNITE'S SPECIES HYPOTHESES beside GenBank's types — under the find's name,
   and under today's where the old one finds none (unite_fetch hops along
   UNITE's own synonyms already). */
$unite = unite_for($name) + ['of' => $name];
if ($unite['hypotheses'] === [] && $today !== null) {
    $unite = unite_for($today) + ['of' => $today];
}
$answer['unite'] = $unite;
json_response($answer);
