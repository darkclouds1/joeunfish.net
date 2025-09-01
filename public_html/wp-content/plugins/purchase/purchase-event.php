<?php
/*
Plugin Name: Purchase Event Mgt.
Plugin URI: http://joeunfish.net/purchase
Description: 행사 관리
Version: 1.0
Author: 먹구름
Author URI: http://joeunfish.net
*/

function purchaseEventShortCode()
{
    date_default_timezone_set('Asia/Seoul');
    require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
    $dbConn = new dbconnClassFish;
    $platForm = $dbConn->MobileCheck();

    $userCan = array(
        'edit' => current_user_can('editor') || current_user_can('administrator')
    );

?>
    <!DOCTYPE html>
    <html lang="kr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>행사|기획판매</title>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js"></script>
        <!-- 행사 관리 .js-->
        <script src="/wp-content/plugins/purchase/purchase-eventOnly.js"></script>
        <!-- 행사 관리 .css-->
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/purchase/purchase.css" />
    </head>

    <body>
        <div class="form-container">
            <div class="container">
                <div class="form-menu" style="display: block;">
                    <div id="entry-title" style="float: left;"></div>
                    <div>
                        <span style="color: white; cursor: pointer;" > 기획|행사 내역 </span>
                    </div>
                </div>

            <!-- 행사 -->
            <div id="purchaseEventView" style="clear: both; align-items: center;">
                <div class="purchase-input-container search-row">
                    <input type="date" id="start-date-event" name="start-date-event" value="<?php echo date('Y-m-d', strtotime('-365 days')); ?>">
                    <label for="end-date-event">~</label>
                    <input type="date" id="end-date-event" name="end-date-event" value="<?php echo date('Y-m-d'); ?>">
                    <button id="search-btn-event">조회</button>
                </div>

                <div class="purchase-input-container input-row" style="display: none;">
                    <input type="date" id="increate-dt-event" value="<?php echo date('Y-m-d'); ?>" placeholder="행사일" onkeypress="focusNext(event, 'major-code-event')">
                    <select id="major-code-event" class="fixed-width" placeholder="행사#선택" onkeypress="focusNext(event, 'code-event')">
                        <option value="">분류#선택</option>
                    </select>
                    <select id="code-event" class="fixed-width" placeholder="기획#선택" onkeypress="focusNext(event, 'event-name')">
                        <option value="">기획#선택</option>
                    </select>
                    <input type="text" id="event-name" class="flex-grow" placeholder="행사명" maxlength="50" style="width: 200px;" onkeypress="focusNext(event, 'event-desc')">
                    <input type="text" id="event-desc" class="flex-grow" placeholder="행사설명" style="width: 400px;" onkeypress="focusNext(event, 'e-start-date')"></br>
                    <!-- <span class="date-group"> -->
                    <span style="margin-bottom: 10px; display: inline-block;">기간: </span>
                    <input type="date" id="e-start-date" value="<?php echo date('Y-m-d'); ?>" onkeypress="focusNext(event, 'end-date-event')">
                    <span style="margin-bottom: 10px; display: inline-block;">~</span>
                    <input type="date" id="e-end-date" value="<?php echo date('Y-m-d'); ?>" onkeypress="focusNext(event, 'prod-code-event')">
                    <select id="prod-code-event" class="fixed-width" placeholder="" onkeypress="focusNext(event, 'unit-price-event')">
                        <option value="">상품#선택</option>
                    </select>
                    <input type="text" id="unit-price-event" class="fixed-width" placeholder="단가" maxlength="6" style="width: 100px; text-align: right;" onkeypress="focusNext(event, 'target-qty-event')">
                    <input type="text" id="target-qty-event" class="fixed-width" placeholder="목표수량" maxlength="7" style="width: 100px; text-align: right;" onkeypress="focusNext(event, 'result-qty-event')">
                    <input type="text" id="result-qty-event" class="fixed-width" placeholder="실적수량" maxlength="7" style="width: 100px; text-align: right;" onkeypress="if(event.key === 'Enter') { purchaseEventClass.saveData(); return false; }">
                    <button id="insert-btn-event">입력</button>
                    <!-- </span> -->
                    <?php if ($platForm === 'computer') { ?>
                        <span style="font-size: 15px; color: red;">※명절|기념일 外 '기타행사'로 처리.</span>
                    <?php } ?>
                </div>

                <div class="actions" style="display: none;">
                    <button id="update-btn-event" class="action-button edit-button">수정</button>
                    <button id="delete-btn-event" class="action-button delete-button">삭제</button>
                </div>
            </div>
        </div>

        <!-- 데이터 테이블 -->
        <div class="table-container">
            <div id='tables'></div>
        </div>

    </body>

    <script>
        var platform = 'computer';
        var purchase_srv = '/wp-content/plugins/purchase/purchase_srv.php';
        var barcodeIn_srv = '/wp-content/plugins/barcodeIn/barcodeIn_srv.php';

        const userCan = {
            edit: <?php echo json_encode($userCan['edit']); ?>
        };

        if (window.matchMedia("only screen and (max-width: 760px)").matches) {
            platForm = 'mobile';
        } else {
            platForm = 'computer';
        }
        console.log('platForm=' + platForm);

        jQuery(document).ready(function($) {
            // PHP에서 전달받은 사용자 ID 사용
            user_no = myScriptData.userId; // wp에서 관리하는 ID
            user_id = myScriptData.userName; // 사용자ID(wp에서는 Name이라고 함)
            console.log('user_id=' + user_id + ', user_no=' + user_no + ', platForm=' + platForm);

            // 초기 데이터 로드
            showPurchaseEvent();
        });

        function focusNext(event, nextElementId) {
            if (event.key === 'Enter') {
                event.preventDefault(); // 기본 Enter 키 동작 방지
                const nextElement = document.getElementById(nextElementId);
                if (nextElement) {
                    nextElement.focus();
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            // 행사 조회 키보드 이벤트
            const startDateInput_event = document.getElementById("start-date-event");
            const endDateInput_event = document.getElementById("end-date-event");
            const searchButton_event = document.getElementById("search-btn-event");

            // -----------------------
            // 조회 버튼 이벤트
            // -----------------------
            // 행사 조회 키보드 이벤트
            startDateInput_event.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    endDateInput_event.focus(); // end-date로 포커스 이동
                }
            }); 
            endDateInput_event.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    searchButton_event.click(); // 조회 버튼 클릭
                }
            });
            // 조회 버튼 이벤트 end

            // ----------------------------
            // 타이틀 변경 및 뷰 보여주기
            // ----------------------------

            // 행사 
            function showPurchaseEvent() {
                // hideAllViews();
                // document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;이벤트::";
                // document.getElementById("purchaseEventView").style.display = "block";
                setTimeout(() => {
                    purchaseEventOnlyClass.loadData();
                }, 100);

                // 대분류 가져오기
                console.log("[ln177::showPurchaseEvent]fetchEventCode('000')");
                fetchEventCode('000');
            }

            // 모든 view를 숨기는 헬퍼 함수 ( 추가 view 없으면 사용안함.)
            function hideAllViews() {
                const views = [
                    "purchaseEventView"
                ];

                views.forEach(viewId => {
                    const element = document.getElementById(viewId);
                    if (element) {
                        element.style.display = "none";
                    }
                });
            }

            // 전역 범위에 함수 할당
            window.showPurchaseEvent = showPurchaseEvent; // 기획판매
        });
    </script>

    </html>
<?php
}

add_shortcode('purchase_event', 'purchaseEventShortCode');
?>