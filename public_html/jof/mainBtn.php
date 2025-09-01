<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main-Menu</title>

    <link rel="stylesheet" type="text/css" href="https://joeunfish.online/jof/css/joeunfish.css" />

    <!-- 웹폰트 -by jsj 20210312 -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100;300;400;500;700;900&display=swap" rel="stylesheet">
    <!--  부트스트랩 js 사용 -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
    <!-- 상단 메뉴 고정 -->
    <script src="//code.jquery.com/jquery-1.12.4.min.js"></script>

</head>

<body>

    <form action="" name="myForm" id="myForm" method="get" autocomplete="on">

        <div class="centered-row">
            <button class="btn btn-1 button-big" type="button" onclick="viewBarcodeIn_bigBtn();">Barcode 입고</button>
            <button class="btn btn-2 button-big">Barcode 출고</button>
        </div>
        <div class="centered-row">
            <button class="btn btn-3 button-big">INVOICE 출력</button>
            <button class="btn btn-4 button-big">AGIS</button>
        </div>

        <div class="button">
            <div>
                <a onclick="viewBarcodeIn_bigBtn();" class="bigbtn">검색</a>
            </div>
        </div>
    </form>

    <script>
        function viewBarcodeIn_bigBtn() {
            console.log("viewBarcodeIn_bigBtn::");
            window.open("https://joeunfish.online/barcodein/", "_blank");
        }
    </script>

</body>

</html>