<?PHP

function sc_build_query($params)
{
	
	$parts = array();
	
	foreach ($params as $key => $value) {
        $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    return implode('&', $parts);
	
}
