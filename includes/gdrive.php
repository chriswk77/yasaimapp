<?php
/**
 * Google Drive — the second storage (27.08.2026: „lass uns mal
 * bei Cloud-Anbietern noch Google Drive dazu nehmen"; a friend of his is
 * on it).
 *
 * THE SAME HOUSE RULES AS cloud.php, and they are worth repeating because
 * this file talks to a different giant:
 *
 *   THE SECRET DOES NOT LEAVE THE SERVER. Access and refresh token live
 *   encrypted in map_cloud_accounts.secret and are read through
 *   cloud_secret() only.
 *
 *   THE SCOPE IS THE FENCE. drive.file means: the app sees and touches
 *   ONLY what it created itself. The user's own archive, their documents,
 *   their photos — invisible. That is the same property the "Private"
 *   pCloud app has, and it is the reason a stolen token here is an
 *   annoyance and not a catastrophe. Whoever widens the scope to
 *   drive (full access) should know they are removing exactly that.
 *
 * WHAT IS DIFFERENT FROM pCLOUD, and each difference shaped a function:
 *
 *   TOKENS EXPIRE. pCloud hands out one token for ever; Google hands out
 *   an access token for an hour and a refresh token to mint new ones.
 *   The secret is therefore a JSON bundle of both, expires_at carries the
 *   hour, and gdrive_access() is the one gate every caller goes through —
 *   it refreshes when the hour is nearly over and writes the new state
 *   back, so no second caller refreshes again.
 *
 *   THE REFRESH TOKEN COMES ONLY ONCE — at the first consent, and only
 *   because the consent URL says access_type=offline&prompt=consent.
 *   Whoever loses it cannot ask for it again quietly; the user has to
 *   consent anew. That is why the refresh flow, when Google answers
 *   invalid_grant, does not retry but says plainly: reconnect.
 *
 *   IDS ARE STRINGS. pCloud numbers its folders; Drive names them with
 *   opaque strings of ~33 characters. Everything here passes ids through
 *   untyped, and the backup's fileid column was widened for it
 *   (migration 0086).
 *
 *   THERE IS NO "ensure" CALL. Drive happily creates five folders of the
 *   same name side by side — createfolderifnotexists has no counterpart.
 *   gdrive_folder_ensure() therefore searches first and creates only on a
 *   miss; the race two devices could run stays harmless for the backup
 *   (one folder wins per sweep) and is written out there.
 *
 * THE PICTURES CAME ON 26.09.2026 („alle Clouds richtig
 * nutzen"): the family at the end of this file — look a file up, trash
 * it, move it, list sub-folders, fetch the media or Drive's own
 * thumbnail. The token never goes to the browser here: a browser upload
 * runs through a session the server opens (step three of that plan).
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

const GDRIVE_CONSENT = 'https://accounts.google.com/o/oauth2/v2/auth';
const GDRIVE_TOKEN   = 'https://oauth2.googleapis.com/token';
const GDRIVE_API     = 'https://www.googleapis.com/drive/v3';
const GDRIVE_UPLOAD  = 'https://www.googleapis.com/upload/drive/v3';
const GDRIVE_SCOPE   = 'https://www.googleapis.com/auth/drive.file';
const GDRIVE_TIMEOUT = 20;

/**
 * Where the user is sent to say yes.
 *
 * access_type=offline AND prompt=consent, and both are load-bearing:
 * without offline no refresh token comes at all, and without prompt Google
 * sends one only on the very first consent of this app — whoever
 * disconnects and reconnects would get a connection that dies after an
 * hour and looks healthy until then.
 */
function gdrive_consent_url(string $state): string
{
    $app = cloud_app('gdrive');
    if ($app === null) {
        throw new RuntimeException('Google Drive ist auf diesem Server nicht eingerichtet.');
    }

    return GDRIVE_CONSENT . '?' . http_build_query([
        'client_id'     => $app['client_id'],
        'redirect_uri'  => cloud_return_url(),
        'response_type' => 'code',
        'scope'         => GDRIVE_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ], '', '&', PHP_QUERY_RFC3986);
}

