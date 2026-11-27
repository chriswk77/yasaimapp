<?php
/**
 * The export for MykIS, the DGfM's mapping program (25.09.2026).
 *
 * THE COLUMNS ARE THE TEMPLATE'S, VERBATIM — mykdaten.xls, the import sheet
 * Frank Dämmrich, who looks after MykIS, posted in May 2021 (sheet
 * „ExpImpInsectis", 55 columns). MykIS maps them by NAME (the maintainer: names
 * like BASIS_datum1 exist for exactly that, and a mapping by position would
 * break on every sheet kept by hand), so the order here is the template's
 * for the human reader, not for the importer. An older, fuller version of
 * the sheet (January 2020, 94 columns with MykIS's internal SYS_ keys)
 * spells two of them „mtb" and „wirt" and imported all the same — MykIS
 * trims and ignores case. „    MTB" WITH ITS FOUR SPACES is the template's
 * own spelling; leave it, it is the one known to import.
 *
 * CSV AND NOT EXCEL („CSV reicht denen auch — CSV kann man
 * sofort in jedes Tabellenprogramm laden"). The state coordinator opens it
 * and imports from there, so it is written the way a GERMAN Excel reads it
 * without asking: semicolons, UTF-8 with its byte-order mark (without it
 * „Schröder" arrives as „SchrÃ¶der"), the day as 05.05.2019 and the MTB
 * with a decimal comma — then the day becomes a date and the MTB a number,
 * which is what the template's cells hold.
 *
 * WHAT GOES IN WAS DECIDED FIELD BY FIELD — first by the maintainer, then
 * corrected after he asked the coordinator for Hessen
 * (25.09.2026). The corrections are the answers of the man who imports:
 *   - THE COORDINATES GO ALONG, in degrees, minutes and seconds
 *     (geob… latitude, geol… longitude). The first plan left them out to
 *     keep the stands of rare species unfindable; but the DGfM does not
 *     publish them — they are seen by the coordinators only, and a
 *     scientific evaluation may need them. With them the inaccuracy
 *     column says something again: the GPS accuracy in metres.
 *   - THE MTB WITH ALL THREE DIGITS — to the sixty-fourth, as the coordinator maps
 *     („5815,432"); the quarter alone („5815,4") was the coarsened plan.
 *   - ERFASSER IS THE FINDER'S ACCOUNT — whoever was signed in when the
 *     find was recorded. The column names whoever entered the record, and
 *     since MykIS can be used privately, that is usually the finder; our
 *     app recorded it, so the account did. (A first reading had it empty
 *     for the coordinator to fill in — the coordinator put that right.)
 *   - OUR „SUBSTRAT / WIRT" IS SPREAD OVER THREE COLUMNS (27.11.2026):
 *     Wirt and organ_substrat only take names of MykIS' own lists, and
 *     the import refuses the whole row for one it does not know — so a
 *     host of the list goes to Wirt, a word of the substrate list to
 *     organ_substrat, everything else to the free substrat_text
 *     (mykis_hosts.php says how, and why the lists live on the server
 *     only). Until then the whole field stood in Wirt, „Laubschicht"
 *     included, as the first coordinator had allowed.
 *   - THE ACCOMPANYING VEGETATION GOES TO beobachtung_bemerkungen, labelled
 *     „Begleitvegetation: …" (26.11.2026, a second coordinator: better
 *     there). It stood behind the substrate in Wirt until then, as the
 *     first coordinator had allowed — but the plants around are an
 *     observation about the site, not what the fungus grew on, and in Wirt
 *     a reader had to cut the two apart again.
 *   - THE ORTSLAGE IS THE NEAREST FIELD NAME, hamlet, wood or peak from
 *     OpenStreetMap within a kilometre (27.11.2026, place_names.php) —
 *     and only for finders at the 64th quadrant or exact, because finer
 *     than that it would give away what a coarser choice kept back. The
 *     tour name was meant for it once, but on most finds it is
 *     „Tagestour".
 *   - THE IDENTIFICATION CERTAINTY GOES TO Qualität AS WE WRITE IT — MykIS
 *     keeps no vocabulary of its own there.
 *   - EMPTY ON PURPOSE: the site note (fo_bemerkung), county, postcode, source, stage, revision,
 *     whereabouts, substrate state, special site — and the species number,
 *     which MykIS finds by itself from genus and species (the coordinator).
 *     BASIS_artgruppe and BASIS_methode the template itself says to leave
 *     empty.
 *
 * ONLY FINDS MYKIS CAN TAKE, and only those the finder stands behind
 * (mykis_eligible):
 *   - OWN FINDS ONLY („es werden immer nur eigene Funde
 *     exportiert, also keine fremden") — a shared find goes out from its
 *     holder's account, with the holder's consent, or not at all.
 *   - AND OWN AS FINDER, unless the account says otherwise (0133;
 *     25.09.2026: „für fremde Funde, also auch bei denen ich
 *     Bestimmer bin … soll der Benutzer selbst entscheiden"). One's own
 *     stock can hold somebody else's finds — a friend's list taken over in
 *     an import, the finds one only determined — and they carry their
 *     finder in the field. TWO TICKS since 0144 (27.09.2026): one for the
 *     finds the account determined, one for the rest — the lists one was
 *     given to enter (mykis_whose_goes). Without them they stay home. A
 *     Finder field that is empty, or names the account in any usual
 *     spelling (person_names.php, the import's own rule), is one's own.
 *   - NAMED AFTER THE DGfM's REFERENCE LIST, the TaxRef (a title linked to
 *     a 'fungi:' entry): „das ist eben das, was man kartieren kann". It
 *     also settles the lichens — MykIS maps only the few the TaxRef
 *     carries.
 *   - DETERMINED WITH CERTAINTY — macroscopically, microscopically or
 *     genetically sure. A find goes out once and a later correction never
 *     follows it (the maintainer: think of a sample sequenced a year later), so
 *     what is marked uncertain or undetermined stays home.
 *     A FIND WITHOUT ANY MARK counts as macroscopically sure unless its
 *     NAME says otherwise (25.09.2026: the unmarked ones are the
 *     imports from Meine Pilze and finds entered from old photos; „alle
 *     Pilzarten, die nicht mit sensu lato oder agg. oder sonst einem
 *     Zusatz, der es unsicher macht, eingetragen sind, könnten als
 *     zumindest makroskopisch sicher bestimmt werden"). mykis_hedged()
 *     reads the name. Its Qualität stays empty — we assume the level, we
 *     do not claim it.
 *     AN AGGREGATE IS NO HEDGE (27.09.2026, correcting the „agg."
 *     of the sentence above): „agg." names a mapping unit of its own, and
 *     the TaxRef carries some of them. When science splits one species
 *     into two, the records before that day stand as the aggregate, and
 *     the split species is mapped „s. str." from then on — and since in
 *     the extreme only a gene sample tells them apart, mapping as the
 *     aggregate stays legitimate: „da braucht man sich dann nicht den
 *     Aufwand machen, genau die Art zu bestimmen". So an unmarked „X agg."
 *     goes, like „X s. str." always did; it still has to be named after
 *     the TaxRef, and the „agg." travels on in the ART column (mykis_name).
 *     AND „s. l." THE SAME, the same day („s. l. schicken wir
 *     auch mit") — the species in the broad sense is the aggregate's
 *     older word. What still hedges an unmarked name: cf., aff., sp.
 *   - NO AUFSAMMLUNG (0174): the dung in its dish is the sample, not a
 *     record of a species. What grew from it goes, as a find like any
 *     other, with „ex cultura" in art_bemerkung (collection_note) —
 *     otherwise the coordinator reads the collecting day as the day the
 *     fungus fruited in the field. Its find date stays the collecting
 *     day, as herbaria and Darwin Core keep it (collections.php).
 *   - In Germany (there is no sheet elsewhere), with a find day (a mapping
 *     record without a date is none, and the creation day of an imported
 *     point would be a false one), under a scientific name.
 *
 * AND SINCE 0127 IT GOES OUT BY ITSELF: twice a year the delivery run
 * (mykis_deliver, by cron in cronjobs/daily.php) mails each finder's new
 * sheet to the coordinator of the state — both having agreed.
 */

declare(strict_types=1);

// collection_note(): the „ex cultura" a species from a culture carries.
require_once __DIR__ . '/collections.php';
// mykis_substrate(): where „Substrat / Wirt" goes among MykIS' lists.
require_once __DIR__ . '/mykis_hosts.php';
// place_name_at(): the Ortslage, from the names fetched per state.
require_once __DIR__ . '/place_names.php';

/** The template's head, in the template's order and spelling. */
const MYKIS_COLUMNS = [
    'BASIS_artgruppe', 'BASIS_methode', 'BASIS_datum1', 'datum2', 'erfasser',
    'nachweisquelle', 'beobachtung_bemerkungen', 'BASIS_ort', 'BASIS_ortslage',
    'name_staat', 'name_provinz', '    MTB', 'name_kreis', 'plz',
    'hoehenstufe', 'ozeanitaet', 'zonalitaet', 'hoehe', 'rechtswert1',
    'hochwert1', 'ostwert1', 'nordwert1', 'geolgrad1', 'geolminute1',
    'geolsekunde1', 'geobgrad1', 'geobminute1', 'geobsekunde1', 'geolgrad2',
    'geolminute2', 'geolsekunde2', 'geobgrad2', 'geobminute2', 'geobsekunde2',
    'fo_bemerkung', 'GATTUNG', 'ART', 'sammler', 'bestimmer', 'Wirt',
    'stadium', 'art_bemerkung', 'rev', 'verbleib', 'organ_substrat',
    'substrat_text', 'substratzustand', 'wuchsstelle', 'sonderstandort',
    'beleg_nr', 'art_nr', 'Foto_Zeichnung', 'Beleg', 'Ungenauigkeit',
    'Qualität',
];

/**
 * The states as the template writes them („Sachsen") — resolved from the
 * three-letter codes the points carry (MUNICIPALITY_PARTS in
 * municipality.php). Germany only: MykIS maps German sheets.
 */
