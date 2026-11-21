<?php
/**
 * HOW A DEVELOPED PICTURE LEAVES THE APP — the account's export style
 * (08.11.2026: „für Instagram … verkleinern … ein Wasserzeichen
 * drüber setzen … ein Copyright … oder ein durchsichtiges Schutzlayer, wo in
 * fast transparenter Schrift immer wieder wiederholt der Autorenname steht …
 * mehrere Copyright-Designs dort konfigurieren … mit Schriftart, Schriftgröße,
 * Transparenz", „ein Logo ist natürlich auch klasse").
 *
 * IT RIDES IN measure_style under 'export', like the plates' hand: one JSON
 * per account, so a template made at the desk is there on the phone, and no
 * migration was needed. Made in Einstellungen → Fotos → Export
 * (assets/js/exportsettings.js), written through api/v1/measure_style.php,
 * read by the photo window's export (photowindow.js, developExport) and drawn
 * by assets/js/exportmark.js — the one drawing for the preview and the file.
 *
 *   artist, copyright, owner_in_file
 *                  NO LONGER READ since 08.11.2026: the author is the
 *                  PICTURE's (its photographer, or who the upload named in
 *                  map_cloud_files.taken_by), never a name fixed on the
 *                  account — that one would have put one's own name into a
 *                  friend's photo. The file always carries only that name.
 *                  Kept in the shape so a stored style still reads.
 *   file_name      the downloaded file's name, with placeholders
 *   marks          the watermark templates, each {id, name, hint, grid, logo}
 *
 * THE PLACEHOLDERS ARE GERMAN WORDS on purpose: the person types them into
 * their own texts, like the picture names. {Urheber} {Jahr} {Art} {Datum}
 * {Fundnummer} {Motiv} {Ort} — exportmark.js fills them, and puts them in
 * with buttons, so nobody has to type a brace. {Urheber} WAS {Name} until
 * 08.11.2026 („Name würde ich auch Urheber nennen"); a stored
 * text is written anew when it is read (export_text).
 *
 * WHATEVER IS OFF FALLS BACK, nothing is refused: a style must never be able
 * to break the export. Sizes are shares of the picture's long edge, so a
 * template looks the same on a 1080 px post and on the full file.
 */

declare(strict_types=1);

const EXPORT_FONTS = ['sans', 'serif', 'mono'];
const EXPORT_PLACES = ['tl', 'tc', 'tr', 'ml', 'mc', 'mr', 'bl', 'bc', 'br'];
const EXPORT_COLORS = ['white', 'black', 'auto'];
/** A row of templates to choose from in a window, not a catalogue. */
const EXPORT_MARKS_MAX = 12;

const EXPORT_STYLE_DEFAULTS = [
    'artist' => '',
    'copyright' => '© {Urheber} {Jahr}',
    'owner_in_file' => true,
    'file_name' => '{Motiv} bearbeitet',
    'marks' => [],
];

/** A new template's parts — the settings tab starts from these too. */
const EXPORT_MARK_DEFAULTS = [
    'hint' => ['on' => true, 'text' => '© {Urheber} {Jahr}', 'font' => 'sans', 'place' => 'br',
               'size' => 2.5, 'color' => 'auto', 'opacity' => 85, 'shadow' => true],
    'grid' => ['on' => false, 'text' => '', 'font' => 'sans', 'angle' => -30, 'gap' => 150,
               'size' => 4, 'color' => 'white', 'opacity' => 15],
    'logo' => ['on' => false, 'file' => '', 'place' => 'bl', 'size' => 15, 'opacity' => 80],
];

/** A text of the person's own: one line, a sane length — the old {Name} read as {Urheber}. */
function export_text(mixed $value, int $max = 120): string
{
    $text = is_string($value) ? preg_replace('/\s+/u', ' ', $value) : '';
    return mb_substr(trim(str_replace('{Name}', '{Urheber}', (string) $text)), 0, $max);
}

/** A number into its range; off or missing gives the fallback. */
function export_number(mixed $value, float $min, float $max, float $fallback, int $digits = 1): float
{
    if (!is_numeric($value)) {
        return $fallback;
    }
    $v = (float) $value;
    return ($v >= $min && $v <= $max) ? round($v, $digits) : $fallback;
}

/** One of a list, or the fallback. */
function export_pick(mixed $value, array $allowed, string $fallback): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

/** A logo's file: the hash its upload was stored under (api/v1/export_logo.php). */
function export_logo_name(mixed $value): string
{
    return is_string($value) && preg_match('/^[0-9a-f]{40}$/', $value) ? $value : '';
}

