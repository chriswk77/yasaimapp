<?php
/**
 * Municipality, subdivision and country for a coordinate.
 *
 * WHAT FOR. A Messtischblatt means something to the mapping office and
 * nothing to a person: "5817" is not a memory. "DE/BAY/Mittenwald" is one.
 * These three details are at the same time the filing scheme of the voucher
 * photos on the hard drive — country, subdivision, next-larger town —, and
 * thus the bridge between what is stored here and what lies there.
 *
 * ADMINISTRATIVE MUNICIPALITY, NOT NEAREST TOWN. Those are two different
 * questions. "Which municipality does the point lie in" is unambiguous,
 * repeatable and yields the same answer in ten years; "which town is
 * nearest" hangs on an arbitrary minimum size and jumps at every border.
 * Across the whole Alpine region the municipality is named after its main
 * town anyway. A remainder stays: an Alpine municipality reaches far into
 * the mountains, and then the name of a town stands there that lies ten
 * kilometers away in the valley. That is the price for an answer that does
 * not change.
 *
 * WHERE FROM. Nominatim, the place service of OpenStreetMap — the same one
 * the forward place search uses. It delivers municipality name, ISO code of
 * the subdivision and country code in one go, the same for all three
 * countries and for the world beyond.
 *
 * WHAT THIS COSTS, AND NOT IN MONEY. The request transmits the exact
 * coordinate of a saved place to a third party — the same trade-off as with
 * the terrain elevation, and the same answer: the request goes out from the
 * server, not from the browser, so that the service sees the address of
 * this server and not that of the phone currently standing in the woods.
 *
 * NOMINATIM IS DONATED, NOT BOUGHT. The usage rules allow one request per
 * second and explicitly ask not to run bulk queries. A new point is one
 * request and thus exactly the case the service exists for. Backfilling
 * eight thousand existing points is NOT — that is what the own boundary
 * table is meant for, and until it exists, the backfill trickles in chunks
 * with pauses.
 */

declare(strict_types=1);

const MUNICIPALITY_URL       = 'https://nominatim.openstreetmap.org/reverse';
const MUNICIPALITY_TIMEOUT = 8;

/**
 * Identifier for the service.
 *
 * Nominatim rejects requests without a meaningful identifier — a program
 * that does not identify itself cannot be contacted in case of abuse either.
 * That is why the address of this installation stands here and not a name
 * that exists a hundred times over.
 */
function municipality_key(): string
{
    $origin = BASE_URL;
    if (!str_starts_with($origin, 'http')) {
        $host   = $_SERVER['HTTP_HOST'] ?? 'unbekannt';
        $origin = 'https://' . $host . BASE_URL;
    }
    return 'Karte/1.0 (+' . $origin . ')';
}

/**
 * Subdivision codes, three letters.
 *
 * WHY AN OWN TABLE AND NOT THE ISO CODE. For Germany and Switzerland
 * ISO 3166-2 assigns two letters (DE-BY, CH-VS); for Austria, however,
 * DIGITS (AT-1 through AT-9). A scheme that wrote "AT/7/Innsbruck" would be
 * unreadable on the hard drive. So either two letters everywhere with
 * invented ones for Austria — or three everywhere and all three countries
 * treated alike. This here is the second choice.
 *
 * THE GERMAN CODES avoid the confusion pairs that naive truncation would
 * produce: Berlin would be BER and Bremen BRE, and nobody tells those two
 * apart on a folder listing anymore. Hence BLN and BRM — both in common
 * use, both distinguishable at a glance. The same for the three S-A states:
 * SAL Saarland, SAC Sachsen, SAH Sachsen-Anhalt.
 *
 * THE SWISS CODES follow the local name and thus the ISO code, which stems
 * from the same: VD Vaud → VAU, TI Ticino → TIC. A German "WAA" for the
 * Waadt would stand on no signpost and no license plate. Basel-Stadt and
 * Basel-Landschaft are called BST and BSL, mirroring BS and BL; a "BAS"
 * for the one and "BSL" for the other would again be a confusion pair.
 *
 * THE AUSTRIAN ONES are simply the abbreviations customary in the country,
 * shortened to three characters (Bgld., Ktn., NÖ, OÖ, Sbg., Stmk., …),
 * umlauts resolved.
 */
