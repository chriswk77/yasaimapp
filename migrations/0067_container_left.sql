-- WHO HAS LEFT A CONTAINER — for themselves, without touching anybody
-- else's access.
--
-- WHY A TABLE AND NOT A DELETE. Access comes in three ways: one is the
-- owner, one was admitted as a friend, or one sits in a group that was
-- admitted. The first two can be ended by removing a row. The third
-- cannot: the entry says "group X may", and deleting it would throw out
-- everyone in that group. Until now the only lever a group member had was
-- to leave the GROUP itself — which drops them out of every container
-- that group is in at once, invisibly, and unhooks the lists they had
-- hung in there. The maintainer on 22.08.2026: better to leave containers one
-- by one, where one can see who else is in.
--
-- A row here therefore says: whatever door was open for this person, they
-- have stepped out of THIS container. container_member_ids() subtracts
-- them, and everything downstream follows on its own — visibility, the
-- buckets, the chat, and container_cleanup(), which unhooks the lists of
-- anyone who is no longer a member.
--
-- COMING BACK is the owner's act: adding the person as a friend clears
-- the row (see container_add). Adding a GROUP does not — that is an act
-- towards the group, not towards somebody who deliberately walked out.
CREATE TABLE IF NOT EXISTS map_container_left (
    container_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    left_at      DATETIME     NOT NULL,
    PRIMARY KEY (container_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
