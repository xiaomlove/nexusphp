<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
?>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?php echo $lang_moresmilies['head_more_smilies']?></title>
<style type="text/css">
img {border: none;}
body {color: #000000; background-color: #ffffff}
</style>
</head>
<body>
<script type="text/javascript">
function SmileIT(smile,form,text){
   window.opener.document.forms[form].elements[text].value = window.opener.document.forms[form].elements[text].value+" "+smile+" ";
   window.opener.document.forms[form].elements[text].focus();
   window.close();
}
</script>

<table class="lista" width="100%" cellpadding="1" cellspacing="1">
<?php
$count = 0;
for($i=1; $i<192; $i++) {
  if ($count % 3==0)
     print("\n<tr>");

     print("\n\t<td class=\"lista\" align=\"center\"><a href=\"javascript: SmileIT('[em$i]','".$_GET["form"]."','".$_GET["text"]."')\"><img src=\"pic/smilies/$i.gif\" alt=\"\" ></a></td>");
     $count++;

  if ($count % 3==0)
     print("\n</tr>");
}

?>
</table>
<div align="center">
 <a href="javascript: window.close()"><?php echo $lang_moresmilies['text_close']?></a>
</div>
</body>
</html>
