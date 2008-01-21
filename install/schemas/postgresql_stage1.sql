-- Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
-- Version 1.1.1
-- Copyright (C) 2006-2007 Dan Fuhry

-- This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
-- as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

-- This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
-- warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.

-- mysql_stage1.sql - MySQL installation schema, early stage

CREATE TABLE {{TABLE_PREFIX}}config(
  config_name varchar(63),
  config_value text
);

