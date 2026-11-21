<?php
/**
 * Location probe — shows what the browser actually delivers.
 *
 * Occasion: on an Android phone the location sat reliably about 50 meters
 * off, always at the same spot, and did not get better even after twenty
 * seconds. That is the pattern of a fix from a Wi-Fi database instead of
 * satellite — but it could not be proven from inside the application.
 *
 * The proof is in the fields that come along with lat/lon:
 *
 *   altitude   A satellite fix knows the altitude, a network fix does not.
 *              If it is empty, no GNSS receiver has measured. That is the
 *              most reliable single indicator.
 *   speed
 *   heading    Likewise come only from the receiver, and only while moving.
 *   accuracy   Satellite in the open 3-10 m, Wi-Fi 20-100 m, cell tower
 *              above that. On its own no proof though: under trees GNSS
 *              gets bad too.
 *
 * The page runs two methods side by side because they behave differently:
 * watchPosition reports on its own when something changes — on some devices,
 * while standing still, not at all anymore. The repeated single query
 * instead asks anew every two seconds.
 *
 * None of this leaves the device. The page sends no coordinate to the
 * server; it lives only in the browser.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';

login_required();

page_header(t('Standort-Probe'));
?>
<p class="hint-text">
    <?= h(t('Zeigt roh an, was die Standortbestimmung dieses Browsers meldet — auf welchem Weg gemessen wurde, verrät die Höhenangabe. Draußen ausführen, nicht am Fenster: das Signal braucht freien Himmel.')) ?>
</p>

<div class="card-block">
    <button type="button" class="button" id="start"><?= h(t('Messung starten')) ?></button>
    <button type="button" class="button subtle" id="stop" disabled><?= h(t('Stoppen')) ?></button>
    <button type="button" class="button subtle" id="copy" disabled><?= h(t('Ergebnis kopieren')) ?></button>
</div>

<div class="card-block">
    <p id="verdict" class="hint-text"><?= h(t('Noch nichts gemessen.')) ?></p>
</div>

<div class="table-frame">
    <table class="list" id="table">
        <thead>
            <tr>
                <th><?= h(t('Zeit')) ?></th><th><?= h(t('Weg')) ?></th><th><?= h(t('Genauigkeit')) ?></th>
                <th><?= h(t('Höhe')) ?></th><th><?= h(t('Höhe ±')) ?></th><th><?= h(t('Tempo')) ?></th><th><?= h(t('Position')) ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7">—</td></tr></tbody>
    </table>
</div>

<script type="module">
/* A MODULE, so the page shares the one t() from i18n.js instead of
   carrying a second catalog reader. Imported by the BARE path on purpose:
   the footer's modules import it bare too, and the import map
   (asset_import_map) versions both the same way — one module, not two
   (see CLAUDE.md, trap 5). Module timing also guarantees
   window.LANG_DATA from page_footer() has been set before this runs. */
import { t } from <?= json_encode(url('assets/js/i18n.js')) ?>;
const buttonStart = document.getElementById('start');
const buttonStop  = document.getElementById('stop');
const buttonCopy = document.getElementById('copy');
const verdict     = document.getElementById('verdict');
const tableBody    = document.querySelector('#table tbody');

let watcher = null, timer = null, rows = [], first = true;

const formatNumber = (w, n = 1) => w === null || w === undefined ? '—' : w.toFixed(n);

function record(pos, method) {
    const c = pos.coords;
    if (first) { tableBody.innerHTML = ''; first = false; }

    rows.push({
        time: new Date(pos.timestamp).toISOString(),
        method,
        accuracy_m: c.accuracy,
        elevation_m: c.altitude,
        elevation_accuracy_m: c.altitudeAccuracy,
        speed_ms: c.speed,
        heading: c.heading,
        lat: c.latitude,
        lon: c.longitude,
    });

    const tr = document.createElement('tr');
    for (const text of [
        new Date(pos.timestamp).toLocaleTimeString('de-DE'),
        method,
        '±' + formatNumber(c.accuracy, 0) + ' m',
        c.altitude === null ? '—' : formatNumber(c.altitude, 0) + ' m',
        c.altitudeAccuracy === null || c.altitudeAccuracy === undefined
            ? '—' : '±' + formatNumber(c.altitudeAccuracy, 0) + ' m',
        c.speed === null ? '—' : formatNumber(c.speed, 1) + ' m/s',
        c.latitude.toFixed(6) + ', ' + c.longitude.toFixed(6),
    ]) {
        const td = document.createElement('td');
        td.textContent = text;
        tr.appendChild(td);
    }
    tableBody.prepend(tr);

    assess();
}

/** Distance between two points in meters. Enough for a few hundred meters. */
function distance(a, b) {
    const R = 6371000;
    const dLat = (b.lat - a.lat) * Math.PI / 180;
    const dLon = (b.lon - a.lon) * Math.PI / 180;
    const m    = (a.lat + b.lat) / 2 * Math.PI / 180;
    const x = dLon * Math.cos(m);
    return Math.sqrt(dLat * dLat + x * x) * R;
}

