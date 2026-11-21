-- The device's own identifier for lists too.
--
-- The same problem as with the points (0020): if the request arrives and
-- only the response gets lost, the device repeats — and without a
-- countermeasure the list stands there twice.
--
-- Since the queue no longer hangs on individual call sites but lies beneath
-- the whole interface, this applies to everything that can be created.
-- Lists are the second; whatever joins later gets the same column and is
-- thus offline-capable from the start.

ALTER TABLE map_lists
    ADD client_id CHAR(36) NULL COMMENT 'vom Gerät vergeben, macht das Anlegen wiederholbar' AFTER user_id,
    ADD UNIQUE KEY uq_map_lists_client (user_id, client_id);
