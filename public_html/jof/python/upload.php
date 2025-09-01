<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
error_log("This is a test log entry.", 3, $logFilePath);


// 로그 파일 경로의 쓰기 권한 확인
if (!is_writable(dirname($logFilePath))) {
    echo "로그 파일 경로에 쓰기 권한이 없습니다: " . dirname($logFilePath);
} else {
    error_log("Upload process started: " . $logFilePath, 3, $logFilePath);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['excelFile'])) {
        // $targetDir = "/jof/python/data/";
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . "/jof/python/data/";
        $targetFile = $targetDir . basename($_FILES['excelFile']['name']);
        error_log("Target file: " . $targetFile, 3, $logFilePath);

        $uploadOk = 1;
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));


        // Allow only certain file formats
        if ($fileType != "xlsx" && $fileType != "xls") {
            echo "Only XLSX and XLS files are allowed.";
            $uploadOk = 0;
            error_log("File type not allowed: " . $fileType, 3, $logFilePath);
        }

        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            echo "File was not uploaded.";
            error_log("File was not uploaded.", 3, $logFilePath);
        } else {
            // Move the uploaded file to the target directory
            if (move_uploaded_file($_FILES['excelFile']['tmp_name'], $targetFile)) {
                echo "File uploaded successfully.";
                error_log("File uploaded successfully.", 3, $logFilePath);

                $command = "/usr/bin/python3 " . $_SERVER['DOCUMENT_ROOT'] . "/jof/python/monInv.py " . basename($targetFile);
                exec($command, $output, $return_var);

                if ($return_var == 0) {
                    echo "Python script executed successfully.";
                    error_log("Python script executed successfully.", 3, $logFilePath);
                } else {
                    echo "Python script execution failed.";
                    error_log("Python script execution failed.", 3, $logFilePath);
                }

                // Assuming the output file is named output.xlsx
                $filename = pathinfo($targetFile, PATHINFO_FILENAME);
                $outputFile = $targetDir . $filename . "_pivot.xlsx";
                error_log("Output file path: " . $outputFile, 3, $logFilePath);

                // Remove the server root path from the output file path
                $webPath = "https://joeunfish.in/jof/python/data/" . $filename . "_pivot.xlsx";

                clearstatcache();  // Clear file status cache

                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/jof/python/data/' . $filename . "_pivot.xlsx";  // 실제 파일이 저장된 서버의 경로를 입력하세요.
                error_log("File path: " . $filePath, 3, $logFilePath);

                if (file_exists($filePath)) {
                    echo "<br><a href='$webPath'>Download the output file</a>";
                } else {
                    echo "<br>Error: Output file not found.";
                    error_log("Output file not found.", 3, $logFilePath);
                }
            } else {
                echo "<br>Error uploading file.";
                error_log("Error uploading file.", 3, $logFilePath);
            }
        }
    }
} else {
    echo "Invalid request.";
}
