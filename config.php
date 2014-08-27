<?php 

// Set this value to true to prevent any new cache files from being created.
// This essentially locks down cinch and keeps unauthorized code from
// being generated on the server
define('PRODUCTION', false);


// Defaults for cinch settings can be set here
$defaults = array (
	'min' => true, // false/min/eval 
	'force' => false, // true/false
	'debug' => true, // true/false
	'reload' => false, // true/false
	'type' => 'auto' // auto/js/css
);


?>