<?PHP

/* ====================
Seditio - Website engine
Copyright Neocrome
http://www.neocrome.net
[BEGIN_SED]
File=system/functions.php
Version=125
Updated=2008-may-26
Type=Core
Author=Neocrome
Description=Functions
[END_SED]
==================== */

/**
 * @package Seditio-N
 * @version 0.0.1
 * @copyright Partial copyright (c) 2008 Cotonti Team
 * @license BSD License
 */

if (!defined('SED_CODE')) { die('Wrong URL.'); }

// Group constants
define('SED_GROUP_GUESTS', 1);
define('SED_GROUP_INACTIVE', 2);
define('SED_GROUP_BANNED', 3);
define('SED_GROUP_MEMBERS', 4);
define('SED_GROUP_TOPADMINS', 5);

/* ======== Pre-sets ========= */

$out = array();
$plu = array();
$sys = array();
$usr = array();

$i = explode(' ', microtime());
$sys['starttime'] = $i[1] + $i[0];

//unset ($warnings, $moremetas, $morejavascript, $error_string,  $sed_cat, $sed_smilies, $sed_acc, $sed_catacc, $sed_rights, $sed_config, $sql_config, $sed_usersonline, $sed_plugins, $sed_groups, $rsedition, $rseditiop, $rseditios, $tcount, $qcount)

$cfg['version'] = 'N-0.0.1';
$cfg['dbversion'] = 'N-0.0.1';

/**
 * Strips everything but alphanumeric, hyphens and underscores
 *
 * @param string $text Input
 * @return string
 */
function sed_alphaonly($text)
{
	return(preg_replace('/[^a-zA-Z0-9\-_]/', '', $text));
}

/*
 * ================================= Authorization Subsystem ==================================
 */

/**
 * Returns specific access permissions
 *
 * @param string $area Seditio area
 * @param string $option Option to access
 * @param string $mask Access mask
 * @return mixed
 */
function sed_auth($area, $option, $mask='RWA')
{
	global $sys, $usr;

	$mn['R'] = 1;
	$mn['W'] = 2;
	$mn['1'] = 4;
	$mn['2'] = 8;
	$mn['3'] = 16;
	$mn['4'] = 32;
	$mn['5'] = 64;
	$mn['A'] = 128;

	$masks = str_split($mask);
	$res = array();

	foreach($masks as $k => $ml)
	{
		if(empty($mn[$ml]))
		{
			$sys['auth_log'][] = $area.".".$option.".".$ml."=0";
			$res[] = FALSE;
		}
		elseif ($option=='any')
		{
			$cnt = 0;

			if(is_array($usr['auth'][$area]))
			{
				foreach($usr['auth'][$area] as $k => $g)
				{ $cnt += (($g & $mn[$ml]) == $mn[$ml]); }
			}
			$cnt = ($cnt==0 && $usr['auth']['admin']['a'] && $ml=='A') ? 1 : $cnt;

			$sys['auth_log'][] = ($cnt>0) ? $area.".".$option.".".$ml."=1" : $area.".".$option.".".$ml."=0";
			$res[] = ($cnt>0) ? TRUE : FALSE;
		}
		else
		{
			$sys['auth_log'][] = (($usr['auth'][$area][$option] & $mn[$ml]) == $mn[$ml]) ? $area.".".$option.".".$ml."=1" : $area.".".$option.".".$ml."=0";
			$res[] = (($usr['auth'][$area][$option] & $mn[$ml]) == $mn[$ml]) ? TRUE : FALSE;
		}
	}
	return (count($res) == 1) ? $res[0]: $res;
}

/**
 * Builds Access Control List (ACL) for a specific user
 *
 * @param int $userid User ID
 * @param int $maingrp User main group
 * @return array
 */
function sed_auth_build($userid, $maingrp=0)
{
	global $db_auth, $db_groups_users;

	$groups = array();
	$authgrid = array();
	$tmpgrid = array();

	if ($userid==0 || $maingrp==0)
	{
		$groups[] = 1;
	}
	else
	{
		$groups[] = $maingrp;
		$sql = sed_sql_query("SELECT gru_groupid FROM $db_groups_users WHERE gru_userid='$userid'");

		while ($row = sed_sql_fetcharray($sql))
		{ $groups[] = $row['gru_groupid']; }
	}

	$sql_groups = implode(',', $groups);
	$sql = sed_sql_query("SELECT auth_code, auth_option, auth_rights FROM $db_auth WHERE auth_groupid IN (".$sql_groups.") ORDER BY auth_code ASC, auth_option ASC");

	while ($row = sed_sql_fetcharray($sql))
	{ $authgrid[$row['auth_code']][$row['auth_option']] |= $row['auth_rights']; }

	return($authgrid);
}

/**
 * Clears user permissions cache
 *
 * @param mixed $id User ID or 'all'
 * @return int
 */
function sed_auth_clear($id='all')
{
	global $db_users;

	if($id=='all')
	{
		$sql = sed_sql_query("UPDATE $db_users SET user_auth='' WHERE 1");
	}
	else
	{
		$sql = sed_sql_query("UPDATE $db_users SET user_auth='' WHERE user_id='$id'");
	}
	return sed_sql_affectedrows();
}

/**
 * Block user if he is not allowed to access the page
 *
 * @param bool $allowed Authorization result
 * @return bool
 */
function sed_block($allowed)
{
	if(!$allowed)
	{
		global $sys;
		header("Location: " . SED_ABSOLUTE_URL . sed_url('message', "msg=930&".$sys['url_redirect'], '', true));
		exit;
	}
	return FALSE;
}


/**
 * Block guests from viewing the page
 *
 * @return bool
 */
function sed_blockguests()
{
	global $usr, $sys;

	if ($usr['id']<1)
	{
		header("Location: " . SED_ABSOLUTE_URL . sed_url('message', "msg=930&".$sys['url_redirect'], '', true));
		exit;
	}
	return FALSE;
}


/*
 * ================================= BBCode Parser API ==================================
 */

/**
 * Registers a new bbcode in database.
 * In 'callback' mode $replacement is normal PHP function body (without declaration) which
 * takes $input array of matches as parameter and must return a replacement string. These
 * variables are also imported as globals in callback function: $cfg, $sys, $usr, $L, $skin, $sed_groups
 *
 * @global $db_bbcode;
 * @param string $name BBcode name
 * @param string $mode Parsing mode, on of the following: 'str' (str_replace), 'ereg' (eregi_replace), 'pcre' (preg_replace) and 'callback' (preg_replace_callback)
 * @param string $pattern Bbcode string or entire regular expression
 * @param string $replacement Replacement string or regular substitution or callback body
 * @param bool $container Whether bbcode is container (like [bbcode]Something here[/bbcode])
 * @param int $priority BBcode priority from 0 to 255. Smaller priority bbcodes are parsed first, 128 is default medium priority.
 * @param string $plug Plugin/part name this bbcode belongs to.
 * @param bool $postrender Whether this bbcode must be applied on a pre-rendered HTML cache.
 * @return bool
 */
function sed_bbcode_add($name, $mode, $pattern, $replacement, $container = true, $priority = 128, $plug = '', $postrender = false)
{
	global $db_bbcode;
	$bbc['name'] = $name;
	$bbc['mode'] = $mode;
	$bbc['pattern'] = $pattern;
	$bbc['replacement'] = $replacement;
	$bbc['container'] = empty($container) ? 0 : 1;
	if($priority >= 0 && $priority < 256)
	{
		$bbc['priority'] = (int) $priority;
	}
	if(!empty($plug))
	{
		$bbc['plug'] = $plug;
	}
	$bbc['postrender'] = empty($postrender) ? 0 : 1;
	return sed_sql_insert($db_bbcode, $bbc, 'bbc_') == 1;
}

/**
 * Removes a bbcode from parser database.
 *
 * @global $db_bbcode
 * @param int $id BBCode ID or 0 to remove all (use carefully)
 * @param string $plug Remove all bbcodes that belong to this plug
 * @return bool
 */
function sed_bbcode_remove($id = 0, $plug = '')
{
	global $db_bbcode;
	if($id > 0)
	{
		return sed_sql_delete($db_bbcode, "bbc_id = $id") == 1;
	}
	elseif(!empty($plug))
	{
		return sed_sql_delete($db_bbcode, "bbc_plug = '" . sed_sql_prep($plug) . "'");
	}
	else
	{
		return sed_sql_delete($db_bbcode) > 0;
	}
}

/**
 * Updates bbcode data in parser database.
 *
 * @global $db_bbcode;
 * @param int $id BBCode ID
 * @param bool $enabled Enable the bbcode
 * @param string $name BBcode name
 * @param string $mode Parsing mode, on of the following: 'str' (str_replace), 'ereg' (eregi_replace), 'pcre' (preg_replace) and 'callback' (preg_replace_callback)
 * @param string $pattern Bbcode string or entire regular expression
 * @param string $replacement Replacement string or regular substitution or callback body
 * @param bool $container Whether bbcode is container (like [bbcode]Something here[/bbcode])
 * @param int $priority BBcode preority from 0 to 255. Smaller priority bbcodes are parsed first, 128 is default medium priority.
 * @param bool $postrender Whether this bbcode must be applied on a pre-rendered HTML cache.
 * @return bool
 */
function sed_bbcode_update($id, $enabled, $name, $mode, $pattern, $replacement, $container, $priority = 128, $postrender = false)
{
	global $db_bbcode;
	$bbc['enabled'] = empty($enabled) ? 0 : 1;
	if(!empty($name))
	{
		$bbc['name'] = $name;
	}
	if(!empty($mode))
	{
		$bbc['mode'] = $mode;
	}
	if(!empty($pattern))
	{
		$bbc['pattern'] = $pattern;
	}
	if(!empty($replacement))
	{
		$bbc['replacement'] = $replacement;
	}
	if($priority >= 0 && $priority < 256)
	{
		$bbc['priority'] = $priority;
	}
	$bbc['container'] = empty($container) ? 0 : 1;
	$bbc['postrender'] = empty($postrender) ? 0 : 1;
	return sed_sql_update($db_bbcode, "bbc_id = $id", $bbc, 'bbc_') == 1;
}

/**
 * Loads bbcodes from database if they havent been already loaded.
 *
 * @global $sed_bbcodes
 * @global $db_bbcode
 */
function sed_bbcode_load()
{
	global $db_bbcode, $sed_bbcodes, $sed_bbcodes_post, $sed_bbcode_containers;
	if(!is_array($sed_bbcodes))
	{
		$sed_bbcodes = array();
		$sed_bbcodes_post = array();
		$sed_bbcode_containers = ''; // required for auto-close
		$bbc_cntr = array();
		$i = 0;
		$j = 0;
		$res = sed_sql_query("SELECT * FROM $db_bbcode WHERE bbc_enabled = 1 ORDER BY bbc_priority");
		while($row = sed_sql_fetchassoc($res))
		{
			if($row['bbc_postrender'] == 1)
			{
				foreach($row as $key => $val)
				{
					$sed_bbcodes_post[$j][str_replace('bbc_', '', $key)] = $val;
				}
				$j++;
			}
			else
			{
				foreach($row as $key => $val)
				{
					$sed_bbcodes[$i][str_replace('bbc_', '', $key)] = $val;
				}
				$i++;
			}
			if($row['bbc_container'] == 1 && !isset($bbc_cntr[$row['bbc_name']]))
			{
				$sed_bbcode_containers .= $row['bbc_name'] . '|';
				$bbc_cntr[$row['bbc_name']] = 1;
			}
		}
		sed_sql_freeresult($res);
		if(!empty($sed_bbcode_containers))
		{
			$sed_bbcode_containers = mb_substr($sed_bbcode_containers, 0, -1);
		}
	}
}

/**
 * Parses bbcodes in text.
 *
 * @global $sed_bbcodes
 * @param string $text Text body
 * @param bool $post Post-rendering
 * @return string
 */
function sed_bbcode_parse($text, $post = false)
{
	global $cfg, $sed_bbcodes, $sed_bbcodes_post, $sed_bbcode_containers;
	if($cfg['parser_custom'])
	{
		return $text;
	}

	// BB auto-close
	$bbc = array();
	if(!$post && preg_match_all('#\[(/)?('.$sed_bbcode_containers.')(=[^\]]*)?\]#i', $text, $mt, PREG_SET_ORDER))
	{
		// Count all unclosed bbcode entries
		for($i = 0, $cnt = count($mt); $i < $cnt; $i++)
		{
				if($mt[$i][1] == '/')
				{
					$bb = $mt[$i][2];
					// Protect from "[/foo] [/bar][foo][bar]" trick
					if($bbc[$bb] > 0) $bbc[$bb]--;
					// else echo 'ERROR: invalid closing bbcode detected';
				}
				else
				{
					// Count opening tag in
					$bbc[$mt[$i][2]]++;
				}
		}
		// Close all unclosed tags. Produces non XHTML-compliant output
		// (doesn't take tag order and semantics into account) but fixes the layout
		if(count($bbc) > 0)
		{
			foreach($bbc as $bb => $c)
			{
				$text .= str_repeat("[/$bb]", $c);
			}
		}
	}
	// Done, ready to parse bbcodes
	$cnt = $post ? count($sed_bbcodes_post) : count($sed_bbcodes);
	for($i = 0; $i < $cnt; $i++)
	{
		$bbcode = ($post) ? $sed_bbcodes_post[$i] : $sed_bbcodes[$i];
		switch($bbcode['mode'])
		{
			case 'str':
				$text = str_ireplace($bbcode['pattern'], $bbcode['replacement'], $text);
			break;

			case 'ereg':
				$text = mb_eregi_replace($bbcode['pattern'], $bbcode['replacement'], $text);
			break;

			case 'pcre':
				$text = preg_replace('`'.$bbcode['pattern'].'`mis', $bbcode['replacement'], $text);
			break;

			case 'callback':
				$code = 'global $cfg, $sys, $usr, $L, $skin, $sed_groups;' . $bbcode['replacement'];
				$text = preg_replace_callback('`'.$bbcode['pattern'].'`mis', create_function('$input', $code), $text);
			break;
		}
	}

	return $text;
}

/**
 * Neutralizes bbcodes in text
 *
 * @param string $text Source text
 * @return string
 */
function sed_bbcode_cdata($text)
{
	$res = $text;
	//$res = preg_replace('`&(?!amp;)`i', '&amp;$1', $res);
	$res = str_replace('[', '&#091;', $res);
	$res = str_replace(']', '&#093;', $res);
	return $res;
}

/**
 * JavaScript HTML obfuscator to protect some parts (like email) from bots
 *
 * @param string $text Source text
 * @return string
 */
function sed_obfuscate($text)
{
	static $calls = 0;
	$enc_string = '';
	$length = mb_strlen($text);
	for ($i=0; $i < $length; $i++) {
		$inter = ord($text[$i]) + 3;
		$enc_char =  chr($inter);
		$enc_string .= ($enc_char == '\\' ? '\\\\' : $enc_char);
	}
	// get a random string to use as a function name
	srand((float) microtime() * 10000000);
	$letters = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
	$rnd = $letters[array_rand($letters)] . md5(time());
	// the actual js (in one line to confuse)
	if($calls++ == 0) $var = 'var a,s,n;';
	$script = "<script language=\"JavaScript\" type=\"text/javascript\">{$var}function $rnd(s){r='';for(i=0;i<s.length;i++){n=s.charCodeAt(i);if(n>=8364){n=128;}r+=String.fromCharCode(n-3);}return r;}a='".$enc_string."';document.write($rnd(a));</script>";
	return $script;
}

/**
 * Supplimentary email obfuscator callback
 *
 * @param array $m PCRE entry
 * @return string
 */
function sed_obfuscate_eml($m)
{
	return $m[1].sed_obfuscate('<a href="mailto:'.$m[2].'">'.$m[2].'</a>');
}


/**
 * Automatically detect and parse URLs in text into HTML
 *
 * @param string $text Text body
 * @return string
 */
function sed_parse_autourls($text)
{
	$text = preg_replace('`(^|\s)(http|https|ftp)://([^\s"\'\[]+)`', '$1<a href="$2://$3">$2://$3</a>', $text);
	$text = preg_replace_callback('`(^|\s)(\w[\._\w\-]+@[\w\.\-]+\.[a-z]+)`', 'sed_obfuscate_eml', $text);
	return $text;
}

/**
 * Supplimentary br stripper callback
 *
 * @param array $m PCRE entries
 * @return string
 */
function sed_parse_pre($m)
{
	return str_replace('<br />', '', $m[0]);
}

/**
 * Parses text body
 *
 * @param string $text Source text
 * @param bool $parse_bbcodes Enable bbcode parsing
 * @param bool $parse_smilies Enable emoticons
 * @param bool $parse_newlines Replace line breaks with <br />
 * @param bool $parse_tabs Parse page into tabs by [newpage] codes
 * @return string
 */
