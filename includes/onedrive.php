<?php
/**
 * OneDrive — the third storage (27.08.2026: „Kannst du mir das
 * auch noch für OneDrive einrichten? … Wie gesagt, es geht immer nur
 * eins."). The talking goes through Microsoft Graph.
 *
 * THE SAME HOUSE RULES as cloud.php and gdrive.php: the secret never
 * leaves the server, and the scope is the fence. Files.ReadWrite.AppFolder
 * means the app sees and touches ONLY its own folder — OneDrive lays it
 * under Apps/<App-Name> and shows it to the user like any other folder.
 * That is the third shape of the same promise: pCloud calls it a "Private"
 * app, Google scopes to drive.file, Microsoft to the app folder.
 *
 * WHAT IS DIFFERENT FROM GOOGLE, and each difference shaped a function:
 *
 *   THE REFRESH TOKEN ROTATES. Google hands one out at consent and keeps
 *   it valid; Microsoft answers EVERY refresh with a new one and lets the
 *   old die. Whoever stores only the access token keeps a connection that
 *   works for ninety days and then tears without a sound — the sliding
 *   window only slides if the newest token is the one stored. So
 *   onedrive_access() writes the answer back every time, not only when
 *   something new happens to come.
 *
 *   THE ROOT NEEDS NO ID. Graph addresses the app folder by the word
 *   'approot' (/me/drive/special/approot), and paths hang off any item by
 *   the colon syntax. The sentinel travels through the storage seam where
 *   pCloud has its 0 and Drive its 'root' — see onedrive_item().
 *
 *   UPLOAD IS A PLAIN PUT. No multipart: the bytes go raw to
 *   …:/<name>:/content, and the name in the URL decides. Replacing is the
 *   DEFAULT there — the backup's case — and keeping both wants an extra
 *   word (conflictBehavior=rename), which is the exact opposite of
 *   pCloud's renameifexists=1 default. The $replace parameter keeps one
 *   meaning across all three providers; the translation lives here.
 *
 * THE PICTURES CAME ON 26.09.2026 („alle Clouds richtig
 * nutzen"): the family at the end of this file — look an item up, delete
 * it (into OneDrive's recycle bin), move it, list sub-folders, and the
 * pre-authenticated addresses Graph hands out for content and previews.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

// 'common', not a tenant: personal Microsoft accounts and work accounts
// both land there. The app registration must match — account type
// "personal + any org", otherwise Microsoft refuses the consent with an
// error that names neither this line nor that setting.
const ONEDRIVE_CONSENT = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
const ONEDRIVE_TOKEN   = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
const ONEDRIVE_API     = 'https://graph.microsoft.com/v1.0';
const ONEDRIVE_SCOPE   = 'Files.ReadWrite.AppFolder offline_access';
const ONEDRIVE_TIMEOUT = 20;

/**
 * Where the user is sent to say yes.
 *
 * offline_access is Microsoft's word for "and a refresh token, please" —
 * the counterpart of Google's access_type=offline. A prompt=consent is
 * NOT needed here: Microsoft hands the refresh token out on every code
 * exchange, not only on the first.
 */
function onedrive_consent_url(string $state): string
{
    $app = cloud_app('onedrive');
    if ($app === null) {
        throw new RuntimeException(t('OneDrive ist auf diesem Server nicht eingerichtet.'));
    }

    return ONEDRIVE_CONSENT . '?' . http_build_query([
        'client_id'     => $app['client_id'],
        'redirect_uri'  => cloud_return_url(),
        'response_type' => 'code',
        'response_mode' => 'query',
        'scope'         => ONEDRIVE_SCOPE,
        'state'         => $state,
    ], '', '&', PHP_QUERY_RFC3986);
}

/**
 * One call to Microsoft's token endpoint — exchange and refresh share it.
 *
 * Errors come as JSON with a status ≠ 200; 'error' is the keyword
 * ("invalid_grant"), 'error_description' the sentence. Both go into the
 * message — the keyword is what Microsoft's documentation indexes by.
 */
function onedrive_token_call(array $parameters): array
{
    $response = @file_get_contents(ONEDRIVE_TOKEN, false, stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query($parameters),
            'timeout'       => ONEDRIVE_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException(t('Microsoft antwortet nicht. Später noch einmal versuchen.'));
    }
    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException(t('Microsoft antwortet unverständlich.'));
    }
    if (isset($d['error'])) {
        throw cloud_refused('Microsoft', (string) $d['error'], (string) ($d['error_description'] ?? ''));
    }
    return $d;
}

