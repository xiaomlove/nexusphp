<?php
require "../include/bittorrent.php";
$passkey = $_GET['passkey'] ?? $CURUSER['passkey'] ?? '';
if (!$passkey) {
    die("require passkey");
}
$cacheKey = "nexus_rss:$passkey:" . md5(http_build_query($_GET));
$cacheData = \Nexus\Database\NexusDB::cache_get($cacheKey);
if ($cacheData && nexus_env('APP_ENV') != 'local') {
    do_log("rss get from cache");
    header ("Content-type: text/xml");
    die($cacheData);
}
dbconn();
function hex_esc($matches) {
	return sprintf("%02x", ord($matches[0]));
}
$dllink = false;

$where = "torrents.visible = 'yes'";
if ($passkey){
	$res = sql_query("SELECT id, enabled, parked, passkey FROM users WHERE passkey=". sqlesc($passkey)." LIMIT 1");
	$user = mysql_fetch_array($res);
	if (!$user)
		die("invalid passkey");
	elseif ($user['enabled'] == 'no' || $user['parked'] == 'yes')
		die("account disabed or parked");
	elseif (isset($_GET['linktype']) && $_GET['linktype'] == 'dl')
		$dllink = true;
	$inclbookmarked=intval($_GET['inclbookmarked'] ?? 0);
	if($inclbookmarked == 1)
	{
		$bookmarkarray = return_torrent_bookmark_array($user['id']);
		if ($bookmarkarray){
			$whereidin = implode(",", $bookmarkarray);
			$where .= ($where ? " AND " : "") . "torrents.id IN(" . $whereidin . ")";
		}
	}
}
$searchstr = mysql_real_escape_string(trim($_GET["search"] ?? ''));
if (empty($searchstr))
	unset($searchstr);
if (isset($searchstr)){
	$search_mode = intval($_GET["search_mode"] ?? 0);
	if (!in_array($search_mode,array(0,2)))
	{
		$search_mode = 0;
	}
	switch ($search_mode)
	{
		case 0:	// AND, OR
		case 1	:
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
		case 2	:	// exact
		{
			$like_expression_array[] = " LIKE '%" . $searchstr. "%'";
			break;
		}
	}

	$ANDOR = ($search_mode == 0 ? " AND " : " OR ");	// only affects mode 0 and mode 1
	foreach ($like_expression_array as &$like_expression_array_element)
		$like_expression_array_element = "(torrents.name" . $like_expression_array_element.($_GET['ismalldescr'] ? " OR torrents.small_descr". $like_expression_array_element : "").")";
	$wherea[] = implode($ANDOR, $like_expression_array);
	$where .= ($where ? " AND " : "") . implode(" AND ", $wherea);
}
$limit = "";
$startindex = intval($_GET['startindex'] ?? 0);
if ($startindex) {
    $limit .= $startindex.", ";
}
$showrows = intval($_GET['rows'] ?? 0);
if($showrows < 1 || $showrows > 200) {
    $showrows = 10;
}
$limit .= $showrows;

//approval status
$approvalStatusNoneVisible = get_setting('torrent.approval_status_none_visible');
if ($approvalStatusNoneVisible == 'no' && !user_can('staffmem', false, $user['id'])) {
    $where .= ($where ? " AND " : "") . "torrents.approval_status = " . \App\Models\Torrent::APPROVAL_STATUS_ALLOW;
}
//check special section permission
$browseMode = get_setting('main.browsecat');
$onlyBrowseSection = get_setting('main.spsct') != 'yes' || !user_can('view_special_torrent', false, $user['id']);
if ($onlyBrowseSection) {
    $where .= ($where ? " AND " : "") . "categories.mode = $browseMode";
}
//check price
if (isset($_GET['paid']) && in_array($_GET['paid'], ['0', '1', '2'], true)) {
    $paidFilter = $_GET['paid'];
} else {
    $paidFilter = '0';
}
if ($paidFilter === '0') {
    $where .= ($where ? " AND " : "") . "torrents.price = 0";
} elseif ($paidFilter === '1') {
    $where .= ($where ? " AND " : "") . "torrents.price > 0";
}

