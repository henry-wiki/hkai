<?PHP


function IsSamePath($str)
{
	$check_uri_parsed = parse_url($str);
				
		$request_uri = $_SERVER["REQUEST_URI"];
		$request_uri_parsed = parse_url($request_uri);
		
		$check_uri_parsed_path 		= rtrim($check_uri_parsed["path"],'/');
		$request_uri_parsed_path 	= rtrim($request_uri_parsed["path"],'/');
		
		$check_uri_parsed_path = strtolower($check_uri_parsed_path);
		$request_uri_parsed_path = strtolower($request_uri_parsed_path);
		
		if($check_uri_parsed_path == $request_uri_parsed_path)
			return true;
		
		
		
		return false;
}

