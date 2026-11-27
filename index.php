<?php
/**
 * The map. Fills the whole screen; everything else happens in
 * assets/js/map.js via the REST interface.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/chat_window.php';
require_once __DIR__ . '/includes/sequences.php';
require_once __DIR__ . '/includes/find_fields.php';
require_once __DIR__ . '/includes/person_names.php';
require_once __DIR__ . '/includes/species_packages.php';   // species_group_shapes() for the start data
require_once __DIR__ . '/includes/taxonomy.php';
require_once __DIR__ . '/includes/map_layers.php';
require_once __DIR__ . '/includes/shapes.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/cloud.php';
require_once __DIR__ . '/includes/photo_window.php';
require_once __DIR__ . '/includes/plate_window.php';
require_once __DIR__ . '/includes/point_window.php';
require_once __DIR__ . '/includes/gallery_windows.php';
require_once __DIR__ . '/includes/offline_map.php';

// THE MAP CARRIES TWO KINDS OF PEOPLE since the excursions (stage 4,
// 22.08.2026): the logged-in user as ever, and the excursion GUEST whose
// session the join page set (pages/excursion.php). The guest gets the
// SAME map — full screen, location, point setting, offline queue — but
// stripped start data: no lists, no containers, no friends, and the API
// (points.php) holds him to his own finds of his running excursion.
require_once __DIR__ . '/includes/excursions.php';
// The theme and accent tiles for the guest's settings window — the
// same partial the settings tab uses.
require_once __DIR__ . '/includes/appearance.php';
$actor = null;
$me = user_current();
if ($me === null) {
    $actor = excursion_actor($pdo);
    if ($actor === null) {
        $me = login_required();
    }
}
$guest = $actor !== null;

// Are the time buckets ready? Asked once and needed twice — and not asked
// twice, because the answer must not differ between the two places.
// Guests never sync via buckets: their few finds come along in one piece,
// and the bucket API does not know them.
$bucketsReady = !$guest && bucket_ready($pdo);

// Hand over the initial state so the map can appear without an
// additional request.
$bucketStatus = $bucketsReady
    ? bucket_status($pdo, (int) $me['id'])
    : ['finger' => [], 'counts' => []];

/* THE RECORDED TRACKS THE SAME WAY (11.09.2026): their fingerprints ride
   in the document, so a start without changes costs no request for them
   either. Tied to the point buckets on purpose — where those are not
   ready, the sync machinery on the device does not run at all, and a
   track bucket nobody fetches would only be a promise. Guests record
   nothing. */
require_once __DIR__ . '/includes/tracks.php';
$tracksReady = $bucketsReady && tracks_ready($pdo);
$trackStatus = $tracksReady
    ? tracks_bucket_status($pdo, (int) $me['id'])
    : ['finger' => [], 'counts' => []];

$startData = [
    'baseUrl' => BASE_URL,
    'csrf'     => csrf_token(),
    // Which offline map files this installation keeps in pCloud (config.php,
    // includes/offline_map.php) — the vector map offers an area download of
    // its base map only when 'europe' is among them.
    'offlineMap' => $guest ? [] : offline_map_files(),
    // The user's own id is included because the map has to sift out its
    // own among shared points: whoever hung their own list into a container
    // already has those points — as their own, editable ones.
    'user' => $guest
        // The guest is nobody's account: id 0 can never collide with a
        // real one, and his name is what the join page took — it fills
        // the finder, which is the one thing the registration was for.
        ? ['id' => 0, 'name' => $actor['name'], 'isAdmin' => false, 'finder' => $actor['name']]
        : ['id' => (int) $me['id'], 'name' => user_display_name($me), 'isAdmin' => is_admin($me),
                   // For the finder record field: a find belongs to a
                   // person with a name, not to a login handle. The nick
                   // remains the fallback in case no name is on file.
                   'finder' => user_full_name($me) !== '' ? user_full_name($me) : user_display_name($me)],
    // Who and what the map is serving — map.js reads only the yes/no,
    /* THE EXCURSION A LOGGED-IN PARTICIPANT IS MAPPING FOR
       (24.08.2026). Not a guest — he stays himself, his finds stay in his
       own stock; they merely carry the excursion's reference, and the
       leader's right hangs on that. The badge on the map says which one,
       in three words, and can be switched off there. */
    'mapping' => (static function () use ($pdo, $guest): ?array {
        // The lookup — and the closing of the window — lives in
        // excursions.php: the onboarding asks the same question. The
        // shape is the one joining from the map answers with, built in
        // one place (excursion_mapping_payload).
        $e = $guest ? null : excursion_mapping_current($pdo);
        return $e === null ? null : excursion_mapping_payload($pdo, $e);
    })(),
    /* THE EXCURSIONS THE MAP MAY OFFER TO MAP ALONG FOR
       (13.09.2026): the ones he leads and the ones he joined, with their
       windows — the badge appears when a window is open (map.js,
       drawMappingOffer), and the map keeps the clock. */
    'mappingOffers' => $guest ? [] : excursion_mapping_offers($pdo, (int) $me['id']),
    // The names one has written under Finder and Bestimmer, for their
    // suggestions (person_names.php, pointwindow.js peopleOffers). A guest
    // has written none.
    'people' => $guest ? [] : person_names_written($pdo, (int) $me['id']),
    // the rest feeds the status line so the guest knows where he is.
    'guest' => $guest ? [
        'excursion' => (string) $actor['excursion']['name'],
        'until'      => excursion_readable_time((string) $actor['excursion']['ends_at']),
        // Ahead of the window (13.09.2026): the status line says when it
        // starts, and the point button says so instead of trying — the
        // server would refuse the find by its time anyway.
        'from'       => excursion_readable_time((string) $actor['excursion']['starts_at']),
        'starts_at'  => (string) $actor['excursion']['starts_at'],
        // The leader's photo policy (stage 6a) — the map only decides
        // whether the capture button appears; the server enforces the
        // measure again at every upload.
        'photosAllowed' => !empty($actor['excursion']['photos_allowed']),
        'photoLimit'    => $actor['excursion']['photo_limit'] === null
            ? null : (int) $actor['excursion']['photo_limit'],
        // The leader's offline area, if he set one: the onboarding's
        // third step hands over here with ?offline=… and map.js runs the
        // same download the Offline tab runs — the tile addresses come
        // from its layer machinery, which lives only there.
        'area' => excursion_area($pdo, $actor['excursion']),
    ] : null,
    // The tracks switch only where the server can hold tracks — a switch
    // over an empty store would promise what migration 0084 has not
    // delivered yet (see the registry entry).
    'layers'   => array_values(array_filter(map_layers_for_js(),
        static fn(array $l): bool => $l['type'] !== 'tracks' || $tracksReady)),
    'lists'   => $guest ? [] : lists_load($pdo, (int) $me['id']),
    'folders' => $guest ? [] : folders_load($pdo, (int) $me['id']),
    // The named scale presets (migration 0089) — a handful of rows, and
    // the viewer's chooser has to work in the woods off the cached page.
    'photo_scales' => $guest ? [] : photo_scales($pdo, (int) $me['id']),
    // The devices those presets belong to (0183), the empty ones too —
    // the microscope window may calibrate into one.
    'scale_devices' => $guest ? [] : scale_devices($pdo, (int) $me['id']),
    // How the measuring overlay dresses (0091) — per account, so the
    // phone draws like the desk. Guests measure in the defaults.
    /* The language catalog for i18n.js — null for German (t() is then
       the identity); rides the start payload so the offline-cached map
       keeps its language. */
    'lang_catalog' => lang_current() === 'de' ? null : lang_catalog(),
    'measure_style' => $guest || !column_exists($pdo, 'map_users', 'measure_style')
        ? measure_style_read(null)
        : measure_style_read($me['measure_style'] ?? null),

    // THE POINTS ARE NO LONGER IN HERE — only the fingerprints of their
    // time buckets. Measured 525 bytes per point: at 20,000 that would be 10 MB
    // in a document that carries no-store and therefore would go over the
    // wire on EVERY call. The fingerprints are 2.6 kB for ten years.
    //
    // They come WITH the document and not via a separate request: the device
    // can thus compare what it has immediately, without asking yet again.
    // Reconciling a warm store therefore costs zero requests.
    'bucketsReady' => $bucketsReady,
    // Fingerprint AND count per bucket — why both is written at
    // bucket_status(). Asked ONCE and read out twice: the function makes
    // one query, and called twice it would be the same one twice.
    'buckets'       => $bucketStatus['finger'],
    'bucketCounts' => $bucketStatus['counts'],
    // And the tracks' — see above. Empty until the recorder app has
    // uploaded something, and empty for a guest.
    'tracksReady'       => $tracksReady,
    'trackBuckets'      => $trackStatus['finger'],
    'trackBucketCounts' => $trackStatus['counts'],

    // AS LONG AS stand IS MISSING, EVERYTHING COMES ALONG AS BEFORE. The code
    // is on the server before the migration; in the window in between there
    // are no fingerprints, and an empty list would mean "you have no
    // points" to the device. An empty map is the worst imaginable sight.
    'points'   => $guest
        ? excursion_guest_points_full($pdo, $actor)
        : ($bucketsReady ? [] : points_load($pdo, (int) $me['id'])),
    // The code is on the server before the migration — that is the order of
    // deployment. In the window in between there would be photo buttons
    // that cannot achieve anything. Better none at all.
    //
    // GUESTS ARE NO LONGER EXCLUDED HERE (24.08.2026). The „!$guest" dates
    // from stage 4, when their photos were still „a later stage" — stage
    // 6a built them, and this one line stayed behind: it hid „Aufnehmen"
    // before the excursion's own rule (guest.photosAllowed) could even be
    // asked, so a leader who allowed photos saw them refused (the maintainer
    // found it). Whether a GUEST may take pictures is the excursion's
    // word, and the server checks the measure again at every upload.
    'photos'   => photos_ready($pdo),
    // The same for areas (migration 0034): without the column the server
    // would silently not accept the outline. An outlined area that
    // reappears as an ordinary point would be worse than a button that
    // does not exist yet. A guest sets points, not areas.
    'outlines' => !$guest && column_exists($pdo, 'map_points', 'outline'),

    // Field timestamps (migration 0039): only once the column is there may
    // the device send just the touched fields when saving. Before that the
    // full form MUST come — a partial addition replaced the whole block on
    // the old server, and the code is live before the migration.
    'fieldTimesReady' => field_times_ready($pdo),
    // Overwritten changes nobody has seen yet — for the bar, without a
    // separate request at startup.
    'conflicts' => $guest ? 0 : overwritten_count($pdo, (int) $me['id']),
    // The fixed field set of every species list (includes/find_fields.php).
    // It lives in code, not in the database — the map gets it delivered
    // here and puts it before its own root fields.
    'find_fields' => find_fields(),
    /* THE FIND-NUMBER SERIES (26.09.2026): the pattern and where the
       counter stands, so the point window's number field can say — offline
       too — which number of the series one types, whether it is free, and
       which is next (map.js, drawNumberHint). Null without a pattern, and
       for a guest, who keeps no series. */
    'findNumber' => $guest ? null : (static function () use ($pdo, $me): ?array {
        require_once __DIR__ . '/includes/find_number.php';
        return find_number_ready($pdo) ? find_number_series($pdo, (int) $me['id']) : null;
    })(),
    /* And the numbers the trash holds — a find put back keeps its number,
       so the number field must not call it free (drawNumberHint). */
    'findNumberTrash' => $guest ? [] : (static function () use ($pdo, $me): array {
        require_once __DIR__ . '/includes/find_number.php';
        return find_number_trash($pdo, (int) $me['id']);
    })(),

    // The subjects' shapes and names — the suggestion list draws the sign
    // before a name that came out of the stored package or out of the
    // user's own finds, where no server answer brought it along.
    'groupShapes' => species_group_shapes(),
    // … and the lists' origins with their dates, for the star behind a
    // current name: whose spelling is this, and how old.
    'speciesOrigins' => species_origins(),
    // Species no list knows yet — one's own and those shared with one
    // (working_names.php); the title field offers them ahead of the lists.
    'workingNames' => $guest ? [] : (static function () use ($pdo, $me): array {
        require_once __DIR__ . '/includes/working_names.php';
        return working_names_for_start($pdo, (int) $me['id']);
    })(),


    // Is a cloud connected? The detail window shows its section only then —
    // a button that leads to "no cloud connected" is one button too many.
    // Deliberately only the yes/no: what is connected and where to belongs in
    // the administration, not into every map call. „Connected" means a cloud
    // that takes PICTURES — cloud_pictures_ready() decides, the one place the
    // server asks it too (the release list, CLOUD_PICTURE_PROVIDERS).
    'cloudConnected' => !$guest && cloud_ready($pdo)
        && cloud_pictures_ready($pdo, (int) $me['id']),
    // And may the BROWSER upload into it — „Auswählen", the recordings?
    // A second list (cloud.php says why): a cloud can take the voucher
    // photos the server pushes before it takes the browser's own uploads.
    'cloudUpload' => !$guest && cloud_ready($pdo)
        && cloud_uploads_ready($pdo, (int) $me['id']),
    // Whether „Entfernen" may offer its strong model: only where its files lie on this server (helpers.php).
    'strongModel' => inpaint_strong_offered(),
    /* The appearance defaults, for the guest's settings window: the
       default is the ABSENCE of the attribute, so the wiring has to know
       which value means "nothing set" (appearance.js). The preamble in
       the head reads the same constants — one source, two readers. */
    'themeDefault'  => THEME_DEFAULT,
    'accentDefault' => ACCENT_DEFAULT,
];

