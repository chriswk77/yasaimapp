-- Abolish the main list.
--
-- It was meant to catch points that would otherwise stand without an
-- assignment when their list is deleted. The idea was right, the execution
-- was not: where things got caught was decided by a checkmark set at some
-- point and long forgotten. Whoever deletes a list a year later then looks
-- for their points in a list they do not think of — and does not find them.
--
-- A silent redirect to a place one determined a year ago is not a rescue
-- but a trap.
--
-- Catching still happens, but visibly: on deletion a NEW list comes into
-- being, named after the deleted one and stamped with the date. It then
-- stands right at the top in view, instead of hiding in an old one.
--
-- The column goes away. It carried nothing but this one decision, and that
-- decision no longer exists. Lists that once were the main list remain as
-- ordinary lists — including the "Meine Markierungen" created by the
-- application itself, if it exists.

ALTER TABLE map_lists
    DROP COLUMN ist_hauptliste;