const MYKIS_STATES = [
    'BAW' => 'Baden-Württemberg', 'BAY' => 'Bayern', 'BLN' => 'Berlin',
    'BRB' => 'Brandenburg', 'BRM' => 'Bremen', 'HAM' => 'Hamburg',
    'HES' => 'Hessen', 'MVP' => 'Mecklenburg-Vorpommern', 'NDS' => 'Niedersachsen',
    'NRW' => 'Nordrhein-Westfalen', 'RLP' => 'Rheinland-Pfalz', 'SAL' => 'Saarland',
    'SAC' => 'Sachsen', 'SAH' => 'Sachsen-Anhalt', 'SLH' => 'Schleswig-Holstein',
    'THU' => 'Thüringen',
];

/**
 * HOW PRECISELY A FINDER HANDS OVER THEIR FINDS (migrations 0129, 0130) —
 * each finder's own choice (25.09.2026: „jeden Benutzer selbst
 * entscheiden lassen, wie genau er seine Daten abgeben will"). Only 'exact'
 * carries coordinates; the others keep the MTB to their step and nothing
 * finer. The sizes are what a step measures on the ground in Hessen, about
 * 50° north: a sheet spans 10' of longitude and 6' of latitude, some 12 by
 * 11 km, and every quadrant digit halves both sides.
 *
 * THE QUARTER IS THE FLOOR AND THE DEFAULT (the same evening:
 * „nur Messtischblatt geht nicht — der Viertelquadrant ist das
 * Ungenaueste, was wir haben", and „genau dahin auch den Standard — wir
 * wollen niemandem etwas aufzwingen, was er nicht will"). The sheet alone
 * stood for an hour and went. Whoever never chose, or chose a step that is
 * gone, hands over the quarter; anything finer only by their own hand.
 *
 * 'label' is the settings' wording (German, translated there), 'short' the
 * coordinators' — their mail is German always.
 */
const MYKIS_PRECISIONS = [
    // From the least handed over to the most — the order the settings
    // offer them in, the default on top.
    'q4'    => ['digits' => 1, 'label' => 'Viertelquadrant — etwa 5,5 × 6 km, ohne Koordinaten',
                'short' => 'nur Messtischblatt bis zum Viertelquadranten'],
    'q16'   => ['digits' => 2, 'label' => '16tel-Quadrant — etwa 2,8 × 3 km, ohne Koordinaten',
                'short' => 'nur Messtischblatt bis zum 16tel'],
    'q64'   => ['digits' => 3, 'label' => '64stel-Quadrant — etwa 1,4 × 1,5 km, ohne Koordinaten',
                'short' => 'nur Messtischblatt bis zum 64stel'],
    'exact' => ['digits' => 3, 'label' => 'Genaue Koordinaten — auf etwa 3 m',
                'short' => 'genaue Koordinaten'],
];

/** The step of whoever never chose — the coarsest there is. */
const MYKIS_PRECISION_DEFAULT = 'q4';

/**
 * A stored precision, or the default for none or a stale one — a 'sheet'
 * from 0129's first hour, say. Unknown never falls towards finer.
 */
function mykis_precision(?string $value): string
{
    return isset(MYKIS_PRECISIONS[(string) $value]) ? (string) $value : MYKIS_PRECISION_DEFAULT;
}

/**
 * THE FINDER'S RULES (0143, 27.09.2026: „einen kompletten Bereich
 * Genauigkeit, wo wir filtern können") — beside the one step for
 * everything, which becomes the step for „alle übrigen Funde":
 *
 *   exclude   genera and species that are not reported at all
 *   redlist   Red List category => step, each of its own (since
 *             28.09.2026, „dass man jede Stufe einzeln in der
 *             Schärfe einstellen kann"; before, the ticked categories
 *             shared ONE step — read and turned round below)
 *   edible    a step for the DGfM's edible mushrooms — for whoever does
 *             not want to give his spots away
 *   genera    genus => step, each of its own
 *   species   species => step, each of its own
 *
 * WHERE SEVERAL FIT ONE FIND, THE COARSEST STEP WINS („am gröbsten, am
 * vorsichtigsten" — his answer when asked whether the more specific rule
 * should win instead). The step for „alle übrigen" applies only where no
 * rule fits; an exclusion beats everything.
 */

/** The Red List categories one can pick — RED_LIST_CATEGORIES (species.php) without „ungefährdet" and „nicht bewertet". */
const MYKIS_RED_LIST = ['0', '1', '2', '3', 'G', 'R', 'V', 'D'];

/** Whether the rules can be stored — the code is live before migration 0143. */
function mykis_rules_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'mykis_rules');
}

/**
 * The rules as stored, cleaned — anything bent or unknown falls away, so a
 * hand-edited row can only ever mean fewer rules, never a wrong one.
 *
 * @return array{exclude: array{genera: string[], species: string[]},
 *               redlist: array<string, string>,
 *               edible: ?string, genera: array<string, string>, species: array<string, string>}
 */
function mykis_rules_read(mixed $stored): array
{
    $d = is_string($stored) ? json_decode($stored, true) : $stored;
    $d = is_array($d) ? $d : [];
    $clean = static fn(mixed $s): string => trim((string) preg_replace('/\s+/u', ' ', (string) $s));
    $step  = static fn(mixed $v): ?string => is_string($v) && isset(MYKIS_PRECISIONS[$v]) ? $v : null;
    $names = static function (mixed $list) use ($clean): array {
        $out = [];
        foreach ((array) $list as $name) {
            $name = $clean($name);
            if ($name !== '' && !in_array(mb_strtolower($name), array_map('mb_strtolower', $out), true)) {
                $out[] = $name;
            }
        }
        return $out;
    };
    $map = static function (mixed $list) use ($clean, $step): array {
        $out = [];
        foreach ((array) $list as $name => $value) {
            $name = $clean($name);
            if ($name !== '' && ($s = $step($value)) !== null) {
                $out[$name] = $s;
            }
        }
        return $out;
    };
    /* THE RED LIST, ONE STEP PER CATEGORY. The shape before 28.09.2026 —
       {categories: [...], step} — is turned round into the same map, so a
       rule stored then keeps working and the next save writes the new
       shape. Category keys like '0' and '1' become PHP integers in an
       array; they are compared as strings. */
    $red = is_array($d['redlist'] ?? null) ? $d['redlist'] : [];
    $pairs = [];
    if (array_key_exists('categories', $red) || array_key_exists('step', $red)) {
        $shared = $step($red['step'] ?? null);
        foreach ((array) ($red['categories'] ?? []) as $category) {
            $pairs[(string) $category] = $shared;
        }
    } else {
        foreach ($red as $category => $value) {
            $pairs[(string) $category] = $step($value);
        }
    }
    $redlist = [];
    foreach (MYKIS_RED_LIST as $category) {
        if (($pairs[$category] ?? null) !== null) {
            $redlist[$category] = $pairs[$category];
        }
    }
    return [
        'exclude' => ['genera'  => $names($d['exclude']['genera'] ?? []),
                      'species' => $names($d['exclude']['species'] ?? [])],
        'redlist' => $redlist,
        'edible'  => $step($d['edible'] ?? null),
        'genera'  => $map($d['genera'] ?? []),
        'species' => $map($d['species'] ?? []),
    ];
}

/** Whether any rule beyond the one step is set — for the coordinator mail's line. */
function mykis_rules_any(array $rules): bool
{
    return ($rules['exclude']['genera'] ?? []) !== [] || ($rules['exclude']['species'] ?? []) !== []
        || ($rules['redlist'] ?? []) !== []
        || ($rules['edible'] ?? null) !== null || ($rules['genera'] ?? []) !== [] || ($rules['species'] ?? []) !== [];
}

/**
 * A name's genus and species as the rules compare them: the first word, and
 * the first two — lower case, so „boletus Edulis" typed in a hurry still
 * meets „Boletus edulis agg." on a find (the „agg." is no part of it).
 *
 * @return array{0: string, 1: string} genus, species — empty when no name
 */
