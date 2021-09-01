<?php

/**
 * Rebuilds the medals cache using data from the medals table.
 */
function rebuild_medals_cache()
{
	global $db, $cache;

	$cacheQuery = $db->simple_select("medals");

	$ms = array();
	while ($m = $db->fetch_array($cacheQuery))
	{
		$ms[$m['medal_id']] = $m;
	}

	$cache->update("medals", $ms);
}

/**
 * Rebuilds the medals users cache from the medals user table.
 */
function rebuild_medals_user_cache()
{
	global $db, $cache;

	$cacheQuery = $db->simple_select("medals_user");

	$mu = array();
	while ($m = $db->fetch_array($cacheQuery))
	{
		$mu[$m['medal_user_id']] = $m;
	}

	$cache->update("medals_user", $mu);
}

/**
 * Rebuilds the medals users cache from the medals user favorite table.
 */
function rebuild_medals_user_favorite_cache()
{
	global $db, $cache;

	$cacheQuery = $db->simple_select("medals_user_favorite");

	$muf = array();
	while ($m = $db->fetch_array($cacheQuery))
	{
		$muf[$m['medals_user_favorite_id']] = $m;
	}

	$cache->update("medals_user_favorite", $muf);
}