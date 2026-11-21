-- MAPPEN STATT UNTERLISTEN — FLAT LISTS, GROUPED ONE LEVEL DEEP
-- (11.10.2026).
--
-- Until now a list could carry sublists to any depth, and a list with
-- sublists was a heading: it held no points and passed its settings down —
-- species list, fields, the share with its rights and filter, „gemeinsam
-- bearbeiten", the auto lists' sources. Whoever wanted to share one branch
-- differently, or give one sublist its own field, could not. From now on
-- every list carries ALL its settings itself, and a MAPPE only gathers
-- lists under one name in the sidebar — no settings, no points, no share,
-- and no Mappe inside a Mappe („Die Mappe hat überhaupt keine Funktion,
-- außer optisch zusammengefasst").
--
-- A TABLE OF ITS OWN, NOT A KIND OF LIST. A Mappe as a row of map_lists
-- would reach every place that reads lists — the point picker, the shares,
-- the auto lists, the exports — and each would have to learn to skip it.
-- Its own table reaches nowhere it is not asked for.
--
-- THE DATA MOVES BY BUTTON, NOT HERE. Turning a heading into a Mappe copies
-- its share (members, rights, filters) onto every list below it — PHP's
-- work, with a preview first, like „Freigaben umstellen"
-- (includes/folders_convert_content.php). Until it is pressed, parent_id
-- goes on meaning what it meant.

CREATE TABLE IF NOT EXISTS map_list_folders (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    client_id  CHAR(36) NULL DEFAULT NULL COMMENT 'assigned by the device, makes creation repeatable',
    name       VARCHAR(100) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0 COMMENT 'one sequence with the lists outside a Mappe',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_list_folders_client (user_id, client_id),
    KEY idx_list_folders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deleting a Mappe leaves its lists standing, outside any Mappe.
ALTER TABLE map_lists
    ADD COLUMN folder_id INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'the Mappe the list lies in, NULL outside any' AFTER parent_id,
    ADD KEY idx_lists_folder (folder_id),
    ADD CONSTRAINT fk_lists_folder FOREIGN KEY (folder_id)
        REFERENCES map_list_folders (id) ON DELETE SET NULL;

-- An import that brought sublists (Takeout, KML) brings a Mappe now; its
-- removal takes the Mappe and the lists in it.
ALTER TABLE map_imports
    ADD COLUMN folder_id INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'the Mappe of the import, when it brought several lists' AFTER list_id;