function mykis_taxon(string $name): array
{
    $words = preg_split('/\s+/u', mb_strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return [$words[0] ?? '', isset($words[1]) ? $words[0] . ' ' . $words[1] : ''];
}

/**
 * A typed name as a rule keeps it: the DGfM's own spelling of the genus, or
 * of the species by its first two words — or null when the list does not
 * know it. A rule for „Boletus edulys" would match nothing and look as if
 * it did. A species rule is two words because the rules compare two words
 * (mykis_taxon): „Boletus edulis var. arenarius" typed becomes „Boletus
 * edulis", and the row shows the user what the rule covers.
 */
function mykis_rule_name(PDO $pdo, string $typed, bool $species): ?string
{
    $words = preg_split('/\s+/u', trim($typed), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$species) {
        if (count($words) !== 1) {
            return null;
        }
        $q = $pdo->prepare('SELECT genus FROM map_tax_dgfm WHERE genus = ? LIMIT 1');
        $q->execute([$words[0]]);
        $found = $q->fetchColumn();
        return is_string($found) && $found !== '' ? $found : null;
    }
    if (count($words) < 2) {
        return null;
    }
    // „Boletus edulis" itself, or a name that begins with it — an agg. or a
    // variety the list carries while the species stands elsewhere. The
    // collation compares without case, the index takes the prefix.
    $two = $words[0] . ' ' . $words[1];
    $q = $pdo->prepare('SELECT binomial FROM map_tax_dgfm WHERE binomial = ? OR binomial LIKE ? LIMIT 1');
    $q->execute([$two, addcslashes($two, '%_\\') . ' %']);
    $found = $q->fetchColumn();
    if (!is_string($found)) {
        return null;
    }
    $spelled = preg_split('/\s+/u', $found, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return count($spelled) >= 2 ? $spelled[0] . ' ' . $spelled[1] : null;
}

/**
 * THE MYKIS SHEET BY HAND ASKS ITSELF (27.09.2026, F1): the
 * export window under Organisieren asks how precisely and whether other
 * finders' finds go along, instead of the settings deciding unseen. What
 * the window starts from are the account's own choices. „Nach deinen
 * Regeln" is offered only to whoever agreed, because the rules live
 * behind the consent (the Genauigkeit tab appears with it) — to anybody
 * else they are invisible, so they must not act either.
 *
 * @return array{consented: bool, precision: string, foreign: bool, determined: bool, excluded: bool,
 *               steps: list<array{value: string, text: string}>}
 */
function mykis_export_choices(PDO $pdo, int $userId): array
{
    $columns = array_keys(array_filter([
        'mykis_consent_at' => mykis_ready($pdo),
        'mykis_precision'  => column_exists($pdo, 'map_users', 'mykis_precision'),
        'mykis_foreign'    => mykis_foreign_ready($pdo),
        'mykis_determined' => mykis_determined_ready($pdo),
        'mykis_rules'      => mykis_rules_ready($pdo),
    ]));
    $row = [];
    if ($columns !== []) {
        $q = $pdo->prepare('SELECT ' . implode(', ', $columns) . ' FROM map_users WHERE id = ?');
        $q->execute([$userId]);
        $row = $q->fetch() ?: [];
    }
    $consented = ($row['mykis_consent_at'] ?? null) !== null;
    $rules = mykis_rules_read($consented ? ($row['mykis_rules'] ?? null) : null);
    $steps = [];
    foreach (MYKIS_PRECISIONS as $key => $level) {
        $steps[] = ['value' => $key, 'text' => t($level['label'])];
    }
    return [
        'consented' => $consented,
        'precision' => mykis_precision($row['mykis_precision'] ?? null),
        // The two ticks as the delivery reads them (mykis_self): before
        // 0144 the one old tick stands for both.
        'foreign'    => (int) ($row['mykis_foreign'] ?? 0) === 1,
        'determined' => (int) ($row['mykis_determined'] ?? ($row['mykis_foreign'] ?? 0)) === 1,
        'excluded'  => $rules['exclude']['genera'] !== [] || $rules['exclude']['species'] !== [],
        'steps'     => $steps,
    ];
}

/**
 * The answers of the export window, read into what mykis_rows() takes.
 *
 * 'rules' is the finder's rules; a fixed step applies to every find, and
 * THE EXCLUSIONS HOLD EITHER WAY („die Ausschlussliste gilt immer") — only
 * the steps of the rules give way. Without the consent there are no rules
 * at all, whatever was sent. A request without the answers — a page loaded
 * before this window asked — takes the account's own choices.
 *
 * @param array $account the map_users row
 * @param array $asked   'precision' => 'rules'|step, 'determined' and
 *                       'foreign' (the rest) => '1'|'0'
 * @return array{precision: ?string, self: ?array, rules: array}
 */
function mykis_manual_options(array $account, array $asked): array
{
    $consented = ($account['mykis_consent_at'] ?? null) !== null;
    $rules = mykis_rules_read($consented ? ($account['mykis_rules'] ?? null) : null);
    $step = is_string($asked['precision'] ?? null) ? $asked['precision'] : '';
    if (isset(MYKIS_PRECISIONS[$step])) {
        $precision = $step;
        $rules = ['exclude' => $rules['exclude']] + mykis_rules_read(null);
    } else {
        $precision = $account['mykis_precision'] ?? null;
    }
    // Only '1' and '0' are answers — the export form sends an empty field
    // for every other format, and a request from before sends none; then
    // the account's own ticks count, as the delivery reads them.
    $answer = static fn(string $key): ?int => in_array($asked[$key] ?? null, ['0', '1'], true) ? (int) $asked[$key] : null;
    $mine = mykis_self($account);
    $foreign = $answer('foreign') ?? (int) ($mine === null || $mine['rest']);
    $determined = $answer('determined') ?? (int) ($mine === null || $mine['determined']);
    return ['precision' => $precision,
            'self' => mykis_self(['mykis_foreign' => $foreign, 'mykis_determined' => $determined] + $account),
            'rules' => $rules];
}

/** Whether a find is on the finder's list of what is not reported. */
function mykis_excluded(array $p, array $rules): bool
{
    [$genus, $species] = mykis_taxon((string) ($p['title'] ?? ''));
    foreach ($rules['exclude']['genera'] ?? [] as $name) {
        if ($genus !== '' && mykis_taxon($name)[0] === $genus) {
            return true;
        }
    }
    foreach ($rules['exclude']['species'] ?? [] as $name) {
        if ($species !== '' && mykis_taxon($name)[1] === $species) {
            return true;
        }
    }
    return false;
}

/**
 * The step a find goes at: the COARSEST of every rule that fits it, or the
 * step for „alle übrigen" when none does. The Red List categories come
 * with the find's species (red_list), the edible mark from the DGfM's lists
 * (dgfm, dgfm_marks.php) — the same points_load() and export_points() hand
 * over for the filter.
 */
function mykis_step(array $p, array $rules, string $default): string
{
    [$genus, $species] = mykis_taxon((string) ($p['title'] ?? ''));
    $steps = [];
    foreach ($rules['genera'] ?? [] as $name => $step) {
        if ($genus !== '' && mykis_taxon((string) $name)[0] === $genus) {
            $steps[] = $step;
        }
    }
    foreach ($rules['species'] ?? [] as $name => $step) {
        if ($species !== '' && mykis_taxon((string) $name)[1] === $species) {
            $steps[] = $step;
        }
    }
    // Every category the find carries brings its own step, where one is set.
    foreach ((array) ($p['red_list'] ?? []) as $category) {
        $redStep = ($rules['redlist'] ?? [])[(string) $category] ?? null;
        if ($redStep !== null) {
            $steps[] = $redStep;
        }
    }
    if (($rules['edible'] ?? null) !== null) {
        foreach ((array) ($p['dgfm'] ?? []) as $mark) {
            if ((is_array($mark) ? ($mark['list'] ?? '') : $mark) === 'edible') {
                $steps[] = $rules['edible'];
                break;
            }
        }
    }
    return $steps === [] ? mykis_precision($default) : mykis_coarsest($steps);
}

/** The coarsest of some steps — MYKIS_PRECISIONS stands coarsest first. */
function mykis_coarsest(array $steps): string
{
    foreach (array_keys(MYKIS_PRECISIONS) as $step) {
        if (in_array($step, $steps, true)) {
            return $step;
        }
    }
    return MYKIS_PRECISION_DEFAULT;
}

/** What „certain" means for the delivery — the stored values of the field. */
const MYKIS_CERTAIN = ['makroskopisch sicher', 'mikroskopisch sicher', 'genetisch sicher'];

/**
 * The rows for MykIS, one per find it can take — aligned with MYKIS_COLUMNS.
 *
 * @param array[] $points  as export_points() hands them over
 * @param string  $holder  the exporting account as „Nachname, Vorname" —
 *        finder and determiner where a find's fields are empty
 * @param string[]|null $self  mykis_self() of the account — null takes
 *        the finds of other finders too
 * @return string[][]
 */
function mykis_rows(array $points, string $holder, ?string $precision = null, ?array $self = null,
                    ?array $rules = null): array
{
    $rules ??= mykis_rules_read(null);
    $rows = [];
    foreach ($points as $p) {
        if (mykis_eligible($p, $self, $rules)) {
            $rows[] = mykis_row($p, $holder, mykis_step($p, $rules, mykis_precision($precision)));
        }
    }
    return $rows;
}

/**
 * Whether a find may go to MykIS — the rules at the head of this file.
 *
 * @param string[]|null $self  mykis_self() of the account; null asks no
 *        question of the finder
 */
function mykis_eligible(array $p, ?array $self = null, ?array $rules = null): bool
{
    /* WHAT THE FINDER EXCLUDED IS NOT ELIGIBLE AT ALL — decided here and not
       when the row is written, or the delivery would book as sent a find
       that never went (mykis_pending reads this). */
    if ($rules !== null && mykis_excluded($p, $rules)) {
        return false;
    }
    $day       = substr((string) ($p['found_at'] ?? ''), 0, 10);
    $country   = strtoupper(trim((string) ($p['country'] ?? '')));
    $extra     = (array) ($p['extra'] ?? []);
    $title     = (string) ($p['title'] ?? '');
    $certainty = trim((string) ($extra['Bestimmungssicherheit'] ?? ''));
    // A foreign find — shared, co-owned, an excursion guest's — carries
    // the name of its holder; an own one carries none.
    return trim((string) ($p['owner'] ?? '')) === ''
        && empty($p['is_collection'])
        && mykis_name($title) !== null
        && mykis_mtb($p['mtb'] ?? null, $p['mtb_quadrant'] ?? null) !== ''
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1
        && ($country === '' || $country === 'DE')
        && (in_array($certainty, MYKIS_CERTAIN, true) || ($certainty === '' && !mykis_hedged($title)))
        && mykis_taxref($p['extra_species'] ?? null)
        && ($self === null || mykis_whose_goes($p, $self));
}

/**
 * Whether a find goes by whose it is (27.09.2026: „Ich würde da
 * zwei Felder draus machen"). One's own finds always; another finder's
 * only on a tick — the first for the finds the account determined (its
 * name in the Bestimmer field), the second for all the rest, the lists
 * one takes over from somebody else: a deceased member's finds, a list
 * handed in to be entered. An empty Bestimmer on another finder's find is
 * the rest — the account's name is not on it, and the row names the
 * finder as determiner (mykis_row).
 *
 * @param array $self  mykis_self(): tokens and the two ticks — or, as
 *        before 0144, a bare token list, which means own finds only
 */
function mykis_whose_goes(array $p, array $self): bool
{
    $tokens = array_key_exists('tokens', $self) ? (array) $self['tokens'] : $self;
    if (mykis_own_finder($p, $tokens)) {
        return true;
    }
    require_once __DIR__ . '/person_names.php';
    $determiner = trim((string) (((array) ($p['extra'] ?? []))['Bestimmer'] ?? ''));
    return $determiner !== '' && person_names_self($determiner, $tokens)
        ? (bool) ($self['determined'] ?? false)
        : (bool) ($self['rest'] ?? false);
}

/**
 * Whether the account found it itself — the Finder field empty (the row
 * then names the holder, mykis_row) or naming the account. An account
 * without a name cannot tell, and keeps what it has, as the import does.
 */
function mykis_own_finder(array $p, array $self): bool
{
    require_once __DIR__ . '/person_names.php';
    $finder = trim((string) (((array) ($p['extra'] ?? []))['Finder'] ?? ''));
    return $finder === '' || $self === [] || person_names_self($finder, $self);
}

/**
 * Whose finds the account sends: its name as the finder question reads it
 * and its two ticks (mykis_whose_goes) — or null when both are set and
 * every find goes, whoever found or determined it.
 *
 * mykis_foreign (0133) is the tick for the rest since 0144; before 0144
 * it was the one tick for all other finders, so where mykis_determined is
 * missing — the migration not applied yet — it stands for both, as it
 * always did. Without either column nobody ticked, and own finds go.
 *
 * @return array{tokens: string[], determined: bool, rest: bool}|null
 */
function mykis_self(array $account): ?array
{
    $rest = (int) ($account['mykis_foreign'] ?? 0) === 1;
    $determined = array_key_exists('mykis_determined', $account)
        ? (int) $account['mykis_determined'] === 1
        : $rest;
    if ($rest && $determined) {
        return null;
    }
    require_once __DIR__ . '/person_names.php';
    return [
        'tokens'     => person_name_tokens(trim((string) ($account['first_name'] ?? '') . ' ' . (string) ($account['last_name'] ?? ''))),
        'determined' => $determined,
        'rest'       => $rest,
    ];
}

/** Is the tick for the finds one determined there (0144)? */
function mykis_determined_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'mykis_determined');
}

