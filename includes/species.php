<?php
/**
 * Species search for the title suggestions.
 *
 * HOW SEARCHING WORKS
 * You type fragments, not names. "bol edu" is supposed to find Boletus edulis,
 * "bol stein" likewise — there the first fragment hits the scientific name,
 * the second the German one. Both mixed, in any order, each fragment as a
 * word prefix.
 *
 * MySQL's full-text search in BOOLEAN MODE delivers that: "bol edu" becomes
 * "+bol* +edu*" — both fragments must occur as word prefixes, anywhere.
 *
 * HOW SORTING WORKS, and why that is not a side issue
 * "tricho" hits 426 names — Tricholoma among them, but also Bryostroma
 * trichostomi, where the fragment sits in the middle of the epithet of an
 * entirely different genus. Whoever types a genus means the genus. So on top
 * comes what STARTS with the first fragment, then the direct hit before the
 * one via a synonym, the alphabet last — and no full-text score anywhere in
 * between; species_search() says why.
 *
 * THE GERMAN NAME COUNTS WITH EQUAL WEIGHT here — not because it has become
 * more important, but because there are groups in which one searches almost
 * only by it: among the plants 95 % of the species carry one, among the
 * lichens two out of 157. Without this rule, "steinpilz" would put the
 * Schwarzer and the Birken-Steinpilz before the Steinpilz, because only the
 * scientific name was checked and none of them starts with "stein".
 *
 * WHICH NAMES GET SUGGESTED
 * First the reference lists for Germany — fungi, lichens, plants —, because
 * they carry the species concept that governs here, and the classification.
 * Whoever types a synonym still gets the currently valid name suggested —
 * with a note about what it was found through. If the list stays short, GBIF
 * is added, which carries everything not recorded for Germany.
 *
 * "List for Germany" means: the species that occur here. NOT: a list of
 * German names. Those appear where they exist, and you can search by them —
 * but they are an accessory, not the purpose.
 *
 * PAGE BY PAGE, not cut off. Fifty at a time, the rest comes as you keep
 * scrolling. A fixed limit would be a silent lie at "cortinarius" — 1,388
 * hits: you would see fifty and not know that there is more.
 *
 * DISPLAYED IS THE SCIENTIFIC NAME, the German one small below it. That is
 * the order in which a find record must read: the scientific name is
 * unambiguous, the German one is an accessory — and for most species it does
 * not even exist.
 */

declare(strict_types=1);

/** From this length on a fragment is considered (full-text index). */
const SPECIES_MIN_CHARS = 3;

/** How many suggestions per page. The rest comes as you keep scrolling. */
const SPECIES_PER_PAGE = 50;

/**
 * Translate the search input into a BOOLEAN MODE expression.
 *
 * Everything that is not a letter or a digit is dropped — including the
 * special characters of the full-text mode itself (+, -, *, ", ...).
 * Otherwise an input like "a-b" could be read as "exclude b", and the user
 * would get inexplicable results.
 *
 * @return array{0:string,1:string,2:string} Expression, first fragment and the
 *         whole input as one piece; all empty if nothing remains
 */
function species_expression(string $input): array
{
    $fragments = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($input)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $parts = [];
    foreach ($fragments as $s) {
        if (mb_strlen($s) >= SPECIES_MIN_CHARS) {
            $parts[] = '+' . $s . '*';
        }
    }

    if ($parts === []) {
        return ['', '', ''];
    }

    // Third, the whole input as one piece, with single spaces between the
    // words. That makes it possible to ask whether a name starts with the
    // BEGINNING OF THE WHOLE INPUT and not just with its first word — the
    // difference between "Boletus edulis" and the 127 other Boletus.
    //
    // FROM $fragments, NOT FROM $parts: here the short fragments count too.
    // The full-text index discards everything under three characters — for
    // "Boletus ed" that means the "ed", and that is exactly where the sorting
    // failed, although the decisive part had been typed. A LIKE needs no
    // index and can work with it.
    return [implode(' ', $parts), trim($parts[0], '+*'), implode(' ', $fragments)];
}

/*
 * THE SENSU QUALIFIER OF THE DGfM LIST (13.09.2026: „Xerocomellus
 * chrysenteron wird als Rotfußröhrling-Aggregat übersetzt — die Taxref-Liste
 * hat einen Eintrag mit sensu stricto. Das müssten wir dem Artenfeld
 * beibringen, dass man das hinter einem Artnamen schreiben kann").
 *
 * The list carries 143 binomials TWICE as valid names: once as the
 * aggregate — „Xerocomellus chrysenteron agg.", German „Rotfußröhrling-
 * Aggregat" — and once as the species in the strict sense, „… (Bull.)
 * Šutara 2008 s. str.", German „Rotfußröhrling". Only `full_name` tells
 * them apart; `binomial` is the same for both, and everything here went by
 * the binomial: the search grouped the two into ONE suggestion whose
 * fields came from either row (MAX over the pair — the aggregate's German
 * name under the species' identifier), and the offline packages kept the
 * first of two equal names. So the name a suggestion is delivered under is
 * the binomial PLUS the qualifier where the list has one — „agg.",
 * „Gruppe", „s. str.", „s. l.", „ss. Bres.", „non ss. Kühner" —, and the
 * two rows become two suggestions, two titles, two references.
 *
 * Read off the END of full_name, where the list writes it after author
 * and year. Nomenclatural remarks that also stand there („nom. inval.",
 * „ined.", „unklares Taxon") are NOT qualifiers: they say something about
 * the name, not about the circumscription, and a find is not called
 * „Russula foo nom. inval.".
 *
 * ONE EXPRESSION, THREE READERS: the search branch, the fungi package and
 * the import, which writes the qualifier into search_text so that typing
 * it finds it (the full-text index knows only what stands in that
 * column). Migration 0103 carries the same expression once more in SQL
 * for the stock — whoever changes a pattern here changes it there.
 */
function species_qualifier_sql(string $alias): string
{
    $f = $alias . '.full_name';
    return "CASE
                WHEN $f LIKE '% agg.' THEN 'agg.'
                WHEN $f LIKE '% Gruppe' THEN 'Gruppe'
                WHEN $f LIKE '% s. str.' OR $f LIKE '% s.str.' OR $f LIKE '% s str.' THEN 's. str.'
                WHEN $f LIKE '% s. l.' OR $f LIKE '% s.l.' THEN 's. l.'
                WHEN $f LIKE '% non ss. %' THEN SUBSTRING($f, LOCATE(' non ss. ', $f) + 1)
                WHEN $f LIKE '% ss. %' THEN SUBSTRING($f, LOCATE(' ss. ', $f) + 1)
            END";
}

