<?php
// create_inventory_snapshot.php

// DOCUMENT_ROOT가 설정되지 않은 경우 (예: CLI 환경)를 위한 처리
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    // 이 파일의 경로는 /wp-content/plugins/barcodeOut/create_inventory_snapshot.php 이므로 3단계 상위 디렉토리입니다.
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
}

// DB 연결 설정
require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 로그 파일 경로
$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/plugins/barcodeOut/inventory_snapshot.log";

try {
    error_log("[" . date("Y-m-d H:i:s") . "] 일일 재고 스냅샷 생성을 시작합니다.\n", 3, $logFilePath);

    $sql = "
        REPLACE INTO tbarcodeout_invt (invt_date, cnt_fillets, cnt_fillets_map, cnt_steaks, cnt_steaks_bulk, cnt_wanchul)
        SELECT
            CURDATE(),
            COALESCE(SUM(CASE WHEN prod_nm = '연어필렛' AND store_code != '002' THEN 1 ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN prod_nm = 'Map연어필렛' AND store_code != '002' THEN 1 ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN prod_nm = '연어(스테이크)' AND store_code != '002' THEN 1 ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN prod_nm = '연어(스테이크용)벌크' AND store_code != '002' THEN 1 ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN store_code = '002' THEN 1 ELSE 0 END), 0)
        FROM
            `tbarcodeout`
        WHERE store_code IN ('000','001','002','003','004')
          AND invoice_yn = 'n';
    ";

    if ($conn->query($sql) === TRUE) {
        $message = "[" . date("Y-m-d H:i:s") . "] 오늘의 재고 스냅샷이 성공적으로 생성/업데이트되었습니다.\n";
        error_log($message, 3, $logFilePath);
    } else {
        throw new Exception("SQL 실행 오류: " . $conn->error);
    }

} catch (Exception $e) {
    $errorMessage = "[" . date("Y-m-d H:i:s") . "] 오류 발생: " . $e->getMessage() . "\n";
    error_log($errorMessage, 3, $logFilePath);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
