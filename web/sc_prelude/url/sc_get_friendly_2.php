<?PHP

function sc_get_friendly_2($n=0)
{
	$Langs 			= sc_get_langs();

	$request_uri = (isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : null);
	$request_uri = @rawurldecode($request_uri);
	$request_uri = @explode("?",$request_uri);
	$request_uri = @array_shift($request_uri);


	$request_uri = explode("/",$request_uri);
	$request_uri = array_filter($request_uri,function($item){return $item != "";});
	$request_uri = array_values($request_uri);

	$first_stack = strtolower($request_uri[0]);
	if (!empty($Langs[$first_stack]))
		array_shift($request_uri);


	return (isset($request_uri[$n]) ? $request_uri[$n] : null);

}
