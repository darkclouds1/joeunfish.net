    <?php

    date_default_timezone_set('Asia/Seoul');

    $documentRoot = '/home/joeunfish.net/public_html';
    require_once $documentRoot . '/wp-load.php';
    require_once $documentRoot . '/classphp/dbConn_fish.php';
    require_once $documentRoot . '/classphp/ediRcvGs_class.php';

    $dbConn   = new dbconnClassFish;
    $platForm = $dbConn->MobileCheck();
    $conn     = $dbConn->conn();
    mysqli_set_charset($conn, 'utf8');
    mysqli_query($conn, "SET time_zone = '+09:00'");

    $logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";
    $folderPath = $documentRoot . '/ecommerce_java/recv';

    $files = glob($folderPath . '/*');
    $latestFile = '';
    $latestTime = 0;

    foreach ($files as $file) {
        $fileTime = filemtime($file);
        if ($fileTime > $latestTime) {
            $latestTime = $fileTime;
            $latestFile = $file;
        }
    }

    // 최신 파일 검색 후 작업
    $files = glob($folderPath . '/*');
    $latestFile = '';
    $latestTime = 0;
    foreach ($files as $file) {
        $fileTime = filemtime($file);
        if ($fileTime > $latestTime) {
            $latestTime = $fileTime;
            $latestFile = $file;
        }
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

    // 데이터 로깅
    // error_log(print_r($messages, true)); // 서버 로그에 데이터 기록

    // -------------------
    // 클래스 파싱
    // -------------------
    $parser   = new OrderParserClass($matches[1], $logFilePath);
    // error_log(print_r($parser, true), 3, $logFilePath);

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

    foreach ($parser->getOrders() as $order) {
        // error_log(print_r($parser->getOrders(), true), 3, $logFilePath);
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

        // error_log("\norder: " . print_r($orderData, true), 3, $logFilePath);

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tableOrders WHERE doc_no = %s", $order['msgId']));

        // 이미 주문번호가 있으면 기존 주문(Master-Detail 모두) 삭제 후 INSERT
        if ($exists > 0) {
            $wpdb->query($wpdb->prepare("DELETE FROM $tableOrderProducts WHERE doc_no = %s", $order['msgId']));
            $wpdb->query($wpdb->prepare("DELETE FROM $tableOrders WHERE doc_no = %s", $order['msgId']));
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

        // debug
        // error_log(print_r($orderData, true), 3, $logFilePath);

        foreach ($order['orderedProducts'] as $product) {
            $productData = [
                'doc_no' => $order['msgId'],
                'product_code' => $product->productCode,
                'product_name' => $product->productName,
                'quantity' => $product->quantity,
                'total_price' => $product->orderAmount,
                'unit_price' => $product->orderPrice,
                'create_id' => $user_name,
                'modify_id' => $user_name,
            ];

            // error_log("\nproduct: " . print_r($productData, true), 3, $logFilePath);

            error_log(print_r($productData, true), 3, $logFilePath);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tableOrderProducts WHERE doc_no = %s AND product_code = %s", $order['msgId'], $product->productCode));
            if ($exists > 0) {
                $wpdb->update($tableOrderProducts, $productData, ['doc_no' => $order['msgId'], 'product_code' => $product->productCode]);
                if ($wpdb->last_error) {
                    error_log(print_r($wpdb->last_error, true), 3, $logFilePath);
                }
            } else {
                $wpdb->insert($tableOrderProducts, $productData);
                if ($wpdb->last_error) {
                    error_log(print_r($wpdb->last_error, true), 3, $logFilePath);
                }
            }
        }
    }

    // 로그 파일 경로 확인 로그
    date_default_timezone_set('Asia/Seoul');
    $currentDateTime = date('Y-m-d H:i:s');
    error_log("Latest file: " . $latestFile . "\n", 3, $logFilePath);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['latestFile' => $latestFile]);
    }

    ?>