/** A delivered name without its qualifier — the reverse of species_qualified_name_sql(). */
function species_bare_name(string $name): string
{
    return (string) preg_replace('~\s+(?:agg\.|Gruppe|s\. str\.|s\. l\.|(?:non )?ss\. .*)$~u', '', $name);
}

/**
 * The name a DGfM row is suggested and stored under: GBIF's current name
 * where the link carries one (map_tax_links.gbif_name, migration 0106 —
 * 13.09.2026: „Macht doch die GBIF-Namen. Sie sind aktueller
 * und weltweit hört man darauf"), else the binomial; and the qualifier
 * where there is one. The DGfM row stays the row — German name, Red List
 * category and the reference come from it; only the name it is called by
 * is GBIF's. $link is the alias of the links row joined for $alias, or ''
 * where the column does not exist yet (species_links_ready).
 */
function species_qualified_name_sql(string $alias, string $link = ''): string
{
    $q = species_qualifier_sql($alias);
    $name = $link === '' ? "$alias.binomial" : "COALESCE($link.gbif_name, $alias.binomial)";
    return "TRIM(CONCAT_WS(' ', $name, $q))";
}

/** The DGfM's own name, GBIF or no GBIF — for the „über …" note and the resolver. */
/**
 * GBIF's name as it is shown — the canonical with the rank marker put back.
 *
 * GBIF'S CANONICAL DROPS THE MARKER: the variety „Russula atropurpurea
 * var. atropurpurella Singer" stands there as „Russula atropurpurea
 * atropurpurella", and the list showed exactly that — a trinomial nobody
 * could place (21.09.2026: „wo kommt Russula atropurpurea
 * atropurpurella her?" — „mach das mit var. und f."). For a subspecies,
 * variety or form the marker goes back between epithet and infraspecific
 * epithet; a species keeps its binomial. Built from the canonical, not
 * cut out of the full name with its author, because authorships are not
 * always a clean suffix. ONE EXPRESSION for the search and the package,
 * and for the synonym's target too: the package resolves a target by
 * name, so target and name must be spelled by the same hand.
 */
function species_gbif_name_sql(string $alias): string
{
    return "CASE WHEN $alias.taxon_rank IN ('SUBSPECIES', 'VARIETY', 'FORM')
                  AND $alias.canonical LIKE '% % %'
                 THEN CONCAT(SUBSTRING_INDEX($alias.canonical, ' ', 2), ' ',
                             CASE $alias.taxon_rank WHEN 'SUBSPECIES' THEN 'subsp.'
                                                   WHEN 'VARIETY' THEN 'var.' ELSE 'f.' END,
                             ' ', SUBSTRING_INDEX($alias.canonical, ' ', -1))
                 ELSE $alias.canonical END";
}

function species_own_name_sql(string $alias): string
{
    return "TRIM(CONCAT_WS(' ', $alias.binomial, " . species_qualifier_sql($alias) . '))';
}

/** Does the link table carry GBIF's names yet (migration 0106)? */
function species_links_ready(PDO $pdo): bool
{
    static $ready = null;
    return $ready ??= column_exists($pdo, 'map_tax_links', 'gbif_name');
}

/**
 * Can the search look a species up under GBIF's name (migration 0134)?
 *
 * Asked, not assumed: without the FULLTEXT index MATCH does not return
 * nothing, it fails — and with it every search, since the branches are one
 * statement. The code goes live before the migration (CLAUDE.md).
 */
function species_gbif_names_searchable(PDO $pdo): bool
{
    static $searchable = null;
    return $searchable ??= (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'map_tax_links'
            AND COLUMN_NAME = 'gbif_name' AND INDEX_TYPE = 'FULLTEXT'"
    )->fetchColumn() > 0;
}

/** The fungi branch's step aside for the lichen list — species_branch() says why. */
function species_yields_to_lichens_sql(): string
{
    return 'AND NOT EXISTS (SELECT 1 FROM map_tax_lichens f
                             WHERE f.binomial = a.binomial AND f.taxnr = f.sipnr)';
}

/**
 * The DGfM's species, found under the name they are shown by (26.09.2026).
 *
 * Since 0106 a DGfM species is suggested under GBIF's name where the link
 * carries one — but search_text holds the DGfM's own name and the German
 * one, nothing else. Typed exactly as shown, 1,056 of the 2,133 were not
 * found as the DGfM's species: „Rhodofomes cajanderi" (the DGfM's
 * Fomitopsis cajanderi) brought GBIF's own bare row instead, without German
 * name or category and with a gbif: reference, while the offline search,
 * whose package delivers the species under the name it shows, had the
 * DGfM's. This branch matches the shown name itself, so such a hit is
 * direct and carries no „über …" note — what was typed IS what is shown.
 *
 * Its own index (0134), not GBIF's: matched in map_tax_gbif's 960,000
 * names and joined back, „cor" cost 80 ms per query, and the search asks
 * twice per keystroke. Nor the name copied into search_text: 0106 and
 * admin/tax_link.php fill gbif_name, and a copy would have to follow both.
 *
 * GBIF's name with a German word („rhodofomes rosen") or with the DGfM's
 * qualifier („tomentella pyrolae ss. auct.") finds nothing here, the index
 * holding the one name alone; offline both do. Seldom typed, and left so —
 * measured on 26.09.2026, 2,083 of the 2,133 come up under the name shown,
 * 14 miss only with the qualifier typed, and the rest yield to the lichen
 * list as every fungus does.
 */
function species_gbif_name_branch(int $i, bool $yieldsToLichens): string
{
    $exclusion = $yieldsToLichens ? species_yields_to_lichens_sql() : '';
    return "SELECT " . species_qualified_name_sql('a', 'lka') . " AS name, gba.author AS author,
                   a.german_name AS german, a.red_list_de AS red_list, 'dgfm' AS source,
                   CONCAT('fungi:', a.taxnr) AS identifier, 'fungi' AS group_key,
                   NULL AS via, 1 AS direct, 0 AS via_rank, lka.gbif_name AS own
              FROM map_tax_links lka
              JOIN map_tax_dgfm a ON a.taxnr = lka.taxnr AND a.taxnr = a.sipnr
              LEFT JOIN map_tax_gbif gba ON gba.gbif_key = lka.gbif_species_key
             WHERE MATCH(lka.gbif_name) AGAINST (:a$i IN BOOLEAN MODE) $exclusion";
}

