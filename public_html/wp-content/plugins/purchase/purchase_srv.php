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
// error_log("\n[ln15] logFilePath=" . $logFilePath, 3, $logFilePath);        

// 파라미터
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['start_date']) ? $start_date = $_GET['start_date'] : $start_date = "";
isset($_GET['end_date']) ? $end_date = $_GET['end_date'] : $end_date = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

// 업체매입 조회
if ($category === 'search') {
    // error_log("[ln25::search] start_date=" . $start_date . ", end_date=" . $end_date, 3, $logFilePath);

    try {
        $sql =
            "SELECT T.serial_no AS serial_no, 
                       T.input_dt AS input_dt, 
                       T.vendor_nm AS vendor_nm, 
                       T.box AS box, 
                       T.weight AS weight, 
                       T.lot_nm AS lot_nm, 
                       mat.mat_nm AS mat_nm, 
                       T.mat_type AS mat_type,
                       T.remarks AS remarks, 
                       T.qty_cur AS qty_cur,
                       T.unitPrice AS unitPrice,
                       COALESCE(SUM(TIN.qty_in), 0) AS qty_in,
                       T.create_dt AS create_dt,  
                       wpu.display_name AS modify_name,
                       T.mat_cd AS mat_cd
                FROM tpurchase AS T
                LEFT JOIN (
                    SELECT DISTINCT mat_cd, mat_nm 
                    FROM tdayprodmat_mst 
                    WHERE use_yn = 'Y'
                    ) AS mat ON T.mat_cd = mat.mat_cd
                LEFT JOIN tpurchase_in AS TIN
                    ON T.serial_no = TIN.tpurchase_serial_no
                LEFT JOIN wp_users wpu
                    ON T.modify_id = wpu.user_login
                WHERE DATE(T.input_dt) BETWEEN ? AND ? 
                GROUP BY T.serial_no, T.input_dt, T.vendor_nm, T.box, T.weight, 
                         T.lot_nm, mat.mat_nm, T.mat_type, T.remarks, T.qty_cur, T.create_dt, wpu.display_name, T.mat_cd
                ORDER BY CASE WHEN T.qty_cur = 0 THEN 1 ELSE 0 END, T.serial_no DESC, T.qty_cur DESC";
        // error_log("[ln52] sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
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

// 자재코드 조회
if ($category === 'getMatCd') {
    $sql = "SELECT DISTINCT mat_cd, mat_nm FROM tdayprodmat_mst WHERE use_yn = 'Y' ORDER BY mat_cd";
    $result = $conn->query($sql);

    $materials = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $materials[] = array(
                'mat_cd' => $row['mat_cd'],
                'mat_nm' => $row['mat_nm']
            );
        }
    }

    $response = array(
        'status' => 'success',
        'data' => $materials
    );

    echo json_encode($response);
    exit;
}

// 판매점 조회
if ($category === 'getStoreCd') {
    $sql = "SELECT DISTINCT store_code, country_origin 
              FROM tcomcode 
             WHERE use_yn = 'Y' 
               AND store_code like '00%'
             ORDER BY store_code";
    $result = $conn->query($sql);

    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = array(
                'code' => $row['store_code'],
                'codeNm' => $row['country_origin']
            );
        }
    }

    $response = array(
        'status' => 'success',
        'data' => $data
    );

    echo json_encode($response);
    exit;
}