/**
 * One call to Google's token endpoint — exchange and refresh share it.
 *
 * ERRORS COME AS JSON WITH A STATUS ≠ 200, unlike pCloud's eternal 200.
 * 'error' is a keyword ("invalid_grant"), 'error_description' the
 * sentence; both go into the message, because the keyword is what one
 * finds in Google's documentation.
 */
function gdrive_token_call(array $parameters): array
{
    $response = @file_get_contents(GDRIVE_TOKEN, false, stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query($parameters),
            'timeout'       => GDRIVE_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException('Google antwortet nicht. Später noch einmal versuchen.');
    }
    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException('Google antwortet unverständlich.');
    }
    if (isset($d['error'])) {
        throw new RuntimeException(sprintf('Google lehnt ab (%s): %s',
            (string) $d['error'], (string) ($d['error_description'] ?? 'ohne Angabe')));
    }
    return $d;
}

/**
 * Trade the code for the token pair.
 *
 * @return array{access: string, refresh: string, expires_at: string}
 */
function gdrive_token_get(string $code): array
{
    $app = cloud_app('gdrive');
    if ($app === null) {
        throw new RuntimeException('Google Drive ist auf diesem Server nicht eingerichtet.');
    }

    $d = gdrive_token_call([
        'code'          => $code,
        'client_id'     => $app['client_id'],
        'client_secret' => $app['client_secret'],
        'redirect_uri'  => cloud_return_url(),
        'grant_type'    => 'authorization_code',
    ]);

    $access  = (string) ($d['access_token'] ?? '');
    $refresh = (string) ($d['refresh_token'] ?? '');
    if ($access === '') {
        throw new RuntimeException('Google nennt kein Zugriffstoken.');
    }
    /* NO REFRESH TOKEN IS A REFUSAL, not a detail. It happens when the
       consent ran without prompt=consent (an old link, a modified URL) —
       the connection would then die within the hour. Better to fail here,
       where reconnecting is one click, than at three in the morning in
       the backup. */
    if ($refresh === '') {
        throw new RuntimeException('Google hat kein Dauer-Token ausgestellt.'
            . ' Bitte noch einmal verbinden und der Abfrage erneut zustimmen.');
    }

    return [
        'access'     => $access,
        'refresh'    => $refresh,
        'expires_at' => cloud_token_expiry((int) ($d['expires_in'] ?? 3600)),
    ];
}

/* Token bundle and expiry moved up into cloud.php (cloud_tokens_pack,
   cloud_tokens_parse, cloud_token_expiry) the day OneDrive arrived and
   would have needed identical copies — the pair that drifts apart. */

/**
 * A valid access token — THE gate for every Drive call.
 *
 * Refreshes when the stored hour is (nearly) over and writes the new
 * state straight back, encrypted, so the next caller finds it fresh.
 * NULL means: no usable connection — not connected, or the refresh token
 * was revoked; the caller treats it like "no cloud", the same ordinary
 * answer photo_to_cloud() knows.
 */
