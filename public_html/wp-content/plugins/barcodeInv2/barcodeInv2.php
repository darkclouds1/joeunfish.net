<?php
/*
 Plugin Name: barcodeInv2 : (CO)1층출고(프린터출력)
 Plugin URI: https://joeunfish.net
 Description: 바코드출고(INVOICE2 출력)
 Version: 1.1
 Author: Monolith
 Author URI: https://joeunfish.net
 License: GPL2
 */

function barcodeInv2ShortCode()
{
    date_default_timezone_set('Asia/Seoul');
    require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

    $dbConn   = new dbconnClassFish;
    $platForm = $dbConn->MobileCheck();

?>
    <!DOCTYPE html>
    <html lang="kr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>barcode scanner IN</title>

        <script src="/wp-content/plugins/barcodeInv2/CodeInv2Class.js"></script>
        <script src="/wp-content/plugins/barcodeInv2/CodeInv2SumClass.js"></script>

        <script>
            // 인스턴스 생성을 최우선으로 실행
            var codeOutClass = new CodeInvClass();
            var codeSumClass = new CodeInvSumClass();
        </script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js" async></script>
        <!-- css -->
        <link rel="stylesheet" type="text/css" href="/jof/css/jofInv2.css" />

    </head>

    <body>
        <div id="contents">
            <div class="form-container">
                <form action="" name="myForm" id="myForm" method="get">
                    <!-- 첫 번째 행 -->
                    <div class="form-row">
                        <select name="category" class="form-field" placeholder="입력" id="category">
                            <option value="1">납기</option>
                            <option value="2">조회</option>
                        </select>
                        <input type="date" class="form-field now-date" id="createDate">
                        <select class="form-field barcode-scan" placeholder="점포#" id="storeNo" onchange="document.getElementById('actionBtn').click();">
                            <option value="">점포#</option>
                        </select>
                    </div>
                </form>

                <div class="button-container" id="confirmBtn">
                    <button id="actionBtn" style="width: 50px;">조회</button>
                    <?php if ($platForm == 'computer') {    ?>
                        <!-- 송장출력 -->
                        <button id="invPrint">INVOICE</button>
                        <button id="ordPrint">주문출력</button>
                        <!-- <button id="invBtn">INV출력</button> -->
                        <button id="toExcel" onclick="tableToExcelInv()">엑셀다운</button>
                    <?php } ?>
                    <button id="manual">사용법</button>
                </div>


                <div id="Explain">
                    <div id='totalrec'></div>
                </div>

                <!-- 메시지 알림-->
                <div class="notification-container" id="notification-container">
                    <p>
                        <font color="white">기업개요 실시간 업데이트를 완료하였습니다.</font>
                    </p>
                </div>

                <div id='tables' class="type10" style="text-align: left; border: 0px solid #dddddd; word-break:break-all;"></div>

                <div id='loading' style='display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;'>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                        <img src="/img/loading/0.gif" alt="로딩중">
                    </div>
                </div>

                <!-- table 건수 요약 -->
                <div id='totalrec'></div>

                <!-- AJAX로 불러온 목록이 여기에 표시됩니다. -->
                <div id="listContainer"></div>

                <!-- 메시지 알림-->
                <div class="notification-container" id="notification-container">
                    <p>
                        <font color="white">APT 실거래 업데이트를 완료하였습니다.</font>
                    </p>
                </div>
                <div id='tables' class="type10" style="text-align: left; border: 0px solid #dddddd; word-break:break-all;"></div>

            </div>
        </div>

        <!-- jQuery body 맨아래 -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    </body>
    <script>
        var platForm = 'computer';
        if (window.matchMedia("only screen and (max-width: 760px)").matches) {
            // 화면의 너비가 760px 이하인 경우 모바일로 간주
            platForm = 'mobile';
        } else {
            // 그렇지 않은 경우 데스크탑으로 간주
            platForm = 'computer';
        }
        console.log('platForm=' + platForm);
    </script>

    </html>

    <script>
        var newItem = []; // 입력 barcode Item array
        var rtnCnt = 0; // 가져온 Data갯수
        var idx = 0; // tables Row 갯수
        var parm = ""; // kwd
        var totalWeight = 0;
        var barcodeList = []; // 목록 데이터를 저장할 배열
        var wpHomeUrl = '<?php echo esc_js(home_url()); ?>';
        let table1;
        jQuery(document).ready(function($) {
            // PHP에서 전달받은 사용자 ID 사용
            user_no = myScriptData.userId; // wp에서 관리하는 ID
            user_id = myScriptData.userName; // 사용자ID(wp에서는 Name이라고 함)

            console.log(user_id);
            loadStoreOptions('c1');

            // manual 버튼 이벤트 리스너 추가
            const manualBtn = document.getElementById('manual');
            if (manualBtn) {
                manualBtn.addEventListener('click', function() {
                    window.open(wpHomeUrl + '/출고사용설명서/', '_blank');
                });
            }

        });

        // 페이지 로드 시 특정 지역의 체크박스를 체크하고 입력란을 비우는 함수
        window.onload = function() {
            const items = document.querySelectorAll('.list-item');
            items.forEach(item => {
                const region = item.querySelector('.item').textContent.match(/\[납품점\]\s*([^\[]+)/)[1].trim();
                if (isSpecialRegion(region)) {
                    const idx = item.id.replace('item-no', '');
                    const checkbox = document.getElementById(`expi_date-checkbox-${idx}`);
                    const input = document.getElementById(`expi_date-${idx}`);

                    if (checkbox && input) {
                        checkbox.checked = true;
                        input.setAttribute('data-temp', input.value);
                        input.value = '';
                        input.readOnly = true;
                    }
                }
            });
        }

        // 페이지 로딩 시 초기 category 값에 따른 버튼 텍스트 설정
        document.getElementById('category').dispatchEvent(new Event('change'));

        // 현재 날짜 객체 생성::input[type=date]'의 value를 설정
        const today = new Date();
        today.setDate(today.getDate() + 1);
        const dToDate = today.toISOString().split('T')[0];
        document.getElementById('createDate').value = dToDate;

        // 점포# DB 코드,코드명(store_code, store_nm)
        function loadStoreOptions(category) {
            const increate_dt = document.getElementById('createDate').value;
            const url = '/wp-content/plugins/barcodeOut/barcodeOut_srv.php?category=' + category + '&increate_dt=' + increate_dt;
            console.log('[barcodeInv2.php] url=' + url);

            $.ajax({
                url: url, // 동적 카테고리
                type: 'GET',
                dataType: 'json',
                success: function(data) {

                    var selectElement = $('#storeNo');
                    selectElement.empty(); // 기존 옵션을 클리어

                    // 항상 기본 옵션 추가
                    selectElement.append($('<option>', {
                        value: '',
                        text: '점포#'
                    }));

                    // 서버에서 받은 데이터가 있을 경우에 추가
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            selectElement.append($('<option>', {
                                value: item.store_code, // value를 store_code로 설정
                                text: item.store_nm + ' (' + item.store_code + ')'
                            }));
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("An error occurred: " + error);
                }
            });

        }

        function handleKeyDown(e) {
            if (e.key === 'Enter') {
                if (document.activeElement) {
                    document.activeElement.blur();
                }
                e.preventDefault();
            }
        }

        // 조회시작 BL# 조회|일자별 click event
        let category = '';
        let gSupply_dt = '';
        document.getElementById('actionBtn').addEventListener('click', function() {
            // ajax_barcodOut(); // ajax_barcodOut 함수 호출
            // 조회 조건
            category = document.getElementById('category').value; // 1:납기일, 2:조회
            const increate_dt = document.getElementById('createDate').value; // 납품일자
            gSupply_dt = new Date(increate_dt);
            gSupply_dt.setHours(0, 0, 0, 0); // 시간을 00:00:00으로 설정

            const store_no = document.getElementById('storeNo').value; // 점번호

            searchAjax_OutKwd(category, increate_dt, store_no);

            // 기출고 된 건은 [출고확정]버튼을 비활성화 시킴
            // finInvoice_yn(increate_dt, store_no);
        });

        // Invoice 출력
        const invPrintBtn = document.getElementById('invPrint');
        if (invPrintBtn) { // 버튼이 존재할 때만 이벤트 리스너 추가
            invPrintBtn.addEventListener('click', function() {
                handleInvoicePrint();
            });
        }
        // 주문 출력
        const ordPrintBtn = document.getElementById('ordPrint');
        if (ordPrintBtn) { // 버튼이 존재할 때만 이벤트 리스너 추가
            ordPrintBtn.addEventListener('click', function() {
                handleOrderPrint();
            });
        }

        // Invoice 출력 처리 함수
        function handleInvoicePrint() {
            const deliveryDate = document.getElementById('createDate').value;
            const store_no = document.getElementById('storeNo').value;

            const poNumbers = document.querySelectorAll('[id^="quantity-"]');
            const itemNumbers = document.querySelectorAll('[id^="expi_date-"]');
            let params = [];

            poNumbers.forEach((poElement, index) => {
                const poNumber = poElement.id.split('-')[1];
                const itemElement = document.getElementById(`item-no${poNumber}`);
                if (itemElement) {
                    const itemNumber = itemElement.querySelector('.item').textContent.match(/\[ITEM#\] (\d+)/)[1];
                    const poNumberCorrected = itemElement.querySelector('.item').textContent.match(/\[PO#\] (\d+-\d+-\d+)/)[1];
                    const quantityChecked = document.getElementById(`quantity-checkbox-${poNumber}`).checked;
                    const expiDateChecked = document.getElementById(`expi_date-checkbox-${poNumber}`).checked;

                    params.push({
                        poNumber: poNumberCorrected,
                        itemNumber: itemNumber,
                        quantityChecked: quantityChecked,
                        expiDateChecked: expiDateChecked
                    });
                }
            });

            // console.log("[handleInvoicePrint::]params=", params);
            invoice2Print(deliveryDate, store_no, params);
        }

        // 주문 출력 처리 함수
        function handleOrderPrint() {
            const deliveryDate = document.getElementById('createDate').value;
            const store_no = document.getElementById('storeNo').value;
            orderPrint(deliveryDate);
        }


        // show notification
        const notification = document.getElementById('notification-container');
        const showNotification = () => {
            notification.classList.add('show')
            setTimeout(() => {
                notification.classList.remove('show')
            }, 500)
        }

        // 조회시작:: - category=1 납기일 조회, 2:조회
        function searchAjax_OutKwd(
            category,
            increate_dt,
            store_no
        ) {
            // ----------------------------
            // 입력: return true;
            // 조회: return 조회 Data
            // ----------------------------
            let param =
                "&increate_dt=" +
                increate_dt +
                "&store_no=" +
                store_no;

            // category=1 조회이면 's'를 붙임.(서버에서 확인)
            server = "/wp-content/plugins/barcodeInv2/barcodeInv2_srv.php?category=" + '1s' + param;

            console.log("[barcodeInv2_srv]" + server);
            move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);
        }

        function recvOutData(data) {
            move_stop();

            try {
                // 헤더정보
                let jsData = JSON.parse(data);
                // console.log("[ln329::]jsData.response.header=" + jsData.response.header);

                items = jsData.response.body.items;
                totalCount = jsData.response.body.totalCount;
                resultCode = jsData.response.header.resultCode;
            } catch (e) {
                console.log(e.message);
                alert(
                    "시스템 작업 중이거나 장애가 있습니다. 잠시 후 다시 사용하시기 바랍니다."
                );
                return false;
            }
            // 데이터 없음
            if (totalCount == 0 || data === "") {
                alert("출고 대상 데이터가 없습니다.");
                // if (lic !== "") {
                //   licn = lic + licn;
                // }
                barcodeList = [];
                licn = "";
                idx = 0;
                document.getElementById("tables").innerHTML = licn;
                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' +
                    platForm +
                    ":" +
                    navigator.platForm +
                    "::" +
                    "]total record=" +
                    idx;

                return false;
            }

            document.getElementById("tables").innerHTML = "";
            document.getElementById("totalrec").innerHTML = "";

            // 조회결과
            let bFinFlag = false;
            try {
                if (platForm == "computer") {
                    // 1:납기일, 2:조회
                    // gSupply_dt 납기일
                    const today = new Date();
                    today.setHours(0, 0, 0, 0); // 시간을 00:00:00으로 설정

                    if (gSupply_dt < today) {
                        bFinFlag = true;
                    }

                    if (category == 1 && !bFinFlag) { // 납기일
                        codeOutClass.makeTabletr2Mobile(data);
                    } else {
                        codeOutClass.makeTableHead();
                        codeOutClass.makeTabletr(data);
                        if (table1.rows.length > 2) setSort();
                    }
                } else {
                    // category == 2::모바일 조회 데이터
                    if (!bFinFlag) {
                        codeOutClass.makeTabletr2Mobile(data);
                    } else {
                        codeOutClass.makeTableHead();
                        codeOutClass.makeTabletr(data);
                        if (table1.rows.length > 2) setSort();
                    }
                }

                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' +
                    platForm +
                    ":" +
                    navigator.platForm +
                    "::" +
                    "]total record=" +
                    idx;
            } catch (e) {
                alert(
                    "ln628::시스템 장애 입니다. 잠시 후 재시도 하시기 바랍니다." + e.message
                );
                return false;
            }

            // -------------------------------------------------------
            //  메시지 표시
            // -------------------------------------------------------
            const ment = "납품 대상 " + idx + "건 조회하였습니다.\n";
            document.getElementById("notification-container").innerHTML =
                "<p><font color='white'>" + ment + "</font></p>";
            showNotification();

            return true;
        }

        // actionBtn 조회 시작
        function actionBtn_Search() {
            const category = document.getElementById("category").value; // 1:입력, 2:조회
            const increate_dt = document.getElementById("createDate").value;
            const store_no = document.getElementById('storeNo').value;

            const rtn = this.searchAjax_OutKwd(
                category,
                increate_dt,
                store_no
            );
            return rtn;
        }
    </script>

<?php }
add_shortcode('barcodeInv2', 'barcodeInv2ShortCode');
