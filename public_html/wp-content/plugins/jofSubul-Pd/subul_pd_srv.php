<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();

mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = __DIR__ . "/logfile.log";

// 파라미터
$category = $_GET['category'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// 수불 추적 데이터 조회
if ($category === "search_pd") {
    $data = getPdData($from_date, $to_date, $conn);
    
    // 맨 앞에 추가했던 디버깅 코드가 오류 메시지를 출력하지 않도록 헤더를 다시 설정
    header('Content-Type: application/json; charset=UTF-8');
    $json = json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('json_encode error: ' . json_last_error_msg(), 3, $logFilePath);
        echo json_encode(['status' => 'error', 'message' => 'JSON 인코딩 실패: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo $json;
    exit;
}

// 생산재고 업데이트
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['category'] ?? '') === 'update_saengsan_stock') {
    $invt_date = $_POST['invt_date'] ?? '';
    $cnt_fillets = $_POST['cnt_fillets'] ?? '';

    if (empty($invt_date) || !isset($cnt_fillets)) {
        echo json_encode(['status' => 'error', 'message' => '날짜 또는 재고 값이 없습니다.']);
        exit;
    }

    $sql = "UPDATE tbarcodeout_invt SET cnt_fillets = ? WHERE invt_date = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'SQL prepare 실패: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("is", $cnt_fillets, $invt_date);
    $success = $stmt->execute();

    if ($success) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => '생산재고가 업데이트되었습니다.']);
        } else {
            // 해당 날짜에 데이터가 없는 경우 INSERT 실행
            $sql_insert = "INSERT INTO tbarcodeout_invt (invt_date, cnt_fillets) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if($stmt_insert) {
                $stmt_insert->bind_param("si", $invt_date, $cnt_fillets);
                $success_insert = $stmt_insert->execute();
                if ($success_insert && $stmt_insert->affected_rows > 0) {
                     echo json_encode(['status' => 'success', 'message' => '생산재고가 추가되었습니다.']);
                } else {
                     echo json_encode(['status' => 'error', 'message' => '생산재고 추가에 실패했습니다.', 'error' => $stmt_insert->error]);
                }
                $stmt_insert->close();
            } else {
                 echo json_encode(['status' => 'error', 'message' => 'INSERT SQL prepare 실패: ' . $conn->error]);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '데이터베이스 업데이트 실패: ' . $stmt->error]);
    }
    
    $stmt->close();
    exit;
}

// 공통 재고 계산 함수 (전역 위치로 이동)
function calculate_stock($target_date, $product_spec, $conn, $sql_part_silje) {
    $closing_stock_data = null;
    $sql_closing = "SELECT invt_date, stock_quantity FROM tbarcodeout_invt WHERE invt_date <= ? AND stock_quantity IS NOT NULL ORDER BY invt_date DESC LIMIT 1";
    $stmt_closing = $conn->prepare($sql_closing);
    $stmt_closing->bind_param("s", $target_date);
    $stmt_closing->execute();
    $closing_stock_data = $stmt_closing->get_result()->fetch_assoc();
    $stmt_closing->close();

    $base_stock = 0;
    $calc_start_date = '1970-01-01'; // 기본값

    if ($closing_stock_data) {
        $base_stock = (int)$closing_stock_data['stock_quantity'];
        $calc_start_date = date('Y-m-d', strtotime($closing_stock_data['invt_date'] . ' +1 day'));
    }

    $deltas_tongwan = 0;
    $deltas_silje = 0;

    if ($calc_start_date <= $target_date) {
        // [수정] 통관량과 실제 작업량 계산을 별도 쿼리로 분리하여 안정성 확보

        // 1. 기간 내 통관량 합계 계산
        $sql_tongwan = "SELECT COALESCE(SUM(p.vol), 0) FROM tpurchase_imp p WHERE p.import_dt BETWEEN ? AND ? AND TRIM(REPLACE(REPLACE(p.spec, '\r', ''), '\n', '')) = ?";
        $stmt_tongwan = $conn->prepare($sql_tongwan);
        if ($stmt_tongwan) {
            $stmt_tongwan->bind_param("sss", $calc_start_date, $target_date, $product_spec);
            $stmt_tongwan->execute();
            $deltas_tongwan = $stmt_tongwan->get_result()->fetch_row()[0] ?? 0;
            $stmt_tongwan->close();
        }

        // 2. 기간 내 실제 작업량 합계 계산
        $sql_silje = "SELECT {$sql_part_silje}";
        $stmt_silje = $conn->prepare($sql_silje);
        if ($stmt_silje) {
            // $sql_part_silje 내부의 placeholder는 4개
            $stmt_silje->bind_param("ssss", $calc_start_date, $calc_start_date, $target_date, $product_spec);
            $stmt_silje->execute();
            $deltas_silje = $stmt_silje->get_result()->fetch_row()[0] ?? 0;
            $stmt_silje->close();
        }
    }
    return $base_stock + $deltas_tongwan - $deltas_silje;
}

