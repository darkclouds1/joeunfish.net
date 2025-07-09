<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('Asia/Seoul');
require($_SERVER['DOCUMENT_ROOT'] . '/classphp/dbConn_fish.php');

$logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
// error_log(print_r($logFilePath, true), 3, $logFilePath);

$dbConn = new dbconnClassFish;
$conn     = $dbConn->conn();
mysqli_set_charset($conn, 'utf8');
mysqli_query($conn, "SET time_zone = '+09:00'");

// GET 방식으로 파라미터를 받습니다.
$supply_dt = isset($_GET['deliveryDate']) ? $_GET['deliveryDate'] : '';
$supply_dt = date('Ymd', strtotime($supply_dt));
$dest_code = isset($_GET['storeCode']) ? $_GET['storeCode'] : '';

// 영어 요일을 한글 요일로 매핑합니다.
$dayOfWeek = date('l', strtotime($supply_dt));
$daysKorean = [
    'Monday'    => '월요일',
    'Tuesday'   => '화요일',

    'Wednesday' => '수요일',
    'Thursday'  => '목요일',
    'Friday'    => '금요일',
    'Saturday'  => '토요일',
    'Sunday' => '일요일'
];
$koreanDayOfWeek = $daysKorean[$dayOfWeek];

if (empty($supply_dt) || empty($dest_code)) {
    error_log("Missing parameters: supply_dt or dest_code", 3, $logFilePath);
    die("Missing parameters: deliveryDate or storeCode");
}
// error_log("Parameters received: supply_dt = $supply_dt, dest_code = $dest_code", 3, $logFilePath); // 파라미터 확인

