<?PHP

function delete_directory($dir) {
	if(!@$dir)
	return false;

	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object)
		{
			if ($object != "." && $object != "..")
			{
				if (is_dir($dir."/".$object))
				delete_directory($dir."/".$object);
				else
				unlink($dir."/".$object);
			}
		}
		rmdir($dir);
	}

	return true;
}
