<?php
/**
 * S3-compatible storage — the fourth storage, and the first without OAuth
 * (28.08.2026: „dann macht den S3 noch").
 *
 * WHO THIS IS FOR. Not Amazon in particular: Hetzner, Wasabi, Backblaze
 * and a dozen others speak the same interface, and for the user that is
 * exactly the point — an S3 bucket is the cheapest honest storage there
 * is. The card therefore says "S3-Speicher", never "AWS".
 *
 * KEYS INSTEAD OF CONSENT, and that changes who holds what. The OAuth
 * three need an app the ADMINISTRATOR registers once; here every user
 * brings their own four values — endpoint, region, bucket, key pair — and
 * no vault entry exists at all. It also changes what the fence is: with
 * OAuth the scope draws it, here the KEY does. A key scoped to one bucket
 * can touch one bucket; whoever pastes in an account-wide key has handed
 * over more, and the connect form says so out loud, because no consent
 * screen will.
 *
 * THE SIGNATURE IS THE WHOLE DIFFICULTY. S3 authenticates every request
 * with AWS Signature Version 4: a canonical rewrite of the request,
 * hashed, signed with a key derived from the secret through four HMAC
 * rounds. Nothing about it is hard, everything about it is exact — one
 * mis-encoded space and the provider answers 403 SignatureDoesNotMatch,
 * naming neither the space nor the place. That is why the derivation is
 * held against Amazon's published test vector (tests/cloud.php) and why
 * the canonical encoding lives in ONE function each for path and query.
 *
 * PATH-STYLE ADDRESSING (endpoint/bucket/key), not virtual-host
 * (bucket.endpoint/key): it works with every compatible provider and
 * spares the user a wildcard-TLS question they cannot answer. The bucket
 * travels as the first path segment.
 *
 * FOLDERS DO NOT EXIST. S3 knows only keys; "Datensicherung/" is a prefix
 * some interfaces DRAW as a folder. storage_folder_ensure() is therefore
 * pure string work here — no request, nothing to create, nothing that
 * could already exist. The one place that costs something is listing,
 * and there the delimiter keeps sub-prefixes out.
 *
 * THE PICTURES CAME ON 26.09.2026 („alle Clouds richtig
 * nutzen"), and S3 is where they differ most: no thumbnails — the app lays
 * its own previews into „.vorschau" (s3_preview_key) —, no moves — a move
 * is a copy and a delete, and since the key IS the id, a moved picture
 * has a new one —, and no browser upload without an address signed in
 * advance (s3_presign_path), because the key never leaves the server.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

const S3_TIMEOUT = 20;

/**
 * The connection as it lies in the encrypted secret: everything S3 needs,
 * one JSON. Endpoint and bucket are not secrets, but they belong to the
 * key pair like a lock belongs to its key — split across columns, a
 * half-edited connection could sign requests for the wrong bucket.
 */
function s3_bundle_pack(string $endpoint, string $region, string $bucket,
                        string $keyId, string $secret): string
{
    return json_encode([
        'endpoint' => $endpoint,
        'region'   => $region,
        'bucket'   => $bucket,
        'key_id'   => $keyId,
        'secret'   => $secret,
    ], JSON_UNESCAPED_SLASHES);
}

/**
 * @return array{endpoint: string, region: string, bucket: string,
 *               key_id: string, secret: string}|null
 */
function s3_bundle_parse(?string $stored): ?array
{
    $d = json_decode((string) $stored, true);
    if (!is_array($d)) {
        return null;
    }
    foreach (['endpoint', 'region', 'bucket', 'key_id', 'secret'] as $piece) {
        if (($d[$piece] ?? '') === '') {
            return null;
        }
    }
    return [
        'endpoint' => (string) $d['endpoint'],
        'region'   => (string) $d['region'],
        'bucket'   => (string) $d['bucket'],
        'key_id'   => (string) $d['key_id'],
        'secret'   => (string) $d['secret'],
    ];
}