/**
 * An old GBIF name that no chosen Germany list uses itself.
 *
 * Offline a name that the plants, the lichens or the fungi carry — valid or
 * as a synonym, and the DGfM's „X agg." as its bare „X" — is theirs, and
 * GBIF's word on it yields (loadSpeciesList in species.js). The binomial
 * column is the name without author or qualifier, so one index lookup per
 * list answers it. Not asked: a GBIF synonym spelled like a name the DGfM
 * shows under GBIF's name — a homonym inside GBIF, and seldom enough.
 */
function species_gbif_name_unclaimed_sql(string $name, array $groups): string
{
    $parts = [];
    foreach (['plants' => 'map_tax_plants', 'lichens' => 'map_tax_lichens', 'fungi' => 'map_tax_dgfm'] as $group => $table) {
        if (in_array($group, $groups, true)) {
            $parts[] = "NOT EXISTS (SELECT 1 FROM $table o WHERE o.binomial = $name)";
        }
    }
    return $parts === [] ? '1' : implode(' AND ', $parts);
}

/**
 * A Germany species found under an old name that only GBIF knows
 * (26.09.2026). $target is 'dgfm' or 'lichens'.
 *
 * The GBIF package brings GBIF's synonyms along — „alte Namen, unter denen
 * man eine Art noch kennt", as its text promises —, and offline such a name
 * leads to its accepted GBIF name, shown as the Germany species of that
 * name where there is one: „pla veg" gave the DGfM's Ganoderma applanatum
 * „über Placodes vegetus", „cri ran" the lichen list's Cladonia rangiferina
 * „über … subsp. crispata". The server searched GBIF's valid names only,
 * and neither came; of 400 inputs, 64 differed in their first ten for that.
 *
 * The accepted name is reached by GBIF's own key, the Germany row by the
 * name it is shown under — the browser resolves a target by name, so a
 * DGfM taxon that keeps its own name beside GBIF's (0106's lumps) is not
 * reached here either.
 *
 * ONLY WHERE THE GERMANY LISTS YIELD LITTLE (species_search), as GBIF
 * itself: the match runs in GBIF's full-text index of 960,000 names, and on
 * a broad input like „cor" that costs 80 ms a query. Where the Germany
 * lists fill a page, these suggestions stand behind it offline too — their
 * name seldom starts with what was typed, since what was typed is the old
 * one.
 */
function species_gbif_synonym_branch(string $target, int $i, array $groups): string
{
    $old = species_gbif_name_sql('gs');
    $accepted = species_gbif_name_sql('ga');
    $from = "FROM map_tax_gbif gs
             JOIN map_tax_gbif ga ON ga.gbif_key = gs.parent_key
                  AND ga.is_synonym = 0 AND " . species_gbif_rank('ga');
    $where = "MATCH(gs.canonical) AGAINST (:a$i IN BOOLEAN MODE)
              AND gs.is_synonym = 1 AND " . species_gbif_rank('gs') . "
              AND " . species_gbif_name_unclaimed_sql($old, $groups);

    if ($target === 'lichens') {
        return "SELECT f.binomial AS name, f.author AS author, f.german_name AS german,
                       f.red_list_de AS red_list, 'lichens' AS source,
                       CONCAT('lichens:', f.uuid) AS identifier, 'lichens' AS group_key,
                       $old AS via, 0 AS direct, 1 AS via_rank, $old AS own
                  $from
                  JOIN map_tax_lichens f ON f.binomial = $accepted AND f.taxnr = f.sipnr
                 WHERE $where";
    }

    $shown = species_qualified_name_sql('a', 'lka');
    $exclusion = in_array('lichens', $groups, true) ? species_yields_to_lichens_sql() : '';
    // The author as in species_branch(): GBIF's where the name is GBIF's.
    return "SELECT $shown AS name,
                   CASE WHEN lka.gbif_name IS NOT NULL THEN ga.author ELSE a.author END AS author,
                   a.german_name AS german, a.red_list_de AS red_list, 'dgfm' AS source,
                   CONCAT('fungi:', a.taxnr) AS identifier, 'fungi' AS group_key,
                   $old AS via, 0 AS direct, 1 AS via_rank, $old AS own
              $from
              JOIN map_tax_links lka ON lka.gbif_species_key = ga.gbif_key
              JOIN map_tax_dgfm a ON a.taxnr = lka.taxnr AND a.taxnr = a.sipnr
             WHERE $where AND $shown = $accepted $exclusion";
}

/**
 * One search branch over one of the reference lists for Germany.
 *
 * All are built alike — valid names and synonyms in one table, the synonym
 * points to the valid name via sipnr —, so one template suffices. The
 * full-text match must sit INSIDE the respective table: only there does its
 * index apply.
 *
 * WHY THE FUNGI LIST YIELDS TO THE LICHENS
 * 377 names appear in both. Without a rule each of them would show up twice,
 * and which of the two entries you saw would be chance. For a name that the
 * lichen list carries, it is the authoritative source — it comes from the
 * lichenologists, has the threat classification for exactly this taxon and
 * knows its group. So the fungi branch hides what sits over there.
 *
 * @param int $i Number of the placeholder; without emulation PDO does not
 *               allow the same name a second time.
 */
