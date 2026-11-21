-- The room can grow without the participants doing anything: the owner
-- later adds a group, or an added group gets new members — and suddenly
-- the hung-in lists lie before eyes nobody ever confirmed. With a handful
-- of friends that was harmless; for excursions with many unfamiliar
-- participants it no longer is.
--
-- This table remembers, per user and container, which membership state
-- they last took note of — as a snapshot of the identifiers, not as a
-- point in time: a snapshot captures every way of growing (added directly,
-- group added, group grown) without having to compare timestamps at every
-- source. Whoever leaves and comes back is "new" again afterwards — that
-- is intended, the room has changed after all.
--
-- No foreign key, as with the neighboring tables from 0041/0042 —
-- container_aufraeumen() vouches for that instead: rows without an own
-- share in the container (or without a container) are cleared away there.

CREATE TABLE map_container_gesehen (
    user_id         INT UNSIGNED NOT NULL,
    container_id    INT UNSIGNED NOT NULL,
    -- JSON list of the member identifiers, state at the last acknowledgment
    mitglieder      TEXT NOT NULL,
    aktualisiert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, container_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