/**
 * A HOST ON THE PUBLIC INTERNET, OR NO REQUEST (security review,
 * 23.09.2026). Any user may connect an S3 store and type any endpoint —
 * and the server then sends requests there and hands back the status and
 * the error text. With 127.0.0.1, 10.x or the cloud metadata address
 * 169.254.169.254 that made our server a scout inside the host's own
 * network. Now: port 443 only (or none), and every address the name
 * resolves to must be public — not loopback, private, link-local or
 * reserved. What is refused is said plainly; it is never a real store.
 *
 * @throws RuntimeException
 */
function s3_host_guard(string $host): void
{
    $name = $host;
    if (preg_match('~^\[?([0-9a-f:.]+)\]?(?::(\d+))?$~i', $host, $m) === 1 && str_contains($m[1], ':') && !isset($m[2])) {
        $name = $m[1];                       // a bare IPv6 literal
    } elseif (preg_match('~^(.+?):(\d+)$~', $host, $m) === 1) {
        if ((int) $m[2] !== 443) {
            throw new RuntimeException(t('Der S3-Endpunkt muss über HTTPS auf Port 443 erreichbar sein.'));
        }
        $name = $m[1];
    }
    $name = trim($name, '[]');
    $ips = filter_var($name, FILTER_VALIDATE_IP) !== false ? [$name] : [];
    if ($ips === []) {
        $ips = gethostbynamel($name) ?: [];
        foreach (@dns_get_record($name, DNS_AAAA) ?: [] as $r) {
            if (isset($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
    }
    if ($ips === []) {
        throw new RuntimeException(t('Den S3-Endpunkt gibt es nicht — der Name löst sich nicht auf.'));
    }
    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new RuntimeException(t('Dieser S3-Endpunkt liegt nicht im öffentlichen Internet und wird nicht angesprochen.'));
        }
    }
}

/** The endpoint as the user typed it, reduced to a bare host name. */
function s3_endpoint_clean(string $raw): string
{
    $host = mb_strtolower(trim($raw));
    // "https://s3.example.com/" pasted whole means the host inside it.
    $host = preg_replace('~^[a-z]+://~', '', $host) ?? $host;
    return trim(explode('/', $host)[0], " \t.");
}

/**
 * A key, canonically encoded for the URI — each segment percent-encoded,
 * the slash kept as the separator.
 *
 * THIS IS WHERE THE SPACE LIVES OR DIES: "Funde 2026-08.csv" must go into
 * the canonical request as %20, and the SAME spelling must stand in the
 * request line — two encodings, one 403 that names nothing. One function,
 * both callers.
 */
function s3_encode_key(string $key): string
{
    return implode('/', array_map('rawurlencode', explode('/', $key)));
}

/**
 * The SigV4 signing key: four HMAC rounds from the secret.
 *
 * $service is a parameter for one reason only: Amazon's published test
 * vector derives for "iam", and the test in tests/cloud.php replays it
 * verbatim — a derivation that cannot reproduce the documented example is
 * wrong in a way no live request would ever explain.
 */
function s3_signing_key(string $secret, string $ymd, string $region, string $service = 's3'): string
{
    $k = hash_hmac('sha256', $ymd, 'AWS4' . $secret, true);
    $k = hash_hmac('sha256', $region, $k, true);
    $k = hash_hmac('sha256', $service, $k, true);
    return hash_hmac('sha256', 'aws4_request', $k, true);
}

/**
 * One signed request to the bucket.
 *
 * EXTRA HEADERS ARE SIGNED TOO (26.09.2026, for the pictures): the
 * x-amz-copy-source of a copy, the Content-Type a picture must be served
 * back with. S3 insists that every x-amz-* header sent is signed as well,
 * and signing the type costs nothing. Without extras the canonical form is
 * exactly the one before — host, x-amz-content-sha256, x-amz-date.
 *
 * @param array<string,string> $query   parameter => value, UNencoded
 * @param array<string,string> $headers name => value, sent and signed
 * @return array{status: int, body: string, headers: array<string,string>}
 */
function s3_request(array $bundle, string $method, string $key,
                    array $query = [], string $body = '', array $headers = []): array
{
    $now = time();
    $stamp = gmdate('Ymd\THis\Z', $now);
    $ymd   = gmdate('Ymd', $now);

    $path = '/' . rawurlencode($bundle['bucket'])
          . ($key === '' ? '/' : '/' . s3_encode_key($key));

    // The canonical query: sorted by name, everything percent-encoded —
    // and the request line carries the IDENTICAL string, so the two
    // cannot drift apart.
    ksort($query);
    $canonicalQuery = implode('&', array_map(
        static fn(string $n, string $v): string => rawurlencode($n) . '=' . rawurlencode($v),
        array_keys($query), array_values($query)));

    $payloadHash = hash('sha256', $body);
    $host = $bundle['endpoint'];

    $signed = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $stamp];
    foreach ($headers as $name => $value) {
        $signed[strtolower((string) $name)] = trim((string) $value);
    }
    ksort($signed);
    $canonicalHeaders = '';
    foreach ($signed as $name => $value) {
        $canonicalHeaders .= "$name:$value\n";
    }
    $signedHeaders = implode(';', array_keys($signed));

    $canonicalRequest = "$method\n$path\n$canonicalQuery\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

    $scope = "$ymd/{$bundle['region']}/s3/aws4_request";
    $toSign = "AWS4-HMAC-SHA256\n$stamp\n$scope\n" . hash('sha256', $canonicalRequest);

    $signature = hash_hmac('sha256', $toSign,
        s3_signing_key($bundle['secret'], $ymd, $bundle['region']));

    $authorization = 'AWS4-HMAC-SHA256 Credential=' . $bundle['key_id'] . '/' . $scope
                   . ', SignedHeaders=' . $signedHeaders
                   . ', Signature=' . $signature;

    $url = 'https://' . $host . $path . ($canonicalQuery === '' ? '' : '?' . $canonicalQuery);
    s3_host_guard($host);

    $sent = "Authorization: $authorization\r\n";
    foreach ($signed as $name => $value) {
        if ($name !== 'host') {
            $sent .= "$name: $value\r\n";
        }
    }
    if ($body !== '' && !isset($signed['content-type'])) {
        $sent .= "Content-Type: application/octet-stream\r\n";
    }

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            // No redirects: an answer „302 → http://10.0.0.1/" would carry
            // the request into the host's own network (s3_host_guard).
            'follow_location' => 0,
            'method'        => $method,
            'header'        => $sent,
            'content'       => $body,
            'timeout'       => S3_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException(t('Der S3-Speicher antwortet nicht. Ist der Endpunkt richtig?'));
    }

    $status  = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $line, $m)) {
            $status  = (int) $m[1];
            $headers = [];   // a redirect's headers are not the answer's
            continue;
        }
        $split = strpos($line, ':');
        if ($split !== false) {
            $headers[mb_strtolower(substr($line, 0, $split))] = trim(substr($line, $split + 1));
        }
    }

    return ['status' => $status, 'body' => $response, 'headers' => $headers];
}

