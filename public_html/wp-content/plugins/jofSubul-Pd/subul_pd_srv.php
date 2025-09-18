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

$conn->close();

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
        
        // [추가] SQL 로깅
        $params_temp = [$product_spec];
        $completed_sql_temp = preg_replace_callback(
            '/\?/',
            function ($matches) use (&$params_temp, $conn) {
                $param = array_shift($params_temp);
                return "'" . $conn->real_escape_string($param) . "'";
            },
            $sql_temp_table
        );
        error_log("[subul_pd_srv.php] Create Temp Table SQL: " . $completed_sql_temp, 3, $logFilePath);

        $stmt_temp->bind_param("s", $product_spec);
        $stmt_temp->execute();
        $stmt_temp->close();

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

        // [추가] SQL 로깅
        $params_tongwan_silje = [
            $extended_from_date, $extended_from_date, $to_date, // Calendar
            $product_spec, $extended_from_date, $to_date, // DailyTongwan
            $extended_from_date, $extended_from_date, $to_date, $product_spec // DailySilje (RawImport + RawExportLogic)
        ];
        $completed_sql_tongwan_silje = preg_replace_callback(
            '/\?/',
            function ($matches) use (&$params_tongwan_silje, $conn) {
                $param = array_shift($params_tongwan_silje);
                return "'" . $conn->real_escape_string($param) . "'";
            },
            $sql_tongwan_silje_deltas
        );
        error_log("[subul_pd_srv.php] Tongwan/Silje Deltas SQL: " . $completed_sql_tongwan_silje, 3, $logFilePath);
        
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
        
        // [추가] SQL 로깅
        $params_daily = [
            $from_date, $to_date,
            $product_name, $from_date, $to_date,
            $from_date, $to_date,
            $from_date, $to_date
        ];
        $completed_sql_daily = preg_replace_callback(
            '/\?/',
            function ($matches) use (&$params_daily, $conn) {
                $param = array_shift($params_daily);
                return "'" . $conn->real_escape_string($param) . "'";
            },
            $sql_daily
        );
        error_log("[subul_pd_srv.php] Daily Data SQL: " . $completed_sql_daily, 3, $logFilePath);

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

        // 날짜별로 반복하면서 데이터 취합
        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');

            // 당일 데이터 가져오기
            $tongwan = $tongwan_data[$current_date]['tongwan'] ?? 0;
            $silje = $tongwan_data[$current_date]['silje'] ?? 0;

            if ($is_first_day) {
                // [신규] 첫날은 미리 계산한 초기 재고를 할당
                $tongwan_stock_for_today = $initial_stock;
                $is_first_day = false;
            } else {
                // [신규] 둘째날부터는 어제 재고에 오늘 변동분을 더함
                if ($current_date <= $today_date) {
                    $tongwan_stock_for_today += ($tongwan - $silje);
                }
            }

            $yesangsaeng = round($silje * 1.7);
            $silsaeng = $daily_values[$current_date]['silsaeng'] ?? 0;
            $silpan = $daily_values[$current_date]['silpan'] ?? 0;
            $yesangpan = $daily_values[$current_date]['yesangpan'] ?? 0;
            $saengsanjaego = $daily_values[$current_date]['saengsanjaego'] ?? null;

            // 일별 데이터 저장
            $result_data['통관계획'][$current_date] = round($tongwan, 1);
            $result_data['실제작업'][$current_date] = round($silje, 1);
            $result_data['통관재고'][$current_date] = ($current_date > $today_date) ? '-' : round($tongwan_stock_for_today, 1);
            $result_data['예상생산'][$current_date] = round($yesangsaeng, 1);
            $result_data['실제생산'][$current_date] = round($silsaeng, 1);
            $result_data['예상판매'][$current_date] = round($yesangpan, 1);
            $result_data['실제판매'][$current_date] = round($silpan, 1);

            if ($saengsanjaego !== null) {
                $saengsan_stock = $saengsanjaego;
            } else {
                // 전날 생산재고가 있으면 오늘 생산량과 판매량을 반영하여 계산
                if (isset($prev_date) && $current_date <= $today_date) {
                    $prev_silsaeng = $daily_values[$prev_date]['silsaeng'] ?? 0;
                    $prev_silpan = $daily_values[$prev_date]['silpan'] ?? 0;
                    $saengsan_stock += $prev_silsaeng - $prev_silpan;
                }
            }
            $result_data['생산재고'][$current_date] = ($current_date > $today_date) ? '-' : round($saengsan_stock, 1);
            $prev_date = $current_date;

            // [사용자 요청] 차주 일요일의 통관재고(stock_quantity)를 저장/업데이트
            // 단, 조회된 최초 기준일 데이터는 업데이트하지 않으며, 미래 날짜 데이터는 생성/업데이트하지 않음
            if ($current_date === $next_sunday) {
                if (($min_stock_date === null || $current_date > $min_stock_date) && $current_date <= $today_date) {
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