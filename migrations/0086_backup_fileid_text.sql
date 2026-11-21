-- The backup's file id learns strings — Google Drive names its files so.
--
-- pCloud numbers its files, and BIGINT was the honest type for that. Drive
-- hands out opaque strings of around 33 characters, and a second storage
-- provider was the plan from the first day of the column (its comment
-- already hedged: "oder NULL wenn er keine nennt"). VARCHAR carries both:
-- the pCloud numbers ride along as digit strings, and nothing compares
-- them arithmetically — the id is a name to hand back, never a number to
-- compute with.
--
-- ONLY THE BACKUP TABLE. map_photos.cloud_fileid and map_cloud_files stay
-- numeric: the voucher photos keep moving to pCloud only for now, and
-- widening their columns before the code that would write strings into
-- them exists would be a guess about that code's shape.
ALTER TABLE map_cloud_backups
    MODIFY fileid VARCHAR(128) NULL
        COMMENT 'die Kennung beim Anbieter — pCloud zählt, Drive benennt; NULL wenn er keine nennt';
