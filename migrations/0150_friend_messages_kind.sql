-- A FRIEND CHAT LEARNS THE CHANGE LINE (02.10.2026).
--
-- „im Chat eine kleine Statusmeldung: Anna hat mit dir den
-- Punkt Boletus edulis geteilt. Findest du bei den Listen unter Geteilt,
-- dort bei Direkt geteilt und dort Von Anna." When a point is shared
-- directly (map_point_hints, 0149), the friend hears of it in the chat the
-- two already share.
--
-- THE SAME TWO COLUMNS THE SHARES' CHAT GOT in 0111 and 0121: 'kind' says
-- whether somebody spoke ('chat') or the map wrote a fact ('change') — the
-- browser draws the second quietly, in one line with the actor's name in
-- front, never in a bubble (assets/js/chat.js, row) — and 'meta' says what
-- the line is about. Every row so far was spoken, so the default is 'chat'.
--
-- THE CODE CAN GO LIVE FIRST: without the column no line is written at all
-- rather than one the sharer would seem to have typed himself.

ALTER TABLE map_friend_messages
    ADD COLUMN kind ENUM('chat', 'change') NOT NULL DEFAULT 'chat' AFTER text,
    ADD COLUMN meta TEXT NULL AFTER kind;
