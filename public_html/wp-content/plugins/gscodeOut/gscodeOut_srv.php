<?PHP
//통합검색

session_start();
date_default_timezone_set('Asia/Seoul');
header('Content-Type: application/json; charset=utf-8');

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


// error_log("[gscodeOut_srv.php] category: " . $category, 3, $logFilePath);

// 점포코드의 점포명 가져오기, store_no 는 => store_code가 넘어옴
if (
    trim($store_code) !== ""
) {
    // SQL 인젝션 방지를 위해 값 이스케이프
    $safe_store_code = $conn->real_escape_string($store_code);
    $sql = "SELECT code_exp AS store_nm
            FROM tcomcodegs AS TCC
            WHERE TCC.grp_code = '1'
              -- AND TCC.use_yn = 'y'
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
    // $increate_dt를 'yyyy-mm-dd' 형식에서 -1 day 해줌
    $date = new DateTime($increate_dt);
    $date->modify('+1 day');
    $supply_dt = $date->format('Y-m-d');
    $supply_dt = str_replace('-', '', $supply_dt);
    $sql = " WITH tgscodeout_data AS (
                SELECT 
                '$supply_dt' AS supply_dt,
                t1.store_code AS store_code,
                t1.store_no AS store_no,
                t1.prod_code AS prod_code,
                t1.prod_nm AS prod_nm,
                t1.weight_in AS weight_in,
                t1.prod_quantity AS prod_quantity,
                t1.weight_out AS weight_out,
                t1.prod_yield AS prod_yield,
                t1.invent_quantity AS invent_quantity,
                t1.invent_note AS invent_note,
                (SELECT t2.invent_quantity
                FROM tgscodeout t2
                WHERE t2.store_code = t1.store_code
                AND t2.prod_code = t1.prod_code
                AND t2.increate_dt < '$increate_dt'
                ORDER BY t2.increate_dt DESC
                LIMIT 1) AS before_invent_quantity
            FROM tgscodeout t1
            WHERE LEFT(t1.increate_dt, 10) = '$increate_dt'
            ),
            order_summary AS (
                SELECT 
                    ORD.supply_dt,
                    CASE WHEN LEFT(ORD.dest_code, 1) = 'V' THEN 'V0000' ELSE '60000' END AS store_code,
                    PRD.product_code AS prod_code,
                    SUM(COALESCE(PRD.quantity, 0)) AS quantity
                FROM torders ORD
                JOIN torderproducts PRD ON ORD.doc_no = PRD.doc_no
                WHERE 
                    ORD.supply_dt = '$supply_dt' 
                    AND LEFT(ORD.msgfrom, 2) = 'GS'
                    AND (LEFT(ORD.dest_code, 1) = '6' OR LEFT(ORD.dest_code, 1) = 'V')
                GROUP BY 
                    ORD.supply_dt, 
                    CASE WHEN LEFT(ORD.dest_code, 1) = 'V' THEN 'V0000' ELSE '60000' END,
                    PRD.product_code
            ),
            latest_inventory AS (
                SELECT 
                    store_code,
                    prod_code,
                    MAX(increate_dt) AS latest_dt
                FROM tgscodeout_invent
                GROUP BY store_code, prod_code
            ),
            order_data AS (
                SELECT 
                    OS.supply_dt,
                    OS.store_code, 
                    CASE 
                        WHEN OS.store_code = 'V0000' THEN 'GS25물류(편의점)' 
                        WHEN OS.store_code = '60000' THEN (SELECT code_exp FROM tcomcodegs WHERE grp_code = '1' AND store_code = '60000' LIMIT 1)
                        ELSE NULL 
                    END AS store_no,
                    OS.prod_code,
                    COM.code_exp AS prod_nm,
                    OS.quantity,
                    INVT.increate_dt AS invent_dt,
                    COALESCE(INVT.invent_quantity, 0) AS invent_quantity
                FROM order_summary OS
                LEFT JOIN tcomcodegs COM ON COM.store_code = OS.prod_code AND COM.grp_code = 2
                LEFT JOIN latest_inventory LI ON LI.store_code = OS.store_code AND LI.prod_code = OS.prod_code
                LEFT JOIN tgscodeout_invent INVT ON INVT.store_code = LI.store_code 
                    AND INVT.prod_code = LI.prod_code 
                    AND INVT.increate_dt = LI.latest_dt
                ORDER BY 
                    OS.supply_dt, 
                    OS.store_code,
                    OS.prod_code
            ),
            add_quantity AS (
                SELECT 
                    supply_dt,
                    CASE WHEN LEFT(dest_code, 1) = 'V' THEN 'V0000' ELSE '60000' END AS store_code,
                    product_code,
                    SUM(quantity_new) AS quantity_new
                FROM torderproducts_add
                WHERE supply_dt = '$supply_dt'
                GROUP BY supply_dt, 
                    CASE WHEN LEFT(dest_code, 1) = 'V' THEN 'V0000' ELSE '60000' END,
                    product_code
            ),
            combined_data AS (
                SELECT
                    o.supply_dt,
                    o.store_code,
                    o.store_no,
                    o.prod_code,
                    o.prod_nm,
                    o.quantity,
                    COALESCE(a.quantity_new, 0) AS quantity_new,
                    0 AS weight_in,
                    o.quantity AS prod_quantity,
                    0 AS weight_out,
                    0 AS prod_yield,
                    o.invent_dt AS invent_dt,
                    0 AS before_invent_quantity,
                    o.invent_quantity AS origin_invent_quantity,
                    0 AS invent_quantity,
                    '' AS invent_note
                FROM order_data o
                LEFT JOIN add_quantity a ON o.supply_dt = a.supply_dt AND o.store_code = a.store_code AND o.prod_code = a.product_code
                WHERE NOT EXISTS (
                    SELECT 1 FROM tgscodeout_data t
                    WHERE t.supply_dt = o.supply_dt AND t.store_code = o.store_code AND t.prod_code = o.prod_code
                )

                UNION

                SELECT
                    COALESCE(t.supply_dt, o.supply_dt) AS supply_dt,
                    COALESCE(t.store_code, o.store_code) AS store_code,
                    COALESCE(t.store_no, o.store_no) AS store_no,
                    COALESCE(t.prod_code, o.prod_code) AS prod_code,
                    COALESCE(t.prod_nm, o.prod_nm) AS prod_nm,
                    COALESCE(o.quantity, 0) AS quantity,
                    COALESCE(a.quantity_new, 0) AS quantity_new,
                    COALESCE(t.weight_in, 0) AS weight_in,
                    t.prod_quantity,
                    COALESCE(t.weight_out, 0) AS weight_out,
                    COALESCE(t.prod_yield, 0) AS prod_yield,
                    COALESCE(o.invent_dt, '') AS invent_dt,
                    COALESCE(t.before_invent_quantity, 0) AS before_invent_quantity,
                    COALESCE(o.invent_quantity, 0) AS origin_invent_quantity,
                    t.invent_quantity,
                    t.invent_note
                FROM tgscodeout_data t
                LEFT JOIN order_data o ON t.supply_dt = o.supply_dt AND t.store_code = o.store_code AND t.prod_code = o.prod_code
                LEFT JOIN add_quantity a ON t.supply_dt = a.supply_dt AND t.store_code = a.store_code AND t.prod_code = a.product_code
            )

            SELECT
                supply_dt,
                store_code,
                store_no,
                prod_code,
                prod_nm,
                SUM(DISTINCT quantity) AS quantity,  -- DISTINCT 추가
                SUM(DISTINCT quantity_new) AS quantity_new,
                CASE 
                    WHEN SUM(DISTINCT weight_in) = 0 THEN ''
                    ELSE CAST(SUM(DISTINCT weight_in) AS CHAR)
                END AS weight_in,
                MAX(prod_quantity) AS prod_quantity, 
                SUM(DISTINCT weight_out) AS weight_out,
                SUM(DISTINCT prod_yield) AS prod_yield,
                MAX(invent_dt) AS invent_dt,
                -- MAX(before_invent_quantity) AS before_invent_quantity,
                (SELECT t2.invent_quantity
                    FROM tgscodeout t2
                    WHERE t2.store_code = cbd.store_code
                    AND t2.prod_code = cbd.prod_code
                    AND t2.increate_dt < '$increate_dt'
                    ORDER BY t2.increate_dt DESC
                    LIMIT 1) AS before_invent_quantity,
                MAX(origin_invent_quantity) AS origin_invent_quantity, 
                MAX(invent_quantity) AS invent_quantity, 
                MAX(invent_note) AS invent_note 
            FROM combined_data cbd
            WHERE store_code IN ('V0000', '60000')
            AND quantity > 0
            GROUP BY store_code, prod_code, prod_nm
            ORDER BY store_code, prod_code;

    ";

    // error_log(
    //     "[barcodeInv2Gs_srv.php] SQL: " . $sql,
    //     3,
    //     $logFilePath
    // );

    $result = $conn->query($sql);
    if (!$result) {
        $error_message = "쿼리 실행 중 오류 발생: " . $conn->error;
        error_log($error_message, 3, $logFilePath);
        echo json_encode(array("error" => $error_message));
        $conn->close();
        return;
    }

    $json_string = $dbConn->rs2Json1(
        $result,
        '00'
    );
    echo $json_string;
    $conn->close();
    return;
}

