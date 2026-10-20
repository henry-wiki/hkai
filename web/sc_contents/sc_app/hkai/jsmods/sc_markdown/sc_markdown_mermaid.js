// jsmods/sc_markdown/sc_markdown_mermaid.js —— Mermaid 圖表模組（default export——sc_markdown.js import 任取名）
// 職責：language-mermaid code block → 圖表（懶渲染：進視窗才做）
// 來源：CDN 傳統 script（header.php 宣告）→ 全域 window.mermaid

// == 輸出方式（改這一行即可切換；或執行期呼叫 set_render_mode()）==
//   "svg_dom" = 方式1：SVG 直插 DOM（矢量 ✓；DOM 節點多 ✗；大圖捲動偏卡 ✗）
//   "svg_uri" = 方式2：SVG → Blob URL <img>（矢量 ✓；DOM 只 1 節點 ✓；捲動順 ✓）← 預設
//   "canvas"  = 方式3：SVG → canvas → PNG <img>（點陣 ✗ 放大模糊；DOM 只 1 節點 ✓；捲動最順 ✓）
const RENDER_MODE = "svg_uri";

// 背景預渲染總開關（false → 停用；只停「背景預渲染」，「進視窗即時渲染」保留）
const MERMAID_PRE_RENDER_ENABLED = false;

// ══════════════════════════════════════════════════════════════════════════════
//  背景預渲染參數（全部可調——只影響「閒置時要不要先幫你把大圖做好」）
// ══════════════════════════════════════════════════════════════════════════════
//
// 【PRE_RENDER_SOURCE_MIN】粗篩門檻：以「mermaid 原文的字元數」計
//   為什麼需要它：
//     mermaid 真正的渲染成本取決於「渲染出來的 SVG 有多大」，
//     但要知道 SVG 多大，就必須先渲染一次——那正是我們想省掉的事（一次渲染會卡 2~5 秒）。
//     因此只能用「原文長度」當作「便宜的代理指標」：原文越長，通常圖越複雜、SVG 越大。
//   2048 這個數字代表什麼：
//     「原文少於 2048 個字元時，就當作它生不出大圖，直接跳過、不做預渲染。」
//   為什麼只是「粗略估計」（它的本質）：
//     這不是精準判斷，只是一個門檻——原文長度與 SVG 大小之間「沒有固定比例」：
//       原文 1KB 可能生出 100KB 的 SVG（小圖），也可能生出 2MB 的 SVG（大圖）。
//     所以它的作用只有兩個：
//       (1) 原文短 → 幾乎確定是小圖 → 省下渲染（小圖交給「進視窗時再畫」就好）
//       (2) 原文長 → 「有可能」是大圖 → 值得先花時間渲染看看
//   一句話：它只能「篩掉明顯的小圖」，不能「挑出確定的大圖」。
//   真正留不留，仍由第 2 段（渲染後量 SVG 大小 ≥ PRE_RENDER_SVG_MIN）決定。
//
// 【PRE_RENDER_SVG_MIN】精篩門檻：以「渲染後 SVG 字串長度」粗估位元組數
//   通過粗篩、實際渲染完之後才量；未達此大小 → 丟棄並還原成 code block（等進視窗再畫）。
//
// 【PRE_RENDER_MAX_COUNT】一次頁面載入最多自動預渲染幾張（避免無限做下去）
// 【PRE_RENDER_REST_MS】每張之間休息幾毫秒（讓瀏覽器有喘息空間，也避免連續占用主執行緒）
// 【PRE_RENDER_KEEP_MAX】預渲染結果最多保留幾張；超過 → 卸載最舊的（還原成 code block）
// 【PRE_RENDER_SCROLL_GUARD_MS】剛滾動過幾毫秒內不啟動（降低「正好在滾動瞬間啟動」的機率）
const PRE_RENDER_SOURCE_MIN      = 2 * 1024;    // 粗篩：原文 < 2048 字元 → 跳過（不渲染）
const PRE_RENDER_SVG_MIN         = 600 * 1024;  // 精篩：渲染後 SVG < 600K → 丟棄
const PRE_RENDER_MAX_COUNT       = 20;          // 自動預渲染上限（張）
const PRE_RENDER_REST_MS         = 3000;        // 每張之間休息 3 秒
const PRE_RENDER_KEEP_MAX        = 20;          // 保留上限（超過丟最舊）
const PRE_RENDER_SCROLL_GUARD_MS = 800;         // 剛滾動過 800ms 內 → 延後啟動

