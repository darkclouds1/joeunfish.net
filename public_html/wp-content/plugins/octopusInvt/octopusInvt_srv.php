<?PHP
/* 
문어 재고관리 조회/저장/삭제
- toctopus_invt: 일자별 문어 재고 테이블
- tpurchase: 매입 정보 테이블
*/

// 파일 최상단에 추가
header('Content-Type: application/json; charset=UTF-8');

session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn   = new dbconnClassFish;
$conn     = $dbConn->conn();

$pdo      = $dbConn->pdo_conn();
$pdo->exec("SET NAMES utf8mb4");

mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

// 조회 파라미터    
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['increate_dt']) ? $increate_dt = $_GET['increate_dt'] : $increate_dt = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

// 로그 출력
error_log("[octopusInvt_srv.php] category: " . $category, 3, $logFilePath);
error_log("[octopusInvt_srv.php] user_id: " . $user_id, 3, $logFilePath);
error_log("[octopusInvt_srv.php] increate_dt: " . $increate_dt, 3, $logFilePath);

// category=1s: 문어 재고 조회
if ($category === '1s') {
    $sql = "
    WITH LatestInventoryDates AS (
        SELECT
            serial_no,
            MAX(input_dt) AS max_input_dt
        FROM
            toctopus_invt
        WHERE
            input_dt <= '$increate_dt'
        GROUP BY
            serial_no
    ),
    LatestInventoryData AS (
        SELECT
            invt.*
        FROM
            toctopus_invt invt
        INNER JOIN
            LatestInventoryDates lid ON invt.serial_no = lid.serial_no AND invt.input_dt = lid.max_input_dt
    )
    SELECT
        p.serial_no,
        p.vendor_nm,
        CONCAT(
            DATE_FORMAT(p.input_dt, '%c/%e'),
            '(',
            CASE DAYOFWEEK(p.input_dt)
                WHEN 1 THEN '일' WHEN 2 THEN '월' WHEN 3 THEN '화' WHEN 4 THEN '수'
                WHEN 5 THEN '목' WHEN 6 THEN '금' WHEN 7 THEN '토'
            END,
            ')'
        ) AS formatted_input_dt,
        '$increate_dt' AS input_dt,
        COALESCE(inv.stock_large, 0) AS stock_large,
        COALESCE(inv.stock_4mi, 0) AS stock_4mi,
        COALESCE(inv.stock_5mi, 0) AS stock_5mi,
        COALESCE(inv.stock_6mi, 0) AS stock_6mi,
        COALESCE(inv.stock_7mi, 0) AS stock_7mi,
        COALESCE(inv.stock_8mi, 0) AS stock_8mi,
        COALESCE(inv.stock_9mi, 0) AS stock_9mi,
        COALESCE(inv.stock_10_15mi, 0) AS stock_10_15mi,
        COALESCE(inv.stock_16_20mi, 0) AS stock_16_20mi,
        COALESCE(inv.stock_21_25mi, 0) AS stock_21_25mi,
        COALESCE(inv.stock_26_30mi, 0) AS stock_26_30mi,
        inv.warehouse_nm,
        inv.remark
    FROM
        tpurchase p
    LEFT JOIN
        LatestInventoryData inv ON p.serial_no = inv.serial_no
    WHERE
        p.mat_cd = 'M003'
        AND p.qty_cur <> 0
    ORDER BY
        p.input_dt ASC;
    ";


    error_log("[octopusInvt_srv.php] SQL: " . preg_replace('/\s+/', ' ', $sql), 3, $logFilePath);

    $result = $conn->query($sql);
    if (!$result) {
        $error_message = "쿼리 실행 중 오류 발생: " . $conn->error;
        error_log($error_message, 3, $logFilePath);
        echo json_encode(array("error" => $error_message));
        $conn->close();
        return;
    }

    $json_string = $dbConn->rs2Json1(
        $result,
        '00'
    );
    echo $json_string;
    $conn->close();
    return;
}

