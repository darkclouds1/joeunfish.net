<?php
// 코스트코 상품출고 INVOICE 출력 (냉장제품)
header('Content-Type: text/html; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

// POST 데이터를 JSON 형식으로 받습니다.
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// JSON 디코딩 에러가 발생할 경우, JSON 에러 메시지를 기록합니다.
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg(), 3, $logFilePath);
    error_log("Received input: " . $input, 3, $logFilePath); // 입력 데이터 로그에 기록
    die("Invalid JSON data received: " . json_last_error_msg());
}

// error_log(print_r($data, true), 3, $logFilePath);

$supply_dt = $data['deliveryDate'];
$supply_dt = date('Ymd', strtotime($supply_dt));
$dest_code = $data['storeCode'];
$params = $data['params'];  // 클라이언트에서 받은 params 배열

// 영어 요일을 한글 요일로 매핑합니다.
$dayOfWeek = date('l', strtotime($supply_dt));
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

if (empty($supply_dt) || empty($dest_code)) {
    error_log("Missing parameters: supply_dt or dest_code", 3, $logFilePath);
    die("Missing parameters: deliveryDate or storeCode");
}
// SQL 쿼리 작성
$sql = " SELECT ROW_NUMBER() OVER (ORDER BY RIGHT(PRD.product_code, 6)) AS no,
                    ORD.supply_dt AS supply_dt,
                    CONCAT('(', ORD.dest_code, ')', dest_name, ' ',ORD.region) AS region,
                    CONCAT(
                        SUBSTRING(ORD.doc_no, -10, 3), '-',
                        SUBSTRING(ORD.doc_no, -7, 4), '-',
                        RIGHT(ORD.doc_no, 3)
	                ) AS PO,
                    RIGHT(PRD.product_code, 6) AS ITEM,
                    COM.code_exp AS product_name,
                    COM.unit AS unit,
                    TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(COALESCE(PRD.quantity_new, PRD.quantity) AS CHAR))) AS quantity,
                    COALESCE(PRD.expi_date, DATE_ADD(ORD.supply_dt, INTERVAL COM.expi_day DAY)) AS expi_date,
                    PRD.weights AS weights,
                    COM.country_origin AS country_origin,
                    FORMAT(PRD.unit_price, 0) AS unit_price,
                    FORMAT(PRD.unit_price * COALESCE(PRD.quantity_new, PRD.quantity), 0) AS total_price,
                    COM.tax_rate AS tax_rate,
                    COM.temp_code AS temp_code,
                    CASE 
                        WHEN COM.tax_rate = 0 THEN '0'
                        ELSE FORMAT(
                            (PRD.unit_price * COALESCE(PRD.quantity_new, PRD.quantity)) - 
                            ((PRD.unit_price * COALESCE(PRD.quantity_new, PRD.quantity)) / (1 + COM.tax_rate / 100)),
                            0
                        )
                        END AS vat,
                    FORMAT(
                        CASE 
                            WHEN COM.tax_rate = 0 THEN PRD.unit_price * COALESCE(PRD.quantity_new, PRD.quantity)
                        ELSE (PRD.unit_price * COALESCE(PRD.quantity_new, PRD.quantity)) / (1 + COM.tax_rate / 100)
                    END,
                        0
                    ) AS total_price_without_vat
                FROM `torders` ORD
                JOIN `torderproducts` PRD ON ORD.doc_no = PRD.doc_no
                JOIN `tcomcode` COM ON COM.store_code = PRD.product_code 
                    AND COM.grp_code = '2' 
                    AND COM.use_yn = 'y'
                    AND COM.store_code NOT IN ('0200000651343','2000000560770','2000000668592', '2000000614117', '0200000684213')   -- 연어 & 스테이크 제외 -by darkcloud 25/6/10
                WHERE 1
                    AND PRD.quantity <> 0
                    AND ORD.msgfrom = 'COST01:DC'
                    AND ORD.supply_dt = ?
                    AND ORD.dest_code = ?
                ORDER BY RIGHT(PRD.product_code, 6) ASC; ";

