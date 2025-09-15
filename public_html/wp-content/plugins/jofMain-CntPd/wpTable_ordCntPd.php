<?php
/* Plugin Name: jofMain-jofMain_ordcntchartPd : 수량-예측챠트
Plugin URI: https://joeunfish.online
Description: 조은수산 주문 예측 챠트 & 에측데이터 저장
Version: 2.0
Author: Monolith
Author URI: https://uloca.net
License: GPL2 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function enqueue_chartjsPd()
{
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
    wp_enqueue_script('chartjs-adapter-date-fns', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js', array('chartjs'), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_chartjsPd');

/**
 * 주어진 상품 코드에 대한 예측 데이터를 생성하는 공통 함수
 * @param string $product_code 예측할 상품 코드
 * @return array|null 예측 데이터 (dates, predictions) 또는 데이터 부족 시 null
 */
function jof_get_product_predictions($product_code) {
    global $wpdb;

    $twelve_months_ago = date('Ymd', strtotime('-12 months'));
    $tomorrow = date('Ymd', strtotime('+1 day'));

    $query = $wpdb->prepare("
        SELECT ord.supply_dt, SUM(prd.quantity) AS total_cnt
        FROM torders ord JOIN torderproducts prd ON ord.doc_no = prd.doc_no
        WHERE prd.product_code = %s AND ord.supply_dt BETWEEN %s AND %s
        GROUP BY ord.supply_dt ORDER BY ord.supply_dt ASC",
        $product_code, $twelve_months_ago, $tomorrow
    );
    $results = $wpdb->get_results($query);

    if (empty($results) || count($results) < 10) {
        return null; // 데이터 부족
    }

    $labels = [];
    $data = [];
    $allData = [];
    foreach ($results as $row) {
        $date = DateTime::createFromFormat('Ymd', $row->supply_dt);
        $formattedDate = $date->format('Y-m-d');
        $labels[] = $formattedDate;
        $data[] = round($row->total_cnt);
        $allData[] = ['date' => $formattedDate, 'quantity' => round($row->total_cnt)];
    }

    $today_weekday = (int)date('w');
    $realDataCount = 9 + $today_weekday;
    if ($realDataCount > 14) $realDataCount = 8;

    $pdWeekCount = (4 * 7) + 1;
    $pdDataCount = $pdWeekCount - $realDataCount;

    $last_seven_days_data = array_slice($allData, -$realDataCount);

    $weekday_data = ['first_third' => array_fill(0, 7, []), 'second_fourth' => array_fill(0, 7, [])];
    $weekday_dates = ['first_third' => array_fill(0, 7, []), 'second_fourth' => array_fill(0, 7, [])];
    $month_weeks = [];

    foreach ($results as $row) {
        $date = DateTime::createFromFormat('Ymd', $row->supply_dt);
        $weekday = $date->format('w');
        $year_month = $date->format('Ym');
        $day = $date->format('j');

        if (!isset($month_weeks[$year_month])) {
            $first_day_of_month = new DateTime($date->format('Y-m-01'));
            $first_day_weekday = $first_day_of_month->format('w');
            $first_full_week_start = $first_day_weekday == 0 ? 1 : 8 - $first_day_weekday;
            $month_weeks[$year_month] = [];
            $current_week = 0;
            for ($i = 1; $i <= 31; $i++) {
                if ($i < $first_full_week_start) {
                    $month_weeks[$year_month][$i] = 0;
                } else {
                    if ($i == $first_full_week_start) $current_week = 1;
                    $month_weeks[$year_month][$i] = $current_week;
                    if (($i - $first_full_week_start + 1) % 7 == 0) $current_week++;
                }
            }
        }

        $week_of_month = $month_weeks[$year_month][$day];
        if ($week_of_month == 2 || $week_of_month == 4) {
            $weekday_data['second_fourth'][$weekday][] = $row->total_cnt;
            $weekday_dates['second_fourth'][$weekday][] = $row->supply_dt;
        } else {
            $weekday_data['first_third'][$weekday][] = $row->total_cnt;
            $weekday_dates['first_third'][$weekday][] = $row->supply_dt;
        }
    }

    $weekday_predictions = ['first_third' => array_fill(0, 7, 0), 'second_fourth' => array_fill(0, 7, 0)];
    for ($i = 0; $i < 7; $i++) {
        if (!empty($weekday_data['first_third'][$i])) {
            $weekday_predictions['first_third'][$i] = intval(calculateWeightedMovingAverage($weekday_data['first_third'][$i], $weekday_dates['first_third'][$i]));
        }
        if (!empty($weekday_data['second_fourth'][$i])) {
            $weekday_predictions['second_fourth'][$i] = intval(calculateWeightedMovingAverage($weekday_data['second_fourth'][$i], $weekday_dates['second_fourth'][$i]));
        }
    }

    $next_week_dates = [];
    $next_week_predictions = [];
    $confidenceIntervals = []; // 신뢰구간 추가

    for ($i = 1; $i < $pdDataCount + 1; $i++) {
        $date = new DateTime("+$i days");
        $next_week_dates[] = $date->format('Y-m-d');
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('j');
        $weekday = $date->format('w');
        $year_month = $date->format('Ym');

        if (!isset($month_weeks[$year_month])) {
             $first_day_of_month = new DateTime("$year-$month-01");
            $first_day_weekday = $first_day_of_month->format('w');
            $first_week_start = $first_day_weekday == 0 ? 1 : 8 - $first_day_weekday;
            $month_weeks[$year_month] = [];
            $current_week = 1;
            for ($j = 1; $j <= 31; $j++) {
                if ($j < $first_week_start) {
                    $month_weeks[$year_month][$j] = 5;
                } else {
                    $month_weeks[$year_month][$j] = $current_week;
                    if ($j % 7 == $first_week_start % 7 && $j != $first_week_start) {
                        $current_week++;
                    }
                }
            }
        }

        $week_of_month = $month_weeks[$year_month][$day];
        $prediction = 0;
        if ($week_of_month == 1 || $week_of_month == 3 || $week_of_month == 5) {
            $prediction = $weekday_predictions['first_third'][$weekday];
        } else {
            $prediction = $weekday_predictions['second_fourth'][$weekday];
        }
        $next_week_predictions[] = $prediction;
        $confidenceIntervals[] = $prediction * 0.1; // 예시: 신뢰구간을 예측값의 10%로 설정
    }

    // 실제 데이터와 예측 데이터를 합쳐서 반환
    $all_predictions = array_merge(array_slice($data, 0, count($data) - $realDataCount), $next_week_predictions);

    return [
        'labels' => $labels,
        'data' => $data,
        'predictions' => $all_predictions,
        'confidence_intervals' => $confidenceIntervals,
        'weekday_predictions' => $weekday_predictions,
        'last_seven_days_data' => $last_seven_days_data,
        'pd_data_count' => $pdDataCount,
        'next_week_dates' => $next_week_dates,
        'next_week_predictions' => $next_week_predictions,
    ];
}