const MUNICIPALITY_PARTS = [
    'DE' => [
        'BW' => 'BAW', 'BY' => 'BAY', 'BE' => 'BLN', 'BB' => 'BRB',
        'HB' => 'BRM', 'HH' => 'HAM', 'HE' => 'HES', 'MV' => 'MVP',
        'NI' => 'NDS', 'NW' => 'NRW', 'RP' => 'RLP', 'SL' => 'SAL',
        'SN' => 'SAC', 'ST' => 'SAH', 'SH' => 'SLH', 'TH' => 'THU',
    ],
    'AT' => [
        '1' => 'BGL', '2' => 'KTN', '3' => 'NOE', '4' => 'OOE', '5' => 'SBG',
        '6' => 'STM', '7' => 'TIR', '8' => 'VBG', '9' => 'WIE',
    ],
    'CH' => [
        'AG' => 'AAR', 'AI' => 'API', 'AR' => 'APA', 'BE' => 'BER',
        'BL' => 'BSL', 'BS' => 'BST', 'FR' => 'FRI', 'GE' => 'GEN',
        'GL' => 'GLA', 'GR' => 'GRA', 'JU' => 'JUR', 'LU' => 'LUZ',
        'NE' => 'NEU', 'NW' => 'NIW', 'OW' => 'OBW', 'SG' => 'SGA',
        'SH' => 'SCH', 'SO' => 'SOL', 'SZ' => 'SWZ', 'TG' => 'THG',
        'TI' => 'TIC', 'UR' => 'URI', 'VD' => 'VAU', 'VS' => 'VAL',
        'ZG' => 'ZUG', 'ZH' => 'ZUR',
    ],
    // ITALY, because mapping happens there regularly. Again digits as in
    // Austria, again a table of its own. The codes are the ones customary
    // in the country (FVG, EMR, TAA, VDA appear like that on signs and in
    // statistics), the rest the obvious shortening of the Italian name.
    'IT' => [
        '21' => 'PIE', '23' => 'VDA', '25' => 'LOM', '32' => 'TAA',
        '34' => 'VEN', '36' => 'FVG', '42' => 'LIG', '45' => 'EMR',
        '52' => 'TOS', '55' => 'UMB', '57' => 'MAR', '62' => 'LAZ',
        '65' => 'ABR', '67' => 'MOL', '72' => 'CAM', '75' => 'PUG',
        '77' => 'BAS', '78' => 'CAL', '82' => 'SIC', '88' => 'SAR',
    ],

    // ---- The other travel countries -----------------------------------
    //
    // FRANCE, BELGIUM AND GREAT BRITAIN ARE NOT LISTED HERE, and that is no
    // oversight: their ISO codes are already three letters (FR-ARA,
    // BE-WAL, GB-ENG). The fallback below leaves them unchanged, and a
    // table would be a second rendition of the same information — that is,
    // a place where something can drift apart without doing any work.
    //
    // The codes follow the local name everywhere, as with the first three
    // countries. Diacritics are resolved (Śląskie → SLA), because a folder
    // name should be typeable.

    // Spain, 19 autonomous communities.
    'ES' => [
        'AN' => 'AND', 'AR' => 'ARA', 'AS' => 'AST', 'CB' => 'CTB',
        'CE' => 'CEU', 'CL' => 'CYL', 'CM' => 'CLM', 'CN' => 'CAN',
        'CT' => 'CAT', 'EX' => 'EXT', 'GA' => 'GAL', 'IB' => 'BAL',
        'MC' => 'MUR', 'MD' => 'MAD', 'ML' => 'MEL', 'NC' => 'NAV',
        'PV' => 'PVA', 'RI' => 'RIO', 'VC' => 'VLC',
    ],
    // Netherlands, 12 provinces.
    'NL' => [
        'DR' => 'DRE', 'FL' => 'FLE', 'FR' => 'FRI', 'GE' => 'GEL',
        'GR' => 'GRO', 'LI' => 'LIM', 'NB' => 'NBR', 'NH' => 'NHO',
        'OV' => 'OVE', 'UT' => 'UTR', 'ZE' => 'ZEE', 'ZH' => 'ZHO',
    ],
    // Poland, 16 voivodeships.
    'PL' => [
        '02' => 'DOL', '04' => 'KUJ', '06' => 'LBL', '08' => 'LBU',
        '10' => 'LOD', '12' => 'MAL', '14' => 'MAZ', '16' => 'OPO',
        '18' => 'PDK', '20' => 'PDL', '22' => 'POM', '24' => 'SLA',
        '26' => 'SWI', '28' => 'WAR', '30' => 'WLP', '32' => 'ZAC',
    ],
    // Czechia, 14 kraje.
    'CZ' => [
        '10' => 'PRA', '20' => 'STC', '31' => 'JHC', '32' => 'PLZ',
        '41' => 'KAR', '42' => 'UST', '51' => 'LIB', '52' => 'HRA',
        '53' => 'PAR', '63' => 'VYS', '64' => 'JHM', '71' => 'OLO',
        '72' => 'ZLI', '80' => 'MSK',
    ],
    // Slovakia, 8 kraje.
    'SK' => [
        'BC' => 'BAN', 'BL' => 'BRA', 'KI' => 'KOS', 'NI' => 'NIT',
        'PV' => 'PRE', 'TA' => 'TRA', 'TC' => 'TRE', 'ZI' => 'ZIL',
    ],
    // Croatia, 20 counties and the city of Zagreb.
    'HR' => [
        '01' => 'ZAG', '02' => 'KRZ', '03' => 'SIS', '04' => 'KAR',
        '05' => 'VAR', '06' => 'KOP', '07' => 'BJE', '08' => 'PRI',
        '09' => 'LIK', '10' => 'VIR', '11' => 'POZ', '12' => 'BRO',
        '13' => 'ZAD', '14' => 'OSI', '15' => 'SIB', '16' => 'VUK',
        '17' => 'SPL', '18' => 'IST', '19' => 'DUB', '20' => 'MED',
        '21' => 'ZGB',
    ],
    // Denmark, 5 regions.
    'DK' => [
        '81' => 'NJY', '82' => 'MJY', '83' => 'SYD', '84' => 'HOV',
        '85' => 'SJA',
    ],
    // Sweden, 21 län. The ISO codes here are one to two characters long.
    'SE' => [
        'AB' => 'STO', 'AC' => 'VBT', 'BD' => 'NBT', 'C'  => 'UPP',
        'D'  => 'SOD', 'E'  => 'OGL', 'F'  => 'JON', 'G'  => 'KRO',
        'H'  => 'KAL', 'I'  => 'GOT', 'K'  => 'BLE', 'M'  => 'SKA',
        'N'  => 'HAL', 'O'  => 'VGL', 'S'  => 'VRM', 'T'  => 'ORE',
        'U'  => 'VML', 'W'  => 'DAL', 'X'  => 'GAV', 'Y'  => 'VNL',
        'Z'  => 'JAM',
    ],
    // Norway, 15 fylker — as of the re-division of 2024, in which Viken
    // fell apart again into Østfold, Akershus and Buskerud.
    'NO' => [
        '03' => 'OSL', '11' => 'ROG', '15' => 'MRO', '18' => 'NOR',
        '31' => 'OST', '32' => 'AKE', '33' => 'BUS', '34' => 'INN',
        '39' => 'VES', '40' => 'TEL', '42' => 'AGD', '46' => 'VLD',
        '50' => 'TRO', '55' => 'TRM', '56' => 'FIN',
    ],
];

