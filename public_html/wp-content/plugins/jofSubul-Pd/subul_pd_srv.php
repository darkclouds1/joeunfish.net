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

function get_tongwan_stock_by_date($date, $product_spec, $conn) {
    // 임시 테이블을 사용하도록 쿼리 수정
    $sql = "
        SELECT COALESCE(SUM(p.vol), 0) AS stock
        FROM tpurchase_imp p
        WHERE
            p.spec = ?
            AND p.import_dt <= ?
            AND p.import_dt >= DATE_SUB(?, INTERVAL 1 MONTH)
            AND NOT EXISTS (
                SELECT 1
                FROM tbarcodein b
                WHERE b.lc_no_prefix = p.awb_suffix
            );
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // 오류 로깅 개선
        error_log("SQL prepare failed in get_tongwan_stock_by_date: " . $conn->error . ", SQL: " . $sql);
        return 0;
    }
    
    $stmt->bind_param("sss", $product_spec, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = 0;
    if ($row = $result->fetch_assoc()) {
        $stock = $row['stock'] ?? 0;
    }
    $stmt->close();
    return $stock;
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
                INNER JOIN tpurchase_imp b ON b.awb_suffix = a.lc_no_prefix 
                WHERE b.spec = ?
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
        // error_log("[subul_pd_srv.php] Create Temp Table SQL: " . $completed_sql_temp, 3, $logFilePath);

        $stmt_temp->bind_param("s", $product_spec);
        $stmt_temp->execute();
        $stmt_temp->close();

        // [수정] 시작일 하루 전 재고를 한 번만 계산하여 초기값으로 설정 (최적화된 함수 사용)
        $yesterday_of_from = date('Y-m-d', strtotime($from_date . ' -1 day'));
        $tongwan_stock = get_tongwan_stock_by_date($yesterday_of_from, $product_spec, $conn);

        // '통관계획' 조회를 위한 날짜 범위 설정 (최근 1개월)
        $tongwan_from_date = date('Y-m-d', strtotime('-1 month'));
        $tongwan_to_date = date('Y-m-d');

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
                p.spec = ? AND p.import_dt BETWEEN ? AND ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tbarcodein b
                    WHERE b.lc_no_prefix = p.awb_suffix
                )
            GROUP BY p.import_dt
        ),
        DailySilje AS (
            SELECT
                DATE(a.increate_dt) as dt,
                SUM(
                    CASE
                        WHEN stc.total_rows = 1 THEN b.vol
                        ELSE 1
                    END
                ) AS silje_qty
            FROM tbarcodein a
            INNER JOIN tpurchase_imp b ON b.awb_suffix = a.lc_no_prefix 
            JOIN temp_shipment_counts stc ON b.serial_no = stc.serial_no
            WHERE
                b.spec = ?
                AND DATE(a.increate_dt) BETWEEN ? AND ?
            GROUP BY DATE(a.increate_dt)
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
            $from_date, $from_date, $to_date, // Calendar
            $product_spec, $tongwan_from_date, $tongwan_to_date, // DailyTongwan
            $product_spec, $from_date, $to_date  // DailySilje
        ];
        $completed_sql_tongwan_silje = preg_replace_callback(
            '/\?/',
            function ($matches) use (&$params_tongwan_silje, $conn) {
                $param = array_shift($params_tongwan_silje);
                return "'" . $conn->real_escape_string($param) . "'";
            },
            $sql_tongwan_silje_deltas
        );
        // error_log("[subul_pd_srv.php] Tongwan/Silje Deltas SQL: " . $completed_sql_tongwan_silje, 3, $logFilePath);
        
        // [수정] 최적화로 인해 파라미터 개수가 9개로 변경
        $stmt_tongwan_silje->bind_param("sssssssss",
            $from_date, $from_date, $to_date,       // Calendar
            $product_spec, $tongwan_from_date, $tongwan_to_date, // DailyTongwan
            $product_spec, $from_date, $to_date  // DailySilje
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
            -- 실생산 (생산내역): 행(row)당 1개의 박스로 계산
            SELECT manu_date AS `date`, 'silsaeng' AS `type`, 1 AS `value`
            FROM tbarcodeout WHERE origin_store_code = '000' AND prod_nm LIKE CONCAT('%', ?, '%') AND manu_date BETWEEN ? AND ?
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
            $product_name, $from_date, $to_date,
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
        // error_log("[subul_pd_srv.php] Daily Data SQL: " . $completed_sql_daily, 3, $logFilePath);

        $stmt_daily->bind_param("ssssssssss", 
            $product_name, $from_date, $to_date,
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
        
        // 4. [수정] 최종 데이터 가공
        $result_data = [];
        $categories = ['통관계획', '실제작업', '예상생산', '실제생산', '예상판매', '실제판매', '생산재고', '통관재고'];
        foreach ($categories as $cat) {
            $result_data[$cat] = ['구분' => $cat, '합계' => 0];
        }
        
        $period = new DatePeriod(
            new DateTime($from_date),
            new DateInterval('P1D'),
            (new DateTime($to_date))->modify('+1 day')
        );
        
        $saengsan_stock = 0;
        $prev_date = null;

        // 날짜별로 반복하면서 데이터 취합
        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');

            // [제거] 루프 내에서 재고를 반복 계산하던 코드 제거
            // $tongwan_stock = get_tongwan_stock_by_date($current_date, $product_spec, $conn);

            // 당일 데이터 가져오기
            $tongwan = $tongwan_data[$current_date]['tongwan'] ?? 0;
            $silje = $tongwan_data[$current_date]['silje'] ?? 0;
            
            // [추가] 일별 변동분을 반영하여 재고 업데이트
            $tongwan_stock += $tongwan - $silje;

            $yesangsaeng = round($silje * 1.7);
            $silsaeng = $daily_values[$current_date]['silsaeng'] ?? 0;
            $silpan = $daily_values[$current_date]['silpan'] ?? 0;
            $yesangpan = $daily_values[$current_date]['yesangpan'] ?? 0;
            $saengsanjaego = $daily_values[$current_date]['saengsanjaego'] ?? null;

            // 일별 데이터 저장
            $result_data['통관계획'][$current_date] = round($tongwan, 1);
            $result_data['실제작업'][$current_date] = round($silje, 1);
            $result_data['통관재고'][$current_date] = round($tongwan_stock, 1);
            $result_data['예상생산'][$current_date] = round($yesangsaeng, 1);
            $result_data['실제생산'][$current_date] = round($silsaeng, 1);
            $result_data['예상판매'][$current_date] = round($yesangpan, 1);
            $result_data['실제판매'][$current_date] = round($silpan, 1);

            if ($saengsanjaego !== null) {
                $saengsan_stock = $saengsanjaego;
            } else {
                // 전날 생산재고가 있으면 오늘 생산량과 판매량을 반영하여 계산
                if (isset($prev_date)) {
                    $prev_silsaeng = $daily_values[$prev_date]['silsaeng'] ?? 0;
                    $prev_silpan = $daily_values[$prev_date]['silpan'] ?? 0;
                    $saengsan_stock += $prev_silsaeng - $prev_silpan;
                }
            }
            $result_data['생산재고'][$current_date] = round($saengsan_stock, 1);
            $prev_date = $current_date;

            // 합계 누적
            $result_data['통관계획']['합계'] += $tongwan;
            $result_data['실제작업']['합계'] += $silje;
            $result_data['예상생산']['합계'] += $yesangsaeng;
            $result_data['실제생산']['합계'] += $silsaeng;
            $result_data['예상판매']['합계'] += $yesangpan;
            $result_data['실제판매']['합계'] += $silpan;
        }

        // 합계 처리
        foreach ($categories as $cat) {
            if ($cat !== '생산재고' && $cat !== '통관재고') {
                 $result_data[$cat]['합계'] = round($result_data[$cat]['합계'], 1);
            }
        }

        // 재고의 합계는 마지막 날의 재고로 설정
        $result_data['생산재고']['합계'] = round($saengsan_stock, 1);
        $result_data['통관재고']['합계'] = round($tongwan_stock, 1);

        return array_values($result_data);

    } catch (Exception $e) {
        error_log("[subul_pd_srv.php] Error in getPdData: " . $e->getMessage(), 3, $logFilePath);
        // 클라이언트에는 항상 JSON 형태로 에러 메시지를 반환
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}