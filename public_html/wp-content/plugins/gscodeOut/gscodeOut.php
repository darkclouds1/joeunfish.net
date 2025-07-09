<?php
/*
 Plugin Name: gscodeOut : 완출생산관리
 Plugin URI: https://joeunfish.online
 Description: 완출생산관리
 Version: 1.0
 Author: Monolith
 Author URI: https://uloca.net
 License: GPL2
 */

function gscodeOutShortCode()
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

        <script src="/wp-content/plugins/gscodeOut/gscodeOutClass.js"></script>
        <!-- <script src="/wp-content/plugins/gscodeOut/gscodeOutSumClass.js"></script> -->
        <script>
            // 인스턴스 생성을 최우선으로 실행
            var codeOutClass = new gscodeOutClass();
            // var codeSumClass = new gscodeOutSumClass();
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
                            <option value="1">생산</option>
                            <!-- <option value="2">조회</option> -->
                        </select>
                        <input type="date" class="form-field now-date" id="createDate" onchange="dateChanged(this.value)">
                        <select class="form-field barcode-scan" placeholder="점포#" id="storeNo" onchange="document.getElementById('actionBtn').click();">
                            <option value="60000">GS리테일|편의점</option>
                        </select>
                    </div>
                </form>

                <div class="button-container" id="confirmBtn">
                    <!-- 날짜/BL# 조건 조회 버튼 및 BL# 별 총중량 및 건수를 확인하는 버튼  -->
                    <button id="actionBtn">조회</button>
                    <?php if ($platForm !== 'mobile'): ?>
                        <button id="saveBtn" class="desktop-only">저장</button>
                    <?php endif; ?>
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
                <div id='tables' class="type10" style="text-align: left; border: 0px solid #dddddd; word-break:break-all;"></div>

            </div>
        </div>

        <!-- jQuery body 맨아래 -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    </body>
    <script>
        var platform = 'computer';
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

        let table1;
        jQuery(document).ready(function($) {
            // PHP에서 전달받은 사용자 ID 사용
            user_no = myScriptData.userId; // wp에서 관리하는 ID
            user_id = myScriptData.userName; // 사용자ID(wp에서는 Name이라고 함)

            console.log(user_id);
            // loadStoreOptions('c1');

            // 키보드 이벤트 바인딩
            bindTableEvents();

            // 초기 데이터 조회
            document.getElementById('actionBtn').click();

        });

        // 날짜 변경 시 실행되는 함수
        let dateChanged = function(date) {
            // 조회 버튼 클릭
            document.getElementById('actionBtn').click();
        }

        // 페이지 로딩 시 초기 category 값에 따른 버튼 텍스트 설정
        document.getElementById('category').dispatchEvent(new Event('change'));

        // 현재 날짜 객체 생성::input[type=date]'의 value를 설정
        const today = new Date();
        today.setDate(today.getDate());
        const formattedtoday = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
        document.getElementById('createDate').value = formattedtoday;

        // createDate에서 enter 하면 조회 버튼을 클릭
        document.getElementById('createDate').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('actionBtn').click();
                e.preventDefault();
            }
        });

        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                saveBarcodeList();
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
        let gIncreate_dt = '';
        document.getElementById('actionBtn').addEventListener('click', function() {
            // ajax_barcodOut(); // ajax_barcodOut 함수 호출
            // 조회 조건
            category = document.getElementById('category').value; // 1:납기일, 2:조회
            const increate_dt = document.getElementById('createDate').value; // 생산일자
            gIncreate_dt = increate_dt;

            const store_no = document.getElementById('storeNo').value; // 점번호

            if (!store_no) {
                alert('점번호는 필수 입력 항목입니다.');
                return;
            }
            if (category == 1) {
                searchAjax_OutKwd(category, increate_dt, store_no);
            } else {
                alert("개발중입니다. 납기일로 조회해서 INVOICE를 출력하세요.");
                // searchAjax_OutKwd(category, store_no);
            }
        });

        // show notification
        const notification = document.getElementById('notification-container');
        const showNotification = () => {
            notification.classList.add('show')
            setTimeout(() => {
                notification.classList.remove('show')
            }, 300)
        }

        // 조회시작:: - category=1 조회, sotore_no='60000' GS|60000
        function searchAjax_OutKwd(
            category,
            increate_dt,
            store_no
        ) {
            let param =
                "&increate_dt=" +
                increate_dt +
                "&store_no=" +
                store_no;

            // category=1 조회이면 's'를 붙임.(서버에서 확인)
            server = "/wp-content/plugins/gscodeOut/gscodeOut_srv.php?category=" + '1s' + param;

            console.log("[gscodeOut_srv]" + server);
            move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);

        }

        function recvOutData(data) {
            move_stop();
            // console.log(data);

            try {
                // 헤더정보
                let jsData = JSON.parse(data);
                // console.log(jsData.response.header);

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
                barcodeList = [];
                licn = "";
                idx = 0;
                document.getElementById("tables").innerHTML = licn;
                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' + platForm + ":" + navigator.platform + "::" + "]total record=" + idx;
                return false;
            }

            document.getElementById("tables").innerHTML = "";
            document.getElementById("totalrec").innerHTML = "";

            // 조회결과
            try {

                if (platForm == 'mobile') {
                    codeOutClass.makeTabletr2Mobile(data);
                } else {
                    codeOutClass.makeTableHead();
                    codeOutClass.makeTabletr(data);
                    codeOutClass.updateInventoryInput_init(); // 재고수량 초기화
                }

                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' + platForm + ":" + navigator.platform + "::" + "]total record=" + idx;
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

        document.getElementById('manual').addEventListener('click', function() {
            window.open('<?php echo home_url("/출고사용설명서/"); ?>', '_blank');
        });
    </script>

<?php }
add_shortcode('gscodeOut', 'gscodeOutShortCode');