function gdrive_access(PDO $pdo, int $userId): ?string
{
    // The retiring row too — a move out of this cloud still reads with it.
    $connection = cloud_connections($pdo, $userId, true)['gdrive'] ?? null;
    $bundle     = cloud_tokens_parse(cloud_secret($pdo, $userId, 'gdrive'));
    if ($connection === null || $bundle === null) {
        return null;
    }

    $expiry = (string) ($connection['expires_at'] ?? '');
    if ($expiry !== '' && strtotime($expiry) > time()) {
        return $bundle['access'];
    }

    try {
        $app = cloud_app('gdrive');
        if ($app === null) {
            return null;
        }
        $d = gdrive_token_call([
            'refresh_token' => $bundle['refresh'],
            'client_id'     => $app['client_id'],
            'client_secret' => $app['client_secret'],
            'grant_type'    => 'refresh_token',
        ]);
    } catch (RuntimeException $e) {
        /* invalid_grant means the refresh token itself is dead — consent
           withdrawn, password changed, or the Google app still in test
           mode (there they die after seven days, see the steps in
           cloud_providers_content.php).
           Nothing here can heal that; only reconnecting can. The error
           lands in the log, the caller gets the ordinary "no cloud". */
        error_log('gdrive_access (' . $userId . '): ' . $e->getMessage());
        return null;
    }

    $access = (string) ($d['access_token'] ?? '');
    if ($access === '') {
        return null;
    }

    // Google usually does NOT send a new refresh token here — the old one
    // stays valid and stays stored. When one does come, it replaces.
    $refresh = (string) ($d['refresh_token'] ?? '') ?: $bundle['refresh'];

    $pdo->prepare(
        'UPDATE map_cloud_accounts SET secret = ?, expires_at = ?, updated_at = NOW()
          WHERE user_id = ? AND provider = "gdrive"'
    )->execute([
        cloud_encrypt(cloud_tokens_pack($access, $refresh)),
        cloud_token_expiry((int) ($d['expires_in'] ?? 3600)),
        $userId,
    ]);

    return $access;
}

/**
 * One call to the Drive API, JSON in and out.
 *
 * @param array|null $body JSON body for POST/PATCH, null for none
 */
function gdrive_call(string $access, string $method, string $url, ?array $body = null): array
{
    $headers = 'Authorization: Bearer ' . $access . "\r\n";
    if ($body !== null) {
        $headers .= "Content-Type: application/json; charset=utf-8\r\n";
    }

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => $headers,
            // An empty body is an OBJECT to Drive — json_encode([]) would
            // send a list, and a PATCH that only moves needs no fields.
            'content'       => $body === null ? '' : ($body === [] ? '{}' : json_encode($body, JSON_UNESCAPED_SLASHES)),
            'timeout'       => GDRIVE_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException('Google Drive antwortet nicht. Später noch einmal versuchen.');
    }
    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException('Google Drive antwortet unverständlich.');
    }
    if (isset($d['error'])) {
        $e = is_array($d['error']) ? $d['error'] : ['message' => (string) $d['error']];
        throw new RuntimeException(sprintf('Google Drive lehnt ab (%s): %s',
            (string) ($e['code'] ?? '?'), (string) ($e['message'] ?? 'ohne Angabe')));
    }
    return $d;
}

/**
 * Who the token belongs to, and how full the storage is.
 *
 * ASKED AT THE DRIVE, NOT AT AN ACCOUNT ENDPOINT: about?fields=user
 * answers within the drive.file scope — a userinfo call would need an
 * extra identity scope on the consent screen for the same email address.
 *
 * @return array{email: string, used: int, quota: ?int}  quota null = unbegrenzt
 */
function gdrive_userinfo(string $access): array
{
    $d = gdrive_call($access, 'GET',
        GDRIVE_API . '/about?fields=' . rawurlencode('user(emailAddress),storageQuota(limit,usage)'));

    $quota = $d['storageQuota'] ?? [];
    return [
        'email' => (string) ($d['user']['emailAddress'] ?? ''),
        'used'  => (int) ($quota['usage'] ?? 0),
        // No limit field means: the account has none.
        'quota' => isset($quota['limit']) ? (int) $quota['limit'] : null,
    ];
}

/** A value inside a Drive search: quotes and backslashes escaped. */
function gdrive_quote(string $value): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}

/**
 * Make sure a folder is there, and give back its id.
 *
 * SEARCH FIRST, CREATE ON A MISS — Drive has no createfolderifnotexists
 * and happily lays five folders of one name side by side. The search is
 * scoped to the parent and to folders, and the trash is left out: a
 * folder the user threw away is a decision, not a hiding place to reuse.
 *
 * @param string $parentId a folder id, or 'root' for the top
 * @return array{folderid: string, created: bool}
 */