// 통관재고 업데이트
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['category'] ?? '') === 'update_tongwan_stock') {
    $invt_date = $_POST['invt_date'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? '';

    if (empty($invt_date) || !isset($stock_quantity)) {
        echo json_encode(['status' => 'error', 'message' => '날짜 또는 재고 값이 없습니다.']);
        exit;
    }

    $sql = "UPDATE tbarcodeout_invt SET stock_quantity = ? WHERE invt_date = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'SQL prepare 실패: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("is", $stock_quantity, $invt_date);
    $success = $stmt->execute();

    if ($success) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => '통관재고가 업데이트되었습니다.']);
        } else {
            // 해당 날짜에 데이터가 없는 경우 INSERT 실행
            $sql_insert = "INSERT INTO tbarcodeout_invt (invt_date, stock_quantity) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if($stmt_insert) {
                $stmt_insert->bind_param("si", $invt_date, $stock_quantity);
                $success_insert = $stmt_insert->execute();
                if ($success_insert && $stmt_insert->affected_rows > 0) {
                     echo json_encode(['status' => 'success', 'message' => '통관재고가 추가되었습니다.']);
                } else {
                     echo json_encode(['status' => 'error', 'message' => '통관재고 추가에 실패했습니다.', 'error' => $stmt_insert->error]);
                }
                $stmt_insert->close();
            } else {
                 echo json_encode(['status' => 'error', 'message' => 'INSERT SQL prepare 실패: ' . $conn->error]);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '데이터베이스 업데이트 실패: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}

// 수입량 예측 실행
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['category'] ?? '') === 'run_prediction') {
    $predict_weeks = (int)($_POST['predict_weeks'] ?? 12);
    $safety_stock_days = (int)($_POST['safety_stock_days'] ?? 1);
    $next_week_import_plan = (int)($_POST['next_week_import_plan'] ?? 0); // 차주 통관 계획량

    try {
        $product_spec = 'Salmon Trim E BB';

        // --- (A) N주 평균 주간 실제작업량 계산 ---
        $end_date_a = date('Y-m-d');
        $start_date_a = date('Y-m-d', strtotime("-$predict_weeks weeks"));
        
        $sql_a = "
            SELECT SUM(export_vol) AS total_workload
            FROM (
                SELECT
                    CASE
                        WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.vol
                        ELSE COUNT(1)
                    END AS export_vol
                FROM tbarcodein a
                LEFT JOIN (
                    SELECT 
                        awb_suffix AS bl_no, 
                        MAX(spec) AS spec, 
                        MAX(import_dt) AS import_dt,
                        MAX(awb) AS awb,
                        MAX(vol) AS vol,
                        MAX(weight) AS weight
                    FROM tpurchase_imp
                    WHERE import_dt >= DATE_SUB(?, INTERVAL 1 MONTH)
                    GROUP BY awb_suffix
                ) ri ON ri.bl_no = LEFT(a.lc_no, 4)
                WHERE DATE(a.increate_dt) BETWEEN ? AND ?
                  AND ri.import_dt IS NOT NULL
                  AND ri.import_dt >= DATE_SUB(DATE(a.increate_dt), INTERVAL 1 MONTH)
                  AND TRIM(REPLACE(REPLACE(ri.spec, '\r', ''), '\n', '')) = ?
                GROUP BY a.lc_no, a.increate_dt, ri.import_dt, ri.spec, ri.awb, ri.vol, ri.weight
            ) AS daily_workload
        ";

        $stmt_a = $conn->prepare($sql_a);
        $stmt_a->bind_param("ssss", $start_date_a, $start_date_a, $end_date_a, $product_spec);
        $stmt_a->execute();
        $result_a = $stmt_a->get_result();
        $row_a = $result_a->fetch_assoc();
        $total_workload = $row_a['total_workload'] ?? 0;
        $avg_weekly_workload = ($predict_weeks > 0) ? round($total_workload / $predict_weeks) : 0;
        $stmt_a->close();

        // --- (B) 안전 재고 계산 ---
        $avg_daily_workload = ($predict_weeks * 7 > 0) ? $total_workload / ($predict_weeks * 7) : 0;
        $safety_stock = round($avg_daily_workload * $safety_stock_days);

        // --- (C) 지난 주말 통관재고 및 현재 통관재고 계산 ---
        $today_dt = new DateTime();
        $day_of_week = (int)$today_dt->format('w'); // 0:일, 6:토
        $today = $today_dt->format('Y-m-d');

        // 지난 주 토요일(W-1) 계산
        $end_of_last_week = (clone $today_dt)->modify('-' . ($day_of_week + 1) . ' days')->format('Y-m-d');

        // 계산에 필요한 공통 SQL 쿼리 (실제 작업량)
        $sql_part_silje = "
            (SELECT SUM(export_vol) FROM (
                SELECT
                    CASE
                        WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.vol
                        ELSE COUNT(1)
                    END AS export_vol
                FROM tbarcodein a
                LEFT JOIN (
                    SELECT 
                        awb_suffix AS bl_no, MAX(spec) AS spec, MAX(import_dt) AS import_dt,
                        MAX(awb) AS awb, MAX(vol) AS vol, MAX(weight) AS weight
                    FROM tpurchase_imp
                    WHERE import_dt >= DATE_SUB(?, INTERVAL 1 MONTH)
                    GROUP BY awb_suffix
                ) ri ON ri.bl_no = LEFT(a.lc_no, 4)
                WHERE DATE(a.increate_dt) BETWEEN ? AND ?
                  AND ri.import_dt IS NOT NULL
                  AND ri.import_dt >= DATE_SUB(DATE(a.increate_dt), INTERVAL 1 MONTH)
                  AND TRIM(REPLACE(REPLACE(ri.spec, '\r', ''), '\n', '')) = ?
                GROUP BY a.lc_no, a.increate_dt, ri.import_dt, ri.spec, ri.awb, ri.vol, ri.weight
            ) AS daily_silje)
        ";
        
        // 지난 주말(W-1) 재고 계산: 9/21(일) 기준으로 순차 계산하여 9/27(토) 재고 구하기
        $stock_at_end_of_last_week = calculate_sequential_stock($end_of_last_week, $product_spec, $conn);
        $current_tongwan_stock = calculate_sequential_stock($today, $product_spec, $conn);

        // --- (D) 금주 입고 예정량 계산 ---
        $start_of_week = (clone $today_dt)->modify("-$day_of_week days")->format('Y-m-d');
        $end_of_week = (clone $today_dt)->modify("+" . (6 - $day_of_week) . " days")->format('Y-m-d');

        $incoming_this_week = 0;
        $sql_d = "SELECT COALESCE(SUM(vol), 0) AS incoming FROM tpurchase_imp WHERE import_dt BETWEEN ? AND ? AND TRIM(REPLACE(REPLACE(spec, '\r', ''), '\n', '')) = ?";
        $stmt_d = $conn->prepare($sql_d);
        $stmt_d->bind_param("sss", $start_of_week, $end_of_week, $product_spec);
        $stmt_d->execute();
        $incoming_this_week = (int)$stmt_d->get_result()->fetch_assoc()['incoming'];
        $stmt_d->close();
        
        // --- (E) 차주 입고 예정량 계산 ---
        $start_of_next_week = (clone $today_dt)->modify("+" . (7 - $day_of_week) . " days")->format('Y-m-d');
        $end_of_next_week = (clone $today_dt)->modify("+" . (13 - $day_of_week) . " days")->format('Y-m-d');
        
        // 차주 통관 계획량: DB 조회값 + 사용자 입력값
        $incoming_next_week_db = 0;
        $sql_e = "SELECT COALESCE(SUM(vol), 0) AS incoming FROM tpurchase_imp WHERE import_dt BETWEEN ? AND ? AND TRIM(REPLACE(REPLACE(spec, '\r', ''), '\n', '')) = ?";
        $stmt_e = $conn->prepare($sql_e);
        $stmt_e->bind_param("sss", $start_of_next_week, $end_of_next_week, $product_spec);
        $stmt_e->execute();
        $incoming_next_week_db = (int)$stmt_e->get_result()->fetch_assoc()['incoming'];
        $stmt_e->close();
        
        $incoming_next_week = $incoming_next_week_db + $next_week_import_plan;

        // --- 최종 예측 계산 (수정된 로직) ---
        // (F) 차주 말 예상 재고 = (W-1 주말재고) + (D)금주입고 + (E)차주입고 - (2주치 예상 소모량)
        $projected_stock_end_of_next_week = $stock_at_end_of_last_week + $incoming_this_week + $incoming_next_week - (2 * $avg_weekly_workload);
        
        // (G) 차차주 필요량 = (A)주간평균 + (B)안전재고
        $total_needed_for_week_after_next = $avg_weekly_workload + $safety_stock;
        
        // 최종 예측치 = (G) - (F)
        $predicted_import_needed_raw = $total_needed_for_week_after_next - $projected_stock_end_of_next_week;
        $predicted_import_needed = round($predicted_import_needed_raw);

        // 결과 반환
        $response_data = [
            'avg_weekly_workload' => $avg_weekly_workload,
            'safety_stock' => $safety_stock,
            'current_tongwan_stock' => round($current_tongwan_stock),
            'stock_at_end_of_last_week' => round($stock_at_end_of_last_week),
            'incoming_this_week' => $incoming_this_week,
            'incoming_next_week_db' => $incoming_next_week_db, // DB에서 조회한 차주 통관 계획량
            'incoming_next_week_input' => $next_week_import_plan, // 사용자 입력 차주 통관 계획량
            'incoming_next_week' => $incoming_next_week, // 총 차주 통관 계획량
            'projected_stock_end_of_next_week' => round($projected_stock_end_of_next_week),
            'total_needed_for_week_after_next' => round($total_needed_for_week_after_next),
            'predicted_import_needed' => max(0, $predicted_import_needed), // 음수 값은 0으로 처리
            'surplus_stock' => max(0, -$predicted_import_needed) // 초과 재고량
        ];

        echo json_encode(['status' => 'success', 'data' => $response_data]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '예측 계산 중 오류 발생: ' . $e->getMessage()]);
    }
    exit;
}

