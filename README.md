z-push-ox
=========

Open-Xchange Z-Push backend. Currently this is a work in progress.


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


Feature List
============

### Legend:
  - X ~ working
  - \- ~ not working
  - O ~ not tested / not testable
  - OX ~ edited in OX and synced to device
  - AS ~ edited on device and synced to OX

### EMail:

### Calendar:
###### Basics:
<table>
  <tr>
    <th></th>
    <th>OX</th>
    <th>AS</th>
  </tr>
  <tr>
    <td>create</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>delete</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>allday events</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>set title</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>set start / end date</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>change folder</td>
    <td>-</td>
    <td>-</td>
  </tr>
  <tr>
    <td>add / edit attendees</td>
    <td>-</td>
    <td>-</td>
  </tr>
  <tr>
    <td>alerts</td>
    <td>-</td>
    <td>-</td>
  </tr>
</table>
###### Recurrence:
<table>
  <tr>
    <th></th>
    <th>OX</th>
    <th>AS</th>
  </tr>
  <tr>
    <td>no recurrence</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>set end of recurrence</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>exceptions</td>
    <td>-</td>
    <td>-</td>
  </tr>
  <tr>
    <td>daily</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>weekly</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>monthly</td>
    <td>X</td>
    <td>X</td>
  </tr>
  <tr>
    <td>monthly on the nth day</td>
    <td>X</td>
    <td>O</td>
  </tr>
  <tr>
    <td>yearly</td>
    <td>O</td>
    <td>O</td>
  </tr>
  <tr>
    <td>yearly on the nth day</td>
    <td>O</td>
    <td>O</td>
  </tr>
</table>


### Contacts:
You can add, delete and modify contacts. Contact folders are supported (given your device supports them as well).
Moving a contact from one group to another is not supported.

###### Synced fields:
  * fileas
  * lastname
  * firstname
  * middlename
  * nickname
  * birthday (see issue #6)
  * title
  * department
  * suffix
  * anniversary
  * assistantname
  * assistnamephonenumber
  * spouse
  * body
  * imaddress
  * imaddress2
  * homecity
  * homecountry
  * homepostalcode
  * homestate
  * homestreet
  * businesscity
  * businesscountry
  * businesspostalcode
  * businessstate
  * businessstreet
  * othercity
  * othercountry
  * otherpostalcode
  * otherstate
  * otherstreet
  * managername
  * email1address
  * email2address
  * email3address
  * companyname
  * jobtitle
  * webpage
  * homephonenumber
  * home2phonenumber
  * mobilephonenumber
  * telephone_pager
  * carphonenumber
  * homefaxnumber
  * radiophonenumber
  * businessphonenumber
  * business2phonenumber
  * businessfaxnumber
  * categories
  * yomifirstname
  * yomilastname
  * yomicompanyname


License
=======

This program is free software: you can redistribute it and/or modify it under 
the terms of the GNU Affero General Public License, version 3.
