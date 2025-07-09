<?PHP
//통합검색
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn   = new dbconnClassFish;
$conn     = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");


$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

// 조회 파라미터    
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";
isset($_GET['increate_dt']) ? $increate_dt = $_GET['increate_dt'] : $increate_dt = "";
isset($_GET['store_no']) ? $store_code = $_GET['store_no'] : $store_code = "";  // store_code 
// 유통기한, 중량, 상품코드 => Update용
isset($_GET['expi_date']) ? $expi_date = $_GET['expi_date'] : $expi_date = "";
isset($_GET['weights']) ? $weight = $_GET['weights'] : $weight = "";
isset($_GET['prod_code']) ? $prod_code = $_GET['prod_code'] : $prod_code = "";

// 점포코드의 점포명 가져오기, store_no 는 => store_code가 넘어옴
if (
    trim($store_code) !== ""
) {
    // SQL 인젝션 방지를 위해 값 이스케이프
    $safe_store_code = $conn->real_escape_string($store_code);
    $sql = "SELECT code_exp AS store_nm
            FROM tcomcode AS TCC
            WHERE TCC.grp_code = '1'
              AND TCC.use_yn = 'y'
              AND TCC.store_code = '$safe_store_code'
            ORDER BY TCC.order_num ASC
              "; // 수정된 부분

    $result = $conn->query($sql);
    if ($result) {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $store_nm = trim($row['store_nm']); // 단일 결과 사용
            // echo $store_nm;
        } else {
            $store_nm = ''; // 결과가 없을 때 빈 문자열 할당
        }
    } else {
        echo "Query failed: " . $conn->error; // 쿼리 실패 시 오류 메시지 출력
        $store_nm = '';
    }
}

// error_log("[barcodeInv2Gs_srv.php] store_code: " . $store_code, 3, $logFilePath);

// category=1s: 납품상품조회 (연어 제외)
if ($category === '1s') {
    $supply_dt = str_replace('-', '', $increate_dt);
    $sql =
            "  SELECT
        (@row_number:=@row_number + 1) AS 'NO',
        subquery.*
    FROM (
        SELECT
            ORD.supply_dt AS '납품일',
            ORD.dest_code AS '점포코드',
            COM.code_exp AS '품명',
            PRD.product_code AS '상품코드',
            COM.country_origin AS '원산지',
            COM.unit AS '단위',
            FLOOR(SUM(COALESCE(PRD.quantity_new, PRD.quantity, 0))) AS '수량',
            MAX(PRD.unit_price) AS '단가',
            FLOOR(SUM(COALESCE(PRD.quantity_new, PRD.quantity, 0))) * MAX(PRD.unit_price) AS '금액',
            FLOOR(COALESCE(SUM(CASE WHEN ORD.dest_code IN ('62970', '60470', '66970', '62980', '68970')
                                    THEN COALESCE(PRD.quantity_new, PRD.quantity, 0)
                                    ELSE 0 END) /
            CASE
                WHEN COM.store_code = '8809611290037' THEN 4
                WHEN COM.store_code = '8809611290365' THEN 2
                WHEN COM.store_code = '8809611290266' THEN 4
                ELSE 1
            END, 0)) AS '박스',
            ADDPRD.quantity_new AS '수량추가'
        FROM
            torders ORD
        JOIN
            torderproducts PRD ON ORD.doc_no = PRD.doc_no
        JOIN
            tcomcodegs COM ON COM.store_code = PRD.product_code
        LEFT JOIN
            torderproducts_add ADDPRD ON ADDPRD.supply_dt = ORD.supply_dt AND ADDPRD.dest_code = ORD.dest_code AND ADDPRD.product_code = PRD.product_code
        WHERE 
            ORD.msgfrom = 'GSSM01:DC'
            AND ORD.supply_dt = '$supply_dt'
            AND COM.grp_code = 2
            AND COM.use_yn = 'y'
                        ";

        if ($store_code !== '') $sql .= " AND ORD.dest_code = '$store_code'";

        $sql .= "    GROUP BY 
            ORD.supply_dt, ORD.dest_code, COM.code_exp, COM.store_code, PRD.product_code
        ORDER BY 
            ORD.dest_code, COM.store_code ASC
    ) AS subquery, (SELECT @row_number:=0) AS r;
        ";

    // error_log("[barcodeInv2Gs_srv.php] SQL: " . $sql, 3, $logFilePath);

    $result = $conn->query($sql);

    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
    $conn->close();
    return;
}

