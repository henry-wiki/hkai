<?php

// == about_hkai.php：關於 HKAI 資料源（sc_config/hkai 層——about 分頁唯一來源） ==
// == 職責：一份 about 資料——靜態文案直接寫；動態值（版本／建置日／技術版本）喺此檔產生 ==
// == 契約：return array（key＝顯示欄位；消費端＝sc_ai_settings about 域 handle_fetch_about） ==
// == 例外：需要掃描／外部查詢嘅特殊值（如更新檢查）先至喺其他地方組合後併入 ==
// == 注意：文案類字串（tagline 等）唔喺此檔——入 i18n 字典 settings.about.*（中英各一版） ==

// 版本號（唯一來源＝repo 根 VERSION 檔——bump 版本＝只改嗰度，about 自動跟）
$about_version = trim(file_get_contents(hkai__core()->get_version_file()));

// 建置日期（動態產生——本檔最後修改日）
$about_build = date("Y-m-d", filemtime(__FILE__));

// 技術棧（PHP 版本動態產生——其餘固定；Python 版本如需動態＝掃描類，喺其他地方組合後併入）
$about_tech = "PHP " . PHP_VERSION . " · Python 3.13 · mod_php";

return [
    "name"          => "HKAI",
    "version"       => $about_version,
    "build"         => $about_build,
    "tech"          => $about_tech,
    "update_status" => "已是最新",
    // 作者／支援機構（開源署名——License／Source 欄待上線後補）
    "author"        => "Henry Yau",
    "supported_by"  => "Seacorner",
    // 版權（年份動態——跟行機時年；公司名可在此改）
    "copyright"     => "© " . date("Y") . " HKAI",
];
