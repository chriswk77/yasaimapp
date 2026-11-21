<?php
/**
 * Registry of the map layers.
 *
 * All services are free and need no key. New layers are added here and
 * appear in the interface without any further change.
 *
 * State of verification: 07.08.2026 — all listed services answered
 * GetCapabilities at that time.
 *
 * On the aerial imagery: the state services deliver 20 cm ground
 * resolution, in Nordrhein-Westfalen even 10 cm, in Switzerland 10 cm in
 * places. Google Satellite sits at about 50 cm. For Saarland, Berlin and
 * Hamburg no freely reachable service could be found; there the worldwide
 * fallback layer takes over.
 *
 * Besides the sixteen federal states, Austria, Switzerland and South Tyrol
 * are included — same layer, same assignment, just other regions.
 *
 * Which region is responsible is decided by the real borders from
 * assets/geo/areas.json — not by bounding rectangles. In Germany those
 * overlap so heavily that Marburg, for example, lies inside the rectangle
 * of Nordrhein-Westfalen and of course has no imagery there.
 *
 * 'minZoom' is the level from which a service actually draws something.
 * Below it, some deliver an empty tile without announcing it — this was
 * verified with real GetMap requests across the whole zoom range, not from
 * the claims in GetCapabilities. Below the limit the fallback layer takes
 * over.
 *
 * 'formatTransparent' applies as soon as several states are in view at the
 * same time — along the Rhine between Wiesbaden and Mainz, for instance.
 * Then the services stack on top of each other, and all but the bottom one
 * must deliver transparency outside their region.
 *
 * The formats were read out of each service individually, not guessed:
 *   image/vnd.jpeg-png8  delivers JPEG as long as the tile is opaque and
 *                        switches to PNG8 only when needed — by far the
 *                        most economical (opaque 23 kB, border 50 kB,
 *                        outside 116 bytes). Far from all states can do it.
 *   image/png; mode=8bit  the leaner PNG variant, where it exists.
 *   image/png             the fallback; noticeably heavier for aerial
 *                         imagery.
 */

declare(strict_types=1);

// The rain picture's box, steps and colours (the 'rain' entry and its legend).
require_once __DIR__ . '/rain.php';

