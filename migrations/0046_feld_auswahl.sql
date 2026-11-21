-- Field type "Auswahl" for the extra fields.
--
-- Until now fields could only hold text (single- or multi-line). For
-- statements with a fixed set of values — the first user is identification
-- certainty (mak. sicher / mik. sicher / unsicher / unbestimmt) — a
-- selection is needed: free text delivers "mak sicher", "makr. sicher"
-- and "sicher (mak.)" side by side, and any analysis over that is none.
--
-- The options hang ON THE FIELD, as a JSON list in the order in which
-- they are offered. NULL for the text fields — they need none.
--
-- The value on the point stays what it was: text in the zusatz block. A
-- selection field writes the same text, just one from the list — and a
-- value whose option is later removed remains legibly on the point (the
-- interface shows it as an additional choice instead of silently
-- swallowing it on the next save).
ALTER TABLE map_list_felder
    ADD optionen JSON NULL
        COMMENT 'die Auswahlwerte, nur bei typ=auswahl'
        AFTER ist_arten;
