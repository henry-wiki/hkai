<?PHP



function remove_suffix($haystack, $needle) {
    
	$needle_length = strlen($needle);
    if (substr($haystack, -$needle_length) === $needle) {
        return substr($haystack, 0, -$needle_length);
    }
    return $haystack;
	
}

