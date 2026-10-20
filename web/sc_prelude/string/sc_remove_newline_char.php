<?PHP




function sc_remove_newline_char($str) 
{
	return preg_replace('/\\r\\n|\\r|\\n/sui', '', $str);
}

