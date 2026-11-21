<?php
/**
 * Guards the ADDRESSES OF FOREIGN SERVICES against our own renamings.
 *
 *   php tests/services.php        (from the project directory)
 *
 * WHY THIS EXISTS. On 22.08.2026 the move into English translated two
 * addresses that do not belong to us: the BfN's WFS path lost its
 * 'schutzgebiet' and became 'protected_area', and swisstopo's layer
 * 'pixelkarte-farbe' became 'pixelkarte-color'. Both look like our
 * vocabulary and are none — they are names on someone else's server.
 * PHP saw a string, the provider saw a path that is not there: protected
 * areas stopped loading per state, and the Swiss topographic map stayed
 * blank. Nothing in the project noticed; only the maintainer did, days later.
 *
 * A FOREIGN NAME IS DATA, NOT VOCABULARY. That is the rule this file
 * enforces. The list below is the record — whoever changes an address
 * changes it HERE too, and thereby says out loud that the provider
 * really renamed something.
 *
 * NO REQUESTS ARE MADE. The addresses are held against the registry,
 * nothing else. Whether a service answers is the business of
 * tests/protected_areas.php, and it is allowed to stay silent there;
 * here a difference is always our doing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/map_layers.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/* Every foreign address the map depends on, spelled as the provider
   spells it. Sorted, so a new entry lands in an obvious place. */
/* EVERY foreign address the map depends on, spelled as the provider
   spells it — the whole registry, frozen. Recorded in one piece and not
   by hand: a hand-kept excerpt tests the entries somebody thought of,
   and the two that broke were exactly the ones nobody thought of. */
const FOREIGN_ADDRESSES = [
    'https://clarity.maptiles.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    'https://dienste.gdi-sh.de/WMS_SH_DOP20col_OpenGBD',
    'https://geo4.service24.rlp.de/wms/rp_dop20.fcgi',
    'https://geodienste.bfn.de/ogc/wfs/schutzgebiet',
    'https://geodienste.bremen.de/wms_dop10_2023',
    'https://geodienste.sachsen.de/wms_geosn_dop-rgb/guest',
    'https://geoservice.dlr.de/eoc/land/wms',
    'https://geoservices.bayern.de/od/wms/dop/v1/dop20',
    'https://geoservices.buergernetz.bz.it/mapproxy/ows/wms',
    'https://idt2-geoserver.regione.veneto.it/geoserver/ows',
    'https://isk.geobasis-bb.de/mapproxy/dop20c/service/wms',
    'https://mapsneu.wien.gv.at/basemap/bmapgelaende/grau/google3857/{z}/{y}/{x}.jpeg',
    'https://mapsneu.wien.gv.at/basemap/bmaporthofoto30cm/normal/google3857/{z}/{y}/{x}.jpeg',
    'https://opendata.lgln.niedersachsen.de/doorman/noauth/dop_wms',
    'https://owsproxy.lgl-bw.de/owsproxy/ows/WMS_LGL-BW_ATKIS_DOP_20_C',
    'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
    'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}',
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    'https://services.bgr.de/wms/grundwasser/huek250/',
    'https://sgx.geodatenzentrum.de/wms_basemapde_schummerung',
    'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.leichte-basiskarte_reliefschattierung/default/current/3857/{z}/{x}/{y}.png',
    'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg',
    'https://www.gds-srv.hessen.de/cgi-bin/lika-services/de-viewer/access/ogc-free-images.ows',
    'https://www.geodaten-mv.de/dienste/adv_dop',
    'https://www.geodatenportal.sachsen-anhalt.de/wss/service/ST_LVermGeo_DOP_WMS_OpenData/guest',
    'https://www.geoproxy.geoportal-th.de/geoproxy/services/DOP',
    'https://www.wms.nrw.de/geobasis/wms_nw_dop',
];

/* The type names of the BfN area kinds — likewise the provider's, and
   likewise German. They travel as TYPENAMES into every request. */
const FOREIGN_TYPE_NAMES = [
    'bfn_sch_Schutzgebiet:Fauna_Flora_Habitat_Gebiete',
    'bfn_sch_Schutzgebiet:Nationalparke',
    'bfn_sch_Schutzgebiet:Naturschutzgebiete',
];

/** Every http(s) address inside the layer registry, however deep. */
function addressesIn(array $rows): array
{
    $found = [];
    array_walk_recursive($rows, static function ($value) use (&$found): void {
        if (is_string($value) && str_starts_with($value, 'http')) {
            $found[] = $value;
        }
    });
    sort($found);
    return array_values(array_unique($found));
}

/** Every WFS type name inside the registry. */
function typeNamesIn(array $rows): array
{
    $found = [];
    array_walk_recursive($rows, static function ($value) use (&$found): void {
        if (is_string($value) && str_contains($value, 'bfn_sch_')) {
            $found[] = $value;
        }
    });
    sort($found);
    return array_values(array_unique($found));
}

$registry = map_layers();

echo "\nForeign addresses\n";

$live = addressesIn($registry);
$recorded = FOREIGN_ADDRESSES;
sort($recorded);

$missing = array_values(array_diff($recorded, $live));
$added   = array_values(array_diff($live, $recorded));

check($missing === [],
    'every recorded address still stands in the registry'
    . ($missing === [] ? '' : ' — gone: ' . implode(', ', $missing)));

/* THE OTHER DIRECTION COUNTS JUST AS MUCH. An address in the registry
   that stands nowhere here is either genuinely new — then it belongs on
   the record — or the result of a rename, which is exactly the case this
   file exists for. Both want a human eye; neither may pass silently. */
check($added === [],
    'no address stands in the registry that is not on record here'
    . ($added === [] ? '' : ' — new: ' . implode(', ', $added)));

check(count($live) === count($recorded),
    sprintf('%d addresses, as recorded', count($recorded)));

echo "\nForeign type names\n";

$liveTypes = typeNamesIn($registry);
foreach (FOREIGN_TYPE_NAMES as $type) {
    check(in_array($type, $liveTypes, true), "the registry still holds $type");
}

echo "\n", $failed === 0 ? "All good.\n" : "$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