function jofMainOrdCntPdChartShortCode()
{
    global $wpdb;
    $logFilePath = __DIR__ . "/logfile.log";

    $orders_table = 'torders';
    $orderproducts_table = 'torderproducts';

    if (!session_id()) {
        session_start();
    }

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

    if (isset($_POST['product_select_ordcnt'])) {
        $selected_product = $_POST['product_select_ordcnt'];
        $_SESSION['selected_product_ordcnt'] = $selected_product;
    } elseif (isset($_SESSION['selected_product_ordcnt'])) {
        $selected_product = $_SESSION['selected_product_ordcnt'];
    } else {
        $selected_product = '2000000560770';
        $_SESSION['selected_product_ordcnt'] = $selected_product;
    }

    $selected_unit = '';
    foreach ($products as $product) {
        if ($product->product_code == $selected_product) {
            $selected_unit = $product->unit;
            break;
        }
    }

    $two_months_ago = date('Ymd', strtotime('-12 months'));
    // $today = date('Ymd');
    $tomorrow = date('Ymd', strtotime('+1 day'));

    $query = $wpdb->prepare(
        "
                            SELECT 
                                ord.supply_dt,
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
                                ord.supply_dt
                            ORDER BY 
                                ord.supply_dt ASC
                            ",
        $selected_product,
        $two_months_ago,
        $tomorrow
    );

    $results = $wpdb->get_results($query);
    $output = "<form method='post'>";

    // 제품 선택 UI 생성
    $output .= "<div style='height: 10px;'></div>";
    $output .= "<select name='product_select_ordcnt' onchange='this.form.submit()'>";
    $output .= "<option value=''>제품을 선택하세요</option>";
    foreach ($products as $product) {
        $selected = ($product->product_code == $selected_product) ? 'selected' : '';
        $output .= "<option value='{$product->product_code}' {$selected}>{$product->product_name}</option>";
    }
    $output .= "</select>";
    $output .= "</form>";

    if (!empty($selected_product)) {
        // 예측 데이터 가져오기
        $predictionResult = jof_get_product_predictions($selected_product);

        if (!$predictionResult) {
            $output .= "<p>데이터가 부족하여 예측을 생성할 수 없습니다. (제품 코드: $selected_product)</p>";
        } else {
            // 예측 결과에서 변수 추출
            $labels = $predictionResult['labels'];
            $data = $predictionResult['data'];
            $predictionData = $predictionResult['predictions'];
            $confidenceIntervals = $predictionResult['confidence_intervals'];
            $weekday_predictions = $predictionResult['weekday_predictions'];
            $lastRealData = $predictionResult['last_seven_days_data'];
            $pdDataCount = $predictionResult['pd_data_count'];
            $next_week_dates = $predictionResult['next_week_dates'];
            $next_week_predictions = $predictionResult['next_week_predictions'];
            $weekdayPdData = []; // 초기화

            // 예측 기간의 레이블 추가
            if (!empty($labels)) {
                $last_date = end($labels);
                for ($i = 1; $i <= $pdDataCount; $i++) {
                    $next_date = date('Y-m-d', strtotime($last_date . ' +' . $i . ' days'));
                    $labels[] = $next_date;
                }
            }

            // 선형 회귀분석 데이터 계산
            $linear_regression_data = jof_calculate_linear_regression($data, count($labels));

            // MAPE 데이터 계산 (실제 데이터가 있는 기간만)
            $mapeData = array();
            $dataCount = count($data);
            for ($i = 0; $i < $dataCount; $i++) {
                $actual = $data[$i];
                if (isset($predictionData[$i])) {
                    $predicted = $predictionData[$i];
                    if ($actual != 0) {
                        $mape = abs(($actual - $predicted) / $actual) * 100;
                        $mapeData[] = $mape;
                    } else {
                        $mapeData[] = null;
                    }
                } else {
                    $mapeData[] = null;
                }
            }

            // 차트 데이터 구조
            $chart_data = array(
                    'labels' => $labels,
                    'datasets' => array(
                        array(
                            'type' => 'line',
                            'label' => '실제 주문량',
                            'data' => $data,
                            'borderColor' => 'rgba(75, 192, 192, 1)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                            'borderWidth' => 2,
                            'pointRadius' => 0,
                            'fill' => false,
                            'tension' => 0.4,
                            'cubicInterpolationMode' => 'monotone',
                            'yAxisID' => 'y',
                        ),
                        array(
                            'type' => 'line',
                            'label' => '선형 회귀분석 예측',
                            'data' => $linear_regression_data,
                            'borderColor' => 'rgba(54, 162, 235, 1)',
                            'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                            'borderWidth' => 2,
                            'pointRadius' => 0,
                            'fill' => false,
                            'tension' => 0,
                            'borderDash' => [5, 5],
                            'yAxisID' => 'y',
                        ),
                        // 요일별 예측 주문량 데이터셋
                        array(
                            'type' => 'line',
                            'label' => '요일별 예측 주문량',
                            'data' => array_map(function ($date, $pred) use (&$weekdayPdData) {
                                // DB 저장 시 사용된 정확한 예측값($pred)을 그대로 사용하고,
                                // 화면 표시용으로 재계산하던 부정확한 로직을 제거합니다.

                                // monthPdDate 배열에 데이터 저장
                                $weekdayPdData[] = array(
                                    'date' => $date,
                                    'quantity' => $pred
                                );

                                return [
                                    'x' => $date,
                                    'y' => $pred
                                ];
                            }, $next_week_dates, $next_week_predictions),
                            'borderColor' => 'rgba(255, 159, 64, 1)',
                            'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                            'borderWidth' => 2,
                            'pointRadius' => 3,
                            'fill' => false,
                            'tension' => 0,
                            'yAxisID' => 'y',
                        ),
                    ),
                );

                // ----------------------------------------------------------
                // 1달 예측데이터 그리드 표시 (마지막 7일 데이터와 동시에 표시)
                // ----------------------------------------------------------
                // error_log("ln524::lastRealData: " . print_r($lastRealData, true), 3, $logFilePath);
                // error_log("ln525::weekdayPdData: " . print_r($weekdayPdData, true), 3, $logFilePath);

                // $weekdayPdData의 첫 번째 요소 삭제 및 인덱스 재배열
                array_shift($weekdayPdData);
                $weekdayPdData = array_values($weekdayPdData);
                // error_log("ln591::weekdayPdData: " . print_r($weekdayPdData, true), 3, $logFilePath);

                // 마지막 7일 데이터와 요일별 예측 주문량 데이터 결합
                $lastRealDataCount = count($lastRealData);
                $weekdayPdData = array_merge($lastRealData, $weekdayPdData);
                // error_log("ln595::combinedArray: " . print_r($weekdayPdData, true), 3, $logFilePath);

                // 1. 요일 배열 정의
                $weekdays = array('일', '월', '화', '수', '목', '금', '토', '주간합계');

                // 2. 첫 번째 데이터의 요일 확인
                $first_day_weekday = date('w', strtotime($weekdayPdData[0]['date']));

                // 4. 테이블 헤더 생성
                $weekdayPdData_html = "<br><table class='order-table' border='1'><tr>";
                foreach ($weekdays as $day) {
                    $weekdayPdData_html .= "<th style='background-color: #f0f0f0; font-size: 13px;'>{$day}</th>";
                }
                $weekdayPdData_html .= "</tr>";

                // -------------------
                // 5. 데이터 표시
                // -------------------
                $data_index = 0;
                $row = 0;
                $weekdayPdDataCount = count($weekdayPdData);
                $maxRows = ceil($weekdayPdDataCount / 7); // 필요한 행의 수 계산

                // error_log("\nln623::weekdayPdDataCount: " . $weekdayPdDataCount . ", maxRows: " . $maxRows, 3, $logFilePath);

                while ($data_index < $weekdayPdDataCount) {
                    $weekdayPdData_html .= "<tr>";
                    $cells_in_row = 0;

                    // error_log("\nln622::data_index: " . $data_index, 3, $logFilePath);
                    if ($data_index >= $weekdayPdDataCount) break;

                    // 데이터 채우기
                    $week_total = 0;
                    while ($cells_in_row < 7 ) {
                        // error_log("\n-----------------------------------", 3, $logFilePath);
                        // error_log("\nln638::cells_in_row: " . $cells_in_row, 3, $logFilePath);
                        // error_log("\nln638::data_index: " . $data_index, 3, $logFilePath);
                        // error_log("\nln639::weekdayPdDataCount: " . $weekdayPdDataCount, 3, $logFilePath);

                        $date = $weekdayPdData[$data_index]['date'];
                        $prediction = $weekdayPdData[$data_index]['quantity'];
                        $week_total += $prediction;

                        // 해당 날짜의 주차 계산
                        $weekOfMonth = getWeekOfMonth($date);

                        // DIDA 주 여부 확인 (2주차, 4주차)
                        // $isDIDAWeek = ($weekOfMonth == 2 || $weekOfMonth == 4);

                        $color = ($lastRealDataCount > $data_index) ? 'black' : 'red';
                        $isToday = date('n/j', strtotime($date)) == date('n/j');
                        $weekdayPdData_html .= "<td style='text-align: center; font-size: 13px;" . ($isToday ? " background-color: #BBF731;" : "") . "'>";
                        $weekdayPdData_html .= "<font color='$color'>(" . date('n/j', strtotime($date)) . ")</font><br>";
                        $weekdayPdData_html .= number_format($prediction);
                        $weekdayPdData_html .= "</td>";

                        $data_index++;
                        $cells_in_row++;
                        if ($data_index >= $weekdayPdDataCount) break;
                    }


                    // 주차별 멘트 설정 - 단순화
                    switch ($row) {
                        case 0:
                            $weekOfMonth = getWeekOfMonth($weekdayPdData[$data_index - $cells_in_row]['date']);
                            $totalMent = ($weekOfMonth == 2 || $weekOfMonth == 4) ?
                                '(DIDA)<br>전주실적' : '전주실적';
                            break;
                        default:
                            $weekOfMonth = getWeekOfMonth($weekdayPdData[$data_index - $cells_in_row]['date']);
                            $totalMent = ($weekOfMonth == 2 || $weekOfMonth == 4) ?
                                '(DIDA)<br>주간예측' : '주간예측';
                            break;
                    }

                    // 남은 셀 채우기 (남은 셀 없이 항상 21개 데이터가 있어야 정상 )
                    while ($cells_in_row < 7) {
                        $weekdayPdData_html .= "<td></td>";
                        $cells_in_row++;
                    }
                    
                    $color = ($lastRealDataCount > $cells_in_row) ? 'red' : 'red';
                    $weekdayPdData_html .= "<td style='text-align: center; font-size: 13px; background-color: #f0f0f0;'><font color='{$color}'>{$totalMent}</font><br>" . number_format($week_total) . "</td>";
                    $weekdayPdData_html .= "</tr>";
                    $row++;

                    // 안전장치: 무한 루프 방지
                    if ($row > 8) {
                        error_log("Warning: Loop exceeded 10 iterations. Breaking to prevent infinite loop.", 3, $logFilePath);
                        break;
                    }
                    
                    $weekdayPdData_html .= "</tr>";
                }

                $weekdayPdData_html .= "</table>";
                $weekdayPdData_html .=
                    "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var table = document.querySelector('.order-table');
                var lastSelectedCell = null;

                table.addEventListener('click', function(e) {
                    var cell = e.target.closest('td');
                    if (!cell || cell.cellIndex === 7) return; // 헤더나 마지막 열(합계)은 제외

                    if (lastSelectedCell) {
                        lastSelectedCell.style.backgroundColor = ''; // 이전 선택 셀 색상 초기화
                    }

                    if (cell !== lastSelectedCell) {
                        cell.style.backgroundColor = '#BBF731'; // 새로 선택한 셀 색상 변경
                        lastSelectedCell = cell;
                    } else {
                        lastSelectedCell = null; // 같은 셀을 다시 클릭하면 선택 해제
                    }
                });
            });
            </script>";


            // 차트 데이터 구조 수정(요일별 예측 주문량)
            $today = new DateTime();
            $next_week_dates = array();
            $next_week_predictions = array();

            // 요일 배열 추가
            $weekdays = array('일', '월', '화', '수', '목', '금', '토');

            // 일요일부터 시작하도록 조정
            $daysUntilSunday = 7 - $today->format('w');
            $startDate = clone $today;
            $startDate->modify("+{$daysUntilSunday} days");

            // 463번 줄 주변의 코드 수정
            $next_week_predictions = array();
            for ($i = 0; $i < 7; $i++) {

                $week_of_month = ceil(date('j', strtotime("+$i days")) / 7);

                if ($week_of_month == 1 || $week_of_month == 3 || $week_of_month == 5) {
                    $next_week_predictions[] = $weekday_predictions['first_third'][$i];
                } else {
                    $next_week_predictions[] = $weekday_predictions['second_fourth'][$i];
                }
            }

            $chart_id = 'myChart_' . uniqid();

            // 한달 예측데이터 그리드 표시
            $output .= $weekdayPdData_html;

            // 차트 표시
            $output .= "<div style='height: 30px;'></div>"; // 추가 50픽셀의 간격
            $output .= "<div style='width: 98%; margin: 0 auto;'>";
            $output .= "<div style='position: relative; height: 300px;'><canvas id='{$chart_id}'></canvas></div>";
            $output .= "</div>";

            // $output .= "<div style='height: 30px;'></div>"; // 추가 40픽셀의 간격

            $output .=
                "<script>
document.addEventListener('DOMContentLoaded', function() {
    var chartData = " . json_encode($chart_data) . ";
    var chartId = '" . $chart_id .
    "';
    var ctx = document.getElementById(chartId);
    
    if (ctx) {
        var myChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 4) {
                                        label += context.parsed.y;  // 요일별 예측 주문량
                                    } else {
                                        label += new Intl.NumberFormat('ko-KR').format(context.parsed.y);
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MM/dd'
                            }
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            callback: function(value, index, values) {
                                var date = new Date(value);
                                if (date.getDay() === 1 || index === 0 || index === values.length - 1) {
                                    return (date.getMonth() + 1) + '/' + date.getDate();
                                }
                                return '';
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: ''
                        },
                        ticks: {
                            callback: function(value, index, values) {
                                return new Intl.NumberFormat('ko-KR').format(value);
                            }
                        }
                    },
                },
                elements: {
                    line: {
                        tension: 0.4,
                        cubicInterpolationMode: 'monotone'
                    }
                }
            }
        });

       // 창 크기 변경 시 차트 크기 조정
        window.addEventListener('resize', function() {
            myChart.resize();
        });
    } else {
        console.error('Canvas element not found:', chartId);
    }

});
</script>";

            // 1주일 평균 예측 주문량 텍스트 및 일주일 예측 주문량 합계 추가
            $output .=
            "
