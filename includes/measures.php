<?php
/**
 * The measurement arithmetic, server side — for the find report (step 6
 * of co-ownership, 11.09.2026: „Bilder und Messungen aller Fotografen am
 * Fund zusammenziehen — mit Namen").
 *
 * A DELIBERATE TWIN OF assets/js/measurestats.js. The photo window and
 * the plates compute the very same numbers in the browser
 * (measureUmPerPx, rectDims, frameValues, measureRows, valueLines,
 * rangeText — out of photowindow.js into a module of their own since
 * 06.10.2026) and there they belong: they are drawn live while one taps. The report is a page the server writes, and
 * it cannot call the browser — so the arithmetic stands here a second
 * time, function for function, with the same names in snake case, and
 * tests/measures.php holds it to hand-computed numbers. Whoever changes
 * one side changes the other; the test is the tripwire.
 *
 * WHAT A ROW IS: one measured structure — {L, B, Q} in µm, B and Q
 * possibly null (a cutout holding a single measure). A picture is an
 * array with 'scale' (photo_scale_read) and 'measures'
 * (photo_measures_read), a voucher photo or a cloud recording alike.
 */

declare(strict_types=1);

/** The structure a measure belongs to — the client's measureLabel(). */
function measure_label(array $m): string
{
    return (string) ($m['label'] ?? 'Sporen');
}

/** µm per pixel of ONE measure through its picture's calibration —
 *  measureUmPerPx() in measurestats.js, the width chain included. */
function measure_um_per_px(array $m, ?array $scale): ?float
{
    if ($scale === null || !isset($scale['line'][0], $scale['line'][1], $scale['um'])) {
        return null;
    }
    [$sa, $sb] = $scale['line'];
    $lineLen = hypot((float) $sb[0] - (float) $sa[0], (float) $sb[1] - (float) $sa[1]);
    if ($lineLen <= 0) {
        return null;
    }
    $umPerPx = (float) $scale['um'] / $lineLen;
    if (!empty($scale['width']) && !empty($m['width'])) {
        $umPerPx *= (float) $scale['width'] / (float) $m['width'];
    }
    return $umPerPx;
}

/** Length and breadth of a rectangle measure in µm — rectDims(). */
function measure_rect_dims(array $m, ?array $scale): ?array
{
    if (($m['type'] ?? '') !== 'rect' || count($m['points'] ?? []) !== 4) {
        return null;
    }
    $umPerPx = measure_um_per_px($m, $scale);
    if ($umPerPx === null) {
        return null;
    }
    [$a, $b, $c, $d] = $m['points'];
    $ax = (float) $b[0] - (float) $a[0];
    $ay = (float) $b[1] - (float) $a[1];
    $len = hypot($ax, $ay);
    if ($len <= 0) {
        return null;
    }
    $nx = -$ay / $len;
    $ny = $ax / $len;
    $o1 = ((float) $c[0] - (float) $a[0]) * $nx + ((float) $c[1] - (float) $a[1]) * $ny;
    $o2 = ((float) $d[0] - (float) $a[0]) * $nx + ((float) $d[1] - (float) $a[1]) * $ny;
    $L = $len * $umPerPx;
    $B = abs($o2 - $o1) * $umPerPx;
    return $L > 0 && $B > 0 ? ['L' => $L, 'B' => $B] : null;
}

/** The values of a cutout rectangle from its inner measures — frameValues(),
 *  slot rule included: polyline first, Q whenever the breadth is straight;
 *  with $straight a polyline's length runs from its first point to its
 *  last, as the books measure (07.10.2026). 'bent' says the length was a
 *  polyline. */