/**
 * Whether a NAME says it is not sure — for a find nobody marked.
 *
 * THE OPEN MARKS ARE READ EXACTLY AS THE IDENTIFICATION FILTER READS THEM
 * (mykis_open_marks, the twin of openMarksIn in identification.js), and
 * cf., aff. and sp. hedge — not s. str., which says the opposite, and
 * since 27.09.2026 neither agg. nor s. l., the aggregate and the broad
 * sense, which name units the DGfM maps (the head of this file says why). The READING stays the
 * filter's twin; only which families hedge is MykIS's own question.
 * Two more count here and only here: „o."/„oder"/„vel" between two names
 * („Tricholoma frondosae o. equestre") and a question mark. The filter
 * leaves them alone; a find reported to the DGfM has to name ONE species.
 */
function mykis_hedged(string $title): bool
{
    return array_diff(mykis_open_marks($title), ['sstr', 'agg', 'sl']) !== []
        || preg_match('/\?|(?:^|\s)(?:o|oder|vel)\.?(?=\s|$)/iu', $title) === 1;
}

/** The single abbreviations per family — identification.js, SINGLE. */
const MYKIS_MARKS_SINGLE = [
    'cf'   => ['cf', 'cfr'],
    'aff'  => ['aff', 'affin'],
    'sp'   => ['sp', 'spp', 'spec', 'species'],
    'sl'   => ['sl'],
    'agg'  => ['agg', 'aggr'],
    'sstr' => ['sstr', 'ss'],
];

/** The two-part abbreviations — identification.js, PAIRS. */
const MYKIS_MARKS_PAIRS = [
    'sl'   => [['s', 'l'], ['s', 'lat'], ['sensu', 'lato']],
    'cf'   => [['c', 'f']],
    'sstr' => [['s', 'str'], ['s', 'stricto'], ['sensu', 'stricto'], ['s', 's']],
];

/**
 * The families of open marks a title carries.
 *
 * A DELIBERATE TWIN of openMarksIn() in assets/js/identification.js — the
 * delivery runs on the server and in a cron job, where the browser's
 * module is out of reach. Same tables, same word-by-word reading, same
 * traps: „affinis" is an epithet, not „aff."; „L." is Linné, not half an
 * „s. l."; „splendens" hides no „sp". Whoever changes one side changes
 * the other; tests/export.php holds this side to the cases of
 * tests/identification.mjs.
 *
 * @return string[] family values in the filter's order, empty when none
 */
function mykis_open_marks(string $name): array
{
    $words = preg_split('/\s+/u', trim((string) preg_replace('/[.,;:!?()\[\]{}"\'«»]/u', ' ',
        mb_strtolower($name))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $found = [];
    foreach (['sp', 'cf', 'aff', 'sl', 'agg', 'sstr'] as $family) {
        $hit = array_intersect($words, MYKIS_MARKS_SINGLE[$family]) !== [];
        foreach (MYKIS_MARKS_PAIRS[$family] ?? [] as [$a, $b]) {
            for ($i = 0; !$hit && $i < count($words) - 1; $i++) {
                $hit = $words[$i] === $a && $words[$i + 1] === $b;
            }
        }
        if ($hit) {
            $found[] = $family;
        }
    }
    return $found;
}

/**
 * Whether the title is linked to the DGfM's reference list.
 *
 * The link lives in extra_species as {"": {"Boletus edulis": "fungi:1234"}}
 * — '' is the title, the value the entry it was matched to; 'fungi' is the
 * TaxRef's source key (species.php). A title typed past the suggestions,
 * or matched to GBIF, carries none.
 */
function mykis_taxref(mixed $references): bool
{
    $data = is_string($references) ? json_decode($references, true) : $references;
    foreach ((array) (is_array($data) ? ($data[''] ?? []) : []) as $reference) {
        if (str_starts_with((string) $reference, 'fungi:')) {
            return true;
        }
    }
    return false;
}

/**
 * The Ortslage of a find — at the 64th quadrant and exact only, and only
 * where somebody building the sheet has named the database
 * (place_names_source).
 */
function mykis_place_name(array $p, string $level): string
{
    $pdo = place_names_source();
    if ($pdo === null || !in_array($level, ['q64', 'exact'], true)
        || !is_numeric($p['lat'] ?? null) || !is_numeric($p['lon'] ?? null)) {
        return '';
    }
    return place_name_at($pdo, (float) $p['lat'], (float) $p['lon']);
}

/**
 * One find as a row of the sheet — mykis_eligible() has passed it — at the
 * finder's precision: coordinates and their inaccuracy only for 'exact',
 * the MTB cut to the chosen step; no precision is the default step.
 */
function mykis_row(array $p, string $holder, ?string $precision = null): array
{
    $level   = mykis_precision($precision);
    $exact   = $level === 'exact';
    $name    = mykis_name((string) ($p['title'] ?? ''));
    $mtb     = mykis_mtb($p['mtb'] ?? null, $p['mtb_quadrant'] ?? null, MYKIS_PRECISIONS[$level]['digits']);
    $day     = substr((string) ($p['found_at'] ?? ''), 0, 10);
    $extra   = (array) ($p['extra'] ?? []);
    $field   = static fn(string $key): string => trim((string) ($extra[$key] ?? ''));
    /* THE VOUCHER NUMBER: what stands in the field „Beleg-Nr.", and where
       nothing stands, the find's number (26.09.2026: „der
       Eintrag darin gewinnt immer … ansonsten kannst du da die Fundnummer
       eintragen" — he means to write the find number on his vouchers). A
       voucher system brought in by an import keeps its numbers in the
       field, and they win. The find's number is its own or, without one,
       the „Y-" mark every other place shows — the automatic delivery
       reads points_load(), which carries no mark, so it is made here.
       „Beleg: ja" follows what the finder said, never the find number
       alone — a number on every find would claim a voucher for each: an
       entered voucher number, or the tick „Beleg aufbewahrt" (26.09.2026). */
    $voucher = $field('Beleg-Nr.');
    $number  = trim((string) ($p['find_number'] ?? ''));
    if ($number === '' && (int) ($p['id'] ?? 0) > 0) {
        require_once __DIR__ . '/find_number.php';
        $number = find_number_fallback((int) $p['id']);
    }
    $date    = implode('.', array_reverse(explode('-', $day)));
    $region  = strtoupper(trim((string) ($p['region'] ?? '')));

    [$latDeg, $latMin, $latSec] = $exact ? mykis_dms((float) ($p['lat'] ?? 0)) : ['', '', ''];
    [$lonDeg, $lonMin, $lonSec] = $exact ? mykis_dms((float) ($p['lon'] ?? 0)) : ['', '', ''];
    // The plants around — one per line in our multiline field, one after
    // another here, under their label (the column takes any remark).
    $vegetation = implode(', ', array_filter(array_map('trim', preg_split('/\R/u', $field('Begleitvegetation')) ?: [])));
    $substrate = mykis_substrate($field('Substrat / Wirt'));

    $values = [
        'BASIS_datum1'   => $date,
        'datum2'         => $date,
        'erfasser'       => $holder,
        'beobachtung_bemerkungen' => $vegetation !== '' ? 'Begleitvegetation: ' . $vegetation : '',
        'BASIS_ort'      => trim((string) ($p['municipality'] ?? '')),
        'BASIS_ortslage' => mykis_place_name($p, $level),
        'name_staat'     => 'Deutschland',
        'name_provinz'   => MYKIS_STATES[$region] ?? '',
        '    MTB'        => $mtb,
        'hoehe'          => ($p['elevation_m'] ?? null) === null ? '' : (string) (int) $p['elevation_m'],
        'geolgrad1'      => $lonDeg,
        'geolminute1'    => $lonMin,
        'geolsekunde1'   => $lonSec,
        'geobgrad1'      => $latDeg,
        'geobminute1'    => $latMin,
        'geobsekunde1'   => $latSec,
        'GATTUNG'        => $name[0],
        'ART'            => $name[1],
        'sammler'        => mykis_person($field('Finder')) ?: $holder,
        /* AN EMPTY BESTIMMER IS THE FINDER'S, where there is one
           (27.09.2026, answer 1a): in a list taken over from
           somebody else the finder determined his own finds, and the
           holder's name on them would claim a determination he never
           made. On one's own finds the Finder field is empty or the
           holder's, so it comes to the same. */
        'bestimmer'      => mykis_person($field('Bestimmer')) ?: (mykis_person($field('Finder')) ?: $holder),
        'Wirt'           => $substrate['wirt'],
        'organ_substrat' => $substrate['organ_substrat'],
        'substrat_text'  => $substrate['substrat_text'],
        // „ex cultura, bestimmt am …" — empty for a find from the field.
        'art_bemerkung'  => collection_note($p),
        'beleg_nr'       => $voucher !== '' ? $voucher : $number,
        // Pictures on this server, or recordings in the cloud — counted by
        // points_load(), listed by the export's loader.
        'Foto_Zeichnung' => ($p['photos'] ?? []) !== [] || ($p['recordings_list'] ?? []) !== []
                            || (int) ($p['recordings'] ?? 0) > 0 ? 'ja' : '',
        'Beleg'          => $voucher !== '' || $field('Beleg aufbewahrt') !== '' ? 'ja' : '',
        'Ungenauigkeit'  => !$exact || ($p['accuracy_m'] ?? null) === null ? '' : (string) (int) round((float) $p['accuracy_m']),
        'Qualität'       => $field('Bestimmungssicherheit'),
    ];
    return array_map(static fn(string $column): string => $values[$column] ?? '', MYKIS_COLUMNS);
}

/**
 * A coordinate as degrees, whole minutes and seconds to a tenth, the
 * second with a decimal comma — 50°7'24,4" is ['50', '7', '24,4'].
 *
 * COUNTED IN TENTHS OF A SECOND FROM THE START, so rounding can never
 * produce „60" seconds or minutes — 7'59,96" becomes 8'0,0", not 7'60,0".
 * A tenth of a second is some three metres, as fine as a phone's GPS.
 */
function mykis_dms(float $value): array
{
    $tenths = (int) round(abs($value) * 36000);
    $sign   = $value < 0 ? '-' : '';
    $degree = intdiv($tenths, 36000);
    $minute = intdiv($tenths % 36000, 600);
    $second = ($tenths % 600) / 10;
    return [$sign . $degree, (string) $minute, str_replace('.', ',', number_format($second, 1, '.', ''))];
}

/** The file: the template's head, then the rows — for a German spreadsheet. */
function mykis_csv(array $rows): string
{
    $cell = static function (string $value): string {
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        /* A VALUE THAT STARTS LIKE A FORMULA IS DEFUSED. A spreadsheet runs
           „=…" as a formula when it opens the file — on the coordinator's
           machine, out of whatever somebody typed into a find field. The
           apostrophe keeps it text; plain numbers are left alone. */
        if (preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value)) {
            $value = "'" . $value;
        }
        return preg_match('/[;"\r\n]/', $value) ? '"' . str_replace('"', '""', $value) . '"' : $value;
    };
    $lines = [implode(';', array_map($cell, MYKIS_COLUMNS))];
    foreach ($rows as $row) {
        $lines[] = implode(';', array_map($cell, $row));
    }
    return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
}

/**
 * Genus and the rest of a scientific name — or null when the title is none.
 *
 * THE FIRST WORD IS THE GENUS, EVERYTHING AFTER IT GOES TO ART: the epithet
 * and whatever qualifies it — var., f., agg., cf., s.l. — stays with the
 * species, where a reader of the sheet looks for it. A name is a capitalised
 * word followed by one that starts small („sp.", „cf." included); „Allee",
 * „Urlaub 2019" or a coordinate pasted as a title are not.
 */
function mykis_name(string $title): ?array
{
    $title = trim((string) preg_replace('/\s+/u', ' ', $title));
    if (!preg_match('/^(\p{Lu}[\p{Ll}-]+) ((?:\p{Ll}|×).*)$/u', $title, $m)) {
        return null;
    }
    return [$m[1], $m[2]];
}

/**
 * Sheet and quadrant digits with a decimal comma — „5815,432" to the
 * sixty-fourth as the coordinator maps, as far as the point knows them and the
 * finder's step allows ($keep digits); the sheet alone when neither does.
 */
function mykis_mtb(?string $sheet, ?string $quadrant, int $keep = 3): string
{
    $sheet = trim((string) $sheet);
    if (!preg_match('/^\d{3,4}$/', $sheet)) {
        return '';
    }
    $digits = substr(trim((string) $quadrant), 0, max(0, $keep));
    return preg_match('/^[1-4]{1,3}$/', $digits) ? $sheet . ',' . $digits : $sheet;
}

/**
 * A person as the template writes one: „Dämmrich, Frank".
 *
 * ONLY TWO WORDS ARE TURNED ROUND. „Sebastian Schröder" is plainly first
 * and last name; „Sebastian Schröder und Volker Maier" or „S. S. & V. M."
 * is not, and turning it round would make a stranger of both — so it stays
 * as typed, and so does anything already carrying its comma.
 */
function mykis_person(string $name): string
{
    $name  = trim((string) preg_replace('/\s+/u', ' ', $name));
    $words = explode(' ', $name);
    if ($name === '' || str_contains($name, ',') || count($words) !== 2) {
        return $name;
    }
    return $words[1] . ', ' . $words[0];
}

// ---------------------------------------------------------------------------
// The delivery to the state coordinators (migration 0127)
// ---------------------------------------------------------------------------

/** Whether migration 0127 is in — the code goes live before it. */
function mykis_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'mykis_consent_at');
}

