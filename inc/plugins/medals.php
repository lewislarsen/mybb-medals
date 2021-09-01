<?php
ini_set('display_errors', 1);

require_once(MYBB_ROOT . 'inc/functions_medals.php');

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

// cache templates - this is important when it comes to performance
// THIS_SCRIPT is defined by some of the MyBB scripts, including index.php
if (defined('THIS_SCRIPT'))
{
	global $templatelist;

	if (isset($templatelist))
	{
		$templatelist .= ',';
	}

	if (THIS_SCRIPT == 'member.php')
	{
		$templatelist .= 'medal_member_profile_medals,medal_member_profile_medals_row,';
	}

	if (THIS_SCRIPT == 'medals.php')
	{
		$templatelist = 'medal_page_row,medal_page_row_none,medal_page_view';
	}

	if (THIS_SCRIPT == 'showthread.php')
	{
		$templatelist .= 'medal_postbit';
	}

	if (THIS_SCRIPT == 'usercp.php')
	{
		global $templatelist;
		if (isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'medal_usercp_menu,medal_usercp_favoritemedals,medal_favorite_row';
	}
}

global $plugins;

$plugins->add_hook('admin_user_menu', 'medals_admin_user_menu');
$plugins->add_hook('admin_user_action_handler', 'medals_admin_user_action_handler');

$plugins->add_hook("postbit", "medals_postbit");
$plugins->add_hook("postbit_pm", "medals_postbit");
$plugins->add_hook("postbit_announcement", "medals_postbit");
$plugins->add_hook("postbit_prev", "medals_postbit");

$plugins->add_hook("member_profile_end", "medals_profile");

$plugins->add_hook('fetch_wol_activity_end', 'medals_fetch_wol_activity_end');
$plugins->add_hook('build_friendly_wol_location_end', 'medals_build_friendly_wol_location_end');

$plugins->add_hook("datahandler_user_delete_content", "medal_user_delete");

$plugins->add_hook("admin_formcontainer_end", "medals_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "medals_usergroup_permission_commit");

$plugins->add_hook("usercp_start", "medals_usercp");
$plugins->add_hook('usercp_menu', 'medals_usercp_menu', 40);

$plugins->add_hook("admin_tools_cache_rebuild", "medals_admin_tools_cache_rebuild");

if (defined('IN_ADMINCP'))
{
	// Add our medal_settings() function to the setting management module to load language strings.
	$plugins->add_hook('admin_config_settings_manage', 'medal_settings');
	$plugins->add_hook('admin_config_settings_change', 'medal_settings');
	$plugins->add_hook('admin_config_settings_start', 'medal_settings');
	// We could hook at 'admin_config_settings_begin' only for simplicity sake.

	$plugins->add_hook("admin_tools_adminlog_begin", "medals_admin_tools_adminlog_begin");
	$plugins->add_hook("admin_tools_get_admin_log_action", "medals_admin_tools_get_admin_log_action");
}

function medals_info()
{
	return array(
		"name"          => "Medals",
		"description"   => "A medal system for MyBB.",
		"website"       => "",
		"author"        => "Lewis Larsen",
		"authorsite"    => "https://lewislarsen.codes",
		"version"       => "1.3",
		"guid"          => "",
		"codename"      => "medals",
		"compatibility" => "*",
	);
}

function medals_install()
{
	global $db;

	$db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "medals` (
      medal_id int(10) UNSIGNED NOT NULL auto_increment,
	  medal_name varchar(255) NULL,
	  medal_description varchar(255) NULL,
	  medal_image_path varchar(255) NULL,
	  admin_user_id int(10) UNSIGNED NOT NULL DEFAULT '0',
	  created_at int(10) UNSIGNED NOT NULL DEFAULT '0',
      PRIMARY KEY  (`medal_id`)
    ) ENGINE=MyISAM  
      COLLATE=utf8_general_ci
	  DEFAULT CHARSET=utf8;
     ");

	$db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "medals_user` (
      medal_user_id int(10) UNSIGNED NOT NULL auto_increment,
      medal_id int(10) UNSIGNED NOT NULL DEFAULT '0',
      user_id int(10) UNSIGNED NOT NULL DEFAULT '0',
      admin_user_id int(10) UNSIGNED NOT NULL DEFAULT '0',
      reason varchar(255) NULL,
	  created_at int(10) UNSIGNED NOT NULL DEFAULT '0',
      PRIMARY KEY  (`medal_user_id`)
    ) ENGINE=MyISAM  
      COLLATE=utf8_general_ci
	  DEFAULT CHARSET=utf8;
     ");

	$db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "medals_user_favorite` (
      medals_user_favorite_id int(10) UNSIGNED NOT NULL auto_increment,
      medal_id int(10) UNSIGNED NOT NULL DEFAULT '0',
      user_id int(10) UNSIGNED NOT NULL DEFAULT '0',
	  updated_at int(10) UNSIGNED NOT NULL DEFAULT '0',
      PRIMARY KEY  (`medals_user_favorite_id`)
    ) ENGINE=MyISAM  
      COLLATE=utf8_general_ci
	  DEFAULT CHARSET=utf8;
     ");

	$db->add_column("usergroups", "canmanagefavoritemedals", "tinyint(1) NOT NULL default '1'");

	// Indexes to help make searching faster for the tables
	$db->write_query("CREATE INDEX IDX_ADM_ID ON " . TABLE_PREFIX . "medals (admin_user_id);");
	$db->write_query("CREATE INDEX IDX_USER_ID ON " . TABLE_PREFIX . "medals_user (user_id);");
	$db->write_query("CREATE INDEX IDX_USER_ID ON " . TABLE_PREFIX . "medals_user_favorite (user_id);");
}