/** One template into shape. */
function export_mark_clean(mixed $m, int $index): ?array
{
    if (!is_array($m)) {
        return null;
    }
    $d = EXPORT_MARK_DEFAULTS;
    $hint = is_array($m['hint'] ?? null) ? $m['hint'] : [];
    $grid = is_array($m['grid'] ?? null) ? $m['grid'] : [];
    $logo = is_array($m['logo'] ?? null) ? $m['logo'] : [];
    $id = is_string($m['id'] ?? null) && preg_match('/^[a-z0-9]{1,16}$/', $m['id']) ? $m['id'] : 'm' . $index;
    return [
        'id' => $id,
        'name' => export_text($m['name'] ?? '', 40) ?: t('Vorlage %d', $index + 1),
        'hint' => [
            'on' => (bool) ($hint['on'] ?? $d['hint']['on']),
            'text' => export_text($hint['text'] ?? $d['hint']['text']),
            'font' => export_pick($hint['font'] ?? null, EXPORT_FONTS, $d['hint']['font']),
            'place' => export_pick($hint['place'] ?? null, EXPORT_PLACES, $d['hint']['place']),
            'size' => export_number($hint['size'] ?? null, 1, 10, $d['hint']['size']),
            'color' => export_pick($hint['color'] ?? null, EXPORT_COLORS, $d['hint']['color']),
            'opacity' => (int) export_number($hint['opacity'] ?? null, 5, 100, $d['hint']['opacity'], 0),
            'shadow' => (bool) ($hint['shadow'] ?? $d['hint']['shadow']),
        ],
        'grid' => [
            'on' => (bool) ($grid['on'] ?? $d['grid']['on']),
            'text' => export_text($grid['text'] ?? ''),
            'font' => export_pick($grid['font'] ?? null, EXPORT_FONTS, $d['grid']['font']),
            'angle' => (int) export_number($grid['angle'] ?? null, -90, 90, $d['grid']['angle'], 0),
            'gap' => (int) export_number($grid['gap'] ?? null, 50, 400, $d['grid']['gap'], 0),
            'size' => export_number($grid['size'] ?? null, 1, 15, $d['grid']['size']),
            'color' => export_pick($grid['color'] ?? null, EXPORT_COLORS, $d['grid']['color']),
            'opacity' => (int) export_number($grid['opacity'] ?? null, 3, 60, $d['grid']['opacity'], 0),
        ],
        'logo' => [
            'on' => (bool) ($logo['on'] ?? $d['logo']['on']),
            'file' => export_logo_name($logo['file'] ?? ''),
            'place' => export_pick($logo['place'] ?? null, EXPORT_PLACES, $d['logo']['place']),
            'size' => export_number($logo['size'] ?? null, 5, 50, $d['logo']['size']),
            'opacity' => (int) export_number($logo['opacity'] ?? null, 10, 100, $d['logo']['opacity'], 0),
        ],
    ];
}

/** The whole export style into shape. */
function export_style_clean(mixed $e): array
{
    $out = EXPORT_STYLE_DEFAULTS;
    if (!is_array($e)) {
        return $out;
    }
    $out['artist'] = export_text($e['artist'] ?? '', 80);
    if (array_key_exists('copyright', $e)) {
        $out['copyright'] = export_text($e['copyright'], 160);
    }
    if (array_key_exists('owner_in_file', $e)) {
        $out['owner_in_file'] = (bool) $e['owner_in_file'];
    }
    $name = export_text($e['file_name'] ?? '', 120);
    // A file name must not carry a path or a forbidden sign.
    $name = trim((string) preg_replace('~[/\\\\:*?"<>|\x00-\x1f]+~u', ' ', $name));
    if ($name !== '') {
        $out['file_name'] = $name;
    }
    $marks = [];
    $ids = [];
    foreach (is_array($e['marks'] ?? null) ? $e['marks'] : [] as $m) {
        if (count($marks) >= EXPORT_MARKS_MAX) {
            break;
        }
        $clean = export_mark_clean($m, count($marks));
        if ($clean === null) {
            continue;
        }
        // Two templates with one id would be one in the export window.
        while (in_array($clean['id'], $ids, true)) {
            $clean['id'] .= 'x';
        }
        $ids[] = $clean['id'];
        $marks[] = $clean;
    }
    $out['marks'] = $marks;
    return $out;
}

/** The logos a style still uses — the others may go (export_logos_tidy). */
function export_logos_used(array $style): array
{
    $used = [];
    foreach ($style['marks'] ?? [] as $m) {
        $file = $m['logo']['file'] ?? '';
        if ($file !== '') {
            $used[$file] = true;
        }
    }
    return array_keys($used);
}

/** Where a user's logos live. Creates nothing. Below data/, so outside the web. */
function export_logo_directory(int $userId): string
{
    return user_directory($userId) . '/logos';
}

/** One logo's file, or null where the name is no logo's. */
function export_logo_path(int $userId, string $file): ?string
{
    return export_logo_name($file) === '' ? null : export_logo_directory($userId) . '/' . $file . '.png';
}

/**
 * THE LOGOS NO TEMPLATE USES GO — after every save of the style. An upload
 * that was never saved into a template, a logo replaced, a template deleted:
 * all leave a file nobody points at, and a quiet folder of orphans would
 * grow with every try.
 */
function export_logos_tidy(int $userId, array $style): void
{
    $dir = export_logo_directory($userId);
    if (!is_dir($dir)) {
        return;
    }
    $used = array_flip(export_logos_used($style));
    foreach (glob($dir . '/*.png') ?: [] as $path) {
        $name = basename($path, '.png');
        // A fresh upload gets an hour to be saved into its template.
        if (!isset($used[$name]) && filemtime($path) < time() - 3600) {
            @unlink($path);
        }
    }
}
