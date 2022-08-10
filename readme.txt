=== Main Site Utilities WP-CLI Package ===
Contributors: ucfwebcom
Tags: ucf, wp cli, wp, cli
Requires at least: 5.3
Tested up to: 5.3
Stable tag: 3.0.1
License: GPLv3 or later
License URI: http://www.gnu.org/copyleft/gpl-3.0.html

Provides utilities (jobs) to run for the main website.

== Description ==

Provides utilities (jobs) to run for the main website.


== Installation ==

= Manual Installation =
1. Upload the plugin files (unzipped) to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress
3. Run commands via wp cli. See [WP-CLI Docs](http://wp-cli.org/commands/plugin/install/) for more command options.

= WP CLI Installation =
1. `$ wp plugin install --activate https://github.com/UCF/Main-Site-Utilities/archive/master.zip`.  See [WP-CLI Docs](http://wp-cli.org/commands/plugin/install/) for more command options.
3. Run commands via wp cli.

== Commands ==

=== Research Import ===

```
wp research import
    <search_url>
    [--params=<params>]
    [--force-template=<bool>]
    [--force-update=<bool>]
```

**Options**

| Option | Type | Description | Default |
|---|---|---|---|
| `search_url` | `string` | The URL of the search service. | empty |
| `--params` | `string` | An HTML encoded parameter string. Can be used to add additional filtering to the search service endpoint. | null |
| `--force-template` | `bool` | If True, forces an update to the page template for each researcher imported. | false |
| `--force-update` | `bool` | If True, removes all researchers prior to importing. | false |


== Changelog ==

= 3.0.1 =
Bug Fixes:
* Corrected a syntax error that causes problems when using WP CLI.

= 3.0.0 =
Enhancements:
* Addition of the Workday job listings shortcode `[ucf-jobs]`.
* This also changes the main plugin's file name from commands.php to main-site-utilities.php, which results in the plugin being deactivated if pushed when currently activated for the site. To go around this we can just deactivate it before we push up the changes to new environments. This shouldn't effect anything, since the only thing we use this plugin for currently is running WP-CLI commands.
* New customizer options have been created for the Jobs/WorkDay URLs for the feed and the base site URL. The base URL is used to create the individual job listing links, since just the relative path to them is included in the json feed. The defaults set for them will need to be updated once we have the "live" WorkDay URLs come July 1.
* A future tag will add in the ability to filter listings by Job Family (faculty, staff, etc).


= 2.0.0 =
Enhancements:
* Added importers for research from the search service, as well as for researchers' data from external WP instances.
* Removed importers that are no longer in use.

= 1.0.1 =
Enhancements:
* Added converter/importer for azindex links.

Bug Fixes:
* Updated name of `Doctoral` program type to `Doctorate` to match the logic used during the import.

= 1.0.0 =
* Initial release


== Upgrade Notice ==

n/a


== Installation Requirements ==

None


== Development & Contributing ==

NOTE: this plugin's readme.md file is automatically generated.  Please only make modifications to the readme.txt file, and make sure the `gulp readme` command has been run before committing readme changes.