function medals_is_installed()
{
	global $db;
	if ($db->table_exists("medals"))
	{
		return true;
	}

	return false;
}

function medals_uninstall()
{
	global $db;

	$db->write_query("DROP TABLE " . TABLE_PREFIX . "medals");
	$db->write_query("DROP TABLE " . TABLE_PREFIX . "medals_user");
	$db->write_query("DROP TABLE " . TABLE_PREFIX . "medals_user_favorite");

	$db->delete_query("templategroups", "prefix IN('medal')");
	$db->delete_query("templates", "title IN('medal_member_profile_medals')");
	$db->delete_query("templates", "title IN('medal_member_profile_medals_row')");
	$db->delete_query("templates", "title IN('medal_postbit')");
	$db->delete_query("templates", "title IN('medal_favorite_no_medals')");
	$db->delete_query("templates", "title IN('medal_favorite_row')");
	$db->delete_query("templates", "title IN('medal_usercp_favoritemedals')");
	$db->delete_query("templates", "title IN('medal_usercp_menu')");
	$db->delete_query('settinggroups', "name='medal'");
	$db->delete_query('settings', "name IN ('medal_postbit_count','medal_profile_count')");
	rebuild_settings();

	if ($db->field_exists("canmanagefavoritemedals", "usergroups"))
	{
		$db->drop_column("usergroups", "canmanagefavoritemedals");
	}

	// delete caches
	if ($db->num_rows($db->write_query("SELECT title FROM `" . TABLE_PREFIX . "datacache` WHERE title='medals'")) == 1)
	{
		$db->write_query("DELETE FROM `" . TABLE_PREFIX . "datacache` WHERE title='medals'");
	}
	if ($db->num_rows($db->write_query("SELECT title FROM `" . TABLE_PREFIX . "datacache` WHERE title='medals_user'")) == 1)
	{
		$db->write_query("DELETE FROM `" . TABLE_PREFIX . "datacache` WHERE title='medals_user'");
	}
	if ($db->num_rows($db->write_query("SELECT title FROM `" . TABLE_PREFIX . "datacache` WHERE title='medals_user_favorite'")) == 1)
	{
		$db->write_query("DELETE FROM `" . TABLE_PREFIX . "datacache` WHERE title='medals_user_favorite'");
	}
}