/**
 * Throw the provider's own words.
 *
 * S3 errors come as XML with a Code and a Message; both go into the
 * exception — "SignatureDoesNotMatch" is findable in every provider's
 * documentation, our own sentence would not be.
 */
function s3_fail(array $answer): never
{
    $code = '';
    $text = '';
    $xml = @simplexml_load_string($answer['body']);
    if ($xml !== false) {
        $code = (string) ($xml->Code ?? '');
        $text = (string) ($xml->Message ?? '');
    }
    throw new RuntimeException(t('Der S3-Speicher lehnt ab (%s): %s',
        $code !== '' ? $code : 'HTTP ' . $answer['status'],
        $text !== '' ? $text : t('ohne Angabe')));
}

/**
 * Do the four values open a bucket? Asked BEFORE storing them.
 *
 * One listing, one object at most: it exercises endpoint, region (the
 * signature scope carries it), bucket and both key halves in a single
 * request — every wrong value fails it, and the user stands in front of
 * the form where fixing is one edit, not in front of a backup at three in
 * the morning.
 */
function s3_check(array $bundle): void
{
    $answer = s3_request($bundle, 'GET', '', ['list-type' => '2', 'max-keys' => '1']);
    if ($answer['status'] !== 200) {
        s3_fail($answer);
    }
}

