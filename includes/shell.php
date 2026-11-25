<?php
/**
 * THE WAY INTO THE DESK'S FRAME PAGE (pages/shell.php says what that page
 * is). Since 25.11.2026 it is the desk's ENTRY: whoever opens the map, the
 * Fundbrowser, a module's area or the administration at the desk — by a
 * bookmark, a link, after the login — lands in the frame page with that
 * area in sight, at the address he asked for („Ja, mach die Rahmenseite
 * zum normalen Eingang"). The pages themselves stay what they are; nothing
 * about their addresses changed, and the phone goes on opening them as
 * pages.
 *
 * WHO IS „AT THE DESK" IS ONLY KNOWN IN THE BROWSER — a wide window AND a
 * mouse (DESK_QUERY). The width alone would not do: a large phone held
 * across is wider than 860, a tablet always, and three areas loaded side by
 * side are a quarter gigabyte that belongs on no device one carries into
 * the woods. So a touch device keeps its pages whatever its width, and so
 * does the recorder app (window.yasaimapp).
 *
 * TWO WAYS IN, and the second exists because of the first one's price:
 *
 * 1. THE PAGE SENDS ITSELF THERE (shell_entry_script, a few lines at the
 *    head of every area's page). It can ask the browser, so it is always
 *    right — but the server has written the whole page by then, for
 *    nothing, and whatever that page shows ONCE is spent: the message a
 *    cloud's sign-in comes back with would be drawn on the page that is
 *    thrown away and be gone in the frame.
 *
 * 2. THE SERVER SENDS ONE THERE BEFORE THE PAGE IS WRITTEN (shell_entry,
 *    called by the bootstrap). It cannot ask the browser, so it goes by
 *    what the browser said before: $_SESSION['desk'], set when the frame
 *    page was written for this session (or at the login, whose form says
 *    what kind of device it is) and taken back when the frame page finds
 *    itself on a device that is none (shell.js sends it through
 *    pages/shell.php?leave=…). IN THE SESSION, NOT IN A COOKIE: the
 *    privacy statement names the two cookies there are, and a third for
 *    this would have been a third to explain.
 *
 *    The server tells a page asked for by the WINDOW from one asked for by
 *    a FRAME by the header Sec-Fetch-Dest — „document" against „iframe".
 *    Only the first is sent on; the frames of the frame page ask for the
 *    very same addresses and must get their pages. A browser that does not
 *    send the header (Safari before 16.4) is never sent on from here and
 *    takes way 1 every time.
 *
 * THE ADDRESS TRAVELS ALONG. Way 2 hands the area and its query to the
 * frame page (?area=…&at=…) and names NO # of its own — a browser carries
 * the # of the address one asked for over a redirect that has none, and
 * the Fundbrowser's open find lives there. Way 1 writes the whole address
 * where the frame page keeps its frames' addresses anyway (sessionStorage,
 * 'shell.at.<area>') and opens the frame page bare: bare is the one
 * address of it the service worker holds for the hour without a network.
 *
 * NO WAY 1 WITHOUT A NETWORK, and none where the frame page does not
 * answer: the page asks for it first and goes only when it came. A laptop
 * on a train must not be sent from a map that lies in its cache to a frame
 * page that does not.
 *
 * ONLY A GET IS EVER SENT ON. The answer to a form shows what the form did,
 * on the page that was asked.
 *
 * AND ONE CAN SAY NO, per tab: sessionStorage 'shell.off' keeps the pages
 * pages — for the test that measures a page itself (tests/neighbours.mjs)
 * and for looking at one alone.
 */

declare(strict_types=1);

/** At the desk: room for it, and a mouse — the head says why the width alone would not do. */
const DESK_QUERY = '(min-width: 861px) and (hover: hover) and (pointer: fine)';

/**
 * The area whose OWN page lies at this path — 'map', 'finds', a module's
 * key, 'admin' —, or null. By the path and not by the button a page lights
 * up in the header: a module's second page may wear its area's button and
 * is still no area the frame page could show.
 */
function shell_area_of(string $path): ?string
{
    require_once __DIR__ . '/page.php';
    // '/map/' and '/map/index.php' are one page.
    $plain = static fn (string $p): string => (string) preg_replace('~index\.php$~', '', $p);
    foreach (app_areas() + app_tools() as $key => [, $href]) {
        if ($href !== null && $plain((string) parse_url((string) $href, PHP_URL_PATH)) === $plain($path)) {
            return (string) $key;
        }
    }
    return null;
}

/** The area this request asks for, or null. */
function shell_area(): ?string
{
    return shell_area_of((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
}

/**
 * WAY 2 — before anything is written: a desk's window asks for an area's
 * page, and gets the frame page with that area instead. The cheap questions
 * first; this runs in every request there is.
 *
 * Not for an address that says „embed": that is a frame's address somebody
 * opened by hand, not an entry.
 */
function shell_entry(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
        || ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') !== 'document'
        || empty($_SESSION['desk'])
        || isset($_GET['embed'])
        || user_current() === null) {
        return;
    }
    $area = shell_area();
    if ($area === null) {
        return;
    }
    // Never kept: the next asking may come from a session that is no desk's.
    header('Cache-Control: no-store');
    redirect_to(url('pages/shell.php') . '?area=' . rawurlencode($area)
        . '&at=' . rawurlencode((string) ($_SERVER['QUERY_STRING'] ?? '')));
}

/**
 * WAY 1 — the lines at the head of an area's page. Inside a frame they end
 * at their first question, which is what they do in the frame page all day.
 *
 * The reasons stand in this file's head and not in the lines: what is
 * written into a page is read by whoever opens its source.
 */
function shell_entry_script(string $area): string
{
    $js = static fn (string $s): string => (string) json_encode($s, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    $shell = url('pages/shell.php');
    return '<script>(()=>{try{'
        . 'if(window.top!==window||navigator.onLine===false||window.yasaimapp'
        . '||sessionStorage.getItem("shell.off")||!matchMedia(' . $js(DESK_QUERY) . ').matches)return;'
        . 'fetch(' . $js($shell) . ',{credentials:"same-origin"}).then((r)=>{'
        . 'if(!r.ok||r.redirected)return;'
        . 'sessionStorage.setItem(' . $js('shell.at.' . $area) . ',location.pathname+location.search+location.hash);'
        . 'location.replace(' . $js($shell . '#' . $area) . ');'
        . '}).catch(()=>{});'
        . '}catch{}})();</script>';
}
