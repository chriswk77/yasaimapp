-- The list symbol 'reh' is called 'hirsch'.
--
-- It was a red deer from the start: the drawing comes from game-icons.net
-- and carries antlers. 'reh' was the name from the conversation, not the
-- one from the picture — and a name that says something other than what
-- one sees confuses exactly the person who later searches for it.
--
-- The code still knows the old name (FORMEN.reh in karte.js), so that
-- lists do not fall back to the circle between deploying and applying.
-- After this migration that line there may go.

UPDATE map_lists SET symbol = 'hirsch' WHERE symbol = 'reh';
