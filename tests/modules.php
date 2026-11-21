<?php
/**
 * The module interface (includes/modules.php) — and that the core keeps to it.
 *
 *   php tests/modules.php
 *
 * THREE THINGS ARE HELD, and none of them needs a real module:
 *
 *   A MODULE'S ANSWERS REACH THE PLACES THAT ASK. A made-up module — a
 *   folder with a module.php, written for this run — offers a header
 *   button, a key tool, the right to write a walk and tables that travel;
 *   the core's own functions must show each of them.
 *
 *   WITHOUT ANY MODULE (the same questions asked of an empty folder) every
 *   hook answers neutral and the core's own functions still work: three
 *   buttons in the header, a find's ways can be read, no walk is written,
 *   only the core's tables travel.
 *
 *   THE CORE CALLS NO MODULE BY NAME. The tripwire, for whatever modules
 *   this checkout has installed: no function a module declares is called in
 *   the core's code, and no core file names a module's folder — in PHP by
 *   the tokenizer, in the browser's scripts with the comments taken out.
 *   A direct call would work on this machine and break every installation
 *   that has no such module; nothing but this test would say so. On a
 *   checkout without modules there is nothing to trip over, and the test
 *   says that it looked at none.
 *
 * WHAT A MODULE KNOWS ABOUT ITSELF — an address, a key of its
 * configuration — this test cannot guess; a module brings its own test for
 * that (modules/<name>/tests/).
 *
 * Against the demodata fixture (anna, ben). No configuration is changed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/point_keyings.php';
require_once __DIR__ . '/../includes/tax_transfer.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

/** Every function the PHP files of a folder declare, lowercase — read from the source. */
function declared_functions(array $files): array
{
    $names = [];
    foreach ($files as $file) {
        $tokens = token_get_all((string) file_get_contents($file));
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
            for ($j = $i + 1; isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE; $j++);
            if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $names[strtolower($tokens[$j][1])] = true;
            }
        }
    }
    return $names;
}

$user = static function (string $email) use ($pdo): ?array {
    $q = $pdo->prepare('SELECT * FROM map_users WHERE email = ?');
    $q->execute([$email]);
    return $q->fetch() ?: null;
};
$anna = $user('anna@demo.local');
$ben  = $user('ben@demo.local');
if (!$anna || !$ben) {
    echo "The demodata fixture is missing — php admin/demodata.php --new\n";
    exit(1);
}
$a = (int) $anna['id'];
$tabsHtml = static function (): string {
    ob_start();
    app_tabs('map');
    return (string) ob_get_clean();
};
$find = (int) $pdo->query("SELECT id FROM map_points WHERE user_id = $a AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
$installed = PROJECT_PATH . '/modules';

/* A folder to put modules into for this run, and to empty again. */
$scratch = sys_get_temp_dir() . '/yasaimapp-modules-' . bin2hex(random_bytes(4));
mkdir($scratch);

try {
    echo "A made-up module at the hooks:\n";
    mkdir("$scratch/with/sample", 0777, true);
    file_put_contents("$scratch/with/sample/module.php", '<?php return [
        "tabs"            => static fn(?array $user): array => $user !== null && (int) $user["id"] === ' . $a . '
            ? ["sample" => ["Probe", "/somewhere/sample.php", "key"]] : [],
        "key_tool"        => static fn(?array $user): ?array => $user !== null && (int) $user["id"] === ' . $a . '
            ? ["url" => "/somewhere/sample.php"] : null,
        "walk_writer"     => static fn(array $user): bool => (int) $user["id"] === ' . $a . ',
        "transfer_tables" => static fn(): array => ["map_sample_one", "map_sample_two"],
    ];');
    file_put_contents("$scratch/with/sample/notes.php", '<?php // beside the module.php — never read as one');
    check(array_keys(modules("$scratch/with")) === ['sample'], 'a folder with a module.php is a module, by its folder\'s name');
    check(module_first('key_tool', $anna) === ['url' => '/somewhere/sample.php'], 'its key tool reaches whoever it is for');
    check(module_first('key_tool', $ben) === null && module_first('key_tool', null) === null, 'and nobody else');
    check(module_each('tabs', $anna) === ['sample' => ['sample' => ['Probe', '/somewhere/sample.php', 'key']]], 'its header button is handed on as it came');
    check(module_first('walk_writer', $anna) === true && module_first('walk_writer', $ben) !== true, 'it says who may write a walk');
    check(module_first('walk_summary', $pdo, ['n' => 0]) === null, 'a hook it does not offer has no answer');
    check(tax_transfer_tables() === array_merge(TAX_TRANSFER_TABLES, ['map_sample_one', 'map_sample_two']),
        'its tables travel after the core\'s, in its order');

    echo "Without any module:\n";
    mkdir("$scratch/none");
    check(modules("$scratch/none") === [], 'an empty folder is no module');
    check(module_first('key_tool', $anna) === null && module_each('tabs', $anna) === [], 'nobody offers a key tool or a button');
    $html = $tabsHtml();
    check(substr_count($html, '<a ') + substr_count($html, '<span') >= 3 && !str_contains($html, 'sample'), 'the header row is the core\'s own');
    $ways = point_keyings_for($pdo, $a, $find);
    check(is_array($ways) && $ways['ready'] === true, 'the ways at a find can be read');
    check(point_keying_save($pdo, $anna, $find, ['n' => 0]) === false, 'no walk is written where no tool says who may');
    check(tax_transfer_tables() === TAX_TRANSFER_TABLES, 'only the core\'s tables travel');
    check(module_first('friends_process', $pdo, $a, 'whatever', []) === null, 'a module\'s form meets nobody and changes nothing');
} finally {
    foreach (["$scratch/with/sample/module.php", "$scratch/with/sample/notes.php"] as $file) @unlink($file);
    foreach (["$scratch/with/sample", "$scratch/with", "$scratch/none", $scratch] as $folder) @rmdir($folder);
    modules($installed);
}
check(!is_dir($scratch), 'the made-up module is gone again');

echo "The core calls no module by name:\n";
$root = dirname(__DIR__);

/* WHAT THE INSTALLED MODULES ARE CALLED: their folders, and every function
   their PHP files declare. Read from the files, so the test names nothing
   itself and holds for a module nobody has written yet. A module's own
   tests are no part of what it offers and are left out. */
$folders = array_map('basename', glob("$installed/*", GLOB_ONLYDIR) ?: []);
$moduleFiles = [];
foreach ($folders as $folder) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$installed/$folder", FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), '/tests/')) $moduleFiles[] = $file->getPathname();
    }
}
$declared = declared_functions($moduleFiles);

