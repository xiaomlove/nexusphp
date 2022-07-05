<?php
require_once("../include/bittorrent.php");
dbconn(true);
require_once(get_langfile_path('torrents.php'));
require_once(get_langfile_path('speical.php'));
loggedinorreturn();
parked();

/**
 * tags
 */
$tagRep = new \App\Repositories\TagRepository();
$tagKeyById = $tagRep->createBasicQuery()->get()->keyBy('id');
$renderKeyArr = $tagKeyById->keys()->toArray();
$elasticsearchEnabled = nexus_env('ELASTICSEARCH_ENABLED');

//check searchbox
switch (nexus()->getScript()) {
    case 'torrents':
        $sectiontype = $browsecatmode;
        break;
    case 'special':
        if (get_setting('main.spsct') != 'yes') {
            httperr();
        }
        if (get_user_class() < get_setting('authority.view_special_torrent')) {
            stderr($lang_special['std_sorry'],$lang_special['std_permission_denied_only'].get_user_class_name(get_setting('authority.view_special_torrent'),false,true,true).$lang_special['std_or_above_can_view'],false);
        }
        $sectiontype = $specialcatmode;
        break;
    default:
        $sectiontype = 0;
}
$showsubcat = get_searchbox_value($sectiontype, 'showsubcat');//whether show subcategory (i.e. sources, codecs) or not
$showsource = get_searchbox_value($sectiontype, 'showsource'); //whether show sources or not
$showmedium = get_searchbox_value($sectiontype, 'showmedium'); //whether show media or not
$showcodec = get_searchbox_value($sectiontype, 'showcodec'); //whether show codecs or not
$showstandard = get_searchbox_value($sectiontype, 'showstandard'); //whether show standards or not
$showprocessing = get_searchbox_value($sectiontype, 'showprocessing'); //whether show processings or not
$showteam = get_searchbox_value($sectiontype, 'showteam'); //whether show teams or not
$showaudiocodec = get_searchbox_value($sectiontype, 'showaudiocodec'); //whether show audio codec or not
$catsperrow = get_searchbox_value($sectiontype, 'catsperrow'); //show how many cats per line in search box
$catpadding = get_searchbox_value($sectiontype, 'catpadding'); //padding space between categories in pixel

$cats = genrelist($sectiontype);
if ($showsubcat){
	if ($showsource) $sources = searchbox_item_list("sources");
	if ($showmedium) $media = searchbox_item_list("media");
	if ($showcodec) $codecs = searchbox_item_list("codecs");
	if ($showstandard) $standards = searchbox_item_list("standards");
	if ($showprocessing) $processings = searchbox_item_list("processings");
	if ($showteam) $teams = searchbox_item_list("teams");
	if ($showaudiocodec) $audiocodecs = searchbox_item_list("audiocodecs");
}

$searchstr_ori = htmlspecialchars(trim($_GET["search"] ?? ''));
$searchstr = mysql_real_escape_string(trim($_GET["search"] ?? ''));
if (empty($searchstr))
	unset($searchstr);

// sorting by MarkoStamcar
$column = '';
$ascdesc = '';
if (isset($_GET['sort']) && $_GET['sort'] && isset($_GET['type']) && $_GET['type']) {

	switch($_GET['sort']) {
		case '1': $column = "name"; break;
		case '2': $column = "numfiles"; break;
		case '3': $column = "comments"; break;
		case '4': $column = "added"; break;
		case '5': $column = "size"; break;
		case '6': $column = "times_completed"; break;
		case '7': $column = "seeders"; break;
		case '8': $column = "leechers"; break;
		case '9': $column = "owner"; break;
		default: $column = "id"; break;
	}

	switch($_GET['type']) {
		case 'asc': $ascdesc = "ASC"; $linkascdesc = "asc"; break;
		case 'desc': $ascdesc = "DESC"; $linkascdesc = "desc"; break;
		default: $ascdesc = "DESC"; $linkascdesc = "desc"; break;
	}

	if($column == "owner")
	{
		$orderby = "ORDER BY pos_state DESC, torrents.anonymous, users.username " . $ascdesc;
	}
	else
	{
		$orderby = "ORDER BY pos_state DESC, torrents." . $column . " " . $ascdesc;
	}

	$pagerlink = "sort=" . intval($_GET['sort']) . "&type=" . $linkascdesc . "&";

} else {

	$orderby = "ORDER BY pos_state DESC, torrents.id DESC";
	$pagerlink = "";

}

$addparam = "";
$wherea = array();
$wherecatina = array();
if ($showsubcat){
	if ($showsource) $wheresourceina = array();
	if ($showmedium) $wheremediumina = array();
	if ($showcodec) $wherecodecina = array();
	if ($showstandard) $wherestandardina = array();
	if ($showprocessing) $whereprocessingina = array();
	if ($showteam) $whereteamina = array();
	if ($showaudiocodec) $whereaudiocodecina = array();
}
//----------------- start whether show torrents from all sections---------------------//
if ($_GET)
	$allsec = intval($_GET["allsec"] ?? 0);
else $allsec = 0;
if ($allsec == 1)		//show torrents from all sections
{
	$addparam .= "allsec=1&";
}
// ----------------- end whether ignoring section ---------------------//
// ----------------- start bookmarked ---------------------//
$inclbookmarked = 0;
if ($_GET)
	$inclbookmarked = intval($_GET["inclbookmarked"] ?? 0);