function sed_parse($text, $parse_bbcodes = TRUE, $parse_smilies = TRUE, $parse_newlines = TRUE, $parse_tabs = FALSE)
{
	global $cfg, $sys, $sed_smilies, $L, $usr;

	if($cfg['parser_custom'])
	{
		include_once $cfg['system_dir'].'/parser.php';
		if(function_exists('sed_custom_parse'))
		{
			return sed_custom_parse($text, $parse_bbcodes, $parse_smilies, $parse_newlines);
		}
	}

	if($parse_tabs)
	{
		$page_tabs = explode('[newpage]', $text, 20);
		$totaltabs = count($page_tabs);
		if($totaltabs > 1)
		{
			$tabs_html = '<div class="tabs">';
			$head_html = '<ul class="tabs-head">';
			$page_html = '';
			$pag['page_tabtitles'] = array();
			$tabs_id = sed_unique(8);

			for($i = 0; $i < $totaltabs; $i++)
			{
				$p1 = mb_stripos($page_tabs[$i], '[title]');
				$p2 = mb_stripos($page_tabs[$i], '[/title]');
				$tab_id = $tabs_id . $i;
				if($p2 > $p1 && $p1 < 4)
				{
					$tab_title = mb_substr($page_tabs[$i], $p1+7, ($p2-$p1)-7);
					$page_tabs[$i] = trim(str_replace('[title]'.$tab_title.'[/title]', '', $page_tabs[$i]));
				}
				else
				{
					$tab_title = $i;
				}

				$head_html .= '<li><a href="#tabs-'.$tab_id.'">'.sed_cc($tab_title).'</a></li>';
				$page_tabs[$i] = trim(str_replace('[newpage]', '', $page_tabs[$i]));
				$page_html .= '<div id="tabs-'.$tab_id.'">'.sed_bbcode_parse($page_tabs[$i]).'</div>';
			}

			$tabs_html .= $head_html . '</ul>' . $page_html . '</div>';
			return $tabs_html;
		}
	}

	$text = ' '.$text;
	$code = array();
	$unique_seed = $sys['unique'];
	$ii = 10000;

	$text = sed_parse_autourls($text);

	if($parse_smilies && is_array($sed_smilies))
	{
		foreach($sed_smilies as $k => $v)
		{
			$ii++;
			$key = '**'.$ii.$unique_seed.'**';
			$code[$key]= '<img class="aux smiley" src="./images/smilies/'.$v['file'].'" alt="'.sed_cc($v['code']).'" />';
			$text = preg_replace('#(^|\s)'.preg_quote($v['code']).'(\s|$)#', '$1'.$key.'$2', $text);
			if(sed_cc($v['code']) != $v['code'])
			{
				// Fix for cc inserts
				$text = preg_replace('#(^|\s)'.preg_quote(sed_cc($v['code'])).'(\s|$)#', '$1'.$key.'$2', $text);
			}
		}
	}

	if($parse_bbcodes)
	{
		$text = sed_bbcode_parse($text);
	}

	if ($parse_bbcodes || $parse_smilies)
	{
		foreach($code as $x => $y)
		{ $text = str_replace($x, $y, $text); }
	}

	if ($parse_newlines)
	{
		$text = nl2br($text);
		$text = str_replace("\r", '', $text);
		// Strip extraneous breaks
		$text = preg_replace('#<(/?)(p|hr|ul|ol|li|blockquote|table|tr|td|th)(.*?)>(\s*)<br />#', '<$1$2$3>', $text);
		$text = preg_replace_callback('#<pre[^>]*>(.+?)</pre>#sm', 'sed_parse_pre', $text);
	}

	return mb_substr($text, 1);
}

/**
 * Post-render parser function
 *
 * @param string $text Text body
 * @param string $area Site area to check bbcode enablement
 * @return string
 */
function sed_post_parse($text, $area = '')
{
	global $cfg;
	if(!$cfg['parser_custom'] && (empty($area) || $cfg["parsebbcode$area"]))
	{
		return sed_bbcode_parse($text, true);
	}
	return $text;
}

/*
 * =========================== Output forming functions ===========================
 */

/* ------------------ */
// TODO eliminate this function
function sed_build_addtxt($c1, $c2)
{
	$result = "
	function addtxt(text)
	{
	document.".$c1.".".$c2.".value  += text;
	document.".$c1.".".$c2.".focus();
	}
	";
	return($result);
}

/**
 * Calculates age out of D.O.B.
 *
 * @param int $birth Date of birth as UNIX timestamp
 * @return int
 */
function sed_build_age($birth)
{
	global $sys;

	if ($birth==1)
	{ return ('?'); }

	$day1 = @date('d', $birth);
	$month1 = @date('m', $birth);
	$year1 = @date('Y', $birth);

	$day2 = @date('d', $sys['now_offset']);
	$month2 = @date('m', $sys['now_offset']);
	$year2 = @date('Y', $sys['now_offset']);

	$age = ($year2-$year1)-1;

	if ($month1<$month2 || ($month1==$month2 && $day1<=$day2))
	{ $age++; }

	if($age < 0)
	{ $age += 136; }

	return ($age);
}

/* ------------------ */
// TODO eliminate this function
function sed_build_bbcodes($c1, $c2, $title)
{
	//$result = "<a href=\"javascript:help('bbcodes','".$c1."','".$c2."')\">".$title."</a>";
	//return($result);
	return '';
}

/* ------------------ */
// TODO eliminate this function
function sed_build_bbcodes_local($limit)
{
	/*global $sed_bbcodes;

	reset ($sed_bbcodes);

	$result = '<div class="bbcodes">';

	while (list($i,$dat)=each($sed_bbcodes))
	{
		$kk = 'bbcodes_'.$dat[1];
		$result .= "<a href=\"javascript:addtxt('".$dat[0]."')\"><img src=\"images/bbcodes/".$dat[1].".gif\" alt=\"\" /></a> ";
	}

	$result .= "</div>";
	return($result);*/
	return '';
}

/**
 * Builds category path
 *
 * @param string $cat Category code
 * @param string $mask Format mask
 * @return string
 */
function sed_build_catpath($cat, $mask)
{
	global $sed_cat, $cfg;
	$mask = str_replace('%25', '%', $mask);
	$mask = str_replace('%24', '$', $mask);
	if($cfg['homebreadcrumb'])
	{
		$tmp[] = '<a href="'.$cfg['mainurl'].'">'.sed_cc($cfg['maintitle']).'</a>';
	}
	$pathcodes = explode('.', $sed_cat[$cat]['path']);
	foreach($pathcodes as $k => $x)
	{
		$tmp[]= sprintf($mask, $x, $sed_cat[$x]['title']);
	}
	return implode(' '.$cfg['separator'].' ', $tmp);
}

