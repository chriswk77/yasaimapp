-- THE MEDIUM A MICROGRAPH WAS TAKEN IN (04.11.2026: „was ich
-- gerne noch für jedes Mikrobild mitspeichern würde, wäre das Medium, in
-- dem es mikroskopiert wurde … H2O … KOH 3 % … Baumwollblau"). Several at
-- once — water and KOH, water and cotton blue — so a list, kept as JSON
-- of stable keys (assets/js/media.js), never of the words a user reads:
-- the words are translated, a key stays. The microscope window ticks
-- them in its sidebar and sends them with every photo and stack.
--
-- ON BOTH PICTURE TABLES, as scale and measures are: a micrograph mostly
-- lands as a cloud recording, but a voucher photo is a picture too
-- („Bilder sind Bilder"), and the plates read both.
--
-- NULL is „nicht angegeben", the same for every picture taken before.

ALTER TABLE map_cloud_files ADD COLUMN media VARCHAR(500) NULL AFTER measures;
ALTER TABLE map_photos ADD COLUMN media VARCHAR(500) NULL AFTER measures;
