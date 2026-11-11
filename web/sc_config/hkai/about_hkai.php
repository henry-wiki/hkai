<?php

// == about_hkai.php：關於 HKAI 資料源（sc_config/hkai 層——About 分頁唯一來源） ==
// == 職責：一份 About 資料——靜態文案直接寫；動態值（版本／建置／日期）在此檔產生 ==
// == 契約：return array（key＝顯示欄位；消費端＝sc_ai_settings about 域 handle_fetch_about） ==
// == 改資料只需改此檔：加減資訊卡＝加減 facts 一項；加減按鈕＝加減 quick_links 一項 ==
// == 文案標籤（左側 label）唔喺此檔——入 i18n 字典 settings.about.*（中英各一版） ==

// 版本號（唯一來源＝sc_config core.version → repo 根 VERSION 檔——bump 版本＝只改嗰度）
$about_version = trim(file_get_contents(sc_get_config("core.version")));

// 建置時間預設（無 .build 檔時＝本檔修改時間）
$about_build = date("Y-m-d H:i", filemtime(__FILE__));

// 建置檔位置（站根 .build——build 時寫入 Ymd.His）
$about_build_file = dirname(sc_get_config("core.version")) . "/.build";

// 判定：.build 存在 → 用 build 時間
if (is_file($about_build_file)) {
    $about_build_value  = trim(file_get_contents($about_build_file));
    $about_build_object = DateTime::createFromFormat("Ymd.His", $about_build_value);

    // 判定：解析成功 → 顯示 Y-m-d H:i
    if ($about_build_object !== false) {
        $about_build = $about_build_object->format("Y-m-d H:i");
    }
}

// 發佈日期（取建置時間的日期部分）
$about_release_date = substr($about_build, 0, 10);

// 核心引擎（背景 AI agent daemon）
$about_engine = "Goose daemon（Python 3.13）";

// 執行環境（PHP 版本動態產生）
$about_platform = "Win32（Tauri）· PHP " . PHP_VERSION;

// 授權模式（對應 repo 根 LICENSE）
$about_license = "MIT";

// 資訊卡（label_key＝i18n 標籤；value＝顯示值——加減一張＝加減一個元素）
$about_facts = [
    ["label_key" => "settings.about.fact.version",      "value" => $about_version],
    ["label_key" => "settings.about.fact.build",        "value" => $about_build],
    ["label_key" => "settings.about.fact.release_date", "value" => $about_release_date],
    ["label_key" => "settings.about.fact.engine",       "value" => $about_engine],
    ["label_key" => "settings.about.fact.platform",     "value" => $about_platform],
    ["label_key" => "settings.about.fact.license",      "value" => $about_license],
    ["label_key" => "settings.about.author",            "value" => "Henry Yau"],
    ["label_key" => "settings.about.supported_by",      "value" => "Seacorner"],
];

// 快速連結（icon＝Font Awesome class；加減一粒＝加減一個元素）
$about_quick_links = [
    ["label_key" => "settings.about.link.website",   "icon" => "fa-solid fa-globe",             "url" => "https://github.com/henry-wiki/hkai"],
    ["label_key" => "settings.about.link.manual",    "icon" => "fa-solid fa-book",              "url" => "https://github.com/henry-wiki/hkai#readme"],
    ["label_key" => "settings.about.link.changelog", "icon" => "fa-solid fa-clock-rotate-left", "url" => "https://github.com/henry-wiki/hkai/blob/main/CHANGELOGS.md"],
    ["label_key" => "settings.about.link.privacy",   "icon" => "fa-solid fa-shield-halved",     "url" => "https://github.com/henry-wiki/hkai/blob/main/PRIVACY.md"],
    ["label_key" => "settings.about.link.license",   "icon" => "fa-solid fa-file-lines",        "url" => "https://github.com/henry-wiki/hkai/blob/main/LICENSE"],
];

// 鳴謝名單（每筆：name／note／url；空 array＝面板出空狀態）
$about_thanks = [
    ["name" => "Henry Yau",      "note" => "作者・主要開發", "url" => "https://github.com/henry-wiki"],
    ["name" => "Seacorner",      "note" => "支持單位", "url" => ""],
    ["name" => "Goose（AAIF）",   "note" => "AI agent 引擎", "url" => ""],
    ["name" => "開源社群",        "note" => "jQuery・Bootstrap・CodeMirror・Font Awesome", "url" => ""],
    ["name" => "HKAI 使用者",     "note" => "回報問題與建議", "url" => "https://github.com/henry-wiki/hkai/issues"],
];

// 技術與開源（實際使用嘅程式／語言／套件；每筆：name／note／url）
$about_credits = [
    ["name" => "PHP 8.4",          "note" => "後端框架・路由・頁面組裝", "url" => "https://www.php.net/"],
    ["name" => "JavaScript (ES6)", "note" => "前端 ESM 模組（jsmods）", "url" => ""],
    ["name" => "jQuery",           "note" => "前端 DOM 操作", "url" => "https://jquery.com/"],
    ["name" => "Python 3.13",      "note" => "AI agent daemon（goose_daemon）", "url" => "https://www.python.org/"],
    ["name" => "Rust／Tauri",      "note" => "桌面外殼（HKAI.exe）", "url" => "https://tauri.app/"],
    ["name" => "Bootstrap",        "note" => "CSS 樣式基礎", "url" => "https://getbootstrap.com/"],
    ["name" => "CodeMirror",       "note" => "Instructions 編輯器", "url" => "https://codemirror.net/"],
    ["name" => "Font Awesome 6",   "note" => "圖示（CSS icon）", "url" => "https://fontawesome.com/"],
    ["name" => "marked 18",        "note" => "Markdown 渲染", "url" => "https://marked.js.org/"],
    ["name" => "DOMPurify 3",      "note" => "HTML 消毒（XSS 防護）", "url" => "https://github.com/cure53/DOMPurify"],
    ["name" => "highlight.js 11",  "note" => "程式碼高亮", "url" => "https://highlightjs.org/"],
    ["name" => "Mermaid 11",       "note" => "流程圖／圖表渲染", "url" => "https://mermaid.js.org/"],
    ["name" => "Goose（AAIF）",     "note" => "AI agent 引擎", "url" => ""],
];

// 贊助名單（每筆：name／note／url；空 array＝面板出空狀態）
$about_sponsors = [
    ["name" => "Seacorner",  "note" => "支持單位", "url" => ""],
    ["name" => "Henry Yau",  "note" => "作者・開發者", "url" => "https://github.com/henry-wiki"],
];

return [
    "name"         => "HKAI",
    "version"      => $about_version,
    "build"        => $about_build,
    "release_date" => $about_release_date,
    "engine"       => $about_engine,
    "platform"     => $about_platform,
    "license"      => $about_license,
    "facts"        => $about_facts,
    "quick_links"  => $about_quick_links,
    "thanks"       => $about_thanks,
    "credits"      => $about_credits,
    "sponsors"     => $about_sponsors,
    // 更新檢查目標（無更新 API——直接跳 GitHub releases）
    "update_url"   => "https://github.com/henry-wiki/hkai/releases",
    // 完整鳴謝與授權頁（footer 連結）
    "credits_url"  => "https://github.com/henry-wiki/hkai/blob/main/THIRD_PARTY_LICENSES.md",
    // 版權（年份動態——跟行機時年）
    "copyright"    => "© " . date("Y") . " HKAI",
];
