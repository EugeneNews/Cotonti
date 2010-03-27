<?php
/* ====================
[BEGIN_SED_EXTPLUGIN]
Code=search
Name=Search
Description=Search with extended features
Version=4.00
Date=2010-mar-10
Author=Neocrome, Spartan, Boss, esclkm, Cotonti Team
Copyright=Partial copyright (c) Cotonti Team 2008 - 2010
Notes=
SQL=
Auth_guests=R
Lock_guests=W12345A
Auth_members=R
Lock_members=W12345A
[END_SED_EXTPLUGIN]

[BEGIN_SED_EXTPLUGIN_CONFIG]
maxwords=01:select:3,5,8,10:5:Maximum search words
maxsigns=02:select:20,30,40,50,60,70,80:40:Maximum signs in query
minsigns=03:select:2,3,4,5:3:Min. signs in query
maxitems=04:select:15,30,50,80,100,150,200:50:Maximum results lines for general search
pagesearch=05:radio::1:Enable pages search
forumsearch=06:radio::1:Enable forums search
searchurl=07:select:Normal,Single:Normal:Type of forum post link to use, Single uses a Single post view, while Normal uses the traditional thread/jump-to link
addfields=08:string:::Additional pages fields for search, separated by commas. Example "page_extra1,page_extra2,page_key"
extrafilters=09:radio::1:Show extrafilters on main search page
[END_SED_EXTPLUGIN_CONFIG]
==================== */


/**
 * Search plugin
 *
 * @package Cotonti
 * @version 0.7.0
 * @author Neocrome, Spartan, Boss, Cotonti Team
 * @copyright Copyright (c) 2008-2010 Cotonti Team
 * @license BSD License
 */

defined('SED_CODE') or die('Wrong URL');

?>