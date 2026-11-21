-- The original file name stays with the import (25.08.2026:
-- „der ist manchmal dann doch aussagekräftig").
--
-- WHAT THE LIST ALREADY SAYS is the source and the day („Takeout,
-- 25.08.2026") — but two takeouts of one day are two rows that look
-- alike, and „nordhang-2024.gpx" says at a glance which is which. The
-- name is written down as it arrived, only shortened; it is a label to
-- read, never a path to open.
--
-- NULL FOR EVERYTHING OLDER, and that stays honest: nobody wrote the
-- name down back then, so nobody may invent one now.
ALTER TABLE map_imports
    ADD COLUMN file_name VARCHAR(200) NULL COMMENT 'wie die hochgeladene Datei hiess' AFTER name,
    -- AND WHAT WAS IN IT (25.08.2026): with several files at
    -- once the same one slips in twice easily — dragged from two folders,
    -- or a stack dropped a second time. The hash answers „is this the
    -- very same file?" without asking anybody: identical content is
    -- skipped, and only that. A different file of the same NAME is a
    -- different file and goes through.
    ADD COLUMN file_hash CHAR(64) NULL COMMENT 'sha256 der hochgeladenen Datei' AFTER file_name,
    ADD KEY idx_import_hash (user_id, file_hash);
