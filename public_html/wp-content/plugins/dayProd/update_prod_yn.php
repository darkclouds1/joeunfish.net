<?php
// DB 연결
header('Content-Type: application/json; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');

// 서버 환경에 따라 경로 설정
if (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = '/home/u753849441/public_html/'; // 정확한 경로로 수정
}
require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

$logFilePath = __DIR__ . "/logfile_update_prod_yn.log";
error_log("\ndbConn_fish.php 파일 경로: " . $documentRoot, 3, $logFilePath);

$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();

mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

// 오늘 날짜 기준으로 3일 이전 데이터 prod_yn을 'y'로 업데이트
$sql = "UPDATE tdayprod 
        SET prod_yn = 'y' 
        WHERE increate_dt <= DATE_SUB(CURDATE(), INTERVAL 0 DAY)";

if (mysqli_query($conn, $sql)) {
        $currentDate = date('Y-m-d H:i:s');
        echo "\n업데이트 성공!";
        error_log("\n[생산확정]업데이트 성공! - 처리일시: " . $currentDate, 3, $logFilePath);
} else {
        $currentDate = date('Y-m-d H:i:s');
        echo "\n에러 발생: " . mysqli_error($conn);
        error_log("\n[생산확정]업데이트 에러 발생: " . mysqli_error($conn) . " - 처리일시: " . $currentDate, 3, $logFilePath);
}

mysqli_close($conn);
