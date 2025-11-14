<?php
    // session_start(); // 세션 시작을 맨 위로 이동
    date_default_timezone_set('Asia/Seoul');
    $logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

    $documentRoot = '/home/joeunfish.net/public_html';

    // require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
    require_once($documentRoot . '/wp-load.php');
    require_once($documentRoot . '/classphp/dbConn_fish.php');
    require_once($documentRoot . '/classphp/ediRcv_class.php');

    $dbConn   = new dbconnClassFish;
    $platForm = $dbConn->MobileCheck();

    // 최신 파일 검색 후 작업
    $folderPath = $documentRoot . '/ecommerce_java/recv';
    $files = glob($folderPath . '/*');
    $latestFile = '';
    $latestTime = 0;

    // 최신 파일 검색
    // foreach ($files as $file) {
    //     $fileTime = filemtime($file);
    //     if ($fileTime > $latestTime) {
    //         $latestTime = $fileTime;
    //         $latestFile = $file;
    //     }
    // }

    // 명령줄 인수 확인
    if (isset($argv[1])) {
        $filename = $argv[1];
        $latestFile = $folderPath . '/' . basename($filename);

        // 파일 존재 여부 확인
        if (!file_exists($latestFile)) {
            error_log("파일을 찾을 수 없습니다: " . $latestFile, 3, $logFilePath);
            die("파일을 찾을 수 없습니다.");
        }
        // die("파일이 확인 되었습니다");
    } else {
        error_log("파일명이 제공되지 않았습니다.", 3, $logFilePath);
        die("사용법: php " . basename(__FILE__) . " <파일명>");
    }

    $fileContents = file_get_contents($latestFile, false, stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'header' => "User-Agent: euc-kr\r\n",
        ],
    ]));

    $fileContents = mb_convert_encoding($fileContents, 'UTF-8', 'EUC-KR');
    $fileContents = preg_replace('/[\x00-\x1F\x7F]/', '', $fileContents);

    preg_match_all('/<<WEB-EDI>>(.*?)\.ENDMSG/s', $fileContents, $matches);

    // 클래스 파싱
    $parser   = new OrderParserClass($matches[1], $logFilePath);

    $user_id = get_current_user_id();
    if ($user_id != 0) {
        $user_info = get_userdata($user_id);
        $user_name = $user_info->user_login; // 로그인 이름
        $display_name = $user_info->display_name; // 표시 이름
    } else {
        $user_name = 'joeunfish';
    }

    global $wpdb;
    $tableOrders = 'torders';
    $tableOrderProducts = 'torderproducts';

    // 필터링을 위한 배열 선언
    $filteredOrders = array();

    // 원본 배열을 순서대로 처리 (뒤의 주문이 앞의 주문을 덮어쓰도록)
    foreach ($parser->getOrders() as $order) {
        // orderDate2 비교
        if (!isset($filteredOrders[$order['msgId']])) {
            // 처음 들어오는 주문
            $filteredOrders[$order['msgId']] = $order;
        } else {
            // 이미 존재하는 주문인 경우
            $existingDate = strtotime($filteredOrders[$order['msgId']]['orderDate2']);
            $newDate = strtotime($order['orderDate2']);

            if ($newDate > $existingDate) {
                // 새 주문의 날짜가 더 최신인 경우
                $filteredOrders[$order['msgId']] = $order;
            } else if ($newDate == $existingDate) {
                // 날짜가 같은 경우 나중에 나온 주문(현재 처리중인 주문)으로 업데이트
                $filteredOrders[$order['msgId']] = $order;
            }
            // 새 주문의 날짜가 더 과거인 경우는 무시
        }
    }

    foreach ($filteredOrders as $order) {

        $orderData = [
            'msgtype' => $order['msgType'],
            'msgfrom' => $order['msgFrom'],
            'doc_no' => $order['msgId'],
            'order_dt' => $order['orderDate'],
            'supply_dt' => $order['deliveryDate'],
            'dest_code' => $order['deliveryCode'],
            'dest_name' => $order['deliveryName'],
            'region' => $order['regionName'],
            'product_count' => $order['orderProductCount'],
            'create_id' => $user_name,
            'modify_id' => $user_name,
        ];

        // if ($order['msgId'] == '20248563472646') {
        //     error_log("order: " . print_r($orderData, true), 3, $logFilePath);
        // }

        // $parser의 기존 데이터 삭제 (중복 삭제)
        $parser->orders = []; // 기존 주문 데이터 삭제
        $parser->setOrders(array_values($filteredOrders)); // 필터링된 주문 데이터로 업데이트

        // 기존 주문번호 존재하면 삭제하고 새로 추가 (단, msgtype이 ORDCHG인 경우는 삭제하지 않음)
        // 주문번호 5번째 자리부터 끝까지 비교 (앞4자리 년도는 비교에서 제외 함.)
        $orderExists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tableOrders WHERE SUBSTRING(doc_no, 5) = %s", substr($order['msgId'], 4)));
        if ($orderExists > 0) {
            // 기존 주문의 msgtype을 확인
            $existingMsgType = $wpdb->get_var($wpdb->prepare("SELECT msgtype FROM $tableOrders WHERE SUBSTRING(doc_no, 5) = %s", substr($order['msgId'], 4)));

            // 기존 msgtype이 'ORDCHG'이고 새 msgtype이 'ORDERS'인 경우 스킵
            if ($existingMsgType == 'ORDCHG' && $order['msgType'] == 'ORDERS') {
                continue; // 다음 주문으로 넘어감
            }

            $wpdb->query($wpdb->prepare("DELETE FROM $tableOrders WHERE SUBSTRING(doc_no, 5) = %s", substr($order['msgId'], 4)));
            $wpdb->insert($tableOrders, $orderData);
            if ($wpdb->last_error) {
                error_log(print_r("dberror:" . $wpdb->last_error, true), 3, $logFilePath);
            }
        } else {
            $wpdb->insert($tableOrders, $orderData);
            if ($wpdb->last_error) {
                error_log(print_r("dberror:" . $wpdb->last_error, true), 3, $logFilePath);
            }
        }
        // error_log(print_r($orderData, true), 3, $logFilePath);

        // ------------------------------------------------------------
        // der['orderedProducts']에 없는 제품 코드는 데이터베이스에 삭제
        // ------------------------------------------------------------
        $currentProductCodes = array_map(function ($product) {
            return $product->productCode;
        }, $order['orderedProducts']);

        // DB에서 현재 주문에 대한 모든 제품 코드 가져오기
        $existingProductCodes = $wpdb->get_col($wpdb->prepare(
            "SELECT product_code FROM $tableOrderProducts WHERE doc_no = %s",
            $order['msgId']
        ));

        // DB에 있지만 현재 주문에 없는 제품 코드 삭제
        foreach ($existingProductCodes as $existingCode) {
            if (!in_array($existingCode, $currentProductCodes)) {
                // 데이터베이스에서 해당 제품 코드 삭제
                $wpdb->delete($tableOrderProducts, [
                    'doc_no' => $order['msgId'],
                    'product_code' => $existingCode
                ]);
            }
        }


        foreach ($order['orderedProducts'] as $product) {

            $productData = [
                'doc_no' => $order['msgId'],
                'product_name' => $product->productName,
                'product_code' => $product->productCode,
                'quantity' => $product->quantity,
                'unit_price' => $product->orderPrice,
                'total_price' => $product->orderAmount,
                'create_id' => $user_name,
                'modify_id' => $user_name,
            ];

            // if ($order['msgId'] == '20248563472646') {
            //     error_log(print_r($productData, true), 3, $logFilePath);
            // }

            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tableOrderProducts WHERE doc_no = %s AND product_code = %s", $order['msgId'], $product->productCode));

                        // ========== [수량 변경 체크 시작] ==========
            // 수량 변경 여부 초기값
            $quantityModified = 0;

            // 이미 주문번호가 있으면 기존 데이터 값을 가져온 후 삭제하고 새로 추가할 때 사용
            if ($exists > 0) {
                // quantity 컬럼 추가
                $existingData = $wpdb->get_results($wpdb->prepare("SELECT expi_date, quantity_new, weights, quantity FROM $tableOrderProducts WHERE doc_no = %s AND product_code = %s", $order['msgId'], $product->productCode), ARRAY_A);
                
                if (!empty($existingData)) {
                    // 기존 값 보존
                    if (isset($existingData[0]['expi_date'])) {
                        $productData['expi_date'] = $existingData[0]['expi_date'];
                    }
                    if (isset($existingData[0]['quantity_new'])) {
                        $productData['quantity_new'] = $existingData[0]['quantity_new'];
                    }
                    if (isset($existingData[0]['weights'])) {
                        $productData['weights'] = $existingData[0]['weights'];
                    }
                    
                    // 수량 변경 체크 (기존 quantity와 신규 quantity 비교)
                    if (isset($existingData[0]['quantity'])) {
                        $oldQuantity = intval($existingData[0]['quantity']);
                        $newQuantity = intval($product->quantity);
                        
                        if ($oldQuantity != $newQuantity) {
                            $quantityModified = 1;
                        }
                    }
                }
            }

            // quantity_modified 필드 추가
            $productData['quantity_modified'] = $quantityModified;
            // ========== [수량 변경 체크 끝] ==========

            $wpdb->query($wpdb->prepare("DELETE FROM $tableOrderProducts WHERE doc_no = %s AND product_code = %s", $order['msgId'], $product->productCode));
            $wpdb->insert($tableOrderProducts, $productData);
            if ($wpdb->last_error) {
                error_log(print_r($wpdb->last_error, true), 3, $logFilePath);
            }
        }
    }

    // 로그 파일 경로 확인 로그
    date_default_timezone_set('Asia/Seoul');
    $currentDateTime = date('Y-m-d H:i:s');
    error_log("Latest file: " . $latestFile . "\n", 3, $logFilePath);

    // if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    //     echo json_encode(['latestFile' => $latestFile]);
    // }

    ?>