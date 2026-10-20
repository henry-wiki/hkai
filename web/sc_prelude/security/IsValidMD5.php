<?PHP


function IsValidMD5($md5="")	{
	
	return preg_match('/^[a-f0-9]{32}$/', $md5);
	
}