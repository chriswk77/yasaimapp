<?php
/**
 * A sample of the coordinators' mail — twenty invented finders with
 * invented finds in Hessen, to look at text and layout before a real
 * coordinator gets one (25.09.2026: „damit ich mir das mal
 * anschauen kann, wie der Text aussieht, wie der Aufbau ist — Fake-CSVs,
 * Fake-Nutzer, vielleicht zwanzig").
 *
 * NOTHING HERE READS A FIND OR BOOKS A DELIVERY. The finders live in this
 * file, their addresses at example.org — a domain reserved for examples
 * and nothing else — and every subject says „[Beispiel]". The one real
 * thing is the way out: mykis_coordinator_mail() and mail_send(), the same
 * as for the coordinators, so a sample in one's own inbox also shows
 * whether the noreply sender lands there or in the spam.
 *
 * THE SAME SAMPLE EVERY TIME (a fixed seed): two looks at it differ only
 * where the code changed.
 */

declare(strict_types=1);

require_once __DIR__ . '/mykis.php';
require_once __DIR__ . '/mtb.php';

/** Twenty invented finders — more than one mail's fifteen sheets. */
const MYKIS_SAMPLE_FINDERS = [
    ['Anna', 'Berger'], ['Ben', 'Keller'], ['Clara', 'Wolf'], ['David', 'Schneider'], ['Eva', 'Fischer'],
    ['Felix', 'Weber'], ['Greta', 'Meyer'], ['Hannes', 'Wagner'], ['Ida', 'Becker'], ['Jonas', 'Schulz'],
    ['Karla', 'Hoffmann'], ['Lukas', 'Koch'], ['Mia', 'Richter'], ['Noah', 'Klein'], ['Olga', 'Neumann'],
    ['Paul', 'Schwarz'], ['Rosa', 'Zimmermann'], ['Simon', 'Braun'], ['Tessa', 'Krüger'], ['Uwe', 'Hofmann'],
];

/** Common species, all in the DGfM reference list. */
const MYKIS_SAMPLE_SPECIES = [
    'Amanita muscaria', 'Boletus edulis', 'Cantharellus cibarius', 'Fomes fomentarius', 'Trametes versicolor',
    'Laetiporus sulphureus', 'Hygrocybe coccinea', 'Mycena galericulata', 'Russula ochroleuca',
    'Lactarius deliciosus', 'Xerocomellus chrysenteron', 'Stereum hirsutum', 'Hypholoma fasciculare',
    'Armillaria ostoyae', 'Clitocybe nebularis', 'Fistulina hepatica', 'Ganoderma applanatum',
    'Pleurotus ostreatus', 'Coprinus comatus', 'Macrolepiota procera',
];

/** Towns in Hessen with their centre — the invented finds scatter around them. */
const MYKIS_SAMPLE_TOWNS = [
    ['Wetzlar', 50.5558, 8.5049], ['Gießen', 50.5841, 8.6784], ['Marburg', 50.8021, 8.7667],
    ['Wiesbaden', 50.0782, 8.2398], ['Darmstadt', 49.8728, 8.6512], ['Fulda', 50.5558, 9.6808],
    ['Kassel', 51.3127, 9.4797], ['Limburg an der Lahn', 50.3836, 8.0503], ['Herborn', 50.6825, 8.3061],
    ['Bad Hersfeld', 50.8682, 9.7079],
];

/** The twenty finders' finds in the sheets mykis_deliver() would build. */
function mykis_sample_files(string $day): array
{
    mt_srand(2026);
    $pick = static fn(array $list) => $list[mt_rand(0, count($list) - 1)];
    $entries = [];
    foreach (MYKIS_SAMPLE_FINDERS as [$first, $last]) {
        $local = strtolower(strtr("$first.$last", ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']));
        // Most keep the default quarter, some choose finer — the list in
        // the mail shows every step.
        $precision = $pick(['q4', 'q4', 'q4', 'q16', 'q64', 'exact', 'exact']);
        $finder = ['first_name' => $first, 'last_name' => $last, 'email' => $local . '@example.org',
                   'mykis_precision' => $precision];
        $points = [];
        for ($n = mt_rand(3, 60); $n > 0; $n--) {
            [$town, $lat, $lon] = $pick(MYKIS_SAMPLE_TOWNS);
            $lat += mt_rand(-2000, 2000) / 100000;
            $lon += mt_rand(-3000, 3000) / 100000;
            $mtb = mtb_from_coordinates($lat, $lon, 'DE');
            $points[] = [[
                'id' => 0, 'title' => $pick(MYKIS_SAMPLE_SPECIES),
                'found_at' => sprintf('2026-%02d-%02d 10:00:00', mt_rand(6, 11), mt_rand(1, 28)),
                'lat' => $lat, 'lon' => $lon, 'accuracy_m' => mt_rand(3, 15), 'elevation_m' => mt_rand(120, 620),
                'mtb' => $mtb['sheet'] ?? null,
                'mtb_quadrant' => $mtb === null ? null : $mtb['quadrant'] . $mtb['q16'] . $mtb['q64'],
                'municipality' => $town, 'region' => 'HES', 'country' => 'DE',
                'extra' => (object) array_filter([
                    'Finder' => "$first $last",
                    'Bestimmungssicherheit' => $pick(['makroskopisch sicher', 'mikroskopisch sicher', 'genetisch sicher', '']),
                    'Substrat / Wirt' => $pick(['Fagus sylvatica', 'Quercus robur', 'Laubstreu', 'Picea abies', '']),
                    'Begleitvegetation' => $pick(["Fagus sylvatica\nQuercus petraea", '', '', 'Picea abies']),
                ]),
                'photos' => [], 'recordings' => mt_rand(0, 3) === 0 ? 1 : 0,
            ], 0];
        }
        $entries[] = mykis_finder_entry($finder, $points);
    }
    // Shared sheets as the delivery cuts them (mykis_packages).
    $files = mykis_packages($entries, 'Hessen', $day);
    return $files;
}

/**
 * The sample mails as the coordination for Hessen would get them — the
 * name from the list where migration 0127 is in.
 *
 * @return array[] each ['subject', 'body', 'options'] for mail_send()
 */
function mykis_sample_mails(PDO $pdo): array
{
    $hessen = [['id' => 0, 'name' => 'Harald Zühlsdorf', 'region' => 'HES', 'part' => null]];
    if (mykis_ready($pdo)) {
        $row = $pdo->query("SELECT id, name, region, part FROM map_mykis_coordinators WHERE region = 'HES' LIMIT 1")->fetch();
        if ($row) {
            $hessen = [$row];
        }
    }
    $operator = mykis_operator($pdo);
    $parts = mykis_parts(mykis_sample_files(date('Y-m-d')), MYKIS_MAIL_MAX_BYTES, MYKIS_FILES_PER_MAIL);
    $mails = [];
    foreach ($parts as $i => $part) {
        $mail = mykis_coordinator_mail($hessen, $part, $i + 1, count($parts), $operator);
        $mails[] = [
            'subject' => '[Beispiel] ' . $mail['subject'],
            'body'    => $mail['body'],
            'options' => [
                'reply_to'    => $operator['email'] ?? '',
                'attachments' => array_map(static fn(array $f): array =>
                    ['name' => $f['name'], 'type' => $f['type'], 'content' => $f['content']], $part),
            ],
        ];
    }
    return $mails;
}
