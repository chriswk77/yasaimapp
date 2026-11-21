<?php
/**
 * Login, sessions and access protection.
 *
 * Two paths lead to a logged-in user:
 *   - the session, for the normal pages in the browser
 *   - a token in the Authorization header, for the REST API and the PWA
 *
 * user_current() understands both, so the API endpoints do not have to
 * be built on two tracks.
 */

declare(strict_types=1);

const LOGIN_MAX_ATTEMPTS  = 10;   // per identifier and per IP
const LOGIN_LOCK_MIN    = 15;   // time window in minutes
const LOGIN_KEEP_DAYS   = 7;    // how long a failed attempt is kept at all

/**
 * Throw away the failed attempts nobody needs any more.
 *
 * THE THROTTLE READS FIFTEEN MINUTES AND KEPT FOR EVER. A row carries an
 * e-mail address, an IP address and a time, and it went only when that
 * very address logged in successfully — a mistyped address, a stranger's
 * knocking, a reset request („reset:…") stayed in the table for good.
 * Found on 20.11.2026 while the privacy statement was being written, which
 * has to name a period; seven days was the decision. Seven and not fifteen
 * minutes, so that a week of knocking can still be looked at by hand.
 *
 * TWO CALLERS, ONE RULE: the daily cron bundle (cronjobs/_jobs.php), so the
 * period holds on a quiet installation too, and every failed attempt
 * itself, so it holds where no cron runs. The statement is cheap — the
 * table is small and mostly empty.
 *
 * @return int  rows removed
 */
function login_attempts_purge(PDO $pdo): int
{
    $stmt = $pdo->prepare('DELETE FROM map_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->execute([LOGIN_KEEP_DAYS]);
    return $stmt->rowCount();
}

/**
 * A locked account counts as not logged in.
 *
 * THE CHECK BELONGS HERE and not only in the login form: whoever was already
 * logged in when the lock came would otherwise have an open session and a
 * valid device token — and would keep getting in for weeks. A lock that only
 * takes effect at the next login is none.
 */
function user_unlocked(mixed $row): ?array
{
    // ?? null, not directly: before migration 0028 the column does not
    // exist, and a missing key would be a warning on every page load.
    return (is_array($row) && ($row['locked_at'] ?? null) === null) ? $row : null;
}

/**
 * Does this column exist yet?
 *
 * WHAT THIS IS FOR: between deploying the code and applying the migrations
 * lies a window in which the new application meets the old database. Usually
 * that is without consequence. This time it was not — and in the most
 * unpleasant way:
 *
 * The code read the role from a column that did not exist yet, therefore no
 * longer considered anyone an admin, and thereby locked up the server
 * management area. But that is where the page for applying the migrations
 * lives. The door was locked from the inside, and the key lay behind it.
 *
 * So the code checks what it finds instead of assuming it. The result is
 * memoized — otherwise the question costs one query on every call.
 */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    static $known = [];
    $key = $table . '.' . $column;

    if (!isset($known[$key])) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        $known[$key] = (int) $stmt->fetchColumn() > 0;
    }
    return $known[$key];
}

/** The same for whole tables — migration 0039 brings the first one checked this way. */
function table_exists(PDO $pdo, string $table): bool
{
    static $known = [];

    if (!isset($known[$table])) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        $known[$table] = (int) $stmt->fetchColumn() > 0;
    }
    return $known[$table];
}

/**
 * Logged-in user or null. The result is memoized per request, so that
 * repeated calls do not ask the database repeatedly.
 */
function user_current(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }

    global $pdo;

    // 1. Token from the Authorization header (API, PWA)
    $token = token_from_request();
    if ($token !== null) {
        $stmt = $pdo->prepare(
            'SELECT u.* FROM map_tokens t
               JOIN map_users u ON u.id = t.user_id
              WHERE t.token_hash = ? AND (t.valid_until IS NULL OR t.valid_until > NOW())
              LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $found = $stmt->fetch();

        if ($found) {
            // Record last use only roughly — once per hour is enough and
            // saves a write on every API call.
            $pdo->prepare(
                'UPDATE map_tokens SET last_used_at = NOW()
                  WHERE token_hash = ?
                    AND (last_used_at IS NULL OR last_used_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))'
            )->execute([hash('sha256', $token)]);

            return $user = user_unlocked($found);
        }
        return $user = null;
    }

    // 2. Session
    if (empty($_SESSION['user_id'])) {
        // 3. Stayed logged in? Then restore the session from that.
        return $user = persistent_restore();
    }

    $stmt = $pdo->prepare('SELECT * FROM map_users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $found = $stmt->fetch();

    if (!$found) {
        // The account was deleted while the session was still running.
        unset($_SESSION['user_id']);
        return $user = null;
    }

    return $user = user_unlocked($found);
}

