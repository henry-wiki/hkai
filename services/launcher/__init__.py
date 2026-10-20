#!/usr/bin/env python3
"""
launcher 套件——標準 daemon 連接器（PHP 同步 exec 冷啟動 → 按關鍵字＋旗標執行，自身即退）。

    main.py                    入口（薄——參數處理＋模式派發）

執行方式（由 PHP 冷啟動；cwd = services）：

    python -u -m launcher.main <daemon> [--bg] [--runtime <path>]

說明：
- <daemon>＝目標 daemon 關鍵字（例："bridge"）——launcher 自砌 `python -u -m {daemon}.main`（exe／-u／-m 唔使傳）
- --bg 有＝非阻塞（detached：無視窗／新程序群組／新 session）；冇＝阻塞（等完工、帶返 exit code）
- --runtime＝daemon 嘅 runtime.json（機器層值——PHP 單一來源）
- detach 同平台細節集中本套件（PHP 側零分支——Windows／Linux 同一句命令）
- 子進程唔承繼 handles（subprocess 默認 close_fds）——daemon 唔會揸住 PHP 嗰條 HTTP 連線：回應收口正常
"""
