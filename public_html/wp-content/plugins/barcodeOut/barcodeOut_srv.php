<?PHP
session_start();
date_default_timezone_set('Asia/Seoul');
header('Content-Type: application/json'); // Ensure the correct content type is set for JSON response

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
// error_log("\n[ln13]barcodeInv_srv.php?category=" . $category, 3, $logFilePath);

// URL 매개변수 10개
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";
isset($_GET['increate_dt']) ? $increate_dt = $_GET['increate_dt'] : $increate_dt = "";
isset($_GET['store_no']) ? $store_code = $_GET['store_no'] : $store_code = "";  // store_code 임
isset($_GET['doc_no']) ? $doc_no = $_GET['doc_no'] : $doc_no = "";
isset($_GET['lc_no']) ? $lc_no = $_GET['lc_no'] : $lc_no = "";
isset($_GET['pack_color']) ? $pack_color = $_GET['pack_color'] : $pack_color = "";
isset($_GET['barcode_out']) ? $barcode_out = $_GET['barcode_out'] : $barcode_out = "";
isset($_GET['manu_date']) ? $manu_date = $_GET['manu_date'] : $manu_date = "";
isset($_GET['expi_date']) ? $expi_date = $_GET['expi_date'] : $expi_date = "";
isset($_GET['weight']) ? $weight = $_GET['weight'] : $weight = "";
isset($_GET['prod_code']) ? $prod_code = $_GET['prod_code'] : $prod_code = "";
isset($_GET['prod_nm']) ? $prod_nm = $_GET['prod_nm'] : $prod_nm = "";
isset($_GET['etc']) ? $etc = $_GET['etc'] : $etc = "";

// 점포코드의 점포명 가져오기
// store_no 는 => store_code가 넘어옴
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