// 재고 저장
if ($category === 'saveInvt') {
    $input = json_decode(file_get_contents('php://input'), true);
    $inventoryList = $input['inventoryList']; // 클라이언트에서 'inventoryList'로 데이터를 보낸다고 가정

    error_log("[octopusInvt_srv.php] inventoryList: " . print_r($inventoryList, true), 3, $logFilePath);
    
    $inventory_date = $increate_dt;

    $results = array();
    $success = true;

    try {
        $pdo->beginTransaction();

        // 해당 날짜의 기존 데이터 삭제
        $stmt = $pdo->prepare("DELETE FROM toctopus_invt WHERE input_dt = :input_dt");
        $stmt->bindParam(':input_dt', $inventory_date, PDO::PARAM_STR);
        $stmt->execute();           

        // Insert 구문 준비
        $stmt = $pdo->prepare(
            "INSERT INTO toctopus_invt 
            (serial_no, vendor_nm, input_dt, stock_large, stock_4mi, stock_5mi, stock_6mi, stock_7mi, stock_8mi, stock_9mi, stock_10_15mi, stock_16_20mi, stock_21_25mi, stock_26_30mi, warehouse_nm, remark) 
            VALUES 
            (:serial_no, :vendor_nm, :input_dt, :stock_large, :stock_4mi, :stock_5mi, :stock_6mi, :stock_7mi, :stock_8mi, :stock_9mi, :stock_10_15mi, :stock_16_20mi, :stock_21_25mi, :stock_26_30mi, :warehouse_nm, :remark)"
        );
        
        foreach ($inventoryList as $item) {
            // 숫자 필드 정리 (콤마, 따옴표 제거)
            $stock_fields = ['stock_large', 'stock_4mi', 'stock_5mi', 'stock_6mi', 'stock_7mi', 'stock_8mi', 'stock_9mi', 'stock_10_15mi', 'stock_16_20mi', 'stock_21_25mi', 'stock_26_30mi'];
            foreach ($stock_fields as $field) {
                if (isset($item[$field]) && $item[$field] !== '') {
                    $item[$field] = (int) str_replace([',', '"'], '', $item[$field]);
                } else {
                    $item[$field] = 0; // 값이 없으면 0으로 설정
                }
            }

            // 값 바인딩
            $stmt->bindValue(':serial_no', $item['serial_no'], PDO::PARAM_INT);
            $stmt->bindValue(':vendor_nm', $item['vendor_nm'], PDO::PARAM_STR);
            $stmt->bindValue(':input_dt', $inventory_date, PDO::PARAM_STR);
            $stmt->bindValue(':stock_large', $item['stock_large'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_4mi', $item['stock_4mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_5mi', $item['stock_5mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_6mi', $item['stock_6mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_7mi', $item['stock_7mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_8mi', $item['stock_8mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_9mi', $item['stock_9mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_10_15mi', $item['stock_10_15mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_16_20mi', $item['stock_16_20mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_21_25mi', $item['stock_21_25mi'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_26_30mi', $item['stock_26_30mi'], PDO::PARAM_INT);
            $stmt->bindValue(':warehouse_nm', $item['warehouse_nm'], PDO::PARAM_STR);
            $stmt->bindValue(':remark', $item['remark'], PDO::PARAM_STR);

            if (!$stmt->execute()) {
                throw new Exception("쿼리 실행 오류: " . implode(", ", $stmt->errorInfo()));
            }
        }
        
        $pdo->commit();
        $results[] = ['success' => true];

    } catch (Exception $e) {
        $pdo->rollBack();
        $success = false;
        error_log("트랜잭션 실패: " . $e->getMessage(), 3, $logFilePath);
        $results[] = ['success' => false, 'error' => $e->getMessage()];
    } finally {
        $stmt = null;
        $pdo = null;
    }

    echo json_encode(['success' => $success, 'results' => $results]);
    exit();
}

// 삭제 로직
if ($category === 'delete' && !empty($increate_dt)) {
    error_log("[octopusInvt_srv.php] delete increate_dt: " . $increate_dt, 3, $logFilePath);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM toctopus_invt WHERE input_dt = :input_dt");
        $stmt->bindParam(':input_dt', $increate_dt, PDO::PARAM_STR);
        
        $stmt->execute();

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "재고일자 {$increate_dt}의 데이터가 성공적으로 삭제되었습니다."]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => "삭제 중 오류가 발생했습니다: " . $e->getMessage()]);
    }
    exit();
}
