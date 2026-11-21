<?php
/**
 * One tab of the administration, on its own.
 *
 * WHY THIS EXISTS. administration.php used to build EVERY tab on every
 * call, including the ones nobody opened. Measured on 25.08.2026 with
 * the maintainer's stock of 1.640 points: about a megabyte of markup per view,
 * of which the Organisieren tab alone was 820 KB — its point data as
 * embedded JSON. On a MacBook that is a blink; on an older phone it is
 * parsing and DOM building for a payload nobody asked for, and a content
 * blocker walks every node of it on top.
 *
 * THIS PAGE ONLY RENDERS, IT NEVER PROCESSES. Every form of the
 * administration still posts to administration.php, and the processing
 * stays there, before any output, exactly as before — a form must not
 * depend on which route brought its tab onto the screen. Called with GET,
 * each *_process() returns its empty state; that is what is handed to the
 * renderer here.
 *
 * WHAT COMES BACK is a fragment: no page skeleton, no scripts, no
 * stylesheet. administration.js drops it into the waiting section and
 * wires it (see loadTab there).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/page.php';

$me     = login_required();
$userId = (int) $me['id'];
$admin  = is_admin($me);
$tab    = (string) ($_GET['tab'] ?? '');

/**
 * The renderers, by tab name — the same names the tab buttons carry as
 * data-anchor, so the loader needs no translation table.
 *
 * 'admin' marks what only an administrator may see. The check is HERE and
 * not only in the page: this address can be called directly, and a tab
 * that hides itself in the markup is not protected, only invisible.
 */
$tabs = [
    'settings'   => ['file' => 'settings',   'render' => static function (PDO $pdo, int $userId): void {
        settings_render($pdo, $userId, settings_process($pdo, $userId)); }],
    'cloud'      => ['file' => 'cloud',      'render' => static function (PDO $pdo, int $userId): void {
        cloud_render($pdo, $userId, cloud_process($pdo, $userId)); }],
    'archives'   => ['file' => 'archives',   'render' => static function (PDO $pdo, int $userId): void {
        archives_render($pdo, $userId, archives_process($pdo, $userId)); }],
    'friends'    => ['file' => 'friends',    'render' => static function (PDO $pdo, int $userId): void {
        friends_render($pdo, $userId, friends_process($pdo, $userId)); }],
    'groups'     => ['file' => 'groups',     'render' => static function (PDO $pdo, int $userId): void {
        groups_render($pdo, $userId, groups_process($pdo, $userId)); }],
    'given'      => ['file' => 'given',      'render' => static function (PDO $pdo, int $userId): void {
        given_render($pdo, $userId, given_process($pdo, $userId)); }],
    'hints'      => ['file' => 'hints',      'render' => static function (PDO $pdo, int $userId): void {
        hints_render($pdo, $userId, hints_process($pdo, $userId)); }],
    'excursions' => ['file' => 'excursions', 'render' => static function (PDO $pdo, int $userId): void {
        excursions_render($pdo, $userId, excursions_process($pdo, $userId)); }],
    'import'     => ['file' => 'import',     'render' => static function (PDO $pdo, int $userId): void {
        import_render($pdo, $userId, import_process($pdo, $userId)); }],
    'organize'   => ['file' => 'organize',   'render' => static function (PDO $pdo, int $userId): void {
        organize_render($pdo, $userId, organize_process($pdo, $userId)); }],
    'trash'      => ['file' => 'trash',      'render' => static function (PDO $pdo, int $userId): void {
        trash_render($pdo, $userId, trash_process($pdo, $userId)); }],
];

if (!isset($tabs[$tab])) {
    http_response_code(404);
    exit;
}
if (!empty($tabs[$tab]['admin']) && !$admin) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/' . $tabs[$tab]['file'] . '_content.php';

header('Content-Type: text/html; charset=utf-8');
// NEVER FROM THE CACHE. A fragment carries the state of a moment — the
// waiting import file, the open requests, the trash count. A browser that
// answered a second call from its own store would show yesterday.
header('Cache-Control: no-store');

($tabs[$tab]['render'])($pdo, $userId);
