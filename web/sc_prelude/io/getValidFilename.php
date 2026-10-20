<?PHP



function getValidFilename($filename,$pattern="/[^a-zA-Z0-9]+/",$replacement="-")
	{
		$pathinfo = pathinfo($filename);
		$filename = $pathinfo["filename"];
		$filename = preg_replace("/_[\d]+$/","",$filename);
		
		$filename = preg_replace($pattern,$replacement,$filename);
		$filename = trim($filename,'-');
		
		if(@empty($filename))
		{
			list($usec, $sec) = explode(" ", microtime());
			
			$mf = $usec + $sec;
			$mf = explode(".",$mf);
			$mf = array_pop($mf);
			
			$filename = date("Ymd-His-".$mf."");
		}
		
		$filename = $filename.".".$pathinfo["extension"];
		
		return $filename;
		
	}



