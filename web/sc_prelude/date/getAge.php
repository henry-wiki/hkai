<?PHP


function getAge($birthdate)
{
	if(!@$birthdate)
		return null;
	
	if(substr($birthdate,0,10) === "0000-00-00")
		return null;
	
    
	$adjust = (date("md") >= date("md", strtotime($birthdate))) ? 0 : -1; 
    $years = date("Y") - date("Y", strtotime($birthdate)); 
    return $years + $adjust; 
}

