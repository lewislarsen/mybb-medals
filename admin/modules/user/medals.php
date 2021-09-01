<?php

require_once (MYBB_ROOT.'inc/functions_medals.php');

global $page, $mybb, $lang, $errors, $db, $settings, $cache;

if (!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

if (!$lang->medals_admin_title)
{
	$lang->load('medals');
}

$page->add_breadcrumb_item($lang->medals, "index.php?module=user-medals");

$sub_tabs['medals'] = array(
	'title'       => $lang->medals,
	'link'        => "index.php?module=user-medals",
	'description' => $lang->medals_page_desc,
);
$sub_tabs['add_medals'] = array(
	'title'       => $lang->medals_add,
	'link'        => "index.php?module=user-medals&action=add",
	'description' => $lang->medals_add_desc,
);
$sub_tabs['assign_medals'] = array(
	'title'       => $lang->medals_assign,
	'link'        => "index.php?module=user-medals&action=assign",
	'description' => $lang->medals_assign_desc,
);
$sub_tabs['users_medals'] = array(
	'title'       => $lang->medals_user,
	'link'        => "index.php?module=user-medals&action=members",
	'description' => $lang->medals_user_desc,
);
$sub_tabs['statistics'] = array(
	'title'       => $lang->statistics,
	'link'        => "index.php?module=user-medals&action=statistics",
	'description' => $lang->statistics_desc,
);

if (!$mybb->input['action'])
{
	$page->output_header($lang->medals);
	$page->output_nav_tabs($sub_tabs, 'medals');

	//
	// START PAGINATION & SORTING
	//
	if ($mybb->get_input("page"))
	{
		$activePage = $mybb->get_input("page", MyBB::INPUT_INT);
	}
	else
	{
		$activePage = 1;
	}

	// Grab the amount of pages!
	$medalsCache = $cache->read('medals');
	$items = is_bool($medalsCache) ? '0' : count($medalsCache);

	$itemsPerPage = "20";

	$pages = ceil($items / $itemsPerPage);

	if ($activePage > $pages)
	{
		$activePage = $pages;
	}
	if ($activePage < 1)
	{
		$activePage = 1;
	}

	$start = $activePage * $itemsPerPage - $itemsPerPage;

	if ($mybb->get_input("dir"))
	{
		$direction = match ($mybb->get_input("dir"))
		{
			"a" => "ASC",
			"d" => "DESC",
			default => "DESC"
		};
	}
	else
	{
		$direction = "DESC";
	}

	// switch the direction
	$switchDir = $mybb->get_input("dir") == 'a' ? 'd' : 'a';

	if ($mybb->get_input("order"))
	{
		$sortQuery = " ORDER BY ";
		$orderInput = $mybb->get_input("order");
		$sortQuery .= match ($orderInput)
		{
			"time" => "created_at $direction",
			"description" => "medal_description $direction",
			"name" => "medal_name $direction",
			"image" => "medal_image_path $direction",
			default => "medal_id $direction",
		};
	}
	else
	{
		$sortQuery = " ORDER BY medal_id $direction";
		$orderInput = "time";
	}

	$generatePagination = draw_admin_pagination($activePage, $itemsPerPage, $items, "index.php?module=user-medals&order=" . $orderInput . '&dir=' . $switchDir);
	echo $generatePagination;

	//
	// END PAGINATION & SORTING
	//

	$baseURL = "index.php?module=user-medals&dir=$switchDir";

	$table = new Table;
	$table->construct_header("<a href='$baseURL&order=name'>$lang->medal_name</a>");
	$table->construct_header("<a href='$baseURL&order=description'>$lang->medal_description</a>");
	$table->construct_header("<a href='$baseURL&order=image'>$lang->medal_image</a>", array('width' => '250', 'class' => 'align_center'));
	$table->construct_header("<a href='$baseURL&order=time'>$lang->medal_created_at</a>", array('width' => '200', 'class' => 'align_center'));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));

	$query = $db->write_query("
	SELECT *
	FROM `" . TABLE_PREFIX . "medals`
	" . $sortQuery . " LIMIT " . ' ' . $start . ",$itemsPerPage");
	while ($medal = $db->fetch_array($query))
	{
		$medal['medal_name'] = htmlspecialchars_uni($medal['medal_name']);
		$table->construct_cell("<a href=\"index.php?module=user-medals&amp;action=edit&amp;id={$medal['medal_id']}\"><strong>{$medal['medal_name']}</strong></a>");
		$table->construct_cell("{$medal['medal_description']}");
		$table->construct_cell("<img style='width:21px;height:auto;' src=\"{$mybb->settings['bburl']}/{$medal['medal_image_path']}\" />", array("class" => "align_center"));
		$table->construct_cell(my_date('relative', $medal['created_at']), array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-medals&amp;action=edit&amp;id={$medal['medal_id']}\">{$lang->edit}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-medals&amp;action=delete&amp;id={$medal['medal_id']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->medal_deletion_confirmation}')\">{$lang->delete}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}

	if ($table->num_rows() == 0)
	{
		$table->construct_cell($lang->medals_none, array('colspan' => 5));
		$table->construct_row();
		$no_results = true;
	}

	$table->output("<a href='$baseURL'>$lang->medals</a>");

	echo $generatePagination;

	$page->output_footer();
}

if ($mybb->input['action'] == "add")
{
	if ($mybb->request_method == "post")
	{
		if (!trim($mybb->input['title']))
		{
			$errors[] = $lang->medal_name_missing;
		}

		if (!trim($mybb->input['image_path']))
		{
			$errors[] = $lang->medal_image_missing;
		}

		if (!$errors)
		{
			$new_medal = array(
				"medal_name"        => $db->escape_string($mybb->input['title']),
				"medal_description" => $db->escape_string($mybb->input['description']),
				"medal_image_path"  => $db->escape_string($mybb->input['image_path']),
				"created_at"        => time(),
				'admin_user_id'     => (int) $mybb->user['uid'],
			);

			$medal = $db->insert_query("medals", $new_medal);

			// rebuild cache
			rebuild_medals_cache();

			//Log admin action
			log_admin_action($medal, $mybb->input['title']);

			flash_message($lang->medal_created, 'success');
			admin_redirect("index.php?module=user-medals");
		}
	}

	$page->add_breadcrumb_item($lang->medals_add);
	$page->output_header($lang->medals . " - " . $lang->medals_add);

	$page->output_nav_tabs($sub_tabs, 'add_medals');
	$form = new Form("index.php?module=user-medals&amp;action=add", "post");


	if ($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer($lang->medals_add);
	$form_container->output_row($lang->medal_name . "<em>*</em>", $lang->medal_name_description, $form->generate_text_box('title', $mybb->get_input('title'), array('id' => 'title')), 'title');
	$form_container->output_row($lang->medal_description, $lang->medal_description_description, $form->generate_text_area('description', $mybb->get_input('description'), array('id' => 'description')), 'description');
	$form_container->output_row($lang->medal_image . "<em>*</em>", $lang->medal_image_description, $form->generate_text_box('image_path', $mybb->get_input('image_path'), array('id' => 'image_path')), 'image_path');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->medals_add_save);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if ($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("medals", "*", "medal_id='" . $mybb->get_input('id', MyBB::INPUT_INT) . "'");
	$medal = $db->fetch_array($query);

	if (!$medal['medal_id'])
	{
		flash_message($lang->invalid_medal, 'error');
		admin_redirect("index.php?module=user-medals");
	}

	if ($mybb->request_method == "post")
	{
		if (!trim($mybb->input['medal_name']))
		{
			$errors[] = $lang->medal_name_missing;
		}

		if (!trim($mybb->input['medal_image_path']))
		{
			$errors[] = $lang->medal_image_missing;
		}

		if (!$errors)
		{
			$updated_title = array(
				"medal_name"        => $db->escape_string($mybb->input['medal_name']),
				"medal_description" => $db->escape_string($mybb->input['medal_description']),
				"medal_image_path"  => $db->escape_string($mybb->input['medal_image_path']),
			);

			$db->update_query("medals", $updated_title, "medal_id='{$medal['medal_id']}'");

			// rebuild cache
			rebuild_medals_cache();

			// Log admin action
			log_admin_action($medal['medal_id'], $mybb->input['medal_name']);

			flash_message($lang->success_medal_updated, 'success');
			admin_redirect("index.php?module=user-medals");
		}
	}

	$page->add_breadcrumb_item($lang->edit_medal);
	$page->output_header($lang->medals . " - " . $lang->edit_medal);

	$page->output_nav_tabs($sub_tabs, 'medals');
	$form = new Form("index.php?module=user-medals&amp;action=edit&amp;id={$medal['medal_id']}", "post");


	if ($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $medal);
	}

	$form_container = new FormContainer($lang->edit_medal);
	$form_container->output_row($lang->medal_name . "<em>*</em>", $lang->medal_name_description, $form->generate_text_box('medal_name', $mybb->input['medal_name'], array('id' => 'medal_name')), 'medal_name');
	$form_container->output_row($lang->medal_description, $lang->medal_description_description, $form->generate_text_area('medal_description', $mybb->input['medal_description'], array('id' => 'medal_description')), 'medal_description');
	$form_container->output_row($lang->medal_image . "<em>*</em>", $lang->medal_image_description, $form->generate_text_box('medal_image_path', $mybb->input['medal_image_path'], array('id' => 'medal_image_path')), 'medal_image_path');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->medals_add_save);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if ($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("medals", "*", "medal_id='" . $mybb->get_input('id', MyBB::INPUT_INT) . "'");
	$medal = $db->fetch_array($query);

	if (!$medal['medal_id'])
	{
		flash_message($lang->invalid_medal, 'error');
		admin_redirect("index.php?module=user-medals");
	}

	// User clicked no
	if ($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=user-medals");
	}

	if ($mybb->request_method == "post")
	{
		$db->delete_query("medals", "medal_id='{$medal['medal_id']}'");
		$db->delete_query("medals_user", "medal_id='{$medal['medal_id']}'");
		$db->delete_query("medals_user_favorite", "medal_id='{$medal['medal_id']}'");

		// rebuild cache
		rebuild_medals_cache();
		rebuild_medals_user_cache();
		rebuild_medals_user_favorite_cache();

		// Log admin action
		log_admin_action($medal['medal_id'], $medal['medal_name']);

		flash_message($lang->success_medal_deleted, 'success');
		admin_redirect("index.php?module=user-medals");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-medals&amp;action=delete&amp;id={$medal['medal_id']}", $lang->medal_deletion_confirmation);
	}
}

if ($mybb->input['action'] == "assign")
{
	// Fetch medals
	$query = $db->simple_select("medals", "medal_id,medal_name", "", array('order_by' => 'medal_name'));
	$medal_groups = array();
	while ($medal = $db->fetch_array($query))
	{
		$medal_groups[$medal['medal_id']] = $medal['medal_name'];
	}

	// if there's no medals, redirect the user
	if($db->num_rows($query) == 0)
	{
		flash_message($lang->create_medal_notice, 'error');
		admin_redirect("index.php?module=user-medals");
	}

	if ($mybb->request_method == "post")
	{
		$options = array(
			'fields' => array('username', 'usergroup', 'additionalgroups', 'displaygroup'),
		);

		$user = get_user_by_username($mybb->input['username'], $options);

		// Are we searching a user?
		if (is_array($user) && isset($mybb->input['search']))
		{
			$where_sql = 'uid=\'' . (int) $user['uid'] . '\'';
			$where_sql_full = 'WHERE b.uid=\'' . (int) $user['uid'] . '\'';
		}
		else
		{
			if (empty($user['uid']))
			{
				$errors[] = $lang->error_invalid_username;
			}
			else
			{
				$query = $db->simple_select("medals_user", "user_id", "medal_id", "user_id='{$user['uid']}'", "medal_id='{$mybb->input['medal']}'");
				if ($db->fetch_field($query, "medal_user_id"))
				{
					$errors[] = $lang->error_medal_already_assigned;
				}
			}

			if (!trim($mybb->input['medal']))
			{
				$errors[] = $lang->medal_missing;
			}
		}

		if (!$errors)
		{
			$assign_medal = array(
				"medal_id"      => $db->escape_string($mybb->input['medal']),
				'user_id'       => $user['uid'],
				'reason'        => $db->escape_string($mybb->input['reason']),
				'admin_user_id' => (int) $mybb->user['uid'],
				'created_at'    => TIME_NOW,
			);

			$medal = $db->insert_query("medals_user", $assign_medal);

			// rebuild cache
			rebuild_medals_user_cache();

			// Log admin action
			log_admin_action($mybb->input['medal'], $db->fetch_field($db->simple_select("medals", "medal_name", "medal_id={$mybb->input['medal']}"), 'medal_name'), $user['username'], $user['uid']);

			flash_message($lang->success_medal_assigned, 'success');
			admin_redirect("index.php?module=user-medals&amp;action=members");
		}
	}

	$page->add_breadcrumb_item($lang->medals_assign);
	$page->output_header($lang->medals . " - " . $lang->medals_assign);

	$page->output_nav_tabs($sub_tabs, 'assign_medals');
	$form = new Form("index.php?module=user-medals&amp;action=assign", "post");


	if ($errors)
	{
		$page->output_inline_error($errors);
	}

	$mybb->input['username'] = $mybb->get_input('username');
	$mybb->input['medal'] = $mybb->get_input('medal');
	$mybb->input['reason'] = $mybb->get_input('reason');

	if (isset($mybb->input['uid']) && empty($mybb->input['username']))
	{
		$user = get_user($mybb->input['uid']);
		$mybb->input['username'] = $user['username'];
	}


	$form_container = new FormContainer($lang->medals_assign);
	$form_container->output_row($lang->username . "<em>*</em>", $lang->medal_username_desc, $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
	$form_container->output_row($lang->medal . "<em>*</em>", $lang->medal_medal_desc, $form->generate_select_box('medal', $medal_groups, $mybb->input['medal'], array('id' => 'medal')), 'medal');
	$form_container->output_row($lang->reason, $lang->reason_desc, $form->generate_text_area('reason', $mybb->input['reason'], array('id' => 'reason')), 'reason');
	$form_container->end();

	// Autocompletion for usernames
	echo '
	<link rel="stylesheet" href="../jscripts/select2/select2.css">
	<script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1804"></script>
	<script type="text/javascript">
	<!--
	$("#username").select2({
		placeholder: "' . $lang->search_for_a_user . '",
		minimumInputLength: 2,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "../xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term, // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var query = $(element).val();
			if (query !== "") {
				$.ajax("../xmlhttp.php?action=get_users&getone=1", {
					data: {
						query: query
					},
					dataType: "json"
				}).done(function(data) { callback(data); });
			}
		},
	});

  	$(\'[for=username]\').on(\'click\', function(){
		$("#username").select2(\'open\');
		return false;
	});
	// -->
	</script>';

	$buttons[] = $form->generate_submit_button($lang->medals_assign);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if ($mybb->input['action'] == "members")
{
	$page->add_breadcrumb_item($lang->medals_user);
	$page->output_header($lang->medals . " - " . $lang->medals_user);
	$page->output_nav_tabs($sub_tabs, 'users_medals');

	//
	// START PAGINATION & SORTING
	//
	if ($mybb->get_input("page"))
	{
		$activePage = $mybb->get_input("page", MyBB::INPUT_INT);
	}
	else
	{
		$activePage = 1;
	}

	// Grab the amount of pages!
	$medalsUserCache = $cache->read('medals_user');
	$items = is_bool($medalsUserCache) ? '0' : count($medalsUserCache);

	$itemsPerPage = "10";

	$pages = ceil($items / $itemsPerPage);

	if ($activePage > $pages)
	{
		$activePage = $pages;
	}
	if ($activePage < 1)
	{
		$activePage = 1;
	}

	$start = $activePage * $itemsPerPage - $itemsPerPage;

	if ($mybb->get_input("dir"))
	{
		$direction = match ($mybb->get_input("dir"))
		{
			"a" => "ASC",
			"d" => "DESC",
			default => "DESC"
		};
	}
	else
	{
		$direction = "DESC";
	}

	// switch the direction
	$switchDir = $mybb->get_input("dir") == 'a' ? 'd' : 'a';

	if ($mybb->get_input("order"))
	{
		$sortQuery = " ORDER BY ";
		$orderInput = $mybb->get_input("order");
		$sortQuery .= match ($orderInput)
		{
			"member" => "member_username $direction",
			"assignee" => "admin_username $direction",
			"time" => "assigned_at $direction",
			"reason" => "reason $direction",
			"medal" => "medal_id $direction",
			"image" => "medal_image_path $direction",
			default => "id $direction",
		};
	}
	else
	{
		$sortQuery = " ORDER BY id $direction";
		$orderInput = "time";
	}

	$generatePagination = draw_admin_pagination($activePage, $itemsPerPage, $items, "index.php?module=user-medals&action=members&order=" . $orderInput . '&dir=' . $switchDir);
	echo $generatePagination;

	//
	// END PAGINATION & SORTING
	//

	$baseURL = "index.php?module=user-medals&action=members&dir=$switchDir";

	$table = new Table;
	if ($mybb->settings['medal_display5'])
	{
		$table->construct_header($lang->medal_user_avatar, array('width' => '90', 'class' => 'align_center'));
	}
	$table->construct_header("<a href='$baseURL&order=member'>$lang->medal_user</a>", array('width' => '150', 'class' => 'align_center'));
	$table->construct_header("<a href='$baseURL&order=medal'>$lang->medal</a>", array('width' => '200', 'class' => 'align_center'));
	$table->construct_header("<a href='$baseURL&order=image'>$lang->medal_image</a>", array('width' => '200', 'class' => 'align_center'));
	if ($mybb->settings['medal_display6'])
	{
		$table->construct_header($lang->medal_admin_avatar, array('width' => '90', 'class' => 'align_center'));
	}
	$table->construct_header("<a href='$baseURL&order=assignee'>$lang->medal_assigned_by</a>", array('width' => '150', 'class' => 'align_center'));
	$table->construct_header("<a href='$baseURL&order=reason'>$lang->medal_reason</a>", array('width' => '250', 'class' => 'align_center'));
	$table->construct_header("<a href='$baseURL&order=time'>$lang->medal_assigned_at</a>", array('width' => '200', 'class' => 'align_center'));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));

	$query = $db->write_query("
	SELECT medu.medal_user_id as id, 
	       medu.reason, 
	       medu.created_at as assigned_at, 
	       u.uid as member_id, 
	       u.username as member_username,
	       u.avatar as member_avatar,
	       u.avatardimensions as member_avatar_dimensions,
	       u.usergroup as member_usergroup,
	       u.displaygroup as member_displaygroup,
	       a.uid as admin_member_id,
	       a.username as admin_username,
	       a.avatar as admin_avatar,
	       a.avatardimensions as admin_avatar_dimensions,
	       a.usergroup as admin_usergroup,
	       a.displaygroup as admin_displaygroup,
	       med.medal_name, med.medal_image_path, med.medal_id 
	FROM `" . TABLE_PREFIX . "medals_user`
	    AS medu
	    LEFT JOIN `" . TABLE_PREFIX . "users`
	        AS u
	        ON medu.user_id = u.uid
	    LEFT JOIN `" . TABLE_PREFIX . "medals`
	        AS med
	        ON medu.medal_id = med.medal_id
	    LEFT JOIN `" . TABLE_PREFIX . "users` 
	        AS a
	        ON medu.admin_user_id = a.uid
	    " . $sortQuery . " LIMIT " . ' ' . $start . ",$itemsPerPage");

	while ($member_medal = $db->fetch_array($query))
	{
		$memberUsername = build_profile_link(format_name(htmlspecialchars_uni($member_medal['member_username']), $member_medal['member_usergroup'], $member_medal['member_displaygroup']), $member_medal['member_id'], "_blank");
		$adminUsername = build_profile_link(format_name(htmlspecialchars_uni($member_medal['admin_username']), $member_medal['admin_usergroup'], $member_medal['admin_displaygroup']), $member_medal['admin_member_id'], "_blank");

		$avatarDimensions = "70x70";

		$memberAvatar = format_avatar($member_medal['member_avatar'], $member_medal['member_avatar_dimensions'], $avatarDimensions);
		$adminAvatar = format_avatar($member_medal['admin_avatar'], $member_medal['admin_avatar_dimensions'], $avatarDimensions);

		if ($mybb->settings['medal_display5'])
		{
			$table->construct_cell("<img src=\"" . $memberAvatar['image'] . "\" alt=\"\" {$memberAvatar['width_height']} />", array("class" => "align_center"));
		}
		$table->construct_cell("<strong>{$memberUsername}</strong>", array("class" => "align_center"));
		$table->construct_cell("{$member_medal['medal_name']}", array("class" => "align_center"));
		$table->construct_cell("<img style='width:20px;height:auto;' src=\"{$mybb->settings['bburl']}/{$member_medal['medal_image_path']}\" />", array("class" => "align_center"));
		if ($mybb->settings['medal_display6'])
		{
			$table->construct_cell("<img src=\"" . $adminAvatar['image'] . "\" alt=\"\" {$adminAvatar['width_height']} />", array("class" => "align_center"));
		}
		$table->construct_cell("<strong>{$adminUsername}</strong>", array("class" => "align_center"));
		$table->construct_cell("{$member_medal['reason']}", array("class" => "align_center"));
		$table->construct_cell(my_date('relative', $member_medal['assigned_at']), array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-medals&amp;action=revoke&amp;id={$member_medal['id']}&amp;medal_id={$member_medal['medal_id']}\">{$lang->revoke_medal}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-medals&amp;action=editreason&amp;id={$member_medal['id']}\">{$lang->edit_reason}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}

	if ($table->num_rows() == 0)
	{
		$table->construct_cell($lang->medals_users_none, array('colspan' => 10));
		$table->construct_row();
		$no_results = true;
	}

	$table->output("<a href='$baseURL'>$lang->medals_user</a>");

	echo $generatePagination;

	$page->output_footer();
}

if ($mybb->input['action'] == "revoke")
{
	$query = $db->simple_select("medals_user", "medal_user_id, user_id, medal_id", "medal_user_id='" . $mybb->get_input('id', MyBB::INPUT_INT) . "'");
	$medalUser = $db->fetch_array($query);

	// refactor this into an inner join with ^^ above query
	$medalQuery = $db->simple_select("medals", "medal_name", "medal_id='" . $mybb->get_input('medal_id', MyBB::INPUT_INT) . "'");
	$medal = $db->fetch_array($medalQuery);

	$userId = $mybb->get_input('id', MyBB::INPUT_INT);
	$medalId = $mybb->get_input('medal_id', MyBB::INPUT_INT);

	$selectFavoriteMedal = $db->write_query("
	SELECT medal_id, user_id FROM `" . TABLE_PREFIX . "medals_user_favorite`
	WHERE medal_id = $medalId
	AND user_id = $userId
	LIMIT 1
	");

	if (!$medalUser['medal_user_id'])
	{
		flash_message($lang->invalid_assigned_medal, 'error');
		admin_redirect("index.php?module=user-medals&amp;action=members");
	}

	// this line of codes throws the error for some reason if "no" is clicked. Weird. Investigate this.
	/*	if (!$medal['medal_id'])
	{
		flash_message($lang->invalid_medal, 'error');
		admin_redirect("index.php?module=user-medals&amp;action=members");
	}*/

	// User clicked no
	if ($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=user-medals&amp;action=members");
	}

	if ($mybb->request_method == "post")
	{
		$db->delete_query("medals_user", "medal_user_id='{$medalUser['medal_user_id']}'");

		// delete the medal from the member's favorites too, if favorited!
		if ($db->num_rows($selectFavoriteMedal) == 1)
		{
			$db->delete_query("medals_user_favorite", "medal_user_id='" . (int) $medalUser['medal_user_id'] . "' AND medal_id='" . (int) $medal['medal_id']);
		}

		// rebuild cache
		rebuild_medals_user_cache();
		rebuild_medals_user_favorite_cache();

		// log admin action
		log_admin_action($medalUser['medal_id'], $db->fetch_field($db->simple_select("medals", "medal_name", "medal_id={$medalUser['medal_id']}"), 'medal_name'), $db->fetch_field($db->simple_select("users", "username", "uid={$medalUser['user_id']}"), 'username'), $medalUser['user_id']);

		flash_message($lang->success_medal_revoked, 'success');
		admin_redirect("index.php?module=user-medals&amp;action=members");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-medals&amp;action=revoke&amp;id={$medalUser['medal_user_id']}", $lang->medal_revoke_confirmation);
	}
}

if ($mybb->input['action'] == "editreason")
{
	$query = $db->simple_select("medals_user", "*", "medal_user_id='" . $mybb->get_input('id', MyBB::INPUT_INT) . "'");
	$medalUser = $db->fetch_array($query);

	if (!$medalUser['medal_user_id'])
	{
		flash_message($lang->invalid_assigned_medal, 'error');
		admin_redirect("index.php?module=user-medals&amp;action=members");
	}

	if ($mybb->request_method == "post")
	{
		if (!$errors)
		{
			$updatedReason = array(
				"reason" => $db->escape_string($mybb->input['reason']),
			);

			$db->update_query("medals_user", $updatedReason, "medal_user_id='{$medalUser['medal_user_id']}'");

			// rebuild cache
			rebuild_medals_user_cache();

			// log admin action
			log_admin_action($medalUser['medal_id'], $db->fetch_field($db->simple_select("medals", "medal_name", "medal_id={$medalUser['medal_id']}"), 'medal_name'), $db->fetch_field($db->simple_select("users", "username", "uid={$medalUser['user_id']}"), 'username'), $medalUser['user_id'], $mybb->input['reason']);

			flash_message($lang->success_reason_updated, 'success');
			admin_redirect("index.php?module=user-medals&amp;action=members");
		}
	}

	$page->add_breadcrumb_item($lang->edit_reason);
	$page->output_header($lang->medals . " - " . $lang->edit_reason_desc);

	$page->output_nav_tabs($sub_tabs, 'users_medals');
	$form = new Form("index.php?module=user-medals&amp;action=editreason&amp;id={$medalUser['medal_user_id']}", "post");


	if ($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $medalUser);
	}

	$form_container = new FormContainer($lang->edit_reason);
	$form_container->output_row($lang->medal_reason, $lang->reason_desc, $form->generate_text_area('reason', $mybb->input['reason'], array('id' => 'reason')), 'reason');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_reason);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if ($mybb->input['action'] == "statistics")
{
	//
	// THIS PAGE DOES WAY TOO MANY QUERIES! (26 AT LAST CHECK)
	// AS WE'VE CACHED ALL THREE TABLES NOW, REDUCE SOME OF THESE QUERIES SOON!
	//

	$page->add_breadcrumb_item($lang->statistics);
	$page->output_header($lang->medals . " - " . $lang->statistics);
	$page->output_nav_tabs($sub_tabs, 'statistics');

	// medal count
	$medalsCache = $cache->read('medals');
	$medalCount = is_bool($medalsCache) ? '0' : count($medalsCache);

	// most awarded medal
	$mostAwardedMedalQuery = $db->write_query("
	SELECT COUNT(mu.medal_id) as medal_count, 
       m.medal_name 
	FROM `" . TABLE_PREFIX . "medals_user` 
	AS mu
	INNER JOIN `" . TABLE_PREFIX . "medals` 
	AS m
	ON mu.medal_id = m.medal_id
	GROUP BY mu.medal_id
	ORDER BY medal_count DESC
	LIMIT 1;
	");

	if ($db->num_rows($mostAwardedMedalQuery) == 0)
	{
		$mostAwardedMedal = "—";
	}
	else
	{
		$mostAwardedMedal = $db->fetch_field($mostAwardedMedalQuery, 'medal_name');
	}

	// least awarded medal
	$leastAwardedMedalQuery = $db->write_query("
	SELECT COUNT(mu.medal_id) as medal_count, 
       m.medal_name 
	FROM `" . TABLE_PREFIX . "medals_user` 
	AS mu
	INNER JOIN `" . TABLE_PREFIX . "medals` 
	AS m
	ON mu.medal_id = m.medal_id
	GROUP BY mu.medal_id
	ORDER BY medal_count ASC
	LIMIT 1;
	");

	if ($db->num_rows($leastAwardedMedalQuery) == 0)
	{
		$leastAwardedMedal = "—";
	}
	else
	{
		$leastAwardedMedal = $db->fetch_field($leastAwardedMedalQuery, 'medal_name');
	}

	// latest medal created
	$latestMedalCreatedQuery = $db->write_query("
	SELECT medal_name 
	FROM `" . TABLE_PREFIX . "medals` 
	ORDER BY medal_id DESC
	LIMIT 1;
	");

	if ($db->num_rows($latestMedalCreatedQuery) == 0)
	{
		$latestMedalCreated = "—";
	}
	else
	{
		$latestMedalCreated = $db->fetch_field($latestMedalCreatedQuery, 'medal_name');
	}

	// most favorite medal
	$mostFavoriteMedalQuery = $db->write_query("
	SELECT COUNT(muf.medal_id) as medal_count, 
       m.medal_name 
	FROM `" . TABLE_PREFIX . "medals_user_favorite` 
	AS muf
	INNER JOIN `" . TABLE_PREFIX . "medals` 
	AS m
	ON muf.medal_id = m.medal_id
	GROUP BY muf.medal_id
	ORDER BY medal_count ASC
	LIMIT 1;
	");

	if ($db->num_rows($mostFavoriteMedalQuery) == 0)
	{
		$mostFavoriteMedal = "—";
	}
	else
	{
		$mostFavoriteMedal = $db->fetch_field($mostFavoriteMedalQuery, 'medal_name');
	}

	// number of members that have at least 1 medal
	$numberOfMembersWithMedalsQuery = $db->write_query("
	SELECT COUNT(DISTINCT u.username) as member_count
	FROM `" . TABLE_PREFIX . "medals_user` 
	AS mu
	INNER JOIN `" . TABLE_PREFIX . "users` 
	AS u
	ON mu.user_id = u.uid
	ORDER BY member_count ASC;
	");

	if ($db->num_rows($numberOfMembersWithMedalsQuery) == 0)
	{
		$numberOfMembersWithMedals = "—";
	}
	else
	{
		$numberOfMembersWithMedals = $db->fetch_field($numberOfMembersWithMedalsQuery, 'member_count');
	}

	// most awarded member
	$mostAwardedMemberQuery = $db->write_query("
	SELECT COUNT(user_id)
	    as medal_count, 
	       u.username, 
	       u.usergroup,
	       u.displaygroup,
	       u.avatar, 
	       u.uid as user_id,
	       u.avatardimensions,
	       u.lastactive,
	       u.regdate
	FROM `" . TABLE_PREFIX . "medals_user`  as mu
	INNER JOIN `" . TABLE_PREFIX . "users`  as u
	        ON mu.user_id = u.uid
	GROUP BY user_id
	ORDER BY medal_count DESC
	LIMIT 1;
	");

	if ($db->num_rows($mostAwardedMemberQuery) == 0)
	{
		$mostAwardedMember = "—";
	}
	else
	{

		while ($member = $db->fetch_array($mostAwardedMemberQuery))
		{
			$mostAwardedMember = build_profile_link(format_name(htmlspecialchars_uni($member['username']), $member['usergroup'], $member['displaygroup']), $member['user_id'], "_blank");
		}
	}

	// most given out by admin
	$mostAdminGivenOutQuery = $db->write_query("
	SELECT COUNT(admin_user_id)
	    as medal_count, 
	       u.username, 
	       u.usergroup,
	       u.displaygroup,
	       u.avatar, 
	       u.uid as user_id,
	       u.avatardimensions,
	       u.lastactive,
	       u.regdate
	FROM `" . TABLE_PREFIX . "medals_user`  as mu
	    INNER JOIN `" . TABLE_PREFIX . "users`  as u
	        ON mu.user_id = u.uid
	GROUP BY admin_user_id
	ORDER BY medal_count DESC
	LIMIT 1;
	");

	if ($db->num_rows($mostAdminGivenOutQuery) == 0)
	{
		$mostGivenOut = "—";
	}
	else
	{

		while ($member = $db->fetch_array($mostAdminGivenOutQuery))
		{
			$mostGivenOut = build_profile_link(format_name(htmlspecialchars_uni($member['username']), $member['usergroup'], $member['displaygroup']), $member['user_id'], "_blank");
		}
	}

	// most recent medal went to what user?
	$recentMedalUserQuery = $db->write_query("
	SELECT u.username, 
	       u.usergroup,
	       u.displaygroup,
	       u.avatar, 
	       u.uid as user_id,
	       u.avatardimensions,
	       u.lastactive,
	       u.regdate
	FROM `" . TABLE_PREFIX . "medals_user`  as mu
	    INNER JOIN `" . TABLE_PREFIX . "users` as u
	        ON mu.user_id = u.uid
	ORDER BY medal_user_id ASC
	LIMIT 1;
	");

	if ($db->num_rows($recentMedalUserQuery) == 0)
	{
		$recentMember = "—";
	}
	else
	{

		while ($member = $db->fetch_array($recentMedalUserQuery))
		{
			$recentMember = build_profile_link(format_name(htmlspecialchars_uni($member['username']), $member['usergroup'], $member['displaygroup']), $member['user_id'], "_blank");
		}
	}

	// favorite count
	$favoriteCache = $cache->read('medals_user_favorite');
	$favoriteCount = is_bool($favoriteCache) ? '0' : count($favoriteCache);

	$table = new Table;
	$table->construct_header($lang->medal_statistics, array("colspan" => 2));
	$table->construct_header($lang->medal_member_statistics, array("colspan" => 2));

	$table->construct_cell("<strong>{$lang->medal_count}</strong>", array('width' => '25%'));
	$table->construct_cell("$medalCount", array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->member_with_medal_count}</strong>", array('width' => '200'));
	$table->construct_cell("$numberOfMembersWithMedals", array('width' => '200'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->most_awarded_medal}</strong>", array('width' => '25%'));
	$table->construct_cell("$mostAwardedMedal", array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->most_awarded_member}</strong>", array('width' => '200'));
	$table->construct_cell($mostAwardedMember, array('width' => '200'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->least_awarded_medal}</strong>", array('width' => '25%'));
	$table->construct_cell("$leastAwardedMedal", array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->most_given_out_by}</strong>", array('width' => '200'));
	$table->construct_cell($mostGivenOut, array('width' => '200'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->latest_medal_created}</strong>", array('width' => '25%'));
	$table->construct_cell("$latestMedalCreated", array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->latest_member_rewarded}</strong>", array('width' => '200'));
	$table->construct_cell($recentMember, array('width' => '200'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->most_favorited_medal}</strong>", array('width' => '25%'));
	$table->construct_cell("$mostFavoriteMedal", array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->total_members_with_favorites}</strong>", array('width' => '200'));
	$table->construct_cell("$favoriteCount", array('width' => '200'));
	$table->construct_row();

	$table->output($lang->statistics);

	$table = new Table;
	$table->construct_header($lang->member_ranking, array('width' => '100', 'class' => 'align_center'));
	$table->construct_header($lang->medal_count, array('width' => '100', 'class' => 'align_center'));
	if ($mybb->settings['medal_display7'])
	{
		$table->construct_header($lang->medal_user_avatar, array('width' => '100', 'class' => 'align_center'));
	}
	$table->construct_header($lang->medal_user, array('width' => '200', 'class' => 'align_center'));
	$table->construct_header($lang->member_joined_at, array('width' => '150', 'class' => 'align_center'));
	$table->construct_header($lang->member_last_active, array('width' => '150', 'class' => 'align_center'));

	$topMemberMedalHoldersQuery = $db->write_query("
	SELECT COUNT(user_id)
	    as medal_count, 
	       u.username, 
	       u.usergroup,
	       u.displaygroup,
	       u.avatar, 
	       u.uid as user_id,
	       u.avatardimensions,
	       u.lastactive,
	       u.regdate
	FROM `" . TABLE_PREFIX . "medals_user`  as mu
	    INNER JOIN `" . TABLE_PREFIX . "users`  as u
	        ON mu.user_id = u.uid
	GROUP BY user_id
	ORDER BY medal_count DESC
	LIMIT 10;
	");

	$ranking = 0;
	while ($member = $db->fetch_array($topMemberMedalHoldersQuery))
	{
		$topMemberUsername = build_profile_link(format_name(htmlspecialchars_uni($member['username']), $member['usergroup'], $member['displaygroup']), $member['user_id'], "_blank");
		$topMemberAvatar = format_avatar($member['avatar'], $member['avatardimensions'], '90x90');
		$topMedalCount = $member['medal_count'];
		$ranking = ++$ranking;
		$topMemberLastActive = my_date('relative', $member['lastactive']);
		$topMemberRegDate = my_date('relative', $member['regdate']);

		$table->construct_cell("<strong>$ranking</strong>", array('class' => 'align_center'));
		$table->construct_cell("$topMedalCount", array('class' => 'align_center'));
		if ($mybb->settings['medal_display7'])
		{
			$table->construct_cell("<img src=\"" . $topMemberAvatar['image'] . "\" alt=\"\" {$topMemberAvatar['width_height']} />", array("class" => "align_center"));
		}
		$table->construct_cell($topMemberUsername, array('class' => 'align_center'));
		$table->construct_cell($topMemberRegDate, array('class' => 'align_center'));
		$table->construct_cell($topMemberLastActive, array('class' => 'align_center'));
		$table->construct_row();
	}

	if ($table->num_rows() == 0)
	{
		$table->construct_cell($lang->member_medal_rankings_none, array('colspan' => 10));
		$table->construct_row();
		$no_results = true;
	}

	$table->output($lang->member_medal_rankings);

	// SETTINGS TABLE
	$postBitSetting = $mybb->settings['medal_display1'] ? "<span style=\"color: green;\">$lang->medal_setting_enabled</span>" : "<span style=\"color: #C00\">{$lang->medal_setting_disabled}</span>";
	$profileSetting = $mybb->settings['medal_display2'] ? "<span style=\"color: green;\">$lang->medal_setting_enabled</span>" : "<span style=\"color: #C00\">{$lang->medal_setting_disabled}</span>";
	$medalsPageSetting = $mybb->settings['medal_display3'] ? "<span style=\"color: green;\">$lang->medal_setting_enabled</span>" : "<span style=\"color: #C00\">{$lang->medal_setting_disabled}</span>";
	$membersAvatarsSetting = $mybb->settings['medal_display5'] ? "<span style=\"color: green;\">$lang->medal_setting_enabled</span>" : "<span style=\"color: #C00\">{$lang->medal_setting_disabled}</span>";
	$adminAvatarsSetting = $mybb->settings['medal_display6'] ? "<span style=\"color: green;\">$lang->medal_setting_enabled</span>" : "<span style=\"color: #C00\">{$lang->medal_setting_disabled}</span>";
	$statisticsPageAvatarsSetting = $mybb->settings['medal_display7'] ? "<span style=\"color: green;\">$lang->medal_setting_enabled</span>" : "<span style=\"color: #C00\">{$lang->medal_setting_disabled}</span>";

	// get the group page option
	if ($mybb->settings['medal_display4'] == '-1')
	{
		$medalsPageGroupSetting = $lang->medal_page_all_groups;
	}
	elseif ($mybb->settings['medal_display4'] == '')
	{
		$medalsPageGroupSetting = $lang->medal_page_no_groups;
	}
	elseif ($mybb->settings['medal_display4'] == 'all')
	{
		$medalsPageGroupSetting = $lang->medal_page_select_not_configured;
	}
	else
	{
		$ids = $mybb->settings['medal_display4'];

		$groups = [];

		foreach (explode(',', $ids) as $id)
		{
			$query = $db->write_query("SELECT title, gid FROM `" . TABLE_PREFIX . "usergroups` WHERE gid=$id ORDER BY title DESC");

			while ($group = $db->fetch_array($query))
			{
				$groups[] = format_name(htmlspecialchars_uni($group['title']), $group['gid']);
			}
		}

		$medalsPageGroupSetting = implode(', ', $groups);
	}

	// query the admin log
	$adminLogQuery = $db->write_query("
	SELECT a.data as log_data, 
	       a.dateline as log_dateline,
	       u.username as member_name,
	       u.displaygroup as member_display_group_id,
	       u.usergroup as usergroup_id,
	       u.avatar as member_avatar, 
	       u.uid as member_id,
	       u.avatardimensions as member_avatar_dimensions
	FROM `" . TABLE_PREFIX . "adminlog`  AS a
	    INNER JOIN `" . TABLE_PREFIX . "users`  AS u
	        ON a.uid = u.uid
	    INNER JOIN `" . TABLE_PREFIX . "usergroups`  as g
	        ON g.gid = u.usergroup
	WHERE a.module='config-plugins'
	  AND a.action='activate'
	ORDER BY a.dateline ASC 
	");

	if ($db->num_rows($adminLogQuery) > 0)
	{
		while ($logEntry = $db->fetch_array($adminLogQuery))
		{
			if (preg_match('/\b(medals)\b/', $logEntry['log_data']))
			{
				$activationDate = my_date('relative', $logEntry['log_dateline']);
				$activationUsername = build_profile_link(format_name(htmlspecialchars_uni($logEntry['member_name']), $logEntry['usergroup_id'], $logEntry['member_display_group_id']), $logEntry['member_id'], "_blank");
				//$activationMemberAvatar = format_avatar($logEntry['member_avatar'], $logEntry['member_avatar_dimensions'], '30x30');
			}
			else
			{
				$activationDate = $lang->medals_plugin_activate_unknown;
				$activationUsername = $lang->medals_plugin_activate_unknown;
			}
		}
	}
	else
	{
		$activationDate = $lang->medals_plugin_activate_unknown;
		$activationUsername = $lang->medals_plugin_activate_unknown;
	}

	// groups allowed to manage their favorite medals
	$groupsThatCanManageFavoritesQuery = $db->query("
	SELECT gid 
	FROM `" . TABLE_PREFIX . "usergroups`
	WHERE canmanagefavoritemedals = 1 
	");

	if ($db->num_rows($groupsThatCanManageFavoritesQuery) > 0)
	{
		$groups = [];

		/*while ($group = $db->fetch_array($groupsThatCanManageFavoritesQuery))
		{
			$gIds[] = $group['gid'];
		}

		$Ids = implode(',',  $gIds);

		$yeet = $db->write_query("SELECT title, gid FROM `" . TABLE_PREFIX . "usergroups` WHERE gid IN ({$Ids})");

		while ($group = $db->fetch_array($yeet))
		{
			$groups[] = format_name(htmlspecialchars_uni($group['title']), $group['gid']);
		}*/

		while ($group = $db->fetch_array($groupsThatCanManageFavoritesQuery))
		{
			$gId = $group['gid'];

			$groupQuery = $db->write_query("SELECT title, gid FROM `" . TABLE_PREFIX . "usergroups` WHERE gid=$gId");

			while ($group = $db->fetch_array($groupQuery))
			{
				$groups[] = format_name(htmlspecialchars_uni($group['title']), $group['gid']);
			}
		}

		$groupsThatCanManageFavorites = implode(', ', $groups);
	}
	else
	{
		$groupsThatCanManageFavorites = $lang->none_favorite_groups;
	}

	$table = new Table;
	$table->construct_header($lang->medal_setting, array("colspan" => 1));
	$table->construct_header($lang->medal_value, array("colspan" => 2));
	$table->construct_cell("<strong>{$lang->setting_medal_display1}</strong>", array("colspan" => 1));
	$table->construct_cell($postBitSetting, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_display2}</strong>", array("colspan" => 1));
	$table->construct_cell($profileSetting, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_display3}</strong>", array("colspan" => 1));
	$table->construct_cell($medalsPageSetting, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_display5}</strong>", array("colspan" => 1));
	$table->construct_cell($membersAvatarsSetting, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_display6}</strong>", array("colspan" => 1));
	$table->construct_cell($adminAvatarsSetting, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_display7}</strong>", array("colspan" => 1));
	$table->construct_cell($statisticsPageAvatarsSetting, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_limit1}</strong>", array("colspan" => 1));
	$table->construct_cell($mybb->settings['medal_limit1'] . ' ' . $lang->medals, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_limit2}</strong>", array("colspan" => 1));
	$table->construct_cell($mybb->settings['medal_limit2'] . ' ' . $lang->medals, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->setting_medal_display4}</strong>", array("colspan" => 1));
	$table->construct_cell($medalsPageGroupSetting, array("colspan" => 2));
	$table->construct_row();
	$table->output($lang->medal_settings);

	$table = new Table;
	$table->construct_header($lang->medal_setting, array("colspan" => 1));
	$table->construct_header($lang->medal_value, array("colspan" => 2));
	$table->construct_cell("<strong>{$lang->medals_plugin_activated}</strong>", array("colspan" => 2));
	$table->construct_cell($activationDate ?? $lang->medals_plugin_activate_unknown, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->medals_plugin_activated_member}</strong>", array("colspan" => 2));
	$table->construct_cell($activationUsername ?? $lang->medals_plugin_activate_unknown, array("colspan" => 2));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->allowed_favorite_groups}</strong>", array("colspan" => 2));
	$table->construct_cell($groupsThatCanManageFavorites, array("colspan" => 1));
	$table->construct_row();
	$table->output($lang->plugin_details);

	$page->output_footer();
}