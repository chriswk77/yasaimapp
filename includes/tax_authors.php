<?php
/**
 * Comparing author citations — shared by admin/tax_link.php (DGfM against
 * GBIF and COL) and by a module's linking tool (books against all three).
 *
 * A species name comes with author and year; without them a name is
 * ambiguous. Agaricus campestris L. and Agaricus campestris Schaeff. are
 * different fungi. The year barely helps for fungi (see tax_link.php), so
 * the author decides — compared not literally but for containment, because
 * every list spells it its own way.
 */

declare(strict_types=1);

/** Boil the author citation down to something comparable: lowercase letters only. */
function author_core(?string $a): string
{
    return preg_replace('~[^a-z]~', '', mb_strtolower((string) $a));
}

/**
 * Do two author citations fit together?
 *
 * Not equality, but containment: the shorter must be contained in the
 * longer. That way the comparison survives abbreviated first names and
 * additional co-authors.
 *
 * WHAT IT DOES NOT SURVIVE, measured 08.10.2026: a sanctioning on one side
 * only — „(Pers. : Fr.) P. Karst." boils down to persfrpkarst, „(Pers.)
 * P.Karst." to perspkarst, and neither contains the other — and a letter
 * with a diacritic, which simply drops out. tax_link.php has used it like
 * this since August and its link table rests on it, so it stays as it is;
 * author_agrees() below is the thorough twin the books needed.
 */
function authorMatches(?string $a, ?string $b): ?bool
{
    $x = author_core($a);
    $y = author_core($b);
    if ($x === '' || $y === '') return null;          // not checkable
    return str_contains($x, $y) || str_contains($y, $x);
}

/**
 * Spellings of one author that neither containment nor the abbreviation
 * rule of author_fits() brings together — taken from what the books really
 * print (measured 25.11.2026 over 38,740 names with an author): the name
 * written out against its two-letter standard abbreviation, „Hry." without
 * the „Rob." GBIF puts in front of Henry, Jul. Schäffer in three shapes.
 * Keys and values are folded names, as author_plain() returns them.
 */
const AUTHOR_ALIASES = [
    'fries' => 'fr', 'linne' => 'l', 'linnaus' => 'l', 'linnaeus' => 'l', 'linn' => 'l', 'linneo' => 'l', 'lin' => 'l',
    'decandolle' => 'dc', 'swartz' => 'sw', 'swart' => 'sw', 'schwartz' => 'sw', 'smith' => 'sm',
    'robhenry' => 'henry', 'hry' => 'henry', 'hy' => 'henry', 'rhry' => 'henry', 'rhenry' => 'henry',
    'rmre' => 'maire', 'mre' => 'maire',
    'julschaff' => 'schaff', 'jschff' => 'schaff', 'jschaff' => 'schaff',
    'thorefries' => 'thfr', 'bk' => 'berk', 'pk' => 'peck', 'teodorowicz' => 'theodor',
];

/** Pairs the older books abbreviate to their initials: „(A. & S.: Fr.) Fr.". */
const AUTHOR_TEAMS = [
    'a&s' => 'alb&schwein', 'k&m' => 'konrad&maubl', 'k&r' => 'kuhner&romagn',
    'b&br' => 'berk&broome', 'bk&br' => 'berk&broome', 'b&c' => 'berk&curtis',
];

/**
 * WHO WROTE BEFORE FRIES. Until 1981 the fungi began with Fries' Systema
 * of 1821, and a name he took up from an older author was cited as his:
 * „Lepiota cristata (Fr.) Kumm." in Moser 1985 is „(Bolton) P. Kumm." in
 * today's lists — one name, cited by two rules. So „Fr." against one of
 * these is no second author but the old way of citing, and
 * author_compare() gives it its own word. Against anybody later — „(Fr.)"
 * here, „(P. Karst.)" there — it is a contradiction like any other.
 */
