<?php
/**
 * The two permissions, explained — once, before the map
 * (24.08.2026).
 *
 * WHO SEES THIS: a guest right after joining an excursion, and a person
 * right after registering. Both are about to map in the woods, and both
 * need two things the browser only grants on request: the LOCATION and
 * the promise to keep local data. Afterwards the map, and this page is
 * never seen again — it is a step in a path, not a setting.
 *
 * AND SINCE 09.09.2026 A THIRD: the registered participant who scanned
 * the QR code. He gets ONLY the map step, and only where the leader laid
 * an area down — the two permissions he settled when he made his
 * account, but his phone's map stock is a question the door cannot
 * answer. See $onlyMaps below.
 *
 * WHY ITS OWN PAGE AND NOT A DIALOG ON THE MAP. Both prompts belong to a
 * CLICK. Firefox opens a window of its own for each; one that appears
 * unbidden while somebody is looking at a map gets clicked away, and a
 * refusal sticks — Firefox does not ask a second time. Here the button
 * IS the request, and the sentence above it says what for.
 *
 * ONE STEP PER PAGE, deliberately: two browser prompts stacked on one
 * screen is exactly the moment one clicks something away blindly.
 *
 * SKIPPING IS ALLOWED on both steps, and the map works afterwards: the
 * location button asks again, and without the pledge the browser MAY
 * clean up — it does not have to. Anything else would hold somebody
 * hostage at a door they came through voluntarily.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/excursions.php';
require_once __DIR__ . '/../includes/map_layers.php';
require_once __DIR__ . '/../includes/offline_map.php';

// A guest counts as much as an account here — both are on their way to
// the map, and the guest has no account by design.
$user  = user_current();
$actor = $user === null ? excursion_actor($pdo) : null;
if ($user === null && $actor === null) {
    redirect_to('login.php');
}

/* THE THIRD STEP EXISTS ONLY WHERE THERE IS SOMETHING TO LOAD
   (24.08.2026): the leader's offline area. Without one the
   path is two steps and says so — a step that offers nothing would be a
   promise broken on arrival. */
$area = null;
if ($actor !== null) {
    $area = excursion_area($pdo, $actor['excursion']);
} else {
    /* THE REGISTERED PARTICIPANT COMES FOR THIS STEP ALONE
       (09.09.2026). He scanned the same code and stands at the same car
       park, so the leader's area is worth the same offer — but the two
       permissions are behind him: he has an account, and he walked them
       when he made it. Asking a second time would teach him to click
       browser prompts away, which is the one thing this page exists to
       prevent. */
    $joined = excursion_mapping_current($pdo);
    $area   = $joined === null ? null : excursion_area($pdo, $joined);
}
$onlyMaps = $actor === null && $area !== null;
$steps = $area !== null ? 3 : 2;

$step = (string) ($_GET['step'] ?? 'location');
if ($onlyMaps) {
    // One step is no path — no counter, no way past it but the buttons.
    $step = 'maps';
} elseif (!in_array($step, ['location', 'storage', 'maps'], true)
    || ($step === 'maps' && $area === null)) {
    $step = 'location';
}

page_header(t('Los geht’s'));
?>

<h1><?= h($onlyMaps ? t('Karten für unterwegs')
    : ($steps === 3 ? t('Noch drei Kleinigkeiten') : t('Noch zwei Kleinigkeiten'))) ?></h1>

<?php if ($step === 'location'): ?>
    <p class="onboarding-step"><?= h(t('Schritt %d von %d', 1, $steps)) ?></p>
    <h2><?= h(t('Standort')) ?></h2>
    <p>
        <?= h(t('Damit deine Funde dort landen, wo du stehst, braucht die Karte deinen Standort. Der Browser fragt dich gleich danach — bitte erlaube es. Die Position bleibt auf deinem Gerät und wandert nur mit den Funden mit, die du selbst anlegst.')) ?>
    </p>
    <div class="button-row">
        <button type="button" class="button" id="onboarding-allow"><?= h(t('Standort erlauben')) ?></button>
        <a class="button subtle" href="<?= h(url('pages/onboarding.php')) ?>?step=storage"><?= h(t('Später')) ?></a>
    </div>
<?php elseif ($step === 'storage'): ?>
    <p class="onboarding-step"><?= h(t('Schritt %d von %d', 2, $steps)) ?></p>
    <h2><?= h(t('Daten auf dem Gerät')) ?></h2>
    <p>
        <?= h(t('Ohne Empfang im Wald merkt sich die App deine Funde auf dem Gerät und schickt sie später ab. Damit der Browser diese Daten nicht bei Platzmangel wegräumt, braucht er deine Erlaubnis.')) ?>
    </p>
    <div class="button-row">
        <button type="button" class="button" id="onboarding-allow"><?= h(t('Speichern erlauben')) ?></button>
        <a class="button subtle" href="<?= h($steps === 3
            ? url('pages/onboarding.php') . '?step=maps' : url('index.php')) ?>"><?= h(t('Später')) ?></a>
    </div>
