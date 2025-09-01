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
// error_log("[octopusInvt_srv.php] category: " . $category, 3, $logFilePath);
// error_log("[octopusInvt_srv.php] user_id: " . $user_id, 3, $logFilePath);
// error_log("[octopusInvt_srv.php] increate_dt: " . $increate_dt, 3, $logFilePath);

// category=1s: 문어 재고 조회
if ($category === '1s') {
    // 해당 날짜에 저장된 데이터가 있는지 확인
    $checkSql = "SELECT 1 FROM toctopus_invt WHERE input_dt = '$increate_dt' LIMIT 1";
    $checkResult = $conn->query($checkSql);
    $dataExists = $checkResult && $checkResult->num_rows > 0;

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
    ),
    MainData AS (
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
            inv.stock_large,
            inv.stock_4mi,
            inv.stock_5mi,
            inv.stock_6mi,
            inv.stock_7mi,
            inv.stock_8mi,
            inv.stock_9mi,
            inv.stock_10_15mi,
            inv.stock_16_20mi,
            inv.stock_21_25mi,
            inv.stock_26_30mi,
            inv.warehouse_nm,
            (
                COALESCE(inv.stock_large, 0) + COALESCE(inv.stock_4mi, 0) + COALESCE(inv.stock_5mi, 0) +
                COALESCE(inv.stock_6mi, 0) + COALESCE(inv.stock_7mi, 0) + COALESCE(inv.stock_8mi, 0) +
                COALESCE(inv.stock_9mi, 0) + COALESCE(inv.stock_10_15mi, 0) + COALESCE(inv.stock_16_20mi, 0) +
                COALESCE(inv.stock_21_25mi, 0) + COALESCE(inv.stock_26_30mi, 0)
            ) AS stock_sum,
            inv.remark,
            0 AS is_summary,
            p.input_dt AS order_dt
        FROM
            tpurchase p
        LEFT JOIN
            LatestInventoryData inv ON p.serial_no = inv.serial_no
        WHERE
            p.mat_cd = 'M003'
            AND p.qty_cur <> 0
    )
    SELECT * FROM MainData
    ";

    if ($dataExists) {
        $sql .= "
        UNION ALL
        SELECT
            'SUM' AS serial_no,
            '합계' AS vendor_nm,
            '' AS formatted_input_dt,
            '$increate_dt' AS input_dt,
            SUM(stock_large) AS stock_large,
            SUM(stock_4mi) AS stock_4mi,
            SUM(stock_5mi) AS stock_5mi,
            SUM(stock_6mi) AS stock_6mi,
            SUM(stock_7mi) AS stock_7mi,
            SUM(stock_8mi) AS stock_8mi,
            SUM(stock_9mi) AS stock_9mi,
            SUM(stock_10_15mi) AS stock_10_15mi,
            SUM(stock_16_20mi) AS stock_16_20mi,
            SUM(stock_21_25mi) AS stock_21_25mi,
            SUM(stock_26_30mi) AS stock_26_30mi,
            '' AS warehouse_nm,
            SUM(stock_sum) AS stock_sum,
            '' AS remark,
            1 AS is_summary,
            NULL AS order_dt
        FROM MainData
        ";
    }

    $sql .= "
    ORDER BY
        is_summary ASC,
        order_dt ASC,
        vendor_nm ASC;
    ";

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

    // error_log("[octopusInvt_srv.php] inventoryList: " . print_r($inventoryList, true), 3, $logFilePath);
    
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
            // 'SUM' 행이 혹시라도 들어오면 건너뛰도록 방어 코드 추가
            if (isset($item['serial_no']) && $item['serial_no'] === 'SUM') {
                continue;
            }

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

// category=frozen: 냉동 재고 조회
if ($category === 'frozen_s') {
    $mat_cd = isset($_GET['mat_cd']) ? $_GET['mat_cd'] : '';

    // error_log("[octopusInvt_srv.php] frozen mat_cd: " . $mat_cd, 3, $logFilePath);

    $sql = "
        SELECT
            p.vendor_nm,
            DATE_FORMAT(p.input_dt, '%y-%m-%d') AS input_dt_formatted,
            m.mat_nm,
            p.mat_type,
            p.lot_nm,
            p.box as box,
            p.qty_cur as qty_cur,
            COALESCE(pi_sum.total_qty_incur, 0) AS stock_qty,
            (p.qty_cur + COALESCE(pi_sum.total_qty_incur, 0)) AS total_qty,
            p.remarks AS warehouse_nm
        FROM
            tpurchase p
        JOIN
            tdayprodmat_mst m ON p.mat_cd = m.mat_cd
        LEFT JOIN
            (SELECT tpurchase_serial_no, SUM(qty_incur) AS total_qty_incur
             FROM tpurchase_in
             GROUP BY tpurchase_serial_no) AS pi_sum ON p.serial_no = pi_sum.tpurchase_serial_no
        WHERE
            m.temp_code = 'F'
            AND (p.qty_cur + COALESCE(pi_sum.total_qty_incur, 0)) <> 0
    ";

    $params = [];
    if (isset($mat_cd) && $mat_cd !== '' && $mat_cd !== 'all') {
        $sql .= " AND p.mat_cd = ? ";
        $params[] = $mat_cd;
    }

    $sql .= "
        GROUP BY p.serial_no, p.vendor_nm, p.input_dt, m.mat_nm, p.mat_type, p.lot_nm, p.box, p.qty_cur, stock_qty, p.remarks
        ORDER BY
            m.mat_nm, p.input_dt DESC, p.vendor_nm
    ";


    // error_log("[octopusInvt_srv.php] SQL: " . $sql, 3, $logFilePath);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($resultData) > 0) {
            $totalBox = 0;
            $totalQtyCur = 0;
            $totalStockQty = 0;
            $totalTotalQty = 0;

            foreach ($resultData as $row) {
                $totalBox += (float)$row['box'];
                $totalQtyCur += (float)$row['qty_cur'];
                $totalStockQty += (float)$row['stock_qty'];
                $totalTotalQty += (float)$row['total_qty'];
            }

            $totalRow = [
                'vendor_nm' => '합계',
                'input_dt_formatted' => '',
                'mat_nm' => '',
                'mat_type' => '',
                'lot_nm' => '',
                'box' => $totalBox,
                'qty_cur' => $totalQtyCur,
                'stock_qty' => $totalStockQty,
                'total_qty' => $totalTotalQty,
                'warehouse_nm' => ''
            ];
            $resultData[] = $totalRow;
        }

        $response = [
            'response' => [
                'header' => ['resultCode' => '00', 'resultMsg' => 'OK'],
                'body' => [
                    'items' => $resultData,
                    'totalCount' => count($resultData)
                ]
            ]
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        error_log("Frozen inventory query failed: " . $e->getMessage(), 3, $logFilePath);
        $response = [
            'response' => [
                'header' => ['resultCode' => '99', 'resultMsg' => 'Error: ' . $e->getMessage()],
                'body' => ['items' => [], 'totalCount' => 0]
            ]
        ];
        echo json_encode($response);
    }

    $pdo = null;
    exit();
}


// 삭제 로직
if ($category === 'delete' && !empty($increate_dt)) {
    // error_log("[octopusInvt_srv.php] delete increate_dt: " . $increate_dt, 3, $logFilePath);

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
