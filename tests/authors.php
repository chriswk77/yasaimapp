<?php
/**
 * Tests the comparison of author citations (includes/tax_authors.php).
 *
 *   php tests/authors.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   A NAME IS THE SAME NAME ONLY WHERE ITS AUTHORS ARE — so every case here
 *   is one a book really prints against what a list really has, and the
 *   comparison must say WHERE two citations part, not merely that they do.
 *
 *   A CITATION READ WRONG IS A CONTRADICTION THAT IS NONE. Measured
 *   25.11.2026 over 38,740 names, more than half of all contradictions were
 *   of that kind: the place of publication behind the author, a year in a
 *   bracket of its own, the sanctioning, an old abbreviation. Each of those
 *   stands here once — whoever changes the reading sees what he breaks.
 *
 *   AND THE OTHER WAY ROUND: what must stay a contradiction. An abbreviation
 *   rule that lets Linnaeus pass for Bulliard helps nobody.
 *
 * No database: the citations are written out.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/tax_authors.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** One case: a citation as a book prints it, one as a list has it, and the word between them. */
function compare(string $expected, string $book, string $list, string $why): void
{
    $got = author_compare($book, $list);
    check($got === $expected, "$expected: „{$book}“ against „{$list}“ — $why" . ($got === $expected ? '' : " (got $got)"));
    // The comparison has no first and no second side.
    $back = author_compare($list, $book);
    if ($back !== $got) {
        check(false, "the same the other way round: „{$list}“ against „{$book}“ (got $back)");
    }
}

echo "The same citation in two spellings:\n";
compare('same', '(Fr.) P. Karst.', '(Fr.) P.Karst.', 'the space after the initial');
compare('same', '(J.E. Lange) Singer', '(J.E.Lange) Singer', 'initials');
compare('same', 'Örstadius & E. Larss.', 'Örstadius, E.Larss.', 'an ampersand against a comma');
compare('same', '(Moser ex Redhead) Redhead et al.', '(M.M.Moser) Redhead, Moncalvo, Vilgalys & Lutzoni', '„et al." for the co-authors, „ex" as a team');
compare('same', '(Schroet.) Kumm.', '(J.Schröt.) P.Kumm.', 'the umlaut written out');

echo "\nWhat a book prints beside the author:\n";
compare('same', '(Fr.) Boud., Hist. Class. Discom. Eur.: 104 (1907)', '(Fr.) Boud.', 'the place of publication');
compare('same', '(Schaeff. : Fr.) Corner (1950), Ann. Bot. Mem. 1: 618', '(Schaeff.) Corner', 'a year in its own bracket, then volume and page');
compare('same', '(Fr. : Fr.) N. Lundq. & J.C. Krug (in Lundqvist, 1972)', '(Fr.) N.Lundq. & J.C.Krug', '„in" somebody\'s paper');
compare('same', '(Fries 1821) Gillet 1878', '(Fr.) Gillet', 'years, and Fries written out');
compare('same', '[Pers. 1799 (1800) : Fr.] P. Kumm. 1871', '(Pers.) P.Kumm.', 'square brackets with a year bracket inside');
compare('same', 'Fr. [= M. phalloides (Bull.) Chev.]', 'Fr.', 'a synonym in a bracket');
compare('same', '(Linné 1753 : Fries 1821) Roussel', '(L.) Roussel', 'Linnaeus written out');
compare('same', '(Schaeff.: Fr.) S. F. Gray var. silvestris Sing. 1932', 'Singer', 'the variety\'s author stands behind its epithet');
compare('same', '(Persoon : Fries) Fries var. acutus', '(Pers.) Fr.', 'a variety nobody is named for is the species\' own');
compare('same', 'Schulz. ap. Kalchbr.', 'Schulzer', '„apud" like „in"');
compare('none', 'ad int.', 'Bidaud & Reumaux', 'a status remark is no author');
compare('none', 'spec. nov.', 'Consiglio', 'neither is „spec. nov."');

