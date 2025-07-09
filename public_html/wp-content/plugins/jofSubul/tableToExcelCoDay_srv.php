<?php
session_start();
date_default_timezone_set('Asia/Seoul');

// 엑셀 파일 생성 라이브러리 로드
require($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
// require('/var/www/fish/vendor/autoload.php');
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;


// 로그 설정 - 강제로 로그 파일 생성
$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

// 로그 파일이 없으면 생성
if (!file_exists($logFilePath)) {
    touch($logFilePath);
    chmod($logFilePath, 0666);
}

try {
    
    require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
    $dbConn = new dbconnClassFish;
    $conn = $dbConn->conn();
    
    if (!$conn) {
        throw new Exception("데이터베이스 연결 실패: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($conn, 'utf8');
    mysqli_query($conn, "SET time_zone = '+09:00'");

    //날짜 처리
    $supply_dt = date('Ymd', strtotime($_GET['deliveryDate']));
    $supply_dt = str_replace('-', '', $supply_dt);
    
    // 로그 메시지 추가
    $logMessage = "처리할 날짜: " . $supply_dt . "\n";
    file_put_contents($logFilePath, $logMessage, FILE_APPEND | LOCK_EX);
    error_log("처리할 날짜: " . $supply_dt, 3, $logFilePath);
    
    // 8. 템플릿 파일 확인 - uploads 폴더에서 찾도록 변경
    $template_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/daily_order.xls';
    if (!file_exists($template_path)) {
        throw new Exception('템플릿 파일이 없습니다: ' . $template_path);
    }
    error_log("템플릿 파일 존재 확인: " . $template_path, 3, $logFilePath);
    
    // 9. 엑셀 헤더 설정 전에 출력 버퍼 정리
    if (ob_get_length()) ob_end_clean();

    // 엑셀 헤더 설정
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="업무일지_' . $supply_dt . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    // 10. 템플릿 로드
    error_log("템플릿 로드 시도...", 3, $logFilePath);
    $spreadsheet = IOFactory::load($template_path);
    $sheet = $spreadsheet->getActiveSheet();
    error_log("템플릿 로드 성공", 3, $logFilePath);
    
    // 10. 날짜 셀 설정
    $weekday = ['일', '월', '화', '수', '목', '금', '토'];
    $dateObj = strtotime($supply_dt);
    $dateStr = date('Y년 n월 j일', $dateObj) . ' ' . $weekday[date('w', $dateObj)] . '요일';
    $sheet->setCellValue('H2', $dateStr);
    $sheet->setCellValue('C2', $dateStr); // C3에도 날짜 입력

    // SQL 쿼리는 동일하게 유지
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
         IFNULL(CASE WHEN ord.region = '평택점' THEN prd.quantity ELSE 0 END, 0) +
         IFNULL(CASE WHEN ord.region = '대구점' THEN prd.quantity ELSE 0 END, 0) +
         IFNULL(CASE WHEN ord.region = '대구점2' THEN prd.quantity ELSE 0 END, 0) +
         IFNULL(CASE WHEN ord.region = '부산점' THEN prd.quantity ELSE 0 END, 0) +
         IFNULL(CASE WHEN ord.region = '울산점' THEN prd.quantity ELSE 0 END, 0) +
         IFNULL(CASE WHEN ord.region = '김해점' THEN prd.quantity ELSE 0 END, 0)
    ) AS '총계'
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

    // 11. SQL 실행 로깅
    error_log("\nsupply_dt=" . $supply_dt, 3, $logFilePath);
    // error_log("\nsql=" . $sql, 3, $logFilePath);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare 실패: " . $conn->error);
    }
    $stmt->bind_param("s", $supply_dt);
    if (!$stmt->execute()) {
        throw new Exception("SQL 실행 실패: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("결과 가져오기 실패: " . $stmt->error);
    }
    
    // 12. 데이터 처리
    error_log("\n엑셀 데이터 채우기 시작...", 3, $logFilePath);

    // 1. 템플릿의 A4~A100 제품명 읽기 (범위 확장)
    $productNames = [];
    for ($row = 4; $row <= 100; $row++) {
        $name = trim($sheet->getCell('A' . $row)->getValue());
        if ($name !== '') {
            $productNames[$name] = $row; // 제품명 => 행번호
            // error_log("\n템플릿 제품명: " . $name . " => 행 " . $row, 3, $logFilePath);
        }
    }
    error_log("\n템플릿에서 읽은 제품명 수: " . count($productNames), 3, $logFilePath);

    // 2. D3~Z3 헤더 읽기
    $colHeaders = [];
    for ($col = 'D'; $col <= 'Z'; $col++) {
        $header = trim($sheet->getCell($col . '3')->getValue());
        if ($header !== '') {
            $colHeaders[$header] = $col; // 헤더명 => 열알파벳
        }
    }

    function normalize($str) {
        return trim($str);
    }
    $normProductNames = [];
    foreach ($productNames as $name => $row) {
        $normProductNames[normalize($name)] = $row;
    }
    $normColHeaders = [];
    foreach ($colHeaders as $header => $col) {
        $normColHeaders[normalize($header)] = $col;
    }
    $row_count = 0;
    $matched_count = 0;
    while ($row = $result->fetch_assoc()) {
        $pnameNorm = normalize(trim($row['product_name']));
        if (!isset($normProductNames[$pnameNorm])) continue;
        $rowIdx = $normProductNames[$pnameNorm];
        $data_written = false;
        
        foreach ($row as $key => $value) {
            if ($key == 'product_name') continue;
            $normKey = normalize($key);
            if (!isset($normColHeaders[$normKey])) continue;
            $colIdx = $normColHeaders[$normKey];
            $sheet->setCellValue($colIdx . $rowIdx, ($value == 0) ? '' : $value);
            $data_written = true;
        }
        
        if ($data_written) {
            $matched_count++;
        }
        $row_count++;
    }

    $stmt->close();
    
    // 13. 엑셀 파일 출력
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    
    // 이미 보낸 헤더가 있는지 확인
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