// SQL 쿼리 준비
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("SQL prepare failed: " . $conn->error, 3, $logFilePath);
    die("SQL prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $supply_dt, $dest_code);
if (!$stmt->execute()) {
    error_log("SQL execute failed: " . $stmt->error, 3, $logFilePath);
    die("SQL execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
if (!$result) {
    error_log("SQL get_result failed: " . $stmt->error, 3, $logFilePath);
    die("SQL get_result failed: " . $stmt->error);
}
function parseNumber($numberStr)
{
    return floatval(str_replace(',', '', $numberStr));
}

// 첫 번째 쿼리 결과를 배열에 저장
$invoiceData = [];

while ($row = $result->fetch_assoc()) {
    $po = $row['PO'];
    $item = $row['ITEM'];

    // params 배열에서 poNumber와 itemNumber를 확인
    foreach ($params as $param) {
        if ($param['poNumber'] == $po && $param['itemNumber'] == $item) {
            if ($param['quantityChecked'] == 1) {
                unset($row['quantity']);
            }
            if ($param['expiDateChecked'] == 1) {
                unset($row['expi_date']);
            }
        }
    }
    // PO 번호별로 배열에 저장
    $invoiceData[$po][] = $row;

    // PO 번호별 첫번째 temp_code를 first_temp_code에 넣고, 첫번째 temp_code가 'F'='냉동'으로 first_temp_exp에 넣음
    if (!isset($invoiceData[$po]['first_temp_code'])) {
        $invoiceData[$po]['first_temp_code'] = $row['temp_code'];
        if ($row['temp_code'] == 'F') {
            $invoiceData[$po]['first_temp_exp'] = '냉동';
        } else {
            $invoiceData[$po]['first_temp_exp'] = '냉장';
        }
        if ($row['temp_code'] == 'E') {
            $invoiceData[$po]['first_temp_exp'] = '';
        }
    }
    // PO 번호별 합계 계산
    if (!isset($invoiceData[$po]['totals'])) {
        $invoiceData[$po]['totals'] = [
            'total_price_without_vat' => 0,
            'vat' => 0,
            'total_price' => 0,
            'total_quantity' => 0
        ];
    }

    // quantity가 삭제되지 않았을 경우에만 total_quantity에 더함
    if (isset($row['quantity'])) {
        $invoiceData[$po]['totals']['total_quantity'] += parseNumber($row['quantity']);
    }

    $invoiceData[$po]['totals']['total_price_without_vat'] += parseNumber($row['total_price_without_vat']);
    $invoiceData[$po]['totals']['vat'] += parseNumber($row['vat']);
    $invoiceData[$po]['totals']['total_price'] += parseNumber($row['total_price']);

    // weights 값이 있는 경우, PO별로 ","로 배열을 만듦
    if (!empty($row['weights'])) {
        $arrayWeights = explode(',', $row['weights']);
        if (!isset($invoiceData[$po]['weights'])) {
            $invoiceData[$po]['weights'] = [];
        }
        $invoiceData[$po]['weights'][] = $arrayWeights;
    }
}

// error_log(print_r($invoiceData, true), 3, $logFilePath);
// PO 번호별로 HTML 생성
$html = '<html>';
$html .= '<style>';
$html .= '.page-break { page-break-after: always; }';
$html .= 'table { width: 100%; margin-bottom: 20px; text-align: center; }';
$html .= 'table th, table td { text-align: center;, font-family: Arial, sans-serif; font-size: 15px; }';
$html .= '</style>';
foreach ($invoiceData as $po => $rows) {

    //  error_log("invoiceData:" . print_r($invoiceData, true), 3, $logFilePath);

    // 개별 중량의 갯수
    $totalWeights = 0;
    if (isset($invoiceData[$po]['weights'])) {
        $totalWeights = 0;
        foreach ($invoiceData[$po]['weights'] as $weightArray) {
            $totalWeights += count($weightArray);
        }
    }

    $html1page = '<body>';
    $html1page .= '<div style="display: flex; align-items: center; justify-content: space-between;">';
    $html1page .= '<div style="flex: 1;"><img src="/wp-content/uploads/2024/03/조은수산_로고2.jpg" alt="JOEUN Fisheries" style="width: 100px; height: auto;"></div>';
    $html1page .= '<h2 style="flex: 2; text-align: center; margin: 0;">★ INVOICE ★</h2>';
    $html1page .= '<br><br><br><br><br>';
    $html1page .= '<div style="flex: 1;"></div>'; // 이 부분은 오른쪽 공간을 비워두기 위해 추가
    $html1page .= '</div>';
    $html1page .= '<table border="0" style="width: 100%; margin-bottom: 20px;">';
    $html1page .= '<tr><td style="text-align: left;">공급처: COSTCO KOREA</td><td style="text-align: left;">납품일짜: ' . date('Y년 n월 j일 ', strtotime($supply_dt)) . $koreanDayOfWeek . '</td></tr>';
    $html1page .= '<tr><td></td><td style="text-align: left;">업체명: (주)조은수산</td></tr>';
    $html1page .= '<tr><td style="text-align: left;">점번호: ' . htmlspecialchars($rows[0]['region'], ENT_QUOTES, 'UTF-8') . '</td><td style="text-align: left;">VENDOR #: 54115-00</td></tr>';
    $html1page .= '<tr><td> </td><td style="text-align: left;">전화번호: 032-673-4116~7 <br>&emsp;&emsp;&emsp;&emsp; 010-2366-4116 </td></tr>';
    $html1page .= '<tr><td style="text-align: left;">PO #: ' . $po . '</td><td style="text-align: left;">FAX: 032-673-4115</td></tr>';
    $html1page .= '<tr><td style="text-align: left;"></td><td style="text-align: left;">Pallet: A J</td>';
    $html1page .= '<td colspan="2" style="text-align: center; border: 1px solid black; font-weight: bold;">' . $invoiceData[$po]['first_temp_exp'] . '</td><td></td><td></td></tr>';
    $html1page .= '</table>';

    // Table Header
    $html1page .= '<table border="1" style="width: 100%; border-collapse: collapse;">';
    $html1page .= '<tr><th style="border-bottom: double; height: 35px; width: 9%">ITEM</th>
                    <th style="border-bottom: double; width: 25%">상품명</th>
                    <th style="border-bottom: double; width: 5%">단위</th>
                    <th style="border-bottom: double; width: 9%">입점수량</th>
                    <th style="border-bottom: double; width: 13%">소비기한</th>
                    <th style="border-bottom: double; width: 9%">원산지</th>
                    <th style="border-bottom: double; width: 7%">온도</th>
                    <th style="border-bottom: double; width: 20%">비고</th></tr>';

    if (count($rows) > 0) {
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row['ITEM'])) {
                continue;
            }
            $count++;
        }
        $rowSpan = $count;
        $count = 0;
        foreach ($rows as $row) {
            if (empty($row['ITEM'])) {
                continue;
            }
    
            $html1page .= '<tr style="height: 35px;">';
            $html1page .= '<td>' . htmlspecialchars(substr($row['ITEM'], -6), ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: left;">' . htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td >' . htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align-last: right; padding-right: 5px;">' . htmlspecialchars($row['quantity'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td >' . htmlspecialchars($row['expi_date'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td >' . htmlspecialchars($row['country_origin'], ENT_QUOTES, 'UTF-8') . '</td>';
            // 온도, 비고
            if ($dest_code == '052' && $count == 0) {
                $html1page .= '<td rowspan="' . $rowSpan . '"></td>';
                $html1page .= '<td "></td>';
            } else {
                if ($dest_code == '052') {
                    // $html1page .= '<td style="width: 7%;">온도</td>';
                    $html1page .= '<td></td>';
                } else {
                    $html1page .= '<td></td>';
                    $html1page .= '<td></td>';
                }
            }
            $html1page .= '</tr>';

            $count++;
        }
    } else {
        $html1page .= '<tr><td colspan="8" style="height: 33px">데이터가 없습니다.</td></tr>';
    }

    // 토탈 입점수량
    $html1page .= '<tr><td colspan="3" style="height: 33px"></td><td style="text-align: right; height: 33px; padding-right: 5px;">' . htmlspecialchars($invoiceData[$po]['totals']['total_quantity'], ENT_QUOTES, 'UTF-8') . '</td><td colspan="4" style="height: 33px"></td></tr>';
    
    // === [여기서부터 개별 중량 표시] ===
    $alreadyPrinted = []; // 중복 방지용
    foreach ($rows as $row) {
        if (empty($row['ITEM'])) continue;
        if (!empty($row['weights']) && !in_array($row['ITEM'], $alreadyPrinted)) {
            $alreadyPrinted[] = $row['ITEM'];
            $weightsArr = explode(',', $row['weights']);
            // 상품명 + 개별중량 헤더
            $html1page .= '<tr>';
            $html1page .= '<td colspan="2" style="border-left: 1px solid white; border-bottom: 1px solid white; height: 33px"></td>';
            $html1page .= '<td colspan="6" style="height: 33px">' . htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') . ' 개별 중량</td>';
            $html1page .= '</tr>';

            // 개별 중량 값들
            $count = 0;
            foreach ($weightsArr as $w) {
                if ($count % 6 == 0) {
                    $html1page .= '<tr><td colspan="2" style="border-left: 1px solid white; border-bottom: 1px solid white; height: 33px"></td>';
                }
                $html1page .= '<td style="width: 10%; height: 33px">' . htmlspecialchars($w, ENT_QUOTES, 'UTF-8') . '</td>';
                $count++;
                if ($count % 6 == 0) {
                    $html1page .= '</tr>';
                }
            }
            // 마지막 행이 6개로 안 채워졌으면 빈 칸 추가
            while ($count % 6 != 0) {
                $html1page .= '<td style="height: 33px"></td>';
                $count++;
            }
            if ($count % 6 != 0) {
                $html1page .= '</tr>';
            }
        }
    }
    $html1page .= '</table>';
    $html1page .= '<div style="margin-top: 0.5cm;">';

    // $html1page .= '<div style="position: absolute; bottom: 3cm;">'; // A4 용지의 끝에서 5줄 위에 위치시키기 위해 절대 위치 사용
    if (true) {
        if ($dest_code === '855') { // 일산점은 오른쪽 정렬
            $html1page .= '<div style="float: right; text-align: right; margin-right: 70px;">';
            $html1page .= '<p style="text-align: left;">금액: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['total_price_without_vat']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
            $html1page .= '<p style="text-align: left;">부가세: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['vat']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
            $html1page .= '<p style="text-align: left;">총액: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['total_price']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
            $html1page .= '</div>';
        } else {
            $html1page .= '<p>금액: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['total_price_without_vat']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
            $html1page .= '<p>부가세: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['vat']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
            $html1page .= '<p>총액: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['total_price']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
        }
    } else if ($dest_code === '855') {  // 일산점은 오른쪽 정렬
        $html1page .= '<div style="float: right; text-align: right; margin-right: 70px;">';
        $html1page .= '<p style="text-align: left;">금액: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['total_price_without_vat']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
        $html1page .= '<p style="text-align: left;">부가세: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['vat']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
        $html1page .= '<p style="text-align: left;">총액: ' . htmlspecialchars(number_format(floor($invoiceData[$po]['totals']['total_price']), 0, '.', ','), ENT_QUOTES, 'UTF-8') . '</p>';
        $html1page .= '</div>';
    }

    $html1page .= '</div>';
    $html1page .= '</body>';
    $html1page .= '<div class="page-break"></div>'; // 페이지 나누기
    $html .= $html1page;
    // 출력물을 3벌 뽑기 위해 originHTML 을 2번 추가
    $html .= $html1page;
    $html .= $html1page;
}
$originHTML = $html;

// 페이지 여러장 출력
$html .= '</html>';
echo $html;
$stmt->close();