// category 1:입력, i:Insert
if ($category === "1i") {
    try {
        // SQL 인젝션을 방지하기 위해 준비된 문장 사용
        $stmt = $conn->prepare("INSERT INTO tbarcodeout (increate_dt, barcode_out, lc_no, store_code, origin_store_code, store_no, pack_color, manu_date, expi_date, weight, prod_code, prod_nm, create_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('SQL 준비 오류: ' . $conn->error);
        }

        // origin_store_code 추가 $store_code 로 같은값을 저장
        $bind_result = $stmt->bind_param("sssssssssdsss", $increate_dt, $barcode_out, $lc_no, $store_code, $store_code, $store_nm, $pack_color, $manu_date, $expi_date, $weight, $prod_code, $prod_nm, $user_id);
        if (!$bind_result) {
            throw new Exception('바인딩 오류: ' . $stmt->error);
        }

        // SQL 실행
        if ($stmt->execute() === true) {
            // SQL 실행 성공
            echo json_encode(array('success' => true, 'message' => '성공적으로 처리되었습니다.', 'category' => $category, 'barcode_out' => $barcode_out));
        } else {
            // SQL 실행 실패 (중복 포함)
            if ($stmt->errno == 1062) { // MySQL 중복 키 오류 코드
                throw new Exception('DB중복! 이전에 입력한 바코드입니다!');
            } else {
                throw new Exception('DB 오류: ' . $stmt->error);
            }
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
        if (isset($conn)) {
            $conn->close();
        }
    }
    return;
}

// 영구삭제
if ($category === "d") {
    try {
        if ($barcode_out === '') {
            throw new Exception("바코드 번호가 넘어오지 않았습니다.");
        }

        if ($user_id === '') {
            throw new Exception("사용자 ID가 넘어오지 않았습니다.");
        }

        // WordPress 코어 로드
        if (!defined('ABSPATH')) {
            require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
        }

        // 사용자 객체 가져오기
        $user = get_user_by('login', $user_id);
        if (!$user) {
            // 사용자 권한 체크를 건너뛰고 일반 사용자로 처리
            $user_roles = ['subscriber'];
        } else {
            $user_roles = $user->roles;
        }

        // 삭제 조건 추가
        $sql = "DELETE FROM tbarcodeout WHERE 1
                AND barcode_out = '" . $conn->real_escape_string($barcode_out) . "'
                AND invoice_yn <> 'y'";

        // '관리자' 또는 '편집자' 역할이 아닌 경우에만 날짜 조건 추가
        if (!in_array('administrator', $user_roles) && !in_array('editor', $user_roles)) {
            $sql .= " AND DATE(create_dt) = CURDATE()";
        }

        if (!$conn->query($sql)) {
            throw new Exception("SQL 실행 중 오류가 발생했습니다: " . $conn->error);
        }

        if ($conn->affected_rows > 0) {
            echo json_encode(array(
                'success' => true,
                'message' => '성공적으로 처리되었습니다.',
                'category' => $category,
                'barcode_out' => $barcode_out
            ));
        } else {
            throw new Exception("삭제는 당일 생산 제품만 가능하고, 이미 출고된 상품은 삭제할 수 없습니다. 해당바코드 번호를 전산실에 문의하세요!");
        }
    } catch (Exception $e) {
        error_log("barcodeOut_srv.php Error: " . $e->getMessage(), 0);
        echo json_encode(array(
            'success' => false,
            'message' => $e->getMessage(),
            'category' => $category
        ));
    } finally {
        if (isset($conn)) {
            $conn->close();
        }
    }
    return;
}

// category=1s: 생산조회 
// - 1(BL# 입력) s:조회
if ($category === '1s') {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt ASC) AS no, 
                    date(increate_dt) as increate_dt, 
                    wpu.display_name as display_name,
                    lc_no,
                    doc_no,
                    barcode_out, 
                    SUBSTRING(store_no, CHAR_LENGTH(store_no) - 2, 3) AS store_no,
                    -- store_no, 
                    pack_color, 
                    manu_date, 
                    expi_date, 
                    weight, 
                    prod_code, 
                    prod_nm, 
                    invoice_yn, 
                    create_dt,
                    modify_dt
               FROM tbarcodeout AS tbar
               LEFT JOIN wp_users AS wpu ON tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
              WHERE 1 AND barcode_out IS NOT NULL
              ";

    // 전체 조회는 조은점 제외해서 조회 (필요 시)
    // if ($store_code === '') {
    // $sql .= " AND store_no <> '조은점'";
    //  } else {
    //     if ($store_nm !== '') $sql .= "     AND store_no = '" . $store_nm . "'";
    // }

    // // 생산일자 선택은 조은점 제외.
    // if ($store_no !== '000') {
    //     if ($increate_dt !== '') $sql .= " AND increate_dt = '$increate_dt'";
    // }
    // 생산조회는 일자별 조회 - 김민숙 팀장 조은점A, 조은점B 일자별 조회 필요
    if ($store_code !== '') $sql .= "   AND store_code = '$store_code'";
    if ($increate_dt !== '') $sql .= "  AND LEFT(increate_dt, 10) = '$increate_dt'";
    if ($lc_no !== '') $sql .= "        AND lc_no = '$lc_no'";
    if ($doc_no !== '') $sql .= "        AND doc_no = '$doc_no'";
    if ($barcode_out !== '') $sql .= "  AND barcode_out = '" . $barcode_out . "'";
    if ($pack_color !== '') $sql .= "   AND pack_color = '" . $pack_color . "'";
    if ($prod_nm !== '') $sql .= "      AND prod_nm = '" . $prod_nm . "'";

    // error_log("ln165::sql=" . $sql, 3, $logFilePath);

    $sql .= " ORDER BY create_dt DESC ";
    $result = $conn->query($sql);

    // error_log("ln193::sql=" . $sql, 3, $logFilePath);

    ob_clean();
    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
    $conn->close();
    return;
}

