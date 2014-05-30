<?php

/*----------------------------------------------------------------------*

	Cinch
	https://github.com/thomhines/cinch
	
	A simple, streamlined plugin to minimize and cache JS/CSS files

	Copyright 2013, Thom Hines
	MIT License
	
*----------------------------------------------------------------------*/


require_once('functions.php');

$filepath = str_replace('cinch/index.php', '', $_SERVER['SCRIPT_FILENAME']); // remove reference to cinch folder

$settings = array();
$file_array = array('css' => array(), 'js' => array());
$cachefile = "";
$error = "";




if($_GET) {
	cinch(array(
		files => explode(",", $_GET['files']),
		type => $_GET['type'],
		force => $_GET['force'],
		reload => false
		)
	);
	
}

else cinch(array(
	files => array(
		'[jquery#2.1.0]',
		//'[angular]',
		//'[prefixfree]',
		'![bootstrap]',
		//'[jquery.scrollTo]',
		'../js/jquery.sticky.js',
		//'[cssreset]',
		//'/css/fontello.css',
		'../css/style.scss',
		'cinch/cache/test.css',
		'../js/scripts.js'
	),
	force => true
));