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

// URL 매개변수
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['manualInput']) ? $manualInput = $_GET['manualInput'] : $manualInput = "";
isset($_GET['createDate']) ? $createDate = $_GET['createDate'] : $createDate = "";
isset($_GET['lcNumber']) ? $lcNumber = $_GET['lcNumber'] : $lcNumber = "";
isset($_GET['barcode1']) ? $barcode1 = $_GET['barcode1'] : $barcode1 = "";

isset($_GET['inputWeight']) ? $inputWeight = $_GET['inputWeight'] : $inputWeight = "";
isset($_GET['barcode2']) ? $barcode2 = $_GET['barcode2'] : $barcode2 = "";
isset($_GET['productionDate']) ? $productionDate = $_GET['productionDate'] : $productionDate = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";


if ($category === "i") {
    try {

        // 먼저 중복 체크 수행
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbarcodein WHERE barcode_one = ? AND barcode_two = ?");
        $check_stmt->bind_param("ss", $barcode1, $barcode2);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        $check_stmt->close();

        // 중복이 있는 경우, manualInput(수기입력) 아니면 중복 체크
        if ($row['count'] > 0 && $manualInput !== "true") {
            echo json_encode(array(
                'success' => false,
                'message' => "DB중복! 이전에 입력한 바코드 조합입니다!"
            ));
            $conn->close();
            return;
        }

        // 중복이 없는 경우 데이터 입력 진행
        $insert_stmt = $conn->prepare("INSERT INTO tbarcodein (
            increate_dt, 
            lc_no, 
            barcode_one, 
            barcode_two, 
            weight, 
            prod_date, 
            create_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $insert_stmt->bind_param(
            "ssssdss",
            $createDate,
            $lcNumber,
            $barcode1,
            $barcode2,
            $inputWeight,
            $productionDate,
            $user_id
        );

        if (!$insert_stmt->execute()) {
            throw new Exception($insert_stmt->error);
        }

        echo json_encode(array(
            'success' => true,
            'message' => '성공적으로 처리되었습니다.',
            'category' => $category,
            'barcode1' => $barcode1
        ));

        $insert_stmt->close();
    } catch (Exception $e) {
        echo json_encode(array(
            'success' => false,
            'message' => "DB 오류가 발생했습니다. 전산실에 문의해주세요. 오류: " . $e->getMessage()
        ));
    }
}

if ($category === "d") {
    $sql = "DELETE FROM tbarcodein WHERE barcode_one = '" . $barcode1 . "'" . " AND barcode_two = '" . $barcode2 . "'";

    // debug
    // echo ($sql);
    // return;
    if ($conn->query($sql) == true) {
        // SQL 실행 성공
        echo json_encode(array('success' => true, 'message' => '성공적으로 처리되었습니다.', 'category' => $category, 'barcode1' => $barcode1, 'barcode2' => $barcode2));
    } else {
        // SQL 실행 실패
        echo json_encode(array('success' => false, 'message' => "삭제 실패했습니다. 해당바코드 번호를 전산실에 문의하세요!", 'category' => $category));
    }

    $conn->close();
    return;
}

// category 1:bl#조회, 2:조회
if ($category === "1") {
    // categori=1 (LC# 입력|확인) => LC# 으로 조회
    $sql = " WITH RankedItems AS (
                    SELECT ROW_NUMBER() OVER (ORDER BY create_dt DESC) AS RowNum,
                        COUNT(*) OVER () AS TotalCount,
                        increate_dt, 
                        wpu.display_name as display_name,
                        lc_no, 
                        barcode_one, 
                        barcode_two, 
                        weight, 
                        prod_date, 
                        create_dt
                   FROM tbarcodein AS tbar, wp_users AS wpu 
                  WHERE 1 
                    AND tbar.create_id = wpu.user_login ";
    if ($lcNumber !== '') $sql .= "   AND lc_no = '" . $lcNumber . "'";
    if ($createDate !== '') $sql .= "   AND LEFT(increate_dt, 10) = '" . $createDate . "'";
    $sql .= " )
                SELECT TotalCount - RowNum + 1 AS no,
                    date(increate_dt) as increate_dt,
                    display_name,
                    lc_no, 
                    barcode_one, 
                    barcode_two, 
                    weight, 
                    date(prod_date) as  prod_date, 
                    DATE_FORMAT(create_dt, '%Y-%m-%d %H:%i') as create_dt
                FROM RankedItems ";
    $result = $conn->query($sql);
    $json_string = $dbConn->rs2Json1($result, '00'); 
    error_log("ln142::sql=" . $sql, 3, $logFilePath);
    echo $json_string;
}

// category 2: 바코드까지 조회
if ($category === "2") {

    if ($lcNumber !== '') $createDate = '';
    $sql = " WITH RankedItems AS (
                SELECT ROW_NUMBER() OVER (ORDER BY create_dt DESC) AS RowNum,
                    COUNT(*) OVER () AS TotalCount,
                    date(increate_dt) as increate_dt,
                    wpu.display_name as display_name,
                    lc_no, 
                    barcode_one, 
                    barcode_two, 
                    weight, 
                    prod_date, 
                    create_dt
                FROM tbarcodein AS tbar, wp_users AS wpu 
                WHERE 1 
                    AND tbar.create_id = wpu.user_login ";
    if ($barcode1 !== '') {
        $sql .= "   AND barcode_one = '" . $barcode1 . "'";
        $createDate = '';
        $lcNumber = '';
    }
    if ($barcode2 !== '') {
        $barcode2 = str_replace("",
            "",
            $barcode2
        );
        $sql .= "   AND barcode_two = '" . $barcode2 . "'";
        $createDate = '';
        $lcNumber = '';
    }

    // LC# 있으면 입력날짜 kwd 없애고 무시
    if ($lcNumber !== '') {
        $sql .= "   AND lc_no = '" . $lcNumber . "'";
        $createDate = '';
    } else {
        if ($createDate !== '') $sql .= "   AND LEFT(increate_dt, 10) = '" . $createDate . "'";
    }

    $sql .= " )
            SELECT TotalCount - RowNum + 1 AS no,
                date(increate_dt) as increate_dt, 
                display_name,
                lc_no, 
                barcode_one, 
                barcode_two, 
                weight, 
                date(prod_date) as prod_date, 
                DATE_FORMAT(create_dt, '%Y-%m-%d %H:%i') as create_dt
            FROM RankedItems ";

    $result = $conn->query($sql);
    // error_log("ln193::SQL=" . $sql, 3, $logFilePath);

    $json_string = $dbConn->rs2Json1($result, '00'); // 추세분석 JSON 파일명 전달
    error_log("ln203::sql=" . $sql, 3, $logFilePath);
    echo $json_string;
}

// category= BL#(송장번호) 최근10일 + alfa
if ($category === "lc") {
    $sql = " SELECT 
                DATE_FORMAT(MAX(import_dt), ' (%c/%e)') AS import_dt, 
                RIGHT(awb, 4) AS bl_no
            FROM 
                `tpurchase_imp`
            WHERE 
                import_dt >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                AND import_dt <= CURDATE()
            GROUP BY 
                RIGHT(awb, 4)
            ORDER BY 
                MAX(import_dt) DESC
            LIMIT 100
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
}

$conn->close();
exit;