function measure_frame_values(array $m, ?array $scale, bool $straight = false): ?array
{
    $umPerPx = measure_um_per_px($m, $scale);
    if ($umPerPx === null || empty($m['inner'])) {
        return null;
    }
    $len = static function (array $mi) use ($umPerPx): float {
        $sum = 0.0;
        $pts = $mi['points'] ?? [];
        for ($i = 1, $n = count($pts); $i < $n; $i++) {
            $sum += hypot((float) $pts[$i][0] - (float) $pts[$i - 1][0],
                          (float) $pts[$i][1] - (float) $pts[$i - 1][1]);
        }
        return $sum * $umPerPx;
    };
    $chord = static function (array $mi) use ($umPerPx): float {
        $pts = $mi['points'] ?? [];
        if (count($pts) < 2) {
            return 0.0;
        }
        $a = $pts[0];
        $b = $pts[count($pts) - 1];
        return hypot((float) $b[0] - (float) $a[0], (float) $b[1] - (float) $a[1]) * $umPerPx;
    };
    if (isset(MEASURE_CYSTID_PROFILES[$m['profile'] ?? ''])) {
        return measure_cystid_values($m, $len, $chord, $straight);
    }
    $first  = $m['inner'][0];
    $second = $m['inner'][1] ?? null;
    if ($second !== null && ($first['type'] ?? '') === 'line' && ($second['type'] ?? '') === 'path') {
        [$first, $second] = [$second, $first];
    }
    $bent = ($first['type'] ?? '') === 'path';
    $L = $bent && $straight ? $chord($first) : $len($first);
    if (!($L > 0)) {
        return null;
    }
    $B = $second === null ? null : $len($second);
    // Q whenever the breadth is straight — the length may be bent (the
    // allantoid spore, 04.10.2026); frameValues() says it at length.
    $Q = $B !== null && $B > 0 && ($second['type'] ?? '') === 'line'
        ? $L / $B : null;
    return ['L' => $L, 'B' => $B, 'Q' => $Q, 'bent' => $bent];
}

/**
 * A CYSTIDIUM MEASURED IN ITS PARTS — CYSTID_PROFILES and cystidValues() in
 * measurestats.js, where the books' reasons stand (Hebeloma after Fungi
 * Europaei 14: l, A, M, B and A/M, A/B, B/M; Conocybe after Fungi Europaei
 * 11: length, belly, head, neck — the belly is the breadth). 'word' is the
 * German word of a part line, null where the part stands in the first line.
 * 'stacked': each part and each ratio on a line of its own (Hebeloma,
 * 08.11.2026 — the reason at CYSTID_PROFILES).
 */
const MEASURE_CYSTID_PROFILES = [
    'hebeloma' => ['parts' => ['L' => null, 'A' => 'A', 'M' => 'M', 'B' => 'B'],
                   'ratios' => [['A', 'M'], ['A', 'B'], ['B', 'M']], 'breadth' => null, 'stacked' => true],
    'conocybe' => ['parts' => ['L' => null, 'V' => null, 'C' => 'Köpfchen', 'N' => 'Hals'],
                   'ratios' => [], 'breadth' => 'V'],
    // The paraphysis (18.11.2026): bent length, breadth, head — the reason at CYSTID_PROFILES.
    'paraphysis' => ['parts' => ['L' => null, 'B' => null, 'K' => 'Kopf'],
                     'ratios' => [], 'breadth' => 'B'],
];

/** cystidValues(): the length, the parts in µm, the ratios of one cystidium. */
function measure_cystid_values(array $m, callable $len, callable $chord, bool $straight): ?array
{
    $profile = MEASURE_CYSTID_PROFILES[$m['profile']];
    $inner = array_values((array) ($m['inner'] ?? []));
    $parts = [];
    foreach (array_keys($profile['parts']) as $i => $key) {
        $mi = $inner[$i] ?? null;
        if ($mi === null) continue;
        $v = $key === 'L' && ($mi['type'] ?? '') === 'path' && $straight ? $chord($mi) : $len($mi);
        if ($v > 0) $parts[$key] = $v;
    }
    if (!(($parts['L'] ?? 0) > 0)) {
        return null;
    }
    $ratios = [];
    foreach ($profile['ratios'] as [$a, $b]) {
        if (($parts[$a] ?? 0) > 0 && ($parts[$b] ?? 0) > 0) $ratios["$a/$b"] = $parts[$a] / $parts[$b];
    }
    $B = $profile['breadth'] !== null ? ($parts[$profile['breadth']] ?? null) : null;
    return ['L' => $parts['L'], 'B' => $B, 'Q' => null, 'bent' => ($inner[0]['type'] ?? '') === 'path',
            'profile' => $m['profile'], 'parts' => $parts, 'ratios' => $ratios];
}

