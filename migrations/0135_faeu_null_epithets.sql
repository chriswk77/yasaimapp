-- Fauna Europaea's second null (26.09.2026).
--
-- 11 678 synonyms carried the text „(null)" as their specific epithet:
-- 8 039 insects, 3 008 molluscs, 300 other animals, 273 arachnids,
-- 48 mammals, 10 birds. The archive writes a missing value as \N and, in
-- these rows, as „(null)", and admin/faeu_import.php knew only the first.
-- All of them are synonyms of subspecies: old species-rank combinations
-- that Fauna Europaea hangs on the nominate subspecies — Phalaena quercus
-- under Lasiocampa quercus quercus —, with the epithet in the
-- infraspecific column. So the binomial read „Phalaena (null) quercus",
-- and every suggestion that went through one said so: „über Phalaena
-- (null) quercus".
--
-- The name is genus and epithet, and the epithet column holds nothing, as
-- the archive meant. search_text is the binomial itself (the import writes
-- it so). The import reads „(null)" as null since; this is the same for
-- the stock. What travels in the animal packages changes without the row
-- count moving — species_packages.php counts their revision up for that,
-- in a push of its own after this ran.
UPDATE map_tax_faeu
   SET binomial    = REPLACE(binomial, ' (null) ', ' '),
       search_text = REPLACE(search_text, ' (null) ', ' '),
       epithet     = NULL
 WHERE epithet = '(null)';
