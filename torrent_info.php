<?php
require "include/bittorrent.php";
require_once "include/benc.php";

function print_array($array, $offset_symbol = "|--", $offset = "", $parent = "")
{
  if (!is_array($array))
  {
    echo "[$array] is not an array!<br />";
    return;
  }
 
  reset($array);


	switch($array['type'])
	{
		case "string":
			printf("<li><div align=left class=string> - <span class=icon>[STRING]</span> <span class=title>[%s]</span> <span class=length>(%d)</span>: <span class=value>%s</span></div></li>",$parent,$array['strlen'],$array['value']);
			break;
		case "integer":
			printf("<li><div align=left class=integer> - <span class=icon>[INT]</span> <span class=title>[%s]</span> <span class=length>(%d)</span>: <span class=value>%s</span></div></li>",$parent,$array['strlen'],$array['value']);
			break;
		case "list":
			printf("<li><div align=left class=list> + <span class=icon>[LIST]</span> <span class=title>[%s]</span> <span class=length>(%d)</span></div>",$parent,$array['strlen']);
			echo "<ul>";
			print_array($array['value'], $offset_symbol, $offset.$offset_symbol);
			echo "</ul></li>";
			break;
		case "dictionary":
			printf("<li><div align=left class=dictionary> + <span class=icon>[DICT]</span> <span class=title>[%s]</span> <span class=length>(%d)</span></div>",$parent,$array['strlen']);
			while (list($key, $val) = each($array))
			{
				if (is_array($val))
				{
					echo "<ul>";
					print_array($val, $offset_symbol, $offset.$offset_symbol,$key);
					echo "</ul>";
				}
			}
			echo "</li>";

			break;
		default:
			  while (list($key, $val) = each($array))
			  {
			    if (is_array($val))
			    {
			      //echo $offset;
			      print_array($val, $offset_symbol, $offset, $key);
			    }
			  }			
			break;
	
	}
 
} 

dbconn();

loggedinorreturn();

if (get_user_class() < $torrentstructure_class)
{
	permissiondenied();
}

$id = (int)$_GET["id"];

if (!$id)
	httperr();

