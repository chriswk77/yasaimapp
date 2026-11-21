-- The plant list names its aggregates, sensu qualifiers and subgenera in
-- the name, not in the author (26.09.2026).
--
-- includes/bfn_list.php split a name after its second word and took the
-- rest for the author, so parts of names ended up there, and 221 valid
-- names of the BfN plant list stood twice:
--
--   * 229 valid names and 2 synonyms by the author „agg." — „Arum
--     maculatum" by „agg." (German „Gefleckter Aronstab (Artengruppe)")
--     beside „Arum maculatum" by „L.";
--   * 4 with the sensu qualifier behind the author — „Barbarea vulgaris" by
--     „L., s. l." beside the same by „R. Br., s. str.";
--   * 21 subgenera, sections and series, their marker taken for the
--     epithet — „Rubus sect." by „Corylifolii Lindl.", „Hieracium subg." by
--     „Pilosella" —, and 5 hybrids whose sign stands free: „Sorbus x" by
--     „ambigua" beside „Sorbus x" by „schinzii".
--
-- Online each pair collapsed into one suggestion whose fields came from
-- either row (the author „L." with the German name of the aggregate);
-- offline the first row took the name. The import names them since; this
-- is the same for the stock — as the DGfM list has named its aggregates
-- since 13.09.2026 („Xerocomellus chrysenteron agg."). After it no valid
-- name stands twice.
--
-- search_text is rebuilt as the import writes it — the name and the German
-- one, hyphens as spaces — so that typing „agg" or „pilosella" finds the
-- row. IT STANDS FIRST IN EVERY SET LIST ON PURPOSE: MySQL and MariaDB
-- assign from left to right, and a later assignment reads the earlier
-- one's new value; behind the binomial it would have read „… agg. agg.".
-- Plain string functions only: the live server speaks MariaDB (see 0040).
UPDATE map_tax_plants
   SET search_text = REPLACE(TRIM(CONCAT(binomial, ' agg. ', COALESCE(german_name, ''))), '-', ' '),
       binomial    = CONCAT(binomial, ' agg.'),
       author      = NULL
 WHERE author = 'agg.';

UPDATE map_tax_plants
   SET search_text = REPLACE(TRIM(CONCAT(binomial, ' s. l. ', COALESCE(german_name, ''))), '-', ' '),
       binomial    = CONCAT(binomial, ' s. l.'),
       author      = NULLIF(TRIM(SUBSTRING(author, 1, CHAR_LENGTH(author) - CHAR_LENGTH(', s. l.'))), '')
 WHERE author LIKE '%, s. l.';

UPDATE map_tax_plants
   SET search_text = REPLACE(TRIM(CONCAT(binomial, ' s. str. ', COALESCE(german_name, ''))), '-', ' '),
       binomial    = CONCAT(binomial, ' s. str.'),
       author      = NULLIF(TRIM(SUBSTRING(author, 1, CHAR_LENGTH(author) - CHAR_LENGTH(', s. str.'))), '')
 WHERE author LIKE '%, s. str.';

UPDATE map_tax_plants
   SET search_text = REPLACE(TRIM(CONCAT(binomial, ' ', SUBSTRING_INDEX(author, ' ', 1), ' ',
                                         COALESCE(german_name, ''))), '-', ' '),
       binomial    = CONCAT(binomial, ' ', SUBSTRING_INDEX(author, ' ', 1)),
       author      = NULLIF(TRIM(SUBSTRING(author, CHAR_LENGTH(SUBSTRING_INDEX(author, ' ', 1)) + 1)), '')
 WHERE (binomial LIKE '% subg.' OR binomial LIKE '% sect.' OR binomial LIKE '% ser.'
        OR binomial LIKE '% x' OR binomial LIKE '% ×')
   AND author IS NOT NULL AND author <> '';
