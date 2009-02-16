ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN password_salt varchar(40) NOT NULL DEFAULT '';
ALTER TABLE {{TABLE_PREFIX}}pages ADD COLUMN page_format varchar(16) NOT NULL DEFAULT 'wikitext';
ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN page_format varchar(16) NOT NULL DEFAULT 'wikitext';

-- Make page_id and namespace column sizes consistent (former bug)

ALTER TABLE {{TABLE_PREFIX}}comments MODIFY COLUMN page_id varchar(512) NOT NULL,
  MODIFY COLUMN namespace varchar(16) NOT NULL;

ALTER TABLE {{TABLE_PREFIX}}logs MODIFY COLUMN page_id varchar(512) NOT NULL,
  MODIFY COLUMN namespace varchar(16) NOT NULL;

ALTER TABLE {{TABLE_PREFIX}}page_text MODIFY COLUMN page_id varchar(512) NOT NULL;

ALTER TABLE {{TABLE_PREFIX}}pages MODIFY COLUMN urlname varchar(512) NOT NULL;

ALTER TABLE {{TABLE_PREFIX}}hits MODIFY COLUMN page_id varchar(512) NOT NULL,
  MODIFY COLUMN namespace varchar(16) NOT NULL;

ALTER TABLE {{TABLE_PREFIX}}acl MODIFY COLUMN page_id varchar(512),
  MODIFY COLUMN namespace varchar(16);

ALTER TABLE {{TABLE_PREFIX}}page_group_members MODIFY COLUMN page_id varchar(512) NOT NULL,
  MODIFY COLUMN namespace varchar(16) NOT NULL;

ALTER TABLE {{TABLE_PREFIX}}tags MODIFY COLUMN page_id varchar(512) NOT NULL,
  MODIFY COLUMN namespace varchar(16) NOT NULL;
