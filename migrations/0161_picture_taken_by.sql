-- WHO TOOK THE PICTURE, WHERE IT IS NOT THE ROW'S OWN USER
-- (09.10.2026: „Fotograf einbauen").
--
-- When a friendship ends, a shared find splits, and each side keeps the
-- other's pictures where its own cloud held a copy: the copy becomes a
-- recording of the one who keeps it (point_owners_split). The row's user
-- is then the keeper — and without this column the name of the person who
-- took the picture would be gone from it. For copyright the picture keeps
-- its photographer's name; the rights on the row stay the keeper's.
--
-- NULL means: taken by the row's user, as for every picture until now.
ALTER TABLE map_cloud_files
    ADD COLUMN taken_by VARCHAR(120) NULL DEFAULT NULL
        COMMENT 'name of who took it when not the row user (a copy kept after a split); NULL = the row user';