const AUTHORS_BEFORE_FRIES = [
    'pers', 'bull', 'batsch', 'schaff', 'dc', 'alb', 'schwein', 'paulet', 'l', 'lilj', 'sowerby', 'with', 'schumach',
    'bolton', 'schrad', 'scop', 'holmsk', 'jacq', 'gmel', 'vahl', 'tode', 'lam', 'ach', 'huds', 'roth', 'wahlenb',
    'sw', 'link', 'hornem', 'sibth', 'willd', 'wulfen', 'mull', 'brot', 'ehrenb', 'dicks', 'relhan', 'lightf',
    'weigel', 'hoffm', 'retz', 'nees', 'ehrh', 'poir', 'vill', 'schrank', 'leyss', 'hedw', 'pollich', 'thunb',
    'afzel', 'rebent', 'ditmar', 'kunze', 'abbot', 'purton', 'hall', 'mich', 'vaill', 'dill', 'batt',
];

/**
 * An author citation reduced to what names the people.
 *
 * The books print what the lists leave out and the other way round:
 * Gröger and Funga Nordica keep the sanctioning („Fr. : Fr.", „(Schaeff.:
 * Fr.)"), years („(Fr. 1815)"), status remarks („inval.", „ad int.",
 * „nom. prov."), sensu clauses („ss. J.E. Lange") and „& al." for a list of
 * co-authors; GBIF has none of that and writes „P.Karst." without the
 * space. What remains here: basionym author and combining author, letters
 * only, diacritics folded.
 *
 * WHAT IT HAD TO LEARN (25.11.2026, after a link that should not have
 * been: a book's name hung on another fungus because nobody had read the
 * bracket). Measured over every name the books print with an author, 1,815
 * of 4,272 contradictions were none — the citation was only read wrong:
 *
 * - THE PLACE OF PUBLICATION behind the author („(Fr.) Boud., Hist. Class.
 *   Discom. Eur.: 104 (1907)") and a year in a bracket of its own. The
 *   first bracket used to be taken up to the LAST closing one, so the
 *   whole citation became the basionym author.
 * - THE SANCTIONING AUTHOR STAYS, behind his colon („pers:fr"). He used
 *   to be dropped, and then „(Pers. : Fr.) Donk" contradicted the list's
 *   „(Fr.) Donk" — which cites the same name by the sanctioner alone.
 * - Square brackets, „et" / „e" / „und" between two authors, „apud" like
 *   „in", „sp. nov." as a status remark, a synonym in a bracket, and the
 *   variety's epithet with its own author behind the species' one.
 *
 * @return array{0:string,1:string} [basionym part, combining part] — each „a&b" or „a&b:sanctioner"
 */
