<?PHP




function clear_all_buffer()
{
	
	while (@ob_end_clean()) {  
		// do nothing   
	}
	
}