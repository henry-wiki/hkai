<?PHP


	function sc_is_match_slug($slug,$full_match=true)
	{
		$parsed_url = parse_url($_SERVER["REQUEST_URI"]);
		$path = @trim($parsed_url["path"],"/");
		$slug = @trim($slug,"/");
		
		$path = strtolower($path);
		$slug = strtolower($slug);
		
		if($full_match)
		{
			return $path == $slug;
		}
		else 
		{
			return strpos($path,$slug) === 0;
		}
		
	}
	