function get_where($tablename = "sources", $itemname = "source", $getname = "sou")
{
	global $where;
	$items = searchbox_item_list($tablename, 0);
	$whereitemina = array();
	foreach ($items as $item)
	{
		if (!empty($_GET[$getname.$item['id']]))
		{
			$whereitemina[] = $item['id'];
		}
	}
	if (count($whereitemina) >= 1){
		$whereitemin = implode(",",$whereitemina);
		$where .= ($where ? " AND " : "") . $itemname." IN(" . $whereitemin . ")";
	}
}
get_where("categories", "category", "cat");
get_where("sources", "source", "sou");
get_where("media", "medium", "med");
get_where("codecs", "codec", "cod");
get_where("standards", "standard", "sta");
get_where("processings", "processing", "pro");
get_where("teams", "team", "tea");
get_where("audiocodecs", "audiocodec", "aud");

$hasStickyFirst = $hasStickySecond = $hasStickyNormal = $noNormalResults = false;
$prependIdArr = $prependRows = $normalRows = [];
$stickyWhere = $normalWhere = '';
if (isset($_GET['sticky']) && $inclbookmarked == 0) {
    $stickyArr = explode(',', $_GET['sticky']);
    //Only handle sticky first + second
    $posStates = [];
    if (in_array('0', $stickyArr, true)) {
        $hasStickyNormal = true;
    }
    if (in_array('1', $stickyArr, true)) {
        $hasStickyFirst = true;
        $posStates[] = \App\Models\Torrent::POS_STATE_STICKY_FIRST;
    }
    if (in_array('2', $stickyArr, true)) {
        $hasStickySecond = true;
        $posStates[] = \App\Models\Torrent::POS_STATE_STICKY_SECOND;
    }
    if (!empty($posStates)) {
        $prependIdArr = \App\Models\Torrent::query()->whereIn('pos_state', $posStates)->pluck('id')->toArray();
    }
}
$prependIdArr = apply_filter("sticky_promotion_torrent_ids", $prependIdArr);
if ($hasStickyNormal) {
    $stickyWhere = sprintf("torrents.pos_state = '%s'", \App\Models\Torrent::POS_STATE_STICKY_NONE);
} elseif ($hasStickyFirst || $hasStickySecond) {
    $noNormalResults = true;
}

if ($where) {
    $normalWhere = "WHERE ".$where;
    if ($stickyWhere) {
        $normalWhere .= " and $stickyWhere";
    }
}
$sort = "id desc";
$fieldStr = "torrents.id, torrents.category, torrents.name, torrents.small_descr, torrents.descr, torrents.info_hash, torrents.size, torrents.added, torrents.anonymous, torrents.owner, categories.name AS category_name";
//$query = "SELECT torrents.id, torrents.category, torrents.name, torrents.small_descr, torrents.descr, torrents.info_hash, torrents.size, torrents.added, torrents.anonymous, users.username AS username, categories.id AS cat_id, categories.name AS cat_name FROM torrents LEFT JOIN categories ON category = categories.id LEFT JOIN users ON torrents.owner = users.id $where ORDER BY $sort LIMIT $limit";
if (!$noNormalResults) {
    $query = "SELECT $fieldStr FROM torrents LEFT JOIN categories ON category = categories.id $normalWhere ORDER BY $sort LIMIT $limit";
    $normalRows = \Nexus\Database\NexusDB::select($query);
}
if (!empty($prependIdArr) && $startindex == 0) {
    $prependIdStr = implode(',', $prependIdArr);
    $prependRows = \Nexus\Database\NexusDB::select("SELECT $fieldStr FROM torrents LEFT JOIN categories ON category = categories.id where torrents.id in ($prependIdStr) and $where ORDER BY field(torrents.id, $prependIdStr)");
}
$list = [];
foreach ($prependRows as $row) {
    $list[$row['id']] = $row;
}
foreach ($normalRows as $row) {
    if (!isset($list[$row['id']])) {
        $list[$row['id']] = $row;
    }
}

//dd($prependIdArr, $prependRows, $normalRows, $list, $startindex,last_query());

