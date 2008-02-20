-- Enano CMS
-- Upgrade schema: 1.1.2 - 1.1.3

-- Storing obscenely huge integers as strings since that's how php processes them.

CREATE TABLE {{TABLE_PREFIX}}diffiehellman (
  key_id SERIAL,
  private_key text,
  public_key text,
  PRIMARY KEY ( key_id )
);