function gdrive_folder_ensure(string $access, string $parentId, string $name): array
{
    $q = 'name = ' . gdrive_quote($name)
       . ' and ' . gdrive_quote($parentId) . ' in parents'
       . " and mimeType = 'application/vnd.google-apps.folder'"
       . ' and trashed = false';

    $d = gdrive_call($access, 'GET', GDRIVE_API . '/files?' . http_build_query([
        'q'      => $q,
        'fields' => 'files(id)',
        'pageSize' => 1,
    ]));

    $id = (string) ($d['files'][0]['id'] ?? '');
    if ($id !== '') {
        return ['folderid' => $id, 'created' => false];
    }

    $made = gdrive_call($access, 'POST', GDRIVE_API . '/files?fields=id', [
        'name'     => $name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => [$parentId],
    ]);
    $id = (string) ($made['id'] ?? '');
    if ($id === '') {
        throw new RuntimeException('Google Drive nennt für „' . $name . '" keine Ordnerkennung.');
    }
    return ['folderid' => $id, 'created' => true];
}

/**
 * A folder's name by its id — null when it is gone. Drive keeps a trashed
 * folder reachable by id, so the trash flag is asked too: a backup written
 * into the trash would be one nobody sees.
 */
function gdrive_folder_name(string $access, string $folderId): ?string
{
    try {
        $d = gdrive_call($access, 'GET', GDRIVE_API . '/files/' . rawurlencode($folderId)
            . '?fields=' . rawurlencode('name,trashed'));
    } catch (RuntimeException $e) {
        // gdrive_call puts the status in the message: „… (404): File not found".
        if (str_contains($e->getMessage(), '(404)')) {
            return null;
        }
        throw $e;
    }
    $name = (string) ($d['name'] ?? '');
    return (!empty($d['trashed']) || $name === '') ? null : $name;
}

/**
 * Rename a folder where it lies. Within drive.file, because the app made
 * it. Drive allows two folders of one name side by side, so there is no
 * conflict to report — the user sees both and knows which one is new.
 */
function gdrive_folder_rename(string $access, string $folderId, string $name): void
{
    gdrive_call($access, 'PATCH', GDRIVE_API . '/files/' . rawurlencode($folderId) . '?fields=id',
        ['name' => $name]);
}

/**
 * The FILES lying directly in a folder, by name — the Drive twin of
 * pcloud_folder_files(), and for the same caller: the backup's honesty
 * about what is really still up there.
 *
 * PAGED TO THE END. Drive answers a hundred entries at a time; a backup
 * folder holds one file per month plus the note, so the second page is
 * theory — but a loop that stops at the first page would, the day it is
 * wrong, re-upload everything past file hundred every night, silently.
 *
 * @return array<string, array{fileid: string, size: int, modified: string}>
 */
