<?PHP

function sc_get_friendly($n=0)
{
	$request_uri = (isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : null);
	$request_uri = @rawurldecode($request_uri);
	$request_uri = @explode("?",$request_uri);
	$request_uri = @array_shift($request_uri);

	$request_uri = explode("/",$request_uri);
	$request_uri = array_filter($request_uri,function($item){return $item != "";});
	$request_uri = array_values($request_uri);

	// 判定：索引超出段數（冇嗰段）→ 回空字串（唔讀唔存在嘅索引）
	if (!isset($request_uri[$n])) {
		return "";
	}

	return $request_uri[$n];

}