/** A measure's mark — its kind within the layer, '' for none: measureMark(). */
function measure_mark(array $m): string
{
    return is_string($m['mark'] ?? null) ? $m['mark'] : '';
}

/** All measured structures under one label across the given pictures —
 *  measureRows(). @return array<int, array{L: float, B: ?float, Q: ?float, bent: bool, mark: string}> */
function measure_rows(array $pictures, string $label, bool $straight = false): array
{
    $rows = [];
    foreach ($pictures as $picture) {
        $scale = $picture['scale'] ?? null;
        foreach ((array) ($picture['measures'] ?? []) as $m) {
            if (measure_label($m) !== $label) {
                continue;
            }
            $dims = measure_rect_dims($m, $scale);
            if ($dims === null) {
                continue;
            }
            if (!empty($m['inner'])) {
                $v = measure_frame_values($m, $scale, $straight);
                if ($v !== null) {
                    $rows[] = $v + ['mark' => measure_mark($m)];
                }
            } else {
                $rows[] = ['L' => $dims['L'], 'B' => $dims['B'], 'Q' => $dims['L'] / $dims['B'], 'bent' => false,
                    'mark' => measure_mark($m)];
            }
        }
    }
    return $rows;
}

/** Every label that carries a rectangle measure or a named part measure
 *  in these pictures, in the order first met. */
function measure_labels(array $pictures): array
{
    $labels = [];
    foreach ($pictures as $picture) {
        foreach ((array) ($picture['measures'] ?? []) as $m) {
            if (($m['type'] ?? '') === 'rect' || measure_part($m) !== '') {
                $labels[measure_label($m)] = true;
            }
        }
    }
    return array_keys($labels);
}

/** A line's part name, '' for none — measurePart(). */
function measure_part(array $m): string
{
    return ($m['type'] ?? '') === 'line' && is_string($m['part'] ?? null) ? $m['part'] : '';
}

/** The part measures of one label — partRows(): each named line's length
 *  in µm. @return array<int, array{part: string, um: float}> */
function measure_part_rows(array $pictures, string $label): array
{
    $rows = [];
    foreach ($pictures as $picture) {
        $scale = $picture['scale'] ?? null;
        foreach ((array) ($picture['measures'] ?? []) as $m) {
            if (measure_label($m) !== $label || measure_part($m) === '' || count($m['points'] ?? []) !== 2) {
                continue;
            }
            $umPerPx = measure_um_per_px($m, $scale);
            if ($umPerPx === null) {
                continue;
            }
            [$a, $b] = $m['points'];
            $um = hypot((float) $b[0] - (float) $a[0], (float) $b[1] - (float) $a[1]) * $umPerPx;
            if ($um > 0) {
                $rows[] = ['part' => measure_part($m), 'um' => $um];
            }
        }
    }
    return $rows;
}

/** One line per part — partLines(): part => „(min) p10 – p90 (max) µm · N = n · Ø m µm",
 *  the bare value without an average for one. */
function measure_part_lines(array $rows): array
{
    $out = [];
    foreach (array_values(array_unique(array_column($rows, 'part'))) as $part) {
        $vals = array_column(array_values(array_filter($rows, static fn(array $r): bool => $r['part'] === $part)), 'um');
        $out[$part] = count($vals) >= 2
            ? measure_range($vals) . ' µm · N = ' . count($vals) . ' · Ø ' . measure_number(array_sum($vals) / count($vals), 1) . ' µm'
            : measure_number($vals[0], 1) . ' µm · N = 1';
    }
    return $out;
}

/** A number the way the browser writes it for de-DE with at most $digits
 *  decimals: „9,1", „9", „1,68" — no trailing zeros. */
function measure_number(float $v, int $digits): string
{
    $s = number_format($v, $digits, ',', '');
    return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
}

/** percentileOf() — linear interpolation on a sorted list. */
function measure_percentile(array $sorted, float $q): float
{
    $pos = (count($sorted) - 1) * $q;
    $lo  = (int) floor($pos);
    $hi  = min($lo + 1, count($sorted) - 1);
    return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($pos - $lo);
}

/** rangeText() — Piximètre's „(min) p10 – p90 (max)" from ten measures on,
 *  a parenthesis left out where it repeats its bound; below ten the plain
 *  „min – max" (07.10.2026, the reason stands at rangeText). */