function gdrive_folder_files(string $access, string $folderId): array
{
    $out  = [];
    $page = null;
    do {
        $d = gdrive_call($access, 'GET', GDRIVE_API . '/files?' . http_build_query(array_filter([
            'q'         => gdrive_quote($folderId) . ' in parents and trashed = false'
                         . " and mimeType != 'application/vnd.google-apps.folder'",
            'fields'    => 'nextPageToken,files(id,name,size,modifiedTime)',
            'pageSize'  => 1000,
            'pageToken' => $page,
        ])));

        foreach ((array) ($d['files'] ?? []) as $e) {
            $name = (string) ($e['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[$name] = [
                'fileid'   => (string) ($e['id'] ?? ''),
                'size'     => (int) ($e['size'] ?? 0),
                'modified' => (string) ($e['modifiedTime'] ?? ''),
            ];
        }
        $page = (string) ($d['nextPageToken'] ?? '') ?: null;
    } while ($page !== null);

    return $out;
}

/**
 * Put bytes into a folder — create, or replace an existing file in place.
 *
 * REPLACING KEEPS THE ID: an update writes new content into the same
 * file, so anything pointing at it keeps working — and the folder never
 * collects "Funde 2026-08 (1).csv" twins, which is the failure mode
 * Drive's cheerful duplicate names would otherwise produce nightly.
 * $replace=false skips the search and simply creates, which is what a
 * caller wants whose names are already unique.
 *
 * MULTIPART/RELATED BY HAND, like the pCloud twin and for the same
 * reason: no cURL on this hosting. Metadata part first (JSON), content
 * part second — that order is the protocol, not a choice.
 *
 * THE SIZE IS CHECKED like at pCloud: the answer names what Drive
 * stored, and a truncated upload must not count as done.
 *
 * @return array{fileid: string, name: string, size: int}
 */
function gdrive_upload_bytes(
    string $access,
    string $folderId,
    string $bytes,
    string $name,
    ?int $mtime = null,
    bool $replace = false,
    ?string $type = null
): array {
    if ($bytes === '') {
        throw new RuntimeException('Nichts zu übertragen: ' . $name);
    }

    $existing = '';
    if ($replace) {
        $found = gdrive_call($access, 'GET', GDRIVE_API . '/files?' . http_build_query([
            'q'      => 'name = ' . gdrive_quote($name)
                      . ' and ' . gdrive_quote($folderId) . ' in parents and trashed = false',
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]));
        $existing = (string) ($found['files'][0]['id'] ?? '');
    }

    $metadata = ['name' => $name];
    if ($existing === '') {
        // parents only on creation — an update may not name them, Drive
        // answers 403 "fieldNotWritable" for it.
        $metadata['parents'] = [$folderId];
    }
    if ($mtime !== null) {
        $metadata['modifiedTime'] = gmdate('Y-m-d\TH:i:s\Z', $mtime);
    }
    /* A PICTURE SAYS IT IS ONE: without a type Drive files the bytes as
       an octet-stream, draws no thumbnail for them and hands them back
       untyped — and the delivery serves only image/* inline. */
    if ($type !== null) {
        $metadata['mimeType'] = $type;
    }

    $boundary = '----karte' . bin2hex(random_bytes(12));
    $body = "--$boundary\r\n"
          . "Content-Type: application/json; charset=utf-8\r\n\r\n"
          . json_encode($metadata, JSON_UNESCAPED_SLASHES) . "\r\n"
          . "--$boundary\r\n"
          . 'Content-Type: ' . ($type ?? 'application/octet-stream') . "\r\n\r\n"
          . $bytes . "\r\n--$boundary--\r\n";

    $url = $existing === ''
        ? GDRIVE_UPLOAD . '/files?uploadType=multipart&fields=id,name,size'
        : GDRIVE_UPLOAD . '/files/' . rawurlencode($existing) . '?uploadType=multipart&fields=id,name,size';

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => $existing === '' ? 'POST' : 'PATCH',
            'header'        => 'Authorization: Bearer ' . $access . "\r\n"
                             . "Content-Type: multipart/related; boundary=$boundary\r\n",
            'content'       => $body,
            'timeout'       => GDRIVE_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException('Google Drive nimmt die Datei gerade nicht an.');
    }
    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException('Google Drive antwortet unverständlich.');
    }
    if (isset($d['error'])) {
        $e = is_array($d['error']) ? $d['error'] : ['message' => (string) $d['error']];
        throw new RuntimeException(sprintf('Google Drive lehnt ab (%s): %s',
            (string) ($e['code'] ?? '?'), (string) ($e['message'] ?? 'ohne Angabe')));
    }

    $fileid = (string) ($d['id'] ?? '');
    if ($fileid === '') {
        throw new RuntimeException('Google Drive nennt keine Dateikennung.');
    }
    $size = (int) ($d['size'] ?? 0);
    if ($size !== strlen($bytes)) {
        throw new RuntimeException(sprintf(
            'Google Drive hat %d von %d Bytes angenommen.', $size, strlen($bytes)));
    }

    return ['fileid' => $fileid, 'name' => (string) ($d['name'] ?? $name), 'size' => $size];
}

