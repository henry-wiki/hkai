// ---------------------------------------------------------------------------
// startup.rs — 啟動流程（一段故事·三步）
// 1. take_splash   ：記住還原幾何 → 縮成小窗 → 居中 → 寫 splash HTML → 顯示
// 2. servers::start：揀埠 → 起 PHP／Apache → 等埠開
// 3. show_main     ：等足最短 splash 時間 → 還原尺寸 → 載入真 UI
// ---------------------------------------------------------------------------

use std::time::{Duration, Instant};

use tauri::{AppHandle, LogicalPosition, LogicalSize, Manager, Url};

use crate::config::{ShellConfig, SplashConfig};

const SPLASH_PAINT_WAIT_MS: u64 = 200;      // 等 WebView 畫好 splash 才顯示（避免空白）
const DEFAULT_WIDTH: f64 = 1150.0;          // 首次開啟（無記憶）時嘅主窗尺寸
const DEFAULT_HEIGHT: f64 = 780.0;
const MIN_RESTORED_WIDTH: f64 = 400.0;      // 細過呢個 = 唔可信（通常係 splash 尺寸）
const MIN_RESTORED_HEIGHT: f64 = 300.0;

/// 窗口矩形（logical 座標·跨 DPI）
struct WindowRect {
    x: f64,
    y: f64,
    width: f64,
    height: f64,
}

/// 啟動流程（背景 thread·唔阻窗口出現）
pub fn run(app: AppHandle, shell: ShellConfig) {
    std::thread::spawn(move || {
        let started = Instant::now();

        // 第 1 步：小窗＋載入中畫面
        let previous = take_splash(&app, &shell.splash);
        let splash_shown = Instant::now();

        // 第 2 步：起子進程（會阻塞到 PHP 埠開為止）
        let port = crate::servers::start(&shell);

        // 第 3 步：還原尺寸＋載入真 UI
        show_main(&app, &shell.splash, previous, port, splash_shown);

        log::info!("main window ready in {} ms", started.elapsed().as_millis());
    });
}

/// 縮成小窗、居中、寫入 splash HTML、顯示；回傳還原幾何（供之後還原）
fn take_splash(app: &AppHandle, splash: &SplashConfig) -> Option<WindowRect> {
    let window = app.get_webview_window("main")?;

    // window-state plugin 已經還原上次尺寸 → 先記住它
    let previous = current_rect(&window);

    // 縮成 splash 尺寸
    let _ = window.set_size(LogicalSize::new(splash.width as f64, splash.height as f64));

    // 永遠擺喺「目前螢幕」正中央（Desktop 1／2 都正確）
    let _ = window.center();

    // 由磁碟載入 splash HTML（改完即時生效）
    load_splash_html(&window);

    // 等 WebView 真正畫好才顯示（避免黑／白閃）
    std::thread::sleep(Duration::from_millis(SPLASH_PAINT_WAIT_MS));
    let _ = window.show();
    log::info!("splash shown");

    previous
}

/// 等足最短時間 → 還原尺寸 → 載入真 UI
fn show_main(
    app: &AppHandle,
    splash: &SplashConfig,
    previous: Option<WindowRect>,
    port: u16,
    splash_shown: Instant,
) {
    let window = match app.get_webview_window("main") {
        Some(value) => value,
        None => return,
    };

    // splash 至少顯示 min_ms（由 config.toml 控制）
    let minimum = Duration::from_millis(splash.min_ms);
    if splash_shown.elapsed() < minimum {
        std::thread::sleep(minimum - splash_shown.elapsed());
    }

    // 還原上次尺寸／位置；首次開啟就用預設尺寸＋居中
    match previous {
        Some(rect) => {
            let _ = window.set_size(LogicalSize::new(rect.width, rect.height));
            let _ = window.set_position(LogicalPosition::new(rect.x, rect.y));
        }
        None => {
            let _ = window.set_size(LogicalSize::new(DEFAULT_WIDTH, DEFAULT_HEIGHT));
            let _ = window.center();
        }
    }

    // 同一個窗 → 導航去 PHP 伺服器（真 UI）
    if let Ok(url) = Url::parse(&format!("http://127.0.0.1:{}/", port)) {
        let _ = window.navigate(url);
    }
}

/// 讀取窗口現時位置／尺寸（logical·跨 DPI）；太細（splash 級）＝當作無記憶
fn current_rect(window: &tauri::WebviewWindow) -> Option<WindowRect> {
    let position = window.outer_position().ok()?;
    let size = window.inner_size().ok()?;
    let scale = window.scale_factor().unwrap_or(1.0);

    let rect = WindowRect {
        x: position.x as f64 / scale,
        y: position.y as f64 / scale,
        width: size.width as f64 / scale,
        height: size.height as f64 / scale,
    };

    if rect.width < MIN_RESTORED_WIDTH || rect.height < MIN_RESTORED_HEIGHT {
        return None;
    }
    Some(rect)
}

/// 由 assets\splash.html 讀 HTML，直接寫入現有頁面（唔使重建 exe）
fn load_splash_html(window: &tauri::WebviewWindow) {
    let path = crate::root_dir().join("assets").join("splash.html");
    let html = match std::fs::read_to_string(&path) {
        Ok(value) => value,
        Err(_) => return,
    };

    // 轉成 JS 字串字面值（安全轉義）
    let encoded = match serde_json::to_string(&html) {
        Ok(value) => value,
        Err(_) => return,
    };

    let script = format!("document.open();document.write({});document.close();", encoded);
    let _ = window.eval(&script);
}
