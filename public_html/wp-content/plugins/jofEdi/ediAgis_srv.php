<?PHP

session_start();
date_default_timezone_set('Asia/Seoul');

require $_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php';

$dbConn   = new dbconnClassFish;
$conn     = $dbConn->conn();
mysqli_set_charset($conn, 'utf8'); 
mysqli_query($conn, "SET time_zone = '+09:00'");


$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log"; 

$category    = isset($_GET['category']) ? $_GET['category'] : 'order';
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

if ($category == 'order') {
    $supply_date = isset($_GET['date']) ? date('Ymd', strtotime($_GET['date'])) : date('Ymd'); // 날짜 형식을 yyyymmdd로 변경
    // 주문 관련 로직 처리
    $sql = "WITH base_data AS (
                    SELECT
                        c.store_code,
                        c.code_exp,
                        c.order_num,
                        COALESCE(latest_invt.invt_cnt, 0) AS invt_cnt,
                        ord.region,
                        COALESCE(prd.quantity, 0) AS quantity
                    FROM tcomcode c FORCE INDEX (idx_tcomcode_grp_use_order)
                    LEFT JOIN (
                        SELECT product_code, doc_no, quantity
                        FROM torderproducts
                    ) prd ON c.store_code = prd.product_code
                    LEFT JOIN torders ord ON prd.doc_no = ord.doc_no AND ord.supply_dt = '$supply_date'
                    LEFT JOIN (
                        SELECT i.store_code, i.invt_cnt
                        FROM tprodinvt i
                        JOIN (
                            SELECT store_code, MAX(invt_dt) AS max_dt
                            FROM tprodinvt
                            WHERE invt_dt <= '$supply_date'
                            GROUP BY store_code
                        ) m ON i.store_code = m.store_code AND i.invt_dt = m.max_dt
                    ) latest_invt ON c.store_code = latest_invt.store_code
                    WHERE c.use_yn = 'y'
                    AND c.grp_code = '2'
                ),
                pivot_data AS (
                    SELECT
                        store_code,
                        code_exp,
                        order_num,
                        invt_cnt,
                        SUM(CASE WHEN region = '양평점' THEN quantity ELSE 0 END) AS `양평(850)`,
                        SUM(CASE WHEN region = '양재점' THEN quantity ELSE 0 END) AS `양재(853)`,
                        SUM(CASE WHEN region = '상봉점' THEN quantity ELSE 0 END) AS `상봉(854)`,
                        SUM(CASE WHEN region = '일산점' THEN quantity ELSE 0 END) AS `일산(855)`,
                        SUM(CASE WHEN region = '대전점' THEN quantity ELSE 0 END) AS `대전(852)`,
                        SUM(CASE WHEN region = '광명점' THEN quantity ELSE 0 END) AS `광명(051)`,
                        SUM(CASE WHEN region = '의정부' THEN quantity ELSE 0 END) AS `의정부(053)`,
                        SUM(CASE WHEN region = '천안점' THEN quantity ELSE 0 END) AS `천안(052)`,
                        SUM(CASE WHEN region = '공세점' THEN quantity ELSE 0 END) AS `공세(056)`,
                        SUM(CASE WHEN region = '송도점' THEN quantity ELSE 0 END) AS `송도(057)`,
                        SUM(CASE WHEN region = '세종점' THEN quantity ELSE 0 END) AS `세종(058)`,
                        SUM(CASE WHEN region = '하남점' THEN quantity ELSE 0 END) AS `하남(060)`,
                        SUM(CASE WHEN region = '고척점' THEN quantity ELSE 0 END) AS `고척(062)`,
                        SUM(CASE WHEN region = '청라점' THEN quantity ELSE 0 END) AS `청라(063)`,
                        SUM(CASE WHEN region = '평택점' THEN quantity ELSE 0 END) AS `평택(064)`,
                        SUM(CASE WHEN region = '대구점' THEN quantity ELSE 0 END) AS `대구(851)`,
                        SUM(CASE WHEN region = '대구점2' THEN quantity ELSE 0 END) AS `혁신(059)`,
                        SUM(CASE WHEN region = '부산점' THEN quantity ELSE 0 END) AS `부산(856)`,
                        SUM(CASE WHEN region = '울산점' THEN quantity ELSE 0 END) AS `울산(050)`,
                        SUM(CASE WHEN region = '김해점' THEN quantity ELSE 0 END) AS `김해(061)`,
                        SUM(CASE WHEN region IN (
                            '양평점','양재점','상봉점','일산점','대전점','광명점','의정부','천안점',
                            '공세점','송도점','세종점','하남점','고척점','청라점','평택점'
                        ) THEN quantity ELSE 0 END) AS `서울소계`,
                        SUM(CASE WHEN region IN (
                            '대구점','대구점2','부산점','울산점','김해점'
                        ) THEN quantity ELSE 0 END) AS `지방소계`
                    FROM base_data
                    GROUP BY store_code, code_exp, order_num, invt_cnt
                )
                SELECT
                    store_code AS product_code,
                    code_exp AS product_name,
                    invt_cnt AS `재고`,
                    `양평(850)`, `양재(853)`, `상봉(854)`, `일산(855)`,
                    `대전(852)`, `광명(051)`, `의정부(053)`, `천안(052)`,
                    `공세(056)`, `송도(057)`, `세종(058)`, `하남(060)`,
                    `고척(062)`, `청라(063)`, `평택(064)`,
                    `대구(851)`, `혁신(059)`, `부산(856)`, `울산(050)`, `김해(061)`,
                    `서울소계`, `지방소계`, (`서울소계` + `지방소계`) AS `총합계`
                FROM pivot_data
                HAVING (`서울소계` + `지방소계`) > 0
                OR store_code IN ('0200000656896', '2000000590654', '0200000657529', '0200000661818')
                ORDER BY order_num ASC;
                    ";

    // error_log("\n[ln:104]order sql: " . $sql, 3, $logFilePath);
    
    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
}

