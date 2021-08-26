<?php

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

	if (THIS_SCRIPT == 'showthread.php')
	{
		$templatelist .= 'medal_postbit';
	}

	if (THIS_SCRIPT == 'medals.php')
	{
		$templatelist .= 'medal_table,medal_user_all_medals_row,medal_user_all_medals_table,medal_user_row,medal_user_table,medal_view';
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

if (defined('IN_ADMINCP'))
{
	// Add our medal_settings() function to the setting management module to load language strings.
	$plugins->add_hook('admin_config_settings_manage', 'medal_settings');
	$plugins->add_hook('admin_config_settings_change', 'medal_settings');
	$plugins->add_hook('admin_config_settings_start', 'medal_settings');
	// We could hook at 'admin_config_settings_begin' only for simplicity sake.
}

function medals_info()
{
	return array(
		"name"          => "Medals",
		"description"   => "A medal system for MyBB.",
		"website"       => "",
		"author"        => "Lewis Larsen",
		"authorsite"    => "https://lewislarsen.codes",
		"version"       => "1.0",
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

	$db->delete_query("templategroups", "prefix IN('medal')");
	$db->delete_query("templates", "title IN('medal_member_profile_medals')");
	$db->delete_query("templates", "title IN('medal_member_profile_medals_row')");
	$db->delete_query("templates", "title IN('medal_postbit')");
	$db->delete_query('settinggroups', "name='medal'");
	$db->delete_query('settings', "name IN ('medal_postbit_count','medal_profile_count')");
	rebuild_settings();
}

function medals_activate()
{
	global $db, $lang;

	// Add a new template (member_profile_medals) to our templates
	$templatearray = array(
		'member_profile_medals'       => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tbody>
		<tr>
		<td class="thead" align="center" colspan="2"><strong>{$lang->users_medals}</strong></td>
		</tr>
		<tr>
			<td class="tcat" width="60%"><span class="smalltext"><strong>{$lang->medal_name}</strong></span></td>
			<td class="tcat" align="center" width="40%"><span class="smalltext"><strong>{$lang->medal_image}</strong></span></td>
		</tr>
		{$medalRow}
	</tbody>
</table>
<br />',
		'member_profile_medals_row'   => '<tr>
<td class="trow1"><a title="{$name}" href="medals.php?action=view&id={$id}"><strong>{$name}</strong></a></td>
<td class="trow1" align="center"><span class="smalltext"><img src="{$image}" alt="{$name}" style="width:16px;height:auto;" /></span></td>
</tr>',
		'postbit'                     => '
		</br>
		<img src="{$post[\'medal_image\']}" alt="{$post[\'medal_name\']}"  title="{$post[\'medal_name\']} - {$post[\'medal_id\']}" style="width:16px;height:auto" />
		',
		'medal_table'                 => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tbody>
			<tr>
		<td class="thead" align="center" colspan="2"><strong>{$lang->medal_table_title}</strong></td>
		</tr>
			<tr>
			<td class="tcat" width="60%"><span class="smalltext"><strong>{$lang->medal_name}</strong></span></td>
			<td class="tcat" align="center" width="40%"><span class="smalltext"><strong>{$lang->medal_image}</strong></span></td>
			</tr>
			<tr>
				<td class="trow1"><a title="{$name}" href="medals.php?action=view&id={$id}"><strong>{$name}</strong></a></td>
<td class="trow2" align="center"><span class="smalltext"><img src="{$image}" alt="{$name}" style="width:16px;height:auto;" /></span></td>
</tr>
</tbody>
</table></br>',
		'medal_user_all_medals_row'   => '<tr>
<td class="trow1"><a title="{$name}" href="?medals.php?action=view&id={$id}"><strong>{$name}</strong></a></td>
<td class="trow1" align="center"><span class="smalltext"><img src="{$image}" alt="{$name}" style="width:16px;height:auto;" /></span></td>
<td class="trow1">{$reason}</td>
<td class="trow1">{$date}</td>
</tr>',
		'medal_user_all_medals_table' => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tbody>
			<tr>
		<td class="thead" align="center" colspan="4"><strong>{$lang->medals_username_breadcrumb}</strong></td>
		</tr>
			<tr>
			<td class="tcat" width="30%"><span class="smalltext"><strong>{$lang->medal_name}</strong></span></td>
			<td class="tcat" width="20%" align="center"><span class="smalltext"><strong>{$lang->medal_image}</strong></span></td>
			<td class="tcat" align="center" width="30%"><span class="smalltext"><strong>{$lang->reason}</strong></span></td>
			<td class="tcat" align="center" width="20%"><span class="smalltext"><strong>{$lang->date}</strong></span></td>
			</tr>
				{$userAllMedalsTableRow}
		</tbody>
		</table>
</br>',
		'medal_user_row'              => '<tr>
<td class="trow1"><strong><a title="{$username}" href="medals.php?action=member&id={$user_id}">{$username}</a</strong></td>
<td class="trow1">{$reason}</td>
<td class="trow1">{$date}</td>
</tr>',
		'medal_user_table'            => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tbody>
			<tr>
		<td class="thead" align="center" colspan="4"><strong>{$lang->medals_table_name}</strong></td>
		</tr>
			<tr>
			<td class="tcat" width="40%"><span class="smalltext"><strong>{$lang->username}</strong></span></td>
			<td class="tcat" align="center" width="40%"><span class="smalltext"><strong>{$lang->reason}</strong></span></td>
			<td class="tcat" align="center" width="40%"><span class="smalltext"><strong>{$lang->date}</strong></span></td>
			</tr>
				{$medalUserRow}
		</tbody>
		</table>
</br>',
		'medal_view'                  => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->medal_base_title}</title>
{$headerinclude}
</head>
	<body>
{$header}
{$medalsUserTable}
{$userAllMedalsTable}
{$medalsTable}
{$footer}
</body>
</html>',
		''                            => '',);

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

	find_replace_templatesets("postbit", '#' . preg_quote('{$post[\'user_details\']}') . '#', '{$post[\'user_details\']}{$post[\'medals\']}');
	find_replace_templatesets("postbit_classic", '#' . preg_quote('{$post[\'user_details\']}') . '#', '{$post[\'user_details\']}{$post[\'medals\']}');


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
			'value'       => '',
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

	find_replace_templatesets("postbit", '#' . preg_quote('{$post[\'medals\']}') . '#', '', 0);
	find_replace_templatesets("postbit_classic", '#' . preg_quote('{$post[\'medals\']}') . '#', '', 0);
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
	global $mybb, $db, $templates, $lang, $theme, $memprofile, $medals, $name, $image, $reason, $id;

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

	// show template if user has 1 medal or more
	if ($db->num_rows($queryMedals) > 0)
	{
		$post['medals'] = '';
		while ($medal = $db->fetch_array($queryMedals))
		{
			$post['medal_id'] = (int) $medal['medal_id'];
			$post['medal_name'] = (string) $medal['medal_name'];
			$post['medal_image'] = (string) $medal['medal_image_path'];

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
	SELECT m.medal_name, m.medal_image_path, m.medal_id, mu.reason
	FROM `" . TABLE_PREFIX . "medals_user` 
	    AS mu
	    INNER JOIN `" . TABLE_PREFIX . "users` 
	        AS u
	        ON mu.user_id = u.uid
	    INNER JOIN `" . TABLE_PREFIX . "medals` 
	        AS m
	        ON mu.medal_id = m.medal_id
	WHERE mu.user_id = $userId
	ORDER BY mu.medal_user_id ASC
	LIMIT $limit
	");
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