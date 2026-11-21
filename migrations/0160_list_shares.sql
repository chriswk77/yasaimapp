-- LISTEN TEILEN — ONE SHARE PER LIST, RIGHTS AND FILTER PER RECIPIENT
-- (08./09.10.2026).
--
-- Until now a Freigabe was a box: its owner took people in, set one birth
-- filter for all, and anybody inside hung his own lists into it. „Geteilt"
-- had become a chaos category — who hung in what, who decides the filter,
-- who took people in, nobody could see. From now on everybody shares only
-- HIS OWN list, with the friends and own working groups he ticks, and
-- decides per recipient what that one sees and may do.
--
-- THE ENGINE STAYS, THE RULE IS NEW. A list's share is one container
-- whose owner is the list's owner and which holds exactly that list
-- (map_containers.list_id). Members, the sync to the device, the chat,
-- the list's trash and the handover keep running as they are tested.
-- The recipient rows (map_container_members) gain what used to sit once
-- per share: their own rights and their own filter — the sidebar's points
-- filter, evaluated on the server (includes/list_shares.php). „Gemeinsam
-- bearbeiten" is no switch any more but the right `own_points` on a
-- recipient row; the share row's `joint` flag follows it, so the list
-- trash and the chat lines keep working unchanged.
--
-- OLD BOXES KEEP RUNNING (list_id NULL) until the conversion button in
-- the administration has turned them into shares of their lists.

ALTER TABLE map_containers
    ADD COLUMN list_id INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'the shared root list — set for a list share, NULL for an old box',
    ADD UNIQUE KEY uq_container_list (list_id);

ALTER TABLE map_container_members
    ADD COLUMN rights LONGTEXT NULL DEFAULT NULL
        COMMENT 'granted keys as a JSON list (list share only), NULL = may see only',
    ADD COLUMN filter TEXT NULL DEFAULT NULL
        COMMENT 'the points filter as JSON (list share only), NULL = sees every point';

-- The heir of a joint list gets a window at his next map opening
-- (09.10.2026): a line in the chat is easily read past, and
-- whoever becomes Listenadministrator by chance should learn what it means.
ALTER TABLE map_container_state
    ADD COLUMN admin_notice TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = show the „du bist jetzt Listenadministrator" window once';

-- COLOUR AND SHAPE OF A SHARED LIST, PER VIEWER AND ACCOUNT
-- (09.10.2026: „individuell jedem Benutzer, also nicht jedes Gerät …
-- so dass es keine Konflikte mit bestehenden anderen Listen gibt").
-- Until now the look lived in the device's local storage.
CREATE TABLE map_shared_looks (
    user_id    INT UNSIGNED NOT NULL,
    list_id    INT UNSIGNED NOT NULL,
    color      CHAR(7)      NULL DEFAULT NULL,
    symbol     VARCHAR(40)  NULL DEFAULT NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, list_id),
    KEY ix_shared_looks_list (list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
