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
 * An author citation reduced to what names the people.
 *
 * The books print what the lists leave out and the other way round:
 * Gröger and Funga Nordica keep the sanctioning („Fr. : Fr.", „(Schaeff.:
 * Fr.)"), years („(Fr. 1815)"), status remarks („inval.", „ad int.",
 * „nom. prov."), sensu clauses („ss. J.E. Lange") and „& al." for a list of
 * co-authors; GBIF has none of that and writes „P.Karst." without the
 * space. What remains here: basionym author and combining author, „ex"
 * reduced to the one who validated, letters only, diacritics folded.
 *
 * @return array{0:string,1:string} [basionym part, combining part]
 */
function author_plain(?string $a): array
{
    $a = (string) $a;
    // Sensu clauses and status remarks are not part of the author.
    $a = preg_replace('~\s+(non\s+)?(ss\.|(?-i:s\.)|sensu|s\.\s*l\.|s\.\s*str\.|auct\.|p\.\s*p\.|nom\.\s*\w+\.?|inval\.|ad\s+int\.|comb\.\s*\w+\.?|emend\.).*$~iu', '', $a);
    // The sanctioning: „Fr. : Fr.", „(Batsch : Fr.)", „Pers.: Fr.".
    $a = preg_replace('~\s*:\s*[^()&,]+~u', '', $a);
    // „A in B": A is the author, B only the book it stood in.
    $a = preg_replace('~\s+in\s+[^()]*~u', '', $a);
    // „A ex B": some lists keep A, some B, some both — both stay, as a team.
    $a = preg_replace('~\s+ex\s+~u', ' & ', $a);
    // Initials go: „J.-E. Gilbert" and „E.-J. Gilbert", „P. Karst." and
    // „P.Karst." — but not an author who IS one letter („L.", „(L.) Fr.").
    $a = preg_replace('~\b\p{Lu}\.(?=\s*-?\s*\p{L})~u', '', $a);
    // „& al.", „et al." stand for co-authors the other side may spell out.
    $a = preg_replace('~\s*(&|et)\s*al\.?~u', '', $a);
    $a = preg_replace('~\d+~', '', $a);

    $fold = static function (string $s): string {
        $s = mb_strtolower($s);
        $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ø' => 'o', 'æ' => 'ae', 'å' => 'a', 'ß' => 'ss']);
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        $t = preg_replace('~[^a-z&,]~', '', $t === false ? $s : $t);
        // „Schroet." and „Schröt." — the umlaut written out folds the same way.
        return str_replace(['ae', 'oe', 'ue'], ['a', 'o', 'u'], $t);
    };

    if (preg_match('~^\s*\((.*)\)\s*(.*)$~u', $a, $m)) {
        return [$fold($m[1]), $fold($m[2])];
    }
    return ['', $fold($a)];
}

/**
 * Do two author citations name the same people — the thorough comparison.
 *
 * The combining author decides: it is the one that tells Agaricus edulis
 * Bull. from Agaricus edulis Pilát. Within it, the whole team or one
 * member of it must agree (containment, so „Örstadius & E. Larss." meets
 * „Örstadius, E.Larss."); the basionym part only has to not contradict
 * where both carry one.
 *
 * @return ?bool null when one side has no author at all
 */
function author_agrees(?string $a, ?string $b): ?bool
{
    [$ba, $ca] = author_plain($a);
    [$bb, $cb] = author_plain($b);
    if ($ca === '' && $ba === '' || $cb === '' && $bb === '') return null;

    // One shared member is enough: „Earle ex A.W. Wilson & Desjardin" and
    // „A. Wilson & Desjardin" share Desjardin; „Kühner & Romagn." and
    // „Konrad & Maubl." share no one.
    $members = static fn(string $s): array => array_filter(preg_split('~[&,]~', $s));
    $fits = static function (string|array $x, string|array $y) use (&$fits): bool {
        if (is_array($x) || is_array($y)) {
            foreach ((array) $x as $p) foreach ((array) $y as $q) if ($fits($p, $q)) return true;
            return false;
        }
        if ($x === '' || $y === '') return false;
        if ($x === $y) return true;
        // „L." is one letter, and one letter sits in almost every name —
        // Linnaeus would agree with Bulliard. Containment only from three.
        if (min(strlen($x), strlen($y)) < 3) return false;
        return str_contains($x, $y) || str_contains($y, $x);
    };
    $clean = static fn(string $s): string => str_replace(['&', ','], '', $s);

    // A citation cut short to its bracket („(M.M. Moser ex M.M. Moser &
    // Singer)" in Gröger II) can only speak through the basionym part.
    if ($ca === '' || $cb === '') {
        if ($ba === '' || $bb === '') return null;
        return $fits($clean($ba), $clean($bb)) || $fits($members($ba), $members($bb));
    }

    $combining = $fits($clean($ca), $clean($cb)) || $fits($members($ca), $members($cb));
    if (!$combining) return false;
    if ($ba !== '' && $bb !== '') {
        return $fits($clean($ba), $clean($bb)) || $fits($members($ba), $members($bb));
    }
    return true;
}
