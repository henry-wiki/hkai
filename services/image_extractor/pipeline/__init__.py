#!/usr/bin/env python3
"""
pipeline — 圖片抽取分層（一層一檔）。

basic.py   檔案層（Pillow）：尺寸／格式／模式／EXIF／sha256
visual.py  視覺層（OpenCV）：亮度／對比／模糊值／主色
ocr.py     文字層（RapidOCR）：全文＋每行 bbox＋信心
qr.py      QR／條碼（OpenCV 內建）
"""
