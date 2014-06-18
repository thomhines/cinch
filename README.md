Cinch 0.8
=========

A simple, streamlined way to combine, compress, and cache web files.



Description
-----------

Cinch allows developers to automatically handle JS/CSS compression and concatenization (combining multiple files into one), reducing file sizes and page load times. There's virtually no installation process; simply change your JS/CSS links to point to Cinch with a list of the files you want to load and it will do the rest.

Furthermore, it's perfect for both development and production environments. Cinch will look for new changes to your JS/CSS files, and if it finds any it will quickly build a static cache file to send to your users.

For more up-to-date details, check out the [cinch website](http://projects.thomhines.com/cinch/).

#### Features:

- Automatic minification of JS/CSS, which removes unnecessary spaces and comments
- Converts common pre-processor formats (LESS, SCSS, SASS, and CoffeeScript) into standard CSS/JS automatically
- Built-in access to tons of common libraries, frameworks and software packages, such as jQuery, Angular, Bootstrap, and more in [Google Hosted Libraries](https://developers.google.com/speed/libraries/), CSS frameworks such as [Foundation](http://foundation.zurb.com/), [960.gs](http://960.gs/), and 
- Live Reload refreshes styles and scripts in your browser automatically when changes are detected to your web files
- Combines multiple files into one file to reduce HTTP connections between the server and your users
- Caches files on server if no new changes have been detected to the source files
- Serves '304 Not Mofidified' headers to users if the user already has the latest code in the browser's cache
- Uses gzip to further compress output files when available
- Adds CSS vendor prefixes automatically, along with a bunch of CSS enhancements
- [Bourbon](http://bourbon.io/) mixins added to any Sass files automatically


Basic usage
-----------

Just upload the 'cinch' folder to **the root folder of your site**, and replace all of your `<script>` or `<link>` tags in your HTML with just one tag that links to all of your JS/CSS files. 

### Example 

	<script src="/js/jquery.min.js" type="text/javascript"></script>
	<script src="/js/functions.js" type="text/javascript"></script>
	<script src="/js/scripts.js" type="text/javascript"></script>
	
looks like this in cinch:

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

- **[bower-package-name(/version)]** - To include an external library from the list below, enclose the name of the library and the version number(optional) in a pair of square brackets, separated by a forward slash (/). If no version is given, the latest version of the libary will be used.

	Example: `?files=[jquery]` or `?files=[jquery/1.10.2]`

	A full list of Bower packages can be found on the [Bower](http://bower.io/search/) website.
	


#### OPTIONAL SETTINGS
*Values marked with a star are the default and will be used if no value is given.*
		
- **type=( js | css | auto* )** - Indicate which type of files are being sent to Cinch
	- **js**: Process files as javascript
	- **css**: Process files as CSS
	- **auto***: Cinch will do it's best to automatically detect which type of files are being used. This is based on the extension of the first file in the list.
	
- **force=( true | false* )** - Force Cinch to rebuild the cache and update the user's browser with the newest code on every page load, even if no changes have been detected.

- **min=( true* | false | pack )** - Enable/disable minification on files. 
	- NOTE: Files marked with a '!' in order to avoid minification will no be minified regardless of this setting's value.
	- NOTE: The 'pack' setting minifies *and* obfuscates files. This setting applies only to javascript files. Standard minification will be applied to CSS files if this setting is used.
	
- **debug=( true* | false )** - When enabled, output files display errors. Otherwise, errors are ignored.


- reload=( true | false ) - Automatically checks for changes to your web files and reloads those files if a new version is found.
	- NOTE: Since this setting is javascript-based, live reloading requires that cinch process at least one link to javascript.
	- NOTE: This setting can only be enabled on a javascript link, not CSS.


### Requirements

- **PHP 5+** - Core functionality (minification and concatenization)  
- **PHP 5.1?** - Sass/SCSS Compiler (Just a guess as to which version is necessary)
- **PHP 5.1+** - LESS Compiler
- **PHP 5.3+** - CoffeeScript Compiler


### FAQs

- **Cinch isn't working. Why is that?**
	There could be a lot of things causing cinch not to run properly on your site: invalid links to cinch or your web files, errors in your code, etc. If you're getting a 404 error on your cinch links, then make sure cinch is properly loaded on your server and your links are correct. If your site is loading cinch, then you can check the top of the output files to see if cinch ran into any bugs or errors. Debug output in cinch is enabled by default.

- **How do I upgrade to a newer version of cinch?**
	Just overwrite the cinch folder with the new version! All of your dependencies will be automatically re-downloaded and all of your cache files will be rebuilt the next time you visit your page. After you've rebuilt your cache files, don't forget to set the 'PRODUCTION' constant at the top of the cinch/cinch.php file if you want to protect your cache folder.

- **How do I link to a package that has both CSS and JS files?**
	In cases, like Bootstrap, that have both CSS and javascript components, just include the package in both your CSS and JS links. Cinch will automatically separate the files into the correct types. If cinch isn't properly detecting which file type you are trying to use, add the type=css or type=js property to your file link.

- **I have so many cache files!**
	Don't worry! This happens a lot as part of the development process. You can delete all of the files in your /cinch/cache folder and cinch will rebuild all of your cache files automatically. Or just wait a month and cinch's automatic clean-up scripts will delete old cache files.


### Other Notes and Goodies

- If you want to speed up performance and prevent new cache files from being created on your server, simply set the PRODUCTION constant in cinch/cinch.php to TRUE. Production mode bypasses most of cinch's code to serve up the cached web files as quickly as possible. NOTE: New changes to any of the raw web files will not be reflected in the cache files.
- The [Bourbon](http://bourbon.io/) mixins library has been packaged with cinch, and will automatically be imported into your Sass files on execution. If you don't need them, no problem; the only extra bulk it will add to your stylesheets will be based on which mixins you use.
- CSS vendor prefixes are added automatically, along with smart CSS minification, color conversions, and more, thanks to [Javier Marín's](https://github.com/javiermarinros) css_optimizer. No need to write 5 lines of CSS to accommodate each browser anymore.
- A separate cache file is created for each combination of JS/CSS files that you use, so that different pages with different requirements can still run as quickly as possible. In order to prevent this folder from being overloaded on a busy development server, the cache is automatically cleared about once a month.



### Special Thanks

Cinch is made with the help of:

- [css_optimizer](https://github.com/javiermarinros/css_optimizer) by [Javier Marín](https://github.com/javiermarinros)

- Nicolas Martin's [PHP port](http://joliclic.free.fr/php/javascript-packer/en/) of Dean Edward's [Packer](http://dean.edwards.name/packer/)

- [JsShrink](https://github.com/vrana/JsShrink/) by Jakub Vrána

- [LESS/SCSS Processing](http://leafo.net/lessphp/)/[scssphp](http://leafo.net/scssphp/) by [leafo](http://leafo.net/)

- [CoffeeScript Processing](https://github.com/alxlit/coffeescript-php) by alxlit