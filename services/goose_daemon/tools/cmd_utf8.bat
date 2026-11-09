@echo off
rem cmd 包裝：轉 UTF-8 codepage 再交返真 cmd（解決中文輸出變亂碼）
chcp 65001 >nul
call "%SC_REAL_COMSPEC%" %*
