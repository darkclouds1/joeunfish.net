<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function jof_execute_daily_prediction() {
    global $wpdb;

    $log_dir = WP_CONTENT_DIR . '/';
    $log_file = $log_dir . 'debug_gs_monthly.log';
    error_log(date('[Y-m-d H:i:s]') . " [Prediction Cron] Cron job started.\n", 3, $log_file);

    $target_products = ['2000000560770']; // 연어필렛

    foreach ($target_products as $selected_product) {
        $base_dt = date('Y-m-d');
        $predict_table = 'torder_predict';

        $prediction_exists_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$predict_table} WHERE product_code = %s AND base_dt = %s",
            $selected_product,
            $base_dt
        ));

        if ($prediction_exists_today > 0) {
            error_log(date('[Y-m-d H:i:s]') . " [Prediction Cron] Prediction for product {$selected_product} already exists today. Skipping.\n", 3, $log_file);
            continue;
        }

        // 공통 예측 함수를 호출하여 결과를 가져옵니다.
        $prediction_data = jof_get_product_predictions($selected_product);

        if (!$prediction_data) {
            error_log(date('[Y-m-d H:i:s]') . " [Prediction Cron] Not enough data to predict for product {$selected_product}. Skipping.\n", 3, $log_file);
            continue;
        }

        $next_week_dates = $prediction_data['next_week_dates'];
        $next_week_predictions = $prediction_data['next_week_predictions'];
        
        // --- DB 저장 로직 시작 ---
        $wpdb->query('START TRANSACTION');
        $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
        $delete_result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$predict_table} WHERE product_code = %s AND predict_dt >= %s",
            $selected_product,
            $tomorrow_date
        ));

        $update_error = false;
        if ($delete_result !== false) {
            for ($i = 0; $i < count($next_week_dates); $i++) {
                $insert_result = $wpdb->insert(
                    $predict_table,
                    [
                        'predict_dt'    => $next_week_dates[$i],
                        'product_code'  => $selected_product,
                        'predict_qty'   => $next_week_predictions[$i],
                        'base_dt'       => $base_dt,
                    ],
                    ['%s', '%s', '%d', '%s']
                );
                if ($insert_result === false) {
                    $update_error = true;
                    break;
                }
            }
        } else {
            $update_error = true;
        }

        if ($update_error) {
            $wpdb->query('ROLLBACK');
            error_log(date('[Y-m-d H:i:s]') . " [Prediction Cron] DB update FAILED for product {$selected_product}.\n", 3, $log_file);
        } else {
            $wpdb->query('COMMIT');
            error_log(date('[Y-m-d H:i:s]') . " [Prediction Cron] DB update SUCCESS for product {$selected_product}.\n", 3, $log_file);
        }
        // --- DB 저장 로직 종료 ---
    }
}
