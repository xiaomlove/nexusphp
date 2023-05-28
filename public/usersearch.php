<?php
require "../include/bittorrent.php";

// 0 - No debug; 1 - Show and run SQL query; 2 - Show SQL query only
$DEBUG_MODE = 0;
dbconn();
loggedinorreturn();
parked();
if (get_user_class() < UC_MODERATOR)
	stderr("Error", "Permission denied.");

stdhead("Administrative User Search");
echo "<h1>Administrative User Search</h1>\n";

if (!empty($_GET['h']))
{
	echo "<table width=65% border=0 align=center><tr><td class=embedded bgcolor='#F5F4EA'><div align=left>\n
	Fields left blank will be ignored;\n
	Wildcards * and ? may be used in Name, Email and Comments, as well as multiple values\n
	separated by spaces (e.g. 'wyz Max*' in Name will list both users named\n
	'wyz' and those whose names start by 'Max'. Similarly  '~' can be used for\n
	negation, e.g. '~alfiest' in comments will restrict the search to users\n
	that do not have 'alfiest' in their comments).<br /><br />\n
    The Ratio field accepts 'Inf' and '---' besides the usual numeric values.<br /><br />\n
	The subnet mask may be entered either in dotted decimal or CIDR notation\n
	(e.g. 255.255.255.0 is the same as /24).<br /><br />\n
    Uploaded and Downloaded should be entered in GB.<br /><br />\n
	For search parameters with multiple text fields the second will be\n
	ignored unless relevant for the type of search chosen. <br /><br />\n
	'Active only' restricts the search to users currently leeching or seeding,\n
	'Disabled IPs' to those whose IPs also show up in disabled accounts.<br /><br />\n
	The 'p' columns in the results show partial stats, that is, those\n
	of the torrents in progress. <br /><br />\n
	The History column lists the number of forum posts and torrent comments,\n
	respectively, as well as linking to the history page.\n
	</div></td></tr></table><br /><br />\n";
}
else
{
	echo "<p align=center>(<a href='".$_SERVER["REQUEST_URI"]."?h=1'>Instructions</a>)";
	echo "&nbsp;-&nbsp;(<a href='".$_SERVER["REQUEST_URI"]."'>Reset</a>)</p>\n";
}

$highlight = " bgcolor=#BBAF9B";

?>

<form method=get action=<?php echo $_SERVER["REQUEST_URI"]?>>
<table border="1" cellspacing="0" cellpadding="5">
<tr>

  <td valign="middle" class=rowhead>Name:</td>
  <td<?php echo $_GET['n']?$highlight:""?>><input name="n" type="text" value="<?php echo htmlspecialchars($_GET['n'])?>" size=35></td>

  <td valign="middle" class=rowhead>Ratio:</td>
  <td<?php echo $_GET['r']?$highlight:""?>><select name="rt">