class _sc_markdown_mermaid {
    constructor() {
        this.inited         = false;
        this.render_mode    = RENDER_MODE;
        this.render_counter = 0;

        // 背景預渲染狀態
        this.pre_render_running    = false;   // 預渲染流程是否正在跑
        this.pre_render_done_count = 0;       // 已預渲染張數（達 PRE_RENDER_MAX_COUNT 停止）
        this.pre_render_queue      = [];      // 待預渲染的 code 元素（由最新訊息往上排）
        this.pre_rendered_list     = [];      // 已預渲染的 {element, source}（保留上限卸載用）
        this.last_scroll_time      = 0;       // 最後一次滾動時間（滾動保護用）
    }

    // 一次性 setup（lazy——第一次渲染自動叫；guard 防重）
    init() {
        if (this.inited) {
            return this;
        }
        this.inited = true;

        // mermaid 設定（不由載入自動渲染——由本模組控制時機）
        // == htmlLabels 取捨（單一設定，可隨時改回 true）==
        //   作用：決定節點標籤用「HTML 小島」還是「純 SVG 文字」渲染
        //   true（mermaid 預設）＝標籤用 HTML（foreignObject ＋ div/p/span）
        //       好處：長標籤自動換行、<br/> 生效、粗體等 HTML 格式可用
        //       壞處：每個節點都要跑 HTML 排版 → 大圖（上千節點）明顯變慢、捲動易卡；
        //             且 SVG 內含大量 HTML 標籤 → 交給 <img>（svg_uri／canvas）時，
        //             嚴格 XML 解析更容易失敗
        //   false（目前設定）＝標籤改用純 SVG <text>
        //       好處：省下 HTML 排版（大圖較快）；SVG 更乾淨、體積更小
        //             → 對 svg_uri／canvas 兩個模式更友善（嚴格 XML 較不易出錯）
        //       風險：長標籤不會自動換行，會衝出方框或與鄰節點重疊。
        //   註：只影響 flowchart（sequence／class／state 等其他圖種不受此設定影響）
        if (window.mermaid) {
            window.mermaid.initialize({
                startOnLoad: false,
                theme: "dark",
                flowchart: { htmlLabels: false },
            });
        }

        // 滾動保護：記錄最後滾動時間
        // 為什麼：requestIdleCallback 只保證「啟動那一刻」空閒，一旦開始渲染就無法中斷；
        //         若使用者正好在那一刻開始滾動，仍會卡一次 → 用「剛滾動過就延後」降低機率
        // capture=true：容器內捲動（非 window）也抓得到
        window.addEventListener("scroll", () => {
            this.last_scroll_time = Date.now();
        }, true);

        return this;
    }

    // 切換輸出方式（執行期也可改——已渲染的圖不重做）
    set_render_mode(render_mode) {
        this.render_mode = render_mode;
    }

    // 入口：掃出元素內所有 mermaid code block → 逐一登記懶渲染（首次自動 setup）
    render_all(j_element) {
        // 一次性 setup（self-guard——首次渲染自動；此後零成本）
        this.init();

        const j_mermaid_blocks = j_element.find("code.language-mermaid");

        // 判定：無 mermaid 區塊 → 不動
        if (!j_mermaid_blocks.length) {
            return;
        }

        j_mermaid_blocks.each((index, code_element) => {
            this.observe_block(jQuery(code_element));
        });
    }

