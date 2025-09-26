<?php

// CORS 헤더 추가
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// OPTIONS 요청 처리 (preflight request)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// get_order_data_srv.php

// 워드프레스 환경 로드 경로 수정
require_once(dirname(__FILE__) . '/../../../wp-load.php');

global $wpdb;

// action 파라미터를 확인하여 로직 분기
$action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';

if ($action === 'update_qty') {
    // 수량 업데이트 로직
    $doc_no = isset($_POST['doc_no']) ? sanitize_text_field($_POST['doc_no']) : '';
    $product_code = isset($_POST['product_code']) ? sanitize_text_field($_POST['product_code']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

    if (empty($doc_no) || empty($product_code) || !is_numeric($quantity)) {
        wp_send_json_error(['message' => '잘못된 입력 값입니다.'], 400);
        exit;
    }

    $table_name = 'torderproducts';

    $result = $wpdb->update(
        $table_name,
        ['quantity' => $quantity],
        ['doc_no' => $doc_no, 'product_code' => $product_code],
        ['%d'], // data format
        ['%s', '%s'] // where format
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'DB 업데이트 실패: ' . $wpdb->last_error]);
    } else {
        // 일치 데이터가 없거나 수량이 동일한 경우도 포함
        wp_send_json_success(['message' => '수량 업데이트가 처리되었습니다.']);
    }
} else {
    // 기본 동작: 주문 데이터 조회
    $store_code = isset($_POST['store_code']) ? sanitize_text_field($_POST['store_code']) : '';
    $supply_dt = isset($_POST['supply_dt']) ? sanitize_text_field($_POST['supply_dt']) : '';

    if (empty($store_code) || empty($supply_dt)) {
        // 오류 응답을 JSON 형식으로 통일
        header('Content-Type: application/json');
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Store code and supply date are required.']);
        exit;
    }

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT ord.doc_no,  
                ord.region, 
                ord.supply_dt, 
                prd.product_code,
                prd.product_name, 
                FORMAT(prd.quantity, 0) AS quantity, 
                FORMAT(prd.unit_price, 0) AS unit_price, 
                FORMAT(prd.total_price, 0) AS total_price 
         FROM `torders` ord
         JOIN torderproducts prd ON prd.doc_no = ord.doc_no
         WHERE ord.dest_code = %s AND ord.supply_dt = %s
        ORDER BY prd.product_code ASC ",
        $store_code,
        $supply_dt
    ));

    header('Content-Type: application/json');
    echo json_encode($results);
}
?>