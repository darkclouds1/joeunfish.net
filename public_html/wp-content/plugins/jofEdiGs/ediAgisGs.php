<?php

/**
 * Plugin Name: edi_agisGS
 * Plugin URI: https://joeunfish.net
 * Description: AGIS 주문관리
 * Version: 0.1.0
 * Author: 정순장
 * Author URI: https://uloca.net
 * License: GPL2
 */

// Your plugin code goes here
function ediAgisGs()
{
    // 사용자 데이터 전달
    wp_localize_script('ediAgisGs', 'myScriptData', array(
        'userId' => get_current_user_id(),
        'userName' => wp_get_current_user()->user_login,
        'userRoles' => wp_get_current_user()->roles
    ));

?>
    <!DOCTYPE html>
    <html lang="ko">

    <head>
        <meta charset="UTF-8">
        <title>주문 정보</title>
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/jofEdi/joeunfishAgis.css" />

        <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
        <script rel="preload" src="/wp-content/plugins/jofEdiGs/ediAgisGs.js"></script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>

    </head>

    <body>

        <?php   
            // 공통 네비게이션 메뉴 포함
            require_once(WP_PLUGIN_DIR . '/jofMain/jofNavigation.php');
            renderJofNavigation();
        ?>

        <div id="contents">

            <div class="form-menu" style="display: grid; grid-template-columns: auto 1fr; align-items: center;">
                <div id="entry-title">&nbsp;&nbsp;(GS)주문관리</div>
                <div style="text-align: center;">
                    <span style="color: white; cursor: pointer;" onclick="showOrders(); return false;">(주)지에스리테일</span> |
                    <span style="color: white; cursor: pointer;" onclick="showOrdersGSCT04(); fetchOrdersGSCT04(); return false;">GS25물류센터</span> |
                    <span style="color: white; cursor: pointer;" onclick="showCodeManagement(); return false;" class="desktop-only">코드조회</span>
                </div>
            </div>

            <div id="orderView" style="display: none;">
                <div class="search-row" style="display: flex; justify-content: flex-start; align-items: center; position: relative; gap: 5px; flex-wrap: wrap;">
                    <label for="arrivalDate">입고일자:</label>
                    <input type="date" id="arrivalDate" name="arrivalDate" onchange="fetchOrders()" onkeypress="if(event.keyCode==13) fetchOrders();">
                    <button onclick="fetchOrders()">조회</button>
                    <button id="excelDownBtn" onclick="downloadExcel()" class="desktop-only" style="height: 40px;">엑셀다운</button>
                    <div style="min-width: 95px; flex-shrink: 0;"></div>
                </div>
                <div id="orderList" style="margin-bottom: 15px; font-weight: bold; font-size: 20px;">주문목록</div>
                <script>
                    // 모바일에서는 안보이게 하는 스크립트
                    document.addEventListener('DOMContentLoaded', function() {
                        if (window.innerWidth <= 768) { // 모바일 화면 크기 기준 
                            var desktopButtons = document.querySelectorAll('.desktop-only');
                            desktopButtons.forEach(function(button) {
                                button.style.display = 'none';
                            });
                        }
                    });
                </script>

                <!-- 주문 조회 내용이 여기에 표시됩니다 -->
                <div id="resultsContainer" class="type10"></div>
            </div>

            <div id="orderViewGSCT04" style="display: none;">
                <label for="arrivalDateGSCT04" style="margin-right: 10px;">입고일자:</label>
                <input type="date" id="arrivalDateGSCT04" name="arrivalDateGSCT04" style="margin-bottom: 10px;" onchange="fetchOrdersGSCT04()" onkeypress="if(event.keyCode==13) fetchOrdersGSCT04();">
                <button onclick="fetchOrdersGSCT04()" style="margin-bottom: 5px;">조회</button>
                <button onclick="downloadExcelGSCT04()" style="display: inline-block;" class="desktop-only">엑셀다운</button>
                <!-- <button onclick="dejonExcelGSCT04()" style="display: inline-block;" class="desktop-only">DEJON-엑셀다운</button>
                <button onclick="" style="display: inline-block;" class="desktop-only">INVOICE출력(개발중)</button> -->
                <!-- 주문 조회 내용이 여기에 표시됩니다 -->
                <div id="orderListGSCT04" style="margin-bottom: 15px; font-weight: bold; font-size: 20px;">GSCT04 주문목록</div>
                <div id="resultsContainerGSCT04" class="type10"></div>
            </div>

            <div id="codeView" style="display: none;">
                <!-- 코드 관리 내용이 여기에 표시됩니다 -->
                <div id="codeResultsContainer" style="overflow: auto; height: 400px;"></div>

            </div>

            <div id='loading' style='display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;'>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                    <img src="/img/loading/0.gif" alt="로딩중" loading="lazy" decoding="async">
                </div>
            </div>

            <script>
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
                        if (excelDownBtn) excelDownBtn.style.display = 'none';
                    } else {
                        platForm = 'computer';
                    }
                });

                document.addEventListener("DOMContentLoaded", function() {
                    showOrders();
                });

                window.onload = function() {
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    const formattedTomorrow =
                        tomorrow.getFullYear() + "-" + ("0" + (tomorrow.getMonth() + 1)).slice(-2) + "-" + ("0" + tomorrow.getDate()).slice(-2);
                    document.getElementById("arrivalDate").value = formattedTomorrow;
                    document.getElementById("arrivalDateGSCT04").value = formattedTomorrow;
                    if (document.getElementById("orderList") !== null) {
                        document.getElementById("orderList").textContent = `주문목록 (${formattedTomorrow})`;
                    }
                    if (document.getElementById("orderListGSCT04") !== null) {
                        document.getElementById("orderListGSCT04").textContent = `GSCT04 주문목록 (${formattedTomorrow})`;
                    }
                    move();
                    setTimeout(function() {
                        fetchOrders();
                    }, 500);
                };

                document.addEventListener("DOMContentLoaded", function() {
                    const arrivalDateInput = document.getElementById("arrivalDate");
                    arrivalDateInput.addEventListener("keypress", function(event) {
                        if (event.key === "Enter") {
                            event.preventDefault();
                            move();
                        }
                    });
                });

            </script>
    </body>

    </html>

<?php
    return ob_get_clean();
}
add_shortcode('edi_agisGs', 'ediAgisGs');
