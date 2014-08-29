<?php

/*----------------------------------------------------------------------*

	cinch
	https://github.com/thomhines/cinch
	
	A simple, streamlined plugin to minimize and cache JS/CSS files

	Copyright 2013, Thom Hines
	MIT License
	
*----------------------------------------------------------------------*/


require_once('cinch.php');


if($_GET) {
	$cinch = new cinch;
	$cinch->run(array(
		files => explode(",", $_GET['files']),
		type => $_GET['type'],
		min => $_GET['min'],
		debug => $_GET['debug'],
		force => $_GET['force'],
		reload => $_GET['reload']
		)
	);
}