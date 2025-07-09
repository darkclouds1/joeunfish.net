<?php
// dejonExcel_srv2.php

// 필요한 라이브러리 로드
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 엑셀 파일 생성
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', '고객코드');
$sheet->setCellValue('A2', '테스트 데이터');


// 엑셀 파일 임시 저장
$filename = 'test_basic.xlsx';
$savePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filename;
$writer = new Xlsx($spreadsheet);
$writer->save($savePath);

// 파일 다운로드 응답
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($savePath));
readfile($savePath);

// 파일 삭제 (옵션)
unlink($savePath);
exit;