// The shared containers (migration 0042): structure and bucket fingerprints
// come with the document, as with the user's own stock — a reconciliation
// without changes then costs no request. The points themselves the device
// fetches via bucket.php?container=…; only while the buckets are missing
// (migration 0038 not there), they come along in one piece, for the same
// reason as the user's own.
require_once __DIR__ . '/includes/container.php';
require_once __DIR__ . '/includes/chat.php';
$startData['shared'] = [];
if (!$guest && container_ready($pdo)) {
    // The unread messages of ALL containers in one query — see chat_unread().
    // It runs on every load of the map, so it must not be one round trip per
    // container for a number that is usually zero.
    $chatUnread = chat_ready($pdo) ? chat_unread($pdo, (int) $me['id']) : [];

    foreach (container_mine($pdo, (int) $me['id']) as $c) {
        $containerStatus = $bucketsReady
            ? container_bucket_status($pdo, $c['id'], (int) $me['id'])
            : ['finger' => [], 'counts' => []];
        $startData['shared'][] = [
            'id'        => $c['id'],
            'name'      => $c['name'],
            'owner'  => $c['owner_name'],
            // WHOSE IT IS, as a number: the sidebar says „meine" for one's
            // own container instead of repeating one's own name, and a name
            // comparison would break on the first namesake.
            'owner_id' => (int) $c['owner_user_id'],
            'shares' => container_shares($pdo, $c['id'], (int) $me['id']),
            'buckets'     => $containerStatus['finger'],
            'counts'    => $containerStatus['counts'],
            'points'    => $bucketsReady ? [] : container_points($pdo, $c['id'], [], (int) $me['id']),
            // The members' walks that travel with their outings (tracks,
            // step two) — set below, once tracks.php is loaded.
            'tracks'    => ['finger' => [], 'counts' => []],
            // For the info window at the container row (sidebar): the same
            // readable sentence as in the administration, here computed
            // once instead of requested on every click. null means
            // "no birth filter" — the same meaning as there.
            'filterText' => container_filter_readable(container_filter($pdo, $c['id'])),
            // How many messages wait, and up to which number. The badge on
            // the sidebar row lives off the first, the window off the
            // second: it may report as read only what it has really shown.
            'chat'       => $chatUnread[$c['id']] ?? ['unread' => 0, 'latest' => 0],
            // WHO IS IN IT. Both windows want it — the info window writes
            // them out, the chat shortens them. It comes along with the
            // document because it is a handful of names that change about
            // never, and a request for it would be one round trip for
            // three words.
            'members' => container_members($pdo, (int) $c['id']),
            // How I call it myself — null when I have not renamed it. The
            // sidebar shows this one, the info window both.
            'own_name' => $c['own_name'] ?? null,
            /* A LIST SHARE (migration 0160) names its list: the sidebar
               draws it under „Von <Name>" or „Gemeinsame Listen" instead
               of as a box. null for an old box. */
            'list_id' => list_share_list($pdo, (int) $c['id']),
            // Whether its chat is mine to read (list_share_chat_member) —
            // a friend who only looks talks in the friend chat instead.
            'chat_member' => chat_member($pdo, (int) $me['id'], (int) $c['id']),
        ];
    }
    // Lists I have just inherited as Listenadministrator — one window each
    // at the start (09.10.2026).
    $startData['list_admin_notices'] = list_share_admin_notices($pdo, (int) $me['id']);
}

/* THE FINDS FRIENDS SHOW ME, AND THE ONES I SHOW (migration 0149,
   includes/point_hints.php). 'givers' are the rows „Von <Name>" under
   „Geteilt", 'buckets'/'counts' their fingerprints as a container carries
   them (the points come via bucket.php?hints=1), 'given' my own finds and
   to whom I show them — the point window's tile draws its ticks from it.
   null while the table is missing: then neither row nor tile appears. */
require_once __DIR__ . '/includes/point_hints.php';
$startData['hints'] = null;
if (!$guest && point_hints_ready($pdo)) {
    $hintStatus = $bucketsReady
        ? point_hints_bucket_status($pdo, (int) $me['id'])
        : ['finger' => [], 'counts' => []];
    $startData['hints'] = [
        'givers'  => point_hints_givers($pdo, (int) $me['id']),
        'buckets' => $hintStatus['finger'],
        'counts'  => $hintStatus['counts'],
        'points'  => $bucketsReady ? [] : point_hints_points($pdo, (int) $me['id']),
        // An object even when empty — JSON would make [] of an empty map,
        // and the tile looks the point id up as a key.
        'given'   => (object) ($hintMaps = point_hints_given_maps($pdo, (int) $me['id']))['all'],
        // Who sees a find of mine or one I co-own because SOMEBODY ELSE ticked
        // him — a co-owner may not take that back (point_hint_set).
        'givenOthers' => (object) $hintMaps['others'],
    ];
}

// The friends, for the personal chat (migration 0068): the speech bubble
// at the sidebar's corner lists them, each with their unread count. A
// handful of names that change about never — they come with the document
// for the same reason the container members do. NOT the requests: an open
// request is no conversation yet (friend_chat_room says the same).
require_once __DIR__ . '/includes/friends.php';
$startData['friends'] = [];
if (!$guest && friends_ready($pdo)) {
    $friendUnread = friend_chat_unread($pdo, (int) $me['id']);
    $overview = friends_overview($pdo, (int) $me['id']);
    foreach ($overview['friends'] as $f) {
        $startData['friends'][] = [
            'id'   => (int) $f['user_id'],
            'name' => $f['display_name'],
            'chat' => $friendUnread[(int) $f['user_id']] ?? ['unread' => 0, 'latest' => 0],
        ];
    }
    // The answers that arrived while the map was closed — with the
    // document, so the badge stands at once and not after the first poll.
    $startData['friendAccepted'] = friends_accepted_unseen($pdo, (int) $me['id']);
    /* AND THE REQUESTS TO ME, for the same reason (08.11.2026:
       „Freundschaftsanfragen werden gar nicht auf der Karte angezeigt,
       so wie Freundschaftsbestätigungen"). They came only with the poll —
       thirty seconds after opening at best, never for whoever had no
       friend, share or request of his own to start it. The same shape
       api/v1/chat.php sends. */
    $startData['friendRequests'] = array_map(static fn(array $r): array => [
        'id' => (int) $r['id'], 'name' => (string) $r['display_name'],
    ], $overview['incoming']);
}

/* THE EXCURSIONS ONE LEADS, AND THEIR FINDS (26.08.2026:
   „eine Liste, nur Exkursionen, wo erstmal alles drin ist").
   
   THE POINTS ARE FOREIGN, and they have to be: the guest account owns
   them, so points_load() — which asks for p.user_id — can never hand them
   out. They travel through the same machinery as the container points,
   which already knows markers with a ring, the drag right and every
   filter.
   
   ONE'S OWN FINDS ARE NOT AMONG THEM (excursion_points explains why), and
   they do not need to be: an own point carries the excursion reference
   too, and the sidebar reads it there.
   
   THE NAMES COME WHOLE, THE ROWS ARE SHOWN IN TENS. A name and a date
   weigh nothing, so fetching more of them later would be a round trip for
   a few hundred bytes; the sidebar shows the last ten and unfolds the
   rest on request (map.js). What can grow heavy are the POINTS — and if
   it ever does, the answer is the same one the containers already use:
   buckets. */
require_once __DIR__ . '/includes/excursions.php';
$startData['excursions']      = [];
$startData['excursionPoints'] = [];
/* The others' walks come with their fingerprints, per excursion and per
   container, once the tracks know their outing (migration 0099). */
