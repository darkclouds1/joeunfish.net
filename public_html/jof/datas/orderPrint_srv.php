<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');
require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn     = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
// 영어 요일을 한글 요일로 변환합니다.
// 날짜를 기반으로 요일을 영어로 가져옵니다.
// 전달된 파라미터 가져오기
$supply_dt = $_GET['deliveryDate'];
$supply_dt = date('Ymd', strtotime($supply_dt));

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
if (empty($supply_dt)) {
    error_log("Missing parameters: supply_dt or dest_code", 3, $logFilePath);
    die("Missing parameters: deliveryDate or storeCode");
}
// SQL 쿼리 작성
$sql = "SELECT 
                IFNULL(c.code_exp, '0') AS product_name,
                SUM(CASE WHEN ord.region = '양평점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '양평',
                SUM(CASE WHEN ord.region = '양재점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '양재',
                SUM(CASE WHEN ord.region = '상봉점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '상봉',
                SUM(CASE WHEN ord.region = '일산점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '일산',
                SUM(CASE WHEN ord.region = '대전점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '대전',
                SUM(CASE WHEN ord.region = '광명점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '광명',
                SUM(CASE WHEN ord.region = '의정부' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '의정부',
                SUM(CASE WHEN ord.region = '천안점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '천안',
                SUM(CASE WHEN ord.region = '공세점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '공세',
                SUM(CASE WHEN ord.region = '송도점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '송도',
                SUM(CASE WHEN ord.region = '세종점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '세종',
                SUM(CASE WHEN ord.region = '하남점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '하남',
                SUM(CASE WHEN ord.region = '고척점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '고척',
                SUM(CASE WHEN ord.region = '청라점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '청라',
                SUM(CASE WHEN ord.region = '평택점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '평택',
                SUM(CASE WHEN ord.region = '대구점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '대구',
                SUM(CASE WHEN ord.region = '대구점2' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '혁신',
                SUM(CASE WHEN ord.region = '부산점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '부산',
                SUM(CASE WHEN ord.region = '울산점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '울산',
                SUM(CASE WHEN ord.region = '김해점' THEN IFNULL(prd.quantity, 0) ELSE 0 END) AS '김해',
                SUM(
                    IFNULL(CASE WHEN ord.region = '양평점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '양재점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '상봉점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '일산점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '대전점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '광명점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '의정부' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '천안점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '공세점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '송도점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '세종점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '하남점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '고척점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '청라점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '평택점' THEN prd.quantity ELSE 0 END, 0)
                ) AS '서울소계',
                SUM(
                    IFNULL(CASE WHEN ord.region = '대구점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '대구점2' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '부산점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '울산점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '김해점' THEN prd.quantity ELSE 0 END, 0)
                ) AS '지방소계',
                SUM(
                    IFNULL(CASE WHEN ord.region = '양평점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '양재점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '상봉점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '일산점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '대전점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '광명점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '의정부' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '천안점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '공세점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '송도점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '세종점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '하남점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '고척점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '청라점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '대구점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '대구점2' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '부산점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '울산점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '김해점' THEN prd.quantity ELSE 0 END, 0) +
                    IFNULL(CASE WHEN ord.region = '평택점' THEN prd.quantity ELSE 0 END, 0)
                ) AS '총합계'
            FROM 
                tcomcode c
            LEFT JOIN 
                torderproducts prd ON c.store_code = prd.product_code
            LEFT JOIN 
                torders ord ON prd.doc_no = ord.doc_no AND ord.supply_dt = ?
            WHERE 
                c.use_yn = 'y'
                AND c.grp_code = '2'
            GROUP BY 
                c.code_exp, c.order_num
            ORDER BY 
                c.order_num ASC;";

// SQL 쿼리 준비
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("SQL prepare failed: " . $conn->error, 3, $logFilePath);
    die("SQL prepare failed: " . $conn->error);
}
$stmt->bind_param("s", $supply_dt);
if (!$stmt->execute()) {
    error_log("SQL execute failed: " . $stmt->error, 3, $logFilePath);
    die("SQL execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    error_log("SQL get_result failed: " . $stmt->error, 3, $logFilePath);
    die("SQL get_result failed: " . $stmt->error);
}

$html .= '<style>';
$html .= 'table { width: 100%; border-collapse: collapse; }';
$html .= 'table, th, td { border: 1px solid black; }';
$html .= 'th, td { padding: 8px; text-align: center; font-family: Arial, sans-serif; font-size: 12px; }';
$html .= 'th { background-color: #f2f2f2; }';
$html .= '</style>';
$html .= '<body>';
$html .= '<table>';
$html .= '<tr>';

// 헤더 생성
$html .= '<tr style="background-color: gray;">';
$fields = $result->fetch_fields();

foreach ($fields as $field) {
    if ($field->name == 'product_name') {
        $html .= '<th>' . date('Y년 m월 d일', strtotime($supply_dt)) . '</th>';
    } else {
        $html .= '<th>' . htmlspecialchars($field->name) . '</th>';
    }
}
$html .= '</tr>';

// 데이터 생성
while ($row = $result->fetch_assoc()) {

    foreach ($row as $key => $value) {
        if (is_numeric($value)) {
            $row[$key] = number_format($value, 0);
        }
    }

    // "총합계"가 0이면 표시하지 않음
    if (isset($row['총합계']) && $row['총합계'] == 0) {
        continue;
    }

    $html .= '<tr>';
    foreach ($row as $key => $cell) {
        if ($cell == 0) {
            $cell = '';
        }

        if ($key == 'product_name') {
            if ($key == 'product_name' && ($cell == '연어필렛' || $cell == '연어(스테이크용)' || $cell == '연어(스테이크용)벌크')) {
                $html .= '<td style="text-align: left; padding: 2px; background-color: yellow; font-weight: bold;">' . htmlspecialchars($cell) . '</td>';
            } else {
                $html .= '<td style="text-align: left; padding: 2px;">' . htmlspecialchars($cell) . '</td>';
            }
        } else {
            $html .= '<td style="padding: 2px; text-align: right;">' . htmlspecialchars($cell) . '</td>';
        }
    }
    $html .= '</tr>';
}

$html .= '</table>';
$html .= '</body>';

// error_log(print_r($invoiceData, true), 3, $logFilePath);

echo $html;
$stmt->close();
