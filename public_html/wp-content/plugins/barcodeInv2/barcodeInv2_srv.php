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
isset($_GET['store_no']) ? $store_code = $_GET['store_no'] : $store_code = "";  // store_code 임
// 소비기한, 중량, 상품코드 => Update용
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

// category=1s: 납품상품조회 (연어 제외)
if ($category === '1s') {
    $supply_dt = str_replace('-', '', $increate_dt);
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY RIGHT(PRD.product_code, 6)) AS no,
                    ORD.supply_dt AS supply_dt,
                    CONCAT(ORD.region, '(', ORD.dest_code, ')') AS region,
                    CONCAT(
                        SUBSTRING(ORD.doc_no, -10, 3), '-',
                        SUBSTRING(ORD.doc_no, -7, 4), '-',
                        RIGHT(ORD.doc_no, 3)
	                ) AS PO,
                    RIGHT(PRD.product_code, 6) AS ITEM,
                    COM.code_exp AS product_name,
                    COM.unit AS unit,
                    TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(COALESCE(PRD.quantity_new, PRD.quantity) AS CHAR))) AS quantity,
                    COALESCE(PRD.expi_date, DATE_ADD(ORD.supply_dt, INTERVAL COM.expi_day DAY)) AS expi_date,
                    PRD.weights AS weights,
                    COM.country_origin AS country_origin,
                    FORMAT(PRD.unit_price, 0) AS unit_price,
                    FORMAT(PRD.total_price, 0) AS total_price,
                    COM.temp_code AS temp_code
                FROM `torders` ORD
                JOIN `torderproducts` PRD ON ORD.doc_no = PRD.doc_no
                JOIN `tcomcode` COM ON COM.store_code = PRD.product_code 
                    AND COM.grp_code = '2' 
                    AND COM.use_yn = 'y'
                    AND COM.store_code NOT IN ('0200000651343','2000000560770', '2000000668592')
                WHERE 1
                    AND ORD.msgfrom = 'COST01:DC'
                    AND ORD.supply_dt = '$supply_dt'
                    AND PRD.quantity <> 0
              ";

    // 전체 조회는 점코드 제외
    if ($store_code === '') {
        // $sql .= " AND store_no <> '조은점'";
    } else {
        if ($store_code !== '') $sql .= " AND ORD.dest_code = '$store_code'";
    }

    $sql .= " ORDER BY RIGHT(PRD.product_code, 6) ASC; ";
    $result = $conn->query($sql);

    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
    $conn->close();
    return;
}

// 업데이트
if ($category === 'update' || $category === 'weightsUpdate') {

    $inputData = file_get_contents('php://input');
    $data = json_decode($inputData, true); // JSON 데이터를 배열로 디코딩

    $key = $data['key'];
    $value = $data['value'];

    // error_log("Value: " . $value, 3, $logFilePath);

    $barcodeList = $data['barcodeList'];

    // 디버깅을 위해 barcodeList 출력
    // error_log("Barcode List: " . print_r($barcodeList, true), 3, $logFilePath);

    $po = str_replace('-', '', $barcodeList['po']);
    $product_code = $barcodeList['product_code'];

    $sql = "UPDATE torderproducts";

    $value = $barcodeList[$key];
    // 빈 값이면 NULL로 처리
    if ($value == '') {
        $value = 'NULL';
    }

    // 입점수량이 바뀌면 quantity_new로 업데이트
    if ($key === 'quantity') {
        $sql .= " SET quantity_new = $value";
    } else {
        // key=weights
        if ($value !== 'NULL') {
            $sql .= " SET $key = '$value', modify_id = '$user_id' ";
        } else {
            $sql .= " SET $key = $value, modify_id = '$user_id' ";
        }
    }

    // 개별 품목은 입점수량 업데이트
    if ($category === 'weightsUpdate') {
        $quantity = $barcodeList['quantity'];
        if ($value !== 'NULL') {
            $sql .= ", quantity_new = $quantity ";
        } else {
            $sql .= ", quantity_new = $value ";
        }
    }


    $sql .= " WHERE RIGHT(doc_no, 10) = '$po'
              AND RIGHT(product_code, 6) = '$product_code'";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(["success" => true, "message" => "success" . $sql]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating record: " . $conn->error . " - " . $conn->error_list[0]['error'] . " - SQL: " . $sql]);
    }

    $conn->close();
    return;
}
