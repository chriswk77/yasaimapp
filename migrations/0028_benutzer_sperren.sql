-- Shut down an account without throwing it away.
--
-- BETWEEN "MAY ENTER" AND "NO LONGER EXISTS" the step in between was
-- missing. Deleting takes points, lists and photos with it — which is as it
-- should be, but it is final, and for "this one should stay out for now" it
-- is too much.
--
-- Blocked means: no longer gets at the application, but keeps everything.
-- Whoever is unblocked finds their points again as if nothing had happened.
--
-- THE TIMESTAMP INSTEAD OF A CHECKMARK, for the same reason as with the
-- trash: "blocked since August 9" answers a question somebody is bound to
-- ask later. A yes/no no longer answers it.

ALTER TABLE map_users
    ADD gesperrt_am DATETIME NULL COMMENT 'gesetzt heisst: kommt nicht mehr herein' AFTER kontingent_bytes;
