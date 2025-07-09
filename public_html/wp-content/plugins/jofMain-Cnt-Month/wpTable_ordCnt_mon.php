<?php
/* Plugin Name: jofMain_ordcntchart_mon : 수량-주문챠트-월별
Plugin URI: https://joeunfish.online/jofMain-Cnt-Month
Description: 조은수산 입|출고 주문 파트트-월별
Version: 1.0    
Author: darkcloud
Author URI: https://joeunfish.net
License: GPL2 */

function enqueue_chartjs_mon()
{
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_chartjs_mon');

function jofMainOrdCntChartShortCode_mon()
{
    global $wpdb;
    $logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

    $orders_table = 'torders';
    $orderproducts_table = 'torderproducts';

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
            tcomcode c
        JOIN
            torderproducts p ON c.store_code = p.product_code
        WHERE
            c.grp_code = '2'
            AND c.use_yn = 'y'
        ORDER BY
            c.order_num ASC
    ");

    // 선택된 상품 설정 => 세션 사용하지 않음 -by darkcloud 2024-11-15
    if (isset($_POST['product_select_ordcnt'])) {
        $selected_product = $_POST['product_select_ordcnt'];
        $_SESSION['selected_product_ordcnt'] = $selected_product;
    } elseif (isset($_SESSION['selected_product_ordcnt'])) {
        $selected_product = $_SESSION['selected_product_ordcnt'];
    } else {
        $selected_product = '2000000560770'; // 기본 상품 코드 설정
        $_SESSION['selected_product_ordcnt'] = $selected_product;
    }

    $_SESSION['selected_product'] = $selected_product;

    // 선택된 상품의 단위 찾기
    $selected_unit = '';
    foreach ($products as $product) {
        if ($product->product_code == $selected_product) {
            $selected_unit = $product->unit;
            break;
        }
    }

    // 디버깅을 위한 로그 추가
    $debug_log = "Selected Product: $selected_product\n";
    $debug_log .= "Date Range: $start_date to $end_date\n";

    // 월별 데이터 쿼리 수정 (주문 데이터)
    $query = $wpdb->prepare(
        "
        SELECT 
            DATE_FORMAT(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), '%%Y-%%m') AS month,
            SUM(prd.quantity) AS total_cnt,
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

    // 출고 데이터 쿼리
    $delivery_query = "";
    if (in_array($selected_product, ['0200000651343', '2000000560770', '2000000668592'])) {
        $delivery_query = $wpdb->prepare(
            "
            SELECT 
                DATE_FORMAT(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), '%%Y-%%m') AS month,
                SUM(binv.weight) AS total_delivery
            FROM 
                tbarcodeinv binv
            JOIN 
                $orders_table ord ON binv.doc_no = ord.doc_no
            WHERE 
                binv.prod_code = %s
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
    } else {
        $delivery_query = $wpdb->prepare(
            "
            SELECT 
                DATE_FORMAT(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), '%%Y-%%m') AS month,
                SUM(COALESCE(prd.quantity_new, prd.quantity)) AS total_delivery
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
    }

    // 캐시 키 생성
    $cache_key = 'monthly_stats_' . $selected_product . '_' . $start_date . '_' . $end_date;

    // 캐시된 데이터 확인
    $cached_data = wp_cache_get($cache_key);

    if ($cached_data === false) {
        // 기존 쿼리 실행 코드
        $results = $wpdb->get_results($query);
        $delivery_results = $wpdb->get_results($delivery_query);
        
        // 디버깅 로그 추가
        $debug_log .= "Order Query Results Count: " . count($results) . "\n";
        $debug_log .= "Delivery Query Results Count: " . count($delivery_results) . "\n";
        
        if (empty($results)) {
            $debug_log .= "Order Query: " . $query . "\n";
            $debug_log .= "Last SQL Error: " . $wpdb->last_error . "\n";
        }
        
        // 로그 파일에 저장
        file_put_contents($logFilePath, date('Y-m-d H:i:s') . " - " . $debug_log, FILE_APPEND);
        
        // 결과 캐싱 (1시간)
        wp_cache_set($cache_key, array($results, $delivery_results), '', 3600);
    } else {
        list($results, $delivery_results) = $cached_data;
    }

    // 데이터 준비
    $labels = array();
    $order_data = array();
    $delivery_data = array();
    $grid_data = array();
    $product_name = '';

    // 모든 월 데이터 준비
    $all_months = array();
    foreach ($results as $row) {
        $month = $row->month;
        $all_months[$month] = array(
            'order' => round($row->total_cnt),
            'delivery' => 0
        );
        if (empty($product_name)) {
            $product_name = $row->product_name;
        }
    }

    foreach ($delivery_results as $row) {
        $month = $row->month;
        if (!isset($all_months[$month])) {
            $all_months[$month] = array(
                'order' => 0,
                'delivery' => round($row->total_delivery)
            );
        } else {
            $all_months[$month]['delivery'] = round($row->total_delivery);
        }
    }

    // 월별 정렬
    ksort($all_months);

    // 차트 데이터 준비
    foreach ($all_months as $month => $data) {
        $month_label = date('y/m', strtotime($month));
        $labels[] = $month_label;
        $order_data[] = $data['order'];
        $delivery_data[] = $data['delivery'];
        $grid_data[] = array(
            'month' => $month_label,
            'order' => number_format($data['order']),
            'delivery' => number_format($data['delivery'])
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
                'label' => '출고수량',
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
    $output .= "<div id='title' style='float: left;'>CO::</div>";
    $output .= "<span style='color: white; cursor: pointer;' onclick='showMonthlyStats(); return false;'>(CO)납기통계(月別)</span>";
    $output .= "</div>";

    // 기간 선택 폼 (중복 제거)
    $output .= "<form method='post' class='period-selection'>";
    $output .= "<input type='date' name='start_date' value='$start_date'>";
    $output .= " ~ ";
    $output .= "<input type='date' name='end_date' value='$end_date'>";

    // 제품 선택
    $output .= "<select name='product_select_ordcnt'>";
    foreach ($products as $product) {
        $selected = ($product->product_code == $selected_product) ? 'selected' : '';
        $output .= "<option value='{$product->product_code}' {$selected}>{$product->product_name}</option>";
    }
    $output .= "</select>";
    $output .= "<button type='submit'>조회</button>";
    $output .= "</form>";

    // 디버깅 정보 표시 (개발 중에만 사용)
    if (empty($results)) {
        $output .= "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;'>";
        $output .= "<strong>디버깅 정보:</strong><br>";
        $output .= "선택된 상품: " . esc_html($selected_product) . "<br>";
        $output .= "기간: " . esc_html($start_date) . " ~ " . esc_html($end_date) . "<br>";
        $output .= "주문 데이터 결과 수: " . count($results) . "<br>";
        $output .= "출고 데이터 결과 수: " . count($delivery_results) . "<br>";
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
    // $output .= "<tr><td style='font-size: 13px; white-space: nowrap;'>주문수량 (" . esc_html($selected_unit) . ")</td>";
    $output .= "<tr><td style='font-size: 13px; white-space: nowrap;'>주문수량</td>";
    foreach ($grid_data as $row) {
        $output .= "<td style='font-size: 13px; white-space: nowrap;'>{$row['order']}</td>";
    }
    $output .= "</tr>";
    $output .= "<tr><td style='font-size: 13px; white-space: nowrap;'>출고수량</td>";
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
    $output .= "
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

        .period-selection { margin-bottom: 20px; }
        .period-selection input[type='date'],
        .period-selection select button {
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
            background-color: #2c80cf !important; /* 파란색으로 변경 */
            color: white !important; /* 텍스트 색상을 흰색으로 */
        }
        .period-selection button:active {
            background-color: #333333;
            border-color: #888888;
            transform: translateY(1px);
        }
        
        .monthly-grid { margin-top: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto; }
        .monthly-grid table { width: 100%; border-collapse: collapse; table-layout: fixed; }
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
    </style>";

    // 차트 스크립트
    $output .= "<script>
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
                            font: { size: 16, weight: 'bold' }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + new Intl.NumberFormat('ko-KR').format(context.parsed.y);
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

    // 상품 선택 드롭다운 요소 찾기
    $output .= "<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 상품 선택 드롭다운 요소 찾기
        const productSelect = document.querySelector('select[name=\"product_select_ordcnt\"]');
        
        // 상품 선택 시 자동 제출
        productSelect.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    </script>";

    return $output;
}
add_shortcode('jofMain_ordcntchart_mon', 'jofMainOrdCntChartShortCode_mon');
