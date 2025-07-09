<?php

/**
 * Plugin Name: AGIS
 * Plugin URI: https://joeunfish.online
 * Description: AGIS 주문관리
 * Version: 1.0.0
 * Author: 정순장
 * Author URI: https://uloca.net
 * License: GPL2
 */

// Your plugin code goes here
function ediOrder()
{

?>
    <!DOCTYPE html>
    <html lang="ko">

    <head>
        <meta charset="UTF-8">
        <title>주문 정보</title>
        <style>
            #ordersContainer {
                font-family: Arial, sans-serif;
            }

            .order-item {
                margin-bottom: 10px;
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }

            .product-list {
                margin-top: 5px;
                background-color: #f9f9f9;
                border: 1px solid #e1e1e1;
                padding: 5px;
                border-radius: 5px;
            }

            .product-item {
                margin: 2px 0;
            }
        </style>
    </head>

    <body>
        <h1>주문 정보</h1>
        <div id="summary"></div>
        <div id="ordersContainer"></div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                fetch('/wp-content/plugins/jofEdi/ediRcv_srv.php')
                    .then(response => response.json())
                    .then(data => {
                        const container = document.getElementById('ordersContainer');
                        const summary = document.getElementById('summary');
                        if (!container || !summary) {
                            console.error('No container or summary element found');
                            return;
                        }
                        // 타입별 갯수
                        const orderTypeCounts = {};

                        // 결과를 화면에 표시
                        if (data && Array.isArray(data.orders)) {
                            data.orders.forEach(order => {
                                if (order && order.msgType) {

                                    if (orderTypeCounts[order.msgType]) {
                                        orderTypeCounts[order.msgType]++;
                                    } else {
                                        orderTypeCounts[order.msgType] = 1;
                                    }

                                    const orderItem = document.createElement('div');
                                    orderItem.className = 'order-item';
                                    orderItem.innerHTML = `<strong>주문 타입: ${order.msgType}</strong>, 발신자: ${order.from}, 메시지 ID: ${order.msgId}<br>
                                                <strong>발주일자:</strong> ${order.orderDate}, <strong>납품일자:</strong> ${order.deliveryDate}, 
                                                <strong>납품처코드:</strong> ${order.deliveryCode}, <strong>납품처명:</strong> ${order.deliveryName}, <strong>지역명:</strong> ${order.regionName}`;


                                    const productList = document.createElement('div');
                                    productList.className = 'product-list';
                                    order.orderedProducts.forEach(product => {
                                        const productItem = document.createElement('div');
                                        productItem.className = 'product-item';
                                        productItem.textContent = `상품 코드: ${product.productCode}, 상품명: ${product.productName}, 수량: ${product.quantity}, 단가: ${product.orderPrice}, 총액: ${product.orderAmount},`;
                                        productList.appendChild(productItem);
                                    });

                                    orderItem.appendChild(productList);
                                    container.appendChild(orderItem);
                                } else {
                                    console.error('Invalid order object:', order);
                                }
                            });

                            summary.innerHTML = '<h2>주문 타입별 갯수</h2>';
                            for (const [type, count] of Object.entries(orderTypeCounts)) {
                                const typeInfo = document.createElement('p');
                                typeInfo.textContent = `${type}: ${count}개`;
                                summary.appendChild(typeInfo);
                            }

                            // 요약 정보를 컨테이너 상단에 추가
                            container.prepend(summary);

                        } else {
                            container.textContent = '주문 정보가 없습니다.';
                            console.log(data);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading the orders:', error);
                    });
            });
        </script>
    </body>

    </html>

<?php
    return ob_get_clean();
}
add_shortcode('edi_order', 'ediOrder');
