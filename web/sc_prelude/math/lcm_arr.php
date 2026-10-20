<?PHP

function lcm_arr($arrs=array()) //least common multiple
{
	if(!@is_array($arrs))
		return null;
	$arrs = array_filter($arrs);

	$arrs = array_map(function($item)	{return intval($item);},$arrs);

	if(count($arrs) < 1)
		return null;

	if(count($arrs) < 2)
		return $arrs[0];

	while(2 <= count($arrs)){
        array_push($arrs, lcm(array_shift($arrs), array_shift($arrs)));
    }
    return reset($arrs);


}
