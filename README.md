Cinch
=====

A simple, streamlined plugin to minimize and cache JS/CSS files.



Description
-----------

Cinch allows developers to automatically handle JS/CSS compression and concatenization (combining multiple files into one), reducing file sizes and page load times. There's virtually no installation process; simply change your JS/CSS links to point to Cinch with a list of the files you want to load and it will do the rest.

Furthermore, it's perfect for both development and production environments. Cinch will look for new changes to your JS/CSS files and if it finds any, it will quickly build a static cache file and to send to your users.



#### Features:

- Automatic minification of JS/CSS, which removes unnecessary spaces and comments
- Converts common pre-processor formats (LESS, SCSS, SASS, and CoffeeScript) into standard CSS/JS automatically
- Built-in access to all libraries in the [Google Hosted Libraries](https://developers.google.com/speed/libraries/)
- Combines multiple files into one file to reduce HTTP connections between the server and your users
- Caches files on server if no new changes have been detected to the source files
- Serves '304 Not Mofidified' headers to users if the user already has the latest code in the browser's cache
- Uses gzip to further compress output files when available



Basic usage
-----------

Just upload the 'cinch' folder to **the root folder of your site**, and replace all of your `<script>` or `<link>` tags in your HTML with just one tag that links to all of your JS/CSS files. 

Javascript:

	<script type="text/javascript" src="/cinch/?[**OPTIONS**]"></script>


CSS: 

	<link type="text/css" href="/cinch/?[**OPTIONS**]">



### Examples

The following example will load up three javascript files (jQuery from Google Hosted Libraries, /js/functions.js, /js/ajax.js) and disable minification.

	<script type="text/javascript" src="/cinch/?files=[jquery/1.10.2],/js/functions.js,/js/ajax.js&min=false"></script>
	
The next example will load up three CSS files (css/reset.css, css/layout.css, css/text.css), disable minification for reset.css (by adding the '!' to the file path for that file), and will force Cinch to create a new cache file on the server every time the page is reloaded.
	
	<link type="text/css" media="all" href="/cinch/?files=!/css/reset.css,/css/layout.css,/css/text.css&force=true">



### Settings

In order to use any of the setting below, just add them to the query string in the `<script>` or `<link>` tag, separated by the '&' character.


#### REQUIRED

- **files=(comma separated list of files)** - List of JS or CSS files to include

*NOTE*: Files should contain relative path from **site root** to the files being listed (eg. `/js/scripts.js`) .	

##### OPTIONS
- **'!'** - To disable minification on individual files, simply add '!' to the beginning of that file's path in the comma separated list. 

	Example: `?files=!/js/plugin.min.js,!/js/scripts.js`

- **[library-name/version]** - To include a library from Google's Hosted Library selection, enclose the name of the library and the version number in a pair of square brackets, separated by a forward slash (/). 

	Example: `?files=[jquery/1.10.2]`

	Available libraries are: 'angularjs', 'chrome-frame', 'dojo', 'ext-core', 'jquery', 'jqueryui', 'mootools', 'prototype', 'scriptaculous', 'swfobject', and 'webfont'. Check [Google's Developer Guide](https://developers.google.com/speed/libraries/devguide) for more information on what versions are available.
	


#### OPTIONAL SETTINGS
*Values marked with a star are the default and will be used if no value is given.*
		
- **t=(js|css|auto*)** - Indicate which type of files are being sent to Cinch
	- **js**: Process files as javascript
	- **css**: Process files as CSS
	- **auto***: Cinch will do it's best to automatically detect which type of files are being used. This is based on the extension of the first file in the list.
	
- **force=(true|false*)** - Force Cinch to rebuild the cache and update the user's browser with the newest code on every page load, even if no changes have been detected.

- **min=(true*|false)** - Enable/disable minification on files. 
	- NOTE: Files will still be concatenated and cached.
	- NOTE: Files marked with a '!' in order to avoid minification will no be minified regardless of this setting's value.

- **clearcache=(true|false*)** - Clears all cache files on the server. This option can be run independently without a files list.
	
- **debug=(true|false*)** - When enabled, output files display errors. Otherwise, errors are ignored.


### Requirements

- **PHP 4.3+** - Core functionality (minification and concatenization)  
- **PHP 5.1?** - Sass/SCSS Compiler (Just a guess as to which version is necessary)
- **PHP 5.1+** - LESS Compiler
- **PHP 5.3+** - CoffeeScript Compiler


### Special Thanks

Cinch is made with the help of:

- [JS minification](http://razorsharpcode.blogspot.com/2010/02/lightweight-javascript-and-css.html) at [Razor-Sharp Code](http://razorsharpcode.blogspot.com/)

- [lessphp](http://leafo.net/lessphp/)/[scssphp](http://leafo.net/scssphp/) - LESS/SCSS Processing by [leafo](http://leafo.net/)

- [coffeescript-php](https://github.com/alxlit/coffeescript-php) - CoffeeScript Processing by alxlit