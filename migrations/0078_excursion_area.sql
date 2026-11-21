-- The excursion's offline map (24.08.2026): the leader picks
-- one of HIS OWN offline areas, and the guests may load it onto their
-- phones in the onboarding — before the walk, where there is still
-- reception.
--
-- A REFERENCE, NOT A COPY of the outline: the areas exist, they are
-- drawn, edited and measured in the Offline tab, and a second outline
-- here would be the twin that drifts. ON DELETE the reference falls to
-- NULL — an excursion without a map is fine, a dangling id is not.
--
-- THE SIZE IS COMPUTED WHEN THE LEADER SAVES, not on the guest's phone:
-- he decides zoom level and area, so he is the one who should see what
-- he is asking of other people's data plans. Tiles and bytes are an
-- ESTIMATE (25 kB per tile, measured) — stored so the guest's page can
-- name a figure without walking the whole tile grid first.
ALTER TABLE map_excursions
    ADD COLUMN area_id    INT UNSIGNED NULL,
    ADD COLUMN area_zoom  TINYINT UNSIGNED NULL,
    ADD COLUMN area_tiles INT UNSIGNED NULL,
    ADD COLUMN area_bytes BIGINT UNSIGNED NULL,
    ADD KEY idx_excursion_area (area_id);
