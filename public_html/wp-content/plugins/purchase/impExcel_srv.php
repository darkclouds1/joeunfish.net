<?PHP
mb_internal_encoding('UTF-8');

// 개발 환경 에러 출력
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 출력 버퍼링 비활성화
if (ob_get_length()) {
    ob_end_clean();
}

session_start();
date_default_timezone_set('Asia/Seoul');

require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

$dbConn   = new dbconnClassFish;
$conn     = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

$logFilePath = __DIR__ . "/logfile.log";

$category    = isset($_GET['category']) ? $_GET['category'] : 'order';
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";

$startDate   = isset($_GET['startDate']) ? date('Ymd', strtotime($_GET['startDate'])) : date('Ymd'); // 날짜 형식을 yyyymmdd로 변경
$endDate     = isset($_GET['endDate']) ? date('Ymd', strtotime($_GET['endDate'])) : date('Ymd'); // 날짜 형식을 yyyymmdd로 변경
$supply_date = $endDate; // 날짜 형식을 yyyymmdd로 변경


// Require the autoload file
$vendorPath = $_SERVER['DOCUMENT_ROOT'] . '/vendor';
$autoloadPath = $vendorPath . '/autoload.php';

if (file_exists($autoloadPath)) {
    error_log("autoload.php 파일을 찾았습니다: $autoloadPath", 3, $logFilePath);
    require $autoloadPath;
} else {
    error_log("autoload.php 파일을 찾을 수 없습니다: $autoloadPath", 3, $logFilePath);
    die("필요한 파일을 로드할 수 없습니다.");
}

try {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    // 스프레드시트 생성 후 추가
    $spreadsheet->getDefaultStyle()->getFont()->setName('맑은 고딕');
    $spreadsheet->getDefaultStyle()->getFont()->setSize(11);

    error_log("PhpSpreadsheet 클래스를 성공적으로 로드했습니다.", 3, $logFilePath);
} catch (Exception $e) {
    error_log("PhpSpreadsheet 클래스 로드 실패: " . $e->getMessage(), 3, $logFilePath);
    die("PhpSpreadsheet 클래스를 로드할 수 없습니다.");
}

// Composer 의존성 확인
$composerJsonPath = $vendorPath . '/composer.json';
if (file_exists($composerJsonPath)) {
    $composerJson = json_decode(file_get_contents($composerJsonPath), true);
    error_log("설치된 의존성: " . print_r($composerJson['require'], true), 3, $logFilePath);
} else {
    error_log("composer.json 파일을 찾을 수 없습니다.", 3, $logFilePath);
}

// Use PhpSpreadsheet classes
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
// use PhpOffice\PhpSpreadsheet\Style\Alignment;
// use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
// Create a new Spreadsheet object
// $spreadsheet = new Spreadsheet();
// $sheet = $spreadsheet->getActiveSheet();
// $sheet->setTitle('Sheet1');

// 기존 파일 로드
$filePath = __DIR__ . '/impExcel-tmp.xls';
error_log("filePath: " . $filePath, 3, $logFilePath);

$reader = IOFactory::createReader('Xls');
$spreadsheet = $reader->load($filePath);

// 스프레드시트 정보를 문자열로 변환
$spreadsheetInfo = "스프레드시트 로드됨: " . $spreadsheet->getProperties()->getTitle();
error_log($spreadsheetInfo, 3, $logFilePath);

// 로그 파일 권한 확인
if (!is_writable($logFilePath)) {
    die("로그 파일에 쓰기 권한이 없습니다: $logFilePath");
}

// error_log("data: " . print_r($spreadsheet, true), 3, $logFilePath);
$sheet = $spreadsheet->getSheetByName('Sheet1');

// 4행부터 모든 데이터 삭제
$highestRow = $sheet->getHighestRow(); // 시트의 가장 높은 행 번호를 가져옵니다.
$sheet->removeRow(3, $highestRow - 2); // 4행부터 마지막 행까지 삭제

// 데이터베이스에서 데이터를 조회
$data = fetchDataFromDatabase($startDate, $endDate);

