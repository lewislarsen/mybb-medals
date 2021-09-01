<?php

const IN_MYBB = 1;

require_once './global.php';
require_once MYBB_ROOT . "inc/functions_user.php";

global $mybb, $cache, $settings, $templates, $lang;

$lang->load('medals');

$templatelist = 'medal_page_row,medal_page_row_none,medal_page_view';

if (!is_member($mybb->settings['medal_display4']))
{
	error_no_permission();
}

if ($mybb->settings['medal_display3'] == 0)
{
	redirect("index.php", $lang->medal_page_redirect_notice_message, $lang->medal_page_redirect_notice, true);
}

add_breadcrumb($lang->medal_base_breadcrumb, "medals.php");

$medalPage = "";
$medalRows = "";
$medalRowsNone = "";

$medals = $cache->read("medals");

if (empty($medals))
{
	eval("\$medalRowsNone .= \"" . $templates->get("medal_page_row_none") . "\";");
}
else
{
	foreach ($medals as $medal)
	{
		$id = (int) $medal['medal_id'];
		$name = (string) $medal['medal_name'];
		$image = (string) $medal['medal_image_path'];
		$date = my_date('relative', $medal['created_at']);

		eval("\$medalRows .= \"" . $templates->get("medal_page_row") . "\";");
	}
}

eval("\$medalPage = \"" . $templates->get("medal_page_view") . "\";");
output_page($medalPage);