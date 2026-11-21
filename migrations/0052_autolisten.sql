-- Auto lists: lists that follow a saved Ordnen filter.
--
-- In Ordnen, the configured filter (chosen lists, search, time span) can
-- be pinned down as a list — just a name, the rest is the filter
-- (the call, 14.08.2026: "keine Haken nötig, das Ergebnis gibt
-- die Liste"). The server recalculates the assignment itself, on every
-- point change (autolisten_nachziehen in daten.php).
--
-- The column carries the filter as JSON ({"quellen":[…],"suche":…,"von":…,
-- "bis":…,"ohnezeit":…}); NULL means: ordinary list. There is deliberately
-- NO separate ist_autoliste column next to it — the attribute IS the
-- filter, and two columns for one statement would drift apart.
--
-- After the container list (0051), an auto list is the second special
-- list: not fillable by hand (sieve and guard in punkt_listen_setzen),
-- without sublists and never inside a branch (liste_eltern_pruefen), but
-- expressly hangable into containers — that is what it is for.
ALTER TABLE map_lists
    ADD COLUMN filter TEXT NULL DEFAULT NULL AFTER ist_containerliste;