$conn->close();

// [신규] 통관재고 계산 최적화 함수
function calculate_stock_with_deltas($target_date, $conn, $all_deltas) {
    global $logFilePath;
    
    // 1. 먼저 대상 날짜에 직접 재고가 있는지 확인
    $direct_stock_data = null;
    $sql_direct = "SELECT stock_quantity FROM tbarcodeout_invt WHERE invt_date = ? AND stock_quantity IS NOT NULL";
    $stmt_direct = $conn->prepare($sql_direct);
    $stmt_direct->bind_param("s", $target_date);
    $stmt_direct->execute();
    $direct_stock_data = $stmt_direct->get_result()->fetch_assoc();
    $stmt_direct->close();
    
    // 대상 날짜에 직접 재고가 있으면 그 값을 반환
    if ($direct_stock_data) {
        return (int)$direct_stock_data['stock_quantity'];
    }
    
    // 2. 대상 날짜 이전의 가장 가까운 마감 재고를 조회 (대상 날짜 포함)
    $closing_stock_data = null;
    $sql_closing = "SELECT invt_date, stock_quantity FROM tbarcodeout_invt WHERE invt_date <= ? AND stock_quantity IS NOT NULL ORDER BY invt_date DESC LIMIT 1";
    $stmt_closing = $conn->prepare($sql_closing);
    $stmt_closing->bind_param("s", $target_date);
    $stmt_closing->execute();
    $closing_stock_data = $stmt_closing->get_result()->fetch_assoc();
    $stmt_closing->close();

    $base_stock = 0;
    // 변동분 계산 시작일. 마감 재고가 있으면 그 다음날, 없으면 아주 먼 과거.
    $calc_start_date = '1970-01-01'; 

    if ($closing_stock_data) {
        $base_stock = (int)$closing_stock_data['stock_quantity'];
        // 마감 재고가 있는 날짜가 대상 날짜와 같으면 변동분 계산하지 않음
        if ($closing_stock_data['invt_date'] === $target_date) {
            $calc_start_date = date('Y-m-d', strtotime($target_date . ' +1 day')); // 다음날로 설정하여 변동분 계산 안함
        } else {
            // 마감 재고가 있는 날짜의 다음날부터 변동분 계산
            $calc_start_date = date('Y-m-d', strtotime($closing_stock_data['invt_date'] . ' +1 day'));
        }
    }

    // 2. 마감 재고일 다음날부터 대상 날짜까지의 변동분(통관량 - 실제작업량)을 미리 계산된 배열에서 합산
    $deltas_sum = 0;
    if ($calc_start_date <= $target_date) {
        $period = new DatePeriod(
            new DateTime($calc_start_date),
            new DateInterval('P1D'),
            (new DateTime($target_date))->modify('+1 day')
        );

        foreach ($period as $day) {
            $d_str = $day->format('Y-m-d');
            if (isset($all_deltas[$d_str])) {
                $deltas_sum += ($all_deltas[$d_str]['tongwan'] - $all_deltas[$d_str]['silje']);
            }
        }
    }

    return $base_stock + $deltas_sum;
}

