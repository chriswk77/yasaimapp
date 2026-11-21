-- Voucher photos may live in the user's own cloud.
--
-- WHY THE ROW STAYS AND ONLY THE BYTES MOVE. A photo is more than its
-- pixels: the moment it was taken, the camera, the coordinates from its
-- EXIF, the order in the gallery, and the fact that it belongs to this
-- find. All of that sits in map_photos and has no business in a foreign
-- storage. So the picture goes to pCloud and the record stays here — with
-- three columns saying where it went.
--
-- dateiname stays filled even afterwards. It is the name the file had on
-- this server, and should a transfer ever have to be undone, it is the
-- only thing that can point back at it.
--
-- NULL MEANS "LIES HERE", not "unknown". Everything already stored keeps
-- lying here, and nothing about it changes.

ALTER TABLE map_photos
    ADD COLUMN cloud_anbieter varchar(20)   NULL COMMENT 'pcloud … — see CLOUD_ANBIETER, NULL = on this server',
    ADD COLUMN cloud_fileid   bigint unsigned NULL COMMENT 'the provider''s id for the picture',
    ADD COLUMN cloud_folderid bigint unsigned NULL COMMENT 'the folder it went into, for the link',
    ADD COLUMN cloud_pfad     varchar(512)  NULL COMMENT 'the path at the time of transfer, for reading',
    ADD COLUMN cloud_am       datetime      NULL COMMENT 'when it went up';

-- Whoever asks "what is still lying here" asks exactly this.
CREATE INDEX idx_map_photos_cloud ON map_photos (user_id, cloud_fileid);
