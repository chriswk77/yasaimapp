-- Rights on shares — stage 3 of sharing.
--
-- WHO GRANTS: the sharer, not the container owner. The points belong to
-- the one who set them — so he also decides what the other members may do
-- with them. The container owner only manages WHO is in, not what each may
-- do.
--
-- AS A LIST OF GRANTED KEYS, not as a level number: "level 3 of 5" every
-- reader would first have to look up, and between two levels there would
-- always lie exactly the right somebody wants to grant individually. The
-- keys:
--
--   verschieben       change the position (drag the marker)
--   loeschen          into the OWNER'S trash (never final)
--   titel             edit the title
--   notiz             edit the note
--   bilder_loeschen   remove voucher photos
--   feld:<Name>       fill in the extra field <Name> of the root list
--
-- NULL MEANS READ ONLY — the whole stock from stage 2 thereby stays
-- exactly what it was. There is no right "create points": points only come
-- into being in one's own lists, the container is a bracket (see migration
-- 0042).
ALTER TABLE map_container_listen
    ADD rechte JSON NULL
        COMMENT 'gewährte Schlüssel als Liste, NULL = nur lesen'
        AFTER user_id;
