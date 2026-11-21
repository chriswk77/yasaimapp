<?php
/**
 * Languages — t() and the catalogs (28.08.2026: „Zeit, eine
 * L10N-Datei zu machen, damit wir multilingual werden können").
 *
 * THE GERMAN TEXT IS THE KEY, gettext's old idea, and here it is not a
 * convenience but the only fit: this application was German-first for
 * its whole life, the house rule keeps user-facing German IN the code
 * where the next reader sees it, and thousands of call sites already
 * hold finished German sentences. Wrapping them in t() changes NOTHING
 * for the German app — t() without a foreign catalog is the identity —
 * and every other language is a pure ADDITION: one file per language
 * under includes/lang/, mapping German sentences to their translation.
 * A sentence the catalog does not know falls back to German instead of
 * to a broken page; being late costs a mixed-language page, never a
 * failure.
 *
 * PLACEHOLDERS are sprintf's (%s, %d): the German call site writes
 * t('Vorlage „%s" gespeichert', $name), and the translation reorders
 * with %1$s where its grammar needs to. Concatenated fragments cannot
 * be translated (word order differs) — whoever wraps a string joins it
 * into ONE sentence with placeholders first.
 *
 * WHICH LANGUAGE, in order: the account's choice (map_users.lang,
 * migration 0096 — NULL means automatic), then the session's (the login
 * page's toggle for whoever is not yet anyone), then the browser's
 * Accept-Language, then German. The answer is cached per request —
 * language does not change mid-page.
 *
 * THE SWEEP IS STAGED, deliberately: this file and the first surface
 * (the login page) prove the pattern; every further page follows the
 * same three moves — join fragments, wrap in t(), add the sentences to
 * includes/lang/en.php. The browser side mirrors this in
 * assets/js/i18n.js with the catalog riding the start payload.
 */

declare(strict_types=1);

/** The languages this installation speaks. The code is the file name
 *  under includes/lang/ — German has no file, it IS the source. */
const LANGUAGES = ['de' => 'Deutsch', 'en' => 'English'];

/**
 * The request's language. See the header for the order of authority.
 */
function lang_current(): string
{
    static $lang = null;
    if (lang_force() !== null) {
        return lang_force();
    }
    if ($lang !== null) {
        return $lang;
    }

    // A ?lang= on any page is the guest's switch (the login page offers
    // it) — remembered in the session so the choice survives the POST.
    $asked = (string) ($_GET['lang'] ?? '');
    if (isset(LANGUAGES[$asked]) && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['lang'] = $asked;
    }

    $me = function_exists('user_current') ? user_current() : null;
    if (is_array($me) && isset($me['lang'], LANGUAGES[$me['lang']])) {
        return $lang = (string) $me['lang'];
    }
    $session = (string) ($_SESSION['lang'] ?? '');
    if (isset(LANGUAGES[$session])) {
        return $lang = $session;
    }
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $code = strtolower(substr(trim($part), 0, 2));
        if (isset(LANGUAGES[$code])) {
            return $lang = $code;
        }
    }
    return $lang = 'de';
}

/**
 * Force the language for the REST of this request — the one caller is
 * the settings form: whoever just chose English should be answered in
 * English, but user_current() was cached before the row changed, and a
 * per-request cache is otherwise exactly right.
 */
function lang_force(?string $code = null): ?string
{
    static $forced = null;
    if ($code !== null) {
        $forced = isset(LANGUAGES[$code]) ? $code : 'de';
    }
    return $forced;
}

/**
 * The catalog for the current language — [] for German, which needs
 * none: the source text already is the German sentence.
 *
 * A MODULE BRINGS ITS OWN SENTENCES (modules/<name>/lang/<code>.php,
 * 21.11.2026): what only a module says is not the core's to translate,
 * and an installation without the folder carries none of it. They join
 * the one catalog here, so the browser's copy (window.LANG_DATA) holds
 * them without knowing of modules. The core's sentence wins where both
 * carry one — tests/lang.php sees to it that none does.
 */
function lang_catalog(): array
{
    static $catalog = null;
    static $for = null;
    if ($catalog === null || $for !== lang_current()) {
        $for = lang_current();
        $catalog = [];
        if ($for !== 'de') {
            $file = __DIR__ . '/lang/' . $for . '.php';
            $catalog = is_file($file) ? (array) (require $file) : [];
            foreach (glob(dirname(__DIR__) . '/modules/*/lang/' . $for . '.php') ?: [] as $file) {
                $catalog += (array) (require $file);
            }
        }
    }
    return $catalog;
}

/**
 * Translate one German sentence — or hand it back untouched.
 *
 * @param string $text the GERMAN sentence, sprintf placeholders included
 * @param string|int|float ...$args values for the placeholders
 */
function t(string $text, string|int|float ...$args): string
{
    $out = lang_catalog()[$text] ?? $text;
    return $args === [] ? $out : vsprintf($out, $args);
}