echo "\nThe old abbreviations:\n";
compare('same', 'R. Hry.', 'Rob.Henry', 'Hry. for Henry, whom GBIF calls Rob.Henry — a known spelling');
compare('same', '(R. Mre.) Sing.', '(Maire) Singer', 'Mre. for Maire, Sing. for Singer');
compare('same', '(A. & S.: Fr.) Fr.', '(Alb. & Schwein.) Fr.', 'A. & S. for Albertini & Schweinitz');
compare('same', 'A.H. Smith', 'A.H.Sm.', 'Smith written out');
compare('part', 'Lge.', 'J.E.Lange', 'Lge. for Lange: an abbreviation is a yes, but the weaker one');
compare('part', '(Schff.) Fr.', '(Schaeff.) Fr.', 'Schff. for Schaeffer');
compare('part', '(Krbh.) Cke.', '(Krombh.) Cooke', 'Krbh. and Cke.');
compare('part', 'Svrček', 'Svrek', 'a letter the list lost — the clean row beside it must win');
compare('same', 'Svrček', 'Svrcek', 'the same without its diacritic');

echo "\nAgreeing, but one side says less:\n";
compare('part', 'Britz.', '(Britzelm.) Sacc.', 'the book cites the author of the epithet alone');
compare('part', 'Quél.', '(Fr.) Quél.', 'the book cites the combining author alone');
compare('part', '(Pers. : Fr.) Donk', '(Fr.) Donk', 'the list cites the sanctioner alone');
compare('part', 'Schaeff.: Fr.', 'Fr.', 'the same without a bracket');
compare('same', 'Schaeff.: Fr.', 'Schaeff.', 'the author before the colon is the name\'s — a sanctioner left out is no difference');
compare('part', '(Kauffm.)', 'Kauffman', 'a citation cut down to its bracket');
compare('none', '(Fries 1838)', 'P.Kumm., 1871', 'only the bracket against no bracket: two halves, nothing to compare');

echo "\nWhere two citations part:\n";
compare('combining', '(Fr.) Quél.', '(Fr.) Singer', 'the same first author, another combination');
compare('basionym', '(O.F. Müll.) Bondartsev', '(Relhan) Bondartsev', 'the same combination, another first author');
compare('sanction', '(Fr.) Kumm.', '(Bolton) P.Kumm.', 'Fries for an author before him: the citing of before 1981');
compare('sanction', 'Fries', 'Pers.', 'the same without a bracket');
compare('basionym', '(Fr.) Singer', '(P.Karst.) Singer', 'Fries against an author AFTER him is a contradiction');
compare('differs', 'Fr.', 'Quél.', 'and so it is without a bracket');
compare('differs', 'Bull.', 'Pilát', 'two authors, one name: a homonym');
compare('differs', '(Kühner & Romagn.) Bon', '(Konrad & Maubl.) Singer', 'two teams that share nobody');

echo "\nWhat must not agree:\n";
compare('differs', 'L.', 'Bull.', 'one letter sits in every name — Linnaeus is not Bulliard');
compare('differs', 'Fr.', 'Fayod', 'two letters are no abbreviation');
compare('differs', 'Mre.', 'Murrill', 'an abbreviation needs all its letters in order');
compare('differs', 'ss. auct.', '(Pers.) Fr.', 'a list row that is only a sensu clause agrees with nobody');
compare('none', '', '(Pers.) Fr.', 'no author, nothing to say');

echo "\nYes, no, or not to say:\n";
check(author_agrees('(Fr.) Kumm.', '(Fr.) P.Kumm.') === true, 'the same citation agrees');
check(author_agrees('Britz.', '(Britzelm.) Sacc.') === true, 'and so does one that says less');
check(author_agrees('(Fr.) Kumm.', '(Bolton) P.Kumm.') === false, 'the old way of citing is a no — whoever weighs it asks author_compare()');
check(author_agrees('(O.F. Müll.) Bondartsev', '(Relhan) Bondartsev') === false, 'another first author is a no');
check(author_agrees(null, 'Fr.') === null, 'without an author there is no answer');

echo "\nThe old comparison of tax_link.php is untouched:\n";
check(authorMatches('P. Karst.', 'P.Karst.') === true, 'containment of the letters');
check(authorMatches('(Pers. : Fr.) P. Karst.', '(Pers.) P.Karst.') === false, 'and its known blind spot, which the link table rests on');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