// [신규] 순차적 재고 계산 함수 (예측 실행용)
function calculate_sequential_stock($target_date, $product_spec, $conn) {
    // 1. 9/21(일) 기준 재고 조회
    $base_date = '2025-09-21';
    $sql_base = "SELECT stock_quantity FROM tbarcodeout_invt WHERE invt_date = ? AND stock_quantity IS NOT NULL";
    $stmt_base = $conn->prepare($sql_base);
    $stmt_base->bind_param("s", $base_date);
    $stmt_base->execute();
    $base_stock_data = $stmt_base->get_result()->fetch_assoc();
    $stmt_base->close();
    
    $base_stock = $base_stock_data ? (int)$base_stock_data['stock_quantity'] : 0;
    
    // 2. 9/22부터 대상 날짜까지의 변동분 계산
    $start_date = '2025-09-22';
    if ($start_date > $target_date) {
        return $base_stock;
    }
    
    // 통관량과 실제작업량 조회
    $sql_tongwan = "SELECT COALESCE(SUM(vol), 0) FROM tpurchase_imp WHERE import_dt BETWEEN ? AND ? AND TRIM(REPLACE(REPLACE(spec, '\r', ''), '\n', '')) = ?";
    $stmt_tongwan = $conn->prepare($sql_tongwan);
    $stmt_tongwan->bind_param("sss", $start_date, $target_date, $product_spec);
    $stmt_tongwan->execute();
    $tongwan_total = $stmt_tongwan->get_result()->fetch_row()[0] ?? 0;
    $stmt_tongwan->close();
    
    // 실제작업량 계산 (기존 로직과 동일)
    $sql_silje = "
        SELECT SUM(export_vol) FROM (
            SELECT
                CASE
                    WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.vol
                    ELSE COUNT(1)
                END AS export_vol
            FROM tbarcodein a
            LEFT JOIN (
                SELECT 
                    awb_suffix AS bl_no, MAX(spec) AS spec, MAX(import_dt) AS import_dt,
                    MAX(awb) AS awb, MAX(vol) AS vol, MAX(weight) AS weight
                FROM tpurchase_imp
                WHERE import_dt >= DATE_SUB(?, INTERVAL 1 MONTH)
                GROUP BY awb_suffix
            ) ri ON ri.bl_no = LEFT(a.lc_no, 4)
            WHERE DATE(a.increate_dt) BETWEEN ? AND ?
              AND ri.import_dt IS NOT NULL
              AND ri.import_dt >= DATE_SUB(DATE(a.increate_dt), INTERVAL 1 MONTH)
              AND TRIM(REPLACE(REPLACE(ri.spec, '\r', ''), '\n', '')) = ?
            GROUP BY a.lc_no, a.increate_dt, ri.import_dt, ri.spec, ri.awb, ri.vol, ri.weight
        ) AS daily_silje
    ";
    $stmt_silje = $conn->prepare($sql_silje);
    $stmt_silje->bind_param("ssss", $start_date, $start_date, $target_date, $product_spec);
    $stmt_silje->execute();
    $silje_total = $stmt_silje->get_result()->fetch_row()[0] ?? 0;
    $stmt_silje->close();
    
    return $base_stock + $tongwan_total - $silje_total;
}

