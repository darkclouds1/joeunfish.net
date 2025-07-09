
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
                    code_exp2 AS '원재료',
                    unit_price AS '단가',
                    DATE(supply_dt) AS '공급일'
                    FROM (
                    SELECT
                    c.code_exp AS product_name,
                    t.product_code,
                    c.code_exp2,
                    t.unit_price,
                    o.supply_dt,
                    ROW_NUMBER() OVER (PARTITION BY t.product_code ORDER BY o.supply_dt DESC) AS rn
                FROM
                    torderproducts t
                INNER JOIN torders o ON t.doc_no = o.doc_no
                INNER JOIN tcomcode c ON t.product_code = c.store_code
                WHERE
                    t.unit_price > 0
                    ) ranked
                    WHERE rn = 1
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

    $sql = "
        WITH SupplyHistory AS (
            SELECT 
                t.product_name,
                t.unit_price,
                DATE(o.supply_dt) as supply_dt  -- 날짜 부분만 추출
            FROM torderproducts t
            INNER JOIN torders o ON t.doc_no = o.doc_no
            INNER JOIN tcomcode c ON t.product_code = c.store_code
            WHERE t.unit_price > 0 
            AND t.product_code = '$product_code'
            GROUP BY t.product_name, t.unit_price, DATE(o.supply_dt)  -- 동일한 조합은 1개만 선택
        ),
        PriceCheck AS (
            SELECT 
                product_name,
                unit_price,
                supply_dt,
                LAG(unit_price) OVER (ORDER BY supply_dt DESC) AS prev_price
            FROM SupplyHistory
        )
        SELECT
            product_name AS '제품명',
            unit_price AS '단가',
            supply_dt AS '공급일'
        FROM PriceCheck
        WHERE prev_price IS NULL  -- 첫 번째 행
        OR unit_price <> prev_price  -- 이전 단가와 다른 경우
        ORDER BY supply_dt DESC
        LIMIT 50;
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
