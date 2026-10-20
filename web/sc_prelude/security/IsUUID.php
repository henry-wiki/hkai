<?PHP


function IsUUID($uuid)	{
	
	if(is_string($uuid) && preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $uuid)) {
		return true;
	}

	return false;
	
}