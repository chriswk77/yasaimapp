-- Whether the DGfM delivery takes finds with somebody else as finder
-- (25.09.2026: „für fremde Funde, also auch bei denen ich
-- Bestimmer bin, bitte bei der DGfM-Lieferung hier auch eine Abfrage
-- machen. Soll der Benutzer selbst entscheiden.").
--
-- Since the import keeps a file's finder in the Finder field (d85bccb),
-- one's own stock can hold other people's finds — a friend's list taken
-- over, the finds one only determined. The delivery sent them, under
-- their finder's name. From now on only for whoever ticks it; the tick
-- starts off for everyone, so nothing goes out in somebody else's name
-- that the account never decided on. The same choice governs the MykIS
-- sheet made by hand under Organisieren → Export.
ALTER TABLE map_users
    ADD mykis_foreign TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = the DGfM delivery takes finds with somebody else as finder too';