// 조회(제조일기준)
if ($category === '2s') {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt ASC) AS no,
                    date(increate_dt) as increate_dt,
                    wpu.display_name as display_name,
                    lc_no,
                    barcode_out,
                    SUBSTRING(store_no, CHAR_LENGTH(store_no) - 2, 3) AS store_no,
                    pack_color,
                    manu_date,
                    expi_date,
                    weight,
                    prod_code,
                    prod_nm,
                    invoice_yn,
                    date_format(create_dt, '%Y-%m-%d %H:%i') as create_dt
                FROM tbarcodeout AS tbar
               LEFT JOIN wp_users AS wpu ON tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
              WHERE 1 
                -- AND invoice_yn <> 'y'
              ";


    // 바코드는 단독으로 조회 
    if ($barcode_out !== '') {
        $sql .= "  AND barcode_out = '" . $barcode_out . "'";
    } else {
        if ($store_code !== '') $sql .= "   AND store_code = '$store_code'";
        if ($increate_dt !== '') $sql .= " AND manu_date = '$increate_dt'";
        // BL번호 조회, 색깔, 제품명 조회 
        if ($lc_no !== '') $sql .= "        AND lc_no = '" . $lc_no . "'";
        if ($pack_color !== '') $sql .= "   AND pack_color = '" . $pack_color . "'";
        if ($prod_nm !== '') $sql .= "      AND prod_nm = '" . $prod_nm . "'";
    }

    $sql .= " ORDER BY create_dt ASC ";

    // error_log("ln241::sql=" . $sql, 3, $logFilePath);

    $result = $conn->query($sql);

    $json_string = $dbConn->rs2Json1($result, '00'); // 추세분석 JSON 파일명 전달
    echo $json_string;
    $conn->close();
    return;
}

// 조회(제조일기준)
if ($category === 'origin_store_code') {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt ASC) AS no,
                    DATE(increate_dt) as increate_dt,
                    wpu.display_name as display_name,
                    lc_no,
                    barcode_out,
                    store_no AS store_no,
                    pack_color,
                    manu_date,
                    expi_date,
                    weight,
                    prod_code,
                    prod_nm,
                    invoice_yn,
                    date_format(create_dt, '%Y-%m-%d %H:%i') as create_dt
                FROM tbarcodeout AS tbar
               LEFT JOIN wp_users AS wpu ON tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
              WHERE 1
                AND barcode_out IS NOT NULL
              ";


    // 바코드와 lc_no는 단독으로 조회 
    if ($barcode_out !== '') {
        $sql .= "  AND barcode_out = '" . $barcode_out . "'";
    } else {
        if ($store_code !== '') $sql .= "   AND origin_store_code = '$store_code'";
        // if ($increate_dt !== '') $sql .= " AND manu_date = '$increate_dt'";
        if ($increate_dt !== '') $sql .= " AND DATE(create_dt) = '$increate_dt'";
        // BL번호 조회, 색깔, 제품명 조회 
        if ($lc_no !== '') $sql .= "        AND lc_no = '" . $lc_no . "'";
        if ($pack_color !== '') $sql .= "   AND pack_color = '" . $pack_color . "'";
        if ($prod_code !== '') $sql .= "      AND prod_code = '" . $prod_code . "'";
    }

    $sql .= " ORDER BY create_dt DESC ";

    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00'); // 추세분석 JSON 파일명 전달
    echo $json_string;
    $conn->close();
    return;
}

// 출고 시 삭제 상태에서 조회는 출고 안된것만 조회 함.
// - invoice_yn = 'n'
if ($category === '3s') {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt DESC) AS no,
                    increate_dt,
                    wpu.display_name as display_name,
                    lc_no,
                    barcode_out,
                    SUBSTRING(store_no, CHAR_LENGTH(store_no) - 2, 3) AS store_no,
                    -- store_no,
                    pack_color,
                    manu_date,
                    expi_date,
                    weight,
                    prod_code,
                    prod_nm,
                    invoice_yn,
                    create_dt
               FROM tbarcodeout AS tbar, wp_users AS wpu 
              WHERE 1 
                AND tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
                AND invoice_yn = 'n'
        ";

    // 전체 조회는 재고 제외해서 조회
    if ($store_code == '') {
        $sql .= " AND store_code NOT IN ('000','001','002','003','004')";
    } else {
        $sql .= " AND store_code = '" . $store_code . "'";
    }

    // 생산일자는 재고 제외
    if ($store_code !== '000' && $store_code !== '001' && $store_code !== '002' && $store_code !== '003' && $store_code !== '004') {
        if ($increate_dt !== '') $sql .= " AND LEFT(increate_dt, 10) = '$increate_dt'";
    }

    if ($lc_no !== '') $sql .= " AND lc_no = '" . $lc_no . "'";

    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
    $conn->close();
    return;
}

