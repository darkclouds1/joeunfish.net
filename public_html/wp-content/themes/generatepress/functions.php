<?php

/**
 * GeneratePress.
 *
 * Please do not make any edits to this file. All edits should be done in a child theme.
 *
 * @package GeneratePress
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Set our theme version.
define('GENERATE_VERSION', '3.4.0');

if (!function_exists('generate_setup')) {
	add_action('after_setup_theme', 'generate_setup');
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * @since 0.1
	 */
	function generate_setup()
	{
		// Make theme available for translation.
		load_theme_textdomain('generatepress');

		// Add theme support for various features.
		add_theme_support('automatic-feed-links');
		add_theme_support('post-thumbnails');
		add_theme_support('post-formats', array('aside', 'image', 'video', 'quote', 'link', 'status'));
		add_theme_support('woocommerce');
		add_theme_support('title-tag');
		add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style'));
		add_theme_support('customize-selective-refresh-widgets');
		add_theme_support('align-wide');
		add_theme_support('responsive-embeds');

		$color_palette = generate_get_editor_color_palette();

		if (!empty($color_palette)) {
			add_theme_support('editor-color-palette', $color_palette);
		}

		add_theme_support(
			'custom-logo',
			array(
				'height' => 70,
				'width' => 350,
				'flex-height' => true,
				'flex-width' => true,
			)
		);

		// Register primary menu.
		register_nav_menus(
			array(
				'primary' => __('Primary Menu', 'generatepress'),
			)
		);

		/**
		 * Set the content width to something large
		 * We set a more accurate width in generate_smart_content_width()
		 */
		global $content_width;
		if (!isset($content_width)) {
			$content_width = 1200; /* pixels */
		}

		// Add editor styles to the block editor.
		add_theme_support('editor-styles');

		$editor_styles = apply_filters(
			'generate_editor_styles',
			array(
				'assets/css/admin/block-editor.css',
			)
		);

		add_editor_style($editor_styles);
	}
}

/**
 * Get all necessary theme files 
 */
$theme_dir = get_template_directory();

require $theme_dir . '/inc/theme-functions.php';
require $theme_dir . '/inc/defaults.php';
require $theme_dir . '/inc/class-css.php';
require $theme_dir . '/inc/css-output.php';
require $theme_dir . '/inc/general.php';
require $theme_dir . '/inc/customizer.php';
require $theme_dir . '/inc/markup.php';
require $theme_dir . '/inc/typography.php';
require $theme_dir . '/inc/plugin-compat.php';
require $theme_dir . '/inc/block-editor.php';
require $theme_dir . '/inc/class-typography.php';
require $theme_dir . '/inc/class-typography-migration.php';
require $theme_dir . '/inc/class-html-attributes.php';
require $theme_dir . '/inc/class-theme-update.php';
require $theme_dir . '/inc/class-rest.php';
require $theme_dir . '/inc/deprecated.php';

if (is_admin()) {
	require $theme_dir . '/inc/meta-box.php';
	require $theme_dir . '/inc/class-dashboard.php';
}

/**
 * Load our theme structure
 */
require $theme_dir . '/inc/structure/archives.php';
require $theme_dir . '/inc/structure/comments.php';
require $theme_dir . '/inc/structure/featured-images.php';
require $theme_dir . '/inc/structure/footer.php';
require $theme_dir . '/inc/structure/header.php';
require $theme_dir . '/inc/structure/navigation.php';
require $theme_dir . '/inc/structure/post-meta.php';
require $theme_dir . '/inc/structure/sidebars.php';
require $theme_dir . '/inc/structure/search-modal.php';


// 자바스크립트 파일 등록
function my_enqueue_scripts()
{
	wp_enqueue_script('joeunfish', '/jof/js/joeunfish.js', array('jquery'), null, true);

	$current_user = wp_get_current_user();

	// PHP 변수를 자바스크립트로 전달
	wp_localize_script('joeunfish', 'myScriptData', array(
		'userId' => get_current_user_id(), // 현재 로그인한 사용자 ID
		'userName' => $current_user->user_login, // 현재 로그인한 사용자 이름
		'userRoles' => wp_get_current_user()->roles,
	));
}
add_action('wp_enqueue_scripts', 'my_enqueue_scripts');

