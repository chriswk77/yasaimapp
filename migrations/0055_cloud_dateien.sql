-- Which recording belongs to which find.
--
-- The scaffolding announced this table from the start: map_cloud_zugaenge
-- carries the connection, this one the files. They are separate because they
-- have different lifetimes — whoever disconnects and reconnects keeps their
-- pictures.
--
-- WHAT IS STORED HERE IS A REFERENCE, NOT A FILE. The recording lies in the
-- user's own cloud and never passes through this server: 'upload_max_filesize'
-- here is 2 MB, and the pictures this exists for — stacked camera shots, raw
-- files, micrographs — are many times that. The browser hands them straight to
-- pCloud; what comes back is an id, and that is what stands here.
--
-- THE FILE ID IS THE PROVIDER'S, not ours. pCloud counts its own files, and
-- the id survives a rename or a move within the account. The path stands
-- beside it all the same, because it is what a human reads: whoever looks
-- into the table in a year wants to see where the picture lies, not a number.

CREATE TABLE map_cloud_dateien (
    id          int unsigned NOT NULL AUTO_INCREMENT,
    user_id     int unsigned NOT NULL,
    point_id    int unsigned NOT NULL,

    anbieter    varchar(20) NOT NULL COMMENT 'pcloud, gdrive … — see CLOUD_ANBIETER',

    -- The provider's ids. 'fileid' identifies the recording, 'folderid' the
    -- folder it went into — the second one saves a lookup when showing.
    fileid      bigint unsigned NOT NULL,
    folderid    bigint unsigned NULL,

    name        varchar(255) NOT NULL,

    -- The path at the time of upload, for reading. It can go stale when the
    -- letter tips over and folders move — that is accepted: the id stays
    -- right, and the path is a label, not an address.
    pfad        varchar(512) NOT NULL DEFAULT '',

    groesse     bigint unsigned NULL COMMENT 'bytes, as pCloud reports them',

    hochgeladen_am datetime NOT NULL,

    PRIMARY KEY (id),

    -- The same file must not be recorded twice for the same find. Uploading
    -- again with the same name overwrites at pCloud and yields the same id.
    UNIQUE KEY uq_cloud_datei (user_id, anbieter, fileid),
    KEY idx_cloud_datei_punkt (point_id),

    CONSTRAINT fk_cloud_datei_user FOREIGN KEY (user_id)
        REFERENCES map_users (id) ON DELETE CASCADE,
    -- Deleting a point takes its references along. The recordings themselves
    -- stay in the cloud — they belong to the user, and a deleted point is no
    -- reason to touch their storage.
    CONSTRAINT fk_cloud_datei_punkt FOREIGN KEY (point_id)
        REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
