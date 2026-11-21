<?php
/**
 * Tests how a picture archive is read (includes/picture_archives.php).
 *
 *   php tests/picture_archives.php
 *
 * WHY: the reading is all there is between somebody's folders and the
 * species his pictures appear under — and the folders were written by hand
 * and by program, by people who never heard of this app. A rule that
 * misreads one shape puts pictures under the wrong species without a sound,
 * or under none. The maintainer's own shapes (measured in his folder on
 * 01.11.2026) stand beside the ones he expects from others: a German name,
 * the species only in the file name, the day above the species, a camera's
 * file names.
 *
 * No database, no pCloud, no link: the lexicon is written out by hand and
 * the tree is built as showpublink delivers it.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('PROJECT_PATH', dirname(__DIR__));
function t(string $s, ...$a): string { return $a ? vsprintf($s, $a) : $s; }
function table_exists(PDO $pdo, string $t): bool { return false; }
require_once __DIR__ . '/../includes/picture_archives.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nPicture archives — reading somebody's folders\n\n";

$lexicon = [
    'species' => array_change_key_case(array_combine($s = [
        'Clitocybe odora', 'Cortinarius armillatus', 'Evernia prunastri', 'Cystodermella cinnabarina',
        'Exidia nigricans', 'Mycena scirpicola', 'Mycena galopus', 'Mycena galopus var. candida',
        'Disciotis venosa', 'Pluteus leoninus', 'Boletus edulis', 'Russula vesca',
    ], $s)),
    'genera' => array_fill_keys(['clitocybe', 'cortinarius', 'evernia', 'cystodermella', 'exidia', 'mycena',
        'disciotis', 'pluteus', 'boletus', 'russula', 'phellinus'], true),
    'german' => ['fichten-steinpilz' => 'Boletus edulis', 'gemeiner steinpilz' => 'Boletus edulis'],
];
$names = ['mycena scripicola' => 'Mycena scirpicola', 'haareis' => ''];
$in = static fn(array $above, string $text): ?array => archive_name_in($text, $above, $lexicon, $names);

// --- what a name says ----------------------------------------------------------

check($in(['Fungi', 'C', 'Cl', 'Clitocybe'], 'C. odora') === ['species', 'Clitocybe odora'], '„C. odora" takes its genus from the folder above');
check($in(['Fungi', 'C', 'Co', 'Cortinarius', 'Telamonia'], 'C. armillatus') === ['species', 'Cortinarius armillatus'], 'a subgenus between genus and species is stepped over');
check($in(['Lichen', 'E', 'Evernia'], 'E. prunastri') === ['species', 'Evernia prunastri'], 'a lichen');
check($in(['Cystodermella'], 'C. cinnabarina syn. Cystoderma terrei') === ['species', 'Cystodermella cinnabarina'], 'a note behind the epithet is no part of the name');
check($in(['Exidia'], 'E. nigricans (plana)') === ['species', 'Exidia nigricans'], 'nor one in brackets');
check($in(['Mycena'], 'M. scripicola') === ['species', 'Mycena scirpicola'], 'a slip takes the owner\'s word for it');
check($in(['Mycena'], 'M. galopus var. candida') === ['species', 'Mycena galopus var. candida'], 'a variety keeps its rank');
check($in(['Mycena'], 'M. foobar') === ['unknown', 'Mycena foobar'], 'an unknown epithet under a genus is a name to ask about');
check($in([], 'Clitocybe odora') === ['species', 'Clitocybe odora'], 'the full name, no folder above needed');
check($in([], 'Pluteus leoninus 2019-08-20 DE,HE, Wiesbaden 8 Spore') === ['species', 'Pluteus leoninus'], 'a file name that begins with the species');
check($in([], 'Fichten-Steinpilz') === ['species', 'Boletus edulis'], 'a German name');
check($in([], 'Gemeiner Steinpilz 3') === ['species', 'Boletus edulis'], 'a German name before a running number');
check($in(['Russula'], '_ R. spec.') === ['genus', null] && $in(['Russula'], '_R. spec.') === ['genus', null], '„_ R. spec." is genus-only');
check($in(['Phellinus'], '_spec.') === ['genus', null], '„_spec." too');
check($in([], 'Russula cf. vesca') === ['genus', null], '„cf." is no sure species');
check($in([], 'Russula') === ['genus', null], 'a genus alone');
check($in([], 'Wald bei Kirn') === null, 'two words of which the first is no genus are just words');
check($in([], 'Haareis') === ['ignored', null], 'what the owner ticked away');
check($in(['Fungi'], 'Ru') === null && $in([], 'IMG_1234') === null, 'a letter folder or a camera\'s name says nothing');

// --- what a file name says besides --------------------------------------------

$f = archive_file_facts('Pluteus leoninus 2019-08-20 DE,HE, Wiesbaden, Rabengrund, Himmelswiese 8 Spore.jpg');
check($f['date'] === '2019-08-20' && $f['place'] === 'Wiesbaden, Rabengrund, Himmelswiese' && $f['label'] === 'Spore',
    'day, place without country and state, and the word after the number');
$f = archive_file_facts('Sarcoscypha austriaca 2020-03-01 DE,HE, Stockstadt [49.770721, 8.433610] 2.jpg');
check($f['lat'] === 49.770721 && $f['lon'] === 8.43361 && $f['place'] === 'Stockstadt' && $f['label'] === '', 'coordinates in brackets');
$f = archive_file_facts('Lactarius (Picea, Betula 8m) 2019-08-10 DE, BAY, Eberstadt, Stubenweiher 0.jpg');
check($f['place'] === 'Eberstadt, Stubenweiher', '„DE, BAY, " with spaces');
$f = archive_file_facts('IMG_20210717_101500.jpg');
check($f['date'] === '2021-07-17' && $f['place'] === '', 'a camera\'s file gives its day and no place');
$f = archive_file_facts('Steinpilz 17.07.2021 Kirn 2.jpg');
check($f['date'] === '2021-07-17' && $f['place'] === 'Kirn', 'a German day');
check(archive_file_facts('Puccinia dentariae ex Cardamine bulbifera 2.jpg')['date'] === null, 'a file without a day says so');
check(archive_date_in('2021-02-30 Kirn') === null, 'a day that does not exist is none');

// --- the reading of a tree ----------------------------------------------------

$file = static fn(string $id, string $name): array => ['name' => $name, 'isfolder' => false, 'fileid' => $id];
$dir = static fn(string $name, array $contents): array => ['name' => $name, 'isfolder' => true, 'contents' => $contents];
$tree = ['name' => 'Live-Pilze', 'contents' => [
    // The maintainer's shape: genus / „X. epithet" / [year] / day folder / files.
    $dir('Fungi', [$dir('D', [$dir('Disciotis', [$dir('D. venosa', [
        $dir('2018', [$dir('2018-04-12 Kühkopf', [
            $file('11', 'Disciotis venosa 2018-04-12 DE,HE, NSG Kühkopf 2.jpg'),
            $file('10', 'Disciotis venosa 2018-04-12 DE,HE, NSG Kühkopf 10.jpg'),
            $file('12', 'Mycena galopus 2018-04-12 DE,HE, NSG Kühkopf 1.jpg'),
        ])]),
        $dir('2022-03-20 Kühkopf', [$file('13', 'Disciotis venosa 2022-03-20 DE,HE, Kühkopf [50.1, 8.2] 1.jpg')]),
    ])]), $dir('M', [$dir('Mycena', [
        $dir('M. scripicola', [
            $file('20', 'Mycena scirpicola 2020-10-21 DE,HE, Goldsteintal 1.jpg'),
            $file('21', 'Mycena scirpicola 2019-10-01 DE,HE, Platte 1.jpg'),
            $file('22', 'Notiz.txt'),
        ]),
        $dir('M. foobar', [$file('23', 'IMG_0001.jpg'), $file('24', 'IMG_0002.jpg')]),
        $dir('_ M. spec.', [$dir('2021-10-15 Dotzheim', [$file('30', 'Mycena spec. 1.jpg')])]),
    ])])]), $dir('Nebenerscheinungen', [$dir('Haareis', [$file('40', 'Hair ice 1.jpg'), $file('41', 'Exidia nigricans (Haareis) 2.jpg')])])]),
    // Somebody else's shape: a day above the species, German names, species only in the file name.
    $dir('Pilze 2021', [
        $dir('2021-07-17 Kirn', [
            $dir('Fichten-Steinpilz', [$file('50', 'IMG_20210717_101500.jpg'), $file('51', 'IMG_20210717_101600.jpg')]),
            $dir('Clitocybe odora', [$file('52', 'DSC0001.jpg')]),
        ]),
        $dir('Unsortiert', [
            $file('60', 'Pluteus leoninus 2021-08-01 1.jpg'),
            $file('61', 'Pluteus leoninus 2021-08-01 2.jpg'),
            $file('62', 'Wald 2021-08-01.jpg'),
        ]),
    ]),
]];
$r = archive_index_from_tree($tree, $lexicon, $names);
$by = [];
foreach ($r['pictures'] as $p) $by[$p['file_id']] = $p;

check(isset($by['10'], $by['11'], $by['12']) && $by['12']['name'] === 'disciotis venosa',
    'the folder decides, not the file name — a misnamed file stays with its folder\'s species');
check($by['10']['find_key'] === $by['11']['find_key'] && $by['10']['find_date'] === '2018-04-12' && $by['10']['place'] === 'Kühkopf',
    'a year folder between species and find; the day folder is the find, with its place');
check($by['13']['lat'] === 50.1 && $by['10']['lat'] === null, 'a find takes a coordinate one of its pictures carries, and only its own');
check($by['20']['name'] === 'mycena scirpicola' && $by['20']['find_key'] !== $by['21']['find_key'] && $by['21']['find_date'] === '2019-10-01',
    'pictures straight in the species folder are grouped by their own day, under the owner\'s word for the slip');
check(!isset($by['22']), 'only pictures — no notes');
check(!isset($by['30']) && !isset($by['40']), 'nothing from a genus-only folder, nothing ticked away');
check(($by['41']['name'] ?? '') === 'exidia nigricans', 'but a file in a ticked-away folder that names its species stays');
check(isset($by['50'], $by['51']) && $by['50']['name'] === 'boletus edulis' && $by['50']['find_key'] === $by['51']['find_key']
    && $by['50']['find_date'] === '2021-07-17' && $by['50']['place'] === 'Kirn', 'a German folder name under a day folder');
check(isset($by['52']) && $by['52']['find_key'] !== $by['50']['find_key'], 'two species in one day folder are two finds');
check(isset($by['60'], $by['61']) && $by['60']['name'] === 'pluteus leoninus' && $by['60']['find_key'] === $by['61']['find_key'],
    'a folder naming nothing: the file names say the species');
$unresolved = array_column($r['unresolved'], null, 'written');
check(($unresolved['Mycena foobar']['pictures'] ?? 0) === 2 && $unresolved['Mycena foobar']['kind'] === 'name',
    'an unknown name is listed once with its pictures');
check(($unresolved['Unsortiert']['pictures'] ?? 0) === 1 && $unresolved['Unsortiert']['kind'] === 'folder',
    'a folder that names nothing is listed by its name');
check($r['genus_only'] === 1 && $r['unplaced'] === 3 && count($r['pictures']) === 12 && $r['species'] === 6,
    'twelve pictures of six species, three to name, one genus-only');

// --- the candidates the lexicon is asked about --------------------------------

$c = archive_candidates($tree);
check(isset($c['binomials']['disciotis venosa'], $c['binomials']['mycena scripicola'], $c['words']['mycena']),
    'the abbreviated folders are asked about under their genus');
check(!isset($c['binomials']['notiz.txt']) && !isset($c['words']['notiz']), 'files that are no pictures are not asked about');

// --- what leaves the server --------------------------------------------------

// Whether what comes back is a picture: tests/picture_fence.php.

// --- who sees what (migration 0184) -------------------------------------------

check(archive_file_without_gps('Pluteus chrysophaeus 2019-08-24 DE,HE, Wiesbaden, Nerotal [50.10781, 8.21527] 16 spore')
    === 'Pluteus chrysophaeus 2019-08-24 DE,HE, Wiesbaden, Nerotal 16 spore', 'the coordinates leave the file name, the place stays');
$none = ['genera' => [], 'species' => []];
$row = ['user_id' => 1, 'name' => 'inocybe geophylla var. lilacina', 'lat' => 50.1, 'lon' => 8.2, 'file' => 'X 2019-08-24 DE,HE, Y [50.10781, 8.21527] 1'];
$with = [2 => ['spots' => true, 'hidden' => $none], 1 => ['spots' => true, 'hidden' => $none]];
$without = [2 => ['spots' => true, 'hidden' => $none], 1 => ['spots' => false, 'hidden' => $none]];
check(archive_row_shown($row, 2, $with) === $row, 'a friend with the find spots sees all');
$friend = archive_row_shown($row, 2, $without);
check($friend['lat'] === null && $friend['lon'] === null && !str_contains($friend['file'], '50.1'), 'a friend without them sees no coordinates');
check(archive_row_shown($row, 1, [1 => ['spots' => true, 'hidden' => $none]]) === $row, 'its owner sees all, whatever he set for others');
check(archive_row_shown($row, 2, [2 => ['spots' => true, 'hidden' => $none]]) === null, 'an owner who does not let him see: nothing');
$genus = [1 => ['spots' => true, 'hidden' => ['genera' => ['Inocybe'], 'species' => []]]];
check(archive_row_shown($row, 2, $genus) === null, 'a hidden genus hides its species, varieties too');
check(archive_row_shown(['name' => 'inocybella x'] + $row, 2, $genus) !== null, 'but not a genus that merely begins the same');
$species = [1 => ['spots' => true, 'hidden' => ['genera' => [], 'species' => ['Inocybe geophylla']]]];
check(archive_row_shown($row, 2, $species) === null, 'a hidden species hides its varieties');
check(archive_row_shown(['name' => 'inocybe rimosa'] + $row, 2, $species) !== null, 'and nothing else of its genus');
check(archive_hidden_read('{"genera":[" Inocybe ","","Inocybe"],"species":null}') === ['genera' => ['Inocybe'], 'species' => []],
    'a stored filter is read tidy');
check(archive_hidden_read(null) === $none, 'and no filter is an empty one');

// --- working names (migration 0189) -------------------------------------------

$lex = ['species' => [], 'genera' => ['inocybe' => true], 'german' => [],
        'working' => ["inocybe sp. nov. 12" => 'Inocybe sp. nov. 12', "inocybe sp. nov. 1" => 'Inocybe sp. nov. 1']];
check(archive_name_in('Inocybe sp. nov. 1', [], $lex, []) === ['species', 'Inocybe sp. nov. 1'], 'a working name is a species, whatever its words');
check(archive_name_in('Inocybe sp. nov. 12 2021-07-17 Kirn', [], $lex, []) === ['species', 'Inocybe sp. nov. 12'],
    'the longest working name wins, and a day after it does not matter');
check(archive_name_in('Inocybe sp. nov. 3', [], $lex, []) === ['unknown', 'Inocybe sp. nov. 3'],
    'an unknown „sp. nov." is listed for its owner, not dropped');
check(archive_name_in('Inocybe sp. 2 2021-07-17', [], $lex, []) === ['unknown', 'Inocybe sp. 2'], 'so is a numbered „sp."');
check(archive_name_in('Inocybe sp.', [], $lex, []) === ['genus', null], 'a bare „sp." stays a genus alone');
$sp1 = ['genera' => [], 'species' => ['Inocybe sp. nov. 1']];
check(archive_name_hidden($sp1, 'inocybe sp. nov. 1') && !archive_name_hidden($sp1, 'inocybe sp. nov. 2'),
    'hiding one working name does not hide its neighbour');
check(archive_link_parse('https://e.pcloud.link/publink/show?code=kZabcdefghijklmnop')['api'] === 'eapi.pcloud.com',
    'a pCloud share link of the European data centre');
check(archive_link_parse('https://evil.example/publink/show?code=kZabcdefghijklmnop') === null
    && archive_link_parse('https://pcloud.link.evil.example/x?code=kZabcdefghijklmnop') === null,
    'any other host is refused — the server asks pCloud, never an address somebody typed');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