function author_plain(?string $a): array
{
    $a = strtr(trim((string) $a), ['[' => '(', ']' => ')', '{' => '(', '}' => ')', '→' => ':']);
    // „Fr. [= M. phalloides (Bull.) Chev.]": a synonym in a bracket of its own.
    $a = preg_replace('~\(\s*=.*$~u', '', $a);
    // The same without a bracket: „Lange 1914 = var. alba Fl. Dan. ex Rea 1922".
    $a = preg_replace('~\s+=\s+.*$~u', '', $a);
    // A spelling the book quotes before the author: „("miser") (P. Karst.) Redhead".
    $a = preg_replace('~\(\s*["„“”‘’\'][^()]*\)~u', '', $a);
    // „D.C ex Fr." is de Candolle, not an initial and a stray letter; and
    // „A. & S.ex Fr." lost the space the next rules look for.
    $a = preg_replace('~\bD\.\s?C\b\.?~u', 'DC.', $a);
    $a = preg_replace('~(?<=\.)(ex|et|in)\s+(?=[\p{Lu}(])~u', ' $1 ', $a);
    // THE RANK BELOW THE SPECIES, printed into the author: „(Schaeff.: Fr.)
    // Gray var. silvestris Singer" — the variety's author is the one behind
    // its epithet. Where nobody follows („Fries var. acutus"), the variety
    // is the species' own and the author before it stands.
    if (preg_match('~^(.*?)(?:^|\s+)(?:(?:var|subsp|ssp)[.:]?\s+[\p{L}-]{3,}|fo?\.\s*\p{Ll}{3,})(.*)$~u', $a, $m)) {
        $a = preg_match('~\p{Lu}~u', $m[2]) ? trim($m[2]) : $m[1];
    }
    // The place of publication: a comma, a title, a colon and the page.
    $a = preg_replace('~,\s*[^,()&:]{3,}:\s*(\d.*)?$~u', '', $a);
    // A bracket that holds a year and no author: „(1907)", „('1949')", „(in Lundqvist, 1972)".
    $a = preg_replace('~\(\s*(in\s[^()]*?)?[\'‘’"„“]?\d{4}[a-z]?[\'’"“]?\s*\)~u', '', $a);
    $a = preg_replace('~\(\s*in\s[^()]*\)~u', '', $a);
    // Sensu clauses and status remarks are not part of the author. A sensu
    // clause is cut only BEHIND an author: a list row that is nothing but
    // „ss. auct." has to stay a citation that agrees with nobody — as no
    // author at all it would win against every row that contradicts.
    $a = preg_replace('~\s+(non\s+)?(ss\.|(?-i:s\.)|sensu|s\.\s*l\.|s\.\s*str\.|auct\.|p\.\s*p\.|em(end)?\.).*$~iu', '', $a);
    // „Fries 1825 non Panus strigosus Berk. & M.A. Curtis": the name it is NOT.
    $a = preg_replace('~\s+non\s+\p{Lu}.*$~u', '', $a);
    $a = preg_replace('~(^|\s+)(sp(ec)?\.\s*n(ov)?\.?|n(ov)?\.\s*sp(ec)?\.?|nom\.\s*\w+\.?|inval\.|ad\s+int\.|comb\.\s*\w+\.?).*$~iu', '', $a);
    // „A ex B", „A in B", „A apud B": some lists keep A, some B, some both
    // — both stay, as a team. „Bres. in Sacc. & Syd." is „(Sacc. & P. Syd.)"
    // in the lists, „Fries in Quélet" is „(Quél.)" in one and „(Fr.)" in
    // the other; until 25.11.2026 whoever stood behind „in" was dropped as
    // the book's editor, and those were contradictions. The sanctioning
    // („Pers. : Fr.") keeps its colon and with it its place behind the
    // author: author_members() and author_sanctioners().
    $a = preg_replace('~\s+(ex|et|and|und|e|in|apud|ap\.)\s+~u', ' & ', $a);
    // Initials go: „J.-E. Gilbert" and „E.-J. Gilbert", „P. Karst." and
    // „P.Karst." — but not an author who IS one letter („L.", „(L.) Fr.").
    $a = preg_replace('~\b\p{Lu}\.(?=\s*-?\s*\p{L})~u', '', $a);
    // An initial that lost its dot in the scan: „J Lge." for J. Lange, „R
    // Haller". Only before a name — „L ex Fr." is Linnaeus.
    $a = preg_replace('~(?<![\p{L}.])\p{Lu}\s+(?=\p{Lu}\p{Ll})~u', '', $a);
    // „& al.", „et al." stand for co-authors the other side may spell out.
    $a = preg_replace('~\s*&\s*al\.?~u', '', $a);
    $a = preg_replace('~\d+~', '', $a);

    $fold = static function (string $s): string {
        $s = mb_strtolower($s);
        $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ø' => 'o', 'æ' => 'ae', 'å' => 'a', 'ß' => 'ss']);
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        $t = preg_replace('~[^a-z&,:]~', '', $t === false ? $s : $t);
        // „Schroet." and „Schröt." — the umlaut written out folds the same way.
        return str_replace(['ae', 'oe', 'ue'], ['a', 'o', 'u'], $t);
    };
    // What still stands in a bracket behind the author is a remark.
    $rest = static fn(string $s): string => preg_replace('~\([^()]*\)?~u', '', $s);

    // The FIRST bracket, closed or cut off by the field's end („(M.M. Moser
    // ex M.M. Moser & Singer" in a book that broke the line there).
    if (preg_match('~^\s*\(([^()]*)(?:\)|$)\s*(.*)$~u', $a, $m)) {
        return [$fold($m[1]), $fold($rest($m[2]))];
    }
    return ['', $fold($rest($a))];
}

