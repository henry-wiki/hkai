<?PHP

function sc_parse_str($string,&$result)
{
	if($string === '')
		return false;

	$result = array();
	// find the pairs "name=value"
	$pairs = explode('&', $string);
	$params = array();

	foreach ($pairs as $pair) {
		// use the original parse_str() on each element
		parse_str($pair, $params);
		$k = key($params);
		if(!isset($result[$k])) {
			$result += $params;
		} else {
			$result[$k] = sc_parse_str_array_merge_recursive_distinct($result[$k], $params[$k]);
		}
	}
	return true;

}
