<?PHP




function sc_str_to_plain($str,$start=0,$strlen=0) 
{
	$str 			= @strip_tags($str);
	$str 			= @sc_remove_newline_char($str);
	
	if($strlen < 1)
	{
		
	}
	else 
	{
		$str = mb_strimwidth($str, $start, $strlen, "...");
		
	}
	return $str;
}

