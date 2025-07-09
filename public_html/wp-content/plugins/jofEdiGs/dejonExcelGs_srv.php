<?PHP

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

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

$category    = isset($_GET['category']) ? $_GET['category'] : 'order';
isset($_GET['user_id']) ? $user_id = $_GET['user_id'] : $user_id = "";
$supply_date = isset($_GET['date']) ? date('Ymd', strtotime($_GET['date'])) : date('Ymd'); // 날짜 형식을 yyyymmdd로 변경

// Require the autoload file
require '/home/joeunfish.online/vendor/autoload.php';

// Use PhpSpreadsheet classes
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($category === 'dejonExcel') {

    $firstThreeRows = [
        ['고객코드', '주문일자', '환종', '거래구분', '과세구분', '비고(건)', '납품처코드', '담당자코드', '단가구분', '품번', '납기일', '출하예정일', '주문수량', '주문단가(부가세미포함)', '주문단가(부가세포함)', '공급가', '부가세', '합계액', '비고(내역)', '외화단가', '외화금액', '프로젝트코드', '관리구분코드', '검사구분', '단가유형', '주문번호', 'LC번호', '주문순번', '견적번호', '견적순번', '모품목코드', '거래처명', '사업자번호', '주민번호', '대표자명', '업태', '종목', '주소', '전화번호', '팩스번호'],
        ['TR_CD', 'SO_DT', 'EXCH_CD', 'SO_FG', 'VAT_FG', 'REMARK_DC', 'SHIP_CD', 'PLN_CD', 'UMVAT_FG', 'ITEM_CD', 'DUE_DT', 'SHIPREQ_DT', 'SO_QT', 'SO_UM', 'VAT_UM', 'SOG_AM', 'SOV_AM', 'SOH_AM', 'REMARKD_DC', 'EXCH_UM', 'EXCH_AM', 'PJT_CD', 'MGMT_CD', 'QC_FG', 'UM_FG', 'SO_NB', 'LC_NB', 'SO_SQ', 'EST_NB', 'EST_SQ', 'SETITEM_CD', 'STRADE_TR_NM', 'STRADE_REG_NB', 'STRADE_PPL_NB', 'STRADE_CEO_NM', 'STRADE_BUSINESS', 'STRADE_JONGMOK', 'STRADE_DIV_ADDR1', 'STRADE_TEL', 'STRADE_FAX'],
        [
            '타입 : 문자\n길이 : 10\n필수 : True\n설명 : 영문/숫자 기준 10자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 8\n필수 : True\n설명 : 숫자 기준 8자리를 입력 하세요.(숫자만 입력)',
            '타입 : 문자\n길이 : 3\n필수 : True\n설명 : 영문/숫자 기준 3자리를 입력 하세요.',
            '타입 : 문자\n길이 : 1\n필수 : True\n설명 : 숫자만 입력하세요. (0.DOMESTIC, 1.LOCAL L/C, 2.구매승인서, 3.MASTER L/C, 4.T/T, 5.D/A, 6.D/P)',
            '타입 : 문자\n길이 : 1\n필수 : True\n설명 : 숫자만 입력하세요. (0.과세, 1.영세, 2.면세, 3.기타)',
            '타입 : 문자\n길이 : 60\n필수 : False\n설명 : 영문/숫자 기준 60자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 5\n필수 : False\n설명 : 영문/숫자 기준 5자리(최대)를 입력 하세요.(고객에 속한 납품처만 입력하세요.)',
            '타입 : 문자\n길이 : 5\n필수 : False\n설명 : 영문/숫자 기준 5자리(최대)를 입력 하세요.(회사에 속한 담당자코드만 입력하세요.)',
            '타입 : 문자\n길이 : 1\n필수 : True\n설명 : 숫자만 입력하세요 (0.부가세미포함단가, 1.부가세포함단가)',
            '타입 : 문자\n길이 : 30\n필수 : True\n설명 : 영문/숫자 기준 30자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 8\n필수 : True\n설명 : 숫자 기준 8자리를 입력 하세요.(숫자만 입력)',
            '타입 : 문자\n길이 : 8\n필수 : True\n설명 : 숫자 기준 8자리를 입력 하세요.(숫자만 입력)',
            '타입 : 숫자\n길이 : 17,6\n필수 : True\n설명 : 숫자 기준 17,6자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 숫자\n길이 : 17,6\n필수 : False\n설명 : 숫자 기준 17,6자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 숫자\n길이 : 17,6\n필수 : False\n설명 : 숫자 기준 17,6자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 숫자\n길이 : 17,4\n필수 : False\n설명 : 숫자 기준 17,4자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 숫자\n길이 : 17,4\n필수 : False\n설명 : 숫자 기준 17,4자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 숫자\n길이 : 17,4\n필수 : False\n설명 : 숫자 기준 17,4자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 문자\n길이 : 60\n필수 : False\n설명 : 영문/숫자 기준 60자리(최대)를 입력 하세요.',
            '타입 : 숫자\n길이 : 17,6\n필수 : False\n설명 : 숫자 기준 17,6자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 숫자\n길이 : 17,4\n필수 : False\n설명 : 숫자 기준 17,4자리를 입력 하세요.(숫자만 입력(소숫점자리수 정확히 입력))',
            '타입 : 문자\n길이 : 10\n필수 : False\n설명 : 영문/숫자 기준 10자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 10\n필수 : False\n설명 : 영문/숫자 기준 10자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 1\n필수 : False\n설명 : 숫자만 입력하세요. (0. 무검사, 1. 검사)',
            '타입 : 문자\n길이 : 10\n필수 : False\n설명 : 영문/숫자 기준 10자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 12\n필수 : False\n설명 : 영문/숫자 기준 12자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 20\n필수 : False\n설명 : 영문/숫자 기준 20자리(최대)를 입력 하세요.',
            '타입 : 숫자\n길이 : 5,0\n필수 : False\n설명 : 숫자 기준 5자리를 입력 하세요.(숫자만 입력)',
            '타입 : 문자\n길이 : 12\n필수 : False\n설명 : 영문/숫자 기준 12자리(최대)를 입력 하세요.',
            '타입 : 숫자\n길이 : 5,0\n필수 : False\n설명 : 숫자 기준 5자리를 입력 하세요.(숫자만 입력)',
            '타입 : 문자\n길이 : 30\n필수 : False\n설명 : 영문/숫자 기준 30자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 60\n필수 : False\n설명 : 영문/숫자 기준 60자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 10\n필수 : False\n설명 : 영문/숫자 기준 10자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 20\n필수 : False\n설명 : 영문/숫자 기준 20자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 30\n필수 : False\n설명 : 영문/숫자 기준 30자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 40\n필수 : False\n설명 : 영문/숫자 기준 40자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 40\n필수 : False\n설명 : 영문/숫자 기준 40자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 60\n필수 : False\n설명 : 영문/숫자 기준 60자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 20\n필수 : False\n설명 : 영문/숫자 기준 20자리(최대)를 입력 하세요.',
            '타입 : 문자\n길이 : 20\n필수 : False\n설명 : 영문/숫자 기준 20자리(최대)를 입력 하세요.'
        ]
    ];

    // error_log("firstThreeRows: " . print_r($firstThreeRows, true), 3, $logFilePath);

    // 첫 번째 행에 첫 번째 배열의 값을 설정
    foreach ($firstThreeRows[0] as $colIndex => $value) {
        $cell = Coordinate::stringFromColumnIndex($colIndex + 1) . '1';
        $sheet->setCellValue($cell, $value);
    }

    // 두 번째 행에 두 번째 배열의 값을 설정
    foreach ($firstThreeRows[1] as $colIndex => $value) {
        $cell = Coordinate::stringFromColumnIndex($colIndex + 1) . '2';
        $sheet->setCellValue($cell, $value);
    }

    // 세 번째 행에 세 번째 배열의 값을 설정
    foreach ($firstThreeRows[2] as $colIndex => $value) {
        $cell = Coordinate::stringFromColumnIndex($colIndex + 1) . '3';
        $value = str_replace("\\n", chr(10), $value); // \n 문자를 char(10)으로 변환
        $returnValue = $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getAlignment()->setWrapText(true); // 자동 줄바꿈 설정
    }

    // 데이터베이스에서 데이터를 조회
    $data = fetchDataFromDatabase($supply_date);

    // $data를 제4행(row number = 4) 부터 데이터
    // error_log("data: " . print_r($data, true), 3, $logFilePath);
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
                        $value = strval($value); // 모든 데이터를 문자열로 변환
                    }
                    $cell = Coordinate::stringFromColumnIndex($colIndex + 1) . ($rowIndex + 4);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                    $sheet->setCellValueExplicit($cell, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } catch (Exception $e) {
                    error_log("Row $rowIndex, Column $colIndex: 셀 값 설정 중 오류 발생 - " . $e->getMessage(), 3, $logFilePath);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage(), 3, $logFilePath);
        echo "오류가 발생했습니다: " . $e->getMessage();
    }

    $sheet->getStyle('A1:AN2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:AN2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    // 3번째 ROW의 문자가 상단 좌측으로 자동 정렬되도록 설정
    $sheet->getStyle('A3:AN3')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $sheet->getStyle('A3:AN3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    // 3번째 Row의 높이를 5cm로 설정
    $sheet->getRowDimension(3)->setRowHeight(100); // 1cm ≈ 28.3465 points, 5cm ≈ 141.7325 points

    $headerStyleArray = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'argb' => 'FFFFFFE0', // 연한 노란색
            ],
        ],
    ];
    $sheet->getStyle('A1:AN2')->applyFromArray($headerStyleArray);

    $headerStyleArray = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'argb' => 'FFBFBFBF', // 회색
            ],
        ],
        'font' => [
            'color' => ['argb' => 'FFFFFFFF'], // 흰색
        ],
    ];
    $sheet->getStyle('A3:AN3')->applyFromArray($headerStyleArray);

    // 데이터가 있는 모든 셀에 테두리
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];

    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray($styleArray);

    // 모든 컬럼 너비를 7cm로 동일하게 설정
    $columnIterator = $sheet->getColumnIterator();
    foreach ($columnIterator as $column) {
        $sheet->getColumnDimension($column->getColumnIndex())->setWidth(23); // 1cm ≈ 28.3465 points, 7cm ≈ 198.4255 points
    }

    $sheet->setSelectedCell('A1');

    // 엑셀 파일 저장 및 출력
    // $filename = 'dejon_report_' . $supply_date . '.xlsx';
    $filename = 'dejon_report_' . $supply_date . '.xls';  // 확장자를 .xlsx에서 .xls로 변경
    $savePath = '/home/joeunfish.online/' . $filename;
    // $writer = new Xlsx($spreadsheet);
    $writer = new Xls($spreadsheet);  // Xlsx 대신 Xls 사용
    $writer->save($savePath);

    // 엑셀 파일을 생성하고 다운로드
    // header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Type: application/vnd.ms-excel');  // MIME 타입 변경
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($savePath));
    readfile($savePath);

    // 파일 삭제 (옵션)
    unlink($savePath);
    exit;
}

