<?php
/**
 * Coming back from the provider's consent screen.
 *
 * This page is the ONE address registered at every provider — pCloud and
 * Google Drive share it, told apart by what the session remembers from the
 * connect button — and it is the only place where the app secrets are
 * used. It does four things and then gets out of the way: check the state,
 * trade the code for a token, ask who that token belongs to, store the
 * connection.
 *
 * NOTHING IS SHOWN HERE. Every outcome ends as a redirect back to the Cloud
 * tab with a message — success as well as failure. A page of its own that
 * says "worked!" would be a dead end one has to click away, and after a
 * failure one would sit on an address that cannot be reloaded: the code is
 * spent after the first attempt.
 *
 * THE MESSAGE TRAVELS THROUGH THE SESSION, not through the address. The URL
 * would otherwise carry the provider's error text into the browser history
 * and into any referer — and it is at times verbose enough to name the
 * account.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';

$me    = login_required();
$userId = (int) $me['id'];

$target = url('pages/administration.php') . '#cloud';

/** Put the outcome down for the Cloud tab and leave. */
function cloud_return(string $kind, string $text, string $target): never
{
    $_SESSION['cloud_message'] = [$kind, $text];
    redirect_to($target);
}

/** „Google Drive ist verbunden — als …" in ONE sentence, so it translates whole. */
function connected_said(string $provider, string $account): string
{
    return $account !== ''
        ? t('%s ist verbunden — als %s.', $provider, $account)
        : t('%s ist verbunden.', $provider);
}

/**
 * Connected — and when this connection is the one a MOVE waited for, the
 * switch happens here (includes/cloud_moves.php): from this moment the new
 * cloud is THE cloud, the old one only lends its key.
 *
 * ONE CLOUD, ALWAYS: a switch that could not begin takes the new
 * connection back out again, or two would stand side by side — the very
 * thing the move was built to avoid. Only the new row goes; the old
 * connection and the backup's bookkeeping stay as they were.
 */
function cloud_return_connected(PDO $pdo, int $userId, string $provider, string $said,
                                ?array $move, string $target): never
{
    if ($move === null || ($move['to'] ?? '') !== $provider) {
        cloud_return('success', $said, $target);
    }
    require_once __DIR__ . '/../includes/cloud_moves.php';
    try {
        cloud_move_begin($pdo, $userId, (string) $move['from'], $provider);
    } catch (Throwable $e) {
        $pdo->prepare('DELETE FROM map_cloud_accounts WHERE user_id = ? AND provider = ?')->execute([$userId, $provider]);
        cloud_return('error', t('Der Umzug ließ sich nicht beginnen: %s', $e->getMessage()), $target);
    }
    cloud_return('success', t('%s ist jetzt deine Cloud. Deine Bilder ziehen jetzt aus %s um.',
        cloud_move_name($provider), cloud_move_name((string) $move['from'])), $target);
}

// --- The state is the thread back to this session --------------------------
//
// WITHOUT IT ANYONE COULD HAND US A CODE. Whoever gets a logged-in user to
// call this address with their own code connects THEIR pCloud account to the
// stranger's map — and everything uploaded later lands in the attacker's
// storage. The state is drawn when the button is pressed, kept in the
// session, and is valid exactly once.
$expected = (string) ($_SESSION['cloud_state'] ?? '');
unset($_SESSION['cloud_state']);

/* WHO IS COMING BACK — remembered at the connect button, like the state.
   Google's return carries no provider name, and pCloud's hostname
   parameter is browser-carried anyway (see PCLOUD_HOSTS: recognised, not
   trusted). The session is the only voice with any authority here.
   Missing means pCloud: every session from before Google Drive existed
   was on its way there. */
$provider = (string) ($_SESSION['cloud_provider'] ?? 'pcloud');
unset($_SESSION['cloud_provider']);

/* A MOVE WAITING FOR THIS RETURN — remembered at the move window's
   „Weiter zu …" (cloud_content.php), and like the state valid exactly
   once: whoever turns back at the provider, or comes back from somewhere
   else, has started no move. */
$move = is_array($_SESSION['cloud_move'] ?? null) ? $_SESSION['cloud_move'] : null;
unset($_SESSION['cloud_move']);

$state = (string) ($_GET['state'] ?? '');

if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
    cloud_return('error', t('Der Rücksprung passt nicht zu dieser Sitzung. Bitte noch einmal auf „Verbinden" drücken.'), $target);
}

// The user pressed cancel at the provider: pCloud comes back without a code.
// That is not an error, it is an answer.
$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    cloud_return('hint', t('Abgebrochen — es wurde nichts verbunden.'), $target);
}

if (!cloud_ready($pdo)) {
    cloud_return('error', t('Die Tabelle für die Zugänge fehlt noch — Migration 0053 einspielen.'), $target);
}

try {
    if ($provider === 'gdrive') {
        require_once __DIR__ . '/../includes/gdrive.php';

        $token = gdrive_token_get($code);
        $who   = gdrive_userinfo($token['access']);

        cloud_connect($pdo, $userId, 'gdrive', [
            'account'    => $who['email'],
            // Both tokens in one bundle — the access token dies within the
            // hour, gdrive_access() renews it from the refresh half.
            'secret'     => cloud_tokens_pack($token['access'], $token['refresh']),
            'expires_at' => $token['expires_at'],
            'folder'     => (string) (cloud_connections($pdo, $userId)['gdrive']['folder'] ?? ''),
        ]);

        cloud_return_connected($pdo, $userId, 'gdrive', connected_said('Google Drive', $who['email']), $move, $target);
    }

    if ($provider === 'onedrive') {
        require_once __DIR__ . '/../includes/onedrive.php';

        $token = onedrive_token_get($code);
        $who   = onedrive_userinfo($token['access']);

        cloud_connect($pdo, $userId, 'onedrive', [
            // Microsoft's drive names its owner, not an address — the
            // display name fills the same slot the others fill with mail.
            'account'    => $who['email'],
            'secret'     => cloud_tokens_pack($token['access'], $token['refresh']),
            'expires_at' => $token['expires_at'],
            'folder'     => (string) (cloud_connections($pdo, $userId)['onedrive']['folder'] ?? ''),
        ]);

        cloud_return_connected($pdo, $userId, 'onedrive', connected_said('OneDrive', $who['email']), $move, $target);
    }

    // Which data centre — see PCLOUD_HOSTS: recognised, not trusted.
    $host = pcloud_host($_GET['hostname'] ?? null);

    $token = pcloud_token_get($host, $code);
    $who   = pcloud_userinfo($host, $token['token']);

    cloud_connect($pdo, $userId, 'pcloud', [
        'account'     => $who['email'] !== '' ? $who['email'] : ('Konto ' . $token['uid']),
        'region'       => $host,
        'secret' => $token['token'],
        // pCloud issues tokens without an expiry — NULL here means "does not
        // expire", not "expired". See the column comment in migration 0053.
        'expires_at' => null,
        // The target folder belongs to the user and is set in the tab. A
        // connection must not overwrite it, otherwise reconnecting silently
        // resets a carefully chosen path.
        'folder'    => (string) (cloud_connections($pdo, $userId)['pcloud']['folder'] ?? ''),
    ]);

    cloud_return_connected($pdo, $userId, 'pcloud', connected_said('pCloud', $who['email']), $move, $target);

} catch (Throwable $e) {
    cloud_return('error', $e->getMessage(), $target);
}