$core = [];
foreach (['*.php', 'pages/*.php', 'includes/*.php', 'admin/*.php', 'api/v1/*.php', 'cronjobs/*.php'] as $pattern) {
    foreach (glob($root . '/' . $pattern) ?: [] as $file) {
        $core[] = $file;
    }
}
/* A module may give a function the name of one the core declares in a file
   this request never loads — the core calling ITS OWN is no breach. */
$declared = array_diff_key($declared, declared_functions($core));

if ($folders === []) {
    echo "  ok    no module is installed in this checkout — nothing the core could name\n";
} else {
    printf("  (%d module(s) installed, %d function(s) of their own)\n", count($folders), count($declared));
    $paths = '~modules/(' . implode('|', array_map(static fn(string $f): string => preg_quote($f, '~'), $folders)) . ')\b~';

    $found = [];
    foreach ($core as $file) {
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (!is_array($token)) continue;
            $hit = ($token[0] === T_STRING && isset($declared[strtolower($token[1])]))
                || (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)
                    && preg_match($paths, $token[1]) === 1);
            if ($hit) $found[] = substr($file, strlen($root) + 1) . ':' . $token[2];
        }
    }
    check($found === [], $found === [] ? 'no core PHP file calls a module\'s function or names its folder'
        : 'the core names a module in: ' . implode(', ', array_unique($found)));

    $foundJs = [];
    foreach (glob($root . '/assets/js/*.js') ?: [] as $file) {
        $code = (string) file_get_contents($file);
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $code);          // block comments
        $code = (string) preg_replace('~(^|[\s;{}(,])//[^\n]*~m', '$1', $code);   // line comments
        if (preg_match($paths, $code) === 1) $foundJs[] = 'assets/js/' . basename($file);
    }
    check($foundJs === [], $foundJs === [] ? 'no core script names a module\'s folder'
        : 'core scripts that name a module: ' . implode(', ', $foundJs));
}

echo $failed === 0 ? "All good.\n" : "$failed failed.\n";
exit($failed === 0 ? 0 : 1);
