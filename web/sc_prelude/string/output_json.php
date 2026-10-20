<?PHP



function output_json($data,$exit=false)
{
	echo json_encode($data,JSON_UNESCAPED_UNICODE);
	
	if($exit)
	die();
}