function species_branch(string $table, string $source, int $i, bool $yieldsToLichens, string $identifier, string $where = '', string $group = '', bool $gbifNames = false): string
{
    /* The DGfM list is the one with a qualifier to show (species_qualifier_sql)
       — and, since 0106, with GBIF's name where the link has one: the
       shown name is the valid row's GBIF name; the „über …" note names
       the DGfM row that was hit under its own name, where the two differ
       (a synonym, or the DGfM name of a renamed species). */
    $dgfm  = $table === 'map_tax_dgfm';
    $links = $dgfm && $gbifNames
        ? ' LEFT JOIN map_tax_links lka ON lka.taxnr = a.taxnr
            LEFT JOIN map_tax_gbif gba ON gba.gbif_key = lka.gbif_species_key' : '';
    $nameA = $dgfm ? species_qualified_name_sql('a', $gbifNames ? 'lka' : '') : 'a.binomial';
    $nameT = $dgfm ? species_own_name_sql('t') : 't.binomial';
    // The author belongs to the name: GBIF's where the name is GBIF's.
    $author = $dgfm && $gbifNames ? 'CASE WHEN lka.gbif_name IS NOT NULL THEN gba.author ELSE a.author END' : 'a.author';
    /* The restriction to one animal group — empty for everything else.
       IT WAS ONCE CALLED $group, exactly like the parameter above it that
       carries the subject. Two different things under one name: the
       assignment here overwrote the parameter, and the WHERE condition
       wrote itself into the group column. MySQL reported it at once
       because the condition contains quotation marks — had it none, it
       would have gone through and every suggestion would have carried the
       wrong shape. */
    $restriction = $where === '' ? '' : 'AND ' . $where;
    $exclusion = $yieldsToLichens ? species_yields_to_lichens_sql() : '';

    /* THE GROUP COMES FROM THE BRANCH, NOT FROM THE ROW. For the animals
       the table holds the class (Aves, Insecta, …), and only
       species_group_where() turns that into a subject. Writing the same
       mapping a second time for the display would mean letting the two
       drift apart. The branch knows it anyway: it was built for exactly
       one subject, and for the three non-animal ones it is the whole
       table. */
    /* DIRECT IS THE VALID ROW ITSELF — OR A ROW THAT BEARS THE VERY NAME
       SHOWN. The second half since 24.09.2026: 0106 shows 2,133 DGfM
       species under GBIF's name, and for 1,041 of them the DGfM keeps
       exactly that name as a synonym row. Typed, the name hit only that
       row and counted as found via a synonym — without an „über …" note,
       since the name hit IS the name shown —, while the browser, whose
       package drops such a row and matches the shown name itself, counted
       it as direct: „tricho" put Trichobelonium kneiffii in a different
       place online and offline.

       ONE CASE STAYS APART, knowingly: a renamed species found ONLY by
       the DGfM's own name counts as direct here and as a synonym hit in
       the browser, which knows the old name only as a synonym. Telling
       the two apart would mean matching the input against the shown name
       once more — a tiebreak after four keys is not worth that.

       The match only filters. Its score no longer travels along — the
       ORDER BY in species_search() says why it must not sort. */
    /* A SPECIES' GERMAN NAME, WHICHEVER ANIMAL LIST WAS HIT (26.09.2026).
       Fauna Europaea carries none — the Stuttgart list carries 12,658 —,
       so a species reached through one of Fauna Europaea's 5,200 old names
       for a Stuttgart species came without it: „phalaena quercus" gave
       Lasiocampa quercus and not the Eichenspinner. Offline the browser
       takes a synonym's target by name and meets the Stuttgart row.

       AND ITS AUTHOR, FROM THE NEWER LIST. The two disagree in 3,336 of
       the 6,723 names they share (Calidris pusilla: „Linnaeus, 1758" in
       Fauna Europaea, „Linnaeus, 1766" in Stuttgart — 1766 is when he
       described it), and online the alphabet picked one (MAX in
       species_search). The rule of 26.09.2026: the newer list's
       author. Fauna Europaea's data is of 09.09.2016, its project ran
       2000–2004; the Stuttgart list is kept up to date at the SNSB and was
       fetched from there on 19.08.2026. Offline its row comes first
       anyway, being the one with the German name.

       Where the Stuttgart list names no author — most of its genera —
       Fauna Europaea's stands. BUT NEVER A SUBGENUS'S: Fauna Europaea's
       3,679 subgenera sit under their genus's bare name, each with its own
       author („Acinopus": Dejean for the genus, then Dejean, Bedel,
       Schaum, Fischer von Waldheim for the subgenera), and MAX() gave the
       genus Schaum's. A subgenus row keeps its place, its author goes;
       the package does the same (species_packages.php). */
    $germanFrom = $table === 'map_tax_faeu'
        ? ' LEFT JOIN map_tax_animals sg ON sg.binomial = a.binomial AND sg.taxnr = sg.sipnr' : '';
    $german = $table === 'map_tax_faeu'
        ? "COALESCE(NULLIF(a.german_name, ''), sg.german_name)" : 'a.german_name';
    if ($table === 'map_tax_faeu') {
        $author = "COALESCE(NULLIF(sg.author, ''), CASE WHEN a.taxon_rank = 'subgenus' THEN NULL ELSE a.author END)";
    }
    /* `own` is the name the row was found under, bare — the DGfM's binomial
       carries no qualifier, every other list's binomial is what it shows.
       The first key of species_search() asks whether the input spells it
       out; every branch delivers one, GBIF's under GBIF's spelling. */
    return "SELECT $nameA AS name, $author AS author, $german AS german,
                   a.red_list_de AS red_list, '$source' AS source, $identifier AS identifier,
                   '$group' AS group_key,
                   CASE WHEN $nameT = $nameA THEN NULL ELSE $nameT END AS via,
                   (t.taxnr = t.sipnr OR $nameT = $nameA) AS direct, 0 AS via_rank,
                   t.binomial AS own
              FROM $table t
              JOIN $table a ON a.taxnr = t.sipnr {$links}{$germanFrom}
             WHERE MATCH(t.search_text) AGAINST (:a$i IN BOOLEAN MODE) $exclusion $restriction";
}

/**
 * All Germany lists as one result.
 *
 * One full-text index per table, and their scores are not comparable — which
 * is why none of them sorts any more (see the ORDER BY in species_search()).
 */
function species_branches(): string
{
    // THE IDENTIFIER IS THE STABLE REFERENCE, not the row number. In the
    // DGfM list taxnr appears like that in the source file; for the two BfN
    // lists the import script assigns the number itself, so there the UUID
    // of the source applies. Whoever confuses the two builds references that
    // the next re-import bends onto a different species.
    return species_branches_for(species_groups_all())['sql'];
}

/** The groups one can record — the same three the settings offer. */
/**
 * The subjects one can record — the same the settings offer.
 *
 * The animal groups come out of ANIMAL_GROUPS, so a new one needs to be
 * added in exactly one place (species_packages.php).
 */
function species_groups_all(): array
{
    require_once __DIR__ . '/species_packages.php';
    return array_merge(['fungi', 'lichens', 'plants'], array_keys(ANIMAL_GROUPS));
}

/**
 * Only the branches the user wants suggestions from.
 *
 * WHY THE NUMBERING IS COUNTED AND NOT WRITTEN OUT. Every branch needs its
 * own placeholder (two, until the score left on 24.09.2026), and PDO without
 * emulation refuses the same name twice — so they used to stand as 1,3,5
 * and the caller bound :a1 to :a6. As soon as a branch may be missing, those
 * numbers no longer fit: leaving out the fungi would leave :a1 unbound, and
 * the statement fails on execute with a message about the number of
 * parameters, which says nothing about the cause. The count travels back
 * with the SQL.
 *
 * @param bool $gbifNamesSearchable Whether the fungi get a second branch
 *        that finds a species under GBIF's name (species_gbif_name_branch).
 * @param bool $gbifSynonyms Whether Germany species are also found under
 *        old names only GBIF knows (species_gbif_synonym_branch) — asked
 *        for where the Germany lists yield little.
 * @return array{sql: string, placeholders: int}
 */