try {
    // colindex 값이 배열순서
    foreach ($data as &$row) {
        $row = array_values($row);
    }
    unset($row); // 참조 해제
    // // $data의 rowIndex 값을 배열 순서로 변경
    // $data = array_values($data);
    // error_log("data: " . print_r($data, true), 3, $logFilePath);
    foreach ($data as $rowIndex => $row) {
        foreach ($row as $colIndex => $value) {
            try {
                if ($value === null) {
                    $value = '';
                } else {
                    if (in_array($colIndex, [2, 3, 4, 5, 6, 7, 8, 10])) { // M, N, P 열은 숫자로 입력
                        $value = is_numeric($value) ? $value : 0;
                    }
                    $value = strval($value); // 모든 데이터를 문자열로 변환
                }

                $cell = Coordinate::stringFromColumnIndex($colIndex + 2) . ($rowIndex + 3);
                if (in_array($colIndex, [2, 3, 4, 5, 6, 7, 8, 10])) { // 숫자로 입력
                    if ($colIndex == 8) {
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                    } else {
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    $sheet->setCellValue($cell, $value);
                } else {
                    $sheet->setCellValueExplicit($cell, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            } catch (Exception $e) {
                error_log("Row $rowIndex, Column $colIndex: 셀 값 설정 중 오류 발생 - " . $e->getMessage(), 3, $logFilePath);
            }
        }
    }

    // 모든 셀에 테두리 적용
    $highestRow = $sheet->getHighestRow();
    $range = 'B2:O' . $highestRow;
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // B3 셀을 선택
    $sheet->setSelectedCell('B3');
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage(), 3, $logFilePath);
    echo "오류가 발생했습니다: " . $e->getMessage();
}

// error_log("data: " . print_r($data, true), 3, $logFilePath);

// 파일 저장
// $writer = new Xls($spreadsheet);  // Excel5 포맷 사용
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
$writer->setPreCalculateFormulas(false);

$savePath = __DIR__ . '/impExcel-' . $supply_date . '.xls'; // 새 파일 이름
// error_log("data: " . print_r($savePath, true), 3, $logFilePath);
$writer->save($savePath);


// 파일 다운로드 부분 수정
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($savePath)) . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');

// 파일 삭제 (옵션)
unlink($savePath);
exit;

// End of Selection

function fetchDataFromDatabase($startDate, $endDate)
{
    global $conn;

    $query = "
            SELECT
                DATE_FORMAT(import_dt, '%Y년%m월%d일') AS '통관일자',
                awb AS 'BL/AWB',
                serial_no AS '순번',
                vol AS '박스수량',
                weight AS 'kg',
                -- 단가: @ 뒤 숫자, 소수점 2자리 DECIMAL로 변환
                CAST(SUBSTRING(remarks, LOCATE('@', remarks) + 1) AS DECIMAL(10, 2)) AS '단가',
                -- 금액(USD): weight * 단가, 소수점 2자리 반올림
                ROUND(weight * CAST(SUBSTRING(remarks, LOCATE('@', remarks) + 1) AS DECIMAL(10, 2)), 2) AS '금액(USD)',
                -- 통관환율: @ 앞 부분 추출 후 '/' 제거하고 소수점 2자리 DECIMAL로 변환
                CAST(REPLACE(SUBSTRING_INDEX(remarks, '@', 1), '/', '') AS DECIMAL(10, 2)) AS '통관환율',
                -- 송장금액(원): (weight * 단가) * 통관환율, 정수로 반올림 (소수점 0자리)
                ROUND(
                    (weight * CAST(SUBSTRING(remarks, LOCATE('@', remarks) + 1) AS DECIMAL(10, 2))) -- 기본 USD 금액 계산
                    *
                    CAST(REPLACE(SUBSTRING_INDEX(remarks, '@', 1), '/', '') AS DECIMAL(10, 2)) -- 적용할 환율
                , 0) AS '송장금액(원)',
                '' AS '송금일',
                -- 금액(원): (weight * 단가) * 통관환율, 소수점 2자리로 반올림
                ROUND(
                    (weight * CAST(SUBSTRING(remarks, LOCATE('@', remarks) + 1) AS DECIMAL(10, 2))) -- 기본 USD 금액 계산
                    *
                    CAST(REPLACE(SUBSTRING_INDEX(remarks, '@', 1), '/', '') AS DECIMAL(10, 2)) -- 적용할 환율
                , 2) AS '금액(원)',
                '' AS '', -- 컬럼명 없는 빈 컬럼
                '' AS '송금금액',
                '' AS '환차손익'
            FROM
                tpurchase_imp
            WHERE
                import_dt BETWEEN ? AND ?
            ORDER BY
                serial_no DESC;
            ";

    // 데이터베이스에서 데이터를 조회    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $data;
}