/**
 * Trade the code for the token pair.
 *
 * @return array{access: string, refresh: string, expires_at: string}
 */
function onedrive_token_get(string $code): array
{
    $app = cloud_app('onedrive');
    if ($app === null) {
        throw new RuntimeException(t('OneDrive ist auf diesem Server nicht eingerichtet.'));
    }

    $d = onedrive_token_call([
        'code'          => $code,
        'client_id'     => $app['client_id'],
        'client_secret' => $app['client_secret'],
        'redirect_uri'  => cloud_return_url(),
        'grant_type'    => 'authorization_code',
        'scope'         => ONEDRIVE_SCOPE,
    ]);

    $access  = (string) ($d['access_token'] ?? '');
    $refresh = (string) ($d['refresh_token'] ?? '');
    if ($access === '') {
        throw new RuntimeException(t('Microsoft nennt kein Zugriffstoken.'));
    }
    // Without offline_access in the consent no refresh token comes — an
    // hour of connection that looks healthy until three in the morning.
    // Same reasoning as at Google, same place to fail: here, loudly.
    if ($refresh === '') {
        throw new RuntimeException(t('Microsoft hat kein Dauer-Token ausgestellt. Bitte noch einmal verbinden und der Abfrage erneut zustimmen.'));
    }

    return [
        'access'     => $access,
        'refresh'    => $refresh,
        'expires_at' => cloud_token_expiry((int) ($d['expires_in'] ?? 3600)),
    ];
}

/**
 * A valid access token — THE gate for every Graph call.
 *
 * THE ANSWER IS ALWAYS WRITTEN BACK, and that is the one sentence to
 * carry away from this file: Microsoft rotates the refresh token, the one
 * just used is on its way out, and only the stored-newest keeps the
 * ninety-day window sliding. Google's twin may skip the write when
 * nothing new came; this one may not.
 */
function onedrive_access(PDO $pdo, int $userId): ?string
{
    // The retiring row too — a move out of this cloud still reads with it.
    $connection = cloud_connections($pdo, $userId, true)['onedrive'] ?? null;
    $bundle     = cloud_tokens_parse(cloud_secret($pdo, $userId, 'onedrive'));
    if ($connection === null || $bundle === null) {
        return null;
    }

    $expiry = (string) ($connection['expires_at'] ?? '');
    if ($expiry !== '' && strtotime($expiry) > time()) {
        return $bundle['access'];
    }

    try {
        $app = cloud_app('onedrive');
        if ($app === null) {
            return null;
        }
        $d = onedrive_token_call([
            'refresh_token' => $bundle['refresh'],
            'client_id'     => $app['client_id'],
            'client_secret' => $app['client_secret'],
            'grant_type'    => 'refresh_token',
            'scope'         => ONEDRIVE_SCOPE,
        ]);
    } catch (RuntimeException $e) {
        // invalid_grant: the refresh token is dead — consent withdrawn,
        // password changed, or ninety days without a single sweep. Only
        // reconnecting heals it; the caller gets the ordinary "no cloud".
        error_log('onedrive_access (' . $userId . '): ' . $e->getMessage());
        return null;
    }

    $access = (string) ($d['access_token'] ?? '');
    if ($access === '') {
        return null;
    }
    $refresh = (string) ($d['refresh_token'] ?? '') ?: $bundle['refresh'];

    $pdo->prepare(
        'UPDATE map_cloud_accounts SET secret = ?, expires_at = ?, updated_at = NOW()
          WHERE user_id = ? AND provider = "onedrive"'
    )->execute([
        cloud_encrypt(cloud_tokens_pack($access, $refresh)),
        cloud_token_expiry((int) ($d['expires_in'] ?? 3600)),
        $userId,
    ]);

    return $access;
}

/**
 * One call to Graph, JSON in and out.
 */
