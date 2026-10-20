#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

// ---------------------------------------------------------------------------
// lab_shell — Tauri 2 外殼（入口）
// 1. 讀設定（config.rs）
// 2. 掛三個官方 plugin：單一實例／窗口記憶／log
// 3. 交啟動流程俾 startup.rs（背景 thread·唔阻窗口）
// 4. 關窗時交清理俾 servers.rs
// ---------------------------------------------------------------------------

mod config;
mod servers;
mod single_instance;
mod startup;

use std::path::PathBuf;

use tauri::RunEvent;

/// exe 所在資料夾（所有相對路徑嘅根）
pub fn root_dir() -> PathBuf {
    std::env::current_exe()
        .ok()
        .and_then(|path| path.parent().map(|parent| parent.to_path_buf()))
        .unwrap_or_else(|| PathBuf::from("."))
}


// ---------------------------------------------------------------------------
// 前端呼叫：開 DevTools（navbar 嘅 console 掣）
// ---------------------------------------------------------------------------
#[tauri::command]
fn open_devtools(window: tauri::WebviewWindow) {
    window.open_devtools();
}

fn main() {
    // 0. 單一實例（以 exe 絕對路徑為準——重複啟動只 focus 舊窗口）
    if single_instance::claim_or_focus() {
        return;
    }

    // 1. 設定：config.toml → runtime\shell.json → 內建預設
    let (shell, warning) = config::load();
    if let Some(message) = warning {
        log::warn!("{}", message);
    }

    // 關窗時仲要用設定（複製一份交俾 run 嘅回呼）
    let shell_for_exit = shell.clone();

    tauri::Builder::default()
        // -------------------------------------------------------------------
        // 官方 plugin 1：窗口位置／尺寸記憶（DPI·多螢幕由官方處理）
        // -------------------------------------------------------------------
        .plugin(tauri_plugin_window_state::Builder::default().build())
        // -------------------------------------------------------------------
        // 官方 plugin 3：log
        // 同時寫 stdout 同 log 目錄（%LOCALAPPDATA%\<identifier>\logs\lab.log）
        // -------------------------------------------------------------------
        .plugin(
            tauri_plugin_log::Builder::default()
                .targets([
                    tauri_plugin_log::Target::new(tauri_plugin_log::TargetKind::Stdout),
                    tauri_plugin_log::Target::new(tauri_plugin_log::TargetKind::LogDir {
                        file_name: Some("hkai".to_string()),
                    }),
                ])
                .build(),
        )
        // -------------------------------------------------------------------
        // setup：App 建立後即刻交俾 startup（背景 thread）
        // -------------------------------------------------------------------
        .invoke_handler(tauri::generate_handler![open_devtools])
        .setup(move |app| {
            startup::run(app.handle().clone(), shell.clone());
            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("tauri build failed")
        // -------------------------------------------------------------------
        // 事件迴圈：關窗（ExitRequested）同真正退出（Exit）都殺子進程
        // servers::kill() 內部只做一次（AtomicBool 守門）
        // -------------------------------------------------------------------
        .run(move |_app_handle, event| match event {
            RunEvent::ExitRequested { .. } | RunEvent::Exit => servers::kill(&shell_for_exit),
            _ => {}
        });
}
