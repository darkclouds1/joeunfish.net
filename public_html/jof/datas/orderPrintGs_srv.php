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
            COM.store_code AS '제품코드',
            COM.code_exp AS '제품명',
            -- 박스 수 계산
            ROUND(COALESCE(SUM(CASE WHEN ORD.dest_code IN ('62970', '60470', '66970', '62980', '68970') THEN PRD.quantity ELSE 0 END) / 
            CASE 
                WHEN COM.store_code = '8809611290037' THEN 4
                WHEN COM.store_code = '8809611290365' THEN 2
                WHEN COM.store_code = '8809611290266' THEN 4
                ELSE 1 -- 기본값
            END, 0)) AS '박스',
            -- 입수 설정
            CASE 
                WHEN SUM(CASE WHEN ORD.dest_code IN ('62970', '60470', '66970', '62980', '68970') THEN PRD.quantity ELSE 0 END) = 0 THEN 0
                WHEN COM.store_code = '8809611290037' THEN 4
                WHEN COM.store_code = '8809611290365' THEN 2
                WHEN COM.store_code = '8809611290266' THEN 4
                ELSE 1 -- 기본값
            END AS '입수',    
            COALESCE(SUM(CASE WHEN ORD.dest_code IN ('62970', '60470', '66970', '62980', '68970') THEN PRD.quantity ELSE 0 END), 0) AS '총수량',
            COALESCE(SUM(CASE WHEN ORD.dest_code = '62970' THEN PRD.quantity ELSE 0 END), 0) AS '이천|62970',
            COALESCE(SUM(CASE WHEN ORD.dest_code = '60470' THEN PRD.quantity ELSE 0 END), 0) AS '양산|60470',
            COALESCE(SUM(CASE WHEN ORD.dest_code = '66970' THEN PRD.quantity ELSE 0 END), 0) AS '남사|66970',
            COALESCE(SUM(CASE WHEN ORD.dest_code = '62980' THEN PRD.quantity ELSE 0 END), 0) AS '이천2|62980',
            COALESCE(SUM(CASE WHEN ORD.dest_code = '68970' THEN PRD.quantity ELSE 0 END), 0) AS '부산|68970'
        FROM 
            tcomcodegs COM
        LEFT JOIN 
            torderproducts PRD ON COM.store_code = PRD.product_code
        LEFT JOIN 
            torders ORD ON ORD.doc_no = PRD.doc_no AND ORD.supply_dt = ? AND ORD.msgfrom = 'GSSM01:DC'
        WHERE COM.grp_code = 2
            AND COM.use_yn = 'y'
        GROUP BY 
            COM.code_exp, COM.store_code
        ORDER BY 
            COM.order_num;";

// error_log($sql, 3, $logFilePath);

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
$html .= '.title { font-size: 16px; font-weight: bold; margin-bottom: 10px; text-align: left; }';
$html .= '</style>';
$html .= '<body>';
$html .= '<div class="title">납품일자: ' . date('Y년 m월 d일', strtotime($supply_dt)) . '</div>';
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
    // 총합계가 '0'인 경우 표시하지 않음
    $html .= '<tr>';
    foreach ($row as $key => $cell) {
        if ($key == '제품명') {
            $html .= '<td style="text-align: left;">' . htmlspecialchars($cell) . '</td>';
        } else {
            if ($cell == 0) {
                $html .= '<td></td>';
            } else {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
        }
    }
    $html .= '</tr>';
}

$html .= '</table>';
$html .= '</body>';

// error_log(print_r($invoiceData, true), 3, $logFilePath);

echo $html;
$stmt->close();
