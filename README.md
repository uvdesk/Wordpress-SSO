# Wordpress-SSO

Login with WordPress

Contributors: Webkul, UVdesk
Requires at least: 4.0
Tested up to: 4.9.2
License: GNU/GPL for more info see license.txt included with plugin
License URI: http://www.gnu.org/licenseses/gpl-2.0.html

1. **INSTALLATION PROCESS**

  * Download Wordpress SSO
  * Go to wordpress admin dashboard.
  * Go to Plugins->Add New.
  * Go to Upload Plugin and browse to downloaded zip file.
  * After uplaoding it, activate the plugin.

2. **SETTING DESCRIPTION**

  * Login URl - site_url()/uv-login?oauth_consumer_key=xyz
  * Token URl(post request) - site_url()/wp-json/oauth/token
  * Authorize Client URl(post request) - site_url()/wp-json/check/token
  * Post Data -
      - auth_consumer_key
      - auth_code
