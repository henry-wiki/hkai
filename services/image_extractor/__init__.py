#!/usr/bin/env python3
"""
image_extractor — 圖片資訊抽取套件（獨立進程；唔依附 bridge／daemon）。

職責：
- 逐張圖片抽取：檔案資訊／EXIF／色彩／清晰度／OCR／QR
- 每張圖寫一份 meta json（<image stem>.meta.json）
- 每張圖寫一份狀態檔（<idx>.status.json）——一個檔案一個真相
- 提供 run／stop／status CLI（bridge 用 HTTP 分發，PHP 唔直接跑 python）

上游：bridge（POST /image_extract/start｜stop）
下游：RapidOCR／OpenCV／Pillow；data/_tmp/image_extract/<message_id>/
"""