function map_layers(): array
{
    return [
        // -------------------------------------------------------------------
        // Aerial imagery — a single layer, assembled from several sources.
        //
        // At the bottom the worldwide image from Esri, which shows something
        // everywhere. On top of it every official service whose region lies
        // in the current view: at a state border that means several side by
        // side, so both sides are sharp at the same time. Where nothing
        // finer exists, the worldwide imagery stays visible.
        // -------------------------------------------------------------------
        'aerial_official' => [
            'name'        => 'Luftbild',
            'group'      => 'base',
            'type'         => 'wms-by-area',
            'format'      => 'image/jpeg',
            'maxZoom'     => 20,
            'attribution' => 'Luftbilder der Landesvermessungen, dl-de/by-2-0',
            'bounds'     => 'assets/geo/areas.json',

            // ONE PICTURE OF 512 INSTEAD OF FOUR OF 256 from every state's
            // WMS (01.10.2026: „Ja, bau die großen Bilder für alle
            // Länderdienste"). A WMS draws whatever size it is asked for, and
            // what costs is the request, not the pixels. Measured that day at
            // level 19, four 256 tiles in a row against one 512 of the same
            // ground, three samples each, the same bytes either way:
            //   NRW 4.9×, BW 3.9×, Sachsen 4.2×, Brandenburg 3.8×, RLP 3.6×,
            //   Venetien 3.6×, Niedersachsen 3.5×, Bayern 3.3×, Thüringen 3.1×,
            //   Sachsen-Anhalt 3.1×, Südtirol 2.6×, Hessen 2.5×, MV 2.5×,
            //   Bremen 2.4×, Schleswig-Holstein 1.7× faster.
            // An area download needs a quarter of the requests, and the map
            // online fills a screen sooner. Österreich and Schweiz deliver a
            // fixed tile grid and stay as they are; a region may still say
            // 'tileSize' itself. Areas loaded before lie under the 256
            // addresses — the vector map still finds them there (regionParts).
            'wmsTileSize' => 512,

            // 'fallbackAlways' => true  puts the worldwide image permanently
            // underneath the state services, instead of showing it only when
            // nothing else draws. That also closes gaps in the middle of a
            // state's region — but it costs considerably, and measurably so:
            //
            // For Esri to shine through, the state services must deliver
            // transparency. Only Hessen and Bayern offer a mixed format for
            // that, which stays JPEG until transparency is actually needed.
            // All the others refuse it and send PNG:
            //   Sachsen 16 -> 178 kB, Thüringen 16 -> 181 kB,
            //   Brandenburg 16 -> 172 kB, Nordrhein-Westfalen 16 -> 61 kB.
            // For Sachsen that is 2.8 MB per screen instead of 250 kB.
            //
            // Hence off: gaps inside a state's region are rare with the
            // official services, tenfold consumption on a phone in the
            // woods is not. Switching takes just the one line.
            'fallbackAlways' => false,
            // From which level on the official services get loaded at all.
            //
            // They are slow: measured over nine tiles each, Esri takes 120
            // to 370 milliseconds, Hessen by contrast 460 to 950. As long
            // as they deliver nothing better, they are pure waiting time.
            //
            // And they only get better late. Esri sits at roughly 0.3 to
            // 0.5 meters per pixel and is thus maxed out by level 16;
            // comparing the same views shows no difference between 14 and
            // 16, but a clear one from 17 on — roof shapes, path edges,
            // individual vehicles.
            //
            // Below that the layer therefore shows only the worldwide
            // image. While searching in the field the map stays fluid that
            // way; zooming in on a point brings the full resolution.
            'minZoomOverall' => 16,
            'regions'     => [
                [
                    'region'   => 'Bremen',
                    'url'    => 'https://geodienste.bremen.de/wms_dop10_2023',
                    'layers' => 'DOP10_2023_HB',
                    'formatTransparent' => 'image/vnd.jpeg-png8',
                    'minZoom' => 9,
                ],
                [
                    'region'   => 'Nordrhein-Westfalen',
                    'url'    => 'https://www.wms.nrw.de/geobasis/wms_nw_dop',
                    'layers' => 'nw_dop_rgb',
                    'formatTransparent' => 'image/png; mode=8bit',
                ],
                [
                    'region'   => 'Niedersachsen',
                    'url'    => 'https://opendata.lgln.niedersachsen.de/doorman/noauth/dop_wms',
                    'layers' => 'ni_dop20',
                    'formatTransparent' => 'image/vnd.jpeg-png8',
                ],
                [
                    // he_dop_rgb is the scale-dependent umbrella layer. The
                    // obvious he_dop20_rgb only draws from about 1:10000 on
                    // and stays empty below that — the reason Hessen was
                    // not visible at all at first.
                    'region'   => 'Hessen',
                    'url'    => 'https://www.gds-srv.hessen.de/cgi-bin/lika-services/de-viewer/access/ogc-free-images.ows',
                    'layers' => 'he_dop_rgb',
                    'formatTransparent' => 'image/vnd.jpeg-png8',
                ],
                [
                    'region'   => 'Rheinland-Pfalz',
                    'url'    => 'https://geo4.service24.rlp.de/wms/rp_dop20.fcgi',
                    'layers' => 'rp_dop20',
                    'formatTransparent' => 'image/vnd.jpeg-png8',
                ],
                [
                    'region'   => 'Baden-Württemberg',
                    'url'    => 'https://owsproxy.lgl-bw.de/owsproxy/ows/WMS_LGL-BW_ATKIS_DOP_20_C',
                    'layers' => 'IMAGES_DOP_20_RGB',
                    'formatTransparent' => 'image/png',
                ],
                [
                    'region'   => 'Bayern',
                    'url'    => 'https://geoservices.bayern.de/od/wms/dop/v1/dop20',
                    'layers' => 'by_dop20c',
                    'formatTransparent' => 'image/vnd.jpeg-png',
                ],
                [
                    // th_dop20rgb is not public; the service answers it
                    // with "Missing WMS:GetMap right".
                    'region'   => 'Thüringen',
                    'url'    => 'https://www.geoproxy.geoportal-th.de/geoproxy/services/DOP',
                    'layers' => 'th_dop',
                    'formatTransparent' => 'image/png; mode=8bit',
                    'minZoom' => 9,
                ],
                [
                    'region'   => 'Sachsen',
                    'url'    => 'https://geodienste.sachsen.de/wms_geosn_dop-rgb/guest',
                    'layers' => 'sn_dop_020',
                    'formatTransparent' => 'image/png',
                ],
                [
                    // NO CORS — measured 01.10.2026: the vector map cannot read
                    // its pictures and asks through our server
                    // (includes/tile_relay.php). Schleswig-Holstein the same.
                    'region'   => 'Sachsen-Anhalt',
                    'url'    => 'https://www.geodatenportal.sachsen-anhalt.de/wss/service/ST_LVermGeo_DOP_WMS_OpenData/guest',
                    'layers' => 'lsa_lvermgeo_dop20_2',
                    'formatTransparent' => 'image/png',
                    'relay'  => true,
                ],
                [
                    'region'   => 'Brandenburg',
                    'url'    => 'https://isk.geobasis-bb.de/mapproxy/dop20c/service/wms',
                    'layers' => 'bebb_dop20c',
                    'formatTransparent' => 'image/png',
                ],
                [
                    'region'   => 'Mecklenburg-Vorpommern',
                    'url'    => 'https://www.geodaten-mv.de/dienste/adv_dop',
                    'layers' => 'mv_dop',
                    'formatTransparent' => 'image/png; mode=8bit',
                    'minZoom' => 11,
                ],
                [
                    'region'   => 'Schleswig-Holstein',
                    'url'    => 'https://dienste.gdi-sh.de/WMS_SH_DOP20col_OpenGBD',
                    'layers' => 'sh_dop20_rgb',
                    'formatTransparent' => 'image/png',
                    'relay'  => true,      // no CORS, see Sachsen-Anhalt
                ],

                // ---------------------------------------------------------
                // Alpine countries. Austria and Switzerland deliver ready-
                // made tile grids instead of WMS — hence 'tileUrl'
                // instead of 'url'/'layers'. That is the faster route, and
                // outside their region they deliver nothing on their own,
                // so the fallback layer shines through underneath.
                // ---------------------------------------------------------
                [
                    // basemap.at, 30 cm, all of Austria from one source.
                    // Mind the order in the template: first the row, then
                    // the column.
                    'region'      => 'Österreich',
                    'tileUrl' => 'https://mapsneu.wien.gv.at/basemap/bmaporthofoto30cm/normal/google3857/{z}/{y}/{x}.jpeg',
                    'maxZoom'   => 19,
                    'attribution' => 'Datenquelle: basemap.at (CC BY 4.0)',
                    // Up to around ten kilometers beyond the border the
                    // service delivers not a 404 but a white tile. That
                    // covered up the Bavarian image at Lake Constance and
                    // on the Inn. See WithoutFillTiles in layers.js.
                    'discardFillTiles' => true,
                ],
                [
                    // SWISSIMAGE from swisstopo, 10 to 25 cm depending on
                    // the area — the sharpest imagery included here.
                    'region'      => 'Schweiz',
                    'tileUrl' => 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg',
                    'maxZoom'   => 20,
                    'attribution' => '© swisstopo',
                    // Like basemap.at, only it shows further out: all
                    // around Switzerland swisstopo generously keeps
                    // drawing, but beyond that delivers opaque white
                    // instead of 404 — near Rosenheim, say, a 668-byte
                    // white tile. Since Switzerland sits above Bayern and
                    // Austria in the stack, at small scales that covered
                    // up half the Alps.
                    'discardFillTiles' => true,
                ],
                [
                    // Only the province of Bozen, not Trentino — the
                    // service does not cover the neighboring area. The
                    // outlines therefore come from the province border,
                    // not from the region.
                    'region'   => 'Südtirol',
                    'url'    => 'https://geoservices.buergernetz.bz.it/mapproxy/ows/wms',
                    'layers' => 'p_bz-Orthoimagery:Aerial-2023-RGB',
                    'formatTransparent' => 'image/png',
                    'attribution' => '© Autonome Provinz Bozen – Südtirol',
                ],
                [
                    // Outside Veneto this service delivers black instead of
                    // transparent tiles and would cover the neighboring
                    // image at the border. 'blackTransparent' computes
                    // that away in the browser — see BlackTransparent in
                    // layers.js. The well-behaved vintage rv:ortofoto_2021
                    // would be the simpler choice, but is three years older.
                    'region'   => 'Venetien',
                    'url'    => 'https://idt2-geoserver.regione.veneto.it/geoserver/ows',
                    'layers' => 'rv:ortofoto_agea_2024',
                    'blackTransparent' => true,
                    'attribution' => '© Regione del Veneto',
                ],
            ],
            // Labeling, like Google's hybrid mode: place names, districts,
            // waters, state and county borders, plus the road network with
            // names and motorway numbers.
            //
            // Bound firmly to the aerial layer instead of being its own
            // checkbox: the other base maps bring their own labeling, there
            // it would be doubled. Two grids from Esri, tuned to their
            // aerial imagery — light lettering with a dark outline,
            // readable over forest. The alternatives from CARTO were
            // nearly invisible on top of it.
            //
            // Order: first the roads, then the names on top.
            'label' => [
                [
                    'url'     => 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}',
                    'maxZoom' => 19,
                ],
                [
                    'url'     => 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
                    'maxZoom' => 19,
                ],
            ],
            'labelAttribution' => 'Esri, HERE, Garmin, © OpenStreetMap-Mitwirkende',

            // The foundation: worldwide, available everywhere, around
            // 50 cm. Everything finer lays itself on top, where it exists.
            //
            // TWO SOURCES, ONE LAYER. Esri Clarity shows central Europe
            // noticeably more neutral than the older World Imagery —
            // measured on the same forest tile in the Spessart 50/53/45
            // instead of 35/46/13, i.e. without the olive cast that turns
            // every conifer forest into a black-green expanse. Path courses
            // and stand boundaries that drown there are visible in Clarity.
            //
            // In return Clarity has gaps: in the Spessart it ends at level
            // 18, World Imagery delivers up to 20. A swap would thus tear a
            // hole exactly where you look in deepest. Hence 'alternateUrl'
            // instead of a second layer underneath — exactly one is loaded
            // per tile, and only where Clarity has nothing does the old
            // source step in. See WithFallbackSource in assets/js/layers.js.
            'fallback' => [
                'type'         => 'tile',
                'url'         => 'https://clarity.maptiles.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                'alternateUrl' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                'attribution' => 'Esri, Maxar, Earthstar Geographics',
                'maxZoom'     => 19,
            ],
        ],

        // THE TOPOGRAPHIC MAP AND OPENTOPOMAP WENT on 01.10.2026 (
        // „Nimm die topografische Karte und OpenTopoMap raus"). The vector
        // map's own OpenStreetMap took their look and their content —
        // TopPlusOpen's measured colours, its symbols in forest and meadow,
        // houses, tracks, quarries, orchards, marsh, cliffs, peaks
        // (vectorlayers.js, topoLayers) — and it comes along offline.

        // NO PRELOADING for the following service, and that is not a
        // technical restriction but an agreement. (OpenTopoMap stood here
        // too, under the same terms, until 01.10.2026.)
        //
        // It is run on donations from volunteers and provides its tiles
        // for viewing a map — expressly not for downloading whole areas.
        // The OpenStreetMap terms of use call "bulk downloading" by name
        // and forbid it. Whoever does it anyway gets blocked: first 403,
        // then longer.
        //
        // Exactly that happened here, because preloading initially treated
        // every layer the same. Offline there is a substitute meant for it:
        // the vector map's OpenStreetMap from our own Europe file
        // (includes/offline_map.php) and the official aerial imagery. Online
        // it stays usable unchanged — viewing was never the problem. Only
        // the Leaflet fallback still shows it; the vector map draws its own.
        'osm' => [
            'name'          => 'OpenStreetMap',
            'group'        => 'base',
            'type'           => 'tile',
            'url'           => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution'   => '© OpenStreetMap-Mitwirkende',
            'maxZoom'       => 19,
            'noPrefetch' => 'Freiwilligendienst — Massendownload nicht erlaubt',
        ],

        // -------------------------------------------------------------------
        // Overlays. They are laid over the base map and can be adjusted in
        // opacity.
        // -------------------------------------------------------------------
        // Hillshades are opaque, mostly white graphics: white means "no
        // shading", only the slopes are gray. Something like that belongs
        // multiplied, not laid on with opacity — otherwise a light haze
        // settles over the map, the shadows fade, and the relief flips in
        // the eye (valleys look like mountains). This holds equally for all
        // three services used here; all deliver bright, opaque images
        // (mean around 200 of 255).
        //
        // The basemap.de hillshade ends at the German border — in Austria
        // and Switzerland it delivers transparent tiles, so there the layer
        // was ineffective. Hence by region here too: only the exceptions
        // are listed, Germany is the fallback.
        'hillshade' => [
            'name'        => 'Geländeschummerung',
            'group'      => 'overlay',
            'type'         => 'wms-by-area',
            'maxZoom'     => 20,
            'blendMode'  => 'multiply',
            'opacity'   => 0.65,
            'bounds'     => 'assets/geo/areas.json',
            'regions'     => [
                [
                    'region'      => 'Österreich',
                    'tileUrl' => 'https://mapsneu.wien.gv.at/basemap/bmapgelaende/grau/google3857/{z}/{y}/{x}.jpeg',
                    // From level 18 on the service answers with 404.
                    'maxZoom'   => 17,
                    'attribution' => 'Datenquelle: basemap.at (CC BY 4.0)',
                ],
                [
                    // The alternative ch.swisstopo.digitales-hoehenmodell_25
                    // draws more strongly, but gets thin from level 16 on
                    // and cuts out entirely at 19. This one stays
                    // throughout.
                    'region'      => 'Schweiz',
                    'tileUrl' => 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.leichte-basiskarte_reliefschattierung/default/current/3857/{z}/{x}/{y}.png',
                    'maxZoom'   => 19,
                    'attribution' => '© swisstopo',
                ],
            ],
            // Lit from the northwest, as is cartographic custom. Verified
            // via the correlation with Esri World Hillshade, which is
            // known to be lit from the northwest: +0.63 to +0.87 in four
            // test areas.
            'fallback' => [
                'url'           => 'https://sgx.geodatenzentrum.de/wms_basemapde_schummerung',
                'layers'        => 'de_basemapde_web_raster_hillshade',
                // JPEG, and this is the only thematic layer where that is
                // right. A hillshade is a soft gray-gradient image —
                // exactly what JPEG was built for and what PNG fails at.
                // Measured on the same tile in the Taunus: 99.7 kB as PNG,
                // 11.3 kB as JPEG. The deviation is at most 4 of 255
                // levels, on average 0.56 — invisible at 65% opacity. It
                // was by far the heaviest layer of all, heavier than the
                // aerial imagery.
                //
                // That transparency falls away with it does no harm here:
                // outside Germany the service delivers pure white, and
                // white is the neutral element under multiplication. The
                // layer is thus just as ineffective there as with a
                // transparent tile — remeasured in Tyrol and Lorraine.
                //
                // 'image/png8' would have been the obvious middle way and
                // is not: the service squeezes 228 gray levels down to 15,
                // and a relief in 15 levels shows stairs instead of slopes.
                'format'        => 'image/jpeg',
                // The service computes the slope per request and has no
                // neighbor pixels at the tile edge, which leaves a lighter
                // line standing there. It only becomes visible when
                // multiplying, as a fine grid across the map. Four times
                // larger tiles mean four times fewer edges.
                'tileSize' => 512,
                'attribution'   => 'basemap.de / BKG',
            ],
        ],

        // Limestone or silicate — for lime-loving species the actual lever.
        //
        // The soil pH from SoilGrids used to sit here. It is unfit for this
        // question, demonstrably so: checked at eight places with known
        // bedrock, it rendered the Franconian Jura and the Karwendel — two
        // of the most pronounced limestone areas of central Europe — in the
        // same color as the granite of the Bavarian Forest. Even at 60 to
        // 100 cm depth the Karwendel stayed "acidic". SoilGrids is a model
        // computed from soil samples, and in the high mountains there are
        // hardly any of those.
        //
        // This map answers the question directly, because it shows not the
        // soil chemistry but the parent rock. The same eight places: all
        // classified correctly.
        //
        // Two limitations: Germany only, outside the service delivers
        // transparency. And the layer name is the bare digit 5 — the
        // position within the service. Should the BGR reorder, the layer
        // shows something else; then update the number here from
        // GetCapabilities.
        'rock_type' => [
            'name'        => 'Gesteinstyp (Kalk/Silikat)',
            'group'      => 'overlay',
            'type'         => 'wms',
            'url'         => 'https://services.bgr.de/wms/grundwasser/huek250/',
            'layers'      => '5',
            'format'      => 'image/png',
            'opacity'   => 0.55,
            'attribution' => 'Geochemischer Gesteinstyp aus HÜK250 © BGR, Hannover',
            'hint'     => 'Ausgangsgestein, nicht Bodenchemie. Nur Deutschland.',

            // Colors read out of the service's legend graphic. Checked in
            // three areas: the tiles contain exclusively these ten colors,
            // exact and without intermediate tones — which is why single
            // classes can be hidden via the legend.
            //
            // Ordered by usefulness, not by systematics: whoever looks for
            // limestone shall find it first.
            'legend' => [
                ['color' => '#bee8ff', 'text' => 'karbonatisch'],
                ['color' => '#ffffcc', 'text' => 'silikatisch'],
                ['color' => '#ccffff', 'text' => 'silikatisch/karbonatisch', 'wide' => true],
                ['color' => '#ccffcc', 'text' => 'silikatisch/organisch',    'wide' => true],
                ['color' => '#ffcc99', 'text' => 'sulfatisch'],
                ['color' => '#d7d79e', 'text' => 'halitisch'],
                ['color' => '#ffccff', 'text' => 'sulfatisch/halitisch',     'wide' => true],
                ['color' => '#73b2ff', 'text' => 'Gewässer'],
                ['color' => '#c7c7c7', 'text' => 'Auffüllung'],
                ['color' => '#e1e1e1', 'text' => 'keine Angaben', 'wide' => true],
            ],
        ],

        'tree_species' => [
            'name'        => 'Baumarten (10 m)',
            'group'      => 'overlay',
            'type'         => 'wms',
            'url'         => 'https://geoservice.dlr.de/eoc/land/wms',
            'layers'      => 'TREE_SPECIES_DE_2022',
            // PNG, and here there is no way around it: deselecting single
            // species recognizes them by their exact color in the image.
            // JPEG smears exactly these colors, and the legend would grasp
            // at nothing. Unlike with the hillshade this is no size issue —
            // 26 kB for this area.
            'format'      => 'image/png',
            'opacity'   => 0.6,
            'attribution' => 'DLR EOC, Baumartenkarte Deutschland 2022',
            'hint'     => 'Vorherrschende Art je 10-Meter-Feld, Sentinel 2022.',

            // Colors and classes are read out of the service's legend
            // graphic, not estimated. Order as there: first the conifers,
            // then the broadleaf trees.
            //
            /* 'show' IS OUR COLOUR, 'color' IS THE PROVIDER'S — and the
               difference is not cosmetic: 'color' is what the pixels in
               the delivered PNG really are, and deselecting a species
               finds them by exactly that number (ClassRaster in
               layers.js). It must therefore never be prettified. Where
               'show' stands, the browser repaints those pixels and the
               legend patch shows the new colour; the matching goes on
               using the old one.

               TWO PAIRS WERE TOO CLOSE TO TELL APART with everything
               switched on (11.09.2026: „Kiefer und Eiche haben
               beide einen Gelbton, das ist echt schwer zu unterscheiden"
               and „Tanne und Fichte auch, mit diesen beiden Grüntönen").
               Both moved out of their family altogether, because a third
               yellow-green or a third green would only have postponed the
               question:

                   Kiefer  #becd46 → #1b4098
                   Tanne   #1e6405 → #870df2

               AND WHAT THE FIRST ATTEMPT TAUGHT, because it is worth more
               than the numbers: Kiefer was a sky blue (#1e90ff) first,
               chosen as the colour with the greatest CIELAB distance to
               all others. The maintainer looked at the map and said: „Optisch
               das nächste Paar find ich jetzt Kiefer und Lärche, beide
               sind sie blau." The measurement had said 34 for that pair —
               and it was wrong, in the one place where the plain CIELAB
               distance is known to be wrong, the blues. What his eye
               went by he said in the same breath, about the pair he
               accepted: „Das Lila ist dunkel und das Sonstige-Pink ist
               heller, das kann man nebeneinander unterscheiden."

               SO THE MEASURE HERE IS LIGHTNESS AND SATURATION, not hue
               distance. On ten-metre speckle under 60 per cent opacity a
               hue is hard to judge; light against dark is not. Measured
               over three aerial grounds, L* and chroma of the mix:

                   the pair he accepts   Tanne / Sonstige   ΔL 13, ΔC 43
                   the pair he rejected  Kiefer / Lärche    ΔL  6, ΔC 11
                   now                   Kiefer / Lärche    ΔL 24, ΔC  3
                                         Kiefer / Douglasie ΔL 12, ΔC 21
                                         Kiefer / Tanne     ΔL  3, ΔC 49

               Every one of them carries at least as much separation as
               the pair he called readable. Whoever picks a new colour
               here measures those two numbers, not the hue. */
            'legend' => [
                ['color' => '#becd46', 'show' => '#1b4098', 'text' => 'Kiefer'],
                ['color' => '#50b978', 'text' => 'Fichte'],
                ['color' => '#5f7387', 'text' => 'Douglasie'],
                ['color' => '#3cb4c8', 'text' => 'Lärche'],
                ['color' => '#1e6405', 'show' => '#870df2', 'text' => 'Tanne'],
                ['color' => '#e6323c', 'text' => 'Buche'],
                ['color' => '#f0dc00', 'text' => 'Eiche'],
                ['color' => '#bea078', 'text' => 'Birke'],
                ['color' => '#fa8c3c', 'text' => 'Erle'],
                ['color' => '#be64c8', 'text' => 'Sonstige'],
                // 'wide': too long for one column, gets a row of its own.
                ['color' => '#000000', 'text' => 'Kronendachverlust', 'wide' => true, 'extra' => 'Fläche ohne Kronendach — Sturm, Käfer oder Einschlag'],
            ],
        ],

        // Protected areas come as geometry, not as a finished image.
        //
        // The BfN's WMS knows only one style per area type: hatched
        // surfaces. On aerial imagery that is barely readable, and which
        // area you have in front of you it does not say at all. But the
        // same service also hands the areas out as GeoJSON — with that we
        // draw them ourselves: strong outline, hardly any fill, name in
        // the middle, and a tap shows ordinance and acreage.
        //
        // The data volume climbs steeply when zooming out; measured in the
        // Taunus across all three types: level 13 around 39 kB, level 12
        // around 249 kB, level 11 around 470 kB, level 10 already 1.2 MB
        // and level 9 over 5 MB. That is why level 11 is the end — below
        // it the map would hang.
        'protected_areas' => [
            'name'        => 'Schutzgebiete',
            'group'      => 'overlay',
            'type'         => 'wfs-outline',
            'url'         => 'https://geodienste.bfn.de/ogc/wfs/schutzgebiet',
            'minZoom'     => 11,
            'attribution' => 'Bundesamt für Naturschutz',

            // The colors are chosen for readability over aerial imagery,
            // not by map convention: green vanishes on forest.
            //
            // 'wide' sets the legend to a single column: "Naturschutzgebiet"
            // fits in no half column and would otherwise break mid-word.
            //
            // NAME AND STATE FIELD PER KIND, because the provider has named
            // them differently before and may again. Until September 2026
            // the FFH areas carried 'Gebietsname' and 'Bundesland'; between
            // the 24th and the 30th the service was rebuilt, and since then
            // all three carry 'NAME' and 'BL' (measured 30.09.2026 — along
            // with the box that now goes in UTM, see outlineKindData in
            // layers.js). A filter on a field that does not exist does not
            // come back as an error one could read — the request simply
            // fails at the network level ("Failed to fetch", no status,
            // 200 ms): the FFH areas of a state could no longer be
            // downloaded. The old names stay as fallbacks where stored data
            // is read (areaName in layers.js), since devices hold downloads
            // from before.
            /* 'currencyLabel' IS THE PROVIDER'S OWN WORD, not ours. The
               service names the vintage of its data in its capabilities
               abstract — "Stand der Daten: FFH-Gebiete (2019),
               Nationalparke (2025), Naturschutzgebiete (2023), …" — and
               only under exactly those labels. Written down here instead
               of guessed from 'text' in the browser: 'Nationalpark' would
               have to become 'Nationalparke' and 'FFH-Gebiet' become
               'FFH-Gebiete', and a rule for that is a rule about someone
               else's prose. Whoever adds an area kind copies its label
               out of the abstract. */
            /* 'short' IS THE KIND'S SHORT NAME as the BfN writes it — NLP,
               NSG, FFH. The point window's facts line shows the kind by it
               alone (fillDetailUneditable in map.js), the legend puts it
               behind the name. NLP and not NP: NP is just as often the
               Naturpark, a weaker kind of its own. */
            'kinds' => [
                [
                    'type'        => 'bfn_sch_Schutzgebiet:Nationalparke',
                    'currencyLabel' => 'Nationalparke',
                    'regionField' => 'BL',
                    'text'       => 'Nationalpark',
                    'short'      => 'NLP',
                    'wide'      => true,
                    'color'      => '#ff5f6d',
                    'nameField' => 'NAME',
                ],
                [
                    'type'        => 'bfn_sch_Schutzgebiet:Naturschutzgebiete',
                    'currencyLabel' => 'Naturschutzgebiete',
                    'regionField' => 'BL',
                    'text'       => 'Naturschutzgebiet',
                    'short'      => 'NSG',
                    'wide'      => true,
                    'color'      => '#ffd54a',
                    'nameField' => 'NAME',
                ],
                [
                    'type'        => 'bfn_sch_Schutzgebiet:Fauna_Flora_Habitat_Gebiete',
                    'currencyLabel' => 'FFH-Gebiete',
                    'regionField' => 'BL',
                    'text'       => 'FFH-Gebiet',
                    'short'      => 'FFH',
                    'wide'      => true,
                    'color'      => '#4dc3ff',
                    'nameField' => 'NAME',
                ],
            ],
        ],

        /* THE RAIN OF THE WEEK BEFORE LAST AND THE ONE BEFORE
           (01.10.2026: „nicht der aktuelle Regen, nicht der Regen eine Woche
           zurück … der Regen, der vorletzte und vorvorletzte Woche gefallen
           ist"). One picture of Germany a day, the fourteen days ending a
           week ago summed from the DWD's radar — how and why in
           includes/rain.php. Its box, steps and colours live there; the
           legend is built from them below (map_layers_for_js), so picture
           and key cannot drift. No tiles: one picture, which the service
           worker keeps once seen — nothing for the area download. */
        'rain' => [
            // IN DAYS, NOT WEEKS (01.10.2026, after „1–3" and
            // „2–3 Wochen"): „vor zwei Wochen" reads as fourteen days ago to
            // some and as the week before last to others, and each name was
            // wrong by a week for one of them. The days say it either way.
            'name'        => 'Regen vor 8–21 Tagen',
            'group'       => 'overlay',
            'type'        => 'image',
            'url'         => 'pages/rain.php?what=png',
            'meta'        => 'pages/rain.php?what=json',
            'box'         => [RAIN_BOX['west'], RAIN_BOX['south'], RAIN_BOX['east'], RAIN_BOX['north']],
            'opacity'     => 0.7,
            'attribution' => 'Niederschlag: Deutscher Wetterdienst (RADOLAN), CC BY 4.0',
            'hint'        => 'Wie viel Regen in den zwei Wochen fiel, die eine Woche zurückliegen. Pilze brauchen etwa eine Woche zum Wachsen.',
            'noPrefetch'  => 'Ein einziges Bild — es bleibt nach dem Ansehen auf dem Gerät.',
            'rainLegend'  => true,
        ],

        /* THE RECORDED TRACKS (11.09.2026, step two of the maintainer's „Wegstrecken
           pauschal an- oder ausschalten"). No address, no tiles, nothing
           foreign: what this switch shows lies on the device already —
           trackstore.js fetched it in month buckets — and map.js draws it
           on a canvas of its own. The entry exists so the switch sits where
           every other overlay's does, with the same memory and the same
           hint. index.php drops it while the server has no map_tracks.
           LAST IN THE PICKER (01.10.2026: „bei den Zusatzlayern
           die Wegstrecken als letztes"): the picker lists in this order. */
        'tracks' => [
            'name'  => 'Wegstrecken',
            'group' => 'overlay',
            'type'  => 'tracks',
            'hint'  => 'Was die Aufzeichnungs-App mitgeschrieben hat. Liegt auf dem Gerät und zeichnet auch ohne Netz.',
        ],
    ];
}