if ($category == 'orderSumGs') {
    $supply_date = isset($_GET['date']) ? date('Ymd', strtotime($_GET['date'])) : date('Ymd'); // 날짜 형식을 yyyymmdd로 변경
    // 주문 관련 로직 처리
    $sql = " WITH order_summary AS (
                SELECT 
                    ORD.supply_dt,
                    CASE WHEN LEFT(ORD.dest_code, 1) = 'V' THEN 'V0000' ELSE '60000' END AS store_code,
                    PRD.product_code AS prod_code,
                    SUM(COALESCE(PRD.quantity, 0)) AS quantity
                FROM torders ORD
                JOIN torderproducts PRD ON ORD.doc_no = PRD.doc_no
                WHERE 
                    ORD.supply_dt = '$supply_date' 
                    AND LEFT(ORD.msgfrom, 2) = 'GS'
                    AND (LEFT(ORD.dest_code, 1) = '6' OR LEFT(ORD.dest_code, 1) = 'V')
                GROUP BY 
                    ORD.supply_dt, 
                    CASE WHEN LEFT(ORD.dest_code, 1) = 'V' THEN 'V0000' ELSE '60000' END,
                    PRD.product_code
            )
                SELECT 
                    CASE 
                        WHEN OS.store_code = 'V0000' THEN 'GS25물류(편의점)' 
                        WHEN OS.store_code = '60000' THEN (SELECT code_exp FROM tcomcodegs WHERE grp_code = '1' AND store_code = '60000' LIMIT 1)
                        ELSE NULL 
                    END AS '납품점',
                    OS.quantity AS '주문수량',
                    COM.code_exp AS '제품명',
                    OS.prod_code AS '제품코드'
                FROM order_summary OS
                LEFT JOIN tcomcodegs COM ON COM.store_code = OS.prod_code AND COM.grp_code = 2
                ORDER BY 
                    OS.supply_dt, 
                    OS.store_code,
                    OS.prod_code

            ";

    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
}


if ($category == 'comSearch') {
    $sql = "SELECT 
                serial_no AS '순번',
                grp_code AS '그룹코드',
                grp_nm AS '그룹코드명',
                store_code AS '코드',
                code_exp AS '코드 설명',
                unit AS '단위',
                tax_rate AS '세율',
                country_origin AS '원산지',
                order_num AS '순서',
                use_yn AS '사용여부',
                expi_day AS '유통기한',
                store_no AS '상품/납품처코드',
                temp_code AS '보관방법'
            FROM 
                tcomcode
            WHERE 1
              AND grp_code IN ( '1', '2' )
            --   AND store_code <> '000'              
            ORDER BY 
                grp_code ASC, order_num ASC;";

    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
}


