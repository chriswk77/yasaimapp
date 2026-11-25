<?php
/**
 * THE DESK'S FRAME PAGE — the map, the Fundbrowser and a module's areas
 * loaded side by side, and a change between them is a change of VIEW, not
 * of page (24.11.2026: „alle Bereiche in Yasai-Map so bauen, dass wir immer
 * im Vollbildmodus bleiben können. Sprich, alles wird direkt geladen. Und
 * das ist nur ein Wechsel der Ansicht, nicht ein Wechsel der Seite").
 *
 * WHY A PAGE AROUND THE PAGES AND NOT ONE PAGE. A browser ends full screen
 * the moment another page loads, and no page can prevent that; so the page
 * one sees must never be left. The areas could have been merged into one
 * document — but the map and the Fundbrowser both hold the SAME point
 * window with the same ids, each a quarter megabyte of script written
 * against „my document", and taking that apart would have been the
 * rebuilding of the map. So each area stays the page it is, whole and
 * unchanged, in a frame of its own; this page holds the frames and shows
 * one at a time. What one gets:
 *
 *   - full screen holds, because this page never goes;
 *   - an area stands as it was left — the map at its place and zoom, the
 *     Fundbrowser at its open find, a key at its couplet;
 *   - a change is instant once an area has loaded.
 *
 * NOTHING OF ITS OWN TO SEE. Every area keeps its own header bar, with the
 * same buttons at the same place as when it is a page of its own — so the
 * app looks exactly as it did. The buttons' clicks are caught in the frame
 * (assets/js/shellframe.js) and handed up here instead of leaving.
 *
 * THE ADMINISTRATION IS IN THE FRAME TOO, BUT ONLY WHEN IT IS ASKED FOR
 * (25.11.2026). It was left out at first, for fear it would be loaded
 * with everything else („da die Verwaltung nicht oft genutzt wird, würde
 * ich die Verwaltung erstmal ausschließen aus diesem Allesladen") — and
 * then leaving it out cost the one thing this page is for: its button
 * changed the page, and full screen was gone („du kannst in den Rahmen ja
 * nachträglich die Verwaltung reinladen, oder?"). So it is a TOOL
 * (app_tools(), 'tool' below): never loaded ahead, a frame only from the
 * click on, and that frame is let go when one leaves it. Two reasons for
 * letting it go instead of keeping it like an area:
 *
 *   - what one does there changes what the areas show — lists and Mappen,
 *     the theme, the language, what lies on the device —, so after a visit
 *     the areas are loaded anew, exactly as the change of page did before;
 *   - and what the areas do changes what IT shows (the trash, the points
 *     table), so the next visit starts fresh as well.
 *
 * The help and the door still leave this page — an ordinary change of
 * page, as before.
 *
 * AT THE DESK ONLY. The phone's pages stay pages: three of them loaded at
 * once are a quarter gigabyte (measured that day in a fresh Chrome: map
 * 290 MB, Fundbrowser 90, a module's key 70, the three in one page 260),
 * which a desk carries and a phone should not. A guest has no areas to walk
 * between and is sent to the map.
 *
 * AND AT THE DESK IT IS THE ENTRY (25.11.2026, after a day of trying it by
 * its address: „Ja, mach die Rahmenseite zum normalen Eingang"). Whoever
 * opens an area's page there is brought here, with that area in sight at
 * the address he asked for — includes/shell.php says by which two ways, and
 * what „at the desk" means. This page's part in it:
 *
 *   - written for a session, it marks the session as a desk's
 *     ($_SESSION['desk']), and from then on the server sends that session's
 *     windows here before it writes an area's page;
 *   - found on a device that is no desk after all (a phone with this
 *     page's address, a window pulled narrow), shell.js asks for
 *     ?leave=<the area's address> — the mark is taken back and the area's
 *     page is what one gets.
 *
 * assets/js/shell.js does the work; app_areas() and app_tools()
 * (includes/page.php) say which there are — the lists the header's buttons
 * are drawn from.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/excursions.php';
require_once __DIR__ . '/../includes/page.php';

if (user_current() === null) {
    // A guest of an excursion has a map and nothing to change to; everybody else signs in first.
    if (excursion_actor($pdo) !== null) {
        redirect_to('index.php');
    }
    login_required();
}

/* „NOT A DESK AFTER ALL" (shell.js): the mark goes, and the area's own page
   is shown as a page. Only to an area's page — the address comes from the
   request, and anything else in it would be a redirect to wherever a link
   says. A # travels by itself: the browser carries it over. */
if (isset($_GET['leave'])) {
    $_SESSION['desk'] = false;
    $to = (string) $_GET['leave'];
    $known = str_starts_with($to, BASE_URL)
        && shell_area_of((string) parse_url($to, PHP_URL_PATH)) !== null;
    redirect_to($known ? $to : 'index.php');
}
$_SESSION['desk'] = true;

$areas = [];
foreach (app_areas() as $key => [$label, $href]) {
    if ($href !== null) {
        $areas[$key] = ['label' => $label, 'url' => $href];
    }
}
foreach (app_tools() as $key => [$label, $href]) {
    $areas[$key] = ['label' => $label, 'url' => $href, 'tool' => true];
}

page_header('YASAIMAPP', ['full' => true]);
?>
<?php /* One <iframe> per area, made by shell.js when the area is first
     wanted. They all fill the window; the one in sight is the one without
     the class is-aside (app.css). */ ?>
<div class="shell" id="shell" data-desk="<?= h(DESK_QUERY) ?>" data-areas="<?= h(json_encode($areas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
<?php
page_footer(['full' => true, 'scripts' => ['assets/js/shell.js']]);
