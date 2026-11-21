-- Species lists: lists in which the title is a species.
--
-- A list marked this way gets suggestions from the name database while the
-- title is being typed. This is deliberately a property of the LIST and not
-- of the application: the map stays neutral, and whoever uses it for
-- viewpoints or parking spots sees nothing of it. Only whoever explicitly
-- declares a list a species list gets the suggestions — there, where they
-- belong.
--
-- And it cannot be deleted while the mark stands. Whoever wants to get rid
-- of it first removes the checkmark; then it is a list like any other. A
-- slip thus costs no collection.

ALTER TABLE map_lists
    ADD ist_arten TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Titel ist ein Artname — Vorschläge aus der Namensdatenbank' AFTER ist_hauptliste;

-- Create one for the existing accounts so the suggestions have a place right
-- away. New accounts do not get one automatically: a theme belongs not in
-- the basic equipment but in a list somebody creates.
INSERT INTO map_lists (user_id, name, farbe, symbol, ist_arten, sortierung, erstellt_am, geaendert_am)
SELECT u.id, 'Meine Funde', '#c0392b', 'pilz', 1, 0, NOW(), NOW()
  FROM map_users u
 WHERE NOT EXISTS (
       SELECT 1 FROM map_lists l
        WHERE l.user_id = u.id AND l.name = 'Meine Funde' AND l.geloescht_am IS NULL
 );
