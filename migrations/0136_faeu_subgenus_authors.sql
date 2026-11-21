-- Fauna Europaea's subspecies get their authors back (26.09.2026).
--
-- 6 713 valid subspecies carried parts of their own name before the
-- author: „(Lasiocampa) quercus quercus (Linnaeus, 1758)" for Lasiocampa
-- quercus quercus, „(Macropoecilus) sericeus transpyrenaeus (Breit, 1933)"
-- for Poecilus sericeus transpyrenaeus — 5 422 insects, 1 044 molluscs,
-- 147 arachnids, 100 other animals. The archive writes the subgenus into
-- the full name of a subspecies but leaves its subgenus column empty, and
-- admin/faeu_import.php, walking the full name part by part, expected no
-- subgenus and stuck on it. The import steps over it since; this is the
-- same for the stock.
--
-- What comes off is exactly what was stuck: the bracketed first word and
-- the name's own epithets behind it, as the binomial spells them — nothing
-- is cut where an author merely begins with a bracket. None is left empty.
-- Plain string functions and no REGEXP_LIKE: the live server speaks
-- MariaDB (see 0040).
UPDATE map_tax_faeu
   SET author = NULLIF(TRIM(SUBSTRING(author, CHAR_LENGTH(CONCAT(
                    SUBSTRING_INDEX(author, ' ', 1), ' ',
                    SUBSTRING(binomial, CHAR_LENGTH(genus) + 2), ' ')) + 1)), '')
 WHERE SUBSTRING_INDEX(author, ' ', 1) LIKE '(%)'
   AND author LIKE CONCAT(SUBSTRING_INDEX(author, ' ', 1), ' ',
                          SUBSTRING(binomial, CHAR_LENGTH(genus) + 2), ' %');
