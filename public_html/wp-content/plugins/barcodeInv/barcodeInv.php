<?php
/*
 Plugin Name: barcodeInv : 바코드출고
 Plugin URI: https://joeunfish.online
 Description: 바코드출고
 Version: 0.9
 Author: Monolith
 Author URI: https://uloca.net
 License: GPL2
 */

function barcodeInvShortCode()
{
    date_default_timezone_set('Asia/Seoul');
    require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

    $dbConn   = new dbconnClassFish;
    $conn     = $dbConn->conn();
    mysqli_set_charset($conn, 'utf8');
    mysqli_query($conn, "SET time_zone = '+09:00'");

    $platForm = $dbConn->MobileCheck();
    // $isAdmin = $dbConn->checkUserIsAdmin();
    $isEditorOrAbove = $dbConn->is_user_editor_or_above(); // 관리자 또는 편집자 이상

?>

    <!DOCTYPE html>
    <html lang="kr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">
        <title>barcode scanner IN</title>

        <script src="/wp-content/plugins/barcodeInv/CodeInvClass.js?ver=<?php echo time(); ?>"></script>
        <script src="/wp-content/plugins/barcodeInv/CodeInvSumClass.js?ver=<?php echo time(); ?>"></script>
        <script>
            // 인스턴스 생성을 최우선으로 실행
            var codeOutClass = new CodeInvClass();
            var codeSumClass = new CodeInvSumClass();
            var isEditorOrAbove = <?php echo json_encode($isEditorOrAbove); ?>; // 관리자 또는 편집자 이상
        </script>
        <script rel="preload" src="/jof/js/common.js?ver=<?php echo time(); ?>"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js?ver=<?php echo time(); ?>" async></script>
        <!-- css: 캐시 방지를 위해 time() 사용 -->
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/barcodeInv/joeunfishInv.css?ver=<?php echo time(); ?>" />

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
                    <div id="entry-title">1층출고(연어)</div>
                </div>
                <form action="" name="myForm" id="myForm" method="get">
                    <!-- 첫 번째 행 -->
                    <div class="form-row">
                        <select class="form-field" id="category" placeholder="입력">
                            <option value="1">납기</option>
                            <option value="3">삭제</option>
                            <option value="2">조회</option>
                        </select>
                        <input type="date" class="form-field" id="createDate" onclick="" onkeydown="if(event.key === 'Enter') { event.preventDefault(); document.getElementById('actionBtn').click(); }">
                        <select class="form-field" id="lcNumber" placeholder="BL#4자" autofocus>
                            <option value="">BL#4자</option>
                        </select>
                        <input type="text" class="form-field" placeholder="갯수(Box)" id="count" readonly>
                        <input type="text" class="form-field" placeholder="총중량(kg)" id="total-weight" readonly>
                    </div>
                    <!-- 두 번째 행 1bacode -->
                    <div class="form-row">
                        <select class="form-field storeNo" id="storeNo" placeholder="점포#선택" onchange="">
                            <option value="">점포#선택</option>
                        </select>
                        <select class="form-field" id="docNo" placeholder="PO#선택" onchange="document.getElementById('actionBtn').click();">
                            <option value="">PO#선택</option>
                        </select>
                        <input type="text" class="form-field" id="barcodeOutput" placeholder="Barcode Scan 출고" maxlength="24" style="min-width: 51%;" inputmode="none">
                    </div>
                    <!-- 세 번째 행 -->
                    <div class="form-row" style="height: 40px; font-size: 15px">
                        <input type="text" class="form-field" placeholder="제조일자" readonly id="productionDate">
                        <input type="text" class="form-field" placeholder="소비기한" readonly id="expiDate">
                        <input type="text" class="form-field" placeholder="중량(kg)" readonly id="weightInput">
                    </div>
                    <!-- 네 번째 행 -->
                    <div class="form-row">
                        <select class="form-field" placeholder="상품명#선택" id="code_exp">
                            <option value="">상품명#선택</option>
                            <option value="2000000560770">연어필렛</option>
                            <option value="0200000651343">연어(스테이크)</option>
                            <option value="2000000668592">연어(스테이크용)벌크</option>
                            <option value="2000000614117">MAP연어필렛</option>
                            <option value="0200000684213">MAP연어(스테이크용)</option>
                        </select>
                        <input type="text" class="form-field" placeholder="조은점(재고)" readonly id="joeun_cnt">
                    </div>
                </form>

                <div class="button-container search-row" id="confirmBtn" style="display: flex; justify-content: center; align-items: center; position: relative; gap: 10px; flex-wrap: wrap;">
                    <!-- 날짜/BL# 조건 조회 버튼 및 BL# 별 총중량 및 건수를 확인하는 버튼  -->
                    <button id="actionBtn">조회</button>
                    <button id="finBtn">출고확정</button>
                    <!-- <button id="initBtn">초기화</button> -->

                    <?php if ($platForm == 'computer') {    ?>
                        <!-- 송장출력 -->
                        <button id="invPrint">INVOICE</button>
                        <!-- 총괄출력 -->
                        <button id="sumPrint">SUMMARY</button>
                        <!-- <button id="invBtn">INV출력</button> -->
                        <button id="toExcel" onclick="tableToExcelInv()">엑셀다운</button>
                        <button id="manual">사용법</button>
                    <?php } ?>
                    <button id="deliveryMove">점포이동</button>
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

                <!-- loading -->
                <div id='loading' style='display: none;'>
                    <div id="code1"><img src='/img/loading/8.gif'></div>
                </div>

                <!-- table 건수 요약 -->
                <div id='totalrec'></div>

                <!-- AJAX로 불러온 목록이 여기에 표시됩니다. -->
                <div id="listContainer"></div>

            </div>
        </div>

        <div id="modalContainer"></div>

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
        var barcodeInv_srv = '/wp-content/plugins/barcodeInv/barcodeInv_srv.php';
        var barcodeOut_srv = '/wp-content/plugins/barcodeOut/barcodeOut_srv.php';

        var newItem = []; // 입력 barcode Item array
        var rtnCnt = 0; // 가져온 Data갯수
        var idx = 0; // tables Row 갯수
        var parm = ""; // kwd
        var totalWeight = 0;
        var barcodeList = []; // 목록 데이터를 저장할 배열
        let gInvoiceYn = 'n';

        let table1;
        jQuery(document).ready(function($) {
            // PHP에서 전달받은 사용자 ID 사용
            user_no = myScriptData.userId; // wp에서 관리하는 ID
            user_id = myScriptData.userName; // 사용자ID(wp에서는 Name이라고 함)

            console.log("user_id=" + user_id);
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

        // 납기일자는 +1day
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const formattedTomorrow =
            tomorrow.getFullYear() +
            "-" +
            ("0" + (tomorrow.getMonth() + 1)).slice(-2) +
            "-" +
            ("0" + tomorrow.getDate()).slice(-2);

        document.getElementById('createDate').value = formattedTomorrow;

        // 점포# DB 코드,코드명(store_code, store_nm) -- category=c1
        function loadStoreOptions(category) {
            const increate_dt = document.getElementById('createDate').value;
            url = barcodeOut_srv + '?category=' + category + '&increate_dt=' + increate_dt;
            console.log("url=" + url);
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
                        text: '점포#선택'
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

        //===============================================
        // doc_no 조회::점포명 또는 날짜가 변경되면 재조회
        //===============================================
        function handleStoreNoOrCreateDateChange() {
            loadDocNoOptions('serDoc_no');

            // 조회데이터 삭제
            document.getElementById("tables").innerHTML = "";
        }

        // 점포명 또는 날짜가 변경되면 doc_no 조회
        document.getElementById('storeNo').addEventListener('change', handleStoreNoOrCreateDateChange);
        document.getElementById('createDate').addEventListener('change', handleStoreNoOrCreateDateChange);

        // PO# 조회 (doc_no)
        var gPoCount = 0;
        var gDocNo = '';

        // PO# 조회 (doc_no)
        function loadDocNoOptions() {
            const store_no = document.getElementById('storeNo').value;
            const increate_dt = document.getElementById('createDate').value;

            // 점포# 바꾸면 PO# 조회
            $url = barcodeInv_srv + '?category=serDoc_no&store_no=' + store_no + '&increate_dt=' + increate_dt;
            console.log("url=" + $url);

            $.ajax({
                url: $url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // console.log("data=" + data);
                    var selectElement = $('#docNo');
                    selectElement.empty(); // Clear existing options
                    selectElement.append($('<option>', {
                        value: '',
                        text: 'PO#전체'
                    }));

                    gPoCount = response.length;
                    gDocNo = response[0];

                    console.log("ln285::gPoCount=" + gPoCount + "::gDocNo=" + gDocNo);

                    response.forEach(function(doc) {
                        selectElement.append($('<option>', {
                            value: doc,
                            text: doc
                        }));
                    });
                    $('#docNo').val($('#docNo option:eq(1)').val());
                },
                error: function(xhr, status, error) {
                    console.error("Status: " + status);
                    console.error("Response: " + xhr.responseText);
                }
            });
        }

        // document.getElementById('code_exp').addEventListener('change', function() {
        //     document.getElementById('barcodeOutput').focus();
        // });

        // BL# 가져오기::DB 납품일자(increate_dt), BL#(lc_no)
        $(document).ready(function() {
            $.ajax({
                url: '/wp-content/plugins/barcodeInv/barcodeOutInv_srv.php?category=lc', // BL#: 신용장번호
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
            // 점포#선택
            loadStoreOptions('c1');
            // 재고표시
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

        // Enter keydown
        document.getElementById('barcodeOutput').addEventListener('keydown', function(e) {
            console.log("Enter keydown");
            if (e.key === 'Enter') {
                // 입력값이 비어있으면 blur 이벤트를 막고 커서를 유지
                if (!this.value.trim()) {
                    e.preventDefault();
                    return;
                }

                if (document.activeElement) {
                    document.activeElement.blur();
                }
                e.preventDefault();
            }
        });

        // 출고추가::barcode Validation - barcode Scan ::바코드입력
        document.getElementById('barcodeOutput').addEventListener('blur', function() {


            //  // category=2:조회일 때는 blur 취소
            let category = document.getElementById('category').value;
            if (category == 2) return;

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
                const prod_code = document.getElementById('code_exp').value;
                // 중량
                let weightInput = inputValue.slice(18, 23);
                const num = parseInt(weightInput, 10); // 숫자로 변환
                weightInput = num / 1000; // 1000으로 나누어 소수 형태로 만듦

                // 중량처리 (이미 앞에 했겠지만 추가 확인 用)
                if (prod_code === '2000000560770' || !prod_code) {
                    weight = Math.round(weightInput * 10) / 10;
                } else {
                    weight = Math.floor(weightInput * 10) / 10; // 소숫점 1번째 외 절사
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
                } else {
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
                    const increate_dt = document.getElementById('createDate').value; // 납품일자
                    const lc_no = document.getElementById('lcNumber').value; // BL#
                    const barcode_out = document.getElementById('barcodeOutput').value;
                    const store_no = document.getElementById('storeNo').value;
                    const prod_code = document.getElementById('code_exp').value;

                    const doc_no = document.getElementById('docNo').value;

                    console.log("store_no=" + store_no);
                    if (doc_no === '' && store_no !== '005') {
                        alert("PO#를 선택하세요.");
                        return;
                    }

                    // 출고확정은 추가 금지
                    console.log("gInvoiceYn= " + gInvoiceYn);

                    if (gInvoiceYn === 'y') {
                        alert("출고확정된 납품점입니다. 기한 내 [확정취소] 후 추가하시기 바랍니다.");
                        return;
                    }

                    if (store_no === '000') {
                        alert("조은점(재고):: 출고 대상이 아닙니다.");
                        document.getElementById('barcodeOutput').value = '';
                        return;
                    }

                    if (barcode_out.length !== 24) {
                        alert("바코드를 다시 스캔하세요!")
                        document.getElementById('barcodeOutput').focus();
                    } else {

                        // 출고::생산 시점에는 '1i' 입력+i(insert)
                        if (category == 1) {

                            const index = barcodeList.findIndex((item) => item.barcode_out === barcode_out);
                            if (index !== -1) {
                                alert("이미 추가된 제품입니다.");
                                document.getElementById('barcodeOutput').value = '';
                                document.getElementById('barcodeOutput').focus();
                                return;
                            }

                            // 상품명은 재고DB에서 callback 함수로 가져옴::barcode=inputValue
                            barcode_invt(this.value, function(name) {
                                console.log('prod_nm=' + name);
                                const prod_nm = name;

                                // 순서대로 array ::pack_color = "" 없음
                                const inputList = increate_dt + "," + lc_no + "," + store_no + "," + doc_no + "," +
                                    barcode_out + "," + manu_date + "," + expi_date + "," + weight + "," + prod_code + "," + prod_nm;
                                const listArray = inputList.split(",");
                                newItem = [];

                                // CodeInvClass.js 內 category=1u
                                if (!(codeOutClass.ajax_barcodeOut(listArray, category))) {
                                    document.getElementById('barcodeOutput').value = ''; // 바코드
                                    // document.getElementById('productionDate').value = ''; // 제조일자
                                    // document.getElementById('expiDate').value = ''; // 소비기한
                                    // document.getElementById('weightInput').value = ''; // 중량
                                    document.getElementById('barcodeOutput').focus(); 
                                } else {
                                    // 조회 이거나 실패
                                    // document.getElementById('barcodeOutput').value = ''; // 바코드
                                    document.getElementById('barcodeOutput').focus();
                                };

                            });

                            // 삭제: 재고처리 시 origin_store_code 의 값으로 원복
                        } else if (category == 3) {
                            if (store_no === '000' || store_no === '001' || store_no === '002' || store_no === '003' || store_no === '004') {
                                alert("이미 재고로 이전된 상태입니다.");
                                document.getElementById('barcodeOutput').focus();
                            } else {
                                // 원복
                                delBarcodeOut(barcode_out);
                                document.getElementById('barcodeOutput').focus();
                            }
                            document.getElementById('barcodeOutput').value = ''; // 바코드
                        }
                    }
                    return;
                }
            } else if (this.value === '') {
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
            if (value.length > 5) { // 입력된 값의 길이가 5를 초과하면
                this.value = value.slice(0, 5); // 처음 4자만 유지
            }
        });

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
        // document.getElementById('barcodeOutput').addEventListener('keydown', handleKeyDown);
        // document.getElementById('lcNumber').addEventListener('keydown', handleKeyDown); 
        // function handleKeyDown(e) {
        //     if (e.key === 'Enter') {
        //         console.log("Enter");
        //         if (document.activeElement) {
        //             document.activeElement.blur();
        //         }
        //         e.preventDefault();
        //     }
        // }

        // 조회시작 BL# 조회|일자별 click event
        let category = '';
        document.getElementById('actionBtn').addEventListener('click', function() {

            // ajax_barcodOut(); // ajax_barcodOut 함수 호출
            category = document.getElementById('category').value; // 1:입력, 2:조회
            const increate_dt = document.getElementById('createDate').value; // 납품일자
            const lc_no = document.getElementById('lcNumber').value; // BL#
            const store_no = document.getElementById('storeNo').value; // 점번호
            const doc_no = document.getElementById('docNo').value;
            const barcode_out = document.getElementById('barcodeOutput').value; //barcode
            // 상품명 가져오기
            const selectElement = document.getElementById('code_exp');
            let prod_nm = selectElement.options[selectElement.selectedIndex].text;
            if (prod_nm == '상품명#선택') prod_nm = '';

            // category=1(출고),3(삭제)  일때 조회는 BL# 입력 필수
            if ((category == '1' || category == '3') && store_no == '') {
                alert("[점포#선택] 하세요.")
                return;
            }

            // pack_color 대신에 doc_no 사용
            searchAjax_OutKwd(category, increate_dt, lc_no, store_no, doc_no, barcode_out, prod_nm);

            // 기출고 된 건은 [출고확정]버튼을 비활성화 시킴
            fInvoice_Yn(increate_dt, store_no, doc_no);
        });

        // document.getElementById('initBtn').addEventListener('click', function() {
        //     location.reload();
        // });
        if (platForm == "computer") {
            document.getElementById('invPrint').addEventListener('click', function() {
                const store_no = document.getElementById('storeNo').value;
                const deliveryDate = document.getElementById('createDate').value;
                const doc_no = document.getElementById('docNo').value;
                invoicePrint(store_no, deliveryDate, doc_no);
            });

            // summary 출력
            document.getElementById('sumPrint').addEventListener('click', function() {
                const deliveryDate = document.getElementById('createDate').value;
                // 엑셀 download용 조회
                console.log("summarySearch");
                summarySearch(deliveryDate);

                console.log("summaryPrint");
                summaryPrint(deliveryDate);
            });
        }

        document.getElementById('finBtn').addEventListener('click', function() {
            // barcodeList => tbarcodeinv table insert
            category = document.getElementById('category').value; // 1:출고, 2:조회, 3:삭제
            const store_no = document.getElementById('storeNo').value;
            const doc_no = document.getElementById('docNo').value;

            if (category !== '1') {
                alert("출고확정은 [납기일] 선택 후 '출고확정'이 가능합니다.")
                return;
            }

            if (store_no === '000') {
                console.log('납품점=' + store_no);
                alert("조은점(재고)는 '출고확정'대상이 아닙니다.")
                return;
            }

            // GS점(005)은 PO# 선택 없이 출고확정 가능
            console.log("store_no=" + store_no);
            if (doc_no === '' && store_no !== '005') {
                alert("PO#를 선택하세요.");
                return;
            }

            if (barcodeList.length > 0) {
                console.log("출고확정을 호출하였습니다.");
                finInv();

            } else {
                alert("출고 목록이 비어 있습니다.");
                return;
            }
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
            doc_no,
            barcode_out,
            prod_nm
        ) {
            // 재고 조회
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
                "&doc_no=" +
                doc_no +
                "&barcode_out=" +
                barcode_out +
                "&prod_nm=" +
                prod_nm +
                "&gPoCount=" + gPoCount +
                "&gDocNo=" + gDocNo;

            // category=1 조회이면 '1s'를 붙임.(서버에서 확인)
            if (category === '1') {
                server = barcodeOut_srv + "?category=" + category + 's' + param;
            } else if (category === '2') {
                server = barcodeOut_srv + "?category=" + category + 'sinv' + param;
            }

            console.log("[ln688::barcodeOut_srv]" + server);

            if (category == 2) move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);
        }

        function recvOutData(data) {
            move_stop();

            // console.log(data);
            // return;

            try {
                // 헤더정보
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
                alert("출고 대상 데이터가 없습니다.");
                barcodeList = [];
                licn = "";
                idx = 0;
                document.getElementById("tables").innerHTML = licn;
                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' + platForm + "]total record=" + idx;

                // 총건수(Box), 총중량 표시
                document.getElementById("count").value = "갯수(Box)"
                document.getElementById("total-weight").value = "총중량(kg)";

                return false;
            }

            document.getElementById("tables").innerHTML = "";
            document.getElementById("totalrec").innerHTML = "";

            // 조회결과
            try {
                if (platForm == "computer") {
                    // 1:출고, 3:삭제 => 모두 목록으로 표시함.
                    if (category == 1 || category == 3) {
                        codeOutClass.makeTabletr2Mobile(data);
                    } else {
                        // category == 2::조회 데이터
                        codeOutClass.makeTableHead();
                        codeOutClass.makeTabletr(data);
                        
                        // ★ 성능 최적화: requestAnimationFrame으로 정렬 기능 활성화
                        // 테이블 렌더링 후 다음 프레임에 정렬 함수 실행
                        if (table1.rows.length > 2) {
                            requestAnimationFrame(() => {
                                setTimeout(() => {
                                    try {
                                        if (typeof setSort === 'function') {
                                            setSort();
                                            console.log("✅ 정렬 기능 활성화됨 - 데이터 " + table1.rows.length + "행");
                                        }
                                    } catch (sortError) {
                                        console.error("[에러] setSort 실패:", sortError.message);
                                    }
                                }, 100); // 100ms 지연으로 안정성 확보
                            });
                        }
                    }
                } else {
                    // category == 2::모바일 조회 데이터
                    codeOutClass.makeTabletr2Mobile(data);
                }

                document.getElementById("totalrec").innerHTML =
                    '<font size="3em">[' + platForm + "]total record=" + idx;
            } catch (e) {
                alert(
                    "ln628::시스템 장애 입니다. 잠시 후 재시도 하시기 바랍니다." + e.message
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

            // 재고조회
            fetchInventoryData();

            return true;
        }

        // actionBtn 조회 시작
        function actionBtn_Search() {

            console.log("ln785::actionBtn_Search");

            const category = document.getElementById("category").value; // 1:입력, 2:조회
            const increate_dt = document.getElementById("createDate").value;
            const lc_no = document.getElementById("lcNumber").value;
            const barcode_out = document.getElementById("barcodeOutput").value;
            const store_no = document.getElementById('storeNo').value;
            const doc_no = document.getElementById("docNo").value;

            // 상품명 가져오기
            const selectElement = document.getElementById('code_exp');
            const prod_nm = selectElement.options[selectElement.selectedIndex].text;
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
                doc_no,
                barcode_out,
                prod_nm
            );
            return rtn;
        }

        document.getElementById('manual').addEventListener('click', function() {
            window.open('<?php echo home_url("/출고사용설명서/"); ?>', '_blank');
        });


        document.getElementById('deliveryMove').addEventListener('click', function() {
            if (barcodeList.length === 0) {
                alert("조회된 데이터가 없습니다. 먼저 조회를 해주세요.");
                return;
            }

            // 상품명#선택을 해야만 이동 가능
            const selectElement = document.getElementById('code_exp');
            const prod_nm = selectElement.options[selectElement.selectedIndex].text;
            if (prod_nm == '상품명#선택') {
                alert("상품명을 선택하세요.");
                return;
            }

            // 이미 모달이 있으면 중복 생성 방지
            if (document.getElementById('moveModal')) {
                return;
            }

            // 모달 HTML 생성
            const modalHtml = `
                <div id="moveModal" class="modal" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.2); display:flex; align-items:center; justify-content:center; z-index:9999;">
                    <div class="modal-content" style="width:400px; padding:24px 20px 18px 20px; border-radius:12px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                        <div class="modal-header" style="margin-bottom:16px;">
                            <div class="modal-title" style="font-size:18px; font-weight:bold;">[점포/PO 이동]</div>
                        </div>
                        <form>
                            <div style="display:flex; gap:8px; margin-bottom:18px;">
                                <select id="moveStoreNo" style="flex:1; min-width:0; width:48%; max-width:180px;"></select>
                                <select id="moveDocNo" style="flex:2; min-width:0; width:68%; max-width:200px;"></select>
                            </div>
                            <div style="display:flex; justify-content:center; gap:12px;">
                                <button type="button" id="moveConfirmBtn" style="min-width:70px;">이동</button>
                                <button type="button" id="moveCancelBtn" style="min-width:70px;">취소</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.getElementById('modalContainer').insertAdjacentHTML('beforeend', modalHtml);

            // 점포 옵션 불러오기
            loadStoreOptions_Modal();

            // 이벤트 연결
            const moveStoreNo = document.getElementById('moveStoreNo');
            const moveDocNo = document.getElementById('moveDocNo');
            const moveCancelBtn = document.getElementById('moveCancelBtn');
            const moveConfirmBtn = document.getElementById('moveConfirmBtn');

            if (moveStoreNo) {
                moveStoreNo.addEventListener('change', function() {
                    loadDocNoOptions_Modal();
                });
            }
            if (moveCancelBtn) {
                moveCancelBtn.addEventListener('click', function() {
                    document.getElementById('moveModal').remove();
                });
            }
            if (moveConfirmBtn) {
                moveConfirmBtn.addEventListener('click', function() {
                    // 실제 이동 처리 함수 호출 (예시)
                    saveModalData();
                });
            }
        }); // deliveryMove

        // 점포 옵션 불러오기 (모달용)
        function loadStoreOptions_Modal() {
            $.ajax({
                url: barcodeOut_srv + '?category=c1',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var selectElement = $('#moveStoreNo');
                    selectElement.empty();
                    selectElement.append($('<option>', {
                        value: '',
                        text: '점포#선택'
                    }));
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            selectElement.append($('<option>', {
                                value: item.store_code,
                                text: item.store_nm + ' (' + item.store_code + ')'
                            }));
                        });
                    }
                    // 점포 선택 후 PO#도 자동 로딩
                    loadDocNoOptions_Modal();
                }
            });
        }

        // PO# 옵션 불러오기 (모달용)
        function loadDocNoOptions_Modal() {
            const store_no = document.getElementById('moveStoreNo').value;
            if (!store_no) {
                $('#moveDocNo').empty().append($('<option>', {
                    value: '',
                    text: 'PO#선택'
                }));
                return;
            }
            const increate_dt = document.getElementById('createDate').value;
            $.ajax({
                url: barcodeInv_srv + '?category=serDoc_no&store_no=' + store_no + '&increate_dt=' + increate_dt,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    var selectElement = $('#moveDocNo');
                    selectElement.empty();
                    selectElement.append($('<option>', {
                        value: '',
                        text: 'PO#선택'
                    }));
                    if (response && response.length > 0) {
                        response.forEach(function(doc) {
                            selectElement.append($('<option>', {
                                value: doc,
                                text: doc
                            }));
                        });
                        // 첫 번째 PO가 있으면 자동 선택
                        selectElement.prop('selectedIndex', 1);
                    } else {
                        // PO#이 없을 때 알림
                        alert("해당 점포의 PO번호를 찾지 못했습니다. 확인 후 다시 진행하시기 바랍니다.");
                        return;
                    }
                }
            });
        }

        // 모달 저장 처리 (예시)
        function saveModalData() {
            // 이동전 점포 코드(예: '050')
            const select = document.getElementById('storeNo');
            const selectedText = select.options[select.selectedIndex].text; // '부산점 (856)'
            const originStoreCode = selectedText.split(' (')[1].replace(')', ''); // '856'
            const originDocNo = document.getElementById('docNo').value; // 기존 doc_no(예: '20258563492259')
            const prodCode = document.getElementById('code_exp').value; // 상품코드 가져오기
            console.log("ln973::prodCode=" + prodCode); // 상품코드 출력

            // 이동 후 점포 코드(예: '050')
            const select2 = document.getElementById('moveStoreNo');
            const selectedText2 = select2.options[select2.selectedIndex].text; // '울산점 (050)'
            const targetStoreNm = selectedText2.split(' (')[0]; // '울산점'
            const targetStoreCode = selectedText2.split(' (')[1].replace(')', ''); // '050'

            const targetDocNo = document.getElementById('moveDocNo').value; // 이동 후 doc_no(예: '20250592248901')
            const storeNo = '코스트코홀세일 ' + targetStoreNm;

            // === 추가: 이동전/이동후 점포, PO가 동일하면 경고 후 return ===
            if (originStoreCode === targetStoreCode && originDocNo === targetDocNo) {
                alert("점번호와 PO#번호가 동일합니다.");
                return;
            }

            if (!targetStoreCode || !targetDocNo || !storeNo || !originDocNo || !originStoreCode || !prodCode) {
                alert('필수값이 누락되었습니다.');
                return;
            }

            const url = barcodeInv_srv + '?category=moveBarcode';
            console.log("[barcodeInv_srv::moveBarcode]" + url);
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    category: 'moveBarcode',
                    target_store_code: targetStoreCode,
                    target_doc_no: targetDocNo,
                    store_no: storeNo,
                    origin_store_code: originStoreCode,
                    origin_doc_no: originDocNo,
                    prod_code: prodCode // prod_code 추가!
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('이동 성공!');
                    document.getElementById('moveModal').remove();
                    document.getElementById('actionBtn').click();
                } else {
                    alert('이동 실패: ' + (data.message || data.error));
                }
            })
            .catch(err => {
                alert('서버 오류: ' + err);
            });
        }
    </script>

<?php }
add_shortcode('barcodeInv', 'barcodeInvShortCode');
