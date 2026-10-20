<?PHP

function sc_compare_url($url,$compareToUrl)
{
	// == compare only for the absolute path /xxxxx/xxxxx
	// == compare query string



	if($url == $compareToUrl)
		return true;

	$parse_url 			= parse_url($url);
	$parse_compareToUrl = parse_url($compareToUrl);

	$parse_url_path	 			= (isset($parse_url["path"]) ? $parse_url["path"] : null);
	$parse_compareToUrl_path 	= (isset($parse_compareToUrl["path"]) ? $parse_compareToUrl["path"] : null);

	if(@preg_match("/\/index\.php$/sui",$parse_url_path))
		$parse_url_path = substr($parse_url_path,0,strlen($parse_url_path) - strlen("index.php"));
	if(@preg_match("/\/index\.php$/sui",$parse_compareToUrl_path))
		$parse_compareToUrl_path = substr($parse_compareToUrl_path,0,strlen($parse_compareToUrl_path) - strlen("index.php"));

	if(!@preg_match("/\/$/sui",$parse_url_path))
		$parse_url_path = $parse_url_path . "/";
	if(!@preg_match("/\/$/sui",$parse_compareToUrl_path))
		$parse_compareToUrl_path = $parse_compareToUrl_path . "/";


	if($parse_url_path === $parse_compareToUrl_path)
		return 1;


	if($parse_url_path !== $parse_compareToUrl_path)
	{
		if(substr($parse_compareToUrl_path, 0, strlen($parse_url_path)) === $parse_url_path)
			return 100;
		else
			return 0;
	}else
	{
		// == weak compare qs
		@parse_str($parse_url["query"],$qs_parse_url_path);
		@parse_str($parse_compareToUrl["query"],$qs_parse_compareToUrl_path);

		if(!@count($qs_parse_url_path))
			return 101;


		// === weak compare : if all key & value in $qs_parse_url_path, can found in $qs_parse_compareToUrl_path, then return true
		foreach($qs_parse_url_path as $key => $value)
		{
			if($value === $qs_parse_compareToUrl_path[$key])
			{
				unset($qs_parse_url_path[$key]);
			}
		}

		if(!@count($qs_parse_url_path))
			return 102;
		else
		{
			// == contain key & value which not match in $qs_parse_compareToUrl_path
			return 0;
		}
	}

	return 0;
}


// == sample ======================================================================================

// $results = array();
// $results[] = sc_compare_url("/aaa","/aaa");				//true	
// $results[] = sc_compare_url("/aaa","/bbb");				//false	
// $results[] = sc_compare_url("/aaa","/aaa/index.php");		//true	
// $results[] = sc_compare_url("/aaa","/aaa/");				//true	
// $results[] = sc_compare_url("/aaa","/aaa/bbb");			//true	
// $results[] = sc_compare_url("/aaa/page_1.php","/aaa/page_2.php");			//false	
// $results[] = sc_compare_url("/aaa/page_1.php","/aaa/index.php");			//false	
// $results[] = sc_compare_url("/aaa/?arga=1","/aaa/?arga=1&argb=2");		//true	
// $results[] = sc_compare_url("/aaa/?arga=1&argb=2&argc=3","/aaa/?argc=3&arga=1&argb=2&argd=4#");		//true
