-- Enano CMS
-- Upgrade schema: 1.1.2 - 1.1.3

-- Storing obscenely huge integers as strings since that's how php processes them.

CREATE TABLE {{TABLE_PREFIX}}diffiehellman (
  key_id SERIAL,
  private_key text,
  public_key text,
  PRIMARY KEY ( key_id )
);

ALTER TABLE {{TABLE_PREFIX}}themes DROP group_policy, ADD COLUMN group_policy varchar(9) NOT NULL DEFAULT 'allow_all', ADD CHECK ( group_policy IN ('allow_all', 'whitelist', 'blacklist') );

ALTER TABLE {{TABLE_PREFIX}}session_keys ALTER COLUMN source_ip TYPE varchar(39),
                                         ADD CHECK ( source_ip IS NOT NULL ),
                                         ALTER COLUMN source_ip SET DEFAULT '127.0.0.1';

