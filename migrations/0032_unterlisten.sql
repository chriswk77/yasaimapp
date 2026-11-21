-- Lists within lists.
--
-- WHY FLAT NO LONGER SUFFICES. Whoever collects for a long time inevitably
-- ends up with lists that are really umbrella terms: "Meine Funde" splits
-- into fungi and lichens, "Campingplätze" into Germany and Italy and below
-- that into federal states. Flat side by side, that becomes a row of twenty
-- entries in which one searches instead of finding.
--
-- A POINTER TO THE PARENT LIST, nothing more is needed. The alternatives —
-- a path column ("1/4/9"), nested sets with left/right — are faster at
-- reading whole branches and considerably more laborious on every change.
-- With a few dozen lists per user every query reads everything at once
-- anyway, and the tree is built in memory. What could be optimized here
-- costs nothing.
--
-- NULL MEANS TOP LEVEL. No artificial root entry: that would be a row that
-- in truth does not exist, but which would have to be considered as a
-- special case in every count, every deletion and every share.
--
-- ON DELETE SET NULL is a net, not a procedure. Deletion in this
-- application is soft (geloescht_am), and liste_loeschen() explicitly
-- reattaches the sublists one level up. The foreign key only kicks in when
-- something really is hard-deleted — when removing a user, say. Then the
-- children should not point to a number that no longer exists.
--
-- NO DEPTH LIMIT IN SQL. MySQL cannot express it, and a trigger for it
-- would be harder to understand than the check in liste_eltern_pruefen().
-- That is also where the guard against cycles stands: a list that is its
-- own child via detours would make every tree build run endlessly.

ALTER TABLE map_lists
    ADD eltern_id INT UNSIGNED NULL
        COMMENT 'übergeordnete Liste, NULL für die oberste Ebene' AFTER user_id,
    ADD KEY ix_map_lists_eltern (eltern_id),
    ADD CONSTRAINT fk_map_lists_eltern
        FOREIGN KEY (eltern_id) REFERENCES map_lists (id) ON DELETE SET NULL;