/**
 * Where the region level is too coarse.
 *
 * TRENTINO-SOUTH TYROL IS NOT AN EXCEPTION BUT THE RULE APPLIED CORRECTLY.
 * The level this scheme means is the self-governing one: federal state,
 * canton, region. In Italy that is almost everywhere the region — just not
 * there. Bolzano and Trento are AUTONOMOUS PROVINCES with their own
 * legislation, and the region above them is largely a shell. Filing both
 * under "TAA" would mean throwing South Tyrolean finds into one folder with
 * the Trentino — and that is precisely the border most likely meant in the
 * German-speaking world.
 *
 * Nominatim delivers ISO3166-2-lvl6 for this right along (measured: IT-BZ
 * for Bolzano, IT-TN for Trento). So it costs no second request.
 *
 * The key is the coarse code, the value the finer assignment. If nothing is
 * found there, the coarse one stays — a point in a province not listed here
 * lands in its region and not in nothingness.
 */
const MUNICIPALITY_PARTS_FINE = [
    'IT-32' => ['IT-BZ' => 'SDT', 'IT-TN' => 'TRN'],
];

/**
 * The spot has no municipality — open sea, Antarctica, no-man's-land.
 *
 * A class of its own for the same reason as with the terrain elevation:
 * "there is nothing there" is final and needs no second attempt, "the
 * service does not answer" does.
 */
