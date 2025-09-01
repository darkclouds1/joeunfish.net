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

    $logFilePath = $documentRoot . "/logfile.log";
    // error_log("\nln18::logFilePath: " . $logFilePath, 3, $logFilePath);

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

    // error_log("\nln31::latestFile: " . $latestFile, 3, $logFilePath);
    // GS는 따로 전문데이터를 받지 않고, 이미 받은 최근 전문으로 작업함.
    // exec('/home/joeunfish.online/ecommerce_java/ecommerce.sh');

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

    // -------------------
    // 클래스 파싱
    // -------------------
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

    // error_log("\nln68::orders=" . print_r($parser->getOrders(), true), 3, $logFilePath);

    foreach ($parser->getOrders() as $order) {
        // error_log("\nln69::order=" . print_r($order, true), 3, $logFilePath);

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

        // error_log("\nln84::orderData=" . print_r($orderData, true), 3, $logFilePath);
        // if ($orderData['dest_code'] == '68970') { 
        //     error_log("\nln90::68970orderData=" . print_r($orderData, true), 3, $logFilePath);
        // }

        // if ($orderData['dest_code'] == '67370') { 
        //     error_log("\nln90::67370 orderData=" . print_r($orderData, true), 3, $logFilePath);
        // }

        // 기존 주문번호 존재하면 삭제하고 새로 추가 (단, msgtype이 ORDCHG인 경우는 삭제하지 않음)
        $orderExists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tableOrders WHERE doc_no = %s", $order['msgId']));
        if ($orderExists > 0) {
            // 기존 주문의 msgtype을 확인
            $existingMsgType = $wpdb->get_var($wpdb->prepare("SELECT msgtype FROM $tableOrders WHERE doc_no = %s", $order['msgId']));

            // 기존 msgtype이 'ORDCHG' 그대로
            if (
                $existingMsgType == 'ORDCHG' && $order['msgType'] == 'ORDERS'
            ) {
                continue; // 다음 주문으로 넘어감
            }

            $wpdb->query($wpdb->prepare(
                "DELETE FROM $tableOrders WHERE doc_no = %s",
                $order['msgId']
            ));
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

        // error_log(print_r("SQL=" . $wpdb->last_query, true), 3, $logFilePath);

        // ------------------------------------------------------------
        // der['orderedProducts']에 없는 제품 코드는 데이터베이스에 삭제
        // ------------------------------------------------------------
        $currentProductCodes = array_map(
            function ($product) {
                return $product->productCode;
            },
            $order['orderedProducts']
        );

        // DB에서 현재 주문에 대한 모든 제품 코드 가져오기
        $existingProductCodes = $wpdb->get_col($wpdb->prepare(
            "SELECT product_code FROM $tableOrderProducts WHERE doc_no = %s",
            $order['msgId']
        ));

        // (주문취소) DB에 있지만 현재 주문에 없는 제품 코드 삭제 
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

            // error_log(print_r($productData, true), 3, $logFilePath);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tableOrderProducts WHERE doc_no = %s AND product_code = %s", $order['msgId'], $product->productCode));

            // 이미 주문번호가 있으면 quantity_new, weights 값을 가져온 후 삭제하고 새로 추가할 때 사용
            if ($exists > 0) {
                $existingWeights = $wpdb->get_results($wpdb->prepare("SELECT expi_date, quantity_new, weights FROM $tableOrderProducts WHERE doc_no = %s AND product_code = %s", $order['msgId'], $product->productCode), ARRAY_A);
                if (!empty($existingWeights) && isset($existingWeights[0]['expi_date'])) {
                    $productData['expi_date'] = $existingWeights[0]['expi_date'];
                }
                if (!empty($existingWeights) && isset($existingWeights[0]['quantity_new'])) {
                    $productData['quantity_new'] = $existingWeights[0]['quantity_new'];
                }
                if (!empty($existingWeights) && isset($existingWeights[0]['weights'])) {
                    $productData['weights'] = $existingWeights[0]['weights'];
                }
            }

            $wpdb->query($wpdb->prepare("DELETE FROM $tableOrderProducts WHERE doc_no = %s AND product_code = %s", $order['msgId'], $product->productCode));
            $wpdb->insert($tableOrderProducts, $productData);
            if ($wpdb->last_error) {
                error_log(print_r($wpdb->last_error, true), 3, $logFilePath);
            } else {
                // error_log(print_r($wpdb->last_query, true), 3, $logFilePath);
            }
        }
    }

    // lastFile 만 남겨놓고 그 외 파일은 모두 삭제
    // foreach ($files as $file) {
    //     if ($file !== $latestFile) {
    //         unlink($file);
    //     }
    // }
    // unlink($latestFile);

    // 로그 파일 경로 확인 로그
    date_default_timezone_set('Asia/Seoul');
    $currentDateTime = date('Y-m-d H:i:s');
    error_log("Latest file: " . $latestFile . "\n", 3, $logFilePath);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['latestFile' => $latestFile]);
    }

    ?>