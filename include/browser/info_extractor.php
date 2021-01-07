<?php

class info_extractor{
	function __construct(){}
	
	/** truncate a given string
   * @method truncate
   * @param string src(source string), string s_str(starting needle), string e_str(ending needle, "" for open end), integer e_offset(optional where to start finding the $e_str)
   * @return string trucated string
   */
	function truncate($src, $s_str, $e_str = "", $e_offset = 0)
	{
		$ret = "";
		$e_offset = strlen($s_str);
		
		$ret = strstr($src, $s_str);
		if($ret == false)
		return "";
		
		if($e_str != "")
		{
			$endpos = strpos ($ret , $e_str, $e_offset);
			if($endpos == false)
				return "";
		}
		
		return substr($ret, strlen($s_str), $endpos - strlen($s_str));
	}

	/** find a certain pattern in a given string
   * @method find_pattern
   * @param string src(source string), string regex(regular expression), boolean multiple(if pattern has multiple occurance), array string res_where_array(where the res should be in regex, order of res_where_array and res_array should be the same, for example: res_array could be "array(array('Name' => '', 'Cloudsize' => '', 'Link' => ''))", then the first element in res_where_array could be, say, "3", which corrsponds to 'Name'), array string res_array(one or multi-dimensional array for the extraced info)
   * @return boolean found_pattern
   */
	function find_pattern($src, $regex, $multiple, $res_where_array)
	{
		$res_array = array();
		if($multiple == true)
		{
			if(!preg_match_all($regex,$src,$info_block,PREG_SET_ORDER))
			return false;
			else
			{
				$counter_infoblock = 0;
				foreach($info_block as $info)
				{
					$counter_reswhere = 0;
					foreach ($res_where_array as $res_where_array_each)
					{
						$res_array[$counter_infoblock][$counter_reswhere] = $info[$res_where_array_each];
						$counter_reswhere++;
					}
					$counter_infoblock++;
				}
				return $res_array;
			}
		}
		else
		{
			if(!preg_match($regex,$src,$info))
			return false;
			else
			{
				$counter = 0;
				foreach ($res_where_array as $res_where_array_each)
				{
					$res_array[$counter] = $info[$res_where_array_each];
					$counter++;
				}
				return $res_array;
			}
		}
	}

	/** remove a given pattern from a given string
   * @method truncate
   * @param string src(source string), string $regex_s(starting needle), string $regex_e(ending needle), integer max(set it to 1 if you are sure the pattern only occurs once, otherwise, it indicates the maximum possible occurance in case of dead loop), boolean all(if remove all or just the pattern)
   * @return string processed string
   */
	function remove($src, $regex_s, $regex_e, $max = 100, $all = false)
	{
		$ret = "";
		$ret = preg_replace("/" . $regex_s . "((\s|.)+?)" . $regex_e . "/i", ($all == false ? "\\1" : ""), $src, $max);
		return $ret;
	}
	
	/** trim a given array of strings types from a given string
   * @method trim_str
   * @param string src(source string), string array regex_trim_array(specifies strings to be trimmed), integersafe_counter(maximum possible occurance of string to be trimmed)
   * @return string processed string
   */
	function trim_str($src, $regex_trim_array, $safe_counter =10)
	{
		$ret = "";

		while($safe_counter>0)
		{
			$safe_counter--;
			$break_flag = true;
			foreach($regex_trim_array as $regex_trim_array_each)
			{
				$ret = preg_replace("/^((" . $regex_trim_array_each . ")*)((\s|.)+?)((" . $regex_trim_array_each . ")*)$/i","\\3", trim($src), 1);
				if($ret != $src)
					$break_flag = false;
				$src = $ret;
			}
			if($break_flag)
				break;
			continue;
		}
		return $ret;
	}
}
?>
