<?PHP


function get_diff_months($startDate,$endDate){
    

	$year_s = substr($startDate,0,4);
	$year_e = substr($endDate,0,4);
	

	$month_s = substr($startDate,5,2);
	$month_e = substr($endDate,5,2);
	

	$diff = (($year_e - $year_s) * 12) + ($month_e - $month_s);
	
	return $diff;
}