// 출고조회
// 2s조회::inv출고된것만 조회 = 2sinv
if ($category === '2sinv') {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt DESC) AS no,
                    date(increate_dt) as increate_dt,
                    wpu.display_name as display_name,
                    lc_no,
                    doc_no,
                    barcode_out,
                    SUBSTRING(store_no, CHAR_LENGTH(store_no) - 2, 3) AS store_no,
                    -- store_no,
                    pack_color,
                    manu_date,
                    expi_date,
                    weight,
                    prod_code,
                    prod_nm,
                    invoice_yn,
                    date_format(create_dt, '%Y-%m-%d %H:%i') as create_dt,
                    origin_store_code,
                    modify_dt
               FROM tbarcodeout AS tbar, wp_users AS wpu 
              WHERE 1 
                -- AND barcode_out IS NOT NULL
               AND tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
               AND tbar.store_code NOT IN ('000','001','002','003','004') -- 출고처가 없는점은 제외 (000:조은점, 001:원재료, 002:완출점, 003:조은A, 004:조은B)
               -- AND invoice_yn = 'y'  -- 완료된것만?
             ";

    // BL은 생산일자와 상관없이 조회
    if ($lc_no !== '') $sql .= " AND lc_no = '$lc_no'";
    if ($increate_dt !== '') $sql .= " AND LEFT(increate_dt, 10) = '$increate_dt'";

    if ($barcode_out !== '') $sql .= " AND barcode_out = '$barcode_out'";
    if ($store_code !== '') $sql .= "  AND store_code = '$store_code'";
    if ($doc_no !== '') $sql .= "      AND doc_no = '$doc_no'";

    if ($pack_color !== '') $sql .= "   AND pack_color = '$pack_color'";
    if ($prod_nm !== '') $sql .= "      AND prod_nm = '$prod_nm'";
    $sql .= " ORDER BY create_dt DESC ";

    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00');

    echo $json_string;
    $conn->close();
    return;
}

// 점포명전부::c1a
if ($category === 'c1a') {
    $sql = " SELECT store_code,
                    RIGHT(code_exp, 3) AS store_nm
                FROM `tcomcode`
                WHERE grp_code = '1'
                AND use_yn = 'y'
                GROUP BY store_code, store_nm
                ORDER BY order_num ASC
        ";

    $result = $conn->query($sql);
    $stores = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stores[] = $row;
        }
    }
    // JSON 형식으로 데이터 응답
    echo json_encode($stores);
    $conn->close();
    return;
}

// 점포명 생산일에 있는것만 조회::category c1:: 코드c, 납품점:1\
// - 해당일에 출고여부='n'인 납품점을 조회
if ($category === "c1") {
    $sql = " SELECT 
                    TCC.store_code AS store_code, 
                    RIGHT(TCC.code_exp, 3) AS store_nm
                FROM 
                    `tcomcode` AS TCC
                LEFT JOIN 
                    `tbarcodeout` AS TBO 
                ON 
                    TCC.store_code = TBO.store_code 
                    AND LEFT(TBO.increate_dt, 10) = '$increate_dt'
                WHERE 
                    TCC.grp_code = '1'
                    AND TCC.use_yn = 'y'
                    AND (TCC.store_code NOT IN ('000', '001', '002', '003', '004'))
                    -- AND (TCC.store_code IN ('000', '001', '002', '003', '004') OR TBO.increate_dt IS NOT NULL)
                    -- // 출고:: 재고 완출점(002) => 연어필렛완출점 GS점(005)
                GROUP BY 
                    TCC.store_code
                ORDER BY order_num ASC
            ";

    $result = $conn->query($sql);

    // error_log(print_r(
    //     $sql,
    //     true
    // ), 3, $logFilePath);


    $stores = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stores[] = $row;
        }
    }
    // JSON 형식으로 데이터 응답
    echo json_encode($stores);
    $conn->close();
    return;
}

// 상품명 조회::etc
// category c1:: 코드c, 상품:2
if ($category === "c2") {
    $sql = " SELECT store_code, RIGHT(code_exp) AS code_exp
                FROM `tcomcode` WHERE 1 ";
    if ($etc !== '') $sql .= " AND code_ext like '% " . $etc . "%'";
    $sql .= " AND grp_code = '2' --상품코드
                AND use_yn = 'y'
                ORDER BY code_exp asc
                ";
    $result = $conn->query($sql);
    $stores = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stores[] = $row;
        }
    }
    // JSON 형식으로 데이터 응답
    echo json_encode($stores);
    $conn->close();
    return;
}