function species_branches_for(array $groups, bool $gbifNames = false, bool $gbifNamesSearchable = false, bool $gbifSynonyms = false): array
{
    $branches = [];
    $nr = 1;

    require_once __DIR__ . '/species_packages.php';

    $branchesPossible = [
        ['fungi',    'map_tax_dgfm',     'dgfm',     true,  "CONCAT('fungi:', a.taxnr)", ''],
        ['lichens', 'map_tax_lichens', 'lichens', false, "CONCAT('lichens:', a.uuid)", ''],
        ['plants', 'map_tax_plants', 'plants', false, "CONCAT('plants:', a.uuid)", ''],
    ];
    // Two branches per animal group, and the Stuttgart list FIRST: where a
    // name stands in both, the one with the German name wins, because $seen
    // in species_search() keeps the first.
    foreach (array_keys(ANIMAL_GROUPS) as $animal) {
        $branchesPossible[] = [$animal, 'map_tax_animals', 'animals', false,
            "CONCAT('animals:', a.taxnr)", species_group_where($animal)];
        $branchesPossible[] = [$animal, 'map_tax_faeu', 'faeu', false,
            "CONCAT('faeu:', a.taxnr)", species_group_where($animal)];
    }

    foreach ($branchesPossible as [$group, $table, $source, $yields, $identifier, $where]) {
        if (!in_array($group, $groups, true)) {
            continue;
        }
        // The fungi yield to the lichens only when the lichens are there at
        // all. Otherwise the exclusion would hide names that nothing else
        // shows any more — the 377 shared ones would simply be missing.
        $branches[] = species_branch($table, $source, $nr,
            $yields && in_array('lichens', $groups, true), $identifier, $where, $group, $gbifNames);
        $nr++;
        if ($table === 'map_tax_dgfm' && $gbifNames && $gbifNamesSearchable) {
            $branches[] = species_gbif_name_branch($nr, in_array('lichens', $groups, true));
            $nr++;
        }
        // GBIF hangs on the fungi switch (19.08.2026), so its old
        // names do too — lichens included, which GBIF counts as fungi.
        if ($table === 'map_tax_dgfm' && $gbifNames && $gbifSynonyms) {
            foreach (in_array('lichens', $groups, true) ? ['lichens', 'dgfm'] : ['dgfm'] as $target) {
                $branches[] = species_gbif_synonym_branch($target, $nr, $groups);
                $nr++;
            }
        }
    }

    return ['sql' => implode(' UNION ALL ', $branches), 'placeholders' => $nr - 1];
}

/**
 * Search species.
 *
 * @param int $offset How many hits are skipped — for loading more.
 * @return array{hits: list<array<string,mixed>>, total: int}
 */
