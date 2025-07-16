<?PHP

session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

$dbConn = new dbconnClassFish;
$conn     = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET NAMES 'utf8'"); // 추가된 부분
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
$category    = isset($_GET['category']) ? $_GET['category'] : 'order';

isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

if ($category == 'order') {
    $supply_date = isset($_GET['date']) ? date('Ymd', strtotime($_GET['date'])) : date('Ymd'); // 날짜 형식을 yyyymmdd로 변경
    // 주문 관련 로직 처리
    $sql =" WITH fixed_products AS (
            SELECT DISTINCT
                c.store_code AS product_code,
                c.code_exp AS product_name,
                c.unit
            FROM
                tcomcodegs c
            JOIN
                torderproducts p ON c.store_code = p.product_code
            WHERE
                c.grp_code = '2'
                AND c.use_yn = 'y'
            ORDER BY
                c.order_num ASC
        )

        SELECT 
            FP.product_code,
            COM.code_exp AS product_name,
            ROUND(COALESCE(SUM(CASE 
                WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code IN ('62970', '60470', '62980', '68970','67370') 
                THEN PRD.quantity ELSE 0 
            END) / 
            CASE 
                WHEN FP.product_code = '8809611290037' THEN 4
                WHEN FP.product_code = '8809611290365' THEN 2
                WHEN FP.product_code = '8809611290266' THEN 4
                ELSE 1 
            END, 0)) AS '박스',

            CASE 
                WHEN SUM(CASE 
                    WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code IN ('62970', '60470', '62980', '68970','67370') 
                    THEN PRD.quantity ELSE 0 
                END) = 0 THEN 0
                WHEN FP.product_code = '8809611290037' THEN 4
                WHEN FP.product_code = '8809611290365' THEN 2
                WHEN FP.product_code = '8809611290266' THEN 4
                ELSE 1 
            END AS '입수',

            COALESCE(SUM(CASE WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code = '62970' THEN PRD.quantity ELSE 0 END), 0) AS '이천|62970',
            COALESCE(SUM(CASE WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code = '60470' THEN PRD.quantity ELSE 0 END), 0) AS '양산|60470',
            -- COALESCE(SUM(CASE WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code = '66970' THEN PRD.quantity ELSE 0 END), 0) AS '남사|66970',
            COALESCE(SUM(CASE WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code = '62980' THEN PRD.quantity ELSE 0 END), 0) AS '이천2|62980',
            COALESCE(SUM(CASE WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code = '68970' THEN PRD.quantity ELSE 0 END), 0) AS '부산|68970',
            COALESCE(SUM(CASE WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code = '67370' THEN PRD.quantity ELSE 0 END), 0) AS '화성|67370',

            COALESCE(GS.GS_qty, 0) AS 'GS편의점',

            COALESCE(SUM(CASE 
                WHEN ORD.msgfrom = 'GSSM01:DC' AND ORD.dest_code IN ('62970', '60470', '66970', '62980', '68970','67370') 
                THEN PRD.quantity ELSE 0 
            END), 0) + COALESCE(GS.GS_qty, 0) AS '총수량'

        FROM 
            fixed_products FP
        LEFT JOIN 
            tcomcodegs COM ON FP.product_code = COM.store_code
        LEFT JOIN 
            torderproducts PRD ON FP.product_code = PRD.product_code
        LEFT JOIN 
            torders ORD ON ORD.doc_no = PRD.doc_no AND ORD.supply_dt = ?
        LEFT JOIN 
            (
                SELECT 
                    PRD.product_code AS GS_code,
                    SUM(PRD.quantity) AS GS_qty
                FROM 
                    torderproducts PRD
                INNER JOIN 
                    torders ORD ON PRD.doc_no = ORD.doc_no
                WHERE 
                    ORD.supply_dt = ?
                    AND ORD.msgfrom = 'GSCT04:DC'
                GROUP BY PRD.product_code
            ) GS ON FP.product_code = GS.GS_code

        GROUP BY 
            FP.product_code, COM.code_exp, COM.order_num, GS.GS_code, GS.GS_qty
        ORDER BY 
            COM.order_num;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $supply_date, $supply_date); // 두 곳에 ?가 있으므로 "ss"
    $stmt->execute();
    $result = $stmt->get_result();
    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
    $stmt->close();
}


