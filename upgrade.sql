-- Enano CMS - upgrade SQL
-- Variables are in the format {{VAR_NAME}}
-- ALL NON-SQL LINES, even otherwise blank lines, must start with "--" or they will get sent to MySQL!
-- Common tasks (version numbers)
DELETE FROM {{TABLE_PREFIX}}config WHERE config_name='enano_version' OR config_name='enano_beta_version' OR config_name='enano_alpha_version' OR config_name='enano_rc_version';
INSERT INTO {{TABLE_PREFIX}}config (config_name, config_value) VALUES( 'enano_version', '1.0.2' );
---BEGIN 1.0.2b1---
-- This is really optional, but could reduce confusion if regex page groups get truncated for no apparent reason.
ALTER TABLE {{TABLE_PREFIX}}page_groups MODIFY COLUMN pg_target text DEFAULT NULL;
---END 1.0.2b1---
---BEGIN 1.0.1.1---
---END 1.0.1.1---
---BEGIN 1.0.1---
---END 1.0.1---
---BEGIN 1.0---
-- Fix for obnoxious $_GET issue
UPDATE {{TABLE_PREFIX}}sidebar SET block_type=1,block_content='<div class="slideblock2" style="padding: 0px;"><form action="$CONTENTPATH$$NS_SPECIAL$Search" method="get" style="padding: 0; margin: 0;"><p><input type="hidden" name="title" value="$NS_SPECIAL$Search" />$INPUT_AUTH$<input name="q" alt="Search box" type="text" size="10" style="width: 70%" /> <input type="submit" value="Go" style="width: 20%" /></p></form></div>' WHERE block_name='Search' AND item_id=4;
-- Added on advice from Neal
INSERT INTO {{TABLE_PREFIX}}acl(target_type,target_id,page_id,namespace,rules) VALUES(2,1,'Memberlist','Special','read=1;mod_misc=1;upload_files=1;upload_new_version=1;create_page=1;edit_acl=1;');
-- Bugfix for MySQL 5.0.45, see http://forum.enanocms.org/viewtopic.php?f=5&t=8
ALTER TABLE {{TABLE_PREFIX}}pages MODIFY COLUMN delvote_ips text DEFAULT NULL;
CREATE TABLE {{TABLE_PREFIX}}page_groups( pg_id mediumint(8) NOT NULL auto_increment, pg_type tinyint(2) NOT NULL DEFAULT 1, pg_name varchar(255) NOT NULL DEFAULT '', pg_target varchar(255) DEFAULT NULL, PRIMARY KEY ( pg_id ) ) CHARACTER SET `utf8` COLLATE `utf8_bin`;
CREATE TABLE {{TABLE_PREFIX}}page_group_members( pg_member_id int(12) NOT NULL auto_increment, pg_id mediumint(8) NOT NULL, page_id varchar(63) NOT NULL, namespace varchar(63) NOT NULL DEFAULT 'Article', PRIMARY KEY ( pg_member_id ) ) CHARACTER SET `utf8` COLLATE `utf8_bin`;
CREATE TABLE {{TABLE_PREFIX}}tags( tag_id int(12) NOT NULL auto_increment, tag_name varchar(63) NOT NULL DEFAULT 'bla', page_id varchar(255) NOT NULL, namespace varchar(255) NOT NULL, user mediumint(8) NOT NULL DEFAULT 1, PRIMARY KEY ( tag_id ) ) CHARACTER SET `utf8` COLLATE `utf8_bin`;
UPDATE {{TABLE_PREFIX}}acl SET rules=CONCAT(rules,'tag_create=4;tag_delete_own=4;tag_delete_other=4;') WHERE target_type=1 AND target_id=2;
DELETE FROM {{TABLE_PREFIX}}search_cache;
---END 1.0---
---BEGIN 1.0RC3---
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_coppa tinyint(1) NOT NULL DEFAULT 0;
UPDATE {{TABLE_PREFIX}}sidebar SET block_content='[[$NS_SPECIAL$CreatePage|Create a page]]\n[[$NS_SPECIAL$UploadFile|Upload file]]\n[[$NS_SPECIAL$SpecialPages|Special pages]]\n{if auth_admin}\n[[$NS_SPECIAL$EditSidebar|Edit the sidebar]]\n$ADMIN_LINK$\n{/if}' WHERE item_id=2;
UPDATE {{TABLE_PREFIX}}sidebar SET block_content='[[User:$USERNAME$|User page]]\n[[Special:Contributions/$USERNAME$|My Contributions]]\n{if user_logged_in}\n[[$NS_SPECIAL$Preferences|Preferences]]\n[[Special:PrivateMessages|Private messages ($UNREAD_PMS$)]]\n[[Special:Usergroups|Group control panel]]\n$THEME_LINK$\n{/if}\n{if user_logged_in}\n$LOGOUT_LINK$\n{else}\n[[Special:Register|Create an account]]\n$LOGIN_LINK$\n[[Special:Login/Special:PrivateMessages|Private messages]]\n{/if}' WHERE item_id=3;
-- Updated PHP-ized search box
-- block_type=3: 3 = BLOCK_PHP
UPDATE {{TABLE_PREFIX}}sidebar SET block_content='?><div class=\"slideblock2\" style=\"padding: 0px;\"><form action=\"<?php echo makeUrlNS(\'Special\', \'Search\'); ?>\" method=\"get\"><input name=\"q\" alt=\"Search box\" type=\"text\" size=\"10\" style=\"width: 70%\" /> <input type=\"submit\" value=\"Go\" style=\"width: 20%\" /></form></div>',block_type=3 WHERE block_name='Search';
---END 1.0RC3---
---BEGIN 1.0RC2---
-- Add the "Moderators" group
UPDATE {{TABLE_PREFIX}}groups SET group_id=9999 WHERE group_id=3;
UPDATE {{TABLE_PREFIX}}group_members SET group_id=9999 WHERE group_id=3;
ALTER TABLE {{TABLE_PREFIX}}groups ADD COLUMN system_group tinyint(1) NOT NULL DEFAULT 0;
UPDATE {{TABLE_PREFIX}}groups SET system_group=1 WHERE group_id=1 OR group_id=2;
INSERT INTO {{TABLE_PREFIX}}groups(group_id,group_name,group_type,system_group) VALUES(3, 'Moderators', 3, 1);
-- ...and add the associated ACL rule
INSERT INTO {{TABLE_PREFIX}}acl(target_type,target_id,page_id,namespace,rules) VALUES(1,3,NULL,NULL,'read=4;post_comments=4;edit_comments=4;edit_page=4;view_source=4;mod_comments=4;history_view=4;history_rollback=4;history_rollback_extra=4;protect=4;rename=3;clear_logs=2;vote_delete=4;vote_reset=4;delete_page=4;set_wiki_mode=2;password_set=2;password_reset=2;mod_misc=2;edit_cat=4;even_when_protected=4;upload_files=2;upload_new_version=3;create_page=3;php_in_pages=2;edit_acl=2;');
-- Reset default user's theme to Oxygen, to emphasize stable release
UPDATE {{TABLE_PREFIX}}users SET theme='oxygen',style='bleu' WHERE user_id=1 OR user_id=2;
-- Create table with extra user information
CREATE TABLE {{TABLE_PREFIX}}users_extra( user_id mediumint(8) NOT NULL, user_aim varchar(63) default NULL, user_yahoo varchar(63) default NULL, user_msn varchar(255) default NULL, user_xmpp varchar(255) default NULL, user_homepage text, user_location text, user_job text, user_hobbies text, email_public tinyint(1) NOT NULL default '0', userpage_comments smallint(5) NOT NULL default '0', PRIMARY KEY ( user_id ) );
-- Turn on the Enano button on the sidebar
INSERT INTO {{TABLE_PREFIX}}config(config_name,config_value) VALUES('powered_btn', '1');
---END 1.0RC2---
---BEGIN 1.0RC1---
-- Not too many DB changes in this release - that's a good sign ;-)
ALTER TABLE {{TABLE_PREFIX}}search_index MODIFY COLUMN word varbinary(64) NOT NULL;
CREATE FULLTEXT INDEX {{TABLE_PREFIX}}page_search_idx ON {{TABLE_PREFIX}}page_text(page_id,namespace,page_text);
UPDATE {{TABLE_PREFIX}}users SET user_level=3 WHERE user_level=2;
UPDATE {{TABLE_PREFIX}}sidebar SET block_content='[[$NS_USER$$USERNAME$|User page]]\n[[$NS_SPECIAL$Contributions/$USERNAME$|My Contributions]]\n{if user_logged_in}\n[[$NS_SPECIAL$Preferences|Preferences]]\n[[$NS_SPECIAL$PrivateMessages|Private messages]]\n[[$NS_SPECIAL$Usergroups|Group control panel]]\n$THEME_LINK$\n{/if}\n{if user_logged_in}\n$LOGOUT_LINK$\n{else}\n[[$NS_SPECIAL$Register|Create an account]]\n$LOGIN_LINK$\n[[$NS_SPECIAL$Login/$NS_SPECIAL$PrivateMessages|Private messages]]\n{/if}',block_name='$USERNAME$' WHERE ( block_name='$USERNAME' OR block_name='$USERNAME$' ) AND item_id=3;
---END 1.0RC1---
---BEGIN 1.0b4---
CREATE TABLE {{TABLE_PREFIX}}hits( hit_id bigint(20) NOT NULL auto_increment, username varchar(63) NOT NULL, time int(12) NOT NULL DEFAULT 0, page_id varchar(63), namespace varchar(63), PRIMARY KEY ( hit_id ) );
CREATE TABLE {{TABLE_PREFIX}}search_index( word binary(32) NOT NULL, page_names text, PRIMARY KEY ( word ) );
CREATE TABLE {{TABLE_PREFIX}}search_cache( search_id int(15) NOT NULL auto_increment, search_time int(11) NOT NULL, query text, results longblob, PRIMARY KEY ( search_id ));
CREATE TABLE {{TABLE_PREFIX}}acl( rule_id int(12) UNSIGNED NOT NULL auto_increment, target_type tinyint(1) UNSIGNED NOT NULL, target_id int(12) UNSIGNED NOT NULL, page_id varchar(255), namespace varchar(24), rules text, PRIMARY KEY ( rule_id ) );
ALTER TABLE  {{TABLE_PREFIX}}users ADD COLUMN old_encryption tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE  {{TABLE_PREFIX}}users MODIFY COLUMN password text;
ALTER TABLE  {{TABLE_PREFIX}}users ADD COLUMN temp_password text, ADD COLUMN temp_password_time int(12) NOT NULL DEFAULT 0;
UPDATE {{TABLE_PREFIX}}users SET old_encryption=1;
UPDATE {{TABLE_PREFIX}}users SET user_level=9 WHERE user_level=2;
UPDATE {{TABLE_PREFIX}}users SET user_level=5 WHERE user_level=1;
UPDATE {{TABLE_PREFIX}}users SET user_level=2 WHERE user_level=0;
UPDATE {{TABLE_PREFIX}}users SET user_level=1 WHERE user_level=-1;
INSERT INTO {{TABLE_PREFIX}}acl(target_type,target_id,page_id,namespace,rules) VALUES(1,2,NULL,NULL,'read=4;post_comments=4;edit_comments=4;edit_page=4;view_source=4;mod_comments=4;history_view=4;history_rollback=4;history_rollback_extra=4;protect=4;rename=4;clear_logs=4;vote_delete=4;vote_reset=4;delete_page=4;tag_create=4;tag_delete_own=4;tag_delete_other=4;set_wiki_mode=4;password_set=4;password_reset=4;mod_misc=4;edit_cat=4;even_when_protected=4;upload_files=4;upload_new_version=4;create_page=4;php_in_pages={{ADMIN_EMBED_PHP}};edit_acl=4;');
-- Group system
CREATE TABLE {{TABLE_PREFIX}}groups( group_id mediumint(5) UNSIGNED NOT NULL auto_increment, group_name varchar(64), group_type tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY ( group_id ) );
CREATE TABLE {{TABLE_PREFIX}}group_members( member_id int(12) UNSIGNED NOT NULL auto_increment, group_id mediumint(5) UNSIGNED NOT NULL, user_id int(12) NOT NULL, is_mod tinyint(1) NOT NULL DEFAULT 0, pending tinyint(1) NOT NULL DEFAULT 0, PRIMARY KEY ( member_id ) );
INSERT INTO {{TABLE_PREFIX}}groups(group_id,group_name,group_type) VALUES(1, 'Everyone', 3),(2,'Administrators',3);
-- Sidebar updates
DELETE FROM {{TABLE_PREFIX}}sidebar WHERE item_id=5 AND block_name='Links';
INSERT INTO {{TABLE_PREFIX}}sidebar(item_order, sidebar_id, block_name, block_type, block_content) VALUES(2, 2, 'Links', 4, 'Links');
UPDATE {{TABLE_PREFIX}}sidebar SET block_content='[[$NS_USER$$USERNAME$|User page]]\n[[$NS_SPECIAL$Contributions/$USERNAME$|My Contributions]]\n{if user_logged_in}\n[[$NS_SPECIAL$Preferences|Preferences]]\n[[$NS_SPECIAL$PrivateMessages|Private messages]]\n[[$NS_SPECIAL$Usergroups|Group control panel]]\n$THEME_LINK$\n{/if}\n{if user_logged_in}\n$LOGOUT_LINK$\n{else}\n[[$NS_SPECIAL$Register|Create an account]]\n[[$NS_SPECIAL$Login/$PAGE_URLNAME$|Log in]]\n[[$NS_SPECIAL$Login/$NS_SPECIAL$PrivateMessages|Private messages]]\n{/if}' WHERE block_name='$USERNAME$' AND item_id=3;
UPDATE {{TABLE_PREFIX}}sidebar SET block_name='$USERNAME$' WHERE block_name='$USERNAME';
-- Set the default theme
INSERT INTO {{TABLE_PREFIX}}themes(theme_id,theme_name,theme_order,default_style,enabled) VALUES('stpatty', 'St. Patty', 1, 'shamrock.css', 1);
UPDATE {{TABLE_PREFIX}}themes SET theme_order=2 WHERE theme_id='oxygen';
UPDATE {{TABLE_PREFIX}}users SET theme='stpatty',style='shamrock';
---END 1.0b4---
---BEGIN 1.0b3---
INSERT INTO {{TABLE_PREFIX}}config(config_name, config_value) VALUES( 'allowed_mime_types', 'cbf:len=168;crc=c3dcad3f;data=0[1],1[4],0[3],1[1],0[2],1[1],0[11],1[1],0[7],1[1],0[9],1[1],0[6],1[3],0[10],1[1],0[2],1[2],0[1],1[1],0[1],1[2],0[6],1[3],0[1],1[1],0[2],1[4],0[1],1[2],0[3],1[1],0[4],1[2],0[26],1[5],0[6],1[2],0[2],1[1],0[4],1[1],0[10],1[2],0[1],1[1],0[6]|end' );
---END 1.0b3---
---BEGIN 1.0b2---
-- 10/1: Removed alterations to users table, moved to upgrade.php, to allow the session manager to work
CREATE TABLE {{TABLE_PREFIX}}privmsgs( message_id int(15) NOT NULL auto_increment, message_from varchar(63), message_to varchar(255), date int(12), subject varchar(63), message_text text, folder_name varchar(63), PRIMARY KEY (message_id) );
CREATE TABLE {{TABLE_PREFIX}}buddies( buddy_id int(15) NOT NULL auto_increment, user_id mediumint(8), buddy_user_id mediumint(8), is_friend tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (buddy_id) );
-- Fill 'em up with a basic sidebar - sometime there will be a migration script that will convert the old sidebar format to the new
CREATE TABLE {{TABLE_PREFIX}}sidebar( item_id smallint(3) NOT NULL auto_increment, item_order smallint(3) NOT NULL DEFAULT 0, sidebar_id smallint(3) NOT NULL DEFAULT 1, block_name varchar(63) NOT NULL, block_type tinyint(1) NOT NULL DEFAULT 0, item_enabled tinyint(1) NOT NULL DEFAULT 1, block_content text, PRIMARY KEY ( item_id ));
INSERT INTO {{TABLE_PREFIX}}sidebar(item_id, item_order, sidebar_id, block_name, block_type, block_content) VALUES (1, 1, 1, 'Navigation', 1, '[[Main Page|Home]]'),(2, 2, 1, 'Tools', 1, '[[Special:CreatePage|Create a page]]\n[[Special:UploadFile|Upload file]]\n[[Special:SpecialPages|Special pages]]\n{if auth_admin}\n[[Special:EditSidebar|Edit the sidebar]]\n[[Special:Administration|Administration]]\n{/if}'),(3, 3, 1, '$USERNAME$', 1, '[[User:$USERNAME$|User page]]\n[[Special:Contributions/$USERNAME$|My Contributions]]\n{if user_logged_in}\n[[Special:Preferences|Preferences]]\n[[Special:PrivateMessages|Private messages]]\n$THEME_LINK$\n{/if}\n{if user_logged_in}\n$LOGOUT_LINK$\n{else}\n[[Special:Register|Create an account]]\n[[Special:Login/$PAGE_URLNAME$|Log in]]\n[[Special:Login/Special:PrivateMessages|Private messages]]\n{/if}'),(4, 4, 1, 'Search', 1, '<div class="slideblock2" style="padding: 3px;"><form action="$SCRIPTPATH$/Special:Search" method="get" style="padding: 0; margin: 0;"><p><input name="q" alt="Search box" type="text" size="10" style="width: 70%" /> <input type="submit" value="Go" style="width: 20%" /></p></form></div>'),(5, 2, 2, 'Links', 3, '$ob = Array();\nif(getConfig(''sflogo_enabled'')==''1'')\n{\n  $ob[] = ''<a style="text-align: center;" href="http://sourceforge.net/" onclick="window.open(this.href);return false;"><img border="0" alt="SourceForge.net Logo" src="http://sflogo.sourceforge.net/sflogo.php?group_id=''.getConfig(''sflogo_groupid'').''&type=''.getConfig(''sflogo_type'').''" /></a>'';\n}\nif(getConfig(''w3c_v32'')     ==''1'') $ob[] = ''<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 3.2" src="http://validator.w3.org/images/v32" /></a>'';\nif(getConfig(''w3c_v40'')     ==''1'') $ob[] = ''<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 4.0" src="http://validator.w3.org/images/v40" /></a>'';\nif(getConfig(''w3c_v401'')    ==''1'') $ob[] = ''<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 4.01" src="http://validator.w3.org/images/v401" /></a>'';\nif(getConfig(''w3c_vxhtml10'')==''1'') $ob[] = ''<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid XHTML 1.0" src="http://validator.w3.org/images/vxhtml10" /></a>'';\nif(getConfig(''w3c_vxhtml11'')==''1'') $ob[] = ''<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid XHTML 1.1" src="http://validator.w3.org/images/vxhtml11" /></a>'';\nif(getConfig(''w3c_vcss'')    ==''1'') $ob[] = ''<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid CSS" src="http://validator.w3.org/images/vcss" /></a>'';\nif(getConfig(''dbd_button'')  ==''1'') $ob[] = ''<a style="text-align: center;" href="http://www.defectivebydesign.org/join/button" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="DRM technology restricts what you can do with your computer" src="http://defectivebydesign.org/sites/nodrm.civicactions.net/files/images/dbd_sm_btn.gif" /><br /><small>Protect your freedom &gt;&gt;</small></a>'';\nif(count($ob) > 0) echo ''<div style="text-align: center; padding: 5px;">''.implode(''<br />'', $ob).''</div>'';');
ALTER TABLE {{TABLE_PREFIX}}banlist ADD COLUMN reason text;
-- Here's a tricky one for ya :-/ what we're trying to do is add an auto-increment primary key to a table, this was a first for me but it seemed to work, tested on MySQL 4.1.20
ALTER TABLE {{TABLE_PREFIX}}comments ADD COLUMN comment_id int(12) NOT NULL auto_increment FIRST, ADD PRIMARY KEY ( comment_id );
-- Session manager stuff
ALTER TABLE {{TABLE_PREFIX}}themes ADD COLUMN default_style varchar(63) NOT NULL DEFAULT '';
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN signature text;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN reg_time int(11) NOT NULL DEFAULT 0;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN account_active tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN activation_key varchar(40) NOT NULL DEFAULT 0;
UPDATE {{TABLE_PREFIX}}users SET account_active=1;
UPDATE {{TABLE_PREFIX}}themes SET default_style='bleu.css' WHERE theme_id='oxygen';
---END 1.0b2---
---BEGIN 1.0b1---
CREATE TABLE {{TABLE_PREFIX}}files( time_id int(12) NOT NULL, page_id varchar(63) NOT NULL, filename varchar(127), size bigint(15) NOT NULL, mimetype varchar(63), file_extension varchar(8), data longblob, PRIMARY KEY (time_id) );
ALTER TABLE {{TABLE_PREFIX}}pages MODIFY COLUMN protected tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE {{TABLE_PREFIX}}pages ADD COLUMN wiki_mode tinyint(1) NOT NULL DEFAULT 2 AFTER protected;
ALTER TABLE {{TABLE_PREFIX}}pages ADD COLUMN password varchar(40) NOT NULL DEFAULT '' AFTER wiki_mode;
ALTER TABLE {{TABLE_PREFIX}}comments ADD COLUMN user_id mediumint(8) NOT NULL DEFAULT -1;
ALTER TABLE {{TABLE_PREFIX}}comments ADD COLUMN time int(12) NOT NULL default 0;
UPDATE {{TABLE_PREFIX}}pages SET wiki_mode=2;
UPDATE {{TABLE_PREFIX}}comments SET user_id=-1;
---END 1.0b1---