function species_search(PDO $pdo, string $input, int $offset = 0, ?array $groups = null): array
{
    [$expression, $firstFragment, $wholeInput] = species_expression($input);
    if ($expression === '') {
        return ['hits' => [], 'total' => 0];
    }

    // WHAT THE USER RECORDS DECIDES WHAT IS SUGGESTED (settings, tab
    // "Arten"). Not given means all of them — an old browser that does not
    // send the choice yet must not suddenly find nothing.
    // Retired groups first become their successors (species_packages.php,
    // SPECIES_GROUP_SUCCESSORS) — the intersection below would otherwise
    // drop them without a trace, and the device would get nothing.
    $groups = $groups === null
        ? species_groups_all()
        : array_values(array_intersect(species_groups_all(), species_groups_current($groups)));

    // Nothing chosen is a valid answer and not an error: whoever wants no
    // suggestions gets none. Asking the database would mean a UNION of
    // nothing, and that is not SQL.
    if ($groups === []) {
        return ['hits' => [], 'total' => 0];
    }
    $offset = max(0, $offset);

    ['sql' => $germanyBranches, 'placeholders' => $placeholders]
        = species_branches_for($groups, species_links_ready($pdo), species_gbif_names_searchable($pdo));

    // How many there are in total. Counted separately, because MySQL 8 has
    // declared SQL_CALC_FOUND_ROWS obsolete — and because the full-text
    // index answers that quickly anyway.
    $count = static function (string $branches, int $placeholders) use ($pdo, $expression): int {
        $counter = $pdo->prepare("SELECT COUNT(DISTINCT name) FROM ($branches) x");
        for ($i = 1; $i <= $placeholders; $i++) {
            $counter->bindValue(':a' . $i, $expression);
        }
        $counter->execute();
        return (int) $counter->fetchColumn();
    };
    $total = $count($germanyBranches, $placeholders);

    /* OLD NAMES ONLY GBIF KNOWS, where the Germany lists yield little —
       species_gbif_synonym_branch() says why only there. Decided by the
       count without them and on every page alike, so that loading more
       goes on in the same list. */
    $withOldNames = false;
    if ($total < SPECIES_PER_PAGE && in_array('fungi', $groups, true) && species_links_ready($pdo)) {
        ['sql' => $germanyBranches, 'placeholders' => $placeholders]
            = species_branches_for($groups, true, species_gbif_names_searchable($pdo), true);
        $withOldNames = true;
    }

    // Grouped by the accepted name, so that a name does not appear twice
    // when several synonyms hit it — and so that loading more stays correct
    // across page boundaries.
    /* A NAME HIT UNDER ITS OWN SPELLING CARRIES NO NOTE, however many
       synonyms hit it too (26.09.2026). MIN() passes over NULL, so the
       note of any synonym won: „boletus edulis" said „über Boletus edulis
       f. albus", and once GBIF's old names counted, nearly every well-known
       species said something — „trametes versicolor" „über Trametes
       versicolor f. antarctica". Offline a direct hit always beat one via
       an old name (searchSpeciesLocally).

       And a Germany list's old name before GBIF's (via_rank): offline the
       fungi package is read before GBIF's, and the first old name that
       hits keeps the note — „boletus tor" says „über Boletus torosus", the
       DGfM's own, not „über Boletus appendiculatus subsp. torosus". */
    /* THE INPUT THAT SPELLS A NAME OUT COMES FIRST (26.09.2026) — two words
       or more, each the start of the name's word in the same place, and no
       word of the name left over: „Marasmiellus ramealis" and „mar ram"
       both spell the old name of Collybiopsis ramealis. The four keys below
       ask only about the name SHOWN, and a species found under its old name
       does not start with what was typed — its varieties do, where the list
       still files them under the old genus. On „Marasmiellus ramealis"
       Marasmiellus ramealis var. macrosporus stood on top, on „bisporella
       citrina" Calycella terrestris („über Bisporella citrina var.
       terrestris") above Calycina citrina, the species typed; on „leccinum
       aurantiacum" Leccinum decipiens above both species the name has meant.
       The first line gets clicked, and the maintainer clicked it (26.09.2026:
       „Varietäten oder Synonyme standen ganz oben statt eigentlich der
       aktuelle Name").
       NOTHING LEFT OVER is what keeps the varieties out: the alphabet puts a
       species before its variety only where both are shown under one genus.
       The name the hit was FOUND under, not the one shown — only that one
       was typed (`own`, every branch delivers it). One word is a genus
       being typed and asks nothing here: „quer" stays with the four keys.
       Compared as the other keys compare, by LIKE — accents off, ß kept —,
       and the word count by the spaces: with exactly as many as the input
       has, no % can reach across a word. searchSpeciesLocally() in
       species.js carries the same key; whoever changes one changes both. */
    $spelled = substr_count($wholeInput, ' ') > 0;
    $stmt = $pdo->prepare(
        "SELECT name, MAX(author) AS author, MAX(german) AS german,
                MAX(red_list) AS red_list, MAX(source) AS source, MAX(identifier) AS identifier,
                MAX(group_key) AS group_key,
                CASE WHEN SUM(via IS NULL) > 0 THEN NULL
                     ELSE COALESCE(MIN(CASE WHEN via_rank = 0 THEN via END), MIN(via)) END AS via,
                MAX(direct) AS direct
           FROM ($germanyBranches) x
          GROUP BY name
          -- MAX(german), not german: in ORDER BY MySQL resolves the name
          -- to the subquery's column, not to the same-named alias above it —
          -- and then rightly complains that it is not grouped.
          -- ABOVE THEM ALL, does the input spell out a name the hit was
          -- found under — the comment above this statement says why.
          -- FOUR KEYS, AND THE ORDER AMONG THEM IS THE WHOLE POINT.
          --
          --   0. Does the scientific name start with the WHOLE input? That
          --      is how species names are typed: 'bol edu', 'fag syl',
          --      'quer rob' — abbreviated genus, abbreviated epithet.
          --      Without this key only the first word counted, and
          --      'Boletus ed' showed the 127 Boletus in alphabetical order,
          --      with edulis somewhere in the middle. The second word was
          --      typed and got thrown away for the sorting.
          --      For 'sal weide' it does not apply (no Salix is called
          --      that) and so does not get in the way there either.
          --
          --   1. Does anything start with the first word — name or German
          --      name? Whoever types 'quer' wants to see nothing where the
          --      word sits in the middle.
          --
          --   2. The SCIENTIFIC name first. That is the main service of
          --      this application; German names are garnish. Without this
          --      key, for 'quer' the 'Queradrige Rötling' beat Quercus — a
          --      fungus that happens to carry a German name with the same
          --      four letters stood above the oak whose accompanying
          --      vegetation someone was just recording. Reported exactly
          --      like that.
          --
          --   3. And WITHIN that, the German hit moves up front. Otherwise
          --      'sal weide' loses against itself: every willow is called
          --      Salix, so starts scientifically with 'sal', and the
          --      Sal-Weide would stand alphabetically behind Silber-,
          --      Alpen- and Strittiger Weide. As the THIRD key it does no
          --      harm anymore — it only applies where key 2 is undecided,
          --      and that is exactly the group it was meant for.
          --
          -- After the four, the direct hit before the one via a synonym,
          -- and then the alphabet.
          --
          -- NO FULL-TEXT SCORE IN BETWEEN (24.09.2026). It stood there, and
          -- it was no relevance: MATCH scores per table, by how rare a word
          -- is in that one index. Since the animal lists there are five
          -- tables, and the score sorted by table — on 'quer' the Stuttgart
          -- list's bird genus Querula (10.7) beat every oak of the plant
          -- list (7.3), and the fungi (6.2) came last wherever a prefix
          -- stands in several groups. Within one table it counted a
          -- repeated word twice: 'quercus robur' put Quercus robur subsp.
          -- robur above the species, while 'Quercus ro' still had the
          -- species on top. The alphabet puts a species before its
          -- subspecies by itself and treats every list alike — and it is
          -- the key the browser has, which knows no score
          -- (searchSpeciesLocally in species.js sorts by the same keys).
          --
          -- COALESCE, or the sorting tips over for everything without a
          -- German name: NULL LIKE '…' is NULL, not 0, and MySQL puts NULL
          -- all the way back under DESC. Boletus edulis var. arenarius
          -- thereby fell behind species that only stood better because they
          -- have a German name at all.
          ORDER BY COALESCE(MAX(own LIKE :spell
                                AND CHAR_LENGTH(own) - CHAR_LENGTH(REPLACE(own, ' ', '')) = :spaces), 0) DESC,
                   (name LIKE :whole) DESC,
                   (name LIKE :start1 OR COALESCE(MAX(german), '') LIKE :start2) DESC,
                   (name LIKE :start4) DESC,
                   (COALESCE(MAX(german), '') LIKE :start3) DESC,
                   direct DESC, name
          LIMIT :n OFFSET :offset"
    );
    // The same value under as many names as there are branches: without
    // emulation PDO does not allow the same placeholder a second time.
    for ($i = 1; $i <= $placeholders; $i++) {
        $stmt->bindValue(':a' . $i, $expression);
    }
    $stmt->bindValue(':start1', $firstFragment . '%');
    $stmt->bindValue(':start2', $firstFragment . '%');
    $stmt->bindValue(':start3', $firstFragment . '%');
    $stmt->bindValue(':start4', $firstFragment . '%');
    $stmt->bindValue(':whole', $wholeInput . '%');
    // „mar ram" becomes „mar% ram%" and wants one space; a single word asks
    // for no space at all and for -1 of them, which no name has.
    $stmt->bindValue(':spell', $spelled ? str_replace(' ', '% ', $wholeInput) . '%' : '');
    $stmt->bindValue(':spaces', $spelled ? substr_count($wholeInput, ' ') : -1, PDO::PARAM_INT);
    $stmt->bindValue(':n', SPECIES_PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $hits = [];
    $seen = [];
    foreach ($stmt as $z) {
        $seen[$z['name']] = true;
        // The bare name counts as seen too (13.09.2026): what the DGfM list
        // knows as „… agg." and „… s. str." is not a gap for GBIF's bare
        // „Xerocomellus chrysenteron" to fill a third time. species.js
        // keeps the same rule for the offline packages.
        $seen[species_bare_name((string) $z['name'])] = true;
        $hits[] = [
            'name'       => $z['name'],
            'author'      => $z['author'] ?: null,
            'german'    => $z['german'] ?: null,
            'red_list' => $z['red_list'] ?: null,
            'source'     => $z['source'],
            'identifier'    => $z['identifier'] ?: null,
            'via'      => $z['via'] ?: null,
            /* THE SUBJECT AS PICTURE AND AS WORD. The suggestion list
               draws the shape and hangs the name on it as a label — a
               symbol without a word is a riddle, and whoever works with
               the keyboard or a screen reader would otherwise see nothing
               at all. The subject's key stays on the server: otherwise the
               browser would need a second table to turn it into shape and
               name, and that is precisely the table there must not be. */
            'form'       => species_group_shape((string) $z['group_key']),
            'group'     => species_group_label((string) $z['group_key']),
        ];
    }

    // With the old names the total is counted anew only where the page
    // cannot tell it: a first page that is not full holds every name there
    // is, and a second count would run GBIF's index once more for it.
    if ($withOldNames) {
        $total = $offset === 0 && count($hits) < SPECIES_PER_PAGE
            ? count($hits)
            : $count($germanyBranches, $placeholders);
    }

    // GBIF only tops up the first page, and only when the Germany lists
    // yield little. Mixing two sources across page boundaries could not be
    // paged reliably — and where Germany already delivers, GBIF is not
    // needed anyway.
    // GBIF IS A FUNGUS LIST — it only tops up where fungi are wanted at all.
    // The rule of 19.08.2026: no fungi means neither the German list
    // nor GBIF.
    if ($offset === 0 && count($hits) < SPECIES_PER_PAGE && in_array('fungi', $groups, true)) {
        $remaining = SPECIES_PER_PAGE - count($hits);
        /* THE BROWSER'S ORDER, which sorts its stored GBIF package by the
           keys of everything else and puts it after the Germany lists as
           this does (searchSpeciesLocally in species.js): the whole input
           as the start of the name, the first fragment as its start, the
           alphabet — on the name as shown, rank marker and all. Until
           26.09.2026 the full-text score decided in between, the one the
           Germany branches lost two days before, and within GBIF too it
           counted a repeated word twice: "hygrocybe mucronella" put
           var. mucronella above var. mitis, offline the other way round.
           A derived table, because map_tax_gbif has a column called name
           of its own.

           AND GBIF'S OLD NAMES, since 26.09.2026: a synonym whose name no
           Germany list uses leads to its accepted name — where that is a
           Germany species, species_gbif_synonym_branch() has it on the
           page already, and $seen passes over it here. Direct before
           „über", as offline; not grouped, because GBIF carries some names
           twice as accepted (Lactarius hortensis Velen. and Fr.), and MAX()
           would mix one's author with the other's key — the first row of a
           name wins below instead. Six rows per free place, not three: a
           name now brings its old names along. */
        $old = species_gbif_name_sql('gs');
        $stmt = $pdo->prepare(
            'SELECT name, author, identifier, via FROM (
                SELECT ' . species_gbif_name_sql('map_tax_gbif') . ' AS name, author,
                       CONCAT("gbif:", gbif_key) AS identifier, NULL AS via
                  FROM map_tax_gbif
                 WHERE MATCH(canonical) AGAINST (:a1 IN BOOLEAN MODE)
                   AND is_synonym = 0
                   AND taxon_rank IN ("species", "subspecies", "variety", "form")
                UNION ALL
                SELECT ' . species_gbif_name_sql('ga') . ', ga.author, CONCAT("gbif:", ga.gbif_key), ' . $old . '
                  FROM map_tax_gbif gs
                  JOIN map_tax_gbif ga ON ga.gbif_key = gs.parent_key
                       AND ga.is_synonym = 0 AND ' . species_gbif_rank('ga') . '
                 WHERE MATCH(gs.canonical) AGAINST (:a2 IN BOOLEAN MODE)
                   AND gs.is_synonym = 1 AND ' . species_gbif_rank('gs') . '
                   AND ' . species_gbif_name_unclaimed_sql($old, $groups) . '
             ) g
             ORDER BY (COALESCE(via, name) LIKE :spell
                       AND CHAR_LENGTH(COALESCE(via, name)) - CHAR_LENGTH(REPLACE(COALESCE(via, name), " ", "")) = :spaces) DESC,
                      (name LIKE :whole) DESC, (name LIKE :start) DESC, (via IS NULL) DESC, name, via
             LIMIT :n'
        );
        $stmt->bindValue(':a1', $expression);
        $stmt->bindValue(':a2', $expression);
        // The spelled name first here too — the name a row was found under
        // is its old one where it has one, else its own.
        $stmt->bindValue(':spell', $spelled ? str_replace(' ', '% ', $wholeInput) . '%' : '');
        $stmt->bindValue(':spaces', $spelled ? substr_count($wholeInput, ' ') : -1, PDO::PARAM_INT);
        $stmt->bindValue(':whole', $wholeInput . '%');
        $stmt->bindValue(':start', $firstFragment . '%');
        $stmt->bindValue(':n', $remaining * 6, PDO::PARAM_INT);
        $stmt->execute();

        $gbifAt = [];
        foreach ($stmt as $z) {
            if (isset($seen[$z['name']])) {
                /* Found directly as well, the name keeps no note — as in the
                   Germany lists and offline. Before the spelled key the
                   direct row always came first; now a spelled old name may
                   bring its name up ahead of it. */
                if ($z['via'] === null && isset($gbifAt[$z['name']])) {
                    $hits[$gbifAt[$z['name']]]['via'] = null;
                }
                continue;
            }
            if (count($hits) >= SPECIES_PER_PAGE) {
                continue;
            }
            $seen[$z['name']] = true;
            $gbifAt[$z['name']] = count($hits);
            $hits[] = [
                'name'       => $z['name'],
                'author'      => $z['author'] ?: null,
                'german'    => null,
                'red_list'   => null,
                'source'     => 'gbif',
                'identifier'    => $z['identifier'],
                'via'      => $z['via'] ?: null,
                // GBIF only fills in fungi here — see above.
                'form'       => species_group_shape('fungi'),
                'group'     => species_group_label('fungi'),
            ];
        }
        $total = max($total, count($hits));
    }

    return ['hits' => $hits, 'total' => $total];
}