if ($category == 'orderGSCT04') {
    $pdo    = $dbConn->pdo_conn();
    $pdo->exec("SET NAMES 'utf8'");
    $supply_date = isset($_GET['date']) ? date('Ymd', strtotime($_GET['date'])) : date('Ymd'); // 날짜 형식을 yyyymmdd로 변경

    // GSCT04 주문 관련 로직 처리
    $stmt = $pdo->query("
        SELECT GROUP_CONCAT(DISTINCT
            CONCAT(
                'COALESCE(SUM(CASE WHEN ORD.dest_code = ''',
                dest_code,
                ''' THEN PRD.quantity ELSE 0 END), 0) AS `',
                region,
                '`'
            )
        ) AS cols
        FROM torders
        WHERE msgfrom = 'GSCT04:DC'
    ");

    $row = $stmt->fetch();
    $cols = $row['cols'] ?? ''; // $cols가 null일 경우 빈 문자열로 설정
    // error_log('cols' . print_r($cols, true), 3, $logFilePath); // 서버 로그에 데이터 기록

    // 동적 SQL 쿼리 생성 
    try {
        $sql = "
        SELECT 
            COM.store_code AS product_code,
            COM.code_exp AS product_name,
            COALESCE(SUM(PRD.quantity), 0) AS '총수량',
            $cols
        FROM 
            tcomcodegs COM
        LEFT JOIN torderproducts PRD ON COM.store_code = PRD.product_code
        LEFT JOIN torders ORD ON ORD.doc_no = PRD.doc_no 
        WHERE COM.grp_code = 2
          AND COM.use_yn = 'y'
          AND ORD.msgfrom = 'GSCT04:DC'
          AND ORD.supply_dt = '$supply_date' 
        GROUP BY 
            COM.code_exp, COM.store_code
        ORDER BY 
            COM.order_num
    ";

        // 동적 SQL 쿼리 실행
        $stmt = $pdo->query($sql);

        error_log('sql=' . print_r($sql, true), 3, $logFilePath); // 서버 로그에 데이터 기록

        // 결과를 JSON으로 변환하여 클라이언트로 전
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as &$row) {
            $new_row = [];
            foreach ($row as $key => &$value) {
                $new_key = simplify_region($key);
                $new_value = simplify_region($value);
                $new_row[$new_key] = $new_value;
            }
            $row = $new_row;
        }

        // 결과를 표시할 때 필터링 적용
        // error_log('result' . print_r($result, true), 3, $logFilePath); // 서버 로그에 데이터 기록

        // 결과를 JSON 형식으로 인코딩
        echo json_encode(array("result" => "success", "data" => $result));
    } catch (PDOException $e) {
        error_log('PDOException: ' . $e->getMessage(), 3, $logFilePath);
        echo json_encode(array("result" => "error", "message" => $e->getMessage()));
    }

    $pdo = null; // PDO 연결 닫기
}

// GSCT04 주문 관련 센터명 간략히 처리
function simplify_region($region)
{
    $exclude_strings = ['(주)지에스리테일', '㈜지에스리테일', '지에스리테일', 'GS25', '일배', '센터', '이편한점', '도화', '저온', 'CVS'];
    foreach ($exclude_strings as $exclude) {
        $region = str_replace($exclude, '', $region);
    }
    return $region;
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
                store_no AS '상품/납품처코드'
            FROM 
                tcomcodegs
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
    // JSON 형식의 입력 데이터를 받습니다.
    $json_input = file_get_contents('php://input');
    // JSON 문자열을 PHP 배열로 변환합니다.
    $data = json_decode($json_input, true);

    // 이제 $data 배열에서 필요한 값을 추출하여 변수에 할당할 수 있습니다.
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

    $update_sql = "UPDATE tcomcodegs SET 
                    grp_code = ?,
                    grp_nm = ?,
                    store_code = ?,
                    code_exp = ?,
                    unit = ?,
                    tax_rate = ?,
                    country_origin = ?,
                    order_num = ?,
                    use_yn = ?,
                    expi_day = ?,
                    store_no = ?,
                    modify_id = ?
                   WHERE serial_no = ?";

    $stmt = $conn->prepare($update_sql);

    $stmt->bind_param(
        "sssssdsisssss",
        $grp_code,
        $grp_nm,
        $store_code,
        $code_exp,
        $unit,
        $tax_rate,
        $country_origin,
        $order_num,
        $use_yn,
        $expi_day,
        $store_no,
        $user_id,
        $serial_no
    );
    // 서버 응답을 JSON 형식으로 일관되게 처리하도록 수정합니다.
    error_log(print_r(
        $data,
        true
    ), 3, $logFilePath);

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
        $delete_sql = "DELETE FROM tcomcodegs WHERE serial_no = ?";
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
    $store_no = $data['data'][10]; // 이 부분은 데이터 배열에 없으므로 오류가 발생할 수 있습니다.

    $insert_sql = "INSERT INTO tcomcodegs (grp_code, grp_nm, store_code, code_exp, unit, tax_rate, country_origin, order_num, use_yn, expi_day, store_no, create_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssssdsissss", $grp_code, $grp_nm, $store_code, $code_exp, $unit, $tax_rate, $country_origin, $order_num, $use_yn, $expi_day, $store_no, $user_id);

    if ($insert_stmt->execute()) {
        echo json_encode(array("result" => "success", "message" => "삽입이 성공적으로 완료되었습니다."));
    } else {
        echo json_encode(array("result" => "error", "message" => "삽입 중 오류가 발생했습니다: " . $insert_stmt->error));
    }
    $insert_stmt->close();
}
