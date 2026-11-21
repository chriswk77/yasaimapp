-- PLATES — A4 COMPOSITIONS OF A FIND'S PICTURES (06.10.2026).
--
-- With two plates of the late Matthias Reul in hand: „So
-- etwas stelle ich mir vor … immer im A4-Format. Vielleicht wie das
-- Beispiel zeigt auch mal zwei A4-Seiten. Und die einzelnen Bilder kann
-- man dann dort reinziehen … Solche Tafeln sind quasi das Ziel, auf das
-- ich hinarbeite mit dem Bildbetrachter." Pictures cut to rectangles,
-- edge to edge, each with its own scale bar, a few texts, a field with
-- the find's details placed wherever he likes.
--
-- ONE ROW PER PLATE, the layout as JSON (includes/plates.php describes
-- it): pages, each a tree of splits and cells, a cell a cutout of one
-- picture of the find. The pictures themselves are not copied — a cell
-- names its picture ('photo:63', 'cloud:12'), and the plate draws it from
-- where it lies, its scale bar from the picture's own calibration. The
-- maker owns the plate; who may see the find sees it.

CREATE TABLE map_plates (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    point_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL COMMENT 'who made it — only he edits it',
    layout     MEDIUMTEXT   NOT NULL COMMENT 'pages, cells and settings as JSON',
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_plates_point (point_id),
    CONSTRAINT fk_plates_user  FOREIGN KEY (user_id)  REFERENCES map_users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_plates_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
