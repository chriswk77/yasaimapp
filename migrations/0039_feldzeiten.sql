-- Field times: one change time per field — and what loses is kept.
--
-- THE CASE. In the forest a point is renamed, without network; the change
-- sits in the phone's queue. At home the same point gets a note on the
-- desktop. When the phone came online in the evening, it used to send the
-- WHOLE record — together with the old, empty note. The afternoon's note
-- was gone, silently, and nobody had ever seen both versions side by side.
--
-- THE RULE: per field, the younger change wins. Phone changes the title,
-- desktop the note — both stay, without any prompt. Only when BOTH have
-- touched the same field must one lose — and that one is not thrown away
-- but kept here and presented at the next look.
--
--   feldzeiten                 JSON on the point: {"titel": "…Z", "zusatz.substrat": "…Z"}
--   map_punkt_ueberschrieben   the losing versions, until somebody looks at them
--
-- WHY ON THE SERVER AND NOT ON THE DEVICE. If the phone wins, the
-- overwritten version is the desktop's — and only the server knows it. If
-- it lay on the device that won, one would never see it where one needs
-- it: at the desktop, where one sits and notices.
--
-- THE TIMES COME FROM THE DEVICE, not from the server. The server only
-- learns of a change from the dead zone in the evening — by its clock the
-- forest change of 14:00 would be "younger" than the desktop change of
-- 16:00, merely because it arrived later. The same decision as with
-- gefunden_am: the clock that stood next to the fungus counts. The price
-- is trust in device clocks; it becomes bearable because a wrong decision
-- costs nothing — the losing version lies in the table and can be brought
-- back.
--
-- AS TEXT (ISO, UTC, milliseconds), NOT AS DATETIME. The times are only
-- compared, never computed with; in the fixed format
-- "2026-08-11T14:32:05.123Z" character strings compare correctly. And it
-- stays honestly visible that this is a device statement, not server time.
--
-- NULL MEANS: NEVER CHANGED AT FINE RESOLUTION. The entire old stock stays
-- NULL and costs not a single byte in the bucket — points nobody touches
-- anymore (the archive, so almost all) never carry field times. Only the
-- first change after this migration writes the first time.

ALTER TABLE map_points
    ADD feldzeiten JSON NULL
        COMMENT 'Änderungszeit je Feld, vom Gerät — {"titel": "ISO-UTC", …}'
        AFTER stand;

-- The losing versions. One row per field and decision; it stays until the
-- user has seen it (gesehen_am), because a notice one can miss is, for
-- overwritten input, no notice.
--
-- wert is JSON because every field shape must fit here: a title (text), a
-- location ({lat, lon}), a list assignment ([ids]), an outline
-- ([[lat, lon], …]).
--
-- NO FOREIGN KEY to map_points: if the point is finally deleted, the entry
-- here must NOT vanish with it — "your change was overwritten and the
-- point is gone in the meantime" is exactly the answer one needs then.
-- Cleanup goes via gesehen_am, by hand, as with the discarded entries of
-- the queue.
CREATE TABLE map_punkt_ueberschrieben (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    point_id      INT UNSIGNED NOT NULL,
    feld          VARCHAR(80)  NOT NULL COMMENT 'titel, notiz, ort, listen, umriss, gefunden_am, zusatz.<name>',
    wert          JSON         NULL     COMMENT 'die unterlegene Fassung',
    wert_zeit     VARCHAR(24)  NULL     COMMENT 'wann sie entstand, Geräteuhr, ISO-UTC',
    entstanden_am DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    gesehen_am    DATETIME     NULL,
    PRIMARY KEY (id),
    KEY offen (user_id, gesehen_am),
    KEY punkt (point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