/**
 * The people of one part of a citation, each under ONE spelling — without
 * a sanctioning author, who stands behind the colon.
 *
 * @return list<string>
 */
function author_members(string $part): array
{
    return author_names(explode(':', $part, 2)[0]);
}

/**
 * Who sanctioned the name: „Fr." in „Pers. : Fr.". Empty in most citations.
 *
 * @return list<string>
 */
function author_sanctioners(string $part): array
{
    return author_names(strtr(explode(':', $part, 2)[1] ?? '', ':', '&'));
}

/** @return list<string> */
function author_names(string $team): array
{
    foreach (AUTHOR_TEAMS as $short => $long) {
        $team = preg_replace('~(?<![a-z])' . $short . '(?![a-z])~', $long, $team);
    }
    $out = [];
    foreach (preg_split('~[&,]~', $team) as $m) {
        if ($m !== '') $out[] = AUTHOR_ALIASES[$m] ?? $m;
    }
    return array_values(array_unique($out));
}

/**
 * How well two folded names are one author.
 *
 *   2  equal, or the shorter contained in the longer („Kumm." in „P.
 *      Kumm.", „Britz." in „Britzelm.").
 *   1  the shorter an ABBREVIATION of the longer — the same first letter
 *      and its letters in the longer one's order: „Lge." for Lange, „Cke."
 *      for Cooke, „Krbh." for Krombholz. The older books abbreviate so,
 *      and a letter the print lost („Bourot") passes the same way. Or ONE
 *      LETTER READ WRONG in a name of five letters and more: the scanned
 *      books carry „Wulren" and „Brirz.", and two people who differ in
 *      one letter of the same name's author are not to be expected.
 *   0  two people.
 *
 * THE ABBREVIATION IS A LOOSE RULE, AND KNOWINGLY: by it „Pers." would
 * pass for Peterson. Measured 25.11.2026 it brought together 91 different
 * pairs in 473 citations, and one of them was two people (Veselý and
 * Veselský). Against that stood 470 contradictions that were only a
 * spelling. Whoever tightens it measures again — a last-letter rule would
 * lose „Clç." for Clémençon and „Heilm.-Claus." for Heilmann-Clausen.
 *
 * AND THEREFORE IT IS THE WEAKER YES. GBIF carries some names twice, once
 * clean and once with a letter lost („Svrček" and „Svrek"); the book's
 * „Svrček" is an abbreviation's worth away from the second, and as an
 * equal yes the broken row won wherever GBIF happened to accept it.
 *
 * NOT BELOW THREE LETTERS: „L." is one letter, and one letter sits in
 * almost every name — Linnaeus would agree with Bulliard. „Fr." meets
 * „Fries" through AUTHOR_ALIASES instead.
 */
function author_fit(string $x, string $y): int
{
    if ($x === '' || $y === '') return 0;
    if ($x === $y) return 2;
    if (min(strlen($x), strlen($y)) < 3) return 0;
    if (str_contains($x, $y) || str_contains($y, $x)) return 2;
    [$short, $long] = strlen($x) < strlen($y) ? [$x, $y] : [$y, $x];
    if ($short[0] !== $long[0]) return 0;
    // A letter doubled on one side only: „Dittm." for Ditmar.
    $single = static fn(string $s): string => preg_replace('~(.)\1~', '$1', $s);
    if (strlen($single($short)) >= 3 && (str_contains($single($long), $single($short)) || str_contains($single($short), $single($long)))) return 1;
    if (strlen($short) !== strlen($long)) {
        $i = 0;
        for ($j = 0, $n = strlen($long), $k = strlen($short); $j < $n && $i < $k; $j++) {
            if ($long[$j] === $short[$i]) $i++;
        }
        if ($i === strlen($short)) return 1;
    }
    // ONE LETTER READ WRONG: „Wulren" for Wulfen, „Brirz." for Britzelmayr,
    // „Bondartzev" for Bondartsev. From five letters on — „Berk." and
    // „Beck" are two people, and so are „Pers." and „Peck". AND NOT THE
    // LAST LETTER OF AN ABBREVIATION: „Rostk." is Rostkovius and „Rostaf."
    // Rostafiński, „Larss." not „Larson" — where an abbreviation ends is
    // exactly where two names of one beginning part.
    $k = strlen($short);
    if ($k < 5 || levenshtein($short, substr($long, 0, $k)) !== 1) return 0;
    return $k === strlen($long) || $short[$k - 1] === $long[$k - 1] ? 1 : 0;
}

