<?php
/*
Plugin Name: subul_mgt 수불관리
Plugin URI: http://joeunfish.online/subul
Description: 수불관리::B/L# 조회
Version: 1.0
Author: 먹구름
Author URI: http://joeunfish.online
*/

function subulShortCode()
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
          <title>통관|매입</title>

          <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
          <script rel="preload" src="/jof/js/joeunfish.js"></script>
          <script rel="preload" src="/jof/js/common.js"></script>
          <script rel="preload" src="/include/JavaScript/tableSort.js"></script>

          <script src="/wp-content/plugins/jofSubul/subulCoDayClass.js"></script>
          <script src="/wp-content/plugins/jofSubul/subulBlDayClass.js"></script>
          <script src="/wp-content/plugins/jofSubul/subulImpDayClass.js"></script>
          <script src="/wp-content/plugins/jofSubul/subulGsDayClass.js"></script>
          <script src="/wp-content/plugins/jofSubul/purchase-out.js"></script>

          <!-- 엑셀 업로드 -->
          <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
          <link rel="stylesheet" type="text/css" href="/wp-content/plugins/jofSubul/subul.css" />

          <!-- 판매 스타일 -->
          <link rel="stylesheet" type="text/css" href="/wp-content/plugins/jofSubul/purchase.css" />
     </head>

     <body>
          <div class="form-container">
               <div class="container">
                    <div class="form-menu">
                         <div id="entry-title" style="float: left;">&nbsp;&nbsp;수불관리:CO::</div>
                         <div>
                              <span style="color: white; cursor: pointer;" onclick="showSubulCo()">CO</span> |
                              <span style="color: white; cursor: pointer;" onclick="showSubulBl()">BL</span> |
                              <span style="color: white; cursor: pointer;" onclick="showSubulImp()">통관</span> |
                              <span style="color: white; cursor: pointer;" onclick="showSubulGs()">GS</span> |
                              <span style="color: white; cursor: pointer;" onclick="showPurchaseOut()">판매</span>
                         </div>
                    </div>

                    <!-- CO생산 및 납품 내역 조회 -->
                    <div id="subulCoView" style="display: block; clear: both; align-items: center;">
                         <!-- 1번째 줄: 조회 영역 -->
                         <div class="input-container search-row">
                              <input type="date" id="startDate-co" value="<?php echo date('Y-m-d'); ?>">
                              <button id="searchBtn-co">조회</button>
                              <?php if ($platForm === 'computer') { ?>
                                   <button id="printBtn-co">출력</button>
                                   <button id="excel-co">엑셀</button>
                                   <button id="excel-day">일지</button>
                                   <button id="excel-form">양식</button>
                              <?php } ?>
                         </div>
                         <!-- 2번째 줄: 테이블 컨테이너 -->
                         <div id="tables-co" style="display: block; clear: both; align-items: center;">
                         </div>
                    </div>

                    <!-- 생산일자별 BL 판매현황 -->
                    <div id="subulBlView" style="display: none; clear: both; align-items: center;">
                         <!-- 1번째 줄: 조회 영역 -->
                         <div class="input-container search-row">
                              <input type="date" id="startDate-bl" value="<?php echo date('Y-m-d'); ?>">
                              <button id="searchBtn-bl">조회</button>
                              <?php if ($platForm === 'computer') { ?>
                                   <button id="excel-bl">엑셀</button>
                              <?php } ?>
                         </div>
                         <!-- 2번째 줄: 테이블 컨테이너 -->
                         <div id="tables-bl" style="display: block; clear: both; align-items: center;">
                         </div>
                    </div>

                    <!-- 통관일자별 BL 판매현황 -->
                    <div id="subulImpView" style="display: none; clear: both; align-items: center;">
                         <!-- 1번째 줄: 조회 영역 -->
                         <div class="input-container search-row">
                              <input type="date" id="startDate-imp" value="<?php echo date('Y-m-d'); ?>" onkeypress="focusNext(event, 'select-imp-bl')">
                              <select id="select-imp-bl" class="fixed-width" placeholder="B/L#선택" maxlength="4" onkeypress="focusNext(event, 'searchBtn-imp')">
                                   <option value="">(awb)B/L# 선택</option>
                              </select>
                              <button id="searchBtn-imp">조회</button>
                              <?php if ($platForm === 'computer') { ?>
                                   <!-- <button id="printBtn-imp">출력</button> -->
                                   <button id="excel-imp">엑셀</button>
                              <?php } ?>
                         </div>
                         <!-- 2번째 줄: 테이블 컨테이너 -->
                         <div id="tables-imp" style="display: block; clear: both; align-items: center;">
                         </div>
                    </div>

                    <!-- GS완출 생산 조회 -->
                    <div id="subulGsView" style="display: none; clear: both; align-items: center;">
                         <!-- 1번째 줄: 조회 영역 -->
                         <div class="input-container search-row">
                              <input type="date" id="startDate-gs" value="<?php echo date('Y-m-d'); ?>">
                              <button id="searchBtn-gs">조회</button>
                              <?php if ($platForm === 'computer') { ?>
                                   <button id="printBtn-gs">출력</button>
                                   <button id="excel-gs">엑셀</button>
                                   <!-- <button id="excel-dayGs">일지</button>
                                   <button id="excel-formGs">양식</button> -->
                              <?php } ?>
                         </div>
                         <!-- 2번째 줄: 테이블 컨테이너 -->
                         <div id="tables-gs" style="display: block; clear: both; align-items: center;">
                         </div>
                    </div>

                    <!-- 판매 조회 -->
                    <div id="subulPurchaseOutView" style="display: none; clear: both; align-items: center;">
                         <!-- 1번째 줄: 조회 영역 -->
                         <div class="purchase-input-container search-row">
                              <input type="date" id="start-date-out" name="start-date-out" value="<?php echo date('Y-m-d', strtotime('-1 week')); ?>">
                              <label for="end-date-out">~</label>
                              <input type="date" id="end-date-out" name="end-date-out" value="<?php echo date('Y-m-d'); ?>">
                              <button id="search-btn-out">조회</button>
                         </div>
                         <!-- 2번째 줄: input 영역 -->
                         <div class="purchase-input-container input-row">
                              <input type="date" id="create-dt-out" value="<?php echo date('Y-m-d'); ?>" placeholder="판매일" onkeypress="focusNext(event, 'prod-code')">
                              <select id="prod-code" class="fixed-width" placeholder="상품명" style="width: 150px;" onkeypress="focusNext(event, 'lc-no')">
                                   <option value="">상품명#선택</option>
                                   <option value="2000000560770">연어필렛</option>
                                   <option value="0200000651343">연어(스테이크)</option>
                                   <option value="2000000668592">연어(스테이크용)벌크</option>
                              </select>
                              <select id="lc-no" class="fixed-width" placeholder="B/L#선택" maxlength="4" onkeypress="focusNext(event, 'origin-store-code')">
                                   <option value="">B/L#선택</option>
                              </select>

                              <select id="origin-store-code" class="fixed-width" placeholder="점번호" " onkeypress=" focusNext(event, 'store-code' )">
                                   <option value="">점번호#선택</option>
                              </select>
                              <select id="store-code" class="fixed-width" placeholder="판매점" " onkeypress=" focusNext(event, 'store-no' )">
                                   <option value="">판매점#선택</option>
                              </select>

                              <input type="text" id="store-no" class="flex-grow" placeholder="판매점(비고)" maxlength="20" style="width: 150px;" onkeypress="focusNext(event, 'weight-out')">
                              <input type="text" id="weight-out" class="fixed-width" placeholder="판매량(kg)" maxlength="7" style="width: 100px;"
                                   onkeypress="focusNext(event, 'manu-date')">
                              생산:<input type="date" id="manu-date" value="<?php echo date('Y-m-d'); ?>" placeholder="생산일" onkeypress="if(event.key === 'Enter') { purchaseOutClass.saveData(); return false; }">

                              <button id="insert-btn-out">입력</button>
                         </div>
                         <?php if ($platForm === 'computer') { ?>
                              <span style="font-size: 13x; color: red;">※생산(tbarcodeout)에 입력::개인점은 생산량에 추가(+)하고, 그 외 모든 판매점은 바코드 없는 판매데이터 임.</span>
                         <?php } ?>

                         <div class="actions">
                              <button id="update-btn-out" class="action-button edit-button">수정</button>
                              <button id="delete-btn-out" class="action-button delete-button">삭제</button>
                         </div>

                         <!-- 2번째 줄: 테이블 컨테이너 -->
                         <div class="table-container">
                              <div id="tables-out" style="display: block; clear: both; align-items: center;">
                              </div>
                         </div>

                    </div>

               </div>
          </div>
          <!-- 버튼 옆에 파일 input 추가 (숨김) -->
          <input type="file" id="excel-upload-input" accept=".xls,.xlsx" style="display:none;">

          <div id='loading' style='display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;'>
               <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                    <img src="/img/loading/0.gif" alt="로딩중" loading="lazy" decoding="async">
               </div>
          </div>

     </body>

     <script>
          let platform = 'computer';
          let subul_co_srv = '/wp-content/plugins/jofSubul/subul_co_srv.php';
          let subul_co_srv_day = '/wp-content/plugins/jofSubul/tableToExcelCoDay_srv.php';

          let subul_gs_srv = '/wp-content/plugins/jofSubul/subul_gs_srv.php';
          let subul_gs_srv_day = '/wp-content/plugins/jofSubul/tableToExcelGsDay_srv.php';

          let subul_bl_srv = '/wp-content/plugins/jofSubul/subul_bl_srv.php';
          let subul_bl_srv_day = '/wp-content/plugins/jofSubul/tableToExcelBlDay_srv.php';

          let subul_imp_srv = '/wp-content/plugins/jofSubul/subul_imp_srv.php';
          let subul_imp_srv_day = '/wp-content/plugins/jofSubul/tableToExcelImpDay_srv.php';

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
          });

          function focusNext(event, nextElementId) {
               if (event.key === 'Enter') {
                    event.preventDefault(); // 기본 Enter 키 동작 방지
                    const nextElement = document.getElementById(nextElementId);
                    if (nextElement) {
                         nextElement.focus();
                    }
               }
          }

          // 전역 범위로 함수들을 이동
          function hideAllViews() {
               const views = [
                    "subulCoView",
                    "subulBlView",
                    "subulImpView",
                    "subulGsView",
                    "subulPurchaseOutView"
               ];

               views.forEach(viewId => {
                    const element = document.getElementById(viewId);
                    if (element) {
                         element.style.display = "none";
                    }
               });
          }

          // CO
          function showSubulCo() {
               hideAllViews();
               document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;수불관리:CO::";
               document.getElementById("subulCoView").style.display = "block";
               if (!window.subulCoDay) {
                    window.subulCoDay = new subulCoDayClass();
               }
          }

          // BL
          function showSubulBl() {
               hideAllViews();
               document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;수불관리:BL::";
               document.getElementById("subulBlView").style.display = "block";
               if (!window.subulBlDay) {
                    window.subulBlDay = new subulBlDayClass();
               }
          }

          // 통관
          function showSubulImp() {
               console.log("showSubulImp 호출됨");
               hideAllViews();
               document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;수불관리:통관::";
               document.getElementById("subulImpView").style.display = "block";
               if (!window.subulImpDay) {
                    console.log("[ln295]subulImpDay 생성");
                    window.subulImpDay = new subulImpDayClass();
               }
          }

          // GS
          function showSubulGs() {
               hideAllViews();
               document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;수불관리:GS::";
               document.getElementById("subulGsView").style.display = "block";
               if (!window.subulGsDay) {
                    window.subulGsDay = new subulGsDayClass();
               }
          }

          // 판매
          function showPurchaseOut() {
               hideAllViews();
               document.getElementById("entry-title").innerHTML = "&nbsp;&nbsp;수불관리:판매::";
               document.getElementById("subulPurchaseOutView").style.display = "block";

               if (!window.purchaseOutClass) {
                    window.purchaseOutClass = new PurchaseOutClass();
               }
          }
     </script>

     </html>
<?php
}

add_shortcode('subul_mgt', 'subulShortCode');

// 엑셀 양식 업로드 AJAX 처리
add_action('wp_ajax_upload_excel_form', 'upload_excel_form_callback');
add_action('wp_ajax_nopriv_upload_excel_form', 'upload_excel_form_callback');
function upload_excel_form_callback()
{
     // 관리자/에디터만 업로드 가능
     if (!current_user_can('editor') && !current_user_can('administrator')) {
          wp_send_json_error('권한이 없습니다.');
     }

     if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
          wp_send_json_error('파일 업로드 오류');
     }

     $upload_dir = WP_PLUGIN_DIR . '/jofSubul/';
     $target_file = $upload_dir . '업무일지.xls';

     // 파일 확장자 체크 (xls, xlsx만 허용)
     $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
     if (!in_array($ext, ['xls', 'xlsx'])) {
          wp_send_json_error('엑셀 파일만 업로드 가능합니다.');
     }

     // 파일 저장
     if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $target_file)) {
          wp_send_json_success();
     } else {
          wp_send_json_error('파일 저장 실패');
     }
}
?>