-- The finder's own rules for how precisely which finds go to the DGfM
-- (27.09.2026: „einen kompletten Bereich Genauigkeit, wo wir
-- filtern können"). One step for everything (mykis_precision, 0129/0130)
-- becomes the step for „alle übrigen Funde", and beside it:
--   - genera and species that are not reported at all;
--   - chosen Red List categories, several at once, with ONE step;
--   - edible mushrooms (the DGfM's list), for whoever does not want to
--     give away his spots;
--   - a list of genera and a list of species, each with a step of its own.
-- Where several rules fit one find, THE COARSEST STEP WINS („am
-- gröbsten, am vorsichtigsten"); includes/mykis.php reads the rules.
--
-- JSON in one column and not a table: the rules are read whole, for one
-- account at a time, and never searched across accounts. NULL means none —
-- then everything goes at mykis_precision, as before.
ALTER TABLE map_users
    ADD mykis_rules TEXT NULL COMMENT 'JSON: the DGfM precision rules — exclusions, Red List, edible, genera, species';
