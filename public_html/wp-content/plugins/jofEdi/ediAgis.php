<?php

/**
 * Plugin Name: edi_agis
 * Plugin URI: https://joeunfish.net
 * Description: AGIS 주문관리
 * Version: 2.0.0
 * Author: 정순장
 * Author URI: https://uloca.net
 * License: GPL2
 */

// ediRcv_srv_job.php 파일을 먼저 실행
// require_once 'ediRcv_srv_job.php';
// 화면에 ediRcv_srv_job.php 파일을 먼저 실행했음을 표시합니다.
// echo "ediRcv_srv_job.php (CO)주문을 업로드 했습니다.";

add_action('wp_ajax_upload_excel_form', 'edi_upload_excel_form_callback');
add_action('wp_ajax_nopriv_upload_excel_form', 'edi_upload_excel_form_callback');

function edi_upload_excel_form_callback()
{
    error_log('[✅ edi_upload_excel_form_callback 진입]');

    // 1. 관리자 또는 에디터 권한 확인
    if (!current_user_can('editor') && !current_user_can('administrator')) {
        error_log('❌ 권한 없음');
        wp_send_json_error('❌ 권한이 없습니다.');
    }

    // 2. 파일 유효성 검사
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        error_log('❌ 파일 업로드 오류: ' . print_r($_FILES, true));
        wp_send_json_error('❌ 파일 업로드 오류');
    }

    // 3. 확장자 검사
    $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'])) {
        error_log('❌ 엑셀 파일 아님: ' . $_FILES['excel_file']['name']);
        wp_send_json_error('❌ 엑셀 파일만 업로드 가능합니다.');
    }

    // 4. 워드프레스 업로드 경로
    $upload_info = wp_upload_dir();
    $upload_dir = $upload_info['basedir'];
    $upload_url = $upload_info['baseurl'];

    // 5. 파일 저장 경로 및 이름 지정 (확장자 무조건 xlsx)
    $file_name = 'daily_order.xls';
    $target_file = trailingslashit($upload_dir) . $file_name;

    // 6. 저장 폴더 권한 확인
    if (!is_writable($upload_dir)) {
        error_log("❌ 업로드 경로에 쓰기 권한 없음: $upload_dir");
        wp_send_json_error("❌ 업로드 경로에 쓰기 권한이 없습니다: $upload_dir");
    }

    // 7. 임시 파일 존재 확인
    if (!file_exists($_FILES['excel_file']['tmp_name'])) {
        error_log('❌ 임시파일 없음: ' . $_FILES['excel_file']['tmp_name']);
        wp_send_json_error('❌ 임시파일이 존재하지 않습니다.');
    }

    // 8. 파일 저장 (변환 없이 이름만 바꿔서 저장)
    if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $target_file)) {
        $error = error_get_last();
        error_log('❌ move_uploaded_file 실패: ' . print_r($error, true));
        wp_send_json_error('❌ 파일 저장 실패: ' . print_r($error, true));
    }

    error_log('✅ 엑셀 업로드 성공: ' . $target_file);

    // 9. 결과 반환
    wp_send_json_success(array(
        'file_path' => $target_file,
        'file_url' => trailingslashit($upload_url) . $file_name,
        'file_name' => $file_name,
        'message' => '✅ 업무일지 엑셀 파일이 성공적으로 업로드되었습니다.'
    ));
}

add_action('wp_ajax_run_uplus_script', 'run_uplus_script_callback');
function run_uplus_script_callback()
{
    // Check for user permissions (editor or administrator)
    if (!current_user_can('editor') && !current_user_can('administrator')) {
        wp_send_json_error('권한이 없습니다.');
        return;
    }

    $command = '/home/joeunfish.net/backup/edi_sequential_job.sh A 2>&1';
    $output = shell_exec($command);

    if ($output === null) {
        wp_send_json_error('스크립트 실행에 실패했습니다.');
    } else {
        wp_send_json_success("스크립트 실행 완료:\n" . $output);
    }
}


