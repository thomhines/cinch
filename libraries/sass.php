<?php
/**
 * sassToScss v0.4.0
 * http://projects.dontgetthewrongidea.com/cinch/
 *
 * Converts old school, indentation-style Sass to SCSS format
 *
 * Copyright 2013, Thom Hines <thomhines@gmail.com>
 * Licensed under MIT License, see LICENSE
 */


function sassToScss($src) {
		
	$sass = $src; 
	
	// REPLACE SASS ELEMENTS WITH SCSS EQUIVALENTS
	$sass = preg_replace("/[ ]{2}/", "	", $sass); // convert spaces to tabs
	$sass = preg_replace("/([\s]):([\S]*)/i", "$1$2:", $sass); // change :property style rules to standard order
	$sass = preg_replace("/([^&]:[^\r\n]+)[\s]*(\r\n|\n|\r)/", "$1;$2", $sass); // add a ";" to all lines that are rules
	$sass = preg_replace('/@import "?([^\r\n"]*)"?/', '@import "$1";', $sass); // add a ";" to all lines that are import rules
	$sass = preg_replace("/,[\s]*[\r\n|\n|\r]/", ", ", $sass); // remove any linebreaks after a comma
	$sass = preg_replace("/=/", "@mixin ", $sass); // convert "=" to "@mixin "
	$sass = preg_replace("/\+([^\s]*)/", "@include $1;", $sass); // convert "+" to "@include "
	
	
	// ANALYZE EACH LINE TO SEE HOW INDENTATION CHANGES FROM ONE LINE TO THE NEXT
	$sass_lines = preg_split("/\r\n|\n|\r/", $sass);
	$tabs = 0;
	$prev_tabs = 0;
	
	for($x = 0; $x < count($sass_lines)+1; $x++) {
	
		// CONVERT MULTI-LINE COMMENT TO SINGLE LINE
		$tabs = strspn($sass_lines[$x], "\t");
		$indentation = $prev_tabs - $tabs;
		if(strpos($sass_lines[$x], '/*') !== false || strpos($sass_lines[$x], '//') !== false) {
			$next_tabs = strspn($sass_lines[$x+1], "\t"); // number of tabs on next line
			if($next_tabs > $tabs) {
				$sass_lines[$x+1] = $sass_lines[$x] . preg_replace("/[\s]+/", " ", $sass_lines[$x+1]);
				$sass_lines[$x] = "";
				$tabs = $prev_tabs;
			} else {
				$sass_lines[$x] .= " */";
			}
		}
		
		// CHANGE INDENTATIONS TO BRACKETS		
		if($tabs > $prev_tabs) $sass_lines[$x-1] .= " {"; // if next line has more indents than this one, add an open bracket
		
		if($tabs < $prev_tabs) { // if next line has fewer indents, add a close bracket
			for($y = 0; $y < $indentation; $y++) {
				$sass_lines[$x-1] .= "\n".str_repeat("\t", $tabs + ($indentation-$y) - 1)."}";
			}
		}
		
		
		$prev_tabs = $tabs;
	}
	
	return implode("\n", $sass_lines); // recombine file and return value
}


?>