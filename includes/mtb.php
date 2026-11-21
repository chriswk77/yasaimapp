<?php
/**
 * Messtischblatt (TK25) from coordinates.
 *
 * WHAT FOR. German biotope and fungus mapping reckons in Messtischblätter:
 * one sheet of the 1:25,000 topographic map, 10 arc minutes wide and 6
 * tall, around 11 × 11 km. Whoever reports finds states MTB and quadrant
 * — without them a record is not connectable for the mapping scheme.
 *
 * THE CATCH: THE GRID DOES NOT SIT ON WGS84. It is defined on the old
 * Potsdam datum (DHDN, Bessel ellipsoid), and between that and WGS84 lie
 * around 100 meters in Germany. Whoever computes the sheet number
 * straight from the satellite coordinates gets the same result almost
 * everywhere — and the wrong one at every sheet edge. That is the sort of
 * mistake you only notice when the mapping office asks back.
 *
 * Hence first the datum conversion, then the formula.
 *
 * WHERE THE NUMBERS COME FROM. From the Koordinatenermittler of the AHO
 * Baden-Württemberg (orchids.de/geozeugs/koordinatenermittler2/) — the
 * tool actually worked with in the mapping scheme. Its Helmert parameters
 * and its sheet formula are taken over; both are checked against 348
 * points across all of Germany (tests/mtb.php) and against known
 * sheets: Hamburg 2426, München 7835, Dresden 4948, Kiel 1626,
 * Frankfurt a.M. 5817, Köln 5007.
 */

declare(strict_types=1);

/**
 * Bounds within which a sheet is output.
 *
 * A RECTANGLE, NOT THE NATIONAL BORDER. Sheets only exist for Germany;
 * for a find in the Dolomites no number may appear. A rectangle is coarse
 * for that — a point just beyond the border still gets a number that
 * does not exist as a printed sheet. The alternative would be a border
 * polygon, i.e. a few hundred kilobytes of geometry for a piece of
 * information that is only a hint anyway.
 *
 * AND IT IS ONLY THE FIRST GUARD SINCE 25.08.2026. How coarse it really
 * is showed itself on Austrian finds: the whole west of the country lies
 * inside this box and got sheet numbers. The second guard is the country
 * itself — see mtb_from_coordinates. The rectangle answers for the
 * moment a point is born, when no country is known yet; afterwards the
 * country decides.
 */
const MTB_LAT_MIN = 47.20;
const MTB_LAT_MAX = 55.10;
const MTB_LON_MIN = 5.80;
const MTB_LON_MAX = 15.10;

/**
 * WGS84 to Potsdam/Bessel, seven parameters.
 *
 * The values are the ones customary in Germany (EPSG:4314 with
 * towgs84=598.1,73.7,418.2,0.202,0.045,-2.455,6.7). The computation
 * follows the position-vector rule and its INVERSE, because the
 * parameters describe the way from Potsdam to WGS84 — what is needed is
 * the way back.
 *
 * The more accurate method would be the BeTA2007 grid; over these
 * parameters it gains one to three meters. On a grid of eleven
 * kilometers that only decides points that lie on the line anyway — and
 * the tool checked against here computes without the grid itself.
 *
 * @return array{0:float,1:float} longitude and latitude on Potsdam, in degrees
 */
function mtb_to_potsdam(float $lat, float $lon): array
{
    // Step 1: geographic to geocentric, on WGS84.
    $a  = 6378137.0;
    $f  = 1 / 298.257223563;
    $e2 = $f * (2 - $f);

    $b = deg2rad($lat);
    $l = deg2rad($lon);
    $sinB = sin($b);
    $n = $a / sqrt(1 - $e2 * $sinB * $sinB);

    $x = $n * cos($b) * cos($l);
    $y = $n * cos($b) * sin($l);
    $z = $n * (1 - $e2) * $sinB;

    // Step 2: the seven parameters, backwards.
    $dx = 598.1;  $dy = 73.7;   $dz = 418.2;
    $rx = deg2rad(0.202  / 3600);
    $ry = deg2rad(0.045  / 3600);
    $rz = deg2rad(-2.455 / 3600);
    $m  = 1 + 6.7e-6;

    $xt = ($x - $dx) / $m;
    $yt = ($y - $dy) / $m;
    $zt = ($z - $dz) / $m;

    $x2 =  $xt + $rz * $yt - $ry * $zt;
    $y2 = -$rz * $xt + $yt + $rx * $zt;
    $z2 =  $ry * $xt - $rx * $yt + $zt;

    // Step 3: geocentric to geographic, on Bessel.
    $ab  = 6377397.155;
    $fb  = 1 / 299.1528128;
    $eb2 = $fb * (2 - $fb);

    $p = sqrt($x2 * $x2 + $y2 * $y2);
    $bb = atan2($z2, $p * (1 - $eb2));

    // A few passes suffice; after three the latitude is accurate to
    // nanodegrees, and that is a million times finer than a sheet edge.
    for ($i = 0; $i < 5; $i++) {
        $sin = sin($bb);
        $nb  = $ab / sqrt(1 - $eb2 * $sin * $sin);
        $h   = $p / cos($bb) - $nb;
        $bb  = atan2($z2, $p * (1 - $eb2 * $nb / ($nb + $h)));
    }

    return [rad2deg(atan2($y2, $x2)), rad2deg($bb)];
}

