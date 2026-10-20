// jsmods/message/act/message_writer.js —— 打字機渲染引擎（act 層——named export message_writer）
// 結構同 evt/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）
// 職責：TypewriterTask（每 DOM 元素的打字動畫實體——時長制：target_duration_dec 毫秒寫完全文）；_message_writer（工廠——get_writer_instance 產出）
// 觸發點：write（寫入最新全文——自動接續印、自動補齊差距）／complete（立即寫完）／stop（凍結）
// 速率：時長制——每秒字數 = 全文長度 ÷ 時長秒；每次 write 重算（串流變長自動加速）；每幀推進 = 每秒字數 × 幀間隔秒（最少 1 字）
// [Child] 打字動畫實體（module 頂層——非 export；由 _message_writer.get_writer_instance() 產出，宿主自行持有）

class TypewriterTask {
    constructor() {
        this.element = null;
        this.full_target_text = "";
        this.current_rendered_length = 0;
        this.request_animation_frame_id = null;
        this.last_timestamp = 0;
        // 多久寫完（毫秒——預設 2000 = 2 秒；全文 N 字 → N ÷ (時長/1000) 字/秒）
        this.target_duration_dec = 2000;
        // 逐幀更新回調（捲動跟隨用）
        this.on_updated = null;
        // 寫完回調（complete／自然寫滿皆觸發）
        this.on_complete = null;
        // markdown 渲染回調（存在 → 輸出 HTML；不存在 → 純文字逐字）
        this.render_html = null;
        // 上次渲染的「完整行邊界」（未前進 → 只更新尾巴純文字，不重建 DOM）
        this.last_boundary_index = -1;
        this.tail_element = null;
    }

    // 設定選項（element／target_duration_dec／on_updated／on_complete／render_html——由宿主建立後呼叫一次）
    set_options(options) {
        if (options.element !== undefined) {
            this.element = options.element;
        }
        if (options.target_duration_dec !== undefined) {
            this.target_duration_dec = options.target_duration_dec;
        }
        if (options.on_updated !== undefined) {
            this.on_updated = options.on_updated;
        }
        if (options.on_complete !== undefined) {
            this.on_complete = options.on_complete;
        }
        if (options.render_html !== undefined) {
            this.render_html = options.render_html;
        }
    }

    // 輸出目前進度（無 render_html → 純文字逐字；有 → 以「完整行邊界」節流渲染 markdown）
    update_output(force_render) {
        // 判定：目標元素已脫離畫面（切換對話／重載／被移除）→ 自行終止（唔再對死元素打字）
        if (
            !this.element
            ||  !this.element.isConnected
        ) {
            this.stop();
            return;
        }

        const revealed_text = this.full_target_text.substring(0, this.current_rendered_length);

        // 判定：無 markdown 渲染回調 → 純文字直接輸出
        if (typeof this.render_html !== "function") {
            this.element.textContent = revealed_text;
            return;
        }

        const render_result = this.render_html(revealed_text);

        // 判定：非強制且完整行邊界未前進 → 只更新尾巴純文字（不重建 DOM——成本極低）
        if (
            !force_render
            &&  render_result.boundary_index === this.last_boundary_index
        ) {
            this.update_tail(render_result);
            return;
        }

        this.last_boundary_index = render_result.boundary_index;
        this.element.innerHTML = render_result.html;
        this.tail_element = this.element.querySelector(".sc_markdown_tail");
        this.update_tail(render_result);
    }

    // 更新尾巴純文字（未完成行——逐字長大；只動 textContent，不重建 DOM）
    update_tail(render_result) {
        // 判定：無尾巴元素 → 不動
        if (!this.tail_element) {
            return;
        }

        const revealed_text = this.full_target_text.substring(0, this.current_rendered_length);
        this.tail_element.textContent = revealed_text.substring(render_result.boundary_index);
    }

    // 寫入最新全文（自動從已印長度接續——補齊差距；未在動畫中才啟動）
    write(content) {
        this.full_target_text = content || "";

        if (!this.request_animation_frame_id) {
            this.start_typing_loop();
        }
    }

    // 動畫核心幀迴圈（rAF 逐幀：時長制速率 → substring 寫入 → on_updated → 印滿停機＋on_complete）
    start_typing_loop() {
        const thisObject = this;

        thisObject.last_timestamp = 0;

        const render_frame = function (current_timestamp) {
            if (!thisObject.last_timestamp) {
                thisObject.last_timestamp = current_timestamp;
            }
            const delta_time = (current_timestamp - thisObject.last_timestamp) / 1000;
            thisObject.last_timestamp = current_timestamp;

            if (thisObject.current_rendered_length < thisObject.full_target_text.length) {
                // 每秒字數（全文 ÷ 時長秒——每次 write 後全文變長自動加速）
                const chars_per_second = thisObject.full_target_text.length / (thisObject.target_duration_dec / 1000);
                // 本幀推進字數（最少 1 字）
                const chars_to_add = Math.max(1, Math.round(chars_per_second * delta_time));
                thisObject.current_rendered_length += chars_to_add;

                thisObject.update_output(false);

                if (typeof thisObject.on_updated === "function") {
                    thisObject.on_updated();
                }

                thisObject.request_animation_frame_id = requestAnimationFrame(render_frame);
            } else {
                // 印滿自動停機＋寫完回調
                thisObject.stop();
                if (typeof thisObject.on_complete === "function") {
                    thisObject.on_complete();
                }
            }
        };

        thisObject.request_animation_frame_id = requestAnimationFrame(render_frame);
    }

    // 立即寫完全文（停機＋on_complete）
    complete() {
        this.stop();
        this.current_rendered_length = this.full_target_text.length;
        this.update_output(true);
        if (typeof this.on_updated === "function") {
            this.on_updated();
        }
        if (typeof this.on_complete === "function") {
            this.on_complete();
        }
    }

    // 停寫凍結（取消 rAF——維持當前進度）
    stop() {
        if (this.request_animation_frame_id) {
            cancelAnimationFrame(this.request_animation_frame_id);
            this.request_animation_frame_id = null;
        }
    }
}

// [Mother] 打字機工廠單例——產出 TypewriterTask 實體（每 DOM 元素一實體；實體由宿主持有）
class _message_writer {
    constructor() {
        this.inited = false;
    }

    init() {
        if (this.inited) {
            return this;
        }
        this.inited = true;

        return this;
    }

    // 產出一個打字實體（宿主自行掛到 DOM 元素／map——manager 不追蹤）
    get_writer_instance() {
        return new TypewriterTask();
    }
}

export const message_writer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_writer());