/**
 * The FILES lying directly under a prefix, by name — the S3 twin of
 * pcloud_folder_files(), for the backup's honesty about what is really
 * still up there.
 *
 * THE DELIMITER KEEPS SUB-PREFIXES OUT, like everywhere the twins skip
 * folders. PAGED TO THE END via the continuation token, for the reason
 * the other twins spell out: a loop that stops at the first thousand
 * would one day silently re-upload everything behind them, every night.
 *
 * @return array<string, array{fileid: string, size: int, modified: string}>
 */
function s3_folder_files(array $bundle, string $prefix): array
{
    $out = [];
    $continue = null;
    do {
        $query = [
            'list-type' => '2',
            'prefix'    => $prefix,
            'delimiter' => '/',
            'max-keys'  => '1000',
        ];
        if ($continue !== null) {
            $query['continuation-token'] = $continue;
        }
        $answer = s3_request($bundle, 'GET', '', $query);
        if ($answer['status'] !== 200) {
            s3_fail($answer);
        }
        $xml = @simplexml_load_string($answer['body']);
        if ($xml === false) {
            throw new RuntimeException(t('Der S3-Speicher antwortet unverständlich.'));
        }

        foreach ($xml->Contents as $e) {
            $key  = (string) $e->Key;
            $name = substr($key, strlen($prefix));
            // The prefix itself can exist as a zero-byte object — some
            // interfaces lay one down when "creating a folder". It is
            // furniture, not a file of ours.
            if ($name === '' || str_contains($name, '/')) {
                continue;
            }
            $out[$name] = [
                'fileid'   => $key,
                'size'     => (int) $e->Size,
                'modified' => (string) $e->LastModified,
            ];
        }

        $continue = ((string) ($xml->IsTruncated ?? '')) === 'true'
            ? (string) $xml->NextContinuationToken
            : null;
    } while ($continue !== null && $continue !== '');

    return $out;
}

/** Is there an object under this key? 404 is an answer, not an error. */
function s3_object_exists(array $bundle, string $key): bool
{
    $answer = s3_request($bundle, 'HEAD', $key);
    if ($answer['status'] === 200) {
        return true;
    }
    if ($answer['status'] === 404) {
        return false;
    }
    s3_fail($answer);
}

/**
 * Put bytes under a prefix — replace in place, or keep both.
 *
 * A PUT ALWAYS OVERWRITES in S3; replacing is not an option here, it is
 * the physics. $replace=false therefore has to do what pCloud's
 * renameifexists does for us: look first, and count the name up until it
 * is free — "Funde.csv", "Funde (2).csv". The backup never takes that
 * path; it exists so the parameter keeps one meaning at all four
 * providers instead of a footnote saying "except S3".
 *
 * THE SIZE IS CHECKED THROUGH THE ETAG: for a single PUT it is the MD5 of
 * the body at every compatible provider, and comparing it verifies the
 * bytes, not just their count. Where a provider answers with something
 * else (encrypted buckets do), a HEAD asks for the stored length instead
 * — slower, but only on that kind of bucket.
 *
 * @return array{fileid: string, name: string, size: int}
 */
