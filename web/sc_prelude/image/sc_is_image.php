<?PHP



function sc_is_image($file_path)
{
	
	$image_extensions = array('jpg', 'jpeg', 'png', 'gif');
    
	$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
	
    if (@in_array($extension, $image_extensions))
		return true;
	
	return false;
}



