<?php
/**
 * The vector map's offline data out of the maintainer's pCloud (30.09.2026).
 *
 * THE DECISION. The OpenStreetMap tiles for offline areas — Europe as one
 * PMTiles file, cut out of OpenFreeMap's planet (admin/europe_extract.py) —
 * lie in his pCloud, which has room (4 TB) where the webspace has 50 GB.
 * An area download reads from it only the pieces its forest needs: the
 * directory, then one tile after the other (assets/js/pmtiles.js).
 *
 * WHY THE SERVER READS AND NOT THE PHONE, both measured 30.09.2026 with a
 * share link of his: pCloud's file servers send no CORS permission at all,
 * so a web page may not read a pCloud file, whole or in part. And every
 * download address pCloud hands out is bound to the address that asked
 * (measured 18.08.2026 with the photos, see pcloud_deliver) — made by the
 * server, it serves only the server. So the server asks, reads the piece,
 * and hands it on: api/v1/offline_map.php. It carries only what an area
 * download needs, never the map one looks at online (that stays with
 * OpenFreeMap).
 *
 * WHICH FILE: config.php's 'offline_map' => ['europe' => '<share link>'],
 * a pCloud share link („Link teilen"). Not a password — the file is public
 * map data — but traffic on his account, so it stays in config.php and out
 * of the repository.
 */

declare(strict_types=1);

/** The longest piece one request hands on: a directory, a few tiles — never a file. */
const OFFLINE_MAP_PIECE_MAX = 8 * 1024 * 1024;

/** How long before its own end a remembered download address is renewed. */
const OFFLINE_MAP_RENEW_BEFORE = 600;

/** The share link configured for a file key, or null. */
function offline_map_share(string $file): ?string
{
    global $config;
    $links = (array) ($config['offline_map'] ?? []);
    $link = trim((string) ($links[$file] ?? ''));
    return $link === '' ? null : $link;
}

/** The file keys this installation has — what the map may offer. @return string[] */
function offline_map_files(): array
{
    global $config;
    return array_keys(array_filter((array) ($config['offline_map'] ?? []),
        static fn ($link) => trim((string) $link) !== ''));
}

/**
 * Code and API host of a pCloud share link. e.pcloud.link lies in the
 * European data centre and answers at eapi.pcloud.com; u.pcloud.link at
 * api.pcloud.com — asked at the wrong one, a code is unknown.
 *
 * @return array{code: string, api: string}|null
 */
function offline_map_publink(string $link): ?array
{
    $parts = parse_url($link);
    if (!is_array($parts)) {
        return null;
    }
    parse_str((string) ($parts['query'] ?? ''), $query);
    $code = (string) ($query['code'] ?? '');
    if (!preg_match('/^[A-Za-z0-9]{10,80}$/', $code)) {
        return null;
    }
    $host = (string) ($parts['host'] ?? '');
    return ['code' => $code, 'api' => str_starts_with($host, 'e.') ? 'eapi.pcloud.com' : 'api.pcloud.com'];
}

/**
 * A download address for the shared file — remembered until shortly before
 * pCloud lets it lapse (a few hours), because asking costs a round trip
 * and an area download asks for a thousand pieces.
 *
 * NO REFERER. getpublinkdownload refuses any request that names a page it
 * came from („Invalid link referer", 7010) — measured from the browser;
 * PHP's HTTP client sends none.
 */
function offline_map_file_url(string $link, bool $renew = false): string
{
    $publink = offline_map_publink($link);
    if ($publink === null) {
        throw new RuntimeException(t('Der Freigabe-Link ist kein pCloud-Link.'));
    }
    $memo = sys_get_temp_dir() . '/yasaimapp-offline-' . md5($publink['code']) . '.json';
    if (!$renew && is_file($memo)) {
        $m = json_decode((string) @file_get_contents($memo), true);
        if (is_array($m) && ($m['until'] ?? 0) > time() + OFFLINE_MAP_RENEW_BEFORE && is_string($m['url'] ?? null)) {
            return $m['url'];
        }
    }

    [$status, $body] = offline_map_http('https://' . $publink['api'] . '/getpublinkdownload?code=' . rawurlencode($publink['code']));
    $d = json_decode($body, true);
    if ($status !== 200 || !is_array($d) || ($d['result'] ?? -1) !== 0 || empty($d['hosts'])) {
        throw new ProviderRefused(
            sprintf('pCloud gibt die Datei nicht heraus: %s',
                is_array($d) ? (string) ($d['error'] ?? $d['result'] ?? '') : "HTTP $status"),
            t('pCloud gibt die Datei nicht heraus (%s).', is_array($d) ? (string) ($d['result'] ?? '?') : "HTTP $status")
        );
    }
    $url = 'https://' . $d['hosts'][0] . $d['path'];
    $until = strtotime((string) ($d['expires'] ?? '')) ?: time() + 3600;
    @file_put_contents($memo, json_encode(['url' => $url, 'until' => $until]), LOCK_EX);
    return $url;
}

/**
 * The bytes [offset, offset + length) of the file behind an address: one
 * range request.
 */
function offline_map_range(string $url, int $offset, int $length): string
{
    [$status, $body] = offline_map_http($url, $offset . '-' . ($offset + $length - 1));
    if ($status === 200 && $offset === 0) {
        // A server that ignores the range sends everything from the start.
        return substr($body, 0, $length);
    }
    if ($status !== 206) {
        throw new RuntimeException(t('Stück nicht lesbar: HTTP %d', $status));
    }
    return $body;
}

/**
 * One piece of a configured file, renewing the address once when pCloud
 * has let it lapse or moved it — the remembered one fails then, and a
 * fresh one is the whole cure.
 */
function offline_map_piece(string $file, int $offset, int $length): string
{
    $link = offline_map_share($file);
    if ($link === null) {
        throw new DomainException(t('Diese Offline-Datei gibt es hier nicht.'));
    }
    try {
        return offline_map_range(offline_map_file_url($link), $offset, $length);
    } catch (RuntimeException) {
        return offline_map_range(offline_map_file_url($link, true), $offset, $length);
    }
}

/**
 * GET with an optional byte range. @return array{0: int, 1: string}
 */
function offline_map_http(string $url, ?string $range = null): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; YASAIMAPP)',
    ]);
    if ($range !== null) {
        curl_setopt($curl, CURLOPT_RANGE, $range);
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body === false) {
        throw new RuntimeException(t('Keine Verbindung zu %s', (string) parse_url($url, PHP_URL_HOST)));
    }
    return [$status, (string) $body];
}