function s3_upload_bytes(array $bundle, string $prefix, string $bytes,
                         string $name, ?int $mtime = null, bool $replace = false,
                         ?string $type = null): array
{
    if ($bytes === '') {
        throw new RuntimeException(t('Nichts zu übertragen: %s', $name));
    }
    // $mtime is accepted for the seam's sake and dropped: S3 lets nobody
    // write LastModified, the store stamps it itself.

    if (!$replace) {
        $name = s3_free_name($bundle, $prefix, $name);
    }

    $key = $prefix . $name;
    /* A PICTURE KEEPS ITS TYPE: S3 hands back on reading exactly the type
       given on writing, and the delivery serves nothing but image/* inline
       (cloud_deliver) — an octet-stream picture would arrive as a broken
       frame. The backup's JSON goes up as before. */
    $answer = s3_request($bundle, 'PUT', $key, [], $bytes,
        $type !== null ? ['content-type' => $type] : []);
    if ($answer['status'] !== 200) {
        s3_fail($answer);
    }

    $etag = trim((string) ($answer['headers']['etag'] ?? ''), '"');
    if ($etag !== md5($bytes)) {
        $head = s3_request($bundle, 'HEAD', $key);
        $stored = (int) ($head['headers']['content-length'] ?? -1);
        if ($head['status'] !== 200 || $stored !== strlen($bytes)) {
            throw new RuntimeException(t(
                'Der S3-Speicher hat %d von %d Bytes angenommen.',
                max(0, $stored), strlen($bytes)));
        }
    }

    return ['fileid' => $key, 'name' => $name, 'size' => strlen($bytes)];
}

/* ---------------------------------------------------------------------------
 * The picture family (26.09.2026) — what the storage seam asks of every
 * provider: look a file up, delete it, move it, list the folders under a
 * folder, and hand out an address to fetch it by.
 * ------------------------------------------------------------------------- */

/**
 * Name, size and parent of one object — null when there is none.
 *
 * @return array{name: string, size: int, parent: string, type: string}|null
 */
function s3_head(array $bundle, string $key): ?array
{
    $answer = s3_request($bundle, 'HEAD', $key);
    if ($answer['status'] === 404) {
        return null;
    }
    if ($answer['status'] !== 200) {
        s3_fail($answer);
    }
    $slash = strrpos($key, '/');
    return [
        'name'   => $slash === false ? $key : substr($key, $slash + 1),
        'size'   => (int) ($answer['headers']['content-length'] ?? 0),
        'parent' => $slash === false ? '' : substr($key, 0, $slash + 1),
        'type'   => (string) ($answer['headers']['content-type'] ?? ''),
    ];
}

/**
 * Delete one object. GONE FOR GOOD — S3 has no trash unless the bucket
 * keeps versions; the delete question says so (map.js). An absent object
 * counts as deleted: S3 itself answers 204 either way.
 */
function s3_delete(array $bundle, string $key): void
{
    $answer = s3_request($bundle, 'DELETE', $key);
    if (!in_array($answer['status'], [200, 204, 404], true)) {
        s3_fail($answer);
    }
}

/**
 * Copy one object inside the bucket — the first half of a move, and done
 * by the store itself: not a byte passes through this server.
 *
 * A 200 CAN STILL BE A FAILURE: a copy that breaks off midway answers 200
 * with an <Error> in the body, which Amazon documents and every
 * compatible store copies.
 */
function s3_copy(array $bundle, string $from, string $to): void
{
    $answer = s3_request($bundle, 'PUT', $to, [], '', [
        'x-amz-copy-source'        => '/' . rawurlencode($bundle['bucket']) . '/' . s3_encode_key($from),
        'x-amz-metadata-directive' => 'COPY',
    ]);
    if ($answer['status'] !== 200 || str_contains($answer['body'], '<Error>')) {
        s3_fail($answer);
    }
}

/**
 * Move = copy + delete. THE KEY IS THE ID, so a moved picture has a new
 * one, and the caller writes it back wherever the old one stood — unlike
 * pCloud, Drive and OneDrive, where an id survives any move.
 */
function s3_move(array $bundle, string $from, string $to): string
{
    if ($from !== $to) {
        s3_copy($bundle, $from, $to);
        s3_delete($bundle, $from);
    }
    return $to;
}

/**
 * The sub-prefixes directly under a prefix — the "folders" an interface
 * draws —, by name. Paged to the end, like the file listing.
 *
 * @return array<string, string> name => prefix
 */