require_once __DIR__ . '/includes/tracks.php';
$trackScopes = !$guest && tracks_assignment_ready($pdo);
if (!$guest && excursions_ready($pdo)) {
    foreach (excursions_mine($pdo, (int) $me['id']) as $e) {
        $status = excursion_bucket_status($pdo, (int) $e['id'], (int) $me['id']);
        $startData['excursions'][] = [
            'id'         => (int) $e['id'],
            'name'       => (string) $e['name'],
            'starts_at'  => $e['starts_at'],
            // Whose it is — the row says „von <Name>" for a co-led one,
            // the same distinction the administration draws.
            'is_main'    => (bool) $e['is_main'],
            'leader'     => $e['leader_name'] ?? null,
            // The fingerprints, exactly as a container carries them: an
            // unchanged excursion then costs no request at startup.
            'buckets'    => $status['finger'],
            'counts'     => $status['counts'],
            // And the same for the others' walks on it (step two of the
            // tracks, 12.09.2026) — reconciled like the finds, without a
            // request when nothing moved.
            'tracks'     => $trackScopes
                ? tracks_bucket_status_scoped($pdo, track_scope_excursion((int) $e['id'], (int) $me['id']))
                : ['finger' => [], 'counts' => []],
        ];
    }
    /* THE POINTS ONLY WITHOUT BUCKETS. With them the device fetches per
       excursion and keeps what it has — which is the whole point of the
       change (26.08.2026: „für den Offline-Betrieb"). Without
       them — migration 0038 missing — they come along in one piece, for
       the same reason as one's own. */
    $startData['excursionPoints'] = $bucketsReady
        ? [] : array_values(excursion_points($pdo, (int) $me['id']));
}
if ($trackScopes) {
    foreach ($startData['shared'] as &$sharedEntry) {
        $scope = track_scope_container($pdo, (int) $sharedEntry['id'], (int) $me['id']);
        if ($scope !== null) {
            $sharedEntry['tracks'] = tracks_bucket_status_scoped($pdo, $scope);
        }
    }
    unset($sharedEntry);
}
/* THE FINDS I CO-OWN (migration 0098, 11.09.2026) — whole, in the
   document, like the excursion finds without buckets: rare, and the
   holder's point with all its pictures and my own filing (point_owners.php).
   They join the foreign store on the client (addExcursionPoints). */
require_once __DIR__ . '/includes/point_owners.php';
$startData['coOwnedPoints'] = $guest ? [] : array_values(co_owned_points($pdo, (int) $me['id']));

/* THE TOUR NAMES THAT WAIT FOR POINTS (migration 0073): a tour created
   empty in Organisieren carries no point yet, so nothing on the device could
   know it — without this list it would be unreachable in the field,
   which is the one place it was created FOR. The picker in the
   list-selection window offers them (selectLists in map.js). A handful
   of short strings, with the document like the friends. */
require_once __DIR__ . '/includes/organize_content.php';
$startData['tourNames'] = $guest ? [] : organize_tour_names($pdo, (int) $me['id']);

/**
 * The X that clears a search field.
 *
 * THE DRAWING LIVES IN includes/icons.php, like every icon of the
 * interface — there was once a separate one here, and it was already a
 * different one from that of the close button two screen lines further
 * down (one X went from 6 to 18, the other from 7 to 17).
 *
 * START HIDDEN. An X next to an empty field promises to clear away
 * something where nothing lies. It only becomes visible once something is
 * really in there — map.js takes care of that.
 */
function clear_button(string $id, string $label): string
{
    return '<button type="button" class="field-clear" id="' . h($id) . '" hidden'
         . ' aria-label="' . h($label) . '" title="' . h($label) . '">'
         /* No stroke option: it was still called 'strich' after the
            English move and thus silently ignored — and the thin registry
            stroke is the wanted one anyway, the X is supposed to step
            back (.field-clear in app.css thins it further). */
         . icon('close')
         . '</button>';
}

/* THE VERSION MARKERS are per file since 24.09.2026 — asset_url() in
   helpers.php says why. */
?>
<!DOCTYPE html>
<html lang="<?= h(lang_current()) ?>">
<head>
    <meta charset="UTF-8">
    <?php /* THE MAP OPENED AT THE DESK goes into the frame page (includes/shell.php,
         way 1; the same lines page_header() writes for the other areas). A
         guest of an excursion has no areas to walk between and stays here. */
    if (!$guest && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        echo shell_entry_script('map');
    } ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <title>YASAIMAPP<?= IS_LOCAL ? ' ' . h(t('(lokal)')) : '' ?></title>
    <script>
    /* The same preamble as in page.php: theme and accent onto <html> before
       the browser paints for the first time — otherwise the default would
       flash. */
    try {
        /* ONE-TIME TAKEOVER, INLINE AND FIRST: the bundle and its keys move
           to English names. It cannot wait for legacy.js — this very block
           reads the theme before the first paint. The same block sits in
           includes/page.php; two copies are the price of the preamble. */
        if (localStorage.getItem('map.settings') === null) {
            const old = JSON.parse(localStorage.getItem('karte.einstellungen')
                ?? localStorage.getItem('pilzkarte.einstellungen') ?? '{}') || {};
            for (const [a, z] of [['pilzkarte.ansicht', 'ansicht'], ['pilzkarte.grundkarte', 'grundkarte']]) {
                const r = localStorage.getItem(a);
                if (r !== null && !(z in old)) { try { old[z] = JSON.parse(r); } catch { old[z] = r; } }
            }
            const names = { ansicht: 'view', auflagen: 'overlays', eimerAus: 'bucketsOff',
                filterOffen: 'filterOpen', grundkarte: 'baseMap', 'kachel-cors': 'tile-cors',
                kopfsucheOffen: 'headerSearchOpen', legendenAus: 'legendsOff',
                legendenZu: 'legendsCollapsed', leisteBereicheZu: 'sidebarSectionsCollapsed',
                leisteSeite: 'sidebarPage', leisteZu: 'sidebarClosed',
                sichtbarEinzeln: 'visibleIndividually', sichtbareGeteilte: 'visibleShared',
                sichtbareListen: 'visibleLists', sort_order: 'sorting', standort: 'location',
                suchbegriffe: 'searchTerms', search_text: 'searchText', thema: 'theme',
                akzent: 'accent', verwaltungReiter: 'administrationTab',
                zugeklappteGeteilte: 'collapsedShared', zugeklappteListen: 'collapsedLists' };
            const fresh = {};
            for (const [n, w] of Object.entries(old)) fresh[names[n] ?? n] = w;
            if (Object.keys(fresh).length) localStorage.setItem('map.settings', JSON.stringify(fresh));
            for (const n of ['karte.einstellungen', 'pilzkarte.einstellungen',
                             'pilzkarte.ansicht', 'pilzkarte.grundkarte']) localStorage.removeItem(n);
        }
        const stored = JSON.parse(localStorage.getItem('map.settings') || '{}');
        /* The retired names, the same map as in includes/page.php — two
           copies are the price of the preamble (see there). */
        const theme = { dunkel: 'charcoal', hell: 'warmlight',
                        papier: 'warmlight', mitternacht: 'charcoal',
                        kohle: 'charcoal', warmlicht: 'warmlight',
                        bernstein: 'amber', schiefer: 'slate' }[stored.theme] ?? stored.theme;
        const accent = { gold: 'orange', pink: 'purple',
                         moosgruen: 'steelblue', jade: 'red',
                         stahlblau: 'steelblue', rot: 'red',
                         minze: 'mint', lila: 'purple' }[stored.accent] ?? stored.accent;
        if (theme && theme !== <?= json_encode(THEME_DEFAULT) ?>) document.documentElement.dataset.theme = theme;
        if (accent && accent !== <?= json_encode(ACCENT_DEFAULT) ?>) document.documentElement.dataset.accent = accent;
    } catch (e) { /* private window or the like — then it's the default appearance */ }
    </script>
    <link rel="stylesheet" href="<?= h(asset_url('assets/vendor/leaflet/leaflet.css')) ?>">
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/map.css')) ?>">
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/chat.css')) ?>">
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/pointwindow.css')) ?>">
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/photowindow.css')) ?>">
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/plates.css')) ?>">

    <?php /* Installable as an app. The manifest is PHP because the path is only
         known at runtime — locally /map/, live perhaps its own
         subdomain. */ ?>
    <link rel="manifest" href="<?= h(url('pages/manifest.webmanifest.php')) ?>">
    <meta name="theme-color" content="#14181c">
    <link rel="icon" href="<?= h(url('assets/symbole/symbol-32.png')) ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= h(url('assets/symbole/symbol-180.png')) ?>">
</head>
<?php /* The guest class hides what a guest has no door to — lists tab,
     administration, logout (his "account" is the session, and it ends
     with the excursion's window on its own). */ ?>
<body class="page-full<?= $guest ? ' guest-access' : ' has-app-bar' ?>">