/**
 * The coordinators, state by state, as the administration lists them —
 * by the state's NAME: the codes would put Saarland after Sachsen-Anhalt.
 */
function mykis_coordinators(PDO $pdo): array
{
    $rows = $pdo->query('SELECT * FROM map_mykis_coordinators')->fetchAll();
    usort($rows, static fn(array $a, array $b): int =>
        strcmp((MYKIS_STATES[$a['region']] ?? $a['region']) . ' ' . $a['part'],
               (MYKIS_STATES[$b['region']] ?? $b['region']) . ' ' . $b['part']));
    return $rows;
}

/**
 * WHERE A SPLIT STATE IS CUT: the first MTB row of its southern part.
 *
 * BADEN-WÜRTTEMBERG IS CUT BETWEEN ROWS 72 AND 73 (27.11.2026, from the
 * northern coordinator: she takes the sheets 62xx to 72xx, the south
 * takes 73xx and below). The row is the sheet number's first two digits
 * and runs from north to south, so one number says it — around
 * Stuttgart/Böblingen. A cut by the sheet and not by a drawn line,
 * because the coordinators share their work by sheets: a find on a
 * sheet that straddles the line still belongs to ONE of them.
 */
const MYKIS_SPLIT_ROWS = ['BAW' => 73];

/** The part of a split state a sheet lies in — 'Nord', 'Süd' or null without a usable sheet. */
function mykis_split_part(string $region, ?string $sheet): ?string
{
    $line = MYKIS_SPLIT_ROWS[$region] ?? null;
    $sheet = trim((string) $sheet);
    if ($line === null || !preg_match('/^\d{3,4}$/', $sheet)) {
        return null;
    }
    return intdiv((int) $sheet, 100) >= $line ? 'Süd' : 'Nord';
}

/**
 * The coordinator a find goes to — by the state it lies in, and in a
 * split state by its MTB sheet (MYKIS_SPLIT_ROWS).
 *
 * A SPLIT STATE WITHOUT ITS LINE STILL WAITS, and so does a find there
 * without a sheet: a guess would send it to the wrong one, and „each find
 * once" leaves no second chance.
 */
function mykis_coordinator_for(array $coordinators, array $point): ?array
{
    $region = strtoupper(trim((string) ($point['region'] ?? '')));
    $rows = array_values(array_filter($coordinators,
        static fn(array $c): bool => $c['region'] === $region));
    if (count($rows) === 1 && ($rows[0]['part'] ?? null) === null) {
        return $rows[0];
    }
    $part = mykis_split_part($region, isset($point['mtb']) ? (string) $point['mtb'] : null);
    if ($part === null) {
        return null;
    }
    $rows = array_values(array_filter($rows, static fn(array $c): bool => ($c['part'] ?? null) === $part));
    return count($rows) === 1 ? $rows[0] : null;
}

/**
 * The moment before which a find must have come in to go out now — or null
 * when nothing is due.
 *
 * TWICE A YEAR, EARLY IN JUNE AND EARLY IN DECEMBER
 * (25.09.2026). The cron job may run every day: in June and December it
 * delivers what was there on the 1st, and for the rest of the month it
 * finds nothing left — a find added on the 15th waits for the next
 * half-year instead of trickling after in a mail of its own. A run cut
 * short by its time budget simply goes on the next day.
 */
function mykis_cutoff(DateTimeImmutable $now): ?string
{
    return in_array((int) $now->format('n'), [6, 12], true) ? $now->format('Y-m-01 00:00:00') : null;
}

/**
 * When the next delivery goes out, in the words the settings use — German,
 * for t(). In June the month's run has taken what was there on the 1st, so
 * whoever agrees in June waits for December; in December likewise for June.
 */
function mykis_next_delivery(DateTimeImmutable $now): string
{
    $month = (int) $now->format('n');
    return $month >= 6 && $month <= 11 ? 'Anfang Dezember' : 'Anfang Juni';
}

/**
 * THE QUEUE IS THE BOOKKEEPING ITSELF (25.09.2026, thinking of
 * fifteen users and more, mapping across state borders: „da müsste man eine
 * Mail-Queue einrichten … und die Warteschlange über Jobs nach und nach
 * abarbeiten"). Every find not yet in map_mykis_deliveries waits for its
 * coordinator; every delivered one without confirmed_at waits for its
 * finder's confirmation. Each call sends what fits into its time and these
 * limits, books what left, and the next call goes on from there. A second
 * table for the queue could only contradict the first.
 *
 * GENTLE WITH THE HOST („dass es bei meinem Provider nicht zu
 * Alarmen kommt von wegen Spam-Versendung"). At most twenty mails a call,
 * a breath between two of them, every mail marked as automatic
 * (mail_send). The coordinators get one mail per address and half-year —
 * sixteen at the very most, whatever the number of users; the only mass
 * is the finders' confirmations, and the cap spreads them over the calls.
 */
const MYKIS_MAILS_PER_CALL = 20;

/** The breath between two mails of one call, in milliseconds. */
const MYKIS_PAUSE_MS = 1500;

/**
 * Attachments per mail before a coordinator's mail is split into parts —
 * five megabytes of sheets, some twenty-four thousand finds (215 bytes a
 * find, measured 25.09.2026). Mail servers take ten to twenty-five; the
 * margin covers the growth in transit (base64, a third).
 */
const MYKIS_MAIL_MAX_BYTES = 5 * 1024 * 1024;