function medals_activate()
{
	global $db, $lang;

	// Add a new template (member_profile_medals) to our templates
	$templatearray = array(
		'member_profile_medals'     => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tbody>
		<tr>
		<td class="thead" align="center" colspan="3"><strong>{$lang->users_medals}</strong></td>
		</tr>
		<tr>
			<td class="tcat" width="50%"><span class="smalltext"><strong>{$lang->medal_name}</strong></span></td>
			<td class="tcat" align="center" width="30%"><span class="smalltext"><strong>{$lang->medal_image}</strong></span></td>
			<td class="tcat" align="center" width="30%"><span class="smalltext"><strong>{$lang->medal_date}</strong></span></td>
		</tr>
		{$medalRow}
	</tbody>
</table>
<br />',
		'member_profile_medals_row' => '<tr>
<td class="trow1"><strong>{$name}</strong></td>
<td class="trow1" align="center"><span class="smalltext"><img src="{$image}" alt="{$name}" style="width:16px;height:auto;" /></span></td>
<td class="trow1" align="center"><span class="smalltext">{$date}</span></td>
</tr>',
		'postbit'                   => '
		<img src="{$post[\'medal_image\']}" alt="{$post[\'medal_name\']}"  title="{$post[\'medal_name\']} - {$post[\'medal_id\']}" style="width:16px;height:auto" />
		',
		'favorite_row'              => '<tr>
<td class="trow1">{$name}</td>
<td class="trow1" align="center"><span class="smalltext"><img src="{$image}" alt="{$name}" style="width:16px;height:auto;" /></span></td>
<td class="trow1" align="center"><input type="checkbox" name="medals[{$id}]" value="{$id}" {$checked} /></td>
</tr>',
		'usercp_favoritemedals'     => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->manage_favorite_medals}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead" colspan="3"><strong>{$lang->manage_favorite_medals}</strong></td>
			</tr>
			<tr>
				<td class="trow1" colspan="3">
					<table cellspacing="0" cellpadding="0" width="100%">
						<tr>
							<td>{$lang->medal_explanation}</td>
						</tr>
						<tr>
							<td>{$lang->favorite_medals_explanation}</td>
						</tr>
						<tr>
							<td><strong>{$lang->medal_abuse_notice}</strong></td>
						</tr>
					</table>
				</td>
			</tr>
			<form action="usercp.php" method="post">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<tr>
			<td class="tcat" width="30%"><span class="smalltext"><strong>{$lang->medal_name}</strong></span></td>
			<td class="tcat" align="center" width="30%"><span class="smalltext"><strong>{$lang->medal_image}</strong></span></td>
			<td class="tcat" align="center" width="40%"><span class="smalltext"><strong>{$lang->medal_action}</strong></span></td>
			</tr>
				{$noMedals}
				{$medalRow}
		</table>
		<br />
		<div align="center">
			<input type="hidden" name="action" value="do_favoritemedals" />
			<input type="submit" class="button" name="submit" value="{$lang->update_favorite_medals}" />
			<a href="usercp.php?action=do_clearfavoritemedals">
				<button type="button" class="button">{$lang->clear_favorite_medals}</button>
			</a>
		</div>
	</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>',
		'favorite_no_medals'        => '<tr>
<td colspan="3" class="trow1">{$lang->no_medals_found}</td>
</tr>',
		'usercp_menu'               => '<td class="trow1 smalltext"><a href="usercp.php?action=favoritemedals" class="usercp_nav_item usercp_nav_medals">{$lang->manage_favorite_medals}</a></td>

<style type="text/css">
.usercp_nav_medals {
	background: url("images/medals.png") no-repeat left center;
}
</style>',
		'medal_page_view'               => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->medal_base_title}</title>
{$headerinclude}
</head>
<body>
	{$header}
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td class="thead" colspan="3"><strong>{$lang->medal_table_title}</strong></td>
		</tr>
		<tr>
			<td class="tcat" align="center"><span class="smalltext"><strong>{$lang->medal}</strong></span></td>
			<td class="tcat" align="center"><span class="smalltext"><strong>{$lang->description}</strong></span></td>
			<td class="tcat" width="50%"><span class="smalltext"><strong>{$lang->date}</strong></span></td>
		</tr>
		{$medalRows}
		{$medalRowsNone}
	</table>
	<br />
	{$footer}
