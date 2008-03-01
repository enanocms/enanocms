-- Enano CMS
-- Upgrade schema: 1.1.2 - 1.1.3

-- Storing obscenely huge integers as strings since that's how php processes them.

CREATE TABLE {{TABLE_PREFIX}}diffiehellman (
  key_id int(12) NOT NULL auto_increment,
  private_key text,
  public_key text,
  PRIMARY KEY ( key_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

ALTER TABLE {{TABLE_PREFIX}}session_keys MODIFY COLUMN source_ip varchar(39) NOT NULL DEFAULT '127.0.0.1';
ALTER TABLE {{TABLE_PREFIX}}themes MODIFY COLUMN group_policy ENUM('allow_all', 'whitelist', 'blacklist') NOT NULL DEFAULT 'allow_all';
UPDATE {{TABLE_PREFIX}}themes SET group_policy = 'allow_all';
