Z-Push-OX
=========

Z-Push-OX is a Z-Push-2 backend for Open-Xchange. As it is a work in progress
please see the [feature matrix] for the currently implemented features and take a
look at the [bugtracker].

Infrastructure
--------------

![](http://z-push.sourceforge.net/soswp/uploads/1232370881.png)

Z-Push-2 is an implementation of Microsoft's ActiveSync protocol which is used
'over-the-air' for multi platform active sync devices, including Windows Mobile
and active sync used on Apple's iPhone, Sony Ericsson and Nokia phones. Open
source Z-Push enables any PHP-based groupware package to become fully syncable
with any ActiveSync-compliant device.

Z-Push-OX is implemented as backend using the Differential Engine.

Changelog
---------

The changelog can be found [here][changelog].

Requirements
------------

  * Open-Xchange >= 7.2.1
  * Z-Push-2 >= 2.0.7

Installation
------------

RPM and DEB files are provided on the [releases] page of the repository.

### Debian / Ubuntu

Z-Push has been re-branded by Debian and is called d-push. You may find the
appropriate deb files [here][d-push].

We did not include [HTTP\_Request2] and [Net\_URL2] as dependency for the deb files as 
there are no official deb files for them. You need to resolve this dependency for 
yourself.

      pear install --alldeps HTTP_Request2 Net_URL2

### Nightly Builds

There are [nightly builds] available. Keep in mind however, that these are likely to be
more unstable than release builds.

### From Source

1.  [setup z-push]

2.  make sure the following extra php libs are installed:

   * [HTTP\_Request2]

   * [Net\_URL2]

3.  clone z-push-ox in the backends directory as ox

        cd backend && git clone https://github.com/z-push-ox/z-push-ox.git ox

4.  add the following to the backend settings (config.php):

        define('BACKEND_PROVIDER', "BackendOX");
        define('OX_SERVER', 'https://your.server'); //http is also valid

Feature Requests & Bugs
-----------------------

Please use the projects [bugtracker] to report bugs or file feature requests.

License
-------

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU Affero General Public License, version 3.

[bugtracker]: https://github.com/z-push-ox/z-push-ox/issues
[d-push]: http://packages.debian.org/search?keywords=d-push
[feature matrix]: https://github.com/z-push-ox/z-push-ox/blob/master/featurematrix.md
[changelog]: https://github.com/z-push-ox/z-push-ox/blob/master/changelog
[HTTP\_Request2]: http://pear.php.net/package/HTTP_Request2
[Net\_URL2]: http://pear.php.net/package/Net_URL2
[nightly builds]: http://sourceforge.net/projects/z-push-ox/files/
[releases]: https://github.com/z-push-ox/z-push-ox/releases
[setup z-push]: http://doc.zarafa.com/7.0/Administrator_Manual/en-US/html/_zpush.html
[Z-Push]: http://z-push.sourceforge.net