/** Largest distance between two reports — the jitter of the fix. */
function scatter() {
    let max = 0;
    for (let a = 0; a < rows.length; a++) {
        for (let b = a + 1; b < rows.length; b++) {
            max = Math.max(max, distance(rows[a], rows[b]));
        }
    }
    return max;
}

/**
 * Assessment in three tiers, because "an altitude comes in" alone is too little.
 *
 * Android's fused location service mixes GPS, Wi-Fi and sensors and can
 * include an altitude even when the horizontal position comes from the
 * network. So the altitude rules out a network fix — but it does not yet
 * prove an ongoing measurement.
 *
 * Two other indicators are harder for that:
 *
 *   Timestamp   If we ask every two seconds and always get the same
 *               timestamp back, no new measurement happens at all. Then
 *               nothing can get more accurate, no matter how long you wait.
 *   Scatter     An ongoing satellite measurement jitters: consecutive
 *               reports lie a few meters apart. A standing position is
 *               identical to six decimal places — that cannot be a
 *               measurement, that is a stored value.
 */
function assess() {
    const n        = rows.length;
    const withAltitude = rows.filter(z => z.elevation_m !== null && z.elevation_m !== undefined).length;
    const best    = Math.min(...rows.map(z => z.accuracy_m));
    const times   = new Set(rows.map(z => z.time)).size;
    const places     = new Set(rows.map(z => z.lat + ',' + z.lon)).size;
    const jitter  = scatter();

    /* The <strong> travels INSIDE the key: the emphasised lead and the
       sentence after the dash are one sentence — split at the tag, the
       translation could never reorder it. */
    const suffix = ' ' + t('Beste Genauigkeit ±%s m, %s verschiedene Zeitstempel und %s verschiedene Positionen bei %s Meldungen, Streuung %s m.',
        Math.round(best), times, places, n, jitter.toFixed(1));

    if (n >= 3 && times === 1) {
        verdict.innerHTML = t('<strong>Es wird nicht neu gemessen</strong> — alle %s Meldungen tragen denselben Zeitstempel. Der Browser gibt eine einmal ermittelte Position immer wieder heraus, statt den Empfänger zu fragen. Warten hilft dagegen nicht.', n) + suffix;
    } else if (n >= 3 && places === 1) {
        verdict.innerHTML = t('<strong>Position steht fest</strong> — %s Meldungen, aber nur eine einzige Position, auf den Zentimeter gleich. Eine laufende Satellitenmessung zappelt immer ein wenig; das hier ist ein gespeicherter Wert.', n) + suffix;
    } else if (withAltitude === 0) {
        verdict.innerHTML = t('<strong>Keine Satellitenortung</strong> — keine der %s Meldungen trägt eine Höhe. Das spricht für eine Ortung ausschließlich über WLAN und Funkzelle.', n) + suffix;
    } else {
        verdict.innerHTML = t('<strong>Laufende Satellitenmessung</strong> — %s von %s Meldungen tragen eine Höhe, und die Position bewegt sich. Bleibt die Genauigkeit trotzdem schlecht, liegt es am Empfang vor Ort, nicht am Browser.', withAltitude, n) + suffix;
    }
}

function error(e, method) {
    verdict.textContent = t('Fehler (%s): %s [Code %s]', method, e.message, e.code);
}

buttonStart.addEventListener('click', () => {
    rows = []; first = true;
    tableBody.innerHTML = '<tr><td colspan="7">' + t('Messung läuft …') + '</td></tr>';
    buttonStart.disabled = true;
    buttonStop.disabled = false;
    buttonCopy.disabled = false;

    const options = { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 };

    watcher = navigator.geolocation.watchPosition(
        p => record(p, 'watch'), e => error(e, 'watch'), options);

    // Additionally ask anew every two seconds. On some devices watchPosition
    // no longer reports at all while standing still — this way you still see
    // whether reception is improving.
    timer = setInterval(() => {
        navigator.geolocation.getCurrentPosition(
            p => record(p, t('einzeln')), e => error(e, t('einzeln')), options);
    }, 2000);
});

buttonStop.addEventListener('click', () => {
    if (watcher !== null) { navigator.geolocation.clearWatch(watcher); watcher = null; }
    clearInterval(timer);
    buttonStart.disabled = false;
    buttonStop.disabled = true;
});

buttonCopy.addEventListener('click', async () => {
    const report = {
        browser: navigator.userAgent,
        reports: rows,
    };
    try {
        await navigator.clipboard.writeText(JSON.stringify(report, null, 2));
        buttonCopy.textContent = t('Kopiert');
        setTimeout(() => { buttonCopy.textContent = t('Ergebnis kopieren'); }, 2000);
    } catch {
        verdict.textContent = t('Kopieren ging nicht — die Tabelle lässt sich auch von Hand markieren.');
    }
});
</script>

<p style="margin-top:2rem"><a class="button subtle" href="<?= h(url()) ?>"><?= h(t('Zurück zur Karte')) ?></a></p>

<?php page_footer(); ?>