class NoMunicipality extends RuntimeException
{
}

/**
 * Bring the ISO code of a subdivision down to three letters.
 *
 * $iso is the coarse level as Nominatim delivers it: "DE-BY", "AT-7",
 * "CH-VS", "IT-32". $fine the finer one, where it exists: "IT-BZ".
 *
 * THE FINE LEVEL BEATS THE COARSE ONE, but only where MUNICIPALITY_PARTS_FINE
 * says so explicitly. Otherwise the mere presence of a lvl6 code would
 * decide at which level a country is filed — and that depends on how
 * completely OpenStreetMap is maintained in that area. The scheme would
 * then change its meaning from place to place without anyone seeing it.
 *
 * THREE LETTERS ALSO OCCUR WITHOUT A TABLE. France, Belgium and Great
 * Britain already assign their ISO codes that way (FR-ARA, BE-WAL, GB-ENG);
 * there the fallback below kicks in and leaves them unchanged. For all
 * remaining countries the part after the hyphen likewise stays —
 * "PT/11/Porto" is not pretty, but true, and better than an invented
 * abbreviation or an empty level that breaks the scheme. Whoever starts
 * mapping regularly in a country adds it above.
 */
function municipality_subdivision(?string $iso, ?string $fine = null): ?string
{
    if ($iso === null || !str_contains($iso, '-')) {
        return null;
    }

    $iso = strtoupper($iso);
    if ($fine !== null && isset(MUNICIPALITY_PARTS_FINE[$iso][strtoupper($fine)])) {
        return MUNICIPALITY_PARTS_FINE[$iso][strtoupper($fine)];
    }

    [$country, $rest] = explode('-', $iso, 2);

    return MUNICIPALITY_PARTS[$country][$rest] ?? ($rest === '' ? null : $rest);
}

/**
 * Pull the municipality name out of the answer.
 *
 * THE FIELD CHANGES WITH THE SIZE OF THE PLACE. Measured on real points:
 * Mittenwald comes as 'town', Krün three kilometers away as 'village',
 * Innsbruck and Münster as 'city'. So a chain is mandatory, a single field
 * is never enough.
 *
 * 'municipality' DOES NOT BELONG IN THE CHAIN, however tempting the name
 * is: for a point in Ticino it holds "Kreis Bellinzona" — a district, not
 * the municipality. Whoever takes that field along ends up one level too
 * high in half of Switzerland and does not notice, because it looks
 * plausible.
 *
 * THE TOPMOST NAME FIRST. At zoom=10 Nominatim answers with exactly the
 * administrative body we want and puts its name on top. The address chain
 * below is the fallback for the case that the answer is built differently
 * some day.
 */
