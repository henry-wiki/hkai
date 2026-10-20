<?PHP


function is_valid_date_str($date_str)
{
	$date_str = trim($date_str);
	
	if(!$date_str)
		return false;
	
	if(strlen($date_str) !== 10)
		return false;
	
	
	// Regular expression to check the date format
    $pattern = '/^\d{4}-\d{2}-\d{2}$/';
    
    if (preg_match($pattern, $date_str)) {
        // If the format is correct, split the date into components
        list($year, $month, $day) = explode('-', $date_str);
        $year 	= (int)$year;
        $month 	= (int)$month;
        $day 	= (int)$day;

        // Check if the date is valid using checkdate
        return checkdate($month, $day, $year);
    }

    return false; // Invalid format 
}