function fetchDataFromDatabase($date)
{
    global $conn;

    $query = "
        SELECT '54115' AS '고객코드',
            tord.order_dt AS '주문일자',
            'KRW' AS '환종',
            '0' AS '거래구분',
            CASE 
                WHEN (tcomgrp2.tax_rate = 10) THEN 0    -- 과세
                WHEN (tcomgrp2.tax_rate = 0)   THEN 2    -- 면세
            END AS '과세구분',
            '' AS '비고(건)',
            tcomgrp1.store_no AS '납품처코드',
            '' AS '담당자코드',
            '0' AS '단가구분',
            tcomgrp2.store_no AS '품번',
            tord.supply_dt AS '납기일',
            tord.supply_dt AS '출하예정일',
            tprd.quantity AS '주문수량',
            tprd.unit_price AS '주문단가(부가세미포함)',
            '' AS '주문단가(부가세포함)',
            tprd.total_price AS '공급가',
            '' AS '부가세',
            '' AS '합계액',
            '' AS '비고(내역)',
            '' AS '외화단가',
            '' AS '외화금액',
            '' AS '프로젝트코드',
            '' AS '관리구분코드',
            '' AS '검사구분',
            '' AS '단가유형',
            CONCAT(
                    SUBSTRING(tord.doc_no, 5, 3), '-', 
                    SUBSTRING(tord.doc_no, 8, 4), '-', 
                    SUBSTRING(tord.doc_no, 12, 3)
                ) AS '주문번호',
            '' AS 'LC번호',
            '' AS '주문순번',
            '' AS '견적번호',
            '' AS '견적순번',
            '' AS '모품목코드',
            '' AS '거래처명',
            '' AS '사업자번호',
            '' AS '주민번호',
            '' AS '대표자명',
            '' AS '업태',
            '' AS '종목',
            '' AS '주소',
            '' AS '전화번호',
            '' AS '팩스번호'
        FROM torders AS tord
        JOIN torderproducts tprd ON tord.doc_no = tprd.doc_no    -- 문서번호
        JOIN tcomcode tcomgrp1 ON tcomgrp1.store_code = tord.dest_code AND tcomgrp1.grp_code = '1'    -- 점코드
        JOIN tcomcode tcomgrp2 ON tcomgrp2.store_code = tprd.product_code AND tcomgrp2.grp_code = '2' -- 품번
        WHERE tord.supply_dt = ?
        ORDER BY tcomgrp1.store_no ASC,
         CONCAT(
                    SUBSTRING(tord.doc_no, 5, 3), '-', 
                    SUBSTRING(tord.doc_no, 8, 4), '-', 
                    SUBSTRING(tord.doc_no, 12, 3)
                ) ASC;
    ";

    // 데이터베이스에서 데이터를 조회    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $data;
}
