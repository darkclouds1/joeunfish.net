<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

// 전달된 파라미터 가져오기
$category = $_GET['category'];
$deliveryDate = $_GET['deliveryDate'];

if (empty($deliveryDate)) {
    error_log("Missing parameters: deliveryDate ", 3, $logFilePath);
    die("Missing parameters: deliveryDate ");
}

if (empty($category)) {
    error_log("Missing parameters: category ", 3, $logFilePath);
    die("Missing parameters: category ");
}

$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

// 날짜를 기반으로 요일을 영어로 가져옵니다.
$dayOfWeek = date('l', strtotime($deliveryDate));

// 영어 요일을 한글 요일로 매핑합니다.
$daysKorean = [
    'Monday' => '월요일',
    'Tuesday' => '화요일',
    'Wednesday' => '수요일',
    'Thursday' => '목요일',
    'Friday' => '금요일',
    'Saturday' => '토요일',
    'Sunday' => '일요일'
];

// 영어 요일을 한글 요일로 변환합니다.
$koreanDayOfWeek = $daysKorean[$dayOfWeek];

// SQL 쿼리 작성
$sql = " SELECT 
                ord.supply_dt as supply_dt,
                ord.dest_code AS store_code,
                CASE
                    WHEN ord.region = '대구점2' THEN '혁신점'
                    ELSE ord.region
                END AS store_name,
                prd.product_code AS item_code,
                com_product.code_exp AS product_name,
                com_store.unit AS unit,
                prd.quantity AS order_quantity,
                ROUND(SUM(COALESCE(tout.weight, 0)), 1) AS received_quantity,
                MIN(tout.expi_date) AS expiration_date,
                com_product.country_origin AS country_origin,
                COUNT(tout.barcode_out) AS box_count,
                com_store.order_num AS order_num
            FROM 
                torders ord
            JOIN 
                torderproducts prd ON ord.doc_no = prd.doc_no
            JOIN 
                tcomcode com_store ON ord.dest_code = com_store.store_code
            JOIN 
                tcomcode com_product ON prd.product_code = com_product.store_code
            LEFT JOIN 
                tbarcodeout tout ON tout.prod_code = prd.product_code 
                                AND tout.store_code = ord.dest_code
                                AND DATE_FORMAT(tout.increate_dt, '%Y%m%d') = ord.supply_dt
            WHERE 
                LEFT(tout.increate_dt, 10) = ?
                AND com_store.order_num IS NOT NULL
            GROUP BY 
                ord.dest_code,
                ord.region,
                prd.product_code,
                prd.product_name,
                com_store.unit,
                prd.quantity,
                com_product.country_origin,
                com_store.order_num
            ORDER BY 
                prd.product_code DESC,
                com_store.order_num ASC
            ";