// ---------------------------------------------------------------------------
// Staying logged in
//
// The browser's session ends when it is closed — that is right and should
// stay that way. Whoever wants to stay logged in additionally gets their
// own long-lived remember token: a random value in the cookie, whose hash
// sits in map_tokens. The same table serves the API, just with a different
// device label.
//
// Three decisions behind this:
//
//   ONLY THE HASH is stored, never the value itself. Whoever gets hold of
//   the database still cannot log in with it — just like with the password.
//
//   THE DEADLINE MOVES ALONG. The 90 days count from the last use, not from
//   the login. "Stay logged in" is supposed to mean being left in peace as
//   long as you use the map — not being thrown out after 90 days in the
//   middle of the season.
//
//   LOGGING OUT DELETES THE TOKEN, and in the database, not just in the
//   browser. A cookie you throw away without invalidating its counterpart
//   remains valid — with a device you have lost, that would be the wrong
//   moment for a surprise.
// ---------------------------------------------------------------------------

const PERSISTENT_COOKIE = 'map_stay';
/* THE NAME THE COOKIE CARRIED BEFORE THE MOVE TO ENGLISH. Browsers out
   there still hold valid tokens under it — renaming without reading the
   old name would log every device out once. persistent_cookie_value()
   reads both, and a successful restore moves the value to the new name.
   Deletable once every device has visited — at the latest when the last
   old token has expired, 90 days after this went live. */
const PERSISTENT_COOKIE_OLD = 'karte_bleib';
const PERSISTENT_DAYS   = 90;

/** The token value, from whichever of the two cookie names carries one. */
function persistent_cookie_value(): string
{
    return (string) ($_COOKIE[PERSISTENT_COOKIE] ?? $_COOKIE[PERSISTENT_COOKIE_OLD] ?? '');
}

/**
 * The user agent without the words every browser says.
 *
 * THE COLUMN HOLDS 100 CHARACTERS, and a phone's Chrome needs 106 to reach
 * the one word that tells a phone from a tablet: „Mobile" stands near the
 * end, after „Mozilla/5.0", „AppleWebKit/537.36 (KHTML, like Gecko)" and
 * the version — so until 28.09.2026 every Android browser was cut right
 * before it, and the maintainer could not tell his tablet from his phone in the
 * list. What goes here carries no information (every Chromium and Safari
 * sends it) and device_label() asks for none of it; Safari's „Version/"
 * stays, it is the one number of that browser. Cutting it instead of
 * widening the column: the code goes live before a migration, and a longer
 * value in the old column would fail the login in strict mode.
 */
function browser_agent_short(string $agent): string
{
    return trim((string) preg_replace(
        ['~^Mozilla/[\d.]+\s*~', '~AppleWebKit/[\d.+]+\s*~', '~\(KHTML, like Gecko\)\s*~'],
        '', $agent));
}

/**
 * Issue a remember token and put it into the cookie.
 *
 * Called after login when the checkbox was set.
 */