// category= BL#(송장번호) 최근10일 + alfa, 최근10일 조회, 4자리 + B/E 입력건도 조회
if ($category === "lc") {
    $sql = " SELECT DATE_FORMAT(STR_TO_DATE(MAX(increate_dt), '%Y-%m-%d'), ' (%c/%e)') AS increate_dt, lc_no
                FROM `tbarcodein`
                WHERE STR_TO_DATE(increate_dt, '%Y-%m-%d') >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)
                GROUP BY lc_no
                ORDER BY MAX(STR_TO_DATE(increate_dt, '%Y-%m-%d')) DESC
                LIMIT 0, 50
                ";
    $result = $conn->query($sql);
    $lcNumber = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $lcNumber[] = $row;
        }
    }
    // JSON 형식으로 데이터 응답
    echo json_encode($lcNumber);
    $conn->close();
    return;
}

// 목록에서 '삭제' 버튼::joeun_cnt 조은점(000)으로 이동
if ($category === "invt") {
    if ($barcode_out !== '') {
        $sql = "SELECT 
                    b.origin_store_code, 
                    c.code_exp AS store_no
                FROM 
                    tbarcodeout b
                LEFT JOIN 
                    tcomcode c ON b.origin_store_code = c.store_code
                WHERE barcode_out = '$barcode_out'
                  AND c.grp_code = '1'
            ";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $origin_store_code = $row['origin_store_code'];
            $store_no = $row['store_no'];

            // 원재료(001), 완출점(002), 조은A(003), 조은B(004), GS점(005)
            if (in_array($origin_store_code, ['001', '002', '003', '004', '005'])) {
                $new_store_code = $origin_store_code;
                $new_store_no = $store_no;
            } else {    // 그 외
                $new_store_code = '000';
                $new_store_no = '조은점';
            }
        } else {
            echo json_encode(array('success' => false, 'message' => "기존 origin_store_code를 조회할 수 없습니다.", 'category' => $category));
            $conn->close();
            return;
        }

        $sql = "UPDATE tbarcodeout SET store_code = '" . $new_store_code . "', store_no = '" . $new_store_no . "', invoice_yn = 'n' WHERE barcode_out = '" . $barcode_out . "'";

        if ($conn->query($sql) == true) {
            // SQL 실행 성공
            echo json_encode(array('success' => true, 'message' => '성공적으로 처리되었습니다.', 'category' => $category, 'barcode_out' => $barcode_out, 'new_store_code' => $new_store_code, 'new_store_no' => $new_store_no));
        } else {
            // SQL 실행 실패
            echo json_encode(array('success' => false, 'message' => "삭제 실패했습니다. 해당바코드 번호를 전산실에 문의하세요!", 'category' => $category));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => "바코드 번호가 넘어오지 않았습니다. ", 'category' => $category));
    }
    $conn->close();
    return;
}

// [출고]상태에서 바코드 스캔 - '재고'상품만 선택한 점포로 이동 (업데이트)
if ($category === "1u") {
    if ($barcode_out !== '') {

        // 재고에서만 출고: 조은점(000),원재료(001),완출점(002),조은A(003),조은B(004)
        // GS점(005) 출고: 모든 재고에서 가능 함
        $sql = "SELECT COUNT(1) AS Cnt FROM tbarcodeout
                WHERE store_code in ('000','001','002','003','004') 
                  AND invoice_yn = 'n'
                AND barcode_out = '" . $barcode_out . "'";
        $result = $conn->query($sql);

        if ($result) {
            // 쿼리 성공
            $row = $result->fetch_assoc();
            if ($row['Cnt'] > 0) {
                // 재고에 있음
                $sql = "UPDATE tbarcodeout SET increate_dt = '" . $increate_dt . "', store_code = '" . $store_code . "', store_no = '" . $store_nm .
                    "' WHERE invoice_yn = 'n' AND barcode_out = '" . $barcode_out . "'";
                if ($conn->query($sql) == true) {
                    // SQL 실행 성공
                    echo json_encode(array('success' => true, 'message' => '성공적으로 처리되었습니다.', 'category' => $category, 'barcode_out' => $barcode_out));
                } else {
                    // SQL 실행 실패
                    echo json_encode(array('success' => false, 'message' => "삭제 실패했습니다. 해당바코드 번호를 전산실에 문의하세요!", 'category' => $category));
                }
            } else {
                // 재고에 없음
                echo json_encode(array('success' => false, 'message' => "재고에 없는 제품(바코드)입니다.", 'category' => $category));
            }
        } else {
            // 쿼리 실패
            echo "SQL 실행 오류: " . $conn->error;
        }
    } else {
        echo json_encode(array('success' => false, 'message' => "바코드 번호가 넘어오지 않았습니다. ", 'category' => $category));
    }
    $conn->close();
    return;
}

