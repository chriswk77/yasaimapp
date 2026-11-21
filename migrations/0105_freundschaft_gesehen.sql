-- The asker learns that the answer came (13.09.2026, „Bekomme
-- ich eine Matchnachricht, wenn eine Freundschaftsanfrage angenommen
-- wurde? Wenn nicht, macht das bitte").
--
-- Until now only the ASKED side was told anything — the badge over the
-- map with its two buttons. The asker found out by looking at the friends
-- list, or when the other person's speech bubble appeared. seen_at is the
-- one column this needs: an accepted row whose asker has not seen the
-- acceptance is shown to him as a badge (api/v1/chat.php, the same poll
-- that carries the requests), and the cross on that badge writes the time
-- here — on the server, so the second device does not show it again.
--
-- THE STOCK COUNTS AS SEEN. Every friendship accepted before today would
-- otherwise greet its asker as news on the next opening, and news it is
-- not.
ALTER TABLE map_friends
    ADD COLUMN seen_at DATETIME NULL COMMENT 'wann der Fragende die Annahme gesehen hat' AFTER answered_at;

UPDATE map_friends SET seen_at = COALESCE(answered_at, created_at) WHERE status = 'accepted';