/**
 * Sheets per mail — fifteen at most. Since the sheets gather many finders
 * (MYKIS_FINDS_PER_FILE) this is a backstop the size limit above reaches
 * long before; it stays so a mail never turns into a pile of attachments.
 */
const MYKIS_FILES_PER_MAIL = 15;

/**
 * ONE SHEET FOR MANY FINDERS (26.11.2026, replacing the sheet per finder
 * of 25.09.2026: „dieses Gestückel pro Benutzer … gefällt mir eigentlich
 * nicht mehr … wenn es mal mehrere werden, bekommt Harald trotzdem
 * mehrere Mails und hat ziemlich viel Arbeit. Die einzelnen Listen
 * einzulesen. Ich würde eher eine große Datei machen"). A coordinator gets
 * one sheet with every finder's finds; the erfasser column names each
 * row's finder, and the mail still lists every finder with address, count
 * and precision for the questions.
 *
 * SPLIT ONLY WHERE IT GETS LARGE, AND NEVER THROUGH A FINDER („in CSV1
 * sind die Personen von bis, dass man so ungefähr eine etwas gleich große
 * Paketgröße zusammenbekommt, aber nicht Nutzer auf 2 Pakete aufteilt.
 * Also wenn dann das eine Paket vielleicht doch etwas größer macht"):
 * above this many finds the finders, in the order of their names, are cut
 * into as many sheets as the count asks for, each about the same size,
 * each finder whole in one — a sheet grows past the mark rather than
 * break a finder in two. Five thousand finds are about a megabyte (215
 * bytes a find, measured 25.09.2026): four sheets a mail before the size
 * limit splits the mail, and a size every spreadsheet opens.
 */
const MYKIS_FINDS_PER_FILE = 5000;

/** Migration 0128 — the finders' confirmations. */
function mykis_confirm_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_mykis_deliveries', 'confirmed_at');
}

/** Migration 0131 — the finder's own tick for a copy. */
function mykis_copy_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'mykis_copy');
}
function mykis_foreign_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'mykis_foreign');
}

/**
 * Whether a finder gets a copy of what went (25.09.2026: only
 * „wenn man eine Kopie möchte"). Without the column nobody ticked it.
 */
function mykis_wants_copy(array $finder): bool
{
    return (int) ($finder['mykis_copy'] ?? 0) === 1;
}

