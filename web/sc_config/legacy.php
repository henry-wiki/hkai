<?php
// == sc_config/legacy.php：常數兼容層（現有 SC_* 讀點零改動；逐檔遷完可拆） ==
// == 由 load.php 統一 include（一次即夠）——值一律經 sc_get_config() 取（load.php 已先 sc_set_configs() 交入） ==

// 哨兵：防重複 require 時重複 define（已定義＝跳過整段）
if (!defined("SC_DOCUMENT_ROOT")) {
    define("CHMOD", 0755);
    define("SC_CHMOD", 0755);

    // 對外文件根＝站根（lab 由站根服務——URL 一律 /web/…；sc_to_web_path 去本常數前綴即得網址）
    define("SC_DOCUMENT_ROOT", SC_REPO_ROOT);

    define("SC_DIR_CONTENTS"        , sc_get_config("contents.dir"));
    define("SC_DIR_FRAMEWORK"       , sc_get_config("contents.framework"));
    define("SC_DIR_APP"             , sc_get_config("contents.app"));
    define("SC_DIR_API"             , sc_get_config("contents.api"));
    define("SC_DIR_REQUEST"         , sc_get_config("contents.request"));
    define("SC_DIR_CONSOLE"         , sc_get_config("contents.console"));
    define("SC_DIR_CHANNEL"         , sc_get_config("contents.channel"));
    define("SC_DIR_MAIN"            , sc_get_config("contents.main"));
    define("SC_DIR_RESOURCES"       , sc_get_config("contents.resources"));
    define("SC_DIR_PROJECTS"        , sc_get_config("contents.projects"));

    define("SC_TMP_DIR"             , sc_get_config("runtime.tmp"));
    define("SC_LOG_DIR"             , sc_get_config("runtime.log"));
    define("SC_DIR_STORAGE"         , sc_get_config("storage.root_dir"));
}
