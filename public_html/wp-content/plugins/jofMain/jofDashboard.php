<?php
/*
 * jofDashboard.php
 * 조은수산 운영 현황 대시보드
 */

// --- 데이터 준비 (임시 데이터) ---
// 최종 주문 업데이트 시간
$last_order_update = date("Y-m-d H:i:s");

// 실시간 주문 현황
$costco_orders_completed = 15;
$costco_orders_total = 23;
$gs_orders_completed = 30;
$gs_orders_total = 35;

// 생산 및 재고
$daily_production = 3200;
$daily_production_change = 5; // %
$salmon_inventory_percent = 75;
$octopus_inventory_percent = 30;

// 주문 예측
$order_prediction_tomorrow = 5500;

// 원가/마진 요약
$margin_change = 1.2; // %

// 메뉴 목록 (jofMain.php 기반)
$main_menus = [
    ['label' => 'GS수발주', 'url' => '/ediagisgs/'],
    ['label' => '코스트코 발주', 'url' => '/ediagis/'],
    ['label' => '(CO)상품출고', 'url' => '/barcodeinv2/'],
    ['label' => '수불관리', 'url' => '/subul/'],
    ['label' => '추적(연어필렛)', 'url' => '/subul-trace/'],
    ['label' => 'ASC수불', 'url' => '/ascInvt/'],
    ['label' => '재고관리(냉동)', 'url' => '/octopusInvt/'],
    ['label' => '생산관리', 'url' => '/barcodeout/'],
    ['label' => '일일생산', 'url' => '/dayprod/'],
    ['label' => '출고관리(연어)', 'url' => '/barcodeinv/'],
    ['label' => '납기통계(CO)', 'url' => '/orderSummary-month/'],
    ['label' => '납기통계(GS)', 'url' => '/orderSummary-month-gs/'],
    ['label' => '주문예측', 'url' => '/predicinv/'],
    ['label' => '단가확인', 'url' => '/unitPrice/'],
    ['label' => '통관|매입', 'url' => '/purchase/'],
    ['label' => '공지사항', 'url' => '#', 'id' => 'jof-notice-btn'],
];

function get_inventory_status($percent) {
    if ($percent <= 40) return ['class' => 'danger', 'label' => '위험'];
    if ($percent <= 70) return ['class' => 'warning', 'label' => '주의'];
    return ['class' => 'safe', 'label' => '안전'];
}

$salmon_status = get_inventory_status($salmon_inventory_percent);
$octopus_status = get_inventory_status($octopus_inventory_percent);

?>

