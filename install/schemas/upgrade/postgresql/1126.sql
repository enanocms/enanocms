-- Postgres never did have a constraint on this column.
UPDATE {{TABLE_PREFIX}}pages SET page_format = 'tinymce' WHERE page_format = 'xhtml';