$res = sql_query("SELECT name FROM torrents WHERE id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
$row = mysql_fetch_assoc($res);

$fn = "$torrent_dir/$id.torrent";

if (!$row || !is_file($fn) || !is_readable($fn))
	httperr();



// Standard html headers
stdhead("Torrent Info");
?>

<style type="text/css">

/* list styles */
ul ul { margin-left: 15px; }
ul, li { padding: 0px; margin: 0px; list-style-type: none; color: #000; font-weight: normal;}
ul a, li a { color: #009; text-decoration: none; font-weight: normal; }
li { display: inline; } /* fix for IE blank line bug */
ul > li { display: list-item; }

li div.string  {padding: 3px;}
li div.integer {padding: 3px;}
li div.dictionary {padding: 3px;}
li div.list {padding: 3px;}
li div.string span.icon {color:#090;padding: 2px;}
li div.integer span.icon {color:#990;padding: 2px;}
li div.dictionary span.icon {color:#909;padding: 2px;}
li div.list span.icon {color:#009;padding: 2px;}

li span.title {font-weight: bold;}

</style>

<?php

begin_main_frame();


// Heading
print("<div align=center><h1>$row[name]</h1>");

$dict = bdec_file($fn, (1024*1024));

// Start table
print("<table width=750 border=1 cellspacing=0 cellpadding=5><td>");

$dict['value']['info']['value']['pieces']['value'] = "0x".bin2hex(substr($dict['value']['info']['value']['pieces']['value'], 0, 25))."...";



echo "<ul id=colapse>";
print_array($dict,"*", "", "root");
echo "</ul>";

// End table

print("</td></table>");


?>


<script type="text/javascript" language="javascript1.2">
var openLists = [], oIcount = 0;
function compactMenu(oID,oAutoCol,oPlMn,oMinimalLink) {
	if( !document.getElementsByTagName || !document.childNodes || !document.createElement ) { return; }
	var baseElement = document.getElementById( oID ); if( !baseElement ) { return; }
	compactChildren( baseElement, 0, oID, oAutoCol, oPlMn, baseElement.tagName.toUpperCase(), oMinimalLink && oPlMn );
}
function compactChildren( oOb, oLev, oBsID, oCol, oPM, oT, oML ) {
	if( !oLev ) { oBsID = escape(oBsID); if( oCol ) { openLists[oBsID] = []; } }
	for( var x = 0, y = oOb.childNodes; x < y.length; x++ ) { if( y[x].tagName ) {
		//for each immediate LI child
		var theNextUL = y[x].getElementsByTagName( oT )[0];
		if( theNextUL ) {
			//collapse the first UL/OL child
			theNextUL.style.display = 'none';
			//create a link for expanding/collapsing
			var newLink = document.createElement('A');
			newLink.setAttribute( 'href', '#' );
			newLink.onclick = new Function( 'clickSmack(this,' + oLev + ',\'' + oBsID + '\',' + oCol + ',\'' + escape(oT) + '\');return false;' );
			//wrap everything upto the child U/OL in the link
			if( oML ) { var theHTML = ''; } else {
				var theT = y[x].innerHTML.toUpperCase().indexOf('<'+oT);
				var theA = y[x].innerHTML.toUpperCase().indexOf('<A');
				var theHTML = y[x].innerHTML.substr(0, ( theA + 1 && theA < theT ) ? theA : theT );
				while( !y[x].childNodes[0].tagName || ( y[x].childNodes[0].tagName.toUpperCase() != oT && y[x].childNodes[0].tagName.toUpperCase() != 'A' ) ) {
					y[x].removeChild( y[x].childNodes[0] ); }
			}
			y[x].insertBefore(newLink,y[x].childNodes[0]);
			y[x].childNodes[0].innerHTML = oPM + theHTML.replace(/^\s*|\s*$/g,'');
			theNextUL.MWJuniqueID = oIcount++;
			compactChildren( theNextUL, oLev + 1, oBsID, oCol, oPM, oT, oML );
} } } }
function clickSmack( oThisOb, oLevel, oBsID, oCol, oT ) {
	if( oThisOb.blur ) { oThisOb.blur(); }
	oThisOb = oThisOb.parentNode.getElementsByTagName( unescape(oT) )[0];
	if( oCol ) {
		for( var x = openLists[oBsID].length - 1; x >= oLevel; x-=1 ) { if( openLists[oBsID][x] ) {
			openLists[oBsID][x].style.display = 'none'; if( oLevel != x ) { openLists[oBsID][x] = null; }
		} }
		if( oThisOb == openLists[oBsID][oLevel] ) { openLists[oBsID][oLevel] = null; }
		else { oThisOb.style.display = 'block'; openLists[oBsID][oLevel] = oThisOb; }
	} else { oThisOb.style.display = ( oThisOb.style.display == 'block' ) ? 'none' : 'block'; }
}
function stateToFromStr(oID,oFStr) {
	if( !document.getElementsByTagName || !document.childNodes || !document.createElement ) { return ''; }
	var baseElement = document.getElementById( oID ); if( !baseElement ) { return ''; }
	if( !oFStr && typeof(oFStr) != 'undefined' ) { return ''; } if( oFStr ) { oFStr = oFStr.split(':'); }
	for( var oStr = '', l = baseElement.getElementsByTagName(baseElement.tagName), x = 0; l[x]; x++ ) {
		if( oFStr && MWJisInTheArray( l[x].MWJuniqueID, oFStr ) && l[x].style.display == 'none' ) { l[x].parentNode.getElementsByTagName('a')[0].onclick(); }
		else if( l[x].style.display != 'none' ) { oStr += (oStr?':':'') + l[x].MWJuniqueID; }
	}
	return oStr;
}
function MWJisInTheArray(oNeed,oHay) { for( var i = 0; i < oHay.length; i++ ) { if( oNeed == oHay[i] ) { return true; } } return false; }
function selfLink(oRootElement,oClass,oExpand) {
	if(!document.getElementsByTagName||!document.childNodes) { return; }
	oRootElement = document.getElementById(oRootElement);
	for( var x = 0, y = oRootElement.getElementsByTagName('a'); y[x]; x++ ) {
		if( y[x].getAttribute('href') && !y[x].href.match(/#$/) && getRealAddress(y[x]) == getRealAddress(location) ) {
			y[x].className = (y[x].className?(y[x].className+' '):'') + oClass;
			if( oExpand ) {
				oExpand = false;
				for( var oEl = y[x].parentNode, ulStr = ''; oEl != oRootElement && oEl != document.body; oEl = oEl.parentNode ) {
					if( oEl.tagName && oEl.tagName == oRootElement.tagName ) { ulStr = oEl.MWJuniqueID + (ulStr?(':'+ulStr):''); } }
				stateToFromStr(oRootElement.id,ulStr);
} } } }
function getRealAddress(oOb) { return oOb.protocol + ( ( oOb.protocol.indexOf( ':' ) + 1 ) ? '' : ':' ) + oOb.hostname + ( ( typeof(oOb.pathname) == typeof(' ') && oOb.pathname.indexOf('/') != 0 ) ? '/' : '' ) + oOb.pathname + oOb.search; }

compactMenu('colapse',false,'');
</script>



<?php
// Standard html footers
end_main_frame();
stdfoot();