// -----------------
// 수입통관 조회
// -----------------
if ($category === 'search-imp') {
    try {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY IMP.import_dt DESC) as row_num,
                IMP.*,
                COALESCE(wpu.display_name, '') as modify_name
                FROM tpurchase_imp AS IMP
                LEFT JOIN wp_users wpu
                    ON IMP.modify_id = wpu.user_login 
                WHERE IMP.import_dt BETWEEN ? AND ?
                ORDER BY IMP.import_dt DESC, IMP.awb ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
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

// 업체매입 저장
if ($category === 'save') {
    try {

        $data = json_decode(file_get_contents('php://input'), true);

        $sql = "SELECT unit FROM tdayprodmat_mst WHERE mat_cd = :mat_cd LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':mat_cd', $data['mat_cd'], PDO::PARAM_STR); // 혹은 PDO::PARAM_INT (자료형에 따라 변경)
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unit = $result['unit'];

        // Unit 값이 'Box' or 'EA'이면 box 값을, 아니면 weight 값을 사용
        $qty_cur = (strtoupper($unit) == 'BOX' || strtoupper($unit) == 'EA') ? $data['box'] : $data['weight'];

        $sql = "INSERT INTO tpurchase (input_dt, mat_cd, mat_type, vendor_nm, box, weight, qty_cur, remarks, unitPrice, create_id, create_dt, modify_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['input_dt'],
            $data['mat_cd'],
            $data['mat_type'],
            $data['vendor_nm'],
            $data['box'],
            $data['weight'],
            $qty_cur,
            $data['remarks'],
            $data['unitPrice'],
            $user_id,
            $user_id
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 수입통관 저장
if ($category === 'save-imp') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        // error_log("[ln52] data=" . json_encode($data), 3, $logFilePath);

        $sql = "INSERT INTO tpurchase_imp (
                    import_dt, spec, vol, weight, airline, awb, 
                    arrival_dt, prod_date, remarks, create_id, create_dt, modify_id
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, CURRENT_TIMESTAMP, ?
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['import_dt'],
            $data['spec'],
            $data['vol'],
            floatval($data['weight']),
            $data['airline'],
            $data['awb'],
            $data['arrival_dt'],
            $data['prod_date'],
            $data['remarks'],
            $user_id,
            $user_id
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 업체매입 수정
if ($category === 'update') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $serial_no = $data['serial_no'];
        $mat_cd = $data['mat_cd'];

        // 데이터베이스에서 serial_no로 mat_cd 조회
        $sql = "SELECT lotin_id FROM tpurchase_in WHERE tpurchase_serial_no = :serial_no LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':serial_no', $serial_no, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // 입고 데이터가 있는 경우, 자재, 박스, 중량 변경 시도 확인
            $sql_check = "SELECT mat_cd, box, weight FROM tpurchase WHERE serial_no = :serial_no";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->bindParam(':serial_no', $serial_no, PDO::PARAM_INT);
            $stmt_check->execute();
            $original_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (
                $original_data &&
                ($original_data['mat_cd'] != $data['mat_cd'] ||
                    $original_data['box'] != $data['box'] ||
                    $original_data['weight'] != $data['weight'])
            ) {
                echo json_encode([
                    'success' => false,
                    'error' => '이동 수량이 있으면 수정이 되지 않습니다.'
                ]);
                exit;
            }
        }

        // Unit 값 조회
        $sql = "SELECT unit FROM tdayprodmat_mst WHERE mat_cd = :mat_cd LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':mat_cd', $mat_cd, PDO::PARAM_STR);
        $stmt->execute();
        $unitResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $unit = $unitResult['unit'];

        // Unit 값에 따라 qty_cur 설정
        $qty_cur = ($unit === 'Box' || $unit === 'EA') ? $data['box'] : $data['weight'];

        $sql = "UPDATE tpurchase SET 
            input_dt = ?,
            vendor_nm = ?, 
            mat_type = ?,
            remarks = ?,
            unitPrice = ?,
            modify_id = ?,
            modify_dt = CURRENT_TIMESTAMP
            WHERE serial_no = ?";

        $params = [
            $data['input_dt'],
            $data['vendor_nm'],
            $data['mat_type'],
            $data['remarks'],
            $data['unitPrice'],
            $user_id,
            $data['serial_no']
        ];

        if (!$result) {
            $sql = "UPDATE tpurchase SET 
                input_dt = ?,
                mat_cd = ?,
                mat_type = ?,
                vendor_nm = ?, 
                box = ?,
                weight = ?,
                qty_cur = ?, 
                remarks = ?,
                unitPrice = ?,
                modify_id = ?,
                modify_dt = CURRENT_TIMESTAMP
                WHERE serial_no = ?";

            $params = [
                $data['input_dt'],
                $data['mat_cd'],
                $data['mat_type'],
                $data['vendor_nm'],
                $data['box'],
                $data['weight'],
                $qty_cur,
                $data['remarks'],
                $data['unitPrice'],
                $user_id,
                $data['serial_no']
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => '수정되었습니다.'
        ]);



    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 수입통관 수정
if ($category === 'update-imp') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $sql = "UPDATE tpurchase_imp SET 
                import_dt = ?,
                spec = ?, 
                vol = ?,
                weight = ?,
                airline = ?,
                awb = ?,
                arrival_dt = ?,
                prod_date = ?,
                remarks = ?,
                modify_id = ?,
                modify_dt = CURRENT_TIMESTAMP
                WHERE serial_no = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['import_dt'],
            $data['spec'],
            $data['vol'],
            floatval($data['weight']),
            $data['airline'],
            $data['awb'],
            $data['arrival_dt'],
            $data['prod_date'],
            $data['remarks'],
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

// 업체매입 삭제
if ($category === 'delete') {
    try {
        $pdo->beginTransaction();

        $serial_nos = json_decode(file_get_contents('php://input'), true)['serial_nos'];
        $placeholders = str_repeat('?,', count($serial_nos) - 1) . '?';

        // 1단계: 삭제할 `tpurchase`에 연결된 `tpurchase_in`의 `lotin_id`를 모두 찾습니다.
        $sql_find_lotin = "SELECT lotin_id FROM tpurchase_in WHERE tpurchase_serial_no IN ($placeholders)";
        $stmt_find_lotin = $pdo->prepare($sql_find_lotin);
        $stmt_find_lotin->execute($serial_nos);
        $lotin_ids = $stmt_find_lotin->fetchAll(PDO::FETCH_COLUMN);

        // 2단계: `tpurchase_in` 데이터가 존재하면, 관련된 하위 데이터를 먼저 삭제합니다.
        if (!empty($lotin_ids)) {
            $lotin_placeholders = str_repeat('?,', count($lotin_ids) - 1) . '?';

            // 2a단계: `tdayprod_lot` 테이블에서 관련된 손자 레코드를 삭제합니다.
            $sql_delete_lot = "DELETE FROM tdayprod_lot WHERE tpurchase_in_lotin_id IN ($lotin_placeholders)";
            $stmt_delete_lot = $pdo->prepare($sql_delete_lot);
            $stmt_delete_lot->execute($lotin_ids);

            // 2b단계: `tpurchase_in` 테이블에서 관련된 자식 레코드를 삭제합니다. (lotin_id를 직접 사용)
            $sql_delete_in = "DELETE FROM tpurchase_in WHERE lotin_id IN ($lotin_placeholders)";
            $stmt_delete_in = $pdo->prepare($sql_delete_in);
            $stmt_delete_in->execute($lotin_ids);
        }

        // 3단계: 마지막으로 `tpurchase` 테이블에서 최상위 레코드를 삭제합니다.
        $sql_delete_purchase = "DELETE FROM tpurchase WHERE serial_no IN ($placeholders)";
        $stmt_delete_purchase = $pdo->prepare($sql_delete_purchase);
        $stmt_delete_purchase->execute($serial_nos);

        $pdo->commit();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();

        // 외래 키 제약 조건 위반 (1451)
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Integrity constraint violation: 1451') !== false) {
            echo json_encode([
                'success' => false,
                'error' => "삭제하려는 데이터에 연결된 생산 데이터가 있어 삭제할 수 없습니다."
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => "삭제 실패: " . $e->getMessage()
            ]);
        }
    }
}

// 수입통관 삭제
if ($category === 'delete-imp') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $serialNos = $data['serial_nos'];

        // 시리얼 번호 배열을 사용하여 삭제
        $placeholders = implode(',', array_fill(0, count($serialNos), '?'));
        $sql = "DELETE FROM tpurchase_imp WHERE serial_no IN ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($serialNos);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 입고 저장::입고량은 매입 잔량보다 작거나 같아야 함
if ($category === 'insert-in') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $tpurchase_serial_no = $data['tpurchase_serial_no'];
        $qty_in = $data['qty_in'];
        $create_id = $data['create_id'];

        // tpurchase 테이블에서 현재 qty_cur 조회
        $sql = "SELECT qty_cur FROM tpurchase WHERE serial_no = :serial_no";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':serial_no', $tpurchase_serial_no, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $qty_cur = $result['qty_cur'];

            // 입고량이 현재 잔량보다 작거나 같은지 확인
            if ($qty_in <= $qty_cur) {
                // 입고잔량은 입고량과 동일하게 초기화
                $qty_incur = $qty_in;

                // tpurchase_in 테이블에 데이터 삽입
                $sql = "INSERT INTO tpurchase_in (
                            tpurchase_serial_no, 
                            increate_dt,
                            qty_in, 
                            qty_incur, 
                            create_id
                        ) VALUES (
                            :tpurchase_serial_no, 
                            :increate_dt,
                            :qty_in, 
                            :qty_incur, 
                            :create_id
                        )";

                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':tpurchase_serial_no', $tpurchase_serial_no, PDO::PARAM_INT);
                $stmt->bindParam(':increate_dt', date('Y-m-d H:i:s'), PDO::PARAM_STR);
                $stmt->bindParam(':qty_in', $qty_in, PDO::PARAM_STR);
                $stmt->bindParam(':qty_incur', $qty_incur, PDO::PARAM_STR);
                $stmt->bindParam(':create_id', $create_id, PDO::PARAM_STR);
                $stmt->execute();

                echo json_encode(['success' => true]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => '입고량이 현재 잔량보다 많습니다.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => '해당 시리얼 번호를 찾을 수 없습니다.'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 자재입고 조회
if ($category === 'search-in') {
    try {
        // 자재입고 조회
        $sql =
            "SELECT 
                    TIN.lotin_id AS lotin_id,
                    TIN.tpurchase_serial_no AS tpurchase_serial_no,
                    TIN.lotin_nm AS lotin_nm,
                    T.weight AS weight,
                    mat.mat_nm AS mat_nm,
                    TIN.qty_in AS qty_in,       -- 이동량
                    TIN.qty_incur AS qty_incur, -- 재고
                    TIN.increate_dt AS increate_dt, -- 입고일
                    wpu.display_name AS create_name, -- 입고자
                    T.remarks AS remarks, -- 비고
                    T.qty_cur AS qty_cur, -- 창고재고
                    mat.mat_cd AS mat_cd
                FROM tpurchase_in AS TIN
                INNER JOIN tpurchase AS T
                    ON T.serial_no = TIN.tpurchase_serial_no
                INNER JOIN (
                    SELECT DISTINCT mat_cd, mat_nm 
                    FROM tdayprodmat_mst 
                    WHERE use_yn = 'Y'
                ) AS mat 
                    ON T.mat_cd = mat.mat_cd
                LEFT JOIN wp_users wpu
                    ON TIN.create_id = wpu.user_login
                WHERE DATE(TIN.increate_dt) BETWEEN ? AND ? 
                ORDER BY CASE WHEN TIN.qty_incur = 0 THEN 1 ELSE 0 END, TIN.lotin_id DESC, TIN.qty_incur DESC
                ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
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

// 업체매입 삭제
if ($category === 'delete-in') {
    try {
        $pdo->beginTransaction();
        $lotin_ids = json_decode(file_get_contents('php://input'), true)['lotin_ids'];

        $placeholders = str_repeat('?,', count($lotin_ids) - 1) . '?';

        // 1. tdayprod_lot에서 관련 데이터를 먼저 삭제합니다.
        $sql_delete_lot = "DELETE FROM tdayprod_lot WHERE tpurchase_in_lotin_id IN ($placeholders)";
        $stmt_delete_lot = $pdo->prepare($sql_delete_lot);
        $stmt_delete_lot->execute($lotin_ids);

        // 2. tpurchase_in에서 데이터를 삭제합니다.
        $sql = "DELETE FROM tpurchase_in WHERE lotin_id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($lotin_ids);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 자재입고 수정
if ($category === 'update-in') {
    try {

        $pdo->beginTransaction();
        $data = json_decode(file_get_contents('php://input'), true);

        // error_log("\n[ln526]lotin_id=" . $lotin_id, 3, $logFilePath);
        // error_log("\n[ln527]qty_in=" . $qty_in, 3, $logFilePath);

        $sql = "UPDATE tpurchase_in SET 
            increate_dt = ?,
            qty_in = ?,
            qty_incur = ?,
            create_id = ?,
            create_dt = CURRENT_TIMESTAMP
            WHERE lotin_id = ? 
              AND tpurchase_serial_no = ?";

        $params = [
            $data['increate_dt'],
            $data['new_qty_in'],
            $data['new_qty_in'],
            $user_id,
            $data['lotin_id'],
            $data['serial_no']
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $result ? '재료데이터가 있으면, 이동량은 수정하실 수 없습니다.' : '수정되었습니다.'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();

        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}


// 엑셀 업로드::통관매입 
if ($category === 'upload-imp') {
    try {
        // 요청 데이터 파싱
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['importData'])) {
            throw new Exception("입력 데이터가 올바르지 않습니다.");
        }
        
        $importData = $input['importData'];

        // 트랜잭션 시작
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO tpurchase_imp 
                                (import_dt, spec, vol, airline, awb, prod_date, create_id, modify_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $insertCount = 0;
        foreach ($importData as $index => $row) {
            $result = $stmt->execute([
                $row['import_dt'],
                $row['spec'],
                $row['vol'],
                $row['airline'],
                $row['awb'],
                // $row['arrival_dt'],
                $row['prod_date'],
                $user_id,
                $user_id
            ]);
            
            if ($result) {
                $insertCount++;
            } else {
                error_log("[upload-imp] 행 " . ($index + 1) . " 삽입 실패: " . json_encode($stmt->errorInfo()), 3, $logFilePath);
            }
        }

        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => '총 ' . $insertCount . '개 데이터가 성공적으로 저장되었습니다.',
            'inserted_count' => $insertCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("[upload-imp] 예외 발생: " . $e->getMessage(), 3, $logFilePath);

        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// ------------------
// 개인판매 조회
// ------------------
if ($category === 'search-out') {
    try {
        $sql =
            "SELECT 
                    TOUT.serial_no AS serial_no,            -- 일련번호
                    date(TOUT.increate_dt) AS increate_dt,  -- 판매일
                    TOUT.lc_no AS lc_no,                    -- 통관번호
                    TOUT.origin_store_code AS origin_store_code, -- 출고점코드
                    TOUT.store_code AS store_code,          -- 판매점코드
                    TOUT.store_no   AS store_no,            -- 판매점명(수기입력)
                    TOUT.weight     AS weight,              -- 중량(판매량)
                    TOUT.prod_code  AS prod_code,           -- 상품코드
                    TOUT.prod_nm    AS prod_nm,             -- 상품명
                    TOUT.invoice_yn AS invoice_yn,          -- 출고여부
                    date_format(TOUT.modify_dt, '%Y-%m-%d %H:%i') AS modify_dt, -- 생성일시
                    wpu.display_name AS create_name -- 담당자
                FROM tbarcodeout AS TOUT
                LEFT JOIN wp_users wpu
                    ON TOUT.modify_id = wpu.user_login
                WHERE DATE(TOUT.increate_dt) BETWEEN ? AND ? 
                  AND TOUT.barcode_out IS NULL
                  AND TOUT.invoice_yn = 'Y'
                ORDER BY TOUT.increate_dt DESC, TOUT.serial_no DESC
                ";

        // error_log("\n[ln646]sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
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

// 개인판매 저장
if ($category === 'save-out') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "INSERT INTO tbarcodeout (
                    increate_dt,           -- 입력일자(선택)
                    prod_code,             -- 상품코드
                    prod_nm,               -- 상품명
                    lc_no,                 -- LC번호=>BL번호
                    origin_store_code,     -- 점번호(000=조은점)
                    store_code,            -- 판매점점
                    store_no,              -- 개인점은 직접입력
                    manu_date,             -- 제조일자(입력일자와 동일)
                    weight,                -- 중량(kg)
                    invoice_yn,            -- 출고여부yn=
                    create_id,             -- 생성자ID
                    modify_id              -- 변경자ID
                ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $data['increate_dt'],
            $data['prod_code'],
            $data['prod_nm'],
            $data['lc_no'],
            $data['origin_store_code'],
            $data['store_code'],
            $data['store_no'],
            $data['increate_dt'],
            floatval($data['weight']),
            'Y',
            $user_id,
            $user_id
        ]);

        // error_log("[ln710]sql=" . $sql, 3, $logFilePath);
        // error_log("[ln711]data=" . json_encode($data), 3, $logFilePath);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 개인판매 정보 업데이트
if ($category === 'update-out') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        // error_log("[ln52] data=" . json_encode($data), 3, $logFilePath);

        $sql = "UPDATE tbarcodeout SET 
                    increate_dt = ?,           -- 입력일자(선택)
                    prod_code = ?,             -- 상품코드
                    prod_nm = ?,               -- 상품명
                    lc_no = ?,                 -- LC번호=>BL번호
                    origin_store_code = ?,     -- 출고점코드
                    store_code = ?,           -- 판매점코드
                    store_no = ?,              -- 비고 (개인점은 직접입력)
                    weight = ?,                -- 중량(kg)
                    modify_dt = ?,             -- 변경일자
                    modify_id = ?              -- 변경자ID
                WHERE
                    serial_no = ?              -- 일련번호로 레코드 식별
                ";

        // error_log("[ln729]sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['increate_dt'],
            $data['prod_code'],
            $data['prod_nm'],
            $data['lc_no'],
            $data['origin_store_code'],
            $data['store_code'],
            $data['store_no'],
            $data['weight'],
            date('Y-m-d H:i:s'),
            $user_id,
            $data['serial_no']
        ]);

        echo json_encode([
            'success' => true, 
            'message' => '[판매번호= ' . $data['serial_no'] . ']가 수정되었습니다.'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 개인판매 정보 삭제
if ($category === 'delete-out') {
    try {
        $serial_nos = json_decode(file_get_contents('php://input'), true)['serial_nos'];

        $placeholders = str_repeat('?,', count($serial_nos) - 1) . '?';
        $sql = "DELETE FROM tbarcodeout WHERE serial_no IN ($placeholders)";

        // error_log("\n[ln770]sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($serial_nos);

        echo json_encode([
            'success' => true,
            'message' => '선택한 항목이 삭제되었습니다.'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}


// ------------------
// 행사 조회
// ------------------
if ($category === 'search-event') {
    try {
        $sql =
            "SELECT 
                    TEVENT.serial_no AS serial_no,            -- 일련번호
                    date(TEVENT.increate_dt) AS increate_dt,  -- 판매일
                    TEVENT.major_code AS major_code,        -- 대분류
                    TCBM.major_nm AS major_nm,            -- 대분류명
                    TEVENT.code AS code,                    -- 소분류
                    TCBC.code_nm AS code_nm,              -- 소분류명
                    TEVENT.event_name AS event_name,        -- 행사명
                    TEVENT.event_desc AS event_desc,        -- 행사설명
                    TEVENT.start_date AS start_date,          -- 시작일
                    TEVENT.end_date AS end_date,              -- 종료일
                    TEVENT.prod_code AS prod_code,          -- 상품코드
                    TCC.code_exp AS prod_nm,              -- 상품명
                    TEVENT.unit_price AS unit_price,        -- 단가
                    TEVENT.target_qty AS target_qty,        -- 목표수량
                    TEVENT.result_qty AS result_qty,        -- 실적수량
                    date_format(TEVENT.modify_dt, '%Y-%m-%d %H:%i') AS modify_dt, -- 생성일시
                    wpu.display_name AS modify_nm -- 담당자
                FROM tevent AS TEVENT
                LEFT JOIN wp_users wpu ON TEVENT.modify_id = wpu.user_login
                LEFT JOIN tcodebook AS TCBM ON TEVENT.major_code = TCBM.major_code AND TCBM.code = '000'  -- 행사 대분류
                LEFT JOIN tcodebook AS TCBC ON TEVENT.major_code = TCBC.major_code AND TEVENT.code = TCBC.code  -- 행사 소분류
                LEFT JOIN tcomcode AS TCC ON TEVENT.prod_code = TCC.store_code AND TCC.grp_code = '2' AND TCC.use_yn = 'y' -- 상품코드
                WHERE DATE(TEVENT.increate_dt) BETWEEN ? AND ? 
                ORDER BY TEVENT.increate_dt, TEVENT.serial_no DESC
                ";

        // error_log("\n[ln646]sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
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

// 행사 저장
if ($category === 'save-event') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "INSERT INTO tevent (
                    increate_dt,      -- 입력일자(선택)
                    major_code,       -- 대분류
                    code,             -- 소분류
                    event_name,       -- 행사명
                    event_desc,       -- 행사설명
                    start_date,       -- 시작일
                    end_date,         -- 종료일
                    prod_code,        -- 상품코드
                    prod_name,        -- 상품명
                    unit_price,       -- 단가
                    target_qty,       -- 목표수량
                    result_qty,       -- 실적수량
                    create_id,        -- 생성자ID
                    modify_id         -- 변경자ID
                ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $data['increate_dt'],
            $data['major_code'],
            $data['code'],
            $data['event_name'],
            $data['event_desc'],
            $data['start_date'],
            $data['end_date'],
            $data['prod_code'],
            $data['prod_name'],
            floatval($data['unit_price']),
            floatval($data['target_qty']),
            floatval($data['result_qty']),
            $user_id,
            $user_id
        ]);

        // error_log("[ln710]sql=" . $sql, 3, $logFilePath);
        // error_log("[ln909]data=" . json_encode($data), 3, $logFilePath);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 행사 정보 업데이트
if ($category === 'update-event') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        // error_log("[ln52] data=" . json_encode($data), 3, $logFilePath);

        $sql = "UPDATE tevent SET 
                    increate_dt = ?,      -- 입력일자(선택)
                    major_code = ?,       -- 대분류
                    code = ?,             -- 소분류
                    event_name = ?,       -- 행사명
                    event_desc = ?,       -- 행사설명
                    start_date = ?,         -- 시작일
                    end_date = ?,           -- 종료일
                    prod_code = ?,        -- 상품코드
                    prod_name = ?,        -- 상품명
                    unit_price = ?,       -- 단가
                    target_qty = ?,       -- 목표수량
                    result_qty = ?,       -- 실적수량
                    modify_id = ?         -- 변경자ID
                WHERE
                    serial_no = ?         -- 일련번호로 레코드 식별
                ";

        // error_log("[ln946]sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['increate_dt'],
            $data['major_code'],
            $data['code'],
            $data['event_name'],
            $data['event_desc'],
            $data['start_date'],
            $data['end_date'],
            $data['prod_code'],
            $data['prod_name'],
            floatval($data['unit_price']),
            floatval($data['target_qty']),
            floatval($data['result_qty']),
            $user_id,
            $data['serial_no']
        ]);

        echo json_encode([
            'success' => true, 
            'message' => '[판매번호= ' . $data['serial_no'] . ']가 수정되었습니다.'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 행사 정보 삭제
if ($category === 'delete-event') {
    try {
        $serial_nos = json_decode(file_get_contents('php://input'), true)['serial_nos'];

        $placeholders = str_repeat('?,', count($serial_nos) - 1) . '?';
        $sql = "DELETE FROM tevent WHERE serial_no IN ($placeholders)";

        // error_log("\n[ln770]sql=" . $sql, 3, $logFilePath);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($serial_nos);

        echo json_encode([
            'success' => true,
            'message' => '선택한 항목이 삭제되었습니다.'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// 행사 대/소분류 조회 (tbookcode)
if ($category === 'getEventCode') {
    // 파라미터: code (000=대분류, 그 외 소분류 코드)
    $code = $_GET['code'];
    // error_log("\n[ln1009]code=" . $code, 3, $logFilePath);

    if ($code === '000') { // 대분류 조회
        $sql = "SELECT DISTINCT major_code AS code, major_nm AS code_nm 
                FROM tcodebook
                WHERE code IN ('000', '001')  -- 000=명절, 001=행사기획
                ORDER BY major_code";
    } else {
        // 소분류 조회
        $sql = "SELECT DISTINCT code, code_nm 
                FROM tcodebook
                WHERE major_code = $code
                  AND code <> '000'
                ORDER BY code";
    }
    $result = $conn->query($sql);

    // error_log("[ln1025]sql=" . $sql, 3, $logFilePath);

    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = array(
                'code' => $row['code'],
                'codeNm' => $row['code_nm']
            );
        }
    }

    $response = array(
        'status' => 'success',
        'data' => $data
    );

    echo json_encode($response);
    exit;
}

// 행사 상품 목록 조회
if ($category === 'getEventProdList') {
    $sql = "SELECT DISTINCT store_code as prod_code, code_exp as prod_name 
              FROM tcomcode
              WHERE grp_code = '2'
                AND use_yn = 'y'
            ORDER BY store_code";    
    $result = $conn->query($sql);

    // error_log("[ln1057]sql=" . $sql, 3, $logFilePath);
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = array(
                'prod_code' => $row['prod_code'],
                'prod_name' => $row['prod_name']
            );
        }
    }

    $response = array(
        'status' => 'success',  
        'data' => $data
    );

    echo json_encode($response);
    exit;
}


$conn->close();
