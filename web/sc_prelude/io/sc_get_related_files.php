<?PHP



function sc_get_related_files($filepath)
{
	$filename 	= pathinfo($filepath,PATHINFO_FILENAME);
	$extension 	= pathinfo($filepath,PATHINFO_EXTENSION);
	$directory 	= dirname($filepath);
	
	$related_files	= glob($directory."/".$filename."*.".$extension."");
	
	return $related_files;
}



