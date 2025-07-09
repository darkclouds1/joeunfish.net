<?php
/*
 Plugin Name: ascInvt3 : ASC더조은
 Plugin URI: https://joeunfish.online
 Description: ASC 더조은 수불관리
 Version: 0.1
 Author: Monolith
 Author URI: https://uloca.net
 License: GPL2
 */

function ascInvt3ShortCode()
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
        <title>ASC 수불관리</title>

        <script src="/wp-content/plugins/ascInvt3/ascInvt3Class.js"></script>
        <script src="/wp-content/plugins/ascInvt3/ascInvtSum3Class.js"></script>
        <script>
            // 인스턴스 생성을 최우선으로 실행
            var codeOutClass = new ascInvt3Class();
            var codeSumClass = new ascInvtSum3Class();
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
                <form action="" name="myForm" id="myForm" method="get" >
                    <!-- 첫 번째 행 -->
                    <div class="form-row">
                        <select name="category" class="form-field" placeholder="입력" id="category">
                            <option value="1">생산일</option>
                            <!-- <option value="2">조회</option> -->
                        </select>
                        <input type="date" class="form-field now-date" id="createDate" onchange=" dateChanged(this.value)">
                    </div>
                </form>

                <div class="button-container" id="confirmBtn" >
                    <!-- 날짜/BL# 조건 조회 버튼 및 BL# 별 총중량 및 건수를 확인하는 버튼  -->
                    <button id="actionBtn">조회</button>
                    <?php if ($platForm !== 'mobile') : ?>
                        <button id="saveBtn">저장</button>
                        <button id="deleteBtn">삭제</button>
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

                <!-- 메시지 알림-->
                <div class="notification-container" id="notification-container">
                    <p>
                        <font color="white">조회하였습니다.</font>
                    </p>
                </div>
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
            // console.log(jQuery.fn.jquery);

            bindTableEvents();
            // console.log($('#specData').length);

            // 초기 데이터 조회
            document.getElementById('actionBtn').click();

        });


        function dateChanged(date) {
            console.log('Selected date:', date);

            // 기존 조회 함수 호출
            document.getElementById('actionBtn').click();
        }

        // 페이지 로딩 시 초기 category 값에 따른 버튼 텍스트 설정
        document.getElementById('category').dispatchEvent(new Event('change'));

        // 현재 날짜 객체 생성::input[type=date]'의 value를 설정
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('createDate').value = today;

        // createDate에서 enter 하면 조회 버튼을 클릭
        document.getElementById('createDate').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('actionBtn').click();
                e.preventDefault();
            }
        });

        // 데스크탑에서만 저장 버튼 클릭 이벤트 추가    
        if (platForm !== 'mobile') {
            document.getElementById('saveBtn').addEventListener('click', function() {
                var $inputs = $('#specData').find('input');
                // console.log("inputs.length=" + $inputs.length);

            if ($inputs.length > 0) {
                // input 태그가 존재할 경우
                if (confirm("입력한 데이터를 저장하시겠습니까?")) {
                    saveBarcodeList();
                } else {
                    console.log('저장이 취소되었습니다.');
                }
            } else {
                // input 태그가 없을 경우
                alert('저장할 데이터가 없습니다. 생산일을 확인하셔서, 먼저 데이터를 입력해주세요.');
                // 또는 다음과 같이 콘솔에 로그를 남길 수 있습니다.
                    // console.log('No input fields found in specData table. Save process aborted.');
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
        let gIncreate_dt = '';

        document.getElementById('actionBtn').addEventListener('click', function() {
            // ajax_barcodOut(); // ajax_barcodOut 함수 호출
            // 조회 조건
            category = document.getElementById('category').value; // 1:납기일, 2:조회
            const increate_dt = document.getElementById('createDate').value; // 생산일자
            gIncreate_dt = increate_dt;

            if (category == 1) {
                searchAjax_OutKwd(category, increate_dt);
            } else {
                alert("개발중입니다. 납기일로 조회해서 INVOICE를 출력하세요.");
                // searchAjax_OutKwd(category, store_no);
            }

        });

        // 데스크탑에서만 삭제 버튼 클릭 이벤트 추가    
        if (platForm !== 'mobile') {
            document.getElementById('deleteBtn').addEventListener('click', function() {
                const increate_dt = document.getElementById('createDate').value; // increate_dt 값을 가져오는 방식에 맞게 수정 필요

            let url = '/wp-content/plugins/ascInvt3/ascInvt3_srv.php';
            console.log("ln211::url=" + url + " category=delete&increate_dt=" + increate_dt);
            if (increate_dt) {
                if (confirm("생산일자 " + increate_dt + "의 데이터를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.")) {
                    // AJAX를 사용하여 서버에 삭제 요청
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: {
                            category: 'delete',
                            increate_dt: increate_dt
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                location.reload(); // 페이지 새로고침
                            } else {
                                alert('삭제 실패: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                            alert('삭제 중 오류가 발생했습니다.');
                        }
                    });
                } else {
                    console.log('삭제가 취소되었습니다.');
                }
            } else {
                    alert('삭제할 생산일자가 선택되지 않았습니다. 생산일자를 먼저 선택해주세요.');
                }
            });
        }

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
        ) {
            let param = "&increate_dt=" + increate_dt + "&user_id=" + user_id;
            // category=1 조회이면 's'를 붙임.(서버에서 확인)
            server = "/wp-content/plugins/ascInvt3/ascInvt3_srv.php?category=" + '1s' + param;

            console.log("[ascInvt3_srv::조회]" + server);
            move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);
        }

        function recvOutData(data) {
            move_stop();
            // console.log(data);

            try {

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
                    '<font size="3em">[' +
                    platForm +
                    ":" +
                    navigator.platform +
                    "::" +
                    "]total record=" +
                    idx;

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
                }

                // codeOutClass.updateInventoryInput_init();

                // 총 건수 표시 
                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' +
                    platForm +
                    ":" +
                    navigator.platform +
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
            const ment = "ASC 수불" + idx + "건 조회하였습니다.\n";
            document.getElementById("notification-container").innerHTML =
                "<p><font color='white'>" + ment + "</font></p>";
            showNotification();

            return true;
        }

        // actionBtn 조회 시작
        function actionBtn_Search() {
            const category = document.getElementById("category").value; // 1:입력, 2:조회
            const increate_dt = document.getElementById("createDate").value;

            const rtn = this.searchAjax_OutKwd(
                category,
                increate_dt,
            );
            return rtn;
        }

        document.getElementById('manual').addEventListener('click', function() {
            window.open('https://joeunfish.net/ASC수불-사용설명서/', '_blank'); // 여기에 원하는 글의 URL을 입력하세요.
        });
    </script>

<?php }
add_shortcode('ascInvt3', 'ascInvt3ShortCode');
