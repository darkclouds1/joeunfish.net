<?php
/*
 Plugin Name: barcodeOut : 바코드생산
 Plugin URI: https://joeunfish.online
 Description: 바코드생산
 Version: 1.4
 Author: Monolith
 Author URI: https://uloca.net
 License: GPL2
 */

function barcodeOutShortCode()
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

        <script src="/wp-content/plugins/barcodeOut/CodeOutClass.js"></script>
        <script>
            // 인스턴스 생성을 최우선으로 실행
            var codeOutClass = new CodeOutClass();
        </script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js" async></script>

        <!-- css -->
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/barcodeOut/joeunfishOut.css?ver=<?php echo filemtime(get_stylesheet_directory() . '/wp-content/plugins/barcodeOut/joeunfishOut.css'); ?>" />

    </head>

    <body>
        <div id="contents">
            <div class="form-container">
                <form action="" name="myForm" id="myForm" method="get">
                    <!-- 첫 번째 행 -->
                    <div class="form-row">
                        <select name="category" class="form-field" id="category" placeholder="입력">
                            <option value="1">납기</option>
                            <option value="2">제조</option>
                        </select>
                        <input type="date" class="form-field" id="createDate" onclick="" onkeydown="if(event.key === 'Enter') { event.preventDefault(); document.getElementById('actionBtn').click(); }">
                        <select class="form-field" id="lcNumber" placeholder="BL#4자" autofocus>
                            <option value="">BL#4자</option>
                        </select>

                        <input type="text" class="form-field" id="count" placeholder="갯수(Box)" readonly>
                        <input type="text" class="form-field" id="total-weight" placeholder="총중량(kg)" readonly>
                    </div>
                    <!-- 두 번째 행 1bacode -->
                    <div class="form-row">
                        <select class="form-field storeNo" id="storeNo" placeholder="점번호#">
                            <option value="">점번호#선택</option>
                        </select>
                        <select class="form-field color" id="color" placeholder="색깔#">
                            <option value="">색깔#</option>
                            <option value="흰색">흰색(W)</option>
                            <option value="갈색">갈색(G)</option>
                            <option value="초록">초록(C)</option>
                            <option value="빨강">빨강(R)</option>
                            <option value="노랑">노랑(Y)</option>
                            <option value="파랑">파랑(B)</option>
                        </select>
                        <input type="text" class="form-field" id="barcodeOutput" placeholder="Barcode Scan 출고" maxlength="24" inputmode="none">
                    </div>
                    <!-- 세 번째 행 2barcode-->
                    <div class="form-row">
                        <input type="text" class="form-field" id="productionDate" placeholder="제조일자" readonly>
                        <input type="text" class="form-field" id="expiDate" placeholder="소비기한" readonly>
                        <input type="text" class="form-field" id="weightInput" placeholder="중량(kg)" readonly>
                    </div>
                    <div class="form-row">
                        <select class="form-field" id="code_exp" placeholder="상품명#선택">
                            <option value="">상품명#선택</option>
                            <option value="2000000560770">연어필렛</option>
                            <option value="0200000651343">연어(스테이크)</option>
                            <option value="2000000668592">연어(스테이크용)벌크</option>
                            <option value="2000000614117">MAP연어필렛</option>
                            <option value="0200000684213">MAP연어(스테이크용)</option>
                        </select>
                        <input type="text" class="form-field" id="joeun_cnt" placeholder="조은점(재고)" readonly>
                        <!-- <button id="joeun_cnt_invt">재고조회</button> -->
                    </div>
                </form>

                <div class="button-container" id="confirmBtn">
                    <!-- 날짜/BL# 조건 조회 버튼 및 BL# 별 총중량 및 건수를 확인하는 버튼  -->
                    <button id="actionBtn">조회</button>
                    <button id="totalDel">전체삭제</button>
                    <button id="initBtn">초기화</button>
                    <?php if ($platForm == 'computer') {    ?>
                        <button id="toExcel" onclick="tableToExcel()">엑셀다운</button>
                    <?php } ?>
                    <!-- 완출재고 조회 버튼 -->
                    <button id="invt002Btn">완출재고</button>

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
                    <div id="code1"><img src='/img/loading/0.gif'></div>
                    <div id="code1"><img src='/img/loading/8.gif'></div>
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
    </script>

    </html>

    <script>
        // 서버 주소
        var barcodeOut_srv = '/wp-content/plugins/barcodeOut/barcodeOut_srv.php';

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
        });

        // 페이지 로딩 시 초기 category 값에 따른 버튼 텍스트 설정
        document.getElementById('category').dispatchEvent(new Event('change'));

        // category 선택 변경 시 이벤트 리스너
        document.getElementById('category').addEventListener('change', function() {
            const category = this.value; // 현재 선택된 category 값
            const button = document.getElementById('actionBtn'); // 버튼 선택

            if (category === '1') {
                button.textContent = '조회'; // category가 '조회'일 때 버튼 텍스트 변경
            } else if (category === '2') {
                button.textContent = '조회'; // category가 '입력'일 때 버튼 텍스트 변경
            }
        });

        // 현재 날짜 객체 생성::input[type=date]'의 value를 설정
        const dToDate = getToday('-');
        document.getElementById('createDate').value = dToDate;

        // 점번호# DB 코드,코드명(store_code, store_nm)
        $(document).ready(function() {
            $.ajax({
                url: barcodeOut_srv + '?category=c1a',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var selectElement = $('#storeNo');
                    // 허용된 점포 코드 배열
                    var allowedCodes = ['000', '001', '002', '005']; // 조은점, 원재료, 완출점, GS점

                    data.forEach(function(item) {
                        // allowedCodes 배열에 포함된 store_code만 필터링
                        if (allowedCodes.includes(item.store_code)) {

                            selectElement.append($('<option>', {
                                value: item.store_code,
                                text: item.store_nm + ' (' + item.store_code + ')'
                            }));
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error("An error occurred: " + error);
                }
            });
        });

        document.getElementById('color').addEventListener('change', function() {
            var color = this.value; // 선택된 옵션의 값
            // 색상 코드 매핑
            var colorMap = {
                "흰색": "#FFFFFF", // 
                "갈색": "#A52A2A ", // 
                "초록": "#008000", // Green
                "빨강": "#FF0000", // Red
                "노랑": "#FFFF00", // Yellow
                "파랑": "#0000FF", // Blue
            };

            // 글자색 변경 로직 검증
            if (color === "흰색" || color === "노랑" || document.getElementById('color').value === "") {
                this.style.color = "#000000"; // 검정색으로 설정
            } else {
                this.style.color = "#FFFFFF"; // 흰색으로 설정
            }

            // 폼 필드의 배경색을 변경
            this.style.backgroundColor = colorMap[color] || "transparent"; // 색상이 매핑되지 않으면 투명으로 설정
        });

        document.getElementById('code_exp').addEventListener('change', function() {
            document.getElementById('barcodeOutput').focus();
        });

        // BL# 가져오기::DB 입력일(increate_dt), BL#(lc_no)
        const url = barcodeOut_srv + '?category=lc';
        console.log('ln257::url=' + url);
        $(document).ready(function() {
            $.ajax({
                url: url, // BL#
                type: 'GET', // HTTP 요청 방식
                dataType: 'json', // 응답 데이터 타입
                success: function(data) {
                    var selectElement = $('#lcNumber');
                    data.forEach(function(item) {
                        selectElement.append($('<option>', {
                            value: item.lc_no,
                            text: item.lc_no + item.increate_dt
                        }));
                    });
                },
                error: function(xhr, status, error) {
                    console.error("An error occurred: " + error);
                }
            });
        });

        // 조은점(재고) 표시
        $(document).ready(function() {
            fetchInventoryData();
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
            // 'Barcode Scan 첫번째' 필드로 포커스 이동
            document.getElementById('barcodeOutput').focus();
        });

        // barcode Scan Validation
        document.getElementById('barcodeOutput').addEventListener('blur', function() {

            //  // category=2:조회일 때는 blur 취소
            let category = document.getElementById('category').value;

            if (category == 2) {
                //document.getElementById('barcodeOutput').focus();
                return;
            }

            const productionDateElement = document.getElementById('productionDate');
            const expiDateElement = document.getElementById('expiDate');
            const weightElement = document.getElementById('weightInput');

            // 입력값의 길이가 24자리인지 검사
            if (this.value.length === 24) {
                const inputValue = this.value;
                // 제조일자
                const manu_dt = inputValue.slice(5, 11);
                const manu_date = "20" + manu_dt.substr(0, 2) + "-" + manu_dt.substr(2, 2) + "-" + manu_dt.substr(4, 2);
                const fomattedManuDate = new Date(manu_date);
                const isValidDate1 = (fomattedManuDate instanceof Date && !isNaN(fomattedManuDate));
                // 소비기한
                const expi_dt = inputValue.slice(11, 17);
                const expi_date = "20" + expi_dt.substr(0, 2) + "-" + expi_dt.substr(2, 2) + "-" + expi_dt.substr(4, 2);
                const formattedExpiDate = new Date(expi_date);
                const isValidDate2 = (formattedExpiDate instanceof Date && !isNaN(formattedExpiDate));

                // 중량
                const prod_code = document.getElementById('code_exp').value;
                let weightInput = inputValue.slice(18, 23);

                const num = parseInt(weightInput, 10); // 숫자로 변환
                let weight = num / 1000; // 1000으로 나누어 g → kg 변환

                if (prod_code === '2000000560770' || !prod_code) {
                    // 연어필렛:: 소숫점 첫 번째 자리에서 반올림 (1.26kg → 1.3kg)
                    weight = Math.round(weight * 10) / 10;
                } else {
                    // 연어(스테이크):: 소숫점 1번째 외 절사 (1.26kg → 1.2kg)
                    weight = Math.floor(weight * 10) / 10;
                }

                // 날짜 데이터 validation
                if (!isValidDate1) {
                    productionDateElement.value = "제조일자 오류!";
                    productionDateElement.style.backgroundColor = '#FB7701'; // 오렌지
                    this.focus();
                    return;
                } else if (!isValidDate2) {
                    expiDateElement.value = "소비기한 오류!";
                    expiDateElement.style.backgroundColor = '#FB7701'; // 오렌지
                    this.focus();
                    return;
                } else if (weight === 0) {
                    document.getElementById('barcodeOutput').value = ''; // 바코드 입력란 비우기
                    alert("중량이 0입니다. 올바른 바코드를 입력하세요.");
                    weightElement.value = "중량 오류!";
                    weightElement.style.backgroundColor = '#FB7701'; // 오렌지
                    this.focus();
                    return;
                } else {

                    // === 소비기한 유효성 검사 추가 ===
                    // 상품명 텍스트 가져오기
                    const selectElement = document.getElementById('code_exp');
                    let prod_nm = selectElement.options[selectElement.selectedIndex].text;

                    // 날짜 객체 생성
                    const manuDateObj = new Date(manu_date);
                    const expiDateObj = new Date(expi_date);

                    // 날짜 차이 계산 (밀리초 → 일)
                    const diffTime = expiDateObj.getTime() - manuDateObj.getTime();
                    const diffDays = diffTime / (1000 * 60 * 60 * 24);

                    // === 소비기한 유효성 검사 시작 ===
                    if (prod_nm === "연어필렛") {
                        console.log("임시로,[연어필렛] 소비기한 유효성 검사 하지 않음.::diffDays=" + diffDays);
                        // if (diffDays !== 9) {
                        //     document.getElementById('barcodeOutput').value = ''; // 바코드 입력란 비우기
                        //     alert("연어필렛의 소비기한이 맞지 않습니다. 올바른 상품명을 선택하시기 바랍니다.");
                        //     expiDateElement.value = "소비기한 오류!";
                        //     expiDateElement.style.backgroundColor = '#FB7701';
                        //     this.focus(); 
                        //     return;
                        // } 
                    } else if (prod_nm.includes("스테이크")) {
                        console.log("임시로,[스테이크] 소비기한 유효성 검사 하지 않음.::diffDays=" + diffDays);
                        // if (diffDays !== 6) {
                        //     document.getElementById('barcodeOutput').value = ''; // 바코드 입력란 비우기
                        //     alert("스테이크의 소비기한이 맞지 않습니다. 올바른 상품명을 선택하시기 바랍니다.");
                        //     expiDateElement.value = "소비기한 오류!";
                        //     expiDateElement.style.backgroundColor = '#FB7701';
                        //     this.focus();
                        //     return;
                        // }
                    }
                    // === 소비기한 유효성 검사 끝 ===

                    // 성공 & 제조일자
                    productionDateElement.value = manu_date;
                    productionDateElement.style.backgroundColor = '#f0f0f0';
                    // 소비기한
                    expiDateElement.value = expi_date;
                    expiDateElement.style.backgroundColor = '#f0f0f0';
                    // 중량 표시
                    weightElement.value = weight;
                    weightElement.style.backgroundColor = '#f0f0f0';

                    // 화면 순서대로
                    const increate_dt = document.getElementById('createDate').value; // 입력일 => 출고일
                    const lc_no = document.getElementById('lcNumber').value; // BL#
                    const barcode_out = document.getElementById('barcodeOutput').value;
                    const store_no = document.getElementById('storeNo').value;
                    const pack_color = document.getElementById('color').value;

                    // 선택된 옵션의 텍스트를 가져옵니다.
                    if (prod_nm == '상품명#선택') prod_nm = '';

                    if (lc_no.toString().length < 4) {
                        alert("BL# 번호를 다시 입력하세요!")
                        document.getElementById('lcNumber').focus();
                    } else if (store_no === '') {
                        alert("점번호#| 선택하세요!")
                        document.getElementById('storeNo').focus();
                    } else if (prod_nm === '') {
                        alert("상품명#을 선택하세요!")
                        document.getElementById('storeNo').focus();
                    } else if (pack_color === '' && prod_nm === '연어필렛') {
                        alert("색깔#| 선택하세요!")
                        document.getElementById('storeNo').focus();
                    } else if (barcode_out.length !== 24) {
                        alert("바코드를 다시 스캔하세요!")
                        document.getElementById('barcodeOutput').focus();
                    } else {

                        // 순서대로 array
                        const inputList = increate_dt + "," + lc_no + "," + store_no + "," + pack_color + "," +
                            barcode_out + "," + manu_date + "," + expi_date + "," + weight + "," + prod_code + "," + prod_nm;
                        const listArray = inputList.split(",");
                        document.getElementById('barcodeOutput').focus();

                        // 입력시작::DB 저장 및 중복 체크 return false;
                        newItem = [];
                        category = "1i"; // '1i': 입력+i(insert)
                        if (!(codeOutClass.ajax_barcodeOut(listArray, category))) {
                            document.getElementById('barcodeOutput').value = ''; // 바코드
                            // document.getElementById('productionDate').value = ''; // 제조일자
                            // document.getElementById('expiDate').value = ''; // 소비기한
                            // document.getElementById('weightInput').value = ''; // 중량
                            document.getElementById('barcodeOutput').focus();
                        } else {
                            // 조회 이거나 실패
                            document.getElementById('barcodeOutput').value = ''; // 바코드
                            document.getElementById('barcodeOutput').focus();
                        };
                    }

                    return;
                }
            } else if (this.value === '') {
                // 사용자가 아무것도 입력하지 않았을 때 처리 로직
                productionDateElement.value = '제조일자';
                productionDateElement.style.backgroundColor = '#f0f0f0';
            } else {
                productionDateElement.value = "바코드 오류!"; // weightInput 필드도 초기화
                productionDateElement.style.backgroundColor = '#FB7701'; // 오렌지
                this.focus();
            }

        });

        // BL#번호 4자리 Validation
        // document.getElementById('lcNumber').addEventListener('input', function() {
        //     let value = this.value;
        //     if (value.length > 5) { // 입력된 값의 길이가 4를 초과하면
        //         this.value = value.slice(0, 4); // 처음 4자만 유지
        //     }
        // });

        // 생산일자에는 모든 키 입력을 막습니다. (단, Tab 키는 예외로 합니다.)
        document.getElementById('productionDate').addEventListener('keydown', function(e) {
            if (e.key !== 'Tab') {
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
        document.getElementById('barcodeOutput').addEventListener('keydown', handleKeyDown);
        // document.getElementById('lcNumber').addEventListener('keydown', handleKeyDown); 

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
        document.getElementById('actionBtn').addEventListener('click', function() {
            // ajax_barcodOut(); // ajax_barcodOut 함수 호출
            // 조회 조건
            category = document.getElementById('category').value; // 1:입력, 2:조회
            const increate_dt = document.getElementById('createDate').value; // 입력일자 => 출고일자
            const lc_no = document.getElementById('lcNumber').value; // BL#
            const store_no = document.getElementById('storeNo').value; // 점번호
            const pack_color = document.getElementById('color').value; // 색깔
            const barcode_out = document.getElementById('barcodeOutput').value; //barcode
            // 상품명 가져오기
            const selectElement = document.getElementById('code_exp');
            let prod_nm = selectElement.options[selectElement.selectedIndex].text;
            const prod_code = selectElement.options[selectElement.selectedIndex].value;
            if (prod_nm == '상품명#선택') prod_nm = '';

            // category=1 일때 조회는 BL# 입력 필수
            //  if (category == '1' && lc_no == '') {
            //      alert("BL# 4자리를 입력하세요.")
            //      return;
            //  }
            searchAjax_OutKwd(category, increate_dt, lc_no, store_no, pack_color, barcode_out, prod_code, prod_nm);

        });

        function deleteAllBarcodes() {
            if (!confirm('선택한 바코드를 모두 삭제하시겠습니까?')) {
                return;
            }

            //  console.log('Original barcodeList:', barcodeList);

            // POST 방식으로 변경
            $.ajax({
                url: barcodeOut_srv + '?category=deleteAll', // category는 URL에 유지
                type: 'POST',
                contentType: 'application/json', // JSON 형식으로 전송
                data: JSON.stringify({
                    barcodes: barcodeList
                }),
                success: function(response) {
                    //  console.log('Server response:', response);
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('삭제 실패: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    alert('서버 통신 중 오류가 발생했습니다.');
                }
            });
        }


        document.getElementById('totalDel').addEventListener('click', deleteAllBarcodes);

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

        // 조회시작:: BL# 입력|확인
        //  - category=1 BL#:조회, 2:조회
        function searchAjax_OutKwd(
            category,
            increate_dt,
            lc_no,
            store_no,
            pack_color,
            barcode_out,
            prod_code,
            prod_nm
        ) {
            // 재고 먼조 조회
            fetchInventoryData();
            // ----------------------------
            // 입력: return true;
            // 조회: return 조회 Data
            // ----------------------------
            let param =
                "&increate_dt=" +
                increate_dt +
                "&lc_no=" +
                lc_no +
                "&store_no=" +
                store_no +
                "&pack_color=" +
                pack_color +
                "&barcode_out=" +
                barcode_out +
                "&prod_code=" +
                prod_code +
                "&prod_nm=" +
                prod_nm;


            // category=1 이고 조회이면 's'를 붙임.(서버에서 확인)
            if (category == 1) {
                server = barcodeOut_srv + '?category=1s' + param;
            } else {
                server = barcodeOut_srv + '?category=origin_store_code' + param;
            }
            console.log("[searchOut_srv]" + server);

            if (category == 2) move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);
        }

        //  재고조회, 완출점까지
        function searchAjax_Invt() {
            // category=1 이고 조회이면 's'를 붙임.(서버에서 확인)
            server = barcodeOut_srv + '?category=invtS';
            console.log("[searchOut_srv]" + server);

            move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);
        }

        //  재고조회, 완출점(002)
        function searchAjax_Invt002() {
            server = barcodeOut_srv + '?category=invt002S';
            console.log("[searchOut_srv]" + server);

            move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);
        }

        function recvOutData(data) {
            move_stop();

            try {
                // 헤더정보
                // console.log("받은 데이터:", data); // 실제 받은 데이터 확인
                let jsData = JSON.parse(data);
                // console.log(jsData.response.header);

                items = jsData.response.body.items;
                totalCount = jsData.response.body.totalCount;
                resultCode = jsData.response.header.resultCode;
            } catch (e) {
                console.log(e.error);
                console.log(e.message);
                alert(
                    "시스템 작업 중이거나 장애가 있습니다. 잠시 후 다시 사용하시기 바랍니다."
                );
                return false;
            }

            // 데이터 없음
            if (totalCount == 0 || data === "") {
                alert("등록된 데이터가 없습니다.");
                barcodeList = [];
                licn = "";
                idx = 0;
                document.getElementById("tables").innerHTML = licn;
                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' + platForm + "]total record=" + idx;

                document.getElementById("count").value = 0 + " (Box)";
                document.getElementById("total-weight").value = 0 + " (kg)";

                return false;
            }

            document.getElementById("tables").innerHTML = "";
            document.getElementById("totalrec").innerHTML = "";

            // 조회결과
            try {
                if (platForm == "computer") {
                    if (category == 1) {
                        codeOutClass.makeTabletr2Mobile(data);
                    } else {
                        // category == 2::모바일 조회 데이터
                        codeOutClass.makeTableHead();
                        codeOutClass.makeTabletr(data);
                        if (table1.rows.length > 2) setSort();
                    }
                } else {
                    // category == 2::모바일 조회 데이터
                    codeOutClass.makeTabletr2Mobile(data);
                }

                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' + platForm + "]total record=" + idx;
            } catch (e) {
                alert(
                    "ln587::시스템 장애 입니다. 잠시 후 재시도 하시기 바랍니다." + e.message
                );
                return false;
            }

            // -------------------------------------------------------
            //  메시지 표시
            // -------------------------------------------------------
            const ment = "\nBL#: " + idx + "건 조회하였습니다.\n";
            document.getElementById("notification-container").innerHTML =
                "<p><font color='white'>" + ment + "</font></p>";
            showNotification();
            return true;
        }

        // actionBtn 조회 시작
        function actionBtn_Search() {
            const category = document.getElementById("category").value; // 1:입력, 2:조회
            const increate_dt = document.getElementById("createDate").value;
            const lc_no = document.getElementById("lcNumber").value;
            const barcode_out = document.getElementById("barcodeOutput").value;
            const store_no = document.getElementById("storeNo").value;
            const pack_color = document.getElementById("color").value;
            // 상품명 가져오기
            const selectElement = document.getElementById('code_exp');
            let prod_nm = selectElement.options[selectElement.selectedIndex].text;
            if (prod_nm == '상품명#선택') prod_nm = '';

            // category=1 (입력모드) 일때는 lcNumber 확인
            if (category == "1" && lc_no == "") {
                alert("BL# 4자리를 입력하세요.");
                return false;
            }
            const rtn = this.searchAjax_OutKwd(
                category,
                increate_dt,
                lc_no,
                store_no,
                pack_color,
                barcode_out,
                prod_code,
                prod_nm
            );
            return rtn;
        }

        //  재고상세조회    
        $(document).ready(function() {
            $('#joeun_cnt').click(function() {
                searchAjax_Invt();
            });
        });

        //  재고조회, 완출점(002)
        $(document).ready(function() {
            $('#invt002Btn').click(function() {
                searchAjax_Invt002();
            });
        });
    </script>

<?php }
add_shortcode('barcodeOut', 'barcodeOutShortCode');