/**
 * The layers prepared the way the map script expects them.
 *
 * Layers with 'kinds' get their legend derived here instead of maintaining
 * it by hand a second time — otherwise color and labeling drift apart
 * sooner or later.
 *
 * 'bounds' gets the version tag appended here. At 111 kB the file is the
 * largest the map loads; without a tag the browser would have to ask back
 * on every visit whether it is still current. The tag is the modification
 * time of the file itself, not of the scripts: a corrected state border
 * should arrive even when nothing about the program has changed.
 */
function map_layers_for_js(): array
{
    $layers = [];
    foreach (map_layers() as $id => $layer) {
        if (isset($layer['bounds'])) {
            $time = @filemtime(PROJECT_PATH . '/' . $layer['bounds']);
            if ($time !== false) {
                $layer['bounds'] .= '?v=' . $time;
            }
        }
        if (!empty($layer['rainLegend'])) {
            // The rain picture's steps, from the code that paints them (includes/rain.php).
            $layer['legend'] = [];
            foreach (RAIN_COLOURS as $i => $colour) {
                $low = RAIN_STEPS[$i - 1] ?? null;
                $high = RAIN_STEPS[$i] ?? null;
                $layer['legend'][] = ['color' => $colour, 'text' => $low === null
                    ? t('unter %d mm', $high)
                    : ($high === null ? t('über %d mm', $low) : t('%d–%d mm', $low, $high))];
            }
            unset($layer['rainLegend']);
        }
        if (isset($layer['kinds']) && !isset($layer['legend'])) {
            $layer['legend'] = array_map(
                static fn(array $kind): array => array_filter([
                    'color' => $kind['color'],
                    'text'  => $kind['text'],
                    'wide' => $kind['wide'] ?? null,
                    // The kind's short name, so the legend teaches the one
                    // the point window's facts line shows (01.10.2026).
                    'short' => $kind['short'] ?? null,
                ], static fn($w) => $w !== null),
                $layer['kinds']
            );
        }
        $layers[] = ['id' => $id] + $layer;
    }
    return $layers;
}
