=== Battlefield 2 Stats ===
Contributors: Viper007Bond
Donate link: http://www.viper007bond.com/donate/
Tags: widget, sidebar, stats, battlefield, bf2
Requires at least: 2.0
Tested up to: 2.3
Stable tag: trunk

Displays your Battlefield 2 stats and displays them on your blog. Intended for your sidebar, but can be used anywhere. Supports sidebar widgets.

== Description ==

This plugin creates a function which outputs your Battlefield 2 profile statistics. The data is fetched via a XML feed from [BF2S.com](http://bf2s.com/). It's intended for your sidebar, but can also be used on a WordPress page.

It's fully configurable via an options page in the WordPress admin area and only requires that you either use the [Sidebar Widgets](http://wordpress.org/extend/plugins/widgets/) plugin or that you add the plugin's output function to your theme's sidebar to get it working.

To see it in action, check out the sidebar of [the plugin's homepage](http://www.viper007bond.com/wordpress-plugins/battlefield-2-stats/ "You'll find the output in the sidebar, down a little bit").

== Installation ==

Extract all files from the ZIP file, making sure to keep the file structure intact. **Rename the folder to "wp_bf2s"** and then upload it to `/wp-content/plugins/`.

This should result in the following file structure:

`- wp-content
    - plugins
        - wp_bf2s
            | readme.txt
            | screenshot-1.png
            | template.po
            | wp_bf2s.php
            - images
                | close.gif
                | rank_0.gif
                | rank_1.gif
                | rank_2.gif
                [ ... snip ... ]
                | rank_19.gif
                | rank_20.gif
                | rank_21.gif`

If you are using the [Sidebar Widgets](http://wordpress.org/extend/plugins/widgets/) plugin, then you're done.

If you aren't, then you'll need to edit your theme's `sidebar.php` file. Add the following code where you'd like the output to show up:

	<?php if (function_exists('wp_bf2s')) { wp_bf2s(); } ?>

== Frequently Asked Questions ==

= My points are too low! Both BFHQ ingame and BF2S.com show more points than the plugin is currently showing. Why is that? =

This plugin only refreshes it's data every 6 hours as BF2S.com only allows 3 queries per 6 hours per IP address. This plugin is limited to just 1 query per 6 hours incase anyone else on your server also uses this plugin, plus 6 hours isn't all that long.

= The plugin output says I'm 100% to the next rank and I have more points than required to get the next rank. What's going on? =

Go play on a different server and get a kill. Your rank should be updated then.

= What do the different percentage types mean? =

* **Rank difference:** This displays the percentage that you are from your current rank points to the points of the next rank, i.e. if you have exactly the points needed for a rank, it'll display 0% to the next rank.
* **Overall points:** This will display how far you are to the next rank based on your global score and not your current rank.

= The plugin is always reporting that the XML feed was blank or that it couldn't parse it. Why is that? =

The most likely answer is that you don't have enough points. BF2S.com only keeps track of people with at least 100 points. Go play some more!

== Screenshots ==

1. Screenshot of the options / debug page in the admin area