<style>
    .prediction-box {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 20px;
    }
    .prediction-item {
        border: 1px solid #333;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        flex: 1;
        max-width: 300px;
    }
    .prediction-title {
        font-size: 17px;
        margin-bottom: 5px;
    }
    .prediction-value {
        font-weight: bold;
        font-size: 17px;
        margin-bottom: 5px;
    }
    .daily-prediction {
        background-color: white;
    }
    .weekly-prediction {
        background-color: white;
    }
    .first-third-prediction {
        background-color: rgba(255, 99, 132, 0.2);
    }
    .second-fourth-prediction {
        background-color: rgba(54, 162, 235, 0.2);
    }

    /* 테이블 관련 스타일 추가 */
    .order-table td {
        transition: background-color 0.3s;
    }
    .order-table td:hover {
        background-color: #f5f5f5;
    }
    /* tr hover 효과 제거 */
    .order-table tr:hover {
        background-color: transparent !important;
    }
</style>

<div style='height: 20px;'></div>

<div class='prediction-box'>
<div class='prediction-item first-third-prediction'>
    <div class='prediction-title'>첫째/셋째 주 예측(SUM)</div>
    <div class='prediction-value'>" . number_format(intval(array_sum($weekday_predictions['first_third']))) . " {$selected_unit}</div>
    <div class='prediction-title'>평균 일일 주문량</div>
    <div class='prediction-value'>" . number_format(intval(array_sum($weekday_predictions['first_third']) / count($weekday_predictions['first_third']))) . " {$selected_unit}</div>
