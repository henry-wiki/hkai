<?PHP

function sc_get_usec($length=3)
{
	list($usec, $sec) = explode(" ", microtime());
	$usec = str_pad(intval($usec*1000),3,"0",STR_PAD_LEFT);

	return $usec;
}