$torrentRep = new \App\Repositories\TorrentRepository();
$url = get_protocol_prefix().$BASEURL;
$year = substr($datefounded, 0, 4);
$yearfounded = ($year ? $year : 2007);
$copyright = "Copyright (c) ".$SITENAME." ".(date("Y") != $yearfounded ? $yearfounded."-" : "").date("Y").", all rights reserved";
$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
//The commented version passed feed validator at http://www.feedvalidator.org
/*print('
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">');*/
$xml .= '<rss version="2.0">';
$xml .= '<channel>
		<title>' . addslashes($SITENAME.' Torrents'). '</title>
		<link><![CDATA[' . $url . ']]></link>
		<description><![CDATA[' . addslashes('Latest torrents from '.$SITENAME.' - '.htmlspecialchars($SLOGAN)) . ']]></description>
		<language>zh-cn</language>
		<copyright>'.$copyright.'</copyright>
		<managingEditor>'.$SITEEMAIL.' ('.$SITENAME.' Admin)</managingEditor>
		<webMaster>'.$SITEEMAIL.' ('.$SITENAME.' Webmaster)</webMaster>
		<pubDate>'.date('r').'</pubDate>
		<generator>'.PROJECTNAME.' RSS Generator</generator>
		<docs><![CDATA[http://www.rssboard.org/rss-specification]]></docs>
		<ttl>60</ttl>
		<image>
			<url><![CDATA[' . $url.'/pic/rss_logo.jpg'. ']]></url>
			<title>' . addslashes($SITENAME.' Torrents') . '</title>
			<link><![CDATA[' . $url . ']]></link>
			<width>100</width>
			<height>100</height>
			<description>' . addslashes($SITENAME.' Torrents') . '</description>
		</image>';
/*print('
		<atom:link href="'.$url.$_SERVER['REQUEST_URI'].'" rel="self" type="application/rss+xml" />');*/
//print('
//');
foreach ($list as $row)
{
    $ownerInfo = get_user_row($row['owner']);
	$title = "";
	if ($row['anonymous'] == 'yes')
		$author = 'anonymous';
	else $author = $ownerInfo['username'];
	$itemurl = $url."/details.php?id=".$row['id'];
	if ($dllink)
		$itemdlurl = $url."/download.php?id=".$row['id']."&amp;downhash=" . rawurlencode( $user['id'] . '|'. $torrentRep->encryptDownHash($row['id'], $user));
	else $itemdlurl = $url."/download.php?id=".$row['id'];
	if (!empty($_GET['icat'])) $title .= "[".$row['category_name']."]";
	$title .= $row['name'];
	if (!empty($_GET['ismalldescr']) && !empty($row['small_descr'])) $title .= "[".$row['small_descr']."]";
	if (!empty($_GET['isize'])) $title .= "[".mksize($row['size'])."]";
	if (!empty($_GET['iuplder'])) $title .= "[".$author."]";
	$content = format_comment($row['descr'], true, false, false, false);
	$xml .= '<item>
			<title><![CDATA['.$title.']]></title>
			<link>'.$itemurl.'</link>
			<description><![CDATA['.$content.']]></description>
';
//print('			<dc:creator>'.$author.'</dc:creator>');
$xml .= '<author>'.$author.'@'.$_SERVER['HTTP_HOST'].' ('.$author.')</author>';
$xml .= '<category domain="'.$url.'/torrents.php?cat='.$row['category'].'">'.$row['category_name'].'</category>
			<comments><![CDATA['.$url.'/details.php?id='.$row['id'].'&cmtpage=0#startcomments]]></comments>
			<enclosure url="'.$itemdlurl.'" length="'.$row['size'].'" type="application/x-bittorrent" />
			<guid isPermaLink="false">'.preg_replace_callback('/./s', 'hex_esc', hash_pad($row['info_hash'])).'</guid>
			<pubDate>'.date('r',strtotime($row['added'])).'</pubDate>
		</item>
';
}
$xml .= '</channel>
</rss>';
do_log("rss cache generated");
\Nexus\Database\NexusDB::cache_put($cacheKey, $xml, 300);
header ("Content-type: text/xml");
echo $xml;
?>
