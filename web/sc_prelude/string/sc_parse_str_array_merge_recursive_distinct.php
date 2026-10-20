<?PHP

// better recursive array merge function listed on the array_merge_recursive PHP page in the comments
function sc_parse_str_array_merge_recursive_distinct (array $array1, array $array2) {
	$merged = $array1;
	foreach ($array2 as $key => &$value) {
		if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
			$merged[$key] = sc_parse_str_array_merge_recursive_distinct($merged[$key], $value);
		} else {
			$merged[$key] = $value;
		}
	}
	return $merged;
}
