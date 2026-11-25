-- A PLATE IS PUBLISHED ON PURPOSE, UNDER A LICENCE (25.11.2026). Until
-- now the public site (includes/public_site.php) showed every plate of its
-- owner by itself. Now the owner decides per plate, in the plate window:
-- „eine Option veröffentlichen, also zur Veröffentlichung freigegeben oder
-- nicht … Beim Veröffentlichen setze ich dort die Lizenz. Und wenn dort
-- Bilder drauf sind von Freunden, dann kann ich das natürlich vorher
-- klären, statt dass es einfach immer automatisch funktioniert".
--
--   published_at   when the plate was released — NULL: not published
--   licence        the licence it went out under; „CC BY-NC 4.0" — the
--                  owner's choice: free to pass on with the name, not for
--                  sale („kommerzielle Nutzung würde ich ausschließen")
--   licence_note   an addition shown with the licence, e.g. a picture
--                  that came with a permission of its own
--   cleared_users  JSON list of the OTHER photographers whose pictures on
--                  the plate the maker confirmed as cleared. A picture by
--                  somebody not on it keeps the plate off the site until
--                  he is confirmed too (plate_publish.php)
--
-- THE STOCK CARRIES OVER. Before this migration the site showed every
-- plate it could, so every existing plate starts as published under the
-- licence — what was public stays public. The rule about other people's
-- pictures holds from the first run on: a plate carrying one is off the
-- site until its maker confirms him. Where no public site exists, the
-- columns mean nothing and nobody sees them.
--
-- THE CODE CAN GO LIVE FIRST: plate_publish_ready() asks for the column;
-- until then the plate window shows no publishing and the site treats
-- every plate as published, except those with other people's pictures.

ALTER TABLE map_plates
    ADD COLUMN published_at  DATETIME     NULL AFTER layout,
    ADD COLUMN licence       VARCHAR(32)  NULL AFTER published_at,
    ADD COLUMN licence_note  VARCHAR(500) NULL AFTER licence,
    ADD COLUMN cleared_users VARCHAR(500) NULL AFTER licence_note;

UPDATE map_plates SET published_at = updated_at, licence = 'CC BY-NC 4.0';
