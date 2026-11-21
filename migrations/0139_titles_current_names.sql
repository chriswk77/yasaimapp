-- Finds under an old name take the name the map calls current
-- (26.09.2026).
--
-- „Varietäten oder Synonyme standen ganz oben statt eigentlich
-- der aktuelle Name … sowas habe ich natürlich angeklickt. Wir bräuchten
-- eben über den Bestand jetzt eine Migration, die solche Arten wieder auf
-- die akzeptierte Art setzt." Offline, GBIF's names stood above the DGfM's
-- until the afternoon of that day — „Hymenopellis radicata" before „Xerula
-- radicata, aktueller Name zu Hymenopellis radicata" —, and an old name
-- typed out put its variety on top until the evening (species_search, the
-- spelled key). The „Meine Pilze" import brought old names as well: that
-- archive speaks the nomenclature of its years.
--
-- WHICH FINDS. A title the DGfM list carries ONLY as an old name — a
-- synonym row, and no valid row of that name, no species shown under it
-- (GBIF's names, 0106), no valid lichen or plant of it — takes the name
-- the suggestion list shows for its species: GBIF's where the link carries
-- one, else the DGfM's, with the qualifier („Laccaria tetraspora agg.").
-- The title's reference moves to the valid row, and German name and Red
-- List category come with it; the derived species row follows, with the
-- genus species_genus() would file.
--
-- WHAT STAYS, and why:
--
--   * an old name that led to more than one species. „Leccinum
--     aurantiacum" meant L. leucopodium to Pilát and L. quercinum to
--     Šutara; only whoever holds the find can say which. The maintainer holds
--     it for the current name of what the DGfM calls L. quercinum
--     (26.09.2026: „Dieser Auffassung folgt aber noch nicht die Taxref"),
--     and his finds already refer to the row that leads there.
--   * a species whose shown name came through a link to one of its own
--     DGfM synonyms (gbif_via 'dgfm_synonym', 155 names): „Leucoagaricus
--     leucothites" would have become Leucoagaricus subcretaceus — a name
--     GBIF gives to a species of its own. The link wants mending, not the
--     finds.
--   * an old name GBIF keeps as a species of its own — „Armillaria
--     gallica" beside A. lutea, „Peziza micropus" beside P. varia.
--     Renamed, the find would change its species in GBIF's eyes, and with
--     it the cloud folder its pictures go to (cloud_find_from_point files
--     by GBIF's species): the ones taken so far would stay behind in the
--     old folder, since only „Unbestimmt" sorts itself
--     (photo_cloud_refile). That is a decision about the species concept,
--     not about a name, so it is asked here as: old and new reference lead
--     to the same GBIF species.
--     THE 11 ON THE STOCK WERE DECIDED NAME BY NAME (26.09.2026), and
--     five move all the same: Stereum ostrea (to S. insignitum), Leccinum
--     albostipitatum (to L. leucopodium), Hygrophorus persoonii (to H.
--     glutinifer, „nach neueren Papers"), Ophiocordyceps sphecocephala (a
--     species from outside Europe — the DGfM files its European use, „ss.
--     auct. europ.", under O. ditmarii) and Amanita echinocephala („auf
--     jeden Fall eine Solitaria" — shown as Aspidella solitaria, as the
--     species is everywhere since 0106). None of the five carries a picture
--     yet. Staying: Armillaria gallica (a new name he has not met),
--     Lactarius subumbonatus beside L. serifluus („noch getrennt lassen"),
--     Peziza micropus (P. varia long in use, the name may yet be
--     conserved), Pluteus chrysophlebius, and Laccaria laccata var.
--     pallidifolia: the common round-spored Laccaria, which the DGfM files
--     as „Laccaria tetraspora agg." — the same group under Singer's name.
--     Mueller and Funga Nordica call it var. pallidifolia, and Dovana et
--     al. (J. Fungi 2025) kept L. laccata itself for the rare ellipsoid-
--     spored taxon of Singer's epitype from Femsjö; the maintainer keeps the
--     variety's name. And Terana caerulea, a spelling only: Species
--     Fungorum writes Terana coerulea, as the basionym Byssus coerulea Lam.
--     does, and so does the DGfM („coerulea mit OE?" — yes).
--   * a title whose reference block names another list, or more than this
--     one name — nothing here could rewrite it cleanly.
--   * varieties the DGfM keeps as valid: whoever wrote „Marasmiellus
--     ramealis var. macrosporus" may have meant it.
--
-- Applied to the local copy of 24.09.2026 on 26.09.2026: 89 finds — 83
-- under 36 old names, and the maintainer's 6 bare Laccaria laccata. 66 of them
-- are the maintainer's (6 entered in the map, the rest imported), 23 belong to
-- two other accounts. „Hymenopellis radicata" became Xerula radicata 12
-- times, „Hygrocybe ovina" Neohygrocybe ovina 6 times. Checked from the
-- ledger: every block holds exactly the new title and its reference, every
-- other field is untouched, species_reference() resolves each new title to
-- the same species, and no find is left under an old name.
--
-- THE WAY BACK. Every renamed find leaves a row in map_title_renames: the
-- title and the reference block as they stood. Nothing reads it; it is
-- there so that a single find can be put back by hand, and so that the
-- next rename of this kind logs itself the same way. Its rows are also the
-- work list of the statements below, which read the OLD title from there
-- and not from the row they rewrite: MySQL does not promise the order of
-- the assignments in an UPDATE over several tables, and the title changes
-- in the same statement as the block that is found by it.
--
-- touched_at moves, so every device fetches the renamed find; updated_at
-- does not — a nomenclature update is not the user's edit (as in 0107).
-- Plain string functions only: the live server speaks MariaDB (see 0040).

CREATE TABLE IF NOT EXISTS map_title_renames (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    point_id      INT UNSIGNED NOT NULL,
    old_title     VARCHAR(200) NOT NULL,
    new_title     VARCHAR(200) NOT NULL,
    old_species   LONGTEXT     NULL COMMENT 'extra_species as it stood — the way back',
    new_reference VARCHAR(80)  NULL COMMENT 'the title''s reference after the rename; NULL = the find had none',
    migration     VARCHAR(80)  NOT NULL COMMENT 'which rename wrote the row',
    renamed_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY point_id (point_id),
    CONSTRAINT fk_title_renames_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The names, once: every title on the stock that is only an old name, with
-- the name, row and genus it moves to. The qualifier expression is
-- species_qualifier_sql() in includes/species.php, written out.
CREATE TEMPORARY TABLE tmp_title_renames AS
SELECT t.title AS old_name,
       MIN(TRIM(CONCAT_WS(' ', COALESCE(lv.gbif_name, v.binomial),
           CASE
               WHEN v.full_name LIKE '% agg.' THEN 'agg.'
               WHEN v.full_name LIKE '% Gruppe' THEN 'Gruppe'
               WHEN v.full_name LIKE '% s. str.' OR v.full_name LIKE '% s.str.' OR v.full_name LIKE '% s str.' THEN 's. str.'
               WHEN v.full_name LIKE '% s. l.' OR v.full_name LIKE '% s.l.' THEN 's. l.'
               WHEN v.full_name LIKE '% non ss. %' THEN SUBSTRING(v.full_name, LOCATE(' non ss. ', v.full_name) + 1)
               WHEN v.full_name LIKE '% ss. %' THEN SUBSTRING(v.full_name, LOCATE(' ss. ', v.full_name) + 1)
           END))) AS new_name,
       MIN(v.taxnr) AS taxnr,
       MIN(COALESCE(g.genus, v.genus)) AS genus
  FROM (SELECT DISTINCT title FROM map_points WHERE title IS NOT NULL AND title <> '') t
  JOIN map_tax_dgfm s ON s.binomial = t.title AND s.taxnr <> s.sipnr
  JOIN map_tax_dgfm v ON v.taxnr = s.sipnr AND v.taxnr = v.sipnr
  LEFT JOIN map_tax_links ls ON ls.taxnr = s.taxnr
  LEFT JOIN map_tax_links lv ON lv.taxnr = v.taxnr
  LEFT JOIN map_tax_gbif g ON g.gbif_key = lv.gbif_species_key AND lv.gbif_name IS NOT NULL
  LEFT JOIN map_tax_lichens f ON f.binomial = v.binomial AND f.taxnr = f.sipnr
 WHERE NOT EXISTS (SELECT 1 FROM map_tax_dgfm w WHERE w.binomial = t.title AND w.taxnr = w.sipnr)
   AND NOT EXISTS (SELECT 1 FROM map_tax_links lk WHERE lk.gbif_name = t.title)
   AND NOT EXISTS (SELECT 1 FROM map_tax_lichens o WHERE o.binomial = t.title AND o.taxnr = o.sipnr)
   AND NOT EXISTS (SELECT 1 FROM map_tax_plants o WHERE o.binomial = t.title AND o.taxnr = o.sipnr)
 GROUP BY t.title
HAVING COUNT(DISTINCT v.taxnr) = 1
   -- the species yields to the lichen list, as in the search
   AND COUNT(f.taxnr) = 0
   AND SUM(CASE WHEN lv.gbif_name IS NOT NULL AND lv.gbif_via = 'dgfm_synonym' THEN 1 ELSE 0 END) = 0
   -- old and new lead to the same GBIF species, linked both — or the maintainer
   -- moved the name all the same (see above)
   AND (t.title IN ('Stereum ostrea', 'Leccinum albostipitatum', 'Hygrophorus persoonii',
                    'Ophiocordyceps sphecocephala', 'Amanita echinocephala', 'Terana caerulea')
        OR SUM(CASE WHEN ls.gbif_species_key IS NULL OR lv.gbif_species_key IS NULL
                      OR ls.gbif_species_key <> lv.gbif_species_key THEN 1 ELSE 0 END) = 0);

-- The finds, with the way back. The reference block qualifies when it
-- holds exactly this one name with a DGfM reference — read as the text
-- json_encode wrote, '"":{"<title>":"fungi:<number>"}' — or when there is
-- none at all; then only the title changes, and the reference follows at
-- the next save (title_references_sync). A finished run is not repeated:
-- the titles are gone, and a find already logged is not logged twice.
INSERT INTO map_title_renames (point_id, old_title, new_title, old_species, new_reference, migration)
SELECT p.id, p.title, r.new_name, p.extra_species,
       IF(p.extra_species LIKE '%"":{%', CONCAT('fungi:', r.taxnr), NULL),
       '0139_titles_current_names'
  FROM map_points p
  JOIN tmp_title_renames r ON r.old_name = p.title
 WHERE (p.extra_species IS NULL
        OR p.extra_species NOT LIKE '%"":{%'
        OR LOCATE(CONCAT('"":{"', p.title, '":"fungi:',
                         SUBSTRING_INDEX(SUBSTRING_INDEX(p.extra_species, CONCAT('"":{"', p.title, '":"fungi:'), -1), '"', 1),
                         '"}'), p.extra_species) > 0)
   AND NOT EXISTS (SELECT 1 FROM map_title_renames y
                    WHERE y.point_id = p.id AND y.migration = '0139_titles_current_names');

-- AND THE MAINTAINER'S OWN „Laccaria laccata" (26.09.2026). A bare name means
-- the species in the narrow sense since 13.09.2026 (species_reference): the
-- rare ellipsoid-spored L. laccata s. str. of Singer's epitype. His finds
-- were mapped without a microscope and are the common round-spored one —
-- „meine normalkartierten Laccata sind die häufige Form … da sie ja
-- offensichtlich auch nur an wenigen Orten vorkommt" —, so they take the
-- name his other finds of it carry, var. pallidifolia (see above), and the
-- reference that title resolves to. His account only (id 1, on the live
-- server as on its restored local copy): what somebody else's bare name
-- means is theirs to say.
INSERT INTO map_title_renames (point_id, old_title, new_title, old_species, new_reference, migration)
SELECT p.id, p.title, 'Laccaria laccata var. pallidifolia', p.extra_species,
       IF(p.extra_species LIKE '%"":{%',
          (SELECT CONCAT('fungi:', MIN(d.taxnr)) FROM map_tax_dgfm d
            WHERE d.binomial = 'Laccaria laccata var. pallidifolia'), NULL),
       '0139_titles_current_names'
  FROM map_points p
 WHERE p.user_id = 1 AND p.title = 'Laccaria laccata'
   AND (p.extra_species IS NULL
        OR p.extra_species NOT LIKE '%"":{%'
        OR LOCATE(CONCAT('"":{"', p.title, '":"fungi:',
                         SUBSTRING_INDEX(SUBSTRING_INDEX(p.extra_species, CONCAT('"":{"', p.title, '":"fungi:'), -1), '"', 1),
                         '"}'), p.extra_species) > 0)
   AND NOT EXISTS (SELECT 1 FROM map_title_renames y
                    WHERE y.point_id = p.id AND y.migration = '0139_titles_current_names');

UPDATE map_points p
  JOIN map_title_renames x ON x.point_id = p.id AND x.migration = '0139_titles_current_names'
   SET p.extra_species = IF(x.new_reference IS NULL, p.extra_species,
           REPLACE(x.old_species,
                   CONCAT('"":{"', x.old_title, '":"fungi:',
                          SUBSTRING_INDEX(SUBSTRING_INDEX(x.old_species, CONCAT('"":{"', x.old_title, '":"fungi:'), -1), '"', 1),
                          '"}'),
                   CONCAT('"":{"', x.new_title, '":"', x.new_reference, '"}'))),
       p.title = x.new_title,
       p.touched_at = CURRENT_TIMESTAMP(6)
 WHERE p.title = x.old_title;

-- The derived row of the title (map_point_species, field ''): name, genus
-- and reference as point_species_set() would write them now. The genus
-- comes from the list of names; the maintainer's Laccaria have none there and
-- keep theirs, which is the same genus.
UPDATE map_point_species a
  JOIN map_title_renames x ON x.point_id = a.point_id AND x.migration = '0139_titles_current_names'
  LEFT JOIN tmp_title_renames r ON r.old_name = x.old_title
   SET a.name = x.new_title,
       a.genus = COALESCE(r.genus, a.genus),
       a.source = IF(x.new_reference IS NULL, a.source, 'fungi'),
       a.identifier = IF(x.new_reference IS NULL, a.identifier, SUBSTRING(x.new_reference, 7))
 WHERE a.field = '' AND a.name = x.old_title;

DROP TEMPORARY TABLE tmp_title_renames;
