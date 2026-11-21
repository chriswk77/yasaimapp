-- The schema moves to English — the last German layer.
--
-- Since 15./16.08.2026 the code is English and the surface German; the
-- schema was deliberately left for its own, carefully staged step
-- (CLAUDE.md), and this is that step. Tables, columns, enum values, and
-- the identifier VALUES the rows carry (field keys in field_times /
-- original / overwritten, filter JSON keys, species source keys, list
-- field types) all move together with the code that speaks them — one
-- name end to end, no translation table (20.08.2026, at the
-- shape names: a layer that outlives a rename is exactly the sort of
-- half-measure that has already cost days).
--
-- THIS FILE RUNS BEFORE ITS CODE, NOT AFTER. The usual order — code
-- first, then the migration — would lock everyone out: with the schema
-- still German the new code cannot even LOG IN, and the emergency page
-- admin/migrations.php sits behind admin_required(). So for this one
-- migration the order inverts: the file is deployed alone, applied
-- through the OLD administration, and the code follows in the next push.
-- Between applying and that deploy the application is down — minutes,
-- announced, four users.
--
-- CHANGE COLUMN with full definitions, not RENAME COLUMN: the live
-- server speaks MariaDB of unknown vintage (see 0040's lesson), and
-- CHANGE works everywhere. Pure renames are metadata-only; nothing is
-- rewritten, nothing rolls back either — take the dump first.
--
-- Index and constraint names keep their German names: no code ever
-- says them (no FORCE INDEX anywhere), and renaming constraints means
-- dropping and re-adding them — cost without gain.

-- THE JSON COLUMNS AND THEIR CHECKS — three worlds, one file. The old
-- migrations declared these columns as JSON. MariaDB (live) turns that
-- into longtext plus a COLUMN-level json_valid check without a name of
-- its own; a fresh MySQL keeps a native JSON type with no check at all;
-- and a MySQL that once imported a MariaDB dump (the local setup) holds
-- NAMED table checks (map_…_chk_1). The first cut of this file dropped
-- those names — and live answered 1091, "check that it exists", at
-- statement 1 (21.08.2026). No DROP fits all three worlds. None is
-- needed: a CHANGE replaces the whole column definition, and MariaDB's
-- column check goes with it; MySQL's native JSON renames freely.
--
-- THE CANARY COMES FIRST, before anything is renamed: the same
-- mechanism — CHANGE on a checked column — against the smallest such
-- table, keeping its name. If a server refuses THIS, the file aborts
-- with the schema untouched, exactly like the first cut aborted today.
ALTER TABLE map_container_listen
    CHANGE `rechte` `rechte` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'gewährte Schlüssel als Liste, NULL = nur lesen';

RENAME TABLE
    map_cloud_dateien TO map_cloud_files,
    map_cloud_zugaenge TO map_cloud_accounts,
    map_container TO map_containers,
    map_container_gesehen TO map_container_state,
    map_container_listen TO map_container_lists,
    map_container_mitglieder TO map_container_members,
    map_container_nachrichten TO map_container_messages,
    map_freunde TO map_friends,
    map_gebiete TO map_areas,
    map_gruppen TO map_groups,
    map_gruppen_mitglieder TO map_group_members,
    map_importe TO map_imports,
    map_list_felder TO map_list_fields,
    map_login_versuche TO map_login_attempts,
    map_point_arten TO map_point_species,
    map_punkt_ueberschrieben TO map_point_overwritten,
    map_tax_flechten TO map_tax_lichens,
    map_tax_pflanzen TO map_tax_plants,
    map_tax_tiere TO map_tax_animals,
    map_tax_verknuepfung TO map_tax_links;

ALTER TABLE map_cloud_files
    CHANGE `anbieter` `provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pcloud, gdrive … — see CLOUD_PROVIDERS',
    CHANGE `pfad` `path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    CHANGE `groesse` `size` bigint unsigned NULL DEFAULT NULL COMMENT 'bytes, as pCloud reports them',
    CHANGE `hochgeladen_am` `uploaded_at` datetime NOT NULL;

ALTER TABLE map_cloud_accounts
    CHANGE `anbieter` `provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pcloud, gdrive, onedrive, s3 — see CLOUD_PROVIDERS',
    CHANGE `ort` `region` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'pCloud: api./eapi.pcloud.com — S3: region. NULL means the provider does not need it',
    CHANGE `konto` `account` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `ordner` `folder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    CHANGE `geheimnis` `secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    CHANGE `laeuft_ab` `expires_at` datetime NULL DEFAULT NULL,
    CHANGE `geprueft_am` `checked_at` datetime NULL DEFAULT NULL,
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `geaendert_am` `updated_at` datetime NOT NULL;

ALTER TABLE map_containers
    CHANGE `besitzer_user_id` `owner_user_id` int unsigned NOT NULL,
    CHANGE `erstellt_am` `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHANGE `filter` `filter` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'birth filter as JSON (time/area/terms), NULL = everything; never editable';

ALTER TABLE map_container_state
    CHANGE `mitglieder` `members` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    CHANGE `aktualisiert_am` `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHANGE `gelesen_bis` `read_up_to` int unsigned NOT NULL DEFAULT '0' COMMENT 'highest read message number',
    CHANGE `eigener_name` `own_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'what this member calls the container; NULL = as the owner';

ALTER TABLE map_container_lists
    CHANGE `user_id` `user_id` int unsigned NOT NULL COMMENT 'who shared = owner of the list',
    CHANGE `rechte` `rights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'granted keys as a list, NULL = read only',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE map_container_members
    CHANGE `art` `type` enum('freund','gruppe','friend','group') NOT NULL,
    CHANGE `ziel_id` `target_id` int unsigned NOT NULL COMMENT 'user_id or group id, depending on type',
    CHANGE `aufgenommen_am` `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE map_container_messages
    CHANGE `user_id` `user_id` int unsigned NOT NULL COMMENT 'who wrote it',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL;

ALTER TABLE map_friends
    CHANGE `von_user_id` `from_user_id` int unsigned NOT NULL COMMENT 'who asked',
    CHANGE `an_user_id` `to_user_id` int unsigned NOT NULL COMMENT 'who was asked',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHANGE `beantwortet_am` `answered_at` datetime NULL DEFAULT NULL;

ALTER TABLE map_areas
    CHANGE `client_id` `client_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'assigned by the device, makes creation repeatable',
    CHANGE `ecken` `corners` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'polygon as [[lat,lon], ...]',
    CHANGE `messwerte` `measurements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'measured bytes per request, key \"<layer>|<zoom>\"',
    CHANGE `gemessen_am` `measured_on` date NULL DEFAULT NULL COMMENT 'when it was measured; the answer ages from there',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `geaendert_am` `updated_at` datetime NOT NULL;

ALTER TABLE map_groups
    CHANGE `besitzer_user_id` `owner_user_id` int unsigned NOT NULL,
    CHANGE `erstellt_am` `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE map_group_members
    CHANGE `gruppe_id` `group_id` int unsigned NOT NULL,
    CHANGE `aufgenommen_am` `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE map_imports
    CHANGE `quelle` `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'takeout, fungi, …',
    CHANGE `liste_id` `list_id` int unsigned NULL DEFAULT NULL COMMENT 'the root list of the import',
    CHANGE `punkte_neu` `points_new` int unsigned NOT NULL DEFAULT '0',
    CHANGE `punkte_zugeordnet` `points_assigned` int unsigned NOT NULL DEFAULT '0',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE map_invites
    CHANGE `erstellt_von` `created_by` int unsigned NOT NULL,
    CHANGE `rolle` `role` enum('admin','benutzer','gast','user','guest') NOT NULL DEFAULT 'benutzer' COMMENT 'the role the redeemed account receives',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `gueltig_bis` `valid_until` datetime NULL DEFAULT NULL,
    CHANGE `eingeloest_von` `redeemed_by` int unsigned NULL DEFAULT NULL,
    CHANGE `eingeloest_am` `redeemed_at` datetime NULL DEFAULT NULL,
    CHANGE `notiz` `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE map_list_fields
    CHANGE `name` `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'label and JSON key at the same time',
    CHANGE `typ` `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT 'text | multiline | choice',
    CHANGE `ist_arten` `is_species` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'suggestions from the name database',
    CHANGE `optionen` `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'the choice values, only for type=choice',
    CHANGE `sortierung` `sort_order` smallint NOT NULL DEFAULT '0';

ALTER TABLE map_lists
    CHANGE `eltern_id` `parent_id` int unsigned NULL DEFAULT NULL COMMENT 'parent list, NULL for the top level',
    CHANGE `client_id` `client_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'assigned by the device, makes creation repeatable',
    CHANGE `farbe` `color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#c0392b',
    CHANGE `ist_arten` `is_species` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'title is a species name — suggestions from the name database',
    CHANGE `ist_containerliste` `is_container_list` tinyint unsigned NOT NULL DEFAULT '0',
    CHANGE `sortierung` `sort_order` smallint NOT NULL DEFAULT '0',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `geaendert_am` `updated_at` datetime NOT NULL,
    CHANGE `geloescht_am` `deleted_at` datetime NULL DEFAULT NULL;

ALTER TABLE map_login_attempts
    CHANGE `kennung` `identifier` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    CHANGE `zeitpunkt` `attempted_at` datetime NOT NULL;

ALTER TABLE map_photos
    CHANGE `dateiname` `filename` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    CHANGE `breite` `width` smallint unsigned NULL DEFAULT NULL,
    CHANGE `hoehe` `height` smallint unsigned NULL DEFAULT NULL,
    CHANGE `aufgenommen_am` `taken_at` datetime NULL DEFAULT NULL,
    CHANGE `kamera` `camera` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `sortierung` `sort_order` smallint unsigned NOT NULL DEFAULT '0',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `cloud_anbieter` `cloud_provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'pcloud … — see CLOUD_ANBIETER, NULL = on this server',
    CHANGE `cloud_pfad` `cloud_path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'the path at the time of transfer, for reading',
    CHANGE `cloud_am` `cloud_uploaded_at` datetime NULL DEFAULT NULL COMMENT 'when it went up';

ALTER TABLE map_point_species
    CHANGE `feld` `field` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'field name, empty = the title of the point',
    CHANGE `gattung` `genus` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'from the name list, else the first word',
    CHANGE `quelle` `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'fungi | lichens | plants | animals | faeu | gbif | col — see species.php',
    CHANGE `kennung` `identifier` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE map_points
    CHANGE `client_id` `client_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'assigned by the device, makes creation repeatable',
    CHANGE `umriss` `outline` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'corner points of the area as [[lat, lon], ...], else NULL',
    CHANGE `genauigkeit_m` `accuracy_m` smallint unsigned NULL DEFAULT NULL,
    CHANGE `hoehe_m` `elevation_m` smallint NULL DEFAULT NULL COMMENT 'elevation above NHN in metres, converted',
    CHANGE `hoehe_ellipsoid_m` `elevation_ellipsoid_m` smallint NULL DEFAULT NULL COMMENT 'raw measurement above the WGS84 ellipsoid',
    CHANGE `hoehe_genauigkeit_m` `elevation_accuracy_m` smallint unsigned NULL DEFAULT NULL COMMENT 'uncertainty of the elevation measurement in metres',
    CHANGE `hoehe_quelle` `elevation_source` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'gnss | dgm1 | outside | worldwide — where elevation_m comes from',
    CHANGE `mtb` `mtb` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Messtischblatt (TK25), four digits',
    CHANGE `mtb_quadrant` `mtb_quadrant` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'quarter, sixteenth, sixty-fourth — each 1=NW 2=NE 3=SW 4=SE',
    CHANGE `gemeinde` `municipality` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name of the municipality as valid at find time',
    CHANGE `landesteil` `region` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'federal state/canton, three letters in DACH, ISO remainder otherwise',
    CHANGE `staat` `country` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'ISO 3166-1, uppercase',
    CHANGE `gemeinde_quelle` `municipality_source` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'osm | none — NULL means not asked yet',
    CHANGE `titel` `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    CHANGE `notiz` `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    CHANGE `zusatz` `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'values of the list fields, by field name',
    CHANGE `zusatz_arten` `extra_species` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'per field name the reference into the name list, \"source:identifier\"',
    CHANGE `gefunden_am` `found_at` datetime NULL DEFAULT NULL COMMENT 'time of the find by the device clock, not the server',
    CHANGE `schutzgebiet` `protected_area` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `geaendert_am` `updated_at` datetime NOT NULL,
    CHANGE `stand` `touched_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT 'every change, derived ones too — for the fingerprint',
    CHANGE `feldzeiten` `field_times` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'change time per field, from the device — {\"title\": \"ISO-UTC\", …}',
    CHANGE `original` `original` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'per field the state before the first foreign change — the rescue anchor',
    CHANGE `import_id` `import_id` int unsigned NULL DEFAULT NULL COMMENT 'the import this point came from — NULL: by hand',
    CHANGE `geloescht_am` `deleted_at` datetime NULL DEFAULT NULL;

ALTER TABLE map_point_overwritten
    CHANGE `feld` `field` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'title, note, position, lists, outline, found_at, extra.<name>',
    CHANGE `wert` `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT 'the losing version',
    CHANGE `wert_zeit` `value_time` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'when it came into being, device clock, ISO-UTC',
    CHANGE `entstanden_am` `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHANGE `gesehen_am` `seen_at` datetime NULL DEFAULT NULL;

ALTER TABLE map_shares
    CHANGE `empfaenger_user_id` `recipient_user_id` int unsigned NULL DEFAULT NULL,
    CHANGE `rechte` `rights` enum('lesen','schreiben','read','write') NOT NULL DEFAULT 'lesen',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `gueltig_bis` `valid_until` datetime NULL DEFAULT NULL;

ALTER TABLE map_tax_col
    CHANGE `col_id` `col_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ColDP identifier, e.g. 9JMY',
    CHANGE `akzeptiert_id` `accepted_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'for synonyms: the valid name',
    CHANGE `eltern_id` `parent_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'parent taxon',
    CHANGE `rang` `taxon_rank` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `name` `name` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'scientific name with author',
    CHANGE `kanonisch` `canonical` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name without author — comparison happens on this',
    CHANGE `autor` `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `familie` `family` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `ordnung` `order_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `klasse` `class` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

ALTER TABLE map_tax_dgfm
    CHANGE `sipnr` `sipnr` int unsigned NOT NULL COMMENT 'taxnr of the valid name; equal = accepted',
    CHANGE `rang` `taxon_rank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `binomial` `binomial` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name without author — comparison happens on this',
    CHANGE `artname` `full_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'complete with author and year',
    CHANGE `gattung` `genus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `art` `epithet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `autor` `author` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `jahr` `year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `deutscher_name` `german_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `rote_liste_d` `red_list_de` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `rote_liste_by` `red_list_by` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `familie` `family` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `ordnung` `order_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `klasse` `class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `gruppe` `group_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `suchtext` `search_text` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'scientific and German name, for the full-text search';

ALTER TABLE map_tax_faeu
    CHANGE `taxnr` `taxnr` int unsigned NOT NULL COMMENT 'taxonID of the source',
    CHANGE `sipnr` `sipnr` int unsigned NOT NULL COMMENT 'points to the valid name; on that one to itself',
    CHANGE `rang` `taxon_rank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `binomial` `binomial` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name without author, with subspecies where present',
    CHANGE `gattung` `genus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `art` `epithet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `autor` `author` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `deutscher_name` `german_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `rote_liste_d` `red_list_de` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `gruppe` `group_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'the class, taken from the parent chain',
    CHANGE `suchtext` `search_text` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

ALTER TABLE map_tax_lichens
    CHANGE `taxnr` `taxnr` int unsigned NOT NULL COMMENT 'running number from the import file',
    CHANGE `uuid` `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'stable identifier from the source',
    CHANGE `sipnr` `sipnr` int unsigned NOT NULL COMMENT 'points to the valid name; on that one to itself',
    CHANGE `rang` `taxon_rank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `binomial` `binomial` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name without author',
    CHANGE `gattung` `genus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `art` `epithet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `autor` `author` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `deutscher_name` `german_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'the Red List carries none; column for later',
    CHANGE `rote_liste_d` `red_list_de` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'category as for the fungi: 0 1 2 3 G R V D *',
    CHANGE `status` `status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'I native, N neobiota, U casual',
    CHANGE `gruppe` `group_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'the stored group labels: Flechte, lichenicoler Pilz, flechtenaehnlicher Pilz',
    CHANGE `suchtext` `search_text` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'scientific and German name, for the full-text search';

ALTER TABLE map_tax_gbif
    CHANGE `eltern_key` `parent_key` int unsigned NULL DEFAULT NULL COMMENT 'for synonyms: the accepted name',
    CHANGE `ist_synonym` `is_synonym` tinyint unsigned NOT NULL DEFAULT '0',
    CHANGE `rang` `taxon_rank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'SPECIES, GENUS, VARIETY …',
    CHANGE `klasse_key` `class_key` int unsigned NULL DEFAULT NULL,
    CHANGE `ordnung_key` `order_key` int unsigned NULL DEFAULT NULL,
    CHANGE `familie_key` `family_key` int unsigned NULL DEFAULT NULL,
    CHANGE `gattung_key` `genus_key` int unsigned NULL DEFAULT NULL,
    CHANGE `art_key` `species_key` int unsigned NULL DEFAULT NULL COMMENT 'accepted species, groups synonyms',
    CHANGE `kanonisch` `canonical` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name without author — search happens on this',
    CHANGE `gattung` `genus` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `epitheton` `epithet` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `infra_epitheton` `infra_epithet` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `autor` `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `jahr` `year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'year of the combination, rarely filled for fungi',
    CHANGE `basionym_autor` `basionym_author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `basionym_jahr` `basionym_year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

ALTER TABLE map_tax_plants
    CHANGE `taxnr` `taxnr` int unsigned NOT NULL COMMENT 'running number from the import file',
    CHANGE `uuid` `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'stable identifier from the source',
    CHANGE `sipnr` `sipnr` int unsigned NOT NULL COMMENT 'points to the valid name; on that one to itself',
    CHANGE `rang` `taxon_rank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `binomial` `binomial` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name without author',
    CHANGE `gattung` `genus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `art` `epithet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `autor` `author` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `deutscher_name` `german_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `rote_liste_d` `red_list_de` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'category as for the fungi: 0 1 2 3 G R V D * nb',
    CHANGE `status` `status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'I native, N neobiota, U casual',
    CHANGE `gruppe` `group_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `suchtext` `search_text` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'scientific and German name, for the full-text search';

ALTER TABLE map_tax_animals
    CHANGE `taxnr` `taxnr` int unsigned NOT NULL COMMENT 'number from the source taxonID',
    CHANGE `sipnr` `sipnr` int unsigned NOT NULL COMMENT 'points to the valid name; on that one to itself',
    CHANGE `rang` `taxon_rank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `binomial` `binomial` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name without author',
    CHANGE `gattung` `genus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `art` `epithet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `autor` `author` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `deutscher_name` `german_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    CHANGE `rote_liste_d` `red_list_de` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'the source carries none; column for later',
    CHANGE `gruppe` `group_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'the class from the source: Insecta, Aves, Mammalia …',
    CHANGE `suchtext` `search_text` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'scientific and German name, for the full-text search';

ALTER TABLE map_tax_links
    CHANGE `taxnr` `taxnr` int unsigned NOT NULL COMMENT 'DGfM entry',
    CHANGE `gbif_key` `gbif_key` int unsigned NULL DEFAULT NULL COMMENT 'matched GBIF entry',
    CHANGE `gbif_art_key` `gbif_species_key` int unsigned NULL DEFAULT NULL COMMENT 'its accepted species',
    CHANGE `col_id` `col_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'matched COL entry',
    CHANGE `col_art_id` `col_species_id` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'its accepted name',
    CHANGE `weg_col` `col_via` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name | col_synonym | dgfm_synonym',
    CHANGE `kandidaten_col` `col_candidates` smallint unsigned NULL DEFAULT NULL,
    CHANGE `autor_passt` `author_matches` tinyint unsigned NULL DEFAULT NULL COMMENT '1 = author confirms the match, 0 = contradicts, NULL = not checkable',
    CHANGE `weg_gbif` `gbif_via` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name | gbif_synonym | dgfm_synonym',
    CHANGE `kandidaten_gbif` `gbif_candidates` smallint unsigned NULL DEFAULT NULL,
    CHANGE `gueltiger_name` `valid_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'name valid today',
    CHANGE `quelle_gueltig` `valid_source` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'col | gbif | dgfm — who supplies it';

ALTER TABLE map_tokens
    CHANGE `geraet` `device` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `letzte_nutzung` `last_used_at` datetime NULL DEFAULT NULL,
    CHANGE `gueltig_bis` `valid_until` datetime NULL DEFAULT NULL;

ALTER TABLE map_users
    CHANGE `passwort_hash` `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    CHANGE `vorname` `first_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'the name one goes by',
    CHANGE `nachname` `last_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'family name',
    CHANGE `rolle` `role` enum('admin','benutzer','gast','user','guest') NOT NULL DEFAULT 'benutzer' COMMENT 'what this person may do',
    CHANGE `kontingent_bytes` `quota_bytes` bigint unsigned NULL DEFAULT NULL COMMENT 'limit for own files; NULL = unlimited',
    CHANGE `gesperrt_am` `locked_at` datetime NULL DEFAULT NULL COMMENT 'set means: no longer gets in',
    CHANGE `eingeladen_von` `invited_by` int unsigned NULL DEFAULT NULL,
    CHANGE `erstellt_am` `created_at` datetime NOT NULL,
    CHANGE `letzter_login` `last_login_at` datetime NULL DEFAULT NULL;

-- ---------------------------------------------------------------------
-- Stored values that are identifiers, not content.
-- ---------------------------------------------------------------------

-- Roles, friendship status, share rights, container member kinds: the
-- enums were widened above to hold both vocabularies; narrow them once
-- the rows have moved.
UPDATE map_users   SET role = 'user'  WHERE role = 'benutzer';
UPDATE map_users   SET role = 'guest' WHERE role = 'gast';
UPDATE map_invites SET role = 'user'  WHERE role = 'benutzer';
UPDATE map_invites SET role = 'guest' WHERE role = 'gast';
ALTER TABLE map_users   MODIFY role enum('admin','user','guest') NOT NULL DEFAULT 'user' COMMENT 'what this person may do';
ALTER TABLE map_invites MODIFY role enum('admin','user','guest') NOT NULL DEFAULT 'user' COMMENT 'the role the redeemed account receives';

-- status keeps its name, so no CHANGE above widened it — widen here.
ALTER TABLE map_friends MODIFY status enum('angefragt','angenommen','requested','accepted') NOT NULL DEFAULT 'angefragt';
UPDATE map_friends SET status = 'requested' WHERE status = 'angefragt';
UPDATE map_friends SET status = 'accepted'  WHERE status = 'angenommen';
ALTER TABLE map_friends MODIFY status enum('requested','accepted') NOT NULL DEFAULT 'requested';

UPDATE map_shares SET rights = 'read'  WHERE rights = 'lesen';
UPDATE map_shares SET rights = 'write' WHERE rights = 'schreiben';
ALTER TABLE map_shares MODIFY rights enum('read','write') NOT NULL DEFAULT 'read';

UPDATE map_container_members SET type = 'friend' WHERE type = 'freund';
UPDATE map_container_members SET type = 'group'  WHERE type = 'gruppe';
ALTER TABLE map_container_members MODIFY type enum('friend','group') NOT NULL;

-- List field kinds ('mehrzeilig' | 'auswahl' → 'multiline' | 'choice').
UPDATE map_list_fields SET type = 'multiline' WHERE type = 'mehrzeilig';
UPDATE map_list_fields SET type = 'choice'    WHERE type = 'auswahl';

-- Where a measurement came from.
UPDATE map_points SET elevation_source = 'outside'   WHERE elevation_source = 'ausserhalb';
UPDATE map_points SET elevation_source = 'worldwide' WHERE elevation_source = 'weltweit';
UPDATE map_points SET municipality_source = 'none'   WHERE municipality_source = 'ohne';

-- Species sources: the group keys that pick the name table.
UPDATE map_point_species SET source = 'fungi'   WHERE source = 'pilze';
UPDATE map_point_species SET source = 'lichens' WHERE source = 'flechten';
UPDATE map_point_species SET source = 'plants'  WHERE source = 'pflanzen';
UPDATE map_point_species SET source = 'animals' WHERE source = 'tiere';
UPDATE map_imports SET source = 'fungi'   WHERE source = 'pilze';
UPDATE map_imports SET source = 'lichens' WHERE source = 'flechten';
UPDATE map_imports SET source = 'plants'  WHERE source = 'pflanzen';
UPDATE map_imports SET source = 'animals' WHERE source = 'tiere';

-- The same keys inside the per-field references, '"source:identifier"'.
-- The ':"' anchor pins the value position; a field NAME could contain
-- these words, but never with a quote directly in front.
UPDATE map_points SET extra_species = REPLACE(REPLACE(REPLACE(REPLACE(extra_species,
    ':"pilze:', ':"fungi:'), ':"flechten:', ':"lichens:'), ':"pflanzen:', ':"plants:'), ':"tiere:', ':"animals:')
    WHERE extra_species IS NOT NULL;

-- ---------------------------------------------------------------------
-- Field keys stored as data. The set is closed: titel, notiz, ort,
-- listen, umriss, gefunden_am, zusatz.<name> (fieldtimes.js). The
-- 'zusatz.' prefix moves FIRST so a user field named 'titel' — key
-- 'zusatz.titel' — never matches the bare pattern afterwards: the
-- remaining patterns demand the quote directly before the word.
-- ---------------------------------------------------------------------
UPDATE map_points SET field_times =
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(field_times,
        '"zusatz.', '"extra.'),
        '"titel":', '"title":'),
        '"notiz":', '"note":'),
        '"ort":', '"position":'),
        '"listen":', '"lists":'),
        '"umriss":', '"outline":'),
        '"gefunden_am":', '"found_at":')
    WHERE field_times IS NOT NULL;

UPDATE map_points SET original =
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(original,
        '"zusatz.', '"extra.'),
        '"titel":', '"title":'),
        '"notiz":', '"note":'),
        '"ort":', '"position":'),
        '"listen":', '"lists":'),
        '"umriss":', '"outline":'),
        '"gefunden_am":', '"found_at":')
    WHERE original IS NOT NULL;

-- Inside each anchor of 'original' sit {wert, von, am} — value, author,
-- when. A user field named exactly 'wert'/'von'/'am' would be caught too;
-- the keys sit inside value objects, the collision is theoretical.
UPDATE map_points SET original =
    REPLACE(REPLACE(REPLACE(original, '"wert":', '"value":'), '"von":', '"by":'), '"am":', '"at":')
    WHERE original IS NOT NULL;

UPDATE map_point_overwritten SET field = CONCAT('extra.', SUBSTRING(field, 8)) WHERE field LIKE 'zusatz.%';
UPDATE map_point_overwritten SET field = 'title'    WHERE field = 'titel';
UPDATE map_point_overwritten SET field = 'note'     WHERE field = 'notiz';
UPDATE map_point_overwritten SET field = 'position' WHERE field = 'ort';
UPDATE map_point_overwritten SET field = 'lists'    WHERE field = 'listen';
UPDATE map_point_overwritten SET field = 'outline'  WHERE field = 'umriss';
UPDATE map_point_overwritten SET field = 'found_at' WHERE field = 'gefunden_am';

-- Granted keys of a shared list: a JSON array of the same field keys,
-- here without colons.
-- The grant vocabulary is its own (SHARE_RIGHTS in container.php):
-- verschieben, loeschen, bilder_loeschen, titel, notiz, feld:<name>.
-- bilder_loeschen before loeschen — the one contains the other.
UPDATE map_container_lists SET rights =
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(rights,
        '"feld:', '"field:'),
        '"titel"', '"title"'),
        '"notiz"', '"note"'),
        '"bilder_loeschen"', '"delete_photos"'),
        '"loeschen"', '"delete"'),
        '"verschieben"', '"move"')
    WHERE rights IS NOT NULL;

-- Filter JSON (containers carry their birth filter, autolists their
-- rule): {zeit:{von,bis,ohnezeit}, gebiet:{ecken,name}, begriffe:[{art,text}]}.
-- 'enthaelt_nicht' before 'enthaelt' — the one contains the other.
UPDATE map_containers SET filter =
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(filter,
        '"zeit":', '"time":'),
        '"quellen":', '"sources":'),
        '"gebiet":', '"area":'),
        '"begriffe":', '"terms":'),
        '"von":', '"from":'),
        '"bis":', '"until":'),
        '"ohnezeit":', '"undated":'),
        '"ecken":', '"corners":'),
        '"art":', '"type":'),
        '"enthaelt_nicht"', '"contains_not"'),
        '"enthaelt"', '"contains"')
    WHERE filter IS NOT NULL;

UPDATE map_lists SET filter =
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(filter,
        '"zeit":', '"time":'),
        '"quellen":', '"sources":'),
        '"gebiet":', '"area":'),
        '"begriffe":', '"terms":'),
        '"von":', '"from":'),
        '"bis":', '"until":'),
        '"ohnezeit":', '"undated":'),
        '"ecken":', '"corners":'),
        '"art":', '"type":'),
        '"enthaelt_nicht"', '"contains_not"'),
        '"enthaelt"', '"contains"')
    WHERE filter IS NOT NULL;

-- The CHECK constraints return, holding the English columns.
ALTER TABLE map_container_lists ADD CONSTRAINT chk_map_container_lists_rights CHECK (json_valid(`rights`));
ALTER TABLE map_areas ADD CONSTRAINT chk_map_areas_corners CHECK (json_valid(`corners`)),
    ADD CONSTRAINT chk_map_areas_measurements CHECK (json_valid(`measurements`));
ALTER TABLE map_list_fields ADD CONSTRAINT chk_map_list_fields_options CHECK (json_valid(`options`));
ALTER TABLE map_points ADD CONSTRAINT chk_map_points_outline CHECK (json_valid(`outline`)),
    ADD CONSTRAINT chk_map_points_extra CHECK (json_valid(`extra`)),
    ADD CONSTRAINT chk_map_points_extra_species CHECK (json_valid(`extra_species`)),
    ADD CONSTRAINT chk_map_points_field_times CHECK (json_valid(`field_times`)),
    ADD CONSTRAINT chk_map_points_original CHECK (json_valid(`original`));
ALTER TABLE map_point_overwritten ADD CONSTRAINT chk_map_point_overwritten_value CHECK (json_valid(`value`));

-- THE LEDGER (map_migrationen) STAYS UNTOUCHED HERE — deliberately. The
-- OLD tool applies this file and must record it afterwards without an
-- error. The NEW runner owns its own ledger instead: it creates
-- map_migrations itself and takes the old rows over (migrations.php);
-- 0064 then drops the German table. A fresh installation never has the
-- problem: its runner records into map_migrations from the first file on.
