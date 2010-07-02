-- Enano CMS
-- Upgrade schema - Enano 1.0.2 beta 1 - 1.0.2 release

-- This is really optional, but could reduce confusion if regex page groups get truncated for no apparent reason.
ALTER TABLE {{TABLE_PREFIX}}page_groups MODIFY COLUMN pg_target text DEFAULT NULL;

-- I have no idea how or why, but the f'ing index didn't get created for who-knows-how-many releases.
-- We'll attempt to create it here, but don't die if it fails
@ALTER TABLE {{TABLE_PREFIX}}page_text ENGINE = MYISAM, COLLATE = utf8_bin, CHARSET = utf8;
ALTER TABLE {{TABLE_PREFIX}}search_index CHARSET = utf8, COLLATE = utf8_bin, MODIFY COLUMN word varchar(64) NOT NULL;

-- The search cache is no longer needed because of the new unified search engine (it's too f'ing fast to need a cache :-D)
@DROP TABLE {{TABLE_PREFIX}}search_cache;

-- Yes, it appears we need pages with names this long after all
ALTER TABLE {{TABLE_PREFIX}}pages MODIFY COLUMN urlname varchar(255), MODIFY COLUMN name varchar(255);

-- Make page_text a little more efficient to deal with
ALTER TABLE {{TABLE_PREFIX}}page_text MODIFY COLUMN page_id varchar(255), MODIFY COLUMN namespace varchar(63), MODIFY COLUMN page_text longtext;

-- Now recreate the fulltext index
@CREATE FULLTEXT INDEX {{TABLE_PREFIX}}page_search_idx ON {{TABLE_PREFIX}}page_text(page_id, namespace, page_text);

-- Addition of new file types
UPDATE {{TABLE_PREFIX}}config SET config_value='cbf:len=185;crc=55fb6f14;data=0[1],1[4],0[3],1[1],0[22],1[1],0[16],1[3],0[16],1[1],0[1],1[2],0[6],1[1],0[1],1[1],0[4],1[2],0[3],1[1],0[48],1[2],0[2],1[1],0[4],1[1],0[37]|end' WHERE config_name = 'allowed_mime_types' AND config_value='cbf:len=168;crc=c3dcad3f;data=0[1],1[4],0[3],1[1],0[2],1[1],0[11],1[1],0[7],1[1],0[9],1[1],0[6],1[3],0[10],1[1],0[2],1[2],0[1],1[1],0[1],1[2],0[6],1[3],0[1],1[1],0[2],1[4],0[1],1[2],0[3],1[1],0[4],1[2],0[26],1[5],0[6],1[2],0[2],1[1],0[4],1[1],0[10],1[2],0[1],1[1],0[6]|end';

-- Reinforcement of "stable release" mentality
@UPDATE {{TABLE_PREFIX}}users SET theme='oxygen', style='bleu' WHERE user_id = 2;

