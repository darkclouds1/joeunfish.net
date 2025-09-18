<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
date_default_timezone_set('Asia/Seoul');

// 엑셀 파일 생성 라이브러리 로드
require($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
// require('/var/www/fish/vendor/autoload.php');
// use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
$pdo = $dbConn->pdo_conn();

mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = __DIR__ . "/logfile.log";
error_log("\n[ln15] logFilePath=" . $logFilePath, 3, $logFilePath);

// 파라미터
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
error_log("\n[ln20] category=" . $category, 3, $logFilePath);

isset($_GET['start_date']) ? $start_date = $_GET['start_date'] : $start_date = "";
isset($_GET['end_date']) ? $end_date = $_GET['end_date'] : $end_date = "";
isset($_GET['bl_no']) ? $bl_no = $_GET['bl_no'] : $bl_no = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";


// GS 상품코드|상품명 조회 => subul_gs_srv.php 로 이동
// if ($category === "gs_prod_code") {
//     $sql = "SELECT store_code, code_exp FROM tcomcodegs WHERE grp_code = '2' AND use_yn = 'y'";
//     $result = $conn->query($sql);
//     $prodCodeList = array();
//     while ($row = $result->fetch_assoc()) {
//         $prodCodeList[] = $row;
//     }
//     echo json_encode($prodCodeList);
// }

// category= BL(생산일의 송장번호 찾기) , tbarcodeout 의 해당날짜의 lc_no 를 가져옴
if ($category === "bl") {
    $sql = " SELECT 
                lc_no AS bl_no
            FROM 
                `tbarcodeout`
            WHERE manu_date= ?
            GROUP BY 
                lc_no
            ORDER BY lc_no ASC
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $start_date);  // start_date를 파라미터로 바인딩
    $stmt->execute();
    $result = $stmt->get_result();

    $lcNumber = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $lcNumber[] = $row;
        }
    }
    // JSON 형식으로 데이터 응답
    echo json_encode($lcNumber);
    $conn->close();
}

