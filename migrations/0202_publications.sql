-- THE FUNDBUCH OF AN ACCOUNT (25.11.2026): one's released finds on one's
-- OWN homepage. Until now the one public find book was built into the
-- installation — its pages read this database and this code directly, so
-- it could only stand on the same server („dann bau mal als Paket um und
-- wir testen es dann direkt bei WK Pilz"). Now a find book is a small
-- package on anybody's webspace that FETCHES what its owner released
-- through api/v1/publication.php (includes/publication.php).
--
--   user_id        whose finds — one find book per account
--   site_url       where it stands, e.g. https://example.org/ — shown in
--                  the find's head („Auf example.org zeigen?") and written
--                  into every picture as the place to ask for more rights
--   key_hash       SHA-256 of the key the package carries. ITS OWN KEY,
--                  NOT A DEVICE TOKEN: a device token is the whole account
--                  (map_tokens), and this one lies in a file on a web
--                  server. It reads the released finds and nothing else.
--                  Only the hash is kept; the key is shown once.
--   last_fetch_at  when the package last asked — the settings say it, so
--                  one sees whether the homepage is still connected
--
-- THE CODE CAN GO LIVE FIRST: publication_ready() asks for the table;
-- until then the settings show no Fundbuch and the API answers 503.

CREATE TABLE map_publications (
    user_id       INT UNSIGNED NOT NULL,
    site_url      VARCHAR(255) NOT NULL,
    key_hash      CHAR(64)     NOT NULL,
    created_at    DATETIME     NOT NULL,
    last_fetch_at DATETIME     NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_publications_key (key_hash),
    CONSTRAINT fk_publications_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