<style>
    /* --- Dashboard Styles --- */
    :root {
        --main-bg-color: #f0f2f5;
        --widget-bg-color: #ffffff;
        --text-color: #333;
        --title-color: #1a237e; /* Deep Ocean Blue 계열 */
        --primary-accent-color: #0d47a1; /* Deep Ocean Blue */
        --danger-color: #d32f2f;
        --warning-color: #f57c00;
        --safe-color: #388e3c;
        --font-family: 'Noto Sans KR', sans-serif;
    }

    .jof-dashboard-wrap {
        font-family: var(--font-family);
        background-color: var(--main-bg-color);
        padding: 0;
        color: var(--text-color);
    }

    /* Top Fixed Bar */
    .jof-top-bar {
        background: var(--widget-bg-color);
        padding: 10px 25px;
        position: sticky;
        top: 32px; /* WP Admin Bar height */
        z-index: 998;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        border-bottom: 1px solid #ddd;
        width: 100%;
        box-sizing: border-box;
    }
     body.admin-bar .jof-top-bar {
        top: 32px;
    }
    body:not(.admin-bar) .jof-top-bar {
        top: 0;
    }

    .jof-top-bar .update-time {
        font-size: 14px;
        font-weight: bold;
        color: var(--danger-color);
        margin-right: 20px;
    }
    .jof-top-bar .user-info {
        font-size: 14px;
        font-weight: 500;
    }

    /* Dashboard Grid */
    .jof-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        padding: 20px;
    }

    /* Widget Card Style */
    .jof-widget {
        background-color: var(--widget-bg-color);
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.05);
        padding: 20px;
        display: flex;
        flex-direction: column;
    }

    .jof-widget .widget-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--title-color);
        margin: 0 0 20px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-accent-color);
    }
    
    .jof-widget .widget-content {
        flex-grow: 1;
    }

    /* Specific Widget Styles */

    /* 주문 현황 */
    .order-status .status-item {
        margin-bottom: 15px;
    }
    .order-status .status-item:last-child {
        margin-bottom: 0;
    }
    .order-status .status-item strong {
        font-size: 16px;
        display: block;
        margin-bottom: 5px;
    }
    .order-status .progress-bar {
        width: 100%;
        height: 20px;
        background-color: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
    }
    .order-status .progress {
        height: 100%;
        background-color: var(--primary-accent-color);
        text-align: right;
        color: white;
        font-size: 12px;
        line-height: 20px;
        padding-right: 8px;
        white-space: nowrap;
        box-sizing: border-box;
    }
    .order-status .progress-label {
        font-size: 14px;
        text-align: right;
        margin-top: 4px;
    }
    
    /* 생산 및 재고 */
    .prod-inv-widget .stat-large {
        font-size: 28px;
        font-weight: bold;
    }
    .prod-inv-widget .stat-change {
        font-size: 16px;
        margin-left: 8px;
    }
    .prod-inv-widget .stat-change.positive { color: #2e7d32; }
    .prod-inv-widget .stat-change.negative { color: var(--danger-color); }

    .inventory-list { margin-top: 20px; }
    .inventory-item { margin-bottom: 15px; }
    .inventory-item strong { font-size: 16px; display: inline-block; width: 120px; }
    .inventory-item .bar-bg { display: inline-block; width: calc(100% - 170px); height: 15px; background: #eee; border-radius: 15px; vertical-align: middle; }
    .inventory-item .bar { height: 100%; border-radius: 15px; }
    .inventory-item .bar.safe { background-color: var(--safe-color); }
    .inventory-item .bar.warning { background-color: var(--warning-color); }
    .inventory-item .bar.danger { background-color: var(--danger-color); }
    .inventory-item .label { display: inline-block; width: 40px; text-align: right; font-weight: bold; font-size: 12px; vertical-align: middle; }
    .inventory-item .label.safe { color: var(--safe-color); }
    .inventory-item .label.warning { color: var(--warning-color); }
    .inventory-item .label.danger { color: var(--danger-color); }

    /* 주문 예측, 원가/마진 */
    .summary-widget .summary-value {
        font-size: 36px;
        font-weight: bold;
        color: var(--primary-accent-color);
        text-align: center;
    }
    .summary-widget .summary-label {
        font-size: 16px;
        text-align: center;
        margin-top: 10px;
    }
     .summary-widget .summary-change {
        font-size: 18px;
        font-weight: 500;
        text-align: center;
        margin-top: 5px;
    }
    .summary-widget .summary-change.positive { color: #2e7d32; }
    .summary-widget .summary-change.negative { color: var(--danger-color); }
    
    /* 전체 메뉴 */
    .menu-widget .menu-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .menu-widget .menu-button {
        display: block;
        padding: 12px 10px;
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 5px;
        text-align: center;
        text-decoration: none;
        color: #333;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }
    .menu-widget .menu-button:hover {
        background-color: var(--primary-accent-color);
        color: white;
        border-color: var(--primary-accent-color);
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .jof-dashboard-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 782px) {
        .jof-dashboard-grid {
            grid-template-columns: 1fr;
        }
        .jof-top-bar {
            flex-direction: column;
            align-items: flex-end;
            position: static; /* 모바일에서는 고정 해제 */
        }
        .jof-top-bar .update-time { margin-bottom: 5px; }
         body.admin-bar .jof-top-bar, body:not(.admin-bar) .jof-top-bar {
            top: 0;
        }
    }

</style>

<div class="jof-dashboard-wrap">
    <!-- 상단 고정 바 -->
    <div class="jof-top-bar">
        <div class="update-time">
            최종 주문 업데이트: <?php echo $last_order_update; ?>
        </div>
        <div class="user-info">
            <?php
                $current_user = wp_get_current_user();
                echo esc_html($current_user->display_name);
            ?> 님
        </div>
    </div>

    <!-- 메인 대시보드 그리드 -->
    <div class="jof-dashboard-grid">
        <!-- [1열] 실시간 현황 -->
        <div class="jof-widget order-status">
            <h3 class="widget-title">실시간 주문 현황 (당일 납기)</h3>
            <div class="widget-content">
                <div class="status-item">
                    <strong>코스트코(CO) 주문</strong>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo ($costco_orders_completed / $costco_orders_total) * 100; ?>%;">
                           <?php echo $costco_orders_completed; ?>
                        </div>
                    </div>
                    <div class="progress-label"><?php echo $costco_orders_completed . ' / ' . $costco_orders_total; ?> 건</div>
                </div>
                <div class="status-item">
                    <strong>GS리테일 주문</strong>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo ($gs_orders_completed / $gs_orders_total) * 100; ?>%;">
                           <?php echo $gs_orders_completed; ?>
                        </div>
                    </div>
                    <div class="progress-label"><?php echo $gs_orders_completed . ' / ' . $gs_orders_total; ?> 건</div>
                </div>
            </div>
        </div>

        <div class="jof-widget prod-inv-widget">
            <h3 class="widget-title">생산 및 재고 현황</h3>
            <div class="widget-content">
                 <div>
                    <span>일일 생산량:</span>
                    <span class="stat-large"><?php echo number_format($daily_production); ?></span> ea
                    <span class="stat-change <?php echo $daily_production_change >= 0 ? 'positive' : 'negative'; ?>">
                        (<?php echo $daily_production_change >= 0 ? '+' : ''; echo $daily_production_change; ?>%)
                    </span>
                </div>
                <div class="inventory-list">
                    <div class="inventory-item">
                        <strong>연어필렛 재고</strong>
                        <div class="bar-bg">
                            <div class="bar <?php echo $salmon_status['class']; ?>" style="width: <?php echo $salmon_inventory_percent; ?>%;"></div>
                        </div>
                         <span class="label <?php echo $salmon_status['class']; ?>"><?php echo $salmon_status['label']; ?></span>
                    </div>
                    <div class="inventory-item">
                        <strong>문어 재고</strong>
                        <div class="bar-bg">
                            <div class="bar <?php echo $octopus_status['class']; ?>" style="width: <?php echo $octopus_inventory_percent; ?>%;"></div>
                        </div>
                        <span class="label <?php echo $octopus_status['class']; ?>"><?php echo $octopus_status['label']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- [2열] 예측 및 분석 -->
        <div class="jof-widget summary-widget">
            <h3 class="widget-title">주문 예측</h3>
            <div class="widget-content">
                <div class="summary-value"><?php echo number_format($order_prediction_tomorrow); ?> <span style="font-size:20px;">ea</span></div>
                <div class="summary-label">익일 주문 예측 수량</div>
            </div>
        </div>

        <div class="jof-widget summary-widget">
            <h3 class="widget-title">원가/마진 분석 요약</h3>
            <div class="widget-content">
                <div class="summary-value positive" style="font-size: 32px; color: <?php echo $margin_change >= 0 ? '#2e7d32' : '#d32f2f'; ?>">
                    <?php echo $margin_change >= 0 ? '▲' : '▼'; ?> <?php echo abs($margin_change); ?>%
                </div>
                <div class="summary-label">주요 품목 마진율 (전주 대비)</div>
            </div>
        </div>

        <!-- [3열] 전체 메뉴 바로가기 -->
        <div class="jof-widget menu-widget" style="grid-column: span 2;">
            <h3 class="widget-title">조은수산 ERP 메뉴</h3>
            <div class="widget-content">
                <div class="menu-grid">
                    <?php foreach ($main_menus as $menu): ?>
                        <a href="<?php echo esc_url($menu['url']); ?>" 
                           class="menu-button" 
                           <?php if(isset($menu['id'])) echo 'id="' . esc_attr($menu['id']) . '"'; ?>>
                           <?php echo esc_html($menu['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