// 조은점 재고조회::etc
// category c1:: 코드c, 조은점재고:3
if ($category === "c3") {
    $sql = " SELECT
                COALESCE(SUM(CASE WHEN prod_nm = '연어필렛' AND store_code != '002' THEN 1 ELSE 0 END), 0) AS cnt_fillets,
                COALESCE(SUM(CASE WHEN prod_nm = '연어(스테이크)' AND store_code != '002' THEN 1 ELSE 0 END), 0) AS cnt_steaks,
                COALESCE(SUM(CASE WHEN prod_nm = '연어(스테이크용)벌크' AND store_code != '002' THEN 1 ELSE 0 END), 0) AS cnt_steaks_bulk,
                COALESCE(SUM(CASE WHEN store_code = '002' THEN 1 ELSE 0 END), 0) AS cnt_wanchul
            FROM 
                `tbarcodeout`
           WHERE store_code IN ('000','001','002','003','004')
             AND invoice_yn = 'n'
                ";
    $result = $conn->query($sql);
    $stores = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stores = $row;
        }
    }
    // JSON 형식으로 데이터 응답
    echo json_encode($stores);
    $conn->close();
    return;
}

// category c4:: 코드c, 조은점재고:3, 조은점재고상세조회:4
// -barcode 재고조회
if ($category === "c4") {
    $sql = " SELECT manu_date, expi_date, weight, prod_code, prod_nm
                FROM `tbarcodeout`
                WHERE 1
                AND store_code in ('000','001','002','003','004')
                AND invoice_yn = 'n'
                AND barcode_out = '" . $barcode_out . "'";
    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00'); // 1건
    echo $json_string;
    $conn->close();
    return;
}

// 재고상세조회, 조은점+완출점
if ($category === "invtS") {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt ASC) AS no,
                    date(increate_dt) as increate_dt,
                    wpu.display_name as display_name,
                    lc_no,
                    barcode_out,
                    SUBSTRING(store_no, CHAR_LENGTH(store_no) - 2, 3) AS store_no,
                    pack_color,
                    manu_date,
                    expi_date,
                    weight,
                    prod_code,
                    prod_nm,
                    invoice_yn,
                    create_dt
               FROM tbarcodeout AS tbar, wp_users AS wpu 
              WHERE 1 
                -- 재고:: 조은점(000), 원재료(001), 완출(002), 조은A(003), 조은B(004)
                AND store_code in ('000','001','002','003','004')
                AND invoice_yn = 'n'
                AND tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
              ";

    // BL번호 조회, 색깔, 제품명 조회 
    $result = $conn->query($sql);

    // error_log("[ln679]SQL=" . print_r($sql, true), 3, $logFilePath);

    $json_string = $dbConn->rs2Json1($result, '00'); // 추세분석 JSON 파일명 전달
    echo $json_string;
    $conn->close();
    return;
}

// 재고상세조회, 완출점
if ($category === "invt002S") {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt ASC) AS no,
                    increate_dt,
                    wpu.display_name as display_name,
                    lc_no,
                    barcode_out,
                    SUBSTRING(store_no, CHAR_LENGTH(store_no) - 2, 3) AS store_no,
                    -- store_no,
                    pack_color,
                    manu_date,
                    expi_date,
                    weight,
                    prod_code,
                    prod_nm,
                    invoice_yn,
                    create_dt
               FROM tbarcodeout AS tbar, wp_users AS wpu 
              WHERE 1 
                -- 재고:: 완출(002)
                AND store_code in ('002')
                AND invoice_yn = 'n'
                AND tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
              ";

    // BL번호 조회, 색깔, 제품명 조회 
    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00'); // 추세분석 JSON 파일명 전달
    echo $json_string;
    $conn->close();
    return;
}

