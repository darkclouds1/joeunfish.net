<?php
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = __DIR__ . "/logfile.log"; 

// error_log("barcodeInv_srv.php?category=" . $category, 3, $logFilePath);
header('Content-Type: application/json'); // Ensure the correct content type is set for JSON response

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    // error_log("barcodeInv_srv.php 데이터=" . print_r($data, true), 3, $logFilePath);

    if (!is_array($data)) {
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    } 

    $category = $data['category'] ?? '';
    // error_log("category=" . $category, 3, $logFilePath);
    $total_box = $data['total_box'] ?? "";
    $total_weight = $data['total_weight'] ?? "";
    $increate_dt = $data['increate_dt'] ?? "";
    $store_code = $data['store_no'] ?? "";      // store_no 는=> store_code가 넘어옴
    $doc_no = $data['doc_no'] ?? "";
    $user_id = $data['user_id'] ?? "";
    $barcode_out = $data['barcode_out'] ?? [];

    // 출고확정
    if ($category === "finInv") {

        // 점포코드의 점포명 가져오기
        if (
            trim($store_code) !== ""
        ) {
            // SQL 인젝션 방지를 위해 값 이스케이프
            $safe_store_code = $conn->real_escape_string($store_code);
            $sql = "SELECT RIGHT(TCC.code_exp, 3) AS store_nm
                    FROM tcomcode AS TCC
                    WHERE TCC.grp_code = '1'
                    AND TCC.use_yn = 'y'
                    AND TCC.store_code = '$safe_store_code'";

            $result = $conn->query($sql);
            if ($result) {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $store_nm = $row['store_nm']; // 단일 결과 사용
                    // echo $store_nm;
                } else {
                    $store_nm = ''; // 결과가 없을 때 빈 문자열 할당 => 결과는 반드시 있어야 함.
                }
            } else {
                echo "Query failed: " . $conn->error; // 쿼리 실패 시 오류 메시지 출력
                $store_nm = '';
            }
            // debug
            // error_log(print_r("store_nm:" . $store_nm, true), 3, $logFilePath);
        }

        $conn->begin_transaction();
        try {
            foreach ($barcode_out as $item) {
                // 아이템 삽입 쿼리
                $sqlInsert = "REPLACE INTO tbarcodeinv (increate_dt, store_code, doc_no, store_no, prod_code, prod_nm, barcode_out, weight, expi_date, create_id)
                                    SELECT ?, ?, ?, ?, bo.prod_code, bo.prod_nm, ?, bo.weight, bo.expi_date, ?
                                      FROM tbarcodeout bo
                                     WHERE bo.barcode_out = ?";

                $stmtInsert = $conn->prepare($sqlInsert);
                if (!$stmtInsert) {
                    throw new Exception("Prepare statement failed: " . $conn->error);
                }

                $stmtInsert->bind_param("sssssss", $item['increate_dt'], $store_code, $doc_no, $store_nm,  $item['barcode_out'], $user_id, $item['barcode_out']);
                if (!$stmtInsert->execute()) {
                    throw new Exception("Execute failed: " . $stmtInsert->error);
                }
                $stmtInsert->close();

                // 바코드에 대한 invoice_yn 업데이트
                $sqlUpdate = "UPDATE tbarcodeout SET doc_no = ?, invoice_yn = 'y', modify_id = ? WHERE barcode_out = ? AND increate_dt = ? AND store_code = ? ";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                if (!$stmtUpdate) {
                    throw new Exception("Prepare statement failed: " . $conn->error);
                }
                $stmtUpdate->bind_param(
                    "sssss",
                    $doc_no,
                    $user_id,
                    $item['barcode_out'],
                    $item['increate_dt'],
                    $store_code
                );

                if (!$stmtUpdate->execute()) {
                    throw new Exception("Execute failed: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
            }

            // 먼저 조건에 맞는 기존 레코드 삭제
            $sqlDelete = "DELETE FROM tbarcodeinv_m WHERE LEFT(increate_dt, 10) = ? AND store_code = ? AND SUBSTRING(doc_no, 5) = ?";
            $stmtDelete = $conn->prepare($sqlDelete);
            $stmtDelete->bind_param("sss", $increate_dt, $store_code, substr($doc_no, 4));
            $stmtDelete->execute();
            $stmtDelete->close();

            // 당일에 한하여 제품 추가해서 출고확정 처리 할 수 있음.
            $sqlInsert_m = "REPLACE INTO tbarcodeinv_m (increate_dt, store_code, doc_no, store_no, invoice_yn, total_box, total_weight, create_id)
                            VALUES (?, ?, ?, ?, 'y', ?, ?, ?)";
            $stmtInsert_m = $conn->prepare($sqlInsert_m);
            if (!$stmtInsert_m) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
            $stmtInsert_m->bind_param("ssssids", $increate_dt, $store_code, $doc_no, $store_nm, $total_box, $total_weight, $user_id);
            if (!$stmtInsert_m->execute()) {
                throw new Exception("Execute failed: " . $stmtInsert_m->error);
            }
            $stmtInsert_m->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => '출고확정 처리가 완료되었습니다.', 'category' => $category]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => '처리 중 에러가 발생하였습니다.', 'error' => $e->getMessage()]);
            error_log(print_r($e->getMessage(), true), 3, $logFilePath);
        } finally {
            $conn->close();
            exit;
        }
    } else if ($category === "finInvCancel") {
        // 확정취소 inv, inv_m 두 테이블 레코드 삭제
        $conn->begin_transaction();
        try {

            $sqlDelete =
                "DELETE t1, t2
                            FROM tbarcodeinv_m t1
                            LEFT JOIN tbarcodeinv t2
                            ON LEFT(t1.increate_dt, 10) = LEFT(t2.increate_dt, 10) AND t1.store_code = t2.store_code
                            WHERE LEFT(t1.increate_dt, 10) = ?
                            AND t1.store_code = ?
                            AND SUBSTRING(t1.doc_no, 5) = ?;
                        ";
            $stmtDelete = $conn->prepare($sqlDelete);
            $stmtDelete->bind_param("sss", $increate_dt, $store_code, substr($doc_no, 4));
            $stmtDelete->execute();
            $stmtDelete->close();

            // tbarcodeout.invoice_yn = 'n' 로 업데이트
            // 바코드에 대한 invoice_yn 업데이트
            $sqlUpdate =
                "UPDATE tbarcodeout 
                             SET invoice_yn = 'n', modify_id = ? 
                           WHERE LEFT(increate_dt, 10) = ? 
                             AND store_code = ?
                             AND SUBSTRING(doc_no, 5) = ?
                        ";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("ssss", $user_id, $increate_dt, $store_code, substr($doc_no, 4));
            $stmtUpdate->execute();
            $stmtUpdate->close();

            echo json_encode(['success' => true, 'message' => '출고취소 처리가 완료되었습니다.', 'category' => $category]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => '처리 중 에러가 발생하였습니다.', 'error' => $e->getMessage()]);
            // debug
            error_log(print_r($e->getMessage(), true), 3, $logFilePath);
        } finally {
            $conn->commit();
            $conn->close();
            exit;
        }
    } else if ($category === "moveBarcode") {
        $target_store_code = $data['target_store_code'] ?? '';
        $target_doc_no = $data['target_doc_no'] ?? '';
        $store_no = $data['store_no'] ?? '';
        $origin_store_code = $data['origin_store_code'] ?? '';
        $origin_doc_no = $data['origin_doc_no'] ?? '';
        $prod_code = $data['prod_code'] ?? '';

        if (!$target_store_code || !$target_doc_no || !$store_no || !$origin_store_code || !$origin_doc_no || !$prod_code) {
            echo json_encode(['success' => false, 'message' => '필수값이 누락되었습니다.']);
            $conn->close();
            exit;
        }
 
        // UPDATE 쿼리
        $sql = "UPDATE tbarcodeout 
                   SET store_code = ?, doc_no = ?, store_no = ?
                 WHERE doc_no = ? AND store_code = ? AND prod_code = ?";

        // debug
        error_log("\nln248::moveBarcode::sql=" . $sql, 3, $logFilePath);

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $conn->error]);
            $conn->close();
            exit;
        }

        $stmt->bind_param("ssssss", $target_store_code, $target_doc_no, $store_no, $origin_doc_no, $origin_store_code, $prod_code);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '이동 처리 완료']);
        } else {
            echo json_encode(['success' => false, 'message' => '이동 처리 실패: ' . $stmt->error]);
        }
        $conn->close();
        exit;
    } else {
        echo json_encode(['error' => 'Invalid category or request method']);
        exit;
    }
} else {
    // doc_no 조회 (PO조회, PO선택 시 조회
    $category = $_GET['category'] ?? "";
    error_log("\nln27::category=" . $category, 3, $logFilePath);

    if ($category === "serDoc_no") {
        $store_code = $_GET['store_no'] ?? "";
        $increate_dt = $_GET['increate_dt'] ?? "";
        if ($increate_dt !== "") {
            $increate_dt = str_replace("-", "", $increate_dt); 
        }

        // 연어필렛, 연어스테이크만 PO# 조회
        $sql = "SELECT ORD.doc_no 
                  FROM `torders` AS ORD 
                  JOIN torderproducts AS PRD ON ORD.doc_no = PRD.doc_no AND PRD.product_code IN ('2000000560770','0200000651343','2000000668592', '2000000614117')
                 WHERE ORD.dest_code = '$store_code'
                   AND ORD.supply_dt = '$increate_dt'
                 GROUP BY ORD.doc_no
                 ORDER BY ORD.doc_no;
        ";

        // debug
        error_log("\nln250::serDoc_no::sql=" . $sql, 3, $logFilePath);
        $result = $conn->query($sql);

        $doc_no = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $doc_no[] = $row['doc_no'];
            }
        }

        // error_log("doc_no=" . print_r($doc_no, true), 3, $logFilePath);

        echo json_encode($doc_no);
        $conn->close();
        exit;
    }

}