if ($category == 'update') {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    $serial_no = $data[0];
    $grp_code = $data[1];
    $grp_nm = $data[2];
    $store_code = $data[3];
    $code_exp = $data[4];
    $unit = $data[5];
    $tax_rate = $data[6];
    $country_origin = $data[7];
    $order_num = $data[8];
    $use_yn = $data[9];
    $expi_day = $data[10];
    $store_no = $data[11];
    $temp_code = $data[12];

    // 음수/0 처리
    if ($order_num < 1) $order_num = 0;

    // 1. 해당 grp_code의 모든 row 조회 (order_num 오름차순)
    $sql = "SELECT serial_no, order_num FROM tcomcode WHERE grp_code = ? ORDER BY order_num ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $grp_code);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    // 2. 업데이트할 row의 order_num을 새 값으로 변경
    foreach ($rows as &$row) {
        if ($row['serial_no'] == $serial_no) {
            $row['order_num'] = $order_num;
            break;
        }
    }
    unset($row);

    // 3. order_num 기준으로 전체를 다시 정렬
    usort($rows, function($a, $b) { return $a['order_num'] - $b['order_num']; });

    // 4. 1부터 차례대로 order_num을 재부여
    foreach ($rows as $idx => &$row) {
        $row['order_num'] = $idx + 1;
    }
    unset($row);

    // 5. DB에 일괄 업데이트
    foreach ($rows as $row) {
        $update_sql = "UPDATE tcomcode SET order_num = ? WHERE serial_no = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("is", $row['order_num'], $row['serial_no']);
        $update_stmt->execute();
        $update_stmt->close();
    }

    // 나머지 필드 업데이트
    $update_sql = "UPDATE tcomcode SET 
                    grp_code = ?,
                    grp_nm = ?,
                    store_code = ?,
                    code_exp = ?,
                    unit = ?,
                    tax_rate = ?,
                    country_origin = ?,
                    use_yn = ?,
                    expi_day = ?,
                    store_no = ?,
                    temp_code = ?,
                    modify_id = ?
                   WHERE serial_no = ?";

    $stmt = $conn->prepare($update_sql);

    $stmt->bind_param(
        "sssssdsssssss",
        $grp_code,
        $grp_nm,
        $store_code,
        $code_exp,
        $unit,
        $tax_rate,
        $country_origin,
        $use_yn,
        $expi_day,
        $store_no,
        $temp_code,
        $user_id,
        $serial_no
    );

    header('Content-Type: application/json');
    if ($stmt->execute()) {
        echo json_encode(array("result" => "success"));
    } else {
        echo json_encode(array("result" => "error", "message" => $stmt->error));
    }

    $stmt->close();
}

// 공통코드 삭제    
if ($category == 'delete') {
    isset($_GET['serial_no']) ? $serial_no = $_GET['serial_no'] : $serial_no = "";
    $serial_no = isset($_GET['serial_no']) ? $_GET['serial_no'] : null;
    if ($serial_no) {
        $delete_sql = "DELETE FROM tcomcode WHERE serial_no = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("s", $serial_no);

        if ($delete_stmt->execute()) {
            echo json_encode(array("result" => "success", "message" => "삭제가 성공적으로 완료되었습니다."));
        } else {
            echo json_encode(array("result" => "error", "message" => "삭제 중 오류가 발생했습니다: " . $delete_stmt->error));
        }

        $delete_stmt->close();
    } else {
        echo json_encode(array("result" => "error", "message" => "serial_no가 제공되지 않았습니다."));
    }
}

