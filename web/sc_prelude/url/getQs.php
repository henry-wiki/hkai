<?PHP

function getQs($newKeyValues=array(),$removeKeys=array(),$withPath=false,$custom_uri=null)
{
	if(!@$custom_uri)
	$custom_uri = $_SERVER["REQUEST_URI"];

	$parse_url = parse_url($custom_uri);

	if (!empty($parse_url["query"]))
		parse_str($parse_url["query"],$parse_url["query"]);
	else
		$parse_url["query"] = array();

	if(@$removeKeys === true)
		$parse_url["query"] = array();

	if(@count($newKeyValues))
	{
		$parse_url["query"] = array_replace_recursive($parse_url["query"],$newKeyValues);
	}
	if(@is_array($removeKeys) && @count($removeKeys))
	{
		foreach($removeKeys as $removeKey)
		{
			if(@isset($parse_url["query"][$removeKey]))
				unset($parse_url["query"][$removeKey]);
		}
	}

	if($withPath)
	{
		$scheme = @strtolower($parse_url["scheme"]);
		if($scheme === "http" || $scheme === "https")
		{
			$path = $scheme."://".$parse_url["host"]."".$parse_url["path"];
		}
		else {
			$path = $parse_url["path"];
		}

		$newpath = $path."?".http_build_query($parse_url["query"],'','&');
		$newpath = rtrim($newpath,"?");

		return $newpath;
	}else
		return http_build_query($parse_url["query"],'','&');
}
