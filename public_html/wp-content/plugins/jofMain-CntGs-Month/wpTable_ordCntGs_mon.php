<?php
/* Plugin Name: jofMain_ordcntgschart_mon : (GS)수량-주문챠트-월별
Plugin URI: https://joeunfish.online/jofMain-CntGs-Month
Description: 조은수산 GS 입|출고 주문 파트-월별
Version: 1.0    
Author: darkcloud
Author URI: https://joeunfish.net
License: GPL2 */

function enqueue_chartjs_gs_mon()
{
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_chartjs_gs_mon');

function jofMainOrdCntGsChartShortCode_mon()
{
    global $wpdb;

    $orders_table = 'torders';
    $orderproducts_table = 'torderproducts';
    $orderproducts_add_table = 'torderproducts_add';

    // 세션 시작
    if (!session_id()) {
        session_start();
    }

    // 기간 설정
    $default_start = date('Y-m-d', strtotime('first day of January last year'));
    $default_end = date('Y-m-d');

    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : $default_start;
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : $default_end;

    // 상품 목록 가져오기
    $products = $wpdb->get_results("
        SELECT DISTINCT
            c.store_code AS product_code,
            c.code_exp AS product_name,
            c.unit
        FROM
            tcomcodegs c
        JOIN
            torderproducts p ON c.store_code = p.product_code
        WHERE
            c.grp_code = '2'
            AND c.use_yn = 'y'
        ORDER BY
            c.order_num ASC
    ");

    // 선택된 상품 설정
    if (isset($_POST['product_select_gs'])) {
        $selected_product = $_POST['product_select_gs'];
    } else {
        $selected_product = '8809611290266'; // 기본 상품 코드 설정
    }

    // 선택된 상품의 단위 찾기
    $selected_unit = '';
    foreach ($products as $product) {
        if ($product->product_code == $selected_product) {
            $selected_unit = $product->unit;
            break;
        }
    }

    // 디버깅을 위한 로그 파일 경로
    $logFilePath = WP_CONTENT_DIR . '/debug_gs_monthly.log';

    // 월별 데이터 쿼리 (단순화된 버전)
    $query = $wpdb->prepare("
        SELECT 
            DATE_FORMAT(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), '%%Y-%%m') AS month,
            SUM(prd.quantity) AS order_cnt,
            MAX(prd.product_name) AS product_name
        FROM 
            $orders_table ord
        JOIN 
            $orderproducts_table prd ON ord.doc_no = prd.doc_no
        WHERE 
            prd.product_code = %s
            AND STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d') BETWEEN %s AND %s
        GROUP BY 
            DATE_FORMAT(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), '%%Y-%%m')
        ORDER BY 
            month ASC
    ", 
        $selected_product,
        $start_date,
        $end_date
    );

    $results = $wpdb->get_results($query);

    // 디버깅 정보
    $debug_info = "선택된 상품: $selected_product\n";
    $debug_info .= "기간: $start_date ~ $end_date\n";
    $debug_info .= "쿼리 결과 수: " . count($results) . "\n";
    $debug_info .= "SQL 오류: " . $wpdb->last_error . "\n";
    file_put_contents($logFilePath, date('Y-m-d H:i:s') . " - " . $debug_info, FILE_APPEND);

    // 납품 데이터 쿼리
    $delivery_query = $wpdb->prepare("
        SELECT 
            DATE_FORMAT(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), '%%Y-%%m') AS month,
            SUM(COALESCE(prd.quantity_new, prd.quantity)) as delivery_cnt
        FROM 
            $orders_table ord
        JOIN 
            $orderproducts_table prd ON ord.doc_no = prd.doc_no
        WHERE 
            prd.product_code = %s
            AND STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d') BETWEEN %s AND %s
        GROUP BY 
            DATE_FORMAT(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), '%%Y-%%m')
        ORDER BY 
            month ASC
    ", 
        $selected_product,
        $start_date,
        $end_date
    );

    $delivery_results = $wpdb->get_results($delivery_query);

    // 추가 수량 데이터 쿼리
    $add_query = $wpdb->prepare("
        SELECT 
            DATE_FORMAT(STR_TO_DATE(supply_dt, '%%Y%%m%%d'), '%%Y-%%m') AS month,
            SUM(quantity_new) as add_quantity
        FROM 
            $orderproducts_add_table
        WHERE 
            product_code = %s
            AND STR_TO_DATE(supply_dt, '%%Y%%m%%d') BETWEEN %s AND %s
        GROUP BY 
            DATE_FORMAT(STR_TO_DATE(supply_dt, '%%Y%%m%%d'), '%%Y-%%m')
        ORDER BY 
            month ASC
    ", 
        $selected_product,
        $start_date,
        $end_date
    );

    $add_results = $wpdb->get_results($add_query);

    // 데이터 준비
    $labels = array();
    $order_data = array();
    $delivery_data = array();
    $grid_data = array();
    $product_name = '';

    // 모든 월 데이터 준비
    $all_months = array();
    
    // 주문 데이터
    foreach ($results as $row) {
        $month = $row->month;
        $all_months[$month] = array(
            'order' => round($row->order_cnt),
            'delivery' => 0,
            'add' => 0
        );
        if (empty($product_name)) {
            $product_name = $row->product_name;
        }
    }

    // 납품 데이터
    foreach ($delivery_results as $row) {
        $month = $row->month;
        if (!isset($all_months[$month])) {
            $all_months[$month] = array(
                'order' => 0,
                'delivery' => round($row->delivery_cnt),
                'add' => 0
            );
        } else {
            $all_months[$month]['delivery'] = round($row->delivery_cnt);
        }
    }

    // 추가 수량 데이터
    foreach ($add_results as $row) {
        $month = $row->month;
        if (!isset($all_months[$month])) {
            $all_months[$month] = array(
                'order' => 0,
                'delivery' => 0,
                'add' => round($row->add_quantity)
            );
        } else {
            $all_months[$month]['add'] = round($row->add_quantity);
        }
    }

    // 월별 정렬
    ksort($all_months);

    // 차트 데이터 준비
    foreach ($all_months as $month => $data) {
        $month_label = date('y/m', strtotime($month . '-01'));
        $labels[] = $month_label;
        $order_data[] = $data['order'];
        $delivery_data[] = $data['delivery'] + $data['add']; // 납품 + 추가 수량
        $grid_data[] = array(
            'month' => $month_label,
            'order' => number_format($data['order']),
            'delivery' => number_format($data['delivery'] + $data['add'])
        );
    }

    // 차트 데이터 준비
    $chart_data = array(
        'labels' => $labels,
        'datasets' => array(
            array(
                'label' => '주문수량',
                'data' => $order_data,
                'backgroundColor' => 'rgba(54, 162, 235, 0.8)',
                'borderColor' => 'rgba(54, 162, 235, 1)',
                'borderWidth' => 1
            ),
            array(
                'label' => '납품수량',
                'data' => $delivery_data,
                'backgroundColor' => 'rgba(75, 192, 192, 0.8)',
                'borderColor' => 'rgba(75, 192, 192, 1)',
                'borderWidth' => 1
            )
        )
    );

    $chart_json = json_encode($chart_data);
    $chart_id = 'myChart_' . uniqid();

    // HTML 출력
    $output = "<div class='monthly-stats-container'>";

    // form-menu 추가
    $output .= "<div class='form-menu'>";
    $output .= "<div id='title' style='float: left;'>GS::</div>";
    $output .= "<span style='color: white; cursor: pointer;'>GS납기통계(月別)</span>";
    $output .= "</div>";

    // 기간 선택 및 제품 선택 폼
    $output .= get_product_selection_form($products, $selected_product, $start_date, $end_date);

    // 디버깅 정보 표시 (데이터가 없을 때)
    if (empty($all_months)) {
        $output .= "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;'>";
        $output .= "<strong>데이터가 없습니다. 다음을 확인해주세요:</strong><br>";
        $output .= "선택된 상품: " . esc_html($selected_product) . "<br>";
        $output .= "기간: " . esc_html($start_date) . " ~ " . esc_html($end_date) . "<br>";
        $output .= "주문 데이터 결과 수: " . count($results) . "<br>";
        $output .= "납품 데이터 결과 수: " . count($delivery_results) . "<br>";
        $output .= "추가 수량 데이터 결과 수: " . count($add_results) . "<br>";
        $output .= "SQL 오류: " . esc_html($wpdb->last_error) . "<br>";
        $output .= "</div>";
    }

    // 차트
    $output .= "<div style='height: 300px;'><canvas id='$chart_id'></canvas></div>";

    // 그리드 테이블
    $output .= "<div class='monthly-grid'>";
    $output .= "<table class='wp-list-table widefat fixed striped'>";
    $output .= "<tr><th style='font-size: 13px; white-space: nowrap;'>구분</th>";
    foreach ($grid_data as $row) {
        $output .= "<th style='font-size: 13px; white-space: nowrap;'>{$row['month']}</th>";
    }
    $output .= "</tr>";
    $output .= "<tr><td style='font-size: 13px; white-space: nowrap;'>주문수량</td>";
    foreach ($grid_data as $row) {
        $output .= "<td style='font-size: 13px; white-space: nowrap;'>{$row['order']}</td>";
    }
    $output .= "</tr>";
    $output .= "<tr><td style='font-size: 13px; white-space: nowrap;'>납품수량</td>";
    foreach ($grid_data as $row) {
        $output .= "<td style='font-size: 13px; white-space: nowrap;'>{$row['delivery']}</td>";
    }
    $output .= "</tr>";
    $output .= "<tr><td style='font-size: 13px; white-space: nowrap;'>실적률 (%)</td>";
    foreach ($grid_data as $row) {
        $order_value = str_replace(',', '', $row['order']);
        $delivery_value = str_replace(',', '', $row['delivery']);

        if ($order_value > 0) {
            $percentage = round(($delivery_value / $order_value) * 100, 1);
            $color = $percentage >= 100 ? '#28a745' : ($percentage >= 90 ? '#ffc107' : '#dc3545');
            $output .= "<td style='font-size: 13px; white-space: nowrap; color: {$color};'>{$percentage}%</td>";
        } else {
            $output .= "<td style='font-size: 13px; white-space: nowrap;'>-</td>";
        }
    }
    $output .= "</tr></table></div>";

    // 스타일 추가
    $output .= get_styles();

    // 차트 스크립트
    $output .= get_chart_script($chart_id, $chart_json, $product_name, $selected_unit);

    return $output;
}

