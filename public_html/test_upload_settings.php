<?php
// PHP 업로드 설정 확인
echo "=== PHP 업로드 설정 확인 ===\n";

// 1. 업로드 관련 설정들
$settings = [
    'file_uploads' => ini_get('file_uploads'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir'),
    'memory_limit' => ini_get('memory_limit')
];

foreach ($settings as $key => $value) {
    echo "$key: $value\n";
}

// 2. 임시 디렉토리 확인
$tmp_dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
echo "\n임시 디렉토리: $tmp_dir\n";
echo "임시 디렉토리 쓰기 권한: " . (is_writable($tmp_dir) ? 'OK' : 'FAIL') . "\n";

// 3. 대상 디렉토리 확인
$target_dir = WP_PLUGIN_DIR . '/jofSubul/';
echo "\n대상 디렉토리: $target_dir\n";
echo "대상 디렉토리 존재: " . (file_exists($target_dir) ? 'OK' : 'FAIL') . "\n";
echo "대상 디렉토리 쓰기 권한: " . (is_writable($target_dir) ? 'OK' : 'FAIL') . "\n";

// 4. WordPress 플러그인 디렉토리 확인
echo "\nWP_PLUGIN_DIR: " . WP_PLUGIN_DIR . "\n";
echo "WP_PLUGIN_DIR 쓰기 권한: " . (is_writable(WP_PLUGIN_DIR) ? 'OK' : 'FAIL') . "\n";

// 5. 파일 업로드 테스트
if (isset($_FILES['test_file'])) {
    echo "\n=== 파일 업로드 테스트 ===\n";
    $file = $_FILES['test_file'];
    
    echo "파일명: " . $file['name'] . "\n";
    echo "임시 파일: " . $file['tmp_name'] . "\n";
    echo "파일 크기: " . $file['size'] . "\n";
    echo "업로드 오류: " . $file['error'] . "\n";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $test_target = $target_dir . 'test_upload.txt';
        if (move_uploaded_file($file['tmp_name'], $test_target)) {
            echo "✅ 테스트 파일 업로드 성공\n";
            unlink($test_target); // 테스트 파일 삭제
        } else {
            echo "❌ 테스트 파일 업로드 실패\n";
        }
    }
}
?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="test_file">
    <input type="submit" value="테스트 업로드">
</form> 