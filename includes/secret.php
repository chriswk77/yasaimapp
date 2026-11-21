<?php
/**
 * The application's own secret — for signing what the server hands a
 * browser and wants to recognize later (security review, 23.09.2026: the
 * proof that a browser joined an excursion as a given guest).
 *
 * UNDER data/, LIKE THE CLOUD VAULT, AND FOR THE SAME REASONS
 * (cloud_vault_path in cloud.php): not in the repository, so deployment
 * neither carries nor overwrites it; not in the database, so a dump does
 * not hand out the key with what it signs; a .php file, so a failed bar
 * executes it and prints nothing. Made on first use, 256 random bits,
 * written beside and moved over so a crash never leaves half a key.
 */

declare(strict_types=1);

function app_secret(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $path = PROJECT_PATH . '/data/secret.php';
    $d = is_file($path) ? @include $path : null;
    if (is_array($d) && is_string($d['key'] ?? null) && strlen($d['key']) === 64) {
        return $key = $d['key'];
    }
    $fresh = bin2hex(random_bytes(32));
    $tmp = $path . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, "<?php\nreturn ['key' => '" . $fresh . "'];\n", LOCK_EX) !== false) {
        @chmod($tmp, 0600);
        @rename($tmp, $path);
    }
    // Read back what stands there now — another request may have won.
    $d = is_file($path) ? @include $path : null;
    return $key = (is_array($d) && is_string($d['key'] ?? null)) ? $d['key'] : $fresh;
}

/** A value with its signature: "value.hmac". */
function app_sign(string $value): string
{
    return $value . '.' . hash_hmac('sha256', $value, app_secret());
}

/** The value, if the signature holds — otherwise null. */
function app_unsign(string $signed): ?string
{
    $cut = strrpos($signed, '.');
    if ($cut === false) {
        return null;
    }
    $value = substr($signed, 0, $cut);
    return hash_equals(hash_hmac('sha256', $value, app_secret()), substr($signed, $cut + 1)) ? $value : null;
}
