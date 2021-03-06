<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=page.list.loop
Tags=page.list.tpl:{LIST_ROW_TAGS_ROW_TAG},{LIST_ROW_TAGS_ROW_URL},{LIST_ROW_NO_TAGS}
[END_COT_EXT]
==================== */

/**
 * Displays tags in list row
 *
 * @package tags
 * @version 0.7.0
 * @author Trustmaster - Vladimir Sibirov
 * @copyright Copyright (c) Cotonti Team 2008-2011
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL');

if ($cfg['plugin']['tags']['pages'])
{
	require_once cot_incfile('tags', 'plug');
	if (cot_plugin_active('i18n') && $i18n_enabled && $i18n_notmain)
	{
		$tags_extra = array('tag_locale' => $i18n_locale);
	}
	else
	{
		$tags_extra = null;
	}
	$item_id = $pag['page_id'];
	$tags = cot_tag_list($item_id, 'pages', $tags_extra);
	if (count($tags) > 0)
	{
		$tag_i = 0;
		foreach ($tags as $tag)
		{
			$tag_u = $cfg['plugin']['tags']['translit'] ? cot_translit_encode($tag) : $tag;
			$tl = $lang != 'en' && $tag_u != $tag ? 1 : null;
			$t->assign(array(
				'LIST_ROW_TAGS_ROW_TAG' => $cfg['plugin']['tags']['title'] ? htmlspecialchars(cot_tag_title($tag)) : htmlspecialchars($tag),
				'LIST_ROW_TAGS_ROW_URL' => cot_url('plug', array('e' => 'tags', 'a' => 'pages', 't' => $tag_u, 'tl' => $tl))
			));
			$t->parse('MAIN.LIST_ROW.LIST_ROW_TAGS_ROW');
			$tag_i++;
		}
	}
	else
	{
		$t->assign(array(
			'LIST_ROW_NO_TAGS' => $L['tags_Tag_cloud_none']
		));
		$t->parse('MAIN.LIST_ROW.PAGE_NO_TAGS');
	}
}
?>
