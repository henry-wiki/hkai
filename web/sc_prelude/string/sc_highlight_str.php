<?PHP




function sc_highlight_str($haystack, $needle, $highlightColorValue) {
     // return $haystack if there is no highlight color or strings given, nothing to do.
    /*
	if (strlen($highlightColorValue) < 1 || strlen($haystack) < 1 || strlen($needle) < 1) {
        return $haystack;
    }
    preg_match_all("/$needle+/i", $haystack, $matches);
    if (is_array($matches[0]) && count($matches[0]) >= 1) {
        foreach ($matches[0] as $match) {
            $haystack = str_replace($match, '<span style="background-color:'.$highlightColorValue.';">'.$match.'</span>', $haystack);
        }
    }
	*/
	
	$haystack = preg_replace('#'. preg_quote($needle) .'#i', '<span style="background-color:'.$highlightColorValue.';">\\0</span>', $haystack);
	//return $str;
  
    return $haystack;
}
