<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
// date_default_timezone_set('Asia/Seoul');

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
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

// GS 수불 조회
if ($category === "search_BlDay") {

    // GS 수불 데이터 조회
    error_log("\n[ln39] start_date=" . $start_date, 3, $logFilePath);
    $data = getBlDayData($start_date);
    
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
function getBlDayData($start_date)
{
    global $conn, $logFilePath;
    $manu_date = $start_date;

    error_log("\n[ln73] manu_date=" . $manu_date, 3, $logFilePath);

    $data = array();
    try {
        // 1. SET 쿼리 별도 실행
        $setQuery = "SET @manu_date := '$manu_date'";
     if (!$conn->query($setQuery)) {
            error_log("[ln_co] SET 쿼리 실패: " . $conn->error, 3, $logFilePath);
            return ['status' => 'error', 'message' => 'SET 쿼리 실패: ' . $conn->error];
        }

        // 2. WITH + SELECT 쿼리 실행
        $sql = "WITH TargetBL AS (
                    SELECT DISTINCT LEFT(lc_no, 4) AS bl_no
                    FROM tbarcodeout
                    WHERE manu_date = @manu_date
                ),

                RawImportRanked AS (
                    SELECT 
                        RIGHT(awb, 4) AS bl_no,
                        awb,
                        DATE(import_dt) AS 통관일자,
                        vol AS 입고_BOX,
                        weight AS 입고_중량,
                        spec AS 제품명,
                        ROW_NUMBER() OVER (PARTITION BY RIGHT(awb, 4) ORDER BY import_dt DESC) AS rn
                    FROM tpurchase_imp
                    WHERE RIGHT(awb, 4) IN (SELECT bl_no FROM TargetBL)
                    AND import_dt >= DATE_SUB(@manu_date, INTERVAL 1 MONTH)
                ),

                RawImport AS (
                    SELECT * FROM RawImportRanked WHERE rn = 1
                ),

                RawExportPre AS (
                    SELECT 
                        LEFT(b.lc_no, 4) AS bl_no,
                        DATE(b.increate_dt) AS 기준일자,
                        b.weight AS 출고중량
                    FROM tbarcodein b
                    JOIN RawImport ri ON LEFT(b.lc_no, 4) = ri.bl_no
                    WHERE DATE(b.increate_dt) >= ri.통관일자
                    AND DATE(b.increate_dt) = @manu_date
                ),

                RawExportAgg AS (
                    SELECT 
                        p.bl_no,
                        p.기준일자,
                        COUNT(*) AS BOX,
                        SUM(p.출고중량) AS weight,
                        MAX(CASE WHEN ri.제품명 IN ('Salmon Trim E BB', 'Salmon Trim F BB') THEN 1 ELSE 0 END) AS is_salmon,
                        MAX(ri.입고_BOX) AS 입고_BOX,
                        MAX(ri.입고_중량) AS 입고_중량
                    FROM RawExportPre p
                    LEFT JOIN RawImport ri ON p.bl_no = ri.bl_no
                    GROUP BY p.bl_no, p.기준일자
                ),

                RawExport AS (
                    SELECT 
                        bl_no,
                        기준일자,
                        CASE 
                            WHEN BOX = 1 AND is_salmon = 1 THEN 입고_BOX
                            ELSE BOX
                        END AS BOX,
                        CASE 
                            WHEN BOX = 1 AND is_salmon = 1 THEN 입고_중량
                            ELSE weight
                        END AS weight
                    FROM RawExportAgg
                ),

                Production AS (
                    SELECT 
                        LEFT(o.lc_no, 4) AS bl_no,
                        o.prod_nm AS 제품명,
                        o.manu_date AS 기준일자,
                        COUNT(o.barcode_out) AS BOX,
                        SUM(o.weight) AS weight,
                        o.store_code AS store_code
                    FROM tbarcodeout o
                    WHERE o.barcode_out IS NOT NULL
                    AND o.manu_date = @manu_date
                    GROUP BY LEFT(o.lc_no, 4), o.prod_nm, o.manu_date
                ),

                Sales AS (
                    SELECT 
                        LEFT(o.lc_no, 4) AS bl_no,
                        RIGHT(o.store_no, 3) AS 판매처,
                        o.prod_nm AS 제품명,
                        DATE(o.increate_dt) AS 기준일자,
                        COUNT(o.barcode_out) AS BOX,
                        SUM(o.weight) AS weight,
                        o.store_code AS store_code
                    FROM tbarcodeout o
                    JOIN RawExport re 
                        ON LEFT(o.lc_no, 4) = re.bl_no
                    JOIN Production p 
                        ON LEFT(o.lc_no, 4) = p.bl_no
                    AND p.기준일자 = re.기준일자   -- 생산일 = 원료출고일
                    WHERE o.invoice_yn = 'y'
                    AND DATE(o.manu_date) = re.기준일자  -- 판매일 = 원료출고일
                    GROUP BY DATE(o.increate_dt), LEFT(o.lc_no, 4), RIGHT(o.store_no, 3), o.prod_nm, DATE(o.manu_date)
                ),

                Inventory AS (
                    SELECT 
                        LEFT(o.lc_no, 4) AS bl_no,
                        o.prod_nm AS 제품명,
                        o.manu_date AS 기준일자,
                        COUNT(o.barcode_out) AS BOX,
                        SUM(o.weight) AS weight
                    FROM tbarcodeout o
                    JOIN RawExport re ON LEFT(o.lc_no, 4) = re.bl_no
                    WHERE o.barcode_out IS NOT NULL
                    AND o.store_code IN (000,001,002,003,004,005)
                    AND o.invoice_yn = 'n'
                    AND o.manu_date BETWEEN re.기준일자 AND DATE_ADD(re.기준일자, INTERVAL 3 DAY)
                    GROUP BY LEFT(o.lc_no, 4), o.prod_nm, o.manu_date
                )

                SELECT * FROM (
                    SELECT '1.원료입고' AS 구분, tb.bl_no AS BL번호, ri.통관일자 AS 기준일자, ri.awb AS AWB번호, ri.제품명,
                        ri.입고_BOX AS BOX, ri.입고_중량 AS weight,
                        NULL AS 수율, NULL AS 판매처
                    FROM TargetBL tb
                    LEFT JOIN RawImport ri ON tb.bl_no = ri.bl_no

                    UNION ALL

                    SELECT '2.원료출고', tb.bl_no, re.기준일자, ri.awb, ri.제품명,
                        re.BOX, re.weight,
                        NULL, NULL
                    FROM TargetBL tb
                    LEFT JOIN RawExport re ON tb.bl_no = re.bl_no
                    LEFT JOIN RawImport ri ON tb.bl_no = ri.bl_no

                    UNION ALL

                    -- 3.생산내역 (출고처 제외)
                    SELECT '3.생산내역' AS 구분,
                        tb.bl_no,
                        COALESCE(re.기준일자, '') AS 기준일자,
                        NULL AS AWB번호,
                        MIN(p.제품명) AS 제품명,
                        COALESCE(SUM(p.BOX), 0) AS BOX,
                        COALESCE(SUM(p.weight), 0) AS weight,
                        CASE 
                            WHEN re.weight IS NOT NULL AND SUM(p.weight) IS NOT NULL 
                            THEN ROUND(SUM(p.weight) / NULLIF(re.weight, 0) * 100, 1)
                            ELSE NULL
                        END AS 수율,
                        NULL AS 판매처
                    FROM TargetBL tb
                    LEFT JOIN RawExport re ON tb.bl_no = re.bl_no
                    LEFT JOIN (
                        SELECT 
                            LEFT(o.lc_no, 4) AS bl_no,
                            o.manu_date AS 기준일자,
                            o.prod_nm AS 제품명,
                            COUNT(o.barcode_out) AS BOX,
                            SUM(o.weight) AS weight,
                            o.store_code
                        FROM tbarcodeout o
                        WHERE o.barcode_out IS NOT NULL
                        GROUP BY LEFT(o.lc_no, 4), o.manu_date, o.prod_nm, o.store_code
                    ) p ON tb.bl_no = p.bl_no AND re.기준일자 = p.기준일자
                    AND (p.store_code NOT IN ('000','001','002','003','004','005','006','007') OR p.store_code IS NULL)
                    GROUP BY tb.bl_no, re.기준일자

                    UNION ALL

                    SELECT '4.판매내역' AS 구분, 
                        tb.bl_no, 
                        s.기준일자, 
                        NULL AS AWB번호, 
                        s.제품명,
                        s.BOX, 
                        s.weight,
                        NULL AS 수율, 
                        s.판매처
                    FROM TargetBL tb
                    LEFT JOIN Sales s 
                        ON tb.bl_no = s.bl_no 
                    AND (s.store_code NOT IN ('000','001','002','003','004','005','006','007') OR s.store_code IS NULL)

                    UNION ALL

                    -- 5.생산내역(출금)
                    SELECT '5.생산내역(출금)' AS 구분,
                        tb.bl_no,
                        COALESCE(re.기준일자, '') AS 기준일자,
                        NULL AS AWB번호,
                        MIN(p.제품명) AS 제품명,
                        COALESCE(SUM(p.BOX), 0) AS BOX,
                        COALESCE(SUM(p.weight), 0) AS weight,
                        CASE 
                            WHEN re.weight IS NOT NULL AND SUM(p.weight) IS NOT NULL 
                            THEN ROUND(SUM(p.weight) / NULLIF(re.weight, 0) * 100, 1)
                            ELSE NULL
                        END AS 수율,
                        NULL AS 판매처
                    FROM TargetBL tb
                    LEFT JOIN RawExport re ON tb.bl_no = re.bl_no
                    LEFT JOIN (
                        SELECT 
                            LEFT(o.lc_no, 4) AS bl_no,
                            o.manu_date AS 기준일자,
                            o.prod_nm AS 제품명,
                            COUNT(o.barcode_out) AS BOX,
                            SUM(o.weight) AS weight,
                            o.store_code
                        FROM tbarcodeout o
                        WHERE o.barcode_out IS NOT NULL
                        GROUP BY LEFT(o.lc_no, 4), o.manu_date, o.prod_nm, o.store_code
                    ) p ON tb.bl_no = p.bl_no AND re.기준일자 = p.기준일자
                    AND p.store_code IN ('005','006','007')
                    GROUP BY tb.bl_no, re.기준일자

                    UNION ALL

                    SELECT '6.판매내역(출금)', tb.bl_no, s.기준일자, NULL, s.제품명,
                        s.BOX, s.weight,
                        NULL, s.판매처
                    FROM TargetBL tb
                    LEFT JOIN Sales s ON tb.bl_no = s.bl_no AND s.store_code IN (005,006,007)

                    UNION ALL

                    SELECT '7.재고내역', tb.bl_no, i.기준일자, NULL, i.제품명,
                        i.BOX, i.weight,
                        NULL, NULL
                    FROM TargetBL tb
                    LEFT JOIN Inventory i ON tb.bl_no = i.bl_no
                ) AS result

                ORDER BY BL번호, 구분, 기준일자;

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
        $rawBoxSum += $r['BOX'];
        $rawKgSum += $r['weight'];
    }

    // 생산내역 소계 계산
    $prodBoxSum = 0;
    $prodKgSum = 0;
    foreach ($productions as $p) {
        $prodBoxSum += $p['BOX'];
        $prodKgSum += $p['weight'];
    }

    // 1. 생산소계 데이터만 추출
    $soGyeRows = [];
    foreach ($rows as $row) {
        if (isset($row['data_type']) && $row['data_type'] === '생산내역역') {
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
        if (isset($row['data_type']) && $row['data_type'] === '판매내역역') {
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
                            <td class="print-td center"><?= $prod['yield_percent'] ?></td>
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