// Your plugin code goes here
function ediAgis()
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
    <html lang="ko">

    <head>
        <meta charset="UTF-8">
        <title>주문 정보</title>
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/jofEdi/joeunfishAgis.css" />
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>

        <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

        <style>
            #resultsContainer table tbody tr:hover {
                background-color: #bbf731 !important;
                cursor: pointer;
            }
        </style>

    </head>

    <body>

        <?php   
            // 공통 네비게이션 메뉴 포함
            require_once(WP_PLUGIN_DIR . '/jofMain/jofNavigation.php');
            renderJofNavigation();
        ?>

        <div id="contents">
            <div class="form-menu" style="display: grid; grid-template-columns: auto 1fr; align-items: center;">
                <div id="entry-title">&nbsp;&nbsp;(CO)주문관리</div>
                <div style="text-align: center;">
                    <span style="color: white; cursor: pointer;" onclick="showOrders(); return false;">주문조회 </span> |
                    <span style="color: white; cursor: pointer;" onclick="showCodeManagement(); return false;">코드조회</span>
                </div>
            </div>

            <div id="orderView" style="display: block;">
                <div class="search-row" style="display: flex; justify-content: flex-start; align-items: center; position: relative; gap: 5px; flex-wrap: wrap;">
                    <label for="arrivalDate" style="margin-left: 10px; margin-right: 10px;">입고일자:</label>
                    <input type="date" id="arrivalDate" name="arrivalDate" style="margin-bottom: 0px; height: 40px; width: 130px;" onchange="fetchOrders()" onkeypress="if(event.keyCode==13) fetchOrders();">
                    <button onclick="fetchOrders()" style="height: 40px; width: 100px;">조회</button>

                    <?php if ($platForm === 'computer') { ?>
                        <button onclick="downloadExcel()" id="excelDownBtn" style="height: 40px; width: 100px;">엑셀다운</button>
                        <button onclick="dejonExcel()" id="dejonExcelBtn" style="height: 40px; width: 100px;">DEJON-엑셀</button>
                        <button id="excel-day">일지</button>
                        <button id="excel-form">양식</button>
                        <!-- 버튼 옆에 파일 input 추가 (숨김) -->
                        <input type="file" id="excel-upload-input" accept=".xls,.xlsx" style="display:none;">
                        <button onclick="run_uplus_script_callback()" id="UplusBtn">U+전자수발주</button>
                    <?php } ?>
                    <div style="min-width: 95px; flex-shrink: 0;"></div>
                </div>
                <div id="orderList" style="margin-top: 10px; margin-bottom: 10px; font-weight: bold; font-size: 20px;">주문목록</div>
                <div id="resultsContainer" class="type10"></div>
            </div>

            <div id="codeView" style="display: none;">
                <!-- 코드 관리 내용이 여기에 표시됩니다 -->
                ※보관방법: F(냉동), C(냉장), A(상온), E(빈란)
                <div id="codeResultsContainer" style="overflow: auto; height: 400px;"></div>
            </div>

            
            <div id='loading' style='display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;'>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                    <img src="/img/loading/0.gif" alt="로딩중">
                </div>
            </div>

            <script>
                let platForm = 'computer';
                let subul_srv = '/wp-content/plugins/jofSubul/subul_srv.php';

                document.addEventListener('DOMContentLoaded', function() {
                    // 엑셀 양식 업로드
                    const excelFormBtn = document.getElementById("excel-form");
                    const excelInput = document.getElementById("excel-upload-input");

                    if (excelFormBtn && excelInput) {
                        excelFormBtn.addEventListener("click", function() {
                            // 파일 업로드 기능 유지
                            excelInput.value = ""; // 이전 선택 초기화
                            excelInput.click();
                        });

                        excelInput.addEventListener("change", function() {
                            if (excelInput.files.length > 0) {
                                const formData = new FormData();
                                formData.append("action", "upload_excel_form");
                                formData.append("excel_file", excelInput.files[0]);

                                fetch("/wp-admin/admin-ajax.php", {
                                        method: "POST",
                                        body: formData,
                                        credentials: "same-origin"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert("업무일지 엑셀 파일이 업로드되었습니다.");
                                        } else {
                                            alert("업로드 실패: " + (data.data || "알 수 없는 오류"));
                                        }
                                    })
                                    .catch(err => {
                                        alert("업로드 중 오류 발생");
                                    });
                            }
                        });
                    }
                });

                // WP 사용자Id 및 권한 확인
                jQuery(document).ready(function($) {
                    user_no = myScriptData.userId;
                    user_id = myScriptData.userName;
                    user_roles = myScriptData.userRoles;

                    // 화면의 너비가 760px 이하인 경우 모바일로 간주
                    if (window.matchMedia("only screen and (max-width: 760px)").matches) {
                        platForm = 'mobile';
                        // 엑셀 다운로드 버튼 숨기기 - 요소 존재 여부 확인 후 처리
                        const excelDownBtn = document.getElementById('excelDownBtn');
                        const dejonExcelBtn = document.getElementById('dejonExcelBtn');
                        const uplusBtn = document.getElementById('UplusBtn');

                        if (excelDownBtn) excelDownBtn.style.display = 'none';
                        if (dejonExcelBtn) dejonExcelBtn.style.display = 'none';
                        if (uplusBtn) uplusBtn.style.display = 'none';
                    } else {
                        // 그렇지 않은 경우 데스크탑으로 간주
                        platForm = 'computer';
                    }
                });

                function run_uplus_script_callback() {
                    if (platForm === 'mobile') {
                        // 모바일에서는 실행하지 않음
                        return;
                    }
                    console.log('U+전자수발주 버튼 클릭');
                    if (!confirm('전체(수신+미수신) 주문데이터를 새로 받습니다.\n (★ 데이터량에 따른 과금 발생 ★)\n' +
                        'U+전자수발주 스크립트를 실행하시겠습니까?')) {
                        return;
                    }

                    // 로딩 표시
                    const loadingDiv = document.getElementById('loading');
                    if(loadingDiv) loadingDiv.style.display = 'block';

                    // admin-ajax.php에 보낼 데이터 준비
                    const data = new URLSearchParams();
                    data.append('action', 'run_uplus_script');

                    fetch('/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        body: data,
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        }
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            // 성공 메시지를 alert으로 표시하고, 로딩을 즉시 숨깁니다.
                            document.getElementById('loading').style.display = 'none';
                            alert('EDI 데이터 수신 작업이 시작되었습니다. 약 1분 후 [주문조회] 버튼을 눌러주세요.');
                            console.log('서버 응답:', result.data);
                        } else {
                            // 로딩을 숨기고 실패 메시지를 표시합니다.
                            document.getElementById('loading').style.display = 'none';
                            alert('오류: ' + (result.data || '알 수 없는 오류가 발생했습니다.'));
                        }
                    })
                    .catch(err => {
                        console.error('스크립트 실행 중 오류:', err);
                        document.getElementById('loading').style.display = 'none';
                        alert('스크립트 실행 중 통신 오류가 발생했습니다.');
                    });
                }

                function ediRcv_srv_job() {
                    // 서버의 PHP 스크립트를 실행합니다.
                    jQuery.ajax({
                        url: '/wp-content/plugins/jofEdi/ediRcv_srv_job.php',
                        type: 'GET',
                        success: function(data) {
                            console.log('EDI 재적용 스크립트 실행 완료:', data);
                            // 데이터 처리가 완료된 후 주문 조회를 자동으로 다시 실행합니다.
                            fetchOrders();
                        },
                        error: function(xhr, status, error) {
                            console.error('EDI 재적용 스크립트 실행 중 오류 발생:', error);
                        }
                    });
                }

                function showOrders() {
                    var orderView = document.getElementById('orderView');
                    var codeView = document.getElementById('codeView');
                    if (orderView && codeView) {
                        orderView.style.display = 'block';
                        codeView.style.display = 'none';
                    }
                }

                function showCodeManagement() {
                    var orderView = document.getElementById('orderView');
                    var codeView = document.getElementById('codeView');
                    if (orderView && codeView) {
                        codeView.style.display = 'block';
                        orderView.style.display = 'none';
                    }

                    // 사용자가 편집자 또는 관리자인 경우에만 fetchDataForCode() 함수 실행
                    console.log(user_roles);
                    console.log(user_roles === 'editor' || user_roles === 'administrator');

                    if (user_roles.includes('editor') || user_roles.includes('administrator')) {
                        // 사용자가 편집자 또는 관리자인 경우 실행할 코드
                        fetchDataForCode();
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    showOrders();
                });

                document.addEventListener('DOMContentLoaded', function() {
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    const formattedTomorrow = tomorrow.getFullYear() + '-' + ('0' + (tomorrow.getMonth() + 1)).slice(-2) + '-' + ('0' + tomorrow.getDate()).slice(-2);

                    console.log('[ln:225] 입고일자: ', formattedTomorrow);
                    document.getElementById('arrivalDate').value = formattedTomorrow;
                    document.getElementById('orderList').textContent = `주문목록 (${formattedTomorrow})`;

                    fetchOrders(); 
                });

                document.addEventListener('DOMContentLoaded', function() {
                    const arrivalDateInput = document.getElementById('arrivalDate');
                    arrivalDateInput.addEventListener('keypress', function(event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            fetchOrders();
                        }
                    });
                });

                function fetchOrders() {
                    move();

                    const arrivalDate = document.getElementById('arrivalDate').value;
                    const url = `/wp-content/plugins/jofEdi/ediAgis_srv.php?category=order&date=${arrivalDate}`;
                    console.log('[ln:191] url:', url);
                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            const totalCount = data.response.body.totalCount;
                            if (totalCount > 0) {
                                document.getElementById('orderList').textContent = `주문(${arrivalDate})(총 ${totalCount}개의 상품)`;
                                let output = '';

                                if (platForm === 'mobile') {
                                    const items = data.response.body.items;

                                    // 먼저 총합계 목록 표시
                                    output += `
                                         <div id="summary-list" class="summary-list">
                                            <ul style="list-style: none; padding: 0; margin: 0;">`;

                                    items.forEach((item, index) => {
                                        let backgroundColor = item.product_name.includes('연어') ? '#ffff00' : 'white';
                                        output += `
                                            <li class="summary-item" 
                                                style="padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; font-size: 14px; background-color: ${backgroundColor};"
                                                onclick="showProductDetail(${index})">
                                                <span style="flex: 0 0 24px; color: #666;">${index + 1}.</span>
                                                <span style="flex: 1; font-weight: bold;">${item.product_name}</span>
                                                <span style="color: #d00; font-weight: bold;">${item['총합계']}</span>
                                            </li>`;
                                    });
                                    output += `</ul></div>`;

                                    // 상품 상세 카드들 (초기에는 숨김)
                                    output += `<div id="product-details" style="display: none;">`;
                                    items.forEach((item, index) => {
                                        let productClass = '';
                                        if (item.product_name.startsWith('연어')) {
                                            productClass = 'salmon';
                                        } else if (item.product_name.includes('데친문어')) {
                                            productClass = 'octopus';
                                        }

                                        output += `
                                            <li id="product-${index}" class="product-item ${productClass}" 
                                                style="display: none; list-style: none; margin-bottom: 3px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                                <div style="padding: 3px; background-color: #1E73BE; color: white; height: 40px; line-height: 40px;">
                                                    <span onclick="showSummaryList(event)" style="float: left; font-size: 16px;">← 목록</span>
                                                    <span style="margin-left: 10px; font-size: 16px;">${item.product_name}</span>
                                                </div>
                                                <div class="inventory-row" style="display: flex; align-items: center; margin: 3px; background-color: #f5f5f5; padding: 3px;">
                                                    <div style="flex: 0 0 40px; font-size: 14px;">재고:</div>
                                                    <div style="flex: 1;">
                                                        <input type="text" class="stock-input" 
                                                            style="width: 100%; padding: 3px; text-align: right; font-size: 14px;"
                                                            value="${numberWithCommas(item['재고'])}"
                                                            data-store-code="${item.product_code}"
                                                            onchange="updateStock(this)"
                                                            maxlength="6">
                                                    </div>
                                                </div>
                                                <div class="orders-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1px; padding: 1px;">`;

                                        // 주문량 표시
                                        Object.keys(item).forEach(key => {
                                            if (!['product_name', 'product_code', '재고'].includes(key) &&
                                                item[key] !== '0') {
                                                let value = item[key];
                                                if (value.endsWith('.0')) {
                                                    value = value.slice(0, -2);
                                                }

                                                const isTotal = key === '서울소계' || key === '지방소계' || key === '총합계';
                                                const style = isTotal ?
                                                    'grid-column: 1 / -1; background-color: #e6e6e6; margin-top: 1px;' :
                                                    'background-color: #ffffff;';
                                                const textColor = key === '총합계' ? 'color: #d00;' : '';

                                                output += `
                                                    <div class="order-item" style="padding: 3px; border: 1px solid #eee; ${style}">
                                                        <div style="font-size: 14px; display: flex; justify-content: space-between; align-items: center; ${textColor}">
                                                            <span style="font-weight: ${isTotal ? 'bold' : 'normal'}">${key}</span>
                                                            <span style="font-weight: bold;">${value}</span>
                                                        </div>
                                                    </div>`;
                                            }
                                        });
                                        output += `</div></li>`;
                                    });
                                    output += `</div>`;

                                } else {
                                    output = '<table class="fixed-header" border="1" style="font-size: 11px; width: 100%; table-layout: fixed;"><thead><tr style="background-color: #d3d3d3; height: 50px;">';

                                    Object.keys(data.response.body.items[0]).forEach(key => {
                                        if (key === 'product_name') {
                                            const dateOptions = {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                weekday: 'short'
                                            };

                                            // 오늘 날짜는 헤드 표시
                                            const displayDate = new Date(arrivalDate).toLocaleDateString('ko-KR', dateOptions);
                                            output += `<th style="width: 150px; background-color: black; color: white; font-size: 13px; font-weight: bold;">${displayDate}</th>`;
                                        } else if (key === '재고') {
                                            output += `<th style="width: 70px;">${key}</th>`;
                                        } else if (key === '총합계') {
                                            output += `<th style="width: 60px;">${key}</th>`;
                                        } else if (key === 'product_code') {
                                            // 이 경우는 그냥 넘김
                                        } else {
                                            output += `<th>${key}</th>`;
                                        }
                                    });
                                    output += '</tr></thead><tbody>';

                                    const items = data.response.body.items;
                                    let rowColor = true;

                                    items.forEach(item => {
                                        let color;
                                        if (item.product_name.startsWith('연어')) {
                                            color = 'yellow';
                                        } else if (item.product_name.includes('데친문어')) {
                                            color = '#ccffff';
                                        } else {
                                            color = rowColor ? 'white' : '#f2f2f2';
                                        }
                                        output += `<tr style="background-color: ${color}; height: 25px;">`;

                                        Object.keys(item).forEach(key => {

                                            if (key === '재고') {
                                                const numberWithCommas = (x) => {
                                                    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                                };
                                                output += `<td><input type="text" style="width: 50px; margin: 0px; padding: 1px; text-align: right; font-size: 11px;" value="${numberWithCommas(item[key])}" data-store-code="${item.product_code}" onchange="updateStock(this)" maxlength="6" onkeypress="return event.keyCode !== 13;"></td>`;
                                            } else {
                                                if (item[key].endsWith('.0')) {
                                                    item[key] = item[key].slice(0, -2);
                                                }
                                                if (item[key] !== '0') {
                                                    if (key === 'product_name') {
                                                        output += `<td style="font-weight:bold;">${item[key]}</td>`;
                                                    } else if (key === '총합계') {
                                                        output += `<td style="color:red; text-align: right;">${item[key]}</td>`;
                                                    } else if (key === 'product_code') {
                                                        // 이 경우는 그냥 넘김

                                                    } else {
                                                        output += `<td style="text-align: right;">${item[key]}</td>`;
                                                    }
                                                } else {
                                                    output += `<td></td>`;
                                                }
                                            }
                                        });
                                        output += '</tr>';
                                        rowColor = !rowColor;
                                    });
                                    output += '</tbody></table>';
                                }

                                // JavaScript 함수들을 별도로 정의
                                window.showProductDetail = function(index) {
                                    document.getElementById('summary-list').style.display = 'none';
                                    document.getElementById('product-details').style.display = 'block';
                                    document.querySelectorAll('.product-item').forEach(item => item.style.display = 'none');
                                    document.getElementById('product-' + index).style.display = 'block';
                                };

                                window.showSummaryList = function(event) {
                                    event.stopPropagation(); // 이벤트 버블링 방지
                                    document.getElementById('summary-list').style.display = 'block';
                                    document.getElementById('product-details').style.display = 'none';
                                };

                                document.getElementById('resultsContainer').innerHTML = output;
                                window.hasDataLoaded = true;

                                // 제품 카드 선택 함수 추가
                                window.selectProductCard = function(element) {
                                    // 모든 product-item에서 선택 스타일 제거
                                    document.querySelectorAll('.product-item').forEach(item => {
                                        item.style.border = '1px solid #ddd';
                                        item.style.boxShadow = 'none';
                                        item.style.backgroundColor = 'white'; // 기본 배경색으로 초기화
                                    });

                                    // 선택된 카드에 스타일 적용
                                    element.style.border = '2px solid #1E73BE';
                                    element.style.boxShadow = '0 2px 8px rgba(30, 115, 190, 0.3)';
                                    element.style.backgroundColor = '#BBF731'; // 선택된 카드 배경색 변경
                                }; // 여기에 중괄호와 세미콜론 추가

                                // 테이블 행 클릭 이벤트 추가
                                document.querySelectorAll('#resultsContainer table tbody tr').forEach(function(row) {
                                    row.addEventListener('click', function() {
                                        // 모든 행에서 선택된 클래스 제거
                                        document.querySelectorAll('#resultsContainer table tbody tr').forEach(function(r) {
                                            r.classList.remove('selected-row');
                                        });
                                        // 클릭된 행에 선택된 클래스 추가
                                        this.classList.add('selected-row');
                                    });
                                });
                            } else {
                                document.getElementById('orderList').textContent = `주문목록 (${arrivalDate})(상품이 없습니다)`;
                                document.getElementById('resultsContainer').innerHTML = "";
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching orders:', error);
                        })
                        .finally(() => {
                            move_stop();
                        });
                }

                function numberWithCommas(x) {
                    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }

                function updateStock(inputElement) {

                    let value = inputElement.value.replace(/[^0-9]/g, '');
                    const store_code = inputElement.getAttribute('data-store-code');
                    const arrivalDate = document.getElementById('arrivalDate').value;

                    // 천단위 콤마 추가
                    const numberWithCommas = (x) => {
                        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    };

                    // 업데이트된 값을 inputbox에 다시 표시
                    inputElement.value = numberWithCommas(value);

                    // 서버에 요청 보내기
                    const xhr = new XMLHttpRequest();
                    const url = `/wp-content/plugins/jofEdi/ediAgis_srv.php?category=invtUpdate&value=${value}&store_code=${store_code}&date=${arrivalDate}&user_id=${user_id}`;

                    console.log('[ln:285] url:', url);

                    xhr.open('GET', url, true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            console.log('서버 응답:', 'success');
                        } else if (xhr.readyState === 4) {
                            console.error('서버 요청 중 오류 발생:', xhr.statusText);
                        }
                    };
                    xhr.send();
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const inputElements = document.querySelectorAll('input[type="text"]');
                    inputElements.forEach(input => {
                        input.addEventListener('keypress', function(event) {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                const formElements = Array.from(document.querySelectorAll('input, button, select, textarea'));
                                const currentIndex = formElements.indexOf(this);
                                const nextElement = formElements[currentIndex + 1];
                                if (nextElement) {
                                    nextElement.focus();
                                }
                            }
                        });
                    });

                    // 일지 버튼 클릭 이벤트 처리 - 컴퓨터에서만 표시
                    const excelDayBtn = document.getElementById('excel-day');
                    if (excelDayBtn && platForm === 'computer') {
                        excelDayBtn.addEventListener('click', function() {
                            const deliveryDate = document.getElementById('arrivalDate').value;
                            tableToExcelCoDay(deliveryDate);
                        });
                    } else if (excelDayBtn) {
                        excelDayBtn.style.display = 'none';
                    }

                });

                function downloadExcel() {
                    if (!window.hasDataLoaded) {
                        alert("조회 후 엑셀다운로드 하시기 바랍니다.");
                        return;
                    }
                    const table = document.querySelector('#resultsContainer table');
                    const workbook = XLSX.utils.table_to_book(table, {
                        sheet: "Sheet1"
                    });

                    const ws = workbook.Sheets.Sheet1;
                    XLSX.utils.sheet_add_aoa(ws, [
                        ['']
                    ], {
                        origin: -1
                    }); // 빈 행 추가

                    const wbout = XLSX.write(workbook, {
                        bookType: 'xlsx',
                        bookSST: true,
                        type: 'binary'
                    });

                    function s2ab(s) {
                        const buf = new ArrayBuffer(s.length);
                        const view = new Uint8Array(buf);
                        for (let i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
                        return buf;
                    }

                    saveAs(new Blob([s2ab(wbout)], {
                        type: "application/octet-stream"
                    }), "주문목록_" + document.getElementById('arrivalDate').value + ".xlsx");
                }

                // 일지 엑셀 변환 - 엑셀다운운
                function tableToExcelCoDay(deliveryDate) {
                    console.log('ln520::deliveryDate=' + deliveryDate);

                    // 날짜 형식 변환
                    const date = new Date(document.getElementById("arrivalDate").value);
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const weekDay = ['일', '월', '화', '수', '목', '금', '토'][date.getDay()];
                    const formattedDate = `${date.getFullYear()}-${month}-${day}(${weekDay})`;

                    // 로딩 표시
                    const loadingDiv = document.createElement('div');
                    loadingDiv.id = 'excel-loading';
                    loadingDiv.innerHTML = '엑셀 파일 생성 중...';
                    document.body.appendChild(loadingDiv);

                    // 요청 데이터 생성
                    const requestData = {
                        deliveryDate: deliveryDate,
                        formattedDate: formattedDate
                    };

                    // AJAX 요청 실행
                    const url = "/wp-content/plugins/jofSubul/tableToExcelCoDay_srv.php?category=search_CoDay&deliveryDate=" + encodeURIComponent(deliveryDate) + "&print=true&remove_store_suffix=true";
                    console.log("ln703::url=" + url);

                    jQuery.ajax({
                        url: url,
                        type: 'POST',
                        data: JSON.stringify(requestData),
                        contentType: 'application/json',
                        xhrFields: {
                            responseType: 'blob'
                        },
                        success: function(response) {
                            // 로딩 제거
                            document.body.removeChild(loadingDiv);

                            // blob URL 생성
                            const blob = new Blob([response], {
                                type: 'application/vnd.ms-excel'
                            });
                            const url = window.URL.createObjectURL(blob);

                            // 파일 다운로드 링크 생성 및 클릭
                            const link = document.createElement('a');
                            link.href = url;
                            link.download = `업무일지_${formattedDate}.xls`; // 파일명 설정
                            document.body.appendChild(link);
                            link.click();

                            // 정리
                            window.URL.revokeObjectURL(url);
                            document.body.removeChild(link);

                            alert(`파일이 다운로드 폴더에 저장되었습니다: 업무일지_${formattedDate}.xls`);
                        },
                        error: function(xhr, status, error) {
                            // 로딩 제거
                            document.body.removeChild(loadingDiv);
                            alert('서버 요청 중 오류가 발생했습니다: ' + error);
                        }
                    });
                }

                function fetchDataForCode() {
                    fetch('/wp-content/plugins/jofEdi/ediAgis_srv.php?category=comSearch')
                        .then(response => response.json())
                        .then(data => {
                            // console.log('코드조회 데이터:', data);
                            const codeResultsContainer = document.getElementById('codeResultsContainer');
                            codeResultsContainer.style.position = 'relative'; // 컨테이너를 relative로 설정
                            codeResultsContainer.style.overflow = 'visible'; // 스크롤 가능하게 설정을 visible로 변경

                            let output = '';
                            output += '<div style="overflow: auto; max-height: 800px; position: relative;">'; // 컨테이너에 max-height와 position 추가
                            output += '<table border="1" style="font-size: 12px; width: 100%; border-collapse: collapse;">';

                            output += '<thead style="position: sticky; top: 0; z-index: 1; background-color: black; color: white;">'; // z-index 추가
                            output += '<tr>';
                            Object.keys(data.response.body.items[0]).forEach(key => {
                                output += `<th style="border: 1px solid white; padding: 8px;">${key}</th>`; // padding 추가
                            });
                            output += `<th style="border: 1px solid white; padding: 8px;">수정</th>`;
                            output += `<th style="border: 1px solid white; padding: 8px;">삭제</th>`;
                            output += `<th style="border: 1px solid white; padding: 8px;">추가</th>`;
                            output += '</tr></thead><tbody>';

                            window.items = data.response.body.items;

                            let rowColor = true;

                            items.forEach((item, index) => {
                                let color = rowColor ? 'white' : '#f2f2f2';
                                output += `<tr id="row-${index}" style="background-color: ${color}" onclick="selectRow(${index})">`;

                                Object.keys(item).forEach(key => {
                                    output += `<td style="text-align: left;">${item[key]}</td>`;
                                });

                                output += `<td><a href="#" onclick="editRow(${index}); return false;">수정</a></td>`;
                                output += `<td><a href="#" onclick="deleteRow(${index}); return false;">삭제</a></td>`;
                                output += `<td><a href="#" onclick="addRowBelow(${index}); return false;">추가</a></td>`;
                                output += '</tr>';
                                rowColor = !rowColor;
                            });

                            output += '</tbody></table>';
                            codeResultsContainer.innerHTML = output;

                        })
                        .catch(error => {
                            console.error('코드조회 중 오류 발생:', error);
                        });
                }

                // 행 선택 함수 추가
                window.selectRow = function(index) {
                    document.querySelectorAll('#codeResultsContainer table tbody tr').forEach(row => {
                        row.classList.remove('selected');
                    });
                    document.getElementById(`row-${index}`).classList.add('selected');
                };

                window.addRowBelow = function(index) {
                    const table = document.getElementById('codeResultsContainer').querySelector('table');
                    const newRow = table.insertRow(index + 2);

                    const item = items[index];
                    let output = `<tr style="background-color: white">`;

                    Object.keys(item).forEach(key => {
                        if (key === '순번') {
                            output += `<td></td>`;
                        } else {
                            const cell = document.getElementById(`row-${index}`).querySelectorAll('td')[Object.keys(item).indexOf(key)];
                            const cellWidth = cell.offsetWidth;
                            const cellStyle = window.getComputedStyle(cell);
                            const padding = parseInt(cellStyle.paddingLeft) + parseInt(cellStyle.paddingRight);
                            const adjustedWidth = cellWidth - padding;
                            output += `<td><input type='text' name='${key}' value='${item[key]}' style='width: ${adjustedWidth}px; font-size: 12px; margin: 0; padding: 2px;'></td>`;
                        }
                    });

                    // 저장 및 취소 링크를 추가합니다.
                    output += `<td><a href="#" onclick='saveNewRow(this); return false;'>저장</a></td>`;
                    output += `<td><a href="#" onclick='cancelRow(this); return false;'>취소</a></td>`;
                    output += `<td></td>`; // 이 부분이 누락되어 있었습니다.
                    output += `</tr>`;

                    newRow.innerHTML = output;
                }

                window.cancelRow = function(element) {
                    const row = element.closest('tr');
                    row.remove();
                }

                window.editRow = function(index) {
                    console.log('수정 모드:', index);
                    const tableRow = document.getElementById(`row-${index}`);
                    const cells = tableRow.querySelectorAll('td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))');

                    cells.forEach((cell, idx) => {
                        const key = Object.keys(items[index])[idx];
                        const text = cell.innerText;
                        const cellWidth = cell.offsetWidth;
                        if (key !== '순번') {
                            cell.innerHTML = `<input type='text' name='${key}' value='${text}' style='width: ${cellWidth}px; font-size: 12px;margin: 0; padding: 2px;'>`;
                        }
                    });

                    const editButton = tableRow.querySelector('td:nth-last-child(3) a');
                    editButton.innerText = '저장';
                    editButton.onclick = function() {
                        saveRow(index);
                    };

                    const cancelButton = tableRow.querySelector('td:nth-last-child(2) a');
                    cancelButton.innerText = '취소';
                    cancelButton.onclick = function() {
                        cancelEditRow(index);
                    };

                    const addButton = tableRow.querySelector('td:last-child a');
                    addButton.style.display = 'none'; // 수정 중에는 추가 버튼을 숨깁니다.
                }


                window.cancelEditRow = function(index) {
                    const tableRow = document.getElementById(`row-${index}`);
                    const cells = tableRow.querySelectorAll('td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))');

                    cells.forEach((cell, idx) => {
                        const key = Object.keys(items[index])[idx];
                        cell.innerHTML = items[index][key];
                    });

                    const editButton = tableRow.querySelector('td:nth-last-child(3) a');
                    editButton.innerText = '수정';
                    editButton.onclick = function() {
                        editRow(index);
                    };

                    const deleteButton = tableRow.querySelector('td:nth-last-child(2) a');
                    deleteButton.innerText = '삭제';
                    deleteButton.onclick = function() {
                        deleteRow(index);
                    };

                    const addButton = tableRow.querySelector('td:last-child a');
                    addButton.style.display = 'inline'; // 취소 후 추가 버튼을 다시 표시합니다.
                }

                window.saveRow = function(index) {
                    const tableRow = document.querySelector(`#row-${index}`);
                    if (!tableRow) {
                        alert("ln:426::오류가 발생했습니다: 해당 행을 찾을 수 없습니다.");
                        return;
                    }

                    const inputs = tableRow.querySelectorAll('input');
                    const firstCellText = tableRow.querySelector('td:first-child').innerText;
                    const data = [firstCellText, ...Array.from(inputs).map(input => input.value)];

                    fetch('/wp-content/plugins/jofEdi/ediAgis_srv.php?category=update&user_id=' + encodeURIComponent(user_id), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(data)
                        })
                        .then(response => response.json())
                        .then(json => {
                            if (json.result === "success") {
                                alert("데이터가 성공적으로 저장되었습니다.");
                            } else {
                                alert("저장 중 오류가 발생했습니다: " + json.message);
                            }
                        })
                        .then(json => console.log('서버 응답:', json));

                    inputs.forEach((input, idx) => {
                        const cell = input.parentElement;
                        cell.innerText = input.value;
                    });

                    const saveButton = tableRow.querySelector('td:nth-last-child(3) a');
                    saveButton.innerText = '수정';
                    saveButton.onclick = function() {
                        editRow(index);
                    };

                    const cancelButton = tableRow.querySelector('td:nth-last-child(2) a');
                    cancelButton.innerText = '삭제';
                    cancelButton.onclick = function() {
                        deleteRow(index);
                    };

                    const addButton = tableRow.querySelector('td:last-child a');
                    addButton.style.display = 'inline'; // 저장 후 추가 버튼을 다시 표시합니다.
                }

                window.addEventListener('error', function(event) {
                    if (event.message.includes("Cannot read properties of null (reading 'style')")) {
                        console.error("오류가 발생한 줄 번호: " + event.lineno);
                        console.error("오류 객체: ", event.error);
                    }
                });

                window.saveNewRow = function(element) {
                    const row = element.closest('tr');
                    const inputs = row.querySelectorAll('input');
                    const data = [];

                    data.push(inputs[0].value); // 그룹코드
                    data.push(inputs[1].value); // 그룹코드명
                    data.push(inputs[2].value); // 코드
                    data.push(inputs[3].value); // 코드 설명
                    data.push(inputs[4].value); // 단위
                    data.push(inputs[5].value); // 세율
                    data.push(inputs[6].value); // 원산지
                    data.push(inputs[7].value); // 순서
                    data.push(inputs[8].value); // 사용여부
                    data.push(inputs[9].value); // 유통기한
                    data.push(inputs[10].value); // 상품/납품처코드
                    data.push(inputs[11].value); // 보관방법

                    // 여기서 fetch 요청을 보내고 처리합니다.
                    console.log(data); // 데이터 확인을 위한 로그

                    fetch('/wp-content/plugins/jofEdi/ediAgis_srv.php?category=insert&user_id=' + encodeURIComponent(user_id), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                data: data
                            })
                        })
                        .then(response => response.json())
                        .then(json => {
                            if (json.result === "success") {
                                alert("데이터가 성공적으로 저장되었습니다.");
                                fetchDataForCode(); // 저장 후 주문조회를 실행합니다.
                            } else {
                                alert("저장 중 오류가 발생했습니다: " + json.message);
                            }
                        })
                        .then(json => console.log('서버 응답:', json));

                    inputs.forEach((input, idx) => {
                        const cell = input.parentElement;
                        cell.innerText = input.value;
                    });


                    const saveButton = row.querySelector('td:nth-last-child(3) a');
                    saveButton.innerText = '수정';
                    saveButton.onclick = function() {
                        editRow(rowId);
                    };

                    const cancelButton = row.querySelector('td:nth-last-child(2) a');
                    cancelButton.innerText = '삭제';
                    cancelButton.onclick = function() {
                        deleteRow(rowId);
                    };

                    const addButton = row.querySelector('td:last-child a');
                    // addButton.style.display = 'inline'; // 저장 후 추가 버튼을 다시 표시합니다.
                }

                window.deleteRow = function(index) {
                    if (confirm("해당 코드를 삭제하시겠습니까? 삭제 후에는 복구가 불가능합니다.")) {
                        const tableRow = document.getElementById(`row-${index}`);
                        const firstColumn = tableRow.querySelector('td:first-child');
                        const serialNo = firstColumn.innerText;

                        fetch(`/wp-content/plugins/jofEdi/ediAgis_srv.php?category=delete&serial_no=${encodeURIComponent(serialNo)}`)
                            .then(response => response.json())
                            .then(json => {
                                if (json.result === "success") {
                                    tableRow.remove();
                                    alert("삭제가 성공적으로 완료되었습니다.");
                                } else {
                                    alert("삭제 중 오류가 발생했습니다: " + json.message);
                                }
                            })
                            .catch(error => {
                                console.error('오류 발생:', error);
                                alert("서버와의 통신 중 오류가 발생했습니다.");
                            });
                    }
                }

                function addCodeManagement() {
                    document.getElementById('addCodeForm').style.display = 'block';
                }

                function cancelCodeForm() {
                    document.getElementById('addCodeForm').style.display = 'none';
                }


                function dejonExcel() {
                    const supplyDate = document.getElementById('arrivalDate').value;

                    const formattedDate = new Date(new Date().getTime() + (9 * 60 * 60 * 1000)).toISOString().replace(/[-:T]/g, '').slice(0, 14);
                    const filename = `DEJON-${formattedDate}.xls`;
                    console.log("filename: " + filename);

                    // 서버의 PHP 스크립트를 실행하여 엑셀 파일을 생성하고 다운로드합니다. 
                    const serverUrl = `/wp-content/plugins/jofEdi/dejonExcel_srv.php?category=dejonExcel&date=${encodeURIComponent(supplyDate)}`;
                    console.log('서버 파라미터 포함한 url:', serverUrl);

                    fetch(serverUrl)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('네트워크 응답이 올바르지 않습니다.');
                            }
                            return response.blob().then(blob => ({
                                blob,
                                response
                            }));
                        })
                        .then(({
                            blob,
                            response
                        }) => {
                            // Blob을 사용하여 다운로드 링크를 생성하고 자동으로 클릭하여 파일을 다운로드합니다.
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.style.display = 'none';
                            a.href = url;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                        })
                        .catch(error => {
                            console.error('다운로드 중 오류가 발생했습니다:', error);
                            alert('다운로드 중 오류가 발생했습니다.');
                        });
                }

                // 양식 버튼 클릭 이벤트 제거 (파일 업로드 기능과 충돌)
                // document.getElementById('excel-form').addEventListener('click', function() {
                //     window.location.href = '/wp-content/uploads/daily_order.xlsx';
                // });
            </script>

    </body>

    </html>

<?php
    return ob_get_clean();
}
add_shortcode('edi_agis', 'ediAgis');
