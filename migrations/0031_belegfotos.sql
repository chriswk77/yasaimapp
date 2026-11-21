-- Voucher photos on the points.
--
-- map_photos has stood there since the base schema and was never in use. It
-- now gets what is missing for operation — it is not created anew, because
-- the same thing a second time would mean that at some point somebody
-- writes into the wrong one.
--
-- WHAT THESE PHOTOS ARE. Memory aids and overview shots: where the fungus
-- stood, what the surroundings looked like, what one recognizes the spot
-- by. The voucher in the narrow sense is made separately with a proper
-- camera and gets stacked. That is why images are shrunk to a 2048 px long
-- edge before anything goes up at all — what lies here does not have to be
-- print-ready, it has to remind. That is the difference between 500 kB and
-- 6 MB per image, and over a day in the field between 25 MB and 300 MB.

ALTER TABLE map_photos
    -- WHY user_id, when the point already has one. Two reasons, both
    -- practical: the path on disk is daten/users/<id>/photos/, and the
    -- quota is counted per user. Asking the point for both would mean
    -- dragging a join along for every file.
    ADD user_id INT UNSIGNED NOT NULL AFTER id,

    -- The same idempotency as with the points.
    --
    -- A photo comes into being in the forest, that is, without network, and
    -- is submitted hours later. If the RESPONSE gets lost in the process —
    -- not the request —, the device does not know whether it arrived, and
    -- tries again. Without this identifier the image would afterwards lie
    -- on the disk twice and count twice against the quota.
    ADD client_id CHAR(36) NULL AFTER point_id,

    -- The dimensions after shrinking, for the page layout: whoever knows
    -- them can reserve the space before the image has loaded. Otherwise the
    -- view jumps with every photo that arrives.
    ADD breite SMALLINT UNSIGNED NULL AFTER original_name,
    ADD hoehe  SMALLINT UNSIGNED NULL AFTER breite,

    -- What gets counted is still the disk, not this column (see
    -- benutzerspeicher.php). It is here for the display "dieses Foto
    -- belegt 480 kB", not as the basis of an accounting.
    ADD bytes INT UNSIGNED NOT NULL DEFAULT 0 AFTER hoehe,

    -- What it was taken with. From EXIF, if it is there.
    ADD kamera VARCHAR(120) NULL AFTER exif_lon,

    -- Order in the view. The first image is the title image, and which one
    -- that is, the human decides and not the timestamp.
    ADD sortierung SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER kamera,

    -- A photo submitted twice is the same photo.
    ADD UNIQUE KEY uq_map_photos_client (user_id, client_id),
    ADD KEY ix_map_photos_benutzer (user_id),
    ADD CONSTRAINT fk_map_photos_user
        FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE;

-- NO geloescht_am, and that is a decision, not an omission.
--
-- Points and lists have a trash because their loss cannot be replaced. A
-- photo lies differently when deleted: the row here is only the tag on the
-- picture — as long as the file stands on the disk, it occupies the quota,
-- and a trash in which the space keeps being consumed explains itself to
-- nobody. So on deletion both go, row and file.
--
-- For syncing with the phone, no tombstone is needed even so: photos travel
-- as part of their point, and whoever removes one touches the point in
-- doing so (geaendert_am). The device gets the new list on the next sync
-- and with it the deletion — without needing a separate trace for it.
--
-- Because this cannot be undone, a confirmation stands before it in the
-- interface. After the final emptying of the trash it is the second
-- irrevocable action of this application, and it is to remain the last.
