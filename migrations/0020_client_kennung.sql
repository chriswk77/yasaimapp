-- The device's own identifier, against doubles when catching up.
--
-- Points set offline wait in the browser and are submitted later, as soon
-- as there is network again. In that there is a case one cannot wish away:
-- the request reaches the server, it creates the point — and the response
-- gets lost on the way back because the dead zone strikes exactly then.
-- The device then does not know whether it worked. If it tries again, the
-- point stands there twice.
--
-- Without a countermeasure, the only choice left would be between duplicate
-- and lost points. Both are unacceptable for find spots.
--
-- That is why the device assigns the identifier, not the server: if the
-- same one arrives a second time, the server returns the already created
-- point instead of producing a new one. The second attempt is thereby
-- without consequence, and the device may repeat as often as it wants.
--
-- The index applies per user: two devices could theoretically produce the
-- same random identifier — with 128 bits that does not happen, but the
-- promise should not depend on it.

ALTER TABLE map_points
    ADD client_id CHAR(36) NULL COMMENT 'vom Gerät vergeben, macht das Anlegen wiederholbar' AFTER user_id,
    ADD UNIQUE KEY uq_map_points_client (user_id, client_id);
