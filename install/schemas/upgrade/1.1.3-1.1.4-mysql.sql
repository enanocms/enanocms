-- This is really honestly a better way to handle plugins.

CREATE TABLE {{TABLE_PREFIX}}plugins (
  plugin_id int(12) NOT NULL auto_increment,
  plugin_filename varchar(63),
  plugin_flags int(12),
  plugin_version varchar(16),
  PRIMARY KEY ( plugin_id )
) ENGINE `MyISAM` CHARACTER SET `utf8` COLLATE `utf8_bin`;

