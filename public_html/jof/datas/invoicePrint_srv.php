<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

// 영어 요일을 한글 요일로 변환합니다.
// 날짜를 기반으로 요일을 영어로 가져옵니다.

// 전달된 파라미터 가져오기
$deliveryDate = $_GET['deliveryDate'];
$storeCode = $_GET['storeCode'];
$doc_no = $_GET['doc_no'];

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


$koreanDayOfWeek = $daysKorean[$dayOfWeek];

if (empty($deliveryDate) || empty($storeCode)) {
    error_log("Missing parameters: deliveryDate or storeCode", 3, $logFilePath);
    die("Missing parameters: deliveryDate or storeCode");
}

// SQL 쿼리 작성
$sql = "SELECT 
            ord.doc_no AS ord_doc_no, 
            ord.supply_dt,
            ord.dest_code, 
            ord.dest_name, 
            ord.region, 
            prd.quantity AS '주문량',
            prd.unit_price, 
            prd.total_price, 
            ord.product_count,
            prd.product_code,
            com.code_exp,
            com.unit,
            ROUND(SUM(COALESCE(tout.weight, 0)), 1) AS total_weight,
            Min(tout.expi_date) AS '소비기한',
            com.country_origin,
            COUNT(tout.barcode_out) AS box,
            ROUND(prd.unit_price * SUM(COALESCE(tout.weight, 0))) AS '금액'
            -- prd.unit_price * FLOOR(SUM(COALESCE(tout.weight, 0))) AS '금액'
        FROM 
            torders ord
        JOIN 
            torderproducts prd ON ord.doc_no = prd.doc_no
        JOIN 
            tcomcode com ON prd.product_code = com.store_code
        LEFT JOIN 
            tbarcodeout tout ON tout.prod_code = prd.product_code 
                            AND tout.store_code = ord.dest_code
                            AND DATE_FORMAT(tout.increate_dt,'%Y%m%d') = ord.supply_dt
                            AND tout.doc_no = ord.doc_no  -- 추가된 조건
        WHERE tout.invoice_yn = 'y'
          AND tout.store_code = ?
          AND LEFT(tout.increate_dt,10) = ?
          AND SUBSTRING(tout.doc_no, 5) = ?
        GROUP BY 
            ord.msgtype, 
            ord.doc_no, 
            ord.supply_dt, 
            ord.dest_code, 
            ord.dest_name, 
            ord.region, 
            ord.product_count,
            prd.product_code,
            prd.product_name,
            com.unit,
            prd.quantity,
            prd.unit_price, 
            prd.total_price, 
            prd.create_dt,
            LEFT(tout.increate_dt,10)
      ORDER BY com.order_num ASC
            ";

// SQL 쿼리 준비
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("SQL prepare failed: " . $conn->error, 3, $logFilePath);
    die("SQL prepare failed: " . $conn->error);
}

