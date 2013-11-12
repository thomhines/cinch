<?php

/*----------------------------------------------------------------------*

	Cinch
	https://github.com/thomhines/cinch
	
	A simple, streamlined plugin to minimize and cache JS/CSS files

	Copyright 2013, Thom Hines
	MIT License
	
*----------------------------------------------------------------------*/

// GZIP CONTENT WHEN AVAILABLE
if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); 
else ob_start(); 



// DELETE CACHE FILES IF CLEARCACHE IS TRUE
if($_GET['clearcache']) clearCache();


if(!isset($_GET['files'])) exit(); // if no files have been selected, stop here



// PREPARE VARIABLES
$file_array = explode(',', $_GET['files']); 
$cachefile = 'cache/'.md5(implode(",", $_GET)); // build cache filename based on current parameters
$path = $_SERVER['SCRIPT_FILENAME']; // set path to site root folder
$path = str_replace('cinch/index.php', '', $path); // remove reference to cinch folder



// USE CORRECT CONTENT TYPE 
if($_GET['t']=='auto' || !isset($_GET['t'])) { // auto detect content type
	if(substr($file_array[0], -3) == '.js' || substr($file_array[0], -7) == '.coffee') $_GET['t'] = 'js';
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
if($new_changes || $_GET['force'] || $_GET['clearcache']) {

	$content = '';
	foreach($file_array as $file) { // combine files
		
		if($_GET['min'] === true || !isset($_GET['min'])) {
			$compress_file = true;
			require_once('processors/minify.php');
		}
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
				if(substr($file, -5) == '.scss') $temp_content = convertSCSS($temp_content);
				
				// IF .SASS, PROCESS AND CONVERT TO CSS
				if(substr($file, -5) == '.sass') $temp_content = convertSASS($temp_content);
				
				// IF .LESS, PROCESS AND CONVERT TO CSS
				if(substr($file, -5) == '.less') $temp_content = convertLESS($temp_content);
				
				// IF CoffeeScript, PROCESS AND CONVERT TO JS
				if(substr($file, -7) == '.coffee') $temp_content = convertCoffee($temp_content);
				
				// MINIFY CONTENT
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

	$timestamp = filemtime($cachefile);
} 



// SET HEADERS TO CACHE FILES PROPERLY IN BROWSER
$gmt_mtime = gmdate('r', $timestamp);
header('ETag: "'.md5($timestamp.$cachefile).'"');
header('Last-Modified: '.$gmt_mtime);
header('Cache-Control: public');



// IF THERE IS A NEW CACHE FILE, SEND NEW CONTENT
if($content) {
	if($_GET['debug'] && isset($error)) echo "/*\n\nERROR:\n$error \n*/\n\n";
	echo $content;
}



// IF FILE IS ALREADY IN USER'S CACHE, SEND 304 NOT MODIFIED HEADER
elseif ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$cachefile)) {
	header('HTTP/1.1 304 Not Modified');		
	exit();
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





/*----------------------------------------------------------------------*

	FUNCTIONS

*----------------------------------------------------------------------*/


function clearCache() {
	$files = glob('cache/*.*s'); // select all .js and .css files
	foreach($files as $file){
		if(is_file($file)) unlink($file);
	}
}


function convertSASS($src) {
	require_once('processors/sass-scss/SassParser.php');
	$options = array(
		'cache' => FALSE,
		'syntax' => 'sass',
		'debug' => FALSE,
	);
	// Execute the compiler.
	$sass = new SassParser($options);
	return $sass->toCss($src);
}

function convertSCSS($src) {
	require_once('processors/sass-scss/SassParser.php');
	$options = array(
		'cache' => FALSE,
		'syntax' => 'scss',
		'debug' => FALSE,
	);
	// Execute the compiler.
	$scss = new SassParser($options);
	return $scss->toCss($src);
}

function convertLESS($src) {
	require_once('processors/less/lessc.inc.php');
	$less = new lessc();
	return $less->compile($src);
}

function convertCoffee($src) {	
	require_once('processors/coffeescript/Init.php');
	CoffeeScript\Init::load();
	return CoffeeScript\Compiler::compile($src);
}



?>