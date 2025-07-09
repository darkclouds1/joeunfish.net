<?php
/**
 * Plugin Name: Disable Update Notifications
 * Description: Hide plugin & theme update notifications
 */

// 플러그인 업데이트 숨기기 - by darkcloud 25/6/10
add_filter( 'pre_site_transient_update_plugins', '__return_null' );

// 테마 업데이트 숨기기
add_filter( 'pre_site_transient_update_themes', '__return_null' );

// 핵심 업데이트 숨기기
add_filter( 'pre_site_transient_update_core', '__return_null' );

// 알림 배지 제거
remove_action( 'admin_notices', 'update_nag', 3 );
