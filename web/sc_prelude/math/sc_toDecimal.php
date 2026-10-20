<?PHP



function sc_toDecimal($value,$decimal=2)
{
	$value = @number_format($value,$decimal,'.','');
	
	return $value;
}
