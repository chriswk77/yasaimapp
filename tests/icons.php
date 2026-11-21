<?php
/**
 * Tests the icon library.
 *
 *   php tests/icons.php
 *
 * WHY THIS TEST EXISTS: the library is worth something exactly when
 * nobody draws past it. Both can go wrong silently —
 *
 *   A SECOND DRAWING. Before the library, the map sheet stood three
 *   times in the project and the clock twice, and the two clocks had
 *   already drifted apart (hand once at 7.2, once at 7.4). Nobody saw
 *   it, because the two never stood side by side. That is why this test
 *   searches the source for <svg> tags outside the library.
 *
 *   A SIZE IN THE SVG. It belongs in the stylesheet — otherwise what
 *   happened in the sidebar head happens: width="18" attributes stood
 *   there doing nothing, because a CSS rule overruled them. An attribute
 *   that does nothing is worse than none.
 *
 * The exceptions are named and justified, not tacit: the map's marker
 * shapes (content, not chrome) and the Leaflet icons, whose size goes
 * into the anchor computation.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/icons.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nIcon library\n\n";

$all = icon_stock_all();
check(count($all) >= 10, count($all) . ' icons in the stock');

echo "Each icon on its own:\n";
foreach ($all as $name => $drawing) {
    $markup = icon($name);

    // Well-formed: an SVG the browser cannot read otherwise only strikes
    // the user — as an empty area where an icon should stand.
    $before = libxml_use_internal_errors(true);
    $readable = simplexml_load_string($markup) !== false;
    libxml_clear_errors();
    libxml_use_internal_errors($before);

    // ONLY THE <svg> TAG ITSELF, up to the first '>': a width= also sits
    // in the geometry — <rect width="17"> in the calendar and the camera
    // is the drawing, not the size. The first version of this test
    // searched the whole markup and fired on exactly these two.
    $head = substr($markup, 0, (int) strpos($markup, '>'));

    check($readable, "$name: well-formed SVG");
    check(str_contains($markup, 'viewBox="0 0 24 24"'), "$name: in the 24x24 box");
    check(!str_contains($head, ' width='), "$name: without a size attribute");
    check(str_contains($markup, 'class="icon'),"$name: carries the class .icon");
    check(str_contains($markup, 'currentColor'), "$name: follows the text colour");
}

echo "The edges:\n";
$thrown = false;
try {
    icon('gibtsnicht');
} catch (InvalidArgumentException $e) {
    $thrown = true;
}
check($thrown, 'an unknown name throws instead of silently drawing nothing');

check(str_contains(icon('magnifier', ['title' => 'Suchen']), '<title>Suchen</title>')
      && !str_contains(icon('magnifier', ['title' => 'Suchen']), 'aria-hidden'),
    'with a title the icon becomes speaking, and aria-hidden gives way');
check(str_contains(icon('magnifier'), 'aria-hidden="true"'),
    'without a title it stays silent — the button around it is labelled anyway');
check(str_contains(icon('map', ['class' => 'brand-icon']), 'class="icon brand-icon"'),
    'an own class comes IN ADDITION to the base class, not INSTEAD of it');
check(str_contains(icon('close', ['stroke' => 2]), 'stroke-width="2"'),
    'the stroke width can be set per call site');

echo "The stock for the script:\n";
$stock = icon_stock();
check(substr_count($stock, '<template') === count($all),
    'without a selection every icon comes along');
foreach (['fold-triangle', 'clock', 'az', 'delete', 'calendar', 'map', 'mountain', 'area',
          'photo', 'place', 'points'] as $name) {
    check(str_contains($stock, 'data-icon="' . $name . '"'),
        "$name stands in the stock — map.js takes it from there");
}

// ---------------------------------------------------------------------------
// Nobody draws past the library
// ---------------------------------------------------------------------------

echo "No second drawing in the project:\n";

$root = dirname(__DIR__);
$files = [];
foreach (['includes', 'assets/js', 'pages', 'admin', 'api', 'modules'] as $folder) {
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator("$root/$folder", FilesystemIterator::SKIP_DOTS)
    ) as $d) {
        if (preg_match('/\.(php|js)$/', $d->getFilename())) {
            $files[] = $d->getPathname();
        }
    }
}
$files[] = "$root/index.php";
foreach (glob("$root/*.php") ?: [] as $d) {
    $files[] = $d;
}

/*
 * THE NAMED EXCEPTIONS, each with a reason:
 *
 *   map.js  Marker shapes are content, not chrome — the user picks
 *             them per list. Still standing here are the marker itself
 *             (symbolFor) and the boxes of the shape picker
 *             (buildChoice); the geometry has lived in
 *             includes/shapes.php since 14.08.2026. Plus the two Leaflet
 *             icons, whose size goes into iconSize/iconAnchor and
 *             therefore MUST stand in the markup.
 *   shapes.js  builds the colour dot from the geometry over there.
 *   icon.php / shapes.php  are the libraries themselves.
 *   photo_window.php  the photo window's measuring plane (28.08.2026)
 *             — an EMPTY <svg> that photowindow.js draws calibration
 *             lines and measures into — and, since stage two, the
 *             distribution plot of the statistics sidebar
 *             (#photo-stats-plot), an equally empty canvas that
 *             photowindow.js fills with the spores' length-against-width
 *             dots. Canvases, not icons; their contents are user data and
 *             could never live in a library. In index.php until
 *             30.09.2026, when the viewer's markup moved so the
 *             administration can print it too.
 */
