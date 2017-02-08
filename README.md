# wp cli plugin install-missing
Install any plugins that are "active" but not installed. 

I use it when pulling an updated database to my local installation. If any plugins were installed since I last worked on the site, this will add them to my local install as well.

* `wp plugin install-missing --dry-run` - Run the search and show report, but don't install missing plugins
* `wp plugin install-missing` - Run the search, show report, and install missing plugins
* `wp plugin install-missing --network` - Run the search, show report, and install missing plugins for each site in the network
* `wp plugin install-missing --network --continue-on-error` - Same as before but it doesn't stop when an error (i.e. plugin not found) occurs

### Installation

Either download and install as a plugin, or run `wp package install billerickson/wp-cli-plugin-install-missing`
