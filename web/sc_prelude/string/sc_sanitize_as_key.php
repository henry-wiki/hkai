<?PHP


function sc_sanitize_as_key($data) 
{
	$data = strtolower($data);
	$data = preg_replace("/[^a-zA-Z0-9\-]+/", "-", $data);
	$data = trim($data,'-');
	
	return $data;
}
