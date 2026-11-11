<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\extract;

// == sc_ai_chats attachment extract_text_from_docx：DOCX 文字抽取（ZipArchive → word/document.xml → 去 tag）（trait——掛入 attachment_Class） ==
// == 上游：prompt request upload_attachment（docx 分支） ==
// == 下游：store_uploaded_document；唔係真 docx → null（拒收） ==

trait extract_text_from_docx
{
    // DOCX 文字抽取（回字串；唔係 docx／無文字 → null）
    public function extract_text_from_docx($binary)
    {
        $temporary_path = tempnam(sys_get_temp_dir(), "hkai_docx_");

        // 判定：建暫存檔失敗 → 拒收
        if ($temporary_path === false) {
            return null;
        }

        file_put_contents($temporary_path, $binary);

        if (class_exists("ZipArchive") === false) {
            @unlink($temporary_path);
            return null;
        }

        $zip_handle = new \ZipArchive();
        $open_result = $zip_handle->open($temporary_path);

        // 判定：開唔到 zip → 清暫存＋拒收
        if ($open_result !== true) {
            @unlink($temporary_path);
            return null;
        }

        $document_xml = $zip_handle->getFromName("word/document.xml");
        $zip_handle->close();
        @unlink($temporary_path);

        // 判定：冇 document.xml → 唔係 docx
        if ($document_xml === false) {
            return null;
        }

        // 段落／換行／tab 先轉成文字符號（去 tag 後保留結構）
        $normalized_xml = str_replace(["</w:p>", "<w:br/>", "<w:tab/>"], ["\r\n", "\r\n", "\t"], $document_xml);

        $plain_text = strip_tags($normalized_xml);
        $plain_text = html_entity_decode($plain_text, ENT_QUOTES | ENT_XML1, "UTF-8");
        $plain_text = trim($plain_text);

        // 判定：抽唔到文字 → 拒收
        if ($plain_text === "") {
            return null;
        }

        return $plain_text;
    }
}
