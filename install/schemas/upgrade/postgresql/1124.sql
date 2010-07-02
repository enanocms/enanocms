ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN password_salt varchar(40) NOT NULL DEFAULT '';
ALTER TABLE {{TABLE_PREFIX}}pages ADD COLUMN page_format varchar(16) NOT NULL DEFAULT 'wikitext';
ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN page_format varchar(16) NOT NULL DEFAULT 'wikitext';

--
-- Make page_id and namespace column sizes consistent (former bug)
-- Yes, this is a PITA in PostgreSQL.
--

-- comments
ALTER TABLE {{TABLE_PREFIX}}comments ADD COLUMN page_id_new varchar(512) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}comments SET page_id_new = page_id;
ALTER TABLE {{TABLE_PREFIX}}comments DROP page_id;
ALTER TABLE {{TABLE_PREFIX}}comments RENAME page_id_new TO page_id;

ALTER TABLE {{TABLE_PREFIX}}comments ADD COLUMN namespace_new varchar(16) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}comments SET namespace_new = namespace;
ALTER TABLE {{TABLE_PREFIX}}comments DROP namespace;
ALTER TABLE {{TABLE_PREFIX}}comments RENAME namespace_new TO namespace;

-- logs
ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN page_id_new varchar(512) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}logs SET page_id_new = page_id;
ALTER TABLE {{TABLE_PREFIX}}logs DROP page_id;
ALTER TABLE {{TABLE_PREFIX}}logs RENAME page_id_new TO page_id;

ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN namespace_new varchar(16) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}logs SET namespace_new = namespace;
ALTER TABLE {{TABLE_PREFIX}}logs DROP namespace;
ALTER TABLE {{TABLE_PREFIX}}logs RENAME namespace_new TO namespace;

-- page_text
ALTER TABLE {{TABLE_PREFIX}}page_text ADD COLUMN page_id_new varchar(512) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}page_text SET page_id_new = page_id;
ALTER TABLE {{TABLE_PREFIX}}page_text DROP page_id;
ALTER TABLE {{TABLE_PREFIX}}page_text RENAME page_id_new TO page_id;

-- pages
ALTER TABLE {{TABLE_PREFIX}}pages ADD COLUMN urlname_new varchar(512) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}pages SET urlname_new = urlname;
ALTER TABLE {{TABLE_PREFIX}}pages DROP urlname;
ALTER TABLE {{TABLE_PREFIX}}pages RENAME urlname_new TO urlname;

-- hits
ALTER TABLE {{TABLE_PREFIX}}hits ADD COLUMN page_id_new varchar(512) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}hits SET page_id_new = page_id;
ALTER TABLE {{TABLE_PREFIX}}hits DROP page_id;
ALTER TABLE {{TABLE_PREFIX}}hits RENAME page_id_new TO page_id;

ALTER TABLE {{TABLE_PREFIX}}hits ADD COLUMN namespace_new varchar(16) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}hits SET namespace_new = namespace;
ALTER TABLE {{TABLE_PREFIX}}hits DROP namespace;
ALTER TABLE {{TABLE_PREFIX}}hits RENAME namespace_new TO namespace;

-- acl
ALTER TABLE {{TABLE_PREFIX}}acl ADD COLUMN page_id_new varchar(512) DEFAULT NULL;
UPDATE {{TABLE_PREFIX}}acl SET page_id_new = page_id;
ALTER TABLE {{TABLE_PREFIX}}acl DROP page_id;
ALTER TABLE {{TABLE_PREFIX}}acl RENAME page_id_new TO page_id;

ALTER TABLE {{TABLE_PREFIX}}acl ADD COLUMN namespace_new varchar(16) DEFAULT NULL;
UPDATE {{TABLE_PREFIX}}acl SET namespace_new = namespace;
ALTER TABLE {{TABLE_PREFIX}}acl DROP namespace;
ALTER TABLE {{TABLE_PREFIX}}acl RENAME namespace_new TO namespace;

-- page_group_members
ALTER TABLE {{TABLE_PREFIX}}page_group_members ADD COLUMN page_id_new varchar(512) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}page_group_members SET page_id_new = page_id;
ALTER TABLE {{TABLE_PREFIX}}page_group_members DROP page_id;
ALTER TABLE {{TABLE_PREFIX}}page_group_members RENAME page_id_new TO page_id;

ALTER TABLE {{TABLE_PREFIX}}page_group_members ADD COLUMN namespace_new varchar(16) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}page_group_members SET namespace_new = namespace;
ALTER TABLE {{TABLE_PREFIX}}page_group_members DROP namespace;
ALTER TABLE {{TABLE_PREFIX}}page_group_members RENAME namespace_new TO namespace;

-- tags
ALTER TABLE {{TABLE_PREFIX}}tags ADD COLUMN page_id_new varchar(512) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}tags SET page_id_new = page_id;
ALTER TABLE {{TABLE_PREFIX}}tags DROP page_id;
ALTER TABLE {{TABLE_PREFIX}}tags RENAME page_id_new TO page_id;

ALTER TABLE {{TABLE_PREFIX}}tags ADD COLUMN namespace_new varchar(16) NOT NULL DEFAULT '';
UPDATE {{TABLE_PREFIX}}tags SET namespace_new = namespace;
ALTER TABLE {{TABLE_PREFIX}}tags DROP namespace;
ALTER TABLE {{TABLE_PREFIX}}tags RENAME namespace_new TO namespace;