<?php
	$options = array("equal","above","below","between");
	for ($i = 0; $i < count($options); $i++){
	    echo "<option value=$i ".(($_GET['rt']=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
	?>
    </select>
    <input name="r" type="text" value="<?php echo htmlspecialchars($_GET['r'])?>" size="5" maxlength="4">
    <input name="r2" type="text" value="<?php echo htmlspecialchars($_GET['r2'])?>" size="5" maxlength="4"></td>

  <td valign="middle" class=rowhead>Member status:</td>
  <td<?php echo $_GET['st']?$highlight:""?>><select name="st">
<?php
	$options = array("(any)","confirmed","pending");
	for ($i = 0; $i < count($options); $i++){
	    echo "<option value=$i ".(($_GET['st']=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
    ?>
    </select></td></tr>
<tr><td valign="middle" class=rowhead>Email:</td>
  <td<?php echo $_GET['em']?$highlight:""?>><input name="em" type="text" value="<?php echo htmlspecialchars($_GET['em'])?>" size="35"></td>
  <td valign="middle" class=rowhead>IP:</td>
  <td<?php echo $_GET['ip']?$highlight:""?>><input name="ip" type="text" value="<?php echo htmlspecialchars($_GET['ip'])?>" maxlength="64"></td>

  <td valign="middle" class=rowhead>Account status:</td>
  <td<?php echo $_GET['as']?$highlight:""?>><select name="as">
<?php
    $options = array("(any)","enabled","disabled");
    for ($i = 0; $i < count($options); $i++){
      echo "<option value=$i ".(($_GET['as']=="$i")?"selected":"").">".$options[$i]."</option>\n";
    }
?>
    </select></td></tr>
<tr>
  <td valign="middle" class=rowhead>Comment:</td>
  <td<?php echo $_GET['co']?$highlight:""?>><input name="co" type="text" value="<?php echo htmlspecialchars($_GET['co'])?>" size="35"></td>
  <td valign="middle" class=rowhead>Mask:</td>
  <td<?php echo $_GET['ma']?$highlight:""?>><input name="ma" type="text" value="<?php echo htmlspecialchars($_GET['ma'])?>" maxlength="17"></td>
  <td valign="middle" class=rowhead>Class:</td>
  <td<?php echo ($_GET['c'] && $_GET['c'] != 1)?$highlight:""?>><select name="c"><option value='1'>(any)</option>
<?php
  $class = $_GET['c'];
  if (!is_valid_id($class))
  	$class = '';
  for ($i = 2;;++$i) {
		if ($c = get_user_class_name($i-2,false,true,true))
       	 print("<option value=" . $i . ($class && $class == $i? " selected" : "") . ">$c</option>\n");
	  else
	   	break;
	}
?>
    </select></td></tr>
<tr>

    <td valign="middle" class=rowhead>Joined:</td>

  <td<?php echo $_GET['d']?$highlight:""?>><select name="dt">
<?php
	$options = array("on","before","after","between");
	for ($i = 0; $i < count($options); $i++){
	  echo "<option value=$i ".(($_GET['dt']=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
?>
    </select>

    <input name="d" type="text" value="<?php echo htmlspecialchars($_GET['d'])?>" size="12" maxlength="10">

    <input name="d2" type="text" value="<?php echo htmlspecialchars($_GET['d2'])?>" size="12" maxlength="10"></td>


  <td valign="middle" class=rowhead>Uploaded:</td>

  <td<?php echo $_GET['ul']?$highlight:""?>><select name="ult" id="ult">
<?php
    $options = array("equal","above","below","between");
    for ($i = 0; $i < count($options); $i++){
  	  echo "<option value=$i ".(($_GET['ult']=="$i")?"selected":"").">".$options[$i]."</option>\n";
    }
?>
    </select>

    <input name="ul" type="text" id="ul" size="8" maxlength="7" value="<?php echo htmlspecialchars($_GET['ul'])?>">

    <input name="ul2" type="text" id="ul2" size="8" maxlength="7" value="<?php echo htmlspecialchars($_GET['ul2'])?>"></td>
  <td valign="middle" class="rowhead">Donor:</td>

  <td<?php echo $_GET['do']?$highlight:""?>><select name="do">
<?php
    $options = array("(any)","Yes","No");
	for ($i = 0; $i < count($options); $i++){
	  echo "<option value=$i ".(($_GET['do']=="$i")?"selected":"").">".$options[$i]."</option>\n";
    }
?>
	</select></td></tr>
<tr>

<td valign="middle" class=rowhead>Last seen:</td>

  <td <?php echo $_GET['ls']?$highlight:""?>><select name="lst">
<?php
  $options = array("on","before","after","between");
  for ($i = 0; $i < count($options); $i++){
    echo "<option value=$i ".(($_GET['lst']=="$i")?"selected":"").">".$options[$i]."</option>\n";
  }
?>
  </select>

  <input name="ls" type="text" value="<?php echo htmlspecialchars($_GET['ls'])?>" size="12" maxlength="10">

  <input name="ls2" type="text" value="<?php echo htmlspecialchars($_GET['ls2'])?>" size="12" maxlength="10"></td>
	  <td valign="middle" class=rowhead>Downloaded:</td>

  <td<?php echo $_GET['dl']?$highlight:""?>><select name="dlt" id="dlt">
<?php
	$options = array("equal","above","below","between");
	for ($i = 0; $i < count($options); $i++){
	  echo "<option value=$i ".(($_GET['dlt']=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
?>
    </select>

    <input name="dl" type="text" id="dl" size="8" maxlength="7" value="<?php echo htmlspecialchars($_GET['dl'])?>">

    <input name="dl2" type="text" id="dl2" size="8" maxlength="7" value="<?php echo htmlspecialchars($_GET['dl2'])?>"></td>

	<td valign="middle" class=rowhead>Warned:</td>

	<td<?php echo $_GET['w']?$highlight:""?>><select name="w">
<?php
  $options = array("(any)","Yes","No");
	for ($i = 0; $i < count($options); $i++){
		echo "<option value=$i ".(($_GET['w']=="$i")?"selected":"").">".$options[$i]."</option>\n";
  }
?>
	</select></td></tr>

<tr><td class="rowhead"></td><td></td>
  <td valign="middle" class=rowhead>Active only:</td>
	<td<?php echo $_GET['ac']?$highlight:""?>><input name="ac" type="checkbox" value="1" <?php echo ($_GET['ac'])?"checked":"" ?>></td>
  <td valign="middle" class=rowhead>Disabled IP: </td>
  <td<?php echo $_GET['dip']?$highlight:""?>><input name="dip" type="checkbox" value="1" <?php echo ($_GET['dip'])?"checked":"" ?>></td>
  </tr>
<tr><td colspan="6" align=center><input name="submit" type=submit class=btn></td></tr>
</table>
<br /><br />
</form>

<?php

// Validates date in the form [yy]yy-mm-dd;
// Returns date if valid, 0 otherwise.
function mkdate($date){
  if (strpos($date,'-'))
  	$a = explode('-', $date);
  elseif (strpos($date,'/'))
  	$a = explode('/', $date);
  else
  	return 0;
  for ($i=0;$i<3;$i++)
  	if (!is_numeric($a[$i]))
    	return 0;
    if (checkdate($a[1], $a[2], $a[0]))
    	return  date ("Y-m-d", mktime (0,0,0,$a[1],$a[2],$a[0]));
    else
			return 0;
}

// ratio as a string
function ratios($up,$down, $color = True)
{
	if ($down > 0)
	{
		$r = number_format($up / $down, 2);
    if ($color)
			$r = "<font color=".get_ratio_color($r).">$r</font>";
	}
	else
		if ($up > 0)
	  	$r = "Inf.";
	  else
	  	$r = "---";
	return $r;
}

// checks for the usual wildcards *, ? plus mySQL ones
function haswildcard($text){
	if (strpos($text,'*') === False && strpos($text,'?') === False
			&& strpos($text,'%') === False && strpos($text,'_') === False)
  	return False;
  else
  	return True;
}

///////////////////////////////////////////////////////////////////////////////
$q = '';
if (count($_GET) > 0 && !$_GET['h'])
{
	// name
  $names = explode(' ',trim($_GET['n']));
  if ($names[0] !== "")
  {
		foreach($names as $name)
		{
	  	if (substr($name,0,1) == '~')
	  	{
      	if ($name == '~') continue;
   	    $names_exc[] = substr($name,1);
      }
	    else
	    	$names_inc[] = $name;
	  }

    if (is_array($names_inc))
    {
	  	$where_is .= isset($where_is)?" AND (":"(";
	    foreach($names_inc as $name)
	    {
      	if (!haswildcard($name))
	        $name_is .= (isset($name_is)?" OR ":"")."u.username = ".sqlesc($name);
	      else
	      {
	        $name = str_replace(array('?','*'), array('_','%'), $name);
	        $name_is .= (isset($name_is)?" OR ":"")."u.username LIKE ".sqlesc($name);
	      }
	    }
      $where_is .= $name_is.")";
      unset($name_is);
	  }

    if (is_array($names_exc))
    {
	  	$where_is .= isset($where_is)?" AND NOT (":" NOT (";
	    foreach($names_exc as $name)
	    {
	    	if (!haswildcard($name))
	      	$name_is .= (isset($name_is)?" OR ":"")."u.username = ".sqlesc($name);
	      else
	      {
	      	$name = str_replace(array('?','*'), array('_','%'), $name);
	        $name_is .= (isset($name_is)?" OR ":"")."u.username LIKE ".sqlesc($name);
	      }
	    }
      $where_is .= $name_is.")";
	  }
	  $q .= ($q ? "&" : "") . "n=".rawurlencode(trim($_GET['n']));
  }

  // email
  $emaila = explode(' ', trim($_GET['em']));
  if ($emaila[0] !== "")
  {
  	$where_is .= isset($where_is)?" AND (":"(";
    foreach($emaila as $email)
    {
	  	if (strpos($email,'*') === False && strpos($email,'?') === False
	    		&& strpos($email,'%') === False)
	    {
      	if (validemail($email) !== 1)
      	{
	        stdmsg("Error", "Bad email.");
	        stdfoot();
	      	die();
	      }
	      $email_is .= (isset($email_is)?" OR ":"")."u.email =".sqlesc($email);
      }
      else
      {
	    	$sql_email = str_replace(array('?','*'), array('_','%'), $email);
	      $email_is .= (isset($email_is)?" OR ":"")."u.email LIKE ".sqlesc($sql_email);
	    }
    }
		$where_is .= $email_is.")";
    $q .= ($q ? "&" : "") . "em=".rawurlencode(trim($_GET['em']));
  }

  //class
  // NB: the c parameter is passed as two units above the real one
  $class = $_GET['c'] - 2;
	if (is_valid_id($class + 1))
	{
  	$where_is .= (isset($where_is)?" AND ":"")."u.class=$class";
    $q .= ($q ? "&" : "") . "c=".($class+2);
  }

  // IP
  $ip = trim($_GET['ip']);
  if ($ip)
  {
  	$regex = "/^(((1?\d{1,2})|(2[0-4]\d)|(25[0-5]))(\.\b|$)){4}$/";
  	if (!filter_var($ip, FILTER_VALIDATE_IP))
    {
    	stdmsg("Error", "Bad IP.");
    	stdfoot();
    	die();
    }

    $mask = trim($_GET['ma']);
    if ($mask == "" || $mask == "255.255.255.255")
    	$where_is .= (isset($where_is)?" AND ":"")."u.ip = '$ip'";
    else
    {
    	if (substr($mask,0,1) == "/")
    	{
      	$n = substr($mask, 1, strlen($mask) - 1);
        if (!is_numeric($n) or $n < 0 or $n > 32)
        {
        	stdmsg("Error", "Bad subnet mask.");
        	stdfoot();
          die();
        }
        else
	      	$mask = long2ip(pow(2,32) - pow(2,32-$n));
      }
      elseif (!preg_match($regex, $mask))
      {
				stdmsg("Error", "Bad subnet mask.");
				stdfoot();
	      die();
      }
      $where_is .= (isset($where_is)?" AND ":"")."INET_ATON(u.ip) & INET_ATON('$mask') = INET_ATON('$ip') & INET_ATON('$mask')";
      $q .= ($q ? "&" : "") . "ma=$mask";
    }
    $q .= ($q ? "&" : "") . "ip=$ip";
  }

  // ratio
  $ratio = trim($_GET['r']);
  if ($ratio)
  {
  	if ($ratio == '---')
  	{
    	$ratio2 = "";
      $where_is .= isset($where_is)?" AND ":"";
      $where_is .= " u.uploaded = 0 and u.downloaded = 0";
    }
    elseif (strtolower(substr($ratio,0,3)) == 'inf')
    {
    	$ratio2 = "";
      $where_is .= isset($where_is)?" AND ":"";
      $where_is .= " u.uploaded > 0 and u.downloaded = 0";
    }
    else
    {
    	if (!is_numeric($ratio) || $ratio < 0)
    	{
      	stdmsg("Error", "Bad ratio.");
      	stdfoot();
        die();
      }
      $where_is .= isset($where_is)?" AND ":"";
      $where_is .= " (u.uploaded/u.downloaded)";
      $ratiotype = $_GET['rt'];
      $q .= ($q ? "&" : "") . "rt=$ratiotype";
      if ($ratiotype == "3")
      {
      	$ratio2 = trim($_GET['r2']);
        if(!$ratio2)
        {
        	stdmsg("Error", "Two ratios needed for this type of search.");
        	stdfoot();
          die();
        }
        if (!is_numeric($ratio2) or $ratio2 < $ratio)
        {
        	stdmsg("Error", "Bad second ratio.");
        	stdfoot();
        	die();
        }
        $where_is .= " BETWEEN $ratio and $ratio2";
        $q .= ($q ? "&" : "") . "r2=$ratio2";
      }
      elseif ($ratiotype == "2")
      	$where_is .= " < $ratio";
      elseif ($ratiotype == "1")
      	$where_is .= " > $ratio";
      else
      	$where_is .= " BETWEEN ($ratio - 0.004) and ($ratio + 0.004)";
    }
    $q .= ($q ? "&" : "") . "r=$ratio";
  }

  // comment
  $comments = explode(' ',trim($_GET['co']));
  if ($comments[0] !== "")
  {
		foreach($comments as $comment)
		{
	    if (substr($comment,0,1) == '~')
	    {
      	if ($comment == '~') continue;
   	    $comments_exc[] = substr($comment,1);
      }
      else
	    	$comments_inc[] = $comment;
	  }

    if (is_array($comments_inc))
    {
	  	$where_is .= isset($where_is)?" AND (":"(";
	    foreach($comments_inc as $comment)
	    {
	    	if (!haswildcard($comment))
		    	$comment_is .= (isset($comment_is)?" OR ":"")."u.modcomment LIKE ".sqlesc("%".$comment."%");
        else
        {
	      	$comment = str_replace(array('?','*'), array('_','%'), $comment);
	        $comment_is .= (isset($comment_is)?" OR ":"")."u.modcomment LIKE ".sqlesc($comment);
        }
      }
      $where_is .= $comment_is.")";
      unset($comment_is);
    }

    if (is_array($comments_exc))
    {
	  	$where_is .= isset($where_is)?" AND NOT (":" NOT (";
	    foreach($comments_exc as $comment)
	    {
	    	if (!haswildcard($comment))
		    	$comment_is .= (isset($comment_is)?" OR ":"")."u.modcomment LIKE ".sqlesc("%".$comment."%");
        else
        {
	      	$comment = str_replace(array('?','*'), array('_','%'), $comment);
	        $comment_is .= (isset($comment_is)?" OR ":"")."u.modcomment LIKE ".sqlesc($comment);
	      }
      }
      $where_is .= $comment_is.")";
	  }
    $q .= ($q ? "&" : "") . "co=".rawurlencode(trim($_GET['co']));
  }

  $unit = 1073741824;		// 1GB

  // uploaded
  $ul = trim($_GET['ul']);
  if ($ul)
  {
  	if (!is_numeric($ul) || $ul < 0)
  	{
    	stdmsg("Error", "Bad uploaded amount.");
    	stdfoot();
      die();
    }
    $where_is .= isset($where_is)?" AND ":"";
    $where_is .= " u.uploaded ";
    $ultype = $_GET['ult'];
    $q .= ($q ? "&" : "") . "ult=$ultype";
    if ($ultype == "3")
    {
	    $ul2 = trim($_GET['ul2']);
    	if(!$ul2)
    	{
      	stdmsg("Error", "Two uploaded amounts needed for this type of search.");
      	stdfoot();
        die();
      }
      if (!is_numeric($ul2) or $ul2 < $ul)
      {
      	stdmsg("Error", "Bad second uploaded amount.");
      	stdfoot();
        die();
      }
      $where_is .= " BETWEEN ".$ul*$unit." and ".$ul2*$unit;
      $q .= ($q ? "&" : "") . "ul2=$ul2";
    }
    elseif ($ultype == "2")
    	$where_is .= " < ".$ul*$unit;
    elseif ($ultype == "1")
    	$where_is .= " >". $ul*$unit;
    else
    	$where_is .= " BETWEEN ".($ul - 0.004)*$unit." and ".($ul + 0.004)*$unit;
    $q .= ($q ? "&" : "") . "ul=$ul";
  }

  // downloaded
  $dl = trim($_GET['dl']);
  if ($dl)
  {
  	if (!is_numeric($dl) || $dl < 0)
  	{
    	stdmsg("Error", "Bad downloaded amount.");
    	stdfoot();
      die();
    }
    $where_is .= isset($where_is)?" AND ":"";
    $where_is .= " u.downloaded ";
    $dltype = $_GET['dlt'];
    $q .= ($q ? "&" : "") . "dlt=$dltype";
    if ($dltype == "3")
    {
    	$dl2 = trim($_GET['dl2']);
      if(!$dl2)
      {
      	stdmsg("Error", "Two downloaded amounts needed for this type of search.");
      	stdfoot();
        die();
      }
      if (!is_numeric($dl2) or $dl2 < $dl)
      {
      	stdmsg("Error", "Bad second downloaded amount.");
      	stdfoot();
        die();
      }
      $where_is .= " BETWEEN ".$dl*$unit." and ".$dl2*$unit;
      $q .= ($q ? "&" : "") . "dl2=$dl2";
    }
    elseif ($dltype == "2")
    	$where_is .= " < ".$dl*$unit;
    elseif ($dltype == "1")
     	$where_is .= " > ".$dl*$unit;
    else
     	$where_is .= " BETWEEN ".($dl - 0.004)*$unit." and ".($dl + 0.004)*$unit;
    $q .= ($q ? "&" : "") . "dl=$dl";
  }

  // date joined
  $date = trim($_GET['d']);
  if ($date)
  {
  	if (!$date = mkdate($date))
  	{
    	stdmsg("Error", "Invalid date.");
    	stdfoot();
      die();
    }
    $q .= ($q ? "&" : "") . "d=$date";
    $datetype = $_GET['dt'];
		$q .= ($q ? "&" : "") . "dt=$datetype";
    if ($datetype == "0")
    // For mySQL 4.1.1 or above use instead
    // $where_is .= (isset($where_is)?" AND ":"")."DATE(added) = DATE('$date')";
    $where_is .= (isset($where_is)?" AND ":"").
    		"(UNIX_TIMESTAMP(added) - UNIX_TIMESTAMP('$date')) BETWEEN 0 and 86400";
    else
    {
      $where_is .= (isset($where_is)?" AND ":"")."u.added ";
      if ($datetype == "3")
      {
        $date2 = mkdate(trim($_GET['d2']));
        if ($date2)
        {
          if (!$date = mkdate($date))
          {
            stdmsg("Error", "Invalid date.");
            stdfoot();
            die();
          }
          $q .= ($q ? "&" : "") . "d2=$date2";
          $where_is .= " BETWEEN '$date' and '$date2'";
        }
        else
        {
          stdmsg("Error", "Two dates needed for this type of search.");
          stdfoot();
          die();
        }
      }
      elseif ($datetype == "1")
        $where_is .= "< '$date'";
      elseif ($datetype == "2")
        $where_is .= "> '$date'";
    }
  }

	// date last seen
  $last = trim($_GET['ls']);
  if ($last)
  {
  	if (!$last = mkdate($last))
  	{
    	stdmsg("Error", "Invalid date.");
    	stdfoot();
      die();
    }
    $q .= ($q ? "&" : "") . "ls=$last";
    $lasttype = $_GET['lst'];
    $q .= ($q ? "&" : "") . "lst=$lasttype";
    if ($lasttype == "0")
    // For mySQL 4.1.1 or above use instead
    // $where_is .= (isset($where_is)?" AND ":"")."DATE(added) = DATE('$date')";
    	$where_is .= (isset($where_is)?" AND ":"").
      		"(UNIX_TIMESTAMP(last_access) - UNIX_TIMESTAMP('$last')) BETWEEN 0 and 86400";
    else
    {
    	$where_is .= (isset($where_is)?" AND ":"")."u.last_access ";
      if ($lasttype == "3")
      {
      	$last2 = mkdate(trim($_GET['ls2']));
        if ($last2)
        {
        	$where_is .= " BETWEEN '$last' and '$last2'";
	        $q .= ($q ? "&" : "") . "ls2=$last2";
        }
        else
        {
        	stdmsg("Error", "The second date is not valid.");
        	stdfoot();
        	die();
        }
      }
      elseif ($lasttype == "1")
    		$where_is .= "< '$last'";
      elseif ($lasttype == "2")
      	$where_is .= "> '$last'";
    }
  }

  // status
  $status = $_GET['st'];
  if ($status)
  {
  	$where_is .= ((isset($where_is))?" AND ":"");
    if ($status == "1")
    	$where_is .= "u.status = 'confirmed'";
    else
    	$where_is .= "u.status = 'pending'";
    $q .= ($q ? "&" : "") . "st=$status";
  }

  // account status
  $accountstatus = $_GET['as'];
  if ($accountstatus)
  {
  	$where_is .= (isset($where_is))?" AND ":"";
    if ($accountstatus == "1")
    	$where_is .= " u.enabled = 'yes'";
    else
    	$where_is .= " u.enabled = 'no'";
    $q .= ($q ? "&" : "") . "as=$accountstatus";
  }

  //donor
	$donor = $_GET['do'];
  if ($donor)
  {
		$where_is .= (isset($where_is))?" AND ":"";
    if ($donor == 1)
    	$where_is .= " u.donor = 'yes'";
    else
    	$where_is .= " u.donor = 'no'";
    $q .= ($q ? "&" : "") . "do=$donor";
  }

  //warned
	$warned = $_GET['w'];
  if ($warned)
  {
		$where_is .= (isset($where_is))?" AND ":"";
    if ($warned == 1)
    	$where_is .= " u.warned = 'yes'";
    else
    	$where_is .= " u.warned = 'no'";
    $q .= ($q ? "&" : "") . "w=$warned";
  }

  // disabled IP
  $disabled = $_GET['dip'];
  if ($disabled)
  {
  	$distinct = "DISTINCT ";
    $join_is .= " LEFT JOIN users AS u2 ON u.ip = u2.ip";
		$where_is .= ((isset($where_is))?" AND ":"")."u2.enabled = 'no'";
    $q .= ($q ? "&" : "") . "dip=$disabled";
  }

  // active
  $active = $_GET['ac'];
  if ($active == "1")
  {
  	$distinct = "DISTINCT ";
    $join_is .= " LEFT JOIN peers AS p ON u.id = p.userid";
    $q .= ($q ? "&" : "") . "ac=$active";
  }


  $from_is = "users AS u".$join_is;
  $distinct = isset($distinct)?$distinct:"";

  $queryc = "SELECT COUNT(".$distinct."u.id) FROM ".$from_is.
  		(($where_is == "")?"":" WHERE $where_is ");

  $querypm = "FROM ".$from_is.(($where_is == "")?" ":" WHERE $where_is ");

  $select_is = "u.id, u.username, u.email, u.status, u.added, u.last_access, u.ip,
  	u.class, u.uploaded, u.downloaded, u.donor, u.modcomment, u.enabled, u.warned";

  $query = "SELECT ".$distinct." ".$select_is." ".$querypm;

  $res = sql_query($queryc) or sqlerr();
  $arr = mysql_fetch_row($res);
  $count = $arr[0];

  $q = isset($q)?($q."&"):"";

  $perpage = 30;

  list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["REQUEST_URI"]."?".$q);

  $query .= $limit;

  $res = sql_query($query) or sqlerr();

  if (mysql_num_rows($res) == 0)
  	stdmsg("Warning","No user was found.");
  else
  {
  	if ($count > $perpage)
  		echo $pagertop;
    echo "<table border=1 cellspacing=0 cellpadding=5>\n";
    echo "<tr><td class=colhead align=left>Name</td>
    		<td class=colhead align=left>Ratio</td>
        <td class=colhead align=left>IP</td>
        <td class=colhead align=left>Email</td>".
        "<td class=colhead align=left>Joined:</td>".
        "<td class=colhead align=left>Last seen:</td>".
        "<td class=colhead align=left>Status</td>".
        "<td class=colhead align=left>Enabled</td>".
        "<td class=colhead>pR</td>".
        "<td class=colhead>pUL</td>".
        "<td class=colhead>pDL</td>".
        "<td class=colhead>History</td></tr>";
    while ($user = mysql_fetch_array($res))
    {
    	if ($user['added'] == '0000-00-00 00:00:00' || $user['added'] == null)
      	$user['added'] = '---';
      if ($user['last_access'] == '0000-00-00 00:00:00' || $user['last_access'] == null)
      	$user['last_access'] = '---';

      if ($user['ip']) {
          $ipstr = $user['ip'];
          if (filter_var($user['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
              $nip = ip2long($user['ip']);
              $auxres = sql_query("SELECT COUNT(*) FROM bans WHERE $nip >= first AND $nip <= last") or sqlerr(__FILE__, __LINE__);
              $array = mysql_fetch_row($auxres);
              if ($array[0] > 0) {
                  $ipstr = "<a href='testip.php?ip=" . $user['ip'] . "'><font color='#FF0000'><b>" . $user['ip'] . "</b></font></a>";
              }
          }
      } else {
          $ipstr = "---";
      }
      $auxres = sql_query("SELECT SUM(uploaded) AS pul, SUM(downloaded) AS pdl FROM peers WHERE userid = " . $user['id']) or sqlerr(__FILE__, __LINE__);
      $array = mysql_fetch_array($auxres);

      $pul = $array['pul'];
      $pdl = $array['pdl'];

      $auxres = sql_query("SELECT COUNT(DISTINCT p.id) FROM posts AS p LEFT JOIN topics as t ON p.topicid = t.id
      	LEFT JOIN forums AS f ON t.forumid = f.id WHERE p.userid = " . $user['id'] . " AND f.minclassread <= " .
      	$CURUSER['class']) or sqlerr(__FILE__, __LINE__);

      $n = mysql_fetch_row($auxres);
      $n_posts = $n[0];

      $auxres = sql_query("SELECT COUNT(id) FROM comments WHERE user = ".$user['id']) or sqlerr(__FILE__, __LINE__);
			// Use LEFT JOIN to exclude orphan comments
      // $auxres = sql_query("SELECT COUNT(c.id) FROM comments AS c LEFT JOIN torrents as t ON c.torrent = t.id WHERE c.user = '".$user['id']."'") or sqlerr(__FILE__, __LINE__);
      $n = mysql_fetch_row($auxres);
      $n_comments = $n[0];

    	echo "<tr><td>" .
      		get_username($user['id']) . "</td>" .
          "<td>" . ratios($user['uploaded'], $user['downloaded']) . "</td>
          <td>" . $ipstr . "</td><td>" . $user['email'] . "</td>
          <td><div align=center>" . $user['added'] . "</div></td>
          <td><div align=center>" . $user['last_access'] . "</div></td>
          <td><div align=center>" . $user['status'] . "</div></td>
          <td><div align=center>" . $user['enabled']."</div></td>
          <td><div align=center>" . ratios($pul,$pdl) . "</div></td>" .
          "<td><div align=right>" . mksize($pul) . "</div></td>
          <td><div align=right>" . mksize($pdl) . "</div></td>
          <td><div align=center>".($n_posts?"<a href=userhistory.php?action=viewposts&id=".$user['id'].">$n_posts</a>":$n_posts).
          "|".($n_comments?"<a href=userhistory.php?action=viewcomments&id=".$user['id'].">$n_comments</a>":$n_comments).
          "</div></td></tr>\n";
    }
    echo "</table>";
    if ($count > $perpage)
    	echo "$pagerbottom";

	/*
    <br /><br />
    <form method=post action=/sendmessage.php>
      <table border="1" cellpadding="5" cellspacing="0">
        <tr>
          <td>
            <div align="center">
              <input name="pmees" type="hidden" value="<?php echo $querypm?>" size=10>
              <input name="PM" type="submit" value="PM" class=btn>
              <input name="n_pms" type="hidden" value="<?php echo $count?>" size=10>
            </div></td>
        </tr>
      </table>
    </form>
    */

  }
}

print("<p>$pagemenu<br />$browsemenu</p>");
stdfoot();
die;

?>