/* ---------------------------------------------------------------------------
 * The picture family (26.09.2026) — what the storage seam asks of every
 * provider: look a file up, delete it, move it, list the folders under a
 * folder, and hand out an address to fetch it by.
 * ------------------------------------------------------------------------- */

/** The token as a header line — Drive's media and thumbnails want it too. */
function gdrive_auth_header(string $access): string
{
    return 'Authorization: Bearer ' . $access . "\r\n";
}

/**
 * Name, size and parent of one file — null when it is gone or in the
 * trash (Drive keeps a trashed file reachable by id; for us it is gone).
 *
 * @return array{name: string, size: int, parent: string, type: string}|null
 */
function gdrive_file_meta(string $access, string $fileId): ?array
{
    try {
        $d = gdrive_call($access, 'GET', GDRIVE_API . '/files/' . rawurlencode($fileId)
            . '?fields=' . rawurlencode('name,size,parents,trashed,mimeType'));
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(404)')) {
            return null;
        }
        throw $e;
    }
    if (!empty($d['trashed'])) {
        return null;
    }
    return [
        'name'   => (string) ($d['name'] ?? ''),
        'size'   => (int) ($d['size'] ?? 0),
        'parent' => (string) ($d['parents'][0] ?? ''),
        'type'   => (string) ($d['mimeType'] ?? ''),
    ];
}

/**
 * A folder's parent, its name and what lies in it (storage_folder_state).
 * Null when the folder is gone or in the trash.
 *
 * NOT gdrive_folder_files(): that one keys by name, and Drive allows two
 * files of one name side by side — a folder deciding „empty or not" must
 * see every entry.
 *
 * @return ?array{parent: string, name: string, entries: list<array{id: string, name: string, folder: bool}>}
 */
function gdrive_folder_state(string $access, string $folderId): ?array
{
    try {
        $d = gdrive_call($access, 'GET', GDRIVE_API . '/files/' . rawurlencode($folderId)
            . '?fields=' . rawurlencode('name,parents,trashed'));
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(404)')) {
            return null;
        }
        throw $e;
    }
    if (!empty($d['trashed'])) {
        return null;
    }
    $entries = [];
    $page = null;
    do {
        $l = gdrive_call($access, 'GET', GDRIVE_API . '/files?' . http_build_query(array_filter([
            'q'         => gdrive_quote($folderId) . ' in parents and trashed = false',
            'fields'    => 'nextPageToken,files(id,name,mimeType)',
            'pageSize'  => 1000,
            'pageToken' => $page,
        ])));
        foreach ((array) ($l['files'] ?? []) as $e) {
            $entries[] = [
                'id'     => (string) ($e['id'] ?? ''),
                'name'   => (string) ($e['name'] ?? ''),
                'folder' => ($e['mimeType'] ?? '') === 'application/vnd.google-apps.folder',
            ];
        }
        $page = (string) ($l['nextPageToken'] ?? '') ?: null;
    } while ($page !== null);

    return [
        'parent'  => (string) ($d['parents'][0] ?? ''),
        'name'    => (string) ($d['name'] ?? ''),
        'entries' => $entries,
    ];
}

/**
 * Into Drive's trash, not gone for good — the same promise pCloud's
 * deletefile keeps, and the one the delete question makes. A file that is
 * already gone counts as deleted.
 */
function gdrive_file_trash(string $access, string $fileId): void
{
    try {
        gdrive_call($access, 'PATCH', GDRIVE_API . '/files/' . rawurlencode($fileId) . '?fields=id',
            ['trashed' => true]);
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), '(404)')) {
            throw $e;
        }
    }
}

/**
 * Move a file or a folder into another folder — THE ID STAYS, as at
 * pCloud. Drive keeps parents as a list; the move takes the item out of
 * all it had and puts it into the one given.
 */