/**
 * How well two parts name the same people.
 *
 *   2  an author of the one is an author of the other. One shared member is
 *      enough: „Earle ex A.W. Wilson & Desjardin" and „A. Wilson &
 *      Desjardin" share Desjardin; „Kühner & Romagn." and „Konrad &
 *      Maubl." share no one.
 *   1  only through an abbreviation (author_fit), or only through the
 *      sanctioning: „Schaeff. : Fr." in the book, „Fr." in the list. One
 *      name — but where the list also has a row of Schaeffer's, that row
 *      is the nearer one.
 *   0  nobody.
 */
function author_part_grade(string $x, string $y): int
{
    $shared = static function (array $xs, array $ys): int {
        $best = 0;
        foreach ($xs as $p) {
            foreach ($ys as $q) {
                $best = max($best, author_fit($p, $q));
            }
        }
        return $best;
    };
    $best = $shared(author_members($x), author_members($y));
    if ($best > 0) return $best;
    return $shared(array_merge(author_members($x), author_sanctioners($x)),
                   array_merge(author_members($y), author_sanctioners($y))) > 0 ? 1 : 0;
}

/**
 * HOW TWO AUTHOR CITATIONS STAND TO EACH OTHER — in one word.
 *
 * (25.11.2026: „Der Autor ist der Schlüssel zu allem. Wir können nicht
 * Artennamen vergleichen ohne den Autoren.") A name is the same name only
 * where its authors are; so the comparison says not merely yes or no but
 * WHERE two citations part, because the kinds weigh differently:
 *
 *   same       both citations are built alike and every part agrees
 *   part       they agree, but not to the letter: the book cites
 *              „Britz." where the list has „(Britzelm.) Sacc.", the list
 *              has only the sanctioner of „(Pers. : Fr.)", or one side
 *              abbreviates („Lge." for Lange). Enough to call it the same
 *              name — and less than `same` where a second row of the list
 *              fits to the letter
 *   none       one side has no author at all, or the two name different
 *              halves of a citation — nothing to hold against each other
 *   combining  the same first author, the combination credited to another:
 *              „(Fr.) Quél." against „(Fr.) Singer"
 *   sanction   the same combining author; one side cites Fries where the
 *              other has an author before him (AUTHORS_BEFORE_FRIES says
 *              why that is the old way of citing and no second author)
 *   basionym   the same combining author, another first author — two
 *              opinions on what the name rests on: „Meruliopsis violacea
 *              (O.F. Müll.) Bondartsev" in a book, „(Relhan) Bondartsev"
 *              in the list, and behind them two different fungi
 *   differs    nothing agrees — a homonym, or a field that holds no author
 *
 * A citation with ONE part — no bracket, or only the bracket, cut short —
 * is held against both parts of the other, since the popular books cite
 * now the author of the epithet and now the one of the combination.
 */