// (주)지에스리테일  (※ GS편의점 =6000)
if ($dest_code !== '60000') {

    // SQL 쿼리 작성
    $sql = " SELECT
            ORD.dest_name,
            ORD.supply_dt AS '납품일',
            ORD.dest_code AS '점포코드',
            CENTER.code_exp AS '센터',
            COM.code_exp AS '품명',
            PRD.product_code AS '바코드',
            COM.country_origin AS '원산지',
            COM.unit AS '단위',
            FLOOR(SUM(COALESCE(PRD.quantity, 0)) + COALESCE(MAX(ADDPRD.quantity_new), 0)) AS '수량',
            MAX(PRD.unit_price) AS '단가',
            FLOOR(SUM(COALESCE(PRD.quantity, 0)) + COALESCE(MAX(ADDPRD.quantity_new), 0)) * MAX(PRD.unit_price) AS '금액',
            FLOOR(
                (SUM(CASE WHEN ORD.dest_code IN ('62970', '60470', '66970', '62980', '68970') 
                        THEN COALESCE(PRD.quantity, 0)
                        ELSE 0 END) + 
                CASE WHEN ORD.dest_code IN ('62970', '60470', '66970', '62980', '68970') 
                    THEN COALESCE(MAX(ADDPRD.quantity_new), 0)
                    ELSE 0 END) /
                CASE 
                    WHEN COM.store_code = '8809611290037' THEN 4
                    WHEN COM.store_code = '8809611290365' THEN 2
                    WHEN COM.store_code = '8809611290266' THEN 4
                    ELSE 1
                END
            ) AS '박스'
        FROM 
            tcomcodegs COM
        LEFT JOIN 
            torderproducts PRD ON COM.store_code = PRD.product_code
        LEFT JOIN 
            torders ORD ON ORD.doc_no = PRD.doc_no AND ORD.msgfrom = 'GSSM01:DC' AND ORD.supply_dt = ?
        LEFT JOIN
            torderproducts_add ADDPRD ON ADDPRD.supply_dt = ORD.supply_dt AND ADDPRD.dest_code = ORD.dest_code AND ADDPRD.product_code = PRD.product_code
        LEFT JOIN
            tcomcodegs CENTER ON CENTER.store_code = ORD.dest_code AND CENTER.grp_code = 1
        WHERE COM.grp_code = 2
            AND COM.use_yn = 'y'
            AND ORD.dest_code = ?
        GROUP BY  COM.code_exp, COM.store_code, PRD.product_code, CENTER.code_exp
        ORDER BY COM.order_num
        ";

    // SQL 쿼리 준비
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $supply_dt, $dest_code);
    if (!$stmt->execute()) {
        error_log("SQL execute failed: " . $stmt->error, 3, $logFilePath);
        die("SQL execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        error_log("SQL get_result failed: " . $stmt->error, 3, $logFilePath);
        die("SQL get_result failed: " . $stmt->error);
    }

    // 1. 결과를 fetch하여 실제 데이터 배열을 얻습니다.
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        return '<body><p>데이터가 없습니다.</p></body>';
    }

    // 2. 데이터 처리
    if (!empty($rows)) {
        // 첫 번째 행에서 dest_name과 센터 정보 추출
        $firstRow = $rows[0];
        $dest_name = htmlspecialchars($firstRow['dest_name'], ENT_QUOTES, 'UTF-8');
        $center = htmlspecialchars($firstRow['센터'], ENT_QUOTES, 'UTF-8');

        $html1page = '<!DOCTYPE html><html lang="ko">';
        $html1page .= '<style>';
        $html1page .= '.page-break { page-break-after: always; }';
        $html1page .= 'table { width: 100%; margin-bottom: 20px; text-align: center; }';
        $html1page .= 'table th, table td { text-align: center;, font-family: Arial, sans-serif; font-size: 15px; }';
        $html1page .= '</style>';

        $html1page .= '<head><meta charset="UTF-8"><title>거래명세표</title></head><body>';
        $html1page .= '<div style="display: flex; align-items: center; justify-content: space-between;">';
        $html1page .= '<div ><img src="/wp-content/uploads/2024/03/조은수산_로고2.jpg" alt="JOEUN Fisheries" style="width: 100px; height: auto;"></div>';
        $html1page .= '<div style="flex-grow: 1; text-align: center; margin-left: 30px;"><H1 style="text-decoration: underline; text-align: left;">' . $dest_name . ' - ' . $center . '</H1></div>'; // 중앙 정렬
        $html1page .= '</br></br>';
        $html1page .= '</div>';
        $html1page .= '<div style="text-align: right;">납품일: ' . date('Y년 m월 d일', strtotime($supply_dt)) . '</div>';
        $html1page .= '<div style="text-align: center;"><H2>거래명세표</H2></div></br>';
        $html1page .= '</div>';

        $html1page .= '<table border="0" style="width: 100%; margin-bottom: 20px;">';
        $html1page .= '<tr><td style="text-align: left;">발주처:' . $dest_name . ' </td><td style="text-align: left;">공급처: (주)조은수산</td></tr>';
        $html1page .= '<tr><td style="text-align: left;">서울특별시 강남구 논현로 508 GS타워 19F</td><td style="text-align: left;">경기도 부천시 수도로 88-18(도당동, 조은수산빌딩)</td></tr>';
        $html1page .= '<tr><td style="text-align: left;">전화번호: 010-9521-9391</td><td style="text-align: left;">032-673-4116</td></tr>';
        $html1page .= '<tr><td style="text-align: left;">팩스번호: 02-2006-2858</td><td style="text-align: left;">032-673-4115</td></tr>';
        $html1page .= '<tr><td style="text-align: center;"></td><td style="text-align: left;">E-Mail: cdy0708@hanmail.net</td></tr>';
        $html1page .= '<tr><td style="text-align: left;">담당자: 김아름 MD</td><td style="text-align: center;"></td></tr>';
        $html1page .= '<tr><td colspan="2" style="text-align: center; font-weight: bold;">아래와 같이 공급합니다.</td></tr>';
        $html1page .= '</table>';

        // 테이블 헤더
        $html1page .= '</br><table border="1" style="width: 100%; border-collapse: collapse;">';
        $html1page .= '<tr><th style="border-bottom: double; height: 50px;">품명</th><th style="border-bottom: double; height: 35px;">바코드</th><th style="border-bottom: double; height: 35px;">원산지</th><th style="border-bottom: double; height: 35px;">단위</th><th style="border-bottom: double; height: 35px;">수량</th><th style="border-bottom: double; height: 35px;">단가</th><th style="border-bottom: double; height: 35px;">금액</th><th style="border-bottom: double; height: 35px;">박스</th></tr>';

        $totalAmount = 0;
        foreach ($rows as $row) {
            $html1page .= '<tr>';
            $html1page .= '<td style="height: 45px; text-align: center;">' . htmlspecialchars($row['품명'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['바코드'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['원산지'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['단위'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['수량'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html1page .= '<td style="text-align: right; padding-right: 10px;">' . number_format(htmlspecialchars($row['단가'], ENT_QUOTES, 'UTF-8')) . '</td>';
            $html1page .= '<td style="text-align: right; padding-right: 10px;">' . number_format(htmlspecialchars($row['금액'], ENT_QUOTES, 'UTF-8')) . '</td>';
            $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['박스'], ENT_QUOTES, 'UTF-8') . 'Box</td>';
            $html1page .= '</tr>';
            $totalAmount += $row['금액'];
        }
        $html1page .= '<tr>';
        $html1page .= '<td colspan="6" style="height: 45px; text-align: right; font-weight: bold; padding-right: 10px;">총계</td>';
        $html1page .= '<td colspan="2" style="height: 45px; text-align: center; font-weight: bold; padding-right: 10px;">' . number_format(htmlspecialchars($totalAmount, ENT_QUOTES, 'UTF-8')) . '</td>';
        $html1page .= '</tr>';
        $html1page .= '</table>';
        $html1page .= '<div style="text-align: right; padding-right: 10px;">(※상기 품목은 부가세 면세 품목 입니다)</div>';
        $html1page .= '</body>';
        $html1page .= '<div class="page-break"></div>'; // 페이지 나누기
    } else {
        $html1page = '<body><p>데이터가 없습니다.</p></body>';
    }

    // gs Invoice 출력은 2장만 -by 정순장 2024.07.19
    // $html .= $html1page;

    $html .= $html1page;
    // 페이지 여러장 출력
    $html .= $html1page . '</html>';
    echo $html;
} else {
    // 60000 - GS편의점 invoice 출력
    $sql = "  
        SELECT
            ROW_NUMBER() OVER (ORDER BY t.dest_code DESC) AS 'no',  -- 순번 추가
            t.dest_code AS '점포코드',
            t.order_dt AS '발주일자',
            t.supply_dt AS '납품일',
            'GS편의점' AS '주문자',
            CONCAT(t.dest_code, ' ', t.region) AS '점포코드',
            p.quantity AS '수량',
            '조은수산' AS '공급자',
            CONCAT(c.country_origin, ' ', c.code_exp) AS '품명',
            p.product_code AS '상품코드',
            c.country_origin AS '원산지',
            REGEXP_SUBSTR(c.code_exp, '[0-9]+g') AS '단위',                   -- 단위: 숫자와 'g'만 추출 (예: '연어180g슬라이스' → '180g')
            p.unit_price AS '단가',
            4 AS '입수',
            CEIL(COALESCE(p.quantity, 0) / 4) AS '박스',                      -- BOX수: 낱개수량 / 입수, 소수점 올림 처리
            COALESCE(p.quantity, 0) * COALESCE(p.unit_price, 0) AS '금액',
            (SELECT SUM(COALESCE(p2.quantity,0) * COALESCE(p2.unit_price,0))
               FROM torders t2
               LEFT JOIN torderproducts p2 ON t2.doc_no = p2.doc_no
               INNER JOIN tcomcodegs c2 ON c2.store_code = p2.product_code
               WHERE t2.msgfrom = 'GSCT04:DC'
                 AND t2.supply_dt = ?
            ) AS total_amount
        FROM torders t 
        LEFT JOIN torderproducts p ON t.doc_no = p.doc_no
        INNER JOIN tcomcodegs c ON c.store_code = p.product_code
        WHERE t.msgfrom = 'GSCT04:DC'
          AND t.supply_dt = ?
        ORDER BY t.dest_code DESC
    ";
    // SQL 쿼리 준비
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $supply_dt, $supply_dt);
    if (!$stmt->execute()) {
        error_log("SQL execute failed: " . $stmt->error, 3, $logFilePath);
        die("SQL execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        error_log("SQL get_result failed: " . $stmt->error, 3, $logFilePath);
        die("SQL get_result failed: " . $stmt->error);
    }

    // 1. 결과를 fetch하여 실제 데이터 배열을 얻습니다.
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    if (empty($rows)) {
        return '<body><p>데이터가 없습니다.</p></body>';
    }

    $total_qty = 0;
    foreach ($rows as $row) {
        $total_qty += $row['수량'];
    }

    // 2. 데이터 처리
    if (!empty($rows)) {

        $html1page = '<!DOCTYPE html><html lang="ko">';
        $html1page .= '<style>';
        $html1page .= '.page-break { page-break-after: always; }';
        $html1page .= 'table { width: 100%; margin-bottom: 20px; text-align: center; }';
        $html1page .= 'table th, table td { text-align: center;, font-family: Arial, sans-serif; font-size: 15px; }';
        $html1page .= '.gs-table, .gs-table th, .gs-table td { font-size: 13px !important; }';
        $html1page .= '</style>';

        $html1page .= '<head><meta charset="UTF-8"><title>거래명세표</title></head><body>';
        $html1page .= '<div style="display: flex; align-items: center; justify-content: space-between;">';
        $html1page .= '<div ><img src="/wp-content/uploads/2024/03/조은수산_로고2.jpg" style="width: 100px; height: auto;"></div>';
        $html1page .= '<div style="flex-grow: 1; text-align: center; margin-left: 100px;"><H1 style="text-decoration: underline; text-align: left;">' . 'GS리테일' . ' - ' . '이천센터' . '</H1></div>'; // 중앙 정렬
        $html1page .= '</br></br>';
        $html1page .= '</div>';
        $html1page .= '<div style="text-align: right;">납품일: ' . date('Y년 m월 d일', strtotime($supply_dt)) . '</div>';
        $html1page .= '<div style="text-align: center;"><H2>거래명세표</H2></div></br>';
        $html1page .= '</div>';

        $html1page .= '<table border="0" style="width: 100%; margin-bottom: 20px;">';
        $html1page .= '<tr><td style="text-align: left;">발주처:' . "GS 리테일 (편의점)" . ' </td><td style="text-align: left;">공급처: (주)조은수산</td></tr>';
        $html1page .= '<tr><td style="text-align: left; font-size: 14px;">서울 강남구 논현로 508 GS타워 18F(신선식품강화팀)</td><td style="text-align: left; font-size: 14px;">경기도 부천시 수도로 88-18(도당동, 조은수산빌딩)</td></tr>';
        $html1page .= '<tr><td style="text-align: left;">전화번호: 010-4034-3749</td><td style="text-align: left;">032-673-4116</td></tr>';
        $html1page .= '<tr><td style="text-align: left;">팩스번호: - </td><td style="text-align: left;">032-673-4115</td></tr>';
        $html1page .= '<tr><td style="text-align: center;"></td><td style="text-align: left;">E-Mail: cdy0708@hanmail.net</td></tr>';
        $html1page .= '<tr><td style="text-align: left;">담당자: - </td><td style="text-align: center;"></td></tr>';
        $html1page .= '</br><tr><td colspan="2" style="text-align: center; font-weight: bold;">아래와 같이 공급합니다.</td></tr>';
        $html1page .= '</table>';

        // 테이블 헤더
        $html1page .= '</br><table border="1" style="width: 100%; border-collapse: collapse;">';
        $html1page .= '<tr><th style="border-bottom: double; height: 50px;">품명</th><th style="border-bottom: double; height: 35px;">바코드</th><th style="border-bottom: double; height: 35px;">원산지</th><th style="border-bottom: double; height: 35px;">단위</th><th style="border-bottom: double; height: 35px;">수량</th><th style="border-bottom: double; height: 35px;">단가</th><th style="border-bottom: double; height: 35px;">금액</th><th style="border-bottom: double; height: 35px;">비고</th></tr>';

        $row = $rows[0];
        $html1page .= '<tr>';
        $html1page .= '<td style="height: 45px; text-align: center;">' . htmlspecialchars($row['품명'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['상품코드'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['원산지'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html1page .= '<td style="text-align: center;">' . htmlspecialchars($row['단위'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html1page .= '<td style="text-align: center;">' . $total_qty . '</td>';
        $html1page .= '<td style="text-align: right; padding-right: 10px;">' . number_format(htmlspecialchars($row['단가'], ENT_QUOTES, 'UTF-8')) . '</td>';
        $html1page .= '<td style="text-align: right; padding-right: 10px;">' . number_format(htmlspecialchars($row['total_amount'], ENT_QUOTES, 'UTF-8')) . '</td>';
        $html1page .= '</tr>';

        $html1page .= '<tr>';
        $html1page .= '<td colspan="6" style="height: 45px; text-align: right; font-weight: bold; padding-right: 10px;">총계</td>';
        $html1page .= '<td colspan="2" style="height: 45px; text-align: center; font-weight: bold; padding-right: 10px;">' . '₩ ' . number_format(htmlspecialchars($row['total_amount'], ENT_QUOTES, 'UTF-8')) . '</td>';
        $html1page .= '</tr>';
        $html1page .= '</table>';
        $html1page .= '<div style="text-align: right; padding-right: 10px;">(※상기 품목은 부가세 면세 품목 입니다)</div>';
        $html1page .= '</body>';
        $html1page .= '<div class="page-break"></div>'; // 페이지 나누기
    } else {
        $html1page = '<body><p>데이터가 없습니다.</p></body>';
    }
    // gs Invoice 출력은 2장만 -by 정순장 2025.05.21
    $html .= $html1page;
    // 페이지 1장 더 출력
    $html .= $html1page;

    //---------------------------------
    // 2. GS 편의점 납품현황 (목록)
    //---------------------------------
    $html_all .= '<style>
                .gs-table, .gs-table th, .gs-table td { font-size: 13px !important; }
                </style>';
    $html_all .= '<h2 style="text-align:center;">GS 편의점 납품현황</h2>';
    $html_all .= '<table class="gs-table" border="1" style="width:100%; border-collapse:collapse; text-align:center;">';
    $html_all .= '<tr style="height: 35px;">';
    $html_all .= '<th>발주일자</th><th>납품일자</th><th>주문자</th><th>납품처</th><th>낱개수량</th><th>공급자</th><th>상품명</th>';
    $html_all .= '</tr>';

    $total_qty = 0;
    foreach ($rows as $row) {
        $html_all .= '<tr style="height: 25px;">';
        $html_all .= '<td>' . date('Y-m-d', strtotime($row['발주일자'])) . '</td>';
        $html_all .= '<td>' . date('Y-m-d', strtotime($row['납품일'])) . '</td>';
        $html_all .= '<td>' . htmlspecialchars($row['주문자'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_all .= '<td style="text-align: left; padding-left: 15px;">' . htmlspecialchars($row['점포코드'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_all .= '<td style="text-align: right; padding-right: 15px;">' . htmlspecialchars($row['수량'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_all .= '<td>' . htmlspecialchars($row['공급자'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_all .= '<td>' . htmlspecialchars($row['품명'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_all .= '</tr>';
        $total_qty += $row['수량'];
    }
    // 합계 행
    $html_all .= '<tr>';
    $html_all .= '<td style="font-weight:bold; height: 30px;">합계</td>';
    $html_all .= '<td colspan="3"></td>';
    $html_all .= '<td style="font-weight:bold; text-align: right; padding-right: 15px;">' . number_format($total_qty) . '</td>';
    $html_all .= '<td colspan="2"></td>';
    $html_all .= '</tr>';
    $html_all .= '</table>';
    $html_all .= '<div class="page-break"></div>';

    $html .= $html_all;

    // 3. 제초처별 수주집계표
    // 1, 2번줄 정보 추출 (예시: 첫 row 기준)
    $page_count = 0;
    $total_page = count($rows);
    $html_dest = '';
    foreach ($rows as $row) {
        $page_count++;
        $html_dest .= '<h2 style="text-align:center;">제조처별 수주집계표</h2></br>';
        // 1번줄
        $html_dest .= '<table style="width:100%; margin-bottom:2px;"><tr>';
        $html_dest .= '<td style="width:33%; text-align:left;">공급자: PV7929002 조은수산</td>';
        $html_dest .= '<td style="width:33%; text-align:center;">수주일자: ' . date('Y-m-d', strtotime($row['발주일자'])) . '</td>';
        $html_dest .= '<td style="width:33%; text-align:left;">발행일자: ' . date('Y-m-d') . '</td>';
        $html_dest .= '</tr></table>';

        // 2번줄
        $html_dest .= '<table style="width:100%; margin-bottom:10px;"><tr>';
        $html_dest .= '<td style="width:33%; text-align:left;">공급받는자: ' . htmlspecialchars($row['점포코드'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_dest .= '<td style="width:33%; text-align:center;"></td>';
        $html_dest .= '<td style="width:33%; text-align:left;">발행시간: ' . date('H:i:s') . '</td>';
        $html_dest .= '</tr></table>';

        // 3번줄
        $html_dest .= '<table style="width:100%; margin-bottom:10px;"><tr>';
        $html_dest .= '<td style="width:33%; text-align:left;">제조처: PV7929</td>';
        $html_dest .= '<td style="width:33%; text-align:center;"></td>';
        $html_dest .= '<td style="width:33%; text-align:left;">페이지: ' . $page_count . '/' . $total_page . '</td>';
        $html_dest .= '</tr></table>';

        // 테이블
        $html_dest .= '<table class="gs-table" border="1" style="width:100%; border-collapse:collapse; text-align:center;">';
        $html_dest .= '<tr style="height: 35px;">';
        $html_dest .= '<th>번호</th><th>상품코드</th><th>상품명</th><th>입수</th><th>BOX수</th><th>단가</th><th>수주수량</th><th>금액</th>';
        $html_dest .= '</tr>';
        $html_dest .= '<tr style="height: 35px;">';
        $html_dest .= '<td>' . $page_count . '</td>';
        $html_dest .= '<td>' . htmlspecialchars($row['상품코드'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_dest .= '<td>' . htmlspecialchars($row['품명'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_dest .= '<td>' . '4' . '</td>';
        $html_dest .= '<td>' . htmlspecialchars($row['박스'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_dest .= '<td>' . htmlspecialchars($row['단가'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_dest .= '<td>' . htmlspecialchars($row['수량'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_dest .= '<td>' . htmlspecialchars($row['금액'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html_dest .= '</tr>';
        $html_dest .= '</table>';

        // 마지막 페이지가 아니면 page-break 추가
        if ($page_count < $total_page) {
            $html_dest .= '<div class="page-break"></div>';
        }
    }
    $html .= $html_dest;

    $html .= '</html>';
    echo $html;
}

$stmt->close();
