<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\extract;

// == sc_ai_chats attachment extract_text_from_pdf：PDF 文字層抽取（FlateDecode 串流 → Tj/TJ 文字指令）（trait——掛入 attachment_Class） ==
// == 上游：prompt request upload_attachment（pdf 分支） ==
// == 下游：store_uploaded_document；掃描版（無文字層）／加密 → null（拒收） ==

trait extract_text_from_pdf
{
    // PDF 串流處理上限（個數——防爆）
    private $pdf_max_stream_count = 200;

    // PDF 文字抽取（回字串；抽唔到／加密 → null）
    public function extract_text_from_pdf($binary)
    {
        $plain_text      = "";
        $search_offset   = 0;
        $processed_count = 0;

        // 逐個 stream 區塊抽文字（上限個數——防爆）
        while ($processed_count < $this->pdf_max_stream_count) {
            $stream_index = strpos($binary, "stream", $search_offset);

            // 判定：再冇 stream → 完
            if ($stream_index === false) {
                break;
            }

            $content_start = $stream_index + 6;

            // 跳過 stream 之後嘅 CR
            while (substr($binary, $content_start, 1) === "\r") {
                $content_start = $content_start + 1;
            }

            // 跳過 stream 之後嘅 LF
            while (substr($binary, $content_start, 1) === "\n") {
                $content_start = $content_start + 1;
            }

            $end_index = strpos($binary, "endstream", $content_start);

            // 判定：冇 endstream → 結構壞，停
            if ($end_index === false) {
                break;
            }

            $raw_stream     = substr($binary, $content_start, $end_index - $content_start);
            $decoded_stream = @gzuncompress($raw_stream);

            // 判定：解壓成功（FlateDecode）→ 抽文字指令
            if ($decoded_stream !== false) {
                $plain_text = $plain_text . " " . $this->extract_pdf_text_operators($decoded_stream);
            }

            $search_offset   = $end_index + 9;
            $processed_count = $processed_count + 1;
        }

        $plain_text = str_replace(["\r\n", "\r", "\n", "\t"], " ", $plain_text);
        $plain_text = trim($plain_text);

        // 判定：抽唔到文字（掃描版／加密）→ 拒收
        if ($plain_text === "") {
            return null;
        }

        return $plain_text;
    }

    // 由 PDF 內容串流抽文字（(..)Tj 與 [..]TJ 兩種指令；\x5c＝反斜線）
    private function extract_pdf_text_operators($stream_content)
    {
        $text_value = "";

        // 單字串（(..)Tj）
        $single_matches = [];
        preg_match_all('#\((?:\x5c.|[^\x5c()])*\)\s*Tj#s', $stream_content, $single_matches);

        // 逐個配對結果併入
        foreach ($single_matches[0] as $single_block) {
            $text_value = $text_value . " " . $this->extract_pdf_text_operands($single_block);
        }

        // 陣列（[..]TJ）
        $array_matches = [];
        preg_match_all('#\[((?:[^\[\]]|\x5c.)*)\]\s*TJ#s', $stream_content, $array_matches);

        // 逐個陣列併入
        foreach ($array_matches[1] as $array_text) {
            $text_value = $text_value . " " . $this->extract_pdf_text_operands($array_text);
        }

        return $text_value;
    }

    // 由括號字串抽出可讀文字（還原 PDF 轉義：\( \) \\ \n \r \t）
    private function extract_pdf_text_operands($segment_text)
    {
        $backslash = chr(92);

        // 逐段抽出 (..) 內文（\＝反斜線）
        $operand_matches = [];
        preg_match_all('#\(((?:\x5c.|[^\x5c()])*)\)#s', $segment_text, $operand_matches);

        $escape_map                 = [];
        $escape_map[$backslash . "("]  = "(";
        $escape_map[$backslash . ")"]  = ")";
        $escape_map[$backslash . $backslash] = $backslash;
        $escape_map[$backslash . "n"]  = "\r\n";
        $escape_map[$backslash . "r"]  = "";
        $escape_map[$backslash . "t"]  = "\t";

        $text_value = "";

        // 逐個操作數還原轉義後併入
        foreach ($operand_matches[1] as $operand_text) {
            $text_value = $text_value . strtr($operand_text, $escape_map);
        }

        return $text_value;
    }
}
