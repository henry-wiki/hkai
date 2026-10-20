<?PHP

function sc_rrmdir($dir) {
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (is_dir($dir."/".$object))
           sc_rrmdir($dir."/".$object);
         else
           unlink($dir."/".$object);
       }
     }
     rmdir($dir);
   }
 }
