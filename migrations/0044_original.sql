-- The rescue anchor — stage 4 of sharing.
--
-- THE CASE. A shared list is a joint project: members with rights
-- (migration 0043) move points, change titles, fill fields. Mostly that is
-- the point of it all — but somebody else's mistap looks exactly like
-- work. The owner needs a way back to what HE had entered, field by field:
-- original position, original title, original note.
--
-- ONE ANCHOR PER FIELD, NO VERSION HISTORY. The state of a field is
-- recorded EXACTLY ONCE: immediately before somebody else writes into it
-- for the first time. After that anyone can work on it, the anchor does
-- not move. This is deliberately not "the state at the time of sharing":
-- the owner keeps working himself, and his own most recent state is the
-- original he wants back.
--
-- IF THE OWNER HIMSELF WRITES, the field's anchor falls: what he enters
-- himself IS the original from then on — the next foreign change then
-- anchors there. The same with "So lassen" in the administration: the
-- foreign change is thereby accepted and becomes the new base.
--
-- Form: {"titel": {"wert": "...", "von": "nick", "am": "ISO-UTC"},
--        "ort": {"wert": {"lat": .., "lon": ..}, ...},
--        "zusatz.<Feldname>": {...}}
--
-- NULL means: no field anchored — never has anybody else written. The
-- entire old stock stays NULL and costs nothing, as with feldzeiten.
-- The column leaves the server only in the owner's administration:
-- punkt_aufbereiten() is a whitelist, members never see it.
ALTER TABLE map_points
    ADD original JSON NULL
        COMMENT 'je Feld der Stand vor der ersten fremden Änderung — der Rettungsanker'
        AFTER feldzeiten;