function municipality_name(array $response): ?string
{
    $ranking = ['city', 'town', 'village', 'hamlet', 'borough', 'suburb'];

    $type = (string) ($response['addresstype'] ?? '');
    $topName = trim((string) ($response['name'] ?? ''));
    if ($topName !== '' && in_array($type, $ranking, true)) {
        return $topName;
    }

    $address = $response['address'] ?? [];
    foreach ($ranking as $field) {
        $value = trim((string) ($address[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return $topName !== '' ? $topName : null;
}

/**
 * Municipality for a coordinate.
 *
 * @return array{country:string, region:?string, municipality:string}
 * @throws NoMunicipality    if no municipality lies there
 * @throws RuntimeException if the service does not answer
 */
function municipality_get(float $lat, float $lon): array
{
    // zoom=10 is the level of the municipality. Without it Nominatim
    // answers at house-number level and includes streets and field names
    // that we neither need nor want to know.
    //
    // accept-language=de fetches the German name where OSM has one. That is
    // not mere convenience: "Biel/Bienne" is named just so, with a slash,
    // and a slash in a folder name is no longer a municipality but one more
    // level. In German, simply "Biel" comes back.
    $url = MUNICIPALITY_URL . '?' . http_build_query([
        'format'          => 'jsonv2',
        'zoom'            => 10,
        'accept-language' => 'de',
        'lat'             => $lat,
        'lon'             => $lon,
    ]);

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "User-Agent: " . municipality_key() . "\r\nAccept: application/json\r\n",
            'timeout'       => MUNICIPALITY_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException(t('Der Ortsdienst antwortet nicht.'));
    }

    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException(t('Der Ortsdienst hat unverständlich geantwortet.'));
    }

    // Nominatim reports "nothing found" with an error field and status
    // 200 — not with an HTTP error. Open sea looks like this.
    if (isset($d['error'])) {
        throw new NoMunicipality(t('Dort liegt keine Gemeinde.'));
    }

    $name = municipality_name($d);
    if ($name === null) {
        throw new NoMunicipality(t('Dort liegt keine Gemeinde.'));
    }

    $country = strtoupper((string) ($d['address']['country_code'] ?? ''));
    if (strlen($country) !== 2) {
        throw new NoMunicipality(t('Ohne Staat ist der Rest wertlos.'));
    }

    return [
        'country'      => $country,
        'region' => municipality_subdivision(
            $d['address']['ISO3166-2-lvl4'] ?? null,
            $d['address']['ISO3166-2-lvl6'] ?? null),
        'municipality'   => $name,
    ];
}

/**
 * A name component as it may lie on a hard drive as a folder.
 *
 * SLASH AND COLON are forbidden or ambiguous on the three common file
 * systems, and both occur in real municipality names. Umlauts stay: they
 * have been allowed everywhere for twenty years, and mangling "Sankt
 * Wolfgang" into "Sankt Wolfgang" would destroy the name the scheme is
 * supposed to make readable.
 *
 * A DOT AT THE START makes the folder invisible under Unix, a dot at the
 * end confuses Windows. Neither occurs in municipality names, but catching
 * them costs nothing.
 */
function municipality_folder_part(string $name): string
{
    $name = str_replace(['/', '\\', ':'], '-', $name);
    $name = preg_replace('/[\x00-\x1F<>:"|?*]+/u', '', $name) ?? $name;
    return trim($name, " \t.");
}

/**
 * The filing scheme as one piece of text: "DE/BAY/Mittenwald".
 *
 * If the subdivision is missing — outside the countries for which there is
 * a table —, the level drops out instead of staying empty. "IT//Bozen"
 * would be a path with a hole in it.
 */
function municipality_folder(?string $country, ?string $subdivision, ?string $municipality): ?string
{
    if ($municipality === null || $municipality === '' || $country === null || $country === '') {
        return null;
    }
    $parts = array_filter([$country, $subdivision, municipality_folder_part($municipality)],
        static fn($t) => $t !== null && $t !== '');

    return implode('/', $parts);
}

/**
 * Every code that can come out for a country — coarse AND fine level.
 *
 * ITS OWN FUNCTION, BECAUSE A BUG ALREADY HID EXACTLY HERE. Whoever reads
 * only MUNICIPALITY_PARTS overlooks "TRN": it stands solely in
 * MUNICIPALITY_PARTS_FINE. A Trentino point thus counted as stale forever, was
 * reset, queried anew, came back as TRN again — and that on every run, with
 * one outbound request per round. Noticed on real data, not while reading.
 *
 * That is why the calculation stands in one place instead of in each that
 * needs it.
 *
 * @return string[]
 */
function municipality_codes(string $country): array
{
    $country   = strtoupper($country);
    $codes = array_values(MUNICIPALITY_PARTS[$country] ?? []);

    foreach (MUNICIPALITY_PARTS_FINE as $coarse => $fine) {
        if (str_starts_with($coarse, $country . '-')) {
            $codes = array_merge($codes, array_values($fine));
        }
    }

    return array_values(array_unique($codes));
}

/**
 * Points whose subdivision dates from a time before the code table.
 *
 * WHAT FOR. A country without a table keeps the bare ISO remainder —
 * "IT/82/Messina". If the table is added later, the old points still stand
 * there with the digit while new ones get "SIC". Two spellings for the same
 * Sicily, and the folders on the hard drive drift apart.
 *
 * WHY NO MIGRATION WITH UPDATE ROWS. It would have to contain every code of
 * every country once more — 131 rows today, and with the next table another
 * new migration. The mapping would then stand in two places, and the second
 * one goes stale silently.
 *
 * Instead: whatever code does not occur in the table of its country is
 * simply marked as open again and asked anew at the next backfill. That
 * heals itself with every future table — and it is also the only way for
 * Trentino-South Tyrol: from a stored "32" it can no longer be read whether
 * Bolzano or Trento was meant.
 *
 * COUNTRIES WITHOUT A TABLE ARE LEFT ALONE. France's "ARA" is not a
 * leftover but the correct answer; collecting it here would mean asking the
 * same points again on every run.
 *
 * @param bool $reset false only counts, true resets
 */
function municipality_outdated(PDO $pdo, bool $reset = false): int
{
    $sum = 0;

    foreach (array_keys(MUNICIPALITY_PARTS) as $country) {
        $codes = municipality_codes($country);
        $space   = implode(',', array_fill(0, count($codes), '?'));

        $where = 'country = ? AND region IS NOT NULL AND region NOT IN (' . $space . ')';
        $values = array_merge([$country], $codes);

        if ($reset) {
            $stmt = $pdo->prepare('UPDATE map_points SET municipality_source = NULL WHERE ' . $where);
            $stmt->execute($values);
            $sum += $stmt->rowCount();
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_points WHERE ' . $where);
            $stmt->execute($values);
            $sum += (int) $stmt->fetchColumn();
        }
    }

    return $sum;
}

/**
 * Fetch the municipality and record it on the point.
 *
 * The one place where this happens — creating, moving and backfilling all
 * pass through here.
 *
 * 'none' IS RECORDED and not treated as a failure. Otherwise the backfill
 * would ask again on every run about the same point in the middle of the
 * Atlantic.
 *
 * @return array|null the details found, or null on failure
 * @throws NoMunicipality|RuntimeException if $silent is false
 */
function municipality_record(PDO $pdo, int $userId, int $pointId, bool $silent = false): ?array
{
    $stmt = $pdo->prepare('SELECT lat, lon FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $p = $stmt->fetch();
    if ($p === false) {
        return null;
    }

    try {
        $result = municipality_get((float) $p['lat'], (float) $p['lon']);
    } catch (NoMunicipality $e) {
        $pdo->prepare(
            'UPDATE map_points
                SET municipality = NULL, region = NULL, country = NULL, municipality_source = ?
              WHERE id = ? AND user_id = ?'
        )->execute(['none', $pointId, $userId]);

        if ($silent) return null;
        throw $e;
    } catch (RuntimeException $e) {
        if ($silent) return null;
        throw $e;
    }

    // updated_at stays untouched. It is the user's timestamp, and the
    // queue on the phone uses it to decide whose version is the younger
    // one. A derivation the server backfills on the side is not a change by
    // the user and must not take any precedence away from them.
    $pdo->prepare(
        'UPDATE map_points
            SET municipality = ?, region = ?, country = ?, municipality_source = ?
          WHERE id = ? AND user_id = ?'
    )->execute([
        $result['municipality'], $result['region'], $result['country'], 'osm',
        $pointId, $userId,
    ]);
    // The place stands in the names of the find's pictures (cloud_names.php).
    require_once __DIR__ . '/cloud_names.php';
    cloud_names_touch($pdo, $pointId);

    /* AND HERE THE SHEET NUMBER FALLS, if the point turns out not to lie
       in Germany (25.08.2026: „auch für österreichische Funde
       wird das MTB angegeben, das gilt da allerdings gar nicht mehr").
       He is right — the Messtischblatt is a German grid, and a number on
       an Austrian find is not a hint, it is a false statement that
       travels into every export.

       THIS IS THE MOMENT IT BECOMES KNOWABLE. When a point is created,
       nobody knows its country: only the coordinates are there, and the
       bounding rectangle in mtb.php reaches deep into Austria. The
       reverse geocoding right here is what settles it, and so the
       correction belongs here and not in a tool somebody has to run.

       Only on a country that really came back, and only when it is not
       Germany — an empty answer means unknown, and unknown must not cost
       a right number. */
    if (($result['country'] ?? '') !== ''
        && strtoupper((string) $result['country']) !== 'DE'
        && column_exists($pdo, 'map_points', 'mtb')) {
        $pdo->prepare(
            'UPDATE map_points SET mtb = NULL, mtb_quadrant = NULL
              WHERE id = ? AND user_id = ? AND mtb IS NOT NULL'
        )->execute([$pointId, $userId]);
    }

    return $result;
}
