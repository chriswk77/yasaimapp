-- The genus of every name beginning with X, recovered (found 25.08.2026
-- while looking into the maintainer's „Xerocomus spec.").
--
-- WHAT WAS BROKEN sat in species_genus(): the hybrid marker was stripped
-- with ltrim over the character SET '×xX ', and ltrim knows no words. It
-- ate the leading X of the name itself — „Xerocomus spec." became
-- „erocomus", failed the capital-letter test that follows, and the find
-- came out with NO GENUS AT ALL. Measured on the day it was found: 298
-- names in the DGfM list and 3.724 in the GBIF backbone begin with X.
-- Xylaria, Xeromphalina, Xerula, Xerocomus — every one of them, silently,
-- for as long as the function has existed.
--
-- THE CODE IS FIXED, but the fix only reaches what is written from now
-- on: map_point_species is rewritten when a point is saved, not on
-- reading. Nobody is going to open every X-find and press save, so the
-- stock is repaired here.
--
-- ONLY THE FALLBACK CASE, and it identifies itself: a genus that came out
-- of a reference table is never empty. An empty genus on a name starting
-- with X is exactly the damage this bug did.
--
-- BINARY 'X' ON PURPOSE. The comparison would otherwise be
-- case-insensitive and would also catch „xerocomus edulis" written in
-- lower case — and a lower-case name gets no genus by the rule of the
-- function, before this bug and after it. What the code refuses, the
-- migration must not hand out.
UPDATE map_point_species
   SET genus = TRIM(TRAILING '.' FROM SUBSTRING_INDEX(name, ' ', 1))
 WHERE (genus IS NULL OR genus = '')
   AND LEFT(name, 1) = BINARY 'X'
   AND SUBSTRING(name, 2, 1) REGEXP '[a-zA-Z]';
