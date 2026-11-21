-- How precisely a finder hands their finds to the DGfM
-- (25.09.2026: „jeden Benutzer selbst entscheiden lassen, wie genau er
-- seine Daten abgeben will — mit dem Rechenbeispiel, wie groß so ein
-- Quadrat ist, dass man sieht, wie weit verunschärft der Fund wird").
--
-- 'exact' sends the coordinates (degrees, minutes, seconds — some three
-- metres) and the MTB to the sixty-fourth; 'q64', 'q16', 'q4' send the MTB
-- to that step and NO coordinates; 'sheet' sends the sheet alone. NULL is
-- 'exact' — the level the coordinators map at, and what a finder who
-- never chose gets; the choice stands right beside the tick that sends.
ALTER TABLE map_users
    ADD mykis_precision VARCHAR(10) NULL COMMENT 'exact, q64, q16, q4 or sheet; NULL = exact';