<div class="app">

    <?php if (!$guest): ?>
    <?php /* THE BAR OVER THE WHOLE PAGE, AT THE DESK (09.10.2026):
         the name, the tabs, and the two ways out on the right. What the
         sidebar's head carried of that — name, help, sign out, the gear to
         the administration — stands here instead and is hidden there
         (.bar-moved, map.css). A guest has no tabs to walk between (no
         administration, no finds' browser), so he gets no bar. On the phone
         the bar is not drawn at all; the sidebar head stays as it was. */ ?>
    <header class="app-bar">
        <a class="brand" href="<?= h(url()) ?>" title="<?= h(t('Zur Karte')) ?>">
            <?= icon('map', ['class' => 'brand-icon']) ?>
            <span class="brand-text">YASAIMAPP</span>
        </a>
        <?php app_tabs('map'); ?>
        <?php /* THE COORDINATE SEARCH LIVES HERE AT THE DESK
             (09.10.2026: „die Suche kannst du auch in die Kopfzeile
             packen"). The block itself stays where it was, below the
             sidebar's head, and map.js (placeHeaderSearch) carries it into
             this slot on a wide screen and back on a narrow one — one set of
             ids, one piece of code behind it. */ ?>
        <div class="app-bar-search" id="app-bar-search"></div>
        <div class="app-bar-tools">
            <?php if ($startData['friends'] !== []): ?>
            <?php /* THE CHAT IN THE BAR AT THE DESK (10.10.2026: „leg
                 mir … den Chat-Button auch noch mit in die Kopfleiste"). The
                 same door as the bubble over the map, which hides at the
                 desk while this one stands (map.css): one flyout, placed
                 under whichever bubble opened it (bindFriendChat). */ ?>
            <button type="button" class="symbol-button small chat-entry" id="friend-chat-entry-bar"
                    title="<?= h(t('Chats mit Freunden')) ?>" aria-label="<?= h(t('Chats mit Freunden')) ?>" aria-expanded="false">
                <?= icon('speech-bubble') ?>
            </button>
            <?php endif; ?>
            <?php /* FULL SCREEN (fullscreen.js): the two drawings, one shown per state. */ ?>
            <button type="button" class="symbol-button small fullscreen-button" data-fullscreen hidden
                    title="<?= h(t('Vollbild')) ?>" aria-label="<?= h(t('Vollbild')) ?>" aria-pressed="false">
                <?= icon('fullscreen', ['class' => 'when-off']) ?><?= icon('fullscreen-exit', ['class' => 'when-on']) ?>
            </button>
            <a class="symbol-button small" href="<?= h(url('pages/help.php')) ?>"
               title="<?= h(t('Hilfe')) ?>" aria-label="<?= h(t('Hilfe')) ?>">
                <?= icon('help') ?>
            </a>
            <form method="post" action="<?= h(url('logout.php')) ?>" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="symbol-button small"
                        title="<?= h(t('Abmelden')) ?>" aria-label="<?= h(t('Abmelden')) ?>">
                    <?= icon('sign-out') ?>
                </button>
            </form>
        </div>
    </header>
    <?php endif; ?>

    <?php /* Sidebar -------------------------------------------------------
         ONE COLUMN, TWO TABS — the same on every screen.

         Before, lists and points stood side by side (desktop) or one above
         the other (phone). The stacking was the worse part: the list column
         was capped at half height, and its footer with "Verwaltung" thus
         landed in the middle of the picture. And it does not scale — the
         points page gets filters by date, substrate and finder, the lists
         page can have fifty entries. Two things that both grow do not fit
         on top of each other.

         They are also two different steps of work: first choose WHAT you
         want to see, then search within it. One after the other, not at
         the same time.

         NO "view points" BUTTON at the bottom. It would be a second footer
         and take up space permanently — of all places on the page where you
         scroll through fifty lists. Instead the tab carries the number:
         whoever checks a list sees it change, and that says more than a
         button.
    */ ?>
    <aside class="sidebar" id="sidebar">

        <?php /* The header row is the toolbar.
             Before, only the word "Karte" stood here — a heading over
             something that is the whole sidebar anyway. Administration and
             logout hung at the foot of the list column instead, where they
             had nothing to do with the lists.

             Icons instead of words, because it is four things side by side
             and 320 pixels wide. Each carries its name in title and in
             aria-label — visible on hover, audible for screen readers. */ ?>
        <div class="sidebar-header">
            <a class="brand bar-moved" href="<?= h(url()) ?>" title="<?= h(t('Zur Karte')) ?>">
                <?= icon('map', ['class' => 'brand-icon']) ?>
                <span class="brand-text">YASAIMAPP</span>
            </a>


            <?php /* All icons from the library (includes/icons.php), all at
                 18 pixels: they stand next to each other in a row, and two
                 icons of different sizes would stand out there at once. */ ?>
            <div class="header-tools">
                <?php /* THE ORDER IS A DECISION (09.09.2026):
                     „Exit. Help. Suche. Verwaltung", from left to right.
                     What comes to stand side by side that way: on the
                     left the two that LEAD OUT of the map — logging out
                     and reading up —, on the right the two that work on
                     what stands here. The × for folding away is gone
                     entirely; the grip at the edge does that now. */ ?>
                <?php if (!$guest): ?>
                    <?php /* A FORM, NOT A LINK (security review, 23.09.2026): a
                         plain GET let any foreign page log a user out with
                         an <img src=".../logout.php">. display: contents keeps
                         the button in the header row as if the form were not
                         there. */ ?>
                    <form method="post" action="<?= h(url('logout.php')) ?>" class="logout-form bar-moved">
                        <?= csrf_field() ?>
                        <button type="submit" class="symbol-button small"
                                title="<?= h(t('Abmelden')) ?>" aria-label="<?= h(t('Abmelden')) ?>">
                            <?= icon('sign-out') ?>
                        </button>
                    </form>

                    <?php /* The help beside the administration, not inside it:
                         one comes here from the MAP, and whoever has to
                         open a tool first to find out how the tool works
                         has already been sent one step too far. */ ?>
                    <a class="symbol-button small bar-moved" href="<?= h(url('pages/help.php')) ?>"
                       title="<?= h(t('Hilfe')) ?>" aria-label="<?= h(t('Hilfe')) ?>">
                        <?= icon('help') ?>
                    </a>
                <?php endif; ?>

                <button type="button" class="symbol-button small bar-moved" id="header-search-button"
                        title="<?= h(t('Koordinate suchen')) ?>" aria-label="<?= h(t('Koordinate suchen')) ?>" aria-expanded="false">
                    <?= icon('magnifier') ?>
                </button>

                <?php if ($guest): ?>
                    <?php /* The guest's own gear (24.08.2026): the
                         administration stays closed to him, but the two
                         things that are his — how the map looks, and who
                         he is on this excursion — belong within reach. */ ?>
                    <button type="button" class="symbol-button small" id="guest-settings-open"
                            title="<?= h(t('Einstellungen')) ?>" aria-label="<?= h(t('Einstellungen')) ?>">
                        <?= icon('gear') ?>
                    </button>
                <?php else: ?>
                    <a class="symbol-button small bar-moved" href="<?= h(url('pages/administration.php')) ?>"
                       title="<?= h(t('Verwaltung')) ?>" aria-label="<?= h(t('Verwaltung')) ?>">
                        <?= icon('gear') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php /* The coordinate search.
             It once stood above the point list and filtered along there.
             That was a conflation: the filter searches what you have, the
             coordinate search leads to a place where nothing is yet.
             Two different moves.

             Folded out instead of permanently visible, because it is
             rarely needed — and when it is, then deliberately. */ ?>
        <div class="header-search" id="header-search" hidden>
            <?php /* The placeholder no longer enumerates everything the field
                 understands. The list was complete and for exactly that
                 reason unreadable: whoever reads it reads six terms instead
                 of starting to type. What it can do now lives in the title —
                 there it bothers nobody and is there when you look for it. */ ?>
            <div class="field-with-x">
                <input type="search" id="coord-search" autocomplete="off" spellcheck="false"
                       placeholder="<?= h(t('Suche …')) ?>"
                       title="<?= h(t('Ort, Koordinate, Plus Code, UTM, Messtischblatt oder Kartenlink')) ?>">
                <?= clear_button('coord-search-clear', t('Suche leeren')) ?>
            </div>
            <div class="coord-match" id="coord-results" hidden></div>
        </div>

        <div class="sidebar-tabs" role="tablist" aria-label="<?= h(t('Listen oder Punkte')) ?>">
            <?php /* The lists tab carries its number too: how many lists are
                 currently selected, own and shared together. From the
                 points page you can see whether a filter is active —
                 otherwise you wonder about missing points there and look
                 for the cause in the wrong place. */ ?>
            <button type="button" role="tab" id="sidebar-tab-lists" aria-controls="sidebar-section-lists"
                    aria-selected="true"><?= h(t('Listen')) ?> <span class="tab-count" id="sidebar-tab-list-count">0</span></button>
            <button type="button" role="tab" id="sidebar-tab-points" aria-controls="sidebar-section-points"
                    aria-selected="false"><?= h(t('Punkte')) ?> <span class="tab-count" id="sidebar-tab-count">0</span></button>
        </div>

        <?php /* Both pages lie side by side in one track and are shifted. That
             way you can swipe between them without content being rebuilt —
             and the scroll position of each page is preserved. */ ?>
        <div class="sidebar-track" id="sidebar-track">

            <section class="sidebar-page" id="sidebar-section-lists" role="tabpanel" aria-labelledby="sidebar-tab-lists">
                <nav class="list-section" id="list-section" data-scroll-remember="map:lists">
                    <?php /* filled by map.js */ ?>
                </nav>
            </section>

            <section class="sidebar-page" id="sidebar-section-points" role="tabpanel" aria-labelledby="sidebar-tab-points">
                <?php /* THE WHOLE FILTER AREA FOLDS AWAY (the call,
                     14.08.2026). Folded does not mean off: what applies
                     keeps applying — the button then lights up and carries
                     the number of conditions, otherwise a short list would
                     be a riddle. */ ?>
                <div class="filter-header">
                    <?php /* WITH THE FOLD TRIANGLE (12.09.2026: „eigentlich
                         ist es ja ein Knopf, um ein Menü aus- und zuzuklappen") —
                         the same sign the group heads inside carry, turned by
                         aria-expanded, which wireCollapsible already sets. */ ?>
                    <button type="button" class="filter-chip filter-button" id="filter-open">
                        <?= icon('fold-triangle', ['class' => 'collapse-triangle']) ?><?= icon('filter') ?><?= h(t('Filter')) ?><span class="filter-button-count"></span>
                    </button>
                </div>

                <div class="filter-section" id="filter-section" hidden>
                    <div class="filter-group" data-group="text">
                        <h4 class="filter-title"><?= h(t('Text')) ?></h4>
                        <?php /* Text filters only. Coordinates are searched up
                             in the header: the filter searches what you
                             have, the coordinate search leads to a place
                             where nothing is yet.

                             The rows are built by assets/js/filter.js —
                             each with its icon behind it ("enthält" /
                             "enthält nicht"), and the search runs across
                             all fields. */ ?>
                        <div class="filter-rows" id="search-rows"></div>
                    </div>

                    <div class="filter-group" data-group="time">
                        <h4 class="filter-title"><?= h(t('Zeit')) ?></h4>
                        <?php /* Day list, both date fields and the clear button
                             are built by assets/js/timerange.js — the same
                             module fills the box in Organisieren. */ ?>
                        <div id="time-filter-build"></div>
                        <?php /* THE MISSING FIND TIME BELONGS TO THE TIME FILTER
                             (the call, 14.08.2026): it is a
                             question about the same datum, just the one
                             about its absence. In Organisieren it had long been
                             there, in the sidebar it was missing. */ ?>
                        <label class="filter-check">
                            <input type="checkbox" id="time-filter-no-time">
                            <?= h(t('ohne Fundzeit')) ?>
                        </label>
                        <p class="hint-text small" id="time-filter-hint" hidden></p>
                    </div>

                    <div class="filter-group" data-group="place">
                        <h4 class="filter-title"><?= h(t('Ort')) ?></h4>
                        <?php /* Drawn areas; a point counts when it lies in ONE
                             of them. The rows are built by filter.js
                             (drawAreas), drawing runs over the map's
                             tracing mode (beginOutline) — and the areas
                             live in the settings bundle, so Organisieren shows
                             and applies the same ones. */ ?>
                        <div class="area-filter" id="area-filter-rows"></div>
                        <?php /* Region checkmarks, municipality search and map
                             sheet — all built by filter.js from the stock:
                             offered is only what the points know. */ ?>
                        <div id="region-filter"></div>
                        <div id="municipality-filter"></div>
                        <div id="mtb-filter"></div>
                    </div>


                    <?php /* HÖHE, and it sits with the place because it is a
                         question about where — just the vertical one. Two
                         handles on one track (drawElevation in filter.js);
                         the upper end means „and above", so a find in the
                         Alps does not fall out because the scale stops. */ ?>

                    <div class="filter-group" data-group="elevation">
                        <h4 class="filter-title"><?= h(t('Höhe')) ?></h4>
                        <div id="elevation-build"></div>
                    </div>

                    <div class="filter-group" data-group="identification">
                        <h4 class="filter-title"><?= h(t('Bestimmung')) ?></h4>
                        <?php /* Built by drawIdentification (filter.js), the
                             same drawing as in Organisieren: the collective hook
                             "nur nicht bestimmte", the abbreviation
                             families from the title (sp., cf., aff.,
                             s. l.) and the steps of the field
                             Bestimmungssicherheit — since 23.08.2026 each
                             checkable on its own, OR-linked like the
                             threat categories. */ ?>
                        <div id="identification-build"></div>
                    </div>

                    <div class="filter-group" data-group="taxonomy">
                        <h4 class="filter-title"><?= h(t('Systematik')) ?></h4>
                        <div id="taxonomy-build"></div>
                    </div>

                    <div class="filter-group" data-group="red-list">
                        <h4 class="filter-title"><?= h(t('Gefährdung')) ?></h4>
                        <?php /* The categories of Germany's Red Lists, each
                             individually checkable (the call,
                             15.08.2026) — without "Daten unzureichend" and
                             "nicht bewertet", which say nothing about the
                             threat level, and without "ungefährdet", which
                             the DGfM list does not award at all. Drawn by
                             filter.js, the same module fills the box in
                             Organisieren.

                             The classification is NOT on the point: it
                             belongs to the species and is looked up on load
                             (species_endangerment in species.php), so it is as
                             current as the Red List itself. */ ?>
                        <div class="rl-selection" id="rl-selection"></div>
                        <?php /* The responsibility hook (17.09.2026):
                             the DGfM's Verantwortungsarten belong to the
                             conservation question, so they stand in this
                             group — an AND under the OR of the categories.
                             Drawn by filter.js (drawResponsibility). */ ?>
                        <div id="rl-responsibility"></div>
                    </div>

                    <div class="filter-group" data-group="edibility">
                        <h4 class="filter-title"><?= h(t('Speisewert')) ?></h4>
                        <?php /* The DGfM's three food-value lists as checkmarks
                             (17.09.2026): Speisepilz, uneinheitlich
                             beurteilt, Giftpilz — OR-linked like the Red List
                             categories, a species may carry several. Its own
                             group and not part of Gefährdung: what is edible
                             is a different question from what is protected.
                             Looked up on the species (dgfm_marks.php), drawn
                             by filter.js (drawEdibility). */ ?>
                        <div class="rl-selection" id="edibility-selection"></div>
                    </div>

                </div><?php /* /filterbereich */ ?>

                <div class="point-section" id="point-section" data-scroll-remember="map:points">
                    <?php /* filled by map.js */ ?>
                </div>
            </section>

        </div>

        <?php /* „Neue Liste" only on the lists page — on the points page the
             button has no business being there.

             AND „SCHLIESSEN" ONLY ON THE PHONE NOW (09.09.2026). It came
             here on 14.08.2026 because the × in the header was a small
             target; at the desk the grip at the edge does that now, and
             it is larger than both together. So the footer there is empty
             on the points page and disappears entirely — see map.css. */ ?>
        <div class="sidebar-footer">
            <button class="button subtle small" id="list-new"><?= h(t('Neue Liste')) ?></button>
            <button class="button subtle small" id="folder-new"><?= h(t('Neue Mappe')) ?></button>
            <button class="button subtle small" id="sidebar-close"><?= h(t('Schließen')) ?></button>
        </div>


    </aside>

    <?php /* THE SIDEBAR'S GRIP (09.09.2026: „in der
         Desktopversion das Schließen ganz entfernen den Button unten und
         stattdessen genauso eine Handle machen, einen kompletten Streifen,
         einen Balken über die ganze Höhe zum Einklappen der Seitenbar und
         zum Ausklappen").

         THE SAME GRAMMAR AS WITH THE STATISTICS: whoever wants to get rid
         of a surface or fetch it grabs its EDGE. A bar over the whole
         height is always there, always in the same place, and one does
         not have to look for it — unlike a button at the very bottom of
         a list through which one has just scrolled fifty lines.

         A COLUMN OF ITS OWN IN THE GRID, not floating: that way it takes
         its own place instead of covering something — the same decision
         as with the statistics' grip on the same day. And when folded it
         slides to the left edge by itself, because the sidebar's column
         goes to zero; it needs no second position for that.

         ON THE PHONE THERE IS NONE. There the sidebar lies over the map
         instead of beside it, so a strip at its edge would have no edge;
         „Schließen" stays in the footer there. */ ?>
    <button type="button" class="sidebar-grip" id="sidebar-grip"
            aria-controls="sidebar" aria-expanded="true"></button>

    <?php /* Map --------------------------------------------------------------*/ ?>
    <div class="map-section">
        <div id="map"></div>

        <button class="symbol-button sidebar-open" id="sidebar-expand" title="<?= h(t('Seitenleiste ausklappen')) ?>" aria-label="<?= h(t('Seitenleiste ausklappen')) ?>">☰</button>

        <?php /* THE SPEECH BUBBLE, and the only one (09.09.2026:
             „die Chat Sprechblase wandert komplett aus der Seitbar raus,
             auch in der Mobilversion. Der Chat ist nur noch über die
             Kartenansicht verfügbar, über das eigene Symbol").

             There were two of them: one in the sidebar's header row, one
             here for the folded sidebar. Two buttons for the same door,
             and map.js kept them in step — effort spent on a duplicate.
             Now the chat hangs where it belongs: over the map, always in
             the same place, regardless of whether the list happens to be
             open. */ ?>
        <?php if ($startData['friends'] !== []): ?>
            <button type="button" class="symbol-button chat-entry chat-entry-floating" id="friend-chat-entry-floating"
                    title="<?= h(t('Chats mit Freunden')) ?>" aria-label="<?= h(t('Chats mit Freunden')) ?>" aria-expanded="false">
                <?= icon('speech-bubble') ?>
            </button>

            <?php /* The flyout the bubbles open: one row per friend, the
                 green dot on the rows where the other side has written,
                 only those while somebody did, and „+ Chat beginnen" for
                 the rest (assets/js/chatflyout.js). map.js fills the rows
                 and places the list under whichever bubble opened it. */ ?>
            <div class="chat-flyout" id="friend-chat-flyout" hidden><?= chat_flyout_parts() ?></div>
        <?php endif; ?>

        <div class="map-tools">
            <button class="tool" id="tool-location" title="<?= h(t('Zu meinem Standort')) ?>">
                <span aria-hidden="true">◎</span><span class="tool-text"><?= h(t('Standort')) ?></span>
            </button>
            <?php /* The hint about the long press lives here and not only in
                 drawPointButton(): that deliberately runs only at the
                 first 'moveend', and until then the button would carry its
                 label from the source — so it belongs in there correctly
                 right away. */ ?>
            <button class="tool main" id="tool-point-set"
                    title="<?= h($startData['outlines'] ? t('Punkt setzen · lange drücken für eine Fläche') : t('Punkt setzen')) ?>">
                <span aria-hidden="true">✚</span><span class="tool-text"><?= h(t('Punkt setzen')) ?></span>
            </button>
        </div>

        <?php /* ONE STACK FOR EVERYTHING THAT FLOATS AT THE TOP
             (24.08.2026: „die überlagern sich teilweise"). Measured: the
             set hint, the waiting badge and the excursion badge all began
             at the same 16 px and lay on top of one another — each one
             had positioned ITSELF, and a second one could not know. Here
             they are a column: whatever is visible queues up, nothing
             needs to know about the others, and a fourth one added later
             falls into line by itself.

             The container lets clicks through (pointer-events), the
             badges take them again — otherwise the invisible column would
             swallow taps on the map beneath it. */ ?>
        <div class="map-badges" id="map-badges">
            <?php /* NO „Abbrechen" ANY MORE (24.08.2026): the
                 button „Punkt setzen" toggles — a second tap ends the
                 mode (setPointMode(!state.placing) in map.js), and it
                 sits under the thumb anyway while the hint floats at the
                 top edge. Two ways out of one mode, and the far one
                 carried the word. */ ?>
            <div class="set-hint" id="set-hint" hidden>
                <?= h(t('In die Karte tippen, um den Punkt zu setzen.')) ?>
            </div>

            <?php /* What is waiting for a connection. A queue you cannot see is
                 a time bomb: you consider your points saved while they sit
                 in the browser. */ ?>
            <div class="pending-bar" id="queue-bar" hidden>
                <span id="queue-text"></span>
                <button class="text-button" id="queue-now"><?= h(t('Jetzt versuchen')) ?></button>
            </div>
        </div>

        <div class="status-row" id="status-row" hidden></div>

        <?php /* THE PHONE'S POINT PEEK (30.08.2026): a tap on a
             sidebar row shows the find ON THE MAP first — the name and
             one small button; the pencil opens the window, the eye says
             a shared find grants only looking.

             AND THE DESK'S NAME TAG since 12.10.2026: at the desk there is
             no point window any more, a click on a point shows this tag
             with the find's first picture small in front of the name, and
             the pencil leads into the Fundbrowser (map.js, deskPeek). */ ?>
        <div class="point-peek" id="point-peek" hidden>
            <img class="point-peek-image" id="point-peek-image" alt="" hidden>
            <span id="point-peek-title"></span>
            <button type="button" class="button small" id="point-peek-open"></button>
        </div>
    </div>

    <?php chat_window(); ?>

    <?php /* A foreign list, as I want to see it ---------------------------------

         NOT AN EDITING WINDOW. Nothing here reaches the owner: colour and
         shape are how the list looks ON THIS DEVICE, and the line above
         says what I may do with its points — the share decides that, and
         it is not negotiable from here. Hence no save button either;
         picking takes effect at once, as with the sublists. */ ?>
    <?php /* One recipient's rights on a shared list — the gear in „Teilen"
         (recipients.js). A row per right, a row per field of the list; no
         levels („nicht abgeholt werden mit allgemeinen Phrasen",
         08.10.2026). Übernehmen only closes this window — the list window's
         Speichern saves. */ ?>
    <dialog class="form-window recipient-rights-window pinned-footer" id="recipient-rights">
        <form method="dialog">
            <h2 id="recipient-rights-heading"></h2>
            <div class="button-row" id="recipient-rights-tools">
                <button type="button" class="button subtle" id="recipient-filter-open"><?= icon('filter') ?><?= h(t('Filter')) ?></button>
                <button type="button" class="button subtle" id="recipient-all"><?= h(t('Alles erlauben')) ?></button>
            </div>
            <p class="recipient-filter-line" id="recipient-filter-line"></p>
            <?php /* THE RIGHTS SCROLL, head and foot stay (12.11.2026:
                 „genau den gleichen Fehler … schmal und lang und die Fußzeile
                 liegt außerhalb des Viewports … zweispaltig"): at the desk the
                 rows stand in two columns (map.css). */ ?>
            <div class="recipient-rights-scroll">
                <table class="list recipient-rights-table" id="recipient-rights-table"></table>
            </div>
            <div class="dialog-footer">
                <button type="button" class="button subtle" id="recipient-rights-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" id="recipient-rights-ok"><?= h(t('Übernehmen')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* THE FILTER OF ONE RECIPIENT — the sidebar's points filter, the
         same eight groups and the same building blocks
         (08.10.2026: „den gleichen Filter wie unser Filter bei Punkten").
         Built by assets/js/recipientfilter.js with filter.js's drawers; what
         the list's points offer is what can be chosen. Evaluated on the
         server (list_share_filter_pass), so a point the recipient may not
         see never reaches his device. */ ?>
    <dialog class="form-window is-wide recipient-filter-window pinned-footer" id="recipient-filter">
        <form method="dialog">
            <h2 id="recipient-filter-heading"></h2>
            <p class="hint-text"><?= h(t('Zu sehen sind dann nur die Punkte, auf die alles zutrifft, was du hier einstellst. Ohne Einstellung sind alle Punkte der Liste zu sehen.')) ?></p>
            <div class="filter-section recipient-filter-groups">
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Text')) ?></h4>
                    <div class="filter-rows" id="sf-terms"></div>
                </div>
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Zeit')) ?></h4>
                    <div class="recipient-filter-dates">
                        <label><?= h(t('von')) ?> <input type="date" id="sf-from"></label>
                        <label><?= h(t('bis')) ?> <input type="date" id="sf-until"></label>
                    </div>
                    <label class="filter-check"><input type="checkbox" id="sf-undated"> <?= h(t('ohne Fundzeit')) ?></label>
                </div>
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Ort')) ?></h4>
                    <div class="area-filter" id="sf-areas"></div>
                    <button type="button" class="text-button" id="sf-areas-take"><?= h(t('Gebiete aus dem Filter der Seitenleiste übernehmen')) ?></button>
                    <div id="sf-regions"></div>
                    <div id="sf-municipalities"></div>
                    <div id="sf-mtb"></div>
                </div>
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Höhe')) ?></h4>
                    <div id="sf-elevation"></div>
                </div>
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Bestimmung')) ?></h4>
                    <div id="sf-identification"></div>
                </div>
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Systematik')) ?></h4>
                    <div id="sf-taxonomy"></div>
                </div>
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Gefährdung')) ?></h4>
                    <div class="rl-selection" id="sf-rl"></div>
                    <div id="sf-responsibility"></div>
                </div>
                <div class="filter-group">
                    <h4 class="filter-title"><?= h(t('Speisewert')) ?></h4>
                    <div class="rl-selection" id="sf-edibility"></div>
                </div>
            </div>
            <div class="dialog-footer">
                <button type="button" class="button subtle" id="sf-clear"><?= h(t('Filter leeren')) ?></button>
                <button type="button" class="button subtle" id="sf-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button"><?= h(t('Übernehmen')) ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="form-window" id="shared-look-window">
        <form method="dialog">
            <h2 id="shared-look-heading"><?= h(t('Geteilte Liste')) ?></h2>

            <p class="hint-text" id="shared-look-rights"></p>

            <?php /* FOR THE ACCOUNT since 09.10.2026 (map_shared_looks) — on
                 phone and computer alike, and nobody else sees it. */ ?>
            <div class="field">
                <label><?= h(t('Farbe')) ?></label>
                <div class="color-selection" id="shared-look-colors" role="radiogroup" aria-label="<?= h(t('Farbe')) ?>"></div>
            </div>

            <div class="field">
                <label><?= h(t('Form der Markierung')) ?></label>
                <div class="shape-selection" id="shared-look-shapes" role="radiogroup" aria-label="<?= h(t('Form')) ?>"></div>
            </div>

            <?php /* The door out of a list share — the list goes on without me. */ ?>
            <div class="button-row">
                <button type="button" class="button danger subtle" id="look-window-leave" hidden><?= icon('sign-out') ?><?= h(t('Nicht mehr sehen')) ?></button>
            </div>

            <div class="dialog-footer">
                <button type="button" class="button subtle" id="shared-look-reset"><?= h(t('Zurücksetzen')) ?></button>
                <button type="button" class="button" id="shared-look-close"><?= h(t('Schließen')) ?></button>
            </div>
        </form>
    </dialog>

    <?php gallery_windows_render(); ?>

    <?php /* Edit list ----------------------------------------------------------*/ ?>
    <dialog class="list-window" id="list-window">
        <form method="dialog" id="list-form" novalidate>
            <h2 id="list-heading"><?= h(t('Liste bearbeiten')) ?></h2>

            <?php /* THREE PARTS: how the list looks, what it holds, who shares it
                 (12.11.2026: „schmal und dafür lang … wir müssen
                 runterscrollen für Speichern"). Side by side where the window
                 is wide (from 1100 px), as tabs where it is not — the phone
                 included. Head, tabs and foot stay put; only the parts scroll
                 (map.css). novalidate on the form: a required name on a tab
                 not in sight would stop the save without a word — map.js
                 checks it and brings its tab forward. */ ?>
            <div class="sidebar-tabs list-window-tabs" role="tablist" aria-label="<?= h(t('Teile der Liste')) ?>">
                <button type="button" role="tab" data-part="look" aria-selected="true"><?= h(t('Aussehen')) ?></button>
                <button type="button" role="tab" data-part="content" aria-selected="false" tabindex="-1"><?= h(t('Inhalt')) ?></button>
                <button type="button" role="tab" data-part="share" aria-selected="false" tabindex="-1"><?= h(t('Teilen')) ?></button>
            </div>
            <div class="list-window-columns">
            <div class="list-window-col is-active" data-part="look">
            <div class="field">
                <label for="list-name"><?= h(t('Name')) ?></label>
                <input type="text" id="list-name" maxlength="100" required data-lf-name
                       placeholder="<?= h(t('Name der Liste')) ?>">
            </div>

            <?php /* THE MAPPE (0168): which one the list lies in, as radios —
                 there are only ever a few, and a dropdown would hide the
                 others (CLAUDE.md, „What there is to choose stands in
                 sight"). Drawn by map.js; hidden while there is no Mappe. */ ?>
            <fieldset class="field" id="list-folder-section" hidden>
                <legend><?= h(t('Mappe')) ?></legend>
                <div id="list-folders"></div>
            </fieldset>

            <div id="list-own">
                <div class="field">
                    <label><?= h(t('Farbe')) ?></label>
                    <div class="color-selection" id="list-colors" role="radiogroup" aria-label="<?= h(t('Farbe')) ?>"></div>
                </div>

                <div class="field">
                    <label><?= h(t('Form der Markierung')) ?></label>
                    <div class="shape-selection" id="list-shapes" role="radiogroup" aria-label="<?= h(t('Form')) ?>"></div>
                </div>
            </div><?php /* /lf-eigenes */ ?>
            </div><?php /* /list-window-col: the look */ ?>

            <div class="list-window-col" data-part="content">
            <label class="checkbox">
                <input type="checkbox" id="list-species">
                <span><?= h(t('Artenliste — der Titel bekommt Vorschläge aus der Namensdatenbank')) ?></span>
            </label>

            <?php /* WHAT THE LIST HOLDS (12.11.2026, migration 0195):
                 the groups its „Art" suggests from, and the name packages
                 every device that writes into it keeps offline. A question
                 with its answers beneath, all in sight; map.js fills the
                 boxes from start.groupShapes and shows them with the species
                 tick above. */ ?>
            <fieldset class="field question" id="list-groups" hidden>
                <legend><?= h(t('Was enthält diese Liste?')) ?></legend>
                <div class="list-groups" id="list-groups-choice"></div>
            </fieldset>

            <?php /* NO LIST OF THE FIND FIELDS HERE any more (12.11.2026:
                 „diesen Hinweistext ebenso entfernen"); they show in the
                 point window, where they are filled in. */ ?>

            <div class="field">
                <label><?= h(t('Eigene Felder')) ?></label>
                <?php /* Additional input fields that points of this list get —
                     besides title, note and coordinates. A find list needs
                     different things than a list of parking spots. */ ?>
                <div class="field-list" id="list-fields"></div>
                <button type="button" class="text-button" id="list-field-new"><?= h(t('Feld hinzufügen')) ?></button>
            </div>

            <?php /* LISTEN TEILEN (08./09.10.2026): sharing starts at
                 the list — every friend and own working group with a box,
                 the way „Direkt teilen" shows them in the point window; a
                 ticked one carries a line with exactly what applies and the
                 gear to its rights. Drawn by assets/js/recipients.js; like
                 everything in this window it applies with Speichern. */ ?>
            </div><?php /* /list-window-col: the content */ ?>

            <div class="list-window-col" data-part="share">
            <div class="field" id="list-recipients-section" hidden>
                <?php /* THE PART'S TITLE, then the groups' heads
                     (12.11.2026: „eine Doppelüberschrift … muss besser
                     markiert sein. Es gibt eine Hauptunterüberschrift und eine
                     Unterüberschrift"): „Teilen" in the text's size and bold,
                     „Freunde" and „Arbeitsgruppen" as group heads with their
                     surface (.list-label, recipients.js). As a tab the tab
                     names the part, and the title steps back (map.css). */ ?>
                <h3 class="list-window-part-title"><?= h(t('Teilen')) ?></h3>
                <div id="list-recipients"></div>
            </div>
            </div><?php /* /list-window-col: the sharing */ ?>
            </div><?php /* /list-window-columns */ ?>

            <?php /* SAVED AS IT GOES (12.11.2026: „Wir sollten mal über
                 ein Autosave nachdenken"): every change is kept a moment
                 later (map.js, listSaveSoon), so the foot only closes — and
                 says that it kept. Sharing asks at each tick instead
                 (recipients.js). */ ?>
            <div class="list-window-footer">
                <button type="button" class="button danger" id="list-delete"><?= h(t('Liste löschen')) ?></button>
                <?php /* Why there is no delete — in the foot, where the button would stand, in red (12.11.2026). */ ?>
                <p class="list-hint" id="list-hint"></p>
                <span class="hint-text small list-saved" id="list-saved" aria-live="polite"></span>
                <button type="button" class="button" id="list-close"><?= h(t('Schließen')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* Edit Mappe (migration 0168) ------------------------------------------
         A Mappe is only a name that gathers lists in the sidebar — so its
         window holds only the name. Which lists lie in it is set at each
         list, in „Liste bearbeiten". */ ?>
    <dialog class="list-window pinned-footer" id="folder-window">
        <form method="dialog" id="folder-form">
            <h2 id="folder-heading"><?= h(t('Mappe bearbeiten')) ?></h2>
            <p class="hint-text"><?= h(t('Eine Mappe fasst Listen in der Seitenleiste zusammen. Sie hat keine eigenen Einstellungen. Welche Listen in ihr liegen, stellst du bei jeder Liste ein, unter „Liste bearbeiten".')) ?></p>
            <div class="field">
                <label for="folder-name"><?= h(t('Name')) ?></label>
                <input type="text" id="folder-name" maxlength="100" required
                       placeholder="<?= h(t('Name der Mappe')) ?>">
            </div>
            <div class="list-window-footer">
                <button type="button" class="button danger" id="folder-delete"><?= icon('delete') ?><?= h(t('Mappe löschen')) ?></button>
                <button type="button" class="button subtle" id="folder-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" id="folder-save"><?= icon('save') ?><?= h(t('Speichern')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* List choice before creating ----------------------------------------
         First step when setting a point: where does it belong? Asking the
         question first is the difference between "sort it in later" and
         "right from the start" — and nobody sorts in later. */ ?>
    <?php /* tabindex="-1": the window itself takes the focus on opening
         (selectLists), not the tour field — a focused text field opens
         the phone's keyboard and squeezes the list tree below the fold.
         Same pattern as the organize dialog. */ ?>
    <dialog class="list-window pinned-footer" id="list-selection-window" tabindex="-1">
        <form method="dialog" id="list-selection-form">
            <?php /* The tour at the very top (23.08.2026): the name
                 every point of this outing receives. Prefilled by
                 selectLists() — the running tour, or a fresh date name.
                 Editing it renames the running tour, the button ends it and
                 begins a new one; both only take effect with "Weiter". */ ?>
            <div class="field tour-field">
                <label for="tour-name"><?= h(t('Tour')) ?></label>
                <?php /* list + datalist: typing stays free (a tour name is
                     free text), and the names already known offer
                     themselves — including the ones created empty in
                     Organisieren, which have no point to be found by
                     (migration 0073). A <select> would have made the
                     free text impossible. */ ?>
                <input type="text" id="tour-name" maxlength="120" autocomplete="off"
                       list="tour-names">
                <datalist id="tour-names"></datalist>
                <?php /* OHNE TOUR IS A MODE, AND IT LOOKS LIKE ONE
                     (22.09.2026): a pressed button, not a tick, and while it
                     is on everything about the tour is greyed almost to
                     nothing — „wir befinden uns in einem ganz anderen Modus".
                     An area or a burn site entered in the evening is no find
                     of today's outing; the running tour runs on without the
                     point. The mode holds until the page reloads — kept over
                     night it would quietly strip tomorrow's finds of their
                     tour. */ ?>
                <div class="tour-row">
                    <button type="button" class="button subtle" id="tour-none" aria-pressed="false"><?= h(t('Ohne Tour')) ?></button>
                    <button type="button" class="button subtle" id="tour-new"
                            title="<?= h(t('Die laufende Tour beenden und eine neue beginnen')) ?>"><?= h(t('Neue Tour')) ?></button>
                </div>
                <p class="hint-text small" id="tour-hint"></p>
            </div>

            <?php /* AN AUFSAMMLUNG INSTEAD OF A FIND (15.10.2026: dung
                 for the moist chamber — „statt Artname eine Option"). A mode
                 like „Ohne Tour", pressed for this one point and off again
                 at the next window; the point becomes the sample its species
                 grow from later (collections.php). */ ?>
            <div class="tour-row point-kind-row">
                <button type="button" class="button subtle" id="point-as-collection" aria-pressed="false"
                        title="<?= h(t('Substrat, das zu Hause in Kultur kommt — die Arten trägst du später an ihm ein')) ?>"><?= h(t('Aufsammlung')) ?></button>
            </div>

            <h2><?= h(t('In welche Listen?')) ?></h2>
            <div class="list-selection" id="list-selection-lists"></div>
            <?php /* THE SHORT VIEW (12.10.2026): only the ticked
                 lists stand above — most finds go where the day's list
                 is. A find out of turn (a lichen on a fungi day) opens
                 every list with this button. A peer action under its box,
                 so left-aligned. */ ?>
            <div class="button-row" id="list-selection-more-row" hidden>
                <button type="button" class="button subtle small" id="list-selection-more"><?= h(t('Listen ändern')) ?></button>
            </div>
            <p class="hint-text small"><?= h(t('Mehrere möglich. Ohne Auswahl landet der Punkt unter „Ohne Liste“.')) ?></p>

            <div class="list-window-footer">
                <button type="button" class="button subtle" id="list-selection-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" id="list-selection-next"><?= h(t('Weiter')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* Detail window ----------------------------------------------------*/ ?>
    <?php point_window_render(); ?>

    <?php /* The picture viewer and its two question windows —
       includes/photo_window.php since 30.09.2026, so the administration
       can print the same window (photowindow.js wires it up). */ ?>
    <?php photo_window_render(); ?>
    <?php plate_window_render(); ?>


    <dialog class="list-window pinned-footer" id="area-window">
        <form method="dialog" id="area-form">
            <h2><?= h(t('Gebiet anlegen')) ?></h2>

            <div class="field">
                <label for="area-name"><?= h(t('Name')) ?></label>
                <input type="text" id="area-name" maxlength="60" required placeholder="<?= h(t('z. B. Urlaub Dolomiten')) ?>">
            </div>

            <p class="hint-text small">
                <?= h(t('Gespeichert wird nur die Umrandung — auf allen deinen Geräten. Karten dafür holst du dann auf dem Gerät, das mitkommt.')) ?>
            </p>

            <label class="checkbox">
                <input type="checkbox" id="area-now">
                <span><?= h(t('Gleich Karten auf dieses Gerät laden')) ?></span>
            </label>

            <div class="list-window-footer">
                <button type="button" class="button subtle" id="area-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" id="area-save"><?= icon('save') ?><?= h(t('Speichern')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* Step 2: maps onto THIS device ---------------------------------------
         Here and only here come the questions that hang on storage:
         which layers, up to which level, how long to keep. All three are
         decided by the device that downloads — which is why none of it
         lives on the server. */ ?>
    <dialog class="list-window pinned-footer" id="load-window">
        <form method="dialog" id="load-form">
            <h2><?= h(t('Karten auf dieses Gerät')) ?></h2>
            <p class="hint-text small" id="load-name"></p>

            <div class="field">
                <label><?= h(t('Welche Ebenen?')) ?></label>
                <?php /* Multi-select with MEASURED size per layer. An aerial
                     image costs a multiple of a topographic map; whoever
                     only looks for the path need not take both along.
                     EVERYTHING is offered, not just what currently lies on
                     the map: whoever wants to switch between aerial image
                     and topo map in the forest needs both — and would
                     otherwise only notice the absence there. */ ?>
                <div class="layer-selection" id="area-layers"></div>
                <p class="hint-text small">
                    <?= h(t('Angezeigt ist die gemessene Größe für dieses Gebiet, nicht für die ganze Ebene. Vorgewählt ist, was gerade auf der Karte liegt. Schutzgebiete holst du nicht hier, sondern unter Verwaltung → Offline → Offline-Daten, dort gleich für ein ganzes Bundesland.')) ?>
                </p>
            </div>

            <div class="field">
                <label for="area-zoom"><?= h(t('Höchste Zoomstufe:')) ?> <span id="area-zoom-value"></span></label>
                <input type="range" id="area-zoom" min="15" max="19" step="1" value="19">
                <p class="hint-text small" id="area-zoom-text"></p>
            </div>

            <?php /* THE DATE HAS A SWITCH IN FRONT OF IT, because "keep for
                 good" was always possible — an empty date field means no
                 expiry — but nobody could see it: the field arrives filled
                 in, and an empty date reads as a forgotten entry, not as a
                 decision (22.08.2026). The checkbox says the
                 decision out loud and takes the field with it. */ ?>
            <div class="field">
                <label class="checkbox" for="area-keep">
                    <input type="checkbox" id="area-keep">
                    <span><?= h(t('Dauerhaft behalten')) ?></span>
                </label>
                <p class="hint-text small">
                    <?= h(t('Die Karten bleiben auf diesem Gerät, bis du sie über „Vom Gerät löschen" selbst entfernst.')) ?>
                </p>
            </div>

            <div class="field" id="area-until-field">
                <label for="area-until"><?= h(t('Karten behalten bis')) ?></label>
                <input type="date" id="area-until">
                <p class="hint-text small">
                    <?= h(t('Danach räumen sich die Karten auf diesem Gerät von selbst weg. Die Umrandung bleibt — nachladen ist ein Knopfdruck.')) ?>
                </p>
            </div>

            <p class="area-estimate" id="area-estimate"></p>

            <div class="area-progress" id="area-progress" hidden>
                <div class="area-bar"><div id="area-progress-inner"></div></div>
                <span id="area-progress-text"></span>
            </div>

            <?php /* ONLY THE SCREEN CARRIES A DOWNLOAD (the app session measured it
                 on the maintainer's Xiaomi, 28.09.2026; keepScreenOn in map.js).
                 The page keeps the screen on by itself, so only a phone locked
                 BY HAND stops the loading, after about a minute, and it goes on
                 after unlocking. The sentence stands above „Laden", so it is
                 read before the tap, and it stays WHILE loading — it is not a
                 .field, which is-loading hides: it speaks to exactly that
                 moment (the wording, 28.09.2026). */ ?>
            <p class="hint-text"><?= h(t('Während des Ladens bleibt der Bildschirm von selbst an. Sperr das Handy nicht, sonst hält das Laden nach etwa einer Minute an. Entsperrst du es, geht es von selbst weiter. Bei großen Gebieten leg das Handy am besten ans Ladekabel.')) ?></p>

            <div class="list-window-footer">
                <button type="button" class="button subtle" id="area-load-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" id="area-load"><?= h(t('Laden')) ?></button>
            </div>
        </form>
    </dialog>

    <?php /* HERE STOOD THE AREA LIST, a third window with the same enumeration
         as the administration.

         MANAGING NOW HAPPENS ONLY THERE. The list existed here twice, in
         two separate implementations — and exactly that became its
         undoing: icons on the buttons and the fixed column width landed
         only in the administration, not here. Two copies of the same list
         drift apart, it is only a matter of time.

         The map keeps the two windows above because those are the two
         steps that do not work WITHOUT it: outlining (you tap it in corner
         by corner) and loading (the address of each tile comes from
         Leaflet's own calculation). Both are invoked from the
         administration and return there afterwards — see
         windowFromUrl() and backToAdministration() in map.js. */ ?>


    <?php /* Overwritten changes -------------------------------------------------
         Two devices changed the same field; the younger version won
         (includes/field_times.php), the defeated one lies on the server.
         Here both stand SIDE BY SIDE, and per field the user decides
         which one counts. The content is built in showConflicts(). */ ?>
    <dialog class="list-window pinned-footer" id="conflict-window">
        <form method="dialog" id="conflict-form">
            <h2 id="conflict-title"><?= h(t('Überschriebene Änderungen')) ?></h2>
            <p class="hint-text small">
                <?= h(t('Dasselbe Feld wurde auf zwei Geräten geändert — die jüngere Fassung hat gewonnen. Hier stehen beide nebeneinander, und je Feld lässt sich die andere zurückholen. Verloren ist keine.')) ?>
            </p>

            <div id="conflict-list"></div>

            <div class="list-window-footer">
                <button type="button" class="button subtle" id="conflict-later"><?= h(t('Später')) ?></button>
                <button type="submit" class="button" id="conflict-apply"><?= h(t('Übernehmen')) ?></button>
            </div>
        </form>
    </dialog>

    <?php if ($guest): ?>
    <?php /* THE GUEST'S SETTINGS (24.08.2026). Two things belong
         to him and to nobody else: how the map looks on HIS phone, and
         who he is on this excursion. The administration stays closed —
         it is full of rooms he has no key to.

         The tiles come from includes/appearance.php, the wiring from
         assets/js/appearance.js — the same pair the settings tab uses,
         so a new colour appears in both places at once. */ ?>
    <dialog class="form-window pinned-footer" id="guest-settings">
        <div class="window-body">
            <h2><?= h(t('Einstellungen')) ?></h2>

            <form id="guest-person">
                <p class="choice-family"><?= h(t('Wer du bist')) ?></p>
                <p class="hint-text small">
                    <?= h(t('Der Name steht als Finder an deinen Funden — auch an denen von vorhin, wenn du ihn hier änderst.')) ?>
                </p>
                <div class="field">
                    <label for="guest-set-first"><?= h(t('Vorname')) ?></label>
                    <input type="text" id="guest-set-first" name="first_name" required maxlength="100">
                </div>
                <div class="field">
                    <label for="guest-set-last"><?= h(t('Nachname')) ?></label>
                    <input type="text" id="guest-set-last" name="last_name" required maxlength="100">
                </div>
                <div class="field">
                    <label for="guest-set-email"><?= h(t('E-Mail (freiwillig)')) ?></label>
                    <input type="email" id="guest-set-email" name="email" maxlength="255">
                    <p class="hint-text small">
                        <?= h(t('Mit E-Mail bekommst du deine Funde später zugeschrieben, wenn du ein Konto anlegst. Nachtragen geht, solange die Exkursion läuft.')) ?>
                    </p>
                </div>
            </form>

            <?php appearance_picker(false); ?>

            <?php /* WHY IT CANNOT BE SAVED, IN THE WINDOW ITSELF
                 (24.08.2026: he cleared the name and nothing happened).
                 The browser does refuse — but its bubble is not seen in a
                 full-screen dialog, and on the tablet not at all. This
                 line describes a STATE and therefore stays in the flow,
                 right above the button it blocks. */ ?>
            <p class="hint-text small guest-person-error" id="guest-person-error" hidden></p>

            <?php /* SAVING IS THE LAST THING IN THE WINDOW
                 (24.08.2026): first everything is set, then one concludes.
                 The button reaches its form from outside via form="…" —
                 the appearance below it needs no saving at all, it takes
                 effect on the tap. */ ?>
            <div class="dialog-footer">
                <button type="button" class="button subtle" id="guest-settings-close"><?= h(t('Schließen')) ?></button>
                <button type="submit" class="button" form="guest-person"><?= icon('save') ?><?= h(t('Speichern')) ?></button>
            </div>
        </div>
    </dialog>
    <?php endif; ?>

    <?php /* HERE STOOD #geteiltfenster, the separate modal for a foreign point.
         It was dropped on 14.08.2026: a point thus had two windows, and
         neither could do what the other could — the modal showed neither
         photos nor area nor municipality, the detail window knew no
         foreign points. Now the detail window (#detail) shows both kinds,
         and what the share does not permit stands grayed out in it. See
         applyDetailRights() in map.js. */ ?>

    <?php /* Bar while outlining */ ?>
    <div class="move-bar" id="draw-bar" hidden>
        <div>
            <?php /* The heading changes: the same tool outlines an offline
                 area and a mapped area. What is currently meant has to be
                 written on it — the map looks the same in both cases. */ ?>
            <strong id="draw-title"><?= h(t('Gebiet umranden')) ?></strong>
            <span id="draw-hint"><?= h(t('In die Karte tippen — Ecke für Ecke')) ?></span>
        </div>
        <?php /* "Zurück" alone was indistinguishable from "Abbrechen" — both
             read like "get me out of here". One takes away a corner, the
             other the whole outline; that has to be written on them. */ ?>
        <button class="button subtle" id="draw-back"><?= h(t('Ecke zurück')) ?></button>
        <button class="button subtle" id="draw-cancel"><?= h(t('Verwerfen')) ?></button>
        <button class="button" id="draw-done"><?= h(t('Fertig')) ?></button>
    </div>

    <?php /* The bar of area editing — "Zeigen" from the administration lays
         the outline onto the map, the handles of the point outlines steer
         it (griffQuelle, map.js). Its own bar instead of the drawing bar
         above: there corners are set ANEW one by one, here you pull on
         what exists — different buttons, different state. Speichern stands
         on the right (concluding button, CLAUDE.md); it stays mute until
         something has really changed. */ ?>
    <div class="move-bar" id="area-bar" hidden>
        <div>
            <strong><?= h(t('Offline-Gebiet')) ?></strong>
            <span id="area-bar-name"></span>
        </div>
        <button class="button subtle" id="area-bar-done"><?= h(t('Schließen')) ?></button>
        <button class="button" id="area-bar-save" disabled><?= icon('save') ?><?= h(t('Speichern')) ?></button>
    </div>

    <?php /* Move bar ------------------------------------------------------------
         On the phone the detail window covers the whole map. "Zeigen"
         therefore folds it away so the marker can be grabbed at all;
         this bar is the way back. The coordinate runs along with it, so
         you can see that the dragging is getting through. */ ?>
    <div class="move-bar" id="move-bar" hidden>
        <div>
            <?php /* For an area this means something else: its marker cannot
                 be dragged, the window merely steps aside here so you can
                 see the map. A bar that says "Marker verschieben" where
                 there is nothing to move would be an untruth. */ ?>
            <strong id="move-title"><?= h(t('Marker verschieben')) ?></strong>
            <span id="move-coord"></span>
        </div>
        <button class="button" id="move-done"><?= h(t('Fertig')) ?></button>
    </div>

    <?php /* The icon stock for map.js: every icon the script only builds at
         runtime stands here as a <template> — fold triangles, sort icons,
         trash cans, fact icons. That way the path data exists only once
         even when both sides need it; assets/js/icon.js fetches them
         from here. */ ?>
    <?= icon_stock() ?>

    <?php
    /* THE ADMINISTRATOR'S REMINDER (02.10.2026: „benachrichtigst,
       wenn ich mich anmelde oder da drin bewege … ein Monat vorher"). A
       client secret that runs out — OneDrive's lives 24 months at most —
       tears every connection to that cloud, and nobody else can see it
       coming. A window and not the status bar: the bar fades after six
       seconds and is read past. Once a day per device (reminder.js), and
       only rendered when there is something to say. The same sentences
       as the administration's head (cloud_secret_warning_text). */
    $secretWarnings = !$guest && is_admin($me) ? cloud_secret_warnings() : [];
    ?>
    <?php if ($secretWarnings !== []): ?>
    <dialog class="form-window" id="secret-reminder">
        <form method="dialog">
            <h2><?= h(t('Client-Secret erneuern')) ?></h2>
            <?php foreach ($secretWarnings as $w): ?>
                <p class="hint-text"><?= h(cloud_secret_warning_text($w)) ?></p>
            <?php endforeach; ?>
            <div class="dialog-footer">
                <button type="submit" class="button subtle" value="later"><?= h(t('Später')) ?></button>
                <a class="button" href="<?= h(url('pages/administration.php')) ?>#cloud_providers"><?= h(t('Zu den Cloud-Anbietern')) ?></a>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <?php /* And the same route for the marker shapes of the lists (circle,
         mushroom, deer …): until 14.08.2026 they lived as FORMEN in
         map.js, but now sit in includes/shapes.php, because the
         containers in the administration show their mounted lists with
         each list's own symbol and PHP needs the same paths for that.
         assets/js/shapes.js fetches them from here. */ ?>
    <?= shape_stock() ?>

</div>

<script>
window.START_DATA = <?= json_encode($startData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= h(asset_url('assets/vendor/leaflet/leaflet.js')) ?>"></script>
<?php /* The version tag for every module map.js imports — before the
   module script, and map.js through asset_url() like the map's entries
   (helpers.php says why that must be identical). */ ?>
<?= asset_import_map() ?>
<script type="module" src="<?= h(asset_url('assets/js/map.js')) ?>"></script>
</body>
</html>