function onedrive_call(string $access, string $method, string $url, ?array $body = null): array
{
    $headers = 'Authorization: Bearer ' . $access . "\r\n";
    if ($body !== null) {
        $headers .= "Content-Type: application/json; charset=utf-8\r\n";
    }

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => $headers,
            'content'       => $body === null ? '' : json_encode($body, JSON_UNESCAPED_SLASHES),
            'timeout'       => ONEDRIVE_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException(t('OneDrive antwortet nicht. Später noch einmal versuchen.'));
    }
    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException(t('OneDrive antwortet unverständlich.'));
    }
    if (isset($d['error'])) {
        $e = is_array($d['error']) ? $d['error'] : ['message' => (string) $d['error']];
        throw cloud_refused('OneDrive', (string) ($e['code'] ?? '?'), (string) ($e['message'] ?? ''));
    }
    return $d;
}

/**
 * Whose drive, and how full.
 *
 * ASKED AT THE DRIVE, like at Google and for the same reason: /me/drive
 * answers within the Files scope. An email would need an identity scope
 * on the consent screen; the owner's display name is enough for
 * „verbunden als …".
 *
 * @return array{email: string, used: int, quota: ?int}  quota null = unbegrenzt
 */
function onedrive_userinfo(string $access): array
{
    $d = onedrive_call($access, 'GET', ONEDRIVE_API . '/me/drive?$select=owner,quota');

    $quota = (int) ($d['quota']['total'] ?? 0);
    return [
        // The key stays 'email' so the caller shapes one message for all
        // three providers; Microsoft simply fills it with the name.
        'email' => (string) ($d['owner']['user']['displayName'] ?? ''),
        'used'  => (int) ($d['quota']['used'] ?? 0),
        'quota' => $quota > 0 ? $quota : null,
    ];
}

/**
 * An item address for Graph: the 'approot' sentinel or a real id.
 *
 * The sentinel is what storage_root() hands out — the app folder needs no
 * fetched id, Graph addresses it by name, and saving the lookup saves a
 * request per sweep.
 */
function onedrive_item(string $itemId): string
{
    return $itemId === 'approot'
        ? '/me/drive/special/approot'
        : '/me/drive/items/' . rawurlencode($itemId);
}

/**
 * Make sure a folder is there, and give back its id.
 *
 * LOOK FIRST, CREATE ON THE 404. The colon syntax resolves a child by
 * name in one request; creating answers a clean conflict when two sweeps
 * race (conflictBehavior=fail), and the loser simply looks again. 'fail'
 * and not 'replace': replace would swallow a folder with content, and a
 * race must never be able to do that.
 *
 * @return array{folderid: string, created: bool}
 */
function onedrive_folder_ensure(string $access, string $parentId, string $name): array
{
    $at = ONEDRIVE_API . onedrive_item($parentId) . ':/' . rawurlencode($name) . '?$select=id,folder';
    try {
        $d = onedrive_call($access, 'GET', $at);
        if (isset($d['folder']) && ($d['id'] ?? '') !== '') {
            return ['folderid' => (string) $d['id'], 'created' => false];
        }
        // The name exists and is a FILE. Creating a folder beside it would
        // rename one of the two behind the user's back — better to say so.
        throw new RuntimeException(t('In OneDrive liegt schon eine Datei namens „%s", wo ein Ordner gebraucht wird.', $name));
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'itemNotFound')) {
            throw $e;
        }
    }

    try {
        $made = onedrive_call($access, 'POST',
            ONEDRIVE_API . onedrive_item($parentId) . '/children', [
                'name'   => $name,
                'folder' => new stdClass(),
                '@microsoft.graph.conflictBehavior' => 'fail',
            ]);
    } catch (RuntimeException $e) {
        // The race: somebody created it between look and create. Then it
        // is there now, and looking again is the answer.
        if (!str_contains($e->getMessage(), 'nameAlreadyExists')) {
            throw $e;
        }
        $d = onedrive_call($access, 'GET', $at);
        $made = $d;
    }

    $id = (string) ($made['id'] ?? '');
    if ($id === '') {
        throw new RuntimeException(t('OneDrive nennt für „%s" keine Ordnerkennung.', $name));
    }
    return ['folderid' => $id, 'created' => true];
}

/**
 * The FILES lying directly in a folder, by name — the Graph twin of
 * pcloud_folder_files(), for the backup's honesty about what is really
 * still up there.
 *
 * PAGED TO THE END via @odata.nextLink, for the same reason the Drive
 * twin walks its pages: a loop that stops at the first page would one day
 * silently re-upload everything past entry two hundred, every night.
 *
 * @return array<string, array{fileid: string, size: int, modified: string}>
 */
