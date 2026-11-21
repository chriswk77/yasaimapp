-- Guests give first and last name separately (24.08.2026) —
-- stored separately, because a later attribution or report wants to
-- sort by last name and a single field can never be split reliably
-- again. The NAME column stays as the display name every view already
-- reads (Ordnen, trash, the finder field); registering composes it.
ALTER TABLE map_excursion_guests
    ADD COLUMN first_name VARCHAR(100) NULL,
    ADD COLUMN last_name  VARCHAR(100) NULL;

-- Existing rows: split at the LAST space — "Anna Maria Muster" becomes
-- "Anna Maria" + "Muster". A single word counts as the first name (it is
-- what people give when they give one word). CHAR_LENGTH, not LENGTH:
-- byte arithmetic would cut inside an umlaut.
UPDATE map_excursion_guests
   SET last_name  = IF(INSTR(name, ' ') > 0, SUBSTRING_INDEX(name, ' ', -1), ''),
       first_name = IF(INSTR(name, ' ') > 0,
                       TRIM(SUBSTRING(name, 1,
                            CHAR_LENGTH(name) - CHAR_LENGTH(SUBSTRING_INDEX(name, ' ', -1)))),
                       name)
 WHERE first_name IS NULL;
