Feature: Install any plugins that are active but missing

  Scenario: No missing plugins
    Given a WP install
    And I run `wp plugin install-missing --dry-run`
    Then STDOUT should contain:
      """
      No missing plugins
      """

  Scenario: Install missing plugins
    Given a WP install
    And I run `wp plugin install display-posts-shortcode --activate`
    And I run `rm -rf wp-content/plugins/display-posts-shortcode`
    And I run `wp plugin install-missing --dry-run`
    Then STDOUT should contain:
      """
      display-posts-shortcode
      """
    When I run `wp plugin install-missing`
    Then STDOUT should contain:
      """
      Installed missing plugins.
      """

  Scenario: Run network mode on a normal installation
    Given a WP install
    And I try `wp plugin install-missing --dry-run --network`
    Then STDERR should contain:
      """
      This is not a multisite installation!
      """
    And STDERR should contain:
      """
      Remove the --network flag to run the plugin on a normal installation
      """
    And the return code should be 1


  Scenario: Install missing plugins network-wide
    Given a WP multisite subdomain install
    And I run `wp site create --slug=site1`
    And I run `wp plugin install display-posts-shortcode --activate`
    And I run `wp plugin install old-post-notification --url=http://site1.example.com/ --activate`
    And I run `rm -rf wp-content/plugins/display-posts-shortcode`
    And I run `rm -rf wp-content/plugins/old-post-notification`
    And I run `wp plugin install-missing --network --dry-run`
    Then STDOUT should contain:
      """
      Site: http://example.com/
      The following plugins are missing:
      * display-posts-shortcode

      Site: http://site1.example.com/
      The following plugins are missing:
      * old-post-notification
      """
    When I run `wp plugin install-missing --network`
    Then STDOUT should contain:
      """
      Site: http://example.com/
      """
    And STDOUT should contain:
      """
      Installing Display Posts Shortcode
      """
    And STDOUT should contain:
      """
      Installed missing plugins for http://example.com/.
      """
    And STDOUT should contain:
      """
      Site: http://site1.example.com/
      """
    And STDOUT should contain:
      """
      Installing Old Post Notification
      """
    And STDOUT should contain:
      """
      Installed missing plugins for http://site1.example.com/.
      """
    When I run `wp plugin install-missing --network --dry-run`
    Then STDOUT should contain:
      """
      Site: http://example.com/
      Success: No missing plugins
      
      Site: http://site1.example.com/
      Success: No missing plugins
      """


  Scenario: Install missing plugins network-wide with error
    Given a WP multisite subdomain install
    And I run `wp site create --slug=site1`
    And I run `wp plugin install display-posts-shortcode --activate`
    And I run `wp plugin install old-post-notification --url=http://site1.example.com/ --activate`
    And I run `rm -rf wp-content/plugins/display-posts-shortcode`
    And I run `rm -rf wp-content/plugins/old-post-notification`
    And I run `wp option update active_plugins '["non-existing-plugin\/plugin.php"]' --format=json --url=http://example.com/`
    And I run `wp option get active_plugins --url=http://example.com/ --format=json`
    Then STDOUT should contain:
      """
      ["non-existing-plugin\/plugin.php"]
      """
    When I try `wp plugin install-missing --network`
    Then STDERR should contain:
      """
      Couldn't find 'non-existing-plugin' in the WordPress.org plugin directory.
      """
    And the return code should be 1


  Scenario: Install missing plugins network-wide continue on error
    Given a WP multisite subdomain install
    And I run `wp site create --slug=site1`
    And I run `wp plugin install display-posts-shortcode --activate`
    And I run `wp plugin install old-post-notification --url=http://site1.example.com/ --activate`
    And I run `rm -rf wp-content/plugins/display-posts-shortcode`
    And I run `rm -rf wp-content/plugins/old-post-notification`
    And I run `wp option update active_plugins '["non-existing-plugin\/plugin.php"]' --format=json --url=http://example.com/`
    And I run `wp option get active_plugins --url=http://example.com/ --format=json`
    Then STDOUT should contain:
      """
      ["non-existing-plugin\/plugin.php"]
      """
    When I run `wp plugin install-missing --network --continue-on-error`
    Then STDOUT should contain:
      """
      Couldn't find 'non-existing-plugin' in the WordPress.org plugin directory.
      Error: No plugins installed.
      """
    And STDOUT should contain:
      """
      Success: Installed missing plugins for http://site1.example.com/.
      """
    And the return code should be 0
    When I run `wp plugin install-missing --network --dry-run`
    Then STDOUT should contain:
      """
      Site: http://example.com/
      The following plugins are missing:
      * non-existing-plugin
      
      Site: http://site1.example.com/
      Success: No missing plugins
      """
