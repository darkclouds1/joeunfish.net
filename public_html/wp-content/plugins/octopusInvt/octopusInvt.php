<?php
/*
 Plugin Name: octopusInvt : OCTOPUS수불관리
 Plugin URI: https://joeunfish.online
 Description: OCTOPUS수불 관리
 Version: 1.0
 Author: Monolith
 Author URI: https://uloca.net
 License: GPL2
 */

function octopusInvtShortCode()
{
    // WordPress 스크립트 및 스타일 대기열에 추가
    add_action('wp_enqueue_scripts', 'octopusInvt_enqueue_scripts');

    // 기존 PHP 코드 시작
    date_default_timezone_set('Asia/Seoul');
    require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

    $dbConn   = new dbconnClassFish;
    $platForm = $dbConn->MobileCheck();

    // 냉동재고 자재 목록 조회
    try {
        $pdo = $dbConn->pdo_conn();
        $sql = "
            SELECT mat_cd, CONCAT(mat_cd, '|', mat_nm) AS mat_display
            FROM `tdayprodmat_mst`
            WHERE temp_code = 'F'
            GROUP BY mat_cd, mat_nm
            ORDER BY mat_nm;
        ";
        $stmt = $pdo->query($sql);
        $frozen_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to fetch frozen materials: " . $e->getMessage());
        $frozen_materials = []; // 에러 발생 시 빈 배열로 초기화
    }
    $pdo = null;

    // Get file modification times for cache busting
    $tablesort_ver = file_exists($_SERVER['DOCUMENT_ROOT'] . '/include/JavaScript/tableSort.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/include/JavaScript/tableSort.js') : time();
    $common_ver = file_exists($_SERVER['DOCUMENT_ROOT'] . '/jof/js/common.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/jof/js/common.js') : time();
    $invt_class_ver = file_exists(__DIR__ . '/octopusInvtClass.js') ? filemtime(__DIR__ . '/octopusInvtClass.js') : time();
    $frozen_js_ver = file_exists(__DIR__ . '/octopusInvt-frozen.js') ? filemtime(__DIR__ . '/octopusInvt-frozen.js') : time();

?>

    <!DOCTYPE html>
    <html lang="kr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>OCTOPUS 수불관리</title>

        <script src="/wp-content/plugins/octopusInvt/octopusInvtClass.js?v=<?php echo $invt_class_ver; ?>"></script>
        <script src="/wp-content/plugins/octopusInvt/octopusInvt-frozen.js?v=<?php echo $frozen_js_ver; ?>"></script>
        <script>
            // 인스턴스 생성을 최우선으로 실행
            var codeOutClass = new octopusInvtClass();
            var frozenInvtClass; // frozenInvtClass는 DOM 로드 후 초기화
        </script>
        <script rel="preload" src="/jof/js/common.js?v=<?php echo $common_ver; ?>"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js?v=<?php echo $tablesort_ver; ?>" async></script>
        <!-- css -->
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/octopusInvt/octopusInvt.css?v=<?php echo $octopus_css_ver; ?>" />

    </head>

    <body>
        <div id="contents">
            <div class="form-container">
                <div class="form-menu">
                    <div id="entry-title" style="float: left;">&nbsp;&nbsp;냉동재고::</div>
                    <div>
                        <span style="color: white; cursor: pointer;" onclick="showFrozenView()">냉동재고</span> |
                        <span style="color: white; cursor: pointer;" onclick="showOctopusView()">문어재고</span> 
                    </div>
                </div>

                <!-- 냉동재고 -->
                <div id="frozenView" style="display: block; clear: both; align-items: center;">
                    <div class="frozen-input-container search-row" style="height: 50px !important;">
                        <select id="mat-cd-frozen" name="mat-cd-frozen" class="form-field" style="width: 200px !important; height: 40px !important;">
                            <option value="">전체</option>
                            <?php foreach ($frozen_materials as $material): ?>
                                <option value="<?php echo htmlspecialchars($material['mat_cd']); ?>">
                                    <?php echo htmlspecialchars($material['mat_display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="search-btn-frozen">조회</button>
                    </div>
                </div>

                <!-- 문어재고 -->
                <div id="octopusView" style="display: none; clear: both; align-items: center;">
                    <form action="" name="myForm" id="myForm" method="get">
                        <!-- 첫 번째 행 -->
                        <div class="form-row">
                            <select name="category" class="form-field" placeholder="입력" id="category" style="height: 40px;">
                                <option value="1">재고일</option>
                            </select>
                            <input type="date" class="form-field now-date" id="createDate" onchange="dateChanged(this.value)" style="height: 40px;">
                            <!-- 날짜/BL# 조건 조회 버튼 및 BL# 별 총중량 및 건수를 확인하는 버튼  -->
                            <button id="actionBtn" type="button">조회</button>
                            <?php if ($platForm !== 'mobile'): ?>
                                <button id="saveBtn" type="button">저장</button>
                                <button id="deleteBtn" type="button">삭제</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div id="Explain">
                    <div id='totalrec'></div>
                </div>

                <div id='tables' class="type10 table-container" style="text-align: left; border: 0px solid #dddddd; word-break:break-all;"></div>

                <div id='loading' style='display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;'>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                        <img src="/img/loading/0.gif" alt="로딩중">
                    </div>
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
        var platForm; // 전역 변수로 선언만 함
    </script>

    </html>

    <script>
        var newItem = []; // 입력 barcode Item array
        var rtnCnt = 0; // 가져온 Data갯수
        var idx = 0; // tables Row 갯수
        var parm = ""; // kwd
        var totalWeight = 0;
        var inventoryList = []; // 목록 데이터를 저장할 배열

        let table1;
        jQuery(document).ready(function($) {
            if (window.matchMedia("only screen and (max-width: 760px)").matches) {
                // 화면의 너비가 760px 이하인 경우 모바일로 간주
                platForm = 'mobile';
            } else {
                // 그렇지 않은 경우 데스크탑으로 간주
                platForm = 'computer';
            }
            console.log('platForm=' + platForm);
            
            // PHP에서 전달받은 사용자 ID 사용
            user_no = myScriptData.userId; // wp에서 관리하는 ID
            user_id = myScriptData.userName; // 사용자ID(wp에서는 Name이라고 함)
            console.log(user_id);

            // frozenInvtClass 인스턴스 생성
            frozenInvtClass = new FrozenInvtClass();

            // 초기 데이터 조회
            frozenInvtClass.loadData();

            // 냉동재고 자재 선택 시 조회
            document.getElementById('mat-cd-frozen').addEventListener('change', function() {
                frozenInvtClass.loadData();
            });

            // 데스크탑에서만 저장 버튼 클릭 이벤트 추가    
            if (platForm !== 'mobile') {
                document.getElementById('saveBtn').addEventListener('click', function() {
                    var $inputs = $('#specData').find('input');

                if ($inputs.length > 0) {
                    // input 태그가 존재할 경우
                    if (confirm("입력한 데이터를 저장하시겠습니까?")) {
                        saveInventoryList();
                    } else {
                        console.log('저장이 취소되었습니다.');
                    }
                } else {
                    // input 태그가 없을 경우
                    alert('저장할 데이터가 없습니다. 먼저 데이터를 조회해주세요.');
                    }
                });
            }

            // 데스크탑에서만 삭제 버튼 클릭 이벤트 추가    
            if (platForm !== 'mobile') {
                document.getElementById('deleteBtn').addEventListener('click', function() {
                    const increate_dt = document.getElementById('createDate').value;

                let url = '/wp-content/plugins/octopusInvt/octopusInvt_srv.php';
                console.log("ln211::url=" + url + " category=delete&increate_dt=" + increate_dt);
                if (increate_dt) {
                    if (confirm("재고일자 " + increate_dt + "의 데이터를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.")) {
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
                        alert('삭제할 재고일자가 선택되지 않았습니다. 재고일자를 먼저 선택해주세요.');
                    }
                });
            }
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
        
        function handleKeyDown(e) {
            if (e.key === 'Enter') {
                if (document.activeElement) {
                    document.activeElement.blur();
                }
                e.preventDefault();
            }
        }


        // 조회시작
        let category = '';
        let gIncreate_dt = '';

        document.getElementById('actionBtn').addEventListener('click', function() {
            category = document.getElementById('category').value;
            const increate_dt = document.getElementById('createDate').value;
            gIncreate_dt = increate_dt;

            searchAjax_OutKwd(category, increate_dt);
        });

        // 냉동재고 조회 버튼 이벤트
        document.getElementById('search-btn-frozen').addEventListener('click', function() {
            frozenInvtClass.loadData();
        });
        
        // 조회시작
        function searchAjax_OutKwd(
            category,
            increate_dt,
        ) {
            let param = "&increate_dt=" + increate_dt; // + "&user_id=" + user_id;
            // category=1 조회이면 's'를 붙임.(서버에서 확인)
            server = "/wp-content/plugins/octopusInvt/octopusInvt_srv.php?category=" + '1s' + param;

            console.log("[octopusInvt_srv::조회]" + server);
            move(); // 조회 일 때 loding...
            getAjax(server, recvOutData);
        }

        function recvOutData(data) {
            move_stop();

            try {
                let jsData = JSON.parse(data);
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
                alert("조회할 매입 데이터가 없습니다.");
                inventoryList = [];
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
                    "ln344::시스템 장애 입니다. 잠시 후 재시도 하시기 바랍니다." + e.message
                );
                return false;
            }

            // -------------------------------------------------------
            //  메시지 표시
            // -------------------------------------------------------
            const ment = "문어 재고 " + idx + "건 조회하였습니다.\n";
            return true;
        }

        // actionBtn 조회 시작
        function actionBtn_Search() {
            const category = document.getElementById("category").value;
            const increate_dt = document.getElementById("createDate").value;

            const rtn = this.searchAjax_OutKwd(
                category,
                increate_dt,
            );
            return rtn;
        }

        // ----------------------------
        // 타이틀 변경 및 뷰 보여주기
        // ----------------------------
        function showOctopusView() {
            hideAllViews();
            document.getElementById("octopusView").style.display = "block";
            // document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;문어재고::";
            document.querySelector('[onclick="showFrozenView()"]').style.color = 'white';
            document.querySelector('[onclick="showOctopusView()"]').style.color = 'yellow';

            // 데이터 로드
            setTimeout(() => {
                document.getElementById('actionBtn').click();
            }, 100);
        }

        function showFrozenView() {
            hideAllViews();
            document.getElementById("frozenView").style.display = "block";
            // document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;냉동재고::";
            document.querySelector('[onclick="showFrozenView()"]').style.color = 'yellow';
            document.querySelector('[onclick="showOctopusView()"]').style.color = 'white';

            frozenInvtClass.loadData();
        }

        // 모든 view를 숨기는 헬퍼 함수
        function hideAllViews() {
            const views = ["octopusView", "frozenView"];
            views.forEach(viewId => {
                const element = document.getElementById(viewId);
                if (element) {
                    element.style.display = "none";
                }
            });
            // 테이블과 요약 정보도 초기화
            document.getElementById("tables").innerHTML = "";
            document.getElementById("totalrec").innerHTML = "";
        }


    </script>

<?php }

function octopusInvt_enqueue_scripts() {
    // 파일의 최종 수정 시간을 버전으로 사용하여 캐시 문제 해결
    $jof_common_ver = filemtime(plugin_dir_path(__FILE__) . '../../../jof/js/common.js');
    $tablesort_ver = filemtime(plugin_dir_path(__FILE__) . '../../../include/JavaScript/tableSort.js');
    $invt_class_ver = filemtime(plugin_dir_path(__FILE__) . 'octopusInvtClass.js');
    $frozen_js_ver = filemtime(plugin_dir_path(__FILE__) . 'octopusInvt-frozen.js');

    // 의존성을 가지고 스크립트들을 등록합니다. jQuery는 WordPress에 내장되어 있습니다.
    wp_enqueue_script('jof-common', '/jof/js/common.js', array('jquery'), $jof_common_ver, true);
    wp_enqueue_script('tablesort', '/include/JavaScript/tableSort.js', array(), $tablesort_ver, true);
    wp_enqueue_script('octopus-invt-class', plugins_url('octopusInvtClass.js', __FILE__), array('jquery'), $invt_class_ver, true);
    wp_enqueue_script('octopus-invt-frozen', plugins_url('octopusInvt-frozen.js', __FILE__), array('octopus-invt-class'), $frozen_js_ver, true);
}

add_shortcode('octopusInvt', 'octopusInvtShortCode');
