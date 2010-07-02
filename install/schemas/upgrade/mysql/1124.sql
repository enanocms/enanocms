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

-- Added in 1.1.6: Indices for several tables
-- The size of 317 is a trial-and-error-produced value based on MySQL's index column size limit
-- of 1000 bytes. It's low like that because of the UTF-8 character set being used.

@ALTER TABLE {{TABLE_PREFIX}}logs ADD INDEX {{TABLE_PREFIX}}logs_page_idx (page_id(317), namespace);
@ALTER TABLE {{TABLE_PREFIX}}logs ADD INDEX {{TABLE_PREFIX}}logs_time_idx (time_id);
@ALTER TABLE {{TABLE_PREFIX}}logs ADD INDEX {{TABLE_PREFIX}}logs_action_idx (log_type, action);
@ALTER TABLE {{TABLE_PREFIX}}comments ADD INDEX {{TABLE_PREFIX}}comments_page_idx (page_id(317), namespace);
@ALTER TABLE {{TABLE_PREFIX}}hits ADD INDEX {{TABLE_PREFIX}}hits_time_idx ( time );
@ALTER TABLE {{TABLE_PREFIX}}hits ADD INDEX {{TABLE_PREFIX}}hits_page_idx (page_id(317), namespace);
@ALTER TABLE {{TABLE_PREFIX}}pages ADD INDEX {{TABLE_PREFIX}}pages_page_idx (urlname(317), namespace);
@ALTER TABLE {{TABLE_PREFIX}}page_text ADD INDEX {{TABLE_PREFIX}}page_text_page_idx (page_id(317), namespace);

