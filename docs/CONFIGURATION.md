OpenSALT Configuration
======================

OpenSALT is intended to be configured using environment variables that can be passed into the docker container via the `docker/.env` file.

MySQL configuration
-------------------
To set the location of the MySQL database use the environment variables

 - MYSQL_HOST - Hostname to connect to the database
 - MYSQL_PORT - *(optional)* Port number to use to connect to the database
 - MYSQL_DATABASE - Name of the database schema
 - MYSQL_USER - Username used to connect to the database
 - MYSQL_PASSWORD - Password used to connect to the database

Secrets configuration
---------------------

A couple secrets are required for creating secure tokens

 - APP_SECRET - Should be a long random string
 - COOKIE_SECRET - Should be a different long random string

Branding configuration
----------------------

OpenSALT can have some branding associated with it

 - BRAND_LOGO_URL - *(optional)* URL to the logo shown to the right of the OpenSALT logo
 - BRAND_LINK_URL - *(optional)* URL that the brand logo will use when clicked
 - BRAND_LOGO_STYLE - *(optional)* An embedded style that will be added to the **img** tag of the logo
 - BRAND_LINK_STYLE - *(optional)* An embedded style that will be added ot the **a** tag wrapping the logo

Optional Features
-----------------------

### Commenting

OpenSALT uses the http://viima.github.io/jquery-comments/ bundle to allow editors to comment on and upvote published frameworks
  - COMMENTS_FEATURE - *(optional)* set to **always-active** to enable, the default is **inactive**

### Realtime notifications

OpenSALT can use Google's Firebase realtime database to update editors in real-time.

Read the [docs/deployments/firebase.md](./deployments/firebase.md) file to see how to configure it.

### Additional Fields

For the purpose of increasing presentation appeal, OpenSALT allows for additional fields in release 2.2. To add additional fields to CfItem, navigate to /additionalfield as a logged in SuperUser and add a field with LsItem in the dropdown.

Any additional fields will automatically be added to the spreadsheet export file and be read by the Spreadsheet Update feature. At this time additional fields for LsDoc and LsAssociation are not supported on the UI or backend. 
