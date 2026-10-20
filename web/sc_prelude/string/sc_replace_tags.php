<?PHP

function sc_replace_tags($tplContent,$replacements=array(),$splitMode=0,$urlEncoded=false)
{
	// == you can only replace by the key which in the tplContent
	switch($splitMode)
	{

		case 1 :
			$splitIndex1 = 2;
			$splitIndex2 = -2;

			$wrapperOpen = "\[\[";
			$wrapperClose = "\]\]";
			if($urlEncoded)
			{
				$wrapperOpen = urlencode("[[");
				$wrapperClose = urlencode("]]");

				$splitIndex1 = strlen($wrapperOpen);
				$splitIndex2 = strlen($wrapperClose) * -1;
			}

			$regex = "(".$wrapperOpen."[0-9a-zA-A\-\_\:]+".$wrapperClose.")";

			$splits = preg_split("/".$regex."/sui",$tplContent,-1,PREG_SPLIT_DELIM_CAPTURE);

		break;
		default :
			$splitIndex1 = 6;
			$splitIndex2 = -5;
			$splits = preg_split("/(\<\!\-\-\[\[[0-9a-zA-A\-\_\:]+\]\]\-\-\>)/sui",$tplContent,-1,PREG_SPLIT_DELIM_CAPTURE);
	}



	// <!--[[ = 6 chars
	// ]]--> = 5 chars

	foreach($splits as $index => &$split)
	{
		// == $index & 1 = $split = what is found in regex, such as <!--[[KEY]]-->
		if($index & 1)
		{
			$testkey = substr($split,$splitIndex1,$splitIndex2);
			if(array_key_exists($testkey,$replacements))
			{
				$split = (isset($replacements[$testkey]) ? $replacements[$testkey] : null);
			}else
			{
				$split = "";
			}
		}
	}unset($split);

	$splits = implode("",$splits);

	return $splits;

}
