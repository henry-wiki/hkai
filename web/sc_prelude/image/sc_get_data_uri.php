<?PHP


function sc_get_data_uri($img_file) {
    $imgData = base64_encode(file_get_contents($img_file));

// Format the image SRC:  data:{mime};base64,{data};
	$src = 'data: '.mime_content_type($img_file).';base64,'.$imgData;
	
    return $src;
}
