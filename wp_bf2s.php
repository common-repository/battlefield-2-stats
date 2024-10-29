<?php /*

Plugin Name: Battlefield 2 Stats
Plugin URI: http://www.viper007bond.com/wordpress-plugins/battlefield-2-stats/
Version: 1.14
Description: Fetches your Battlefield 2 stats from <a href="http://bf2s.com/">BF2S.com</a> and displays them on your blog. Based on and uses a bit of code from the <a href="http://jrm.cc/extras/mlb/">BF2S MyLeaderBoard API</a> by <a href="http://jrm.cc/">Jeff Minard</a>.
Author: Viper007Bond
Author URI: http://www.viper007bond.com/

*/


# There's nothing to edit in here! To configure this plugin, go to your WordPress admin area
# and click on "Options" and then "BF2 Stats". You can control the plugin there. ;)


$wp_bf2s_version = '1.14';


// Load up the localization file if we're using WordPress in a different language
// Place it in your "wp_bf2s" folder and name it "wp_bf2s-[value in wp-config].mo"
// A link to some localization files is location at the homepage of this plugin
load_plugin_textdomain('wp_bf2s', 'wp-content/plugins/wp_bf2s');


// These next items is how this plugin hooks into WordPress
add_action('admin_menu', 'bf2s_addoptionspage');	// Tell WordPress about the options page
add_action('admin_head', 'bf2s_initfunctions');		// Tell WordPress to run our function that needs to run on every admin page
add_action('admin_notices', 'bf2s_displayupdate');	// Have WordPress display the update notice if there's a new version
// Set the parameters of the options page
function bf2s_addoptionspage() {
	if (function_exists('add_options_page')) {
		add_options_page(__('Battlefield 2 Stats Configuration', 'wp_bf2s'), str_replace(' ', '&nbsp;', __('BF2 Stats', 'wp_bf2s')), 6, basename(__FILE__), 'bf2s_optionspage');
	}
}


// A bit of CSS for the plugin
add_action('admin_head', 'wp_bf2s_generic_css');
add_action('admin_head', 'wp_bf2s_admin_css');
add_action('wp_head', 'wp_bf2s_generic_css'); // Comment this line out if you want to apply your own styling for the next rank span
function wp_bf2s_generic_css() {
	echo '	<style type="text/css">.bf2s_nextrank { cursor: help; text-decoration: underline; }</style>' . "\n";
}
function wp_bf2s_admin_css() {
	echo '	<style type="text/css">.bf2s_hide a { float: right; margin: -9px -9px 0 10px; border: none; }</style>' . "\n";
}


// Create the default settings
$bf2s_default_settings = array(
	'version' => $wp_bf2s_version,
	'display_nick_link' => 'on',
	'display_rank_icon' => 'on',
	'display_score' => 'on',
	'display_time' => 'on',
	'display_kills' => 'on',
	'display_deaths' => 'on',
	'check_for_updates' => 'on',
);

// Handle option page form submission
if ($_POST && $_GET['page'] == basename(__FILE__)) {
	$bf2s_settings = bf2s_settings();

	if ($_POST['defaults']) {
		$bf2s_default_settings['version']			= $wp_bf2s_version;
		$bf2s_default_settings['lastupdatecheck']	= $bf2s_settings['lastupdatecheck'];
		$bf2s_default_settings['pid']				= $bf2s_settings['pid'];

		update_option('bf2s_settings', $bf2s_default_settings);

		$bf2s_message .= '<p><strong>' . __('Options reset to defaults.', 'wp_bf2s') . '</strong></p>';
	} else {
		$bf2s_new_pid = (int) $_POST['pid'];

		// Don't allow multiple PIDs because even though the BF2S.com feed supports it, this plugin doesn't, at least for now
		if (strstr($bf2s_new_pid, ',')) {
			$temp = explode(',', $bf2s_new_pid);
			$bf2s_new_pid = $temp[0];
		}
		$bf2s_new_pid = trim($bf2s_new_pid);

		// If the new PID is 0, fall back to the current PID
		if ($bf2s_new_pid === '0') {
			$bf2s_pid = $_POST['pid_current'];
		}

		// If the PID hasn't changed, then we have nothing to worry about
		elseif ($bf2s_new_pid == $_POST['pid_current'] || $_POST['pid_current'] === '0') {
			$bf2s_pid = $bf2s_new_pid;
			$bf2s_message .= '<p><strong>' . __('Options saved.', 'wp_bf2s') . '</strong></p>';
		}
		
		// Otherwise, save everything but the PID and ask for confirmation
		else {
			$bf2s_pid = $_POST['pid_current'];
			$bf2s_message .= '<p><strong>' . sprintf(__("Display options saved, but you've attempted to change your PID / player name. Are you REALLY sure that you want to do that? Changing your it multiple times over a short period of time can get your server's IP address temporarily banned from fetching data from BF2S.com. If you're really sure you want to change it to &quot;%s&quot;, then <a href='%s'>click here</a>.", 'wp_bf2s'), '<a href="http://bf2s.com/player/' . $bf2s_new_pid . '/">' . $bf2s_new_pid . '</a>', htmlspecialchars(add_query_arg('newpid', $bf2s_new_pid), ENT_QUOTES)) . '</strong></p>';
		}

		// Save to the database
		update_option('bf2s_settings', array(
			'version' =>				$wp_bf2s_version,
			'lastupdatecheck' =>		$bf2s_settings['lastupdatecheck'],
			'pid' =>					trim($bf2s_pid),
			'display_nick_link' =>		$_POST['display_nick_link'],
			'display_rank_icon' =>		$_POST['display_rank_icon'],
			'display_score' =>			$_POST['display_score'],
			'display_time' =>			$_POST['display_time'],
			'display_time_units' =>		$_POST['display_time_units'],
			'display_spm' =>			$_POST['display_spm'],
			'display_kills' =>			$_POST['display_kills'],
			'display_deaths' =>			$_POST['display_deaths'],
			'display_kdr' =>			$_POST['display_kdr'],
			'display_wins' =>			$_POST['display_wins'],
			'display_losses' =>			$_POST['display_losses'],
			'display_wlr' =>			$_POST['display_wlr'],
			'display_rank_percent' =>	$_POST['display_rank_percent'],
			'display_percent_type' =>	($_POST['display_percent_type'] == 'overall') ? 'overall' : 'difference',
			'check_for_updates' =>		$_POST['check_for_updates'],
		));
	}
}

