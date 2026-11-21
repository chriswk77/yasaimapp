-- Picture ids learn strings — every cloud names its files its own way
-- (26.09.2026: „alle Clouds richtig nutzen … dafür sind sie
-- gedacht, das war der Grundgedanke").
--
-- pCloud numbers its files, and BIGINT was the honest type while the
-- pictures went there alone; 0086 widened only the backup's column and
-- said why — widening before the code that writes strings exists would be
-- a guess about that code. The code is coming now: Google Drive names a
-- file with some 33 characters, OneDrive with an id like "01ABC!123", and
-- at S3 the id IS the object key, the whole path. The pCloud numbers ride
-- along as digit strings; nothing computes with an id, it is only handed
-- back.
--
-- 512 AND NOT 1024: the unique key of map_cloud_files spans user, provider
-- and id, and InnoDB's index limit is 3072 bytes — 4 + 80 + 512 × 4 fits,
-- 1024 × 4 would not. The paths beside the ids already stop at 512, and an
-- S3 key is made by us from such a path; the storage layer keeps it
-- inside. ROW_FORMAT=DYNAMIC makes that 3072 the limit in force on any
-- server — the tables are small, the rebuild costs nothing.
--
-- And the calibration image learns WHERE it lies: map_photo_scales gets
-- the provider beside its fileid, as every other picture row has one. The
-- images there so far can only be pCloud's.

ALTER TABLE map_cloud_files ROW_FORMAT=DYNAMIC,
    MODIFY fileid   VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        COMMENT 'the provider''s id — a pCloud number, a Drive or OneDrive id, an S3 key',
    MODIFY folderid VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
        COMMENT 'the folder it lies in — an id, or at S3 the prefix';

ALTER TABLE map_photos ROW_FORMAT=DYNAMIC,
    MODIFY cloud_fileid   VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
        COMMENT 'the provider''s id for the picture — NULL = on this server',
    MODIFY cloud_folderid VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
        COMMENT 'the folder it went into, for the link — an id, or at S3 the prefix';

ALTER TABLE map_cloud_copies ROW_FORMAT=DYNAMIC,
    MODIFY fileid   VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
        COMMENT 'the copy''s id at the recipient''s provider',
    MODIFY folderid VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
        COMMENT 'the folder the copy lies in — an id, or at S3 the prefix';

ALTER TABLE map_photo_scales ROW_FORMAT=DYNAMIC,
    MODIFY fileid VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
        COMMENT 'the calibration image''s id at the provider, in the folder Kalibrierung — NULL = none',
    ADD COLUMN provider VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
        COMMENT 'where the calibration image lies — pcloud, gdrive, onedrive, s3; NULL = none'
        AFTER fileid;

UPDATE map_photo_scales SET provider = 'pcloud' WHERE fileid IS NOT NULL;
