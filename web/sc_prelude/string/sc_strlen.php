<?PHP


function sc_strlen($str)
{
	$str = str_replace( "\r\n","\n", $str );
	
	return mb_strlen($str);
}
