-- This is really honestly a better way to handle plugins.

CREATE TABLE {{TABLE_PREFIX}}plugins (
  plugin_id SERIAL,
  plugin_filename varchar(63),
  plugin_flags int,
  plugin_version varchar(16),
  PRIMARY KEY ( plugin_id )
);