</body>
</html>',
		'medal_page_row'               => '<tr>
<td class="trow1">{$name}</td>
<td class="trow1" align="center"><span class="smalltext"><img src="{$image}" alt="{$name}" style="width:16px;height:auto;" /></span></td>
<td class="trow1" align="center">{$date}</td>
</tr>',
		'medal_page_row_none'               => '<tr>
<td colspan="3" class="trow1">{$lang->no_medals_added}</td>
</tr>',
	);

	$group = array(
		'prefix' => $db->escape_string('medal'),
		'title'  => $db->escape_string('Medal System'),
	);

	// Update or create template group:
	$query = $db->simple_select('templategroups', 'prefix', "prefix='{$group['prefix']}'");

	if ($db->fetch_field($query, 'prefix'))
	{
		$db->update_query('templategroups', $group, "prefix='{$group['prefix']}'");
	}
	else
	{
		$db->insert_query('templategroups', $group);
	}

	// Query already existing templates.
	$query = $db->simple_select('templates', 'tid,title,template', "sid=-2 AND (title='{$group['prefix']}' OR title LIKE '{$group['prefix']}=_%' ESCAPE '=')");

	$templates = $duplicates = array();

	while ($row = $db->fetch_array($query))
	{
		$title = $row['title'];
		$row['tid'] = (int) $row['tid'];

		if (isset($templates[$title]))
		{
			// PluginLibrary had a bug that caused duplicated templates.
			$duplicates[] = $row['tid'];
			$templates[$title]['template'] = false; // force update later
		}
		else
		{
			$templates[$title] = $row;
		}
	}

	// Delete duplicated master templates, if they exist.
	if ($duplicates)
	{
		$db->delete_query('templates', 'tid IN (' . implode(",", $duplicates) . ')');
	}

	// Update or create templates.
	foreach ($templatearray as $name => $code)
	{
		if (strlen($name))
		{
			$name = "medal_{$name}";
		}
		else
		{
			$name = "medal";
		}

		$template = array(
			'title'    => $db->escape_string($name),
			'template' => $db->escape_string($code),
			'version'  => 1,
			'sid'      => -2,
			'dateline' => TIME_NOW,
		);

		// Update
		if (isset($templates[$name]))
		{
			if ($templates[$name]['template'] !== $code)
			{
				// Update version for custom templates if present
				$db->update_query('templates', array('version' => 0), "title='{$template['title']}'");

				// Update master template
				$db->update_query('templates', $template, "tid={$templates[$name]['tid']}");
			}
		}
		// Create
		else
		{
			$db->insert_query('templates', $template);
		}

		// Remove this template from the earlier queried list.
		unset($templates[$name]);
	}

	// Remove no longer used templates.
	foreach ($templates as $name => $row)
	{
		$db->delete_query('templates', "title='{$db->escape_string($name)}'");
	}

	// Include this file because it is where find_replace_templatesets is defined
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

	// Edit the index template and add our variable to above {$forums}
	find_replace_templatesets("member_profile", "#" . preg_quote('{$profilefields}') . "#i", '{$profilefields}{$medals}');

	find_replace_templatesets("postbit", '#' . preg_quote('{$post[\'user_details\']}') . '#', '{$post[\'user_details\']}</br>{$post[\'medals\']}');
	find_replace_templatesets("postbit_classic", '#' . preg_quote('{$post[\'user_details\']}') . '#', '{$post[\'user_details\']}</br>{$post[\'medals\']}');
	// Settings group array details
	$group = array(
		'name'        => 'medal',
		'title'       => $db->escape_string($lang->setting_group_medal),
		'description' => $db->escape_string($lang->setting_group_medal_desc),
		'isdefault'   => 0,
	);

	// Check if the group already exists.
	$query = $db->simple_select('settinggroups', 'gid', "name='medal'");

	if ($gid = (int) $db->fetch_field($query, 'gid'))
	{
		// We already have a group. Update title and description.
		$db->update_query('settinggroups', $group, "gid='{$gid}'");
	}
	else
	{
		// We don't have a group. Create one with proper disporder.
		$query = $db->simple_select('settinggroups', 'MAX(disporder) AS disporder');
		$disporder = (int) $db->fetch_field($query, 'disporder');

		$group['disporder'] = ++$disporder;

		$gid = (int) $db->insert_query('settinggroups', $group);
	}

	// Deprecate all the old entries.
	$db->update_query('settings', array('description' => 'MEDALDELETEMARKER'), "gid='{$gid}'");

	// add settings
	$settings = array(
		'display1' => array(
			'optionscode' => 'yesno',
			'value'       => 1,
		),
		'display2' => array(
			'optionscode' => 'yesno',
			'value'       => 1,
		),
		'limit1'   => array(
			'optionscode' => 'text',
			'value'       => 4,
		),
		'limit2'   => array(
			'optionscode' => 'text',
			'value'       => 10,
		),
		'display3' => array(
			'optionscode' => 'yesno',
			'value'       => 1,
		),
		'display4' => array(
			'optionscode' => 'groupselect',
			'value'       => 'all',
		),
		'display5' => array(
			'optionscode' => 'yesno',
			'value'       => 1,
		),
		'display6' => array(
			'optionscode' => 'yesno',
			'value'       => 1,
		),
		'display7' => array(
			'optionscode' => 'yesno',
			'value'       => 1,
		),
	);

	$disporder = 0;

	// Create and/or update settings.
	foreach ($settings as $key => $setting)
	{
		// Prefix all keys with group name.
		$key = "medal_{$key}";

		$lang_var_title = "setting_{$key}";
		$lang_var_description = "setting_{$key}_desc";

		$setting['title'] = $lang->{$lang_var_title};
		$setting['description'] = $lang->{$lang_var_description};

		// Filter valid entries.
		$setting = array_intersect_key($setting,
			array(
				'title'       => 0,
				'description' => 0,
				'optionscode' => 0,
				'value'       => 0,
			));

		// Escape input values.
		$setting = array_map(array($db, 'escape_string'), $setting);

		// Add missing default values.
		++$disporder;

		$setting = array_merge(
			array('description' => '',
				  'optionscode' => 'yesno',
				  'value'       => 0,
				  'disporder'   => $disporder),
			$setting);

		$setting['name'] = $db->escape_string($key);
		$setting['gid'] = $gid;

		// Check if the setting already exists.
		$query = $db->simple_select('settings', 'sid', "gid='{$gid}' AND name='{$setting['name']}'");

		if ($sid = $db->fetch_field($query, 'sid'))
		{
			// It exists, update it, but keep value intact.
			unset($setting['value']);
			$db->update_query('settings', $setting, "sid='{$sid}'");
		}
		else
		{
			// It doesn't exist, create it.
			$db->insert_query('settings', $setting);
			// Maybe use $db->insert_query_multiple somehow
		}
	}

	// Delete deprecated entries.
	$db->delete_query('settings', "gid='{$gid}' AND description='MEDALDELETEMARKER'");

	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();
}

