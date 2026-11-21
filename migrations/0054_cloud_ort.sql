-- Where the account sits at the provider.
--
-- WHY THIS NEEDS A COLUMN OF ITS OWN. pCloud runs two separate data centres
-- with their OWN addresses: api.pcloud.com (US) and eapi.pcloud.com (Europe).
-- An account lives in exactly one of them, and the wrong address does not
-- answer with "elsewhere" but with "login failed" — whoever does not know
-- that hunts for the mistake in the token.
--
-- pCloud states which one on the way back from the consent screen (parameter
-- 'hostname'). That fact therefore belongs to the connection: without it every
-- later call would have to guess or try both addresses.
--
-- DELIBERATELY NAMED IN GENERAL TERMS, not 'hostname'. The same column carries
-- the region for an S3 store (eu-central-1) and nothing at all for Google
-- Drive. A name from one provider's world would already be wrong for the
-- second.

ALTER TABLE map_cloud_zugaenge
    ADD COLUMN ort varchar(64) NULL
        COMMENT 'pCloud: api./eapi.pcloud.com — S3: region. NULL means the provider does not need it'
        AFTER anbieter;
