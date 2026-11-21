-- THE WEB ADDRESSES OF A SPECIES (10.10.2026: „Können wir das
-- einfach mal in die Datenbank schon bei uns aufnehmen? Ich sage dir dann
-- später, wo ich es gerne haben würde … beim Schlüsseln, wenn man bei einer
-- Art rauskommt … Internet-Links, so ein bisschen kategorisiert").
--
-- What Pilz-Link, the maintainer's earlier project, gathered over years: the
-- portrait pages and distribution maps that exist for a species on some
-- thirty sites — Artsdatabanken, Mushroom Observer, 123pilze, Artfakta,
-- Taunuspilz … These cannot be derived from the name: they carry the
-- site's own ids, and above all they say FOR WHICH SPECIES THERE IS A PAGE
-- AT ALL. The export comes keyed to the GBIF key and the DGfM number,
-- which our own taxonomy speaks (map_tax_gbif, map_tax_dgfm).
--
-- ONLY THE STORED HALF lives here. The links that follow from the name
-- alone (Google, GBIF map, pilze-deutschland.de …) are written by
-- includes/web_links.php and stored nowhere.
--
-- Replaced as a whole on every import (Administration → Artlinks): the
-- file is the truth, a row of an older export that is no longer in it is
-- gone from the source too.

CREATE TABLE map_web_links (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    gbif_key   INT UNSIGNED NULL DEFAULT NULL,
    dgfm_taxnr INT UNSIGNED NULL DEFAULT NULL,
    name       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'scientific name as the source knew it — the last way to couple',
    source     VARCHAR(40)  NOT NULL COMMENT 'the site, as Pilz-Link called its table (artsdatabanken, pilz123 …)',
    category   VARCHAR(40)  NOT NULL COMMENT 'Porträt, Verbreitungskarte … — as Pilz-Link grouped them',
    label      VARCHAR(80)  NOT NULL,
    sublabel   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'several pages of one site: Makro/Mikro, 1, 2 …, a language',
    url        VARCHAR(700) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_web_links_gbif (gbif_key),
    KEY ix_web_links_dgfm (dgfm_taxnr),
    KEY ix_web_links_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
