-- The personal chat between two friends.
--
-- The container chat (0060) answers "what is going on in OUR shared
-- patch"; what was missing was the quiet word aside — "are you coming on
-- Saturday?" does not belong in front of the whole container. The maintainer's
-- wish of 22.08.2026: behind every friend a personal conversation, built
-- exactly like the container chat.
--
-- THE FRIENDSHIP IS THE ROOM. A message hangs on the map_friends row, not
-- on a pair of user ids: being friends IS the read right, the same law as
-- membership in the container chat. Both sides read everything, there are
-- no recipients and no visibility per line.
--
-- THE CHAT DIES WITH THE FRIENDSHIP, deliberately. friendship_remove()
-- deletes the messages along with the row — whoever ends a friendship
-- ends the conversation, and whoever asks again later starts a new row
-- with a new id and an empty conversation. Keeping the old words around
-- would mean a record of a connection both sides declared over; that is
-- the bucket, not the door (CLAUDE.md, three farewells).
--
-- NO DELETING PER MESSAGE, for the container chat's reason: between two
-- people a vanished line is worse than a regretted one.
--
-- TEXT AND NOT VARCHAR from the start — 0061 already walked that path for
-- the container: the cap is a reasonableness cap and lives in ONE place,
-- CHAT_MAX in includes/chat.php.
CREATE TABLE IF NOT EXISTS map_friend_messages (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    friendship_id INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL COMMENT 'who wrote',
    text          TEXT NOT NULL,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    -- The question is always "everything in this conversation from
    -- number X on" — exactly this index, and no second one is needed.
    KEY friendship_run (friendship_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Up to where each of the two has read. The container chat could lean on
-- map_container_state, which already carried one row per member; the
-- friendship has no such table, so this small one is it. Two rows per
-- conversation at most, removed together with the messages.
--
-- 0 means "nothing read yet" and is the right start: the numbers begin
-- at 1, and whoever has never opened the conversation really has not.
CREATE TABLE IF NOT EXISTS map_friend_message_state (
    friendship_id INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    read_up_to    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'highest read message number',
    updated_at    DATETIME NOT NULL,
    PRIMARY KEY (friendship_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
