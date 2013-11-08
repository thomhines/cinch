<?php

/*----------------------------------------------------------------------*

	Cinch
	https://github.com/thomhines/cinch
	
	A simple, streamlined plugin to minimize and cache JS/CSS files

	Copyright 2013, Thom Hines
	MIT License
	
*----------------------------------------------------------------------*/


include('minify.php');

// GZIP CONTENT WHEN AVAILABLE
if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); 
else ob_start(); 


// DELETE CACHE FILES IF CLEARCACHE IS TRUE
if(isset($_GET['clearcache']) && $_GET['clearcache'] === true) {
	$files = glob('cache/*.*s'); // select all .js and .css files
	foreach($files as $file){
	  if(is_file($file)) unlink($file);
	}
}
if(!isset($_GET['files'])) exit(); // if no files have been selected, stop here


// PREPARE VARIABLES
$file_array = explode(',', $_GET['files']); 
$cachefile = 'cache/'.md5(implode(",", $_GET)); // build cache filename based on current parameters
$path = $_SERVER['SCRIPT_FILENAME']; // set path to site root folder
$path = str_replace('cinch/index.php', '', $path); // remove reference to cinch folder


// USE CORRECT CONTENT TYPE 
if($_GET['t']=='auto' || !isset($_GET['t'])) { // auto detect content type
	if(substr($file_array[0], -3) == '.js') $_GET['t'] = 'js';
	else $_GET['t'] = 'css';
} 
if($_GET['t']=='js') { // JS
	header("Content-type: application/x-javascript; charset: UTF-8"); 
	$cachefile .= '.js';
}
else { // otherwise, assume CSS
	header("Content-type: text/css; charset: UTF-8");
	$cachefile .= '.css';
}


// CHECK TO SEE IF CACHE IS OLDER THAN ALL OF THE FILES
if(file_exists($cachefile)) $timestamp = filemtime($cachefile);
else $timestamp = 0;
$new_changes = false;
foreach($file_array as $file) {
	if(substr($file,0,1) == "!") $file = substr($file,1); ; // remove '!'
	if(is_file($path.$file)) if(filemtime($path.$file) > $timestamp) $new_changes = true;
}


// IF NEW CHANGES HAVE BEEN DETECTED, REBUILD CACHE FILE
if($new_changes || $_GET['force']) {

	$content = '';
	foreach($file_array as $file) { // combine files
		
		if($_GET['min'] === true || !isset($_GET['min'])) $compress_file = true;
		else $compress_file = false;
		if(substr($file,0,1) == "!") {
			$compress_file = false;
			if(substr($file,0,1) == "!") $file = substr($file,1); // remove '!' from the front of filename
		}
		if(!is_file($path.$file)) $error .= "'".$file."' is not a valid file.\n";
		else {
			if(!$handle = fopen($path.$file, "r")) $error .= "There was an error trying to open '".$file."'.\n";
			$temp_content = "";
			if(!$temp_content = fread($handle, filesize($path.$file))) $error .= "There was an error trying to open '".$file."'.\n";
			
			else {
			
				// IF .SCSS, PROCESS AND CONVERT TO CSS
				if(substr($file, -5) == '.scss') {
					require_once('scss.inc.php');
					$scss = new scssc();
					$temp_content = $scss->compile($temp_content);
				}
				
				// IF .LESS, PROCESS AND CONVERT TO CSS
				if(substr($file, -5) == '.less') {
					require_once('lessc.inc.php');
					$less = new lessc();
					$temp_content = $less->compile($temp_content);
				}

				if($compress_file && $_GET['t']=='js') $temp_content = minifyJS($temp_content);
				elseif($compress_file) $temp_content = minifyCSS($temp_content, $path);
				
				if($_GET['debug']) $temp_content = "/* $file */\n".$temp_content;
				$content .= $temp_content."\n\n";
			}			
			fclose($handle);
		}
	}
	

	
	
	// OUTPUT FILE
	if(!$handle = fopen($cachefile, 'w')) {
		 echo "ERROR: Cannot open cache file.\n";
		 exit;
	}
	if(fwrite($handle, $content) === FALSE) {
		echo "ERROR: Cannot write to cache file.\n";
		exit;
	}
	fclose($handle);
	
	$timestamp = gmmktime();
} 



// SET HEADERS TO CACHE FILES PROPERLY IN BROWSER
$gmt_mtime = gmdate('r', $timestamp);
header('ETag: "'.md5($timestamp.$cachefile).'"');
header('Last-Modified: '.$gmt_mtime);
header('Cache-Control: public');



// IF THERE IS A NEW CACHE FILE, SEND NEW CONTENT
if($new_changes || $_GET['force']) {
	if($_GET['debug'] && isset($error)) echo "/*\n\nERROR:\n$error \n*/\n\n";
	echo $content;
}


// IF FILE IS ALREADY IN USER'S CACHE, SEND 304 NOT MODIFIED HEADER
elseif(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
	if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$cachefile)) {
		header('HTTP/1.1 304 Not Modified');
		exit();
	}
}

// OTHERWISE, LOAD CACHED FILE AND SEND TO USER
else {
	if(!$handle = fopen($cachefile, "r")) {
		echo "ERROR: There was an error trying to open '".$cachefile."'.\n";
		exit;
	}
	$content = fread($handle, filesize($cachefile));
	fclose($handle);
	
	if($_GET['debug'] && isset($error)) echo "/*\n\nERROR:\n$error \n*/\n\n";
	echo $content;
}



?>