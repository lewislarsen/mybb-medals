<?php

global $page, $mybb, $lang, $errors, $db, $settings;

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

if (!$mybb->input['action'])
{
	$page->output_header($lang->medals);
	$page->output_nav_tabs($sub_tabs, 'medals');

	$table = new Table;
	$table->construct_header($lang->medal_name);
	$table->construct_header($lang->medal_description);
	$table->construct_header($lang->medal_image, array('width' => '250', 'class' => 'align_center'));
	$table->construct_header($lang->medal_created_at, array('width' => '200', 'class' => 'align_center'));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));

	$query = $db->simple_select("medals", "*", "");

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
		$table->construct_cell($lang->medals_none, array('colspan' => 3));
		$table->construct_row();
		$no_results = true;
	}

	$table->output($lang->medals);

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

			/*			// Log admin action
						log_admin_action($utid, $mybb->input['title'], $mybb->input['posts']);*/

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

			// Log admin action
			//log_admin_action($medal['medal_id'], $mybb->input['medal_name'], $mybb->input['medal_image_path']);

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

		// Log admin action
		//log_admin_action($medal['medal_id'], $medal['medal_name']);

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

			//print_r($assign_medal);

			$medal = $db->insert_query("medals_user", $assign_medal);

			/*			// Log admin action
						log_admin_action($utid, $mybb->input['title'], $mybb->input['posts']);*/

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

	$table = new Table;
	if ($mybb->settings['medal_display5'])
	{
		$table->construct_header($lang->medal_user_avatar, array('width' => '90', 'class' => 'align_center'));
	}
	$table->construct_header($lang->medal_user, array('width' => '150', 'class' => 'align_center'));
	$table->construct_header($lang->medal, array('width' => '200', 'class' => 'align_center'));
	$table->construct_header($lang->medal_image, array('width' => '200', 'class' => 'align_center'));
	if ($mybb->settings['medal_display6'])
	{
		$table->construct_header($lang->medal_admin_avatar, array('width' => '90', 'class' => 'align_center'));
	}
	$table->construct_header($lang->medal_assigned_by, array('width' => '150', 'class' => 'align_center'));
	$table->construct_header($lang->medal_reason, array('width' => '250', 'class' => 'align_center'));
	$table->construct_header($lang->medal_assigned_at, array('width' => '200', 'class' => 'align_center'));
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
	ORDER BY medu.medal_user_id DESC
	");

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
		$table->construct_cell("<img style='width:30px;height:auto;' src=\"{$mybb->settings['bburl']}/{$member_medal['medal_image_path']}\" />", array("class" => "align_center"));
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

	$table->output($lang->medals_user);

	$page->output_footer();
}

if ($mybb->input['action'] == "revoke")
{
	$query = $db->simple_select("medals_user", "medal_user_id", "medal_user_id='" . $mybb->get_input('id', MyBB::INPUT_INT) . "'");
	$medalUser = $db->fetch_array($query);

	// refactor this into an inner join with ^^ above query
	$medalQuery = $db->simple_select("medals", "medal_id", "medal_id='" . $mybb->get_input('medal_id', MyBB::INPUT_INT) . "'");
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
				"reason"  => $db->escape_string($mybb->input['reason']),
			);

			$db->update_query("medals_user", $updatedReason, "medal_user_id='{$medalUser['medal_user_id']}'");

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