-- A tour may exist before it has points (23.08.2026: "einfach
-- nur eine leere neue Tour erstellen").
--
-- THE LABEL ON THE POINT STAYS THE TRUTH (migration 0071) — this table is
-- a NAME REGISTRY beside it, not a second storage place for the
-- membership. A point keeps carrying its tour as text, which is what lets
-- a tour begun in a dead zone travel with the point and needs no
-- provisional ids; the registry only lets a name stand there while no
-- point carries it yet. Whoever lists tours unites both sides: the names
-- on the points and the names registered here.
--
-- That is also why nothing needs backfilling: every tour that already
-- exists is visible through its points, registry or not.

CREATE TABLE IF NOT EXISTS map_tours (
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, name),
    CONSTRAINT fk_tours_user FOREIGN KEY (user_id)
        REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
