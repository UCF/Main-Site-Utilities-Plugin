=== Main Site Utilities WP-CLI Package ===
Contributors: ucfwebcom
Tags: ucf, wp cli, wp, cli
Requires at least: 4.7.5
Tested up to: 4.7.5
Stable tag: 1.0.1
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
