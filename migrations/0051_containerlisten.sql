-- Container lists: own lists meant to be seen only inside the container.
--
-- The container checkmark in the Ordnen dialog creates, when needed, a
-- list named after the container by itself and hangs it in
-- (ordnen_container_ziel). This list is a vehicle: it carries what one
-- gives into the joint mapping — and the maintainer therefore wants it NOT
-- under "Meine Listen", only under its container (call of 14.08.2026).
--
-- The column marks exactly that. It hides the list in one's own tree
-- (sidebar of the map, Ordnen on the left, own section of the lists
-- dialog); under the container it still appears, and in the point window
-- it stays operable as a checkbox — memberships are managed there, that
-- is not browsing the tree. Lists created by hand and then hung in do not
-- get the mark: whoever keeps a list deliberately also wants to see it.
ALTER TABLE map_lists
    ADD COLUMN ist_containerliste TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER ist_arten;
