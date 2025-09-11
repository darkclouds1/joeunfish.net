<?php
/*
 Plugin Name: jofMain-jofMain : 조은수산 입|출고 관리
 Plugin URI: https://joeunfish.net
 Description: 조은수산 입|출고 관리
 Version: 1.2
 Author: Monolith
 Author URI: https://uloca.net
 License: GPL2
 */

function jofMainShortCode()
{
    // date_default_timezone_set('Asia/Seoul');
    // require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
    // $dbConn = new dbconnClassFish;
    // $platForm = $dbConn->MobileCheck();

    $userCan = array(
        // 'edit' => current_user_can('editor') || current_user_can('administrator')
        'edit' => is_user_logged_in()       // 모든 로그인한 사용자 접근 가능
    );

?>
    <!DOCTYPE html>
    <html lang="ko">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>mainMenu</title>
        <link rel="stylesheet" type="text/css" href="/jof/css/jofMain.css?v=<?php echo time(); ?>" />
        <script rel="preload" src="/jof/js/joeunfish.js"></script>
        <script rel="preload" src="/wp-content/plugins/jofMain/tasks.js"></script>
    </head>

    <body>
        <form action="" name="myForm" id="myForm" method="get" autocomplete="on">
            <div class="button-container">
                <!-- <div class="button-container"> -->
                <button class="purchase">통관|매입</button>
                <button class="barcodein">1층입고</button>
                <button class="barcodeinv">1층출고(연어)</button>
                <button class="barcodeout">3층생산</button>
                <button class="agis co-buttons">(CO)주문관리</button>
                <button class="barcodeinv2 co-buttons">(CO)상품출고</button>
                <button class="agisgs gs-buttons">(GS)주문관리</button>
                <button class="barcodeinv2gs gs-buttons">(GS)상품출고</button>
                <!-- <button class="gsInvt">완출생산관리</button> -->
                <button class="octopusInvt">냉동자재(재고)</button>
                <button class="ascInvt" style="background-color: #33F;">ASC수불</button>
                <button class="ascInvt3" style="background-color: #33F;">ASC더조은</button>
                <button class="barcodeoutmap" style="background-color: #ffffff; color: #000000;">생산(MAP)</button>
                <button class="dayProd custom-button" style="background-color: #4CAF50;">일일생산</button>
                <button class="unitPrice custom-button" style="background-color: #4CAF50;">단가확인</button>
                <button class="orderSummary-month">(CO)납기통계</button>
                <button class="orderSummary-month-gs">(GS)납기통계</button>
                <button class="orderSummary">주문통계</button>
                <button class="analysis">주문예측</button>
                <button class="subul subul-buttons">수불관리</button>
                <button class="subul-trace subul-buttons">추적(연어필렛)</button>
                <button id="openTaskBtn" class="tasks" style="background-color: #F3F;">공지사항</button>
            </div> 

            <!-- 공지사항 -->
            <div id="taskPanel" class="task-panel"></br>
                <div style="text-align: left; margin: 10px 0 10px 0; font-size: 20px;">★ 공지사항 ★
                    <button id="closeTaskBtn" class="close-btn" style="float: right; font-size: 13px;">닫기</button>
                </div>
                <hr style="border: 1px solid #ddd; margin: 10px 0;">
                <div class="task-bar">
                    <span class="icon" style="align-items: left;">✔️+</span>
                    <span class="text" onclick="addNewTask()">할 일 추가</span>
                    <span class="menu" style="align-items: right;">⋮</span>
                </div>
                <div class="task-content">
                    <br>
                    <div id="taskList"></div>
                    <!-- <input type="checkbox" id="myCheckbox" onclick="handleCheck(this)"> -->
                </div>
            </div>

        </form>
        <script>
            var platform = 'computer';

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
                console.log(user_id + ', ' + platForm);

                // 공지사항 갯수 업데이트
                updateTaskCount();
            });

            // 공지사항 갯수 업데이트
            function updateTaskCount() {
                getTaskCount().then(taskCount => {
                    const openTaskBtn = document.getElementById('openTaskBtn');
                    openTaskBtn.textContent = `공지사항(${taskCount})`;
                }).catch(error => {
                    console.error('Error updating task count:', error);
                });
            }

            function getTaskCount() {
                return new Promise((resolve, reject) => {
                    const url = '/wp-content/plugins/jofMain/tasks_srv.php?category=taskCount';
                    jQuery.ajax({
                        url: url,
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            console.log('Server response:', response); // 서버 응답 확인
                            if (response.success) {
                                resolve(response.data);
                            } else {
                                reject('Error fetching task count: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            reject('AJAX error: ' + status + ', ' + error);
                        }
                    });
                });
            }

            // 바코드입고
            function viewBarcodeIn(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/barcodein/";
            }
            document.querySelector('.barcodein').addEventListener('click', viewBarcodeIn);

            // 바코드생산
            function viewBarcodeOut(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/barcodeout/";
            }
            document.querySelector('.barcodeout').addEventListener('click', viewBarcodeOut);
            
            // 바코드생산(MAP)
            function viewBarcodeOutMap(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/barcodeoutmap/";
            }
            document.querySelector('.barcodeoutmap').addEventListener('click', viewBarcodeOutMap);

            // 바코드출고
            function viewBarcodeInv(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/barcodeinv/";
            }
            document.querySelector('.barcodeinv').addEventListener('click', viewBarcodeInv);

            // 바코드출고2 :: 연어 외 제품
            function viewBarcodeInv2(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/barcodeinv2/";
            }
            document.querySelector('.barcodeinv2').addEventListener('click', viewBarcodeInv2);

            // 바코드출고2GS :: GS 제품
            function viewBarcodeInv2GS(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/barcodeinv2gs/";
            }
            document.querySelector('.barcodeinv2gs').addEventListener('click', viewBarcodeInv2GS);

            // 매출분석
            function viewAnalysis(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/predicinv/";
            }
            document.querySelector('.analysis').addEventListener('click', viewAnalysis);

            // AGIS(COST)
            function viewAgis(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/ediagis/";
            }
            document.querySelector('.agis').addEventListener('click', viewAgis);

            // AGIS(GS)
            function viewAgisGS(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/ediagisgs/";
            }
            document.querySelector('.agisgs').addEventListener('click', viewAgisGS);

            // 완출생산관리(GS)
            // function viewGsInvt(event) {
            //     event.preventDefault(); // Prevent form submission
            //     window.location.href = "/gsinvt/";
            // }
            // document.querySelector('.gsInvt').addEventListener('click', viewGsInvt);

            // 문어재고관리(냉동창고) - 2025-08-26 추가
            function viewOctopusInvt(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/octopusInvt/";
            }
            document.querySelector('.octopusInvt').addEventListener('click', viewOctopusInvt);

            // 업체매입
            function viewPurchase(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/purchase/";
            }
            document.querySelector('.purchase').addEventListener('click', viewPurchase);

            // ASC수불
            function viewAscInvt(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/ascInvt/";
            }
            document.querySelector('.ascInvt').addEventListener('click', viewAscInvt);

            // ASC 더조은
            function viewAscInvt3(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/ascinvt-3/";
            }
            document.querySelector('.ascInvt3').addEventListener('click', viewAscInvt3);

            // ASC 더조은
            function viewDayProd(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/dayprod/";
            }
            document.querySelector('.dayProd').addEventListener('click', viewDayProd);

            // 단가확인
            function viewUnitPrice(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/unitPrice/";
            }
            document.querySelector('.unitPrice').addEventListener('click', viewUnitPrice);

            // 공지사항
            const openTaskBtn = document.getElementById('openTaskBtn');
            const closeTaskBtn = document.getElementById('closeTaskBtn');
            const taskPanel = document.getElementById('taskPanel');

            // Event listener to open the panel
            openTaskBtn.addEventListener('click', () => {
                event.preventDefault(); // 페이지 리로드 방지
                
                // 이미 열려있으면 닫기
                if (taskPanel.style.display === 'block') {
                    taskPanel.style.display = 'none';
                    return;
                }
                
                taskPanel.classList.add('active');
                taskPanel.style.display = 'block'; // taskPanel 보이기
                loadTasks();
            });

            // Event listener to close the panel
            closeTaskBtn.addEventListener('click', () => {
                // taskPanel.classList.remove('active');
                taskPanel.style.display = 'none'; // taskPanel 숨기기
            });

            // 수불관리
            function viewSubul(event) {
                event.preventDefault(); 
                window.location.href = "/subul/";
            }
            document.querySelector('.subul').addEventListener('click', viewSubul);

            // 추적(연어필렛)
            function viewSubulTrace(event) {
                event.preventDefault(); 
                window.location.href = "/subul-trace/";
            }
            document.querySelector('.subul-trace').addEventListener('click', viewSubulTrace);

            // 주문통계
            function viewOrderSummary(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/orderSummary/";
            }
            document.querySelector('.orderSummary').addEventListener('click', viewOrderSummary);

            // 납기통계(CO)
            function viewOrderSummaryMonth(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/orderSummary-month/";
            }
            document.querySelector('.orderSummary-month').addEventListener('click', viewOrderSummaryMonth);

            // 납기통계(GS)
            function viewOrderSummaryMonthGS(event) {
                event.preventDefault(); // Prevent form submission
                window.location.href = "/orderSummary-month-gs/";
            }
            document.querySelector('.orderSummary-month-gs').addEventListener('click', viewOrderSummaryMonthGS);
        </script>


    </body>

    </html>
<?php }
add_shortcode('jofMain', 'jofMainShortCode'); ?>