function gdrive_move(string $access, string $itemId, string $toFolderId): void
{
    $d = gdrive_call($access, 'GET', GDRIVE_API . '/files/' . rawurlencode($itemId) . '?fields=parents');
    $from = implode(',', array_map('strval', (array) ($d['parents'] ?? [])));
    gdrive_call($access, 'PATCH', GDRIVE_API . '/files/' . rawurlencode($itemId) . '?'
        . http_build_query(array_filter([
            'addParents'    => $toFolderId,
            'removeParents' => $from,
            'fields'        => 'id',
        ], static fn($v): bool => $v !== '')), []);
}

/**
 * Move a file and rename it in one PATCH (storage_file_place). Drive keeps
 * two files of one name side by side, so a taken name overwrites nothing
 * here — the caller looks first all the same, for a clean folder.
 */
function gdrive_place(string $access, string $itemId, string $toFolderId, string $name): void
{
    $d = gdrive_call($access, 'GET', GDRIVE_API . '/files/' . rawurlencode($itemId) . '?fields=parents');
    $from = array_map('strval', (array) ($d['parents'] ?? []));
    $query = ['fields' => 'id'];
    if (!in_array($toFolderId, $from, true)) {
        $query['addParents']    = $toFolderId;
        $query['removeParents'] = implode(',', $from);
    }
    gdrive_call($access, 'PATCH', GDRIVE_API . '/files/' . rawurlencode($itemId) . '?'
        . http_build_query(array_filter($query, static fn($v): bool => $v !== '')), ['name' => $name]);
}

/**
 * The FOLDERS lying directly in a folder, by name — for the letter count
 * of the genus tree. Paged to the end, like the file listing.
 *
 * @return array<string, string> name => id
 */
function gdrive_subfolders(string $access, string $folderId): array
{
    $out  = [];
    $page = null;
    do {
        $d = gdrive_call($access, 'GET', GDRIVE_API . '/files?' . http_build_query(array_filter([
            'q'         => gdrive_quote($folderId) . ' in parents and trashed = false'
                         . " and mimeType = 'application/vnd.google-apps.folder'",
            'fields'    => 'nextPageToken,files(id,name)',
            'pageSize'  => 1000,
            'pageToken' => $page,
        ])));
        foreach ((array) ($d['files'] ?? []) as $e) {
            $name = (string) ($e['name'] ?? '');
            if ($name !== '' && !isset($out[$name])) {
                $out[$name] = (string) ($e['id'] ?? '');
            }
        }
        $page = (string) ($d['nextPageToken'] ?? '') ?: null;
    } while ($page !== null);

    return $out;
}

/** Where the bytes of a file are fetched — the media address, with the token as a header. */
function gdrive_file_fetch(string $access, string $fileId): array
{
    return [
        'url'     => GDRIVE_API . '/files/' . rawurlencode($fileId) . '?alt=media',
        'headers' => gdrive_auth_header($access),
    ];
}

/**
 * Drive's own preview of a picture at the given edge — null when Drive
 * has none (a raw file it cannot read, a picture too fresh to have one).
 * The link is private like the file: fetched with the token.
 *
 * @return array{url: string, headers: string}|null
 */
function gdrive_thumb_fetch(string $access, string $fileId, int $edge): ?array
{
    $d = gdrive_call($access, 'GET', GDRIVE_API . '/files/' . rawurlencode($fileId) . '?fields=thumbnailLink');
    $link = (string) ($d['thumbnailLink'] ?? '');
    if ($link === '') {
        return null;
    }
    return ['url' => gdrive_thumb_sized($link, $edge), 'headers' => gdrive_auth_header($access)];
}

/**
 * Drive's thumbnail links end in „=s220" — that number is the edge, and
 * another number asks for another size. A link without it gets one.
 */
function gdrive_thumb_sized(string $link, int $edge): string
{
    return preg_match('~=s\d+$~', $link) === 1
        ? (string) preg_replace('~=s\d+$~', '=s' . $edge, $link)
        : $link . '=s' . $edge;
}

