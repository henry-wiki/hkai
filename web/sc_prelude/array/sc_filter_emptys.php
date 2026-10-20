<?php


function sc_filter_emptys($row=array(),$filterZero=true)	
{
		
	if(@$row)
	$row = array_map_recursive(function($item)	use($filterZero) {
			
			if($filterZero)
			$item = @empty($item) ? "" : $item;
			else
			$item = @trim($item) === "" ? "" : $item;
			
			if(@preg_match("/^0\.[0]+$/",$item))	$item = "";
			if(@$item === "0")	$item = "";
			
			
			if(substr($item,0,10) === "0000-00-00" && (strlen($item) == 19 || strlen($item) == 16 || strlen($item) == 10))
			$item = "";
		
		return $item;
	},$row);
	
	return $row;
}



