<?PHP


function getDiffMinutes($t1,$t2){
    
	if(!@$t1)
		return null;
	if(!@$t2)
		return null;
	
	
	
	$t1 = strtotime($t1);
	$t2 = strtotime($t2);
	
	return floor($t2 - $t1) / 60;
	
}

