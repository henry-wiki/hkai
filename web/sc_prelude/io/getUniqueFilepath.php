<?PHP



function getUniqueFilepath($filepath,$try=1000,&$unique_key="")
	{
		$try = intval($try) < 1 ? 1000 : intval($try);
		
		
		if(!@file_exists($filepath))
			return $filepath;
		else
		{
			$pathinfo = pathinfo($filepath);
			for($i=1;$i<=$try;$i++)
			{
				$new_filepath = $pathinfo["dirname"]."/".$pathinfo["filename"]."_".$i.".".$pathinfo["extension"];
				
				if(!@file_exists($new_filepath))
				{
					$unique_key = $i;
					
					return $new_filepath;
					break;
				} 
			}
			
			list($usec, $sec) = explode(" ", microtime());
			
			$new_filepath = $pathinfo["dirname"]."/".$pathinfo["filename"]."_".$usec.".".$pathinfo["extension"];
				
			if(!@file_exists($new_filepath))
			{
				$unique_key = $usec;
				
				return $new_filepath;
			} 
			
			// == very unlucky, the filepath still existing, maybe there is a billion files in same dir.
			$unique_key = false;
		}
		
		return $filepath;
		
	}