/**
 * The Messtischblatt for a point.
 *
 * THE FORMULA, in Potsdam coordinates:
 *
 *   row    = 560 − latitude × 10    (rounded down)
 *   column = longitude × 6 − 34     (rounded down)
 *   sheet  = row × 100 + column, four digits
 *
 * Row 10 thus lies in the far north, row 86 in the Allgäu; column 01 in
 * the west.
 *
 * THE QUADRANTS divide the sheet into four, then into four again, then
 * once more. Counting is 1 = northwest, 2 = northeast, 3 = southwest,
 * 4 = southeast — unlike reading from top left to bottom right, but that
 * is how it stands in every mapping manual.
 *
 * THE COUNTRY BEATS THE RECTANGLE (25.08.2026: „auch für
 * österreichische Funde wird das MTB angegeben, das gilt da allerdings
 * gar nicht mehr"). The rectangle above reaches from 47.20 to 55.10
 * north and 5.80 to 15.10 east — and the whole west of Austria lies
 * inside it, Vorarlberg, Tyrol, Salzburg. They got a sheet number that
 * has never been printed. The rectangle stays as the answer for the
 * moment a point is created, when nobody knows the country yet; as soon
 * as it IS known it decides, and it decides better.
 *
 * An EMPTY country means unknown, not foreign — the reverse geocoding
 * may not have run yet or may have found nothing, and dropping the sheet
 * for that would take a right answer away.
 *
 * @param string|null $country  ISO code as municipality.php stores it
 *                              ('DE', 'AT', 'CH'), null when unknown
 * @return array{sheet:string, quadrant:string, q16:string, q64:string,
 *               compass:string, full:string}|null
 *         null if the point lies outside the range
 */
function mtb_from_coordinates(float $lat, float $lon, ?string $country = null): ?array
{
    if ($country !== null && $country !== '' && strtoupper(trim($country)) !== 'DE') {
        return null;
    }

    if ($lat < MTB_LAT_MIN || $lat > MTB_LAT_MAX
        || $lon < MTB_LON_MIN || $lon > MTB_LON_MAX) {
        return null;
    }

    [$lp, $bp] = mtb_to_potsdam($lat, $lon);

    $row  = (int) floor(560 - $bp * 10.0);
    $column = (int) floor($lp * 6.0 - 34);
    if ($row < 1 || $row > 99 || $column < 1 || $column > 99) {
        return null;
    }
    $sheet = str_pad((string) ($row * 100 + $column), 4, '0', STR_PAD_LEFT);

    /** One quarter: 1 = NW, 2 = NO, 3 = SW, 4 = SO. */
    $quarter = static function (float $lp, float $bp, float $divisorL, float $divisorB): string {
        $west = ((int) floor($lp * $divisorL)) % 2;      // 0 = west,  1 = east
        $north = ((int) floor($bp * $divisorB)) % 2;      // 0 = south, 1 = north
        return (string) ($west + (1 - $north) * 2 + 1);
    };

    $q  = $quarter($lp, $bp, 12.0, 20.0);
    $q16 = $quarter($lp, $bp, 24.0, 40.0);
    $q64 = $quarter($lp, $bp, 48.0, 80.0);

    $north = ((int) floor($bp * 20.0)) % 2;
    $west = ((int) floor($lp * 12.0)) % 2;

    return [
        'sheet'    => $sheet,
        'quadrant' => $q,
        'q16'      => $q16,
        'q64'      => $q64,
        // The VALUE stays German deliberately: NO/SO are the compass letters
        // the German mapping scheme writes (O = Ost), and whoever sees them
        // reads German. Only the key is code.
        'compass'  => ($north ? 'N' : 'S') . ($west ? 'O' : 'W'),
        // The notation customary in the mapping scheme: 5817/234
        'full'     => $sheet . '/' . $q . $q16 . $q64,
    ];
}

/** Just the short form "5817/2" — sheet and quadrant, usually all it takes. */
function mtb_short(?array $mtb): string
{
    return $mtb === null ? '' : $mtb['sheet'] . '/' . $mtb['quadrant'];
}

/**
 * The long form, read off a STORED point: "6022/222".
 *
 * THE SAME NOTATION AS 'full' ABOVE, only from the other direction: there
 * out of the freshly computed quarters, here out of the two columns
 * map_points.mtb and map_points.mtb_quadrant, where those quarters sit as
 * one run of three digits — quadrant, sixteenth, sixty-fourth.
 *
 * ALL THREE GO INTO THE FILE, AND THAT IS THE POINT (27.08.2026:
 * „Ich möchte mit 16 und 64"). The sixteenth and the sixty-fourth are what
 * makes a find locatable to about 1.4 km instead of 5.5; a report that
 * names only the quarter throws that away, and no reader can get it back
 * from the file. A first version cut the reference down to "6022/2" — that
 * was the wrong way round, and it is here as a warning: the quadrant column
 * carries three digits BECAUSE all three are wanted.
 *
 * WRITTEN OUT ONCE, FOR EVERY WRITER. The composition stood inline in the
 * CSV column and again in the find report's — two copies of one notation,
 * exactly the pair that drifts apart on the first change.
 */
function mtb_full(?string $sheet, ?string $quadrant): string
{
    $sheet    = trim((string) $sheet);
    $quadrant = trim((string) $quadrant);
    if ($sheet === '') {
        return '';
    }

    // Without a quadrant the sheet stands alone — a dangling slash would
    // look like a value got lost on the way.
    return $quadrant === '' ? $sheet : $sheet . '/' . $quadrant;
}