    // 註冊「進入視窗才渲染」（不支援 IntersectionObserver → 直接渲染）
    observe_block(j_code_element) {
        const code_element = j_code_element[0];

        // 判定：瀏覽器不支援 IntersectionObserver → 立即渲染
        if (typeof IntersectionObserver !== "function") {
            this.render_block(j_code_element);
            return;
        }

        const mermaid_observer = new IntersectionObserver((entries, this_observer) => {
            entries.forEach((entry) => {
                // 判定：尚未進入視窗 → 繼續等待
                if (!entry.isIntersecting) {
                    return;
                }

                this_observer.unobserve(entry.target);
                this.render_block(jQuery(entry.target));
            });
        }, { rootMargin: "200px" });

        mermaid_observer.observe(code_element);
    }

    // 修復：標籤內嘅括號（mermaid 保留符號）→ 自動加引號隔開（節點＋箭嘴兩種標籤）
    // AI 生成常犯：C[全表掃描 O(N) I/O] 未引號 → parser 報錯（修後：C["全表掃描 O(N) I/O"]）
    //             A -->|通過(低風險)| B 一樣死（修後：A -->|"通過(低風險)"| B）
    // 判定範圍極窄：只郁「方括號內〔或雙柱之間〕、非開頭、含括號、未引號」嘅文字；其餘原文原封不動
    quote_labels_with_parentheses(mermaid_source) {
        // 節點標籤替換（[...] 內括號）
        const node_label_fixed = mermaid_source.replace(/\[(?!\()([^\[\]"\n]*[()][^\[\]"\n]*)\]/g, '["$1"]');

        // 箭嘴標籤替換（|...| 內括號——2026-10-07 實案：A5 -->|通過(低風險)| 令全圖 parse 死）
        return node_label_fixed.replace(/\|([^\|"\n]*[()][^\|"\n]*)\|/g, '|"$1"|');
    }

    // 數量判斷（單一元素）：有幾個 mermaid 區塊（code.language-mermaid）——數量知識住本模組（單一來源；呼叫端唔自己估）
    count_mermaid(j_element) {
        return j_element.find("code.language-mermaid").length;
    }

    // 單一區塊：渲染 → 依 render_mode 產生輸出（失敗 → 保留原純文字）
    async render_block(j_code_element) {
        const mermaid_source = j_code_element.text();
        // 先修復（未引號括號自動隔開）再 render；修唔到嘅一樣跌返 catch（原文保留）
        const quoted_source  = this.quote_labels_with_parentheses(mermaid_source);
        const render_id      = "sc_mermaid_" + (this.render_counter++);

        try {
            const render_result = await window.mermaid.render(render_id, quoted_source);
            const output_html   = await this.build_output_html(render_result.svg);
            j_code_element.parent("pre").replaceWith("<div class='sc_mermaid_diagram'>" + output_html + "</div>");
        } catch (render_error) {
            // 語法錯誤 → 標示原文塊（顯示層警告——唔可以靜靜咁死）＋console
            j_code_element.parent("pre").attr("data-render_failed", "1");
            console.error("mermaid render failed:", render_error.message);
        }
    }

    // 依 render_mode 產生要插入容器的 HTML（未知模式 → 退回 svg_uri）
    async build_output_html(svg_string) {
        // 判定：方式1 → SVG 直插 DOM
        //   svg_dom 走「寬鬆 HTML 解析器」，<br> 這類未閉合標籤不會出錯 → 不需正規化
        if (this.render_mode === "svg_dom") {
            return svg_string;
        }

        // 正規化為合法 XML（svg_uri／canvas 都必須——兩者都是把 SVG 交給 <img> 載入）
        // == 為什麼 ==
        //   <img src="blob:..."> 載入 SVG 時瀏覽器用「嚴格 XML 解析器」；
        //   而 mermaid 的 SVG 常含 HTML 風格標籤（未閉合的 <br>、foreignObject 內的 HTML）
        //   → 一遇標籤不匹配，整張圖直接失敗（實測：Opening and ending tag mismatch: br line 1 and p）
        // == 不做會怎樣 ==
        //   svg_uri／canvas 兩個模式「完全出不了圖」（只剩破圖與 console 錯誤）；
        //   唯一能用的只剩 svg_dom（但它 DOM 節點爆炸、大圖捲動卡）
        const well_formed_svg = this.normalize_svg(svg_string);

        // 判定：正規化失敗 → 退回直插 DOM（至少畫面有圖，代價是 DOM 節點多）
        if (well_formed_svg === "") {
            console.error("mermaid svg normalize failed: fallback to svg_dom");
            return svg_string;
        }

        // 判定：方式3 → canvas 轉 PNG（失敗自動退回方式2）
        if (this.render_mode === "canvas") {
            const canvas_html = await this.build_canvas_html(well_formed_svg);

            // 判定：canvas 轉換成功 → 用 canvas 結果
            if (canvas_html !== "") {
                return canvas_html;
            }
        }

        // 方式2（預設）：SVG → Blob URL <img>
        return this.build_svg_uri_html(well_formed_svg);
    }

    // 正規化 SVG 為「合法 XML」
    // == 為什麼需要 ==
    //   mermaid 產出的 SVG 常含 HTML 風格標籤（例如未閉合的 <br>——常見於 foreignObject 內，
    //   以及 sequence／class／state 等圖種；htmlLabels:false 只影響 flowchart，擋不到全部）
    //   而 <img> 載入 SVG 用的是「嚴格 XML 解析器」→ 標籤不匹配就整張圖失敗
    // == 不做會怎樣 ==
    //   svg_uri／canvas 直接壞掉（破圖）；只有 svg_dom 能用
    // == 做法 ==
    //   1. 用 DOMParser("text/html") 寬鬆解析 → 瀏覽器自動把 <br> 補成 <br/>
    //   2. 用 XMLSerializer 輸出 → 得到含 xmlns 的合法 XML
    // == 失敗時 ==
    //   回空字串；呼叫端據此退回 svg_dom（至少畫面有圖）
    normalize_svg(svg_string) {
        try {
            const svg_document = new DOMParser().parseFromString(svg_string, "text/html");
            const svg_element  = svg_document.querySelector("svg");

            // 判定：找不到 svg 元素 → 正規化失敗
            if (!svg_element) {
                return "";
            }

            return new XMLSerializer().serializeToString(svg_element);
        } catch (normalize_error) {
            console.error("mermaid svg normalize failed:", normalize_error.message);
            return "";
        }
    }

    // 方式2：SVG → Blob URL（矢量——放大不模糊；無 data-URI 長度上限、不膨脹）
    // 註1：傳入的 svg_string 必須是「已正規化的合法 XML」（見 build_output_html／normalize_svg）
    //      不做正規化就直接做 Blob → <img> 會因嚴格 XML 解析失敗而破圖
    // 註2：object URL 由瀏覽器在頁面卸載時自動釋放（本模組不主動 revoke——圖片載入後仍需要它）
    build_svg_uri_html(svg_string) {
        const svg_blob = new Blob([svg_string], { type: "image/svg+xml;charset=utf-8" });
        const svg_url  = URL.createObjectURL(svg_blob);

        return "<img src='" + svg_url + "' />";
    }

    // 方式3：SVG → canvas → PNG data-URI（點陣；失敗回空字串讓呼叫端退回方式2）
    // 註1：傳入的 svg_string 必須是「已正規化的合法 XML」（同 build_svg_uri_html）
    //      原因：canvas 也是靠 new Image() 載入 SVG，一樣走嚴格 XML 解析器 →
    //      不正規化會 onerror，轉不出圖（等同方式3 完全失效）
    // 註2：canvas 過大（超過瀏覽器上限）會拋錯 → catch 後回空字串 → 自動退回方式2
    async build_canvas_html(svg_string) {
        return new Promise((resolve) => {
            const svg_blob = new Blob([svg_string], { type: "image/svg+xml;charset=utf-8" });
            const svg_url  = URL.createObjectURL(svg_blob);
            const image    = new Image();

            image.onload = () => {
                try {
                    const pixel_ratio = window.devicePixelRatio || 1;

                    let image_width  = image.width;
                    let image_height = image.height;

                    // 判定：SVG 無固定寬高（width=100% 之類）→ 改取 viewBox 尺寸
                    if (image_width < 1 || image_height < 1) {
                        const view_box_match = svg_string.match(/viewBox=["']([^"']+)["']/);

                        // 判定：有 viewBox → 取第 3、4 個數值當寬高
                        if (view_box_match) {
                            const view_box_parts = view_box_match[1].split(/[\s,]+/);
                            image_width  = parseFloat(view_box_parts[2]);
                            image_height = parseFloat(view_box_parts[3]);
                        }
                    }

                    const canvas  = document.createElement("canvas");
                    canvas.width  = image_width * pixel_ratio;
                    canvas.height = image_height * pixel_ratio;

                    const canvas_ctx = canvas.getContext("2d");
                    canvas_ctx.scale(pixel_ratio, pixel_ratio);
                    canvas_ctx.drawImage(image, 0, 0);

                    resolve("<img src='" + canvas.toDataURL("image/png") + "' />");
                } catch (canvas_error) {
                    // 轉換失敗（如 canvas 過大）→ 回空字串
                    console.error("mermaid canvas failed:", canvas_error.message);
                    resolve("");
                } finally {
                    URL.revokeObjectURL(svg_url);
                }
            };
            image.onerror = () => {
                URL.revokeObjectURL(svg_url);
                resolve("");
            };

            image.src = svg_url;
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  背景預渲染（閒置時悄悄處理大圖）
    //  目的：你靜止不動時，先在背景把大圖做好；等你滾下去時已經就緒 → 不卡
    //  代價：預渲染好的圖會留在記憶體（由 PRE_RENDER_KEEP_MAX 控制上限）
    // ══════════════════════════════════════════════════════════════════════════

    // 啟動預渲染（頁面載入、歷史訊息渲染完後呼叫一次）
    // 為什麼：不啟動的話，大圖只能等你滾到才畫——那 2~5 秒就卡在你眼前
    // 不做會怎樣：捲動到每一張大圖都卡一次
    start_pre_render() {
        // 一次性 setup（self-guard——首次自動）
        this.init();

        // 判定：背景預渲染已停用 → 不做任何事
        if (!MERMAID_PRE_RENDER_ENABLED) {
            return;
        }

        // 判定：已在跑 → 不重複啟動
        if (this.pre_render_running) {
            return;
        }

        this.pre_render_running    = true;
        this.pre_render_done_count = 0;
        this.pre_render_queue      = this.collect_pre_render_targets();

        this.pre_render_sweep();
    }

    // 空閒時才執行（requestIdleCallback：使用者滾動時瀏覽器沒空 → 自動延後）
    // 為什麼：這是「可以慢、不可以卡」的核心——把工作排到瀏覽器空閒時段
    // 不做會怎樣：渲染會硬插在你操作的空檔，容易在你滾動時啟動而卡住
    idle_run(callback) {
        // 判定：瀏覽器支援 requestIdleCallback → 用它（滾動時自動延後）
        if (typeof window.requestIdleCallback === "function") {
            window.requestIdleCallback(callback, { timeout: 5000 });
            return;
        }

        // 後援：不支援（部分 WebView）→ 用 setTimeout 讓出主執行緒
        window.setTimeout(callback, 16);
    }

    // 滾動保護：最近是否剛滾動過
    // 為什麼：開始渲染後無法中斷；若使用者正好在那一刻開始滾動仍會卡一次
    // 不做會怎樣：更容易在你開始滾動的瞬間啟動渲染
    is_scroll_recent() {
        const elapsed_ms = Date.now() - this.last_scroll_time;
        return elapsed_ms < PRE_RENDER_SCROLL_GUARD_MS;
    }

    // 收集預渲染候選（由「最新訊息」往上）
    // 為什麼：最新回應最可能被看到；越舊的越少人往下翻
    // 不做會怎樣：順序錯亂，可能先去做很久以前的舊圖
    collect_pre_render_targets() {
        const target_list = [];
        const j_messages   = jQuery(".agent_message").toArray().reverse();

        // 逐訊息（新 → 舊）收集 mermaid 區塊
        j_messages.forEach((message_element) => {
            const j_blocks = jQuery(message_element).find("code.language-mermaid");

            j_blocks.each((index, code_element) => {
                // 判定：原文太短 → 粗篩跳過（見檔案頂端 PRE_RENDER_SOURCE_MIN 的完整說明）
                if (code_element.textContent.length < PRE_RENDER_SOURCE_MIN) {
                    return;
                }
                target_list.push(code_element);
            });
        });

        return target_list;
    }

    // 主流程：一次一張，做完休息 PRE_RENDER_REST_MS
    pre_render_sweep() {
        // 判定：已達張數上限 → 停止
        if (this.pre_render_done_count >= PRE_RENDER_MAX_COUNT) {
            this.pre_render_running = false;
            return;
        }
        // 判定：佇列已空 → 停止
        if (this.pre_render_queue.length === 0) {
            this.pre_render_running = false;
            return;
        }

        this.idle_run(async () => {
            // 判定：剛滾動過 → 延後 1 秒再排（避免正好在你滾動時啟動）
            if (this.is_scroll_recent()) {
                window.setTimeout(() => this.pre_render_sweep(), 1000);
                return;
            }

            const code_element = this.pre_render_queue.shift();

            // 判定：元素已不在文件（已進視窗渲染／已被手動重渲染）→ 跳過且不計數
            if (!code_element.isConnected) {
                this.pre_render_sweep();
                return;
            }

            await this.pre_render_one(code_element);

            this.pre_render_done_count++;
            window.setTimeout(() => this.pre_render_sweep(), PRE_RENDER_REST_MS);
        });
    }

    // 預渲染單張：成功且夠大 → 保留；太小 → 丟棄（還原成 code block）
    async pre_render_one(code_element) {
        const mermaid_source = code_element.textContent;
        // 先修復（同 render_block 同一支）；還原用嘅 source 照舊係原文
        const quoted_source  = this.quote_labels_with_parentheses(mermaid_source);
        const render_id      = "sc_mermaid_" + (this.render_counter++);

        try {
            const render_result = await window.mermaid.render(render_id, quoted_source);

            // 判定：SVG 太小（未達精篩門檻）→ 不值得保留，還原交給進視窗時處理
            if (!this.is_big_svg(render_result.svg)) {
                return;
            }

            const output_html = await this.build_output_html(render_result.svg);
            const j_diagram   = jQuery("<div class='sc_mermaid_diagram'>" + output_html + "</div>");

            jQuery(code_element).parent("pre").replaceWith(j_diagram);

            // 記錄（供保留上限卸載用——保留原文才能還原）
            this.pre_rendered_list.push({ element: j_diagram[0], source: mermaid_source });

            this.enforce_keep_max();
        } catch (render_error) {
            // 預渲染失敗 → 一樣標示（顯示層警告）＋console
            jQuery(code_element).parent("pre").attr("data-render_failed", "1");
            console.error("mermaid pre-render failed:", render_error.message);
        }
    }

    // 判斷是否為大圖（以渲染後 SVG 字串長度粗估位元組數）
    is_big_svg(svg_string) {
        return svg_string.length >= PRE_RENDER_SVG_MIN;
    }

    // 保留上限：預渲染結果太多 → 卸載最舊的（還原成 code block，並重新登記進視窗渲染）
    enforce_keep_max() {
        // 判定：未超過上限 → 不動
        if (this.pre_rendered_list.length <= PRE_RENDER_KEEP_MAX) {
            return;
        }

        const oldest_item = this.pre_rendered_list.shift();
        const j_oldest    = jQuery(oldest_item.element);

        // 判定：該元素已不在文件（已被人為移除）→ 不還原
        if (!j_oldest.length) {
            return;
        }

        const escaped_source = jQuery("<div>").text(oldest_item.source).html();
        const j_code         = jQuery("<pre><code class='language-mermaid'>" + escaped_source + "</code></pre>");

        j_oldest.replaceWith(j_code);

        // 還原後重新登記：等它再次進入視窗時才渲染
        this.observe_block(j_code.find("code"));
    }
}

export default (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_markdown_mermaid());
