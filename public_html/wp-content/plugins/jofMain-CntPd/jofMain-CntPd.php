<?php
/**
 * Plugin Name: jofMain CntPd
 * Description: A plugin to display order counts and predictions.
 * Version: 1.2
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// 이 플러그인에 필요한 파일만 포함합니다.
require_once __DIR__ . '/wpTable_ordCntPd.php';
require_once __DIR__ . '/jof-daily-prediction.php';

// 매일 실행될 함수를 Cron 이벤트 훅에 연결합니다.
add_action('jof_daily_prediction_event', 'jof_execute_daily_prediction');

// 플러그인이 활성화될 때 실행될 함수
function jof_cntpd_plugin_activation() {
    if (!wp_next_scheduled('jof_daily_prediction_event')) {
        wp_schedule_event(strtotime('tomorrow 3:00:00'), 'daily', 'jof_daily_prediction_event');
    }
}
register_activation_hook(__FILE__, 'jof_cntpd_plugin_activation');

// 플러그인이 비활성화될 때 실행될 함수
function jof_cntpd_plugin_deactivation() {
    wp_clear_scheduled_hook('jof_daily_prediction_event');
}
register_deactivation_hook(__FILE__, 'jof_cntpd_plugin_deactivation');

