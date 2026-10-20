<?PHP

function sc_curl_v2020($url,$postdata=array(),$headers=array(),$is_post=true)
{
	if(!@is_array($headers))
		$headers = array();
	
	// Initialize cURL
	$curl = curl_init();

	// Set the options
	curl_setopt($curl,CURLOPT_URL, $url);

	if($is_post)
	{
		// This sets the number of fields to post
		curl_setopt($curl,CURLOPT_POST, true);

		
		
		// This is the fields to post in the form of an array.
		curl_setopt($curl,CURLOPT_POSTFIELDS, $postdata);
	}
	
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
	
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	
	
	
	//execute the post
	$result = curl_exec($curl);

	//close the connection
	curl_close($curl);
	
	return $result;
}