</div>
<div class='prediction-item second-fourth-prediction'>
    <div class='prediction-title'>둘/넷째(DIDA)주 예측(SUM)</div>
    <div class='prediction-value'>" . number_format(intval(array_sum($weekday_predictions['second_fourth']))) . " {$selected_unit}</div>
    <div class='prediction-title'>평균 일일 주문량</div>
    <div class='prediction-value'>" . number_format(intval(array_sum($weekday_predictions['second_fourth']) / count($weekday_predictions['second_fourth']))) . " {$selected_unit}</div>
</div>
</div>";

        } // end of else for if (!$predictionResult)
    } else {
        // 제품이 선택되지 않았을 경우 차트를 표시하지 않음
        $output .= "<p>제품을 선택해주세요.</p>";
    }

    return $output;
}

// 가중이동평균
function calculateWeightedMovingAverage($data, $dates)
{
    $n = count($data);
    if ($n == 0) return 0; // 데이터가 없으면 0 반환
    if ($n == 1) return max(1, $data[0]);

    // 데이터가 모두 0인 경우 처리
    if (array_sum($data) == 0) {
        return 1; // 또는 다른 기본값
    }
 
    // 이상치 제거
    $filtered_data = removeOutliersMAD($data);

    // 필터링 후 데이터 로그
    error_log("Filtered data: " . print_r($filtered_data, true));

    // 필터링 후 데이터가 없거나 하나만 있는 경우 처리
    if (empty($filtered_data)) {
        return max(1, calculateMedian($data));
    }
    if (count($filtered_data) == 1) {
        return max(1, reset($filtered_data));
    }

    // 이중 지수평활법 적용
    // 이 방법은 데이터의 수준(level)과 추세(trend)를 모두 고려합니다.
    // alpha: 수준 평활 계수 (0.9는 최근 데이터에 높은 가중치를 줍니다)
    // beta: 추세 평활 계수 (0.9는 최근 추세 변화에 빠르게 반응합니다)
    // 높은 alpha와 beta 값은 최근 데이터와 추세 변화에 더 민감하게 반응하지만,노이즈에도 더 민감할 수 있습니다.
    $alpha = 0.8;
    $beta = 0.8;

    $level = reset($filtered_data);
    $trend = next($filtered_data) - $level;

    $smoothed_values = [$level];

    foreach ($filtered_data as $i => $value) {
        if ($i == 0) continue; // 첫 번째 값은 이미 처리했으므로 건너뜀
        $last_level = $level;
        $level = $alpha * $value + (1 - $alpha) * ($level + $trend);
        $trend = $beta * ($level - $last_level) + (1 - $beta) * $trend;
        $smoothed_values[] = $level + $trend;
    }

    // 다음 기간 예측
    $prediction = $level + $trend;

    // EMA 계산 (비교용)
    $ema = calculateEMA($filtered_data, $alpha);

    // EMA와 예측값의 차이 계산
    $diff = abs($prediction - $ema);
    $avg = ($prediction + $ema) / 2;

    // 차이가 크면 EMA와 예측값의 평균 사용
    if ($avg != 0 && $diff > 0.1 * $avg) {
        $prediction = $avg;
    }

    return max(1, $prediction);
}

