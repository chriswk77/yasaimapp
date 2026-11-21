<?php
/**
 * MODULES — what is not part of the core hangs itself in here (20.11.2026).
 *
 * WHAT A MODULE IS. A folder modules/<name>/ with a file module.php that
 * RETURNS an array: the things the module has to offer, each under the name
 * of the place that asks for it (a „hook") and each a function the core
 * calls there. The core never names a module. It asks: „does anybody add a
 * button to the header?" — and where the folder is empty, nobody does, and
 * every page works as before. That is the whole contract, and the reason
 * for it: a part that cannot be published with the rest (or that only some
 * installations have) lives in its own folder and its own repository, and
 * the core neither breaks without it nor tells what it would be.
 *
 * THE HOOKS THE CORE ASKS — a module offers the ones it has an answer to:
 *
 *   tabs(?array $user): array
 *       buttons for the header row, between „Fundbrowser" and „Verwaltung":
 *       [key => [label, url, icon name]] (includes/page.php, app_tabs).
 *
 *   key_tool(?array $user): ?array
 *       a tool that walks an identification key FOR A FIND, for this
 *       account: ['url' => address of its page] — or null where the account
 *       may not use it. The core hangs its own parameters on the address,
 *       and THEY ARE A CONTRACT the tool's page has to read:
 *         find    the find's id — the tool keys for this find
 *         name    the species as it stands in the find's field
 *         resume  1 = go on where this account's walk stands
 *         user    whose walk is to be shown (another keyer's, read only)
 *         way     which of that walk's ways
 *         embed   1 = inside another page's window, without the header
 *       Inside a window the tool answers by postMessage with the type
 *       'yasaimapp-keying' (assets/js/microscope.js reads it).
 *
 *   walk_writer(array $user): bool
 *       may this account store a walk at a find (includes/point_keyings.php)
 *
 *   walk_summary(PDO $pdo, array $walk): ?array
 *       the ways of a stored walk, for the lines under „Art" and the plates
 *
 *   walk_lines(PDO $pdo, array $walk): array
 *       one finished line of text per CONFIRMED way of the walk — the core
 *       keeps them beside the walk (migration 0199) and shows them where no
 *       tool draws the ways. How a line reads is the tool's to decide.
 *
 *   friends_process(PDO $pdo, int $userId, string $action, array $post): ?string
 *       a form of the module's in Verwaltung → Freunde was sent: the message
 *       to show, or null where the action is not the module's. May throw
 *       InvalidArgumentException like the tab's own actions.
 *
 *   friends_render(PDO $pdo, int $userId, array $overview, string $target): void
 *       the module's section under the friends, written out in place
 *
 *   transfer_tables(): array
 *       tables that travel with „Taxonomie einspielen" (includes/tax_transfer.php)
 *
 * WHAT A MODULE BRINGS BESIDES ITS ANSWERS (21.11.2026) — all of it in its
 * own folder, each found by the place that needs it, none of it named:
 *
 *   pages/, api/, admin/   its own addresses; it hands them to the core
 *                          through the hooks above (tabs, key_tool) and to
 *                          its own scripts through the page
 *   assets/js/*.js         join every page's import map (asset_import_map,
 *                          helpers.php); they import the core's scripts by
 *                          the relative path up to assets/js/
 *   assets/css/            loaded by the module's own page ('head_extra')
 *   migrations/*.sql       run with the core's, in ONE numbering and ONE
 *                          ledger (includes/migrations.php)
 *   lang/<code>.php        laid over the core's catalog (lang_catalog,
 *                          includes/i18n.php)
 *   includes/, lang/, migrations/ and module.php are barred from the web
 *   by the root's .htaccess, like the core's own.
 *
 * A NEW HOOK is a new line here first. A place in the core that calls a
 * module's function by its name instead has undone the whole thing — and
 * tests/modules.php looks for exactly that.
 *
 * NOT A PLUGIN SYSTEM. No events, no priorities, no settings page: a list
 * of named questions, each asked at one place. Whatever needs more than
 * that is a reason to talk, not to build.
 */

declare(strict_types=1);

/**
 * The modules of this installation, by folder name.
 *
 * Read once per request. `$from` names another folder to read from and
 * replaces what was read — for the test, which asks the same questions of
 * an installation without modules.
 *
 * @return array<string, array<string, mixed>>
 */
function modules(?string $from = null): array
{
    static $loaded = null;
    if ($loaded !== null && $from === null) {
        return $loaded;
    }
    $loaded = [];
    foreach (glob(($from ?? PROJECT_PATH . '/modules') . '/*/module.php') ?: [] as $file) {
        $module = require $file;
        if (is_array($module)) {
            $loaded[basename(dirname($file))] = $module;
        }
    }
    return $loaded;
}

/**
 * Ask every module that offers the hook — the answers by module name.
 *
 * @return array<string, mixed>
 */
function module_each(string $hook, mixed ...$arguments): array
{
    $answers = [];
    foreach (modules() as $name => $module) {
        if (isset($module[$hook]) && is_callable($module[$hook])) {
            $answers[$name] = $module[$hook](...$arguments);
        }
    }
    return $answers;
}

/** The first answer that is one (not null) — for a question only one module can answer. */
function module_first(string $hook, mixed ...$arguments): mixed
{
    foreach (modules() as $module) {
        if (isset($module[$hook]) && is_callable($module[$hook])) {
            $answer = $module[$hook](...$arguments);
            if ($answer !== null) {
                return $answer;
            }
        }
    }
    return null;
}
