<?php
/*
Plugin Name: System Management  
Plugin URI: http://joeunfish.net/sys-mng
Description: 시스템 작업관리, shortcode: [sys_mng]
Version: 1.0
Author: 먹구름
Author URI: http://joeunfish.net
*/

function sysMngShortCode()
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
        <title>시스템 작업관리</title>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/include/JavaScript/tableSort.js"></script>

        <script src="/wp-content/plugins/jofSysMng/sys-mng.js"></script>

        <!-- 엑셀 업로드 -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
        <link rel="stylesheet" type="text/css" href="/wp-content/plugins/jofSysMng/sys-mng.css" />
    </head>

    <body>
        <div class="form-container">
            <div class="container">
                <div class="form-menu">
                    <div id="entry-title" style="float: left;">&nbsp;&nbsp;시스템 작업내역</div>
                </div>

                <!-- 시스템 작업관리 -->
                <div id="sysMngView" style="display: block; clear: both; align-items: center;">
                    <!-- 조회 영역 -->
                    <div class="sys-mng-input-container search-row">
                        <input type="date" id="start-date-sys" name="start-date-sys" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>">
                        <label for="end-date-sys">~</label>
                        <input type="date" id="end-date-sys" name="end-date-sys" value="<?php echo date('Y-m-d'); ?>">
                        <input type="text" id="search-keyword" placeholder="내용 검색" style="width: 200px;">
                        <button id="search-btn-sys">조회</button>
                    </div>

                    <!-- 입력 영역 -->
                    <div class="sys-mng-input-container input-row">
                        <input type="datetime-local" id="increate-dt" placeholder="입력일시" value="<?php echo date('Y-m-d\TH:i'); ?>">
                        <input type="datetime-local" id="request-dt" placeholder="요청일시">
                        <input type="text" id="title" placeholder="제목" maxlength="100" style="width: 300px;" onkeypress="focusNext(event, 'sys-content')">
                        <input type="text" id="sys-content" placeholder="내용" maxlength="500" style="width: 400px;" onkeypress="focusNext(event, 'work-detail')">
                    </div>

                    <div class="sys-mng-input-container input-row">
                        <textarea id="work-detail" placeholder="작업상세" maxlength="5000" style="width: 100%; min-height: 160px;"></textarea>
                    </div>

                    <div class="sys-mng-input-container input-row">
                        <input type="datetime-local" id="start-dt" placeholder="작업 시작일시">
                        <input type="datetime-local" id="end-dt" placeholder="작업 종료일시">
                        <select id="cost-type" style="width: 150px;">
                            <option value="">유/무상 선택</option>
                            <option value="유상">유상</option>
                            <option value="무상">무상</option>
                        </select>
                        <button id="insert-btn-sys">입력</button>
                    </div>

                    <div class="actions">
                        <button id="update-btn-sys" class="action-button edit-button">수정</button>
                        <button id="delete-btn-sys" class="action-button delete-button">삭제</button>
                        <button id="excel-btn-sys" class="action-button excel-button" onclick="sysMngExcel()">엑셀</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 데이터 테이블 -->
        <div class="table-container">
            <div id='tables'></div>
        </div>

    </body>

    <script>
        var platForm = 'computer';
        var sys_mng_srv = '/wp-content/plugins/jofSysMng/sys-mng_srv.php';

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
            console.log('user_id=' + user_id + ', user_no=' + user_no + ', platForm=' + platForm);

            // 초기 데이터 로드
            sysMngClass.loadData();
        });

        function focusNext(event, nextElementId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const nextElement = document.getElementById(nextElementId);
                if (nextElement) {
                    nextElement.focus();
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            // 조회 키보드 이벤트
            const startDateInput_sys = document.getElementById("start-date-sys");
            const endDateInput_sys = document.getElementById("end-date-sys");
            const searchButton_sys = document.getElementById("search-btn-sys");

            startDateInput_sys.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    endDateInput_sys.focus();
                }
            });
            
            endDateInput_sys.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    searchButton_sys.click();
                }
            });
        });

        // 엑셀 다운로드
        function sysMngExcel() {
            const startDate = document.getElementById('start-date-sys').value;
            const endDate = document.getElementById('end-date-sys').value;
            const searchKeyword = document.getElementById('search-keyword').value;

            const formattedDate = new Date(new Date().getTime() + (9 * 60 * 60 * 1000)).toISOString().replace(/[-:T]/g, '').slice(0, 14);
            const filename = `시스템작업관리-${formattedDate}.xls`;

            const serverUrl = `/wp-content/plugins/jofSysMng/sys-mng-excel_srv.php?category=sysMngExcel&startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}&searchKeyword=${encodeURIComponent(searchKeyword)}`;
            
            fetch(serverUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('네트워크 응답이 올바르지 않습니다.');
                    }
                    return response.blob();
                })
                .then(blob => {
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
    </script>

    </html>
<?php
}

add_shortcode('sys_mng', 'sysMngShortCode');
?>
