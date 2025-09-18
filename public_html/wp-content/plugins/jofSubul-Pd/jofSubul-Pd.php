<?php
/*
Plugin Name: 수불추적관리
Plugin URI: http://joeunfish.in/
Description: 주문 예측량(torder_predict) 테이블을 포함하여 수불을 추적합니다.
Version: 1.0
Author: Monolith - by 먹구름
Author URI: http://joeunfish.in
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// shortcode 등록
add_shortcode('jof_subul_pd', 'jof_subul_pd_shortcode');

function jof_subul_pd_shortcode() {
    // 파일 수정 시간을 기반으로 버전 관리 (Cache Busting)
    $pd_css_ver = file_exists(__DIR__ . '/subul_pd.css') ? filemtime(__DIR__ . '/subul_pd.css') : time();
    $common_ver = file_exists($_SERVER['DOCUMENT_ROOT'] . '/jof/js/common.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/jof/js/common.js') : time();
    $pd_class_ver = file_exists(__DIR__ . '/subulPdClass.js') ? filemtime(__DIR__ . '/subulPdClass.js') : time();

    ob_start();

    // 기존 PHP 코드 시작
    date_default_timezone_set('Asia/Seoul');
    require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

    $dbConn   = new dbconnClassFish;
    $platForm = $dbConn->MobileCheck();

    // From: 전주 일요일
    // 오늘 날짜를 기준으로 지난 주 일요일을 명확하게 계산합니다.
    $fromDate = new DateTime();
    $dayOfWeek = (int)$fromDate->format('w'); // 0=일, 1=월, ..., 6=토
    
    // 1. 먼저 이번 주 일요일로 날짜를 이동합니다.
    //    (오늘이 일요일이라면 날짜는 변경되지 않습니다)
    if ($dayOfWeek > 0) {
        $fromDate->modify("-{$dayOfWeek} days");
    }
    // 2. 그 다음, 7일을 빼서 정확히 '지난 주 일요일'을 구합니다.
    // $fromDate->modify('-7 days');


    // To: fromDate로부터 10일 뒤
    $toDate = clone $fromDate;
    $toDate->modify('+10 days');

    $defaultFromDate = $fromDate->format('Y-m-d');
    $defaultToDate = $toDate->format('Y-m-d');

    // 여기에 HTML, CSS, JavaScript 코드를 넣습니다.
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>수불추적관리</title>

        <script src="/wp-content/plugins/jofSubul-Pd/subulPdClass.js?v=<?php echo $pd_class_ver; ?>"></script>
        <script>
            // 전역 스코프에서 인스턴스가 없을 경우에만 생성
            if (typeof window.subulPdClass === 'undefined') {
                window.subulPdClass = new subulPdClass();
            }
        </script>
        <script rel="preload" src="/jof/js/common.js?v=<?php echo $common_ver; ?>"></script>
        
        <!-- css -->
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/jofSubul-Pd/subul_pd.css?v=<?php echo $pd_css_ver; ?>" />

    </head>
    <body>
        <div id="contents">
            <span> ■ 조회조건: 시작일(주) 선택 시 자동 10일 후로 설정됩니다. (※실제생산=조은점(000), 제외: 원재료, 완출, GS점) </span>
            <div id="jofSubulPd-container">
                <!-- 조회 조건 -->
                <div class="search-row">
                    <input type="date" id="from_date_pd" value="<?php echo esc_attr($defaultFromDate); ?>">
                    <label for="to_date_pd">~</label>
                    <input type="date" id="to_date_pd" value="<?php echo esc_attr($defaultToDate); ?>" readonly>
                    <button id="searchBtn-pd">조회</button> 
                </div>
                
                <!-- 결과 테이블 -->
                <div id="tables-pd"></div>

                <!-- 주간 네비게이션 -->
                <div id="weekly-nav-pd" style="text-align: center; font-size: 1.2em; user-select: none;">
                    <span id="prev-week-pd" style="cursor: pointer; padding: 10px; color: red;">◀</span>
                    <span id="current-week-pd" style="margin: 0 15px; display: inline-block; width: 100px; color: red;"></span>
                    <span id="next-week-pd" style="cursor: pointer; padding: 10px; color: red;">▶</span>
                </div>
            </div>
        </div>
        
        <!-- jQuery body 맨아래 -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        
        <script type="text/javascript">
            // PHP 변수를 JavaScript로 전달
            var platForm = '<?php echo $platForm; ?>';
            window.subul_pd_srv = "<?php echo esc_js(plugins_url('subul_pd_srv.php', __FILE__)); ?>";

            function initializeSubulPd() {
                if (typeof window.subulPd === 'undefined') {
                    // 클래스 인스턴스 생성 및 초기화
                    // 모든 UI 및 이벤트 처리는 subulPdClass 내에서 이루어집니다.
                    window.subulPd = new subulPdClass();
                    window.subulPd.init();
                }
            }

            // DOM이 준비되었는지 확인하여 스크립트 실행 (안정적인 방법)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeSubulPd);
            } else {
                // DOMContentLoaded 이벤트가 이미 발생한 경우 즉시 실행
                initializeSubulPd();
            }
        </script>
    </body>
    </html>
    <?php

    return ob_get_clean();
}
