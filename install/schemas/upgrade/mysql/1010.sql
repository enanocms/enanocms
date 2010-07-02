-- Enano CMS
-- Upgrade schema - Enano 1.0 - 1.0.1

-- Fix for obnoxious $_GET issue
UPDATE {{TABLE_PREFIX}}sidebar SET block_type=1,block_content='<div class="slideblock2" style="padding: 0px;"><form action="$CONTENTPATH$$NS_SPECIAL$Search" method="get" style="padding: 0; margin: 0;"><p><input type="hidden" name="title" value="$NS_SPECIAL$Search" />$INPUT_AUTH$<input name="q" alt="Search box" type="text" size="10" style="width: 70%" /> <input type="submit" value="Go" style="width: 20%" /></p></form></div>' WHERE block_name='Search' AND item_id=4;
-- Added on advice from Neal
-- Remember that 1 = AUTH_DENY.
INSERT INTO {{TABLE_PREFIX}}acl(target_type,target_id,page_id,namespace,rules) VALUES(2,1,'Memberlist','Special','read=1;mod_misc=1;upload_files=1;upload_new_version=1;create_page=1;edit_acl=1;');
-- Bugfix for MySQL 5.0.45, see http://forum.enanocms.org/viewtopic.php?f=5&t=8
ALTER TABLE {{TABLE_PREFIX}}pages MODIFY COLUMN delvote_ips text DEFAULT NULL;
CREATE TABLE {{TABLE_PREFIX}}page_groups( pg_id mediumint(8) NOT NULL auto_increment, pg_type tinyint(2) NOT NULL DEFAULT 1, pg_name varchar(255) NOT NULL DEFAULT '', pg_target varchar(255) DEFAULT NULL, PRIMARY KEY ( pg_id ) ) CHARACTER SET `utf8` COLLATE `utf8_bin`;
CREATE TABLE {{TABLE_PREFIX}}page_group_members( pg_member_id int(12) NOT NULL auto_increment, pg_id mediumint(8) NOT NULL, page_id varchar(63) NOT NULL, namespace varchar(63) NOT NULL DEFAULT 'Article', PRIMARY KEY ( pg_member_id ) ) CHARACTER SET `utf8` COLLATE `utf8_bin`;
CREATE TABLE {{TABLE_PREFIX}}tags( tag_id int(12) NOT NULL auto_increment, tag_name varchar(63) NOT NULL DEFAULT 'bla', page_id varchar(255) NOT NULL, namespace varchar(255) NOT NULL, user mediumint(8) NOT NULL DEFAULT 1, PRIMARY KEY ( tag_id ) ) CHARACTER SET `utf8` COLLATE `utf8_bin`;
UPDATE {{TABLE_PREFIX}}acl SET rules=CONCAT(rules,'tag_create=4;tag_delete_own=4;tag_delete_other=4;') WHERE target_type=1 AND target_id=2;
DELETE FROM {{TABLE_PREFIX}}search_cache;