function author_compare(?string $a, ?string $b): string
{
    [$ba, $ca] = author_plain($a);
    [$bb, $cb] = author_plain($b);
    if ($ba . $ca === '' || $bb . $cb === '') return 'none';

    // An author before Fries, abbreviated or written out („Persoon", „Schaeffer").
    $old = static function (string $x): bool {
        foreach (author_members($x) as $m) {
            foreach (AUTHORS_BEFORE_FRIES as $o) {
                if ($m === $o || strlen($o) >= 3 && str_starts_with($m, $o)) return true;
            }
        }
        return false;
    };
    $before = static fn(string $x, string $y): bool => author_members($x) === ['fr'] && $old($y);
    $sanction = static fn(string $x, string $y): bool => $before($x, $y) || $before($y, $x);
    /* WHOM FRIES TOOK THE NAME FROM, cited two ways. „Albatrellus cristatus
     * (Pers. : Fr.) Kotl. & Pouzar" in Moser, „(Schaeff.) Kotl. & Pouzar"
     * in the lists: the old books name the author Fries cites, the lists
     * the one who described it first. Fries stands in the one bracket, an
     * author before him in the other — and the combination is one, made
     * once by the same people. ONLY BEHIND A SHARED COMBINING AUTHOR: two
     * bare citations, „Schaeff. : Fr." and „Bull.", may well be two
     * Agaricus of one name. */
    $fries = static fn(string $x): bool => in_array('fr', array_merge(author_members($x), author_sanctioners($x)), true);
    $taken = static fn(string $x, string $y): bool => $fries($x) && $old($x) && $old($y) || $fries($y) && $old($y) && $old($x);

    if ($ba === '' || $ca === '' || $bb === '' || $cb === '') {
        $best = 0;
        foreach (array_filter([$ba, $ca]) as $x) {
            foreach (array_filter([$bb, $cb]) as $y) {
                $best = max($best, author_part_grade($x, $y));
            }
        }
        $alike = ($ba === '') === ($bb === '') && ($ca === '') === ($cb === '');
        if ($best > 0) return $alike && $best === 2 ? 'same' : 'part';
        // Only the bracket here, no bracket there: the one names the first
        // author, the other perhaps the second — „Entoloma (Fries)" in a
        // book and „Entoloma P. Kumm." in the list do not contradict.
        if (!$alike && ($ca === '' || $cb === '') && ($ba === '' || $bb === '')) return 'none';
        // Two citations without a bracket: „Agaricus x Fr." in the old way, „Pers." in today's.
        if ($ba . $bb === '' && $sanction($ca, $cb)) return 'sanction';
        // „Russula emetica Fr." in a book, „(Schaeff.) Pers." in the list:
        // the bare Fries of the old books against a name that rests on an
        // author before him — the same old way of citing, seen from a
        // citation that says nothing else.
        $bare = static fn(string $b, string $c, string $other): bool => $b === '' && author_members($c) === ['fr'] && $old($other);
        return $bare($ba, $ca, $bb) || $bare($bb, $cb, $ba) ? 'sanction' : 'differs';
    }

    $basionym = author_part_grade($ba, $bb);
    $combining = author_part_grade($ca, $cb);
    if ($basionym && $combining) return $basionym + $combining === 4 ? 'same' : 'part';
    if ($basionym) return 'combining';
    if ($combining) return $sanction($ba, $bb) || $taken($ba, $bb) ? 'sanction' : 'basionym';
    // „Melanoleuca humilis (Fr.) Singer" against „(Pers.) Pat.": the first
    // author is the same in the old way of citing, only the combination is
    // credited to another — no more than `combining`.
    return $sanction($ba, $bb) || $taken($ba, $bb) ? 'combining' : 'differs';
}

/**
 * Do two author citations name the same people — yes, no, or not to say.
 *
 * Yes where author_compare() finds them agreeing, wholly or as far as both
 * speak; every contradiction is a no here, the old way of citing included.
 * Who wants to weigh the kinds asks author_compare() itself.
 *
 * @return ?bool null where there is nothing to compare
 */
function author_agrees(?string $a, ?string $b): ?bool
{
    $how = author_compare($a, $b);
    return $how === 'none' ? null : in_array($how, ['same', 'part'], true);
}
