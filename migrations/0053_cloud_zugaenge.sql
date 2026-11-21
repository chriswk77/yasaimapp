-- Cloud: each user's own storage space.
--
-- WHAT FOR, AND WHY NOT ON THE SERVER
-- Into one's own storage goes EVERYTHING about a find at full resolution:
-- the voucher photo from the phone as the original, micrographs, stacked
-- camera shots, raw files. On the server, only the shrunken version of the
-- voucher photo remains (fotos.php: 2048 px, shrunk in the browser so
-- uploading gets through in the forest) — the originals weigh many times
-- that and have no business on shared hosting: a single user doing serious
-- microscopy would fill it alone.
--
-- That is why everyone brings their OWN storage. The server only remembers
-- where it lies and how to get there; the files themselves it never sees.
--
-- ONE CONNECTION PER PROVIDER AND USER, no more. Two pCloud accounts side
-- by side would be one more question at every upload ("into which?"), and
-- nobody likes answering that twice a day. Whoever wants to switch
-- disconnects and reconnects.
--
-- THE PROVIDER STANDS AS TEXT, not as a number. 'pcloud' is readable when
-- one looks into the table in a year; a 1 is not. Which values are valid
-- is said by CLOUD_ANBIETER in includes/cloud.php — the database does NOT
-- check it, because a new provider would otherwise need a migration.
--
-- WHAT DOES NOT (YET) STAND HERE: the files themselves. Which shot belongs
-- to which find is the next step and gets a table of its own. This one
-- carries only the connection.

CREATE TABLE map_cloud_zugaenge (
    id          int unsigned NOT NULL AUTO_INCREMENT,
    user_id     int unsigned NOT NULL,

    anbieter    varchar(20) NOT NULL COMMENT 'pcloud, gdrive, onedrive, s3 — siehe CLOUD_ANBIETER',

    -- What the account is called at the provider. Only for display
    -- ("verbunden als …"): whoever has two accounts must be able to see
    -- which one hangs here.
    konto       varchar(190) NULL,

    -- The folder under which everything lies. Empty means root.
    --
    -- Beneath it, the application recreates the maintainer's GROWN scheme, and
    -- that is TAXONOMIC, not geographic (see cloud_ebenen in
    -- includes/cloud.php):
    --
    --   Fungi (Pilze)/Fungi/Basidiomycota/A/Am/Amanita/A. muscaria/
    --       2026-08-14 Goldsteintal/
    --
    -- Where a find lies is stated on the point; what one SEARCHES by is
    -- the species.
    ordner      varchar(255) NOT NULL DEFAULT '',

    -- THE SECRET AS TEXT AND NOT AS varchar: OAuth tokens are, depending
    -- on the provider, short (pCloud) or very long (Google with refresh
    -- token and scope list). A column too tight would cut it off, and the
    -- error would only show at the first refresh.
    --
    -- IT IS A PASSWORD EQUIVALENT. Whoever has it gets at the user's
    -- entire cloud storage. It therefore does NOT belong in output, not in
    -- dumps and not in error messages — cloud.php never hands it to the
    -- interface, and the database dump (admin/dump.php) must leave it out.
    geheimnis   text NULL,

    -- When the access token expires. NULL means "does not expire" (pCloud
    -- issues such tokens) — not "expired".
    laeuft_ab   datetime NULL,

    -- When the provider was last spoken to successfully. The interface
    -- shows by this whether the connection still carries, without asking
    -- on every page load.
    geprueft_am datetime NULL,

    erstellt_am  datetime NOT NULL,
    geaendert_am datetime NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cloud_user_anbieter (user_id, anbieter),
    CONSTRAINT fk_cloud_user FOREIGN KEY (user_id)
        REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