function onedrive_folder_files(string $access, string $folderId): array
{
    $out = [];
    $url = ONEDRIVE_API . onedrive_item($folderId)
         . '/children?$select=id,name,size,lastModifiedDateTime,folder&$top=200';

    while ($url !== null) {
        $d = onedrive_call($access, 'GET', $url);
        foreach ((array) ($d['value'] ?? []) as $e) {
            $name = (string) ($e['name'] ?? '');
            if ($name === '' || isset($e['folder'])) {
                continue;
            }
            $out[$name] = [
                'fileid'   => (string) ($e['id'] ?? ''),
                'size'     => (int) ($e['size'] ?? 0),
                'modified' => (string) ($e['lastModifiedDateTime'] ?? ''),
            ];
        }
        $url = (string) ($d['@odata.nextLink'] ?? '') ?: null;
    }

    return $out;
}

/**
 * Put bytes into a folder — replace in place, or keep both.
 *
 * A PLAIN PUT with the name in the address; Graph's default on a taken
 * name is REPLACE, which keeps the id and is exactly the backup's case.
 * $replace=false asks for 'rename' instead — the meaning pCloud reaches
 * with renameifexists=1 — so the parameter says the same thing at all
 * three providers and only the wire word differs.
 *
 * THE SIZE IS CHECKED like everywhere: the answer names what was stored,
 * and a truncated upload must not count as done.
 *
 * @return array{fileid: string, name: string, size: int}
 */
function onedrive_upload_bytes(
    string $access,
    string $folderId,
    string $bytes,
    string $name,
    ?int $mtime = null,
    bool $replace = false
): array {
    if ($bytes === '') {
        throw new RuntimeException(t('Nichts zu übertragen: %s', $name));
    }

    $url = ONEDRIVE_API . onedrive_item($folderId) . ':/' . rawurlencode($name)
         . ':/content?@microsoft.graph.conflictBehavior=' . ($replace ? 'replace' : 'rename');

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => 'PUT',
            'header'        => 'Authorization: Bearer ' . $access . "\r\n"
                             . "Content-Type: application/octet-stream\r\n",
            'content'       => $bytes,
            'timeout'       => ONEDRIVE_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException(t('OneDrive nimmt die Datei gerade nicht an.'));
    }
    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException(t('OneDrive antwortet unverständlich.'));
    }
    if (isset($d['error'])) {
        $e = is_array($d['error']) ? $d['error'] : ['message' => (string) $d['error']];
        throw cloud_refused('OneDrive', (string) ($e['code'] ?? '?'), (string) ($e['message'] ?? ''));
    }

    $fileid = (string) ($d['id'] ?? '');
    if ($fileid === '') {
        throw new RuntimeException(t('OneDrive nennt keine Dateikennung.'));
    }
    $size = (int) ($d['size'] ?? 0);
    if ($size !== strlen($bytes)) {
        throw new RuntimeException(t(
            'OneDrive hat %d von %d Bytes angenommen.', $size, strlen($bytes)));
    }

    /* The find moment onto the file, best effort: the PUT cannot carry it,
       and a failed stamp is a blemish, not a lost backup — the content is
       up. fileSystemInfo is the writable pair Graph provides for this. */
    if ($mtime !== null) {
        try {
            onedrive_call($access, 'PATCH', ONEDRIVE_API . '/me/drive/items/' . rawurlencode($fileid), [
                'fileSystemInfo' => [
                    'lastModifiedDateTime' => gmdate('Y-m-d\TH:i:s\Z', $mtime),
                ],
            ]);
        } catch (RuntimeException $e) {
            error_log('onedrive_upload_bytes mtime (' . $name . '): ' . $e->getMessage());
        }
    }

    return ['fileid' => $fileid, 'name' => (string) ($d['name'] ?? $name), 'size' => $size];
}

/* ---------------------------------------------------------------------------
 * The picture family (26.09.2026) — what the storage seam asks of every
 * provider: look a file up, delete it, move it, list the folders under a
 * folder, and hand out an address to fetch it by.
 * ------------------------------------------------------------------------- */

/**
 * One call to Graph whose answer is not JSON — a DELETE answers 204 and
 * an empty body, which onedrive_call() would take for gibberish.
 *
 * @return array{status: int, body: string}
 */
function onedrive_send(string $access, string $method, string $url): array
{
    $body = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => 'Authorization: Bearer ' . $access . "\r\n",
            'timeout'       => ONEDRIVE_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));
    if ($body === false) {
        throw new RuntimeException(t('OneDrive antwortet nicht. Später noch einmal versuchen.'));
    }
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    return ['status' => $status, 'body' => $body];
}

