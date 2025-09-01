<?php
/*
Plugin Name: Unit Price 
Description: 단가 확인 기능을 제공하는 플러그인
Version: 1.0
Author: Your Name
*/

function upc_shortcode()
{
    wp_enqueue_style('upc-style', plugins_url('unit-style.css', __FILE__));
    ob_start();

?>
    <!DOCTYPE html>
    <html lang="kr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>단가확인</title>

        <script rel="preload" src="/wp-content/plugins/unit-price/price_history.js"></script>
        <script rel="preload" src="/wp-content/plugins/unit-price/unit_price.js"></script>
        <script rel="preload" src="/jof/js/common.js"></script>
        <script rel="preload" src="/jof/js/joeunfish.js"></script>

    </head>

    <body>
        <div class="button-group">
            <button id="check-unit-price" class="grid-toggle-btn" data-grid="unit-price-grid">단가확인</button>
            <button id="check-material" class="grid-toggle-btn" data-grid="material-grid">재료단가</button>
        </div>
        <div id="unit-price-grid" class="grid-container" style="display: block;"></div>
        <div id="material-grid" class="grid-container" style="display: none;"></div>

        <div id='loading' style='display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;'>
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <img src="/img/loading/0.gif" alt="로딩중">
            </div>
        </div>


    </body>

    <script>
        // DOM이 로드된 후 이벤트 리스너 추가
        document.addEventListener('DOMContentLoaded', function() {
            // 모든 단가 요소에 클릭 이벤트 추가
            document.querySelectorAll('.price-history-trigger').forEach(priceElement => {
                priceElement.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const prodCd = this.dataset.prodCd;
                    priceHistory.showHistory(prodCd);
                });
            });
        });
    </script>

<?php
    return ob_get_clean();
}
add_shortcode('unit_price', 'upc_shortcode');
