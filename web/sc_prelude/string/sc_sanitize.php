<?PHP


function sc_sanitize($data) 
{
	$data = htmlspecialchars($data);
    $data = stripslashes($data);
    $data = trim($data);
    return $data;
}
