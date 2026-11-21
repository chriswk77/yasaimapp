-- Leaf colors: sublists take over color and symbol of their main list.
--
-- WHAT FOR. Until now the main list's look applied to the whole branch —
-- the map asked upwards for every sublist (listenWurzel in karte.js), and
-- what a sublist itself carried in farbe/symbol was meaningless. With the
-- new lists this turns around: points now live only in leaves, and every
-- leaf carries its own look.
--
-- So that when the rendering is switched over (the map then reads the list
-- itself instead of the root) not a single marker changes color, every
-- sublist here gets the values of its root EXPLICITLY copied in. What was
-- until now mere meaninglessness thereby becomes the starting value —
-- afterwards it can be changed per leaf.
--
-- ORDER OF DELIVERY: this migration belongs applied BEFORE the stage with
-- the leaf rendering is deployed. The other way round nothing would break
-- — but sublists still carrying default values would stand there gray and
-- as a circle until it is applied. That is why it is a stage of its own.
--
-- VIA THE ROOT, NOT THE IMMEDIATE PARENTS. A grandchild under a child with
-- default values would otherwise inherit the child's gray instead of the
-- color it carried on the map until just now.
--
-- NO WITH RECURSIVE, AND THAT FROM EXPERIENCE: the first attempt resolved
-- the root via a recursive CTE and ran locally (MySQL 8) impeccably — but
-- the live server speaks MariaDB, and there a CTE may not stand before an
-- UPDATE (abort 1064 on applying). Instead, the ancestor chain as joins
-- lined up in a row: LISTE_TIEFE_MAX is 5, so a leaf has at most four
-- ancestors; five levels cover that with reserve. COALESCE takes the
-- TOPMOST occupied one — precisely the root, since above it all joins are
-- empty.
--
-- That target and ancestor rows are the same table is harmless: if an
-- intermediate node is processed before its child, it already carries the
-- root values — the child then reads the same values, just one level
-- earlier. Both orders end the same.
--
-- DELETED LISTS RUN ALONG TOO. The trash restores them one day, and then
-- they should look as they did in life — not gray. geaendert_am stays
-- untouched: for the user nothing has changed, the map looks the same
-- before and after this migration.

UPDATE map_lists u
  JOIN map_lists e1 ON e1.id = u.eltern_id
  LEFT JOIN map_lists e2 ON e2.id = e1.eltern_id
  LEFT JOIN map_lists e3 ON e3.id = e2.eltern_id
  LEFT JOIN map_lists e4 ON e4.id = e3.eltern_id
  LEFT JOIN map_lists e5 ON e5.id = e4.eltern_id
   SET u.farbe  = COALESCE(e5.farbe,  e4.farbe,  e3.farbe,  e2.farbe,  e1.farbe),
       u.symbol = COALESCE(e5.symbol, e4.symbol, e3.symbol, e2.symbol, e1.symbol);