function s3_subprefixes(array $bundle, string $prefix): array
{
    $out = [];
    $continue = null;
    do {
        $query = ['list-type' => '2', 'prefix' => $prefix, 'delimiter' => '/', 'max-keys' => '1000'];
        if ($continue !== null) {
            $query['continuation-token'] = $continue;
        }
        $answer = s3_request($bundle, 'GET', '', $query);
        if ($answer['status'] !== 200) {
            s3_fail($answer);
        }
        $xml = @simplexml_load_string($answer['body']);
        if ($xml === false) {
            throw new RuntimeException(t('Der S3-Speicher antwortet unverständlich.'));
        }
        foreach ($xml->CommonPrefixes as $p) {
            $sub  = (string) $p->Prefix;
            $name = rtrim(substr($sub, strlen($prefix)), '/');
            if ($name !== '') {
                $out[$name] = $sub;
            }
        }
        $continue = ((string) ($xml->IsTruncated ?? '')) === 'true'
            ? (string) $xml->NextContinuationToken
            : null;
    } while ($continue !== null && $continue !== '');

    return $out;
}

/**
 * The query-string half of SigV4: an address that carries its own
 * signature and needs no header — for a picture fetched by the server
 * (delivery) and, later, one the browser PUTs straight into the bucket.
 *
 * HELD AGAINST AMAZON'S PUBLISHED EXAMPLE (tests/cloud.php), host and path
 * as given — the example is virtual-host style, our requests are
 * path-style; the arithmetic is the same.
 *
 * @param array<string,string> $extraQuery further parameters, signed along
 * @return string path and query, signature last — prefix it with https://host
 */
function s3_presign_path(string $host, string $path, string $keyId, string $secret,
                         string $region, string $method, int $expires, int $now,
                         array $extraQuery = []): string
{
    $stamp = gmdate('Ymd\THis\Z', $now);
    $ymd   = gmdate('Ymd', $now);
    $scope = "$ymd/$region/s3/aws4_request";

    $query = $extraQuery + [
        'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential'    => "$keyId/$scope",
        'X-Amz-Date'          => $stamp,
        'X-Amz-Expires'       => (string) $expires,
        'X-Amz-SignedHeaders' => 'host',
    ];
    ksort($query);
    $canonicalQuery = implode('&', array_map(
        static fn(string $n, string $v): string => rawurlencode($n) . '=' . rawurlencode($v),
        array_keys($query), array_values($query)));

    $canonicalRequest = "$method\n$path\n$canonicalQuery\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
    $toSign = "AWS4-HMAC-SHA256\n$stamp\n$scope\n" . hash('sha256', $canonicalRequest);
    $signature = hash_hmac('sha256', $toSign, s3_signing_key($secret, $ymd, $region));

    return $path . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
}

/** A presigned address for one object of the bucket, path-style like every request here. */
function s3_presigned(array $bundle, string $method, string $key, int $expires = 300,
                      ?int $now = null): string
{
    $path = '/' . rawurlencode($bundle['bucket']) . '/' . s3_encode_key($key);
    return 'https://' . $bundle['endpoint'] . s3_presign_path($bundle['endpoint'], $path,
        $bundle['key_id'], $bundle['secret'], $bundle['region'], $method, $expires, $now ?? time());
}

/**
 * WHERE A PICTURE'S PREVIEW LIES — S3 draws no thumbnails, so the app lays
 * its own beside the pictures, under its folder in „.vorschau/<edge>/",
 * mirroring the picture's path (26.09.2026: they may be visible
 * there). Two edges exist, the photo window's: 320 for tiles, 2048 for
 * the large view.
 */
function s3_preview_key(string $home, string $key, int $edge): string
{
    $rest = $home !== '' && str_starts_with($key, $home) ? substr($key, strlen($home)) : $key;
    return $home . '.vorschau/' . ($edge <= 320 ? 320 : 2048) . '/' . $rest;
}

