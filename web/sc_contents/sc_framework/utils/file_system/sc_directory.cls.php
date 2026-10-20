<?php
namespace sc\framework\utils\file_system;

class sc_directory_Class
{
    // == properties ==
    private static $instance = null;
    
    private $rootdir = null;
    
    // == constructor ==
    public function __construct($rootdir = null)	{	
        $this->rootdir = $this->detect_document_root();
        
        if ($rootdir !== null) {
            $this->set_rootdir($rootdir);
        }
    }
    
    // == public API ==
    public static function getInstance()	
    {
        if (!@isset(self::$instance)) {	$class = __CLASS__;self::$instance = new $class();	}
        return self::$instance;
    }
    
    // 日後可改 root
    public function set_rootdir($rootdir)
    {
        $this->rootdir = rtrim($rootdir, "/");
    }
    
    // ==================================================================================
    public function get_files_multi_levels($dirpath = null,$deep = 0)
    {
        if($deep == 0)
        {
            $dirpath = str_replace("\\","/",$dirpath);
            
            if(str_ends_with($dirpath,"/"))
            {
                $dirpath = substr($dirpath, 0, -1);
            }
        }
        
        $files = [];
        //$files["parent"] 		= $dirpath;
        $files["self"]     = [];
        $files["children"] = [];
        
        // store the deep indexes and get the max deep index
        $deepIndexes = [$deep];
        $totals      = [0];
        
        if(@file_exists($dirpath) && is_dir($dirpath))
        {
            $list = [];
            
            
            $rootFileArray          = [];
            $rootFileArray["name"]  = basename($dirpath);
            $rootFileArray["path"]  = $dirpath;
            $rootFileArray["isDir"] = intval(is_dir($dirpath));
            $rootFileArray["deep"]  = $deep;
            
            $files["self"] = &$rootFileArray;
            
            if(!is_readable($dirpath))
            {
                
            }
            else 
            {
                $dir = opendir($dirpath);
                
                while(($file = readdir($dir)) !== false)
                {
                    // == filtering == 
                    if ( $file == '.' or $file == '..' )
                    {
                        continue;
                    }
                    
                    $isDir = is_dir($dirpath."/".$file);
                    
                    
                    
                    if($isDir)
                    {
                        $children = $this->{__FUNCTION__}($dirpath."/".$file,$deep + 1);
                        
                        // [parent level] == store in array == 
                        $deepIndexes[] = $children["maxDeep"];
                        
                        // [parent level] == store in array == 
                        $totals[] = $children["total"];
                        
                        unset($children["maxDeep"]);
                        unset($children["total"]);
                        
                        
                        $list[] = $children;
                        
                    }
                    else
                    {
                        $fileArray           = [];
                        $fileArray["parent"] = $dirpath;
                        $fileArray["name"]   = $file;
                        $fileArray["path"]   = $dirpath."/".$file;
                        $fileArray["isDir"]  = intval($isDir);
                        $fileArray["deep"]   = $deep + 1;
                        
                        $list[] = $fileArray;
                        
                    }
                }
                
                // == get files amount ==
                $listCount = @count($list);
                
                // == store in array == 
                $totals[] = $listCount;
                
                if($listCount > 0)
                {
                    $deepIndexes[] = $deep + 1;
                }
                
                // == store in ref array == 
                $rootFileArray["count"]   = $listCount;
                $rootFileArray["maxDeep"] = @max($deepIndexes);
                $rootFileArray["total"]   = array_sum($totals);
                
                // 
                $files["children"] = $list;
                
                closedir($dir);
            }
            
            // [children level] - store the max deep / total, it will be useful for [parent level]
            if($deep)
            {
                $files["maxDeep"] = @max($deepIndexes);
                $files["total"]   = @array_sum($totals);
            }
        }
        
        return $files;
        
    }
    public function get_all_files($dirpath = null,$deep = 0)
    {
        if($deep == 0)
        {
            $dirpath = str_replace("\\","/",$dirpath);
            $dirpath = realpath($dirpath);
            $dirpath = rtrim($dirpath,"/");
            
        }
        
        
        
        $files         = [];
        $files["list"] = [];
        
        // store the deep indexes and get the max deep index
        $deepIndexes = [$deep];
        $totals      = [0];
        
        $childrenLists = [];
        
        $isDir = is_dir($dirpath);
        
        if(@file_exists($dirpath) && $isDir)
        {
            $list = [];
            
            $rootFileArray          = [];
            $rootFileArray["name"]  = basename($dirpath);
            $rootFileArray["path"]  = $dirpath;
            $rootFileArray["isDir"] = intval($isDir);
            $rootFileArray["deep"]  = $deep;
            
            $list[] = &$rootFileArray;
            
            
            
            if(!is_readable($dirpath))
            {
                $files["list"] = $list;
            }
            else 
            {
                $dir = \opendir($dirpath);
                
                $counter = 0;
                while(($file = \readdir($dir)) !== false)
                {
                    // == filtering == 
                    if ( $file == '.' or $file == '..' )
                    {
                        continue;
                    }
                    
                    $isDir = is_dir($dirpath."/".$file);
                    
                    $fileArray           = [];
                    $fileArray["parent"] = $dirpath;
                    $fileArray["name"]   = $file;
                    $fileArray["path"]   = $dirpath."/".$file;
                    $fileArray["isDir"]  = intval($isDir);
                    $fileArray["deep"]   = $deep + 1;
                    
                    if($isDir)
                    {
                        
                        $children = $this->{__FUNCTION__}($dirpath."/".$file,$deep + 1);
                        
                        // [parent level] == store in array == 
                        $deepIndexes[] = $children["maxDeep"];
                        
                        // [parent level] == store in array == 
                        $totals[] = $children["total"];
                        
                        // [parent level] == store in array == 
                        $childrenLists[] = $children["list"];
                        
                        
                    }
                    
                    if(!$isDir)
                    {
                        $list[] = $fileArray;
                    }
                    
                    ++$counter;
                    
                }
                
                
                // == get files amount ==
                $listCount = $counter;
                
                // == store in array == 
                $totals[] = $listCount;
                
                if($listCount > 0)
                {
                    $deepIndexes[] = $deep + 1;
                }
                
                // == store in ref array == 
                $rootFileArray["count"]   = $listCount;
                $rootFileArray["maxDeep"] = @max($deepIndexes);
                $rootFileArray["total"]   = array_sum($totals);
                
                // merge children lists is not empty
                if($childrenLists)
                {
                    $childrenLists = call_user_func_array("array_merge",$childrenLists);
                }
                $files["list"] = array_merge($list,$childrenLists);
                
                closedir($dir);
                
            }
            
            // [children level] - store the max deep / total, it will be useful for [parent level]
            if($deep)
            {
                $files["maxDeep"] = @max($deepIndexes);
                $files["total"]   = @array_sum($totals);
            }
        }
        
        
        if($deep === 0)
        {
            return $files["list"];
        }
        else
        {
            return $files;
        }
    }
    