// Handle PID change confirmations
elseif ($_GET['newpid'] && $_GET['page'] == basename(__FILE__)) {
	// Get the current settings
	$bf2s_settings = bf2s_settings();

	// Change it and save it
	$bf2s_settings['pid'] = trim($_GET['newpid']);
	update_option('bf2s_settings', $bf2s_settings);

	// Clear the data cache
	delete_option('bf2s_data');

	$bf2s_message .= '<p><strong>' . __('PID changed and data cache cleared.', 'wp_bf2s') . '</strong></p>';
}

// Handle clearing of the log
elseif ($_GET['clearlog'] === '1' && $_GET['page'] == basename(__FILE__)) {
	delete_option('bf2s_log');

	$bf2s_message .= '<p><strong>' . __('Log cleared.', 'wp_bf2s') . '</strong></p>';
}


// In a function so that it doesn't execute on every single page load
function bf2s_settings() {
	global $bf2s_settings, $bf2s_default_settings, $wp_bf2s_version;

	// Get the current settings, set to default if they don't exist
	$bf2s_settings = get_option('bf2s_settings');
	if (!is_array($bf2s_settings)) {
		// Put the default settings into the database
		$bf2s_default_settings['pid'] = '0';
		$bf2s_default_settings['version'] = $wp_bf2s_version;
		$bf2s_default_settings['lastupdatecheck'] = 0;
		update_option('bf2s_settings', $bf2s_default_settings);

		// Now set the settings to the default ones
		$bf2s_settings = $bf2s_default_settings;
	}
	
	// Make sure we have a PID
	elseif (!isset($bf2s_settings['pid'])) {
		$bf2s_settings['pid'] = 0;
		update_option('bf2s_settings', $bf2s_settings);
	}

	return $bf2s_settings;
}


// Some stuff that needs to run on every admin page
function bf2s_initfunctions() {
	global $wp_bf2s_version, $wp_version, $bf2s_log;

	$bf2s_settings = bf2s_settings();

	// If we've upgraded from a previous version, perform some updates
	if ($bf2s_settings['version'] < $wp_bf2s_version) {
		if ($bf2s_settings['version']) {
			$bf2s_log[] = array(time(), sprintf(__('Old version of the plugin options detected. Updating from version %s to version %s...', 'wp_bf2s'), $bf2s_settings['version'], $wp_bf2s_version));
		} else {
			$bf2s_log[] = array(time(), sprintf(__('Old version of the plugin options detected. Updating to version %s...', 'wp_bf2s'), $wp_bf2s_version));
		}

		// Upgrade from past versions
		if ($bf2s_settings['version'] < 1.10) {
			$bf2s_settings['display_nick_link'] = 'on';
			$bf2s_settings['check_for_updates'] = 'on';
			$bf2s_settings['lastupdatecheck'] = 0;
		}
		if ($bf2s_settings['version'] < 1.13) {
			// Only allow numbers now as BF2S.com no longer allows the use of playernames
			$bf2s_settings['pid'] = (int) $bf2s_settings['pid'];
		}
		$bf2s_settings['version'] = $wp_bf2s_version; // Update the version in the database
		update_option('bf2s_settings', $bf2s_settings);

		// And clear out the update message
		delete_option('bf2s_globalnotice');
	}

	// If we're allowed to check for new versions and if it's been over 24 hours since we last checked for a new version, then let's check now
	if ($bf2s_settings['check_for_updates'] == 'on' && $bf2s_settings['lastupdatecheck'] < time() - 86400) {

		// Record that we've checked for a new version, successful or not
		$bf2s_settings['lastupdatecheck'] = time();
		update_option('bf2s_settings', $bf2s_settings);

		// Create the update URL and include some stats about this install
		// (The stats are just for my curiosity of who's using my plugin and to help me better code it)
		$update_url = 'http://www.viper007bond.com/wp_bf2s_version_check.php?wpbf2sver=' . urlencode($wp_bf2s_version) . '&blogurl=' . urlencode(get_bloginfo('url')) . '&wpver=' . urlencode($wp_version) . '&pid=' . urlencode($bf2s_settings['pid']);

		// Alright, now go check the update script
		$update_results = unserialize(wp_bf2s_fetch_url($update_url));

		// If we got a result and the latest version is newer than this one...
		if ($update_results === FALSE || !isset($update_results['version'])) {
			$bf2s_log[] = array(time(), __("Failed to check for a new version. We'll try again in at least 24 hours.", 'wp_bf2s'));
		} elseif ($update_results['version'] > $wp_bf2s_version) {
			// Since we have a new version, save the data so we can keep displaying it without re-connecting to the update URL
			update_option('bf2s_globalnotice', $update_results);

			$bf2s_log[] = array(time(), __('New version of this plugin has been found and saved the data about it.', 'wp_bf2s'));
		}
	}

	// If any new log entries were made, save them to the database
	if ($bf2s_log) wp_bf2s_update_log();
}


