cinch - about the cache folder
==============================


This folder is where all cached files are stored on the server. A separate cache file is created for each combination of JS/CSS files and when any of the query settings are changed. These files are not automatically deleted and can add up quickly if you are making frequent changes, so it may be desirable to clear this folder from time to time. 

To do so, you can simply delete the files from this folder (they will automatically be regenerated), or run the 'clearcache' function from your browser, like so:


	http://example.com/cinch/?clearcache=true