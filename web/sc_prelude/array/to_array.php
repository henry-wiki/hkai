<?PHP

function to_array($data)
{
	return @is_array($data) ? $data : array();
}
