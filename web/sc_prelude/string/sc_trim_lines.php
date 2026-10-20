<?PHP




function sc_trim_lines($str) 
{
	return preg_replace('/^\h+|\h+$/m', '', $str);
}

