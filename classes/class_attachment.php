<?php
class ATTACHMENT{
	var $userid;
	var $class;
	var $countlimit;
	var $countsofar=0;
	var $sizelimit;
	var $allowedext = array();

	function __construct($userid) {
		$this->userid = $userid;
		$this->set_class();
		$this->set_count_so_far();
		$this->set_count_limit();
		$this->set_size_limit();
		$this->set_allowed_ext();
	}

	function enable_attachment()
	{
		global $enableattach_attachment;
		if ($enableattach_attachment == 'yes')
			return true;
		else return false;
	}

	function set_class()
	{
		$userid = $this->userid;
		$row = get_user_row($userid);
		$this->class = $row['class'];
	}

	function set_count_so_far()
	{
		$userid = $this->userid;
		$now = date("Y-m-d H:i:s", TIMENOW-86400);
		$countsofar = get_row_count("attachments", "WHERE userid=".sqlesc($userid)." AND added > ".sqlesc($now));
		$this->countsofar = $countsofar;
	}

	function get_count_so_far()
	{
		return $this->countsofar;
	}

	function get_count_limit_class($class)
	{
		global $classone_attachment, $classtwo_attachment, $classthree_attachment, $classfour_attachment,$countone_attachment, $counttwo_attachment, $countthree_attachment, $countfour_attachment;
		if ($class >= $classfour_attachment && $countfour_attachment)
			return $countfour_attachment;
		elseif ($class >= $classthree_attachment && $countthree_attachment)
			return $countthree_attachment;
		elseif ($class >= $classtwo_attachment && $counttwo_attachment)
			return $counttwo_attachment;
		elseif ($class >= $classone_attachment && $countone_attachment)
			return $countone_attachment;
	}

	function set_count_limit()
	{
		$class = $this->class;
		$countlimit = $this->get_count_limit_class($class);
		$this->countlimit = $countlimit;
	}

	function get_count_limit()
	{
		return $this->countlimit;
	}

	function get_count_left()
	{
		$left = $this->countlimit - $this->countsofar;
		return $left;
	}

	function get_size_limit_class($class)
	{
		global $classone_attachment, $classtwo_attachment, $classthree_attachment, $classfour_attachment,$sizeone_attachment, $sizetwo_attachment, $sizethree_attachment, $sizefour_attachment;
		if ($class >= $classfour_attachment && $sizefour_attachment)
			return $sizefour_attachment;
		elseif ($class >= $classthree_attachment && $sizethree_attachment)
			return $sizethree_attachment;
		elseif ($class >= $classtwo_attachment && $sizetwo_attachment)
			return $sizetwo_attachment;
		elseif ($class >= $classone_attachment && $sizeone_attachment)
			return $sizeone_attachment;
	}

	function set_size_limit()
	{
		$class = $this->class;
		$sizelimit = $this->get_size_limit_class($class);
		$this->sizelimit = $sizelimit;
	}

	function get_size_limit_kb()
	{
		return $this->sizelimit;
	}

	function get_size_limit_byte()
	{
		return $this->sizelimit * 1024;
	}

	function get_allowed_ext_class($class)
	{
		global $classone_attachment, $classtwo_attachment, $classthree_attachment, $classfour_attachment,$extone_attachment, $exttwo_attachment, $extthree_attachment, $extfour_attachment;
		$allowedext = array();
		if ($class >= $classone_attachment){
			$temprow = $this->extract_allowed_ext($extone_attachment);
			if (count($temprow)){
				foreach ($temprow as $temp){
					$allowedext[] = $temp;
				}
			}
			if ($class >= $classtwo_attachment){
				$temprow = $this->extract_allowed_ext($exttwo_attachment);
				if (count($temprow)){
					foreach ($temprow as $temp){
						$allowedext[] = $temp;
					}
				}
				if ($class >= $classthree_attachment){
					$temprow = $this->extract_allowed_ext($extthree_attachment);
					if (count($temprow)){
						foreach ($temprow as $temp){
							$allowedext[] = $temp;
						}
					}
					if ($class >= $classfour_attachment){
						$temprow = $this->extract_allowed_ext($extfour_attachment);
						if (count($temprow)){
							foreach ($temprow as $temp){
								$allowedext[] = $temp;
							}
						}
					}
				}
			}
		}
		return $allowedext;
	}

	function set_allowed_ext()
	{
		$class = $this->class;
		$allowedext = $this->get_allowed_ext_class($class);
		$this->allowedext = $allowedext;
	}

	function get_allowed_ext()
	{
		return $this->allowedext;
	}

	function extract_allowed_ext($string)
	{
		$string = rtrim(trim($string), ",");
		$exts = explode(",", $string);
		$extrow = array();
		foreach ($exts as $ext){
			$extrow[] = trim($ext);
		}
		return $extrow;
	}

	function is_gif_ani($filename) {
    		if(!($fh = @fopen($filename, 'rb')))
        		return false;
    		$count = 0;
	//an animated gif contains multiple "frames", with each frame having a
	//header made up of:
	// * a static 4-byte sequence (\x00\x21\xF9\x04)
	// * 4 variable bytes
	// * a static 2-byte sequence (\x00\x2C)

	// We read through the file til we reach the end of the file, or we've found
	// at least 2 frame headers
    		while(!feof($fh) && $count < 2){
        		$chunk = fread($fh, 1024 * 100); //read 100kb at a time
        		$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
		}
    		return $count > 1;
	}
}
?>
