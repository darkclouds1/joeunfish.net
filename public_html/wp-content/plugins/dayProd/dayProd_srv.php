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
    isset($_GET['increate_dt']) ? $increate_dt = $_GET['increate_dt'] : $increate_dt = "";
    isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

    // 조회
    if ($category === 'search') {
        $sql =
            "WITH all_products AS (
            SELECT DISTINCT
                d_prod.prod_yn AS '확정여부',
                m2.mat_nm AS '원료명',
                m2.unit AS '원료단위',
                CASE 
                    WHEN d_mat.qty_unit IS NULL THEN ''
                    WHEN d_mat.qty_unit = FLOOR(d_mat.qty_unit) THEN CAST(FLOOR(d_mat.qty_unit) AS CHAR)
                    ELSE CAST(d_mat.qty_unit AS CHAR)
                END AS 'mBox',
                CASE 
                    WHEN d_mat.qty IS NULL THEN ''
                    WHEN d_mat.qty = FLOOR(d_mat.qty) THEN CAST(FLOOR(d_mat.qty) AS CHAR)
                    ELSE CAST(d_mat.qty AS CHAR)
                END AS '소모량',
                m1.prod_nm AS '완제품명',
                m1.unit AS '완제품단위',
                CASE 
                    WHEN d_prod.qty_unit IS NULL THEN ''
                    WHEN d_prod.qty_unit = FLOOR(d_prod.qty_unit) THEN CAST(FLOOR(d_prod.qty_unit) AS CHAR)
                    ELSE CAST(d_prod.qty_unit AS CHAR)
                END AS 'pBox',
                CASE 
                    WHEN d_prod.qty IS NULL THEN ''
                    WHEN d_prod.qty = FLOOR(d_prod.qty) THEN CAST(FLOOR(d_prod.qty) AS CHAR)
                    ELSE CAST(d_prod.qty AS CHAR)
                END AS '생산량',
                CASE 
                    WHEN d_prod.yield_rate IS NULL THEN ''
                    WHEN d_prod.yield_rate = FLOOR(d_prod.yield_rate) THEN CAST(FLOOR(d_prod.yield_rate) AS CHAR)
                    ELSE CAST(d_prod.yield_rate AS CHAR)
                END AS '생산율',
                ? AS '생산일자',
                m1.prod_cd,
                m2.mat_cd,
                COALESCE(m2.inv_yn, '') AS inv_yn,  -- 재고로 관리
                m1.order_num AS prod_order,
                m2.order_num AS mat_order,
                COALESCE(d_mat.create_dt, '') AS create_dt,
                COALESCE(d_mat.create_id, '') AS create_id,
                COALESCE(wpu.display_name, '') AS create_name
            FROM tdayprod_mst m1
            JOIN tdayprodmat_mst m2 
                ON m1.prod_cd = m2.prod_cd
            LEFT JOIN tdayprod d_mat   -- 재료 소모량
                ON d_mat.prod_cd = m1.prod_cd 
                AND d_mat.mat_cd = m2.mat_cd 
                AND DATE(d_mat.increate_dt) = ?
            LEFT JOIN tdayprod d_prod   -- 완제품 생산량 = mat_cd = 'PROD'
                ON d_prod.prod_cd = m1.prod_cd 
                AND d_prod.mat_cd = 'PROD'
                AND DATE(d_prod.increate_dt) = ?
            LEFT JOIN wp_users wpu
                ON CONVERT(d_mat.create_id USING utf8mb4) = CONVERT(wpu.user_login USING utf8mb4)
            WHERE m1.use_yn = 'Y'
            AND m2.use_yn = 'Y'
        )
        SELECT 
            ap.확정여부 AS '확정여부',
            ap.원료명 AS '원료명',
            CASE 
                WHEN ap.inv_yn = 'Y' THEN '재고량을 입력'
                ELSE COALESCE(
                    NULLIF(NULLIF(lot.lotin_nm, ''), NULL), 
                    tpi.lotin_nm, 
                    ''
                )
            END AS '입고LOT명',
            CASE 
                WHEN lot.qty_use IS NULL THEN ''
                WHEN lot.qty_use = FLOOR(lot.qty_use) THEN CAST(FLOOR(lot.qty_use) AS CHAR)
                ELSE CAST(lot.qty_use AS CHAR)
            END AS '사용량',
            ap.원료단위 AS '원료단위',
            ap.`mBox` AS 'mBox',
            ap.소모량 AS '소모량',
            ap.완제품명 AS '완제품명',
            ap.완제품단위 AS '완제품단위',
            ap.`pBox` AS 'pBox',
            ap.`생산량` AS '생산량',
            ap.생산율 AS '생산율',
            ap.생산일자 AS '생산일자',
            ap.prod_cd AS 'prod_cd',
            ap.mat_cd AS 'mat_cd',
            ap.inv_yn as 'inv_yn',
            DATE_FORMAT(ap.create_dt, '%Y-%m-%d %H:%i') AS '저장일시',
            ap.create_name AS '담당자',
            tpi.lotin_id AS 'lotin_id'
        FROM 
            all_products ap
        LEFT JOIN 
            tdayprod_lot lot
            ON ap.prod_cd = lot.prod_cd
            AND ap.mat_cd = lot.mat_cd
            AND DATE(lot.increate_dt) = ?
        LEFT JOIN 
            tpurchase_in tpi
            ON lot.tpurchase_in_lotin_id = tpi.lotin_id
        ORDER BY 
            ap.prod_order ASC, ap.mat_order ASC, lot.increate_dt ASC, lot.create_dt ASC;
        ";


        // mysqli prepare statement 사용
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt === false) {
            error_log("Prepare failed: " . mysqli_error($conn), 3, $logFilePath);
            throw new Exception("쿼리 준비 실패");
        }

        // 파라미터 바인딩
        mysqli_stmt_bind_param($stmt, 'ssss', $increate_dt, $increate_dt, $increate_dt, $increate_dt);

        // 쿼리 실행
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Execute failed: " . mysqli_stmt_error($stmt), 3, $logFilePath);
            throw new Exception("쿼리 실행 실패");
        }

        // 결과 가져오기
        $result = mysqli_stmt_get_result($stmt);

        if ($result === false) {
            error_log("Get result failed: " . mysqli_stmt_error($stmt), 3, $logFilePath);
            throw new Exception("결과 가져오기 실패");
        }

        // 결과를 배열로 변환
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        // 리소스 해제
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 저장 - 완제품 생산량 저장 후 재료 데이터 저장
    if ($category === 'save') {

        try {
            // 원본 POST 데이터 확인
            $raw_data = file_get_contents('php://input');
            $saveData = json_decode($raw_data, true);

            // JSON 디코딩 에러 확인
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[ln156::dayProd_srv.php]JSON Error: ' . json_last_error_msg(), 3, $logFilePath);
                throw new Exception('Invalid JSON data: ' . json_last_error_msg());
            }

            if (!$saveData) {
                throw new Exception('No data received');
            }

            $data = $saveData;
            $pdo->beginTransaction();

            $increate_dt = $_GET['increate_dt'];
            $prod_cd = $data['prod_cd'];
            $yield_rate = $data['yield_rate'] === "" ? "0" : $data['yield_rate'];
            $prod_qty_unit = $data['prod_qty_unit'] === "" ? "0" : $data['prod_qty_unit'];
            $prod_qty = $data['prod_qty'] === "" ? "0" : $data['prod_qty'];
            $materials = $data['materials'];

            // 1. 기존 데이터 삭제 (완제품, 재료, LOT 데이터 모두 삭제)
            $delete_sql = "DELETE t1, t2 
           FROM tdayprod t1 
           LEFT JOIN tdayprod_lot t2 
           ON t1.increate_dt = t2.increate_dt 
           AND t1.prod_cd = t2.prod_cd 
           AND t1.mat_cd = t2.mat_cd 
           WHERE t1.prod_cd = ? 
           AND DATE(t1.increate_dt) = ?";
            $stmt = $pdo->prepare($delete_sql);
            $stmt->execute([$prod_cd, $increate_dt]);

            // 2. 완제품 생산량 저장
            $insert_prod_sql =
                "INSERT INTO tdayprod (increate_dt, prod_cd, mat_cd, qty_unit, qty, yield_rate, create_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insert_prod_sql);
            $stmt->execute([
                $increate_dt,
                $prod_cd,
                'PROD',
                $prod_qty_unit,
                $prod_qty,
                $yield_rate,
                $user_id,
            ]);

            // 3. 재료 및 LOT 데이터 저장
            foreach ($materials as $mat) {
                // 3-1. 재료 데이터 저장
                $mat_qty_unit = $mat['mat_qty_unit'] === "" ? "0" : $mat['mat_qty_unit'];
                $mat_qty = $mat['mat_qty'] === "" ? "0" : $mat['mat_qty'];

                // tdayprod 데이터 저장
                $insert_mat_sql =
                    "INSERT INTO tdayprod (increate_dt, prod_cd, mat_cd, qty_unit, qty, create_id) 
                 VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($insert_mat_sql);
                $stmt->execute([
                    $increate_dt,
                    $prod_cd,
                    $mat['mat_cd'],
                    $mat_qty_unit,
                    $mat_qty,
                    $user_id
                ]);

                // 3-2. LOT 데이터 저장
                if (isset($mat['lots']) && !empty($mat['lots'])) {
                    // 기존 LOT 데이터 명시적으로 삭제 (방어적 코드)
                    $delete_lot_sql = "DELETE FROM tdayprod_lot 
                               WHERE increate_dt = ? 
                               AND prod_cd = ? 
                               AND mat_cd = ?";
                    $delete_lot_stmt = $pdo->prepare($delete_lot_sql);
                    $delete_lot_stmt->execute([$increate_dt, $prod_cd, $mat['mat_cd']]);

                    $insert_lot_sql =
                        "INSERT INTO tdayprod_lot (increate_dt, prod_cd, mat_cd, serial_no, qty_use, create_id, tpurchase_in_lotin_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($insert_lot_sql);

                    // lot serial_no를 위한 카운터
                    $lot_serial_no = 1;

                    // 추가: 이미 사용한 serial_no 목록 추적
                    $used_serial_nos = [];

                    foreach ($mat['lots'] as $lot) {
                        // serial_no 중복 확인 및 고유값 보장
                        while (in_array($lot_serial_no, $used_serial_nos)) {
                            $lot_serial_no++;
                        }
                        $used_serial_nos[] = $lot_serial_no;

                        // 현재 재고 확인
                        if ($lot['lotin_id'] !== null) {
                            $check_stock_sql = "SELECT qty_incur FROM tpurchase_in WHERE lotin_id = ?";
                            $check_stmt = $pdo->prepare($check_stock_sql);
                            $check_stmt->execute([$lot['lotin_id']]);
                            $current_stock = $check_stmt->fetchColumn();
                            // lotin_id 가 있을 때 재고 부족 확인
                            if ($current_stock === false || (floatval($current_stock) < floatval($lot['qty_use']))) {
                                throw new Exception(json_encode(['error' => '사용량이 재고량보다 큽니다.', 'lotin_id' => $lot['lotin_id']]));
                            }
                        }

                        // 삽입 시도
                        try {
                            $stmt->execute([
                                $increate_dt,
                                $prod_cd,
                                $mat['mat_cd'],
                                $lot_serial_no,
                                $lot['qty_use'],
                                $user_id,
                                $lot['lotin_id']
                            ]);
                        } catch (PDOException $e) {
                            // 중복 키 오류 발생시 (코드 23000) serial_no 증가 후 다시 시도
                            if ($e->getCode() == '23000') {
                                $lot_serial_no++;
                                // 다시 시도
                                $stmt->execute([
                                    $increate_dt,
                                    $prod_cd,
                                    $mat['mat_cd'],
                                    $lot_serial_no,
                                    $lot['qty_use'],
                                    $user_id,
                                    $lot['lotin_id']
                                ]);
                            } else {
                                // 다른 오류는 그대로 던짐
                                throw $e;
                            }
                        }

                        if ($lot['lotin_id'] !== null) {
                            // 트리거가 실행된 후 데이터 조회
                            $select_sql = "SELECT lotin_nm FROM tpurchase_in WHERE lotin_id = ?";
                            $select_stmt = $pdo->prepare($select_sql);
                            $select_stmt->execute([$lot['lotin_id']]);
                            $lotin_nm = $select_stmt->fetchColumn();

                            // tdayprod_lot에 lotin_nm 업데이트
                            $update_sql = "UPDATE tdayprod_lot 
                                            SET lotin_nm = ? 
                                            WHERE tpurchase_in_lotin_id = ? 
                                            AND increate_dt = ? 
                                            AND prod_cd = ? 
                                            AND mat_cd = ? 
                                            AND serial_no = ?";
                            $update_stmt = $pdo->prepare($update_sql);
                            $update_stmt->execute([
                                $lotin_nm,
                                $lot['lotin_id'],
                                $increate_dt,
                                $prod_cd,
                                $mat['mat_cd'],
                                $lot_serial_no
                            ]);
                        }
                        $lot_serial_no++;
                    }
                }
            }

            $pdo->commit();

            $prod_name = $conn->query("SELECT prod_nm FROM tdayprod_mst WHERE prod_cd = '$prod_cd'")->fetch_assoc()['prod_nm'];
            echo json_encode([
                'success' => true,
                'prod_name' => $prod_name  // DB에서 조회한 생산품명
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit();
    }

    // 전체 삭제
    if ($category === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM tdayprod WHERE DATE(increate_dt) = ?");
            $stmt->execute([$increate_dt]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }

    // 제품 마스터 조회
    if ($category == 'tMstSearch') {
        $sql = "SELECT 
                prod_cd,
                prod_nm,
                unit,
                order_num,
                use_yn
            FROM 
                tdayprod_mst
            ORDER BY 
                order_num ASC";

        $result = $conn->query($sql);

        $items = array();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode(array("items" => $items));
    }

    // 제품 마스터 수정
    if ($category == 'tMstUpdate') {
        try {
            $conn->begin_transaction();

            $json_input = file_get_contents('php://input');
            $data = json_decode($json_input, true);

            // 1. 모든 제품 데이터를 order_num 순으로 조회
            $select_sql = "SELECT prod_cd, order_num 
                      FROM tdayprod_mst 
                      ORDER BY order_num ASC";

            $select_stmt = $conn->prepare($select_sql);
            $select_stmt->execute();
            $result = $select_stmt->get_result();

            // 2. 순서 재정렬을 위한 배열 생성
            $products = [];
            $new_order = 1;
            $target_order = $data['order_num'];

            while ($row = $result->fetch_assoc()) {
                if ($row['prod_cd'] == $data['prod_cd']) {
                    // 수정하려는 데이터는 새로운 위치에 삽입
                    continue;
                }
                $products[] = $row['prod_cd'];
            }

            // 수정하려는 데이터를 원하는 위치에 삽입
            array_splice($products, $target_order - 1, 0, [$data['prod_cd']]);

            // 3. 모든 데이터 순서 업데이트
            $update_sql = "UPDATE tdayprod_mst SET order_num = ? WHERE prod_cd = ?";
            $update_stmt = $conn->prepare($update_sql);

            foreach ($products as $index => $prod_cd) {
                $order = $index + 1;
                $update_stmt->bind_param("is", $order, $prod_cd);
                $update_stmt->execute();
            }

            // 4. 수정하려는 데이터의 다른 필드들 업데이트
            $main_update_sql = "UPDATE tdayprod_mst SET 
                           prod_nm = ?,
                           unit = ?,
                           use_yn = ?,
                           modify_dt = CURRENT_TIMESTAMP,
                           modify_id = ?
                           WHERE prod_cd = ?";

            $main_stmt = $conn->prepare($main_update_sql);
            $main_stmt->bind_param(
                "sssss",
                $data['prod_nm'],
                $data['unit'],
                $data['use_yn'],
                $data['modify_id'],
                $data['prod_cd']
            );

            if (!$main_stmt->execute()) {
                throw new Exception("Update failed: " . $main_stmt->error);
            }

            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(array("success" => true));
        } catch (Exception $e) {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode(array("success" => false, "error" => $e->getMessage()));
        } finally {
            if (isset($main_stmt)) $main_stmt->close();
            if (isset($select_stmt)) $select_stmt->close();
            if (isset($update_stmt)) $update_stmt->close();
        }
    }
    // 제품 마스터 삭제
    if ($category == 'tMstDelete') {
        $json_input = file_get_contents('php://input');
        $data = json_decode($json_input, true);
        $prod_cd = $data['prod_cd'];

        if ($prod_cd) {
            $delete_sql = "DELETE FROM tdayprod_mst WHERE prod_cd = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("s", $prod_cd);

            if ($delete_stmt->execute()) {
                echo json_encode(array("success" => true));
            } else {
                echo json_encode(array("success" => false, "error" => $delete_stmt->error));
            }

            $delete_stmt->close();
        } else {
            echo json_encode(array("success" => false, "error" => "제품코드가 없습니다."));
        }
    }

    // 제품 마스터 추가
    if ($category == 'tMstInsert') {
        try {
            $conn->begin_transaction(); // 트랜잭션 시작

            $json_input = file_get_contents('php://input');
            $data = json_decode($json_input, true);

            $insert_sql =
                "INSERT INTO tdayprod_mst (
                       prod_cd, 
                       prod_nm, 
                       unit, 
                       order_num, 
                       use_yn, 
                       create_id
                   ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($insert_sql);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param(
                "sssiss",
                $data['prod_cd'],
                $data['prod_nm'],
                $data['unit'],
                $data['order_num'],
                $data['use_yn'],
                $data['create_id']
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            // order_num 업데이트 쿼리
            $update_sql = "UPDATE tdayprod_mst 
                     SET order_num = order_num + 1 
                     WHERE order_num >= ? 
                     AND prod_cd != ? 
                     ORDER BY order_num DESC";

            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt === false) {
                throw new Exception("Update prepare failed: " . $conn->error);
            }

            $update_stmt->bind_param(
                "is",
                $data['order_num'],
                $data['prod_cd']
            );

            if (!$update_stmt->execute()) {
                throw new Exception("Update execute failed: " . $update_stmt->error);
            }

            $conn->commit(); // 트랜잭션 커밋
            echo json_encode(array("success" => true));
        } catch (Exception $e) {
            $conn->rollback(); // 오류 시 롤백
            error_log("Error in tMstInsert: " . $e->getMessage(), 3, $logFilePath);
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "error" => $e->getMessage()
            ));
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
            if (isset($update_stmt)) {
                $update_stmt->close();
            }
        }
    }

    // 자재마스터 조회
    if ($category == 'tMatMstSearch') {
        $sql =
            "SELECT 
            m2.prod_cd as prod_cd,
            m1.prod_nm as prod_nm,
            m2.mat_cd as mat_cd,
            m2.mat_nm as mat_nm,
            m2.unit as unit,
            m2.order_num as order_num,
            COALESCE(m2.inv_yn, '') as inv_yn,
            m2.use_yn as use_yn,
            m2.create_dt as create_dt,
            m2.create_id as create_id,
            m2.modify_dt as modify_dt,
            m2.modify_id as modify_id
        FROM 
            tdayprodmat_mst m2
        JOIN 
            tdayprod_mst m1 ON m2.prod_cd = m1.prod_cd
        ORDER BY 
            m2.prod_cd ASC, m2.order_num ASC";

        $result = $conn->query($sql);
        $items = array();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode(array("items" => $items));
    }

    // 자재 마스터 수정
    if ($category == 'tMatMstUpdate') {
        try {
            $conn->begin_transaction();

            $json_input = file_get_contents('php://input');
            $data = json_decode($json_input, true);

            // 1. 해당 prod_cd의 모든 자재 데이터를 order_num 순으로 조회
            $select_sql = "SELECT mat_cd, order_num 
                          FROM tdayprodmat_mst 
                          WHERE prod_cd = ?
                          ORDER BY order_num ASC";

            $select_stmt = $conn->prepare($select_sql);
            $select_stmt->bind_param("s", $data['prod_cd']);
            $select_stmt->execute();
            $result = $select_stmt->get_result();

            // 2. 순서 재정렬을 위한 배열 생성
            $materials = [];
            $target_order = $data['order_num'];

            while ($row = $result->fetch_assoc()) {
                if ($row['mat_cd'] == $data['mat_cd']) {
                    // 수정하려는 데이터는 새로운 위치에 삽입
                    continue;
                }
                $materials[] = $row['mat_cd'];
            }

            // 수정하려는 데이터를 원하는 위치에 삽입
            array_splice($materials, $target_order - 1, 0, [$data['mat_cd']]);

            // 3. 모든 데이터 순서 업데이트
            $update_order_sql = "UPDATE tdayprodmat_mst SET order_num = ? WHERE prod_cd = ? AND mat_cd = ?";
            $update_order_stmt = $conn->prepare($update_order_sql);

            foreach ($materials as $index => $mat_cd) {
                $order = $index + 1;
                $update_order_stmt->bind_param("iss", $order, $data['prod_cd'], $mat_cd);
                $update_order_stmt->execute();
            }

            // 4. 수정하려는 데이터의 다른 필드들 업데이트
            $update_sql = "UPDATE tdayprodmat_mst SET 
                        mat_nm = ?,
                        unit = ?,
                        inv_yn = ?,
                        use_yn = ?,
                        modify_dt = CURRENT_TIMESTAMP,
                        modify_id = ?
                       WHERE prod_cd = ? AND mat_cd = ?";

            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param(
                "sssssss",
                $data['mat_nm'],
                $data['unit'],
                $data['inv_yn'],
                $data['use_yn'],
                $data['modify_id'],
                $data['prod_cd'],
                $data['mat_cd']
            );

            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }

            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(array("success" => true));
        } catch (Exception $e) {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode(array("success" => false, "error" => $e->getMessage()));
        } finally {
            if (isset($stmt)) $stmt->close();
            if (isset($select_stmt)) $select_stmt->close();
            if (isset($update_order_stmt)) $update_order_stmt->close();
        }
    }

    // 재료 마스터 삭제
    if ($category == 'tMatMstDelete') {
        $json_input = file_get_contents('php://input');
        $data = json_decode($json_input, true);
        $prod_cd = $data['prod_cd'];
        $mat_cd = $data['mat_cd'];

        if ($prod_cd && $mat_cd) {
            $delete_sql = "DELETE FROM tdayprodmat_mst WHERE prod_cd = ? AND mat_cd = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ss", $prod_cd, $mat_cd);

            if ($delete_stmt->execute()) {
                echo json_encode(array("success" => true));
            } else {
                echo json_encode(array("success" => false, "error" => $delete_stmt->error));
            }

            $delete_stmt->close();
        } else {
            echo json_encode(array("success" => false, "error" => "제품코드와 재료코드가 필요합니다."));
        }
    }

    // 재료 마스터 추가
    if ($category == 'tMatMstInsert') {
        try {
            $json_input = file_get_contents('php://input');
            $data = json_decode($json_input, true);

            // prod_cd 존재 여부 확인
            $check_sql = "SELECT COUNT(*) as cnt FROM tdayprod_mst WHERE prod_cd = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $data['prod_cd']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['cnt'] == 0) {
                http_response_code(400);  // 클라이언트 에러 코드
                echo json_encode(array(
                    "success" => false,
                    "error" => "존재하지 않는 제품코드입니다. 먼저 제품마스터에 등록해주세요."
                ));
                exit;
            }
            $check_stmt->close();

            $insert_sql =
                "INSERT INTO tdayprodmat_mst (
                        prod_cd,
                        mat_cd, 
                        mat_nm, 
                        unit, 
                        order_num, 
                        inv_yn,
                        use_yn, 
                        create_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($insert_sql);
            if (
                $stmt === false
            ) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param(
                "ssssisss",
                $data['prod_cd'],
                $data['mat_cd'],
                $data['mat_nm'],
                $data['unit'],
                $data['order_num'],
                $data['inv_yn'],
                $data['use_yn'],
                $data['create_id']
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            echo json_encode(array("success" => true));
        } catch (Exception $e) {
            error_log("Error in tMatMstInsert: " . $e->getMessage(), 3, $logFilePath);
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "error" => $e->getMessage()
            ));
        }
    }


    // 생산확정
    if ($category == 'finProd') {
        try {
            $increate_dt = $_GET['increate_dt'] ?? '';

            if (empty($increate_dt)) {
                throw new Exception('날짜가 선택되지 않았습니다.');
            }

            $sql = "UPDATE tdayprod SET prod_yn = 'y' WHERE DATE(increate_dt) = ?";

            $stmt = $pdo->prepare($sql);
            if ($stmt === false) {
                throw new Exception("쿼리 준비 실패");
            }

            if (!$stmt->execute([$increate_dt])) {
                throw new Exception("쿼리 실행 실패");
            }

            echo json_encode([
                'success' => true,
                'message' => '생산이 확정되었습니다.'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error in finProd: " . $e->getMessage(), 3, $logFilePath);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // 확정취소
    if ($category == 'finCancel') {
        try {
            $increate_dt = $_GET['increate_dt'] ?? '';

            if (empty($increate_dt)) {
                throw new Exception('날짜가 선택되지 않았습니다.');
            }

            $sql = "UPDATE tdayprod SET prod_yn = 'n' WHERE DATE(increate_dt) = ?";

            $stmt = $pdo->prepare($sql);
            if ($stmt === false) {
                throw new Exception("쿼리 준비 실패");
            }

            if (!$stmt->execute([$increate_dt])) {
                throw new Exception("쿼리 실행 실패");
            }

            echo json_encode([
                'success' => true,
                'message' => '생산확정이 취소되었습니다.'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error in finCancel: " . $e->getMessage(), 3, $logFilePath);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // tpurchase_in 입고LOT명 조회
    if ($category == 'lotin_nm') {
        $mat_cd = $_GET['mat_cd'] ?? '';
        $sql = "SELECT TPI.lotin_id, TPI.lotin_nm 
                  FROM tpurchase_in TPI
                  LEFT JOIN tpurchase TP ON TP.serial_no = TPI.tpurchase_serial_no
                 WHERE TP.mat_cd = ?
                   AND TPI.qty_incur > 0
                 ORDER BY TPI.lotin_id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mat_cd]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'lotin_list' => $result]);
    }


    $conn->close();