/**
 * The threat categories of the Red Lists, from the most severe to the
 * lightest classification.
 *
 * THE SAME SCALE IN ALL THREE LISTS — fungi (DGfM), lichens and plants
 * (Red List Centre of the BfN) use it identically, so it appears once here
 * and not three times next to the tables. The order is that of the official
 * scale and at the same time the one in which the checkboxes appear: whoever
 * filters almost always searches from the top.
 *
 * "nb" MEANS THE SAME THING IN BOTH SOURCES and is therefore unified: the
 * plant list writes "nb", the fungi list the diamond sign. Two spellings for
 * "nicht bewertet" would mean two checkboxes for the same question — and
 * whoever ticks the wrong one gets an empty list and looks for the mistake
 * in themselves.
 */
const RED_LIST_CATEGORIES = ['0', '1', '2', '3', 'G', 'R', 'V', 'D', '*', 'nb'];

/**
 * What the categories mean, in the words of the lists — the legend under
 * the closing page's species table (13.09.2026). assets/js/filter.js
 * carries the same ten for the filter's checkboxes; the browser cannot
 * read this file, so the twin is deliberate and this comment is the
 * tripwire: whoever changes a word changes it there too.
 */
const RED_LIST_NAMES = [
    '0'  => 'ausgestorben oder verschollen',
    '1'  => 'vom Aussterben bedroht',
    '2'  => 'stark gefährdet',
    '3'  => 'gefährdet',
    'G'  => 'Gefährdung unbekannten Ausmaßes',
    'R'  => 'extrem selten',
    'V'  => 'Vorwarnliste',
    'D'  => 'Daten unzureichend',
    '*'  => 'ungefährdet',
    'nb' => 'nicht bewertet',
];