elseif ($CURUSER['notifs']){
	if (strpos($CURUSER['notifs'], "[inclbookmarked=0]") !== false)
		$inclbookmarked = 0;
	elseif (strpos($CURUSER['notifs'], "[inclbookmarked=1]") !== false)
		$inclbookmarked = 1;
	elseif (strpos($CURUSER['notifs'], "[inclbookmarked=2]") !== false)
		$inclbookmarked = 2;
}

if (!in_array($inclbookmarked,array(0,1,2)))
{
	$inclbookmarked = 0;
	write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking inclbookmarked field in" . $_SERVER['SCRIPT_NAME'], 'mod');
}
if ($inclbookmarked == 0)  //all(bookmarked,not)
{
	$addparam .= "inclbookmarked=0&";
}
elseif ($inclbookmarked == 1)		//bookmarked
{
	$addparam .= "inclbookmarked=1&";
	if(isset($CURUSER))
	$wherea[] = "torrents.id IN (SELECT torrentid FROM bookmarks WHERE userid=" . $CURUSER['id'] . ")";
}
elseif ($inclbookmarked == 2)		//not bookmarked
{
	$addparam .= "inclbookmarked=2&";
	if(isset($CURUSER))
	$wherea[] = "torrents.id NOT IN (SELECT torrentid FROM bookmarks WHERE userid=" . $CURUSER['id'] . ")";
}
// ----------------- end bookmarked ---------------------//

if (!isset($CURUSER) || get_user_class() < $seebanned_class)
	$wherea[] = "banned = 'no'";
// ----------------- start include dead ---------------------//
if (isset($_GET["incldead"]))
	$include_dead = intval($_GET["incldead"] ?? 0);
elseif ($CURUSER['notifs']){
	if (strpos($CURUSER['notifs'], "[incldead=0]") !== false)
		$include_dead = 0;
	elseif (strpos($CURUSER['notifs'], "[incldead=1]") !== false)
		$include_dead = 1;
	elseif (strpos($CURUSER['notifs'], "[incldead=2]") !== false)
		$include_dead = 2;
	else $include_dead = 1;
}
else $include_dead = 1;

if (!in_array($include_dead,array(0,1,2)))
{
	$include_dead = 0;
	write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking incldead field in" . $_SERVER['SCRIPT_NAME'], 'mod');
}
if ($include_dead == 0)  //all(active,dead)
{
	$addparam .= "incldead=0&";
}
elseif ($include_dead == 1)		//active
{
	$addparam .= "incldead=1&";
	$wherea[] = "visible = 'yes'";
}
elseif ($include_dead == 2)		//dead
{
	$addparam .= "incldead=2&";
	$wherea[] = "visible = 'no'";
}
// ----------------- end include dead ---------------------//
$special_state = 0;
if ($_GET)
	$special_state = intval($_GET["spstate"] ?? 0);
elseif ($CURUSER['notifs']){
	if (strpos($CURUSER['notifs'], "[spstate=0]") !== false)
		$special_state = 0;
	elseif (strpos($CURUSER['notifs'], "[spstate=1]") !== false)
		$special_state = 1;
	elseif (strpos($CURUSER['notifs'], "[spstate=2]") !== false)
		$special_state = 2;
	elseif (strpos($CURUSER['notifs'], "[spstate=3]") !== false)
		$special_state = 3;
	elseif (strpos($CURUSER['notifs'], "[spstate=4]") !== false)
		$special_state = 4;
	elseif (strpos($CURUSER['notifs'], "[spstate=5]") !== false)
		$special_state = 5;
	elseif (strpos($CURUSER['notifs'], "[spstate=6]") !== false)
		$special_state = 6;
	elseif (strpos($CURUSER['notifs'], "[spstate=6]") !== false)
		$special_state = 7;
}

if (!in_array($special_state,array(0,1,2,3,4,5,6,7)))
{
	$special_state = 0;
	write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking spstate field in " . $_SERVER['SCRIPT_NAME'], 'mod');
}
if($special_state == 0)	//all
{
	$addparam .= "spstate=0&";
}
elseif ($special_state == 1)	//normal
{
	$addparam .= "spstate=1&";

	$wherea[] = "sp_state = 1";

	if(get_global_sp_state() == 1)
	{
		$wherea[] = "sp_state = 1";
	}
}
elseif ($special_state == 2)	//free
{
	$addparam .= "spstate=2&";

	if(get_global_sp_state() == 1)
	{
		$wherea[] = "sp_state = 2";
	}
	else if(get_global_sp_state() == 2)
	{
		;
	}
}
elseif ($special_state == 3)	//2x up
{
	$addparam .= "spstate=3&";
	if(get_global_sp_state() == 1)	//only sp state
	{
		$wherea[] = "sp_state = 3";
	}
	else if(get_global_sp_state() == 3)	//all
	{
		;
	}
}
elseif ($special_state == 4)	//2x up and free
{
	$addparam .= "spstate=4&";

	if(get_global_sp_state() == 1)	//only sp state
	{
		$wherea[] = "sp_state = 4";
	}
	else if(get_global_sp_state() == 4)	//all
	{
		;
	}
}
elseif ($special_state == 5)	//half down
{
	$addparam .= "spstate=5&";

	if(get_global_sp_state() == 1)	//only sp state
	{
		$wherea[] = "sp_state = 5";
	}
	else if(get_global_sp_state() == 5)	//all
	{
		;
	}
}
elseif ($special_state == 6)	//half down
{
	$addparam .= "spstate=6&";

	if(get_global_sp_state() == 1)	//only sp state
	{
		$wherea[] = "sp_state = 6";
	}
	else if(get_global_sp_state() == 6)	//all
	{
		;
	}
}
elseif ($special_state == 7)	//30% down
{
	$addparam .= "spstate=7&";

	if(get_global_sp_state() == 1)	//only sp state
	{
		$wherea[] = "sp_state = 7";
	}
	else if(get_global_sp_state() == 7)	//all
	{
		;
	}
}

