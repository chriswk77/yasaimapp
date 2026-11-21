-- Base schema of the map application.
--
-- All tables carry the map_ prefix because they share the database with the
-- other projects.
--
-- Identifiers deliberately without umlauts: MySQL can handle them, but they
-- regularly cause trouble on export, when copying between servers, and in
-- tools.

-- Log of the applied migrations. Must come first and be the only one to
-- carry "IF NOT EXISTS", because admin/migrationen.php reads it before
-- anything has been applied.
CREATE TABLE IF NOT EXISTS map_migrationen (
  datei          VARCHAR(190) NOT NULL,
  eingespielt_am DATETIME     NOT NULL,
  PRIMARY KEY (datei)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Users and access
-- ---------------------------------------------------------------------------

CREATE TABLE map_users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email          VARCHAR(190) NOT NULL,
  passwort_hash  VARCHAR(255) NOT NULL,
  name           VARCHAR(100) NOT NULL DEFAULT '',
  ist_admin      TINYINT(1)   NOT NULL DEFAULT 0,
  eingeladen_von INT UNSIGNED NULL,
  erstellt_am    DATETIME     NOT NULL,
  letzter_login  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_map_users_email (email),
  KEY ix_map_users_einlader (eingeladen_von),
  CONSTRAINT fk_map_users_einlader
    FOREIGN KEY (eingeladen_von) REFERENCES map_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE map_invites (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code           VARCHAR(40)  NOT NULL,
  erstellt_von   INT UNSIGNED NOT NULL,
  erstellt_am    DATETIME     NOT NULL,
  gueltig_bis    DATETIME     NULL,
  eingeloest_von INT UNSIGNED NULL,
  eingeloest_am  DATETIME     NULL,
  notiz          VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_map_invites_code (code),
  KEY ix_map_invites_ersteller (erstellt_von),
  KEY ix_map_invites_einloeser (eingeloest_von),
  CONSTRAINT fk_map_invites_ersteller
    FOREIGN KEY (erstellt_von) REFERENCES map_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_map_invites_einloeser
    FOREIGN KEY (eingeloest_von) REFERENCES map_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Device tokens for the REST API and the PWA. Only the SHA-256 hash is
-- stored, never the token itself.
CREATE TABLE map_tokens (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  token_hash     CHAR(64)     NOT NULL,
  geraet         VARCHAR(100) NOT NULL DEFAULT '',
  erstellt_am    DATETIME     NOT NULL,
  letzte_nutzung DATETIME     NULL,
  gueltig_bis    DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_map_tokens_hash (token_hash),
  KEY ix_map_tokens_user (user_id),
  CONSTRAINT fk_map_tokens_user
    FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed login attempts, for the brake against brute-force guessing.
-- Thinned out regularly on login.
CREATE TABLE map_login_versuche (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kennung   VARCHAR(190) NOT NULL,
  ip        VARCHAR(45)  NOT NULL DEFAULT '',
  zeitpunkt DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY ix_map_login_kennung (kennung, zeitpunkt),
  KEY ix_map_login_ip (ip, zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Lists and points
-- ---------------------------------------------------------------------------

CREATE TABLE map_lists (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  name         VARCHAR(100) NOT NULL,
  farbe        CHAR(7)      NOT NULL DEFAULT '#c0392b',
  symbol       VARCHAR(40)  NOT NULL DEFAULT 'pilz',
  sortierung   SMALLINT     NOT NULL DEFAULT 0,
  erstellt_am  DATETIME     NOT NULL,
  geaendert_am DATETIME     NOT NULL,
  geloescht_am DATETIME     NULL,
  PRIMARY KEY (id),
  KEY ix_map_lists_user (user_id, sortierung),
  CONSTRAINT fk_map_lists_user
    FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- lat/lon as DECIMAL instead of FLOAT: six decimal places are roughly eleven
-- centimeters and are stored exactly, without rounding drift from writing
-- back and forth. A find spot that wanders a little on every save would be
-- exactly the bug this application is meant to fix.
CREATE TABLE map_points (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED  NOT NULL,
  lat           DECIMAL(9,6)  NOT NULL,
  lon           DECIMAL(10,6) NOT NULL,
  genauigkeit_m SMALLINT UNSIGNED NULL,
  hoehe_m       SMALLINT      NULL,
  titel         VARCHAR(200)  NOT NULL DEFAULT '',
  notiz         TEXT          NULL,
  gefunden_am   DATE          NULL,
  schutzgebiet  VARCHAR(255)  NULL,
  erstellt_am   DATETIME      NOT NULL,
  geaendert_am  DATETIME      NOT NULL,
  geloescht_am  DATETIME      NULL,
  PRIMARY KEY (id),
  -- Points within a user's map viewport.
  KEY ix_map_points_ausschnitt (user_id, lat, lon),
  -- Sync with the PWA: "everything that has changed since X".
  KEY ix_map_points_abgleich (user_id, geaendert_am),
  CONSTRAINT fk_map_points_user
    FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The n:m mapping this project exists for in the first place: a point may
-- lie in any number of lists at the same time.
CREATE TABLE map_point_lists (
  point_id INT UNSIGNED NOT NULL,
  list_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (point_id, list_id),
  KEY ix_map_point_lists_liste (list_id),
  CONSTRAINT fk_map_point_lists_punkt
    FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE,
  CONSTRAINT fk_map_point_lists_liste
    FOREIGN KEY (list_id) REFERENCES map_lists (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE map_photos (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  point_id      INT UNSIGNED  NOT NULL,
  dateiname     VARCHAR(190)  NOT NULL,
  original_name VARCHAR(190)  NOT NULL DEFAULT '',
  aufgenommen_am DATETIME     NULL,
  exif_lat      DECIMAL(9,6)  NULL,
  exif_lon      DECIMAL(10,6) NULL,
  erstellt_am   DATETIME      NOT NULL,
  PRIMARY KEY (id),
  KEY ix_map_photos_punkt (point_id),
  CONSTRAINT fk_map_photos_punkt
    FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sharing a list: either with an existing account or as a read link with a
-- random token for people without an account.
CREATE TABLE map_shares (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_id           INT UNSIGNED NOT NULL,
  empfaenger_user_id INT UNSIGNED NULL,
  token             CHAR(32)     NULL,
  rechte            ENUM('lesen','schreiben') NOT NULL DEFAULT 'lesen',
  erstellt_am       DATETIME     NOT NULL,
  gueltig_bis       DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_map_shares_token (token),
  KEY ix_map_shares_liste (list_id),
  KEY ix_map_shares_empfaenger (empfaenger_user_id),
  CONSTRAINT fk_map_shares_liste
    FOREIGN KEY (list_id) REFERENCES map_lists (id) ON DELETE CASCADE,
  CONSTRAINT fk_map_shares_empfaenger
    FOREIGN KEY (empfaenger_user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
