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

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
error_log("\n[ln15] logFilePath=" . $logFilePath, 3, $logFilePath);

// 파라미터
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
error_log("\n[ln20] category=" . $category, 3, $logFilePath);

isset($_GET['start_date']) ? $start_date = $_GET['start_date'] : $start_date = "";
isset($_GET['end_date']) ? $end_date = $_GET['end_date'] : $end_date = "";
isset($_GET['bl_no']) ? $bl_no = $_GET['bl_no'] : $bl_no = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

// GS 수불 조회
if ($category === "search_GsDay") {

    // GS 수불 데이터 조회
    $data = getGsDayData($start_date);

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

// GS 수불 데이터 조회 함수
function getGsDayData($start_date)
{
    global $conn, $logFilePath;

    $dateObj = DateTime::createFromFormat('Y-m-d', $start_date) ?: DateTime::createFromFormat('Ymd', $start_date);
    if ($dateObj) {
        $dateObj->modify('+1 day'); // 원료출고는 납품일자(기준일자+1일)로 조회
        $delivery_dt = $dateObj->format('Ymd');

        // $dateObj->modify('-1 day'); // 생산일자는 공급일자(기준일자-1일)로 조회
        // $make_dt = $dateObj->format('Ymd');
    }
    $make_dt = preg_replace('/[^0-9]/', '', $delivery_dt);

    $data = array();
    try {
        // 1. SET 쿼리 별도 실행
        $setQuery = "SET @increate_dt := '$start_date', @delivery_dt := '$delivery_dt', @make_dt := '$make_dt' ";
        if (!$conn->query($setQuery)) {
            error_log("[ln_co] SET 쿼리 실패: " . $conn->error, 3, $logFilePath);
            return ['status' => 'error', 'message' => 'SET 쿼리 실패: ' . $conn->error];
        }

        // 2. WITH + SELECT 쿼리 실행
        $sql = "WITH GSDayProd AS (
                    SELECT
                        increate_dt,
                        lc_no AS bl_no,
                        (SELECT MAX(manu_date) FROM tbarcodeout WHERE store_code = '005' AND lc_no = o.lc_no) AS manu_date,
                        weight,
                        prod_code,
                        prod_nm,
                        store_no
                    FROM tbarcodeout o
                    WHERE o.store_code = '005'
                    AND o.barcode_out IS NULL
                    AND DATE(o.increate_dt) = @increate_dt
                ),
                RawImport AS (
                    SELECT bl_no, spec, import_dt, awb, vol, weight
                    FROM (
                        SELECT 
                            RIGHT(awb, 4) AS bl_no,
                            spec,
                            import_dt,
                            awb,
                            vol,
                            weight,
                            ROW_NUMBER() OVER (PARTITION BY RIGHT(awb, 4) ORDER BY import_dt DESC) AS rn
                        FROM tpurchase_imp
                        WHERE RIGHT(awb, 4) IN (SELECT DISTINCT bl_no FROM GSDayProd)
                    ) t
                    WHERE rn = 1
                ),
                -- 납품소계
                GSOrderAdd AS (
                    SELECT *
                        FROM (
                        SELECT
                            COM.store_code AS product_code,
                            COM.code_exp AS product_name,
                            '62970' AS dest_code,
                            '이천|62970' AS dest_name,
                            COALESCE(SUM(CASE WHEN ORD.dest_code = '62970' THEN PRD.quantity ELSE 0 END), 0) +
                            COALESCE(addq.quantity_new, 0) AS quantity
                        FROM tcomcodegs COM
                        LEFT JOIN torderproducts PRD ON COM.store_code = PRD.product_code
                        LEFT JOIN torders ORD ON ORD.doc_no = PRD.doc_no
                                                AND ORD.supply_dt = @make_dt
                                                AND ORD.msgfrom = 'GSSM01:DC'
                        LEFT JOIN (
                            SELECT dest_code, product_code, SUM(quantity_new) AS quantity_new
                            FROM torderproducts_add
                            WHERE supply_dt = @make_dt
                            GROUP BY dest_code, product_code
                        ) addq ON addq.dest_code = '62970' AND addq.product_code = COM.store_code
                        WHERE COM.grp_code = 2 AND COM.use_yn = 'y'
                        GROUP BY COM.store_code, COM.code_exp, addq.quantity_new
                        HAVING quantity > 0

                        UNION ALL

                        SELECT COM.store_code, COM.code_exp, '60470', '양산|60470',
                                COALESCE(SUM(CASE WHEN ORD.dest_code = '60470' THEN PRD.quantity ELSE 0 END), 0) +
                                COALESCE(addq.quantity_new, 0)
                        FROM tcomcodegs COM
                        LEFT JOIN torderproducts PRD ON COM.store_code = PRD.product_code
                        LEFT JOIN torders ORD ON ORD.doc_no = PRD.doc_no
                                                AND ORD.supply_dt = @make_dt
                                                AND ORD.msgfrom = 'GSSM01:DC'
                        LEFT JOIN (
                            SELECT dest_code, product_code, SUM(quantity_new) AS quantity_new
                            FROM torderproducts_add
                            WHERE supply_dt = @make_dt
                            GROUP BY dest_code, product_code
                        ) addq ON addq.dest_code = '60470' AND addq.product_code = COM.store_code
                        WHERE COM.grp_code = 2 AND COM.use_yn = 'y'
                        GROUP BY COM.store_code, COM.code_exp, addq.quantity_new
                        HAVING COALESCE(SUM(CASE WHEN ORD.dest_code = '60470' THEN PRD.quantity ELSE 0 END), 0) + COALESCE(addq.quantity_new, 0) > 0

                        UNION ALL

                        SELECT COM.store_code, COM.code_exp, '66970', '남사|66970',
                                COALESCE(SUM(CASE WHEN ORD.dest_code = '66970' THEN PRD.quantity ELSE 0 END), 0) +
                                COALESCE(addq.quantity_new, 0)
                        FROM tcomcodegs COM
                        LEFT JOIN torderproducts PRD ON COM.store_code = PRD.product_code
                        LEFT JOIN torders ORD ON ORD.doc_no = PRD.doc_no
                                                AND ORD.supply_dt = @make_dt
                                                AND ORD.msgfrom = 'GSSM01:DC'
                        LEFT JOIN (
                            SELECT dest_code, product_code, SUM(quantity_new) AS quantity_new
                            FROM torderproducts_add
                            WHERE supply_dt = @make_dt
                            GROUP BY dest_code, product_code
                        ) addq ON addq.dest_code = '66970' AND addq.product_code = COM.store_code
                        WHERE COM.grp_code = 2 AND COM.use_yn = 'y'
                        GROUP BY COM.store_code, COM.code_exp, addq.quantity_new
                        HAVING COALESCE(SUM(CASE WHEN ORD.dest_code = '66970' THEN PRD.quantity ELSE 0 END), 0) + COALESCE(addq.quantity_new, 0) > 0

                        UNION ALL

                        SELECT COM.store_code, COM.code_exp, '62980', '이천2|62980',
                                COALESCE(SUM(CASE WHEN ORD.dest_code = '62980' THEN PRD.quantity ELSE 0 END), 0) +
                                COALESCE(addq.quantity_new, 0)
                        FROM tcomcodegs COM
                        LEFT JOIN torderproducts PRD ON COM.store_code = PRD.product_code
                        LEFT JOIN torders ORD ON ORD.doc_no = PRD.doc_no
                                                AND ORD.supply_dt = @make_dt
                                                AND ORD.msgfrom = 'GSSM01:DC'
                        LEFT JOIN (
                            SELECT dest_code, product_code, SUM(quantity_new) AS quantity_new
                            FROM torderproducts_add
                            WHERE supply_dt = @make_dt
                            GROUP BY dest_code, product_code
                        ) addq ON addq.dest_code = '62980' AND addq.product_code = COM.store_code
                        WHERE COM.grp_code = 2 AND COM.use_yn = 'y'
                        GROUP BY COM.store_code, COM.code_exp, addq.quantity_new
                        HAVING COALESCE(SUM(CASE WHEN ORD.dest_code = '62980' THEN PRD.quantity ELSE 0 END), 0) + COALESCE(addq.quantity_new, 0) > 0

                        UNION ALL

                        SELECT COM.store_code, COM.code_exp, '68970', '부산|68970',
                                COALESCE(SUM(CASE WHEN ORD.dest_code = '68970' THEN PRD.quantity ELSE 0 END), 0) +
                                COALESCE(addq.quantity_new, 0)
                        FROM tcomcodegs COM
                        LEFT JOIN torderproducts PRD ON COM.store_code = PRD.product_code
                        LEFT JOIN torders ORD ON ORD.doc_no = PRD.doc_no
                                                AND ORD.supply_dt = @make_dt
                                                AND ORD.msgfrom = 'GSSM01:DC'
                        LEFT JOIN (
                            SELECT dest_code, product_code, SUM(quantity_new) AS quantity_new
                            FROM torderproducts_add
                            WHERE supply_dt = @make_dt
                            GROUP BY dest_code, product_code
                        ) addq ON addq.dest_code = '68970' AND addq.product_code = COM.store_code
                        WHERE COM.grp_code = 2 AND COM.use_yn = 'y'
                        GROUP BY COM.store_code, COM.code_exp, addq.quantity_new
                        HAVING COALESCE(SUM(CASE WHEN ORD.dest_code = '68970' THEN PRD.quantity ELSE 0 END), 0) + COALESCE(addq.quantity_new, 0) > 0

                        UNION ALL

                        SELECT
                            prd.product_code,
                            com.code_exp,
                            'GS25' AS dest_code,
                            'GS25' AS dest_name,
                            SUM(prd.quantity) + COALESCE(addq.quantity_new, 0) AS quantity
                        FROM torders ord
                        INNER JOIN torderproducts prd ON ord.doc_no = prd.doc_no
                        INNER JOIN tcomcodegs com ON com.store_code = prd.product_code
                                                    AND com.grp_code = 2 AND com.use_yn = 'y'
                        LEFT JOIN (
                            SELECT dest_code, product_code, SUM(quantity_new) AS quantity_new
                            FROM torderproducts_add
                            WHERE supply_dt = @make_dt
                            GROUP BY dest_code, product_code
                        ) addq ON addq.dest_code = 'GS25' AND addq.product_code = prd.product_code
                        WHERE ord.msgfrom = 'GSCT04:DC' AND ord.supply_dt = @make_dt
                        GROUP BY prd.product_code, com.code_exp, addq.quantity_new
                        HAVING SUM(prd.quantity) + COALESCE(addq.quantity_new, 0) > 0

                        ) AS combined_result
            )

            SELECT 0 AS gsort_key, '원료입고' AS data_type,
                ri.bl_no, ri.awb, DATE_FORMAT(ri.import_dt, '%Y-%m-%d') AS date,
                ri.vol, ri.weight, ri.spec AS prod_nm, NULL AS yield_percent, NULL AS store_no
            FROM RawImport ri

            UNION ALL

            -- 원료출고는 생산내역과 독립적으로 처리 delivery_dt = 생산일 +1 day
            SELECT 1 AS gsort_key, '원료출고' AS data_type,
                o.lc_no AS bl_no, im.awb AS awb, DATE_FORMAT(o.manu_date, '%Y-%m-%d') AS date,
                COUNT(o.barcode_out) AS vol, SUM(o.weight) AS weight, o.prod_nm AS prod_nm, NULL AS yield_percent, NULL AS store_no
             FROM tbarcodeout o
            LEFT JOIN tpurchase_imp im ON LEFT(o.lc_no,4) = RIGHT(im.awb,4)
            WHERE o.store_code = '005'
              AND o.barcode_out IS NOT NULL
              AND DATE(o.increate_dt) = @delivery_dt
            GROUP BY o.lc_no

            UNION ALL

            SELECT 2 AS gsort_key, '생산내역' AS data_type,
                dp.bl_no, ri.awb, dp.manu_date AS date,
                NULL AS vol, dp.weight, dp.prod_nm,
                NULL AS yield_percent, dp.store_no
            FROM GSDayProd dp
            LEFT JOIN RawImport ri ON dp.bl_no = ri.bl_no

            UNION ALL

            SELECT 3 AS gsort_key, '납품소계' AS data_type,
                NULL AS bl_no, NULL AS awb,
                DATE_FORMAT(@make_dt, '%Y-%m-%d') AS date,
                SUM(p.quantity) AS vol, NULL AS weight,
                p.product_name AS prod_nm, NULL AS yield_percent,
                NULL AS store_no
            FROM GSOrderAdd p
            GROUP BY p.product_name

            UNION ALL

            SELECT 4 AS gsort_key, '납품내역' AS data_type,
                NULL AS bl_no, NULL AS awb,
                DATE_FORMAT(@make_dt, '%Y-%m-%d') AS date,
                p.quantity AS vol, NULL AS weight,
                p.product_name AS prod_nm, NULL AS yield_percent,
                p.dest_name AS store_no
            FROM GSOrderAdd p
            ORDER BY gsort_key ASC, bl_no ASC, prod_nm ASC, store_no ASC;
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
function generatePrintHTML($result, $deliveryDate)
{
    global $logFilePath;

    header('Content-Type: text/html; charset=UTF-8');

    // 날짜 포맷 변환
    $dateObj = DateTime::createFromFormat('Ymd', $deliveryDate) ?: DateTime::createFromFormat('Y-m-d', $deliveryDate);
    if ($dateObj) {
        $weekdays = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];
        $formattedDate = $dateObj->format('Y년 m월 d일 ') . $weekdays[(int)$dateObj->format('w')];
        $listDate = ltrim($dateObj->format('n/j'), '0') . '(' . mb_substr($weekdays[(int)$dateObj->format('w')], 0, 1) . ')';
    } else {
        $formattedDate = $deliveryDate; // 변환 실패 시 원본 사용
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
    usort($allBlNos, function ($a, $b) use ($importProdNmByBl) {
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
            /* ---------------------- */
            /* 프린트 및 납품목록 테이블 스타일 통합 */
            /* ---------------------- */
            /* 프린트 테이블 스타일 */
            .print-table {
                border-collapse: collapse;
                width: 100%;
            }

            .center {
                text-align: center;
            }

            .print-th,
            .print-td {
                border: 1px solid #333;
                padding: 4px 8px;
                text-align: center;
            }

            .print-th,
            .soGye-row {
                background-color: #f2f2f2 !important;
                /* 연한 회색 */
            }

            body {
                font-family: "맑은 고딕", sans-serif;
                font-size: 12px;
            }

            /* 프린트 미디어 쿼리 */
            @media print {
                body {
                    margin: 0;
                }

                .table {
                    page-break-inside: avoid;
                }
            }

            /* 납품목록 테이블 전용 스타일 */
            .gs-delivery-table {
                table-layout: fixed;
                width: 100%;
            }

            .gs-delivery-table th,
            .gs-delivery-table td {
                width: auto !important;
            }

            .gs-delivery-table .col-store {
                width: 150px !important;
            }
        </style>

    </head>

    <body>
        <h1 style="text-align: center;">수불관리:GS::<?php echo $formattedDate; ?></h1>
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
                    <th class="print-th">비고(수율)</th>
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
                            <td class="print-td"></td>
                            <td class="print-td"></td>
                            <td class="print-td"></td>
                            <td class="print-td"></td>
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
                            <td class="print-td center"><?= $prod['store_no'] ?></td>
                        <?php } else { ?>
                            <td class="print-td"></td>
                            <td class="print-td"></td>
                            <td class="print-td"></td>
                            <td class="print-td"></td>
                            <td class="print-td"></td>
                        <?php } ?>
                    </tr>
                <?php endforeach; ?>

                <!-- 소계 행 수정 -->
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
                    if (strpos($p['prod_nm'], '생연어') !== false) {
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
                    <td class="print-td">연어필렛</td>
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
                    <td class="print-td">스테이크</td>
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
                <tr>
                    <td colspan="11" style="height:20px; border:none; background:none;"></td>
                </tr>

                <!-- GS 납품목록 테이블 -->
                <?php
                // 1. 납품내역 데이터만 추출
                $deliveryDetailRows = [];
                foreach ($rows as $row) {
                    if (isset($row['data_type']) && $row['data_type'] === '납품내역') {
                        $deliveryDetailRows[] = $row;
                    }
                }

                // 2. 납품점 동적 추출 및 정렬
                $allStores = [];
                foreach ($deliveryDetailRows as $row) {
                    $allStores[$row['store_no']] = isset($row['order_num']) ? $row['order_num'] : 9999;
                }
                uksort($allStores, function ($a, $b) use ($allStores) {
                    return $allStores[$a] - $allStores[$b];
                });
                $allStores = array_keys($allStores);

                // 3. 납품점별 weight 매핑
                function getBoxByStore($details, $store_no)
                {
                    $sum = 0;
                    foreach ($details as $row) {
                        if ($row['store_no'] === $store_no) {
                            $sum += $row['vol'];
                        }
                    }
                    // 0이면 빈칸
                    return $sum == 0 ? '' : $sum;
                }

                // 4. 전체 합계
                function getTotalBox($details)
                {
                    $sum = 0;
                    foreach ($details as $row) {
                        $sum += $row['vol'];
                    }
                    return $sum;
                }
                ?>

                <!-- 한 줄 띄우고 제목 추가 -->
                <table class="print-table gs-delivery-table">
                    <caption style="caption-side: top; text-align:left; font-weight:bold; font-size:14px; margin-bottom:8px;">□ GS 납품목록: <?php echo $listDate; ?></caption>
                    <thead>
                        <tr>
                            <td class="print-td center col-store" style="background:#f2f2f2;">납품점</td>
                            <?php foreach ($allStores as $store_no): ?>
                                <td class="print-td center"><?= $store_no ?></td>
                            <?php endforeach; ?>
                            <td class="print-td center" style="background:#f2f2f2;">합계</td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $productNames = [];
                        foreach ($deliveryDetailRows as $row) {
                            $productNames[$row['prod_nm']] = true;
                        }
                        $productNames = array_keys($productNames);
                        ?>
                        <?php foreach ($productNames as $prod_nm): ?>
                            <tr>
                                <td class="print-td center col-store"><?= $prod_nm ?></td>
                                <?php
                                $rowSum = 0;
                                foreach ($allStores as $store_no):
                                    $vol = 0;
                                    foreach ($deliveryDetailRows as $row) {
                                        if ($row['prod_nm'] === $prod_nm && $row['store_no'] === $store_no) {
                                            $vol += $row['vol'];
                                        }
                                    }
                                    $rowSum += $vol;
                                ?>
                                    <td class="print-td center"><?= $vol > 0 ? $vol : '' ?></td>
                                <?php endforeach; ?>
                                <td class="print-td center" style="background:#f2f2f2;"><?= $rowSum > 0 ? $rowSum : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </tbody>
        </table>
    </body>

    </html>
<?php
}


function generateExcelFile($data, $deliveryDate)
{

    // 날짜 포맷 변환
    $dateObj = DateTime::createFromFormat('Ymd', $deliveryDate) ?: DateTime::createFromFormat('Y-m-d', $deliveryDate);
    if ($dateObj) {
        $weekdays = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];
        $formattedDate = $dateObj->format('Y년 m월 d일 ') . $weekdays[(int)$dateObj->format('w')];
    } else {
        $formattedDate = $deliveryDate;
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
        '구분',
        'B/L번호',
        'Box',
        '무게',
        '통관일',
        '구분',
        'B/L번호',
        'Box',
        '무게',
        '생산일',
        '수율'
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
    $sheet->mergeCells('A' . $currentRow . ':K' . $currentRow);
    $currentRow++;

    // 납품내역 데이터 정리
    $deliveryDetails = array_filter($data, function ($row) {
        return $row['data_type'] === '납품내역';
    });

    // 납품점 목록 추출 및 정렬
    $stores = array_unique(array_column($deliveryDetails, 'store_no'));
    sort($stores);

    // 연어필렛 행
    $sheet->setCellValue('A' . $currentRow, '연어필렛');
    $sheet->mergeCells('A' . $currentRow . ':A' . ($currentRow + 2));
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
    $sheet->mergeCells('A' . $currentRow . ':A' . ($currentRow + 1));
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
