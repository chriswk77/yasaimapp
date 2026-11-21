<?php
/**
 * The manifest — as PHP, because BASE_URL is only known at runtime.
 *
 * Locally the application lives under /map/, live it could sit on a subdomain
 * of its own. A hard-coded path would be wrong in one of the two places, and
 * a wrong start_url opens the installed app on an empty page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$symbol = static fn(string $file, string $purpose = 'any'): array => [
    'src'     => BASE_URL . 'assets/symbole/' . $file,
    'sizes'   => preg_replace('/\D+/', '', $file) . 'x' . preg_replace('/\D+/', '', $file),
    'type'    => 'image/png',
    'purpose' => $purpose,
];

echo json_encode([
    /* A STABLE IDENTITY, spelled out. Without 'id' Chrome takes start_url
       as the installed app's identity — which works until start_url ever
       changes, and then the same app counts as another one. The id is a
       name, not an address: it may stay while the address moves. */
    'id'         => BASE_URL,
    'name'       => 'YASAIMAPP',
    'short_name' => 'YASAIMAPP',
    'description' => 'Eigene Punkte auf amtlichen Luftbildern — auch ohne Netz.',
    'lang'       => 'de',
    'dir'        => 'ltr',

    'start_url' => BASE_URL,
    'scope'     => BASE_URL,

    // 'standalone': without an address bar, like an app. The map needs every
    // pixel, and an address bar helps nobody here.
    'display'          => 'standalone',
    'orientation'      => 'any',
    'background_color' => '#14181c',
    'theme_color'      => '#14181c',

    'icons' => [
        [
            'src'   => BASE_URL . 'assets/symbole/symbol-192.png',
            'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any',
        ],
        [
            'src'   => BASE_URL . 'assets/symbole/symbol-512.png',
            'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any',
        ],
        // 'maskable': Android cuts its own shape out of this. Without a
        // dedicated version for that, it crops the normal icon and the
        // margin goes missing.
        [
            'src'   => BASE_URL . 'assets/symbole/symbol-maskierbar-192.png',
            'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable',
        ],
        [
            'src'   => BASE_URL . 'assets/symbole/symbol-maskierbar-512.png',
            'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
