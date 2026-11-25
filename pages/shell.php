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
<div class="shell" id="shell" data-areas="<?= h(json_encode($areas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
<?php
page_footer(['full' => true, 'scripts' => ['assets/js/shell.js']]);
