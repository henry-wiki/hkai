<?PHP

function sc_replace_tags_v2($tplContent,$replacements=array(),$keepNonMatched=false)
{
	// == you can only replace by the key which in the tplContent

	$replace_keys = array();
	$replace_vals = array();

	foreach($replacements as $key => $val)
	{

		$replace_keys[] = "<!--[[".$key."]]-->";
		$replace_keys[] = "[[".$key."]]";

		$replace_vals[] = $val;
		$replace_vals[] = $val;
	}

	$content = str_replace($replace_keys,$replace_vals,$tplContent);

	if(!@$keepNonMatched)
	{
		$content = preg_replace("/<\!--\[\[(.*?)\]\]-->/sui","",$content);
		$content = preg_replace("/\[\[(.*?)\]\]/sui","",$content);
	}


	return $content;

}