function persistent_remember(int $userId): void
{
    global $pdo;

    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    $value = bin2hex(random_bytes(32));

    $pdo->prepare(
        'INSERT INTO map_tokens (user_id, token_hash, device, created_at, last_used_at, valid_until)
         VALUES (?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))'
    )->execute([
        $userId,
        hash('sha256', $value),
        mb_substr('Browser: ' . browser_agent_short((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unbekannt')), 0, 100),
        PERSISTENT_DAYS,
    ]);

    persistent_cookie_set($value, time() + PERSISTENT_DAYS * 86400);
}

/**
 * Restore the login from the remember token.
 *
 * Returns the user and at the same time puts them into the session, so that
 * the following calls take the short path again.
 */
function persistent_restore(): ?array
{
    global $pdo;

    $value = persistent_cookie_value();
    // Check the shape before the database query: 64 hex characters,
    // otherwise it cannot be one of our tokens anyway.
    if (!preg_match('/^[0-9a-f]{64}$/', $value)) {
        return null;
    }

    $hash = hash('sha256', $value);

    $stmt = $pdo->prepare(
        'SELECT u.* FROM map_tokens t
           JOIN map_users u ON u.id = t.user_id
          WHERE t.token_hash = ? AND t.valid_until > NOW()
          LIMIT 1'
    );
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if (!$user) {
        // Expired, invalidated or made up. Clear the cookie away so it does
        // not keep the database busy again on every call.
        persistent_cookie_delete();
        return null;
    }

    // Let the deadline move along — at most once a day, otherwise every
    // page view writes to the database.
    $pdo->prepare(
        'UPDATE map_tokens
            SET last_used_at = NOW(),
                valid_until    = DATE_ADD(NOW(), INTERVAL ? DAY)
          WHERE token_hash = ?
            AND (last_used_at IS NULL OR last_used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))'
    )->execute([PERSISTENT_DAYS, $hash]);

    if (user_unlocked($user) === null) {
        return null;
    }

    // Still travelling under the old cookie name: reissue under the new
    // one, so the fallback read above can retire some day.
    if (!isset($_COOKIE[PERSISTENT_COOKIE]) && PHP_SAPI !== 'cli' && !headers_sent()) {
        persistent_cookie_set($value, time() + PERSISTENT_DAYS * 86400);
    }

    session_renew();
    $_SESSION['user_id'] = (int) $user['id'];

    return $user;
}

/** Invalidate the remember token — in the database and in the browser. */
function persistent_forget(): void
{
    global $pdo;

    $value = persistent_cookie_value();
    if (preg_match('/^[0-9a-f]{64}$/', $value)) {
        $pdo->prepare('DELETE FROM map_tokens WHERE token_hash = ?')
            ->execute([hash('sha256', $value)]);
    }
    persistent_cookie_delete();
}

function persistent_cookie_set(string $value, int $until): void
{
    setcookie(PERSISTENT_COOKIE, $value, [
        'expires'  => $until,
        'path'     => BASE_URL,
        'httponly' => true,
        'secure'   => !IS_LOCAL,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[PERSISTENT_COOKIE] = $value;
}

function persistent_cookie_delete(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    persistent_cookie_set('', time() - 42000);
    unset($_COOKIE[PERSISTENT_COOKIE]);
    // The old-named cookie as well — logging out must not leave a token
    // behind under a name only the fallback read still knows.
    setcookie(PERSISTENT_COOKIE_OLD, '', [
        'expires'  => time() - 42000,
        'path'     => BASE_URL,
        'httponly' => true,
        'secure'   => !IS_LOCAL,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[PERSISTENT_COOKIE_OLD]);
}

/**
 * Change the session id, as long as that is still possible. After the first
 * byte of output no cookie can be set anymore — on the command line that is
 * always the case.
 */
function session_renew(): void
{
    if (PHP_SAPI !== 'cli' && !headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/** Read the token from the Authorization header. */
function token_from_request(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $match)) {
        return $match[1];
    }
    return null;
}

/** Page for logged-in users only. Otherwise redirects to the login. */
function login_required(): array
{
    $user = user_current();
    if ($user === null) {
        $target = $_SERVER['REQUEST_URI'] ?? '';
        redirect_to('login.php' . ($target ? '?next=' . urlencode($target) : ''));
    }
    return $user;
}

/** As above, but for API endpoints: JSON instead of a redirect. */
function api_login_required(): array
{
    $user = user_current();
    if ($user === null) {
        json_error(t('Nicht angemeldet.'), 401);
    }
    return $user;
}

/**
 * Is this person an administrator?
 *
 * The one place where the role is evaluated. Before, empty($user['is_admin'])
 * stood everywhere for this — five times the same condition, and on the
 * switch to roles one could easily have been overlooked. An overlooked check
 * for admin rights is the most unpleasant kind of careless mistake.
 */
function is_admin(?array $user): bool
{
    // As long as the role column is not there yet, the old flag applies.
    // Without this fallback nobody would be admin anymore after deploying —
    // and thus nobody able to apply the migration that creates the column.
    if (!array_key_exists('role', (array) $user)) {
        return !empty($user['is_admin']);
    }
    return $user['role'] === 'admin';
}

/**
 * The roles as they appear in the database, with their labels.
 *
 * The order is that of the forms: what is needed most often comes first.
 */
const ROLES = [
    'user' => 'Benutzer',
    'admin'    => 'Admin',
    'guest'     => 'Gast',
];

function role_readable(?string $role): string
{
    // The label is translated HERE, not in ROLES: a const cannot call
    // t(), and the keys next to the labels are database values anyway.
    return t(ROLES[$role ?? ''] ?? 'Benutzer');
}

/** If the value comes from outside, anything unknown becomes an ordinary user. */
function role_check(mixed $value): string
{
    return isset(ROLES[(string) $value]) ? (string) $value : 'user';
}

/**
 * The first account of the installation.
 *
 * WHY THE OLDEST AND NOT THE 1. Meant is the person who set up the
 * installation — and they normally carry the id 1, because they were the
 * first. But "normally" is no foundation: on this development machine it is
 * the 12, because accounts came into being and vanished again while
 * building. A hard-coded 1 would have protected nobody here, and the rule
 * would not have been tested either.
 *
 * The smallest assigned id is the same thing, just without the assumption:
 * the counter assigns in ascending order and reuses nothing, so the smallest
 * is at the same time the oldest.
 */
function founder_id(PDO $pdo): int
{
    static $id = null;
    if ($id === null) {
        $id = (int) $pdo->query('SELECT MIN(id) FROM map_users')->fetchColumn();
    }
    return $id;
}

function is_founder(PDO $pdo, int $userId): bool
{
    return $userId === founder_id($pdo) && $userId > 0;
}

/**
 * How somebody is displayed.
 *
 * First and last name. THE NICK NO LONGER EXISTS (migration 0048) — among a
 * handful of people who know each other, it was a second identity without
 * benefit: you log in with the e-mail address, the name is what gets shown.
 * The fallback is the e-mail address, in case an account carries no name
 * (yet).
 */
function user_display_name(?array $user): string
{
    $name = user_full_name($user);
    return $name !== '' ? $name : (string) ($user['email'] ?? '');
}

/**
 * The same display name as an SQL expression — for queries that deliver
 * names right along (friends, shares, members) instead of fetching per row.
 * MUST yield the same as user_display_name(): first and last name,
 * otherwise the e-mail address.
 */
function sql_display_name(string $t = 'u'): string
{
    return "COALESCE(NULLIF(TRIM(CONCAT($t.first_name, ' ', $t.last_name)), ''), $t.email)";
}

/** First and last name, as they are written together. Empty if both are missing. */
function user_full_name(?array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

/** Page for administrators only. */
function admin_required(): array
{
    $user = login_required();
    if (!is_admin($user)) {
        http_response_code(403);
        exit(t('Kein Zugriff.'));
    }
    return $user;
}

// ---------------------------------------------------------------------------
// Logging in and out
// ---------------------------------------------------------------------------

/**
 * Login attempt. Returns the user or null.
 *
 * Counts failed attempts per identifier and per IP. Both are needed: without
 * IP counting somebody can try many accounts at the same time, without
 * identifier counting the attack gets through from changing addresses.
 */
function login(string $identifier, string $password): ?array
{
    global $pdo;

    $identifier = mb_strtolower(trim($identifier));
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';

    // ONLY THE E-MAIL ADDRESS. The nick as a second login name is
    // abolished (migration 0048) — one identifier is enough, and everyone
    // has the e-mail anyway.
    $stmt = $pdo->prepare('SELECT * FROM map_users WHERE email = ? LIMIT 1');
    $stmt->execute([mb_strtolower($identifier)]);
    $user = $stmt->fetch();

    // A locked account behaves like a wrong password: it should not be
    // possible to tell whether somebody was locked or never existed.
    /* ONE HASHING PER ATTEMPT, WHATEVER THE ACCOUNT (security review,
       23.09.2026). A locked account used to skip password_verify and an
       unknown one checked a fixed dummy hash that was not a valid bcrypt
       string — both answered measurably faster than a real wrong
       password, and so told apart „exists", „locked" and „unknown". Now
       an existing account always verifies, and an unknown identifier
       hashes the password once with the current default: the same work,
       the same cost setting, whatever PHP version the host runs. */
    if ($user) {
        $verified = password_verify($password, (string) $user['password_hash']);
    } else {
        password_hash($password, PASSWORD_DEFAULT);
        $verified = false;
    }
    $ok = $user && ($user['locked_at'] ?? null) === null && $verified;

    if (!$ok) {
        $pdo->prepare('INSERT INTO map_login_attempts (identifier, ip, attempted_at) VALUES (?, ?, NOW())')
            ->execute([$identifier, $ip]);
        login_attempts_purge($pdo);   // the old ones go where a new one comes
        return null;
    }

    login_session($user);
    return $user;
}

/**
 * Everything that follows a proven identity — the password at the login,
 * the link of „Passwort vergessen". ONE PATH, so a reset cannot log in
 * with less than the login does.
 */
function login_session(array $user): void
{
    global $pdo;

    // This identifier's failed attempts are forgiven — after a reset too,
    // or the new password would meet the lock the forgotten one built.
    $pdo->prepare('DELETE FROM map_login_attempts WHERE identifier = ?')
        ->execute([mb_strtolower((string) $user['email'])]);

    // Change the session id, so that an id planted beforehand does not
    // remain valid after login. On the command line — that is, in
    // maintenance and check scripts — there is no session to renew.
    session_renew();
    $_SESSION['user_id'] = (int) $user['id'];

    $pdo->prepare('UPDATE map_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

    // THE ATTRIBUTION (excursions, stage 7): whoever once mapped as a
    // guest and left this e-mail gets those guest rows tied to the
    // account — on every login, idempotent and one cheap UPDATE. Only
    // the login catches BOTH cases: the guest who registers later, and
    // the participant who had an account all along.
    require_once __DIR__ . '/excursions.php';
    excursion_claim($pdo, (int) $user['id'], (string) $user['email']);
}

/**
 * Is the login currently locked? Returns the remaining minutes, or 0 if the
 * way is clear.
 */
function login_locked(string $email): int
{
    global $pdo;

    $stmt = $pdo->prepare(
        /* 'attempted_at' AND NOT 'timestamp'. The column is called what the
           schema calls it — German, and it stays that way until the schema
           moves in its own step (see CLAUDE.md). The English rename
           translated the name inside this SQL string, and nothing could
           notice: PHP sees a string, the database sees a column that does
           not exist, and the error only appears when somebody logs in.
           Which nobody does who is already logged in. */
        'SELECT SUM(identifier = ? AND ip = ?) AS pair,
                SUM(ip = ?) AS from_here,
                SUM(identifier = ?) AS on_account,
                MAX(attempted_at) AS latest
           FROM map_login_attempts
          WHERE (identifier = ? OR ip = ?)
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $id = mb_strtolower(trim($email));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt->execute([$id, $ip, $ip, $id, $id, $ip, LOGIN_LOCK_MIN]);
    $row = $stmt->fetch();

    /* THREE LIMITS INSTEAD OF ONE (security review, 23.09.2026). The one
       limit counted „this account OR this address": ten wrong passwords
       typed by anybody, anywhere, locked the real owner out for a quarter
       of an hour — and could be repeated for ever. Now:
         - ten from this address at this account — the ordinary guesser;
         - thirty from this address at all — one machine trying many
           accounts;
         - fifty at this account from everywhere together — a spread-out
           attack, and the only limit a stranger can still spring on the
           owner, now five times as costly. */
    $locked = (int) $row['pair'] >= LOGIN_MAX_ATTEMPTS
        || (int) $row['from_here'] >= LOGIN_MAX_ATTEMPTS * 3
        || (int) $row['on_account'] >= LOGIN_MAX_ATTEMPTS * 5;
    if (!$locked) {
        return 0;
    }

    $freeAt = strtotime($row['latest']) + LOGIN_LOCK_MIN * 60;
    return max(1, (int) ceil(($freeAt - time()) / 60));
}

/** Log out and discard the session completely. */
function logout(): void
{
    // First invalidate the remember token — otherwise the next request
    // would log right back in, and "Abmelden" would have no effect.
    persistent_forget();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Registration via invitation code
// ---------------------------------------------------------------------------

/** Fetch the invitation for a code, provided it is valid and not redeemed. */
function invitation_check(string $code): ?array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT * FROM map_invites
          WHERE code = ? AND redeemed_by IS NULL
            AND (valid_until IS NULL OR valid_until > NOW())
          LIMIT 1'
    );
    $stmt->execute([trim($code)]);
    return $stmt->fetch() ?: null;
}

/**
 * Create a new account and invalidate the invitation. Runs in a transaction,
 * so that a code does not count twice when two registrations arrive at the
 * same time.
 */
/**
 * Change name and e-mail address — the settings tab, one form, one save.
 * The two used to be separate, with separate saving; the e-mail address
 * demanded the password, the name did not. Merged, the WHOLE form now
 * demands the password, instead of the prompt depending on whether the
 * address happened to change too — that would have been a silent exception
 * nobody suspects.
 *
 * The first name is mandatory as at registration — it feeds the display
 * name and the finder field.
 *
 * A NEW ADDRESS IS CONFIRMED BEFORE IT COUNTS (23.09.2026,
 * after the security review): it waits as pending, a link goes to it, and
 * until the link is followed the old address stays the login
 * (email_confirmation.php). Without migration 0116 it is stored at once,
 * as before.
 *
 * @return ?string the pending address a link went to, or null
 */
function account_person_email_update(PDO $pdo, int $userId, string $firstName,
                                    string $lastName, string $email, string $password): ?string
{
    $stmt = $pdo->prepare('SELECT password_hash FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify($password, $hash)) {
        throw new InvalidArgumentException(t('Das Passwort stimmt nicht.'));
    }

    $firstName = mb_substr(trim($firstName), 0, 60);
    $lastName  = mb_substr(trim($lastName), 0, 80);
    if ($firstName === '') {
        throw new InvalidArgumentException(t('Bitte trage deinen Vornamen ein.'));
    }

    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(t('Diese E-Mail-Adresse ist nicht vollständig. Eine Adresse sieht so aus: name@beispiel.de'));
    }

    $stmt = $pdo->prepare('SELECT 1 FROM map_users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException(t('Für diese E-Mail-Adresse gibt es schon ein Konto.'));
    }

    require_once __DIR__ . '/email_confirmation.php';
    $current = $pdo->prepare('SELECT email FROM map_users WHERE id = ?');
    $current->execute([$userId]);
    if (email_confirmation_ready($pdo) && (string) $current->fetchColumn() !== $email) {
        $pdo->prepare('UPDATE map_users SET first_name = ?, last_name = ? WHERE id = ?')
            ->execute([$firstName, $lastName, $userId]);
        email_confirmation_send($pdo, $userId, $email);
        return $email;
    }
    $pdo->prepare('UPDATE map_users SET first_name = ?, last_name = ?, email = ? WHERE id = ?')
        ->execute([$firstName, $lastName, $email, $userId]);
    return null;
}

/**
 * The rules for a new password — the settings and „Passwort vergessen"
 * ask the same. The limit is the one of the registration (register.php).
 *
 * @throws InvalidArgumentException
 */
function password_new_check(string $newPassword, string $repeat): void
{
    if (mb_strlen($newPassword) < 10) {
        throw new InvalidArgumentException(t('Das neue Passwort ist zu kurz. Es braucht mindestens 10 Zeichen.'));
    }
    if ($newPassword !== $repeat) {
        throw new InvalidArgumentException(t('Die beiden neuen Passwörter sind nicht gleich. Bitte tippe sie noch einmal.'));
    }
}

/**
 * Set a new password.
 *
 * First confirm the old one — an open session on somebody else's computer
 * should not suffice to lock somebody out for good. After that ALL OTHER
 * devices are thrown out (map_tokens): whoever changes their password
 * usually wants exactly that. One's own device stays logged in — the
 * session keeps running, and one's own stay-logged-in token is spared when
 * deleting.
 */
function account_password_update(PDO $pdo, int $userId, string $current,
                                string $newPassword, string $repeat): void
{
    $stmt = $pdo->prepare('SELECT password_hash FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify($current, $hash)) {
        throw new InvalidArgumentException(t('Dein jetziges Passwort stimmt nicht.'));
    }
    password_new_check($newPassword, $repeat);

    $pdo->prepare('UPDATE map_users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

    $own = hash('sha256', (string) ($_COOKIE[PERSISTENT_COOKIE] ?? ''));
    $pdo->prepare('DELETE FROM map_tokens WHERE user_id = ? AND token_hash <> ?')
        ->execute([$userId, $own]);
}

function account_create(string $email, string $password, array $person, ?array $invitation): int
{
    global $pdo;

    $email = mb_strtolower(trim($email));
    $hash  = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        // THE ROLE COMES FROM THE INVITATION, not from the form — otherwise
        // somebody could declare themselves administrator when joining.
        // Without an invitation there is only the very first person, and
        // setup.php makes them the administrator.
        $role = $invitation['role'] ?? 'user';

        // WITHOUT the nick column, on purpose: until migration 0048 has
        // run, it still exists (NOT NULL DEFAULT '') — an INSERT that does
        // not name it runs before the migration as well as after. The code
        // goes live before the migration.
        $stmt = $pdo->prepare(
            'INSERT INTO map_users (email, password_hash, first_name, last_name, role, invited_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $email, $hash,
            trim($person['first_name'] ?? ''), trim($person['last_name'] ?? ''),
            $role, $invitation['created_by'] ?? null,
        ]);
        $userId = (int) $pdo->lastInsertId();

        if ($invitation !== null) {
            /* A SPENT CODE IS GONE, not marked (25.08.2026:
               „unnötige Info, unnötige Ladezeit, wenn du das nur
               ausblendest"). What the row used to say afterwards — who
               came in through it and when — the user table says better:
               it carries invited_by, which points at the INVITER and not
               at the code, so „eingeladen von …" survives this
               untouched.

               AND IT CLOSES A HOLE. redeemed_by carries
               ON DELETE SET NULL, and a spent code was recognised by
               redeemed_by IS NOT NULL. Delete the account in the
               administration — which it can, members_content.php — and
               the marking fell back to NULL: the code was live again and
               could be redeemed a second time. Measured on the day this
               was written, in a transaction and rolled back. A row that
               no longer exists cannot come back to life.

               The race guard works exactly as before, only through the
               ROW instead of the column: two people registering at once,
               the first deletes and gets rowCount 1, the second finds
               nothing. The condition on redeemed_by stays for the
               deployment window — until the migration has run, rows
               marked by the old code may still lie there, and they are
               spent. */
            $stmt = $pdo->prepare(
                'DELETE FROM map_invites WHERE id = ? AND redeemed_by IS NULL'
            );
            $stmt->execute([$invitation['id']]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException(t('Der Einladungscode wurde soeben von jemand anderem eingelöst.'));
            }
        }

        // Every account starts with one list, so that the map never stands
        // there empty and without a place to file things. „Meine Funde"
        // AND A SPECIES LIST from birth (24.08.2026): the find
        // fields and the species suggestions are the reason most people
        // get an account here at all, and a start list without them
        // looked like the application could not do it. The name stays
        // neutral — a find is a find, whatever one collects.
        $withSpecies = column_exists($pdo, 'map_lists', 'is_species');

        /* SINCE THE MAPPEN (migration 0168, 11.10.2026): „Meine
           Funde" is a Mappe with the three lists in it, each a species
           list of its own — a Mappe carries no settings, so the flag that
           used to sit on the root sits on every list. Below, the old way
           for a server that has not applied 0168 yet. */
        if (table_exists($pdo, 'map_list_folders') && column_exists($pdo, 'map_lists', 'folder_id')) {
            $pdo->prepare('INSERT INTO map_list_folders (user_id, name, sort_order, created_at, updated_at)
                           VALUES (?, ?, 10, NOW(), NOW())')->execute([$userId, 'Meine Funde']);
            $folderId = (int) $pdo->lastInsertId();
            $in = $pdo->prepare(
                'INSERT INTO map_lists (user_id, folder_id, name, color, symbol, is_species, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
            );
            foreach ([['Flechten', '#3d9970', 'lichen'], ['Pflanzen', '#6aa84f', 'plant'], ['Pilze', '#8e6bb5', 'mushroom']] as $i => [$name, $color, $symbol]) {
                $in->execute([$userId, $folderId, $name, $color, $symbol, ($i + 1) * 10]);
            }
            /* AND WHAT EACH HOLDS (0195, 12.11.2026): the three start lists
               suggest their own group, and the device keeps exactly those
               packages. Named as the migration names them. */
            if (column_exists($pdo, 'map_lists', 'species_groups')) {
                $pdo->prepare("UPDATE map_lists SET species_groups = CASE name
                                   WHEN 'Flechten' THEN '[\"lichens\"]' WHEN 'Pflanzen' THEN '[\"plants\"]'
                                   WHEN 'Pilze' THEN '[\"fungi\"]' END
                                WHERE user_id = ? AND name IN ('Flechten', 'Pflanzen', 'Pilze')")->execute([$userId]);
            }
            $pdo->commit();
            return $userId;
        }

        $pdo->prepare(
            'INSERT INTO map_lists (user_id, name, color, symbol, sort_order'
            . ($withSpecies ? ', is_species' : '') . ', created_at, updated_at)
             VALUES (?, ?, ?, ?, 0' . ($withSpecies ? ', 1' : '') . ', NOW(), NOW())'
        )->execute([$userId, 'Meine Funde', '#c0392b', 'circle']);
        // Read at once: any query in between may leave a different last id.
        $rootId = (int) $pdo->lastInsertId();

        /* AND THREE SUBLISTS BELOW IT (23.09.2026: „beim
           Neuanmelden unterhalb von Meine Funde die drei Listen Flechten,
           Pflanzen und Pilze anlegen, jeweils mit dem entsprechenden
           Symbol — Flechten Türkis, Pflanzen Grün, Pilze Braun"; Pilze violett since
           the same afternoon: „das passt gut zu unserem Standardthema"). The help
           named creating them as one of the first steps anyway; now the
           account starts where most would go first. „Meine Funde" thereby
           becomes a heading — it holds no points yet, so nothing has to be
           passed down. The species flag stays on the root: it governs the
           whole branch (listRoot). Colours from the picker's row. */
        if ($rootId > 0 && column_exists($pdo, 'map_lists', 'parent_id')) {
            $sub = $pdo->prepare(
                'INSERT INTO map_lists (user_id, parent_id, name, color, symbol, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            foreach ([['Flechten', '#3d9970', 'lichen'], ['Pflanzen', '#6aa84f', 'plant'], ['Pilze', '#8e6bb5', 'mushroom']] as $i => [$name, $color, $symbol]) {
                $sub->execute([$userId, $rootId, $name, $color, $symbol, $i]);
            }
        }

        $pdo->commit();
        return $userId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Generate an invitation code. Without easily confused characters. */
function invitation_code_generate(): string
{
    $icon = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        if ($i > 0 && $i % 4 === 0) {
            $code .= '-';
        }
        $code .= $icon[random_int(0, strlen($icon) - 1)];
    }
    return $code;
}

// ---------------------------------------------------------------------------
// Device tokens for API and PWA
// ---------------------------------------------------------------------------

/**
 * Create a token and return it in plain text. Only the hash is stored —
 * whoever reads the database cannot log in with it.
 *
 * IT DOES NOT EXPIRE (25.08.2026 — asked, and this is his
 * answer). valid_until stays NULL, and user_current() already reads that
 * as "no end": the condition there is `valid_until IS NULL OR
 * valid_until > NOW()`, written that way from the start.
 *
 * WHY NOT THE 180 DAYS THAT USED TO STAND HERE. They were never extended
 * on use — last_used_at was written, valid_until was not. A recorder app
 * would therefore have stopped uploading after half a year, and the
 * failure would have shown itself at the worst possible moment: back from
 * a week in the woods, with a full card and a device that is suddenly a
 * stranger. An expiry that nobody renews is not security, it is a trap
 * with a delay.
 *
 * WHAT REPLACES IT is the list in the settings: every paired device
 * stands there with its name and its last sign of life, and one click
 * throws it out. A lost phone is answered by an act, not by waiting six
 * months. A browser's "stay logged in" is a different matter and keeps
 * its own expiry (PERSISTENT_DAYS, further up) — nobody manages those in
 * a list, and a forgotten one should lapse on its own.
 */
function token_create(int $userId, string $device): string
{
    global $pdo;

    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO map_tokens (user_id, token_hash, device, created_at, valid_until)
         VALUES (?, ?, ?, NOW(), NULL)'
    )->execute([$userId, hash('sha256', $token), mb_substr(trim($device), 0, 100)]);

    return $token;
}
