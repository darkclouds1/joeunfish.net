<?php
/*
 Plugin Name: barcodeIn : 바코드입고
 Plugin URI: https://joeunfish.online
 Description: 바코드입고
 Version: 1.0
 Author: Monolith
 Author URI: https://uloca.net
 License: GPL2
 */

function barcodeInShortCode()
{
    date_default_timezone_set('Asia/Seoul');
    require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
    $dbConn   = new dbconnClassFish;
    $conn     = $dbConn->conn();
    mysqli_set_charset($conn, 'utf8');
    mysqli_query($conn, "SET time_zone = '+09:00'");

    $platForm = $dbConn->MobileCheck();
?>

    <!DOCTYPE html>
    <html lang="kr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>barcode scanner IN</title>

        <!-- css -->
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/barcodeInv/joeunfishInv.css" />
        
        <!-- js -->
        <script rel="preload" src="/wp-content/plugins/barcodeIn/CodeInClass.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js" async></script>

    </head>

    <body>

        <?php   
            // 공통 네비게이션 메뉴 포함
            require_once(WP_PLUGIN_DIR . '/jofMain/jofNavigation.php');
            renderJofNavigation();
        ?>

        <div id="contents">
            <div class="form-container">
                <div class="form-menu">
                    <div id="entry-title">1층입고</div>
                </div>
                <form action="" name="myForm" id="myForm" method="get">
                    <!-- 첫 번째 행 -->
                    <div class="form-row">
                        <select class="form-field" id="category" placeholder="입력" >
                            <option value="1">입고</option>
                            <option value="3">삭제</option>
                            <option value="2">조회</option>
                        </select>
                        <input type="date" class="form-field" id="createDate" onclick="" onkeydown="if(event.key === 'Enter') { event.preventDefault(); document.getElementById('actionBtn').click(); }">
                        <select class="form-field" id="lcNumber" placeholder="BL#4자" autofocus onchange="handleSelect(this)" >
                            <option value="">BL#4자</option>
                        </select>
                        <input type="text" class="form-field" placeholder="갯수(Box)" id="count" readonly>
                        <input type="text" class="form-field" placeholder="총중량(kg)" id="total-weight" readonly>
                    </div>
                    <!-- 두 번째 행 1bacode -->
                    <div class="form-row">
                        <input type="text" class="form-field barcode-scan" placeholder="Barcode Scan 첫번째" maxlength="32" id="barcodeInput1" inputmode="none">
                        <input type="text" class="form-field barcode-scan" placeholder="Barcode Scan 두번째" maxlength="38" id="barcodeInput2" inputmode="none">
                    </div>
                    <!-- 세 번째 행 2barcode-->
                    <div class="form-row">
                        <input type="text" class="form-field" placeholder="중량(kg)" readonly id="weightInput" onkeydown="moveToNextField(event)">
                        <input type=" text" class="form-field" placeholder="생산일" readonly id="productionDate" onkeydown="if (event.keyCode === 13) insertBtn_Click();">
                    </div>
                </form>

                <div class="button-container search-row" id="confirmBtn" style="display: flex; justify-content: center; align-items: center; position: relative; gap: 10px;">
                    <!-- 날짜/BL# 조건 조회 버튼 및 BL# 별 총중량 및 건수를 확인하는 버튼  -->
                    <button id="actionBtn">BL#조회</button>
                    <button id="insertBtn">수기입력</button>
                    <button id="initBtn">초기화</button>
                    <?php if ($platForm == 'computer') {    ?>
                        <button id="toExcel" onclick="tableToExcel()">엑셀다운</button>
                    <?php } ?>
                    <div style="min-width: 95px; flex-shrink: 0;"></div>
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

                <!-- 랜덤 loading -->
                <div id='loading' style='display: none;'>
                    <div id="code1"><img src='/img/loading/0.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/1.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/2.gif' width='400px'></div>
                    <!-- <div id="code1"><img src='/img/loading/3.gif' width='300px'></div> -->
                    <div id="code1"><img src='/img/loading/4.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/5.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/6.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/7.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/8.gif' width='400px'></div>
                </div>

                <!-- table 건수 요약 -->
                <div id='totalrec'></div>

                <!-- AJAX로 불러온 목록이 여기에 표시됩니다. -->
                <div id="listContainer"></div>

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

        // 수기입력 여부
        let manualInput = false;
    </script>

    </html>

    <script>
        // 서버 주소
        var barcodeIn_srv = '/wp-content/plugins/barcodeIn/barcodeIn_srv.php';

        let table1;
        codeInClass = new CodeInClass();
        jQuery(document).ready(function($) {
            // PHP에서 전달받은 사용자 ID 사용
            user_no = myScriptData.userId; // wp에서 관리하는 ID
            user_id = myScriptData.userName; // 사용자ID(wp에서는 Name이라고 함)

            console.log(user_id);
        });


        document.addEventListener("DOMContentLoaded", function() {
            const productionDate = document.getElementById("productionDate");

            // Apply date mask
            productionDate.addEventListener("input", function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-numeric characters
                if (value.length > 8) value = value.slice(0, 8); // Limit input length

                // Format value as yyyy-mm-dd
                if (value.length >= 5) {
                    e.target.value = value.slice(0, 4) + '-' + value.slice(4, 6) + '-' + value.slice(6, 8);
                } else if (value.length >= 3) {
                    e.target.value = value.slice(0, 4) + '-' + value.slice(4, 6);
                } else {
                    e.target.value = value;
                }
            });

            // Prevent manual edits if readonly
            productionDate.addEventListener("keydown", function(e) {
                if (this.readOnly) e.preventDefault();
            });
        });

        // 페이지 로딩 시 초기 category 값에 따른 버튼 텍스트 설정
        document.getElementById('category').dispatchEvent(new Event('change'));

        // category 선택 변경 시 이벤트 리스너
        document.getElementById('category').addEventListener('change', function() {
            const category = this.value; // 현재 선택된 category 값
            const button = document.getElementById('actionBtn'); // 버튼 선택

            if (category === '1') {
                button.textContent = 'BL#조회'; // category가 '조회'일 때 버튼 텍스트 변경
            } else if (category === '2') {
                button.textContent = '조회'; // category가 '입력'일 때 버튼 텍스트 변경
            }
            // 수기입력 상태 => 중량, 생산일 읽기전용 처리
            if (manualInput === true) onCategoryChange();
        });

        // 현재 날짜 객체 생성
        const dToDate = getToday('-');
        // 'input[type=date]'의 value를 설정
        document.getElementById('createDate').value = dToDate;

        // BL# 가져오기::DB 입력일(increate_dt), BL#(lc_no)
        const url = barcodeIn_srv + '?category=lc';
        console.log("url=" + url);      

        $(document).ready(function() {
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var selectElement = $('#lcNumber');
                    data.forEach(function(item) {
                        selectElement.append($('<option>', {
                            value: item.bl_no,
                            text: item.bl_no + item.import_dt
                        }));
                    });
                },
                error: function(xhr, status, error) {
                    console.error("An error occurred: " + error);
                }
            });
        });

        // focus 이동
        document.getElementById('count').addEventListener('click', function() {
            // 'Barcode Scan 첫번째' 필드로 포커스 이동
            document.getElementById('lcNumber').focus();
        });

        document.getElementById('total-weight').addEventListener('click', function() {
            // 'Barcode Scan 첫번째' 필드로 포커스 이동
            document.getElementById('lcNumber').focus();
        });

        document.getElementById('weightInput').addEventListener('click', function() {
            if (manualInput) return;
            // 'Barcode Scan 첫번째' 필드로 포커스 이동
            document.getElementById('barcodeInput1').focus();
        });

        document.getElementById('productionDate').addEventListener('click', function() {
            if (manualInput) return;
            // 'Barcode Scan 첫번째' 필드로 포커스 이동
            document.getElementById('barcodeInput2').focus();
        });

        // barcode 첫번째 Validation (자릿수:32)
        document.getElementById('barcodeInput1').addEventListener('blur', function() {

            //  조회 일 때는 blur 취소
            const category = document.getElementById('category').value; // 1:입력, 2:조회
            console.log("category= " + category);

            // 조회 또는 수기입력 시 처리 취소
            if (category == 2 || manualInput) return;

            // barcode 첫번째 Validation
            const inputValue = this.value;
            const weightInputElement = document.getElementById('weightInput');

            // lcNumber 값의 길이가 4자리 숫자가 아닌 경우
            const lcNumberValue = document.getElementById('lcNumber').value;
            console.log("lcNumberValue= " + lcNumberValue);
            if (
                inputValue.length !== 0 &&
                !/^\d{4}([EB])?$/i.test(lcNumberValue)
            ) {
                // 입고일때는 BL#번호 4자리 또는 4자리+E|B Validation
                if (category == 1) alert("BL#는 숫자4자리 또는 숫자4자리+E/B 입니다.");
                document.getElementById('lcNumber').focus(); // lcNumber 필드로 포커스 이동
                return;
            }

            if (inputValue.length === 32 && inputValue.slice(-2).toUpperCase() === "NO") {
                // 조건 충족 시, 처리 로직
                const weightPart = inputValue.substr(22, 3); // 22번째부터 3자리 숫자 추출
                const formattedWeight = weightPart.substr(0, 2) + "." + weightPart.substr(2, 1); // 형식 변환

                // 형식 검증
                const isValidFormat = /^\d+\.\d+$/.test(formattedWeight);
                if (!isValidFormat) {
                    console.log("입력된 값이 유효한 형식이 아닙니다. 형식은 [숫자.숫자] 여야 합니다.");
                    weightInputElement.value = "바코드 오류!"; // weightInput 필드도 초기화
                    weightInputElement.style.backgroundColor = '#FB7701';
                    this.select();
                } else {
                    weightInputElement.value = formattedWeight; // 변환된 값 채우기
                    weightInputElement.style.backgroundColor = '#f0f0f0';
                    document.getElementById('barcodeInput2').focus();
                }

            } else if (inputValue.length === 0) {
                // 사용자가 아무것도 입력하지 않았을 때 처리 로직
                weightInputElement.value = ""; // weightInput 필드 초기화
                weightInputElement.style.backgroundColor = '#f0f0f0';
            } else {
                weightInputElement.value = "바코드 오류!"; // weightInput 필드도 초기화
                weightInputElement.style.backgroundColor = '#FB7701';
                this.select();
            }
        });

        // 입고시작::barcode 두번째 Validation
        document.getElementById('barcodeInput2').addEventListener('blur', function() {

            //  조회 일 때는 blur 취소
            const category = document.getElementById('category').value; // 1:입력, 2:조회
            // console.log("category= " + category);

            // 조회 또는 수기입력 시 처리 취소
            if (category == 2 || manualInput) return;

            const productionDateElement = document.getElementById('productionDate');
            // 입력값 length= 36,38 (마지막문자='B2') 2가지 종류?
            if (this.value.length >= 36 && this.value.slice(-2).toUpperCase() === "NO") {
                productionDateElement.value = "바코드 오류!"; // productionDate 필드도 초기화
                productionDateElement.style.backgroundColor = '#FB7701';
                this.focus();
                return;
            }

            // 입력값의 길이가 38자리인지 검사
            if (this.value.length >= 36 && this.value.slice(-2).toUpperCase() !== "NO") {
                // 조건 충족 시, 처리 로직 (날짜 추출) - 특수문자 제거

                const inputValue = this.value.replace(/[^a-zA-Z0-9 ]/g, "");
                this.value = inputValue;

                const datePart = inputValue.substr(2, 6);
                const formattedDate = "20" + datePart.substr(0, 2) + "-" + datePart.substr(2, 2) + "-" + datePart.substr(4, 2); // 형식 변환

                // formattedDate로부터 Date 객체 생성
                const date = new Date(formattedDate);
                const isValidDate = (date instanceof Date && !isNaN(date));

                // 날짜 데이터 validation
                if (!isValidDate) {
                    productionDateElement.value = "바코드 오류!"; // weightInput 필드도 초기화
                    productionDateElement.style.backgroundColor = '#FB7701'; // 오렌지
                    this.focus();
                    return;
                } else {

                    // 성공 & 날짜 표시
                    productionDateElement.value = formattedDate;
                    productionDateElement.style.backgroundColor = '#f0f0f0';

                    if (category == 1) {

                        const barcodeInput1 = document.getElementById('barcodeInput1').value;
                        const lcNumberValue = document.getElementById('lcNumber').value;
                        if (lcNumberValue.toString().length !== 4) {
                            alert("BL# 번호를 다시 입력하세요!")
                            document.getElementById('lcNumber').focus();
                        } else if (barcodeInput1.length !== 32 || barcodeInput1.slice(-2).toUpperCase() !== "NO") {
                            alert("Barcode 1 을 다시 입력하세요!")
                            document.getElementById('barcodeInput1').focus();
                        } else {
                            const createDate = document.getElementById('createDate').value;
                            const lcNumber = document.getElementById('lcNumber').value;
                            const barcode1 = document.getElementById('barcodeInput1').value;
                            const weightInput = document.getElementById('weightInput').value;
                            const barcode2 = document.getElementById('barcodeInput2').value;
                            const inputList = createDate + "," + lcNumber + "," + barcode1 + "," + weightInput + "," + barcode2 + "," + formattedDate;

                            // Array[barcode1, 중량, barcode2, 생산일]
                            const listArray = inputList.split(",");
                            document.getElementById('barcodeInput1').focus();

                            // console.log(listArray);
                            // 입력시작::DB 저장 및 중복 체크 return false;
                            newItem = [];
                            if (!ajax_barcodeIn(listArray)) {

                                document.getElementById('barcodeInput1').value = '';
                                document.getElementById('barcodeInput2').value = '';
                                document.getElementById('weightInput').value = '';
                                document.getElementById('barcodeInput1').focus();
                            }
                        }

                    } else if (category == 3) {
                        // 삭제::생산입고에서 바코드 삭제
                        const barcode1 = document.getElementById('barcodeInput1').value;
                        const barcode2 = document.getElementById('barcodeInput2').value;
                        delBarcode3(barcode1, barcode2);
                        document.getElementById('barcodeInput1').focus();
                    }

                    // waitAndRunAsync();
                    // DB 서버프로세스 진행

                    return;
                }
            } else if (this.value === '') {
                // 사용자가 아무것도 입력하지 않았을 때 처리 로직
                productionDateElement.value = '생산일';
                productionDateElement.style.backgroundColor = '#f0f0f0';
            } else {
                productionDateElement.value = "바코드 오류!"; // weightInput 필드도 초기화
                productionDateElement.style.backgroundColor = '#FB7701'; // 오렌지
                this.focus();
            }
        });

        // BL#번호 4자리 Validation
        document.getElementById('lcNumber').addEventListener('input', function() {
            let value = this.value;
            if (value.length > 4) { // 입력된 값의 길이가 4를 초과하면
                this.value = value.slice(0, 4); // 처음 4자만 유지
            }
        });

        // 생산일자에는 모든 키 입력을 막습니다. (단, Tab 키는 예외로 합니다.)
        document.getElementById('productionDate').addEventListener('keydown', function(e) {
            if (e.key !== 'Tab' && manualInput == false) {
                e.preventDefault();
            }
        });

        // 클래스가 'form-field'인 모든 입력 필드를 선택
        document.querySelectorAll('.form-field').forEach(field => {
            field.addEventListener('focus', function() {
                // 입력 필드에 포커스가 되면 텍스트 전체 선택
                if (this.tagName.toLowerCase() !== 'select') {
                    this.select();
                }
            });
        });

        // Enter keydown
        document.getElementById('barcodeInput1').addEventListener('keydown', handleKeyDown);
        document.getElementById('barcodeInput2').addEventListener('keydown', handleKeyDown);
        document.getElementById('lcNumber').addEventListener('keydown', handleKeyDown);

        function handleKeyDown(e) {
            if (e.key === 'Enter') {
                if (e.target.id === 'lcNumber') {
                    var lcNumberLength = document.getElementById('lcNumber').value.length;
                    var category = document.getElementById('category').value;

                    console.log("lcNumberLength=" + lcNumberLength + ", category=" + category);
                    if (lcNumberLength === 4) {
                        actionBtn_Search();
                        document.getElementById('barcodeInput1').focus();
                        return;
                    }
                }

                if (document.activeElement) {
                    document.activeElement.blur();
                }
                e.preventDefault();
            }
        }

        // 조회시작 BL# 조회|일자별 click event
        document.getElementById('actionBtn').addEventListener('click', function() {
            actionBtn_Search();
        });

        // 수기입력 저장
        document.getElementById('insertBtn').addEventListener('click', function() {
            insertBtn_Click();
        });

        // 초기화
        document.getElementById('initBtn').addEventListener('click', function() {
            location.reload();
        });

        // show notification
        const notification = document.getElementById('notification-container');
        const showNotification = () => {
            notification.classList.add('show')
            setTimeout(() => {
                notification.classList.remove('show')
            }, 1000)
        }
    </script>

<?php }
add_shortcode('barcodeIn', 'barcodeInShortCode'); ?>