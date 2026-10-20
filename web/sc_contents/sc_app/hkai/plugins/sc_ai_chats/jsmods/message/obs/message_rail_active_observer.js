// jsmods/message/obs/message_rail_active_observer.js —— 導航點活躍觀察綁定（factory 單例——MutationObserver 盯 .sc_thread__scroll_rail 子樹：重建（childList）＋亮熄燈（class）；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——觀察 rail（childList＋subtree＋attributes〔class 白名單〕）；重掛先 disconnect；就緒即露面（add .on——顯示與否交 CSS））② 動作（refresh_active_dots——算活躍點清單／最細粒，寫返 rail attr（data-active_dots／data-active_min_dot）；判「最細粒 < data-message_limit」＝掂到窗口頭段 → 交 reached_top 同一門（入場閘＋政策閘內建））
// 只睇 message_list（.sc_thread__scroll_rail DOM——零後端）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_rail_active_observer {
    constructor() {
        // rail 觀察器（重掛時先 disconnect）
        this.rail_mutation_observer = null;
    }

    // 建立＋觀察（由載入收結呼叫——一次；掛好即露面）
    init() {
        const thisObject = this;

        // 判定：舊觀察器存在 → 先解除（重掛唔殘留）
        if (thisObject.rail_mutation_observer) {
            thisObject.rail_mutation_observer.disconnect();
            thisObject.rail_mutation_observer = null;
        }

        // 判定：瀏覽器唔支援 MutationObserver → 唔掛（維持基本行為）
        if (typeof MutationObserver !== "function") {
            return;
        }

        const rail_element = jQuery(".sc_thread__scroll_rail")[0];

        // 判定：rail 唔存在 → 唔掛觀察器
        if (!rail_element) {
            return;
        }

        // 觀察 rail（重建（childList）＋亮熄燈（class 白名單）都見到——一變即刷新；自身 attr（data-active_dots 等）唔喺白名單＝唔會自觸）
        thisObject.rail_mutation_observer = new MutationObserver(function () {
            thisObject.refresh_active_dots();
        });

        thisObject.rail_mutation_observer.observe(rail_element, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ["class"],
        });

        // 導航點露面（觀察器就緒先顯——顯示與否交 CSS 閘（.on）；一次露面、之後常駐——mlf／hook 都唔再掂）
        jQuery(rail_element).addClass("on");
    }

    // 活躍點刷新（算清單＋最細粒 → 寫 rail attr → 判「掂到窗口頭段」→ 交補載）
    async refresh_active_dots() {
        const j_rail = jQuery(".sc_thread__scroll_rail");
        const j_dots = j_rail.children(".msg_dot");

        const active_dot_numbers = [];

        // 逐粒點（DOM 次序＝窗口次序——1＝最舊；活躍＝.active）
        for (let index = 0; index < j_dots.length; index++) {
            const j_dot = jQuery(j_dots[index]);

            // 判定：活躍（可見）→ 記低粒號（第 index + 1 粒）
            if (j_dot.hasClass("active")) {
                active_dot_numbers.push(index + 1);
            }
        }

        // 寫返 rail attr（清單＝逗號連（由細到大）；最細粒＝頭一個——冇活躍＝空字串）
        const active_dots_text    = active_dot_numbers.join(",");
        const active_min_dot_text = active_dot_numbers.length > 0 ? String(active_dot_numbers[0]) : "";

        j_rail.attr("data-active_dots", active_dots_text);
        j_rail.attr("data-active_min_dot", active_min_dot_text);

        // 判定：冇活躍點（重建中／空窗——冇最細粒可比）→ 唔判補載
        if (active_dot_numbers.length === 0) {
            return;
        }

        // 頁大（.sc_thread__viewport——窗口界 SSOT）
        const j_messages = jQuery(".sc_thread__viewport");
        const message_limit = parseInt(j_messages.attr("data-message_limit"), 10);

        // 判定：最細粒冇細過頁大（≥ 或讀唔到——活躍範圍未掂到窗口頭段）→ 唔補載，回頭
        if (!(active_dot_numbers[0] < message_limit)) {
            return;
        }

        // 橋接駁（同域 obs——交 reached_top 同一門：內判兩閘＋向上補載）
        const message_instance = await registry().sc_ai_chats().message();
        const message_intersection_observer_instance = await message_instance.obs().message_intersection_observer();

        message_intersection_observer_instance.reached_top();
    }
}

export const message_rail_active_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_rail_active_observer());
