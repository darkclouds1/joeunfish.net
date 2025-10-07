<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
$pdo = $dbConn->pdo_conn();

mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = __DIR__ . "/logfile.log";

isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['start_date']) ? $start_date = $_GET['start_date'] : $start_date = "";
isset($_GET['end_date']) ? $end_date = $_GET['end_date'] : $end_date = "";
isset($_GET['search_keyword']) ? $search_keyword = $_GET['search_keyword'] : $search_keyword = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

if ($category === 'search') {
    try {
        $sql = "SELECT SYS.serial_no, 
                DATE_FORMAT(SYS.increate_dt, '%Y-%m-%d %H:%i') as increate_dt, 
                DATE_FORMAT(SYS.request_dt, '%Y-%m-%d %H:%i') as request_dt, 
                SYS.title, 
                SYS.content, 
                SYS.work_detail, 
                DATE_FORMAT(SYS.start_dt, '%Y-%m-%d %H:%i') as start_dt, 
                DATE_FORMAT(SYS.end_dt, '%Y-%m-%d %H:%i') as end_dt, 
                SYS.cost_type, DATE_FORMAT(SYS.modify_dt, '%Y-%m-%d %H:%i') as modify_dt, 
                COALESCE(wpu.display_name, '') as modify_name 
            FROM tsystem_mng AS SYS 
            LEFT JOIN wp_users wpu ON SYS.modify_id = wpu.user_login WHERE DATE(SYS.increate_dt) BETWEEN ? AND ?";

        $params = [$start_date, $end_date];
        if (!empty($search_keyword)) {
            $sql .= " AND (SYS.title LIKE ? OR SYS.content LIKE ? OR SYS.work_detail LIKE ?)";
            $searchParam = '%' . $search_keyword . '%';
            $params[] = $searchParam; $params[] = $searchParam; $params[] = $searchParam;
        }
        $sql .= " ORDER BY SYS.increate_dt DESC, SYS.serial_no DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

if ($category === 'save') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 디버깅 로그 추가
        error_log("=== SAVE DATA ===\n", 3, $logFilePath);
        error_log("Full received data: " . print_r($data, true) . "\n", 3, $logFilePath);
        error_log("title: " . ($data['title'] ?? 'NULL') . "\n", 3, $logFilePath);
        error_log("content: [" . ($data['content'] ?? 'NULL') . "]\n", 3, $logFilePath);
        error_log("work_detail: [" . ($data['work_detail'] ?? 'NULL') . "]\n", 3, $logFilePath);
        
        $sql = "INSERT INTO tsystem_mng 
                (`increate_dt`, `request_dt`, `title`, `content`, `work_detail`, `start_dt`, `end_dt`, `cost_type`, `create_id`, `modify_id`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $params = [
            $data['increate_dt'] ?? null, 
            $data['request_dt'] ?? null, 
            $data['title'] ?? null, 
            $data['content'] ?? null, 
            $data['work_detail'] ?? null, 
            $data['start_dt'] ?? null, 
            $data['end_dt'] ?? null, 
            $data['cost_type'] ?? null, 
            $user_id, 
            $user_id
        ];
        
        error_log("SQL params: " . print_r($params, true) . "\n", 3, $logFilePath);
        
        $stmt->execute($params);
        
        error_log("Data saved successfully\n", 3, $logFilePath);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Save error: " . $e->getMessage() . "\n", 3, $logFilePath);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

if ($category === 'update') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 디버깅 로그 추가
        error_log("=== UPDATE DATA ===\n", 3, $logFilePath);
        error_log("Full received data: " . print_r($data, true) . "\n", 3, $logFilePath);
        error_log("content: [" . ($data['content'] ?? 'NULL') . "]\n", 3, $logFilePath);
        error_log("work_detail: [" . ($data['work_detail'] ?? 'NULL') . "]\n", 3, $logFilePath);
        
        $sql = "UPDATE tsystem_mng 
                SET `increate_dt`=?, `request_dt`=?, `title`=?, `content`=?, `work_detail`=?, 
                    `start_dt`=?, `end_dt`=?, `cost_type`=?, `modify_id`=?, `modify_dt`=CURRENT_TIMESTAMP 
                WHERE `serial_no`=?";
                
        $stmt = $pdo->prepare($sql);
        
        $params = [
            $data['increate_dt'] ?? null, 
            $data['request_dt'] ?? null, 
            $data['title'] ?? null, 
            $data['content'] ?? null, 
            $data['work_detail'] ?? null, 
            $data['start_dt'] ?? null, 
            $data['end_dt'] ?? null, 
            $data['cost_type'] ?? null, 
            $user_id, 
            $data['serial_no']
        ];
        
        error_log("SQL params: " . print_r($params, true) . "\n", 3, $logFilePath);
        
        $stmt->execute($params);
        
        error_log("Data updated successfully\n", 3, $logFilePath);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Update error: " . $e->getMessage() . "\n", 3, $logFilePath);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

if ($category === 'delete') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $serialNos = $data['serial_nos'];
        $placeholders = implode(',', array_fill(0, count($serialNos), '?'));
        $sql = "DELETE FROM tsystem_mng WHERE serial_no IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($serialNos);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

$conn->close();
?>