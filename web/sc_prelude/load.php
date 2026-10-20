<?PHP

// == sc_prelude/load.php：預載器——遞迴 include_once sc_prelude 目錄內全部 .php ==
// == 時機：任何入口最頂 require（index.php／setup.php／sc_console/app.php）——require 即自動載入 ==
// == 速度：readdir 流式（零 pattern·零陣列）＋絕對路徑＋include_once 自動去重（load.php 自身二次相遇即跳過） ==

// 對外入口：載入 sc_prelude 全樹（__DIR__＝本檔所在＝sc_prelude 根）
function sc_prelude_load()
{
    sc_prelude_load_func(__DIR__);
}

// 單層掃描＋子目錄下探（遞歸；每層一個目錄手柄——流式讀、唔建檔案清單）
function sc_prelude_load_func($dir)
{
    $handle = opendir($dir);
    
    // 判定：目錄開唔到 → 直接回（正常恆存在）
    if (!$handle) {
        return;
    }
    
    // 逐一讀取目錄項（唔排序·唔過濾——最低成本）
    while (($entry = readdir($handle)) !== false) {
        
        // 跳過 . 與 .. 兩項
        if (in_array($entry, [".", ".."])) {
            continue;
        }
        
        $path = $dir . "/" . $entry;
        
        // 子目錄 → 遞歸下探
        if (is_dir($path)) {
            sc_prelude_load_func($path);
            continue;
        }
        
        // 非 .php 檔 → 唔載（sc_prelude 只放 php 函數）
        if (substr($entry, -4) !== ".php") {
            continue;
        }
        
        // .php → include_once（載過自動跳過——去重全靠佢）
        include_once($path);
    }
    
    closedir($handle);
}

// 執行：本檔一經 require 即自動載入全樹（入口只寫 require_once 一行）
sc_prelude_load();