/**
 * An upload session for ONE file the browser sends itself (26.09.2026,
 * step three of the picture plan) — Drive's resumable upload, OPENED HERE
 * with the token and USED THERE without it: the browser PUTs the bytes to
 * the address this returns, and the token never leaves the server.
 *
 * THE ORIGIN IS NAMED ON OPENING: Drive answers the browser's PUT with the
 * CORS headers it needs only for the origin that opened the session.
 *
 * @return string the session address the browser PUTs the bytes to
 */
function gdrive_upload_session(string $access, string $folderId, string $name, string $type,
                               int $size, ?int $mtime, string $origin): string
{
    $metadata = ['name' => $name, 'parents' => [$folderId], 'mimeType' => $type];
    if ($mtime !== null) {
        $metadata['modifiedTime'] = gmdate('Y-m-d\TH:i:s\Z', $mtime);
    }
    $response = @file_get_contents(GDRIVE_UPLOAD . '/files?uploadType=resumable&fields=' . rawurlencode('id,name,size'),
        false, stream_context_create(['http' => [
            'method'          => 'POST',
            'header'          => gdrive_auth_header($access)
                               . "Content-Type: application/json; charset=UTF-8\r\n"
                               . 'X-Upload-Content-Type: ' . $type . "\r\n"
                               . ($size > 0 ? 'X-Upload-Content-Length: ' . $size . "\r\n" : '')
                               . 'Origin: ' . $origin . "\r\n",
            'content'         => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'timeout'         => GDRIVE_TIMEOUT,
            'ignore_errors'   => true,
            'follow_location' => 0,
        ]]));

    $status   = 0;
    $location = '';
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $line, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($line, 'location:') === 0) {
            $location = trim(substr($line, 9));
        }
    }
    if ($status !== 200 || $location === '') {
        $d = json_decode((string) $response, true);
        $e = is_array($d['error'] ?? null) ? $d['error'] : ['message' => (string) ($d['error'] ?? '')];
        throw new RuntimeException(sprintf('Google Drive öffnet keine Hochlade-Sitzung (%s): %s',
            (string) ($e['code'] ?? $status), (string) ($e['message'] ?? '') ?: 'ohne Angabe'));
    }
    return $location;
}

/**
 * The whole tree below a folder (storage_tree) — ONE paged listing of
 * everything the app can see, sorted into the tree here. drive.file shows
 * only what YASAIMAPP made, which is all the sweep may touch anyway.
 *
 * @return ?array{id: string, name: string, files: list<array{id: string, name: string}>, folders: list<array>}
 */
function gdrive_tree(string $access, string $folderId): ?array
{
    if (gdrive_folder_name($access, $folderId) === null) {
        return null;
    }
    $children = [];
    $page = null;
    do {
        $l = gdrive_call($access, 'GET', GDRIVE_API . '/files?' . http_build_query(array_filter([
            'q'         => 'trashed = false',
            'fields'    => 'nextPageToken,files(id,name,parents,mimeType)',
            'pageSize'  => 1000,
            'pageToken' => $page,
        ])));
        foreach ((array) ($l['files'] ?? []) as $e) {
            foreach ((array) ($e['parents'] ?? []) as $parent) {
                $children[(string) $parent][] = $e;
            }
        }
        $page = (string) ($l['nextPageToken'] ?? '') ?: null;
    } while ($page !== null);

    $seen = [];
    $node = static function (string $id, string $name) use (&$node, &$seen, $children): array {
        $seen[$id] = true;
        $out = ['id' => $id, 'name' => $name, 'files' => [], 'folders' => []];
        foreach ($children[$id] ?? [] as $e) {
            $childId = (string) ($e['id'] ?? '');
            if (($e['mimeType'] ?? '') === 'application/vnd.google-apps.folder') {
                if (!isset($seen[$childId])) {   // a folder with two parents is walked once
                    $out['folders'][] = $node($childId, (string) ($e['name'] ?? ''));
                }
            } else {
                $out['files'][] = ['id' => $childId, 'name' => (string) ($e['name'] ?? '')];
            }
        }
        return $out;
    };
    return $node($folderId, '');
}
