<?PHP

function getPath($custom_uri=null)
{
	if(!@$custom_uri)
	$custom_uri = $_SERVER["REQUEST_URI"];

	$parse_url = parse_url($custom_uri);

	return (isset($parse_url["path"]) ? $parse_url["path"] : null);

}
