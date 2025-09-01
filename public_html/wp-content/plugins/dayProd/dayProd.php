<?php
/*
 Plugin Name: dayProd : 일일생산관리
 Plugin URI: https://joeunfish.online
 Description: 일일생산 관리
 Version: 0.1
 Author: Monolith
 Author URI: https://joeunfish.net
 License: GPL2
 */

function dayProdShortCode()
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
        <title>일일생산관리</title>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script src="/wp-content/plugins/dayProd/dayProdClass.js?ver=<?php echo filemtime(get_stylesheet_directory() . '/wp-content/plugins/dayProd/dayProdClass.js'); ?>"></script>
        <script src="/wp-content/plugins/dayProd/dayProd.js"></script>
        <script src="/wp-content/plugins/dayProd/dayProd_TMst.js"></script>
        <script rel="preload" src="/jof/js/common.js"></script>

        <!-- css -->
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/dayProd/dayProd.css?ver=<?php echo filemtime(get_stylesheet_directory() . '/wp-content/plugins/dayProd/dayProd.css'); ?>" />


    </head>

    <body>

        <div id="contents">

            <div class="form-container">

                <?php if ($platForm == 'computer') { ?>
                    <div class="form-menu">
                        <div id="title" style="float: left;">
                            &nbsp;&nbsp; 생산일지::
                        </div>
                        <span style="text-decoration: underline; color: white; cursor: pointer;" onclick="showDayProd(); return false;">일일생산</span> |
                        <?php if (current_user_can('editor') || current_user_can('administrator')) { ?>
                            <span style="text-decoration: underline; color: white; cursor: pointer;" onclick="showTMst(); return false;">제품마스터</span> |
                            <span style="text-decoration: underline; color: white; cursor: pointer;" onclick="showTMat_Mst(); return false;">자재마스터</span>
                        <?php } ?>
                    </div>
                <?php } ?>

                <!-- 생산일지 폼 -->
                <div id="dayProdView" style="display: block; clear: both; align-items: center;">
                    <div class="button-container">
                        <?php if ($platForm == 'computer'): ?>
                            <label for="createDate" style="width: 70px; text-align: center;">생산일:</label>
                        <?php endif; ?>
                        <input type="date" id="createDate" style="width: 150px; height: 35px; font-size: 16px; margin-right: 10px;" onchange="dateChanged(this.value)">
                        <button id="searchBtn" class="prod-button" style="width: 150px; height: 40px; font-size: 16px; margin-right: 10px;">조회</button>

                        <!-- 버튼 표시 제어 편집자 or 관리자 권한 -->
                        <?php if ($userCan['edit'] && $platForm == 'computer'): ?>
                            <button id="finBtn" class="prod-button" style="width: 150px; height: 40px; font-size: 16px; margin-right: 10px;">생산확정</button>
                            <button id="finCancelBtn" class="prod-button" style="width: 150px; height: 40px; font-size: 16px; margin-right: 10px;">확정취소</button>
                        <?php endif; ?>

                    </div>

                    <!-- 알림 메시지 -->
                    <div class="notification-container" id="notification-container">
                        <p>
                            <font color="white"></font>
                        </p>
                    </div>

                    <!-- 데이터 테이블 -->
                    <div class="table-container">
                        <div id='tables' class="type10"></div>
                    </div>
                </div>

                <div id="tMstView" style="display: none;clear: both; align-items: center;">
                    <!-- 제품마스터 관리 내용이 여기에 표시됩니다 -->
                    <div id="tMstView" style="overflow: auto; height: 400px;"></div>
                </div>

                <div id="tMatMstView" style="display: none;clear: both; align-items: center;">
                    <!-- 재료마스터 관리 내용이 여기에 표시됩니다 -->
                    <div id="tMatMstView" style="overflow: auto; height: 400px;"></div>
                </div>

                <!-- 랜덤 loading -->
                <div id='loading' style='display: none;' align="center">
                    <div id="code1"><img src='/img/loading/0.gif' width='400px'></div>
                    <!-- <div id="code1"><img src='/img/loading/1.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/2.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/3.gif' width='300px'></div>
                    <div id="code1"><img src='/img/loading/4.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/5.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/6.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/7.gif' width='400px'></div>
                    <div id="code1"><img src='/img/loading/8.gif' width='400px'></div> -->
                </div>
            </div>
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    </body>

    <script>
        // 전역 변수로 선언
        let dayProdClass;
        var platForm = 'computer';
        var dayProd_srv = '/wp-content/plugins/dayProd/dayProd_srv.php';

        const userCan = {
            edit: <?php echo json_encode($userCan['edit']); ?>
        };

        // const today = (() => {
        //     const date = new Date();
        //     return date.toISOString().split('T')[0]; // YYYY-MM-DD 형식으로 변환
        // })();

        if (window.matchMedia("only screen and (max-width: 760px)").matches) {
            // 화면의 너비가 760px 이하인 경우 모바일로 간주
            platForm = 'mobile';
        } else {
            // 그렇지 않은 경우 데스크탑으로 간주
            platForm = 'computer';
        }
        console.log('platForm=' + platForm);

        jQuery(document).ready(function($) {
            // PHP에서 전달받은 사용자 ID 사용
            user_no = myScriptData.userId; // wp에서 관리하는 ID
            user_id = myScriptData.userName; // 사용자ID(wp에서는 Name이라고 함)
            console.log(user_id);
            
            // 초기 날짜 설정
            const today = new Date();
            if (platForm === 'mobile') {
                today.setDate(today.getDate() - 1); // 모바일인 경우 하루 전날로 설정
            }
            document.getElementById('createDate').value = today.toISOString().split('T')[0];

            // 검색 버튼 이벤트
            $('#searchBtn').on('click', function(e) {
                e.preventDefault();
                dayProd.loadData();
            });

            // 생산확정 버튼 이벤트
            $('#finBtn').on('click', function(e) {
                e.preventDefault();
                dayProd.finProd();
            });

            // 확정취소 버튼 이벤트
            $('#finCancelBtn').on('click', function(e) {
                e.preventDefault();
                dayProd.finCancel();
            });

            // 삭제 버튼 이벤트
            $('#deleteBtn').on('click', function(e) {
                e.preventDefault();
                dayProd.deleteData();

            });
            // 초기 데이터 로드
            dayProd.loadData();
        });

        // 날짜 변경 이벤트 - 조회
        function dateChanged(date) {
            console.log('dateChanged=' + date);
            if (dayProd) {
                dayProd.loadData();
            }
        }

        // show notification
        const notification = document.getElementById('notification-container');
        const showNotification = () => {
            notification.classList.add('show')
            setTimeout(() => {
                notification.classList.remove('show')
            }, 500)
        }
    </script>

    </html>

<?php }
add_shortcode('dayProd', 'dayProdShortCode');
?>