function medals_deactivate()
{
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

	// remove template edits
	find_replace_templatesets("member_profile", "#" . preg_quote('{$medals}') . "#i", '', 0);

	find_replace_templatesets("postbit", '#' . preg_quote('</br>{$post[\'medals\']}') . '#', '', 0);
	find_replace_templatesets("postbit_classic", '#' . preg_quote('</br>{$post[\'medals\']}') . '#', '', 0);
}

function medals_admin_user_menu(&$sub_menu)
{
	global $lang;

	$lang->load('medals');

	$sub_menu['80'] = array(
		'id'    => 'medals',
		'title' => $lang->medals,
		'link'  => 'index.php?module=user-medals',
	);

	return $sub_menu;
}

function medals_admin_user_action_handler(&$actions)
{
	$actions['medals'] = array(
		'active' => 'medals',
		'file'   => 'medals.php',
	);

	return $actions;
}

// Display medals on profile page
function medals_profile()
{
	global $mybb, $db, $templates, $lang, $theme, $memprofile, $medals, $name, $image, $reason, $id, $date;

	// load language
	$lang->load("medals");

	// set the username as part of the language string
	$lang->users_medals = $lang->sprintf($lang->users_medals, $memprofile['username']);

	// Only run this function is the setting is set to yes
	if ($mybb->settings['medal_display2'] == 0)
	{
		return;
	}

	$queryMedals = queryUser($memprofile['uid'], $mybb->settings['medal_limit2'] ?? '10');

	// show template if user has 1 medal or more
	if ($db->num_rows($queryMedals) > 0)
	{
		$medalRow = '';
		while ($medal = $db->fetch_array($queryMedals))
		{
			$id = (string) $medal['medal_id'];
			$name = (string) $medal['medal_name'];
			$reason = (string) $medal['reason'];
			$date = my_date('normal', $medal['created_at']);
			$image = (string) $medal['medal_image_path'];

			eval("\$medalRow .= \"" . $templates->get("medal_member_profile_medals_row") . "\";");
		}

		eval("\$medals = \"" . $templates->get("medal_member_profile_medals") . "\";");
	}
}

