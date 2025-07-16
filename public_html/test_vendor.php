<?php
// vendor 폴더 테스트 파일

echo "=== Vendor 폴더 테스트 ===\n";

// 1. autoload.php 파일 존재 확인
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "✅ autoload.php 파일 존재\n";
} else {
    echo "❌ autoload.php 파일 없음\n";
    exit(1);
}

// 2. autoload.php 로드
try {
    require_once $autoloadPath;
    echo "✅ autoload.php 로드 성공\n";
} catch (Exception $e) {
    echo "❌ autoload.php 로드 실패: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. PhpSpreadsheet 클래스 사용 가능 확인
try {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    echo "✅ PhpSpreadsheet 클래스 사용 가능\n";
    
    // 간단한 테스트
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Test');
    echo "✅ PhpSpreadsheet 기본 기능 테스트 성공\n";
    
} catch (Exception $e) {
    echo "❌ PhpSpreadsheet 테스트 실패: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== 모든 테스트 통과! Vendor 폴더가 정상 작동합니다. ===\n";
?> 