/**
 * A name that is free under the prefix — „DSC_0001.jpg", then
 * „DSC_0001 (2).jpg": S3 overwrites without asking, so this does what
 * pCloud's renameifexists does for us. And a key stays within the 512
 * characters the id columns hold (0138): an overlong name loses the end of
 * its stem, never its extension.
 */
function s3_free_name(array $bundle, string $prefix, string $name): string
{
    $dot  = strrpos($name, '.');
    $stem = $dot === false || $dot === 0 ? $name : substr($name, 0, $dot);
    $tail = $dot === false || $dot === 0 ? '' : substr($name, $dot);
    $room = 500 - mb_strlen($prefix) - mb_strlen($tail);
    if ($room < 8) {
        throw new RuntimeException(t('Der Ordnerpfad ist zu lang für einen S3-Schlüssel.'));
    }
    if (mb_strlen($stem) > $room) {
        $stem = rtrim(mb_substr($stem, 0, $room));
    }
    $name = $stem . $tail;
    if (!s3_object_exists($bundle, $prefix . $name)) {
        return $name;
    }
    for ($n = 2; ; $n++) {
        $candidate = "$stem ($n)$tail";
        if (!s3_object_exists($bundle, $prefix . $candidate)) {
            return $candidate;
        }
    }
}

/**
 * Where the BROWSER puts one file (26.09.2026, step three of the picture
 * plan): the key it will have, and an address signed in advance for an
 * hour — the key pair never leaves the server.
 *
 * @return array{key: string, url: string}
 */
function s3_upload_target(array $bundle, string $prefix, string $name): array
{
    s3_host_guard($bundle['endpoint']);
    $key = $prefix . s3_free_name($bundle, $prefix, $name);
    return ['key' => $key, 'url' => s3_presigned($bundle, 'PUT', $key, 3600)];
}

/**
 * LET THE BROWSER PUT INTO THE BUCKET — a CORS rule for our origin
 * (26.09.2026: set by the app itself when the key allows,
 * otherwise explained). Without it the browser refuses the PUT before it
 * ever leaves the page.
 *
 * ADDED BESIDE THE USER'S OWN RULES, NEVER INSTEAD OF THEM: S3 keeps one
 * CORS document per bucket, and a PUT replaces it whole — so the present
 * one is read first, left alone when it already lets us in, and extended
 * otherwise. PutBucketCors insists on a Content-MD5.
 */
function s3_cors_allow(array $bundle, string $origin): void
{
    $answer = s3_request($bundle, 'GET', '', ['cors' => '']);
    $present = '';
    if ($answer['status'] === 200) {
        if (str_contains($answer['body'], '<AllowedOrigin>' . htmlspecialchars($origin, ENT_XML1) . '</AllowedOrigin>')) {
            return;
        }
        $present = $answer['body'];
    } elseif ($answer['status'] !== 404) {
        s3_fail($answer);
    }

    $rule = '<CORSRule><AllowedOrigin>' . htmlspecialchars($origin, ENT_XML1) . '</AllowedOrigin>'
          . '<AllowedMethod>PUT</AllowedMethod><AllowedMethod>GET</AllowedMethod><AllowedMethod>HEAD</AllowedMethod>'
          . '<AllowedHeader>*</AllowedHeader><ExposeHeader>ETag</ExposeHeader>'
          . '<MaxAgeSeconds>3600</MaxAgeSeconds></CORSRule>';
    $xml = $present !== '' && str_contains($present, '</CORSConfiguration>')
        ? str_replace('</CORSConfiguration>', $rule . '</CORSConfiguration>', $present)
        : '<?xml version="1.0" encoding="UTF-8"?>'
          . '<CORSConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/">' . $rule . '</CORSConfiguration>';

    $put = s3_request($bundle, 'PUT', '', ['cors' => ''], $xml, [
        'content-md5'  => base64_encode(md5($xml, true)),
        'content-type' => 'application/xml',
    ]);
    if ($put['status'] !== 200) {
        s3_fail($put);
    }
}

