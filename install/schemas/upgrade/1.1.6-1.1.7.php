<?php

// Migrate usernames in the logs table

global $db, $session, $paths, $template, $plugins; // Common objects

$q = $db->sql_query('SELECT user_id, username FROM ' . table_prefix . 'users;');
if ( !$q )
	$db->_die();

$map = array();
while($row = $db->fetchrow())
{
	$map[ $row['username'] ] = $row['user_id'];
}
$db->free_result();

$q = $db->sql_query('SELECT author FROM ' . table_prefix . 'logs WHERE author_uid = 1;');
if ( !$q )
	$db->_die();

$updated = array();

while ( $row = $db->fetchrow($q) )
{
	if ( isset($map[ $row['author'] ]) && !is_valid_ip($row['author']) && !in_array($row['author'], $updated) )
	{
		$author = $db->escape($row['author']);
		$sql = "UPDATE " . table_prefix . "logs SET author_uid = {$map[ $row['author'] ]} WHERE author = '$author';";
		if ( !$db->sql_query($sql) )
			$db->_die();
		$updated[] = $row['author'];
	}
}

