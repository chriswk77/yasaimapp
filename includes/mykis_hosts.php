<?php
/**
 * WHERE OUR „SUBSTRAT / WIRT" GOES IN THE MYKIS SHEET — three columns,
 * two of them checked against MykIS' own lists.
 *
 * THE IMPORT REFUSES A WIRT IT DOES NOT KNOW (27.11.2026: a coordinator
 * imported the Hessen sample, and 325 of 573 rows came back „nicht
 * vorhanden oder falsch geschrieben"). MykIS keeps a host list beside its
 * species list — 12,086 names, nearly all plants by their botanical name,
 * mosses, lichens and some 450 fungi, and 66 animals by their GERMAN name
 * and coarsely („Hausrind", „HIRSCHARTIGE", „KÄFER"), the scientific name
 * only as a synonym beside it. organ_substrat is checked against a list
 * of 40 words („Laubstreu", „Zapfen", „liegender Ast"). substrat_text is
 * free text and takes anything.
 *
 * So one value of ours is read in this order:
 *   1. a name of the host list → Wirt, in MykIS' own spelling — a synonym
 *      or a unique German plant name becomes the list's name
 *      (Bos primigenius taurus → „Hausrind", Esskastanie → Castanea
 *      sativa), „spec." becomes „sp.";
 *   2. a word of the organ list → organ_substrat;
 *   3. everything else → substrat_text, where nothing is refused.
 * A value like „Fagus sylvatica, Laubstreu" is cut at commas and
 * semicolons and its parts placed one by one; Wirt and organ_substrat take
 * one entry each, a second goes to the free text.
 *
 * THE LISTS LIVE ON THE SERVER ONLY (27.11.2026: „Liste nur auf den
 * Server"). They are MykIS' work, and this repository is public — so they
 * are uploaded once in the administration (tab DGfM-Kartierung) as a JSON
 * file and kept in data/mykis/, which git ignores. Without the file every
 * value goes to substrat_text: a row MykIS takes beats a Wirt it refuses.
 *
 * THE FILE carries the raw lists, not our lookup keys — the normalising
 * stays in ONE place, mykis_norm(), and a better rule needs no new file:
 *   {"made": "2026-11-27", "source": "…",
 *    "hosts": [["Abies alba", "", "Weiß-Tanne"], …],   name, synonym, German
 *    "organ": ["Laubstreu", …]}
 */

declare(strict_types=1);

/** Where the uploaded lists lie on the server. */
function mykis_lists_path(): string
{
    return dirname(__DIR__) . '/data/mykis/lists.json';
}

/**
 * A name as a lookup key: lower case, no hyphens (Ess-Kastanie /
 * Esskastanie), single spaces, and the genus marker as MykIS writes it
 * — „Acer spec.", „Acer spec", „Acer spp." are all „acer sp.".
 */
function mykis_norm(string $name): string
{
    $key = mb_strtolower(trim($name), 'UTF-8');
    $key = str_replace(['-', '‐', '–'], '', $key);
    $key = (string) preg_replace('/\s+/u', ' ', $key);
    return (string) preg_replace('/ (?:spec|spp|sp)\.?$/u', ' sp.', $key);
}

/**
 * The lookup tables from the raw lists.
 *
 * NAME BEATS SYNONYM BEATS GERMAN NAME, and a German name that two hosts
 * share („Weide" is no single willow) is no key at all: it would name one
 * of them at random.
 *
 * @return array{hosts: array<string, string>, organ: array<string, string>, made: string, counts: array{hosts: int, organ: int}}
 */
function mykis_lists_build(array $raw): array
{
    $hosts = [];
    $synonyms = [];
    $german = [];
    foreach ($raw['hosts'] ?? [] as $row) {
        $name = trim((string) ($row[0] ?? ''));
        if ($name === '') {
            continue;
        }
        $hosts[mykis_norm($name)] = $name;
        $synonym = trim((string) ($row[1] ?? ''));
        if ($synonym !== '') {
            $synonyms[mykis_norm($synonym)] = $name;
        }
        $de = trim((string) ($row[2] ?? ''));
        if ($de !== '' && $de !== $name) {
            $german[mykis_norm($de)][$name] = true;
        }
    }
    $count = count($hosts);
    foreach ($synonyms as $key => $name) {
        $hosts[$key] ??= $name;
    }
    foreach ($german as $key => $names) {
        if (count($names) === 1) {
            $hosts[$key] ??= array_key_first($names);
        }
    }
    $organ = [];
    foreach ($raw['organ'] ?? [] as $word) {
        $word = trim((string) $word);
        if ($word !== '') {
            $organ[mykis_norm($word)] = $word;
        }
    }
    return ['hosts' => $hosts, 'organ' => $organ, 'made' => (string) ($raw['made'] ?? ''),
            'counts' => ['hosts' => $count, 'organ' => count($organ)]];
}

/**
 * The lists of this server, read once per request — or null without the
 * file. A test hands its own raw lists in ($set) and false to forget them.
 */
function mykis_lists(array|false|null $set = null): ?array
{
    static $lists = null, $read = false;
    if ($set === false) {
        [$lists, $read] = [null, false];
        return null;
    }
    if ($set !== null) {
        [$lists, $read] = [mykis_lists_build($set), true];
    }
    if (!$read) {
        $read = true;
        $text = is_file(mykis_lists_path()) ? (string) file_get_contents(mykis_lists_path()) : '';
        $raw = $text === '' ? null : json_decode($text, true);
        $lists = is_array($raw) ? mykis_lists_build($raw) : null;
    }
    return $lists;
}

/**
 * Our field placed in the three columns.
 *
 * @return array{wirt: string, organ_substrat: string, substrat_text: string}
 */
function mykis_substrate(string $value): array
{
    $out = ['wirt' => '', 'organ_substrat' => '', 'substrat_text' => ''];
    $value = trim($value);
    $lists = mykis_lists();
    if ($value === '' || $lists === null) {
        $out['substrat_text'] = $value;
        return $out;
    }
    $whole = mykis_norm($value);
    $parts = isset($lists['hosts'][$whole]) || isset($lists['organ'][$whole])
        ? [$value]
        : array_filter(array_map('trim', preg_split('/[,;]/u', $value) ?: []), 'strlen');
    $rest = [];
    foreach ($parts as $part) {
        $key = mykis_norm($part);
        if ($out['wirt'] === '' && isset($lists['hosts'][$key])) {
            $out['wirt'] = $lists['hosts'][$key];
        } elseif ($out['organ_substrat'] === '' && isset($lists['organ'][$key])) {
            $out['organ_substrat'] = $lists['organ'][$key];
        } else {
            $rest[] = $part;
        }
    }
    $out['substrat_text'] = implode(', ', $rest);
    return $out;
}

/**
 * An uploaded file checked and kept. A file that is not the lists (too
 * few hosts, no names) is refused rather than half believed.
 *
 * @return array{hosts: int, organ: int}|null  null when refused
 */
function mykis_lists_store(string $text): ?array
{
    $raw = json_decode($text, true);
    if (!is_array($raw) || !is_array($raw['hosts'] ?? null) || !is_array($raw['organ'] ?? null)) {
        return null;
    }
    $lists = mykis_lists_build($raw);
    if ($lists['counts']['hosts'] < 100 || $lists['counts']['organ'] < 5) {
        return null;
    }
    $dir = dirname(mykis_lists_path());
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    if (file_put_contents(mykis_lists_path(), $text, LOCK_EX) === false) {
        return null;
    }
    mykis_lists(false);
    return $lists['counts'];
}
