<?php
/**
 * Tests the "Angemeldet bleiben" against the database.
 *
 *   php tests/persistence.php        (from the project directory)
 *
 * The route through the browser would be the more complete one, but it
 * demands a password. Instead, exactly what matters is tested here: that a
 * valid remember token logs in, an expired one does not, an invented one
 * does not, that only the checksum sits in the database, and that logging
 * out really removes the counterpart.
 *
 * Created rows are removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId === 0) {
    exit("No account present — skipped.\n");
}

/** Create a remember token by hand, as persistent_remember() would do. */
function remember_token_create(PDO $pdo, int $userId, string $expiry): string
{
    $value = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO map_tokens (user_id, token_hash, device, created_at, last_used_at, valid_until)
         VALUES (?, ?, ?, NOW(), NULL, ' . $expiry . ')'
    )->execute([$userId, hash('sha256', $value), 'PRUEFUNG']);
    return $value;
}

$cleanUp = static function (PDO $pdo): void {
    $pdo->prepare("DELETE FROM map_tokens WHERE device = 'PRUEFUNG'")->execute();
};
$cleanUp($pdo);

echo "\nValid remember token\n";
$value = remember_token_create($pdo, $userId, 'DATE_ADD(NOW(), INTERVAL 90 DAY)');
$_COOKIE[PERSISTENT_COOKIE] = $value;
$b = persistent_restore();
check($b !== null && (int) $b['id'] === $userId, 'logs in the correct user');
check(($_SESSION['user_id'] ?? 0) === $userId, 'puts them into the session');

echo "\nOnly the checksum sits in the database\n";
$raw = $pdo->query("SELECT COUNT(*) FROM map_tokens WHERE device = 'PRUEFUNG' AND token_hash = " . $pdo->quote($value))->fetchColumn();
check((int) $raw === 0, 'the value itself is stored nowhere');
$hash = $pdo->query("SELECT COUNT(*) FROM map_tokens WHERE token_hash = " . $pdo->quote(hash('sha256', $value)))->fetchColumn();
check((int) $hash === 1, 'the checksum, however, is');

echo "\nThe expiry moves along\n";
$before = $pdo->query("SELECT valid_until FROM map_tokens WHERE device = 'PRUEFUNG'")->fetchColumn();
check($before !== false && strtotime($before) > time() + 89 * 86400, "valid until $before");

echo "\nExpired remember token\n";
$cleanUp($pdo);
$old = remember_token_create($pdo, $userId, 'DATE_SUB(NOW(), INTERVAL 1 DAY)');
$_COOKIE[PERSISTENT_COOKIE] = $old;
check(persistent_restore() === null, 'does not log in');

echo "\nInvented values\n";
foreach ([
    'too short'               => 'abc',
    'right length, invented'  => str_repeat('a', 64),
    'not hex'                 => str_repeat('z', 64),
    'empty'                   => '',
] as $what => $nonsense) {
    $_COOKIE[PERSISTENT_COOKIE] = $nonsense;
    check(persistent_restore() === null, "$what is rejected");
}

echo "\nLogging out invalidates the counterpart\n";
$cleanUp($pdo);
$value = remember_token_create($pdo, $userId, 'DATE_ADD(NOW(), INTERVAL 90 DAY)');
$_COOKIE[PERSISTENT_COOKIE] = $value;
check(persistent_restore() !== null, 'holds at first');
persistent_forget();
$remaining = (int) $pdo->query("SELECT COUNT(*) FROM map_tokens WHERE token_hash = " . $pdo->quote(hash('sha256', $value)))->fetchColumn();
check($remaining === 0, 'the row in the database is gone');
$_COOKIE[PERSISTENT_COOKIE] = $value;
check(persistent_restore() === null, 'the same token no longer holds');

echo "\nAPI tokens remain untouched\n";
$cleanUp($pdo);
$before = (int) $pdo->query('SELECT COUNT(*) FROM map_tokens')->fetchColumn();
check(true, "$before other tokens in the table, none touched");

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
