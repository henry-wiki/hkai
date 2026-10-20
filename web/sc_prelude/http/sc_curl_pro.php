<?PHP

function sc_curl_pro($url,$postdata=array(),$is_post=false,&$ref=array())
{
	$ch = curl_init();

	if(@isset($ref["HTTP_USER_AGENT"]))
		$browser = @($ref["HTTP_USER_AGENT"]);
	else
		$browser = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	
	if(!@is_array($ref))
		$ref = array();
	
	
	if(@isset($ref["WithHeader"]))
		$WithHeader = @intval($ref["WithHeader"]);
	else
		$WithHeader = 1;
	
	$POSTFIELDS_in_STR = @intval($ref["POSTFIELDS_in_STR"]);
	
	$headers = array();
	
	
	
	

	// Set the options
	curl_setopt($ch,CURLOPT_URL, $url);

	if($is_post)
	{
		// This sets the number of fields to post
		curl_setopt($ch,CURLOPT_POST, true);

		if($POSTFIELDS_in_STR)
		{
			// == if : Content-Type: application/x-www-form-urlencoded, then must call this
			$postdata = http_build_query($postdata);
		}
		
		
		// This is the fields to post in the form of an array.
		curl_setopt($ch,CURLOPT_POSTFIELDS, $postdata);
	}
	
	if(@$WithHeader)
	curl_setopt($ch, CURLOPT_HEADER, 1);
	
	

	// >>>>>>>>>> curl debug >>>>>>>>>>
		//$out = fopen(__DIR__ . "/curl_result.txt", 'w');
		//curl_setopt($ch, CURLOPT_VERBOSE, true);
		//curl_setopt($ch, CURLOPT_STDERR, $out);
	
	// <<<<<<<<< curl debug <<<<<<<<<<<<<<
	
	curl_setopt($ch, CURLOPT_USERAGENT, $browser);
	curl_setopt($ch, CURLOPT_ENCODING, "gzip");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION,0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,10); 
	curl_setopt($ch, CURLOPT_TIMEOUT, 1200); //timeout in seconds
	
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	
	
	if (!empty($ref["REFERER"]))
	{
		curl_setopt($ch, CURLOPT_REFERER, $ref["REFERER"]);
	}
	if (!empty($ref["HTTPHEADER"]))
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, $ref["HTTPHEADER"]);
	}
	
	
	
		$parse_url = parse_url($url);
		$scheme = strtolower($parse_url["scheme"]);
		if($scheme == "https")
		{
			
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false); 
			
			if (!empty($ref["SSLVERSION"]))
			{
				curl_setopt($ch, CURLOPT_SSLVERSION, $ref["SSLVERSION"]);
			}
		}
		curl_setopt($ch, CURLOPT_HEADERFUNCTION,
			function($curl, $header) use (&$headers)
			{
			 
				$len = strlen($header);
				$header = explode(':', $header, 2);
				if (count($header) < 2) // ignore invalid headers
					return $len;

				$name = strtolower(trim($header[0]));
				if (!array_key_exists($name, $headers))
					$headers[$name] = [trim($header[1])];
				else
					$headers[$name][] = trim($header[1]);

				return $len;
			}
		);

		
		
		if (!empty($ref["SaveCookie"]))
		{
			//if(!@file_exists($ref["SaveCookie"]))
			curl_setopt($ch, CURLOPT_COOKIEJAR, $ref["SaveCookie"]); 
		}
		if (!empty($ref["UseCookie"]))
		{
			curl_setopt($ch, CURLOPT_COOKIEFILE, $ref["UseCookie"]); 
		}
	
	//execute the post
	$result = curl_exec($ch);
	if (curl_error($ch)) {
		$ref["error"] = curl_error($ch);
	}

	$ref["header"] = $headers;
	$ref["curl_info"] = curl_getinfo($ch);
	
	
	//close the connection
	curl_close($ch);
	
	return $result;
}
