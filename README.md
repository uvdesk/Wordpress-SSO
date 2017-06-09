# Wordpress-SSO

Login with WordPress

1. **INSTALLATION PROCESS**

  * Download Wordpress SSO
  * Go to wordpress admin dashboard.
  * Go to Plugins->Add New.
  * Go to Upload Plugin and browse to downloaded zip file.
  * After uplaoding it, activate the plugin.

2. **SETTING DESCRIPTION**

  * Login URl - site_url()/uv-login?oauth_consumer_key=xyz
  * Token URl(post request) - site_url()/wp-json/oauth/token
  * Post Data -
      - oauth_consumer_key
      - oauth_code