// CO 수불 조회
if ($category === "search_CoDay") {

    // GS 수불 데이터 조회
    $data = getCoDayData($start_date);

    // 아래는 기존처럼 출력/엑셀/JSON 처리
    if (isset($_GET['print']) && $_GET['print'] === 'true') {
        generatePrintHTML($data, $start_date);
    } else if (isset($_GET['excel']) && $_GET['excel'] === 'true') {
        generateExcelFile($data, $start_date);
    } else {
        $json = json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            error_log('json_encode error: ' . json_last_error_msg(), 3, $logFilePath);
            echo json_encode(['status' => 'error', 'message' => 'JSON 인코딩 실패: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo $json;
        exit;
    }
}
$conn->close();

// CO 수불 쿼리 조회
function getCoDayData($start_date)
{
    global $conn, $logFilePath;

    $dateObj = DateTime::createFromFormat('Y-m-d', $start_date) ?: DateTime::createFromFormat('Ymd', $start_date);
    if ($dateObj) {
        $dateObj->modify('+1 day'); // 원료출고는 납품일자(기준일자+1일)로 조회
        $delivery_dt = $dateObj->format('Y-m-d');

        // $dateObj->modify('-1 day'); // 생산일자는 공급일자(기준일자-1일)로 조회
        // $make_dt = $dateObj->format('Ymd');
    }
    $make_dt = $start_date;
    $increate_dt = $start_date;

    $data = array();
    try {
        // 1. SET 쿼리 별도 실행
        $setQuery = "SET @make_dt := '$make_dt', @delivery_dt := '$delivery_dt', @increate_dt := '$increate_dt'";
        if (!$conn->query($setQuery)) {
            error_log("[ln_co] SET 쿼리 실패: " . $conn->error, 3, $logFilePath);
            return ['status' => 'error', 'message' => 'SET 쿼리 실패: ' . $conn->error];
        }

        $sql = " WITH 
            RawImport AS (
                SELECT 
                    RIGHT(awb, 4) AS bl_no,
                    spec,
                    import_dt,
                    awb,
                    vol,
                    weight
                FROM tpurchase_imp
                WHERE import_dt >= DATE_SUB(@make_dt, INTERVAL 1 MONTH)      -- 원료입고 통관일은 생산일 기준 1개월 전까지
                GROUP BY bl_no
            ),
            -- 원료출고 정보 (BL별)
            RawExport AS (
                SELECT 
                    a.lc_no AS bl_no,
                    a.increate_dt AS export_date,
                    ri.import_dt AS import_date,
                    ri.spec AS spec,
                    ri.awb AS awb,
                    CASE 
                        WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.vol
                        ELSE COUNT(1)
                    END AS export_vol,
                    CASE 
                        WHEN COUNT(1) = 1 AND ri.spec != 'Salmon Trim B BB' AND LENGTH(a.lc_no) = 4 THEN ri.weight
                        ELSE SUM(a.weight)
                    END AS export_weight
                FROM 
                    tbarcodein a
                    LEFT JOIN RawImport ri ON RIGHT(ri.awb, 4) = LEFT(a.lc_no, 4) AND ri.import_dt >= DATE_SUB(DATE(a.increate_dt), INTERVAL 1 MONTH)  -- 통관일 기준 1개월 내
                WHERE 
                    DATE(a.increate_dt) = @make_dt
                GROUP BY 
                    a.lc_no,
                    a.increate_dt,
                    ri.import_dt,
                    ri.spec,
                    ri.awb,
                    ri.vol,
                    ri.weight
                ORDER BY bl_no ASC
            ),

            -- 생산내역 (제품별)
            Production AS (
                SELECT 
                    lc_no AS bl_no,
                    DATE(create_dt) AS manu_date,
                    prod_nm,
                    COUNT(serial_no) AS box_count,
                    CAST(SUM(weight) AS DECIMAL(10,1)) AS prod_weight
                FROM 
                    tbarcodeout
                WHERE 
                    DATE(create_dt) = @make_dt
                GROUP BY 
                    lc_no,
                    prod_nm,
                    DATE(create_dt)
            ),

            -- 생산합계 정보 (연어필렛)
            ProductionTotalFillet AS (
                SELECT 
                    '전체' AS bl_no,
                    MAX(b.import_dt) AS import_date,
                    DATE(a.create_dt) AS manu_date,
                    COUNT(1) AS total_boxes,
                    CAST(SUM(a.weight) AS DECIMAL(10,1)) AS total_prod_weight
                FROM 
                    tbarcodeout a
                    LEFT JOIN tpurchase_imp b ON RIGHT(b.awb, 4) = a.lc_no
                WHERE 
                    a.prod_nm NOT LIKE '%스테이크%'
                    AND DATE(a.create_dt) = @make_dt
            ),

            -- 생산합계 정보 (스테이크)
            ProductionTotalSteak AS (
                SELECT 
                    '전체' AS bl_no,
                    MAX(b.import_dt) AS import_date,
                    DATE(a.create_dt) AS manu_date,
                    COUNT(1) AS total_boxes,
                    CAST(SUM(a.weight) AS DECIMAL(10,1)) AS total_prod_weight
                FROM 
                    tbarcodeout a
                    LEFT JOIN tpurchase_imp b ON RIGHT(b.awb, 4) = a.lc_no
                WHERE 
                    a.prod_nm LIKE '%스테이크%'
                    AND DATE(a.create_dt) = @make_dt
            ),

            -- 기타판매 정보 (판매점별)
            OtherSales AS (
                SELECT 
                    lc_no AS bl_no,
                    b.import_dt AS import_date,
                    a.manu_date AS manu_date,
                    store_no,
                    COUNT(1) AS sales_count,
                    CAST(SUM(a.weight) AS DECIMAL(10,1)) AS sales_weight,
                    a.prod_nm
                FROM 
                    tbarcodeout a
                    LEFT JOIN tpurchase_imp b ON RIGHT(b.awb, 4) = a.lc_no
                WHERE 
                    a.store_code = '006'
                    AND a.manu_date = @delivery_dt
                GROUP BY 
                    lc_no,
                    store_no
            ),

            -- 기타합계 정보
            OtherSalesTotal AS (
                SELECT 
                    NULL AS bl_no,
                    prod_nm,
                    a.manu_date AS manu_date,
                    COUNT(1) AS total_sales_count,
                    CAST(SUM(a.weight) AS DECIMAL(10,1)) AS total_sales_weight
                FROM 
                    tbarcodeout a
                    LEFT JOIN tpurchase_imp b ON RIGHT(b.awb, 4) = a.lc_no
                WHERE 
                    a.store_code = '006'
                    AND a.manu_date = @delivery_dt
                GROUP BY 
                    prod_nm
            ),

            -- 합계 수율 계산
            RawExportTotalFillet AS (
                SELECT 
                    SUM(export_weight) AS total_export_weight
                FROM RawExport
                WHERE bl_no IN (
                    SELECT bl_no FROM Production WHERE prod_nm NOT LIKE '%스테이크%'
                )
            ),

            RawExportTotalSteak AS (
                SELECT 
                    SUM(export_weight) AS total_export_weight
                FROM RawExport
                WHERE bl_no IN (
                    SELECT bl_no FROM Production WHERE prod_nm LIKE '%스테이크%'
                )
            ),

            prod_list AS (
                SELECT DISTINCT prod_nm FROM tbarcodeout WHERE increate_dt = @delivery_dt
            ),

            StoreDelivery AS (
                SELECT
                    NULL AS bl_no,
                    @delivery_dt AS supply_dt,
                    a.manu_date AS manu_date,
                    tc.store_code,
                    IFNULL(REPLACE(RIGHT(tc.code_exp, 3), '점', ''), '') AS store_tail,  -- '점' 제거
                    IFNULL(tc.order_num, 9999) AS order_num,
                    IFNULL(COUNT(a.prod_nm), 0) AS count_and_vol,
                    IFNULL(CAST(SUM(a.weight) AS DECIMAL(10,1)), 0) AS delivery_weight,
                    p.prod_nm
                FROM 
                    tcomcode tc
                    CROSS JOIN prod_list p
                    LEFT JOIN tbarcodeout a
                        ON a.store_code = tc.store_code
                        AND a.prod_nm = p.prod_nm
                        -- AND a.invoice_yn = 'y'
                        AND a.increate_dt = @delivery_dt
                WHERE 
                    tc.grp_code = '1'
                    AND tc.use_yn = 'y'
                    AND tc.store_code NOT IN ('000', '001', '002', '003', '004', '005', '006','007')
                GROUP BY 
                    p.prod_nm, tc.store_code
            )
            -- 최종 결과 쿼리
            SELECT 
                gsort_key, data_type, bl_no, awb, date, vol, weight, prod_nm, yield_percent, store_no, NULL AS order_num
            FROM (
                SELECT 
                    0 AS gsort_key,
                    '원료입고' AS data_type,
                    ri.bl_no AS bl_no,
                    ri.awb AS awb,
                    ri.import_dt AS date,
                    ri.vol AS vol,
                    ri.weight AS weight,
                    ri.spec AS prod_nm,  -- spec 을 제품에 표기
                    NULL AS yield_percent,
                    NULL AS store_no
                FROM 
                    RawImport ri
                INNER JOIN RawExport re ON ri.bl_no = LEFT(re.bl_no, 4)
                WHERE ri.import_dt >= DATE_SUB(@make_dt, INTERVAL 1 MONTH)

                UNION ALL

                SELECT
                    1 AS gsort_key,
                    '원료출고' AS data_type,
                    re.bl_no AS bl_no,
                    re.awb AS awb,
                    DATE_FORMAT(re.export_date, '%Y-%m-%d') AS date,
                    re.export_vol AS vol,
                    re.export_weight AS weight,
                    re.spec AS prod_nm,  -- spec 을 제품에 표기
                    NULL AS yield_percent,
                    NULL AS store_no
                FROM 
                    RawExport re

                UNION ALL

                SELECT 
                    2 AS gsort_key,
                    '생산내역' AS data_type,
                    p.bl_no,
                    re.awb AS awb,
                    DATE_FORMAT(p.manu_date, '%Y-%m-%d') AS date,
                    p.box_count AS vol,
                    p.prod_weight AS weight,
                    p.prod_nm,
                    concat(ROUND((p.prod_weight / NULLIF(re.export_weight, 0)) * 100, 1), '%') AS yield_percent,
                    NULL AS store_no
                FROM 
                    Production p
                    LEFT JOIN RawExport re ON p.bl_no = re.bl_no

                UNION ALL

                SELECT 
                    3 AS gsort_key,
                    '생산소계' AS data_type,
                    NULL AS bl_no,
                    NULL AS awb,
                    DATE_FORMAT(p.manu_date, '%Y-%m-%d') AS date,
                    SUM(p.box_count) AS vol,
                    SUM(p.prod_weight) AS weight,
                    p.prod_nm,
                    CONCAT(
                        ROUND(
                            (SUM(p.prod_weight) / 
                            NULLIF(
                                (SELECT SUM(re.export_weight) 
                                FROM RawExport re 
                                WHERE (
                                    (re.spec = 'Salmon Trim B BB' AND p.prod_nm LIKE '%스테이크%')
                                    OR 
                                    (re.spec = 'Salmon Trim E BB' AND p.prod_nm LIKE '%연어필렛%')
                                )
                                )
                            , 0)
                        ) * 100, 1
                    ), '%') AS yield_percent,
                    NULL AS store_no
                FROM 
                    Production p
                GROUP BY 
                    p.prod_nm

                UNION ALL

                SELECT 
                    4 AS gsort_key,
                    '기타판매' AS data_type,
                    os.bl_no,
                    NULL AS awb,
                    DATE_FORMAT(os.manu_date, '%Y-%m-%d') AS date,
                    os.sales_count AS vol,
                    COALESCE(os.sales_weight, 0) AS weight,
                    os.prod_nm AS prod_nm,
                    NULL AS yield_percent,
                    COALESCE(os.store_no, '') AS store_no
                FROM 
                    OtherSales os

                UNION ALL

                SELECT 
                    5 AS gsort_key,
                    '기타합계' AS data_type,
                    ost.bl_no,
                    NULL AS awb,
                    DATE_FORMAT(ost.manu_date, '%Y-%m-%d') AS date,
                    ost.total_sales_count AS vol,
                    ost.total_sales_weight AS weight,
                    ost.prod_nm AS prod_nm,
                    NULL AS yield_percent,
                    NULL AS store_no
                FROM 
                    OtherSalesTotal ost

            )  AS NonDelivery
            
            UNION ALL

            SELECT 
                gsort_key, data_type, bl_no, awb, date, vol, weight, prod_nm, yield_percent, store_no, order_num
            FROM (
                SELECT
                    6 AS gsort_key,
                    '납품소계' AS data_type,
                    NULL AS bl_no,
                    NULL AS awb,
                    DATE_FORMAT(supply_dt, '%Y-%m-%d') AS date,
                    SUM(count_and_vol) AS vol,
                    SUM(delivery_weight) AS weight,
                    prod_nm,
                    NULL AS yield_percent,
                    NULL AS store_no,
                    NULL AS order_num
                FROM StoreDelivery
                GROUP BY prod_nm

                UNION ALL

                SELECT
                    7 AS gsort_key,
                    '납품내역' AS data_type,
                    bl_no,
                    NULL AS awb,
                    DATE_FORMAT(supply_dt, '%Y-%m-%d') AS date,
                    count_and_vol AS vol,
                    delivery_weight AS weight,
                    prod_nm,
                    NULL AS yield_percent,
                    concat(store_tail, '(', store_code, ')') AS store_no,
                    order_num
                FROM StoreDelivery
            ) AS Delivery

            ORDER BY gsort_key, prod_nm DESC, order_num ASC, awb DESC, bl_no ASC
            ";


        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $result->free();
        } else {
            error_log("[ln_co] 데이터 쿼리 실패: " . $conn->error, 3, $logFilePath);
            return ['status' => 'error', 'message' => '데이터 쿼리 실패: ' . $conn->error];
        }
    } catch (Exception $e) {
        error_log("[ln_co]Error: " . $e->getMessage(), 3, $logFilePath);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
    return $data;
}

// 프린트 화면 생성
function generatePrintHTML($result, $deliveryDate) {
    global $logFilePath;

    header('Content-Type: text/html; charset=UTF-8');

    // 날짜 포맷 변환
    $dateObj = DateTime::createFromFormat('Ymd', $deliveryDate) ?: DateTime::createFromFormat('Y-m-d', $deliveryDate);
    if ($dateObj) {
        $weekdays = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];
        $formattedDate = $dateObj->format('Y년 m월 d일 ') . $weekdays[(int)$dateObj->format('w')];
        // +1일(납품일자) 구하기
        $dateObj->modify('+1 day');

        // COSTCO 납품목록 95/27(화) 형식으로 변환
        $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
        $deliveryDatePlus1 = $dateObj->format('m/d') . '(' . $weekdays[(int)$dateObj->format('w')] . ')';

    } else {
        $formattedDate = $deliveryDate;
        $deliveryDatePlus1 = $deliveryDate;
    }

    // 결과를 배열로 변환
    $rows = $result;
    // error_log("\n[ln_co] rows=" . print_r($rows, true), 3, $logFilePath);

    // 1. 원료입고 데이터만 따로 배열로 분리
    $importArr = [];
    foreach ($rows as $row) {
        if (isset($row['data_type']) && $row['data_type'] === '원료입고') {
            $importArr[] = $row; // 전체 원료입고 데이터 저장
        }
    }

    // 2. bl_no별로 date를 매핑 (키: bl_no, 값: date)
    $importByBl = [];
    foreach ($importArr as $imp) {
        $bl_no = trim((string)$imp['bl_no']);
        $importByBl[$bl_no] = $imp['date'];
    }

    // 3. 로그로 확인
    // error_log("\n[ln_co] importArr=" . print_r($importArr, true), 3, $logFilePath);
    // error_log("\n[ln_co] importByBl=" . print_r($importByBl, true), 3, $logFilePath);

    // 1. 원료입고 bl_no별 prod_nm 매핑
    $importProdNmByBl = [];
    foreach ($importArr as $imp) {
        $bl_no = trim((string)$imp['bl_no']);
        $importProdNmByBl[$bl_no] = $imp['prod_nm'];
    }

    // 1. 원료출고 데이터 분류
    $raws = [];
    foreach ($rows as $row) {
        if (isset($row['data_type']) && $row['data_type'] === '원료출고') {
            $raws[] = $row;
        }
    }
    // error_log("\n[ln_co] raws=" . print_r($raws, true), 3, $logFilePath);

    // 3. 생산내역 데이터 분류
    $productions = [];
    foreach ($rows as $row) {
        if (isset($row['data_type']) && $row['data_type'] === '생산내역') {
            $productions[] = $row;
        }
    }
    // error_log("\n[ln_co] productions=" . print_r($productions, true), 3, $logFilePath);

    // 원료출고 bl_no별로 재구성
    $rawByBl = [];
    foreach ($raws as $r) {
        $rawByBl[trim((string)$r['bl_no'])] = $r;
    }

    // 생산내역 bl_no별로 재구성
    $prodByBl = [];
    foreach ($productions as $p) {
        $prodByBl[trim((string)$p['bl_no'])] = $p;
    }

    // 4. bl_no 집합 및 정렬
    $allBlNos = array_unique(array_merge(array_keys($rawByBl), array_keys($prodByBl)));

    // isSalmonTrimB 여부에 따라 정렬
    usort($allBlNos, function($a, $b) use ($importProdNmByBl) {
        $aIsTrimB = isset($importProdNmByBl[$a]) && $importProdNmByBl[$a] === "Salmon Trim B BB";
        $bIsTrimB = isset($importProdNmByBl[$b]) && $importProdNmByBl[$b] === "Salmon Trim B BB";
        
        if ($aIsTrimB === $bIsTrimB) {
            // 둘 다 TrimB이거나 둘 다 아닌 경우 bl_no로 정렬
            return strcmp($a, $b);
        }
        
        // TrimB인 것을 뒤로 보냄
        return $aIsTrimB ? 1 : -1;
    });

    $bothBlNos = [];
    $onlyOneBlNos = [];
    foreach ($allBlNos as $bl_no) {
        if (isset($rawByBl[$bl_no]) && isset($prodByBl[$bl_no])) {
            $bothBlNos[] = $bl_no;
        } else {
            $onlyOneBlNos[] = $bl_no;
        }
    }
    $finalBlNos = array_merge($bothBlNos, $onlyOneBlNos);
    $rowspan = count($finalBlNos);

    // 원료출고 소계 계산
    $rawBoxSum = 0;
    $rawKgSum = 0;
    foreach ($raws as $r) {
        $rawBoxSum += $r['vol'];
        $rawKgSum += $r['weight'];
    }

    // 생산내역 소계 계산
    $prodBoxSum = 0;
    $prodKgSum = 0;
    foreach ($productions as $p) {
        $prodBoxSum += $p['vol'];
        $prodKgSum += $p['weight'];
    }

    // 1. 생산소계 데이터만 추출
    $soGyeRows = [];
    foreach ($rows as $row) {
        if (isset($row['data_type']) && $row['data_type'] === '생산소계') {
            $soGyeRows[] = $row;
        }
    }

    // 2. prod_nm에 따라 분류
    $steakRow = null;
    $filletRow = null;
    foreach ($soGyeRows as $row) {
        if (strpos($row['prod_nm'], '스테이크') !== false) {
            $steakRow = $row;
        } else if (strpos($row['prod_nm'], '연어필렛') !== false) {
            $filletRow = $row;
        }
    }
    // error_log("\n[ln_co] steakRow=" . print_r($steakRow, true), 3, $logFilePath);
    // error_log("\n[ln_co] filletRow=" . print_r($filletRow, true), 3, $logFilePath);  

    // 1. 납품소계 데이터만 추출
    $deliverySoGyeRows = [];
    foreach ($rows as $row) {
        if (isset($row['data_type']) && $row['data_type'] === '납품소계') {
            $deliverySoGyeRows[] = $row;
        }
    }

    // 2. prod_nm에 따라 분류
    $deliverySteakRow = null;
    $deliveryFilletRow = null;
    foreach ($deliverySoGyeRows as $row) {
        if (strpos($row['prod_nm'], '스테이크') !== false) {
            $deliverySteakRow = $row;
        } else if (strpos($row['prod_nm'], '연어필렛') !== false) {
            $deliveryFilletRow = $row;
        }
    }

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "맑은 고딕", sans-serif; font-size: 12px; }
            .print-table {
                border-collapse: collapse;
                width: 100%;
            }
            .print-th, .print-td {
                border: 1px solid #333;
                padding: 4px 8px;
                text-align: center;
            }
            .center {
                text-align: center;
            }
            .print-th, .soGye-row {
                background-color: #f2f2f2 !important;
            }
            @media print {
                body { margin: 0; }
                .table { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
    <h1 style="text-align: center;">수불관리:CO::<?php echo $formattedDate; ?></h1>
    <table class="print-table">
        <thead>
            <tr>
                <th class="print-th" style="width:150px;">구분</th>
                <th class="print-th">B/L번호</th>
                <th class="print-th">Box</th>
                <th class="print-th">무게</th>
                <th class="print-th">통관일</th>
                <th class="print-th">구분</th>
                <th class="print-th">B/L번호</th>
                <th class="print-th">Box</th>
                <th class="print-th">무게</th>
                <th class="print-th">생산일</th>
                <th class="print-th">수율</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($finalBlNos as $rowIdx => $bl_no): ?>
                <tr>
                    <!-- 원료출고 -->
                    <?php if ($rowIdx === 0): ?>
                        <td class="print-td center" rowspan="<?= $rowspan ?>">원료출고</td>
                    <?php endif; ?>
                    <?php
                    $bl_no_str = trim((string)$bl_no);
                    $importDate = isset($importByBl[$bl_no_str]) ? $importByBl[$bl_no_str] : '';
                    if (isset($rawByBl[$bl_no_str])) {
                        $raw = $rawByBl[$bl_no_str];
                        $isSalmonTrimB = (isset($raw['prod_nm']) && $raw['prod_nm'] === "Salmon Trim B BB");
                        $blNoDisplay = $raw['bl_no'] . ($isSalmonTrimB ? 'A' : '');
                        $blNoStyle = $isSalmonTrimB ? 'color:red;' : '';
                    ?>
                        <td class="print-td center" style="<?= $blNoStyle ?>"><?= $blNoDisplay ?></td>
                        <td class="print-td center"><?= $raw['vol'] ?></td>
                        <td class="print-td center"><?= $raw['weight'] ?></td>
                        <td class="print-td center"><?= $importDate ?></td>
                    <?php } else { ?>
                        <td class="print-td"></td><td class="print-td"></td><td class="print-td"></td><td class="print-td"></td>
                    <?php } ?>

                    <!-- 생산내역 -->
                    <?php if ($rowIdx === 0): ?>
                        <td class="print-td center" rowspan="<?= $rowspan ?>">생산내역</td>
                    <?php endif; ?>
                    <?php
                    if (isset($prodByBl[$bl_no])) {
                        $prod = $prodByBl[$bl_no];
                        // 원료입고 prod_nm을 참조
                        $importProdNm = isset($importProdNmByBl[$bl_no]) ? $importProdNmByBl[$bl_no] : '';
                        $isSalmonTrimB = ($importProdNm === "Salmon Trim B BB");
                        $prodBlNoDisplay = $prod['bl_no'] . ($isSalmonTrimB ? 'A' : '');
                        $prodBlNoStyle = $isSalmonTrimB ? 'color:red;' : '';
                    ?>
                        <td class="print-td center" style="<?= $prodBlNoStyle ?>"><?= $prodBlNoDisplay ?></td>
                        <td class="print-td center"><?= $prod['vol'] ?></td>
                        <td class="print-td center"><?= $prod['weight'] ?></td>
                        <td class="print-td center"><?= $prod['date'] ?></td>
                        <td class="print-td center"><?= $prod['yield_percent'] ?></td>
                    <?php } else { ?>
                        <td class="print-td"></td><td class="print-td"></td><td class="print-td"></td><td class="print-td"></td><td class="print-td"></td>
                    <?php } ?>
                </tr>
            <?php endforeach; ?>
            <!-- 소계 행 수정 -->
            <!-- 연어필렛 소계 -->
            <?php
            // 연어필렛 소계 계산
            $rawFilletBoxSum = 0;
            $rawFilletKgSum = 0;
            $prodFilletBoxSum = 0;
            $prodFilletKgSum = 0;

            foreach ($raws as $r) {
                if (isset($r['prod_nm']) && $r['prod_nm'] === 'Salmon Trim E BB') {
                    $rawFilletBoxSum += $r['vol'];
                    $rawFilletKgSum += $r['weight'];
                }
            }

            foreach ($productions as $p) {
                if (strpos($p['prod_nm'], '연어필렛') !== false) {
                    $prodFilletBoxSum += $p['vol'];
                    $prodFilletKgSum += $p['weight'];
                }
            }
            ?>

            <!-- 스테이크 소계 -->
            <?php
            // 스테이크 소계 계산
            $rawSteakBoxSum = 0;
            $rawSteakKgSum = 0;
            $prodSteakBoxSum = 0;
            $prodSteakKgSum = 0;

            foreach ($raws as $r) {
                if (isset($r['prod_nm']) && $r['prod_nm'] === 'Salmon Trim B BB') {
                    $rawSteakBoxSum += $r['vol'];
                    $rawSteakKgSum += $r['weight'];
                }
            }

            foreach ($productions as $p) {
                if (strpos($p['prod_nm'], '스테이크') !== false) {
                    $prodSteakBoxSum += $p['vol'];
                    $prodSteakKgSum += $p['weight'];
                }
            }

            // 수율 계산
            $filletYield = $rawFilletKgSum > 0 ? round(($prodFilletKgSum / $rawFilletKgSum) * 100, 1) : 0;
            $steakYield = $rawSteakKgSum > 0 ? round(($prodSteakKgSum / $rawSteakKgSum) * 100, 1) : 0;
            ?>
            <tr>
                <td class="print-td center" rowspan="2">원료소계</td>
                <td class="print-td">T-E</td>
                <td class="print-td center"><?= $rawFilletBoxSum ?></td>
                <td class="print-td center"><?= $rawFilletKgSum ?></td>
                <td class="print-td"></td>
                <td class="print-td center" rowspan="2">생산소계</td>
                <td class="print-td">연어필렛</td>
                <td class="print-td center"><?= $prodFilletBoxSum ?></td>
                <td class="print-td center"><?= $prodFilletKgSum ?></td>
                <td class="print-td"></td>
                <td class="print-td center"><?= $filletYield ?>%</td>
            </tr>

            <tr>
                <td class="print-td">T-B</td>
                <td class="print-td center"><?= $rawSteakBoxSum ?></td>
                <td class="print-td center"><?= $rawSteakKgSum ?></td>
                <td class="print-td"></td>
                <td class="print-td">스테이크</td>
                <td class="print-td center"><?= $prodSteakBoxSum ?></td>
                <td class="print-td center"><?= $prodSteakKgSum ?></td>
                <td class="print-td"></td>
                <td class="print-td center"><?= $steakYield ?>%</td>
            </tr>

            <!-- 생산소계(스테이크/연어필렛) 행 제거 -->

            <!-- 빈 줄 -->
            <tr><td colspan="11" style="height:20px; border:none; background:none;"></td></tr>

            <!-- 연어필렛/스테이크 납품목록 테이블 -->
            <?php
            // 1. 납품내역 데이터만 추출
            $deliveryDetailRows = [];
            foreach ($rows as $row) {
                if (isset($row['data_type']) && $row['data_type'] === '납품내역') {
                    $deliveryDetailRows[] = $row;
                }
            }

            // 2. prod_nm에 따라 분류
            $filletDetails = [];
            $steakDetails = [];
            foreach ($deliveryDetailRows as $row) {
                if (strpos($row['prod_nm'], '스테이크') !== false) {
                    $steakDetails[] = $row;
                } else if (strpos($row['prod_nm'], '연어필렛') !== false) {
                    $filletDetails[] = $row;
                }
            }

            // 3. 납품점(18개) 추출 (store_no 기준, 정렬)
            $filletStores = array_column($filletDetails, 'store_no');
            $steakStores = array_column($steakDetails, 'store_no');
            $allStores = array_unique(array_merge($filletStores, $steakStores));
            
            // order_num으로 정렬하기 위해 store_no와 order_num을 매핑
            $storeOrderMap = [];
            foreach ($deliveryDetailRows as $row) {
                if (!isset($storeOrderMap[$row['store_no']])) {
                    $storeOrderMap[$row['store_no']] = $row['order_num'];
                }
            }
            
            // order_num 기준으로 정렬
            usort($allStores, function($a, $b) use ($storeOrderMap) {
                $orderA = isset($storeOrderMap[$a]) ? $storeOrderMap[$a] : 9999;
                $orderB = isset($storeOrderMap[$b]) ? $storeOrderMap[$b] : 9999;
                return $orderA - $orderB;
            });

            // 4. store_no별로 box, weight 매핑
            function getValueByStore($details, $store_no, $key) {
                foreach ($details as $row) {
                    if ($row['store_no'] === $store_no) {
                        // '점' 문자 제거
                        if ($key === 'store_no') {
                            return str_replace('점', '', $row[$key]);
                        }
                        // box와 weight 모두 0이면 빈칸
                        if (
                            isset($row['vol']) && isset($row['weight']) &&
                            $row['vol'] == 0 && $row['weight'] == 0
                        ) {
                            return '';
                        }
                        // 해당 값이 0이면 빈칸
                        if ($row[$key] == 0) {
                            return '';
                        }
                        return $row[$key];
                    }
                }
                return '';
            }
            ?>

            <div style="margin-top:30px;"></div>
            <table class="print-table">
                <caption style="caption-side: top; text-align:left; font-weight:bold; font-size:14px; margin-bottom:8px;">□ COSTCO 납품목록:: <?= $deliveryDatePlus1 ?></caption>
                <tbody>
                    <!-- 연어필렛 납품점 row -->
                    <tr>
                        <td class="print-td center" style="background:#f2f2f2; width:75px;">납품점</td>
                        <?php foreach ($allStores as $store_no): ?>
                            <td class="print-td center"><?= $store_no ?></td>
                        <?php endforeach; ?>
                        <td class="print-td center" style="background:#f2f2f2;">합계</td>
                    </tr>
                    <!-- 연어필렛 box row -->
                    <tr>
                        <td class="print-td center" rowspan="2" style="background:#f2f2f2;">연어필렛</td>
                        <?php foreach ($allStores as $store_no): ?>
                            <td class="print-td center"><?= getValueByStore($filletDetails, $store_no, 'vol') ?></td>
                        <?php endforeach; ?>
                        <td class="print-td center" style="background:#f2f2f2;">
                            <?php
                            $filletBoxTotal = 0;
                            foreach ($filletDetails as $detail) {
                                $filletBoxTotal += $detail['vol'];
                            }
                            echo $filletBoxTotal;
                            ?>
                        </td>
                    </tr>
                    <!-- 연어필렛 weight row -->
                    <?php 
                    $filletWeightTotal = 0;
                    foreach ($filletDetails as $detail) {
                        $filletWeightTotal += $detail['weight'];
                    }
                    ?>
                    <tr>
                        <?php foreach ($allStores as $store_no): ?>
                            <td class="print-td center"><?= getValueByStore($filletDetails, $store_no, 'weight') ?></td>
                        <?php endforeach; ?>
                        <td class="print-td center" style="background:#f2f2f2;"><?= $filletWeightTotal ?></td>
                    </tr>
                    <!-- 스테이크 box row -->
                    <tr>
                        <td class="print-td center" rowspan="2" style="background:#f2f2f2;">스테이크</td>
                        <?php foreach ($allStores as $store_no): ?>
                            <td class="print-td center"><?= getValueByStore($steakDetails, $store_no, 'vol') ?></td>
                        <?php endforeach; ?>
                        <td class="print-td center" style="background:#f2f2f2;">
                            <?php
                            $steakBoxTotal = 0;
                            foreach ($steakDetails as $detail) {
                                $steakBoxTotal += $detail['vol'];
                            }
                            echo $steakBoxTotal;
                            ?>
                        </td>
                    </tr>
                    <!-- 스테이크 weight row -->
                    <?php 
                    $steakWeightTotal = 0;
                    foreach ($steakDetails as $detail) {
                        $steakWeightTotal += $detail['weight'];
                    }
                    ?>
                    <tr>
                        <?php foreach ($allStores as $store_no): ?>
                            <td class="print-td center"><?= getValueByStore($steakDetails, $store_no, 'weight') ?></td>
                        <?php endforeach; ?>
                        <td class="print-td center" style="background:#f2f2f2;"><?= $steakWeightTotal ?></td>
                    </tr>
                </tbody>
            </table>
        </tbody>
    </table>
    </body>
    </html>
    <?php
}

function generateExcelFile($data, $deliveryDate) {

    // 날짜 포맷 변환
    $dateObj = DateTime::createFromFormat('Ymd', $deliveryDate) ?: DateTime::createFromFormat('Y-m-d', $deliveryDate);
    if ($dateObj) {
        $weekdays = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];
        $formattedDate = $dateObj->format('Y년 m월 d일 ') . $weekdays[(int)$dateObj->format('w')];
        // +1일(납품일자) 구하기
        $dateObj->modify('+1 day');
        $deliveryDatePlus1 = $dateObj->format('Y-m-d');
    } else {
        $formattedDate = $deliveryDate;
        $deliveryDatePlus1 = $deliveryDate;
    }

    // 새로운 스프레드시트 생성
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // 제목 설정
    $sheet->setCellValue('A1', '수불관리:CO::' . $formattedDate);
    $sheet->mergeCells('A1:K1');

    // 스타일 설정
    $titleStyle = [
        'font' => [
            'bold' => true,
            'size' => 14
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    $sheet->getStyle('A1')->applyFromArray($titleStyle);

    // 헤더 설정
    $headers = [
        '구분', 'B/L번호', 'Box', '무게', '통관일',
        '구분', 'B/L번호', 'Box', '무게', '생산일', '수율'
    ];
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setWidth(15);
    }
    $sheet->fromArray($headers, NULL, 'A2');

    // 헤더 스타일
    $headerStyle = [
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F2F2F2']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ];
    $sheet->getStyle('A2:K2')->applyFromArray($headerStyle);

    // 데이터 정리
    $currentRow = 3;
    $rawExportTotal = ['box' => 0, 'weight' => 0];
    $productionTotal = ['box' => 0, 'weight' => 0];

    // 원료출고와 생산내역 데이터 분리
    $rawExports = [];
    $productions = [];
    foreach ($data as $row) {
        if ($row['data_type'] === '원료출고') {
            $rawExports[] = $row;
            $rawExportTotal['box'] += $row['vol'];
            $rawExportTotal['weight'] += $row['weight'];
        } elseif ($row['data_type'] === '생산내역') {
            $productions[] = $row;
            $productionTotal['box'] += $row['vol'];
            $productionTotal['weight'] += $row['weight'];
        }
    }

    // 데이터 입력
    $maxRows = max(count($rawExports), count($productions));
    for ($i = 0; $i < $maxRows; $i++) {
        // 원료출고 데이터
        if ($i === 0) {
            $sheet->setCellValue('A' . $currentRow, '원료출고');
        }
        if (isset($rawExports[$i])) {
            $raw = $rawExports[$i];
            $sheet->setCellValue('B' . $currentRow, $raw['bl_no']);
            $sheet->setCellValue('C' . $currentRow, $raw['vol']);
            $sheet->setCellValue('D' . $currentRow, $raw['weight']);
            $sheet->setCellValue('E' . $currentRow, $raw['date']);
        }

        // 생산내역 데이터
        if ($i === 0) {
            $sheet->setCellValue('F' . $currentRow, '생산내역');
        }
        if (isset($productions[$i])) {
            $prod = $productions[$i];
            $sheet->setCellValue('G' . $currentRow, $prod['bl_no']);
            $sheet->setCellValue('H' . $currentRow, $prod['vol']);
            $sheet->setCellValue('I' . $currentRow, $prod['weight']);
            $sheet->setCellValue('J' . $currentRow, $prod['date']);
            $sheet->setCellValue('K' . $currentRow, $prod['yield_percent']);
        }

        $currentRow++;
    }

    // 소계 행 추가
    $sheet->setCellValue('A' . $currentRow, '원료소계');
    $sheet->setCellValue('C' . $currentRow, $rawExportTotal['box']);
    $sheet->setCellValue('D' . $currentRow, $rawExportTotal['weight']);
    $sheet->setCellValue('F' . $currentRow, '생산소계');
    $sheet->setCellValue('H' . $currentRow, $productionTotal['box']);
    $sheet->setCellValue('I' . $currentRow, $productionTotal['weight']);

    // 생산소계 행 (스테이크/연어필렛)
    $currentRow++;
    foreach ($data as $row) {
        if ($row['data_type'] === '생산소계') {
            if (strpos($row['prod_nm'], '스테이크') !== false) {
                $sheet->setCellValue('A' . $currentRow, '생산소계');
                $sheet->setCellValue('B' . $currentRow, '스테이크');
                $sheet->setCellValue('C' . $currentRow, $row['vol']);
                $sheet->setCellValue('D' . $currentRow, $row['weight']);
                $sheet->setCellValue('E' . $currentRow, $row['yield_percent']);
            } elseif (strpos($row['prod_nm'], '연어필렛') !== false) {
                $sheet->setCellValue('F' . $currentRow, '생산소계');
                $sheet->setCellValue('G' . $currentRow, '연어필렛');
                $sheet->setCellValue('H' . $currentRow, $row['vol']);
                $sheet->setCellValue('I' . $currentRow, $row['weight']);
                $sheet->setCellValue('J' . $currentRow, $row['date']);
                $sheet->setCellValue('K' . $currentRow, $row['yield_percent']);
            }
        }
    }

    // 납품소계 행 (스테이크/연어필렛)
    $currentRow++;
    foreach ($data as $row) {
        if ($row['data_type'] === '납품소계') {
            if (strpos($row['prod_nm'], '스테이크') !== false) {
                $sheet->setCellValue('A' . $currentRow, '납품소계');
                $sheet->setCellValue('B' . $currentRow, '스테이크');
                $sheet->setCellValue('C' . $currentRow, $row['vol']);
                $sheet->setCellValue('D' . $currentRow, $row['weight']);
                $sheet->setCellValue('E' . $currentRow, date('m/d(월)', strtotime($row['date'])));
            } elseif (strpos($row['prod_nm'], '연어필렛') !== false) {
                $sheet->setCellValue('F' . $currentRow, '납품소계');
                $sheet->setCellValue('G' . $currentRow, '연어필렛');
                $sheet->setCellValue('H' . $currentRow, $row['vol']);
                $sheet->setCellValue('I' . $currentRow, $row['weight']);
                $sheet->setCellValue('J' . $currentRow, date('m/d(월)', strtotime($row['date'])));
            }
        }
    }

    // COSTCO 납품목록 테이블 추가
    $currentRow += 2;
    $sheet->setCellValue('A' . $currentRow, '□ COSTCO 납품목록');
    $sheet->mergeCells('A'.$currentRow.':K'.$currentRow);
    $currentRow++;

    // 납품내역 데이터 정리
    $deliveryDetails = array_filter($data, function($row) {
        return $row['data_type'] === '납품내역';
    });

    // 납품점 목록 추출 및 정렬
    $stores = array_unique(array_column($deliveryDetails, 'store_no'));
    sort($stores);

    // 연어필렛 행
    $sheet->setCellValue('A' . $currentRow, '연어필렛');
    $sheet->mergeCells('A'.$currentRow.':A'.($currentRow+2));
    foreach ($stores as $idx => $store) {
        $sheet->setCellValue(chr(66 + $idx) . $currentRow, $store);
    }

    // Box 및 무게 데이터 입력
    $currentRow++;
    foreach ($deliveryDetails as $detail) {
        if (strpos($detail['prod_nm'], '연어필렛') !== false) {
            $storeIdx = array_search($detail['store_no'], $stores);
            if ($storeIdx !== false) {
                $sheet->setCellValue(chr(66 + $storeIdx) . $currentRow, $detail['vol']);
                $sheet->setCellValue(chr(66 + $storeIdx) . ($currentRow + 1), $detail['weight']);
            }
        }
    }
    $currentRow += 2;

    // 스테이크 행
    $sheet->setCellValue('A' . $currentRow, '스테이크');
    $sheet->mergeCells('A'.$currentRow.':A'.($currentRow+1));
    foreach ($deliveryDetails as $detail) {
        if (strpos($detail['prod_nm'], '스테이크') !== false) {
            $storeIdx = array_search($detail['store_no'], $stores);
            if ($storeIdx !== false) {
                $sheet->setCellValue(chr(66 + $storeIdx) . $currentRow, $detail['vol']);
                $sheet->setCellValue(chr(66 + $storeIdx) . ($currentRow + 1), $detail['weight']);
            }
        }
    }

    // 전체 테두리 스타일 적용
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    $sheet->getStyle('A2:K' . $currentRow)->applyFromArray($borderStyle);

    // 파일 저장
    $writer = new Xlsx($spreadsheet);
    $filename = '수불관리_' . $deliveryDate . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}