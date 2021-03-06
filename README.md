# Main Site Utilities WP-CLI Package #

Provides utilities (jobs) to run for the main website.

## Description ##

Provides utilities (jobs) to run for the main website.


## Installation ##

### Manual Installation ###
1. Upload the plugin files (unzipped) to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress
3. Run commands via wp cli. See [WP-CLI Docs](http://wp-cli.org/commands/plugin/install/) for more command options.

### WP CLI Installation ###
1. `$ wp plugin install --activate https://github.com/UCF/Main-Site-Utilities/archive/master.zip`.  See [WP-CLI Docs](http://wp-cli.org/commands/plugin/install/) for more command options.
3. Run commands via wp cli.

## Commands ##

All commands are stored under the `mainsite` core command. To see available options run `wp mainsite`.

### Degree Commands ###

All degree commands are stored under the `degrees` command. To see avilable options run `wp mainsite degrees`.

Import: `wp mainsite degrees import <search_url> <catalog_url> [--publish]`

Imports degrees into the main site.

- <search_url>
    - The url of the search service. (Required)
- <catalog_url>
    - The url of the undergraduate catalog api. (Required)
- [--publish]
    - Flag that publishes all new degrees. (Optional)

Tuition and Fees: `wp mainsite degrees tuition <api>`

Adds tuition and fee information to main site degrees.

- <api>
    - The url of the tuition feed

Imports degrees from various sources and writes them into degree custom post types.


## Changelog ##

### 1.0.1 ###
Enhancements:
* Added converter/importer for azindex links.

Bug Fixes:
* Updated name of `Doctoral` program type to `Doctorate` to match the logic used during the import.

### 1.0.0 ###
* Initial release


## Upgrade Notice ##

n/a


## Installation Requirements ##

None


## Development & Contributing ##

NOTE: this plugin's readme.md file is automatically generated.  Please only make modifications to the readme.txt file, and make sure the `gulp readme` command has been run before committing readme changes.
