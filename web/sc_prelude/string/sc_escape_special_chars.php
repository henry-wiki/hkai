<?PHP

function sc_escape_special_chars($str)
{
	$search = array("'", '"', '<', '>');
    $replace = array('&#039;', '&quot;', '&lt;', '&gt;');
    return str_replace($search, $replace, $str);
}
