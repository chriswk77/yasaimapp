-- The find number (23.08.2026): every find gets one, and its
-- shape is the user's choice.
--
-- WITHOUT A PATTERN NOTHING IS STORED: the number then simply IS the
-- database id, readable from the row itself — that it jumps (other
-- people's points land in between) is known and accepted. Only whoever
-- configures a pattern in the settings gets minted numbers.
--
-- THE MINTED NUMBER IS STORED, NOT DERIVED. A derived running number
-- would renumber the whole collection whenever a point is deleted — a
-- find number is a label on a record, it must never change under one's
-- hands. Hence a column on the point, written once at creation and never
-- touched again; numbers of deleted finds are not reused, like in any
-- paper collection book.
--
-- THE COUNTER LIVES ON THE ACCOUNT, not in MAX(find_number): the numbers
-- are text (patterns put prose around them), and a counter that has to
-- parse its own output breaks the day the pattern changes. find_counter
-- holds the NEXT number to assign; the settings let it be set, so an
-- existing collection can continue at its real count.
ALTER TABLE map_users
    ADD COLUMN find_number_pattern VARCHAR(60) NULL
        COMMENT 'shape of minted find numbers; NULL = plain database id',
    ADD COLUMN find_counter INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'next running number to assign';

ALTER TABLE map_points
    ADD COLUMN find_number VARCHAR(80) NULL
        COMMENT 'minted at creation from the pattern; NULL = the id speaks';
