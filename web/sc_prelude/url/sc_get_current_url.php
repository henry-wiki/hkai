<?PHP



function sc_get_current_url($abspath)
{
	$isSecure = (!@empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

	$protocol = $isSecure ? "https://" : "http://";
	
	$url = $protocol . $_SERVER["HTTP_HOST"] . $abspath;
	
	return $url;
}