// 제품 선택 폼 생성 함수 수정
function get_product_selection_form($products, $selected_product, $start_date, $end_date) {
    $output = "<form method='post' class='period-selection' id='gsMonthlyForm'>";
    $output .= "<input type='date' name='start_date' value='$start_date'>";
    $output .= " ~ ";
    $output .= "<input type='date' name='end_date' value='$end_date'>";
    
    $output .= "<select name='product_select_gs' id='product_select_gs' onchange='this.form.submit()'>";
    foreach ($products as $product) {
        $selected = ($product->product_code == $selected_product) ? 'selected' : '';
        $unit = $product->unit ? " ({$product->unit})" : '';
        $output .= "<option value='{$product->product_code}' {$selected}>{$product->product_name}{$unit}</option>";
    }
    $output .= "</select>";
    
    // 조회 버튼 유지
    $output .= "<button type='submit'>조회</button>";
    $output .= "</form>";

    // JavaScript 추가
    $output .= "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('gsMonthlyForm');
        const productSelect = document.getElementById('product_select_gs');

        // 상품 선택 시 자동 제출
        productSelect.addEventListener('change', function() {
            form.submit();
        });
    });
    </script>";
    
    return $output;
}

// 스타일 정의 함수
function get_styles() {
    return "
    <style>
        .monthly-stats-container { padding: 20px; }
        .form-menu {
            color: white;
            padding: 5px 5px;
            text-align: center;
            font-size: 17px;
            background-color: #2c80cf;
            border-radius: 8px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .form-menu span {
            margin: 0 10px;
        }
        .form-menu span:hover {
            color: #e5e5e5;
        }

        .period-selection { 
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .period-selection input[type='date'],
        .period-selection select,
        .period-selection button {
            height: 40px;
            padding: 6px 12px;
            font-size: 15px;
            line-height: 1.5;
            border: 1px solid #ced4da;
            border-radius: 4px;
            vertical-align: middle;
        }
        .period-selection button {
            height: 35px;
            width: 100px;
            padding: 0;
            cursor: pointer;
            color: #ffffff;
            background-color: #222222;
            transition: all 0.3s ease;
        }
        .period-selection button:hover {
            background-color: #2c80cf !important;
            color: white !important;
        }
        .period-selection button:active {
            background-color: #333333;
            border-color: #888888;
            transform: translateY(1px);
        }
        
        .monthly-grid { 
            margin-top: 20px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            overflow-x: auto; 
        }
        .monthly-grid table { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
        }
        .monthly-grid th { 
            background-color: #f8f9fa; 
            padding: 8px 6px; 
            text-align: center; 
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .monthly-grid td { 
            padding: 8px 6px; 
            text-align: center; 
            font-size: 13px;
            border-bottom: 1px solid #dee2e6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 상품 선택 드롭다운 스타일 수정 */
        #product_select_gs {
            min-width: 200px;
            cursor: pointer;
        }
        
        /* 조회 버튼 트랜지션 효과 추가 */
        #searchButton {
            transition: display 0.3s ease-in-out;
        }
    </style>";
}

// 차트 스크립트 생성 함수
function get_chart_script($chart_id, $chart_json, $product_name, $selected_unit) {
    return "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('$chart_id').getContext('2d');
            var chartData = $chart_json;
            new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: '" . esc_js($product_name) . " ★단위(" . esc_js($selected_unit) . ")★',
                            font: { 
                                size: 16, 
                                weight: 'bold' 
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + 
                                           new Intl.NumberFormat('ko-KR').format(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('ko-KR').format(value);
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>";
}

add_shortcode('jofMain_ordcntgschart_mon', 'jofMainOrdCntGsChartShortCode_mon');