<?php else: ?>
    <?php
        // The estimate the leader saw while picking (stage: excursion
        // area). Per map kind — whoever takes both pays twice, and the
        // sentence says so instead of letting the phone find out.
        $size = $area['bytes'] > 0
            ? ($area['bytes'] < 1048576
                ? round($area['bytes'] / 1024) . ' kB'
                : ($area['bytes'] < 1073741824
                    ? number_format($area['bytes'] / 1048576, $area['bytes'] > 10485760 ? 0 : 1, ',', '.') . ' MB'
                    : number_format($area['bytes'] / 1073741824, 2, ',', '.') . ' GB'))
            : null;
    ?>
    <?php if (!$onlyMaps): /* Alone the step is the page — and the
             heading above already says what this is. */ ?>
        <p class="onboarding-step"><?= h(t('Schritt %d von %d', 3, 3)) ?></p>
        <h2><?= h(t('Karten für unterwegs')) ?></h2>
    <?php endif; ?>
    <p>
        <?php /* The area's name rides in as an argument so the sentence
                 stays ONE key — h() sits on the name, the sentence itself
                 carries the <strong> along. */ ?>
        <?= t('Die Exkursionsleitung hat ein Gebiet hinterlegt: %s. Lade es jetzt herunter, solange du noch Empfang hast — im Wald zeigt die Karte dann auch ohne Netz, wo du bist.',
            '<strong>' . h($area['name']) . '</strong>') ?>
    </p>
    <?php /* The download itself happens ON THE MAP: the tile addresses are
         built by its layer machinery (which service draws at which zoom,
         per region), and a second copy of that here would be the twin
         that drifts. So these links hand over, and map.js starts the
         same download the Offline tab runs. */ ?>
    <?php
        /* THE KEYS AND THE WORDS COME FROM THE REGISTRY (map_layers.php),
           not from here: „aerial_official" is what the map's layer list
           is keyed by, and typing „aerial" once already cost a silent
           miss — the handover found no layer and did nothing. The
           display names travel along for the same reason: one source. */
        /* BESIDE THE AERIAL IMAGE THE MAP WITH THE WAYS: the topographic
           map until 01.10.2026, since then the vector map's OpenStreetMap
           — and that only once our Europe file is set up, without it the
           base map cannot go onto the phone (includes/offline_map.php).
           Its key is 'osm': map.js finds the layer by the display name,
           and „OpenStreetMap" is the vector base map's name there. */
        $offerable = array_intersect_key(map_layers(), array_flip(array_merge(['aerial_official'],
            in_array('europe', offline_map_files(), true) ? ['osm'] : [])));
    ?>
    <?php /* THE SIZE ON THE BUTTON ITSELF (24.08.2026): the
         participant decides whether this goes onto his phone, so the
         figure belongs where he decides, not in a sentence above. */ ?>
    <div class="button-row">
        <?php $first = true; foreach ($offerable as $key => $layer): ?>
            <?php /* The leader's estimate is the aerial image's; the base
                     map weighs a fraction of it and goes without a figure. */ ?>
            <a class="button<?= $first ? '' : ' subtle' ?>"
               href="<?= h(url('index.php')) ?>?offline=<?= h($key) ?>"><?= h($layer['name']) ?><?=
                $size !== null && $key === 'aerial_official' ? ' · ' . h($size) : '' ?></a>
            <?php $first = false; ?>
        <?php endforeach; ?>
        <a class="button subtle" href="<?= h(url('index.php')) ?>"><?= h(t('Später')) ?></a>
    </div>
    <p class="hint-text small">
        <?= h(count($offerable) > 1
            ? t('Beides geht auch nacheinander — und später jederzeit über das Ebenen-Symbol auf der Karte.')
            : t('Das geht auch später jederzeit über das Ebenen-Symbol auf der Karte.')) ?>
        <?php /* The same warning as at the door (13.09.2026): the link
                 comes by mail, mail is read at the desk, and the map
                 stays in the browser that fetched it. */ ?>
        <?= h(t('Die Karte bleibt auf dem Gerät, auf dem du sie lädst — also am besten hier auf dem Handy, das du im Gelände dabeihast.')) ?>
    </p>
<?php endif; ?>

<script type="module">
/* The button IS the request — see the head of this file. Whatever the
   answer, the path continues: a refusal is an answer too, and standing
   still would be the worse response to it. */
const next = <?= json_encode(match ($step) {
    'location' => url('pages/onboarding.php') . '?step=storage',
    'storage'  => $steps === 3 ? url('pages/onboarding.php') . '?step=maps' : url('index.php'),
    default    => url('index.php'),
}) ?>;
const button = document.getElementById('onboarding-allow');
// The maps step has no permission to ask — its links hand over directly.
if (button) {

function onward() {
    location.href = next;
}

button.addEventListener('click', () => {
    button.disabled = true;
    <?php if ($step === 'location'): ?>
        if (!navigator.geolocation) { onward(); return; }
        // The answer comes when it comes — and if the person never
        // answers, they are not stuck here: nothing else waits on it.
        navigator.geolocation.getCurrentPosition(onward, onward,
            { enableHighAccuracy: true, timeout: 20000 });
    <?php else: ?>
        (navigator.storage?.persist?.() ?? Promise.resolve()).then(onward, onward);
    <?php endif; ?>
});
}
</script>

<?php page_footer(); ?>
