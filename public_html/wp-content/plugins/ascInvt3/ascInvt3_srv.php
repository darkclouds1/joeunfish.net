<?PHP
/* 
수불관리 조회 저장
tascinvt3 => trigger로 실사용, 여량 자동 저장 중
tascinvt3_m => 수불관리 summary 레코드도 trigger로 자동 저장 중  
*/

// 파일 최상단에 추가
header('Content-Type: application/json; charset=UTF-8');

session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');
$dbConn   = new dbconnClassFish;
$conn     = $dbConn->conn();

$pdo      = $dbConn->pdo_conn();
$pdo->exec("SET NAMES utf8mb4");

mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

// 조회 파라미터    
isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['increate_dt']) ? $increate_dt = $_GET['increate_dt'] : $increate_dt = "";
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

// 로그 출력
error_log("[ascInvt_srv.php] category: " . $category, 3, $logFilePath);
error_log("[ascInvt_srv.php] user_id: " . $user_id, 3, $logFilePath);
error_log("[ascInvt_srv.php] increate_dt: " . $increate_dt, 3, $logFilePath);

// category=1s: ASC 납품상품조회
if ($category === '1s') {
    $sql = "
    WITH LastKnownValues AS (
    SELECT 
        LEFT('$increate_dt', 10) as last_increate_dt, 
        t1.prod_code, 
        t1.prod_nm, 
        t1.asc_nm, 
        t1.raw_stock as last_raw_stock, 
        t1.unit_cost as last_unit_cost,
        t1.raw_cost as last_raw_cost,
        t1.sell_price as last_sell_price
    FROM tascinvt3 t1 
    WHERE t1.increate_dt = (
        SELECT MAX(increate_dt) 
        FROM tascinvt3 t2 
        WHERE t2.prod_code = t1.prod_code 
        AND t2.prod_nm = t1.prod_nm 
        AND t2.asc_nm = t1.asc_nm 
        AND DATE(increate_dt) <= '$increate_dt'
    )
),
SummaryData AS (
    SELECT 
        'SUM' as serial_no,
        LEFT('$increate_dt', 10) as increate_dt,
        t.prod_code,
        MAX(t.prod_nm) as prod_nm,
        '합계' as asc_nm,
        '' as init_stock,
        '' as in_luxury,
        '' as in_cheong,
        '' as unit_cost,
        SUM(t.out_qty) as out_qty,
        SUM(t.raw_stock) as raw_stock,
        SUM(t.realuse_qty) as realuse_qty,
        SUM(t.remain_qty) as remain_qty,
        FORMAT(SUM(t.raw_cost), 0) as raw_cost,
        FORMAT(MAX(t.sell_price), 0) as sell_price,
        FORMAT(SUM(t.out_qty) * MAX(t.sell_price), 0) as total_sales,
        CONCAT(FORMAT((SUM(t.out_qty) / NULLIF(SUM(t.realuse_qty), 0) * 100), 2), '%') as yield_rate,
        1 as is_summary
    FROM tascinvt3 t
    WHERE DATE(t.increate_dt) = '$increate_dt'
    GROUP BY t.prod_code
),
MainData AS (
    SELECT 
         '' as serial_no,
        COALESCE(LEFT(t.increate_dt, 10), last_increate_dt) as increate_dt,
        COALESCE(t.prod_code, l.prod_code) as prod_code,
        COALESCE(t.prod_nm, l.prod_nm) as prod_nm,
        COALESCE(t.asc_nm, l.asc_nm) as asc_nm,
        COALESCE(t.init_stock, l.last_raw_stock, '') as init_stock,
        COALESCE(t.in_luxury, '') as in_luxury,
        COALESCE(t.in_cheong, '') as in_cheong,
        FORMAT(COALESCE(t.unit_cost, l.last_unit_cost, ''), 0) as unit_cost,
        COALESCE(t.out_qty, '') as out_qty,
        COALESCE(t.raw_stock, l.last_raw_stock, 0) as raw_stock,
        CASE WHEN t.increate_dt IS NOT NULL THEN t.realuse_qty ELSE 0 END as realuse_qty,
        CASE WHEN t.increate_dt IS NOT NULL THEN t.remain_qty ELSE 0 END as remain_qty,
        FORMAT(COALESCE(t.raw_cost, l.last_raw_cost, 0), 0) as raw_cost,
        FORMAT(COALESCE(t.sell_price, l.last_sell_price, ''), 0) as sell_price,
        '' as total_sales,
        '' as yield_rate,
        0 as is_summary
    FROM (
        SELECT * FROM tascinvt3 WHERE DATE(increate_dt) = '$increate_dt'
    ) t 
    RIGHT JOIN LastKnownValues l 
        ON t.prod_code = l.prod_code 
        AND t.asc_nm = l.asc_nm 
        AND t.prod_nm = l.prod_nm

    UNION ALL

    SELECT *
    FROM SummaryData
),
NumberedData AS (
    SELECT 
        CASE 
            WHEN is_summary = 1 THEN 'SUM'
            WHEN DATE(increate_dt) != DATE('$increate_dt') THEN ''  -- 해당 날짜에 데이터가 없는 경우
            ELSE CAST(ROW_NUMBER() OVER (
                PARTITION BY CASE WHEN is_summary = 0 AND DATE(increate_dt) = DATE('$increate_dt') THEN 1 ELSE 0 END 
                ORDER BY 
                    prod_code,
                    CASE 
                        WHEN asc_nm = '합계' THEN 2
                        ELSE 1 
                    END,
                    asc_nm
            ) AS CHAR)
        END as serial_no,
        increate_dt,
        prod_code,
        prod_nm,
        asc_nm,
        init_stock,
        in_luxury,
        in_cheong,
        unit_cost,
        sell_price,
        out_qty,
        raw_stock,
        realuse_qty,
        remain_qty,
        raw_cost,
        total_sales,
        yield_rate
    FROM MainData
)
SELECT 
    serial_no,
    increate_dt,
    prod_code,
    prod_nm,
    asc_nm,
    init_stock,
    in_luxury,
    in_cheong,
    unit_cost,
    sell_price,
    out_qty,
    raw_stock,
    realuse_qty,
    remain_qty,
    raw_cost,
    total_sales,
    yield_rate
FROM NumberedData
ORDER BY 
    prod_code,
    CASE 
        WHEN asc_nm = '합계' THEN 2
        ELSE 1 
    END,
    asc_nm;
        ";


    error_log("[ascInvt_srv.php] SQL: " . $sql, 3, $logFilePath);

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

    // UTF-8로 인코딩 변환
    array_walk_recursive($barcodeList, function (&$item) {
        if (!mb_detect_encoding($item, 'UTF-8', true)) {
            $item = mb_convert_encoding($item, 'UTF-8', mb_detect_encoding($item, null, true));
        }
    });

    error_log("[ascInvt_srv.php] barcodeList: " . print_r($barcodeList, true), 3, $logFilePath);

    $results = array();
    $success = true;



    try {

        // 트랜잭션 시작
        $pdo->beginTransaction();

        // $increate_dt 에 해당하는 데이터가 이미 있으면 삭제
        $stmt = $pdo->prepare("DELETE FROM tascinvt3 WHERE DATE(increate_dt) = :increate_dt");
        $stmt->bindParam(':increate_dt', $increate_dt, PDO::PARAM_STR);
        $stmt->execute();


        $stmt = $pdo->prepare(
            "REPLACE INTO tascinvt3 
            (serial_no, increate_dt, prod_code, prod_nm, asc_nm, init_stock, in_luxury, in_cheong, 
            unit_cost, out_qty, raw_stock, realuse_qty, remain_qty, raw_cost, sell_price, create_id, modify_id) 
            VALUES (:serial_no, :increate_dt, :prod_code, :prod_nm, :asc_nm, :init_stock, :in_luxury, :in_cheong, 
            :unit_cost, :out_qty, :raw_stock, :realuse_qty, :remain_qty, :raw_cost, :sell_price, :create_id, :modify_id)"
        );

        // prod_code별 요약 데이터를 저장할 배열
        $summaryData = [];

        foreach ($barcodeList as $item) {
            // 숫자 필드에 대해 콤마와 쌍따옴표 제거 및 정수로 변환 또는 null 유지
            $fields = ['init_stock', 'in_luxury', 'in_cheong', 'unit_cost', 'out_qty', 'raw_stock', 'realuse_qty', 'remain_qty', 'raw_cost', 'sell_price'];
            foreach ($fields as $field) {
                $item[$field] = $item[$field] === '' ? null : (int) str_replace([',', '"'], '', $item[$field]);
            }

            $stmt->bindValue(
                ':serial_no',
                $item['serial_no'] ?: null,
                $item['serial_no'] ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $stmt->bindValue(':increate_dt', $item['increate_dt'], PDO::PARAM_STR);
            $stmt->bindValue(':prod_code', $item['prod_code'], PDO::PARAM_STR);
            $stmt->bindValue(':prod_nm', $item['prod_nm'], PDO::PARAM_STR);
            $stmt->bindValue(':asc_nm', $item['asc_nm'], PDO::PARAM_STR);
            $stmt->bindValue(':init_stock', $item['init_stock'], $item['init_stock'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':in_luxury', $item['in_luxury'], $item['in_luxury'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':in_cheong', $item['in_cheong'], $item['in_cheong'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':unit_cost', $item['unit_cost'], $item['unit_cost'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':out_qty', $item['out_qty'], $item['out_qty'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':raw_stock', $item['raw_stock'], $item['raw_stock'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':realuse_qty', $item['realuse_qty'], $item['realuse_qty'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':remain_qty', $item['remain_qty'], $item['remain_qty'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':raw_cost', $item['raw_cost'], $item['raw_cost'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':sell_price', $item['sell_price'], $item['sell_price'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':create_id', $user_id, PDO::PARAM_STR);
            $stmt->bindValue(':modify_id', $user_id, PDO::PARAM_STR);

            error_log("Executing query for item: " . print_r($item, true), 3, $logFilePath);

            if (!$stmt->execute()) {
                throw new Exception("Error executing query: " . implode(", ", $stmt->errorInfo()));
            }
        }

        $results[] = array(
            'serial_no' => $item['serial_no'],
            'increate_dt' => $item['increate_dt'],
            'success' => true
        );
        // 모든 작업이 성공적으로 완료되면 커밋
        $pdo->commit();
    } catch (Exception $e) {
        // 오류 발생 시 롤백
        $pdo->rollBack();
        $success = false;
        error_log("Transaction failed: " . $e->getMessage(), 3, $logFilePath);
        $results[] = array(
            'success' => false,
            'error' => $e->getMessage()
        );
    } finally {
        $stmt = null;
        $pdo = null;
    }

    echo json_encode(['success' => $success, 'results' => $results]);
    exit();
}

// 삭제 로직
if ($category === 'delete' && !empty($increate_dt)) {
    error_log("[ascInvt_srv.php] delete increate_dt: " . $increate_dt, 3, $logFilePath);

    try {
        // 데이터베이스 연결 (이미 연결되어 있다고 가정)
        // $pdo = new PDO(...);
        // 트랜잭션 시작
        $pdo->beginTransaction();

        // tascinvt3 테이블에서 데이터 삭제
        $stmt = $pdo->prepare("DELETE FROM tascinvt3 WHERE DATE(increate_dt) = :increate_dt");
        $stmt->bindParam(':increate_dt', $increate_dt, PDO::PARAM_STR);

        error_log("[ascInvt_srv.php] delete stmt: " . $stmt->queryString, 3, $logFilePath);
        $stmt->execute();

        // 트랜잭션 커밋
        $pdo->commit();

        // 성공 메시지 반환
        echo json_encode(['success' => true, 'message' => "생산일자 {$increate_dt}의 데이터가 성공적으로 삭제되었습니다."]);
    } catch (Exception $e) {
        // 오류 발생 시 롤백
        $pdo->rollBack();

        // 오류 메시지 반환
        echo json_encode(['success' => false, 'message' => "삭제 중 오류가 발생했습니다: " . $e->getMessage()]);
    }
    exit();
}
