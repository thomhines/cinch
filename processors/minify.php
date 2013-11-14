<?php

/* THANKS TO http://razorsharpcode.blogspot.com/2010/02/lightweight-javascript-and-css.html */
function minifyJS($_src) {
	// Buffer output
	ob_start();
	$_time=microtime(TRUE);
	$_ptr=0;
	while ($_ptr<=strlen($_src)) {
		if ($_src[$_ptr]=='/') {
			// Let's presume it's a regex pattern
			$_regex=TRUE;
			if ($_ptr>0) {
				// Backtrack and validate
				$_ofs=$_ptr;
				while ($_ofs>0) {
					$_ofs--;
					// Regex pattern should be preceded by parenthesis, colon or assignment operator
					if ($_src[$_ofs]=='(' || $_src[$_ofs]==':' || $_src[$_ofs]=='=') {
						while ($_ptr<=strlen($_src)) {
							$_str=strstr(substr($_src,$_ptr+1),'/',TRUE);
							if (!strlen($_str) && $_src[$_ptr-1]!='/' || strpos($_str,"\n")) {
								// Not a regex pattern
								$_regex=FALSE;
								break;
							}
							echo '/'.$_str;
							$_ptr+=strlen($_str)+1;
							// Continue pattern matching if / is preceded by a \
							if ($_src[$_ptr-1]!='\\' || $_src[$_ptr-2]=='\\') {
								echo '/';
								$_ptr++;
								break;
							}
						}
						break;
					}
					elseif ($_src[$_ofs]!="\t" && $_src[$_ofs]!=' ') {
						// Not a regex pattern
						$_regex=FALSE;
						break;
					}
				}
				if ($_regex && _ofs<1)
					$_regex=FALSE;
			}
			if (!$_regex || $_ptr<1) {
				if (substr($_src,$_ptr+1,2)=='*@') {
					// JS conditional block statement
					$_str=strstr(substr($_src,$_ptr+3),'@*/',TRUE);
					echo '/*@'.$_str.$_src[$_ptr].'@*/';
					$_ptr+=strlen($_str)+6;
				}
				elseif ($_src[$_ptr+1]=='*') {
					// Multiline comment
					$_str=strstr(substr($_src,$_ptr+2),'*/',TRUE);
					$_ptr+=strlen($_str)+4;
				}
				elseif ($_src[$_ptr+1]=='/') {
					// Multiline comment
					$_str=strstr(substr($_src,$_ptr+2),"\n",TRUE);
					$_ptr+=strlen($_str)+2;
				}
				else if (($_src[$_ptr] == ' ') && (($_src[$_ptr + 1] == '-') || ($_src[$_ptr + 1] == '.') || ($_src[$_ptr + 1] == '#'))) {
					// fix "Npx -Npx" cases (space followed by -)
					// fix "#boo .bla" cases (space followed by .)
					echo $_src[$_ptr];
					$_ptr++;
				}
				else {
					// Division operator
					echo $_src[$_ptr];
					$_ptr++;
				}
			}
			continue;
		}
		elseif ($_src[$_ptr]=='\'' || $_src[$_ptr]=='"') {
			$_match=$_src[$_ptr];
			// String literal
			while ($_ptr<=strlen($_src)) {
				$_str=strstr(substr($_src,$_ptr+1),$_src[$_ptr],TRUE);
				echo $_match.$_str;
				$_ptr+=strlen($_str)+1;
				if ($_src[$_ptr-1]!='\\' || $_src[$_ptr-2]=='\\') {
					echo $_match;
					$_ptr++;
					break;
				}
			}
			continue;
		}
		if ($_src[$_ptr]!="\r" && $_src[$_ptr]!="\n" && ($_src[$_ptr]!="\t" && $_src[$_ptr]!=' ' ||
				preg_match('/[\w\$]/',$_src[$_ptr-1]) && preg_match('/[\w\$]/',$_src[$_ptr+1])))
			// Ignore whitespaces
			echo str_replace("\t",' ',$_src[$_ptr]);
		$_ptr++;
	}
	$_out=ob_get_contents();
	ob_end_clean();
	return $_out;
}


function minifyCSS($_src) {
	$_out = preg_replace("/\s+/", " ", $_src); // remove excess spaces
	$_out = preg_replace("/\s?(;|{|})\s?/", "$1", $_out); // remove spaces next to CSS specific characters (ie. ;, {, })
	$_out = preg_replace("/\/\*(.|[\r\n])*?\*\//", "", $_out); // remove comments	
	$_out = trim($_out);
	
	return $_out;
}

?>