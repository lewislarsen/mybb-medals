<?php
// base
const IN_MYBB = 1;
define('THIS_SCRIPT', substr($_SERVER['SCRIPT_NAME'], -strpos(strrev($_SERVER['SCRIPT_NAME']), '/')));

global $lang, $settings, $mybb, $templates, $db, $cache;

$templatelist = 'medal_table,medal_user_all_medals_row,medal_user_all_medals_table,medal_user_row,medal_user_table,medal_view';

require_once './global.php';
require_once MYBB_ROOT . "inc/functions_user.php";

$fileName = "medals";
$scriptURL = $settings['bburl'] . '/' . $fileName . '.' . substr(strrchr(__FILE__, '.'), 1);
// end base

// load the language
$lang->load('medals');


// check the plugin state
$pCache = $cache->read("plugins");

// if the page is disabled then redirect them!
if ($mybb->settings['medal_display3'] == 0)
{
	redirect("index.php", $lang->medal_page_redirect_notice_message, $lang->medal_page_redirect_notice, true);
}

// check if they're a member of the allowed usergroups defined in settings
if (!is_member($mybb->settings['medal_display4']))
{
	error_no_permission();
}

$userAllMedalsTable = '';
$medalsUserTable = '';
$medals = '';

// define the action parameter
$action = $mybb->input['action'] = $mybb->get_input('action');

if ($action == 'view' && $mybb->input['id'])
{
	// set variables to make the error go away
	$medalsTable = '';

	$medalId = $mybb->input['id'];

	$queryMedalFromId2 = $db->query("
	SELECT m.medal_name, m.medal_description
	FROM `" . TABLE_PREFIX . "medals`
	AS m
	WHERE m.medal_id = $medalId");

	$medalName =  $db->fetch_field($queryMedalFromId2, 'medal_name');

	$lang->medals_table_name = $lang->sprintf($lang->medals_table_name, $medalName);

	if($db->num_rows($queryMedalFromId2) == 0)
	{
		error($lang->medal_invalid_medal);
	}

	$queryMedalFromId = $db->query("
	SELECT u.username, u.uid as user_id, m.medal_name, m.medal_id, mu.reason, mu.created_at, m.medal_image_path
	FROM `" . TABLE_PREFIX . "medals_user`
	AS mu
	INNER JOIN `" . TABLE_PREFIX . "medals`
	AS m
	ON mu.medal_id = m.medal_id
	INNER JOIN `" . TABLE_PREFIX . "users`
	AS u
	ON mu.user_id = u.uid
	WHERE mu.medal_id = $medalId
	");

	$medalsUserTable = '';
	$medalUserRow = '';
	while($medal = $db->fetch_array($queryMedalFromId))
	{
		$user_id = $medal['user_id'];
		$username = $medal['username'];
		$reason = $medal['reason'];
		$date = my_date('normal', $medal['created_at']);
		$id = $medal['medal_id'];
		$name = $medal['medal_name'];
		$image = $medal['medal_image_path'];

		eval("\$medalUserRow .= \"" . $templates->get("medal_user_row") . "\";");
	}
	eval("\$medalsUserTable .= \"" . $templates->get("medal_user_table") . "\";");


	// set the breadcrumb
	add_breadcrumb($lang->medal_base_breadcrumb, $scriptURL);
	add_breadcrumb($medalName, $scriptURL);
}

if ($action == 'member' && $mybb->input['id'])
{
	// set variables to make the error go away
	$medalsTable = '';

	$userId = $mybb->input['id'];

	$getUserData = $db->query("
	SELECT u.username, u.uid as user_id
	FROM `" . TABLE_PREFIX . "users`
	AS u
	WHERE u.uid = $userId");

	if($db->num_rows($getUserData) == 0)
	{
		error($lang->medal_invalid_user);
	}

	$username =  $db->fetch_field($getUserData, 'username');
	$title = $lang->medals_username_breadcrumb = $lang->sprintf($lang->medals_username_breadcrumb, $username);

	$getUsersMedals = $db->query("
	SELECT m.medal_name, m.medal_image_path, m.medal_id, mu.reason, mu.created_at
	FROM `" . TABLE_PREFIX . "medals_user` 
	    AS mu
	    INNER JOIN `" . TABLE_PREFIX . "users` 
	        AS u
	        ON mu.user_id = u.uid
	    INNER JOIN `" . TABLE_PREFIX . "medals` 
	        AS m
	        ON mu.medal_id = m.medal_id
	WHERE mu.user_id = $userId
	ORDER BY mu.medal_user_id ASC");

	// show template if user has 1 medal or more
	if ($db->num_rows($getUsersMedals) > 0)
	{
		$userAllMedalsTableRow = '';
		$userAllMedalsTable = '';
		while ($medal = $db->fetch_array($getUsersMedals))
		{
			$id = (int) $medal['medal_id'];
			$name = (string) $medal['medal_name'];
			$image = (string) $medal['medal_image_path'];
			$reason = (string) $medal['reason'];
			$date = my_date('normal', $medal['created_at']);

			eval("\$userAllMedalsTableRow .= \"" . $templates->get("medal_user_all_medals_row") . "\";");
		}
		eval("\$userAllMedalsTable .= \"" . $templates->get("medal_user_all_medals_table") . "\";");
	}

	// set the breadcrumb
	add_breadcrumb($lang->medal_base_breadcrumb, $scriptURL);
	add_breadcrumb($title, $scriptURL.'?action=member&id='.$userId);
}

if (!$action)
{
	add_breadcrumb($lang->medal_base_breadcrumb, $scriptURL);

	$queryAllMedals = $db->query("
	SELECT m.medal_name, m.medal_image_path, m.medal_id
	FROM `" . TABLE_PREFIX . "medals`
	AS m
	ORDER BY m.medal_id DESC
	");

	// show template if user has 1 medal or more
	if ($db->num_rows($queryAllMedals) > 0)
	{
		$medalsTable = '';
		while ($medal = $db->fetch_array($queryAllMedals))
		{
			$id = (int) $medal['medal_id'];
			$name = (string) $medal['medal_name'];
			$image = (string) $medal['medal_image_path'];

			eval("\$medalsTable .= \"" . $templates->get("medal_table") . "\";");
		}
	}
}
eval("\$medals = \"" . $templates->get("medal_view") . "\";");
output_page($medals);