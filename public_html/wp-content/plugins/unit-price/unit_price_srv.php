
<?php

session_start();
date_default_timezone_set('Asia/Seoul');
$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/plugins/unit-price/logfile.log";
// error_log("\n[unit_price_srv.php] logFilePath=" . $logFilePath, 3, $logFilePath);

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();

mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

isset($_GET['category']) ? $category = $_GET['category'] : $category = "";

// 제품 납품 단가 관리
if ($category == 'price') {
    // 최적화 버전 FINAL: 캐시 테이블 사용 (트리거 자동 업데이트)
    // 성능: 126,483 rows → 138 rows (약 900배 개선)
    $sql = "SELECT
                COALESCE(c.code_exp, g.code_exp) AS '제품명',
                cache.product_code AS '제품코드',
                c.code_exp2 AS '원재료',
                ROUND(cache.unit_price) AS '단가',
                DATE(cache.supply_dt) AS '공급일'
            FROM torderproducts_latest_price cache
            LEFT JOIN tcomcode c ON cache.product_code = c.store_code
            LEFT JOIN tcomcodegs g 
                ON cache.product_code = g.store_code
                AND g.grp_code = '2'
                AND g.use_yn = 'Y'
            WHERE (c.store_code IS NOT NULL OR g.store_code IS NOT NULL)
            ORDER BY DATE(cache.supply_dt) DESC, COALESCE(c.code_exp, g.code_exp) ASC;
    ";

    // error_log("\n[price] sql=" . $sql, 3, $logFilePath);
    $result = $conn->query($sql);
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    send_json_response(true, $data);
    // echo $json_string;
}

// 재료 단가 관리
if ($category == 'matprice') {
    $sql = " WITH RankedData AS (
                SELECT DISTINCT
                    p.vendor_nm,
                    m.mat_nm,
                    p.mat_cd,
                    p.lot_nm,
                    p.unitPrice,
                    p.input_dt,
                    ROW_NUMBER() OVER (
                        PARTITION BY p.vendor_nm, p.mat_cd
                        ORDER BY p.lot_nm DESC
                    ) as dup_check,
                    ROW_NUMBER() OVER (ORDER BY p.mat_cd ASC, p.lot_nm) as rn
                FROM tpurchase p
                INNER JOIN tdayprodmat_mst m ON p.mat_cd = m.mat_cd
            )
            SELECT 
                CONCAT(vendor_nm, '-', mat_nm) AS '자재명',
                mat_cd AS '자재코드',
                lot_nm AS 'lot명',
                ROUND(unitPrice) AS '자재단가',
                DATE(input_dt) AS '매입일',
                rn
            FROM RankedData
            WHERE dup_check = 1
            ORDER BY mat_cd, input_dt DESC, mat_nm, rn ASC;
           ";


    $result = $conn->query($sql);
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    send_json_response(true, $data);
    // echo $json_string;
}

if ($category == 'old-price') {
    isset($_GET['product_code']) ? $product_code = $_GET['product_code'] : $product_code = "";
    if ($product_code == '') {
        send_json_response(false, '제품코드를 입력해주세요.');
        exit;
    }

    $sql = "WITH SupplyHistory AS (
                -- 1. tcomcode 테이블 기준 상품의 공급 이력
                SELECT
                    c.code_exp AS product_name,
                    t.unit_price,
                    DATE(o.supply_dt) AS supply_dt
                FROM torderproducts t
                INNER JOIN torders o ON t.doc_no = o.doc_no
                INNER JOIN tcomcode c ON t.product_code = c.store_code
                WHERE t.unit_price > 0
                AND t.product_code = '$product_code'
                GROUP BY c.code_exp, t.unit_price, DATE(o.supply_dt)

                UNION ALL

                -- 2. tcomcodegs 테이블 기준 상품의 공급 이력
                SELECT
                    g.code_exp AS product_name,
                    t.unit_price,
                    DATE(o.supply_dt) AS supply_dt
                FROM torderproducts t
                INNER JOIN torders o ON t.doc_no = o.doc_no
                INNER JOIN tcomcodegs g ON t.product_code = g.store_code
                WHERE t.unit_price > 0
                AND t.product_code = '$product_code'
                AND g.grp_code = '2' -- 상품 코드만
                AND g.use_yn = 'Y'   -- 사용중인 코드만
                GROUP BY g.code_exp, t.unit_price, DATE(o.supply_dt)
            ),
            PriceCheck AS (
                -- 3. 합쳐진 공급 이력에서 단가 변경점 추적
                SELECT
                    product_name,
                    unit_price,
                    supply_dt,
                    LAG(unit_price) OVER (ORDER BY supply_dt, unit_price) AS prev_price -- 날짜와 단가로 정렬
                FROM SupplyHistory
            )
            -- 4. 단가가 변경된 이력만 최종 선택
            SELECT
                product_name AS '제품명',
                ROUND(unit_price) AS '단가',
                supply_dt AS '공급일'
            FROM PriceCheck
            WHERE prev_price IS NULL      -- 가장 오래된 첫 단가
            OR unit_price <> prev_price -- 이전 단가와 다른 경우
            ORDER BY supply_dt DESC
            LIMIT 50
    ";

    error_log("[old-price] sql=" . $sql, 3, $logFilePath);

    $result = $conn->query($sql);
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    send_json_response(true, $data);
}

$conn->close();


function send_json_response($success, $data)
{
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => $success,
        'data' => $data
    ));
    exit;
}