$category_get = intval($_GET["cat"] ?? 0);
$source_get = $medium_get = $codec_get = $standard_get = $processing_get = $team_get = $audiocodec_get = 0;
if ($showsubcat){
if ($showsource) $source_get = intval($_GET["source"] ?? 0);
if ($showmedium) $medium_get = intval($_GET["medium"] ?? 0);
if ($showcodec) $codec_get = intval($_GET["codec"] ?? 0);
if ($showstandard) $standard_get = intval($_GET["standard"] ?? 0);
if ($showprocessing) $processing_get = intval($_GET["processing"] ?? 0);
if ($showteam) $team_get = intval($_GET["team"] ?? 0);
if ($showaudiocodec) $audiocodec_get = intval($_GET["audiocodec"] ?? 0);
}

$all = intval($_GET["all"] ?? 0);

if (!$all)
{
	if (!$_GET && $CURUSER['notifs'])
	{
		$all = true;
		foreach ($cats as $cat)
		{
			$all &= $cat['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[cat'.$cat['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$catcheck = false;
			else
			$catcheck = true;

			if ($catcheck)
			{
				$wherecatina[] = $cat['id'];
				$addparam .= "cat$cat[id]=1&";
			}
		}
		if ($showsubcat){
		if ($showsource)
		foreach ($sources as $source)
		{
			$all &= $source['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[sou'.$source['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$sourcecheck = false;
			else
			$sourcecheck = true;

			if ($sourcecheck)
			{
				$wheresourceina[] = $source['id'];
				$addparam .= "source{$source['id']}=1&";
			}
		}
		if ($showmedium)
		foreach ($media as $medium)
		{
			$all &= $medium['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[med'.$medium['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$mediumcheck = false;
			else
			$mediumcheck = true;

			if ($mediumcheck)
			{
				$wheremediumina[] = $medium['id'];
				$addparam .= "medium{$medium['id']}=1&";
			}
		}
		if ($showcodec)
		foreach ($codecs as $codec)
		{
			$all &= $codec['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[cod'.$codec['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$codeccheck = false;
			else
			$codeccheck = true;

			if ($codeccheck)
			{
				$wherecodecina[] = $codec['id'];
				$addparam .= "codec{$codec['id']}=1&";
			}
		}
		if ($showstandard)
		foreach ($standards as $standard)
		{
			$all &= $standard['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[sta'.$standard['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$standardcheck = false;
			else
			$standardcheck = true;

			if ($standardcheck)
			{
				$wherestandardina[] = $standard['id'];
				$addparam .= "standard{$standard['id']}=1&";
			}
		}
		if ($showprocessing)
		foreach ($processings as $processing)
		{
			$all &= $processing['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[pro'.$processing['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$processingcheck = false;
			else
			$processingcheck = true;

			if ($processingcheck)
			{
				$whereprocessingina[] = $processing['id'];
				$addparam .= "processing{$processing['id']}=1&";
			}
		}
		if ($showteam)
		foreach ($teams as $team)
		{
			$all &= $team['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[tea'.$team['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$teamcheck = false;
			else
			$teamcheck = true;

			if ($teamcheck)
			{
				$whereteamina[] = $team['id'];
				$addparam .= "team{$team['id']}=1&";
			}
		}
		if ($showaudiocodec)
		foreach ($audiocodecs as $audiocodec)
		{
			$all &= $audiocodec['id'];
			$mystring = $CURUSER['notifs'];
			$findme  = '[aud'.$audiocodec['id'].']';
			$search = strpos($mystring, $findme);
			if ($search === false)
			$audiocodeccheck = false;
			else
			$audiocodeccheck = true;

			if ($audiocodeccheck)
			{
				$whereaudiocodecina[] = $audiocodec['id'];
				$addparam .= "audiocodec{$audiocodec['id']}=1&";
			}
		}
		}
	}
	// when one clicked the cat, source, etc. name/image
	elseif ($category_get)
	{
		int_check($category_get,true,true,true);
		$wherecatina[] = $category_get;
		$addparam .= "cat=$category_get&";
	}
	elseif ($medium_get)
	{
		int_check($medium_get,true,true,true);
		$wheremediumina[] = $medium_get;
		$addparam .= "medium=$medium_get&";
	}
	elseif ($source_get)
	{
		int_check($source_get,true,true,true);
		$wheresourceina[] = $source_get;
		$addparam .= "source=$source_get&";
	}
	elseif ($codec_get)
	{
		int_check($codec_get,true,true,true);
		$wherecodecina[] = $codec_get;
		$addparam .= "codec=$codec_get&";
	}
	elseif ($standard_get)
	{
		int_check($standard_get,true,true,true);
		$wherestandardina[] = $standard_get;
		$addparam .= "standard=$standard_get&";
	}
	elseif ($processing_get)
	{
		int_check($processing_get,true,true,true);
		$whereprocessingina[] = $processing_get;
		$addparam .= "processing=$processing_get&";
	}
	elseif ($team_get)
	{
		int_check($team_get,true,true,true);
		$whereteamina[] = $team_get;
		$addparam .= "team=$team_get&";
	}
	elseif ($audiocodec_get)
	{
		int_check($audiocodec_get,true,true,true);
		$whereaudiocodecina[] = $audiocodec_get;
		$addparam .= "audiocodec=$audiocodec_get&";
	}
	else	//select and go
	{
		$all = True;
		foreach ($cats as $cat)
		{
		    $__is = (isset($_GET["cat{$cat['id']}"]) && $_GET["cat{$cat['id']}"]);
			$all &= $__is;
			if ($__is)
			{
				$wherecatina[] = $cat['id'];
				$addparam .= "cat{$cat['id']}=1&";
			}
		}
		if ($showsubcat){
		if ($showsource)
		foreach ($sources as $source)
		{
            $__is = (isset($_GET["source{$source['id']}"]) && $_GET["source{$source['id']}"]);
            $all &= $__is;
			if ($__is)
			{
				$wheresourceina[] = $source['id'];
				$addparam .= "source{$source['id']}=1&";
			}
		}
		if ($showmedium)
		foreach ($media as $medium)
		{
            $__is = (isset($_GET["medium{$medium['id']}"]) && $_GET["medium{$medium['id']}"]);
            $all &= $__is;
            if ($__is)
			{
				$wheremediumina[] = $medium['id'];
				$addparam .= "medium{$medium['id']}=1&";
			}
		}
		if ($showcodec)
		foreach ($codecs as $codec)
		{
            $__is = (isset($_GET["codec{$codec['id']}"]) && $_GET["codec{$codec['id']}"]);
            $all &= $__is;
            if ($__is)
			{
				$wherecodecina[] = $codec['id'];
				$addparam .= "codec{$codec['id']}=1&";
			}
		}
		if ($showstandard)
		foreach ($standards as $standard)
		{
            $__is = (isset($_GET["standard{$standard['id']}"]) && $_GET["standard{$standard['id']}"]);
            $all &= $__is;
            if ($__is)
			{
				$wherestandardina[] = $standard['id'];
				$addparam .= "standard{$standard['id']}=1&";
			}
		}
		if ($showprocessing)
		foreach ($processings as $processing)
		{
            $__is = (isset($_GET["processing{$processing['id']}"]) && $_GET["processing{$processing['id']}"]);
            $all &= $__is;
            if ($__is)
			{
				$whereprocessingina[] = $processing['id'];
				$addparam .= "processing{$processing['id']}=1&";
			}
		}
		if ($showteam)
		foreach ($teams as $team)
		{
            $__is = (isset($_GET["team{$team['id']}"]) && $_GET["team{$team['id']}"]);
            $all &= $__is;
            if ($__is)
			{
				$whereteamina[] = $team['id'];
				$addparam .= "team{$team['id']}=1&";
			}
		}
		if ($showaudiocodec)
		foreach ($audiocodecs as $audiocodec)
		{
            $__is = (isset($_GET["audiocodec{$audiocodec['id']}"]) && $_GET["audiocodec{$audiocodec['id']}"]);
            $all &= $__is;
            if ($__is)
			{
				$whereaudiocodecina[] = $audiocodec['id'];
				$addparam .= "audiocodec{$audiocodec['id']}=1&";
			}
		}
		}
	}
}

if ($all)
{
	//stderr("in if all","");
	$wherecatina = array();
	if ($showsubcat){
	$wheresourceina = array();
	$wheremediumina = array();
	$wherecodecina = array();
	$wherestandardina = array();
	$whereprocessingina = array();
	$whereteamina = array();
	$whereaudiocodecina = array();}
	$addparam .= "";
}
//stderr("", count($wherecatina)."-". count($wheresourceina));
$wherecatin = $wheresourcein = $wheremediumin = $wherecodecin = $wherestandardin = $whereprocessingin = $whereteamin = $whereaudiocodecin = '';
if (count($wherecatina) > 1)
$wherecatin = implode(",",$wherecatina);
elseif (count($wherecatina) == 1)
$wherea[] = "category = $wherecatina[0]";

if ($showsubcat){
if ($showsource){
if (count($wheresourceina) > 1)
$wheresourcein = implode(",",$wheresourceina);
elseif (count($wheresourceina) == 1)
$wherea[] = "source = $wheresourceina[0]";}

if ($showmedium){
if (count($wheremediumina) > 1)
$wheremediumin = implode(",",$wheremediumina);
elseif (count($wheremediumina) == 1)
$wherea[] = "medium = $wheremediumina[0]";}

if ($showcodec){
if (count($wherecodecina) > 1)
$wherecodecin = implode(",",$wherecodecina);
elseif (count($wherecodecina) == 1)
$wherea[] = "codec = $wherecodecina[0]";}

if ($showstandard){
if (count($wherestandardina) > 1)
$wherestandardin = implode(",",$wherestandardina);
elseif (count($wherestandardina) == 1)
$wherea[] = "standard = $wherestandardina[0]";}

if ($showprocessing){
if (count($whereprocessingina) > 1)
$whereprocessingin = implode(",",$whereprocessingina);
elseif (count($whereprocessingina) == 1)
$wherea[] = "processing = $whereprocessingina[0]";}
}
if ($showteam){
if (count($whereteamina) > 1)
$whereteamin = implode(",",$whereteamina);
elseif (count($whereteamina) == 1)
$wherea[] = "team = $whereteamina[0]";}

if ($showaudiocodec){
if (count($whereaudiocodecina) > 1)
$whereaudiocodecin = implode(",",$whereaudiocodecina);
elseif (count($whereaudiocodecina) == 1)
$wherea[] = "audiocodec = $whereaudiocodecina[0]";}

$wherebase = $wherea;
$search_area = 0;
if (isset($searchstr))
{
	if (!isset($_GET['notnewword']) || !$_GET['notnewword']){
		insert_suggest($searchstr, $CURUSER['id']);
		$notnewword="";
	}
	else{
		$notnewword="notnewword=1&";
	}
	$search_mode = intval($_GET["search_mode"] ?? 0);
	if (!in_array($search_mode,array(0,1,2)))
	{
		$search_mode = 0;
		write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking search_mode field in" . $_SERVER['SCRIPT_NAME'], 'mod');
	}

	$search_area = intval($_GET["search_area"] ?? 0) ;

	if ($search_area == 4) {
		$searchstr = (int)parse_imdb_id($searchstr);
	}
	$like_expression_array =array();
	unset($like_expression_array);

	switch ($search_mode)
	{
		case 0:	// AND, OR
		case 1	:
			{
				$searchstr = str_replace(".", " ", $searchstr);
				$searchstr_exploded = explode(" ", $searchstr);
				$searchstr_exploded_count= 0;
				foreach ($searchstr_exploded as $searchstr_element)
				{
					$searchstr_element = trim($searchstr_element);	// furthur trim to ensure that multi space seperated words still work
					$searchstr_exploded_count++;
					if ($searchstr_exploded_count > 10)	// maximum 10 keywords
					break;
					$like_expression_array[] = " LIKE '%" . $searchstr_element. "%'";
				}
				break;
			}
		case 2	:	// exact
		{
			$like_expression_array[] = " LIKE '%" . $searchstr. "%'";
			break;
		}
		/*case 3 :	// parsed
		{
		$like_expression_array[] = $searchstr;
		break;
		}*/
	}
	$ANDOR = ($search_mode == 0 ? " AND " : " OR ");	// only affects mode 0 and mode 1

	switch ($search_area)
	{
		case 0   :	// torrent name
		{
			foreach ($like_expression_array as &$like_expression_array_element)
			$like_expression_array_element = "(torrents.name" . $like_expression_array_element." OR torrents.small_descr". $like_expression_array_element.")";
			$wherea[] =  implode($ANDOR, $like_expression_array);
			break;
		}
		case 1	:	// torrent description
		{
			foreach ($like_expression_array as &$like_expression_array_element)
			$like_expression_array_element = "torrents.descr". $like_expression_array_element;
			$wherea[] =  implode($ANDOR,  $like_expression_array);
			break;
		}
		/*case 2	:	// torrent small description
		{
			foreach ($like_expression_array as &$like_expression_array_element)
			$like_expression_array_element =  "torrents.small_descr". $like_expression_array_element;
			$wherea[] =  implode($ANDOR, $like_expression_array);
			break;
		}*/
		case 3	:	// torrent uploader
		{
			foreach ($like_expression_array as &$like_expression_array_element)
			$like_expression_array_element =  "users.username". $like_expression_array_element;

			if(!isset($CURUSER))	// not registered user, only show not anonymous torrents
			{
				$wherea[] =  implode($ANDOR, $like_expression_array) . " AND torrents.anonymous = 'no'";
			}
			else
			{
				if(get_user_class() > $torrentmanage_class)	// moderator or above, show all
				{
					$wherea[] =  implode($ANDOR, $like_expression_array);
				}
				else // only show normal torrents and anonymous torrents from hiself
				{
					$wherea[] =   "(" . implode($ANDOR, $like_expression_array) . " AND torrents.anonymous = 'no') OR (" . implode($ANDOR, $like_expression_array). " AND torrents.anonymous = 'yes' AND users.id=" . $CURUSER["id"] . ") ";
				}
			}
			break;
		}
		case 4  :  //imdb url
			foreach ($like_expression_array as &$like_expression_array_element)
			$like_expression_array_element = "torrents.url". $like_expression_array_element;
			$wherea[] =  implode($ANDOR,  $like_expression_array);
			break;
		default :	// unkonwn
		{
			$search_area = 0;
			$wherea[] =  "torrents.name LIKE '%" . $searchstr . "%'";
			write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking search_area field in" . $_SERVER['SCRIPT_NAME'], 'mod');
			break;
		}
	}
	$addparam .= "search_area=" . $search_area . "&";
	$addparam .= "search=" . rawurlencode($searchstr) . "&".$notnewword;
	$addparam .= "search_mode=".$search_mode."&";
}

//approval status
$approvalStatusNoneVisible = get_setting('torrent.approval_status_none_visible');
$approvalStatusIconEnabled = get_setting('torrent.approval_status_icon_enabled');
$approvalStatus = null;
$showApprovalStatusFilter = false;
//when enable approval status icon, all user can use this filter, otherwise only staff member and approval none visible is 'no' can use
if ($approvalStatusIconEnabled == 'yes' || (get_user_class() >= $staffmem_class && $approvalStatusNoneVisible == 'no')) {
    $showApprovalStatusFilter = true;
}
//when user can use approval status filter, and pass `approval_status` parameter, will affect
//OR if [not approval can not be view] and not staff member, force to view  approval allowed
if ($showApprovalStatusFilter && isset($_REQUEST['approval_status']) && is_numeric($_REQUEST['approval_status'])) {
    $approvalStatus = intval($_REQUEST['approval_status']);
    $wherea[] = "torrents.approval_status = $approvalStatus";
} elseif ($approvalStatusNoneVisible == 'no' && get_user_class() < $staffmem_class) {
    $wherea[] = "torrents.approval_status = " . \App\Models\Torrent::APPROVAL_STATUS_ALLOW;
}

$where = implode(" AND ", $wherea);

if ($wherecatin)
$where .= ($where ? " AND " : "") . "category IN(" . $wherecatin . ")";
if ($showsubcat){
if ($wheresourcein)
$where .= ($where ? " AND " : "") . "source IN(" . $wheresourcein . ")";
if ($wheremediumin)
$where .= ($where ? " AND " : "") . "medium IN(" . $wheremediumin . ")";
if ($wherecodecin)
$where .= ($where ? " AND " : "") . "codec IN(" . $wherecodecin . ")";
if ($wherestandardin)
$where .= ($where ? " AND " : "") . "standard IN(" . $wherestandardin . ")";
if ($whereprocessingin)
$where .= ($where ? " AND " : "") . "processing IN(" . $whereprocessingin . ")";
if ($whereteamin)
$where .= ($where ? " AND " : "") . "team IN(" . $whereteamin . ")";
if ($whereaudiocodecin)
$where .= ($where ? " AND " : "") . "audiocodec IN(" . $whereaudiocodecin . ")";
}

$tagFilter = "";
$tagId = intval($_REQUEST['tag_id'] ?? 0);
if ($tagId > 0) {
    $tagFilter = " inner join torrent_tags on torrents.id = torrent_tags.torrent_id and torrent_tags.tag_id = $tagId ";
    $addparam .= "tag_id={$tagId}&";
}
if ($allsec == 1 || $enablespecial != 'yes')
{
	if ($where != "")
		$where = "WHERE $where ";
	else $where = "";
	$sql = "SELECT COUNT(*) FROM torrents " . ($search_area == 3 || $column == "owner" ? "LEFT JOIN users ON torrents.owner = users.id " : "") . $tagFilter . $where;
}
else
{
	if ($where != "")
		$where = "WHERE $where AND categories.mode = '$sectiontype'";
	else $where = "WHERE categories.mode = '$sectiontype'";
	$sql = "SELECT COUNT(*), categories.mode FROM torrents LEFT JOIN categories ON category = categories.id " . ($search_area == 3 || $column == "owner" ? "LEFT JOIN users ON torrents.owner = users.id " : "") . $tagFilter . $where . " GROUP BY categories.mode";
}

if ($elasticsearchEnabled) {
    $searchRep = new \App\Repositories\SearchRepository();
    $esParams = $_GET;
    $esParams['mode'] = $sectiontype;
    $resultFromElastic = $searchRep->listTorrentFromEs($esParams, $CURUSER['id'], $_SERVER['QUERY_STRING']);
    $count = $resultFromElastic['total'];
} else {
    $res = sql_query($sql);
    $count = 0;
    while($row = mysql_fetch_array($res)) {
        $count += $row[0];
    }
}

if ($CURUSER["torrentsperpage"])
$torrentsperpage = (int)$CURUSER["torrentsperpage"];
elseif ($torrentsperpage_main)
	$torrentsperpage = $torrentsperpage_main;
else $torrentsperpage = 50;

do_log("[TORRENT_COUNT_SQL] $sql", 'debug');

if ($count)
{
	if ($addparam != "")
	{
		if ($pagerlink != "")
		{
			if ($addparam[strlen($addparam)-1] != ";")
			{ // & = &amp;
				$addparam = $addparam . "&" . $pagerlink;
			}
			else
			{
				$addparam = $addparam . $pagerlink;
			}
		}
	}
	else
	{
		//stderr("in else","");
		$addparam = $pagerlink;
	}
	//stderr("addparam",$addparam);
	//echo $addparam;

	list($pagertop, $pagerbottom, $limit, $offset, $size, $page) = pager($torrentsperpage, $count, "?" . $addparam);
	$fieldsStr = implode(', ', \App\Models\Torrent::getFieldsForList(true));
    if ($allsec == 1 || $enablespecial != 'yes') {
        $query = "SELECT $fieldsStr FROM torrents ".($search_area == 3 || $column == "owner" ? "LEFT JOIN users ON torrents.owner = users.id " : "")." $tagFilter $where $orderby $limit";
    } else {
        $query = "SELECT $fieldsStr FROM torrents ".($search_area == 3 || $column == "owner" ? "LEFT JOIN users ON torrents.owner = users.id " : "")." LEFT JOIN categories ON torrents.category=categories.id $tagFilter $where $orderby $limit";
    }
    do_log("[TORRENT_LIST_SQL] $query", 'debug');
    if (!$elasticsearchEnabled) {
        $res = sql_query($query);
    }
} else {
    unset($res);
}

if (isset($searchstr))
	stdhead($lang_torrents['head_search_results_for'].$searchstr_ori);
elseif ($sectiontype == $browsecatmode)
	stdhead($lang_torrents['head_torrents']);
else stdhead($lang_torrents['head_special']);
print("<table width=\"97%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">");

displayHotAndClassic();

if ($allsec != 1 || $enablespecial != 'yes'){ //do not print searchbox if showing bookmarked torrents from all sections;
?>
<form method="get" name="searchbox" action="?">
	<table border="1" class="searchbox" cellspacing="0" cellpadding="5" width="100%">
		<tbody>
		<tr>
		<td class="colhead" align="center" colspan="2"><a href="javascript: klappe_news('searchboxmain')"><img class="plus" src="pic/trans.gif" id="picsearchboxmain" alt="Show/Hide" /><?php echo $lang_torrents['text_search_box'] ?></a></td>
		</tr></tbody>
		<tbody id="ksearchboxmain" style="display:none">
		<tr>
			<td class="rowfollow" align="left">
				<table>
					<?php
						function printcat($name, $listarray, $cbname, $wherelistina, $btname, $showimg = false)
						{
							global $catpadding,$catsperrow,$lang_torrents,$CURUSER,$CURLANGDIR,$catimgurl;

							print("<tr><td class=\"embedded\" colspan=\"".$catsperrow."\" align=\"left\"><b>".$name."</b></td></tr><tr>");
							$i = 0;
							foreach($listarray as $list){
								if ($i && $i % $catsperrow == 0){
									print("</tr><tr>");
								}
								print("<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px; padding-left: ".$catpadding."px;\"><input type=\"checkbox\" id=\"".$cbname.$list['id']."\" name=\"".$cbname.$list['id']."\"" . (in_array($list['id'],$wherelistina) ? " checked=\"checked\"" : "") . " value=\"1\" />".($showimg ? return_category_image($list['id'], "?") : "<a title=\"" .$list['name'] . "\" href=\"?".$cbname."=".$list['id']."\">".$list['name']."</a>")."</td>\n");
								$i++;
							}
							$checker = "<input name=\"".$btname."\" value='" .  $lang_torrents['input_check_all'] . "' class=\"btn medium\" type=\"button\" onclick=\"javascript:SetChecked('".$cbname."','".$btname."','". $lang_torrents['input_check_all'] ."','" . $lang_torrents['input_uncheck_all'] . "',-1,10)\" />";
							print("<td colspan=\"2\" class=\"bottom\" align=\"left\" style=\"padding-left: 15px\">".$checker."</td>\n");
							print("</tr>");
						}
					printcat($lang_torrents['text_category'],$cats,"cat",$wherecatina,"cat_check",true);

					if ($showsubcat){
						if ($showsource)
							printcat($lang_torrents['text_source'], $sources, "source", $wheresourceina, "source_check");
						if ($showmedium)
							printcat($lang_torrents['text_medium'], $media, "medium", $wheremediumina, "medium_check");
						if ($showcodec)
							printcat($lang_torrents['text_codec'], $codecs, "codec", $wherecodecina, "codec_check");
						if ($showaudiocodec)
							printcat($lang_torrents['text_audio_codec'], $audiocodecs, "audiocodec", $whereaudiocodecina, "audiocodec_check");
						if ($showstandard)
							printcat($lang_torrents['text_standard'], $standards, "standard", $wherestandardina, "standard_check");
						if ($showprocessing)
							printcat($lang_torrents['text_processing'], $processings, "processing", $whereprocessingina, "processing_check");
						if ($showteam)
							printcat($lang_torrents['text_team'], $teams, "team", $whereteamina, "team_check");
					}
					?>
				</table>
			</td>

			<td class="rowfollow" valign="middle">
				<table>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium"><?php echo $lang_torrents['text_show_dead_active'] ?></font>
						</td>
				 	</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="incldead" style="width: 100px;">
								<option value="0"><?php echo $lang_torrents['select_including_dead'] ?></option>
								<option value="1"<?php print($include_dead == 1 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_active'] ?> </option>
								<option value="2"<?php print($include_dead == 2 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_dead'] ?></option>
							</select>
						</td>
				 	</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<br />
						</td>
				 	</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium"><?php echo $lang_torrents['text_show_special_torrents'] ?></font>
						</td>
				 	</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="spstate" style="width: 100px;">
								<option value="0"><?php echo $lang_torrents['select_all'] ?></option>
<?php echo promotion_selection($special_state, 0)?>
							</select>
						</td>
					</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<br />
						</td>
					</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium"><?php echo $lang_torrents['text_show_bookmarked'] ?></font>
						</td>
				 	</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="inclbookmarked" style="width: 100px;">
								<option value="0"><?php echo $lang_torrents['select_all'] ?></option>
								<option value="1"<?php print($inclbookmarked == 1 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_bookmarked'] ?></option>
								<option value="2"<?php print($inclbookmarked == 2 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_bookmarked_exclude'] ?></option>
							</select>
						</td>
					</tr>
                    <?php if ($showApprovalStatusFilter) {?>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px">
                            <br />
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px">
                            <font class="medium"><?php echo $lang_torrents['text_approval_status'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px">
                            <select class="med" name="approval_status" style="width: 100px;">
                                <option value=""><?php echo $lang_torrents['select_all'] ?></option>
                                <?php
                                foreach (\App\Models\Torrent::listApprovalStatus(true) as $key => $value) {
                                    printf('<option value="%s"%s>%s</option>', $key, isset($approvalStatus) && (string)$approvalStatus === (string)$key ? ' selected' : '', $value);
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <?php }?>
				</table>
			</td>
		</tr>
		</tbody>
		<tbody>
		<tr>
			<td class="rowfollow" align="center">
				<table>
					<tr>
						<td class="embedded">
							<?php echo $lang_torrents['text_search'] ?>&nbsp;&nbsp;
						</td>
						<td class="embedded">
							<table>
								<tr>
									<td class="embedded">
										<input id="searchinput" name="search" type="text" value="<?php echo  $searchstr_ori ?>" autocomplete="off" style="width: 200px" ondblclick="suggest(event.keyCode,this.value);" onkeyup="suggest(event.keyCode,this.value);" onkeypress="return noenter(event.keyCode);"/>
										<script src="js/suggest.js" type="text/javascript"></script>
										<div id="suggcontainer" style="text-align: left; width:100px;  display: none;">
											<div id="suggestions" style="width:204px; border: 1px solid rgb(119, 119, 119); cursor: default; position: absolute; color: rgb(0,0,0); background-color: rgb(255, 255, 255);"></div>
										</div>
									</td>
								</tr>
							</table>
						</td>
						<td class="embedded">
							<?php echo "&nbsp;" . $lang_torrents['text_in'] ?>

							<select name="search_area">
								<option value="0"><?php echo $lang_torrents['select_title'] ?></option>
								<option value="1"<?php print(isset($_GET["search_area"]) && $_GET["search_area"] == 1 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_description'] ?></option>
								<?php
								/*if ($smalldescription_main == 'yes'){
								?>
								<option value="2"<?php print($_GET["search_area"] == 2 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_small_description'] ?></option>
								<?php
								}*/
								?>
								<option value="3"<?php print(isset($_GET["search_area"]) && $_GET["search_area"] == 3 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_uploader'] ?></option>
								<option value="4"<?php print(isset($_GET["search_area"]) && $_GET["search_area"] == 4 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_imdb_url'] ?></option>
							</select>

							<?php echo $lang_torrents['text_with'] ?>

							<select name="search_mode" style="width: 60px;">
								<option value="0"><?php echo $lang_torrents['select_and'] ?></option>
								<option value="1"<?php echo isset($_GET["search_mode"]) && $_GET["search_mode"] == 1 ? " selected=\"selected\"" : "" ?>><?php echo $lang_torrents['select_or'] ?></option>
								<option value="2"<?php echo isset($_GET["search_mode"]) && $_GET["search_mode"] == 2 ? " selected=\"selected\"" : "" ?>><?php echo $lang_torrents['select_exact'] ?></option>
							</select>

							<?php echo $lang_torrents['text_mode'] ?>
						</td>
					</tr>
<?php
$Cache->new_page('hot_search', 3670, true);
if (!$Cache->get_page()){
	$secs = 3*24*60*60;
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs)));
	$dt2 = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs*2)));
	sql_query("DELETE FROM suggest WHERE adddate <" . $dt2) or sqlerr();
	$searchres = sql_query("SELECT keywords, COUNT(DISTINCT userid) as count FROM suggest WHERE adddate >" . $dt . " GROUP BY keywords ORDER BY count DESC LIMIT 15") or sqlerr();
	$hotcount = 0;
	$hotsearch = "";
	while ($searchrow = mysql_fetch_assoc($searchres))
	{
		$hotsearch .= "<a href=\"".htmlspecialchars("?search=" . rawurlencode($searchrow["keywords"]) . "&notnewword=1")."\"><u>" . $searchrow["keywords"] . "</u></a>&nbsp;&nbsp;";
		$hotcount += mb_strlen($searchrow["keywords"],"UTF-8");
		if ($hotcount > 60)
			break;
	}
	$Cache->add_whole_row();
	if ($hotsearch)
	print("<tr><td class=\"embedded\" colspan=\"3\">&nbsp;&nbsp;".$hotsearch."</td></tr>");
	$Cache->end_whole_row();
	$Cache->cache_page();
}
echo $Cache->next_row();

if ($tagKeyById->isNotEmpty()) {
    echo '<tr><td colspan="3" class="embedded" style="padding-top: 4px">' . $tagRep->renderSpan($tagKeyById, $renderKeyArr, true) . '</td></tr>';
}

?>

				</table>
			</td>
			<td class="rowfollow" align="center">
				<input type="submit" class="btn" value="<?php echo $lang_torrents['submit_go'] ?>" />
			</td>
		</tr>
		</tbody>
	</table>
	</form>
<?php
}
	if ($Advertisement->enable_ad()){
        $belowsearchboxad = $Advertisement->get_ad('belowsearchbox');
        if (!empty($belowsearchboxad[0])) {
            echo "<div align=\"center\" style=\"margin-top: 10px\" id=\"\">".$belowsearchboxad[0]."</div>";
        }
	}
if($inclbookmarked == 1)
{
	print("<h1 align=\"center\">" . get_username($CURUSER['id']) . $lang_torrents['text_s_bookmarked_torrent'] . "</h1>");
}
elseif($inclbookmarked == 2)
{
	print("<h1 align=\"center\">" . get_username($CURUSER['id']) . $lang_torrents['text_s_not_bookmarked_torrent'] . "</h1>");
}

if ($count) {
    $rows = [];
    if ($elasticsearchEnabled) {
        $rows = $resultFromElastic['data'];
    } else {
        while ($row = mysql_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    $rows = apply_filter('torrent_list', $rows, $page, $sectiontype);
	print($pagertop);
	if ($sectiontype == $browsecatmode)
		torrenttable($rows, "torrents");
	elseif ($sectiontype == $specialcatmode)
		torrenttable($rows, "music");
	else torrenttable($rows, "bookmarks");
	print($pagerbottom);
}
else {
	if (isset($searchstr)) {
		print("<br />");
		stdmsg($lang_torrents['std_search_results_for'] . $searchstr_ori . "\"",$lang_torrents['std_try_again']);
	}
	else {
		stdmsg($lang_torrents['std_nothing_found'],$lang_torrents['std_no_active_torrents']);
	}
}
if ($CURUSER){
	if ($sectiontype == $browsecatmode)
		$USERUPDATESET[] = "last_browse = ".TIMENOW;
	else	$USERUPDATESET[] = "last_music = ".TIMENOW;
}
print("</td></tr></table>");
stdfoot();