$stmt->bind_param("sss", $storeCode, $deliveryDate, substr($doc_no, 4));
if (!$stmt->execute()) {
    error_log("SQL execute failed: " . $stmt->error, 3, $logFilePath);
    die("SQL execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    error_log("SQL get_result failed: " . $stmt->error, 3, $logFilePath);
    die("SQL get_result failed: " . $stmt->error);
}

if ($result->num_rows === 0) {
    echo json_encode(['error' => '출고 정보를 찾지 못했습니다. [출고확정]이 되었는지 확인하세요.']);
    $stmt->close();
    $conn->close();
    exit;
}

// 첫 번째 쿼리 결과를 배열에 저장

$invoiceDataByDocNo = [];
while ($row = $result->fetch_assoc()) {
    $invoiceDataByDocNo[$row['ord_doc_no']][] = $row;
}
// error_log("invoiceDataByDocNo: " . print_r($invoiceDataByDocNo, true), 3, $logFilePath);
$stmt->close();

$html = '<html>';
$html .= '<style>';
$html .= '.page-break { page-break-after: always; }';
$html .= 'table { width: 100%; margin-bottom: 20px; text-align: center; }';
$html .= 'table th, table td { text-align: center;, font-family: Arial, sans-serif; font-size: 15px; }';
$html .= '</style>';

foreach ($invoiceDataByDocNo as $ordDocNo => $invoiceData) {

    // error_log("ln138::invoiceData: " . print_r($invoiceData, true), 3, $logFilePath);
    // 개별무게 조회
    $sql =
    " SELECT 
                    prd.doc_no,
                    prd.product_code,
                    tout.weight AS individual_weight
                FROM 
                    torders ord
                JOIN 
                    torderproducts prd ON ord.doc_no = prd.doc_no
                LEFT JOIN 
                    tbarcodeout tout ON tout.prod_code = prd.product_code 
                                    AND tout.store_code = ord.dest_code
                                    AND DATE_FORMAT(tout.increate_dt,'%Y%m%d') = ord.supply_dt
                                    AND SUBSTRING(tout.doc_no, 5) = SUBSTRING(ord.doc_no, 5)
                WHERE tout.invoice_yn = 'y'
                AND tout.store_code = ?
                AND LEFT(tout.increate_dt,10) = ?
                AND prd.doc_no = ?
            ORDER BY prd.product_code ASC, tout.weight ASC, tout.expi_date ASC; 
        ";

    // error_log("ln160::sql: " . $sql, 3, $logFilePath);

    // SQL 쿼리 준비
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log(
            "SQL prepare failed: " . $conn->error,
            3,
            $logFilePath
        );
        die("SQL prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sss", $storeCode, $deliveryDate, $invoiceData[0]['ord_doc_no']);
    if (!$stmt->execute()) {
        error_log("SQL execute failed: " . $stmt->error, 3, $logFilePath);
        die("SQL execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        error_log("SQL get_result failed: " . $stmt->error, 3, $logFilePath);
        die("SQL get_result failed: " . $stmt->error);
    }

    // 두 번째 쿼리 결과를 배열에 저장
    $productWeights = [];
    while ($row = $result->fetch_assoc()) {
        $productWeights[$row['product_code']][] = $row;
    }
    $stmt->close();

    // error_log("ln196::productWeights: " . print_r($productWeights, true), 3, $logFilePath);

    // 첫 번째 요소를 가져옵니다.
    // $row = reset($invoiceData);

    $html1page = '';
    $html1page .= '<body>';
    $html1page .= '<div style="display: flex; align-items: center; justify-content: space-between;">';
    $html1page .= '<div style="flex: 1;"><img src="/wp-content/uploads/2024/03/조은수산_로고2.jpg" alt="JOEUN Fisheries" style="width: 100px; height: auto;"></div>';
    $html1page .= '<h2 style="flex: 2; text-align: center; margin: 0;">★ INVOICE ★</h2>';
    $html1page .= '<br><br><br><br><br>';
    $html1page .= '<div style="flex: 1;"></div>'; // 이 부분은 오른쪽 공간을 비워두기 위해 추가
    $html1page .= '</div>';


    $html1page .= '<table border="0" style="width: 100%; margin-bottom: 20px;">';
    $html1page .= '<tr><td style="text-align: left;">공급처: COSTCO KOREA</td><td style="text-align: left;">납품일짜: ' . date('Y년 n월 j일 ', strtotime($deliveryDate)) . $koreanDayOfWeek . '</td></tr>';
    $html1page .= '<tr><td style="text-align: left;">점번호: ' . htmlspecialchars($storeCode, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($invoiceData[0]['region'], ENT_QUOTES, 'UTF-8') . ')</td><td style="text-align: left;">업체명: 조은수산</td></tr>';
    $html1page .= '<tr><td style="text-align: left;">DEPT: 64</td><td style="text-align: left;">전화번호: 032-673-4116</td></tr>';
    $html1page .= '<tr><td style="text-align: left;">PO #: ' . substr($invoiceData[0]['ord_doc_no'], 4, 3) . '-' . substr($invoiceData[0]['ord_doc_no'], 7, 4) . '-' . substr($invoiceData[0]['ord_doc_no'], 11, 3) . '</td><td style="text-align: left;">FAX: 032-673-4115</td></tr>';
    $html1page .= '<tr><td style="text-align: left;"></td><td style="text-align: left;">담당자 H.P: 010-2366-4116</td></tr>';
    $html1page .= '<tr><td style="text-align: left;"></td><td style="text-align: left;">Pallet: A J</td></tr>';
    $html1page .= '</table>';

    // 이 부분은 오른쪽 공간에 "냉장" 박스를 추가
    $html1page .= '<div style="flex: 3; text-align: right;"><div style="border: 1px solid black; padding: 5px; display: inline-block;">냉장</div></div>';
    $html1page .= '<br>';

    // body
    $html1page .= '<table border="1" style="width: 100%; border-collapse: collapse;">';
    $html1page .= '<tr><th style="border-bottom: double; height: 37px; text-align: center;">ITEM #</th>
                    <th style="border-bottom: double; text-align: center; ">상품명</th>
                    <th style="border-bottom: double; text-align: center; width: 70px;">단위</th>
                    <th style="border-bottom: double; text-align: center; width: 80px;">입점 중량</th>
                    <th style="border-bottom: double; text-align: center; width: 80px;">박스 수량</th>
                    <th style="border-bottom: double; text-align: center;">소비기한</th>
                    <th style="border-bottom: double; text-align: center;">원산지</th>
                    <th style="border-bottom: double; text-align: center; width: 80px;">온도</th></tr>';

    // 데이터가 있는 경우에만 총액 계산
    $totalAmount = 0;
    if (
        count($invoiceData) > 0
    ) {
        foreach ($invoiceData as $row) {

            $html1page .= '<tr style="height: 37px;">';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars(substr($row['product_code'], -6), ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: left; padding-left: 10px;">' . htmlspecialchars($row['code_exp'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: right; padding-right: 30px;">' . htmlspecialchars($row['total_weight'], ENT_QUOTES, 'UTF-8') . '</td>';
            // $html1page .= '<td style="text-align: right; padding-right: 30px;">' . htmlspecialchars($row['box'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: right; padding-right: 30px;">' . ($storeCode !== '063' ? htmlspecialchars($row['box'], ENT_QUOTES, 'UTF-8') : '') . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['소비기한'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['country_origin'], ENT_QUOTES, 'UTF-8') . '</td>';
            // 온도
            $html1page .= '<td></td>';
            $html1page .= '</tr>';
            $totalAmount += $row['금액']; // 각 row의 금액을 총액에 더합니다.
        }
    } else {
        $html1page .= '<tr><td colspan="9">데이터가 없습니다.</td></tr>';
    }

    $html1page .= '</table>';
    $html1page .= '<div style="margin-top: 5cm;">';

    // $html1page .= '<div style="position: absolute; bottom: 3cm;">'; // A4 용지의 끝에서 5줄 위에 위치시키기 위해 절대 위치 사용
    if (
        $storeCode === '855'
    ) {
        $html1page .= '<div style="float: right; text-align: right; margin-right: 70px;">';
        $html1page .= '<p style="text-align: left;">금액: </p>';
        $html1page .= '<p style="text-align: left;">부가세: </p>';
        $html1page .= '<p style="text-align: left;">총액: ' . htmlspecialchars(
            number_format(
                floor($totalAmount),
                0,
                '.',
                ','
            ),
            ENT_QUOTES,
            'UTF-8'
        ) . '</p>';
        $html1page .= '</div>';
    } else {
        $html1page .= '<p>금액: </p>'; // 총 금액 표시
        $html1page .= '<p>부가세: </p>'; // 부가세 계산 및 표시
        // 총액 계산 및 표시, 소수점 이하 절사 및 천 단위 쉼표 추가
        $html1page .= '<p>총액: ' . htmlspecialchars(number_format(floor($totalAmount), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
        $html1page .= '</div>';
    }

    $html1page .= '</body></html>';
    $html1page .= '<div class="page-break"></div>';

    // 여러장 출력
    // for ($i = 0; $i < 3; $i++) {
    //     $html .= $html1page;
    //     if ($i < 2) {
    //         $html .= '<div class="page-break"></div>';
    //     }
    // }
    $html_invoicePage = "";
    for ($i = 0; $i < 3; $i++) {
        $html_invoicePage .= $html1page;
    }

    $html .= $html_invoicePage;

    // =================
    // item 별로 표시
    // =================
    // $row = reset($invoiceData);
    // error_log("ln330::invoiceData: " . print_r($invoiceData, true), 3, $logFilePath);
    foreach ($invoiceData as $index => $row) {

        // error_log("ln337::row: " . print_r($row, true), 3, $logFilePath);
        // 검폼 첨부 (연어필렛)
        $html2page = "";
        // 검품 첨부 HTML 생성
        $html2page .= '<html><body>';
        $html2page .= '<h2 style="text-align: center;">검품 첨부</h2>';
        $html2page .= '<div style="display: flex; justify-content: space-between;">';
        $html2page .= '<p style="text-align: left;">점번호: ' . htmlspecialchars($storeCode, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($row['region'], ENT_QUOTES, 'UTF-8') . ')</p>';
        $html2page .= '<p style="text-align: right;">납품일짜: ' . date('Y년 n월 j일 ', strtotime($deliveryDate)) . $koreanDayOfWeek . '</p>';
        $html2page .= '</div>';
        $html2page .= '<br>';

        // 각 상품별로 고유한 ID를 부여하기 위해 index를 사용
        $html2page .= '<table id="product-' . htmlspecialchars($row['product_code'], ENT_QUOTES, 'UTF-8') . '-' . $index . '-' . $row['ord_doc_no'] . '" border="1" style="width: 100%; border-collapse: collapse;">';
        $html2page .= '<tr><th style="border-bottom: double; height: 30px;">ITEM #</th>
                        <th style="border-bottom: double;">상품명</th>
                        <th style="border-bottom: double;">단위</th>
                        <th style="border-bottom: double;">입점 중량</th>
                        <th style="border-bottom: double;">박스 수량</th>
                        <th style="border-bottom: double;">소비기한</th>                    
                        <th style="border-bottom: double;" colspan="2">비고</th>
                        </tr>';

        $html2page .= '<tr>';
        $html2page .= '<td style="width: 80px; height: 25px; border-bottom: double;">' . htmlspecialchars(substr($row['product_code'], -6), ENT_QUOTES, 'UTF-8') . '</td>';
        $html2page .= '<td style="width: 150px; border-bottom: double;">' . htmlspecialchars($row['code_exp'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html2page .= '<td style="width: 80px; border-bottom: double;">' . htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html2page .= '<td style="width: 80px; border-bottom: double;">' . htmlspecialchars(
            $row['total_weight'],
            ENT_QUOTES,
            'UTF-8'
        ) . '</td>';
        $html2page .= '<td style="width: 80px; border-bottom: double;">' . htmlspecialchars($row['box'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html2page .= '<td style="width: 100px; border-bottom: double;">' . htmlspecialchars($row['소비기한'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html2page .= '<td style="width: 80px; border-bottom: double;"></td>'; // 비고
        $html2page .= '<td style="width: 80px; border-bottom: double;"></td>'; // 비고2
        $html2page .= '</tr>';

        if (array_key_exists($row['product_code'], $productWeights)) {
            $weights = $productWeights[$row['product_code']];
            $weightCount = count($weights);

            if ($weightCount <= 30) {
                $rowsNeeded = $weightCount;
            } else {
                $rowsNeeded = 30;
            }

            // 필요한 행만큼 <tr> 태그 생성
            for ($j = 0; $j < $rowsNeeded; $j++) {
                $html2page .= '<tr style="height: 25px;">'; // 행의 높이를 12px로 설정
                // $html2page .= '<td></td><td></td><td></td>'; // 첫 세 컬럼은 비워둡니다.
                for ($i = 0; $i < 8; $i++) { // 8개의 추가 컬럼을 생성합니다.
                    $html2page .= '<td></td>';
                }
                $html2page .= '</tr>';
            }

            // 각 행에 대해 weight 값을 채워 넣기
            // invoiceData가 변경될 때마다 #weightTable의 ID에 01, 02 숫자를 증가시키는 코드
            $columnIndex = 0;
            $rowIndex = 0;

            for ($i = 0; $i < $weightCount; $i++) {
                if ($weightCount < 151) {
                    $columnIndex = intdiv($i, 30) + 3; // 4번째 컬럼부터 시작, 30개 단위로 변경
                    $rowIndex = $i % 30;
                } else {
                    $columnIndex = intdiv($i, 30) + 1; // 2번째 컬럼부터 시작, 30개 단위로 변경
                    $rowIndex = $i % 30;
                }

                // 해당 위치에 weight 값 삽입:: table id 에  doc_no 사용하여 위치 지정
                $html2page .= '<script>';
                $html2page .= 'var tableName = "product-' . htmlspecialchars($row['product_code'], ENT_QUOTES, 'UTF-8') . '-' . $index . '-' . $row['ord_doc_no'] . '";';
                $html2page .= 'var table = document.querySelector("#" + tableName);'; // 테이블 선택
                $html2page .= 'var row = table.rows[' . ($rowIndex + 2) . '];';
                $html2page .= 'var cell = row.cells[' . $columnIndex . '];';
                $html2page .= 'cell.innerHTML = "' . htmlspecialchars($weights[$i]['individual_weight'], ENT_QUOTES, 'UTF-8') . '";'; // 셀에 weight 값 삽입
                $html2page .= '</script>';
            }

            if (
                $storeCode === '060'
            ) {
                $html2page .= '<tr>';
                if ($weightCount >= 150) {
                    $html2page .= '<td>소계:</td>';
                    for ($i = 0; $i < 5; $i++) {
                        $sum = 0;
                        for ($j = $i * 30; $j < ($i + 1) * 30 && $j < $weightCount; $j++) {
                            $sum += $weights[$j]['individual_weight'];
                        }
                        if ($sum == 0) {
                            $html2page .= '<td></td>';
                        } else {
                            $html2page .= '<td>' . htmlspecialchars($sum, ENT_QUOTES, 'UTF-8') . '</td>';
                        }
                    }
                } else {
                    $html2page .= '<td colspan="3">소계:</td>';
                    for ($i = 0; $i < 5; $i++) {
                        $sum = 0;
                        for ($j = $i * 30; $j < ($i + 1) * 30 && $j < $weightCount; $j++) {
                            $sum += $weights[$j]['individual_weight'];
                        }
                        if ($sum == 0) {
                            $html2page .= '<td></td>';
                        } else {
                            $html2page .= '<td>' . htmlspecialchars($sum, ENT_QUOTES, 'UTF-8') . '</td>';
                        }
                    }
                }
                $html2page .= '</tr>';
            }
            $html2page .= '</table>';
            $html2page .= '</body></html>';
            $html2page .= '<div class="page-break"></div>';
            $html .= $html2page;
        }
    }   // doc_no 별로 반복문 종료
}

// HTML 출력
echo $html;
