<?php
require "../include/bittorrent.php";
dbconn();
function data_url($file, $mime) 
{  
  $contents = file_get_contents($file);
  $base64   = base64_encode($contents); 
  return ('data:' . $mime . ';base64,' . $base64);
}
$url = get_protocol_prefix().$BASEURL;
$year = substr($datefounded, 0, 4);
$yearfounded = ($year ? $year : 2007);
$attribution = "Copyright (c) ".$SITENAME." ".(date("Y") != $yearfounded ? $yearfounded."-" : "").date("Y").", all rights reserved";
header ("Content-type: text/xml");
$Cache->new_page('opensearch_description', 86400);
if (!$Cache->get_page()){
	$Cache->add_whole_row();
	print("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n");
?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/"
	xmlns:moz="http://www.mozilla.org/2006/browser/search/">
	<ShortName><?php echo $SITENAME?> Torrents</ShortName>
	<Description>Search Torrents at <?php echo $SITENAME?> - <?php echo htmlspecialchars($SLOGAN)?>.</Description>
	<Url type="text/html"
		rel="results"
		pageOffset="0"
      		template="<?php echo $url?>/torrents.php?search={searchTerms}&amp;page={startPage?}" />
	<Url type="application/rss+xml"
		rel="results"
		indexOffset="0"
		template="<?php echo $url?>/torrentrss.php?search={searchTerms}&amp;rows={count?}&amp;startindex={startIndex?}" />
	<Url type="application/opensearchdescription+xml"
		rel="self"
		template="<?php echo $url?>/opensearch.php" />
	<Url type="application/x-suggestions+json"
		rel="suggestions"
		template="<?php echo $url?>/searchsuggest.php?q={searchTerms}" />
	<Contact><?php echo $SITEEMAIL?></Contact>
	<Tags>Torrents <?php echo PROJECTNAME?></Tags>
	<LongName><?php echo $SITENAME?> Torrents Search</LongName>
	<Image height="32" width="32" type="image/x-icon"><?php echo data_url('favicon.ico', 'image/x-icon')?></Image>
	<Image height="32" width="32" type="image/x-icon"><?php echo $url?>/favicon.ico</Image>
	<moz:SearchForm><?php echo $url?>/torrents.php</moz:SearchForm>
	<Query role="example" searchTerms="batman" />
	<Developer><?php echo $SITENAME?> Staff</Developer>
	<Attribution><?php echo $attribution?></Attribution>
	<SyndicationRight>limited</SyndicationRight>
	<Language>*</Language>
	<InputEncoding>UTF-8</InputEncoding>
	<OutputEncoding>UTF-8</OutputEncoding>
</OpenSearchDescription>
<?php
	$Cache->end_whole_row();
	$Cache->cache_page();
}
echo $Cache->next_row();
?>
