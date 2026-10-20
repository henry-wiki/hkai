<?PHP


function getBirthday($age){
    
	$date = date("Y-m-d",mktime(0,0,0,date("n"),date("j"),date("Y")-$age));
	
	
    return $date; 
}

