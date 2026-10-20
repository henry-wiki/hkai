<?php

// == web 對外 API（純函數：UI 資源輸出） ==

function sc_load_js($file,$version=null)
{
	$file = realpath($file);
	
	if(!@$file)
		return null;
	
	$abs_path = sc_to_web_path($file);
	
	$qs = array();
	if(@$version)
	{
		$qs["version"] = $version;
	}
	$qs = http_build_query($qs);
	$qs = @$qs ? "?" .$qs : "";
	
	$abs_path = $abs_path . "" . $qs;
	
	echo "<script src='".$abs_path."' type='text/javascript'></script>";
	
}


function sc_load_css($file,$version=null)
{
	$file = realpath($file);
	
	if(!@$file)
		return null;
	
	$abs_path = sc_to_web_path($file);
	
	$qs = array();
	if(@$version)
	{
		$qs["version"] = $version;
	}
	$qs = http_build_query($qs);
	$qs = @$qs ? "?" .$qs : "";
	
	$abs_path = $abs_path . "" . $qs;
	
	echo "<link href='".$abs_path."' rel='stylesheet' type='text/css' />";
}
