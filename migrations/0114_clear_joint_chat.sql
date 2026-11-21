-- The chat of the share „Gemeinsam" starts afresh (23.09.2026:
-- „lösche einfach den Chatverlauf — da ist momentan eh noch nichts drin
-- außer die paar Sachen, die jetzt eine unvollständige Bezeichnung
-- haben"). Its lines came from the first joint-list weeks, when a change
-- line named only the fields and neither the values nor the list; the
-- values were never stored, so the lines cannot be completed. Only
-- the maintainer has worked in the joint lists so far.
--
-- GUARDED BY ID, NAME AND OWNER: container 8, „Gemeinsam", the maintainer's
-- (user 1). Any other share's chat is untouched. The read marks in
-- map_container_state may point past the last id — harmless: the next
-- line gets a higher id and counts as unread, as it should.
DELETE m FROM map_container_messages m
  JOIN map_containers c ON c.id = m.container_id
 WHERE c.id = 8 AND c.name = 'Gemeinsam' AND c.owner_user_id = 1;
