<?php
/*
Plugin Name: Order Display Plugin
Description: Display order data based on store and delivery date selection
Version: 1.0
Author: darkcloud
*/

// Enqueue necessary scripts and styles
function odp_enqueue_scripts()
{
    wp_enqueue_script('jquery');
    // wp_enqueue_script('odp-script', plugin_dir_url(__FILE__) . 'odp-script.js', array('jquery'), '1.0', true);
    wp_enqueue_style('odp-style', plugin_dir_url(__FILE__) . 'odp-style.css');

    // CSS 스타일 추가
    $custom_css = "
        .order-table tr:hover {
            background-color: #bbf731;
            cursor: pointer;
        }
        .order-table tr.selected-row {
            background-color: #bbf731;
        }
        .mobile-orders .order-header.selected {
            background-color: #bbf731 !important;
        }
    ";
    wp_add_inline_style('odp-style', $custom_css);

    wp_localize_script('jquery', 'odp_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'odp_enqueue_scripts');

// Create shortcode for the form
function odp_form_shortcode()
{
    ob_start();
?>
    <form id="odp-form">
        <div class="odp-form-row">
            <select class="form-field barcode-scan" placeholder="점포#선택" id="storeNo" name="store_code" style="font-size: 13px; width: 120px; margin-right: 1px; padding: 3px;">
                <option value="">점포#선택</option>
            </select>
            <input type="date" id="supplyDate" name="supply_dt" style="font-size: 13px; width: 120px; margin-right: 3px; padding: 3px;">
            <button type="submit" style="width: 60px; font-size: 13px;">조회</button>
        </div>
    </form>
    <div id="odp-results"></div>

    <script>
        jQuery(document).ready(function($) {
            // 화면의 너비가 760px 이하인 경우 모바일로 간주
            if (window.matchMedia("only screen and (max-width: 760px)").matches) {
                platForm = 'mobile';
                if(document.getElementById('excelDownBtn')) document.getElementById('excelDownBtn').style.display = 'none';
                if(document.getElementById('dejonExcelBtn')) document.getElementById('dejonExcelBtn').style.display = 'none';
            } else {
                platForm = 'computer';
            }
            console.log('platForm=' + platForm);

            // 납기일자 +1day (input이 존재할 때만)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const formattedTomorrow =
                tomorrow.getFullYear() +
                "-" +
                ("0" + (tomorrow.getMonth() + 1)).slice(-2) +
                "-" +
                ("0" + tomorrow.getDate()).slice(-2);
            if ($('#supplyDate').length) {
                $('#supplyDate').val(formattedTomorrow);
            }

            // 점포#선택
            loadStoreOptions();

            function loadStoreOptions() {
                console.log('loadStoreOptions');
                $.ajax({
                    url: odp_ajax.ajax_url,
                    type: "GET",
                    dataType: "json",
                    data: {
                        action: "odp_get_store_options",
                        category: "your_category",
                        increate_dt: "your_increate_dt",
                    },
                    success: function(data) {
                        var selectElement = $("#storeNo");
                        selectElement.empty();
                        selectElement.append(
                            $("<option>", {
                                value: "",
                                text: "점포#선택",
                            })
                        );

                        if (Array.isArray(data) && data.length > 0) {
                            data.forEach(function(item) {
                                selectElement.append(
                                    $("<option>", {
                                        value: item.store_code,
                                        text: item.store_nm + " (" + item.store_code + ")",
                                    })
                                );
                            });
                            // 첫 번째 점포 자동 선택
                            // selectElement.val(data[0].store_code);
                            // 자동으로 폼 submit (납기일자도 이미 셋팅되어 있음)
                            // $("#odp-form").submit();
                        } else {
                            console.log('점포 데이터 없음', data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("An error occurred: " + error);
                    },
                });
            }

            // AJAX 요청 부분 수정
            $("#odp-form").on("submit", function(e) {
                e.preventDefault();
                var storeCode = $("#storeNo").val();
                var supplyDate = $("#supplyDate").val();
                supplyDate = supplyDate.replace(/-/g, '');

                // console.log("ln89::storeCode=" + storeCode + ", supplyDate=" + supplyDate);

                if (storeCode && supplyDate) {
                    $.ajax({
                        url: '<?php echo plugin_dir_url(__FILE__) . 'get_order_data_srv.php'; ?>',
                        type: "POST",
                        data: {
                            store_code: storeCode,
                            supply_dt: supplyDate,
                        },
                        success: function(response) {
                            displayOrderData(response);
                        },
                        error: function(xhr, status, error) {
                            console.error("An error occurred: " + error);
                            $("#odp-results").html(
                                "<p>데이터를 불러오는 중 오류가 발생했습니다.</p>"
                            );
                        },
                    });
                } else {
                    $("#odp-results").html("<p>점포와 납기일을 모두 선택해주세요.</p>");
                }
            });

            // 점포 선택 시 자동 조회
            $("#storeNo").on("change", function() {
                $("#odp-form").submit();
            });

            function displayOrderData(data) {
                var resultsDiv = $("#odp-results");
                resultsDiv.empty();

                if (Array.isArray(data) && data.length > 0) {
                    if (platForm === 'mobile') {
                        // 모바일용 표시 형식
                        var currentDocNo = '';
                        var orderContainer = $('<div>').addClass('mobile-orders');

                        data.forEach(function(item) {
                            if (currentDocNo !== item.doc_no) {
                                currentDocNo = item.doc_no;
                                var orderHeader = $('<div>')
                                    .addClass('order-header')
                                    .attr('data-doc-no', item.doc_no)
                                    .css({
                                        'background-color': '#f0f0f0',
                                        'padding': '10px',
                                        'margin-bottom': '5px',
                                        'border-radius': '5px',
                                        'cursor': 'pointer'
                                    })
                                    .html(`<strong>주문번호: ${item.doc_no}</strong><br>`);

                                orderContainer.append(orderHeader);
                            }

                            // 주문 상품 정보
                            var orderItem = $('<div>')
                                .addClass('order-item')
                                .attr('data-doc-no', item.doc_no)
                                .css({
                                    'padding': '10px',
                                    'margin-bottom': '10px',
                                    'border-bottom': '1px solid #ddd',
                                    'cursor': 'pointer'
                                })
                                .html(
                                    `<div style="margin-bottom:5px">${item.product_name}</div>` +
                                    `<div style="color:#666">상품코드: ${item.product_code}</div>` +
                                    `<div style="display:flex;justify-content:space-between;margin-top:5px">` +
                                    `<span>수량: ${item.quantity}</span>` +
                                    `<span>단가: ${item.unit_price}원</span>` +
                                    `</div>` +
                                    `<div style="text-align:right;color:#e91e63;margin-top:5px">` +
                                    `<strong>총액: ${item.total_price}원</strong>` +
                                    `</div>`
                                );

                            // 클릭 이벤트 추가
                            orderItem.on('click', function() {
                                var docNo = $(this).attr('data-doc-no');
                                $('.order-header').removeClass('selected');
                                $('.order-header[data-doc-no="' + docNo + '"]').addClass('selected');
                            });

                            orderContainer.append(orderItem);
                        });

                        // 주문번호 헤더 클릭 이벤트
                        orderContainer.on('click', '.order-header', function() {
                            $('.order-header').removeClass('selected');
                            $(this).addClass('selected');
                        });

                        resultsDiv.append(orderContainer);
                    } else {
                        var table = $("<table>").addClass("order-table").css("font-size", "13px");
                        var header = $("<tr style='background-color: #d3d3d3; height: 30px;'> ").append(
                            $('<th>').text('주문 번호').css('text-align', 'center'),
                            $('<th>').text('지역').css('text-align', 'center'),
                            $('<th>').text('납기일자').css('text-align', 'center'),
                            $('<th>').text('상품코드').css('text-align', 'center'),
                            $('<th>').text('상품명').css('text-align', 'center'),
                            $('<th>').text('수량').css('text-align', 'center'),
                            $('<th>').text('단가').css('text-align', 'center'),
                            $('<th>').text('총 가격').css('text-align', 'center')
                        );
                        table.append(header);
                        data.forEach(function(item) {
                            var row = $("<tr style='height: 30px;'>").append(
                                $('<td>').text(item.doc_no).css('text-align', 'center'),
                                $('<td>').text(item.region).css('text-align', 'center'),
                                $('<td>').text(item.supply_dt).css('text-align', 'center'),
                                $('<td>').text(item.product_code).css('text-align', 'center'),
                                $('<td>').text(item.product_name),
                                $('<td>').text(item.quantity).css('text-align', 'right'),
                                $('<td>').text(item.unit_price).css('text-align', 'right'),
                                $('<td>').text(item.total_price).css('text-align', 'right')
                            );
                            table.append(row);
                        });

                        // 테이블 생성 후 클릭 이벤트 추가
                        table.find('tr').not(':first').on('click', function() {
                            // 모든 행에서 선택된 클래스 제거
                            table.find('tr').removeClass('selected-row');
                            // 클릭된 행에 선택된 클래스 추가
                            $(this).addClass('selected-row');
                        });

                        resultsDiv.append(table);
                    }
                } else {
                    resultsDiv.text("데이터가 없거나 올바르지 않은 형식입니다.");
                }

            }
        });
    </script>

<?php
    return ob_get_clean();
}
add_shortcode('odp_form', 'odp_form_shortcode');

// AJAX handler for store options
function odp_get_store_options()
{
    global $wpdb;

    $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $increate_dt = isset($_GET['increate_dt']) ? sanitize_text_field($_GET['increate_dt']) : '';

    // Perform your database query here to get store options
    $results = $wpdb->get_results($wpdb->prepare(
        " SELECT store_code,
                    RIGHT(code_exp, 3) AS store_nm
                FROM `tcomcode`
                WHERE grp_code = '1'
                AND use_yn = 'y'
                AND store_code NOT IN ('000','001', '002', '003', '004', '005')
                GROUP BY store_code, store_nm
                ORDER BY order_num ASC
        ",
        $category,
        $increate_dt
    ));

    wp_send_json($results);
}
add_action('wp_ajax_odp_get_store_options', 'odp_get_store_options');
