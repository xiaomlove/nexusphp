<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
$id = $_GET["id"];
if (get_user_class() < $viewnfo_class || !is_valid_id($id) || $enablenfo_main != 'yes')
permissiondenied();

$r = sql_query("SELECT name,nfo FROM torrents WHERE id=$id") or sqlerr();
$a = mysql_fetch_assoc($r) or die($lang_viewnfo['std_puke']);

//error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

// view might be one of: "magic", "latin-1", "strict" or "fonthack"
$view = "";
if (isset($_GET["view"])) {
$view = unesc($_GET["view"]);
}
else {
$view = "magic"; // default behavior
}

$nfo = "";
if ($view == "latin-1" || $view == "fonthack") {
// Do not convert from ibm-437, read bytes as is.
// NOTICE: TBSource specifies Latin-1 encoding in include/bittorrent.php:
// stdhead()
$nfo = htmlspecialchars(($a["nfo"]));
}
else {
// Convert from ibm-437 to html unicode entities.
// take special care of Swedish letters if in magic view.
$nfo = code($a["nfo"], $view == "magic");
}

stdhead($lang_viewnfo['head_view_nfo']);
print($lang_viewnfo['text_nfo_for']."<a href=details.php?id=$id>".htmlspecialchars($a["name"])."</a>\n");

?>
<table border="1" cellspacing="0" cellpadding="10" align="center">
<tr>
<?php /*<td align="center" width="25%">
<a href="viewnfo.php?id=<?php echo $id?>&view=fonthack"
title="Teckensnittshack: Anv�nder nagon av teckensnitten MS LineDraw eller Terminal"><b>Teckensnittshack</b></a></td>*/?>
<td align="center" width="50%">
<a href="viewnfo.php?id=<?php echo $id?>&view=magic"
title=<?php echo $lang_viewnfo['title_dos_vy'] ?>>
<b><?php echo $lang_viewnfo['text_dos_vy'] ?></b></a></td>
<td align="center" width="50%">
<a href="viewnfo.php?id=<?php echo $id?>&view=latin-1"
title='<?php echo $lang_viewnfo['title_windows_vy']?>'><b><?php echo $lang_viewnfo['text_windows_vy'] ?></b></a></td>
<?php /*<td align="center" width="25%">
<a href="viewnfo.php?id=<?php echo $id?>&view=strict"
title="Strikt: Visar nfo-filen som den ser ut i teckentabellen IBM-437">
<b>Strikt DOS-vy</b></a></td>*/?>
</tr>
<tr>
<td colspan="3">
<table border=1 cellspacing=0 cellpadding=5><tr><td class=text>
<?php
// -- About to output NFO data
if ($view == "fonthack") {
// Please notice: MS LineDraw's glyphs are included in the Courier New font
// as of Courier New version 2.0, but uses the correct mappings instead.
// http://support.microsoft.com/kb/q179422/
print("<pre style=\"font-size:10pt; font-family: 'MS LineDraw', 'Terminal', monospace;\">");
}
else {
// IE6.0 need to know which font to use, Mozilla can figure it out in its own
// (windows firefox at least)
// Anything else than 'Courier New' looks pretty broken.
// 'Lucida Console', 'FixedSys'
print("<pre style=\"font-size:10pt; font-family: 'Courier New', monospace;\">");
}
// Writes the (eventually modified) nfo data to output, first formating urls.
print(format_urls($nfo));
print("</pre>\n");
?>
</td></tr></table>
</td>
</tr>
</table>
<?php
stdfoot();
