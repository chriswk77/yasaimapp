<?php
/**
 * The find report — a page built to be printed, and thus to be saved as PDF.
 *
 * WHY NO PDF LIBRARY. A PDF writer in PHP means vendored foreign code with
 * its own font and encoding trouble (FPDF speaks Latin-1, TCPDF weighs
 * megabytes) — in a project that deliberately has no build step and no
 * package manager. The browser can already do it: every one of them prints
 * to PDF, with correct umlauts, embedded photos and page breaks the CSS
 * says where to make. So the report is a page, and "Als PDF speichern" is
 * the print dialog's own entry. That also makes it configurable at no
 * extra cost — everything below is CSS.
 *
 * WHAT IT SHOWS is what Organisieren hands over: the chosen points, the chosen
 * columns, photos in the chosen size, optionally the overview map. The
 * heading names the AREA rather than a typed title — municipalities, map
 * sheets and the time span of the finds (report_heading), because that is
 * what an excursion is identified by.
 *
 * FOREIGN FINDS BELONG IN (23.08.2026): a group's report lists
 * what the group found — with their photos, because sharing a list shares
 * them (the same call, same day; pages/photo.php carries the permission).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/vocabulary.php';

$me = login_required();
/* The layers whose bent lengths the reader reads straight, as the books
   do — the photo window's switch per layer (07.10.2026), so the report
   says the numbers its reader sees there. */
$straightLength = measure_style_read($me['measure_style'] ?? null)['straight_length'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Nur über das Formular.');
}
csrf_check();

$ids = array_values(array_filter(array_map(
    'intval', explode(',', (string) ($_POST['points'] ?? ''))), static fn(int $i): bool => $i > 0));

$points = export_points($pdo, (int) $me['id'], $ids);
if ($points === []) {
    http_response_code(422);
    exit('Nichts zu berichten — die Auswahl ist leer.');
}

/* THE ORDER OF THE REPORT IS THE ORDER OF THE FIND, not of the database:
   a report is read along the excursion. Points without a find time sort
   to the end — they belong to no moment of it. */
usort($points, static function (array $a, array $b): int {
    $x = (string) ($a['found_at'] ?? '');
    $y = (string) ($b['found_at'] ?? '');
    if ($x === '' || $y === '') {
        return ($x === '' ? 1 : 0) <=> ($y === '' ? 1 : 0);
    }
    return $x <=> $y;
});

$catalogue = report_columns($pdo, (int) $me['id'], $points);
$chosen    = array_values(array_filter((array) ($_POST['columns'] ?? []),
    static fn($k): bool => isset($catalogue[(string) $k])));
if ($chosen === []) {
    // Never an empty report: without a choice it shows what a find is.
    $chosen = ['title', 'find_number', 'found_at', 'municipality'];
    $chosen = array_values(array_filter($chosen, static fn($k) => isset($catalogue[$k])));
}

$photoSize = (string) ($_POST['photos'] ?? 'none');
if (!in_array($photoSize, ['none', 'small', 'medium', 'large', 'full'], true)) {
    $photoSize = 'none';
}
$layout  = ($_POST['layout'] ?? '') === 'tiles' ? 'tiles' : 'list';
$withMap = ($_POST['map'] ?? '') === '1';
$merge   = ($_POST['merge'] ?? '') === '1';
/* THE GERMAN NAME UNDER THE SCIENTIFIC ONE (12.09.2026: „dort,
   wo wir deutsche Namen haben, über ein Häkchen deutsche Namen mit
   einblenden lassen, in einer zweiten Zeile unter dem wissenschaftlichen").
   Garnish, as the content rule says — scientific first, and the German
   only where a list carries one: a find picked from GBIF or typed by hand
   has none and shows no second line. Resolved once for the whole report
   through the taxon references the finds carry. */
$withGerman = ($_POST['german'] ?? '') === '1';
$germanOf   = [];
if ($withGerman) {
    require_once __DIR__ . '/../includes/species.php';
    $refs = [];
    foreach ($points as $p) {
        foreach (report_species_refs($p) as $ref) {
            $refs[substr($ref, 4)] = true;          // 'ref:' off, the identifier itself
        }
    }
    $germanOf = species_german_names($pdo, array_keys($refs));
}
/** The German name of a group — the first its members can offer. */
$germanName = static function (array $members) use ($germanOf): string {
    foreach ($members as $p) {
        foreach (report_species_refs($p) as $ref) {
            if (isset($germanOf[substr($ref, 4)])) {
                return $germanOf[substr($ref, 4)];
            }
        }
    }
    return '';
};

