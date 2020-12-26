=== Drafts for Friends ===
Contributors: firoz2456
Tags: drafts, friends, draftforfriends, share draft
Requires at least: 4.9
Tested up to: 4.9.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Your friends don't need to login to see the drafts

== Description ==

Using this plugin you can generate unique link and send it to your friends, so they can access it without login. 

You can generate unique links for drafts, schdeuled post and pending post. You can set expiry time. This is very helpful before publishing any post. 


== Installation ==

1. Upload `draftsforfriends` folder to the `/wp-content/plugins/` directory
2. Activate the `draftsforfriends` plugin through the 'Plugins' menu in WordPress
3. You can access `draftsforfriends` via `WP-Admin -> Posts -> Drafts for Friends`


== Screenshots ==

1. Administrator Page
2. Extended the expiry
3. Previewing shared draft


== Changelog ==

= 1.0.0 =
* Added Author Name and other header information
* Update deprecated WordPress functions
* Added new column "Expires After"
* Added value in human-readable format.
* Code added to restrict direct plugin access
* Added Javascript alert for delete "shared draft"
* Javascript validation added for selection box in "Share Draft" form
* WP Nonce added in  "Share Draft" form
* Strict comparisons added
* Replaced direct database access code with wp_query
* Sanitized and Validate $_POST and $GET array 
* Sanitized and Validate all output text
* Code formatting using phpcs and phpbcf (WordPress VIP standard)
* Javascript code and CSS separate from the main plugin file
* Added i18n functions
* Added code comments for all the functions
* Added README.txt file


== Upgrade Notice ==
N/A