if ($category == 'insert') {
    // JSON 형식의 입력 데이터를 받습니다.
    $json_input = file_get_contents('php://input');
    // JSON 문자열을 PHP 배열로 변환합니다.
    $data = json_decode($json_input, true);

    error_log(
        print_r($data, true),
        3,
        $logFilePath
    );

    $grp_code = $data['data'][0];
    $grp_nm = $data['data'][1];
    $store_code = $data['data'][2];
    $code_exp = $data['data'][3];
    $unit = $data['data'][4];
    $tax_rate = $data['data'][5];
    $country_origin = $data['data'][6];
    $order_num = $data['data'][7];
    $use_yn = $data['data'][8];
    $expi_day = $data['data'][9];
    $store_no = $data['data'][10];
    $temp_code = $data['data'][11];

    // 1. 새 row를 임시로 삽입 (order_num은 일단 입력값 사용)
    $insert_sql = "INSERT INTO tcomcode (grp_code, grp_nm, store_code, code_exp, unit, tax_rate, country_origin, order_num, use_yn, expi_day, store_no, temp_code, create_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssssdsisssss", $grp_code, $grp_nm, $store_code, $code_exp, $unit, $tax_rate, $country_origin, $order_num, $use_yn, $expi_day, $store_no, $temp_code, $user_id);
    $insert_stmt->execute();
    $insert_stmt->close();

    // 2. 해당 grp_code의 모든 row를 order_num 오름차순으로 조회
    $select_sql = "SELECT serial_no FROM tcomcode WHERE grp_code = ? ORDER BY order_num ASC, serial_no ASC";
    $select_stmt = $conn->prepare($select_sql);
    $select_stmt->bind_param("s", $grp_code);
    $select_stmt->execute();
    $result = $select_stmt->get_result();

    $serials = [];
    while ($row = $result->fetch_assoc()) {
        $serials[] = $row['serial_no'];
    }
    $select_stmt->close();

    // 3. 1부터 차례대로 order_num 재부여
    foreach ($serials as $idx => $serial_no) {
        $new_order_num = $idx + 1;
        $update_sql = "UPDATE tcomcode SET order_num = ? WHERE serial_no = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("is", $new_order_num, $serial_no);
        $update_stmt->execute();
        $update_stmt->close();
    }

    echo json_encode(array("result" => "success", "message" => "삽입 및 재정렬이 성공적으로 완료되었습니다."));
}

if ($category == 'modify_dt') {

    // Start Generation Here
    $latest_file = '';
    $files = glob($_SERVER['DOCUMENT_ROOT'] . '/ecommerce_java/recv/*');
    if ($files) {
        $latest_file = basename(max($files));
    }
    echo json_encode(array("status" => "success", "modify_dt" => $latest_file));
    return;

    // ==========================
    // 최신 modify_dt 가져오기
    // ==========================
    $sql = "SELECT MAX(modify_dt) AS latest_modify_dt FROM torders";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        $latest_modify_dt = $row['latest_modify_dt'];

        // JSON 형식으로 반환
        echo json_encode(array(
            "status" => "success",
            "modify_dt" => $latest_modify_dt
        ));
    } else {
        echo json_encode(array(
            "status" => "error",
            "message" => "최신 수정 일자를 가져오는데 실패했습니다."
        ));
    }

    $result->close();
    $conn->close();
}

if ($category == 'invtUpdate') {
    $store_code = isset($_GET['store_code']) ? $_GET['store_code'] : null; // 제품코드
    $invt_dt = isset($_GET['date']) ? $_GET['date'] : 0; // 날짜 형식을 yyyy-mm-dd
    $value = isset($_GET['value']) ? $_GET['value'] : null;

    $check_sql = "SELECT * FROM tprodinvt WHERE store_code = ? AND LEFT(invt_dt, 10) = ?";
    $check_stmt = $conn->prepare($check_sql);

    error_log("[ln:390] check_sql: " . $check_sql, 3, $logFilePath);


    $check_stmt->bind_param("ss", $store_code, $invt_dt);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $update_sql = "UPDATE tprodinvt SET invt_cnt = ?, modify_id = ? WHERE store_code = ? AND LEFT(invt_dt, 10) = ?";
        $update_stmt = $conn->prepare($update_sql);

        error_log("[ln:401] update_sql: " . $update_sql, 3, $logFilePath);

        $update_stmt->bind_param("ssss", $value, $user_id, $store_code, $invt_dt);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        $insert_sql = "INSERT INTO tprodinvt (invt_cnt, store_code, invt_dt, create_id) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssss", $value, $store_code, $invt_dt, $user_id);
        $insert_stmt->execute();
        $insert_stmt->close();
    }

    $check_stmt->close();
    echo json_encode(array("result" => "success", "message" => "재고가 성공적으로 업데이트되었습니다."));
}
