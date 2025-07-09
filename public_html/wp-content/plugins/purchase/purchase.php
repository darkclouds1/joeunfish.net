<?php
/*
Plugin Name: Purchase Management
Plugin URI: http://joeunfish.online/test/purchase
Description: 업체매입관리
Version: 1.3
Author: 먹구름
Author URI: http://joeunfish.online
*/

function purchaseShortCode()
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
        <title>통관|매입</title>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js"></script>

        <script src="/wp-content/plugins/purchase/purchase-imp.js"></script>
        <script src="/wp-content/plugins/purchase/purchase.js"></script>
        <script src="/wp-content/plugins/purchase/purchase-in.js"></script>
        <!-- <script src="/wp-content/plugins/purchase/purchase-out.js"></script> -->
        <script src="/wp-content/plugins/purchase/purchase-event.js"></script>

        <!-- 엑셀 업로드 -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/purchase/purchase.css" />
    </head>

    <body>
        <div class="form-container">
            <div class="container">
                <div class="form-menu">
                    <div id="entry-title" style="float: left;">&nbsp;&nbsp;수입::</div>
                    <div>
                        <span style="color: white; cursor: pointer;" onclick="showImport()">수입</span> |
                        <span style="color: white; cursor: pointer;" onclick="showPurchase()">매입 </span> |
                        <span style="color: white; cursor: pointer;" onclick="showPurchaseIn()">자재 </span> |
                        <!-- <span style="color: white; cursor: pointer;" onclick="showPurchaseOut()">판매 </span> | -->
                        <span style="color: white; cursor: pointer;" onclick="showPurchaseEvent()">행사 &nbsp;&nbsp; </span>
                    </div>
                </div>

                <!-- 수입 -->
                <div id="importView" style="display: block; clear: both; align-items: center;">
                    <!-- 1번째 줄: 조회 영역 -->
                    <div class="purchase-input-container search-row">
                        <input type="date" id="start-date-imp" name="start-date-imp" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>">
                        <label for="end-date-imp">~</label>
                        <input type="date" id="end-date-imp" name="end-date-imp" value="<?php echo date('Y-m-d'); ?>">
                        <button id="search-btn-imp">조회</button>
                    </div>

                    <div class="purchase-input-container input-row">
                        <input type="date" id="import-dt" value="<?php echo date('Y-m-d'); ?>" placeholder="통관일">
                        <input type="text" id="spec" class="fixed-width" placeholder="제품명" maxlength="100" onkeypress="focusNext(event, 'vol')">
                        <input type="text" id="vol" class="" placeholder="수량(박스)" " maxlength=" 10" style="width: 100px;" onkeypress="focusNext(event, 'awb')">
                        <input type="text" id="awb" class="fixed-width" placeholder="B/L번호" " maxlength=" 50" onkeypress="focusNext(event, 'weight-imp')">
                        <input type="text" id="weight-imp" class="flex-grow" placeholder="Weight(kg)" step="0.01" " maxlength=" 13" style="width: 100px;" onkeypress="focusNext(event, 'remarks-imp')">

                        <input type="text" id="remarks-imp" class="flex-grow" placeholder="비고" maxlength="50" onkeypress="if(event.key === 'Enter') { purchaseImpClass.saveData(); return false; }">
                        <button id="insert-btn-imp">입력</button>
                    </div>

                    <div class="actions">
                        <button id="update-btn-imp" class="action-button edit-button">수정</button>
                        <button id="delete-btn-imp" class="action-button delete-button">삭제</button>
                        <button id="excel-btn-imp" class="action-button excel-button" onclick="impExcel()">엑셀</button>
                    </div>
                    <div class="excel-upload" style="display: <?php echo $platForm === 'mobile' ? 'none' : 'block'; ?>">
                        <button onclick="handleExcelUpload()">Upload</button>
                        <input type="file" id="excelFile" accept=".xlsx, .xls" >
                    </div>
                </div>
            </div>

            <!-- 매입-->
            <div id="purchaseView" style="display: none; clear: both; align-items: center;">
                <!-- 1번째 줄: 조회 영역 -->
                <div class="purchase-input-container search-row">
                    <input type="date" id="start-date" name="start-date" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>">
                    <label for="end-date">~</label>
                    <input type="date" id="end-date" name="end-date" value="<?php echo date('Y-m-d'); ?>">
                    <button id="search-btn">조회</button>
                </div>

                <div class="purchase-input-container input-row">
                    <input type="date" id="input-dt" value="<?php echo date('Y-m-d'); ?>" placeholder="매입일">
                    <select id="mat-cd" class="fixed-width" placeholder="자재명" maxlength="5" style="width: 200px;" onkeypress="focusNext(event, 'vendor-nm')">
                        <option value="">자재명</option>
                    </select>
                    <input type="text" id="vendor-nm" class="fixed-width" placeholder="업체명" maxlength="10" style="width: 150px;" onkeypress=" focusNext(event, 'box' )">
                    <input type="text" id="box" class="fixed-width" placeholder="BOX 수" maxlength="10" style="width: 100px;" onkeypress=" focusNext(event, 'weight' )">
                    <input type="text" id="weight" class="fixed-width" placeholder="Weight(kg)" maxlength="13" style="width: 100px;" onkeypress=" focusNext(event, 'remarks' )">
                    <input type="text" id="remarks" class="flex-grow" placeholder="비고" maxlength="50" onkeypress=" focusNext(event, 'unitPrice' )">
                    <input type=" text" id="unitPrice" class="flex-grow" placeholder="단가" maxlength="9" style="width: 150px;" onkeypress=" if(event.key==='Enter' ) { purchaseClass.saveData(); return false; }">
                    <button id="insert-btn">입력</button>
                </div>

                <div class="actions">
                    <button id="update-btn" class="action-button edit-button">수정</button>
                    <button id="delete-btn" class="action-button delete-button">삭제</button>
                </div>
            </div>

            <!-- 자재 -->
            <div id="purchaseInView" style="display: none; clear: both; align-items: center;">
                <!-- 1번째 줄: 조회 영역 -->
                <div class="purchase-input-container search-row">
                    <input type="date" id="start-date-in" name="start-date-in" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>">
                    <label for="end-date-in">~</label>
                    <input type="date" id="end-date-in" name="end-date-in" value="<?php echo date('Y-m-d'); ?>">
                    <button id="search-btn-in">조회</button>
                </div>

                <div class="purchase-input-container input-row">
                    <input type="date" id="create-dt-in" value="<?php echo date('Y-m-d'); ?>" placeholder="입고일" onkeypress="focusNext(event, 'qty-in')">
                    <select id="mat-cd-in" class="fixed-width" placeholder="자재명" maxlength="5" style="width: 200px;" readonly onkeypress="focusNext(event, 'qty-in')">
                        <option value="">자재명</option>
                    </select>
                    <input type="text" id="lot-nm-in" class="fixed-width" placeholder="Lot명" maxlength="10" readonly onkeypress="focusNext(event, 'qty-in')">
                    <input type="text" id="qty-in" class="fixed-width" placeholder="입고량" maxlength="10" onkeypress="if(event.key === 'Enter') { purchaseInClass.updateData(); return false; }">
                    <input type="text" id="remarks-in" class="flex-grow" placeholder="비고" maxlength="50" readonly onkeypress="focusNext(event, 'qty-in')">
                </div>

                <div class="actions">
                    <button id="update-btn-in" class="action-button edit-button">수정</button>
                    <button id="delete-btn-in" class="action-button delete-button">삭제</button>
                    <button id="redirect-btn-imp" class="action-button" style="width: 110px !important;" onclick="window.location.href='/dayProd/'">일일생산</button>

                </div>
            </div>

            <!-- 판매 -->
            <div id="purchaseOutView" style="display: none; clear: both; align-items: center;">
                <!-- 1번째 줄: 조회 영역 -->
                <div class="purchase-input-container search-row">
                    <input type="date" id="start-date-out" name="start-date-out" value="<?php echo date('Y-m-d', strtotime('-1 week')); ?>">
                    <label for="end-date-out">~</label>
                    <input type="date" id="end-date-out" name="end-date-out" value="<?php echo date('Y-m-d'); ?>">
                    <button id="search-btn-out">조회</button>
                </div>

                <div class="purchase-input-container input-row">
                    <input type="date" id="create-dt-out" value="<?php echo date('Y-m-d'); ?>" placeholder="판매일" onkeypress="focusNext(event, 'prod-code')">
                    <select id="prod-code" class="fixed-width" placeholder="상품명" style="width: 200px;" onkeypress="focusNext(event, 'lc-no')">
                        <option value="">상품명#선택</option>
                        <option value="2000000560770">연어필렛</option>
                        <option value="0200000651343">연어(스테이크)</option>
                        <option value="2000000668592">연어(스테이크용)벌크</option>
                    </select>
                    <select id="lc-no" class="fixed-width" placeholder="B/L#선택" maxlength="4" onkeypress="focusNext(event, 'origin-store-code')" autofocus onchange="handleSelect(this)">
                        <option value="">B/L#선택</option>
                    </select>

                    <select id="origin-store-code" class="fixed-width" placeholder="점번호" " onkeypress=" focusNext(event, 'store-code' )">
                        <option value="">점번호#선택</option>
                    </select>
                    <select id="store-code" class="fixed-width" placeholder="판매점" " onkeypress=" focusNext(event, 'store-no' )">
                        <option value="">판매점#선택</option>
                    </select>

                    <input type="text" id="store-no" class="flex-grow" placeholder="판매점(비고)" maxlength="20" onkeypress="focusNext(event, 'weight-out')">
                    <input type="text" id="weight-out" class="fixed-width" placeholder="판매량(kg)" " maxlength=" 7" style="width: 150px;" onkeypress="if(event.key === 'Enter') { purchaseOutClass.saveData(); return false; }">
                    <button id="insert-btn-out">입력</button>
                </div>
                <?php if ($platForm === 'computer') { ?>
                    <span style="font-size: 15x; color: red;">※개인점은 생산 '+추가' 개인점으로 판매. 그 외 판매점으로 판매(생산점 재고에서 '-마이너스' 처리).</span>
                <?php } ?>

                <div class="actions">
                    <button id="update-btn-out" class="action-button edit-button">수정</button>
                    <button id="delete-btn-out" class="action-button delete-button">삭제</button>
                </div>
            </div>

            <!-- 행사 -->
            <div id="purchaseEventView" style="display: none; clear: both; align-items: center;">
                <div class="purchase-input-container search-row">
                    <input type="date" id="start-date-event" name="start-date-event" value="<?php echo date('Y-m-d', strtotime('-365 days')); ?>">
                    <label for="end-date-event">~</label>
                    <input type="date" id="end-date-event" name="end-date-event" value="<?php echo date('Y-m-d'); ?>">
                    <button id="search-btn-event">조회</button>
                </div>

                <div class="purchase-input-container input-row">
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
                </div>
                <?php if ($platForm === 'computer') { ?>
                    <span style="font-size: 15px; color: red;">※명절|기념일 外 '기타행사'로 처리.</span>
                <?php } ?>

                <div class="actions">
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
            purchaseImpClass.loadData();

        });

        // 판매:: 점번호 선택 이벤트 리스너 추가
        document.getElementById('origin-store-code').addEventListener('change', function() {
            const storeCodeSelect = document.getElementById('store-code');
            // 006(기타점)이 선택된 경우
            if (this.value === '006') {
                // 006(기타점)이 선택된 경우 판매점을 006으로 설정
                storeCodeSelect.value = '006';
            } else if (this.value === '') {
                // 점번호#선택이 선택된 경우 판매점도 기본값으로 설정
                storeCodeSelect.value = '';
            }
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
            // 수입통관 조회 키보드 이벤트
            const startDateInput_imp = document.getElementById("start-date-imp");
            const endDateInput_imp = document.getElementById("end-date-imp");
            const searchButton_imp = document.getElementById("search-btn-imp");

            // 업체매입 조회 키보드 이벤트
            const startDateInput = document.getElementById("start-date");
            const endDateInput = document.getElementById("end-date");
            const searchButton = document.getElementById("search-btn");

            // 자재 조회 키보드 이벤트
            const startDateInput_in = document.getElementById("start-date-in");
            const endDateInput_in = document.getElementById("end-date-in");
            const searchButton_in = document.getElementById("search-btn-in");

            // 판매 조회 키보드 이벤트
            // const startDateInput_out = document.getElementById("start-date-out");
            // const endDateInput_out = document.getElementById("end-date-out");
            // const searchButton_out = document.getElementById("search-btn-out");

            // 행사 조회 키보드 이벤트
            const startDateInput_event = document.getElementById("start-date-event");
            const endDateInput_event = document.getElementById("end-date-event");
            const searchButton_event = document.getElementById("search-btn-event");

            // -----------------------
            // 조회 버튼 이벤트
            // -----------------------
            // 수입통관 조회 키보드 이벤트
            startDateInput_imp.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    endDateInput_imp.focus(); // end-date로 포커스 이동
                }
            });
            endDateInput_imp.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    searchButton_imp.click(); // 조회 버튼 클릭
                }
            });

            // 업체매입 조회 키보드 이벤트
            startDateInput.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    endDateInput.focus(); // end-date로 포커스 이동
                }
            });
            endDateInput.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    searchButton.click(); // 조회 버튼 클릭
                }
                fetchMaterialList();
            });

            // 자재이동 조회 키보드 이벤트
            startDateInput_in.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    endDateInput_in.focus(); // end-date로 포커스 이동
                }
            });
            endDateInput_in.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    searchButton_in.click(); // 조회 버튼 클릭
                }
            });

            // // 개인판매 조회 키보드 이벤트
            // startDateInput_out.addEventListener("keypress", function(event) {
            //     if (event.key === "Enter") {
            //         event.preventDefault(); // 기본 Enter 키 동작 방지
            //         endDateInput_out.focus(); // end-date로 포커스 이동
            //     }
            // });
            // endDateInput_out.addEventListener("keypress", function(event) {
            //     if (event.key === "Enter") {
            //         event.preventDefault(); // 기본 Enter 키 동작 방지
            //         searchButton_out.click(); // 조회 버튼 클릭
            //     }
            // });

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
            // 수입통관
            function showImport() {
                // 모든 view를 먼저 숨김
                hideAllViews();
                // 타이틀 변경
                document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;수입::";
                // 해당 view만 표시
                document.getElementById("importView").style.display = "block";
                // 데이터 로드
                setTimeout(() => {
                    purchaseImpClass.loadData();
                }, 100);
            }

            // 업체매입
            function showPurchase() {
                hideAllViews();
                document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;매입::";
                document.getElementById("purchaseView").style.display = "block";
                setTimeout(() => {
                    purchaseClass.loadData();
                }, 100);
                // 자재명 목록 조회
                purchaseClass.fetchMaterialList();
            }

            // 자재
            function showPurchaseIn() {
                hideAllViews();
                document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;자재::";
                document.getElementById("purchaseInView").style.display = "block";
                setTimeout(() => {
                    purchaseInClass.loadData();
                }, 100);
                // 자재명 목록 조회
                purchaseInClass.fetchMaterialList();
            }

            // 개인판매
            // function showPurchaseOut() {
            //     hideAllViews();
            //     document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;판매::";
            //     document.getElementById("purchaseOutView").style.display = "block";
            //     setTimeout(() => {
            //         purchaseOutClass.loadData();
            //         fetchStoreList();
            //     }, 100);
            // }

            // 행사 
            function showPurchaseEvent() {
                hideAllViews();
                document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;이벤트::";
                document.getElementById("purchaseEventView").style.display = "block";
                setTimeout(() => {
                    purchaseEventClass.loadData();
                }, 100);

                // 대분류 가져오기
                console.log("[ln468::showPurchaseEvent]fetchEventCode('000')");
                fetchEventCode('000');
            }

            // 모든 view를 숨기는 헬퍼 함수
            function hideAllViews() {
                const views = [
                    "importView",
                    "purchaseView",
                    "purchaseInView",
                    // "purchaseOutView",
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
            window.showImport = showImport; // 수입
            window.showPurchase = showPurchase; // 매입
            window.showPurchaseIn = showPurchaseIn; // 자재이동
            // window.showPurchaseOut = showPurchaseOut; // 판매
            window.showPurchaseEvent = showPurchaseEvent; // 행사
        });
    </script>

    </html>
<?php
}

add_shortcode('purchase_management', 'purchaseShortCode');
?>