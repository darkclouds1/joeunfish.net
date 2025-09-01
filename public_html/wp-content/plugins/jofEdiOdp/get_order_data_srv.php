<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
global $wpdb;

header('Content-Type: application/json');

$store_code = isset($_POST['store_code']) ? sanitize_text_field($_POST['store_code']) : '';
$supply_dt = isset($_POST['supply_dt']) ? sanitize_text_field($_POST['supply_dt']) : '';

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
    str_replace('-', '', $supply_dt)
));

echo json_encode($results);