// If we've been asked to hide the update message
if ($_GET['hidebf2supdate']) {
	delete_option('bf2s_globalnotice');

	$bf2s_settings = bf2s_settings();
	$bf2s_settings['lastupdatecheck'] = time();
	update_option('bf2s_settings', $bf2s_settings);
}


// This function checks to see if we've saved that there's a new version and then if there is, keeps displaying a notice about it
$wp_bf2s_notice_displayed = FALSE;
function bf2s_displayupdate() {
	// If the user can't activate plugins, don't display the update message
	if (function_exists('current_user_can')) {
		if (!current_user_can('activate_plugins')) return;
	} else {
		get_currentuserinfo();
		if ($user_level < 8) return;
	}

	global $wp_bf2s_notice_displayed;

	// If we've already displayed the message on this page, don't do it again
	if ($wp_bf2s_notice_displayed == TRUE) return;

	// Otherwise mark that we're about to display the message
	$wp_bf2s_notice_displayed = TRUE;

	// Grab the data about the update
	$globalnotice = get_option('bf2s_globalnotice');

	// If we don't have a message to display, then no need to go any further
	if (!is_array($globalnotice)) return;

	// Or if it's just a minor update, only display the update message on the options page
	if ($globalnotice['minorupdate'] == 'yes' && $_GET['page'] != basename(__FILE__)) return;

	echo "\n" . '<div class="error"><p><span class="bf2s_hide"><a href="' . htmlspecialchars(add_query_arg('hidebf2supdate', '1'), ENT_QUOTES) . '"><img src="../wp-content/plugins/wp_bf2s/images/close.gif" alt="Close" title="' . __('Hide this message for at least 24 hours', 'wp_bf2s') . '" width="10" height="10" /></a></span>' . sprintf(__("A new version (v%s) of the Battlefield 2 Stats plugin is out! It's recommended that you download <a href='%s'>the latest version</a> now to make use of the new features and/or bug fixes. New in this version: <em>%s</em>", 'wp_bf2s'), $globalnotice['version'], 'http://www.viper007bond.com/wordpress-plugins/battlefield-2-stats/', $globalnotice['description']) . "</p></div>\n";
}


