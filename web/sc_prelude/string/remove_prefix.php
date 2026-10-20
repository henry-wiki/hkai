<?PHP



function remove_prefix($text, $prefix) {
    if(0 === strpos($text, $prefix))
        $text = substr($text, strlen($prefix)).'';
    return $text;
}