/**
 * Name, size and parent of one item — null when it is gone.
 *
 * @return array{name: string, size: int, parent: string, type: string}|null
 */
function onedrive_file_meta(string $access, string $itemId): ?array
{
    try {
        $d = onedrive_call($access, 'GET', ONEDRIVE_API . onedrive_item($itemId)
            . '?$select=name,size,parentReference,file');
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(itemNotFound)')) {
            return null;
        }
        throw $e;
    }
    return [
        'name'   => (string) ($d['name'] ?? ''),
        'size'   => (int) ($d['size'] ?? 0),
        'parent' => (string) ($d['parentReference']['id'] ?? ''),
        'type'   => (string) ($d['file']['mimeType'] ?? ''),
    ];
}

/**
 * A folder's parent, its name and what lies in it (storage_folder_state).
 * Null when the folder is gone.
 *
 * @return ?array{parent: string, name: string, entries: list<array{id: string, name: string, folder: bool}>}
 */
function onedrive_folder_state(string $access, string $folderId): ?array
{
    try {
        $d = onedrive_call($access, 'GET', ONEDRIVE_API . onedrive_item($folderId)
            . '?$select=name,parentReference');
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(itemNotFound)')) {
            return null;
        }
        throw $e;
    }
    $entries = [];
    $url = ONEDRIVE_API . onedrive_item($folderId) . '/children?$select=id,name,folder&$top=200';
    while ($url !== null) {
        $l = onedrive_call($access, 'GET', $url);
        foreach ((array) ($l['value'] ?? []) as $e) {
            $entries[] = [
                'id'     => (string) ($e['id'] ?? ''),
                'name'   => (string) ($e['name'] ?? ''),
                'folder' => isset($e['folder']),
            ];
        }
        $url = (string) ($l['@odata.nextLink'] ?? '') ?: null;
    }
    return [
        'parent'  => (string) ($d['parentReference']['id'] ?? ''),
        'name'    => (string) ($d['name'] ?? ''),
        'entries' => $entries,
    ];
}

/**
 * Delete one item — OneDrive lays it into its recycle bin, the same
 * promise pCloud's trash keeps. An item already gone counts as deleted.
 */
function onedrive_file_delete(string $access, string $itemId): void
{
    $answer = onedrive_send($access, 'DELETE', ONEDRIVE_API . onedrive_item($itemId));
    if (!in_array($answer['status'], [200, 204, 404], true)) {
        throw new RuntimeException(t('OneDrive lehnt das Löschen ab (HTTP %d).', $answer['status']));
    }
}

/** The app folder's real id — a move names its target by id, not by the sentinel. */
function onedrive_folder_id(string $access, string $folderId): string
{
    if ($folderId !== 'approot') {
        return $folderId;
    }
    $d = onedrive_call($access, 'GET', ONEDRIVE_API . '/me/drive/special/approot?$select=id');
    $id = (string) ($d['id'] ?? '');
    if ($id === '') {
        throw new RuntimeException(t('OneDrive nennt keine Kennung für den App-Ordner.'));
    }
    return $id;
}

/** Move a file or a folder into another folder — the id stays, as at pCloud. */
function onedrive_move(string $access, string $itemId, string $toFolderId): void
{
    onedrive_call($access, 'PATCH', ONEDRIVE_API . onedrive_item($itemId) . '?$select=id', [
        'parentReference' => ['id' => onedrive_folder_id($access, $toFolderId)],
    ]);
}

/**
 * Move an item and rename it in one PATCH (storage_file_place). Graph
 * refuses a taken name with a conflict rather than replacing — the caller
 * looks first all the same.
 */
function onedrive_place(string $access, string $itemId, string $toFolderId, string $name): void
{
    onedrive_call($access, 'PATCH', ONEDRIVE_API . onedrive_item($itemId) . '?$select=id', [
        'name'            => $name,
        'parentReference' => ['id' => onedrive_folder_id($access, $toFolderId)],
    ]);
}

/**
 * The FOLDERS lying directly in a folder, by name — for the letter count
 * of the genus tree. Paged to the end via @odata.nextLink.
 *
 * @return array<string, string> name => id
 */