const MEASURE_DECILES_FROM = 10;

function measure_range(array $values): string
{
    $s = $values;
    sort($s);
    $um = static fn(float $v): string => measure_number($v, 1);
    $min = $um($s[0]);
    $max = $um(end($s));
    $span = static fn(string $a, string $b): string => $a === $b ? $a : $a . ' – ' . $b;
    if (count($s) < MEASURE_DECILES_FROM) {
        return $span($min, $max);
    }
    $lo = $um(measure_percentile($s, .10));
    $hi = $um(measure_percentile($s, .90));
    return ($min !== $lo ? '(' . $min . ') ' : '') . $span($lo, $hi) . ($max !== $hi ? ' (' . $max . ')' : '');
}

/**
 * The summary sentence(s) of one label — valueLines() in words: the
 * range notation for length and breadth, Q, N, the plain average; a
 * single row says its bare measure.
 *
 * @return string[] lines, the first the main one
 */
function measure_summary(array $rows): array
{
    if ($rows === []) {
        return [];
    }
    $um   = static fn(float $v): string => measure_number($v, 1);
    $q    = static fn(float $v): string => measure_number($v, 2);
    $mean = static fn(array $a): float => array_sum($a) / count($a);
    $Ls = array_map(static fn(array $r): float => (float) $r['L'], $rows);
    $Bs = array_values(array_map(static fn(array $r): float => (float) $r['B'],
        array_filter($rows, static fn(array $r): bool => $r['B'] !== null)));
    $Qs = array_values(array_map(static fn(array $r): float => (float) $r['Q'],
        array_filter($rows, static fn(array $r): bool => $r['Q'] !== null)));
    $dims = static fn(array $list): string => count($list) >= 2 ? measure_range($list) : $um($list[0]);
    if (count($rows) >= 2) {
        $cystid = measure_cystid_lines($rows, $dims, $um, $q, $mean);
        // The length named too where the parts stand one under the other (valueLines says why).
        $L = array_filter($rows, static fn(array $r): bool => empty(MEASURE_CYSTID_PROFILES[$r['profile'] ?? '']['stacked'])) === [] ? 'L ' : '';
        $lines = [$L . $dims($Ls) . ($Bs !== [] ? ' × ' . $dims($Bs) : '') . ' µm', ...$cystid['range']];
        $bits = [];
        if (count($Qs) >= 2) {
            $bits[] = 'Q = ' . $q(min($Qs)) . ' – ' . $q(max($Qs));
        } elseif (count($Qs) === 1) {
            $bits[] = 'Q ≈ ' . $q($Qs[0]);
        }
        $bits[] = 'N = ' . count($Ls) . ($Bs !== [] && count($Bs) !== count($Ls) ? '/' . count($Bs) : '');
        $lines[] = implode(' · ', $bits);
        array_push($lines, ...$cystid['qn']);
        $lines[] = 'Ø ' . $L . $um($mean($Ls)) . ($Bs !== [] ? ' × ' . $um($mean($Bs)) : '') . ' µm'
            . ($Qs !== [] ? ' · Q ≈ ' . $q($mean($Qs)) : '') . $cystid['mean'];
        array_push($lines, ...$cystid['mean_lines']);
        return $lines;
    }
    $r = $rows[0];
    $parts = $ratios = '';
    if (!empty(MEASURE_CYSTID_PROFILES[$r['profile'] ?? '']['stacked'])) {
        // One cystidium, stacked: its length, then each part, then each ratio, a line each.
        $profile = MEASURE_CYSTID_PROFILES[$r['profile']];
        $lines = ['L ' . $um((float) $r['L']) . ($r['B'] !== null ? ' × ' . $um((float) $r['B']) : '') . ' µm'];
        foreach ($profile['parts'] as $key => $word) {
            if ($word !== null && $key !== $profile['breadth'] && ($r['parts'][$key] ?? 0) > 0) {
                $lines[] = measure_part_word($word) . ' ' . $um((float) $r['parts'][$key]) . ' µm';
            }
        }
        foreach ($r['ratios'] as $w => $v) $lines[] = $w . ' ≈ ' . $q((float) $v);
        return $lines;
    }
    if (isset(MEASURE_CYSTID_PROFILES[$r['profile'] ?? ''])) {
        $profile = MEASURE_CYSTID_PROFILES[$r['profile']];
        foreach ($profile['parts'] as $key => $word) {
            if ($word !== null && $key !== $profile['breadth'] && ($r['parts'][$key] ?? 0) > 0) {
                $parts .= ' · ' . measure_part_word($word) . ' ' . $um((float) $r['parts'][$key]);
            }
        }
        foreach ($r['ratios'] as $w => $v) $ratios .= ' · ' . $w . ' ≈ ' . $q((float) $v);
    }
    return [$um((float) $r['L']) . ($r['B'] !== null ? ' × ' . $um((float) $r['B']) : '') . ' µm'
        . ($r['Q'] !== null ? ' · Q ≈ ' . $q((float) $r['Q']) : '') . $parts . $ratios];
}