// admin bar 숨김 functions.php 파일에 추가
function hide_admin_bar_for_non_admins()
{
	if (!current_user_can('administrator')) {
		show_admin_bar(false);
	}
}
add_action('after_setup_theme', 'hide_admin_bar_for_non_admins');


// 회원가입 이메일 도메인 제한
add_filter('registration_errors', 'check_email_domain', 10, 3);
function check_email_domain($errors, $sanitized_user_login, $user_email)
{
	// @joeunfish.net 도메인 체크
	if (!preg_match('/@joeunfish\.net$/', $user_email)) {
		$errors->add(
			'email_error',
			__('회원가입은 @joeunfish.net 도메인의 이메일 주소만 사용 가능합니다.')
		);
	}
	return $errors;
}

// REST API를 통한 회원가입 시도 차단
add_filter('rest_pre_insert_user', 'check_email_domain_api', 10, 2);
function check_email_domain_api($prepared_user, $request)
{
	$email = $request->get_param('email');
	if (!is_joeunfish_email($email)) {
		return new WP_Error(
			'rest_invalid_email',
			__('회원가입은 @joeunfish.net 도메인의 이메일 주소만 사용 가능합니다.'),
			array('status' => 400)
		);
	}
	return $prepared_user;
}

function is_joeunfish_email($email)
{
	return preg_match('/@joeunfish\.net$/i', $email);
}

// 모바일 기기 감지 함수
function is_mobile() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

// 모바일 페이지로 리다이렉트
function mobile_redirect() {
    if (is_mobile() && is_front_page()) {
        wp_redirect('/mobile'); // '/mobile'은 모바일용 페이지 URL
        exit;
    }
}
add_action('template_redirect', 'mobile_redirect');

// --------------------------
// 주문 예측 內  Plugin 순서
// --------------------------
function predicinv_content() {
  ob_start(); // 출력 버퍼 시작
  echo do_shortcode('[jofMain_ordcntchartPd]');

  // 숏코드 사이에 간격과 분리선 추가
  echo '<div style="margin: 30px 0;">
          <hr style="border: 0; height: 1px; background-color: #ddd; margin: 15px 0;">
        </div>';
  echo do_shortcode('[purchase_event]');
  return ob_get_clean(); // 버퍼 내용 반환 및 종료
}

function predicinv_content_shortcode() {
  return predicinv_content();
}
add_shortcode('predicinv_plugins', 'predicinv_content_shortcode');

// --------------------------
// 화면 로딩 속도 개선
// --------------------------
// 불필요한 리소스 제거 (특히 JS)
add_action('init', function() {
    if (!is_user_logged_in()) {
        // 관리자 바 제거
        add_filter('show_admin_bar', '__return_false');
        // emoji 제거
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
    }
});

// 모든 JS에 defer 또는 async 적용
function defer_js_parsing($url) {
    if (is_user_logged_in()) return $url;
    if (strpos($url, '.js') === false) return $url;
    return "$url' defer='defer";
}
add_filter('clean_url', 'defer_js_parsing', 11, 1);



// add_action('init', function () {
//     flush_rewrite_rules();
// });

// --------------------------
// 엑셀 파일 다운로드 
// --------------------------
add_action('wp_ajax_upload_excel_form', 'handle_excel_upload');
function handle_excel_upload() {
    if (!current_user_can('upload_files')) {
        wp_send_json_error('권한이 없습니다.');
    }

    if (!isset($_FILES['excel_file'])) {
        wp_send_json_error('파일이 전송되지 않았습니다.');
    }

    $uploaded_file = $_FILES['excel_file'];
    $upload_dir = wp_upload_dir();
    $target = $upload_dir['basedir'] . '/업무일지_' . time() . '.xlsx';

    if (move_uploaded_file($uploaded_file['tmp_name'], $target)) {
        // 여기서 jofSubul 함수 호출 등 추가 처리
        wp_send_json_success('파일 업로드 성공');
    } else {
        wp_send_json_error('파일 저장 실패');
    }
}