// SQL 쿼리 준비
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("SQL prepare failed: " . $conn->error, 3, $logFilePath);
    die("SQL prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $deliveryDate);
if (!$stmt->execute()) {
    error_log("SQL execute failed: " . $stmt->error, 3, $logFilePath);
    die("SQL execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    error_log("SQL get_result failed: " . $stmt->error, 3, $logFilePath);
    die("SQL get_result failed: " . $stmt->error);
}

// ----------------------------------
// 엑셀 다운로드 용 조회
// ----------------------------------
if ($_GET['category'] === 'summarySearch') {
    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;

    $stmt->close();
    $conn->close();
    return;
}

// ----------------------------------
// summary 프린터
// 첫 번째 쿼리 결과를 배열에 저장
// ----------------------------------

error_log("category: " . $category, 3, $logFilePath);

if ($category == 'summaryPrint') {

    error_log("category: " . $category, 3, $logFilePath);

    $invoiceData = [];
    while ($row = $result->fetch_assoc()) {
        $invoiceData[] = $row;
    }
    $stmt->close();
    $conn->close();

    // 첫 번째 요소를 가져옵니다.
    $row = reset($invoiceData);

    // 첫 번째 쿼리 결과를 기반으로 HTML 생성
    // item_code 별로 데이터를 그룹화합니다.
    $groupedData = [];
    foreach ($invoiceData as $data) {
        $groupedData[$data['item_code']][] = $data;
    }

    // HTML 생성
    $html = '<html><head>';
    $html .= '<style>';
    $html .= '@media print {';
    $html .= '  .page-break { page-break-before: always; }';
    $html .= '}';
    $html .= 'table { width: 100%; margin-bottom: 20px; text-align: center; }';
    $html .= 'table tr, table th, table td { text-align: center;, font-family: Arial, sans-serif; font-size: 15px; height: 50px !important; }'; // 높이를 50px로 설정
    $html .= '</style>';
    $html .= '</head>';
    $html = '<body>';

    foreach ($groupedData as $itemCode => $items) {

        error_log("itemCode: " . $itemCode, 3, $logFilePath);

        $html .= '<div style="display: flex; align-items: center; justify-content: space-between;">';
        $html .= '<div style="flex: 1;"><img src="/wp-content/uploads/2024/03/조은수산_로고2.jpg" alt="JOEUN Fisheries" style="width: 100px; height: auto;"></div>';
        $html .= '<h2 style="flex: 2; text-align: center; margin: 0;">★ INVOICE ★</h2>';
        $html .= '<br><br><br><br><br>';
        $html .= '<div style="flex: 1;"></div>'; // 이 부분은 오른쪽 공간을 비워두기 위해 추가
        $html .= '</div>';

        $html .= '<table border="0" style="width: 100%; margin-bottom: 20px;">';
        $html .= '<tr><td style="text-align: left;">공급처: COSTCO KOREA</td><td style="text-align: left;">납품일짜: ' . date('Y년 n월 j일 ', strtotime($deliveryDate)) . $koreanDayOfWeek . '</td></tr>';
        $html .= '<tr><td style="text-align: left;">점번호: 전점 </td><td style="text-align: left;"> VENDOR # : 54115-01 </td></tr>';
        $html .= '<tr><td style="text-align: left;"> </td><td style="text-align: left;">전화번호: 032-673-4116</td></tr>';
        $html .= '<tr><td style="text-align: left;"></td><td style="text-align: left;">FAX: 032-673-4115</td></tr>';
        $html .= '<tr><td style="text-align: left;"></td><td style="text-align: left;">담당자 H.P: 010-2366-4116</td></tr>';
        $html .= '</table>';

        $html .= '<br>';
        // body
        $html .= '<style>';
        $html .= 'table { width: 100%; margin-bottom: 20px; text-align: center; }';
        $html .= 'table th, table td { text-align: center;, font-family: Arial, sans-serif; font-size: 15px; }';
        $html .= '</style>';

        $html .= '<table border="1" style="width: 100%; border-collapse: collapse;">';

        $html .= '<tr style="height: 30px;"><th style="border-bottom: double;">점명</th><th style="border-bottom: double;">ITEM #</th><th style="border-bottom: double;">상품명</th><th style="border-bottom: double;">단위</th><th style="border-bottom: double;">발주수량</th><th style="border-bottom: double;">입점수량</th><th style="border-bottom: double;">유통기한</th><th style="border-bottom: double;">원산지</th><th style="border-bottom: double;">박스</th></tr>';

        // 데이터가 있는 경우에만 테이블 행 추가
        // 발주수량과 박스 수량의 합계를 계산
        $totalOrderQuantity = 0;
        $totalBoxCount = 0;
        $totalReceivedQuantity = 0; // 입점수량합계 초기화

        if (count($items) > 0) {
            $totalReceivedQuantity = 0; // 입점수량합계 초기화
            foreach ($items as $row) {
                $html .= '<tr style="height: 30px;">';
                $html .= '<td>' . htmlspecialchars($row['store_name'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars(substr($row['item_code'], -6), ENT_QUOTES, 'UTF-8') . '</td>';
                $row['product_name'] = str_replace(["연어（노르웨이） 매입품목 1KG", "연어（노르웨이） 매입품목 1"], "연어필렛", $row['product_name']);
                $html .= '<td>' . htmlspecialchars($row['product_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars(intval($row['order_quantity']), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['received_quantity'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['expiration_date'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['country_origin'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['box_count'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';

                $totalOrderQuantity += $row['order_quantity'];
                $totalReceivedQuantity += $row['received_quantity']; // 입점수량합계 계산
                $totalBoxCount += $row['box_count'];
            }

            // 합계 행 추가
            $html .= '<tr style="height: 30px;">';
            $html .= '<td colspan="4" style="text-align: right; font-weight: bold;">합계</td>';
            $html .= '<td>' . htmlspecialchars($totalOrderQuantity, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($totalReceivedQuantity, ENT_QUOTES, 'UTF-8') . '</td>'; // 입점수량합계 추가
            $html .= '<td></td>'; // 유통기한은 비워둠
            $html .= '<td></td>'; // 원산지는 비워둠
            $html .= '<td>' . htmlspecialchars($totalBoxCount, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '</tr>';
        } else {
            $html .= '<tr style="height: 30px;"><td colspan="9">데이터가 없습니다.</td></tr>';
        }

        $html .= '</table>';

        // 페이지 나누기 
        $html .= '<style>.page-break { page-break-after: always; }</style>';
        $html .= '<div class="page-break"></div>';
    }

    $html .= '</body></html>';
    // HTML 출력
    echo $html;
}