$exceptions = [
    'assets/js/map.js'   => 4,   // marker, shape picker, 2 Leaflet icons
    'assets/js/shapes.js'  => 1,   // shapePoint
    'includes/photo_window.php' => 3,   // measuring plane + distribution plot + the ellipses' grips (0190)
    // The calibration stage's measuring plane (Einstellungen → Fotos) —
    // an empty canvas like the two above, drawn into by calibrate.js.
    'includes/settings_content.php' => 1,
    // The live preview's measuring plane (Einstellungen → Fotos → Messen,
    // 26.09.2026) — the same kind of canvas, filled by measuredraw.js.
    'assets/js/measurepreview.js' => 1,
    // The vector map rasterizes a find's symbol — the shapes library's own
    // drawing — into an image for MapLibre, which needs the SVG namespace
    // on it (iconImage); the string names the tag, it draws nothing.
    'assets/js/vectormap.js' => 1,
    // A plate's distribution (06.10.2026) — the photo window's plot in ink
    // on paper: the strip's small one to drag (plates.js), and the one the
    // printed page draws into its canvas (plateprint.js). User data, like
    // the plot above, never a library's drawing. Since 07.10.2026 the strip
    // holds a second: a part measure's curve (Keimporus, Sterigmen …).
    'assets/js/plates.js' => 2,
    'assets/js/plateprint.js' => 1,
    // The writing on a plate's pictures (08.10.2026): the viewer's texts,
    // arrows and counting boxes laid over a cell — user data, drawn like
    // the measuring plane, never a library's drawing.
    'assets/js/plateannot.js' => 1,
    // The microscope's quick measuring (16.11.2026): an empty plane over
    // the live picture, filled with the lines and rectangles one sets —
    // user data, like the photo window's measuring plane.
    'includes/gallery_windows.php' => 1,
];

/* A MODULE NAMES ITS OWN EXCEPTIONS (21.11.2026). Its files are held to the
   same rule — the scan above reads modules/ too —, but what it draws is not
   this list's to know: modules/<name>/tests/icon_exceptions.php returns
   [path inside the module => how many], each with its reason written there. */
foreach (glob("$root/modules/*/tests/icon_exceptions.php") ?: [] as $file) {
    $module = basename(dirname($file, 2));
    foreach ((array) require $file as $path => $count) {
        $exceptions["modules/$module/$path"] = (int) $count;
    }
}

/* The libraries do not count — they ARE the drawings. */
$libraries = ['includes/icons.php', 'includes/shapes.php'];

foreach ($files as $path) {
    $short = str_replace($root . '/', '', $path);
    if (in_array($short, $libraries, true) || str_contains($short, '/vendor/')) {
        continue;
    }
    $content = (string) file_get_contents($path);
    $count   = substr_count($content, '<svg');
    $allowed = $exceptions[$short] ?? 0;

    check($count <= $allowed, $allowed > 0
        ? "$short: at most $allowed own SVG (found: $count)"
        : "$short: no own SVG drawing");
}

echo "No sizes in the markup:\n";
foreach ($files as $path) {
    $short = str_replace($root . '/', '', $path);
    if (in_array($short, $libraries, true) || str_contains($short, '/vendor/')) {
        continue;
    }
    $content = (string) file_get_contents($path);

    // Only lines with an <svg> tag: width= also stands on <rect> and
    // <input>, and those are not what this rule means.
    $matches = 0;
    foreach (explode("\n", $content) as $row) {
        if (str_contains($row, '<svg') && preg_match('/\bwidth="/', $row)) {
            $matches++;
        }
    }
    // The two Leaflet icons in map.js may (see above), and the printed
    // plate's plot: an SVG turned into an image needs its own size, or the
    // browser rasterises it at 300 × 150 before the canvas scales it up.
    $sized = ['assets/js/map.js' => 2, 'assets/js/plateprint.js' => 1];
    check($matches <= ($sized[$short] ?? 0),
        "$short: no size on the <svg> (found: $matches)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
