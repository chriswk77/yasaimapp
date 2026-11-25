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
    'thorefries' => 'thfr', 'bk' => 'berk', 'pk' => 'peck',
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
    $a = preg_replace('~\s+(non\s+)?(ss\.|(?-i:s\.)|sensu|s\.\s*l\.|s\.\s*str\.|auct\.|p\.\s*p\.|emend\.).*$~iu', '', $a);
    $a = preg_replace('~(^|\s+)(sp(ec)?\.\s*n(ov)?\.?|n(ov)?\.\s*sp(ec)?\.?|nom\.\s*\w+\.?|inval\.|ad\s+int\.|comb\.\s*\w+\.?).*$~iu', '', $a);
    // „A in B", „A apud B": A is the author, B only the book it stood in.
    $a = preg_replace('~\s+(in|apud|ap\.)\s+[^()]*~u', '', $a);
    // „A ex B": some lists keep A, some B, some both — both stay, as a
    // team. The sanctioning („Pers. : Fr.") keeps its colon and with it its
    // place behind the author: author_members() and author_sanctioners().
    $a = preg_replace('~\s+(ex|et|and|und|e)\s+~u', ' & ', $a);
    // Initials go: „J.-E. Gilbert" and „E.-J. Gilbert", „P. Karst." and
    // „P.Karst." — but not an author who IS one letter („L.", „(L.) Fr.").
    $a = preg_replace('~\b\p{Lu}\.(?=\s*-?\s*\p{L})~u', '', $a);
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
 *      and a letter the print lost („Bourot") passes the same way.
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
    if ($short[0] !== $long[0] || strlen($short) === strlen($long)) return 0;
    $i = 0;
    for ($j = 0, $n = strlen($long), $k = strlen($short); $j < $n && $i < $k; $j++) {
        if ($long[$j] === $short[$i]) $i++;
    }
    return $i === strlen($short) ? 1 : 0;
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

    $before = static fn(string $x, string $y): bool => author_members($x) === ['fr']
        && array_intersect(author_members($y), AUTHORS_BEFORE_FRIES) !== [];
    $sanction = static fn(string $x, string $y): bool => $before($x, $y) || $before($y, $x);

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
        return $ba . $bb === '' && $sanction($ca, $cb) ? 'sanction' : 'differs';
    }

    $basionym = author_part_grade($ba, $bb);
    $combining = author_part_grade($ca, $cb);
    if ($basionym && $combining) return $basionym + $combining === 4 ? 'same' : 'part';
    if ($basionym) return 'combining';
    if ($combining) return $sanction($ba, $bb) ? 'sanction' : 'basionym';
    return 'differs';
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
