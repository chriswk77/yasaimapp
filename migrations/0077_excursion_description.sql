-- The excursion tells about itself (24.08.2026): where it
-- goes, which forest, what to expect. The text is the INVITATION — it
-- stands on the guest page behind the QR code and the link, and the
-- planned mail dispatch will carry the same words. NULL for the many
-- excursions that need no prose.
ALTER TABLE map_excursions
    ADD COLUMN description TEXT NULL;
