<?php
/**
 * The aerial pictures of the states that let no web page read them.
 *
 * THE FINDING (01.10.2026). The vector map draws every raster picture
 * itself — it fetches the bytes and hands MapLibre a bitmap
 * (vectorlayers.js, the stack protocol) — and a page may only read a
 * foreign picture when the server says so (CORS). Sachsen-Anhalt's and
 * Schleswig-Holstein's DOP services send no such permission: measured, a
 * fetch fails, only an opaque answer comes, and an opaque answer cannot
 * be drawn. The Leaflet map never noticed, because an <img> may show what
 * it cannot read; since the vector map is everybody's, those two states
 * showed the worldwide picture at best, at the fine levels nothing.
 *
 * SO THE SERVER ASKS (01.10.2026: „Ja, bau die Weiterleitung
 * für die zwei Länder"): api/v1/tile_relay.php takes the very GetMap query
 * Leaflet's WMS layer writes, checks it against the registry, asks the
 * state and hands the picture on. Only for the regions that say
 * 'relay' => true in map_layers.php, only GetMap, only their own layer,
 * only pictures — a relay for two services, never an open proxy. The price
 * is the traffic on the webspace, viewing and area downloads alike; the
 * Leaflet map keeps asking the states directly (layers.js, relayBase).
 */

declare(strict_types=1);

require_once __DIR__ . '/map_layers.php';

/** The largest picture side passed on — the map asks 512 (wmsTileSize). */
const TILE_RELAY_MAX_SIDE = 1024;

/** The formats a relayed GetMap may ask for. */
const TILE_RELAY_FORMATS = ['image/jpeg', 'image/png', 'image/vnd.jpeg-png8'];

/**
 * The registry's service for a region that is relayed, or null.
 *
 * @return array<string, mixed>|null
 */
function tile_relay_service(string $region): ?array
{
    foreach (map_layers() as $entry) {
        if (($entry['type'] ?? '') !== 'wms-by-area') {
            continue;
        }
        foreach ($entry['regions'] ?? [] as $service) {
            if (($service['region'] ?? '') === $region && !empty($service['relay']) && !empty($service['url'])) {
                return $service;
            }
        }
    }
    return null;
}

/**
 * The query to send on, rebuilt from checked values only — or null when
 * anything is not what the map itself would ask.
 *
 * @param array<string, mixed> $get     the request's query, any key case
 * @param array<string, mixed> $service from tile_relay_service()
 */
function tile_relay_query(array $get, array $service): ?string
{
    $q = array_change_key_case(array_map(static fn ($v) => is_string($v) ? $v : '', $get), CASE_LOWER);

    if (strcasecmp($q['service'] ?? '', 'WMS') !== 0 || strcasecmp($q['request'] ?? '', 'GetMap') !== 0) {
        return null;
    }
    if (($q['layers'] ?? '') !== (string) ($service['layers'] ?? '')) {
        return null;
    }
    if (($q['styles'] ?? '') !== '' || !in_array($q['format'] ?? '', TILE_RELAY_FORMATS, true)) {
        return null;
    }
    $transparent = strtolower($q['transparent'] ?? 'false');
    if (!in_array($transparent, ['true', 'false'], true)) {
        return null;
    }
    $version = $q['version'] ?? '';
    if (!in_array($version, ['1.3.0', '1.1.1'], true)) {
        return null;
    }
    $sides = [];
    foreach (['width', 'height'] as $side) {
        $n = filter_var($q[$side] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => TILE_RELAY_MAX_SIDE]]);
        if ($n === false) {
            return null;
        }
        $sides[$side] = $n;
    }
    $crsKey = $version === '1.3.0' ? 'crs' : 'srs';
    if (($q[$crsKey] ?? '') !== 'EPSG:3857') {
        return null;
    }
    $box = explode(',', $q['bbox'] ?? '');
    if (count($box) !== 4) {
        return null;
    }
    $world = 20037508.35;
    foreach ($box as $i => $v) {
        if (!is_numeric($v) || abs((float) $v) > $world * 1.01) {
            return null;
        }
        $box[$i] = (string) (float) $v;
    }

    return http_build_query([
        'service'     => 'WMS',
        'request'     => 'GetMap',
        'layers'      => $q['layers'],
        'styles'      => '',
        'format'      => $q['format'],
        'transparent' => $transparent,
        'version'     => $version,
        'width'       => $sides['width'],
        'height'      => $sides['height'],
        $crsKey       => 'EPSG:3857',
        'bbox'        => implode(',', $box),
    ]);
}

/**
 * Ask the state. A WMS reports its errors as XML with status 200, so only
 * an answer that IS a picture counts.
 *
 * @return array{status: int, type: string, body: string}
 */
function tile_relay_fetch(string $url): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; YASAIMAPP)',
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    if ($body === false) {
        return ['status' => 504, 'type' => '', 'body' => ''];
    }
    if ($status !== 200 || !str_starts_with(strtolower($type), 'image/')) {
        return ['status' => 502, 'type' => '', 'body' => ''];
    }
    return ['status' => 200, 'type' => $type, 'body' => (string) $body];
}
