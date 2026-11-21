-- DNA sequences at a find — the second kind of voucher
-- (22.09.2026: „ein eigener Upload für eine Gensequenz, mit ihren eigenen
-- Formaten, so dass man zu dem Fund als Mitbearbeiter hinzugefügt wird.
-- Ein reiner Bild-Upload wäre der falsche Weg: eine Gensequenz ist Text,
-- und Text können wir später verarbeiten und weiter auswerten").
--
-- A SEQUENCE IS THE SEQUENCER'S, the way a picture is the photographer's
-- (map_point_owners, migration 0098): whoever hangs one on somebody else's
-- find becomes a co-owner and keeps the find, friendship or not. Only the
-- sequencer deletes his sequence. Several per find (ITS and LSU apart).
--
-- THE TEXT LIVES HERE, ON THE SERVER — unlike the pictures, which the
-- server never holds. A sequence is a thousand letters, and it is the
-- one voucher we want to compare and export later; a copy in every
-- co-owner's cloud would be a copy of nothing. Chromatograms (AB1) are
-- deliberately not here yet: binary, hundreds of kilobytes — they will go
-- the pictures' way, into the cloud, when they come.
--
-- Cascade on the point: a find gone for good takes its sequences along,
-- like its pictures. Cascade on the user: an account that is deleted
-- takes its vouchers (the find stays, as it does when a photographer goes).
CREATE TABLE map_point_sequences (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    point_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL COMMENT 'the sequencer — whose voucher this is',
    marker     VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'ITS, LSU, RPB2 …',
    label      VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'the FASTA header, or what the uploader called it',
    bases      MEDIUMTEXT   NOT NULL COMMENT 'upper case, IUPAC letters and gaps, nothing else',
    length     INT UNSIGNED NOT NULL,
    lab        VARCHAR(120) NOT NULL DEFAULT '',
    sample_no  VARCHAR(80)  NOT NULL DEFAULT '',
    accession  VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'GenBank or UNITE',
    primers    VARCHAR(120) NOT NULL DEFAULT '',
    note       TEXT         NULL,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY point_id (point_id),
    KEY user_id (user_id),
    CONSTRAINT fk_sequence_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE,
    CONSTRAINT fk_sequence_user  FOREIGN KEY (user_id)  REFERENCES map_users (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
