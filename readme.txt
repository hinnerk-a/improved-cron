=== Improved Cron ===
Contributors: cpkwebsolutions, hinnerk
Donate link: http://cpkwebsolutions.com/donate
Tags: cron, ping, scheduled, jobs, view, wp_cron, task
Requires at least: 3.3
Tested up to: 4.3.1
Stable tag: 1.3.3
License: GPLv2 or later

Keep WP-Cron running every minute for scheduled tasks without actually using Cron.

== Description ==

Cron not running when you expect?  This plugin may help.  Also provides insight into WP-Cron exactly like the Cron View plugin.

This plugin will visit your site every minute and thereby ensure that your cron jobs run on time.  It is mostly intended for people who can't use real cron for some reason.

Note: This is the exact same plugin that was previously sold on Code Canyon.

Contributions are welcome: https://github.com/hinnerk-a/improved-cron

== Installation ==

1. Either use the built-in plugin installer, or download the zip and extract to your 'wp-content/plugins' folder.
2. Activate the plugin in Plugins > Installed Plugins
3. Open the 'Improved Cron' main menu item on the left side of your WordPress dashboard

== Frequently Asked Questions ==

= What is a “fake visit”? =
WordPress Cron only runs when someone visits your site. A fake visit causes a page to load without needing a real person to visit.
For the more technically minded, the plugin spawns a PHP sub-process that loops indefinitely and loads wp-cron.php each minute. This causes WP Cron to be triggered in exactly the same way it would if a real user was visiting your site.

= Can the interval be adjusted? =
Yes. However, you’d need to modify the plugin code. The reason is that WordPress won’t allow cron jobs to run more frequently than 1 minute, and running every minute has very little downside.
If you really must tinker with it, hook a filter into 'imcron_interval_id' like this (change '123' to your desired interval time):

    add_filter( 'cron_schedules', 'add_my_own_interval' );
    function add_my_own_interval() {
        $seconds = 123;
        $interval['my_own_interval'] = array('interval' => $seconds, 'display' => sprintf( '%d seconds', $seconds ) );
        return $interval;
    }

    add_filter( 'imcron_interval_id', 'set_imcron_interval' );
    function set_imcron_interval() {
        return 'my_own_interval';
    }

You’ll need to stop and re-start on the settings page to get the new interval to take effect.

= Will it list every scheduled event in the admin, including those scheduled by plugins or themes? =
Yes, it includes all events scheduled within WordPress at the time.

= What is PHP requirement of using this? Is it PHP exec() enable? =
If your WP-Cron jobs work when you have a visitor, then this plugin should work for you. The plugin uses exactly the same code as WP Cron does, just in a different way, and with safeguards, a keep alive, logging available, etc.
The ‘background process’ is really a HTTP request that loops indefinitely until you press stop in the panel. I also use a lock file system to prevent multiple ‘processes’ starting, as well as provide a failsafe method of killing the process (if the lock file is gone when it wakes up, then it dies).

= Doesn’t running a background process consume extra memory? =
Yes, but only a small, fixed, amount of memory. During testing, I left the plugin running for a couple of weeks while logging memory usage each minute. There was no growth in memory usage (Iow, no memory leak) and a pretty small memory footprint (under 250KB).

= Do you know if the “fake” visits will be detected by WP Stats/Google Analytics/etc? =
The plugin calls wp-cron.php directly, so I doubt any stats programs will record these visits.

= Does this allow you to create cron jobs, or does it just help them run? =
It just helps them run when you expect them to (+/- 1 minute). Normally, WP Cron requires a visitor so the actual run time of a cron job can be hours after you scheduled it to run.

= Will this works with WordPress MultiSite? And if so, can I turn certain double crons (like plugin update checks) off? =
I haven’t done any testing with WordPress multisite yet. I created it for a project I was working on and spun it off into a standalone plugin. Unfortunately, I haven’t made anywhere near enough sales to justify much extra development effort at this stage, however I’m open to sponsorship to test and/or extend it.

== Screenshots ==

1. Interface of Improved Cron

== Changelog ==

= 1.3.3 =
* Returning actual bgp interval in settings makes possible to be changed, dynamically.

= 1.3.2 =
* Fix stable tag

= 1.3.1 =
* Fixed readme.txt
* Cleanup SVN

= 1.3.0 =
* Hinnerk Altenburg now contributing
* Cleanup and smaller fixes
* Security fixes
* New filter hook 'imcron_interval_id'
* Updates docs

= 1.2.0 =
* Transferred from Code Canyon

== Upgrade Notice ==

= 1.3 =
Improved Cron has been updated to run with current WP versions (4.3), properly. The interval can now be changed via filter hook.