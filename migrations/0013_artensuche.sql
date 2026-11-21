-- Species search: full-text index over scientific and German names.
--
-- WHAT THE SEARCH MUST BE ABLE TO DO
-- One types fragments, not names: "bol edu" is supposed to find Boletus
-- edulis, "bol stein" likewise — there the first fragment hits the
-- scientific name, the second the German one. Both mixed, in any order,
-- and each fragment only as a word prefix.
--
-- WHY FULL TEXT AND NOT LIKE
-- With LIKE, every fragment would need a leading percent sign to hit the
-- second word too — and with that, every index drops out. With 39,000 rows
-- that would still work, with the 397,000 from GBIF no longer. MySQL's
-- full-text search in BOOLEAN MODE, by contrast, can do exactly what is
-- wanted: "+bol* +edu*" means "both fragments must occur as word prefixes",
-- and for that there is an index.
--
-- suchtext merges both names, hyphens become spaces. Otherwise
-- "Birken-Steinpilz" would be a single word and "stein" would not hit it.
--
-- Fragments too short are not found by the index: innodb_ft_min_token_size
-- usually stands at 3. Two letters must therefore be handled differently by
-- the application — it does so by only searching from three characters on.

ALTER TABLE map_tax_dgfm
    ADD suchtext VARCHAR(600) NULL COMMENT 'wissenschaftlicher und deutscher Name, für die Volltextsuche';

UPDATE map_tax_dgfm
   SET suchtext = TRIM(CONCAT_WS(' ',
           REPLACE(binomial, '-', ' '),
           REPLACE(COALESCE(deutscher_name, ''), '-', ' ')
       ));

ALTER TABLE map_tax_dgfm
    ADD FULLTEXT KEY ft_suchtext (suchtext);

-- Be searchable via GBIF too, for everything the German list does not carry.
ALTER TABLE map_tax_gbif
    ADD FULLTEXT KEY ft_kanonisch (kanonisch);
