<?PHP
//통합검색
session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

$dbConn   = new dbconnClassFish;
$conn = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");


// URL 매개변수
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['createDate']) ? $createDate = $_GET['createDate'] : $createDate = "";
isset($_GET['lcNumber']) ? $lcNumber = $_GET['lcNumber'] : $lcNumber = "";
isset($_GET['barcode1']) ? $barcode1 = $_GET['barcode1'] : $barcode1 = "";
isset($_GET['barcode2']) ? $barcode2 = $_GET['barcode2'] : $barcode2 = "";

// category 1:입력, 2:조회
if ($category === "2") {
    // LC# 있으면 입력날짜 kwd 없애고 무시
    if ($lcNumber !== '') $createDate = '';
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
    if ($createDate !== '') $sql .= "   AND LEFT(increate_dt, 10) = '" . $createDate . "'";
    if ($lcNumber !== '') $sql .= "   AND lc_no = '" . $lcNumber . "'";
    if ($barcode1 !== '') $sql .= "   AND barcode_one = '" . $barcode1 . "'";
    if ($barcode2 !== '') $sql .= "   AND barcode_two = '" . $barcode2 . "'";
    $sql .= " )
            SELECT TotalCount - RowNum + 1 AS no,
                increate_dt, 
                display_name,
                lc_no, 
                barcode_one, 
                barcode_two, 
                weight, 
                prod_date, 
                create_dt
            FROM RankedItems
          ";
} else {

    // categori=1 (LC# 입력|확인) => LC# 으로 조회
    $sql = " WITH RankedItems AS (
                SELECT ROW_NUMBER() OVER (ORDER BY create_dt DESC) AS RowNum,
                    COUNT(*) OVER () AS TotalCount,
                    increate_dt, 
                    lc_no, 
                    barcode_one, 
                    barcode_two, 
                    weight, 
                    prod_date, 
                    create_dt
                FROM tbarcodein";
    $sql .= "   WHERE 1";
    if ($lcNumber !== '') $sql .= "   AND lc_no = '" . $lcNumber . "'";
    if ($createDate !== '') $sql .= "   AND LEFT(increate_dt, 10) = '" . $createDate . "'";
    $sql .= " )
            SELECT TotalCount - RowNum + 1 AS no,
                increate_dt, 
                lc_no, 
                barcode_one, 
                barcode_two, 
                weight, 
                prod_date, 
                create_dt
            FROM RankedItems
            ";
}
$sql .= " ORDER BY create_dt DESC ";
$result = $conn->query($sql);

// debug
// echo $sql;
// exit;

$json_string = $dbConn->rs2Json1($result, '00'); // 추세분석 JSON 파일명 전달
echo $json_string;