/* ------------------ */
// TODO replace with new comments plugin
function sed_build_comments($code, $url, $display)
{
	global $db_com, $db_users, $db_pages, $cfg, $usr, $L, $sys;

	list($usr['auth_read_com'], $usr['auth_write_com'], $usr['isadmin_com']) = sed_auth('comments', 'a');
	sed_block($usr['auth_read_com']);

	if ($cfg['disable_comments'] || !$usr['auth_read_com'])
	{ return (array('',''));  }

	if ($display)
	{
		$ina = sed_import('ina','G','ALP');
		$ind = sed_import('ind','G','INT');

		$d = sed_import('d', 'G', 'INT');
		$d = empty($d) ? 0 : (int) $d;

		if ($ina=='send' && $usr['auth_write_com'])
		{
			sed_shield_protect();
			$rtext = sed_import('rtext','P','HTM');

			/* == Hook for the plugins == */
			$extp = sed_getextplugins('comments.send.first');
			if (is_array($extp))
			{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
			/* ===== */

			$error_string .= (mb_strlen($rtext)<2) ? $L['com_commenttooshort']."<br />" : '';
			$error_string .= (mb_strlen($rtext)>2000) ? $L['com_commenttoolong']."<br />" : '';

			if (empty($error_string))
			{
				$sql = sed_sql_query("INSERT INTO $db_com (com_code, com_author, com_authorid, com_authorip, com_text, com_date) VALUES ('".sed_sql_prep($code)."', '".sed_sql_prep($usr['name'])."', ".(int)$usr['id'].", '".$usr['ip']."', '".sed_sql_prep($rtext)."', ".(int)$sys['now_offset'].")");

				if (mb_substr($code, 0, 1) =='p')
				{
					$page_id = mb_substr($code, 1, 10);
					$sql = sed_sql_query("UPDATE $db_pages SET page_comcount='".sed_get_comcount($code)."' WHERE page_id='".$page_id."'");
				}

				/* == Hook for the plugins == */
				$extp = sed_getextplugins('comments.send.new');
				if (is_array($extp))
				{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
				/* ===== */

				sed_shield_update(20, "New comment");
				header("Location: " . SED_ABSOLUTE_URL . "$url&comments=1"); //needs trustmaster's attention
				exit;
			}
		}

		if ($ina=='delete' && $usr['isadmin_com'])
		{
			sed_check_xg();
			$sql = sed_sql_query("SELECT * FROM $db_com WHERE com_id='$ind' LIMIT 1");

			if ($row = sed_sql_fetchassoc($sql))
			{
				if ($cfg['trash_comment'])
				{ sed_trash_put('comment', $L['Comment']." #".$ind." (".$row['com_author'].")", $ind, $row); }

				$sql = sed_sql_query("DELETE FROM $db_com WHERE com_id='$ind'");

				if (mb_substr($row['com_code'], 0, 1) == 'p')
				{
					$page_id = mb_substr($row['com_code'], 1, 10);
					$sql = sed_sql_query("UPDATE $db_pages SET page_comcount=".sed_get_comcount($row['com_code'])." WHERE page_id=".$page_id);
				}

				sed_log("Deleted comment #".$ind." in '".$code."'",'adm');
			}

			header("Location: " . SED_ABSOLUTE_URL . "".$url."&comments=1");
			exit;
		}

		$error_string .= ($ina=='added') ? $L['com_commentadded']."<br />" : '';

		$t = new XTemplate(sed_skinfile('comments'));

		/* == Hook for the plugins == */
		$extp = sed_getextplugins('comments.main');
		if (is_array($extp))
		{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
		/* ===== */

		$sql = sed_sql_query("SELECT c.*, u.user_avatar FROM $db_com AS c
		LEFT JOIN $db_users AS u ON u.user_id=c.com_authorid
		WHERE com_code='$code' ORDER BY com_id ASC LIMIT $d, ".$cfg['maxrowsperpage']);

		if (!empty($error_string))
		{
			$t->assign("COMMENTS_ERROR_BODY",$error_string);
			$t->parse("COMMENTS.COMMENTS_ERROR");
		}

		if ($usr['auth_write_com'])
		{
			$bbcodes = ($cfg['parsebbcodecom']) ? sed_build_bbcodes("newcomment", "rtext", $L['BBcodes']) : '';
			$smilies = ($cfg['parsesmiliescom']) ? sed_build_smilies("newcomment", "rtext", $L['Smilies']) : '';
			$pfs = ($usr['id']>0) ? sed_build_pfs($usr['id'], "newcomment", "rtext", $L['Mypfs']) : '';
			$pfs .= (sed_auth('pfs', 'a', 'A')) ? " &nbsp; ".sed_build_pfs(0, "newcomment", "rtext", $L['SFS']) : '';
			$post_main = "<textarea class=\"minieditor\" name=\"rtext\" rows=\"4\" cols=\"80\">".$rtext."</textarea><br />".$bbcodes." ".$smilies." ".$pfs;
		}

		$t->assign(array(
			"COMMENTS_CODE" => $code,
			"COMMENTS_FORM_SEND" => $url."&amp;comments=1&amp;ina=send",
			"COMMENTS_FORM_AUTHOR" => $usr['name'],
			"COMMENTS_FORM_AUTHORID" => $usr['id'],
			"COMMENTS_FORM_TEXT" => $post_main,
			"COMMENTS_FORM_TEXTBOXER" => $post_main,
			"COMMENTS_FORM_BBCODES" => $bbcodes,
			"COMMENTS_FORM_SMILIES" => $smilies,
			"COMMENTS_FORM_MYPFS" => $pfs
		));

		if ($usr['auth_write_com'])
		{

			/* == Hook for the plugins == */
			$extp = sed_getextplugins('comments.newcomment.tags');
			if (is_array($extp))
			{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
			/* ===== */

			$t->parse("COMMENTS.COMMENTS_NEWCOMMENT");
		}

		if (sed_sql_numrows($sql)>0)
		{
			$i = $d;

			/* === Hook - Part1 : Set === */
			$extp = sed_getextplugins('comments.loop');
			/* ===== */

			while ($row = sed_sql_fetcharray($sql))
			{
				$i++;
				$com_author = sed_cc($row['com_author']);
				$com_text = sed_cc($row['com_text']);

				$com_admin = ($usr['isadmin_com']) ? $L['Ip'].":".sed_build_ipsearch($row['com_authorip'])." &nbsp;".$L['Delete'].":[<a href=\"".$url."&amp;comments=1&amp;ina=delete&amp;ind=".$row['com_id']."&amp;".sed_xg()."\">x</a>]" : '' ;
				$com_authorlink = ($row['com_authorid']>0) ? "<a href=\"".sed_url('users', 'm=details&id='.$row['com_authorid'])."\">".$com_author."</a>" : $com_author ;

				$t-> assign(array(
					"COMMENTS_ROW_ID" => $row['com_id'],
					"COMMENTS_ROW_ORDER" => $i,
					"COMMENTS_ROW_URL" => $url."&amp;comments=1#c".$row['com_id'],
					"COMMENTS_ROW_AUTHOR" => $com_authorlink,
					"COMMENTS_ROW_AUTHORID" => $row['com_authorid'],
					"COMMENTS_ROW_AVATAR" => sed_build_userimage($row['user_avatar']),
					"COMMENTS_ROW_TEXT" => sed_parse($com_text, $cfg['parsebbcodecom'], $cfg['parsesmiliescom'], 1),
					"COMMENTS_ROW_DATE" => @date($cfg['dateformat'], $row['com_date'] + $usr['timezone'] * 3600),
					"COMMENTS_ROW_ADMIN" => $com_admin,
				));

				/* === Hook - Part2 : Include === */
				if (is_array($extp))
				{ foreach($extp as $k => $pl) { include($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
				/* ===== */

				$t->parse("COMMENTS.COMMENTS_ROW");
			}

			$totalitems = sed_sql_result(sed_sql_query("SELECT COUNT(*) FROM $db_com WHERE com_code='$code'"), 0, 0);
			$pagnav = sed_pagination($url."&amp;comments=1", $d, $totalitems, $cfg['maxrowsperpage']);
			list($pagination_prev, $pagination_next) = sed_pagination_pn($url."&amp;comments=1", $d, $totalitems, $cfg['maxrowsperpage'], TRUE);
			$t->assign(array(
				"COMMENTS_PAGES_INFO" => $L['Total']." : ".$totalitems.", ".$L['comm_on_page'].": ".($i-$d),
				"COMMENTS_PAGES_PAGESPREV" => $pagination_prev,
				"COMMENTS_PAGES_PAGNAV" => $pagnav,
				"COMMENTS_PAGES_PAGESNEXT" => $pagination_next
			));
			$t->parse("COMMENTS.PAGNAVIGATOR");

		}
		else
		{
			$t-> assign(array(
				"COMMENTS_EMPTYTEXT" => $L['com_nocommentsyet'],
			));
			$t->parse("COMMENTS.COMMENTS_EMPTY");
		}

		/* == Hook for the plugins == */
		$extp = sed_getextplugins('comments.tags');
		if (is_array($extp))
		{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
		/* ===== */

		$t->parse("COMMENTS");
		$res_display = $t->text("COMMENTS");
	}
	else
	{
		$res_display = '';
	}

	$res = "<a href=\"".$url."&amp;comments=1\"><img src=\"skins/".$usr['skin']."/img/system/icon-comment.gif\" alt=\"\" />";

	if ($cfg['countcomments'])
	{
		$nbcomment = sed_sql_result(sed_sql_query("SELECT COUNT(*) FROM $db_com where com_code='$code'"), 0, "COUNT(*)");
		$res .= " (".$nbcomment.")";
	}
	$res .= "</a>";

	return(array($res, $res_display, $nbcomment));
}

/**
 * Returns country text button
 *
 * @param string $flag Country code
 * @return string
 */
function sed_build_country($flag)
{
	global $sed_countries;

	$flag = (empty($flag)) ? '00' : $flag;
	return '<a href="'.sed_url('users', 'f=country_'.$flag).'">'.$sed_countries[$flag].'</a>';
}

/**
 * Returns user email link
 *
 * @param string $email E-mail address
 * @param bool $hide Hide email option
 * @return string
 */
function sed_build_email($email, $hide = false)
{
	global $L;
	if($hide)
	{
		return $L['Hidden'];
	}
	elseif(!empty($email) && preg_match('#^\w[\._\w\-]+@[\w\.\-]+\.[a-z]+$#', $email))
	{
		return sed_obfuscate('<a href="mailto:'.$email.'">'.$email.'</a>');
	}
}

/**
 * Returns country flag button
 *
 * @param string $flag Country code
 * @return string
 */
function sed_build_flag($flag)
{
	global $sed_countries;
	$flag = (empty($flag)) ? '00' : $flag;
	return '<a href="'.sed_url('users', 'f=country_'.$flag).'" title="'.$sed_countries[$flag].'"><img src="images/flags/f-'.$flag.'.gif" alt="'.$flag.'" /></a>';
}

/**
 * Returns forum thread path
 *
 * @param int $sectionid Section ID
 * @param string $title Thread title
 * @param string $category Category code
 * @param string $link Display as links
 * @param mixed $master Master section
 * @return string
 */
function sed_build_forums($sectionid, $title, $category, $link = TRUE, $master = false)
{
	global $sed_forums_str, $cfg, $db_forum_sections, $L;
	$pathcodes = explode('.', $sed_forums_str[$category]['path']);

	if($link)
	{
		if($cfg['homebreadcrumb'])
		{
			$tmp[] = '<a href="'.$cfg['mainurl'].'">'.sed_cc($cfg['maintitle']).'</a>';
		}
		$tmp[] = '<a href="'.sed_url('forums').'">'.$L['Forums'].'</a>';
		foreach($pathcodes as $k => $x)
		{
			$tmp[] = '<a href="'.sed_url('forums', 'c='.$x.'#'.$x).'">'.sed_cc($sed_forums_str[$x]['title']).'</a>';
		}
		if(is_array($master))
		{
			$tmp[] = '<a href="'.sed_url('forums', 'm=topics&s='.$master[0]).'">'.sed_cc($master[1]).'</a>';
		}
		$tmp[] = '<a href="'.sed_url('forums', 'm=topics&s='.$sectionid).'">'.sed_cc($title).'</a>';
	}
	else
	{
		foreach($pathcodes as $k => $x)
		{
			$tmp[]= sed_cc($sed_forums_str[$x]['title']);
		}
		if(is_array($master))
		{
			$tmp[] = $master[1];
		}
		$tmp[] = sed_cc($title);
	}

	return implode(' '.$cfg['separator'].' ', $tmp);
}


/* ------------------ */
// TODO eliminate this function
function sed_build_gallery($id, $c1, $c2, $title)
{
	return("<a href=\"javascript:gallery('".$id."','".$c1."','".$c2."')\">".$title."</a>");
}

/**
 * Returns group link (button)
 *
 * @param int $grpid Group ID
 * @return string
 */
function sed_build_group($grpid)
{
	if(empty($grpid)) return '';
	global $sed_groups, $L;

	if($sed_groups[$grpid]['hidden'])
	{
		if(sed_auth('users', 'a', 'A'))
		{
			return '<a href="'.sed_url('users', 'gm='.$grpid).'">'.$sed_groups[$grpid]['title'].'</a> ('.$L['Hidden'].')';
		}
		else
		{
			return $L['Hidden'];
		}
	}
	else
	{
		return '<a href="'.sed_url('users', 'gm='.$grpid).'">'.$sed_groups[$grpid]['title'].'</a>';
	}
}

/**
 * Builds "edit group" option group for "user edit" part
 *
 * @param int $userid Edited user ID
 * @param bool $edit Permission
 * @param int $maingrp User main group
 * @return string
 */
function sed_build_groupsms($userid, $edit=FALSE, $maingrp=0)
{
	global $db_groups_users, $sed_groups, $L, $usr;

	$sql = sed_sql_query("SELECT gru_groupid FROM $db_groups_users WHERE gru_userid='$userid'");

	while ($row = sed_sql_fetcharray($sql))
	{
		$member[$row['gru_groupid']] = TRUE;
	}

	foreach($sed_groups as $k => $i)
	{
		$checked = ($member[$k]) ? "checked=\"checked\"" : '';
		$checked_maingrp = ($maingrp==$k) ? "checked=\"checked\"" : '';
		$readonly = (!$edit || $usr['level'] < $sed_groups[$k]['level'] || $k==SED_GROUP_GUESTS || $k==SED_GROUP_INACTIVE || $k==SED_GROUP_BANNED || ($k==SED_GROUP_TOPADMINS && $userid==1)) ? "disabled=\"disabled\"" : '';
		$readonly_maingrp = (!$edit || $usr['level'] < $sed_groups[$k]['level'] || $k==SED_GROUP_GUESTS || ($k==SED_GROUP_INACTIVE && $userid==1) || ($k==SED_GROUP_BANNED && $userid==1)) ? "disabled=\"disabled\"" : '';

		if ($member[$k] || $edit)
		{
			if (!($sed_groups[$k]['hidden'] && !sed_auth('users', 'a', 'A')))
			{
				$res .= "<input type=\"radio\" class=\"radio\" name=\"rusermaingrp\" value=\"$k\" ".$checked_maingrp." ".$readonly_maingrp." /> \n";
				$res .= "<input type=\"checkbox\" class=\"checkbox\" name=\"rusergroupsms[$k]\" ".$checked." $readonly />\n";
				$res .= ($k == SED_GROUP_GUESTS) ? $sed_groups[$k]['title'] : "<a href=\"".sed_url('users', 'g='.$k)."\">".$sed_groups[$k]['title']."</a>";
				$res .= ($sed_groups[$k]['hidden']) ? ' ('.$L['Hidden'].')' : '';
				$res .= "<br />";
			}
		}
	}

	return $res;
}

/**
 * Returns user ICQ pager link
 *
 * @param int $text ICQ number
 * @return string
 */
function sed_build_icq($text)
{
	global $cfg;

	$text = (int) $text;
	if($text > 0)
	{
		return $text.' <a href="http://www.icq.com/'.$text.'#pager"><img src="http://web.icq.com/whitepages/online?icq='.$text.'&amp;img=5" alt="" /></a>';
	}
	return '';
}

/**
 * Returns IP Search link
 *
 * @param string $ip IP mask
 * @return string
 */
function sed_build_ipsearch($ip)
{
	global $xk;
	if(!empty($ip))
	{
		return '<a href="'.sed_url('admin', 'm=tools&p=ipsearch&a=search&id='.$ip.'&x='.$xk).'">'.$ip.'</a>';
	}
	return '';
}

/**
 * Returns MSN link as e-mail link
 *
 * @param string $msn MSN address
 * @return string
 */
function sed_build_msn($msn)
{
	return sed_build_email($msn);
}

/**
 * Odd/even class choser for row
 *
 * @param int $number Row number
 * @return string
 */
function sed_build_oddeven($number)
{
	return ($number % 2 == 0 ) ? 'even' : 'odd';
}

/* ------------------ */
// TODO eliminate this function
function sed_build_pfs($id, $c1, $c2, $title)
{
	global $L, $cfg, $usr, $sed_groups;
	if ($cfg['disable_pfs'])
	{ $res = ''; }
	else
	{
		if ($id==0)
		{ $res = "<a href=\"javascript:pfs('0','".$c1."','".$c2."')\">".$title."</a>"; }
		elseif ($sed_groups[$usr['maingrp']]['pfs_maxtotal']>0 && $sed_groups[$usr['maingrp']]['pfs_maxfile']>0 && sed_auth('pfs', 'a', 'R'))
		{ $res = "<a href=\"javascript:pfs('".$id."','".$c1."','".$c2."')\">".$title."</a>"; }
		else
		{ $res = ''; }
	}
	return($res);
}

/**
 * Returns user PM link
 *
 * @param int $user User ID
 * @return string
 */
function sed_build_pm($user)
{
	global $usr, $L;
	return '<a href="'.sed_url('pm', 'm=send&to='.$user).'" title="'.$L['pm_sendnew'].'"><img src="skins/'.$usr['skin'].'/img/system/icon-pm.gif"  alt="'.$L['pm_sendnew'].'" /></a>';
}

/* ------------------ */
// TODO replace with new ratings subsystem
function sed_build_ratings($code, $url, $display)
{
	global $db_ratings, $db_rated, $db_users, $cfg, $usr, $sys, $L;

	list($usr['auth_read_rat'], $usr['auth_write_rat'], $usr['isadmin_rat']) = sed_auth('ratings', 'a');

	if ($cfg['disable_ratings'] || !$usr['auth_read_rat'])
	{ return (array('','')); }

	$sql = sed_sql_query("SELECT * FROM $db_ratings WHERE rating_code='$code' LIMIT 1");

	if ($row = sed_sql_fetcharray($sql))
	{
		$rating_average = $row['rating_average'];
		$yetrated = TRUE;
		if ($rating_average<1)
		{ $rating_average = 1; }
		elseif ($rating_average>10)
		{ $rating_average = 10; }
		$rating_cntround = round($rating_average, 0);
	}
	else
	{
		$yetrated = FALSE;
		$rating_average = 0;
		$rating_cntround = 0;
	}

	$res = "<a href=\"".$url."&amp;ratings=1\"><img src=\"skins/".$usr['skin']."/img/system/vote".$rating_cntround.".gif\" alt=\"\" /></a>";

	if ($display)
	{
		$ina = sed_import('ina','G','ALP');
		$newrate = sed_import('newrate','P','INT');

		$alr_rated = sed_sql_result(sed_sql_query("SELECT COUNT(*) FROM ".$db_rated." WHERE rated_userid=".$usr['id']." AND rated_code = '".sed_sql_prep($code)."'"), 0, 'COUNT(*)');

		if ($ina=='send' && $newrate>=1 && $newrate<=10 && $usr['auth_write_rat'] && $alr_rated<=0)
		{
			/* == Hook for the plugins == */
			$extp = sed_getextplugins('ratings.send.first');
			if (is_array($extp))
			{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
			/* ===== */

			if (!$yetrated)
			{
				$sql = sed_sql_query("INSERT INTO $db_ratings (rating_code, rating_state, rating_average, rating_creationdate, rating_text) VALUES ('".sed_sql_prep($code)."', 0, ".(int)$newrate.", ".(int)$sys['now_offset'].", '') ");
			}

			$sql = sed_sql_query("INSERT INTO $db_rated (rated_code, rated_userid, rated_value) VALUES ('".sed_sql_prep($code)."', ".(int)$usr['id'].", ".(int)$newrate.")");
			$sql = sed_sql_query("SELECT COUNT(*) FROM $db_rated WHERE rated_code='$code'");
			$rating_voters = sed_sql_result($sql, 0, "COUNT(*)");
			$ratingnewaverage = ($rating_average * ($rating_voters - 1) + $newrate) / ( $rating_voters );
			$sql = sed_sql_query("UPDATE $db_ratings SET rating_average='$ratingnewaverage' WHERE rating_code='$code'");

			/* == Hook for the plugins == */
			$extp = sed_getextplugins('ratings.send.done');
			if (is_array($extp))
			{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
			/* ===== */

			header("Location: " . SED_ABSOLUTE_URL . "$url&ratings=1&ina=added");
			exit;
		}

		$votedcasted = ($ina=='added') ? 1 : 0;

		$rate_form = "<input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"1\" /><img src=\"skins/".$usr['skin']."/img/system/vote1.gif\" alt=\"\" /> 1 - ".$L['rat_choice1']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"2\" /><img src=\"skins/".$usr['skin']."/img/system/vote2.gif\" alt=\"\" /> 2 - ".$L['rat_choice2']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"3\" /><img src=\"skins/".$usr['skin']."/img/system/vote3.gif\" alt=\"\" /> 3 - ".$L['rat_choice3']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"4\" /><img src=\"skins/".$usr['skin']."/img/system/vote4.gif\" alt=\"\" /> 4 - ".$L['rat_choice4']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"5\" checked=\"checked\" /><img src=\"skins/".$usr['skin']."/img/system/vote5.gif\" alt=\"\" /> 5 - ".$L['rat_choice5']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"6\" /><img src=\"skins/".$usr['skin']."/img/system/vote6.gif\" alt=\"\" /> 6 - ".$L['rat_choice6']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"7\" /><img src=\"skins/".$usr['skin']."/img/system/vote7.gif\" alt=\"\" /> 7 - ".$L['rat_choice7']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"8\" /><img src=\"skins/".$usr['skin']."/img/system/vote8.gif\" alt=\"\" /> 8 - ".$L['rat_choice8']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"9\" /><img src=\"skins/".$usr['skin']."/img/system/vote9.gif\" alt=\"\" /> 9 - ".$L['rat_choice9']."<br /><input type=\"radio\" class=\"radio\" name=\"newrate\" value=\"10\" /><img src=\"skins/".$usr['skin']."/img/system/vote10.gif\" alt=\"\" /> 10 - ".$L['rat_choice10'];

		if ($usr['id']>0)
		{
			$sql1 = sed_sql_query("SELECT rated_value FROM $db_rated WHERE rated_code='$code' AND rated_userid='".$usr['id']."' LIMIT 1");

			if ($row1 = sed_sql_fetcharray($sql1))
			{
				$alreadyvoted = TRUE;
				$rating_uservote = $L['rat_alreadyvoted']." (".$row1['rated_value'].")";
			}
		}

		$t = new XTemplate(sed_skinfile('ratings'));

		/* == Hook for the plugins == */
		$extp = sed_getextplugins('ratings.main');
		if (is_array($extp))
		{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
		/* ===== */

		if (!empty($error_string))
		{
			$t->assign("RATINGS_ERROR_BODY",$error_string);
			$t->parse("RATINGS.RATINGS_ERROR");
		}

		if ($yetrated)
		{
			$sql = sed_sql_query("SELECT COUNT(*) FROM $db_rated WHERE rated_code='$code' ");
			$rating_voters = sed_sql_result($sql, 0, "COUNT(*)");
			$rating_average = $row['rating_average'];
			$rating_since = $L['rat_since']." ".date($cfg['dateformat'], $row['rating_creationdate'] + $usr['timezone'] * 3600);
			if ($rating_average<1)
			{ $rating_average = 1; }
			elseif ($ratingaverage>10)
			{ $rating_average = 10; }

			$rating = round($rating_average,0);
			$rating_averageimg = "<img src=\"skins/".$usr['skin']."/img/system/vote".$rating.".gif\" alt=\"\" />";
			$sql = sed_sql_query("SELECT COUNT(*) FROM $db_rated WHERE rated_code='$code' ");
			$rating_voters = sed_sql_result($sql, 0, "COUNT(*)");
		}
		else
		{
			$rating_voters = 0;
			$rating_since = '';
			$rating_average = $L['rat_notyetrated'];
			$rating_averageimg = '';
		}

		$t->assign(array(
			"RATINGS_AVERAGE" => $rating_average,
			"RATINGS_AVERAGEIMG" => $rating_averageimg,
			"RATINGS_VOTERS" => $rating_voters,
			"RATINGS_SINCE" => $rating_since
		));


		if ($usr['id']>0 && $votedcasted)
		{
			$t->assign(array(
				"RATINGS_EXTRATEXT" => $L['rat_votecasted'],
			));
			$t->parse("RATINGS.RATINGS_EXTRA");
		}
		elseif ($usr['id']>0 && $alreadyvoted)
		{
			$t->assign(array(
				"RATINGS_EXTRATEXT" => $rating_uservote,
			));
			$t->parse("RATINGS.RATINGS_EXTRA");
		}
		elseif ($usr['id']==0)
		{
			$t->assign(array(
				"RATINGS_EXTRATEXT" => $L['rat_registeredonly'],
			));
			$t->parse("RATINGS.RATINGS_EXTRA");
		}

		elseif ($usr['id']>0 && !$alreadyvoted)
		{
			$t->assign(array(
				"RATINGS_NEWRATE_FORM_SEND" => $url."&amp;ratings=1&amp;ina=send",
				"RATINGS_NEWRATE_FORM_VOTER" => $usr['name'],
				"RATINGS_NEWRATE_FORM_RATE" => $rate_form
			));
			$t->parse("RATINGS.RATINGS_NEWRATE");
		}

		/* == Hook for the plugins == */
		$extp = sed_getextplugins('ratings.tags');
		if (is_array($extp))
		{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
		/* ===== */

		$t->parse("RATINGS");
		$res_display = $t->text("RATINGS");
	}
	else
	{
		$res_display = '';
	}

	return(array($res, $res_display));
}

/* ------------------ */
// TODO eliminate this function
function sed_build_smilies($c1, $c2, $title)
{
	return '';
}

/* ------------------ */
/**
 * Builds local smiley selection box. Obsolete.
 *
 * @param int $limit Max number of smilies in the box.
 * @return string
 */
function sed_build_smilies_local($limit)
{
	global $sed_smilies;

	$result = '<div class="smilies">';

	if (is_array($sed_smilies))
	{
		foreach($sed_smilies as $k => $v)
		{
			$result .= "<a href=\"javascript:addtxt('".sed_cc($v['code'])."')\"><img src=\"./images/smilies/".$v['file']."\" alt=\"".sed_cc($v['code'])."\" /></a> ";
		}
	}

	$result .= "</div>";
	return $result;
}

/**
 * Returns stars image for user level
 *
 * @param int $level User level
 * @return unknown
 */
function sed_build_stars($level)
{
	global $skin;

	if($level>0 and $level<100)
	{
		return '<img src="skins/'.$skin.'/img/system/stars'.(floor($level/10)+1).'.gif" alt="" />';
	}
	else
	{
		return '';
	}
}

/**
 * Returns time gap between 2 dates
 *
 * @param int $t1 Stamp 1
 * @param int $t2 Stamp2
 * @return string
 */
function sed_build_timegap($t1,$t2)
{
	global $L;

	$gap = $t2 - $t1;

	if($gap<=0 || !$t2 || $gap>94608000)
	{
		$result = '';
	}
	elseif($gap<60)
	{
		$result  = $gap.' '.$L['Seconds'];
	}
	elseif($gap<3600)
	{
		$gap = floor($gap/60);
		$result = ($gap<2) ? '1 '.$L['Minute'] : $gap.' '.$L['Minutes'];
	}
	elseif($gap<86400)
	{
		$gap1 = floor($gap/3600);
		$gap2 = floor(($gap-$gap1*3600)/60);
		$result = ($gap1<2) ? '1 '.$L['Hour'].' ' : $gap1.' '.$L['Hours'].' ';
		if ($gap2>0)
		{
			$result .= ($gap2<2) ? '1 '.$L['Minute'] : $gap2.' '.$L['Minutes'];
		}
	}
	else
	{
		$gap = floor($gap/86400);
		$result = ($gap<2) ? '1 '.$L['Day'] : $gap.' '.$L['Days'];
	}

	return $result;
}

/**
 * Returns user timezone offset
 *
 * @param int $tz Timezone
 * @return string
 */
function sed_build_timezone($tz)
{
	global $L;

	$result = 'GMT';

	if($tz==-1 OR $tz==1)
	{
		$result .= $tz.' '.$L['Hour'];
	}
	elseif($tz!=0)
	{
		$result .= $tz.' '.$L['Hours'];
	}
	return $result;
}

/**
 * Returns link for URL
 *
 * @param string $text URL
 * @param int $maxlen Max. allowed length
 * @return unknown
 */
function sed_build_url($text, $maxlen=64)
{
	global $cfg;

	if(!empty($text))
	{
		if(mb_strpos($text, 'http://') !== 0)
		{
			$text='http://'. $text;
		}
		$text = sed_cc($text);
		$text = '<a href="'.$text.'">'.sed_cutstring($text, $maxlen).'</a>';
	}
	return $text;
}

/**
 * Returns link to user profile
 *
 * @param int $id User ID
 * @param string $user User name
 * @return string
 */
function sed_build_user($id, $user)
{
	global $cfg;

	if($id == 0 && !empty($user))
	{
		return $user;
	}
	elseif($id == 0)
	{
		return '';
	}
	else
	{
		return (!empty($user)) ? '<a href="'.sed_url('users', 'm=details&id='.$id).'">'.$user.'</a>' : '?';
	}
}

/**
 * Returns user avatar image
 *
 * @param string $image Image src
 * @return string
 */
function sed_build_userimage($image)
{
	if(empty($image))
	{
		$image = 'datas/defaultav/blank.png';
	}
	return '<img src="'.$image.'" alt="" class="avatar" />';
}

/**
 * Renders user signature text
 *
 * @param string $text Signature text
 * @return string
 */
function sed_build_usertext($text)
{
	global $cfg;
	return sed_parse($text, $cfg['parsebbcodeusertext'], $cfg['parsesmiliesusertext'], 1);
}

/*
 * ================================ Cache Subsystem ================================
 */
// TODO scheduled for complete removal and replacement with new cache system

/**
 * Clears cache item
 *
 * @param string $name Item name
 * @return bool
 */
function sed_cache_clear($name)
{
	global $db_cache;

	sed_sql_query("DELETE FROM $db_cache WHERE c_name='$name'");
	return(TRUE);
}

/**
 * Clears cache completely
 *
 * @return bool
 */
function sed_cache_clearall()
{
	global $db_cache;
	sed_sql_query("DELETE FROM $db_cache");
	return(TRUE);
}

/**
 * Fetches cache value
 *
 * @param string $name Item name
 * @return mixed
 */
function sed_cache_get($name)
{
	global $cfg, $sys, $db_cache;

	if (!$cfg['cache'])
	{ return FALSE; }
	$sql = sed_sql_query("SELECT c_value FROM $db_cache WHERE c_name='$name' AND c_expire>'".$sys['now']."'");
	if ($row = sed_sql_fetcharray($sql))
	{ return(unserialize($row['c_value'])); }
	else
	{ return(FALSE); }
}

/**
 * Get all cache data and import it into global scope
 *
 * @param int $auto Only with autoload flag
 * @return mixed
 */
function sed_cache_getall($auto = 1)
{
	global $cfg, $sys, $db_cache;
	if (!$cfg['cache'])
	{ return FALSE; }
	$sql = sed_sql_query("DELETE FROM $db_cache WHERE c_expire<'".$sys['now']."'");
	if ($auto)
	{ $sql = sed_sql_query("SELECT c_name, c_value FROM $db_cache WHERE c_auto=1"); }
	else
	{ $sql = sed_sql_query("SELECT c_name, c_value FROM $db_cache"); }
	if (sed_sql_numrows($sql)>0)
	{ return($sql); }
	else
	{ return(FALSE); }
}

/**
 * Puts an item into cache
 *
 * @param string $name Item name
 * @param mixed $value Item value
 * @param int $expire Expires in seconds
 * @param int $auto Autload flag
 * @return bool
 */
function sed_cache_store($name,$value,$expire,$auto="1")
{
	global $db_cache, $sys, $cfg;

	if (!$cfg['cache'])
	{ return(FALSE); }
	$sql = sed_sql_query("REPLACE INTO $db_cache (c_name, c_value, c_expire, c_auto) VALUES ('$name', '".sed_sql_prep(serialize($value))."', '".($expire + $sys['now'])."', '$auto')");
	return(TRUE);
}

/**
 * Makes HTML sequences safe
 *
 * @param string $text Source string
 * @return string
 */
function sed_cc($text)
{
	/*$text = str_replace(
	array('{', '<', '>' , '$', '\'', '"', '\\', '&amp;', '&nbsp;'),
	array('&#123;', '&lt;', '&gt;', '&#036;', '&#039;', '&quot;', '&#92;', '&amp;amp;', '&amp;nbsp;'), $text);
	return $text;*/
	return htmlspecialchars($text);
}

/**
 * Checks GET anti-XSS parameter
 *
 * @return bool
 */
function sed_check_xg()
{
	global $xg, $cfg;

	if($xg!=sed_sourcekey())
	{
		sed_diefatal('Wrong parameter in the URL.');
	}
	return TRUE;
}

/**
 * Checks POST anti-XSS parameter
 *
 * @return string
 */
function sed_check_xp()
{
	global $xp, $xg;

	$sk = sed_sourcekey();
	if($_SERVER['REQUEST_METHOD'] == 'POST' && !defined('SED_AUTH'))
	{
		if($xp != $sk && $xg != $sk)
		{
			sed_diefatal('Wrong parameter in the URL.');
		}
	}
	return $sk;
}

/**
 * Truncates a post and makes sure parsing is correct
 *
 * @param string $text Post text
 * @param int $max_chars Max. length
 * @param bool $parse_bbcodes Parse bbcodes
 * @return unknown
 */
function sed_cutpost($text, $max_chars, $parse_bbcodes = true)
{
    $text = $max_chars == 0 ? $text : sed_cutstring(strip_tags($text), $max_chars);
    // Fix partial cuttoff
    $text = preg_replace('#\[[^\]]*?$#', '...', $text);
    // Parse the BB-codes or skip them
    if($parse_bbcodes)
    {
        // Parse it
        $text = sed_parse($text);
    }
    else $text = preg_replace('#\[[^\]]+?\]#', '', $text);
    return $text;
}

/**
 * Truncates a string
 *
 * @param string $res Source string
 * @param int $l Length
 * @return unknown
 */
function sed_cutstring($res, $l)
{
	global $cfg;
	if(mb_strlen($res)>$l)
	{
		$res = mb_substr($res, 0, ($l-3)).'...';
	}
	return $res;
}

/**
 * Creates image thumbnail
 *
 * @param string $img_big Original image path
 * @param string $img_small Thumbnail path
 * @param int $small_x Thumbnail width
 * @param int $small_y Thumbnail height
 * @param bool $keepratio Keep original ratio
 * @param string $extension Image type
 * @param string $filen Original file name
 * @param int $fsize File size in kB
 * @param string $textcolor Text color
 * @param int $textsize Text size
 * @param string $bgcolor Background color
 * @param int $bordersize Border thickness
 * @param int $jpegquality JPEG quality in %
 * @param string $dim_priority Resize priority dimension
 */
function sed_createthumb($img_big, $img_small, $small_x, $small_y, $keepratio, $extension, $filen, $fsize, $textcolor, $textsize, $bgcolor, $bordersize, $jpegquality, $dim_priority="Width")
{
	if (!function_exists('gd_info'))
	{ return; }

	global $cfg;

	$gd_supported = array('jpg', 'jpeg', 'png', 'gif');

	switch($extension)
	{
		case 'gif':
			$source = imagecreatefromgif($img_big);
			break;

		case 'png':
			$source = imagecreatefrompng($img_big);
			break;

		default:
			$source = imagecreatefromjpeg($img_big);
			break;
	}

	$big_x = imagesx($source);
	$big_y = imagesy($source);

	if (!$keepratio)
	{
		$thumb_x = $small_x;
		$thumb_y = $small_y;
	}
	elseif ($dim_priority=="Width")
	{
		$thumb_x = $small_x;
		$thumb_y = floor($big_y * ($small_x / $big_x));
	}
	else
	{
		$thumb_x = floor($big_x * ($small_y / $big_y));
		$thumb_y = $small_y;
	}

	if ($textsize==0)
	{
		if ($cfg['th_amode']=='GD1')
		{ $new = imagecreate($thumb_x+$bordersize*2, $thumb_y+$bordersize*2); }
		else
		{ $new = imagecreatetruecolor($thumb_x+$bordersize*2, $thumb_y+$bordersize*2); }

		$background_color = imagecolorallocate ($new, $bgcolor[0], $bgcolor[1] ,$bgcolor[2]);
		imagefilledrectangle ($new, 0,0, $thumb_x+$bordersize*2, $thumb_y+$bordersize*2, $background_color);

		if ($cfg['th_amode']=='GD1')
		{ imagecopyresized($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y); }
		else
		{ imagecopyresampled($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y); }

	}
	else
	{
		if ($cfg['th_amode']=='GD1')
		{ $new = imagecreate($thumb_x+$bordersize*2, $thumb_y+$bordersize*2+$textsize*3.5+6); }
		else
		{ $new = imagecreatetruecolor($thumb_x+$bordersize*2, $thumb_y+$bordersize*2+$textsize*3.5+6); }

		$background_color = imagecolorallocate($new, $bgcolor[0], $bgcolor[1] ,$bgcolor[2]);
		imagefilledrectangle ($new, 0,0, $thumb_x+$bordersize*2, $thumb_y+$bordersize*2+$textsize*4+14, $background_color);
		$text_color = imagecolorallocate($new, $textcolor[0],$textcolor[1],$textcolor[2]);

		if ($cfg['th_amode']=='GD1')
		{ imagecopyresized($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y); }
		else
		{ imagecopyresampled($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y); }

		imagestring ($new, $textsize, $bordersize, $thumb_y+$bordersize+$textsize+1, $big_x."x".$big_y." ".$fsize."kb", $text_color);
	}

	switch($extension)
	{
		case 'gif':
			imagegif($new, $img_small);
			break;

		case 'png':
			imagepng($new, $img_small);
			break;

		default:
			imagejpeg($new, $img_small, $jpegquality);
			break;
	}

	imagedestroy($new);
	imagedestroy($source);
}

/**
 * Terminates script execution and performs redirect
 *
 * @param bool $cond Really die?
 * @return bool
 */
function sed_die($cond=TRUE)
{
	if ($cond)
	{
		header("Location: " . SED_ABSOLUTE_URL . sed_url('message', "msg=950", '', true));
		exit;
	}
	return FALSE;
}

/**
 * Terminates script execution with fatal error
 *
 * @param string $text Reason
 * @param string $title Message title
 */
function sed_diefatal($text='Reason is unknown.', $title='Fatal error')
{
	global $cfg;

	$disp = "<strong><a href=\"".$cfg['mainurl']."\">".$cfg['maintitle']."</a></strong><br />";
	$disp .= @date('Y-m-d H:i').'<br />'.$title.' : '.$text;
	die($disp);
}

/**
 * Terminates with "disabled" error
 *
 * @param unknown_type $disabled
 */
function sed_dieifdisabled($disabled)
{
	if ($disabled)
	{
		header("Location: " . SED_ABSOLUTE_URL . sed_url('message', "msg=940", '', true));
		exit;
	}
	return;
}

/**
 * Checks a file to be sure it is valid
 *
 * @param string $path File path
 * @param string $name File name
 * @param string $ext File extension
 * @return bool
 */
function sed_file_check($path, $name, $ext)
{
	global $L, $cfg;
	if($cfg['pfsfilecheck'])
	{
		require($cfg['system_dir'].'/mimetype.php');
		$fcheck = FALSE;
		if(in_array($ext, array('jpg', 'jpeg', 'png', 'gif')))
		{
			switch($ext)
			{
				case 'gif':
					$fcheck = @imagecreatefromgif($path);
				break;
				case 'png':
					$fcheck = @imagecreatefrompng($path);
				break;
				default:
					$fcheck = @imagecreatefromjpeg($path);
				break;
			}
			$fcheck = $fcheck !== FALSE;
		}
		else
		{
			if(!empty($mime_type[$ext]))
			{
				foreach($mime_type[$ext] as $mime)
				{
					$f = ((substr(phpversion(),0, 3)>="5.1")) ? '' : fopen($path, "rb");
					if(substr(phpversion(),0, 3) < "5.1") fseek($f, $mime[3]);
					$ff = ((substr(phpversion(),0, 3)>="5.1")) ? '' : fread($f, $mime[4]);
					$content = (substr(phpversion(),0, 3)>="5.1") ? file_get_contents($path,0,NULL,$mime[3],$mime[4]) : $ff ;
					$content = ($mime[2]) ? bin2hex($content) : $content;
					$mime[1] = ($mime[2]) ? strtolower($mime[1]) : $mime[1];
					$i++;
					if ($content == $mime[1])
					{
						$fcheck = TRUE;
						break;
					}
				}
				$fclose = ((substr(phpversion(),0, 3)>="5.1")) ? '' : fclose($f);
			}
			else
			{
				$fcheck = ($cfg['pfsnomimepass']) ? 1 : 2;
				sed_log(sprintf($L['pfs_filechecknomime'], $ext, $name), 'sec');
			}
		}
		if(!$fcheck)
		{
			sed_log(sprintf($L['pfs_filecheckfail'], $ext, $name), 'sec');
		}
	}
	else
	{
		$fcheck = true;
	}
	return($fcheck);
}

/*
 * ==================================== Forum Functions ==================================
 */

/**
 * Gets details for forum section
 *
 * @param int $id Section ID
 * @return mixed
 */
function sed_forum_info($id)
{
	global $db_forum_sections;

	$sql = sed_sql_query("SELECT * FROM $db_forum_sections WHERE fs_id='$id'");
	if($res = sed_sql_fetcharray($sql))
	{
		return ($res);
	}
	else
	{
		return ('');
	}
}

/**
 * Moves outdated topics to trash
 *
 * @param string $mode Selection criteria
 * @param int $section Section
 * @param int $param Selection parameter value
 * @return int
 */
function sed_forum_prunetopics($mode, $section, $param)
{
	global $cfg, $sys, $db_forum_topics, $db_forum_posts, $db_forum_sections, $L;

	$num = 0;
	$num1 = 0;

	switch ($mode)
	{
		case 'updated':
			$limit = $sys['now'] - ($param*86400);
			$sql1 = sed_sql_query("SELECT * FROM $db_forum_topics WHERE ft_sectionid='$section' AND ft_updated<'$limit' AND ft_sticky='0'");
			break;

		case 'single':
			$sql1 = sed_sql_query("SELECT * FROM $db_forum_topics WHERE ft_sectionid='$section' AND ft_id='$param'");
			break;
	}

	if (sed_sql_numrows($sql1)>0)
	{
		while ($row1 = sed_sql_fetchassoc($sql1))
		{
			$q = $row1['ft_id'];

			if ($cfg['trash_forum'])
			{
				$sql = sed_sql_query("SELECT * FROM $db_forum_posts WHERE fp_topicid='$q' ORDER BY fp_id DESC");

				while ($row = sed_sql_fetchassoc($sql))
				{ sed_trash_put('forumpost', $L['Post']." #".$row['fp_id']." from topic #".$q, "p".$row['fp_id']."-q".$q, $row); }
			}

			$sql = sed_sql_query("DELETE FROM $db_forum_posts WHERE fp_topicid='$q'");
			$num += sed_sql_affectedrows();

			if ($cfg['trash_forum'])
			{
				$sql = sed_sql_query("SELECT * FROM $db_forum_topics WHERE ft_id='$q'");

				while ($row = sed_sql_fetchassoc($sql))
				{ sed_trash_put('forumtopic', $L['Topic']." #".$q." (no post left)", "q".$q, $row); }
			}

			$sql = sed_sql_query("DELETE FROM $db_forum_topics WHERE ft_id='$q'");
			$num1 += sed_sql_affectedrows();
		}

		$sql = sed_sql_query("DELETE FROM $db_forum_topics WHERE ft_movedto='$q'");
		$sql = sed_sql_query("UPDATE $db_forum_sections SET fs_topiccount=fs_topiccount-'$num1', fs_postcount=fs_postcount-'$num', fs_topiccount_pruned=fs_topiccount_pruned+'$num1', fs_postcount_pruned=fs_postcount_pruned+'$num' WHERE fs_id='$section'");
	}
	$num1 = ($num1=='') ? '0' : $num1;
	return($num1);
}

/**
 * Changes last message for the section
 *
 * @param int $id Section ID
 */
function sed_forum_sectionsetlast($id)
{
	global $db_forum_topics, $db_forum_sections;
	$sql = sed_sql_query("SELECT ft_id, ft_lastposterid, ft_lastpostername, ft_updated, ft_title, ft_poll FROM $db_forum_topics WHERE ft_sectionid='$id' AND ft_movedto='0' and ft_mode='0' ORDER BY ft_updated DESC LIMIT 1");
	$row = sed_sql_fetcharray($sql);
	$sql = sed_sql_query("UPDATE $db_forum_sections SET fs_lt_id=".(int)$row['ft_id'].", fs_lt_title='".sed_sql_prep($row['ft_title'])."', fs_lt_date=".(int)$row['ft_updated'].", fs_lt_posterid=".(int)$row['ft_lastposterid'].", fs_lt_postername='".sed_sql_prep($row['ft_lastpostername'])."' WHERE fs_id='$id'");
	return;
}

/**
 * Returns a list of plugins registered for a hook
 *
 * @param string $hook Hook name
 * @param string $cond Permissions
 * @return array
 */
function sed_getextplugins($hook, $cond='R')
{
	global $sed_plugins, $usr;

	if (is_array($sed_plugins))
	{
		foreach($sed_plugins as $i => $k)
		{
			if($k['pl_hook']==$hook && sed_auth('plug', $k['pl_code'], $cond))
			{
				$extplugins[$i] = $k;
			}
		}
	}
	return $extplugins;
}

/**
 * Returns number of comments for item
 *
 * @param string $code Item code
 * @return int
 */
function sed_get_comcount($code)
{
	global $db_com;

	$sql = sed_sql_query("SELECT DISTINCT com_code, COUNT(*) FROM $db_com WHERE com_code='$code' GROUP BY com_code");

	if ($row = sed_sql_fetcharray($sql))
	{
		return (int) $row['COUNT(*)'];
	}
	else
	{
		return 0;
	}
}

/**
 * Imports data from the outer world
 *
 * @param string $name Variable name
 * @param string $source Source type: G (GET), P (POST), C (COOKIE) or D (variable filtering)
 * @param string $filter Filter type
 * @param int $maxlen Length limit
 * @param bool $dieonerror Die with fatal error on wrong input
 * @return mixed
 */
function sed_import($name, $source, $filter, $maxlen=0, $dieonerror=FALSE)
{
	switch($source)
	{
		case 'G':
			$v = $_GET[$name];
			$log = TRUE;
			break;

		case 'P':
			$v = $_POST[$name];
			$log = TRUE;
			if ($filter=='ARR') { return($v); }
			break;

		case 'C':
			$v = $_COOKIE[$name];
			$log = TRUE;
			break;

		case 'D':
			$v = $name;
			$log = FALSE;
			break;

		default:
			sed_diefatal('Unknown source for a variable : <br />Name = '.$name.'<br />Source = '.$source.' ? (must be G, P, C or D)');
			break;
	}

	if (MQGPC && ($source=='G' || $source=='P' || $source=='C') )
	{
		$v = stripslashes($v);
	}

	if ($v=='')
	{
		return('');
	}

	if ($maxlen>0)
	{
		$v = mb_substr($v, 0, $maxlen);
	}

	$pass = FALSE;
	$defret = NULL;
	$filter = ($filter=='STX') ? 'TXT' : $filter;

	switch($filter)
	{
		case 'INT':
			if (is_numeric($v) && floor($v)==$v)
			{
				$pass = TRUE;
			}
		break;

		case 'NUM':
			if(is_numeric($v))
			{
				$pass = TRUE;
			}
		break;

		case 'TXT':
			$v = trim($v);
			if (mb_strpos($v, '<')===FALSE)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = str_replace('<', '&lt;', $v);
			}
		break;

		case 'SLU':
			$v = trim($v);
			$f = preg_replace('/[^a-zA-Z0-9_=\/]/', '', $v);
			if($v == $f)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = '';
			}
		break;

		case 'ALP':
			$v = trim($v);
			$f = sed_alphaonly($v);
			if($v == $f)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = $f;
			}
		break;

		case 'PSW':
			$v = trim($v);
			$f = sed_alphaonly($v);
			$f = mb_substr($f, 0 ,32);

			if ($v == $f)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = $f;
			}
		break;

		case 'HTM':
			$v = trim($v);
			$pass = TRUE;
		break;

		case 'ARR':
			$pass = TRUE;
		break;

		case 'BOL':
			if($v == '1' || $v == 'on')
			{
				$pass = TRUE;
				$v = '1';
			}
			elseif($v=='0' || $v=='off')
			{
				$pass = TRUE;
				$v = '0';
			}
			else
			{
				$defret = '0';
			}
			break;

		case 'LVL':
			if(is_numeric($v) && $v >= 0 && $v <= 100 && floor($v)==$v)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = NULL;
			}
			break;

		case 'NOC':
			$pass = TRUE;
			break;

		default:
			sed_diefatal('Unknown filter for a variable : <br />Var = '.$cv_v.'<br />Filter = '.$filter.' ?');
			break;
	}

	$v = preg_replace('/(&#\d+)(?![\d;])/', '$1;', $v);
	if($pass)
	{
		return($v);
	}
	else
	{
		if($log)
		{
			sed_log_sed_import($source, $filter, $name, $v);
		}
		if($dieonerror)
		{
			sed_diefatal('Wrong input.');
		}
		else
		{
			return($defret);
		}
	}
}


/**
 * Extract info from SED file headers
 *
 * @param string $file File path
 * @param string $limiter Tag name
 * @param int $maxsize Max header size
 * @return array
 */
function sed_infoget($file, $limiter='SED', $maxsize=32768)
{
	$result = array();

	if($fp = @fopen($file, 'r'))
	{
		$limiter_begin = "[BEGIN_".$limiter."]";
		$limiter_end = "[END_".$limiter."]";
		$data = fread($fp, $maxsize);
		$begin = mb_strpos($data, $limiter_begin);
		$end = mb_strpos($data, $limiter_end);

		if ($end>$begin && $begin>0)
		{
			$lines = mb_substr($data, $begin+8+mb_strlen($limiter), $end-$begin-mb_strlen($limiter)-8);
			$lines = explode ("\n",$lines);

			foreach ($lines as $k => $line)
			{
				$linex = explode ("=", $line);
				$ii=1;
				while (!empty($linex[$ii]))
				{
					$result[$linex[0]] .= trim($linex[$ii]);
					$ii++;
				}
			}
		}
		else
		{ $result['Error'] = 'Warning: No tags found in '.$file; }
	}
	else
	{ $result['Error'] = 'Error: File '.$file.' is missing!'; }
	@fclose($fp);
	return ($result);
}

/**
 * Outputs standard javascript
 *
 * @param string $more Extra javascript
 * @return string
 */
function sed_javascript($more='')
{
	global $cfg, $lang;
	if($cfg['jquery'])
	{
		$result .= '<script type="text/javascript" src="js/jquery.js"></script>';
		$result .= '<script type="text/javascript" src="js/jquery-ui.js"></script>';
		$result .= file_exists("./js/ui/i18n/ui.datepicker-$lang.js") ? '<script type="text/javascript" src="js/ui/i18n/ui.datepicker-'.$lang.'.js"></script>' : '<script type="text/javascript" src="js//ui/i18n/ui.datepicker-en.js"></script>' ;
	}
	$result .= '<script type="text/javascript" src="js/base.js"></script>';
	if(!empty($more))
	{
		$result .= '<script type="text/javascript">
//<![CDATA[
'.$more.'
//]]>
</script>';
	}
	return $result;
}

/**
 * Returns a language file path for a plugin or FALSE on error.
 *
 * @param string $name Plugin name
 * @param bool $core Use core module rather than a plugin
 * @return bool
 */
function sed_langfile($name, $core = false)
{
	global $cfg, $lang;
	if($core)
	{
		 // For module support, comming in N-0.1.0
		 if(@file_exists($cfg['system_dir']."/lang/$lang/$name.lang.php"))
		 	return $cfg['system_dir']."/lang/$lang/$name.lang.php";
		 else
		 	return $cfg['system_dir']."/lang/en/$name.lang.php";
	}
	else
	{
		if(@file_exists($cfg['plugins_dir']."/$name/lang/$name.$lang.lang.php"))
			return $cfg['plugins_dir']."/$name/lang/$name.$lang.lang.php";
		else
			return $cfg['plugins_dir']."/$name/lang/$name.en.lang.php";
	}
}

/**
 * Load smilies from current pack
 */
function sed_load_smilies()
{
	global $sed_smilies;
	$sed_smilies = array();
	if(!file_exists('./images/smilies/set.js')) return;

	// A simple JSON parser and decoder
	$json = '';
	$started = false;
	$fp = fopen('./images/smilies/set.js', 'r');
	$i = -1;
	$prio = array();
	$code = array();
	$file = array();
	while(!feof($fp))
	{
		$line = fgets($fp);
		if($line == '];') break;
		if($started)
		{
			$line = trim($line, " \t\r\n");
			if($line == '{')
			{
				$i++;
			}
			elseif($line != '},')
			{
				if(preg_match('#^(\w+)\s*:\s*"?(.+?)"?,?$#', $line, $m))
				{
					switch($m[1])
					{
						case 'prio':
							$prio[$i] = intval($m[2]);
							break;
						case 'code':
							$code[$i] = $m[2];
							break;
						case 'file':
							$file[$i] = $m[2];
							break;
					}
				}
			}
		}
		elseif(strstr($line, 'smileSet'))
		{
			$started = true;
		}
	}
	fclose($fp);

	// Sort the result
	array_multisort($prio, SORT_ASC, $code, $file);
	$cnt = count($code);
	for($i = 0; $i < $cnt; $i++)
	{
		$sed_smilies[$i] = array(
		'code' => $code[$i],
		'file' => $file[$i]
		);
	}
}



/**
 * Loads comlete category structure into array
 *
 * @return array
 */
function sed_load_structure()
{
	global $db_structure, $cfg, $L;

	$res = array();
	$sql = sed_sql_query("SELECT * FROM $db_structure ORDER BY structure_path ASC");

	while ($row = sed_sql_fetcharray($sql))
	{
		if (!empty($row['structure_icon']))
		{ $row['structure_icon'] = "<img src=\"".$row['structure_icon']."\" alt=\"\" />"; }

		$path2 = mb_strrpos($row['structure_path'], '.');

		$row['structure_tpl'] = (empty($row['structure_tpl'])) ? $row['structure_code'] : $row['structure_tpl'];

		if ($path2>0)
		{
			$path1 = mb_substr($row['structure_path'],0,($path2));
			$path[$row['structure_path']] = $path[$path1].'.'.$row['structure_code'];
			$tpath[$row['structure_path']] = $tpath[$path1].' '.$cfg['separator'].' '.$row['structure_title'];
			$row['structure_tpl'] = ($row['structure_tpl']=='same_as_parent') ? $parent_tpl : $row['structure_tpl'];
		}
		else
		{
			$path[$row['structure_path']] = $row['structure_code'];
			$tpath[$row['structure_path']] = $row['structure_title'];
		}

		$order = explode('.',$row['structure_order']);
		$parent_tpl = $row['structure_tpl'];

		$res[$row['structure_code']] = array (
			'path' => $path[$row['structure_path']],
			'tpath' => $tpath[$row['structure_path']],
			'rpath' => $row['structure_path'],
			'tpl' => $row['structure_tpl'],
			'title' => $row['structure_title'],
			'desc' => $row['structure_desc'],
			'icon' => $row['structure_icon'],
			'group' => $row['structure_group'],
			'order' => $order[0],
			'way' => $order[1]
		);
	}

	return($res);
}

/**
 * Loads complete forum structure into array
 *
 * @return array
 */
function sed_load_forum_structure()
{
	global $db_forum_structure, $cfg, $L;

	$res = array();
	$sql = sed_sql_query("SELECT * FROM $db_forum_structure ORDER BY fn_path ASC");

	while ($row = sed_sql_fetcharray($sql))
	{
		if (!empty($row['fn_icon']))
		{ $row['fn_icon'] = "<img src=\"".$row['fn_icon']."\" alt=\"\" />"; }

		$path2 = mb_strrpos($row['fn_path'], '.');

		$row['fn_tpl'] = (empty($row['fn_tpl'])) ? $row['fn_code'] : $row['fn_tpl'];

		if ($path2>0)
		{
			$path1 = mb_substr($row['fn_path'],0,($path2));
			$path[$row['fn_path']] = $path[$path1].'.'.$row['fn_code'];
			$tpath[$row['fn_path']] = $tpath[$path1].' '.$cfg['separator'].' '.$row['fn_title'];
			$row['fn_tpl'] = ($row['fn_tpl']=='same_as_parent') ? $parent_tpl : $row['fn_tpl'];
		}
		else
		{
			$path[$row['fn_path']] = $row['fn_code'];
			$tpath[$row['fn_path']] = $row['fn_title'];
		}

		$parent_tpl = $row['fn_tpl'];

		$res[$row['fn_code']] = array (
			'path' => $path[$row['fn_path']],
			'tpath' => $tpath[$row['fn_path']],
			'rpath' => $row['fn_path'],
			'tpl' => $row['fn_tpl'],
			'title' => $row['fn_title'],
			'desc' => $row['fn_desc'],
			'icon' => $row['fn_icon'],
			'defstate' => $row['fn_defstate']
		);
	}

	return($res);
}

/**
 * Logs an event
 *
 * @param string $text Event description
 * @param string $group Event group
 */
function sed_log($text, $group='def')
{
	global $db_logger, $sys, $usr, $_SERVER;

	$sql = sed_sql_query("INSERT INTO $db_logger (log_date, log_ip, log_name, log_group, log_text) VALUES (".(int)$sys['now_offset'].", '".$usr['ip']."', '".sed_sql_prep($usr['name'])."', '$group', '".sed_sql_prep($text.' - '.$_SERVER['REQUEST_URI'])."')");
	return;
}

/**
 * Logs wrong input
 *
 * @param string $s Source type
 * @param string $e Filter type
 * @param string $v Variable name
 * @param string $o Value
 */
function sed_log_sed_import($s, $e, $v, $o)
{
	$text = "A variable type check failed, expecting ".$s."/".$e." for '".$v."' : ".$o;
	sed_log($text, 'sec');
	return;
}

/**
 * Sends mail with standard PHP mail()
 *
 * @global $cfg
 * @param string $fmail Recipient
 * @param string $subject Subject
 * @param string $body Message body
 * @param string $headers Message headers
 * @param string $additional_parameters Additional parameters passed to sendmail
 * @return bool
 */
function sed_mail($fmail, $subject, $body, $headers='', $additional_parameters = null)
{
	global $cfg;

	if(empty($fmail))
	{
		return(FALSE);
	}
	else
	{
		$headers = (empty($headers)) ? "From: \"".$cfg['maintitle']."\" <".$cfg['adminemail'].">\n"."Reply-To: <".$cfg['adminemail'].">\n"."Content-Type: text/plain; charset=".$cfg['charset']."\n" : $headers;
		$body .= "\n\n".$cfg['maintitle']." - ".$cfg['mainurl']."\n".$cfg['subtitle'];
		if($cfg['charset'] != 'us-ascii')
		{
			$headers .= "Content-Transfer-Encoding: 8bit\n";
			$subject = mb_encode_mimeheader($subject, $cfg['charset'], 'B', "\n");
		}
		mail($fmail, $subject, $body, $headers, $additional_parameters);
		sed_stat_inc('totalmailsent');
		return(TRUE);
	}
}
/* ------------------ */
// FIXME this function is obsolete, or meta/title generation must be reworked
function sed_htmlmetas()
{
        global $cfg;
        $contenttype = ($cfg['doctypeid']>2 && $cfg['xmlclient']) ? "application/xhtml+xml" : "text/html";
        $result = "<meta http-equiv=\"content-type\" content=\"".$contenttype."; charset=".$cfg['charset']."\" />
<meta name=\"description\" content=\"".$cfg['maintitle']." - ".$cfg['subtitle']."\" />
<meta name=\"keywords\" content=\"".$cfg['metakeywords']."\" />
<meta name=\"generator\" content=\"Cotonti http://www.cotonti.com\" />
<meta http-equiv=\"expires\" content=\"Fri, Apr 01 1974 00:00:00 GMT\" />
<meta http-equiv=\"pragma\" content=\"no-cache\" />
<meta http-equiv=\"cache-control\" content=\"no-cache\" />
<meta http-equiv=\"last-modified\" content=\"".gmdate("D, d M Y H:i:s")." GMT\" />
<link rel=\"shortcut icon\" href=\"favicon.ico\" />
";
        return ($result);
}

/**
 * Creates UNIX timestamp out of a date
 *
 * @param int $hour Hours
 * @param int $minute Minutes
 * @param int $second Seconds
 * @param int $month Month
 * @param int $date Day of the month
 * @param int $year Year
 * @return int
 */
function sed_mktime($hour = false, $minute = false, $second = false, $month = false, $date = false, $year = false)
{
	// Code from http://www.php.net/date
	// Author rickenmeer at hotmail dot com
	// 12-Jan-2004 12:30

	if ($hour === false)  $hour  = Date ('G');
	if ($minute === false) $minute = Date ('i');
	if ($second === false) $second = Date ('s');
	if ($month === false)  $month  = Date ('n');
	if ($date === false)  $date  = Date ('j');
	if ($year === false)  $year  = Date ('Y');

	if ($year >= 1970) return mktime ($hour, $minute, $second, $month, $date, $year);

	$m_days = Array (31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
	if ($year % 4 == 0 && ($year % 100 > 0 || $year % 400 == 0))
	{ $m_days[1] = 29;  }

	$d_year = 1970 - $year;
	$days = -1 - $d_year * 365;
	$days -= floor ($d_year / 4);
	$days += floor (($d_year - 70) / 100);
	$days -= floor (($d_year - 370) / 400);

	for ($i = 1; $i < $month; $i++)
	{ $days += $m_days [$i - 1]; }
	$days += $date - 1;

	$stamp = $days * 86400;
	$stamp += $hour * 3600;
	$stamp += $minute * 60;
	$stamp += $second;

	return $stamp;
}

/**
 * Standard SED output filters, adds XSS protection to forms
 *
 * @param unknown_type $output
 * @return unknown
 */
function sed_outputfilters($output)
{
	global $cfg;

	/* === Hook === */
	$extp = sed_getextplugins('output');
	if (is_array($extp))
	{ foreach($extp as $k => $pl) { include_once($cfg['plugins_dir'].'/'.$pl['pl_code'].'/'.$pl['pl_file'].'.php'); } }
	/* ==== */

	$output = str_replace('</FORM>', '</form>', $output);
	$output = str_replace('</form>', sed_xp().'</form>', $output);

	return($output);
}

/**
 * Renders page navigation bar
 *
 * @param string $url Basic URL
 * @param int $current Current page number
 * @param int $entries Total rows
 * @param int $perpage Rows per page
 * @param string $characters It is symbol for parametre which transfer pagination
 * @param string $onclick Name of JavaScript function which it will be specified in parametre OnClick of the link
 * @param string $object List of pairs parametre:value through a comma. Use when it is necessary to pass in function $onclick not only value of number of page
 * @return string
 */
function sed_pagination($url, $current, $entries, $perpage, $characters = 'd', $onclick = '', $object='')
{
	if(function_exists('sed_pagination_custom'))
	{
		// For custom pagination functions in plugins
		return sed_pagination_custom($url, $current, $entries, $perpage, $characters, $onclick, $object);
	}

	if($entries <= $perpage)
	{
		return '';
	}
	$each_side = 3; // Links each side
	$address = strstr($url, '?') ? $url . '&amp;'.$characters.'=' : $url . '?'.$characters.'=';

	$totalpages = ceil($entries / $perpage);
	$currentpage = floor($current / $perpage) + 1;
	$cur_left = $currentpage - $each_side;
	if($cur_left < 1) $cur_left = 1;
	$cur_right = $currentpage + $each_side;
	if($cur_right > $totalpages) $cur_right = $totalpages;

	$before = '';
	$pages = '';
	$after = '';
	$i = 1;
	$n = 0;
	while($i < $cur_left)
	{
		$k = ($i - 1) * $perpage;
		$listparam = empty($object) ? '' : 'var list = {'.$characters.': '.$k.', '.$object.'}; ';
		$strlistparam = empty($object) ? $k : 'list';
		$event = empty($onclick) ? '' : ' onclick="'.$listparam.'return '.$onclick.'('.$strlistparam.');"';
		$before .= '<span class="pagenav_pages"><a href="'.$address.$k.'"'.$event.'>'.$i.'</a></span>';
		$i *= ($n % 2) ? 2 : 5;
		$n++;
	}
	for($j = $cur_left; $j <= $cur_right; $j++)
	{
		$k = ($j - 1) * $perpage;
		$class = $j == $currentpage ? 'current' : 'pages';
		$listparam = empty($object) ? '' : 'var list = {'.$characters.': '.$k.', '.$object.'}; ';
		$strlistparam = empty($object) ? $k : 'list';
		$event = empty($onclick) ? '' : ' onclick="'.$listparam.'return '.$onclick.'('.$strlistparam.');"';
		$pages .= '<span class="pagenav_'.$class.'"><a href="'.$address.$k.'"'.$event.'>'.$j.'</a></span>';
	}
	while($i <= $cur_right)
	{
		$i *= ($n % 2) ? 2 : 5;
		$n++;
	}
	while($i < $totalpages)
	{
		$k = ($i - 1) * $perpage;
		$listparam = empty($object) ? '' : 'var list = {'.$characters.': '.$k.', '.$object.'}; ';
		$strlistparam = empty($object) ? $k : 'list';
		$event = empty($onclick) ? '' : ' onclick="'.$listparam.'return '.$onclick.'('.$strlistparam.');"';
		$after .= '<span class="pagenav_pages"><a href="'.$address.$k.'"'.$event.'>'.$i.'</a></span>';
		$i *= ($n % 2) ? 5 : 2;
		$n++;
	}
	$pages = $before . $pages . $after;

	return $pages;
}

/**
 * Renders page navigation previous/next buttons
 *
 * @param string $url Basic URL
 * @param int $current Current page number
 * @param int $entries Total rows
 * @param int $perpage Rows per page
 * @param bool $res_array Return results as array
 * @param string $characters It is symbol for parametre which transfer pagination
 * @param string $onclick Name of JavaScript function which it will be specified in parametre OnClick of the link
 * @param string $object List of pairs parametre:value through a comma. Use when it is necessary to pass in function $onclick not only value of number of page
 * @return mixed
 */
function sed_pagination_pn($url, $current, $entries, $perpage, $res_array = FALSE, $characters = 'd', $onclick = '', $object='')
{
	if(function_exists('sed_pagination_pn_custom'))
	{
		// For custom pagination functions in plugins
		return sed_pagination_pn_custom($url, $current, $entries, $perpage, $res_array, $characters, $onclick, $object);
	}

	global $L;

	$address = strstr($url, '?') ? $url . '&amp;'.$characters.'=' : $url . '?'.$characters.'=';
	$totalpages = ceil($entries / $perpage);
	$currentpage = floor($current / $perpage) + 1;

	if ($current > 0)
	{
		$prev_n = $current - $perpage;
		if ($prev_n < 0) { $prev_n = 0; }
		$listparam = empty($object) ? '' : 'var list = {'.$characters.': '.$prev_n.', '.$object.'}; ';
		$strlistparam = empty($object) ? $prev_n : 'list';
		$event = empty($onclick) ? '' : ' onclick="'.$listparam.'return '.$onclick.'('.$strlistparam.');"';
		$prev = '<span class="pagenav_prev"><a href="'.$address.$prev_n.'"'.$event.'>'.$L['pagenav_prev'].'</a></span>';
		$listparam = empty($object) ? '' : 'var list = {'.$characters.': 0, '.$object.'}; ';
		$strlistparam = empty($object) ? 0 : 'list';
		$event = empty($onclick) ? '' : ' onclick="'.$listparam.'return '.$onclick.'('.$strlistparam.');"';
		$first = '<span class="pagenav_first"><a href="'.$address.'0"'.$event.'>'.$L['pagenav_first'].'</a></span>';
	}

	if (($current + $perpage) < $entries)
	{
		$next_n = $current + $perpage;
		$listparam = empty($object) ? '' : 'var list = {'.$characters.': '.$next_n.', '.$object.'}; ';
		$strlistparam = empty($object) ? $next_n : 'list';
		$event = empty($onclick) ? '' : ' onclick="'.$listparam.'return '.$onclick.'('.$strlistparam.');"';
		$next = '<span class="pagenav_next"><a href="'.$address.$next_n.'"'.$event.'>'.$L['pagenav_next'].'</a></span>';
		$last_n = ($totalpages - 1) * $perpage;
		$listparam = empty($object) ? '' : 'var list = {'.$characters.': '.$last_n.', '.$object.'}; ';
		$strlistparam = empty($object) ? $last_n : 'list';
		$event = empty($onclick) ? '' : ' onclick="'.$listparam.'return '.$onclick.'('.$strlistparam.');"';
		$last = '<span class="pagenav_last"><a href="'.$address.$last_n.'"'.$event.'>'.$L['pagenav_last'].'</a></span>';
	}

	$res_l = $first . $prev;
	$res_r = $next . $last;
	return $res_array ? array($res_l, $res_r) : $res_l . ' ' . $res_r;
}

/**
 * Delete all PFS files for a specific user. Returns number of items removed.
 *
 * @param int $userid User ID
 * @return int
 */
function sed_pfs_deleteall($userid)
{
	global $db_pfs_folders, $db_pfs, $cfg;

	if (!$userid)
	{ return; }
	$sql = sed_sql_query("DELETE FROM $db_pfs_folders WHERE pff_userid='$userid'");
	$num = $num + sed_sql_affectedrows();
	$sql = sed_sql_query("DELETE FROM $db_pfs WHERE pfs_userid='$userid'");
	$num = $num + sed_sql_affectedrows();

	$cfg['pfs_dir_user'] = sed_pfs_path($userid);
	$cfg['th_dir_user'] = sed_pfs_thumbpath($userid);

	$bg = $userid.'-';
	$bgl = mb_strlen($bg);

	$handle = @opendir($cfg['pfs_dir_user']);
	while ($f = @readdir($handle))
	{
		if ($cfg['pfsuserfolder'])
		{ @unlink($cfg['pfs_dir_user'].$f);
		}
		elseif (mb_substr($f, 0, $bgl)==$bg)
		{ @unlink($cfg['pfs_dir_user'].$f); }
	}
	@closedir($handle);

	$handle = @opendir($cfg['th_dir_user']);
	while ($f = @readdir($handle))
	{
		if ($cfg['pfsuserfolder'])
		{ @unlink($cfg['th_dir_user'].$f); }
		elseif (mb_substr($f, 0, $bgl)==$bg)
		{ @unlink($cfg['th_dir_user'].$f); }
	}
	@closedir($handle);

	if ($cfg['pfsuserfolder'] && $userid>0)
	{
		@rmdir($cfg['pfs_dir_user']);
		@rmdir($cfg['th_dir_user']);
	}

	return($num);
}

/**
 * Returns PFS path for a user, relative from site root
 *
 * @param int $userid User ID
 * @return string
 */
function sed_pfs_path($userid)
{
	global $cfg;

	if ($cfg['pfsuserfolder'])
	{ return($cfg['pfs_dir'].$userid.'/'); }
	else
	{ return($cfg['pfs_dir']); }
}

/**
 * Returns PFS path for a user, relative from PFS root
 *
 * @param int $userid User ID
 * @return string
 */
function sed_pfs_relpath($userid)
{
	global $cfg;

	if ($cfg['pfsuserfolder'])
	{ return($userid.'/'); }
	else
	{ return(''); }
}

/**
 * Returns absolute path
 *
 * @param unknown_type $userid
 * @return unknown
 */
function sed_pfs_thumbpath($userid)
{
	global $cfg;

	if ($cfg['pfsuserfolder'])
	{ return($cfg['th_dir'].$userid.'/'); }
	else
	{ return($cfg['th_dir']); }
}

/**
 * Reads raw data from file
 *
 * @param string $file File path
 * @return string
 */
function sed_readraw($file)
{
	if(!strstr($file, '..') && file_exists($file))
	{
		return file_get_contents($file);
	}
	else
	{
		return 'File not found : '.$file;
	}
}

/**
 * Displays redirect page
 *
 * @param string $url Target URI
 */
function sed_redirect($url)
{
	global $cfg;

	if ($cfg['redirmode'])
	{
		$output = $cfg['doctype']."
		<html>
		<head>
		<meta http-equiv=\"content-type\" content=\"text/html; charset=".$cfg['charset']."\" />
		<meta http-equiv=\"refresh\" content=\"0; url=".SED_ABSOLUTE_URL . $url."\" />
		<title>Redirecting...</title></head>
		<body>Redirecting to <a href=\"". SED_ABSOLUTE_URL .$url."\">".$cfg['mainurl']."/".$url."</a>
		</body>
		</html>";
		header("Refresh: 0; URL=". SED_ABSOLUTE_URL .$url);
		echo($output);
		exit;
	}
	else
	{
		header("Location: " . SED_ABSOLUTE_URL . $url);
		exit;
	}
	return;
}

/**
 * Strips all unsafe characters from file base name and converts it to latin
 *
 * @param string $basename File base name
 * @param bool $underscore Convert spaces to underscores
 * @return string
 */
function sed_safename($basename, $underscore = true)
{
	global $lang, $sed_translit;
	$fname = mb_substr($basename, 0, mb_strrpos($basename, '.'));
	$ext = mb_substr($basename, mb_strrpos($basename, '.') + 1);
	if($lang != 'en' && is_array($sed_translit))
	{
		$fname = strtr($fname, $sed_translit);
	}
	if($underscore) $fname = str_replace(' ', '_', $fname);
	$fname = preg_replace('#[^a-zA-Z0-9\-_\.\ \+]#', '', $fname);
	$fname = str_replace('..', '.', $fname);
	if(empty($fname)) $fname = sed_unique();
	return $fname . '.' . mb_strtolower($ext);
}


/**
 * Renders a dropdown
 *
 * @param string $check Seleced value
 * @param string $name Dropdown name
 * @param array $values Options available
 * @return string
 */
function sed_selectbox($check, $name, $values)
{
	$check = trim($check);
	$values = explode(',', $values);
	$selected = (empty($check) || $check=="00") ? "selected=\"selected\"" : '';
	$result =  "<select name=\"$name\" size=\"1\"><option value=\"\" $selected>---</option>";
	foreach ($values as $k => $x)
	{
		$x = trim($x);
		$selected = ($x == $check) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"$x\" $selected>".sed_cc($x)."</option>";
	}
	$result .= "</select>";
	return($result);
}

/**
 * Renders category dropdown
 *
 * @param string $check Seleced value
 * @param string $name Dropdown name
 * @param bool $hideprivate Hide private categories
 * @return string
 */
function sed_selectbox_categories($check, $name, $hideprivate=TRUE)
{
	global $db_structure, $usr, $sed_cat, $L;

	$result =  "<select name=\"$name\" size=\"1\">";

	foreach($sed_cat as $i => $x)
	{
		$display = ($hideprivate) ? sed_auth('page', $i, 'W') : TRUE;

		if (sed_auth('page', $i, 'R') && $i!='all' && $display)
		{
			$selected = ($i==$check) ? "selected=\"selected\"" : '';
			$result .= "<option value=\"".$i."\" $selected> ".$x['tpath']."</option>";
		}
	}
	$result .= "</select>";
	return($result);
}

/**
 * Renders country dropdown
 *
 * @param string $check Seleced value
 * @param string $name Dropdown name
 * @return string
 */
function sed_selectbox_countries($check,$name)
{
	global $sed_countries;

	$selected = (empty($check) || $check=='00') ? "selected=\"selected\"" : '';
	$result =  "<select name=\"$name\" size=\"1\">";
	foreach($sed_countries as $i => $x)
	{
		$selected = ($i==$check) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"$i\" $selected>".$x."</option>";
	}
	$result .= "</select>";

	return($result);
}

/**
 * Generates date part dropdown
 *
 * @param int $utime Selected timestamp
 * @param string $mode Display mode: 'short' or complete
 * @param string $ext Variable name suffix
 * @return string
 */
function sed_selectbox_date($utime, $mode, $ext='')
{
	global $L;
	list($s_year, $s_month, $s_day, $s_hour, $s_minute) = explode('-', @date('Y-m-d-H-i', $utime));
	$p_monthes = array();
	$p_monthes[] = array(1, $L['January']);
	$p_monthes[] = array(2, $L['February']);
	$p_monthes[] = array(3, $L['March']);
	$p_monthes[] = array(4, $L['April']);
	$p_monthes[] = array(5, $L['May']);
	$p_monthes[] = array(6, $L['June']);
	$p_monthes[] = array(7, $L['July']);
	$p_monthes[] = array(8, $L['August']);
	$p_monthes[] = array(9, $L['September']);
	$p_monthes[] = array(10, $L['October']);
	$p_monthes[] = array(11, $L['November']);
	$p_monthes[] = array(12, $L['December']);

	$result = "<select name=\"ryear".$ext."\">";
	for ($i = 1902; $i<2030; $i++)
	{
		$selected = ($i==$s_year) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"$i\" $selected>$i</option>";
	}
	$result .= ($utime==0) ? "<option value=\"0\" selected=\"selected\">---</option>" : "<option value=\"0\">---</option>";

	$result .= "</select><select name=\"rmonth".$ext."\">";
	reset($p_monthes);
	foreach ($p_monthes as $k => $line)
	{
		$selected = ($line[0]==$s_month) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"".$line[0]."\" $selected>".$line[1]."</option>";
	}
	$result .= ($utime==0) ? "<option value=\"0\" selected=\"selected\">---</option>" : "<option value=\"0\">---</option>";

	$result .= "</select><select name=\"rday".$ext."\">";
	for ($i = 1; $i<32; $i++)
	{
		$selected = ($i==$s_day) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"$i\" $selected>$i</option>";
	}
	$result .= ($utime==0) ? "<option value=\"0\" selected=\"selected\">---</option>" : "<option value=\"0\">---</option>";
	$result .= "</select> ";

	if ($mode=='short')
	{ return ($result); }

	$result .= " <select name=\"rhour".$ext."\">";
	for ($i = 0; $i<24; $i++)
	{
		$selected = ($i==$s_hour) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"$i\" $selected>".sprintf("%02d",$i)."</option>";
	}
	$result .= ($utime==0) ? "<option value=\"0\" selected=\"selected\">---</option>" : "<option value=\"0\">---</option>";

	$result .= "</select>:<select name=\"rminute".$ext."\">";
	for ($i = 0; $i<60; $i=$i+1)
	{
		$selected = ($i==$s_minute) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"$i\" $selected>".sprintf("%02d",$i)."</option>";
	}
	$result .= ($utime==0) ? "<option value=\"0\" selected=\"selected\">---</option>" : "<option value=\"0\">---</option>";
	$result .= "</select>";

	return ($result);
}

/**
 * Renders PFS folder selection dropdown
 *
 * @param int $user User ID
 * @param int $skip Skip folder
 * @param int $check Checked folder
 * @return string
 */
function sed_selectbox_folders($user, $skip, $check)
{
	global $db_pfs_folders;

	$sql = sed_sql_query("SELECT pff_id, pff_title, pff_isgallery, pff_ispublic FROM $db_pfs_folders WHERE pff_userid='$user' ORDER BY pff_title ASC");

	$result =  "<select name=\"folderid\" size=\"1\">";

	if ($skip!="/" && $skip!="0")
	{
		$selected = (empty($check) || $check=="/") ? "selected=\"selected\"" : '';
		$result .=  "<option value=\"0\" $selected>/ &nbsp; &nbsp;</option>";
	}

	while ($row = sed_sql_fetcharray($sql))
	{
		if ($skip!=$row['pff_id'])
		{
			$selected = ($row['pff_id']==$check) ? "selected=\"selected\"" : '';
			$result .= "<option value=\"".$row['pff_id']."\" $selected>".sed_cc($row['pff_title'])."</option>";
		}
	}
	$result .= "</select>";
	return ($result);
}

/**
 * Returns forum category dropdown code
 *
 * @param int $check Selected category
 * @param string $name Dropdown name
 * @return string
 */
function sed_selectbox_forumcat($check, $name)
{
	global $usr, $sed_forums_str, $L;

	$result =  "<select name=\"$name\" size=\"1\">";

	foreach($sed_forums_str as $i => $x)
	{
		$selected = ($i==$check) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"".$i."\" $selected> ".$x['tpath']."</option>";
	}
	$result .= "</select>";
	return($result);
}


/**
 * Generates gender dropdown
 *
 * @param string $check Checked gender
 * @param string $name Input name
 * @return string
 */
function sed_selectbox_gender($check,$name)
{
	global $L;

	$genlist = array ('U', 'M', 'F');
	$result =  "<select name=\"$name\" size=\"1\">";
	foreach(array ('U', 'M', 'F') as $i)
	{
		$selected = ($i==$check) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"$i\" $selected>".$L['Gender_'.$i]."</option>";
	}
	$result .= "</select>";
	return($result);
}

/**
 * Returns group selection dropdown code
 *
 * @param string $check Seleced value
 * @param string $name Dropdown name
 * @param array $skip Hidden groups
 * @return string
 */
function sed_selectbox_groups($check, $name, $skip=array(0))
{
	global $sed_groups;

	$res = "<select name=\"$name\" size=\"1\">";

	foreach($sed_groups as $k => $i)
	{
		$selected = ($k==$check) ? "selected=\"selected\"" : '';
		$res .= (in_array($k, $skip)) ? '' : "<option value=\"$k\" $selected>".$sed_groups[$k]['title']."</option>";
	}
	$res .= "</select>";

	return($res);
}

/**
 * Returns language selection dropdown
 *
 * @param string $check Seleced value
 * @param string $name Dropdown name
 * @return string
 */
function sed_selectbox_lang($check, $name)
{
	global $sed_languages, $sed_countries, $cfg;

	$handle = opendir($cfg['system_dir'].'/lang/');
	while ($f = readdir($handle))
	{
		if ($f[0] != '.')
		{ $langlist[] = $f; }
	}
	closedir($handle);
	sort($langlist);

	$result = "<select name=\"$name\" size=\"1\">";
	while(list($i,$x) = each($langlist))
	{
		$selected = ($x==$check) ? "selected=\"selected\"" : '';
		$lng = (empty($sed_languages[$x])) ? $sed_countries[$x] : $sed_languages[$x];
		$result .= "<option value=\"$x\" $selected>".$lng." (".$x.")</option>";
	}
	$result .= "</select>";

	return($result);
}

/**
 * Renders forum section selection dropdown
 *
 * @param string $check Seleced value
 * @param string $name Dropdown name
 * @return string
 */
function sed_selectbox_sections($check, $name)
{
	global $db_forum_sections, $cfg;

	$sql = sed_sql_query("SELECT fs_id, fs_title, fs_category FROM $db_forum_sections WHERE 1 ORDER by fs_order ASC");
	$result = "<select name=\"$name\" size=\"1\">";
	while ($row = sed_sql_fetcharray($sql))
	{
		$selected = ($row['fs_id'] == $check) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"".$row['fs_id']."\" $selected>".sed_cc(sed_cutstring($row['fs_category'], 24));
		$result .= ' '.$cfg['separator'].' '.sed_cc(sed_cutstring($row['fs_title'], 32));
	}
	$result .= "</select>";
	return($result);
}

/**
 * Returns skin selection dropdown
 *
 * @param string $check Seleced value
 * @param string $name Dropdown name
 * @return string
 */
function sed_selectbox_skin($check, $name)
{
	$handle = opendir('skins/');
	while ($f = readdir($handle))
	{
		if (mb_strpos($f, '.') === FALSE && is_dir('skins/' . $f))
		{ $skinlist[] = $f; }
	}
	closedir($handle);
	sort($skinlist);

	$result = '<select name="'.$name.'" size="1">';
	while(list($i,$x) = each($skinlist))
	{
		$selected = ($x==$check) ? 'selected="selected"' : '';
		$skininfo = "skins/$x/$x.php";
		if (file_exists($skininfo))
		{
			$info = sed_infoget($skininfo);
			$result .= (!empty($info['Error'])) ? '<option value="'.$x.'" '.$selected.'>'.$x.' ('.$info['Error'].')' : '<option value="'.$x.'" '.$selected.'>'.$info['Name'];
		}
		else
		{
			$result .= '<option value="'.$x.'" $selected>'.$x;
		}
		$result .= '</option>';
	}
	$result .= '</select>';

	return $result;
}

/**
 * Returns skin selection dropdown
 *
 * @param string $skinname Skin name
 * @param string $name Dropdown name
 * @param string $theme Selected theme
 * @return string
 */
function sed_selectbox_theme($skinname, $name, $theme)
{
	global $skin_themes;

	if(empty($skin_themes))
	{
		if(file_exists("skins/$skinname/$skinname.css"))
		{
			$skin_themes = array($skinname => $skinname);
		}
		else
		{
			$skin_themes = array('style' => $skinname);
		}
	}

	$result = '<select name="'.$name.'" size="1">';
	foreach($skin_themes as $x => $tname)
	{
		$selected = ($x==$theme) ? 'selected="selected"' : '';
		$result .= '<option value="'.$x.'" '.$selected.'>'.$tname.'</option>';
	}
	$result .= '</select>';

	return $result;
}

/**
 * Gets huge user selection box
 *
 * @param int $to Selected user ID
 * @return string
 */
function sed_selectbox_users($to)
{
	global $db_users;

	$result = "<select name=\"userid\">";
	$sql = sed_sql_query("SELECT user_id, user_name FROM $db_users ORDER BY user_name ASC");
	while ($row = sed_sql_fetcharray($sql))
	{
		$selected = ($row['user_id']==$to) ? "selected=\"selected\"" : '';
		$result .= "<option value=\"".$row['user_id']."\" $selected>".sed_cc($row['user_name'])."</option>";
	}
	$result .= "</select>";
	return($result);
}

/**
 * Sends standard HTTP headers and disables browser cache
 *
 * @return bool
 */
function sed_sendheaders()
{
	global $cfg;
	$contenttype = ($cfg['doctypeid']>2 && $cfg['xmlclient']) ? 'application/xhtml+xml' : 'text/html';
	header('Expires: Fri, Apr 01 1974 00:00:00 GMT');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: post-check=0,pre-check=0', FALSE);
	header('Content-Type: '.$contenttype.'; charset='.$cfg['charset']);
	header('Cache-Control: no-store,no-cache,must-revalidate');
	header('Cache-Control: post-check=0,pre-check=0', FALSE);
	header('Pragma: no-cache');
	return(TRUE);
}

/* ------------------ */
// TODO this function is obsolete, doctype should be set in header.tpl
function sed_setdoctype($type)
{
	switch($type)
	{
		case '0': // HTML 4.01
			return ("<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\">");
			break;

		case '1': // HTML 4.01 Transitional
			return ("<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">");
			break;

		case '2': // HTML 4.01 Frameset
			return ("<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Frameset//EN\" \"http://www.w3.org/TR/html4/frameset.dtd\">");
			break;

		case '3': // XHTML 1.0 Strict
			return ("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">");
			break;

		case '4': // XHTML 1.0 Transitional
			return ("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">");
			break;

		case '5': // XHTML 1.0 Frameset
			return ("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Frameset//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd\">");
			break;

		case '6': // XHTML 1.1
			return ("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">");
			break;

		case '7': // XHTML 2  ;]
			return ("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 2//EN\" \"http://www.w3.org/TR/xhtml2/DTD/xhtml2.dtd\">");
			break;

		default: // ...
			return ("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">");
			break;
	}
}

/**
 * Clears current user action in Who's online.
 *
 */
function sed_shield_clearaction()
{
	global  $db_online, $usr;

	$sql = sed_sql_query("UPDATE $db_online SET online_action='' WHERE online_ip='".$usr['ip']."'");
}

/**
 * Anti-hammer protection
 *
 * @param int $hammer Hammer rate
 * @param string $action Action type
 * @param int $lastseen User last seen timestamp
 * @return int
 */
function sed_shield_hammer($hammer,$action, $lastseen)
{
	global $cfg, $sys, $usr;

	if ($action=='Hammering')
	{
		sed_shield_protect();
		sed_shield_clearaction();
		sed_stat_inc('totalantihammer');
	}

	if (($sys['now']-$lastseen)<4)
	{
		$hammer++;
		if($hammer>$cfg['shieldzhammer'])
		{
			sed_shield_update(180, 'Hammering');
			sed_log('IP banned 3 mins, was hammering', 'sec');
			$hammer = 0;
		}
	}
	else
	{
		if ($hammer>0)
		{ $hammer--; }
	}
	return($hammer);
}

/**
 * Warn user of shield protection
 *
 */
function sed_shield_protect()
{
	global $cfg, $sys, $online_count, $shield_limit, $shield_action;

	if ($cfg['shieldenabled'] && $online_count>0 && $shield_limit>$sys['now'])
	{
		sed_diefatal('Shield protection activated, please retry in '.($shield_limit-$sys['now']).' seconds...<br />After this duration, you can refresh the current page to continue.<br />Last action was : '.$shield_action);
	}
}

/**
 * Updates shield state
 *
 * @param int $shield_add Hammer
 * @param string $shield_newaction New action type
 */
function sed_shield_update($shield_add, $shield_newaction)
{
	global $cfg, $usr, $sys, $db_online;
	if ($cfg['shieldenabled'])
	{
		$shield_newlimit = $sys['now'] + floor($shield_add * $cfg['shieldtadjust'] /100);
		$sql = sed_sql_query("UPDATE $db_online SET online_shield='$shield_newlimit', online_action='$shield_newaction' WHERE online_ip='".$usr['ip']."'");
	}
}

/**
 * Returns skin file path
 *
 * @param string $base Item name
 * @return string
 */
function sed_skinfile($base, $plug = false)
{
	global $usr, $cfg;
	if($plug)
	{
		$bname = strstr($base, '.') ? mb_substr($base, 0, mb_strpos($base, '.')) : $base;
		if(file_exists('./skins/'.$usr['skin'].'/plugin.standalone.'.$base.'.tpl'))
		{
			return './skins/'.$usr['skin'].'/plugin.standalone.'.$base.'.tpl';
		}
		elseif(file_exists('./skins/'.$usr['skin'].'/'.$base.'.tpl'))
		{
			return './skins/'.$usr['skin'].'/'.$base.'.tpl';
		}
		elseif(file_exists('./skins/'.$cfg['defaultskin'].'/plugin.standalone.'.$base.'.tpl'))
		{
			return './skins/'.$cfg['defaultskin'].'/plugin.standalone.'.$base.'.tpl';
		}
		elseif(file_exists('skins/'.$cfg['defaultskin'].'/'.$base.'.tpl'))
		{
			return 'skins/'.$cfg['defaultskin'].'/'.$base.'.tpl';
		}
		elseif(file_exists($cfg['plugins_dir'].'/'.$bname.'/tpl/'.$base.'.tpl'))
		{
			return $cfg['plugins_dir'].'/'.$bname.'/tpl/'.$base.'.tpl';
		}
		else
		{
			return $cfg['plugins_dir'].'/'.$bname.'/'.$base.'.tpl';
		}
	}
	$base_depth = count($base);
	if($base_depth==1)
	{
		if(file_exists('skins/'.$usr['skin'].'/'.$base.'.tpl'))
		{
			return 'skins/'.$usr['skin'].'/'.$base.'.tpl';
		}
		else
		{
			return 'skins/'.$cfg['defaultskin'].'/'.$base.'.tpl';
		}

	}

	for($i=$base_depth; $i>1; $i--)
	{
		$levels = array_slice($base, 0, $i);
		$skinfile = 'skins/'.$usr['skin'].'/'.implode('.', $levels).'.tpl';
		if(file_exists($skinfile))
		{
			return $skinfile;
		}
		$skinfile = 'skins/'.$cfg['defaultskin'].'/'.implode('.', $levels).'.tpl';
		if(file_exists($skinfile))
		{
			return $skinfile;
		}
	}
	return 'skins/'.$usr['skin'].'/'.$base[0].'.tpl';
}


/**
 * Parses smiles in text
 *
 * @param string $res Source text
 * @return string
 */
function sed_smilies($res)
{
	global $sed_smilies;

	if (is_array($sed_smilies))
	{
		foreach($sed_smilies as $k => $v)
		{ $res = str_replace($v['smilie_code'],"<img src=\"".$v['smilie_image']."\" alt=\"\" />", $res); }
	}
	return($res);
}

/**
 * Gets XSS protection code
 *
 * @return string
 */
function sed_sourcekey()
{
	global $usr;
	return $usr['id'] > 0 ? $_SESSION['sourcekey'] : 'GUEST';
}

/*
 * ===================================== Statistics API ==========================================
 */

/**
 * Creates new stats parameter
 *
 * @param string $name Parameter name
 */
function sed_stat_create($name)
{
	global $db_stats;

	$sql = sed_sql_query("INSERT INTO $db_stats (stat_name, stat_value) VALUES ('".sed_sql_prep($name)."', 1)");
	return;
}

/**
 * Returns statistics parameter
 *
 * @param string $name Parameter name
 * @return int
 */
function sed_stat_get($name)
{
	global $db_stats;

	$sql = sed_sql_query("SELECT stat_value FROM $db_stats where stat_name='$name' LIMIT 1");
	$result = (sed_sql_numrows($sql)>0) ? sed_sql_result($sql, 0, 'stat_value') : FALSE;
	return($result);
}

/**
 * Increments stats
 *
 * @param string $name Parameter name
 */
function sed_stat_inc($name)
{
	global $db_stats;

	$sql = sed_sql_query("UPDATE $db_stats SET stat_value=stat_value+1 WHERE stat_name='$name'");
	return;
}

/*
 * =========================================================================================
 */

/**
 * Returns substring position in file
 *
 * @param string $file File path
 * @param string $str Needle
 * @param int $maxsize Search limit
 * @return int
 */
function sed_stringinfile($file, $str, $maxsize=32768)
{
	if ($fp = @fopen($file, 'r'))
	{
		$data = fread($fp, $maxsize);
		$pos = mb_strpos($data, $str);
		$result = !($pos===FALSE);
	}
	else
	{ $result = FALSE; }
	@fclose($fp);
	return ($result);
}

/*
 * ===================================== Tags API ==========================================
 */

/**
 * Tags a given item from a specific area with a keyword
 *
 * @param string $tag The tag (keyword)
 * @param int $item Item ID
 * @param string $area Site area code (e.g. 'pages', 'forums', 'blog')
 * @return bool
 */
function sed_tag($tag, $item, $area = 'pages')
{
	global $db_tag_references;
	$item = (int) $item;
	if(sed_tag_isset($tag, $item, $area))
	{
		return false;
	}
	sed_sql_query("INSERT INTO $db_tag_references VALUES('$tag', $item, '$area')");
	sed_tag_register($tag);
	return true;
}

/**
 * Collects data for a tag cloud in some area. The result is an associative array with
 * tags as keys and count of entries as values.
 *
 * @param string $area Site area
 * @param string $order Should be 'tag' to order the result set by tag (alphabetical) or 'cnt' to order it by item count (descending)
 * @param int $limit Use this parameter to limit number of rows in the result set
 * @return array
 */
function sed_tag_cloud($area = 'pages', $order = 'tag', $limit = null)
{
	global $db_tag_references;
	$res = array();
	$limit = is_null($limit) ? '' : ' LIMIT ' . $limit;
	$order = $order == 'tag' ? '`tag`' : '`cnt` DESC';
	$sql = sed_sql_query("SELECT `tag`, COUNT(*) AS `cnt`
		FROM $db_tag_references
		WHERE tag_area = '$area'
		GROUP BY `tag`
		ORDER BY $order $limit");
	while($row = sed_sql_fetchassoc($sql))
	{
		$res[$row['tag']] = $row['cnt'];
	}
	sed_sql_freeresult($sql);
	return $res;
}

/**
 * Gets an array of autocomplete options for a given tag
 *
 * @param string $tag Beginning of a tag
 * @param int $min_length Minimal length of the beginning
 * @return array
 */
function sed_tag_complete($tag, $min_length = 3)
{
	global $db_tags;
	if(mb_strlen($tag) < $min_length)
	{
		return false;
	}
	$res = array();
	$sql = sed_sql_query("SELECT `tag` FROM $db_tags WHERE `tag` LIKE '$tag%'");
	while($row = sed_sql_fetchassoc($sql))
	{
		$res[] = $row['tag'];
	}
	sed_sql_freeresult($sql);
	return $res;
}

/**
 * Returns number of items tagged with a specific keyword
 *
 * @param string $tag The tag (keyword)
 * @param string $area Site area or empty to count in all areas
 * @return int
 */
function sed_tag_count($tag, $area = '')
{
	global $db_tag_references;
	$query = "SELECT COUNT(*) FROM $db_tag_references WHERE `tag` = '$tag'";
	if(!empty($area))
	{
		$query .= " AND tag_area = '$area'";
	}
	return (int) sed_sql_result(sed_sql_query($query), 0, 0);
}

/**
 * Checks whether the tag has already been registered in the dictionary
 *
 * @param string $tag The tag
 * @return bool
 */
function sed_tag_exists($tag)
{
	global $db_tags;
	return sed_sql_result(sed_sql_query("SELECT COUNT(*) FROM $db_tags WHERE `tag` = '$tag'"), 0, 0) == 1;
}

/**
 * Checks whether a tag has been already set on a specific item
 *
 * @param string $tag The tag (keyword)
 * @param int $item Item ID
 * @param string $area Site area code (e.g. 'pages', 'forums', 'blog')
 * @return bool
 */
function sed_tag_isset($tag, $item, $area = 'pages')
{
	global $db_tag_references;
	$item = (int) $item;
	$sql = sed_sql_query("SELECT COUNT(*) FROM $db_tag_references WHERE `tag` = '$tag' AND tag_item = $item AND tag_area = '$area'");
	return sed_sql_result($sql, 0, 0) == 1;
}

/**
 * Returns an array containing tags which have been set on an item
 *
 * @param int $item Item ID
 * @param string $area Site area code (e.g. 'pages', 'forums', 'blog')
 * @return array
 */
function sed_tag_list($item, $area = 'pages')
{
	global $db_tag_references;
	$res = array();
	$sql = sed_sql_query("SELECT `tag` FROM $db_tag_references WHERE tag_item = $item AND tag_area = '$area'");
	while($row = sed_sql_fetchassoc($sql))
	{
		$res[] = $row['tag'];
	}
	sed_sql_freeresult($sql);
	return $res;
}

/**
 * Parses user input into array of valid and safe tags
 *
 * @param string $input Comma separated user input
 * @return array
 */
function sed_tag_parse($input)
{
	$res = array();
	$invalid = array('`', '^', ':', '?', '=', '|', '\\', '/', '"', "\t", "\r\n", "\n");
	$tags = explode(',', $input);
	foreach($tags as $tag)
	{
		$tag = str_replace($invalid, ' ', $tag);
		$tag = preg_replace('#\s\s+#', ' ', $tag);
		$tag = trim($tag);
		if(!empty($tag))
		{
			$res[] = sed_tag_prep($tag);
		}
	}
	$res = array_unique($res);
	return $res;
}

/**
 * Convert the tag to lowercase and prepare it for SQL operations. Please call this after sed_import()!
 *
 * @param string $tag The tag
 * @return string
 */
function sed_tag_prep($tag)
{
	return sed_sql_prep(mb_strtolower($tag));
}

/**
 * Attempts to register a tag in the dictionary. Duplicate entries are just ignored.
 *
 * @param string $tag The tag
 */
function sed_tag_register($tag)
{
	global $db_tags;
	sed_sql_query("INSERT IGNORE INTO $db_tags VALUES('$tag')");
}

/**
 * Removes tag reference from a specific item
 *
 * @param string $tag The tag (keyword)
 * @param int $item Item ID
 * @param string $area Site area code (e.g. 'pages', 'forums', 'blog')
 * @return bool
 */
function sed_tag_remove($tag, $item, $area = 'pages')
{
	global $db_tag_references;
	if(sed_tag_isset($tag, $item, $area))
	{
		sed_sql_query("DELETE FROM $db_tag_references WHERE `tag` = '$tag' AND tag_item = $item AND tag_area = '$area'");
		return true;
	}
	return false;
}

/**
 * Removes all tags attached to an item, or all tags from area if item is set to 0.
 * Returns number of tag references affected.
 *
 * @param int $item Item ID
 * @param string $area Site area
 * @return int
 */
function sed_tag_remove_all($item = 0, $area = 'pages')
{
	global $db_tag_references;
	if($item == 0)
	{
		sed_sql_query("DELETE FROM $db_tag_references WHERE tag_area = '$area'");
	}
	else
	{
		sed_sql_query("DELETE FROM $db_tag_references WHERE tag_item = $item AND tag_area = '$area'");
	}
	return sed_sql_affectedrows();
}

/**
 * Converts a lowercase tag into title-case string (capitalizes first latters of the words)
 *
 * @param string $tag A tag
 * @return string
 */
function sed_tag_title($tag)
{
	return mb_convert_case($tag, MB_CASE_TITLE);
}

/**
 * Unregisters a tag from the dictionary
 *
 * @param string $tag The tag
 */
function sed_tag_unregister($tag)
{
	global $db_tags;
	sed_sql_query("DELETE FROM $db_tags WHERE `tag` = '$tag'");
}

/*
 * ==========================================================================================
 */


/**
 * Returns a String afterbeing processed by a sprintf mask for titles
 *
 * @param string $area Area maskname or actual mask
* @param array $tags Tag Masks
 * @param array $data title options
  * @return string
 */
function sed_title($mask, $tags, $data)
{
	global $cfg;
	$mask = (!empty($cfg[$mask])) ? $cfg[$mask] : $mask;
	$mask = str_replace($tags[0], $tags[1], $mask);
	$title = vsprintf($mask, $data);
	return $title;
}

/**
 * Sends item to trash
 *
 * @param string $type Item type
 * @param string $title Title
 * @param int $itemid Item ID
 * @param mixed $datas Item contents
 */
function sed_trash_put($type, $title, $itemid, $datas)
{
	global $db_trash, $sys, $usr;

	$sql = sed_sql_query("INSERT INTO $db_trash (tr_date, tr_type, tr_title, tr_itemid, tr_trashedby, tr_datas)
	VALUES
	(".$sys['now_offset'].", '".sed_sql_prep($type)."', '".sed_sql_prep($title)."', '".sed_sql_prep($itemid)."', ".$usr['id'].", '".sed_sql_prep(serialize($datas))."')");
}

/**
 * Generates random string
 *
 * @param int $l Length
 * @return string
 */
function sed_unique($l=16)
{
	return(mb_substr(md5(mt_rand(0,1000000)), 0, $l));
}

/**
 * Loads URL Transformation Rules
 *
 */
function sed_load_urltrans()
{
	global $sed_urltrans;
	$sed_urltrans = array();
	$fp = fopen('./datas/urltrans.dat', 'r');
	// Rules
	while($line = trim(fgets($fp), " \t\r\n"))
	{
		$parts = explode("\t", $line);
		$rule = array();
		$rule['trans'] = $parts[2];
		$parts[1] == '*' ? $rule['params'] = array() : parse_str($parts[1], $rule['params']);
		foreach($rule['params'] as $key => $val)
		{
			if(strstr($val, '|'))
			{
				$rule['params'][$key] = explode('|', $val);
			}
		}
		$sed_urltrans[$parts[0]][] = $rule;
	}
	fclose($fp);
}

/**
 * Transforms parameters into URL by following user-defined rules
 *
 * @param string $name Site area or script name
 * @param mixed $params URL parameters as array or parameter string
 * @param string $tail URL postfix, e.g. anchor
 * @param bool $header Set this TRUE if the url will be used in HTTP header rather than body output
 * @return string
 */
function sed_url($name, $params = '', $tail = '', $header = false)
{
	global $cfg, $sed_urltrans;
	// Preprocess arguments
	is_array($params) ? $args = $params : parse_str($params, $args);
	$area = empty($sed_urltrans[$name]) ? '*' : $name;
	// Find first matching rule
	$url = $sed_urltrans['*'][0]['trans']; // default rule
	$rule = array();
	if(!empty($sed_urltrans[$area]))
	{
		foreach($sed_urltrans[$area] as $rule)
		{
			$matched = true;
			foreach($rule['params'] as $key => $val)
			{
				if(empty($args[$key])
					|| (is_array($val) && !in_array($args[$key], $val))
					|| ($val != '*' && $args[$key] != $val))
				{
					$matched = false;
					break;
				}
			}
			if($matched)
			{
				$url = $rule['trans'];
				break;
			}
		}
	}
	// Some special substitutions
	$mainurl = parse_url($cfg['mainurl']);
	$spec['_area'] = $name;
	$spec['_host'] = $mainurl['host'];
	$spec['_rhost'] = $_SERVER['HTTP_HOST'];
	$spec['_path'] = SED_SITE_URI;
	// Transform the data into URL
	if(preg_match_all('#\{\$(\w+)\}#', $url, $matches, PREG_SET_ORDER))
	{
		foreach($matches as $m)
		{
			if(isset($spec[$m[1]]))
			{
				$url = str_replace($m[0], urlencode($spec[$m[1]]), $url);
			}
			elseif(isset($args[$m[1]]))
			{
				$url = str_replace($m[0], urlencode($args[$m[1]]), $url);
				unset($args[$m[1]]);
			}
			else
			{
				$url = str_replace($m[0], urlencode($GLOBALS[$m[1]]), $url);
			}
		}
	}
	// Append query string if needed
	if(!empty($args))
	{
		$qs = '?';
		$sep = $header ? '&' : '&amp;';
		$sep_len = strlen($sep);
		foreach($args as $key => $val)
		{
			// Exclude static parameters that are not used in format,
			// they should be passed by rewrite rule (htaccess)
			if($rule['params'][$key] != $val)
			{
				$qs .= $key .'=' . urlencode($val) . $sep;
			}
		}
		$qs = substr($qs, 0, -$sep_len);
		$url .= $qs;
	}
	// Almost done
	$url .= $tail;
	$url = str_replace('&amp;amp;', '&amp;', $url);
	return $url;
}

/**
 * Encodes a string for use in URLs
 *
 * @param string $str Source string
 * @param bool $translit Transliterate non-English characters
 * @return string
 */

function sed_urlencode($str, $translit = false)
{
	global $lang, $sed_translit;
	if($translit && $lang != 'en' && is_array($sed_translit))
	{
		// Apply transliteration
		$str = strtr($str, $sed_translit);
	}
	return urlencode($str);
}

/**
 * Decodes a string that has been previously encoded with sed_urlencode()
 *
 * @param string $str Encoded string
 * @param bool $translit Transliteration of non-English characters was used
 * @return string
 */
function sed_urldecode($str, $translit = false)
{
	global $lang, $sed_translitb;
	if($translit && $lang != 'en' && is_array($sed_translitb))
	{
		// Apply transliteration
		$str = strtr($str, $sed_translitb);
	}
	return urldecode($str);
}

/**
 * Fetches user entry from DB
 *
 * @param int $id User ID
 * @return array
 */
function sed_userinfo($id)
{
	global $db_users;

	$sql = sed_sql_query("SELECT * FROM $db_users WHERE user_id='$id'");
	if ($res = sed_sql_fetcharray($sql))
	{ return ($res); }
	else
	{
		$res['user_name'] = '?';
		return ($res);
	}
}

/**
 * Checks whether user is online
 *
 * @param int $id User ID
 * @return bool
 */
function sed_userisonline($id)
{
	global $sed_usersonline;

	$res = FALSE;
	if (is_array($sed_usersonline))
	{ $res = (in_array($id,$sed_usersonline)) ? TRUE : FALSE; }
	return ($res);
}

/**
 * Wraps text
 *
 * @param string $str Source text
 * @param int $wrap Wrapping boundary
 * @return string
 */
function sed_wraptext($str,$wrap=128)
{
	if (!empty($str))
	{ $str = preg_replace("/([^\n\r ?&\.\/<>\"\\-]{80})/i"," \\1\n", $str); }
	return($str);
}

/**
 * Returns XSS protection variable for GET URLs
 *
 * @return unknown
 */
function sed_xg()
{
	return ('x='.sed_sourcekey());
}

/**
 * Returns XSS protection field for POST forms
 *
 * @return string
 */
function sed_xp()
{
	return ('<div><input type="hidden" name="x" value="'.sed_sourcekey().'" /></div>');
}

/**
 * Set cookie with optional HttpOnly flag
 * @param string $name The name of the cookie
 * @param string $value The value of the cookie
 * @param int $expire The time the cookie expires in unixtime
 * @param string $path The path on the server in which the cookie will be available on.
 * @param string $domain The domain that the cookie is available.
 * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection. When set to TRUE, the cookie will only be set if a secure connection exists.
 * @param bool $httponly HttpOnly flag
 * @return bool
 */
function sed_setcookie($name, $value, $expire, $path, $domain, $secure = false, $httponly = false)
{
	if (PHP_VERSION >= '5.2.0')
	{ return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly); }

	if (!$httponly && (PHP_VERSION >= '4.0.4'))
	{ return setcookie($name, $value, $expire, $path, $domain, $secure); }

	if (trim($domain) != '')
	{ $domain .= ($secure ? '; secure' : '') . ($httponly ? '; httponly' : ''); }
	return setcookie($name, $value, $expire, $path, $domain);
}

/* ============== FLAGS AND COUNTRIES (ISO 3166) =============== */

$sed_languages['de']= 'Deutsch';
$sed_languages['dk']= 'Dansk';
$sed_languages['es']= 'Espa�ol';
$sed_languages['fi']= 'Suomi';
$sed_languages['fr']= 'Fran�ais';
$sed_languages['it']= 'Italiano';
$sed_languages['nl']= 'Nederlands';
$sed_languages['ru']= '&#1056;&#1091;&#1089;&#1089;&#1082;&#1080;&#1081;';
$sed_languages['se']= 'Svenska';
$sed_languages['en']= 'English';
$sed_languages['pl']= 'Polski';
$sed_languages['pt']= 'Portugese';
$sed_languages['cn']= '&#27721;&#35821;';
$sed_languages['gr']= 'Greek';
$sed_languages['hu']= 'Hungarian';
$sed_languages['jp']= '&#26085;&#26412;&#35486;';
$sed_languages['kr']= '&#54620;&#44397;&#47568;';

// XTemplate classes
require_once $cfg['system_dir'].'/xtemplate.class.php';

// =========== Extra fields for pages =====================

/**
 * Add extra field for pages
 *
 * @param string $sql_table Table for adding extrafield (without sed_)
 * @param string $name Field name (unique)
 * @param string $type Field type (input, textarea etc)
 * @param string $html HTML display of element without parameter "name="
 * @param string $variants Variants of values (for radiobuttons, selectors etc)
 * @param string $description Description of field (optional, for admin)
 * @return bool
 *
 */
function sed_extrafield_add($sql_table, $name, $type, $html, $variants="", $description="")
{
	global $db_extra_fields ;
	$fieldsres = sed_sql_query("SELECT field_name FROM $db_extra_fields");
	while($row = sed_sql_fetchassoc($fieldsres)) $extrafieldsnames[] = $row['field_name'];
	if(count($extrafieldsnames)>0) if (in_array($name,$extrafieldsnames)) return 0; // No adding - fields already exist

	// Check table sed_$sql_table - if field with same name exists - exit.
	$fieldsres = sed_sql_query("SELECT * FROM sed_$sql_table LIMIT 1");
	while ($i < mysql_num_fields($fieldsres)) {
		$column = mysql_fetch_field($fieldsres, $i);
		// get column prefix in this table
		$column_prefix = substr($column->name, 0, strpos($column->name, "_"));
		if(strpos($column->name, "_$name")) return false; // No adding - fields already exist
		$i++;
	}

	$extf['location'] = $sql_table;
	$extf['name'] = $name;
	$extf['type'] = $type;
	$extf['html'] = $html;
	$extf['variants'] = $variants;
	$extf['description'] = $description;
	$step1 = sed_sql_insert($db_extra_fields, $extf, 'field_') == 1;
	switch($type)	{
	case "input": $sqltype = "VARCHAR(255)"; break;
	case "textarea": $sqltype = "TEXT"; break;
	case "select": $sqltype = "VARCHAR(255)"; break;
	case "checkbox": $sqltype = "BOOL"; break;
	}
	$sql = "ALTER TABLE sed_$sql_table ADD ".$column_prefix."_$name $sqltype ";
	$step2 = sed_sql_query($sql);
	return $step1&&$step2;
}

/**
 * Update extra field for pages
 *
 * @param string $sql_table Table contains extrafield (without sed_)
 * @param string $oldname Exist name of field
 * @param string $name Field name (unique)
 * @param string $type Field type (input, textarea etc)
 * @param string $html HTML display of element without parameter "name="
 * @param string $variants Variants of values (for radiobuttons, selectors etc)
 * @param string $description Description of field (optional, for admin)
 * @return bool
 *
 */
function sed_extrafield_update($sql_table, $oldname, $name, $type, $html, $variants="", $description="")
{
	global $db_extra_fields;
	$fieldsres = sed_sql_query("SELECT * FROM sed_$sql_table LIMIT 1");
	$column = mysql_fetch_field($fieldsres, 0);
	$column_prefix = substr($column->name, 0, strpos($column->name, "_"));
	$extf['location'] = $sql_table;
	$extf['name'] = $name;
	$extf['type'] = $type;
	$extf['html'] = $html;
	$extf['variants'] = $variants;
	$extf['description'] = $description;
	$step1 = sed_sql_update($db_extra_fields, "field_name = '$oldname' AND field_location='$sql_table'", $extf, 'field_') == 1;
	switch($type)	{
	case "input": $sqltype = "VARCHAR(255)"; break;
	case "textarea": $sqltype = "TEXT"; break;
	case "select": $sqltype = "VARCHAR(255)"; break;
	case "checkbox": $sqltype = "BOOL"; break;
	}
	$sql = "ALTER TABLE sed_$sql_table CHANGE ".$column_prefix."_$oldname ".$column_prefix."_$name $sqltype ";
	$step2 = sed_sql_query($sql);
	return $step1&&$step2;
}

/**
 * Delete extra field
 *
 * @param string $sql_table Table contains extrafield (without sed_)
 * @param string $name Name of extra field
 * @return bool
 *
 */
function sed_extrafield_remove($sql_table, $name)
{
	global $db_extra_fields;
	$fieldsres = sed_sql_query("SELECT * FROM sed_$sql_table LIMIT 1");
	$column = mysql_fetch_field($fieldsres, 0);
	$column_prefix = substr($column->name, 0, strpos($column->name, "_"));
	$step1 = sed_sql_delete($db_extra_fields, "field_name = '$name' AND field_location='$sql_table'") == 1;
	$sql = "ALTER TABLE sed_$sql_table DROP ".$column_prefix."_".$name;
	$step2 = sed_sql_query($sql);
	return $step1&&$step2;
}


?>