// Display medals on posts
function medals_postbit(&$post)
{
	global $mybb, $db, $templates, $settings;

	// Only run this function is the setting is set to yes
	if ($mybb->settings['medal_display1'] == 0)
	{
		// otherwise array key error
		$post['medals'] = '';
		return;
	}

	$queryMedals = queryUser($post['uid'], $mybb->settings['medal_limit1'] ?? '4');

	$post['medals'] = '';
	// show template if user has 1 medal or more
	if ($db->num_rows($queryMedals) > 0)
	{
		$post['medals'] = '';
		while ($medal = $db->fetch_array($queryMedals))
		{
			$post['medal_id'] = (int) $medal['medal_id'];
			$post['medal_name'] = (string) $medal['medal_name'];
			$post['medal_image'] = (string) $medal['medal_image_path'];
			$post['created_at'] = my_date('normal', $medal['created_at']);

			eval("\$post['medals'] .= \"" . $templates->get("medal_postbit") . "\";");
		}
	}
}

function queryUser($userId, $limit = null): mysqli_result|bool|PDOStatement|null
{
	global $db;

	if (is_null($limit))
	{
		$limit = '100';
	}

	return $db->query("
	SELECT m.medal_name, m.medal_image_path, m.medal_id, mu.created_at, mu.reason, mu.user_id, muf.medals_user_favorite_id
	FROM `" . TABLE_PREFIX . "medals_user` 
	    AS mu
	    INNER JOIN `" . TABLE_PREFIX . "users` 
	        AS u
	        ON mu.user_id = u.uid
	    INNER JOIN `" . TABLE_PREFIX . "medals` 
	        AS m
	        ON mu.medal_id = m.medal_id
	    LEFT JOIN `" . TABLE_PREFIX . "medals_user_favorite` 
	        AS muf
	        ON muf.medal_id = m.medal_id
	WHERE mu.user_id = $userId
		ORDER BY
		muf.medals_user_favorite_id DESC,
		mu.medal_user_id ASC
	LIMIT $limit
	");

	/*	ORDER BY
			muf.medals_user_favorite_id ASC,
			mu.medal_user_id ASC*/
	/*CASE WHEN muf.medals_user_favorite_id THEN muf.medals_user_favorite_idELSE mu.medal_user_id END*/
}

/*
 * Loads the settings language strings.
*/
function medal_settings()
{
	global $lang;

	// Load our language file
	$lang->load('medals');
}

/*
 * Add Who's Online to the /medals.php page
*/
// WOL Support
function medals_fetch_wol_activity_end(&$args)
{
	if ($args['activity'] != 'unknown')
	{
		return;
	}

	if (my_strpos($args['location'], 'medals.php') === false)
	{
		return;
	}

	$args['activity'] = 'medals';
}

function medals_build_friendly_wol_location_end(&$args)
{
	global $lang;

	$lang->load('medals');

	if ($args['user_activity']['activity'] == 'medals')
	{
		$args['location_name'] = $lang->viewing_medals;
	}
}

// Delete medals if user is deleted
function medal_user_delete($delete)
{
	global $db;

	// Remove any of the user(s) medals
	$db->delete_query("medals_user", "user_id='{$delete->delete_uids}'");

	return $delete;
}

// Admin CP permission control
function medals_usergroup_permission()
{
	global $mybb, $lang, $form, $form_container, $run_module, $page;
	$lang->load("medals", true);

	if ($run_module == 'user' && $page->active_action == 'groups' && !empty($form_container->_title) & !empty($lang->misc) & $form_container->_title == $lang->misc)
	{
		$medalsOptions = array(
			$form->generate_check_box('canmanagefavoritemedals', 1, $lang->can_manage_favorite_medals, array("checked" => $mybb->input['canmanagefavoritemedals'])));
		$form_container->output_row($lang->favorite_medals, "", "<div class=\"group_settings_bit\">" . implode("</div><div class=\"group_settings_bit\">", $medalsOptions) . "</div>");
	}
}

function medals_usergroup_permission_commit()
{
	global $mybb, $updated_group;
	$updated_group['canmanagefavoritemedals'] = $mybb->get_input('canmanagefavoritemedals', MyBB::INPUT_INT);
}

// Add page to usercp
function medals_usercp()
{
	global $db, $mybb, $lang, $templates, $theme, $headerinclude, $usercpnav, $header, $footer, $name, $image, $checked, $id, $reason;
	$lang->load("medals");
	$lang->load("usercp");

	if ($mybb->get_input('action', MyBB::INPUT_STRING) == "favoritemedals")
	{
		add_breadcrumb($lang->nav_usercp, "usercp.php");
		add_breadcrumb($lang->manage_favorite_medals, "usercp.php?action=favoritemedals");

		// to silence undefined variables
		$medalRow = '';
		$noMedals = '';

		// if no permission!
		if (!$mybb->usergroup['canmanagefavoritemedals'])
		{
			error_no_permission();
		}
		$queryMedals = queryUser($mybb->user['uid'], '500');

		if ($db->num_rows($queryMedals) > 0)
		{
			while ($medal = $db->fetch_array($queryMedals))
			{
				$id = (string) $medal['medal_id'];
				$name = (string) $medal['medal_name'];
				$image = (string) $medal['medal_image_path'];
				$reason = (string) $medal['reason'];
				$checked = (string) !is_null($medal['medals_user_favorite_id']) ? 'checked' : '';

				eval("\$medalRow .= \"" . $templates->get("medal_favorite_row") . "\";");
			}
		}
		else
		{
			eval("\$noMedals .= \"" . $templates->get("medal_favorite_no_medals") . "\";");
		}
		eval("\$favoriteManagementPage = \"" . $templates->get("medal_usercp_favoritemedals") . "\";");
		output_page($favoriteManagementPage);
	}

	if ($mybb->request_method == "post" && $mybb->get_input('action', MyBB::INPUT_STRING) == "do_favoritemedals")
	{
		// verify POST request
		verify_post_check($mybb->get_input('my_post_key'));

		// if no checkboxes selected
		if (!isset($mybb->input['medals']) || !is_array($mybb->get_input('medals', MyBB::INPUT_ARRAY)))
		{
			error($lang->no_medals_selected);
		}

		// grab the ids
		$medalIds = implode(',', array_map('intval', $mybb->get_input('medals', MyBB::INPUT_ARRAY)));

		// get the user
		$user = $mybb->user['uid'];

		// ensure the user has the medals they are trying to favorite
		$obtainUsersMedalIDs = $db->write_query("
		SELECT mu.medal_id 
		FROM `" . TABLE_PREFIX . "medals_user`
		AS mu
		WHERE mu.medal_id
		IN ($medalIds)
		AND mu.user_id = $user
		");

		if (!$db->num_rows($obtainUsersMedalIDs))
		{
			error($lang->invalid_medals);
		}
		else
		{
			// delete the existing ids for the user
			$db->delete_query('medals_user_favorite', "user_id = $user");

			// insert new IDs
			$dateline = time();

			foreach ($mybb->get_input('medals', MyBB::INPUT_ARRAY) as $id)
			{
				$db->write_query("
				INSERT INTO `" . TABLE_PREFIX . "medals_user_favorite` (user_id, medal_id, updated_at)
				VALUES ($user, $id, $dateline)");
			}

			// rebuild cache
			rebuild_medals_user_favorite_cache();
		}
		redirect("usercp.php?action=favoritemedals", $lang->favorite_medals_updated, $lang->manage_favorite_medals, true);
	}

	if ($mybb->get_input('action', MyBB::INPUT_STRING) == "do_clearfavoritemedals")
	{

		// if no permission!
		if (!$mybb->usergroup['canmanagefavoritemedals'])
		{
			error_no_permission();
		}

		// get the user
		$user = $mybb->user['uid'];

		// ensure the user has any favorite medals
		$obtainUsersFavoriteMedals = $db->write_query("
		SELECT mu.medal_id 
		FROM `" . TABLE_PREFIX . "medals_user_favorite`
		AS mu
		WHERE mu.user_id = $user
		");

		if ($db->num_rows($obtainUsersFavoriteMedals) == 0)
		{
			error($lang->no_medals_found_clear);
		}
		else
		{
			$db->delete_query('medals_user_favorite', "user_id = $user");

			// rebuild cache
			rebuild_medals_user_favorite_cache();
		}

		redirect("usercp.php?action=favoritemedals", $lang->favorite_medals_cleared, $lang->manage_favorite_medals, true);
	}
}

function medals_usercp_menu()
{
	global $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;

	$lang->load("medals");

	if ($mybb->usergroup['canmanagefavoritemedals'])
	{
		eval("\$usercpmenu .= \"" . $templates->get('medal_usercp_menu') . "\";");
	}
}

function medals_admin_tools_adminlog_begin()
{
	global $lang;

	$lang->load("medals");
}

function medals_admin_tools_get_admin_log_action(&$plugin_array)
{
	global $mybb, $db, $logitem;

	if ($plugin_array['logitem']['module'] == "user-medals")
	{
		$plugin_array['lang_string'] = match ($plugin_array['logitem']['action'])
		{
			"add" => "admin_log_medals_action_add",
			"edit" => "admin_log_medals_action_edit",
			"delete" => "admin_log_medals_action_delete",
			"assign" => "admin_log_medals_action_assign",
			"revoke" => "admin_log_medals_action_revoke",
			"editreason" => "admin_log_medals_action_editreason",
		};

		return $plugin_array;
	}
}

// rebuilds all medal caches when user clicks "Rebuild & Reload All" from the Cache Manager in ACP.
function medals_admin_tools_cache_rebuild()
{
	// rebuild our caches too!
	rebuild_medals_user_favorite_cache();
	rebuild_medals_user_cache();
	rebuild_medals_cache();
}