/* THE SPECIES LIST (23.08.2026): with the checkmark, finds of
   the SAME species become one line — on an excursion several people map
   the same fungus in the same spot, and a report that lists it three
   times says three times nothing. What the species IS comes from the
   taxon reference where there is one, and from the name where there is
   none or where two references name the same fungus (report_merge_groups).
   NOTHING IS MERGED IN THE DATA: this is a way of reading, the points
   stay as they are. That is the whole reason it is a checkbox and not a
   tool.

   NO DISTANCE HERE, deliberately. The maintainer's 15–20 m belong to the real
   merging of excursion points, which is a data operation for later; a
   species list answers "which species occurred", and there the second
   stand of the same fungus is the same answer. If a report over a whole
   region ever needs the distance, it can come — as a second checkmark,
   not as a silent rule.

   Every entry is a group: one point in the ordinary case, several in the
   merged one. Everything below reads groups, so both ways go through the
   same rendering. */
$groups = [];
if ($merge) {
    // Grouped in export.php (report_merge_groups) — by reference, and by
    // name where a find never got one, the guests' above all.
    $groups = report_merge_groups($points);
} else {
    foreach ($points as $p) {
        $groups[] = [$p];
    }
}

$heading = report_heading($points);
$owners  = [];
foreach ($points as $p) {
    $owners[trim((string) ($p['owner'] ?? ($me['first_name'] . ' ' . $me['last_name'])))] = true;
}
$owners = array_keys(array_filter($owners, static fn($k) => trim((string) $k) !== '', ARRAY_FILTER_USE_KEY));
sort($owners, SORT_NATURAL | SORT_FLAG_CASE);

// 180 mm is the A4 text width with this page's margins — "full" means the
// picture fills the line, not that it overflows it.
$photoWidth = ['small' => 28, 'medium' => 45, 'large' => 70, 'full' => 180][$photoSize] ?? 0;

/* THE OVERVIEW MAP'S OWN PIXELS — 182 × 90 mm at 96 dpi, and then twice
   that. The doubling is not decoration: a picture 688 pixels wide,
   printed across 182 mm, is 96 dpi and looks it. Doubled it is 192 dpi,
   and the tiles for it are fetched one zoom level deeper, so it is real
   detail and not an enlargement. Four times over would be 384 dpi and
   about a hundred tiles from a foreign server for one report — twice is
   where the gain stops being worth the fetching. */
$mapWidth  = (int) round(182 * 96 / 25.4 * 2);
$mapHeight = (int) round(90 * 96 / 25.4 * 2);

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Fundbericht<?= $heading['area'] !== '' ? ' · ' . h($heading['area']) : '' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* IT BECOMES A PDF, NOT A PRINTOUT (23.08.2026): "Da brauchen
   wir nicht auf die Druckbarkeit zu sehr zu achten." That one sentence
   decides the whole look. Full-area colour would be expensive on paper
   and costs a PDF nothing, so the report may have surfaces: a coloured
   table head, zebra rows, tinted tiles.

   ITS OWN PALETTE, NOT THE APPLICATION'S TOKENS — and here that is right
   for once (CLAUDE.md would otherwise send one to :root): a report leaves
   the house and looks the same for everybody who receives it. Through
   --accent it would arrive in the dress of whoever exported it, and the
   amber theme would hand a find report its warning colour. A muted forest
   green suits find lists and stays readable when somebody prints it
   after all.

   print-color-adjust IS THE LOAD-BEARING LINE. Without it browsers drop
   every background when printing — the zebra stripes and the table head
   would be there on screen and gone in the PDF, which is exactly the
   surface this design lives on. */
@page { size: A4; margin: 16mm 14mm 14mm; }

:root {
    --ink:      #1b201c;   /* near-black with a touch of green, softer than #000 */
    --quiet:    #5d675f;
    --leaf:     #2f6b45;   /* the report's own green: heads, rules, tile titles */
    --leaf-pale:#e8f0ea;   /* the tinted surface under it */
    --zebra:    #f4f7f4;
    --hair:     #d8ded9;
    --desk:     #eceeec;   /* the ground the sheet lies on — screen only */
}

* { print-color-adjust: exact; -webkit-print-color-adjust: exact; }

