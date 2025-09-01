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

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
// 파라미터
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['due_date']) ? $due_date = $_GET['due_date'] : $due_date = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

// 할일 조회
if ($category === 'search'
) { 
    try {

        $sql =
            "  SELECT TAS.*, 
                COALESCE(wpu.display_name, '') as modify_name
                FROM tasks AS TAS
                LEFT JOIN wp_users wpu
                ON CONVERT(TAS.create_id USING utf8mb4) = CONVERT(wpu.user_login USING utf8mb4)
                WHERE TAS.delete_yn = 'n'
                ORDER BY 
                TAS.serial_no DESC,
                TAS.finish_yn ASC,      -- 미완료('n')가 먼저, 완료('y')가 나중에
                CASE 
                    WHEN TAS.finish_yn = 'n' THEN TAS.due_date 
                    ELSE NULL 
                END DESC,               -- 미완료 항목만 날짜순 정렬
                TAS.modify_dt DESC     -- 수정일시 내림차순
            ";


        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

if ($category === 'taskCount') {
    try {
        $sql = "SELECT COUNT(*) as task_count
                FROM tasks
                WHERE delete_yn = 'n' AND finish_yn = 'n'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        // 디버깅을 위해 JSON 출력 전에 확인
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data['task_count']
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 할일 저장
if ($category === 'save') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("\nln80:data=" . json_encode($data), 3, $logFilePath);
        // 로그인 확인
        if ($user_id === '' || $user_id === null) {
            echo json_encode([
                'success' => false,
                'error' => '로그인 후 작성해주세요.'
            ]);
            exit;
        }

        $sql =
        "REPLACE INTO tasks (
            title, content, due_date, create_id, create_dt, modify_id
        ) VALUES ( ?, ?, ?, ?, CURRENT_TIMESTAMP, ? )";

        error_log("\nln106:sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['due_date'],
            $user_id,
            $user_id
        ]);

        // 방금 저장된 할일의 serial_no 가져오기
        $serial_no = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'serial_no' => $serial_no]);

        // 오늘 날짜 이전이고 완료 안된 할일 완료로 변경
        $sql = "UPDATE tasks 
                SET finish_yn = 'y'
                WHERE due_date < NOW()
                AND finish_yn = 'n';";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();



    } catch (Exception $e) {
        error_log("\nln120:error=" . $e->getMessage(), 3, $logFilePath);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 할일 수정
// tasks_srv.php 파일의 update 부분 수정
if ($category === 'update') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        // 권한 체크
        if (!checkTaskPermission($pdo, $data['serial_no'], $user_id)) {
            echo json_encode([
                'success' => false,
                'error' => '본인이 작성한 할일만 수정할 수 있습니다.'
            ]);
            exit;
        }

        $sql = "UPDATE tasks SET 
                title = ?,
                content = ?,
                due_date = ?,
                finish_yn = ?,
                modify_id = ?,
                modify_dt = CURRENT_TIMESTAMP
                WHERE serial_no = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['due_date'],
            $data['finish_yn'],
            $user_id,
            $data['serial_no']
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 할일 완료 업데이트
if ($category === 'updatefinish') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        // 권한 체크
        if (!checkTaskPermission($pdo, $data['serial_no'], $user_id)) {
            echo json_encode([
                'success' => false,
                'error' => '본인이 작성한 할일만 상태를 변경할 수 있습니다.'
            ]);
            exit;
        }

        $sql = "UPDATE tasks SET finish_yn = ? WHERE serial_no = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['finish_yn'], $data['serial_no']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

if ($category === 'delete') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        // 권한 체크
        if (!checkTaskPermission($pdo, $data['serial_no'], $user_id)) {
            echo json_encode([
                'success' => false,
                'error' => '본인이 작성한 할일만 삭제할 수 있습니다.'
            ]);
            exit;
        }

        $sql = "UPDATE tasks SET 
                delete_yn = 'y',
                modify_id = ?,
                modify_dt = CURRENT_TIMESTAMP
                WHERE serial_no = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $data['serial_no']]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}


// 사용자 권한 체크 함수 추가
function checkTaskPermission($pdo, $serial_no, $user_id)
{
    global $logFilePath;

    // 먼저 사용자의 역할 확인
    $sql = "SELECT u.ID, um.meta_value as user_role 
           FROM wp_users u
           JOIN wp_usermeta um ON u.ID = um.user_id 
           WHERE u.user_login = ? 
           AND um.meta_key = 'wp_capabilities'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // 관리자나 편집자인 경우 무조건 권한 부여
    if ($userInfo && $userInfo['user_role']) {
        $capabilities = unserialize($userInfo['user_role']);
        if (isset($capabilities['administrator']) || isset($capabilities['editor'])) {
            error_log("ln245::관리자/편집자 권한 확인", 3, $logFilePath);
            return true;
        }
    }

    // 일반 사용자는 본인 작성글만 수정 가능
    $sql = "SELECT create_id FROM tasks WHERE serial_no = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$serial_no]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("create_id=" . $task['create_id'] . ", user_id=" . $user_id, 3, $logFilePath);
    error_log("ln192:checkTaskPermission=" . ($task && $task['create_id'] === $user_id), 3, $logFilePath);

    return $task && $task['create_id'] === $user_id;
}

$conn->close();
