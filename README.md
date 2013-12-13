Cinch 0.5
=========

A simple, streamlined plugin to minimize and cache JS/CSS files.



Description
-----------

Cinch allows developers to automatically handle JS/CSS compression and concatenization (combining multiple files into one), reducing file sizes and page load times. There's virtually no installation process; simply change your JS/CSS links to point to Cinch with a list of the files you want to load and it will do the rest.

Furthermore, it's perfect for both development and production environments. Cinch will look for new changes to your JS/CSS files, and if it finds any it will quickly build a static cache file to send to your users.



#### Features:

- Automatic minification of JS/CSS, which removes unnecessary spaces and comments
- Converts common pre-processor formats (LESS, SCSS, SASS, and CoffeeScript) into standard CSS/JS automatically
- Built-in access to tons of common libraries, such as jQuery, Prototype, and more in [Google Hosted Libraries](https://developers.google.com/speed/libraries/), CSS frameworks such as [Foundation](http://foundation.zurb.com/) and [960.gs](http://960.gs/), and a variety of javascript plugins. See the entire list below.
- Combines multiple files into one file to reduce HTTP connections between the server and your users
- Caches files on server if no new changes have been detected to the source files
- Serves '304 Not Mofidified' headers to users if the user already has the latest code in the browser's cache
- Uses gzip to further compress output files when available



Basic usage
-----------

Just upload the 'cinch' folder to **the root folder of your site**, and replace all of your `<script>` or `<link>` tags in your HTML with just one tag that links to all of your JS/CSS files. 

### Example 

	<script src="/js/jquery.min.js" type="text/javascript"></script>
	<script src="/js/functions.js" type="text/javascript"></script>
	<script src="/js/scripts.js" type="text/javascript"></script>
	
turns into:

	<script src="/cinch/?files=/js/jquery.min.js,/js/functions.js,/js/scripts.js" type="text/javascript"></script>

#### More Examples


The following example will load up three javascript files (jQuery from Google Hosted Libraries, /js/functions.js, /js/ajax.js) and disable minification.

	<script type="text/javascript" src="/cinch/?files=[jquery/1.10.2],/js/functions.js,/js/ajax.js&min=false"></script>
	
The next example will load up three CSS files (css/reset.css, css/layout.css, css/text.css), disable minification for reset.css (by adding the '!' to the file path for that file), and will force Cinch to create a new cache file on the server every time the page is reloaded.
	
	<link type="text/css" media="all" href="/cinch/?files=!/css/reset.css,/css/layout.css,/css/text.css&force=true">



### Settings

In order to use any of the setting below, just add them to the query string in the `<script>` or `<link>` tag, separated by the '&' character. All settings work for both JS and CSS type files. 


#### REQUIRED

- **files=(comma separated list of files)** - List of JS or CSS files to include

*NOTE*: Files should contain relative path from **site root** to the files being listed (eg. `/js/scripts.js`) .	

##### OPTIONS
- **!(/path/to/filename)** - To disable minification on individual files, simply add '!' to the beginning of that file's path in the comma separated list. 

	Example: `?files=!/js/plugin.min.js,!/js/scripts.js`

- **[library-name/version]** - To include a library from [Google Hosted Libraries](https://developers.google.com/speed/libraries/) selection, enclose the name of the library and the version number in a pair of square brackets, separated by a forward slash (/). If no version is given, the latest version of the libary will be used (as of this writing).

	Example: `?files=[jquery]` or `?files=[jquery/1.10.2]`

	Available libraries are (default version is in paratheses):
	
	**[960gs](https://raw.github.com/nathansmith/960-Grid-System/master/code/css/960.css)**, 
	**[angularjs](https://ajax.googleapis.com/ajax/libs/angularjs/1.2.4/angular.min.js)** (1.2.4), 
	**[chrome-frame](https://ajax.googleapis.com/ajax/libs/chrome-frame/1.0.3/CFInstall.min.js)** (1.0.3), 
	**[dojo](https://ajax.googleapis.com/ajax/libs/dojo/1.9.1/dojo/dojo.js)** (1.9.1), 
	**[ext-core](https://ajax.googleapis.com/ajax/libs/ext-core/3.1.0/ext-core.js)** (3.1.0), 
	**[fittext](https://raw.github.com/davatron5000/FitText.js/master/jquery.fittext.js)**, 
	**[foldy960](https://raw.github.com/davatron5000/Foldy960/master/style.css)**, 
	**[foundation-css](http://foundation.zurb.com/)** (5.0.2), 
	**[foundation-js](http://foundation.zurb.com/)** (5.0.2), 
	**[html5shiv](http://html5shiv.googlecode.com/svn/trunk/html5.js)**, 
	**[html5shim](http://html5shiv.googlecode.com/svn/trunk/html5.js)**, 
	**[isotope-css](https://raw.github.com/desandro/isotope/master/css/style.css)**, 
	**[isotope-js](https://raw.github.com/desandro/isotope/master/jquery.isotope.min.js)**, 
	**[jquery](https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js)** (1.10.2), 
	**[jqueryui](https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js)** (1.10.3), 
	**[kube](http://imperavi.com/css/kube.css)**, 
	**[lettering](https://raw.github.com/davatron5000/Lettering.js/master/jquery.lettering.js)**, 
	**[masonry](http://masonry.desandro.com/masonry.pkgd.min.js)**, 
	**[mixitup](https://raw.github.com/barrel/mixitup/master/jquery.mixitup.min.js)**, 
	**[modernizr](http://modernizr.com/downloads/modernizr-latest.js)**, 
	**[mootools](https://ajax.googleapis.com/ajax/libs/mootools/1.4.5/mootools-yui-compressed.js)** (1.4.5), 
	**[normalize](http://necolas.github.io/normalize.css/2.1.3/normalize.css)** (2.1.3), 
	**[prototype](https://ajax.googleapis.com/ajax/libs/prototype/1.7.1.0/prototype.js)** (1.7.1.0), 
	**[pure](http://yui.yahooapis.com/pure/0.3.0/pure-min.css)** (0.3.0), 
	**[reset5](http://reset5.googlecode.com/hg/reset.min.css)**, 
	**[responsiveslides-css](https://raw.github.com/viljamis/ResponsiveSlides.js/master/responsiveslides.css)**, 
	**[responsiveslides-js](https://raw.github.com/viljamis/ResponsiveSlides.js/master/responsiveslides.min.js)**, 
	**[scriptaculous](https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/scriptaculous.js)** (1.9.0), 
	**[skeleton](http://www.getskeleton.com/)** (1.2), 
	**[skeleton-grid](http://www.getskeleton.com/)** (1.2), 
	**[stellar](https://raw.github.com/markdalgleish/stellar.js/master/jquery.stellar.min.js)**, 
	**[swfobject](https://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js)** (2.2), 
	**[waypoints](https://raw.github.com/imakewebthings/jquery-waypoints/master/waypoints.min.js)**, 
	**[webfont](https://ajax.googleapis.com/ajax/libs/webfont/1.5.0/webfont.js)** (1.5.0), 
	**[yui-reset](http://yui.yahooapis.com/3.14.0/build/cssreset/cssreset-min.css)** (3.14.0)
	


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
	
- **debug=(true|false*)** - When enabled, output files display errors. Otherwise, errors are ignored.


### Requirements

- **PHP 5+** - Core functionality (minification and concatenization)  
- **PHP 5.1?** - Sass/SCSS Compiler (Just a guess as to which version is necessary)
- **PHP 5.1+** - LESS Compiler
- **PHP 5.3+** - CoffeeScript Compiler


### Special Thanks

Cinch is made with the help of:

- [JS minification](http://razorsharpcode.blogspot.com/2010/02/lightweight-javascript-and-css.html) at [Razor-Sharp Code](http://razorsharpcode.blogspot.com/)

- Nicolas Martin's [PHP port](http://joliclic.free.fr/php/javascript-packer/en/) of Dean Edward's [Packer](http://dean.edwards.name/packer/)

- [LESS/SCSS Processing](http://leafo.net/lessphp/)/[scssphp](http://leafo.net/scssphp/) by [leafo](http://leafo.net/)

- [CoffeeScript Processing](https://github.com/alxlit/coffeescript-php) by alxlit