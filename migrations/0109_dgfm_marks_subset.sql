-- A genus claim that means only part of the genus (18.09.2026:
-- „Russula nigricans ist kein Giftpilz, es ist sogar ein Speisepilz" —
-- and Russula ionochlora the same). The poisonous list closes many genera
-- with a „spp." row, and some of those rows say in their remark that
-- they mean a subset: „Russula spp. — scharfe und bittere Arten",
-- „Clitocybe spp. — weitere verdächtige Arten", „Lepiota spp. — kleine
-- Schirmlinge mit bräunlichen und roten Pigmenten". The list does not
-- name those species, so no mark can be set from such a row without
-- inventing one for every mild Russula. 'subset' keeps the row in the
-- table, visible in the administration, and out of every lookup;
-- 'genus' stays for the rows that mean the whole genus (Galerina spp.,
-- Scleroderma spp. — „alle Arten sind verdächtig", Morchella spp. — „roh
-- sehr giftig"). Decided at import (dgfm_genus_scope in dgfm_marks.php).
ALTER TABLE map_dgfm_marks
    MODIFY matched ENUM('species', 'genus', 'subset', 'none') NOT NULL;