// Now the options page
function bf2s_optionspage() {
	global $bf2s_message, $bf2s_log, $bf2s_debug;

	// If there's a new version out and we know about it and have a message to display, this function will display it
	bf2s_displayupdate();

	// Get the current settings
	$bf2s_settings = bf2s_settings();

	if ($bf2s_message) echo "\n" . '<div id="message" class="updated fade">' . $bf2s_message . "</div>\n";

	?>

<div class="wrap">
	<h2><?php _e('Battlefield 2 Stats Configuration', 'wp_bf2s'); ?></h2>

	<form name="bf2s_config" method="post" action="">

	<fieldset class="options">
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
			<tr valign="top">
				<th width="40%" scope="row">
					<label for="pid"><?php _e('Your PID:', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="pid" type="text" id="pid" size="50" style="width: 60%;" value="<?php echo htmlspecialchars($bf2s_settings['pid'], ENT_QUOTES); ?>" />
					<input name="pid_current" type="hidden" id="pid_current" size="50" value="<?php echo htmlspecialchars($bf2s_settings['pid'], ENT_QUOTES); ?>" />
					<br /><?php _e('You <strong>cannot</strong> use a playername! Valid example:', 'wp_bf2s'); ?> <code>44260977</code><br />
					<?php _e('If you do not know your PID, you can find it via', 'wp_bf2s'); ?> <a href="http://bf2s.com/">BF2S.com</a>.
				</td>
			</tr>
		</table>
	</fieldset>
	<fieldset class="options">
		<legend><?php _e('Display Options', 'wp_bf2s'); ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
			<tr valign="top">
				<th width="40%" scope="row">
					<label for="display_nick_link"><?php _e('Display player name?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_nick_link" type="checkbox" id="display_nick_link" <?php if ($bf2s_settings['display_nick_link'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th width="40%" scope="row">
					<label for="display_rank_icon"><?php _e('Display rank icon?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_rank_icon" type="checkbox" id="display_rank_icon" <?php if ($bf2s_settings['display_rank_icon'] == 'on') echo 'checked="checked" '; ?>/>&nbsp;(<?php _e('Requires player name to be displayed', 'wp_bf2s'); ?>)
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_score"><?php _e('Display global score?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_score" type="checkbox" id="display_score" <?php if ($bf2s_settings['display_score'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_time"><?php _e('Display play time?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_time" type="checkbox" id="display_time" <?php if ($bf2s_settings['display_time'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_time_units"><?php _e('Display play time units?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_time_units" type="checkbox" id="display_time_units" <?php if ($bf2s_settings['display_time_units'] == 'on') echo 'checked="checked" '; ?>/>&nbsp;(<?php echo __('Example:', 'wp_bf2s') . ' ' . wp_bf2s_secstohms(849964, 'on'); ?>)
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_spm"><?php _e('Display score per minute?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_spm" type="checkbox" id="display_spm" <?php if ($bf2s_settings['display_spm'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_kills"><?php _e('Display kills?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_kills" type="checkbox" id="display_kills" <?php if ($bf2s_settings['display_kills'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_deaths"><?php _e('Display deaths?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_deaths" type="checkbox" id="display_deaths" <?php if ($bf2s_settings['display_deaths'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_kdr"><?php _e('Display kill/death ratio?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_kdr" type="checkbox" id="display_kdr" <?php if ($bf2s_settings['display_kdr'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_wins"><?php _e('Display wins?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_wins" type="checkbox" id="display_wins" <?php if ($bf2s_settings['display_wins'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_losses"><?php _e('Display losses?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_losses" type="checkbox" id="display_losses" <?php if ($bf2s_settings['display_losses'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_wlr"><?php _e('Display win/loss ratio?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_wlr" type="checkbox" id="display_wlr" <?php if ($bf2s_settings['display_wlr'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_rank_percent"><?php _e('Display percent to next rank?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="display_rank_percent" type="checkbox" id="display_rank_percent" <?php if ($bf2s_settings['display_rank_percent'] == 'on') echo 'checked="checked" '; ?>/>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="display_percent_type"><?php _e('Percentage type:', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<select name="display_percent_type" id="display_percent_type">
						<option value="difference"<?php if ($bf2s_settings['display_percent_type'] === 'difference') echo ' selected="selected"'; ?>>Rank difference</option>
						<option value="overall"<?php if ($bf2s_settings['display_percent_type'] === 'overall') echo ' selected="selected"'; ?>>Overall points</option>
					</select>
					<br /><?php echo sprintf(__("See <a href='%s'>FAQ</a> for type descriptions.", 'wp_bf2s'), 'http://www.viper007bond.com/wordpress-plugins/battlefield-2-stats/#faq'); ?>
				</td>
			</tr>
		</table>
	</fieldset>
	<fieldset class="options">
		<legend><?php _e('Other Options', 'wp_bf2s'); ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
			<tr valign="top">
				<th width="40%" scope="row">
					<label for="check_for_updates"><?php _e('Check for plugin updates periodically?', 'wp_bf2s'); ?></label>
				</th>
				<td>
					<input name="check_for_updates" type="checkbox" id="check_for_updates" <?php if ($bf2s_settings['check_for_updates'] == 'on') echo 'checked="checked" '; ?>/>&nbsp;<?php _e('If this box is checked, this plugin will connect to Viper007Bond.com and check for a new version from time to time. Note that some stats (like WordPress version and plugin version) will be sent along too in order to better help me improve this plugin.', 'wp_bf2s'); ?>
				</td>
			</tr>
		</table>
	</fieldset>

	<p class="submit">
		<input type="submit" name="saveplaceholder" value="<?php _e('Update Options', 'wp_bf2s') ?> &raquo;" style="display: none;" /><!-- This is so that pressing enter in the PID box doesn't reset to defaults -->
		<input type="submit" name="defaults" value="&laquo; <?php _e('Reset to Defaults', 'wp_bf2s'); ?>" style="float: left;" />
		<input type="submit" name="save" value="<?php _e('Update Options', 'wp_bf2s') ?> &raquo;" />
	</p>

	</form>
</div>

<div class="wrap">
	<h2><?php _e('Test Output &amp; Debugging', 'wp_bf2s'); ?></h2>

	<p><?php
			_e("Here is what your output will look like, along with the plugin's log and any error messages.", 'wp_bf2s');
			if (!$_GET['debug']) echo ' ' . sprintf(__('<a href="%s">Click here</a> to see the raw plugin data.', 'wp_bf2s'), htmlspecialchars(add_query_arg('debug', 1), ENT_QUOTES) . '#rawoutput');
	?></p>

	<fieldset class="options" style="margin: 0 auto; width: 250px;">
<?php wp_bf2s(); ?>

	</fieldset>
	<fieldset class="options" style="margin-top: 50px;">
		<legend><?php _e('Debug Output', 'wp_bf2s'); ?></legend>

<?php
	if (is_array($bf2s_debug)) {
		echo "		<ol>\n";
		foreach ($bf2s_debug as $item) {
			echo "			<li>" . $item . "</li>\n";
		}
		echo "		</ol>\n";
	} else {
		_e('Odd, the debug log was empty!', 'wp_bf2s');
	}

	if ($_GET['debug']) {
		echo "		<pre id='rawoutput'>\n\n\nSettings = ";
		print_r($bf2s_settings);
		echo "\nPlayer Data = ";
		print_r(wp_bf2s_getdata($bf2s_settings['pid']));
		echo "</pre>\n";
	}
?>
	</fieldset>
	<fieldset class="options" style="margin-top: 25px;">
		<legend><?php _e('Log Output', 'wp_bf2s'); ?></legend>

<?php
	$log_from_db = get_option('bf2s_log');

	echo "		<ol style='list-style: none;'>\n";
	if (is_array($log_from_db)) {
		echo '		<p>' . sprintf(__('This is a list of the last 25 things of importance to occur in relation to this plugin. To clear this list, <a href="%s">click here</a>.', 'wp_bf2s'), htmlspecialchars(add_query_arg('clearlog', 1), ENT_QUOTES)) . "</p>\n\n";
		foreach ($log_from_db as $item) {
			echo '			<li><strong>[' . date('M d H:i:s', ($item[0] - date('Z')) + (get_option('gmt_offset') * 3600)) . ']</strong>&nbsp;&nbsp;' . $item[1] . "</li>\n";
		}
	} else {
		echo '			<li>' . __('Nothing to report yet! :)', 'wp_bf2s') . "</li>\n";
	}
	echo "		</ol>\n";
?>

	</fieldset>
</div>

<?php
} // End options page


// The template function
function wp_bf2s($echo = TRUE) {
	global $bf2s_debug, $wp_bf2s_version;

	// Get the current settings
	$bf2s_settings = bf2s_settings();
	
	// Make sure we have a valid PID
	if ($bf2s_settings['pid'] == '0') {
		$bf2s_debug[] = __('The current PID is 0 which is an invalid PID. Please enter a valid PID.', 'wp_bf2s');
		$output .= __('<strong>Error:</strong> Please enter a valid BF2 PID.', 'wp_bf2s');
		if ($echo === TRUE) echo $output; else return $output;
	} else {
		// Query our PID and it's data
		$results = wp_bf2s_getdata($bf2s_settings['pid']);

		// Make sure we got a result
		if (!$results['PID']) {
			if ($_GET['page'] == basename(__FILE__)) {
				$output .= __('<strong>Error:</strong> No results were returned. See below for details.', 'wp_bf2s');
			} else {
				$output .= __("<strong>Error:</strong> No results were returned. Site owner: see plugin's options page.", 'wp_bf2s');
			}
		} else {
			// Create some statistics
			$results['SPM'] = round($results['SCORE'] / ($results['TIME'] / 60), 2); // Score per Minute
			$results['KDR'] = ($results['DEATHS'] != 0) ? round($results['KILLS'] / $results['DEATHS'], 2) : __('N/A', 'wp_bf2s'); // Kill / Death Ratio
			$results['WLR'] = ($results['LOSSES'] != 0) ? round($results['WINS'] / $results['LOSSES'], 2) : __('N/A', 'wp_bf2s'); // Win / Loss Ratio
			$results['WINPERCENT'] = ($results['WINS'] + $results['LOSSES'] != 0) ? round(($results['WINS'] / ($results['WINS'] + $results['LOSSES'])) * 100, 1) : __('N/A', 'wp_bf2s');
			$results['LOSSPERCENT'] = 100 - $results['WINPERCENT']; // Doing it this way rather than division again so that we always get 100% as a total

			// All of the ranks and the points needed to get them according to http://ubar.bf2s.com/ranks.php at the time of the release of this plugin
			$ranks = array(
				0	=>	array(
							'name'		=> __('Private', 'wp_bf2s'),
							'reqscore'	=> 0,
							'nextrank'	=> 1,
						),
				1	=>	array(
							'name'		=> __('Private First Class', 'wp_bf2s'),
							'reqscore'	=> 150,
							'nextrank'	=> 2,
						),
				2	=>	array(
							'name'		=> __('Lance Corporal', 'wp_bf2s'),
							'reqscore'	=> 500,
							'nextrank'	=> 3,
						),
				3	=>	array(
							'name'		=> __('Corporal', 'wp_bf2s'),
							'reqscore'	=> 800,
							'nextrank'	=> 4,
						),
				4	=>	array(
							'name'		=> __('Sergeant', 'wp_bf2s'),
							'reqscore'	=> 2500,
							'nextrank'	=> 5,
						),
				5	=>	array(
							'name'		=> __('Staff Sergeant', 'wp_bf2s'),
							'reqscore'	=> 5000,
							'nextrank'	=> 6,
						),
				6	=>	array(
							'name'		=> __('Gunnery Sergeant', 'wp_bf2s'),
							'reqscore'	=> 8000,
							'nextrank'	=> 7,
						),
				7	=>	array(
							'name'		=> __('Master Sergeant', 'wp_bf2s'),
							'reqscore'	=> 20000,
							'nextrank'	=> 9,
						),
				8	=>	array(
							'name'		=>	__('First Sergeant', 'wp_bf2s'),
							'reqscore'	=> 20000,
							'nextrank'	=> 9,
						),
				9	=>	array(
							'name'		=> __('Master Gunnery Sergeant', 'wp_bf2s'),
							'reqscore'	=> 50000,
							'nextrank'	=> 12,
						),
				10	=>	array(
							'name'		=> __('Sergeant Major', 'wp_bf2s'),
							'reqscore'	=> 50000,
							'nextrank'	=> 12,
						),
				11	=>	array(
							'name'		=> __('Sergeant Major of the Corps', 'wp_bf2s'),
							'reqscore'	=> 50000,
							'nextrank'	=> 12,
						),
				12	=>	array(
							'name'		=> __('2nd Lieutenant', 'wp_bf2s'),
							'reqscore'	=> 60000,
							'nextrank'	=> 13,
						),
				13	=>	array(
							'name'		=> __('1st Lieutenant', 'wp_bf2s'),
							'reqscore'	=> 75000,
							'nextrank'	=> 14,
						),
				14	=>	array(
							'name'		=> __('Captain', 'wp_bf2s'),
							'reqscore'	=> 90000,
							'nextrank'	=> 15,
						),
				15	=>	array(
							'name'		=> __('Major', 'wp_bf2s'),
							'reqscore'	=> 115000,
							'nextrank'	=> 16,
						),
				16	=>	array(
							'name'		=> __('Lieutenant Colonel', 'wp_bf2s'),
							'reqscore'	=> 125000,
							'nextrank'	=> 17,
						),
				17	=>	array(
							'name'		=> __('Colonel', 'wp_bf2s'),
							'reqscore'	=> 150000,
							'nextrank'	=> 18,
						),
				18	=>	array(
							'name'		=> __('Brigadier General', 'wp_bf2s'),
							'reqscore'	=> 180000,
							'nextrank'	=> 20,
						),
				19	=>	array(
							'name'		=> __('Major General', 'wp_bf2s'),
							'reqscore'	=> 180000,
							'nextrank'	=> 20,
						),
				20	=>	array(
							'name'		=> __('Lieutenant General', 'wp_bf2s'),
							'reqscore'	=> 200000,
						),
				21	=>	array(
							'name'		=> __('General', 'wp_bf2s'),
							'reqscore'	=> 200000,
						),
			);

			// Now we start outputting
			$output .= "\n<!-- Battlefield 2 Stats v" . $wp_bf2s_version . " | http://www.viper007bond.com/wordpress-plugins/battlefield-2-stats/ -->\n";
			$output .= '<ul id="wp_bf2s">' . "\n";

			if ($bf2s_settings['display_nick_link'] == 'on') {
				$output .= '	<li class="wp_bf2s_name"><a href="' . $results['LINK'] . '" title="' . __('View full player statistics', 'wp_bf2s') . '">';
				
				if ($bf2s_settings['display_rank_icon'] == 'on' && $results['RANK'] != 0 && $results['RANK'] < count($ranks) - 1)
					$output .= "<img src='" . get_bloginfo('wpurl') . "/wp-content/plugins/wp_bf2s/images/rank_" . $results['RANK'] . ".gif' alt='" . $ranks[$results['RANK']]['name'] . "' title='" . $ranks[$results['RANK']]['name'] . "' width='16' height='16' class='wp_bf2s_rankicon' style='border: none;' />";
				
				$output .= $results['NICK'] . "</a></li>\n";
			}

			if ($bf2s_settings['display_score'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_score">' . __('Global Score:', 'wp_bf2s') . ' ' . number_format($results['SCORE'], 0, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "</li>\n";

			if ($bf2s_settings['display_time'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_time">' . __('Playtime:', 'wp_bf2s') . ' ' . wp_bf2s_secstohms($results['TIME'], $bf2s_settings['display_time_units']) . "</li>\n";

			if ($bf2s_settings['display_spm'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_spm">' . __('Score per Minute:', 'wp_bf2s') . ' ' . number_format($results['SPM'], 2, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "</li>\n";

			if ($bf2s_settings['display_kills'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_kills">' . __('Kills:', 'wp_bf2s') . ' ' . number_format($results['KILLS'], 0, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "</li>\n";

			if ($bf2s_settings['display_deaths'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_deaths">' . __('Deaths:', 'wp_bf2s') . ' ' . number_format($results['DEATHS'], 0, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "</li>\n";

			if ($bf2s_settings['display_kdr'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_kdr">' . __('Kill/Death Ratio:', 'wp_bf2s') . ' ' . number_format($results['KDR'], 2, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "</li>\n";

			if ($bf2s_settings['display_wins'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_wins">' . __('Wins:', 'wp_bf2s') . ' ' . number_format($results['WINS'], 0, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . ' (' . number_format($results['WINPERCENT'], 1, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "%)</li>\n";

			if ($bf2s_settings['display_losses'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_losses">' . __('Losses:', 'wp_bf2s') . ' ' . number_format($results['LOSSES'], 0, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . ' (' . number_format($results['LOSSPERCENT'], 1, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "%)</li>\n";

			if ($bf2s_settings['display_wlr'] == 'on')
				$output .= '	<li class="wp_bf2s_detail display_wlr">' . __('Win/Loss Ratio:', 'wp_bf2s') . ' ' . number_format($results['WLR'], 2, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . "</li>\n";

			if ($bf2s_settings['display_rank_percent'] == 'on' && $ranks[$results['RANK']]['nextrank']) {
				// If we're over 100% to the next rank (i.e. we should have the next rank, but EA just hasn't updated our rank yet), just set it to 100%
				if ($results['SCORE'] > $ranks[$ranks[$results['RANK']]['nextrank']]['reqscore']) $percent = '100';
				elseif ($results['SCORE'] == $ranks[$results['RANK']]['reqscore']) $percent = '0';
				else {
					if ($bf2s_settings['display_percent_type'] == 'overall') {
						$percent = round(($results['SCORE'] / $ranks[$ranks[$results['RANK']]['nextrank']]['reqscore']) * 100, 2);
					} else {
						$percent = round((($results['SCORE'] - $ranks[$results['RANK']]['reqscore']) / ($ranks[$ranks[$results['RANK']]['nextrank']]['reqscore'] - $ranks[$results['RANK']]['reqscore'])) * 100, 2);
	
						// Isn't math fun? :rolleyes:
					}
				}

				$output .= '	<li class="wp_bf2s_detail display_rank_percent">' . number_format($percent, 2, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . '% ' . __('to', 'wp_bf2s') . ' <span class="bf2s_nextrank" title="' . $ranks[$ranks[$results['RANK']]['nextrank']]['name'] . '">' . __('the next rank', 'wp_bf2s') . '</span></li>' . "\n";
			}

			$output .= "	<li class='wp_bf2s_detail thanks_bf2s'>" . sprintf(__('Stats thanks to <a href="%s" title="A great Battlefield 2 stats website">BF2S.com</a>', 'wp_bf2s'), 'http://bf2s.com/') . "</li>\n";
			$output .= "</ul>";
		}

		if ($echo === TRUE) {
			echo $output;
		} else {
			return $output;
		}
	}
} // End wp_bf2s()

// Just for simplicity and clean code purposes, we'll keep the query code in it's own function
function wp_bf2s_getdata($pid) {
	global $bf2s_log, $bf2s_debug;

	// Let's see if we have any existing data
	$data = get_option('bf2s_data');

	// If we already have some data in the database, let's check it out
	if (isset($data['PID'])) {
		$bf2s_debug[] = __('Found previous data in the database.', 'wp_bf2s');

		$data_age = time() - $data['UPDATED'];

		// If the data is under 6 hours old, let's use it!
		if ($data_age < 21600) {
			$bf2s_debug[] = sprintf(__("The data from the database is only %s old, which is newer than 6 hours. Therefore, we'll use it!", 'wp_bf2s'), wp_bf2s_secstohms($data_age));
			return $data;
		} else {
			$bf2s_debug[] = sprintf(__("The data from the database is %s old, which is older than 6 hours. Therefore, we need to attempt to get fresher data.", 'wp_bf2s'), wp_bf2s_secstohms($data_age));
		}
	} else {
		$bf2s_debug[] = __('No data was found in the database, so we need to get some data.', 'wp_bf2s');
	}

	# If we're still here, then that must mean we need to refresh our data!

	// Minutes to wait after an error
	$error_delay = 15;
	
	// If we had a data fetch failure less than the value above minutes ago, don't try again yet
	// This is to make it so that we don't keep trying on every single page load to get new data if BF2S.com is down
	if (isset($data['FAILED']) && $data['FAILED'] > time() - ($error_delay * 60)) {
		$bf2s_debug[] = sprintf(__("Aborting the fetching of new data. We had a fetch data failure only %s ago (see the log for the error message). Since we failed so recently, we'll wait until it's been at least %s minutes since we failed before trying again. This to avoid hammering BF2S.com and to avoid long page generation times here.", 'wp_bf2s'), wp_bf2s_secstohms(time() - $data['FAILED']), $error_delay);

		if ($data['PID']) $bf2s_debug[] = __('Since we already have some old data to use, falling back to that.', 'wp_bf2s');
		return $data;
	}

	// Create the fetch URL
	$url = 'http://bf2s.com/xml.php?pids=' . $pid;
	$bf2s_debug[] = __('Attempting to fetch XML document from', 'wp_bf2s') . ' <code>' . $url . '</code>';

	// And now we connect to the server and get the URL we requested
	$feed_contents = wp_bf2s_fetch_url($url);
	
	## Now we figure out what we got back from wp_bf2s_fetch_url()

	// If the function failed..
	if ($feed_contents === FALSE) {
		// Record that we had a failure
		$data['FAILED'] = time();
		update_option('bf2s_data', $data);

		if ($data['PID']) $bf2s_debug[] = __('Since we already have some old data to use, falling back to that.', 'wp_bf2s');
		return $data;
	}
	
	// Or if it worked, but got a blank result
	elseif (empty($feed_contents)) {
		$bf2s_debug[] = sprintf(__("Sucessfully read the XML document, but it was blank. The most likely reason is that your PID is invalid, is in the queue, has too few points, etc. See <a href='%s'>the BF2S.com FAQ</a> for possible details.", 'wp_bf2s'), 'http://bf2s.com/faq.php');

		if ($data['PID']) $bf2s_debug[] = __('Since we already have some old data to use, falling back to that.', 'wp_bf2s');
		return $data;
	}
	
	// If it worked, but BF2S.com says we're banned
	elseif (stristr($feed_contents, 'auto block enabled') !== FALSE) {
		$error = __("Failed to fetch the XML document. This server's IP address has been temporarily banned from the feed due to exceeding the 3 queries per 6 hours limit. This is either a result of you changing your PID too many times over a short period of time or someone else on this server also accessing the feed and combined, you hit the limit. Sorry, but there's nothing this script can do now but wait. Just give it some time and BF2S.com should allow you to fetch new data in a few hours.", 'wp_bf2s');

		$bf2s_debug[] = $error;

		if ($data['PID']) $bf2s_debug[] = __('Since we already have some old data to use, falling back to that.', 'wp_bf2s');
		return $data;
	}
	
	// All must be good, so let's parse and such! :)
	else {
		$bf2s_debug[] = __('Sucessfully read the XML document, attempting to parse...', 'wp_bf2s');

		// Parse the XML document, code stolen from Jeff Minard as I suck at using these functions
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		$parse_status = xml_parse_into_struct($parser, $feed_contents, $values);
		xml_parser_free($parser);

		if ($parse_status === 0) {
			$bf2s_debug[] = __('Failed to parse the XML document that was fetched.', 'wp_bf2s');

			// Record that we had a failure
			$data['FAILED'] = time();
			update_option('bf2s_data', $data);

			if ($data['PID']) $bf2s_debug[] = __('Since we already have some old data to use, falling back to that.', 'wp_bf2s');
			return $data;
		} else {
			// Create our new data array
			$newdata = array();
			$newdata['UPDATED'] = time(); // Record when we gathered the data so we know when to expire it
			$newdata = array_merge($newdata, $values[1]['attributes']);

			// Now let's check to see if we got any results from the XML feed
			if ($newdata['NICK']) {
				$bf2s_debug[] = __('Sucessfully parsed the XML document that was fetched.', 'wp_bf2s');

				// Save our data
				if (update_option('bf2s_data', $newdata)) $bf2s_debug[] = __('Sucessfully saved new data array to the database.', 'wp_bf2s');
				else {
					$error = __('Saving of the new data array to the database failed for some unknown reason!', 'wp_bf2s');

					$bf2s_log[] = array(time(), $error);
					$bf2s_debug[] = $error;

					// Save the new log entry to the database
					wp_bf2s_update_log();
				}

				// And return it
				return $newdata;
			} else {
				$bf2s_debug[] = __('Failed to parse the XML document that was fetched.', 'wp_bf2s');

				// Record that we had a failure
				$data['FAILED'] = time();
				update_option('bf2s_data', $data);

				if ($data['PID']) $bf2s_debug[] = __('Since we already have some old data to use, falling back to that.', 'wp_bf2s');
				return $data;
			}
		}
	}
}

// This is a cleaned up copy of fgc() from Jeff Minard's API, a replacement for file_get_contents
// It's in it's own function for code organization
function wp_bf2s_fetch_url($url) {
	global $bf2s_log, $bf2s_debug;

	// Parse the URL into it's compontents
	$destination = parse_url($url);

	// If no port was set, use 80
	if(!$destination['port'] && $destination['scheme'] == 'http') $destination['port'] = 80;
	
	// Open a socket connection to the server
	$fp = @fsockopen($destination['host'], $destination['port'], $error_number, $error_string, 5);
	if (!$fp) {
		$error = sprintf(__('Failed to connect to %s. The error / error number was:', 'wp_bf2s'), $destination['host'] . ":" . $destination['port']) . " $error_string ($error_number)";

		$bf2s_log[] = array(time(), $error);
		$bf2s_debug[] = $error;

		// Save the new log entry to the database
		wp_bf2s_update_log();

		return FALSE;
	} else {
		stream_set_timeout($fp, 10);

		$get = $destination['path'];
		if ($destination['query']) $get .= "?" . $destination['query'];
		
		$out  = "GET " . $get . " HTTP/1.0\r\n";
		$out .= "Host: " . $destination['host'] . "\r\n";
		$out .= "Connection: Close\r\n\r\n";
		
		$start = time();
		
		fwrite($fp, $out);
		while (!feof($fp)) {
			$result .= fgets($fp, 2048);
			if (time() - $start > 10) break; // too many seconds passed -- the hard way, damnit.
		}
		fclose($fp);

		$result = trim(strstr(trim(str_replace("\r", '', $result)), "\n\n"));
	}

	return $result;
}

// This function converts seconds to hours:minutes:seconds
function wp_bf2s_secstohms($orig_seconds, $units = 'on') {
	if ($orig_seconds >= 3600) $hours = floor($orig_seconds / 3600);
	else $hours = 0;

	$minutes = sprintf("%02d", floor(($orig_seconds - ($hours * 3600)) / 60));
	$seconds = sprintf("%02d", floor(($orig_seconds - ($hours * 3600) - ($minutes * 60))));

	if ($units == 'on') {
		if ($hours != 0)					$output .= number_format($hours, 0, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . __('h', 'wp_bf2s') . ':';
		if ($hours != 0 || $minutes != 0)	$output .= $minutes . __('m', 'wp_bf2s') . ':';
											$output .= $seconds . __('s', 'wp_bf2s');
	} else {
		if ($hours != 0)					$output .= number_format($hours, 0, __('.', 'wp_bf2s'), __(',', 'wp_bf2s')) . ':';
		if ($hours != 0 || $minutes != 0)	$output .= $minutes . ':';
											$output .= $seconds;
	}

	return $output;
}

// This saves any new log entries when called
function wp_bf2s_update_log() {
	global $bf2s_log;

	if (!$bf2s_log) return;

	// Reverse the order of the new items (we want newest first)
	$bf2s_log = array_reverse($bf2s_log);

	// Get the current log
	$old_log = get_option('bf2s_log');

	// If we already had a log in the database, merge in the new data
	if (is_array($old_log)) $bf2s_log = array_merge($bf2s_log, $old_log);

	// Trim items off the end of the array (i.e. the oldest stuff)
	$bf2s_log = array_slice($bf2s_log, 0, 25);

	// Save the new log
	update_option('bf2s_log', $bf2s_log);

	// Now clear the log variable
	unset($bf2s_log);
}


# These functions create a widget for the WordPress plugin "WP-Dash", a dashboard replacement
# See the plugin's site for more details: http://somethingunpredictable.com/wp-dash/
function wp_bf2s_wpdash_widget_css($ID) {
	return default_widget_css($ID) . "
		#widget$ID {
			width: 180px;
		}
		#content$ID {
			width: 179px;
			overflow: auto;
		}
		#content$ID ul {
			padding: 0 0 0 15px;
			margin: 0;
		}
		#content$ID li {
			padding: 1px 0 2px 0;
			margin: 0;
		}
		";
}
// This outputs the main content of the widget
function wp_bf2s_wpdash_widget_content() {
	return wp_bf2s(FALSE);
}
// This gives the widget to WP-Dash
function wp_bf2s_wpdash_widget_available() {
	if (function_exists('make_widget_available'))
		make_widget_available(__('Battlefield 2 Stats', 'wp_bf2s'), __('Displays your Battlefield 2 stats.', 'wp_bf2s'), 'wp_bf2s_wpdash_widget_');
}
add_action('init', 'wp_bf2s_wpdash_widget_available');


# These functions create a widget for the sidebar control plugin, WordPress widgets
# See the plugin's site for more details: http://automattic.com/code/widgets/
function wp_bf2s_wpw_widget_init() {
	if (!function_exists('register_sidebar_widget')) return;

	function wp_bf2s_wpw_widget($args) {
		extract($args);

		$options = get_option('widget_bf2s');

		echo $before_widget;
		if ($options['title']) echo $before_title . $options['title'] . $after_title;

		wp_bf2s();
		
		echo $after_widget;
	}

	function wp_bf2s_wpw_widget_control() {

		$options = get_option('widget_bf2s');
		if (!is_array($options)) {
			$options = array('title' => __('My BF2 Stats', 'wp_bf2s'));
			update_option('widget_bf2s', $options);
		}

		if ($_POST['bf2s-submit']) {
			$options['title'] = strip_tags(stripslashes($_POST['bf2s-title']));
			update_option('widget_bf2s', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);

		?>

			<p style="text-align: center;">
				<label for="bf2s-title">Title: <input style="width: 200px; padding-left: 3px; padding-right: 3px;" id="bf2s-title" name="bf2s-title" type="text" value="<?php echo $title; ?>" /></label>
			</p>
			<p style="text-align: center; margin-top: 15px;">
				<?php echo sprintf(__("To configure the output of your actual stats,<br />please visit the plugin's <a href='%s'>options page</a>.", 'wp_bf2s'), 'options-general.php?page=wp_bf2s.php'); ?>
			</p>
			<input type="hidden" id="bf2s-submit" name="bf2s-submit" value="1" />
<?php
	}

	register_sidebar_widget(__('Battlefield 2 Stats', 'wp_bf2s'), 'wp_bf2s_wpw_widget');
	register_widget_control(__('Battlefield 2 Stats', 'wp_bf2s'), 'wp_bf2s_wpw_widget_control', 300, 100);
}
add_action('plugins_loaded', 'wp_bf2s_wpw_widget_init');

?>