/** The finders who agreed — with a confirmed address, not locked. */
function mykis_finders(PDO $pdo): array
{
    $lang = (column_exists($pdo, 'map_users', 'lang') ? ', lang' : '')
          . (column_exists($pdo, 'map_users', 'mykis_precision') ? ', mykis_precision' : '')
          . (mykis_copy_ready($pdo) ? ', mykis_copy' : '')
          . (mykis_foreign_ready($pdo) ? ', mykis_foreign' : '')
          . (mykis_determined_ready($pdo) ? ', mykis_determined' : '')
          . (mykis_rules_ready($pdo) ? ', mykis_rules' : '');
    return $pdo->query(
        "SELECT id, email, first_name, last_name$lang FROM map_users
          WHERE mykis_consent_at IS NOT NULL AND email_confirmed_at IS NOT NULL AND locked_at IS NULL
          ORDER BY last_name, first_name, id")->fetchAll();
}

/** The ids of every find that went out already. */
function mykis_delivered_ids(PDO $pdo): array
{
    return array_flip(array_map('intval',
        $pdo->query('SELECT point_id FROM map_mykis_deliveries')->fetchAll(PDO::FETCH_COLUMN)));
}

/**
 * One finder's own finds, as the sheet reads them.
 *
 * LEAN ON PURPOSE: the export's loader (export_points) gathers shared,
 * co-owned and excursion finds and asks the cloud once per find — none of
 * which the delivery needs, since only own finds go and the count of
 * recordings comes along with points_load().
 */
function mykis_points(PDO $pdo, int $userId): array
{
    require_once __DIR__ . '/data.php';
    $points = points_load($pdo, $userId);
    $references = [];
    if (column_exists($pdo, 'map_points', 'extra_species')) {
        $stmt = $pdo->prepare('SELECT id, extra_species FROM map_points WHERE user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId]);
        foreach ($stmt as $row) {
            $references[(int) $row['id']] = $row['extra_species'];
        }
    }
    foreach ($points as &$p) {
        $p['extra_species'] = $references[(int) $p['id']] ?? null;
    }
    unset($p);
    return $points;
}

/**
 * HOW MANY FINDS STAY OUT FOR THEIR NAME ALONE (05.11.2026,
 * with the working names): finds that would go — day, sheet, certainty,
 * whose — but whose title no DGfM list knows: a working name, a species
 * the lists do not carry yet, a name typed past the suggestions. They used
 * to stay out without a word; the DGfM tab now says how many.
 *
 * Asked of mykis_eligible() itself with the reference filled in, so the
 * count can never drift from the rule it counts the exceptions of.
 */
function mykis_unlisted(array $points, ?array $self): int
{
    $n = 0;
    foreach ($points as $p) {
        if (mykis_taxref($p['extra_species'] ?? null)) {
            continue;
        }
        $listed = $p;
        $listed['extra_species'] = ['' => ['' => 'fungi:0']];
        if (mykis_eligible($listed, $self)) {
            $n++;
        }
    }
    return $n;
}

/**
 * What waits for the coordinators — per coordinator address, per finder.
 *
 * It reads every consenting finder's stock once; fine for dozens of
 * finders twice a year, and the first thing to split up when there are
 * hundreds.
 *
 * @param string|null $cutoff  only finds created before it; null takes
 *        everything that waits (the button in the administration)
 * @return array[] each ['email' => address, 'coordinators' => rows sharing
 *         it, 'finders' => [['finder' => row, 'points' => [[point,
 *         coordinator id], …]], …]] — in the list's order of states
 */
function mykis_pending(PDO $pdo, ?string $cutoff): array
{
    $coordinators = mykis_coordinators($pdo);
    $delivered = mykis_delivered_ids($pdo);
    $pending = [];
    foreach (mykis_finders($pdo) as $finder) {
        $self  = mykis_self($finder);
        $rules = mykis_rules_read($finder['mykis_rules'] ?? null);
        foreach (mykis_points($pdo, (int) $finder['id']) as $p) {
            if (isset($delivered[(int) $p['id']]) || !mykis_eligible($p, $self, $rules)
                || ($cutoff !== null && (string) ($p['created_at'] ?? '') >= $cutoff)) {
                continue;
            }
            $coordinator = mykis_coordinator_for($coordinators, $p);
            if ($coordinator === null || $coordinator['consented_at'] === null) {
                continue;
            }
            $key = strtolower((string) $coordinator['email']);
            $pending[$key] ??= [
                'email'        => (string) $coordinator['email'],
                'coordinators' => array_values(array_filter($coordinators,
                    static fn(array $c): bool => strcasecmp((string) $c['email'], (string) $coordinator['email']) === 0)),
                'finders'      => [],
            ];
            $pending[$key]['finders'][(int) $finder['id']] ??= ['finder' => $finder, 'points' => []];
            $pending[$key]['finders'][(int) $finder['id']]['points'][] = [$p, (int) $coordinator['id']];
        }
    }
    foreach ($pending as &$address) {
        $address['finders'] = array_values($address['finders']);
    }
    unset($address);
    return array_values($pending);
}

/**
 * Send what waits — the one loop behind the administration's button and
 * the cron job (cronjobs/daily.php).
 *
 * FIRST THE COORDINATORS, THEN THE FINDERS, AND NEVER BOTH IN ONE CALL:
 * the copies start only in a call that found no coordinator mail left to
 * send, so there is always at least one call's interval between the
 * sheets and the word that they went („ein bisschen
 * zeitversetzt").
 *
 * A COPY ONLY FOR WHOEVER TICKED IT (0131). A finder without the tick has
 * their delivery booked as settled right away — nothing waits for a mail
 * that will never go, and the administration counts only real copies.
 *
 * @return array{mails: int, finds: int, confirmations: int, failed: int, open: int}
 */
function mykis_deliver(PDO $pdo, ?string $cutoff, float $deadline): array
{
    require_once __DIR__ . '/mail.php';
    place_names_source($pdo, true);
    $done = ['mails' => 0, 'finds' => 0, 'confirmations' => 0, 'failed' => 0, 'open' => 0];
    $sent = 0;
    // At least one mail per call, whatever the clock says — a call that
    // sends nothing because it started late never finishes.
    $full = static function () use (&$sent, $deadline): bool {
        return $sent > 0 && (microtime(true) >= $deadline || $sent >= MYKIS_MAILS_PER_CALL);
    };
    $breathe = static function () use (&$sent): void {
        if ($sent > 0) {
            usleep(MYKIS_PAUSE_MS * 1000);
        }
    };
    $confirmReady = mykis_confirm_ready($pdo);
    $record = $pdo->prepare($confirmReady
        ? 'INSERT IGNORE INTO map_mykis_deliveries (point_id, coordinator_id, delivered_at, confirmed_at)
           VALUES (?, ?, NOW(), IF(? = 1, NULL, NOW()))'
        : 'INSERT IGNORE INTO map_mykis_deliveries (point_id, coordinator_id, delivered_at) VALUES (?, ?, NOW())');
    $operator = mykis_operator($pdo);
    $day = date('Y-m-d');

    $pending = mykis_pending($pdo, $cutoff);
    foreach ($pending as $address) {
        // A coordinator's parts belong together — they go in one call, or
        // the numbering „Teil 1 von 2" would start over in the next.
        if ($full()) {
            $done['open']++;
            continue;
        }
        // Every finder of this address in shared sheets, named after the states it looks after.
        $label = implode(' ', array_unique(array_map(static fn(array $c): string =>
            MYKIS_STATES[$c['region']] ?? (string) $c['region'], $address['coordinators'])));
        $files = mykis_packages(array_map(static fn(array $entry): array =>
            mykis_finder_entry($entry['finder'], $entry['points']), $address['finders']), $label, $day);
        $parts = mykis_parts($files, MYKIS_MAIL_MAX_BYTES, MYKIS_FILES_PER_MAIL);
        foreach ($parts as $i => $part) {
            $breathe();
            // The states THIS mail carries finds from — an address may look
            // after two, and one of them may have nothing to send (a split
            // state waits); naming it would announce finds that are not there.
            $used = [];
            foreach ($part as $file) {
                foreach ($file['finders'] as $entry) {
                    foreach ($entry['points'] as [, $coordinatorId]) {
                        $used[$coordinatorId] = true;
                    }
                }
            }
            $shown = array_values(array_filter($address['coordinators'],
                static fn(array $c): bool => isset($used[(int) $c['id']]))) ?: $address['coordinators'];
            $mail = mykis_coordinator_mail($shown, $part, $i + 1, count($parts), $operator);
            $ok = mail_send($address['email'], $mail['subject'], $mail['body'], [
                'reply_to'    => $operator['email'] ?? '',
                'attachments' => array_map(static fn(array $f): array =>
                    ['name' => $f['name'], 'type' => $f['type'], 'content' => $f['content']], $part),
            ]);
            if (!$ok) {
                // Not booked, so it waits for the next call — a mail that did
                // not leave must not count as delivered.
                $done['failed']++;
                break;
            }
            $sent++;
            $done['mails']++;
            foreach ($part as $file) {
                foreach ($file['finders'] as $entry) {
                    $copy = mykis_wants_copy($entry['finder']) ? 1 : 0;
                    foreach ($entry['points'] as [$p, $coordinatorId]) {
                        $record->execute($confirmReady
                            ? [(int) $p['id'], $coordinatorId, $copy]
                            : [(int) $p['id'], $coordinatorId]);
                    }
                    $done['finds'] += count($entry['points']);
                }
            }
        }
    }

    if ($pending !== [] || !$confirmReady) {
        return $done;
    }

    /* THE COPIES: one mail per finder who asked for it, what went where,
       with the very sheets the coordinators received — written in the
       finder's own language, the one mail here that goes to a user. */
    $coordinators = array_column(mykis_coordinators($pdo), null, 'id');
    $waiting = $pdo->query('SELECT d.point_id, d.coordinator_id, p.user_id
                              FROM map_mykis_deliveries d JOIN map_points p ON p.id = d.point_id
                             WHERE d.confirmed_at IS NULL')->fetchAll();
    $byFinder = [];
    foreach ($waiting as $row) {
        $byFinder[(int) $row['user_id']][(int) $row['coordinator_id']][] = (int) $row['point_id'];
    }
    $confirm = $pdo->prepare('UPDATE map_mykis_deliveries SET confirmed_at = NOW() WHERE point_id = ?');
    $finders = array_column($pdo->query(
        'SELECT id, email, first_name, last_name' . (column_exists($pdo, 'map_users', 'lang') ? ', lang' : '')
        . (column_exists($pdo, 'map_users', 'mykis_precision') ? ', mykis_precision' : '')
        . (mykis_copy_ready($pdo) ? ', mykis_copy' : '')
        . (mykis_rules_ready($pdo) ? ', mykis_rules' : '')
        . ' FROM map_users')->fetchAll(), null, 'id');

    foreach ($byFinder as $userId => $perCoordinator) {
        if ($full()) {
            $done['open']++;
            continue;
        }
        $finder = $finders[$userId] ?? null;
        if ($finder === null) {
            continue;
        }
        if (!mykis_wants_copy($finder)) {
            // Booked while the tick was on, and taken off since — settled
            // without a mail, as if it had never been on.
            foreach ($perCoordinator as $ids) {
                foreach ($ids as $id) {
                    $confirm->execute([$id]);
                }
            }
            continue;
        }
        $points = array_column(mykis_points($pdo, $userId), null, 'id');
        $holder = mykis_holder($finder);
        $files = [];
        $lines = [];
        foreach ($perCoordinator as $coordinatorId => $ids) {
            $coordinator = $coordinators[$coordinatorId] ?? null;
            $rows = [];
            foreach ($ids as $id) {
                if (isset($points[$id])) {
                    // At today's precision — a finder who changed it between
                    // the sheet and this mail sees the new step; rare, and
                    // the coordinator's copy is the one that counts.
                    $rows[] = mykis_row($points[$id], $holder, mykis_step($points[$id],
                        mykis_rules_read($finder['mykis_rules'] ?? null), mykis_precision($finder['mykis_precision'] ?? null)));
                }
            }
            $state = $coordinator === null ? '' : (MYKIS_STATES[$coordinator['region']] ?? $coordinator['region']);
            $lines[] = ['state' => $state, 'name' => $coordinator['name'] ?? '', 'email' => $coordinator['email'] ?? '',
                        'count' => count($ids)];
            if ($rows !== []) {
                $files[] = ['name' => mykis_file_name($state, $day), 'type' => 'text/csv; charset=utf-8',
                            'content' => mykis_csv($rows)];
            }
        }
        $breathe();
        $before = lang_current();
        lang_force(isset($finder['lang'], LANGUAGES[$finder['lang']]) ? (string) $finder['lang'] : 'de');
        $mail = mykis_confirmation_mail($finder, $lines);
        lang_force($before);
        if (!mail_send((string) $finder['email'], $mail['subject'], $mail['body'], ['attachments' => $files])) {
            $done['failed']++;
            continue;
        }
        $sent++;
        foreach ($perCoordinator as $ids) {
            foreach ($ids as $id) {
                $confirm->execute([$id]);
            }
        }
        $done['confirmations']++;
    }
    return $done;
}

/** A finder as the sheet names people: „Nachname, Vorname". */
function mykis_holder(array $finder): string
{
    return trim(trim((string) $finder['last_name']) . ', ' . trim((string) $finder['first_name']), ', ');
}

/**
 * Who answers for the delivery itself — the first administrator. A reply
 * to the coordinators' mail goes there: a question about the sending, or
 * „please stop", must not bounce off a noreply address.
 */
function mykis_operator(PDO $pdo): ?array
{
    $row = $pdo->query("SELECT email, first_name, last_name FROM map_users
                         WHERE role = 'admin' AND locked_at IS NULL ORDER BY id LIMIT 1")->fetch();
    return $row ?: null;
}

/**
 * A file name that survives every mail program: letters, digits, dashes,
 * umlauts written out — „Funde-Hessen-2026-12-01.csv", and with a number
 * where the finds are cut into several sheets: „…-2026-12-01-Datei-2.csv".
 */
function mykis_file_name(string $label, string $day, ?int $number = null): string
{
    $label = strtr(trim($label), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
    $label = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $label), '-') ?: 'Funde';
    return 'Funde-' . $label . '-' . $day . ($number === null ? '' : '-Datei-' . $number) . '.csv';
}

/**
 * One finder's part of a coordinator's sheet: the rows, each find at its
 * own step (the finder's rules, mykis_step), and what the mail says about
 * the finder.
 *
 * @param array[] $points  each [point, coordinator id]
 */
function mykis_finder_entry(array $finder, array $points): array
{
    $holder    = mykis_holder($finder);
    $precision = mykis_precision($finder['mykis_precision'] ?? null);
    $rules     = mykis_rules_read($finder['mykis_rules'] ?? null);
    return [
        'finder'    => $finder,
        'points'    => $points,
        'precision' => $precision,
        'rules'     => $rules,
        'rows'      => array_map(static fn(array $pair): array =>
            mykis_row($pair[0], $holder, mykis_step($pair[0], $rules, $precision)), $points),
    ];
}

/**
 * Which finders go into which sheet — MYKIS_FINDS_PER_FILE says why. Given
 * the finders' find counts in their order, the groups as lists of their
 * indexes: as many as the total asks for, contiguous (a sheet holds the
 * names „von … bis …"), a finder never split, and cut so the LARGEST sheet
 * is as small as it can be — the sheets come out about the same size, and
 * one grows past the mark only where whole finders leave no other way.
 * A greedy cut was tried first and left the last sheet half again as large
 * as the others; this is the exact answer, by dynamic programming — a few
 * hundred finders and a handful of sheets cost nothing.
 *
 * @param int[] $counts
 * @return int[][]
 */
function mykis_package_groups(array $counts, int $maxFinds = MYKIS_FINDS_PER_FILE): array
{
    $counts = array_values($counts);
    $n = count($counts);
    if ($n === 0) {
        return [];
    }
    $k = min($n, max(1, (int) ceil(array_sum($counts) / max(1, $maxFinds))));
    $prefix = [0];
    foreach ($counts as $i => $c) {
        $prefix[$i + 1] = $prefix[$i] + $c;
    }
    // $best[$j][$i]: the smallest possible largest sheet for the first $i finders in $j sheets.
    $best = [0 => [0 => 0]];
    $cut = [];
    for ($j = 1; $j <= $k; $j++) {
        for ($i = $j; $i <= $n; $i++) {
            $best[$j][$i] = PHP_INT_MAX;
            for ($m = $j - 1; $m < $i; $m++) {
                if (!isset($best[$j - 1][$m])) {
                    continue;
                }
                $largest = max($best[$j - 1][$m], $prefix[$i] - $prefix[$m]);
                if ($largest < $best[$j][$i]) {
                    $best[$j][$i] = $largest;
                    $cut[$j][$i] = $m;
                }
            }
        }
    }
    /* No more sheets than that largest one needs: one finder above the
       mark sets the size by himself, and the rest then fit fewer sheets
       (a finder of 10 and two of 1, at a mark of 5: two sheets, not three). */
    $sheets = $k;
    while ($sheets > 1 && isset($best[$sheets - 1][$n]) && $best[$sheets - 1][$n] <= $best[$k][$n]) {
        $sheets--;
    }
    $groups = [];
    for ($j = $sheets, $i = $n; $j > 0; $j--) {
        $m = $cut[$j][$i];
        array_unshift($groups, range($m, $i - 1));
        $i = $m;
    }
    return $groups;
}

/**
 * The finders' entries (mykis_finder_entry) as the sheets a coordinator
 * gets: in the order of the finders' names — „Personen von bis" —, cut
 * by mykis_package_groups, each sheet one CSV under its number.
 *
 * @return array[] each with 'name', 'type', 'content', 'finders' (the
 *                 entries in it), 'number' and 'of'
 */
function mykis_packages(array $entries, string $label, string $day, int $maxFinds = MYKIS_FINDS_PER_FILE): array
{
    $key = static fn(array $e): string => mb_strtolower(mykis_holder($e['finder']));
    usort($entries, static fn(array $a, array $b): int => strcmp($key($a), $key($b)));
    $groups = mykis_package_groups(array_map(static fn(array $e): int => count($e['points']), $entries), $maxFinds);
    $files = [];
    foreach ($groups as $n => $indexes) {
        $finders = array_map(static fn(int $i): array => $entries[$i], $indexes);
        $files[] = [
            'name'    => mykis_file_name($label, $day, count($groups) > 1 ? $n + 1 : null),
            'type'    => 'text/csv; charset=utf-8',
            'content' => mykis_csv(array_merge(...array_map(static fn(array $e): array => $e['rows'], $finders))),
            'finders' => $finders,
            'number'  => $n + 1,
            'of'      => count($groups),
        ];
    }
    return $files;
}

/**
 * The sheets split into mails — by count and by size, at a sheet's
 * border, never through one. A single sheet larger than the size limit
 * goes alone.
 *
 * @param array[] $files  each with 'content'
 * @return array[][] the files per mail
 */
function mykis_parts(array $files, int $maxBytes, int $maxFiles = PHP_INT_MAX): array
{
    $parts = [];
    $current = [];
    $size = 0;
    foreach ($files as $file) {
        $length = strlen((string) $file['content']);
        if ($current !== [] && ($size + $length > $maxBytes || count($current) >= $maxFiles)) {
            $parts[] = $current;
            $current = [];
            $size = 0;
        }
        $current[] = $file;
        $size += $length;
    }
    if ($current !== []) {
        $parts[] = $current;
    }
    return $parts;
}

/**
 * The mail to a coordinator — German always: it goes to the DGfM, not to a
 * user. THE SALUTATION NAMES THE PERSON, not a „Herr" or „Frau" — the list
 * gives names, not how their bearers wish to be addressed.
 *
 * @param array[] $coordinators  the rows sharing this address
 * @param array[] $files         this part's sheets (mykis_packages), each
 *                               with 'name', 'number', 'of' and 'finders'
 */
function mykis_coordinator_mail(array $coordinators, array $files, int $part, int $parts, ?array $operator): array
{
    $names  = implode(' und ', array_unique(array_map(
        static fn(array $c): string => (string) $c['name'], $coordinators)));
    $states = implode(', ', array_unique(array_map(static fn(array $c): string =>
        (MYKIS_STATES[$c['region']] ?? $c['region']) . ($c['part'] ? ' (' . $c['part'] . ')' : ''),
        $coordinators)));
    $entries = array_merge(...array_map(static fn(array $f): array => $f['finders'], $files));
    $finds   = array_sum(array_map(static fn(array $e): int => count($e['points']), $entries));
    $sheets  = (int) ($files[0]['of'] ?? count($files));
    $count   = static fn(int $n): string => $n === 1 ? 'ein Fund' : number_format($n, 0, ',', '.') . ' Funde';
    /* EVERY FINDER BY NAME, ADDRESS, COUNT AND PRECISION — the sheet no
       longer says who is who by its name, so the list does; „Nachname,
       Vorname" as the erfasser column writes them, so a row and its line
       here are found by the same words. Several sheets: each under its
       file name with its range „von … bis …". */
    $finderLine = static fn(array $e): string => '  ' . mykis_holder($e['finder'])
        . ' <' . $e['finder']['email'] . '> — ' . $count(count($e['points']))
        . ' — ' . MYKIS_PRECISIONS[mykis_precision($e['precision'] ?? null)]['short']
        // The finder's own rules make single groups coarser or finer —
        // the sheet's rows show each find's step, the line says so.
        . (mykis_rules_any($e['rules'] ?? []) ? ', mit eigenen Regeln für einzelne Arten und Gruppen' : '')
        . "\n";
    $list = '';
    foreach ($files as $f) {
        if ($sheets > 1) {
            $first = mykis_holder($f['finders'][0]['finder']);
            $last  = mykis_holder($f['finders'][count($f['finders']) - 1]['finder']);
            $list .= ($list === '' ? '' : "\n") . $f['name'] . ' — '
                . ($first === $last ? $first : "$first bis $last") . ', '
                . $count(array_sum(array_map(static fn(array $e): int => count($e['points']), $f['finders']))) . ":\n";
        }
        foreach ($f['finders'] as $e) {
            $list .= $finderLine($e);
        }
    }
    $sheetWords = $sheets > 1
        ? "in $sheets Tabellen mit den Spalten der MykIS-Importvorlage (mykdaten.xls), zum Öffnen in "
          . "jedem Tabellenprogramm. Damit keine Tabelle zu groß wird, sind die Finder auf die Tabellen "
          . "verteilt, jeder ganz in einer"
        : 'in einer Tabelle mit den Spalten der MykIS-Importvorlage (mykdaten.xls), zum Öffnen in '
          . 'jedem Tabellenprogramm' . ($files !== [] ? ' (' . $files[0]['name'] . ')' : '');
    // The operator's address stands in the sentence too („hier
    // kannst du meine Adresse direkt eintragen") — a reply reaches them
    // through Reply-To anyway, but a forwarded mail loses that header.
    $who  = trim((string) ($operator['first_name'] ?? '') . ' ' . (string) ($operator['last_name'] ?? ''));
    $at   = trim((string) ($operator['email'] ?? ''));
    $whom = $who === '' ? $at : ($at === '' ? $who : "$who ($at)");
    /* A PART SAYS WHERE IT STANDS: the last one has nothing left to
       announce („es gibt keine übrigen Tabellen beim letzten
       Teil"). */
    $partLine = $parts <= 1 ? '' : ($part < $parts
        ? "Dies ist Teil $part von $parts; die übrigen Tabellen folgen in "
          . ($parts - $part === 1 ? 'einer weiteren Mail' : 'weiteren Mails') . ".\n\n"
        : "Dies ist Teil $part von $parts, der letzte; die übrigen Tabellen kamen in "
          . ($parts === 2 ? 'der vorigen Mail' : 'den vorigen Mails') . ".\n\n");

    return [
        'subject' => 'Pilzfunde für MykIS — ' . $states . ' (' . count($entries) . ' Finder, ' . $count($finds) . ')'
                   . ($parts > 1 ? " — Teil $part von $parts" : ''),
        'body'    => "Guten Tag $names,\n\n"
            // Where the data comes from, said once and first — the erfasser
            // column then needs no explaining (it names the finder, who
            // recorded the find in the app).
            . "anbei die Pilzfunde aus Ihrem Zuständigkeitsgebiet ($states), erfasst mit der App "
            . "YASAIMAPP — $sheetWords. In der Spalte „erfasser“ steht bei jedem Fund sein Finder.\n\n"
            . ($sheets > 1 ? '' : "Gemeldet haben:\n\n")
            . $list . "\n"
            . $partLine
            // How an unmarked find is read stays in-house: the
            // coordination hears the rule, not how we apply it.
            . "Mitgeschickt werden nur Funde, deren Name in der Referenzliste der DGfM steht und "
            . "die sicher bestimmt sind — makroskopisch, mikroskopisch oder genetisch. Jeder Fund "
            . "kommt nur einmal.\n\n"
            . "Wie genau die Funde abgegeben werden, entscheidet jeder Finder selbst — mindestens "
            . "auf den Viertelquadranten des Messtischblatts genau; die Liste oben nennt es. "
            . "Genaue Koordinaten stehen in Grad, Minuten und Sekunden, dazu das "
            /* NO WORD ABOUT WHAT THE COORDINATION MAY DO WITH THEM
               (26.11.2026: „Das ist ja peinlich. Das kann ich einem
               Landeskoordinator so nicht schreiben") — they know how the
               DGfM treats exact places better than any mail could say. */
            . "Messtischblatt bis zum 64stel-Quadranten.\n\n"
            // OpenStreetMap's licence (ODbL) asks for its source by name.
            . (place_names_source() === null ? '' : "Bei Findern, die den 64stel-Quadranten oder genaue Koordinaten "
                . "abgeben, steht in „BASIS_ortslage“ der nächste Flurname, Weiler oder Wald im Umkreis von "
                . "einem Kilometer, nach OpenStreetMap ("
                . PLACE_NAME_CREDIT . ").\n\n")
            . "Fragen zu einem Fund richten Sie bitte direkt an den Finder, unter der Adresse oben. "
            . ($whom !== '' ? "Eine Antwort auf diese Mail geht an $whom, zuständig für den Versand.\n\n" : "\n")
            . "Neue Funde kommen zweimal im Jahr, Anfang Juni und Anfang Dezember, weil Sie "
            . "dem automatischen Versand zugestimmt haben.\n\n"
            . "Viele Grüße\nYASAIMAPP\n",
    ];
}

/**
 * The finder's confirmation — in the finder's language (the caller sets
 * it), with what went where.
 *
 * @param array[] $lines  each ['state', 'name', 'email', 'count']
 */
function mykis_confirmation_mail(array $finder, array $lines): array
{
    $list = '';
    foreach ($lines as $l) {
        $list .= '  ' . ($l['count'] === 1
            ? t('%s (%s, %s): ein Fund', $l['state'], $l['name'], $l['email'])
            : t('%s (%s, %s): %d Funde', $l['state'], $l['name'], $l['email'], $l['count'])) . "\n";
    }
    return [
        'subject' => t('Deine Pilzfunde sind an die DGfM-Kartierung gegangen'),
        'body'    => t('Hallo %s,', trim((string) $finder['first_name'])) . "\n\n"
            . t('wie vereinbart sind deine Pilzfunde an die Landeskoordination der DGfM gegangen:') . "\n\n"
            . $list . "\n"
            . t('Die Tabellen hängen an — genau das, was die Landeskoordination bekommen hat. Jeder Fund geht nur einmal; spätere Änderungen gehen nicht nach. Rückfragen kommen direkt an deine Adresse.') . "\n\n"
            . t('Die Kopie oder die Meldung selbst stellst du jederzeit in der Verwaltung unter Einstellungen → DGfM-Kartierung ab.') . "\n\n"
            . t('Viele Grüße') . "\nYASAIMAPP\n",
    ];
}