// 생산량 및 최종 재고량 저장
if ($category === 'saveInvt') {

    $input = json_decode(file_get_contents('php://input'), true);
    $barcodeList = $input['barcodeList'];

    try {
        foreach ($barcodeList as $item) {
            $store_code = $item['store_code'];
            $store_no = $item['store_no'];
            $prod_code = $item['prod_code'];
            $prod_nm = $item['prod_nm'];
            $weight_in = $item['weight_in'];
            $prod_quantity = $item['prod_quantity'];
            $weight_out = $item['weight_out'];
            $prod_yield = $item['prod_yield'];
            $invent_quantity = $item['invent_quantity'];
            $invent_note = $item['invent_note'];

            // 문자를 decimal 타입으로 변환
            $weight_in = floatval(str_replace(',', '', $weight_in));
            $prod_quantity = floatval(str_replace(',', '', $prod_quantity));
            $weight_out = floatval(str_replace(',', '', $weight_out));
            $prod_yield = floatval(str_replace(',', '', $prod_yield));
            $invent_quantity = floatval(str_replace(',', '', $invent_quantity));

            // Check if the record exists
            $checkSql = "SELECT COUNT(*) as count FROM tgscodeout WHERE LEFT(increate_dt, 10) = '$increate_dt' AND store_code = '$store_code' AND prod_code = '$prod_code'";
            $checkResult = $conn->query($checkSql);

            $row = $checkResult->fetch_assoc();

            if ($row['count'] > 0) {
                // Record exists, perform update
                $sql = "UPDATE tgscodeout 
                        SET store_no = '$store_no', prod_nm = '$prod_nm', weight_in = $weight_in, prod_quantity = $prod_quantity, weight_out = $weight_out, prod_yield = $prod_yield, 
                            invent_quantity = $invent_quantity, invent_note = '$invent_note', modify_id = '$user_id'
                        WHERE LEFT(increate_dt, 10) = '$increate_dt' AND store_code = '$store_code' AND prod_code = '$prod_code'";
            } else {
                // Record does not exist, perform insert
                $sql = "INSERT INTO tgscodeout (increate_dt, store_code, store_no, prod_code, prod_nm, weight_in, prod_quantity, weight_out, prod_yield, invent_quantity, invent_note, create_id)
                        VALUES ('$increate_dt', '$store_code', '$store_no', '$prod_code', '$prod_nm', $weight_in, $prod_quantity, $weight_out, $prod_yield, $invent_quantity, '$invent_note', '$user_id')";
            }

            if (!$conn->query($sql)) {
                throw new Exception('Error: ' . $sql . '<br>' . $conn->error);
            }

            // tgscodeout_invent 에 최종 재고량 저장
            $sql = "REPLACE INTO tgscodeout_invent (store_code, store_no, prod_code, prod_nm, increate_dt, invent_quantity, create_id)
                    VALUES ('$store_code', '$store_no', '$prod_code', '$prod_nm', '$increate_dt', $invent_quantity, '$user_id')
                ";

            if (!$conn->query($sql)) {
                throw new Exception('Error: ' . $sql . '<br>' . $conn->error);
            }
        }

        $conn->close();
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {

        error_log("[ln::320] SQL= " . $sql, 3, $logFilePath);
        error_log("[gscodeOut_srv.php] Error: " . $e->getMessage(), 3, $logFilePath);

        $conn->close();

        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}