function calculateEMA($data, $alpha)
{
    if (empty($data)) return 0;
    if (count($data) == 1) return reset($data);

    $ema = reset($data);
    foreach (array_slice($data, 1) as $value) {
        $ema = $alpha * $value + (1 - $alpha) * $ema;
    }
    return $ema;
}

//  $mad_threshold 3.5에서 2.5 또는 1.5으로 낮추면 더 많은 데이터 포인트가 이상치로 간주됩니다.
// 0.5로 낮추면 더 많은 데이터 포인트가 이상치로 간주됩니다.
function removeOutliersMAD($data, $mad_threshold = 0.5, $iqr_factor = 1.0)
{
    if (empty($data)) return [];

    // MAD 방식
    $median = calculateMedian($data);
    $mad = calculateMedian(array_map(function ($x) use ($median) {
        return abs($x - $median);
    }, $data));

    // IQR 방식
    sort($data);
    $count = count($data);
    $q1 = $data[floor($count / 4)];
    $q3 = $data[floor($count * 3 / 4)];
    $iqr = $q3 - $q1;
    $lower_bound = $q1 - $iqr_factor * $iqr;
    $upper_bound = $q3 + $iqr_factor * $iqr;

    // MAD가 0인 경우 처리
    if ($mad == 0) {
        // IQR만 사용
        return array_filter($data, function ($x) use ($lower_bound, $upper_bound) {
            return $x >= $lower_bound && $x <= $upper_bound;
        });
    }

    // MAD와 IQR 방식 모두 사용
    return array_filter($data, function ($x) use ($median, $mad, $mad_threshold, $lower_bound, $upper_bound) {
        $mad_condition = $mad != 0 ? (abs($x - $median) / $mad <= $mad_threshold) : true;
        $iqr_condition = $x >= $lower_bound && $x <= $upper_bound;
        return $mad_condition && $iqr_condition;
    });
}

