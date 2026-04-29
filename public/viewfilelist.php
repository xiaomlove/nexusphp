<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());

//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

if (!function_exists('viewfilelist_ext_category')) {
	/**
	 * Maps a file extension to a category used for badge color.
	 */
	function viewfilelist_ext_category(string $ext): string
	{
		static $map = [
			'video'    => ['mkv','mp4','avi','mov','wmv','flv','ts','m2ts','mts','webm','mpg','mpeg','vob','rm','rmvb','m4v','3gp','ogv','asf','divx','mxf'],
			'audio'    => ['mp3','flac','wav','ogg','m4a','aac','opus','wma','ape','alac','dts','ac3','mka','mp2','mid','midi','tak','tta','wv'],
			'image'    => ['jpg','jpeg','png','gif','bmp','tiff','tif','webp','svg','heic','heif','ico','psd','raw','arw','cr2','nef'],
			'subtitle' => ['srt','ass','ssa','sub','idx','vtt','sup','smi','sbv'],
			'archive'  => ['zip','rar','7z','tar','gz','bz2','xz','zst','lz','lzma','tbz2','tgz','txz','cab','arj'],
			'iso'      => ['iso','img','mds','mdf','bin','cue','nrg','dmg','vhd','vmdk'],
			'document' => ['pdf','epub','mobi','azw','azw3','doc','docx','xls','xlsx','ppt','pptx','rtf','djvu','fb2','chm','odt','ods','odp'],
			'text'     => ['txt','md','log','sfv','md5','sha1','sha256','par','par2','json','xml','yaml','yml','csv','ini'],
			'nfo'      => ['nfo'],
			'code'     => ['php','js','ts','py','rb','go','rs','c','h','cpp','hpp','cs','java','sh','sql','html','css','scss','vue'],
			'exec'     => ['exe','msi','app','deb','rpm','apk','dmg','pkg','run','bat','cmd','ps1','jar'],
			'torrent'  => ['torrent'],
		];
		foreach ($map as $cat => $list) {
			if (in_array($ext, $list, true)) {
				return $cat;
			}
		}
		return 'other';
	}
}

if (!function_exists('viewfilelist_render_badge')) {
	/**
	 * Renders a small colored extension badge for a filename.
	 * Returns inline HTML safe to splice before the filename.
	 */
	function viewfilelist_render_badge(string $filename): string
	{
		$dot = strrpos($filename, '.');
		$ext = $dot !== false ? strtolower(substr($filename, $dot + 1)) : '';
		// Defend against weirdly long fake extensions; if not alphanumeric or too long, treat as no ext.
		if ($ext === '' || strlen($ext) > 5 || !ctype_alnum($ext)) {
			$cat = 'other';
			$label = '?';
		} else {
			$cat = viewfilelist_ext_category($ext);
			$label = strtoupper($ext);
		}
		return '<span class="fileicon fi-' . htmlspecialchars($cat) . '" title="' . htmlspecialchars($cat) . '">' . htmlspecialchars($label) . '</span>';
	}
}

$id = intval($_GET['id'] ?? 0);
if(isset($CURUSER))
{
	$css = '<style>
.fileicon { display:inline-block; box-sizing:border-box; min-width:38px; padding:1px 5px; margin-right:6px; border-radius:3px; font:10px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight:bold; letter-spacing:.3px; color:#fff; text-align:center; vertical-align:1px; text-transform:uppercase; }
.fileicon.fi-video    { background:#3498db; }
.fileicon.fi-audio    { background:#27ae60; }
.fileicon.fi-image    { background:#9b59b6; }
.fileicon.fi-subtitle { background:#e84393; }
.fileicon.fi-archive  { background:#e67e22; }
.fileicon.fi-iso      { background:#34495e; }
.fileicon.fi-document { background:#c0392b; }
.fileicon.fi-text     { background:#7f8c8d; }
.fileicon.fi-nfo      { background:#16a085; }
.fileicon.fi-code     { background:#2c3e50; }
.fileicon.fi-exec     { background:#d35400; }
.fileicon.fi-torrent  { background:#8e44ad; }
.fileicon.fi-other    { background:#95a5a6; }
</style>';

	$s  = $css;
	$s .= "<table class=\"main\" border=\"1\" cellspacing=0 cellpadding=\"5\">\n";

	$subres = sql_query("SELECT * FROM files WHERE torrent = ".sqlesc($id)." ORDER BY id");
	$s.="<tr><td class=colhead>".$lang_viewfilelist['col_path']."</td><td class=colhead align=center><img class=\"size\" src=\"pic/trans.gif\" alt=\"size\" /></td></tr>\n";
	while ($subrow = mysql_fetch_array($subres)) {
		$badge = viewfilelist_render_badge((string)$subrow["filename"]);
		$s .= "<tr><td class=rowfollow>" . $badge . htmlspecialchars($subrow["filename"]) . "</td><td class=rowfollow align=\"right\">" . mksize($subrow["size"]) . "</td></tr>\n";
	}
	$s .= "</table>\n";
	echo $s;
}
?>
