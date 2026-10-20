<?php


function array_to_key_value($array=array(),$key="",$value="")
{
	$array = array_group_by($array,$key,true);
	$array = @array_map(function($item) use($value)	{return (isset($item[$value]) ? $item[$value] : null);},$array);

	return $array;
}



