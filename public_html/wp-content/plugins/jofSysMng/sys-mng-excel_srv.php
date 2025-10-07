<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

date_default_timezone_set('Asia/Seoul');

$dbConn = new dbconnClassFish;
$pdo = $dbConn->pdo_conn();

isset($_GET['category']) ? $category = $_GET['category'] : $category = "";
isset($_GET['startDate']) ? $startDate = $_GET['startDate'] : $startDate = "";
isset($_GET['endDate']) ? $endDate = $_GET['endDate'] : $endDate = "";
isset($_GET['searchKeyword']) ? $searchKeyword = $_GET['searchKeyword'] : $searchKeyword = "";

if ($category === 'sysMngExcel') {
    try {
        $sql = "SELECT 
                    SYS.serial_no,
                    DATE_FORMAT(SYS.increate_dt, '%Y-%m-%d %H:%i') as increate_dt,
                    DATE_FORMAT(SYS.request_dt, '%Y-%m-%d %H:%i') as request_dt,
                    SYS.title,
                    SYS.content,
                    SYS.work_detail,
                    DATE_FORMAT(SYS.start_dt, '%Y-%m-%d %H:%i') as start_dt,
                    DATE_FORMAT(SYS.end_dt, '%Y-%m-%d %H:%i') as end_dt,
                    SYS.cost_type,
                    COALESCE(wpu.display_name, '') as modify_name,
                    DATE_FORMAT(SYS.modify_dt, '%Y-%m-%d %H:%i') as modify_dt
                FROM tsystem_mng AS SYS
                LEFT JOIN wp_users wpu ON SYS.modify_id = wpu.user_login
                WHERE DATE(SYS.increate_dt) BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        
        if (!empty($searchKeyword)) {
            $sql .= " AND (SYS.title LIKE ? OR SYS.content LIKE ? OR SYS.work_detail LIKE ?)";
            $searchParam = '%' . $searchKeyword . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql .= " ORDER BY SYS.increate_dt DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 새 스프레드시트 생성
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('맑은 고딕');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(11);
        
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('시스템관리');
        
        // 헤더 설정
        $headers = ['번호', '등록일시', '요청일시', '제목', '내용', '작업내역', '시작일시', '종료일시', '비용구분', '수정자', '수정일시'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');
            $col++;
        }
        
        // 데이터 입력
        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['serial_no']);
            $sheet->setCellValue('B' . $row, $item['increate_dt']);
            $sheet->setCellValue('C' . $row, $item['request_dt']);
            $sheet->setCellValue('D' . $row, $item['title']);
            $sheet->setCellValue('E' . $row, $item['content']);
            $sheet->setCellValue('F' . $row, $item['work_detail']);
            $sheet->setCellValue('G' . $row, $item['start_dt']);
            $sheet->setCellValue('H' . $row, $item['end_dt']);
            $sheet->setCellValue('I' . $row, $item['cost_type']);
            $sheet->setCellValue('J' . $row, $item['modify_name']);
            $sheet->setCellValue('K' . $row, $item['modify_dt']);
            $row++;
        }
        
        // 열 너비 자동 조정
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // 모든 셀에 테두리 적용
        $highestRow = $sheet->getHighestRow();
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A1:K' . $highestRow)->applyFromArray($styleArray);
        
        // 첫 번째 셀 선택
        $sheet->setSelectedCell('A1');
        
        // 파일 다운로드
        $writer = new Xlsx($spreadsheet);
        
        // 출력 버퍼 정리
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode('업무일지.xlsx') . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        echo "오류가 발생했습니다: " . $e->getMessage();
    }
}
?>