body {
    font: 10.5pt/1.5 "Helvetica Neue", Arial, sans-serif;
    color: var(--ink);
    background: var(--desk);
    margin: 0;
    padding: 1.5rem;
}
@media print { body { padding: 0; background: #fff; } }

/* THE SHEET IS A4 ON SCREEN TOO (24.08.2026). Before this the
   preview was as wide as the window, and the print box 182mm — and the
   overview map, which frames itself once at load, kept its centre and
   zoom while its container changed width underneath it. On screen you
   saw every find, on paper a cut-out of the middle. Whatever the eye
   checks in the preview has to be the thing that prints, so the preview
   gets the printed page's width and nothing else: 210mm of A4 less the
   14mm margins of @page above — change one, change the other.

   Only the WIDTH is fixed, never the height: a report is as long as it
   is, and the browser breaks it into pages. This is one continuous sheet,
   not a stack of paper simulations. */
.report-sheet {
    width: 182mm;
    margin: 0 auto;
    background: #fff;
    padding: 6mm;
    border-radius: 4px;
    box-shadow: 0 1px 4px rgba(0,0,0,.13), 0 8px 24px rgba(0,0,0,.07);
}
@media print {
    /* On paper the @page box IS the sheet — the shadow, the padding and
       the rounding would only push the content inwards a second time. */
    .report-sheet { width: auto; padding: 0; box-shadow: none; border-radius: 0; }
}
/* NARROW SCREENS SHRINK THE SHEET, they do not reflow it: transform
   leaves the layout width at 182mm, so the map container keeps exactly
   the pixel size it will have on paper. Anything that changed the width
   — a media query, zoom, max-width — would bring back the very drift
   this whole block exists to end. --sheet-scale comes from the script. */
.report-scale {
    transform: scale(var(--sheet-scale, 1));
    /* TOP LEFT, not top center. Centred, the shrinking happens around the
       middle of the WINDOW while the sheet — wider than the window and
       therefore pinned to the left edge — swings out to the right: 327
       pixels of room, and the paper painted from 114 to 441. From the
       left corner the sheet shrinks into exactly the room there is. */
    transform-origin: top left;
    /* Scaling paints smaller but still reserves the full height; the
       margin below pulls the empty remainder back up. */
    margin-bottom: calc((var(--sheet-scale, 1) - 1) * var(--sheet-height, 0px));
}
@media print { .report-scale { transform: none; margin-bottom: 0; } }

/* THE HEAD AS A BAND: title, area and facts stand on one tinted surface
   with the green rule under it — that is the one place a report is
   allowed to be loud, and it makes the first page recognizable as a
   document rather than a table dump. */
.report-head {
    background: var(--leaf-pale);
    border-bottom: 2.5px solid var(--leaf);
    border-radius: 6px 6px 0 0;
    padding: .8rem 1rem .7rem;
    margin-bottom: 1.3rem;
}
h1 { font-size: 16pt; margin: 0; letter-spacing: .01em; color: var(--leaf); }
.report-place { font-size: 11.5pt; font-weight: 600; margin: .15rem 0 .1rem; }
.report-facts { color: var(--quiet); margin: 0; font-size: 9.5pt; }
.report-facts span + span::before { content: " · "; }

/* THE TABLE SCALES AS ONE PIECE. --table-font is written by the script
   below when a wide column choice would otherwise run off the sheet; for
   that to be worth anything, everything in the table has to hang on it.
   Hence em, not rem and not pt: rem measures from the root and would
   leave the cell padding at full size while the writing shrank — with
   twenty columns the padding alone is a fifth of the width, so half the
   shrinking would go nowhere. The three values below are the old fixed
   ones divided by 14 px, the table's own font size: at full size nothing
   moves, only at a reduced one does it all move together. */
table { width: 100%; border-collapse: collapse; font-size: var(--table-font, 10.5pt); }
th, td { text-align: left; vertical-align: top; padding: .39em .63em; }
thead th {
    background: var(--leaf);
    color: #fff;
    font-size: .81em;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-weight: 600;
}
thead th:first-child { border-radius: 4px 0 0 0; }
thead th:last-child  { border-radius: 0 4px 0 0; }
/* THE ZEBRA (the maintainer asked for it by name): every second row on a
   surface so the eye keeps the line across twelve columns. Pale enough
   that the hairline below still does its work on a full page. */
tbody tr:nth-child(even) { background: var(--zebra); }
tbody tr { border-bottom: .5px solid var(--hair); }
/* THE HEAD REPEATS ON EVERY PAGE — a table that runs over three pages is
   unreadable from page two on without it. */
thead { display: table-header-group; }
/* A find must not be torn across two pages — neither the row nor its
   photo strip. This is the one thing a print layout has to get right. */
tbody tr, .report-tile { break-inside: avoid; page-break-inside: avoid; }

/* THE TILES: for many columns, where a table would squeeze every value
   into a thumb's width (23.08.2026). The find's name leads,
   the rest stands underneath as "Feld: Wert" pairs — the same reading as
   the point window. As a card with the green edge on the left: that is
   what makes a page of tiles read as a list of finds instead of a wall
   of text. */
.report-tile {
    border-left: 3px solid var(--leaf);
    border-bottom: .5px solid var(--hair);
    border-radius: 0 4px 4px 0;
    padding: .5rem .7rem .55rem;
    margin-bottom: .35rem;
}
/* Counted INSIDE their own container: as bare siblings of the head band
   and the map the parity would depend on what stands above them — it
   worked, and it would have flipped the day somebody added a line there. */
.report-tiles > .report-tile:nth-child(even) { background: var(--zebra); }
.report-tile-title { font-weight: 600; font-size: 11.5pt; color: var(--leaf); }
.report-tile-pairs { display: flex; flex-wrap: wrap; gap: .2rem 1.2rem; margin-top: .25rem; }
.report-tile-pairs div { font-size: 9.5pt; }
.report-tile-pairs b { font-weight: 600; color: var(--quiet); font-weight: 500; }

/* How many finds stand behind one species line — quiet, in brackets:
   the species is the statement, the number the footnote. */
.report-count {
    font-size: 8.5pt; font-weight: 500; color: var(--quiet);
    background: #fff; border: .5px solid var(--hair); border-radius: 999px;
    padding: .02rem .4rem; margin-left: .4rem; white-space: nowrap;
}
tbody tr:nth-child(even) .report-count { background: #fff; }
/* The German name, a second line under the scientific one — quieter and
   not bold: garnish, not the name (CLAUDE.md, „scientific names first"). */
.report-german { font-weight: 400; font-size: .9em; color: var(--quiet); margin-top: .05rem; }
/* IN THE TABLE the chip shrinks along, in the tiles it does not: only the
   table is ever scaled down, and a chip that kept 8.5pt inside a 7pt row
   would stand there as the largest thing in the line. */
table .report-count { font-size: .81em; }

.report-photos { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .45rem; }
.report-photos img { height: auto; border: .5px solid var(--hair); border-radius: 3px; }

/* THE MAP IS A PICTURE, and the script at the foot of the page says at
   length why. What matters here: a canvas carries a FIXED bitmap, so
   whatever box it lands in, it shows the same section — the one thing a
   live map could not promise. height:auto keeps its proportions, so a
   print box that turns out a few millimetres narrower than the preview
   makes the picture smaller and never, ever cuts it. */
.report-map {
    display: block; width: 100%; height: auto; margin-bottom: 1.3rem;
    border: .5px solid var(--hair); border-radius: 5px;
    background: #eceee9;   /* the paper colour of the tiles, until they are there */
}
.report-foot {
    margin-top: 1.6rem; padding-top: .5rem;
    border-top: .5px solid var(--hair);
    color: var(--quiet); font-size: 8.5pt;
}

/* The print bar — in the PDF it is gone, on screen it says what to do.
   Without it one stares at a page and looks for the download. */
.report-bar { background: var(--leaf-pale); border: 1px solid var(--hair); border-radius: 8px;
              padding: .7rem .9rem; margin-bottom: 1.4rem; font-size: 10pt; color: var(--quiet); }
.report-bar button { font: inherit; font-weight: 600; padding: .4rem 1rem;
                     border: 0; border-radius: 6px; background: var(--leaf); color: #fff;
                     cursor: pointer; margin-right: .6rem; }
@media print { .report-bar { display: none; } }
/* The warning colour, not the accent: this says something did not work
   out, and it is the only line in the bar that is not an instruction.
   [hidden] has to be spelled out HERE, and only here: this page is a
   document of its own with its own <style> — it loads neither app.css nor
   map.css, and so it does not get their
   „[hidden] { display: none !important }" either. Everywhere else that
   line settles it; here .report-wide's own display would win, and the
   sentence would stand there always (see trap 1 in CLAUDE.md). */
.report-wide { display: block; margin-top: .5rem; color: #a4551b; font-weight: 500; }
.report-wide[hidden] { display: none; }
    /* Pictures of a shared find (co-ownership, 11.09.2026): the name under
       a picture that is not the holder's, the Motivname under every one,
       and the measurement lines per structure after the strip. */
    .report-photo { display: inline-block; vertical-align: top; margin: 0 2mm 2mm 0; }
    .report-photo img { display: block; }
    .report-caption { font-size: .8em; color: #555; margin-top: .5mm; max-width: 100%; }
    .report-measures { font-size: .85em; margin: 1mm 0 2mm; }
    .report-measures .report-measures-label { font-weight: 600; }
    .report-measures .report-measures-quiet { color: #555; }
</style>
</head>
<body>

<div class="report-bar">
    <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
    Im Druckfenster als Ziel „Als PDF sichern" wählen.
    <?php if ($withMap): ?>
        <?php /* The tiles come from a foreign server, and until they are all
             there the picture is not drawn. The script takes this line
             away the moment it is — so it says something true at every
             moment instead of standing there as a general warning. */ ?>
        <span id="report-map-wait">Die Karte wird gezeichnet — einen Augenblick.</span>
    <?php endif; ?>
    <?php if ($layout === 'list'): ?>
        <?php /* A STATE, so it stays in the flow (CLAUDE.md): it is the reason
             the table looks cut off, and it has to stand until somebody
             acts on it. The script unhides it when even the smallest
             writing does not bring the columns onto the page. */ ?>
        <span class="report-wide" id="report-wide" hidden>
            So viele Spalten passen nicht auf A4 — auch klein geschrieben nicht.
            Weniger Angaben wählen, oder im Fundbericht-Fenster die Kachelform nehmen.
        </span>
    <?php endif; ?>
</div>

<?php /* Everything below is the printed page, at the printed width. The bar
     above is screen furniture and stays outside. */ ?>
<div class="report-scale" id="report-scale">
<div class="report-sheet" id="report-sheet">

<div class="report-head">
    <h1>Fundbericht</h1>
    <?php if ($heading['area'] !== ''): ?>
        <p class="report-place"><?= h($heading['area']) ?></p>
    <?php endif; ?>
    <p class="report-facts">
        <?php if ($heading['sheets'] !== ''): ?><span>MTB <?= h($heading['sheets']) ?></span><?php endif; ?>
        <?php if ($heading['span'] !== ''): ?><span><?= h($heading['span']) ?></span><?php endif; ?>
        <?php if ($merge): ?>
            <?php /* Merged, the interesting number is a different one: how many
                 SPECIES were there — and out of how many finds. */ ?>
            <span><?= count($groups) ?> Art<?= count($groups) === 1 ? '' : 'en' ?>
                  aus <?= count($points) ?> Fund<?= count($points) === 1 ? '' : 'en' ?></span>
        <?php else: ?>
            <span><?= count($points) ?> Fund<?= count($points) === 1 ? '' : 'e' ?></span>
        <?php endif; ?>
        <?php if ($owners !== []): ?><span><?= h(implode(', ', $owners)) ?></span><?php endif; ?>
    </p>
</div>

<?php if ($withMap): ?>
    <?php /* NO LEAFLET ON THIS PAGE ANY MORE. It is the map everywhere else in
         the application and right there; here it was the defect (see the
         script below). The canvas brings its pixel size with it so the
         space is reserved before the first tile arrives — otherwise the
         page would jump once under the reading eye. */ ?>
    <?php /* The padding and the zoom cap are the old Leaflet values doubled
         with the picture: 20 screen pixels of air around the outermost
         find, and „no closer than zoom 15" — one level deeper here because
         every pixel is half as big on paper. */ ?>
    <canvas class="report-map" id="report-map"
            width="<?= $mapWidth ?>" height="<?= $mapHeight ?>"
            data-points="<?= h(json_encode(array_map(static fn(array $p): array => [
                (float) $p['lat'], (float) $p['lon']], $points))) ?>"
            data-pad="40" data-max-zoom="16" data-radius="8" data-font="20"
            data-wait="report-map-wait"></canvas>
<?php endif; ?>

<?php if ($layout === 'list'): ?>
    <table>
        <thead>
            <tr><?php foreach ($chosen as $key): ?><th><?= h($catalogue[$key][0]) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($groups as $members): ?>
            <tr>
                <?php foreach ($chosen as $i => $key): ?>
                    <td>
                        <?= nl2br(h(report_group_value($catalogue, $key, $members))) ?>
                        <?php if ($i === 0 && count($members) > 1): ?>
                            <span class="report-count"><?= count($members) ?> Funde</span>
                        <?php endif; ?>
                        <?php if ($i === 0 && $withGerman && ($g = $germanName($members)) !== ''): ?>
                            <div class="report-german"><?= h($g) ?></div>
                        <?php endif; ?>
                        <?php if ($i === 0 && $photoWidth > 0) {
                            foreach ($members as $p) { report_photos($p, $photoWidth, (int) $me['id']); report_measures($p, $straightLength); }
                        } ?>
                        <?php if ($i === 0) { foreach ($members as $p) { report_sequences($p); } } ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <?php
    // The first column is the tile's headline, the rest its pairs — once,
    // not per find.
    $first = $chosen[0];
    $rest  = array_slice($chosen, 1);
    ?>
    <div class="report-tiles">
    <?php foreach ($groups as $members): ?>
        <div class="report-tile">
            <div class="report-tile-title">
                <?= h(report_group_value($catalogue, $first, $members)) ?: 'Ohne Titel' ?>
                <?php if (count($members) > 1): ?>
                    <span class="report-count"><?= count($members) ?> Funde</span>
                <?php endif; ?>
            </div>
            <?php if ($withGerman && ($g = $germanName($members)) !== ''): ?>
                <div class="report-german"><?= h($g) ?></div>
            <?php endif; ?>
            <div class="report-tile-pairs">
                <?php foreach ($rest as $key): $value = report_group_value($catalogue, $key, $members); ?>
                    <?php if (trim($value) !== ''): ?>
                        <div><b><?= h($catalogue[$key][0]) ?>:</b> <?= h($value) ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php if ($photoWidth > 0) {
                foreach ($members as $p) { report_photos($p, $photoWidth, (int) $me['id']); report_measures($p, $straightLength); }
            } ?>
            <?php foreach ($members as $p) { report_sequences($p); } ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php /* The name, since 24.08.2026 (CLAUDE.md: the NAME carries the topic).
     „mit der Karte" was the line from before the christening; the maintainer
     read it on the first printed report of a real excursion, 12.09.2026. */ ?>
<p class="report-foot">
    Erstellt am <?= h(date('d.m.Y')) ?> mit YASAIMAPP.
</p>

</div><?php /* .report-sheet */ ?>
</div><?php /* .report-scale */ ?>

<script>
/* THE SHEET SHRINKS TO FIT THE WINDOW, IT DOES NOT NARROW. On a phone
   194mm of paper do not fit, and the obvious fix — let it get narrower —
   is the one thing that must not happen: the map inside frames itself
   for its container, so a narrower container would print a different
   section again, and the whole point of the A4 preview is gone. So the
   layout keeps its printed width and only the PAINTING is scaled down.
   The reader sees a small whole page instead of a large half one, and
   what they see is to scale. */
(function () {
    var scale = document.getElementById('report-scale');
    var sheet = document.getElementById('report-sheet');
    if (!scale || !sheet) return;

    var lastRoom = -1, lastHeight = -1;
    var fit = function () {
        var room = scale.clientWidth;
        var need = sheet.offsetWidth;
        var height = sheet.offsetHeight;
        if (need === 0) return;
        /* THE GUARD IS NOT AN OPTIMISATION. The observer below watches the
           element this function writes to, and margin-bottom changes its
           height — without the bail-out that is a loop feeding itself. */
        if (room === lastRoom && height === lastHeight) return;
        lastRoom = room;
        lastHeight = height;
        scale.style.setProperty('--sheet-scale', String(Math.min(1, room / need)));
        scale.style.setProperty('--sheet-height', height + 'px');
    };
    fit();
    window.addEventListener('resize', fit);
    window.addEventListener('load', fit);
    /* Both boxes are watched, and for different reasons: the sheet grows
       as photos arrive and the map draws, and the window's own width
       reaches us here more reliably than through the resize event alone
       (a phone that turns, a pane that is dragged). */
    if (window.ResizeObserver) {
        var watch = new ResizeObserver(fit);
        watch.observe(sheet);
        watch.observe(scale);
    }

    /* THE TABLE IS BROUGHT BACK ONTO THE PAGE (24.08.2026).
       Since the preview is A4, one can finally SEE what was always true:
       past a certain number of columns a find table is wider than the
       paper. A table never falls below its own minimum width — the
       longest word in every column plus the padding — so width:100% is a
       wish, not a rule, and what sticks out is simply cut off when
       printing.

       So the writing gets smaller until it fits. Measured, not reckoned
       from the number of columns: what makes a table wide is not how many
       columns there are but what stands IN them — six columns of species
       names are wider than twelve of dates. Quarter point by quarter
       point, because a computed factor misses: the padding scales with
       the font, the column widths do not follow it linearly, and after
       one step the browser has redistributed everything anyway.

       6.5pt IS THE FLOOR and it is a decision, not a safety margin.
       Anything smaller is not a small report any more, it is an
       unreadable one, and a report nobody can read has not been rescued
       by fitting on the page. Whoever hits the floor gets told — with
       twenty columns no font size helps, that is what the tiles form is
       for, and it stands in the same window. */
    var table = sheet.querySelector('table');
    var floorNote = document.getElementById('report-wide');
    if (!table) return;

    var BASE = 10.5, FLOOR = 6.5, STEP = .25;
    var fitTable = function () {
        var cs = getComputedStyle(sheet);
        var room = sheet.clientWidth
            - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        if (room <= 0) return;
        var size = BASE;
        table.style.setProperty('--table-font', size + 'pt');
        /* The half pixel keeps a rounding difference from costing a step:
           a table that is 688.4 wide in 688 of room fits. */
        while (table.offsetWidth > room + .5 && size > FLOOR) {
            size = Math.max(FLOOR, size - STEP);
            table.style.setProperty('--table-font', size + 'pt');
        }
        if (floorNote) floorNote.hidden = table.offsetWidth <= room + .5;
    };
    fitTable();
    /* Again once the photos are there: they stand in the cells and carry
       their millimetres with them, so before they arrive the table is
       measured too narrow. */
    window.addEventListener('load', fitTable);
    window.addEventListener('beforeprint', fitTable);
})();
</script>

<?php if ($withMap): ?>
<?php /* THE MAP IS PAINTED ONCE AND IS THEN A PICTURE (24.08.2026 —
     „Warum kann man denn nicht einfach ein Canvas machen"): a live map
     cannot be pinned down for printing — Leaflet frames itself for the box
     of one moment, and the print box is never that box; two repairs
     (handlers off, re-framing on beforeprint) were right and not enough,
     because beforeprint fires before the print layout exists. So the map
     stops being a map. The painting lives in assets/js/staticmap.js since
     13.09.2026, shared with the excursion's closing page; the canvas above
     carries its points and its settings as data attributes. */ ?>
<script type="module" src="<?= h(asset_url('assets/js/staticmap.js')) ?>"></script>
<?php endif; ?>

</body>
</html>
<?php

/**
 * What one column says about a GROUP of finds.
 *
 * One find: its value, unchanged. Several: the distinct values, and that
 * is the honest answer — three finds of one species were made at three
 * times, by three people, possibly in two municipalities, and a report
 * that showed only the first would quietly drop the other two.
 *
 * THE TIME BECOMES A SPAN, not a list: "14.08.2026 – 16.08.2026" reads,
 * six timestamps in a row do not. Everything else is joined, capped —
 * after the fifth name the cell stops being information.
 */
function report_group_value(array $catalogue, string $key, array $members): string
{
    $values = [];
    foreach ($members as $p) {
        $value = trim(($catalogue[$key][1])($p));
        if ($value !== '' && !in_array($value, $values, true)) {
            $values[] = $value;
        }
    }
    if ($values === [])      return '';
    if (count($values) === 1) return $values[0];

    if ($key === 'found_at') {
        // Sorted on the RAW time, not on the German text — "01.09." would
        // otherwise sort before "14.08.".
        $times = array_filter(array_map(static fn(array $p): string => (string) ($p['found_at'] ?? ''), $members));
        sort($times);
        $first = report_time($times[0] ?? null);
        $last  = report_time(end($times) ?: null);
        return $first === $last ? $first : "$first – $last";
    }

    $shown = array_slice($values, 0, 5);
    return implode(', ', $shown) . (count($values) > 5 ? ' …' : '');
}

/**
 * The photo strip of one find.
 *
 * The preview image, not the full one: a report holds a dozen pictures,
 * and the big ones are several megabytes each — the printout would take
 * minutes and show no more detail at 45 mm width.
 */
function report_photos(array $p, int $widthMm, int $viewerId): void
{
    $photos     = is_array($p['photos'] ?? null) ? $p['photos'] : [];
    $recordings = is_array($p['recordings_list'] ?? null) ? $p['recordings_list'] : [];
    if ($photos === [] && $recordings === []) {
        return;
    }
    /* WHOSE PICTURE (co-ownership, 11.09.2026): the name goes under a
       picture whose photographer is not the find's holder — on a shared
       find the leader's micrograph beside the guest's voucher — and the
       Motivname under every one that has it. The holder's own need no
       name: the find is his, the report says so in its columns. */
    $holder = (int) ($p['owner_id'] ?? $viewerId);
    $caption = static function (?string $title, ?int $by, ?string $name) use ($holder): string {
        $bits = [];
        if ($title !== null && $title !== '') {
            // The micro names international (includes/vocabulary.php), the macro ones as stored.
            $bits[] = vocab($title);
        }
        if ($by !== null && $by !== $holder && $name !== null && $name !== '') {
            $bits[] = t('Foto: %s', $name);
        }
        return $bits === [] ? '' : '<div class="report-caption">' . h(implode(' · ', $bits)) . '</div>';
    };
    echo '<div class="report-photos">';
    foreach ($photos as $photo) {
        printf('<figure class="report-photo" style="width:%dmm"><img src="%s" style="width:%dmm" alt="">%s</figure>',
            $widthMm, h(url('pages/photo.php') . '?id=' . (int) $photo['id'] . '&k=1'), $widthMm,
            $caption($photo['title'] ?? null, isset($photo['photographer_id']) ? (int) $photo['photographer_id'] : null,
                $photo['photographer'] ?? null));
    }
    /* THE CLOUD RECORDINGS, through our own preview route: pCloud's links
       are bound to the asking address (pages/cloud_thumb.php) — and since
       co-ownership that route serves the whole circle, from my own copy
       where the worker has made one. The 2048-px preview, like the
       window: a print at 45 mm needs no original. */
    foreach ($recordings as $f) {
        printf('<figure class="report-photo" style="width:%dmm"><img src="%s" style="width:%dmm" alt="">%s</figure>',
            $widthMm, h(url('pages/cloud_thumb.php') . '?id=' . (int) $f['id'] . '&c=1&large=1'), $widthMm,
            $caption($f['title'] ?? null, isset($f['photographer_id']) ? (int) $f['photographer_id'] : null,
                $f['photographer'] ?? null));
    }
    echo '</div>';
}

/**
 * The measurements of one find, per structure, over EVERY picture of it
 * — voucher photos and cloud recordings, all photographers: „Sporen:
 * (8,2) 8,6 – 10,1 (10,4) × (4,8) 5,0 – 6,0 (6,1) µm · Q = 1,5 – 1,9 ·
 * N = 26". The same arithmetic the photo window shows, in PHP
 * (includes/measures.php explains the twin). Printed whether or not the
 * report carries pictures: a species description is numbers first.
 */
/**
 * The sequences of a find (migration 0112), one line each: marker, length,
 * accession, the sequencer — whatever the picture size, a voucher in text
 * costs the page nothing.
 */
function report_sequences(array $p): void
{
    $rows = is_array($p['sequences'] ?? null) ? $p['sequences'] : [];
    foreach ($rows as $s) {
        $parts = array_filter([
            $s['marker'] !== '' ? $s['marker'] : null,
            t('%d bp', (int) $s['length']),
            $s['accession'] !== '' ? $s['accession'] : null,
            $s['lab'] !== '' ? $s['lab'] : null,
        ]);
        echo '<div class="report-measures"><span class="report-measures-label">' . h(t('Sequenz')) . ':</span> '
            . h(implode(' · ', $parts))
            . ' <span class="report-measures-quiet">· ' . h(t('von %s', (string) $s['author'])) . '</span></div>';
    }
}

function report_measures(array $p, array $straight = []): void
{
    require_once __DIR__ . '/../includes/measures.php';
    $pictures = array_merge(
        is_array($p['photos'] ?? null) ? $p['photos'] : [],
        is_array($p['recordings_list'] ?? null) ? $p['recordings_list'] : []
    );
    foreach (measure_labels($pictures) as $label) {
        $rows = measure_rows($pictures, $label, in_array($label, $straight, true));
        $lines = measure_summary($rows);
        report_measure_layer($label, $rows, $lines, $straight);
        /* THE PART MEASURES under the structure (07.10.2026): germ pore,
           ornament, sterigma … one line each — and in a layer that holds
           nothing else (a basidium's sterigmata), they stand on their own. */
        foreach (measure_part_lines(measure_part_rows($pictures, $label)) as $part => $line) {
            report_measure_line(vocab($label) . ' · ' . vocab($part), [$line]);
        }
    }
}

/** A layer's own numbers: the structure's line, how its lengths were read,
 *  and a line per mark. Nothing when the layer holds no structure. */
function report_measure_layer(string $label, array $rows, array $lines, array $straight): void
{
    if ($lines === []) {
        return;
    }
    /* WHERE A LENGTH WAS TRACED AS A POLYLINE the line says how it is
       read — on paper there is no switch to look at, and a curved
       length and a straight one differ by just enough to mislead. */
    if (array_filter($rows, static fn(array $r): bool => $r['bent']) !== []) {
        $lines[] = in_array($label, $straight, true)
            ? t('Länge klassisch gemessen, gerade von Ende zu Ende')
            : t('Länge gebogen gemessen, entlang der Linie');
    }
    // The words international, the comparisons above on the stored ones (includes/vocabulary.php).
    report_measure_line(vocab($label), $lines);
    /* EACH MARK ITS OWN LINE under the layer's (07.10.2026) — the same
       measures again, only their kind: the photo window's „Zeigen" for
       paper. The unmarked ones get theirs only beside marked ones. */
    $marks = array_values(array_unique(array_filter(array_column($rows, 'mark'))));
    if ($marks === []) {
        return;
    }
    $unmarked = array_values(array_filter($rows, static fn(array $r): bool => $r['mark'] === ''));
    foreach ($marks as $mark) {
        $own = array_values(array_filter($rows, static fn(array $r): bool => $r['mark'] === $mark));
        report_measure_line(vocab($label) . ' (' . vocab($mark) . ')', measure_summary($own));
    }
    if ($unmarked !== []) {
        report_measure_line(vocab($label) . ' (' . t('ohne Merkmal') . ')', measure_summary($unmarked));
    }
}

/** One label's line: the main numbers, the rest quiet behind them. */
function report_measure_line(string $label, array $lines): void
{
    echo '<div class="report-measures"><span class="report-measures-label">' . h($label) . ':</span> '
        . h(array_shift($lines));
    foreach ($lines as $line) {
        echo ' <span class="report-measures-quiet">· ' . h($line) . '</span>';
    }
    echo '</div>';
}
