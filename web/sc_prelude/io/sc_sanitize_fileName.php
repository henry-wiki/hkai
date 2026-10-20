<?PHP



function sc_sanitize_fileName($fileName)
{
	//sanitize, remove double dot .. and remove get parameters if any
	$fileName = preg_replace('@\?.*$@' , '', preg_replace('@\.{2,}@' , '', preg_replace('@[^\/\\a-zA-Z0-9\-\._]@', '', $fileName)));
	return $fileName;
}




