<?PHP



function sc_get_select_options($type,$IdValue="")	
{
	global $GlobalVars;
	
	$select_options = "";
	switch($type)
	{
		
		case "Birth_Year" : 
			$select_options = "";
			for($i=Date("Y");$i>(date("Y") - 100);$i--)	{
				$year = $i;
				$selected = $year == $IdValue ? "selected" : "";
				$select_options .= "<option ".$selected." value='".$year."'>".$year."</option>";
			}
			
		break;
		case "Birth_Month" : 
			$select_options = "";
			for($i=1;$i<=12;$i++)	{
				$month = $i < 10 ? "0" . $i : $i;
				$selected = $month == $IdValue ? "selected" : "";
				$select_options .= "<option ".$selected." value='".$month."'>".$month."</option>";
			}
			
		break;
		case "Birth_Day" : 
			$select_options = "";
			for($i=1;$i<=31;$i++)	{
				$day = $i < 10 ? "0" . $i : $i;
				$selected = $day == $IdValue ? "selected" : "";
				$select_options .= "<option ".$selected." value='".$day."'>".$day."</option>";
			}
			
		break;
		
		
	}
	
	return $select_options;
}