function calculateMedian($arr)
{
    if (empty($arr)) return 0;

    sort($arr);
    $count = count($arr);
    $middleval = floor(($count - 1) / 2);
    if ($count % 2) {
        $median = $arr[$middleval];
    } else {
        $low = $arr[$middleval];
        $high = $arr[$middleval + 1];
        $median = (($low + $high) / 2);
    }
    return $median;
}

// 주차 계산 함수 - 단순히 일요일부터 시작하는 주차만 계산
function getWeekOfMonth($date)
{
    $firstDayOfMonth = date('Y-m-01', strtotime($date));
    $firstDayWeekday = date('w', strtotime($firstDayOfMonth));
    $dayOfMonth = date('j', strtotime($date));

    // 일요일부터 시작하는 주차 계산
    $offset = $dayOfMonth + $firstDayWeekday - 1;
    return ceil($offset / 7);
}

// 선형 회귀분석 계산 함수
function jof_calculate_linear_regression($data, $total_points) {
    $n = count($data);
    if ($n < 2) {
        return array_fill(0, $total_points, 0); // 데이터가 부족하여 계산 불가
    }

    $sum_x = 0;
    $sum_y = 0;
    $sum_xy = 0;
    $sum_x2 = 0;

    foreach ($data as $i => $y) {
        $x = $i;
        $sum_x += $x;
        $sum_y += $y;
        $sum_xy += $x * $y;
        $sum_x2 += $x * $x;
    }

    $denominator = ($n * $sum_x2 - $sum_x * $sum_x);
    if ($denominator == 0) {
        // 0으로 나누기 방지, 평균값 반환
        $avg = $sum_y / $n;
        return array_fill(0, $total_points, round($avg));
    }

    $slope = ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
    $intercept = ($sum_y - $slope * $sum_x) / $n;

    $regression_line = [];
    for ($i = 0; $i < $total_points; $i++) {
        $predicted_y = $slope * $i + $intercept;
        // 예측값은 0보다 작을 수 없음
        $regression_line[] = round(max(0, $predicted_y));
    }

    return $regression_line;
}

add_shortcode('jofMain_ordcntchartPd', 'jofMainOrdCntPdChartShortCode');