// 기출고(=기성출고) 여부 확인
// category invYn::invoice Yn
if ($category === "invYn") {
    isset($_GET['store_code']) ? $store_code = $_GET['store_code'] : $store_code = "";

    $sql = "SELECT COALESCE(
                (SELECT invoice_yn 
                FROM `tbarcodeinv_m`
                WHERE LEFT(increate_dt, 10) = '$increate_dt'
                AND store_code = '$store_code'
                ), 'n'
            ) AS invoice_yn
            ";
    $result = $conn->query($sql);
    $invoice_yn = $result->fetch_assoc();
    // JSON 형식으로 데이터 응답
    $encoded_invoice_yn = base64_encode($invoice_yn['invoice_yn']);
    echo json_encode(['invoice_yn' => $encoded_invoice_yn]);
    $conn->close();
    return;
}

// 조회된 전체 barcodeList 의 내용을 삭제

if ($_GET['category'] === 'deleteAll') {
    try {

        // POST 데이터 확인
        $postData = file_get_contents('php://input');
        // error_log("Raw POST data: " . $postData);  // 디버깅용

        if (empty($postData)) {
            throw new Exception('POST 데이터가 없습니다.');
        }

        // JSON 디코딩
        $data = json_decode($postData, true);
        // error_log("Decoded data: " . print_r($data, true));  // 디버깅용

        if (
            json_last_error() !== JSON_ERROR_NONE
        ) {
            throw new Exception('JSON 파싱 오류: ' . json_last_error_msg());
        }
        // barcodes 배열 확인
        $barcodeList = $data['barcodes'] ?? null;
        if (!$barcodeList || !is_array($barcodeList)) {
            throw new Exception('올바른 바코드 데이터가 아닙니다.');
        }

        $conn->begin_transaction();

        $successCount = 0;
        foreach ($barcodeList as $item) {
            if (empty($item['barcode_out'])) continue;

            $stmt = $conn->prepare("DELETE FROM tbarcodeout WHERE barcode_out = ? AND invoice_yn = 'n'");
            $stmt->bind_param("s", $item['barcode_out']);

            if ($stmt->execute()) {
                $successCount++;
            }

            $stmt->close();
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "{$successCount}개의 바코드가 성공적으로 삭제되었습니다."
        ]);
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// 일자별 출고 조회
if ($category === 'sumSearch') {
    $sql = " SELECT ROW_NUMBER() OVER (ORDER BY create_dt ASC) AS no, 
                    increate_dt, 
                    wpu.display_name as display_name,
                    lc_no, 
                    barcode_out, 
                    SUBSTRING(store_no, CHAR_LENGTH(store_no) - 2, 3) AS store_no,
                    -- store_no, 
                    pack_color, 
                    manu_date, 
                    expi_date, 
                    weight, 
                    prod_code, 
                    prod_nm, 
                    invoice_yn, 
                    create_dt 
               FROM tbarcodeout AS tbar, wp_users AS wpu 
              WHERE 1 
                AND tbar.create_id COLLATE utf8mb4_unicode_ci = wpu.user_login COLLATE utf8mb4_unicode_ci
              ";

    // 전체 조회는 조은점 제외해서 조회 (필요 시)
    if ($store_code === '') {
        // $sql .= " AND store_no <> '조은점'";
    } else {
        if ($store_nm !== '') $sql .= "     AND store_no = '" . $store_nm . "'";
    }

    // // 생산일자 선택은 조은점 제외.
    // if ($store_no !== '000') {
    //     if ($increate_dt !== '') $sql .= " AND increate_dt = '$increate_dt'";
    // }
    // 생산조회는 일자별 조회 - 김민숙 팀장 조은점A, 조은점B 일자별 조회 필요
    if ($increate_dt !== '') $sql .= "  AND LEFT(increate_dt, 10) = '$increate_dt'";
    if ($lc_no !== '') $sql .= "        AND lc_no = '$lc_no'";
    if ($barcode_out !== '') $sql .= "  AND barcode_out = '" . $barcode_out . "'";
    if ($pack_color !== '') $sql .= "   AND pack_color = '" . $pack_color . "'";
    if ($prod_nm !== '') $sql .= "      AND prod_nm = '" . $prod_nm . "'";


    $sql .= " ORDER BY create_dt ASC ";
    $result = $conn->query($sql);

    $json_string = $dbConn->rs2Json1($result, '00');
    echo $json_string;
    $conn->close();
    return;
}