// 업데이트
if ($category === 'update') {

    $inputData = file_get_contents('php://input');
    $data = json_decode($inputData, true); // JSON 데이터를 배열로 디코딩

    $key = $data['key'];        // `quantity_new`
    $value = $data['value'];    // 수량추가
    $barcodeList = $data['barcodeList'];

    // error_log("Barcode List: " . print_r($barcodeList, true), 3, $logFilePath);

    $supply_dt = $barcodeList['supply_dt'];
    $dest_code = $barcodeList['dest_code'];
    $product_code = $barcodeList['product_code'];
    $quantity_new = $barcodeList['quantity_new'];

    // 빈 값이면 NULL로 처리
    if ($quantity_new == '' || $quantity_new == '0' || $quantity_new == '-') $quantity_new = 'NULL';

    // key 값으로 있으면 UPDATE, 없으면 INSERT
    $sql = "REPLACE INTO torderproducts_add 
        (supply_dt, dest_code, product_code, quantity_new, create_id, modify_id, msgfrom) 
        VALUES 
        ('$supply_dt', '$dest_code', '$product_code', $quantity_new, '$user_id', '$user_id', 'GSSM01:DC')";

    // error_log("[barcodeInv2Gs_srv.php] SQL: " . $sql, 3, $logFilePath);

    $result = $conn->query($sql);

    if ($result) {
        echo json_encode(["success" => true, "message" => "success" . $sql]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating record: " . $conn->error . " - " . $conn->error_list[0]['error'] . " - SQL: " . $sql]);
    }

    // 수량 추가 NULL인 데이터 삭제
    $delete_sql = "DELETE FROM torderproducts_add 
                    WHERE quantity_new is null";

    $delete_result = $conn->query($delete_sql);
    if (!$delete_result) {
        echo json_encode(["success" => false, "message" => "Error deleting record: " . $conn->error . " - " . $conn->error_list[0]['error'] . " - SQL: " . $delete_sql]);
        return;
    }
    // error_log("[barcodeInv2Gs_srv.php] SQL: " . $sql, 3, $logFilePath);

    $conn->close();
    return;
}

// -------------------------------------------------
// category=gs: GS편의점점::msgfrom = 'GSCT04:DC
// -------------------------------------------------
if ($category === 'gs') {
    $supply_dt = str_replace('-', '', $increate_dt);
    $sql = "  
        SELECT
            ROW_NUMBER() OVER (ORDER BY t.dest_code DESC) AS 'no',  -- 순번 추가
            t.dest_code AS '점포코드',
            t.order_dt AS '발주일자',
            t.supply_dt AS '납품일',
            'GS편의점' AS '주문자',
            CONCAT(t.dest_code, ' ', t.region) AS '점포코드',
            p.quantity AS '수량',
            '조은수산' AS '공급자',
            CONCAT(c.country_origin, ' ', c.code_exp) AS '품명',
            p.product_code AS '상품코드',
            c.country_origin AS '원산지',
            REGEXP_SUBSTR(c.code_exp, '[0-9]+g') AS '단위',                     -- 단위: 숫자와 'g'만 추출 (예: '연어180g슬라이스' → '180g')
            p.unit_price AS '단가',
            4 AS '입수',
            CEIL(COALESCE(p.quantity, 0) / 4) AS '박스',                      -- BOX수: 낱개수량 / 입수, 소수점 올림 처리
            COALESCE(p.quantity, 0) * COALESCE(p.unit_price, 0) AS '금액'      -- 금액 = 낱개수량 * 단가, NULL 안전 계산
        FROM torders t 
        LEFT JOIN torderproducts p ON t.doc_no = p.doc_no
        INNER JOIN tcomcodegs c ON c.store_code = p.product_code
                WHERE t.msgfrom = 'GSCT04:DC'
                    AND t.supply_dt = $supply_dt
                ORDER BY t.dest_code DESC
        ";

    // error_log("[barcodeInv2Gs_srv.php] SQL: " . $sql, 3, $logFilePath);
    $result = $conn->query($sql);

    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
    $conn->close();
    return;
}

// -------------------------------------------------
// category=store_code: 점포 목록 조회
// -------------------------------------------------
if ($category === 'store_code') {

    $sql = "
            SELECT store_code, 
                CASE 
                    WHEN code_exp = '(주)지에스리테일' THEN 'GS편의점'
                    ELSE code_exp
                END AS store_name
            FROM tcomcodegs 
            WHERE grp_code = 1
            ORDER BY order_num ASC"
        ;

    $result = $conn->query($sql);

    $stores = array();
    while ($row = $result->fetch_assoc()) {
        $stores[] = array(
            'code' => $row['store_code'],
            'name' => $row['store_name']
        );
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stores);
    $conn->close();
    return;
}
