
<?php

session_start();
date_default_timezone_set('Asia/Seoul');
$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

isset($_GET['category']) ? $category = $_GET['category'] : $category = "";


// 제품 납품 단가 관리
if ($category == 'price') {
    $sql = " SELECT
                product_name AS '제품명',
                product_code AS '제품코드',
                raw_material AS '원재료',
                unit_price AS '단가',
                DATE(supply_dt) AS '공급일'
            FROM (
                -- ROW_NUMBER를 사용하여 각 제품의 최신 데이터를 찾기 위한 서브쿼리
                SELECT
                    product_name,
                    product_code,
                    raw_material,
                    unit_price,
                    supply_dt,
                    ROW_NUMBER() OVER (PARTITION BY product_code ORDER BY supply_dt DESC) AS rn
                FROM (
                    -- 1. tcomcode 테이블 기준 상품 정보 조회
                    SELECT
                        c.code_exp AS product_name,
                        t.product_code,
                        c.code_exp2 AS raw_material,
                        t.unit_price,
                        o.supply_dt
                    FROM
                        torderproducts t
                    INNER JOIN torders o ON t.doc_no = o.doc_no
                    INNER JOIN tcomcode c ON t.product_code = c.store_code
                    WHERE
                        t.unit_price > 0

                    UNION ALL

                    -- 2. tcomcodegs 테이블 기준 상품 정보 조회
                    SELECT
                        g.code_exp AS product_name,
                        t.product_code,
                        NULL AS raw_material, -- tcomcodegs에는 원재료 컬럼이 없으므로 NULL 처리
                        t.unit_price,
                        o.supply_dt
                    FROM
                        torderproducts t
                    INNER JOIN torders o ON t.doc_no = o.doc_no
                    INNER JOIN tcomcodegs g ON t.product_code = g.store_code
                    WHERE
                        t.unit_price > 0
                        AND g.grp_code = '2' -- 그룹코드가 '상품코드'인 경우
                        AND g.use_yn = 'Y'   -- 사용 여부가 'Y'인 경우
                ) AS combined_products
            ) AS ranked_products
            WHERE
                rn = 1 -- 각 제품별로 가장 최신 데이터만 선택
            ORDER BY
                product_name ASC, supply_dt DESC;
    ";

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
                unitPrice AS '자재단가',
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
                unit_price AS '단가',
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