function onedrive_subfolders(string $access, string $folderId): array
{
    $out = [];
    $url = ONEDRIVE_API . onedrive_item($folderId) . '/children?$select=id,name,folder&$top=200';
    while ($url !== null) {
        $d = onedrive_call($access, 'GET', $url);
        foreach ((array) ($d['value'] ?? []) as $e) {
            $name = (string) ($e['name'] ?? '');
            if ($name !== '' && isset($e['folder'])) {
                $out[$name] = (string) ($e['id'] ?? '');
            }
        }
        $url = (string) ($d['@odata.nextLink'] ?? '') ?: null;
    }
    return $out;
}

/**
 * Where the bytes of a file are fetched — the short-lived download address
 * Graph hands out, PRE-AUTHENTICATED: no token goes along, and none may,
 * because it leads to another host. Null when the item is gone.
 *
 * @return array{url: string, headers: string}|null
 */
function onedrive_file_fetch(string $access, string $itemId): ?array
{
    try {
        $d = onedrive_call($access, 'GET', ONEDRIVE_API . onedrive_item($itemId)
            . '?$select=id,@microsoft.graph.downloadUrl');
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(itemNotFound)')) {
            return null;
        }
        throw $e;
    }
    $url = (string) ($d['@microsoft.graph.downloadUrl'] ?? '');
    return $url === '' ? null : ['url' => $url, 'headers' => ''];
}

/**
 * OneDrive's own preview of a picture, fitted into a square of the given
 * edge („c320x320" keeps the proportions, „…_crop" would cut) — null when
 * there is none. The address comes pre-authenticated, like the content's.
 *
 * @return array{url: string, headers: string}|null
 */
function onedrive_thumb_fetch(string $access, string $itemId, int $edge): ?array
{
    try {
        $d = onedrive_call($access, 'GET', ONEDRIVE_API . onedrive_item($itemId)
            . '/thumbnails/0/c' . $edge . 'x' . $edge);
    } catch (RuntimeException $e) {
        // No preview is an answer, not a fault — a raw file, a fresh one.
        error_log('onedrive_thumb_fetch (' . $itemId . '): ' . $e->getMessage());
        return null;
    }
    $url = (string) ($d['url'] ?? '');
    return $url === '' ? null : ['url' => $url, 'headers' => ''];
}

/**
 * An upload session for ONE file the browser sends itself (26.09.2026,
 * step three of the picture plan). The address Graph hands out is
 * PRE-AUTHENTICATED: the browser PUTs byte ranges to it with no token at
 * all — and must not send one, Microsoft refuses a request that carries
 * both. „rename" on a taken name, like pCloud's renameifexists: two
 * cameras both count DSC_0001.jpg.
 *
 * @return string the session address the browser PUTs the ranges to
 */
function onedrive_upload_session(string $access, string $folderId, string $name, ?int $mtime): string
{
    $item = ['@microsoft.graph.conflictBehavior' => 'rename', 'name' => $name];
    if ($mtime !== null) {
        $item['fileSystemInfo'] = ['lastModifiedDateTime' => gmdate('Y-m-d\TH:i:s\Z', $mtime)];
    }
    $d = onedrive_call($access, 'POST', ONEDRIVE_API . onedrive_item($folderId) . ':/' . rawurlencode($name)
        . ':/createUploadSession', ['item' => $item]);
    $url = (string) ($d['uploadUrl'] ?? '');
    if ($url === '') {
        throw new RuntimeException(t('OneDrive öffnet keine Hochlade-Sitzung.'));
    }
    return $url;
}

/**
 * The whole tree below a folder (storage_tree) — folder by folder, since
 * Graph lists one storey at a time. The app folder is all the app sees.
 *
 * @return ?array{id: string, name: string, files: list<array{id: string, name: string}>, folders: list<array>}
 */
function onedrive_tree(string $access, string $folderId, int $depth = 0): ?array
{
    $state = onedrive_folder_state($access, $folderId);
    if ($state === null) {
        return null;
    }
    $out = ['id' => $folderId, 'name' => $state['name'], 'files' => [], 'folders' => []];
    foreach ($state['entries'] as $e) {
        if (!$e['folder']) {
            $out['files'][] = ['id' => $e['id'], 'name' => $e['name']];
        } elseif ($depth < 16) {
            $sub = onedrive_tree($access, $e['id'], $depth + 1);
            if ($sub !== null) {
                $out['folders'][] = $sub;
            }
        }
    }
    return $out;
}
