<?php
/**
 * Tests the relay for the aerial services without CORS (includes/tile_relay.php).
 *
 *   php tests/tile_relay.php
 *
 * WHAT MATTERS is the gate: the relay hands on a picture from a foreign
 * server, so it must let through exactly the query the map writes and
 * nothing else — not another layer, not another service, not a giant
 * picture, not a request that is no GetMap. The last block asks the two
 * states for real; without network it is skipped, not failed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tile_relay.php';

$failed = 0;
$skipped = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "Which services:\n";
$sh = tile_relay_service('Schleswig-Holstein');
$st = tile_relay_service('Sachsen-Anhalt');
check($sh !== null && $st !== null, 'Schleswig-Holstein and Sachsen-Anhalt are relayed');
check(tile_relay_service('Hessen') === null, 'Hessen is not — it sends CORS itself');
check(tile_relay_service('') === null && tile_relay_service('../etc') === null, 'nothing and nonsense are no service');

// The query Leaflet's WMS layer writes for a 512 tile at level 19 near Kiel.
$map = [
    'relay' => 'Schleswig-Holstein', 'service' => 'WMS', 'request' => 'GetMap', 'layers' => 'sh_dop20_rgb',
    'styles' => '', 'format' => 'image/jpeg', 'transparent' => 'false', 'version' => '1.3.0',
    'width' => '512', 'height' => '512', 'crs' => 'EPSG:3857',
    'bbox' => '1090909.2676860357,7234575.391452573,1091062.1417355691,7234728.265502106',
];
$with = static fn (array $change) => array_merge($map, $change);

echo "The gate:\n";
$q = tile_relay_query($map, $sh);
check($q !== null && str_contains($q, 'layers=sh_dop20_rgb') && str_contains($q, 'width=512'), 'the map\'s own query passes');
check(!str_contains((string) $q, 'relay='), 'our own key does not travel on');
check(tile_relay_query(array_change_key_case($map, CASE_UPPER), $sh) !== null, 'the keys in capitals too');
check(tile_relay_query($with(['layers' => 'other_layer']), $sh) === null, 'another layer: no');
check(tile_relay_query($with(['request' => 'GetCapabilities']), $sh) === null, 'no GetMap: no');
check(tile_relay_query($with(['request' => 'GetFeatureInfo']), $sh) === null, 'GetFeatureInfo: no');
check(tile_relay_query($with(['width' => '4096']), $sh) === null, 'a picture of 4096: no');
check(tile_relay_query($with(['width' => '0']), $sh) === null, 'a picture of 0: no');
check(tile_relay_query($with(['format' => 'text/html']), $sh) === null, 'another format: no');
check(tile_relay_query($with(['crs' => 'EPSG:4326']), $sh) === null, 'another projection: no');
check(tile_relay_query($with(['bbox' => '1,2,3']), $sh) === null, 'three numbers: no box');
check(tile_relay_query($with(['bbox' => '1,2,3,x']), $sh) === null, 'a letter in the box: no');
check(tile_relay_query($with(['bbox' => '1,2,3,99999999999']), $sh) === null, 'a box beyond the world: no');
check(tile_relay_query($with(['styles' => 'x']), $sh) === null, 'a style: no');
check(tile_relay_query($with(['layers' => 'sh_dop20_rgb', 'exceptions' => 'x']), $sh) !== null
    && !str_contains((string) tile_relay_query($with(['exceptions' => 'x']), $sh), 'exceptions'),
    'an unknown key is dropped, not passed on');

echo "The states, asked for real:\n";
$cases = [
    ['Schleswig-Holstein', $sh, $map],
    ['Sachsen-Anhalt', $st, $with(['relay' => 'Sachsen-Anhalt', 'layers' => 'lsa_lvermgeo_dop20_2',
        'bbox' => '1296844.6,6776853.1,1296997.5,6777006.0'])],
];
foreach ($cases as [$name, $service, $get]) {
    $url = $service['url'] . '?' . tile_relay_query($get, $service);
    $answer = tile_relay_fetch($url);
    if ($answer['status'] === 504) { $skipped++; echo "  ----  skipped (no network): $name\n"; continue; }
    $size = $answer['body'] !== '' ? @getimagesizefromstring($answer['body']) : false;
    check($answer['status'] === 200 && is_array($size) && $size[0] === 512 && $size[1] === 512,
        "$name answers a picture of 512 × 512 (" . ($answer['type'] ?: 'none') . ', ' . strlen($answer['body']) . ' bytes)');
}
$bad = tile_relay_fetch($sh['url'] . '?' . str_replace('sh_dop20_rgb', 'no_such_layer', (string) tile_relay_query($map, $sh)));
if ($bad['status'] !== 504) {
    check($bad['status'] === 502, 'a WMS error page (XML with 200) is no picture: 502');
}

echo $failed ? "\n$failed check(s) failed.\n" : "\nAll good." . ($skipped ? " ($skipped skipped)" : '') . "\n";
exit($failed ? 1 : 0);
