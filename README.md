z-push-ox
=========

Open-Xchange Z-Push backend. Currently this is a work in progress and as some 
things like contact sync work to the degree of being usable others like calendar 
sync are simply broken.

20130708 virusbrain:
Added some initial stuff for email syncing. It is not really reliable at the 
moment, but works (sometimes) ;-).


Install
=======

1. setup Z-Push

2. make sure the following extra php libs are installed:
    * HTTP\_Request2
    * Net\_URL2

3. clone z-push-ox in the backends directory as ox
    * `cd backend && git clone https://github.com/z-push-ox/z-push-ox.git ox`

4. add the following to the backend settings (config.php):
    * `define('BACKEND_PROVIDER', "BackendOX");`
    * `define('OX_SERVER', 'https://your.server'); //http is also valid`


License
=======

This program is free software: you can redistribute it and/or modify it under 
the terms of the GNU Affero General Public License, version 3.