    // == internal（private）==
    // 自動偵測 document root（Web 用 $_SERVER；CLI 無 DOCUMENT_ROOT → __DIR__ 推算 fallback）
    private function detect_document_root()
    {
        if (!empty($_SERVER["DOCUMENT_ROOT"])) {
            return rtrim($_SERVER["DOCUMENT_ROOT"], "/");
        }
        return null;
    }
    
    
    // 磁碟路徑 → web 相對路徑（內部自行計算：去掉 rootdir 前綴）
    // 註：public——sc_get_backtrace_pathinfo() 全域函數依賴此方法（backtrace 檔名顯示）
    public function get_absolute_path($filepath)
    {
        if (!@$filepath) {
            return $filepath;
        }
        
        $rootdir  = rtrim($this->rootdir, "/");
        $filepath = str_replace("\\", "/", $filepath);
        
        // 在 rootdir 下 → 剝離前綴；否則原樣回傳
        if (strpos($filepath, $rootdir . "/") === 0) {
            return substr($filepath, strlen($rootdir));
        }
        return $filepath;
    }
    
    // web 相對路徑 → 磁碟路徑（內部自行計算：加回 rootdir）
    private function get_disk_path($filepath)
    {
        if (!@$filepath) {
            return $filepath;
        }
        
        $rootdir  = rtrim($this->rootdir, "/");
        $filepath = ltrim($filepath, "/");
        
        return $rootdir . "/" . $filepath;
    }
    
    
    
    
    
}