/** A part's word as the reader's language says it — the letters A, M, B stay. */
function measure_part_word(string $word): string
{
    return strlen($word) === 1 || !function_exists('t') ? $word : t($word);
}

/** cystidLines(): a cystidium's parts and ratios beside the standard lines. */
function measure_cystid_lines(array $rows, callable $dims, callable $um, callable $q, callable $mean): array
{
    $out = ['range' => [], 'qn' => [], 'mean' => '', 'mean_lines' => []];
    $names = array_values(array_unique(array_filter(array_map(static fn(array $r) => $r['profile'] ?? null, $rows),
        static fn($p) => isset(MEASURE_CYSTID_PROFILES[$p ?? '']))));
    foreach ($names as $name) {
        $profile = MEASURE_CYSTID_PROFILES[$name];
        $mine = array_values(array_filter($rows, static fn(array $r) => ($r['profile'] ?? null) === $name));
        $parts = [];
        foreach ($profile['parts'] as $key => $word) {
            if ($word === null || $key === $profile['breadth']) continue;
            $vs = array_values(array_filter(array_map(static fn(array $r) => $r['parts'][$key] ?? 0.0, $mine), static fn($v) => $v > 0));
            if ($vs !== []) $parts[] = [measure_part_word($word), $vs];
        }
        if (!empty($profile['stacked'])) {
            $ratios = [];
            foreach ($profile['ratios'] as [$a, $b]) {
                $vs = array_values(array_filter(array_map(static fn(array $r) => $r['ratios']["$a/$b"] ?? 0.0, $mine), static fn($v) => $v > 0));
                if ($vs !== []) $ratios[] = ["$a/$b", $vs];
            }
            foreach ($parts as [$w, $vs]) $out['range'][] = $w . ' ' . $dims($vs) . ' µm';
            // No span of the ratios — only their averages compare with the books (cystidLines says why).
            foreach ($parts as [$w, $vs]) $out['mean_lines'][] = 'Ø ' . $w . ' ' . $um($mean($vs)) . ' µm';
            foreach ($ratios as [$w, $vs]) $out['mean_lines'][] = 'Ø ' . $w . ' ≈ ' . $q($mean($vs));
            continue;
        }
        if ($parts !== []) {
            $out['range'][] = implode(' · ', array_map(static fn(array $p) => $p[0] . ' ' . $dims($p[1]), $parts)) . ' µm';
        }
        $ratios = [];
        foreach ($profile['ratios'] as [$a, $b]) {
            $vs = array_values(array_filter(array_map(static fn(array $r) => $r['ratios']["$a/$b"] ?? 0.0, $mine), static fn($v) => $v > 0));
            if ($vs !== []) $ratios[] = ["$a/$b", $vs];
        }
        if ($ratios !== []) {
            $out['qn'][] = implode(' · ', array_map(static fn(array $r) => count($r[1]) >= 2
                ? $r[0] . ' = ' . $q(min($r[1])) . ' – ' . $q(max($r[1])) : $r[0] . ' ≈ ' . $q($r[1][0]), $ratios));
            foreach ($ratios as [$w, $vs]) $out['mean'] .= ' · ' . $w . ' ≈ ' . $q($mean($vs));
        }
        foreach ($parts as [$w, $vs]) $out['mean'] .= ' · ' . $w . ' ' . $um($mean($vs)) . ' µm';
    }
    return $out;
}