function getPdData($from_date, $to_date, $conn)
{
    global $logFilePath;

    try {
        $product_spec = 'Salmon Trim E BB'; // 제품 규격
        $product_name = '연어필렛';         // 제품명

        // [최적화] 1. 반복 계산되는 ShipmentTotalCounts를 임시 테이블로 미리 한 번만 계산
        $conn->query("DROP TEMPORARY TABLE IF EXISTS temp_shipment_counts");
        $sql_temp_table = "
            CREATE TEMPORARY TABLE temp_shipment_counts (INDEX(serial_no)) AS (
                SELECT
                    b.serial_no,
                    COUNT(*) AS total_rows
                FROM tbarcodein a
                INNER JOIN tpurchase_imp b ON b.awb_suffix = a.lc_no 
                WHERE TRIM(REPLACE(REPLACE(b.spec, '\r', ''), '\n', '')) = ?
                GROUP BY b.serial_no
            )
        ";
        $stmt_temp = $conn->prepare($sql_temp_table);
        if ($stmt_temp === false) {
            throw new Exception("임시 테이블 생성 실패: " . $conn->error);
        }
        

        $stmt_temp->bind_param("s", $product_spec);
        $stmt_temp->execute();
        $stmt_temp->close();

        // [신규] 'calculate_stock' 함수에서 사용할 공통 SQL 쿼리 (실제 작업량)
        $sql_part_silje = "
            (SELECT SUM(export_vol) FROM (
                SELECT
                    CASE
                        WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.vol
                        ELSE COUNT(1)
                    END AS export_vol
                FROM tbarcodein a
                LEFT JOIN (
                    SELECT 
                        awb_suffix AS bl_no, MAX(spec) AS spec, MAX(import_dt) AS import_dt,
                        MAX(awb) AS awb, MAX(vol) AS vol, MAX(weight) AS weight
                    FROM tpurchase_imp
                    WHERE import_dt >= DATE_SUB(?, INTERVAL 1 MONTH)
                    GROUP BY awb_suffix
                ) ri ON ri.bl_no = LEFT(a.lc_no, 4)
                WHERE DATE(a.increate_dt) BETWEEN ? AND ?
                  AND ri.import_dt IS NOT NULL
                  AND ri.import_dt >= DATE_SUB(DATE(a.increate_dt), INTERVAL 1 MONTH)
                  AND TRIM(REPLACE(REPLACE(ri.spec, '\r', ''), '\n', '')) = ?
                GROUP BY a.lc_no, a.increate_dt, ri.import_dt, ri.spec, ri.awb, ri.vol, ri.weight
            ) AS daily_silje)
        ";

        // [수정] 2. 슬라이딩 윈도우 계산을 위해 조회 기간을 'from_date - 1개월'로 확장하여 '일별 변동분' 조회
        $extended_from_date = date('Y-m-d', strtotime($from_date . ' -1 month'));

        // 2. [최적화] 기간 내 '일별 변동분' 조회 쿼리에서 임시 테이블을 사용하도록 수정
        $sql_tongwan_silje_deltas = "
        WITH Calendar AS (
            SELECT DATE_ADD(?, INTERVAL (t.n + u.n + v.n) DAY) AS dt
            FROM (
                SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
            ) t,
            (
                SELECT 0 AS n UNION ALL SELECT 10 UNION ALL SELECT 20 UNION ALL SELECT 30 UNION ALL SELECT 40 UNION ALL
                SELECT 50 UNION ALL SELECT 60 UNION ALL SELECT 70 UNION ALL SELECT 80 UNION ALL SELECT 90
            ) u,
            (
                SELECT 0 AS n UNION ALL SELECT 100 UNION ALL SELECT 200 UNION ALL SELECT 300
            ) v
            WHERE DATE_ADD(?, INTERVAL (t.n + u.n + v.n) DAY) <= ?
        ),
        DailyTongwan AS (
            SELECT
                p.import_dt AS dt,
                SUM(p.vol) AS tongwan_qty
            FROM tpurchase_imp p
            WHERE
                TRIM(REPLACE(REPLACE(p.spec, '\r', ''), '\n', '')) = ? AND p.import_dt BETWEEN ? AND ?
            GROUP BY p.import_dt
        ),
        RawImport AS (
            SELECT
                awb_suffix AS bl_no,
                MAX(spec) AS spec,
                MAX(import_dt) AS import_dt,
                MAX(awb) AS awb,
                MAX(vol) AS vol,
                MAX(weight) AS weight
            FROM tpurchase_imp
            WHERE import_dt >= DATE_SUB(?, INTERVAL 1 MONTH)
            GROUP BY awb_suffix
        ),
        RawExportLogic AS (
            SELECT
                DATE(a.increate_dt) AS dt,
                CASE
                    WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.vol
                    ELSE COUNT(1)
                END AS export_vol
            FROM tbarcodein a
            LEFT JOIN RawImport ri ON ri.bl_no = LEFT(a.lc_no, 4)
            WHERE
                DATE(a.increate_dt) BETWEEN ? AND ?
                AND ri.import_dt IS NOT NULL
                AND ri.import_dt >= DATE_SUB(DATE(a.increate_dt), INTERVAL 1 MONTH)
                AND TRIM(REPLACE(REPLACE(ri.spec, '\r', ''), '\n', '')) = ?
            GROUP BY
                a.lc_no, a.increate_dt, ri.import_dt, ri.spec, ri.awb, ri.vol, ri.weight
        ),
        DailySilje AS (
            SELECT
                dt,
                SUM(export_vol) AS silje_qty
            FROM RawExportLogic
            GROUP BY dt
        )
        SELECT
            c.dt AS `date`,
            COALESCE(t.tongwan_qty, 0) AS 'tongwan',
            COALESCE(s.silje_qty, 0) AS 'silje'
        FROM Calendar c
        LEFT JOIN DailyTongwan t ON c.dt = t.dt
        LEFT JOIN DailySilje s ON c.dt = s.dt
        ORDER BY c.dt;
    ";
    
        $stmt_tongwan_silje = $conn->prepare($sql_tongwan_silje_deltas);
        if ($stmt_tongwan_silje === false) {
            throw new Exception("통관/실적 데이터 SQL prepare 실패: " . $conn->error);
        }

        
        // [수정] 최적화로 인해 파라미터 개수가 9개에서 10개로 변경
        $stmt_tongwan_silje->bind_param("ssssssssss",
            $extended_from_date, $extended_from_date, $to_date,       // Calendar
            $product_spec, $extended_from_date, $to_date, // DailyTongwan
            $extended_from_date, $extended_from_date, $to_date, $product_spec  // DailySilje (RawImport + RawExportLogic)
        );
        $stmt_tongwan_silje->execute();
        if ($stmt_tongwan_silje->error) {
            throw new Exception("통관/실적 데이터 SQL execute 실패: " . $stmt_tongwan_silje->error);
        }
        $result_tongwan_silje = $stmt_tongwan_silje->get_result();
        $tongwan_data = [];
        while ($row = $result_tongwan_silje->fetch_assoc()) {
            $tongwan_data[$row['date']] = [
                'tongwan' => $row['tongwan'],
                'silje' => $row['silje']
            ];
        }
        $stmt_tongwan_silje->close();

        // 3. 나머지 일별 데이터(생산, 판매 등) 조회 쿼리 (기존과 동일)
        $sql_daily = "
        SELECT DATE(`date`) as `date`, `type`, SUM(`value`) as `value` FROM (
            -- 실생산 (생산내역): create_dt, prod_code='2000000560770' 사용
            SELECT DATE(create_dt) AS `date`, 'silsaeng' AS `type`, 1 AS `value`
            FROM tbarcodeout
            WHERE origin_store_code = '000' 
            AND prod_code = '2000000560770'
            AND DATE(create_dt) BETWEEN ? AND ?
            UNION ALL

            -- 실판매 (납품내역): 행(row)당 1개의 박스로 계산
            SELECT a.increate_dt AS `date`, 'silpan' AS `type`, 1 AS `value`
            FROM tbarcodeout a 
            WHERE a.store_code NOT IN ('000','001','002','003','004','005','006','007') AND prod_nm LIKE CONCAT('%', ?, '%') AND a.increate_dt BETWEEN ? AND ?
            UNION ALL

            -- 예상판매
            SELECT predict_dt as `date`, 'yesangpan' as `type`, (predict_qty DIV 10) as `value`
            FROM torder_predict
            WHERE product_code = '2000000560770' AND predict_dt BETWEEN ? AND ?
            UNION ALL

            -- 생산재고
            SELECT invt_date AS `date`, 'saengsanjaego' AS `type`, cnt_fillets AS `value`
            FROM tbarcodeout_invt
            WHERE invt_date BETWEEN ? AND ?

        ) AS daily_data GROUP BY DATE(`date`), `type`
    ";

        $stmt_daily = $conn->prepare($sql_daily);
        if ($stmt_daily === false) {
            throw new Exception("일별 데이터 SQL prepare 실패: " . $conn->error);
        }
        

        $stmt_daily->bind_param("sssssssss", 
            $from_date, $to_date,
            $product_name, $from_date, $to_date,
            $from_date, $to_date,
            $from_date, $to_date
        );
        
        $stmt_daily->execute();
        if ($stmt_daily->error) {
            throw new Exception("일별 데이터 SQL execute 실패: " . $stmt_daily->error);
        }

        $result_daily = $stmt_daily->get_result();
        $daily_values = [];
        while ($row = $result_daily->fetch_assoc()) {
            $daily_values[$row['date']][$row['type']] = $row['value'];
        }
        $stmt_daily->close();
        
        // 4. [수정] 최종 데이터 가공 (마감재고 기반 초기재고 계산 + 일별 누적 방식)
        $result_data = [];
        $categories = ['통관계획', '실제작업', '예상생산', '실제생산', '예상판매', '실제판매', '생산재고', '통관재고'];
        foreach ($categories as $cat) {
            $result_data[$cat] = ['구분' => $cat, '합계' => 0];
        }

        // [신규] 최초 통관재고 기준일 조회
        $min_stock_date = null;
        $sql_min_date = "SELECT MIN(invt_date) as min_date FROM tbarcodeout_invt WHERE stock_quantity IS NOT NULL AND stock_quantity != 0";
        $result_min_date = $conn->query($sql_min_date);
        if ($result_min_date && $row_min_date = $result_min_date->fetch_assoc()) {
            if (!empty($row_min_date['min_date'])) {
                $min_stock_date = $row_min_date['min_date'];
            }
        }

        // [신규] 1. 조회 시작일(from_date)을 포함, 그 이전의 가장 가까운 마감 재고를 조회
        $sql_closing_stock = "
            SELECT invt_date, stock_quantity
            FROM tbarcodeout_invt
            WHERE invt_date <= ? AND stock_quantity IS NOT NULL AND stock_quantity != 0
            ORDER BY invt_date DESC
            LIMIT 1
        ";
        $stmt_closing = $conn->prepare($sql_closing_stock);
        $stmt_closing->bind_param("s", $from_date);
        $stmt_closing->execute();
        $result_closing = $stmt_closing->get_result();
        $closing_stock_data = $result_closing->fetch_assoc();
        $stmt_closing->close();

        $base_stock = 0;
        $calc_start_date_str = ''; // 변동분 계산 시작일
        $closing_date_str = '';

        if ($closing_stock_data) {
            $closing_date_str = $closing_stock_data['invt_date'];
            $base_stock = (int)$closing_stock_data['stock_quantity'];
            $calc_start_date_str = date('Y-m-d', strtotime($closing_date_str . ' +1 day'));
        } else {
            $calc_start_date_str = date('Y-m-d', strtotime($from_date . ' -2 month'));
        }
        
        // [신규] 2. 위에서 결정된 시작일부터 필요한 모든 변동분 데이터를 조회
        $tongwan_data = [];
        if ($calc_start_date_str <= $to_date) {
            $sql_tongwan_silje_deltas = "
                WITH Calendar AS (
                    SELECT DATE_ADD(?, INTERVAL (t.n + u.n + v.n) DAY) AS dt
                    FROM (
                        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                        SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
                    ) t,
                    (
                        SELECT 0 AS n UNION ALL SELECT 10 UNION ALL SELECT 20 UNION ALL SELECT 30 UNION ALL SELECT 40 UNION ALL
                        SELECT 50 UNION ALL SELECT 60 UNION ALL SELECT 70 UNION ALL SELECT 80 UNION ALL SELECT 90
                    ) u,
                    (
                        SELECT 0 AS n UNION ALL SELECT 100 UNION ALL SELECT 200 UNION ALL SELECT 300
                    ) v
                    WHERE DATE_ADD(?, INTERVAL (t.n + u.n + v.n) DAY) <= ?
                ),
                DailyTongwan AS (
                    SELECT
                        p.import_dt AS dt,
                        SUM(p.vol) AS tongwan_qty
                    FROM tpurchase_imp p
                    WHERE
                        TRIM(REPLACE(REPLACE(p.spec, '\r', ''), '\n', '')) = ? AND p.import_dt BETWEEN ? AND ?
                    GROUP BY p.import_dt
                ),
                RawImport AS (
                    SELECT
                        awb_suffix AS bl_no,
                        MAX(spec) AS spec,
                        MAX(import_dt) AS import_dt,
                        MAX(awb) AS awb,
                        MAX(vol) AS vol,
                        MAX(weight) AS weight
                    FROM tpurchase_imp
                    WHERE import_dt >= DATE_SUB(?, INTERVAL 1 MONTH)
                    GROUP BY awb_suffix
                ),
                RawExportLogic AS (
                    SELECT
                        DATE(a.increate_dt) AS dt,
                        CASE
                            WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.vol
                            ELSE COUNT(1)
                        END AS export_vol
                    FROM tbarcodein a
                    LEFT JOIN RawImport ri ON ri.bl_no = LEFT(a.lc_no, 4)
                    WHERE
                        DATE(a.increate_dt) BETWEEN ? AND ?
                        AND ri.import_dt IS NOT NULL
                        AND ri.import_dt >= DATE_SUB(DATE(a.increate_dt), INTERVAL 1 MONTH)
                        AND TRIM(REPLACE(REPLACE(ri.spec, '\r', ''), '\n', '')) = ?
                    GROUP BY
                        a.lc_no, a.increate_dt, ri.import_dt, ri.spec, ri.awb, ri.vol, ri.weight
                ),
                DailySilje AS (
                    SELECT
                        dt,
                        SUM(export_vol) AS silje_qty
                    FROM RawExportLogic
                    GROUP BY dt
                )
                SELECT
                    c.dt AS `date`,
                    COALESCE(t.tongwan_qty, 0) AS 'tongwan',
                    COALESCE(s.silje_qty, 0) AS 'silje'
                FROM Calendar c
                LEFT JOIN DailyTongwan t ON c.dt = t.dt
                LEFT JOIN DailySilje s ON c.dt = s.dt
                ORDER BY c.dt;
            ";
            
            $stmt_tongwan_silje_final = $conn->prepare($sql_tongwan_silje_deltas);
            if ($stmt_tongwan_silje_final === false) throw new Exception("통관/실적 쿼리 준비 실패: " . $conn->error);
            
            $stmt_tongwan_silje_final->bind_param("ssssssssss",
                $calc_start_date_str, $calc_start_date_str, $to_date,       // Calendar
                $product_spec, $calc_start_date_str, $to_date, // DailyTongwan
                $calc_start_date_str, $calc_start_date_str, $to_date, $product_spec  // DailySilje
            );
            $stmt_tongwan_silje_final->execute();
            $result_tongwan_silje = $stmt_tongwan_silje_final->get_result();
            
            while ($row = $result_tongwan_silje->fetch_assoc()) {
                $tongwan_data[$row['date']] = [ 'tongwan' => $row['tongwan'], 'silje' => $row['silje'] ];
            }
            $stmt_tongwan_silje_final->close();
        }

        // [신규] 3. 초기 재고(initial_stock) 계산
        $initial_stock = $base_stock;
        if ($closing_date_str !== $from_date && $calc_start_date_str < $from_date) {
            $initial_period = new DatePeriod(
                new DateTime($calc_start_date_str),
                new DateInterval('P1D'),
                new DateTime($from_date) // from_date 직전까지
            );
            foreach ($initial_period as $d) {
                $d_str = $d->format('Y-m-d');
                $initial_stock += ($tongwan_data[$d_str]['tongwan'] ?? 0) - ($tongwan_data[$d_str]['silje'] ?? 0);
            }
        }
        
        $period = new DatePeriod(
            new DateTime($from_date),
            new DateInterval('P1D'),
            (new DateTime($to_date))->modify('+1 day')
        );
        
        $saengsan_stock = 0;
        $prev_date = null;
        $tongwan_stock_for_today = 0;
        $is_first_day = true;
        $next_sunday = date('Y-m-d', strtotime($from_date . ' +7 days')); // 차주 일요일 계산
        $today_date = date('Y-m-d'); // 오늘 날짜

        // [신규] 순차적 재고 계산을 위한 누적 재고 변수
        $cumulative_tongwan_stock = $base_stock; // 기준 재고로 초기화
        
        // 날짜별로 반복하면서 데이터 취합
        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');

            // 당일 데이터 가져오기
            $tongwan = $tongwan_data[$current_date]['tongwan'] ?? 0;
            $silje = $tongwan_data[$current_date]['silje'] ?? 0;

            // [수정] 순차적 재고 계산: 기준일 이후부터만 변동분 반영
            if ($current_date > $closing_date_str) {
                $cumulative_tongwan_stock += $tongwan - $silje;
            }
            $tongwan_stock_for_today = $cumulative_tongwan_stock;

            $yesangsaeng = round($silje * 1.7);
            $silsaeng = $daily_values[$current_date]['silsaeng'] ?? 0;
            $silpan = $daily_values[$current_date]['silpan'] ?? 0;
            $yesangpan = $daily_values[$current_date]['yesangpan'] ?? 0;
            $saengsanjaego = $daily_values[$current_date]['saengsanjaego'] ?? null;

            // 일별 데이터 저장 (0 값은 '-'로 표시)
            $result_data['통관계획'][$current_date] = ($tongwan == 0) ? '-' : round($tongwan, 1);
            $result_data['실제작업'][$current_date] = ($silje == 0) ? '-' : round($silje, 1);
            $result_data['통관재고'][$current_date] = ($current_date > $today_date) ? '-' : (($tongwan_stock_for_today == 0) ? '-' : round($tongwan_stock_for_today, 1));
            $result_data['예상생산'][$current_date] = ($yesangsaeng == 0) ? '-' : round($yesangsaeng, 1);
            $result_data['실제생산'][$current_date] = ($silsaeng == 0) ? '-' : round($silsaeng, 1);
            $result_data['예상판매'][$current_date] = ($yesangpan == 0) ? '-' : round($yesangpan, 1);
            $result_data['실제판매'][$current_date] = ($silpan == 0) ? '-' : round($silpan, 1);

            if ($saengsanjaego !== null) {
                $saengsan_stock = $saengsanjaego;
            } else {
                if ($current_date === $today_date) {
                    // 오늘 날짜의 재고는 스냅샷 생성 로직과 동일하게 tbarcodeout에서 직접 계산
                    $sql_today_stock = "
                        SELECT COALESCE(SUM(CASE WHEN prod_nm = '연어필렛' AND store_code != '002' THEN 1 ELSE 0 END), 0)
                        FROM `tbarcodeout`
                        WHERE store_code IN ('000','001','002','003','004') AND invoice_yn = 'n'
                    ";
                    $stmt_today_stock = $conn->prepare($sql_today_stock);
                    if ($stmt_today_stock) {
                        $stmt_today_stock->execute();
                        $result_today_stock = $stmt_today_stock->get_result();
                        $row_today_stock = $result_today_stock->fetch_row();
                        $saengsan_stock = $row_today_stock[0] ?? 0;
                        $stmt_today_stock->close();
                    }
                } else {
                    // DB에 없는 다른 날짜는 전날 재고를 기반으로 계산
                    if (isset($prev_date) && $current_date < $today_date) {
                        $prev_silsaeng = $daily_values[$prev_date]['silsaeng'] ?? 0;
                        $prev_silpan = $daily_values[$prev_date]['silpan'] ?? 0;
                        $saengsan_stock += $prev_silsaeng - $prev_silpan;
                    }
                }
            }
            $result_data['생산재고'][$current_date] = ($current_date > $today_date) ? '-' : round($saengsan_stock, 1);
            $prev_date = $current_date;

            // [사용자 요청] 차주 일요일의 통관재고(stock_quantity)를 저장/업데이트
            // 단, 조회된 최초 기준일 데이터는 업데이트하지 않으며, 미래 날짜 데이터는 생성/업데이트하지 않음
            if ($current_date === $next_sunday) {
                // [임시] 2025-09-21 데이터는 수정하지 않음
                if (($min_stock_date === null || $current_date > $min_stock_date) && $current_date <= $today_date && $current_date !== '2025-09-21') {
                    $stock_to_save = round($tongwan_stock_for_today, 1);
                    $sql_update_stock = "
                        INSERT INTO tbarcodeout_invt (invt_date, stock_quantity)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity)
                    ";
                    $stmt_update_stock = $conn->prepare($sql_update_stock);
                    if ($stmt_update_stock) {
                        $stmt_update_stock->bind_param("sd", $current_date, $stock_to_save);
                        $stmt_update_stock->execute();
                        if ($stmt_update_stock->error) {
                            error_log("[subul_pd_srv.php] Failed to update stock_quantity for $current_date: " . $stmt_update_stock->error, 3, $logFilePath);
                        }
                        $stmt_update_stock->close();
                    } else {
                        error_log("[subul_pd_srv.php] Failed to prepare statement for stock_quantity update: " . $conn->error, 3, $logFilePath);
                    }
                }
            }

            // 합계 누적
            $result_data['통관계획']['합계'] += $tongwan;
            $result_data['실제작업']['합계'] += $silje;
            $result_data['예상생산']['합계'] += $yesangsaeng;
            $result_data['실제생산']['합계'] += $silsaeng;
            $result_data['예상판매']['합계'] += $yesangpan;
            $result_data['실제판매']['합계'] += $silpan;
        }

        // 재고의 합계는 마지막 날의 재고로 설정
        $result_data['생산재고']['합계'] = round($saengsan_stock, 1);
        $result_data['통관재고']['합계'] = round($tongwan_stock_for_today, 1);

        return array_values($result_data);

    } catch (Exception $e) {
        error_log("[subul_pd_srv.php] Error in getPdData: " . $e->getMessage(), 3, $logFilePath);
        // 클라이언트에는 항상 JSON 형태로 에러 메시지를 반환
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}