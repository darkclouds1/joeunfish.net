<?php
/* Plugin Name: jofMain_ordcntgschart : (GS)수량-주문챠트
Plugin URI: https://test.joeunfish.online
Description: 조은수산 입|출고 관리
Version: 0.1
Author: Monolith
Author URI: https://uloca.net
License: GPL2 */
function enqueue_chartjs_for_gs_chart()
{
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_chartjs_for_gs_chart');

function jofMainOrdCntGsChartShortCode()
{
    global $wpdb;

    $orders_table = 'torders';
    $orderproducts_table = 'torderproducts';

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

    $months = 3;
    $weeks = $months * 4.33; // 한 달 평균 4.33주
    $limit = ceil($weeks); // 주 수를 올림하여 정수로 변환

    $months_ago = date('Ymd', strtotime("-$months months"));
    $today = date('Ymd');

    $query = $wpdb->prepare("
    SELECT 
        YEARWEEK(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'),1) AS year_week,
        MIN(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d')) AS week_start,
        MAX(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d')) AS week_end,
        SUM(prd.quantity) AS total_cnt,
        MAX(prd.product_name) AS product_name
    FROM 
        $orders_table ord
    JOIN 
        $orderproducts_table prd ON ord.doc_no = prd.doc_no
    WHERE 
        prd.product_code = %s
        AND ord.supply_dt BETWEEN %s AND %s
    GROUP BY 
         YEARWEEK(STR_TO_DATE(ord.supply_dt, '%%Y%%m%%d'), 1) -- 주간 시작일을 월요일로 설정
    ORDER BY 
        year_week DESC
            LIMIT %d
            ",
        $selected_product,
        $months_ago,
        $today,
        $limit
    );

    $results = $wpdb->get_results($query);

    if (empty($results)) {
        $output = "<p>데이터가 없습니다. 다른 제품을 선택해주세요.</p>";

        // 제품 선택 콤보박스 추가
        $output .= "<form method='post'>";
        $output .= "<select name='product_select_gs' onchange='this.form.submit()'>";
        foreach ($products as $product) {
            $selected = ($product->product_code == $selected_product) ? 'selected' : '';
            $unit = $product->unit ? " ({$product->unit})" : '';
            $output .= "<option value='{$product->product_code}' {$selected}>{$product->product_name}{$unit}</option>";
        }
        $output .= "</select>";
        $output .= "</form>";

        return $output;
    }

    $results = array_reverse($results);

    $labels = array();
    $data = array();
    $product_name = '';

    // 한글 요일 배열 정의
    $korean_days = array('일', '월', '화', '수', '목', '금', '토');

    foreach ($results as $row) {
        $week_start = strtotime($row->week_start);
        $day_of_week = $korean_days[date('w', $week_start)]; // 한글 요일
        $date_string = date('n/j', $week_start);
        $labels[] = "$date_string ($day_of_week)"; // 날짜와 한글 요일 결합
        $data[] = round($row->total_cnt); // 소수점 제거
        if (empty($product_name)) {
            $product_name = $row->product_name;
        }
    }

    // 평균 계산
    $average = array_sum($data) / count($data);

    // 색상 배열 생성
    $backgroundColors = array_map(function ($value) use ($average) {
        return $value <= $average ? 'rgba(255, 0, 0, 0.8)' : 'rgba(0, 0, 0, 0.8)'; // 빨간색 또는 파란색
    }, $data);

    $borderColors = array_map(function ($value) use ($average) {
        return $value <= $average ? 'rgba(255, 0, 0, 1)' : 'rgba(0, 0, 0, 0.8)'; // 빨간색 또는 파란색
    }, $data);

    $chart_data = array(
        'labels' => $labels,
        'datasets' => array(
            array(
                'data' => $data,
                'backgroundColor' => $backgroundColors, // 조건에 따른 배경색
                'borderColor' => $borderColors, // 조건에 따른 테두리색
                'borderWidth' => 1,
                'barThickness' => 'flex',
                'barPercentage' => 0.5,
            )
        )
    );

    $chart_json = json_encode($chart_data);
    $chart_id = 'myChart_' . uniqid();

    $output = "<form method='post'>";
    $output .= "<select name='product_select_gs' onchange='this.form.submit()'>";
    foreach ($products as $product) {
        $selected = ($product->product_code == $selected_product) ? 'selected' : '';
        $unit = $product->unit ? " ({$product->unit})" : '';
        $output .= "<option value='{$product->product_code}' {$selected}>{$product->product_name}{$unit}</option>";
    }
    $output .= "</select>";
    $output .= "</form>";

    $output .= "<div style='height: 270px;'><canvas id='$chart_id'></canvas></div>";
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
                            display: false
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
                                    return new Intl.NumberFormat('ko-KR').format(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value, index, values) {
                                    return new Intl.NumberFormat('ko-KR').format(value);
                                }
                            },
                            grid: {
                                display: true
                            },
                            suggestedMin: 0,
                            suggestedMax: Math.ceil(Math.max(...chartData.datasets[0].data) * 1.1)
                        }
                    }
                }
            });
        });
    </script>";

    return $output;
}
add_shortcode('jofMain_ordcntgschart', 'jofMainOrdCntGsChartShortCode');