/**
 * Bring a raw classification onto the shared scale.
 *
 * Anything not on it counts as no classification — better to claim nothing
 * than to invent a category that does not exist.
 */
function red_list_category(mixed $raw): ?string
{
    $value = trim((string) ($raw ?? ''));
    if ($value === '♦') {
        $value = 'nb';
    }
    return in_array($value, RED_LIST_CATEGORIES, true) ? $value : null;
}

/**
 * The German names behind taxon references — for the report's second
 * line under the scientific name (12.09.2026: „über ein
 * Häkchen deutsche Namen mit einblenden lassen, in einer zweiten Zeile
 * unter dem wissenschaftlichen").
 *
 * THE SAME THREE TABLES AS THE ENDANGERMENT, for the same reason: the
 * German lists are what carry a German name; GBIF carries none, and a
 * find picked from there simply has no second line. One query per source
 * for the whole batch.
 *
 * @param string[] $identifiers  'fungi:815260', 'lichens:<uuid>', …
 * @return array<string,string>  identifier => German name, only where one stands
 */
function species_german_names(PDO $pdo, array $identifiers): array
{
    $tables = [
        'fungi'   => ['map_tax_dgfm',    'taxnr'],
        'lichens' => ['map_tax_lichens', 'uuid'],
        'plants'  => ['map_tax_plants',  'uuid'],
    ];
    $bySource = [];
    foreach ($identifiers as $identifier) {
        $parts = explode(':', (string) $identifier, 2);
        if (count($parts) !== 2 || !isset($tables[$parts[0]]) || $parts[1] === '') {
            continue;
        }
        $bySource[$parts[0]][$parts[1]] = true;
    }
    $out = [];
    foreach ($bySource as $source => $ids) {
        [$table, $column] = $tables[$source];
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $ids   = array_keys($ids);
        $space = implode(',', array_fill(0, count($ids), '?'));
        // A synonym row carries no German name of its own; its valid
        // name's counts — the reference points at the written name, the
        // German name belongs to the species (13.09.2026).
        $stmt  = $pdo->prepare(
            "SELECT t.$column AS identifier, COALESCE(NULLIF(t.german_name, ''), v.german_name) AS german_name
               FROM $table t LEFT JOIN $table v ON v.taxnr = t.sipnr
              WHERE t.$column IN ($space)"
        );
        $stmt->execute($ids);
        foreach ($stmt as $z) {
            $name = trim((string) ($z['german_name'] ?? ''));
            if ($name !== '') {
                $out[$source . ':' . $z['identifier']] = $name;
            }
        }
    }
    return $out;
}

/**
 * The threat classification for taxon identifiers — for all at once.
 *
 * IN ONE SWEEP, not per point: the sidebar asks for the whole inventory, and
 * one query per find would, at four hundred points, be four hundred queries
 * for a piece of information that hangs on the species and not on the find.
 *
 * WHY THE CLASSIFICATION IS NOT STORED ON THE POINT: it belongs to the
 * species, not to the find. The Red Lists get reissued — frozen, a find from
 * 2019 would forever show the classification of back then, and that would be
 * exactly the wrong value when reporting. The protected area sits on the
 * point because it hangs on the PLACE and comes from an external service;
 * here both are different.
 *
 * @param string[] $identifiers "fungi:49097", "lichens:<uuid>", "plants:<uuid>"
 * @return array<string,string> identifier => category
 */
function species_endangerment(PDO $pdo, array $identifiers): array
{
    /* Per source its table and the column the identifier points to. GBIF is
       missing here on purpose: it carries no classification for Germany —
       whoever takes a name from there has none, and that is not an error. */
    $tables = [
        'fungi'    => ['map_tax_dgfm',     'taxnr'],
        'lichens' => ['map_tax_lichens', 'uuid'],
        'plants' => ['map_tax_plants', 'uuid'],
    ];

    $bySource = [];
    foreach ($identifiers as $identifier) {
        $parts = explode(':', (string) $identifier, 2);
        if (count($parts) !== 2 || !isset($tables[$parts[0]]) || $parts[1] === '') {
            continue;
        }
        $bySource[$parts[0]][$parts[1]] = true;
    }

    $out = [];
    foreach ($bySource as $source => $ids) {
        [$table, $column] = $tables[$source];
        // The code goes live before the migration (see CLAUDE.md): without
        // the taxonomy there is simply no classification, but no crash.
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $ids   = array_keys($ids);
        $space = implode(',', array_fill(0, count($ids), '?'));
        // The synonym row's category is its valid name's — see the same
        // line in species_german_names().
        $stmt  = $pdo->prepare(
            "SELECT t.$column AS identifier, COALESCE(NULLIF(t.red_list_de, ''), v.red_list_de) AS red_list_de
               FROM $table t LEFT JOIN $table v ON v.taxnr = t.sipnr
              WHERE t.$column IN ($space)"
        );
        $stmt->execute($ids);

        foreach ($stmt as $z) {
            $category = red_list_category($z['red_list_de']);
            if ($category !== null) {
                $out[$source . ':' . $z['identifier']] = $category;
            }
        }
    }

    return $out;
}
