<?php
/*
 Plugin Name: monInv : 일별현황
 Plugin URI: https://joeunfish.online
 Description: 더존출고현황=>일별현황으로 변경
 Version: 0.9
 Author: Monolith
 Author URI: https://joeunfish.online
 License: GPL2
 */

function monInvShortCode()
{
    date_default_timezone_set('Asia/Seoul');

?>

    <!DOCTYPE html>
    <html>

    <head>
        <title>Excel File Upload</title>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <style>
            body {
                /* display: flex; */
                justify-content: center;
                align-items: center;
                height: 10vh;
                margin: 0;
            }
        </style>
        <script>
            $(document).ready(function() {
                $("form").on("submit", function(event) {
                    event.preventDefault();
                    // console.log("Form submission prevented."); // 로그 추가하여 폼 제출 방지 확인

                    var formData = new FormData(this);
                    console.log("FormData object:", formData); // FormData 객체 로그 추가

                    var fileInput = $('input[type="file"]')[0]; // 파일 입력 요소 참조
                    if (fileInput.files.length > 0) {
                        var file = fileInput.files[0]; // 파일 입력에서 첫 번째 파일을 가져옵니다.
                        formData.append('excelFile', file); // 파일 객체를 FormData에 명시적으로 추가
                        console.log('File name: ' + file.name);
                        // console.log('File type: ' + file.type);
                    }

                    $.ajax({
                        url: '/jof/python/upload.php',
                        type: 'POST',
                        data: formData,
                        success: function(data) {
                            alert("File has been uploaded successfully");
                            $("#result").html(data);
                            console.log("Server response:", data); // 성공 시 로그 추가
                        },
                        error: function(xhr, status, error) {
                            alert("File upload failed: " + xhr.status + " " + error);
                            console.log("Error:", xhr.responseText); // 실패 시 로그 추가
                        },
                        cache: false,
                        contentType: false,
                        processData: false
                    });
                });
            });
        </script>
    </head>

    <body>
        <form action="/jof/python/upload.php" method="post" enctype="multipart/form-data">
            <input type="file" name="excelFile" accept=".xlsx, .xls">
            <input type="submit" value="Upload">
        </form>
        <div id="result"></div>
    </body>

    </html>

<?php }
add_shortcode('monInv', 'monInvShortCode');
