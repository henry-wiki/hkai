<?PHP

function sc_array_trim($data)
{
	if (gettype($data) == 'array')
		return array_map(__NAMESPACE__ . "\sc_array_trim", $data);
	else
		return trim($data);

}
