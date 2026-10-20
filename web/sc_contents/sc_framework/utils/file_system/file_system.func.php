<?php

// =========================================================================================================================
// == file_system 對外 API（純函數，無 class） ==
// == sc_to_web_path()：磁碟路徑 → web 相對路徑（去 SC_DOCUMENT_ROOT，瀏覽器可用） ==
// == sc_to_disk_path()：web 相對路徑 → 磁碟路徑（加回 SC_DOCUMENT_ROOT） ==
// == sc_scan_files()：遞迴掃描目錄 → 回傳檔案/目錄路徑清單 ==
// == 線 B：依賴框架常數 SC_DOCUMENT_ROOT → 框架工具層，遷自 function/file/ ==
// =========================================================================================================================

function sc_to_web_path($filepath)
{
    if (!$filepath) {
        return false;
    }
    
    // 統一正斜線（Windows 反斜線 → 正斜線——匹配與輸出一致）
    $filepath = str_replace("\\", "/", $filepath);
    
    // SC_DOCUMENT_ROOT 同源正規化（dirname() 在 Windows 回傳反斜線）
    $document_root = str_replace("\\", "/", SC_DOCUMENT_ROOT);
    
    // 在 docroot 內 → 去前綴即 web path
    if (strpos($filepath, $document_root) === 0) {
        return substr($filepath, strlen($document_root));
    }
    
    // 非 docroot（如 CDN URL）→ 原樣
    return $filepath;
}

function sc_to_disk_path($filepath)
{
    if(!@$filepath)
    return $filepath;
    
    $web_path = sc_to_web_path($filepath);
    
    $disk_path = SC_DOCUMENT_ROOT . "/" . sc_trim($web_path,"/");
    
    return $disk_path;
}

// =========================================================================================================================
// == sc_scan_files：遞迴掃描目錄 → 回傳檔案/目錄路徑清單 ==
// == 參數：scantype（1=file only／2=dir only／0=all）、regex（路徑過濾）、returnWebPath（回傳 web 相對路徑） ==
// == 遷自 function/file/sc_scan_files.php ==
// =========================================================================================================================

function sc_scan_files($dir, $scantype = 1, $regex = null, $returnWebPath = false, $debug = 0)
{
    if(substr($dir,-1,1) == "/")
    $dir = substr($dir,0,-1);
    
    $files = [];
    
    $pathBundles = [$dir];
    
    while(count($pathBundles) > 0)
    {
        $path    = array_shift($pathBundles);
        $scandir = @scandir($path);
        
        if(count($scandir) && @is_array($scandir))
        {
            foreach($scandir as $item)
            {
                if($item == "." || $item == "..") continue;
                
                $newpath = $path."/".$item;
                
                $is_file = is_file($newpath);
                $is_dir  = is_dir($newpath);
                
                if($scantype === 1)
                {
                    if($is_file)
                    {
                        if(@$regex)
                        {
                            if(!preg_match($regex,$newpath))
                            continue;
                        }
                        
                        if($returnWebPath)
                        $files[] = sc_to_web_path($newpath);
                        else 
                        $files[] = $newpath;
                    }
                }
                else if($scantype === 2)
                {
                    if($is_dir)
                    {
                        if($returnWebPath)
                        $newpath = sc_to_web_path($newpath);
                        
                        if($returnWebPath)
                        $files[] = sc_to_web_path($newpath);
                        else 
                        $files[] = $newpath;
                    }
                }
                else
                {
                    if(@$regex)
                    {
                        if(!preg_match($regex,$newpath))
                        continue;
                    }
                    
                    if($returnWebPath)
                    $files[] = sc_to_web_path($newpath);
                    else 
                    $files[] = $newpath;
                }
                
                if($is_dir)
                {
                    $pathBundles[] = $path."/".$item;
                }
            }
        }
    }
    return $files;
}

// =========================================================================================================================
// == sc_get_tail_content：讀取檔案末尾 N 個字元（多字元安全） ==
// == 參數：tail（0=空、null/負=全檔、正數=末尾 N 字元） ==
// =========================================================================================================================

function sc_get_tail_content(string $path, ?int $tail = 0, string $encoding = 'UTF-8'): string
{
    if ($tail === 0) {
        return '';
    }

    $fp = @fopen($path, 'rb');
    if (!$fp) {
        return '';
    }

    $stat = fstat($fp);
    $size = $stat['size'] ?? 0;
    if ($size === 0) {
        fclose($fp);
        return '';
    }

    if ($tail === null || $tail < 0) {
        $len = $size;
    } else {
        $len = min($size, $tail * 4 + 16);
        fseek($fp, -$len, SEEK_END);
    }

    $raw = fread($fp, $len);
    fclose($fp);

    if ($raw === false || $raw === '') {
        return '';
    }

    return ($tail !== null && $tail > 0)
        ? mb_substr($raw, -$tail, null, $encoding)
        : $raw;
}
