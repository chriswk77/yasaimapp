<?php
/**
 * Generate the application's icons. Command line only.
 *
 *   php admin/symbols_generate.php
 *
 * A phone needs them as PNG in fixed sizes — not every home screen accepts
 * an SVG. Instead of painting them by hand and storing them somewhere, they
 * come into being here from a few lines: that way they are traceable at any
 * time, identical at every size, and a color change is a one-liner instead
 * of a round through an image program.
 *
 * The drawing is deliberately plain: dark ground as in the application, a
 * light ring and a dot inside — the same language as the location display
 * on the map. No theme, no mushroom; the map is neutral.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const SYMBOL_PATH = PROJECT_PATH . '/assets/symbole';

/** The sizes Android and iOS expect, plus one for the address bar. */
const SIZES = [32, 180, 192, 512];

if (!is_dir(SYMBOL_PATH) && !mkdir(SYMBOL_PATH, 0775, true)) {
    fwrite(STDERR, "Verzeichnis lässt sich nicht anlegen: " . SYMBOL_PATH . "\n");
    exit(1);
}

/**
 * Draw one icon.
 *
 * @param bool $borderless For 'maskable': Android cuts a circle or a square
 *                      with rounded corners out of it, so the ground must
 *                      reach the edge and the mark must stay well inside.
 *                      Without that, the system crops the icon and it looks
 *                      wrong in a different way on every device.
 */
function symbol_draw(int $edge, bool $borderless = false): GdImage
{
    $image = imagecreatetruecolor($edge, $edge);
    imagesavealpha($image, true);
    imagealphablending($image, true);

    $ground  = imagecolorallocate($image, 0x1c, 0x22, 0x29);
    $ring   = imagecolorallocate($image, 0x6a, 0xa8, 0x4f);
    $center  = imagecolorallocate($image, 0xe6, 0xeb, 0xf0);

    if ($borderless) {
        imagefilledrectangle($image, 0, 0, $edge, $edge, $ground);
    } else {
        // Rounded square, by hand: four circles in the corners and two
        // rectangles in between. GD cannot do that on its own.
        $r = (int) round($edge * 0.22);
        imagefilledrectangle($image, $r, 0, $edge - $r, $edge, $ground);
        imagefilledrectangle($image, 0, $r, $edge, $edge - $r, $ground);
        foreach ([[$r, $r], [$edge - $r, $r], [$r, $edge - $r], [$edge - $r, $edge - $r]] as [$x, $y]) {
            imagefilledellipse($image, $x, $y, $r * 2, $r * 2, $ground);
        }
    }

    // Everything smaller for the maskable icon: the safe zone is only the
    // central circle area with 80 percent diameter.
    $factor  = $borderless ? 0.44 : 0.60;
    $outer  = (int) round($edge * $factor);
    $thickness   = max(2, (int) round($edge * ($borderless ? 0.075 : 0.10)));
    $m       = intdiv($edge, 2);

    imagefilledellipse($image, $m, $m, $outer, $outer, $ring);
    imagefilledellipse($image, $m, $m, $outer - $thickness * 2, $outer - $thickness * 2, $ground);
    imagefilledellipse($image, $m, $m, (int) round($outer * 0.34), (int) round($outer * 0.34), $center);

    return $image;
}

foreach (SIZES as $edge) {
    $image = symbol_draw($edge);
    $file = SYMBOL_PATH . "/symbol-$edge.png";
    imagepng($image, $file, 9);
    imagedestroy($image);
    printf("  %-28s %5d Bytes\n", basename($file), filesize($file));
}

foreach ([192, 512] as $edge) {
    $image = symbol_draw($edge, true);
    $file = SYMBOL_PATH . "/symbol-maskierbar-$edge.png";
    imagepng($image, $file, 9);
    imagedestroy($image);
    printf("  %-28s %5d Bytes\n", basename($file), filesize($file));
}

echo "\n  Fertig in " . SYMBOL_PATH . "\n";
