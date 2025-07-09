<?PHP
if (!class_exists('OrderParserClass')) {

    class OrderParserClass
    {
        private $logFilePath;
        public $orders = [];

        public function __construct($ediData, $logFilePath)
        {
            $this->logFilePath = $logFilePath;
            $this->parseEDI($ediData);
        }

        public function getOrders()
        {
            return $this->orders;
        }

        private function parseEDI($data)
        {
            $filteredMessages = array_filter($data, function ($msg) {

                // return preg_match('/^\s*MSGTYPE:(ORDERS|ORDCHG)/', $msg) && preg_match('/FROM:COST01:DC/', $msg);
                // GS리테일 & GS물류 발주서 메시지 추출 - GSSM01:DC, GSCT04:DC
                return preg_match('/^\s*MSGTYPE:(ORDERS|ORDCHG)/', $msg) && (preg_match('/FROM:GSSM01:DC/', $msg) || preg_match('/FROM:GSCT04:DC/', $msg));
            });

            // 필터링된 메시지들을 배열로 정리
            $messages = array_values(array_map('trim', $filteredMessages));

            // 각 메시지를 파싱해서 넣을 배열 선언
            $parsedData = [];
            foreach ($messages as $message) {
                $parsedData  = $this->parseData($message);
                $this->orders[] = $parsedData;
            }
        }

        // 주문서 1개를 파싱
        private function parseData($data)
        {
            // error_log("data: " . print_r($data, true), 3,  $this->logFilePath);
            $parsedData = new stdClass();

            $pattern = '/MSGTYPE:(\w+)\s*FROM:(\w+:\w+)\s*MSGID:([\w\d_]+)\s*(?=MSGFORM)/';
            preg_match($pattern, $data, $matches);

            if (!empty($matches[2])) {
                // 기본 정보 설정
                $parsedData->msgType = $matches[1];
                $parsedData->msgFrom = $matches[2];
                $parsedData->msgId = $matches[3];

                preg_match('/BODY:(.*?)L000001/', $data, $bodyMatches);
                if (!empty($bodyMatches)) {
                    // 빈공백으로 배열 만듬
                    $details = preg_split('/\s+/', trim($bodyMatches[1]));

                    $parsedData->orderDate = $details[2];          // 발주일자
                    // $parsedData->deliveryDate = $details[3];    // 납품일자 => GS인경우 상품데이터에서 추출
                    // $parsedData->orderer = $details[7];             // 주문자j

                    // GS리테일 정보 추출, GS물류(=%지에스리테일%) 정보 추출
                    if ($parsedData->msgFrom == "GSSM01:DC") {
                        $parsedData->deliveryName = "(주)지에스리테일";
                        // 배송지 정보 추출
                        $targetValues = [62970, 60470, 66970, 62980, 68970];
                        foreach ($targetValues as $target) {
                            $index = array_search($target, $details);
                            // 연속으로 deliveryCode가 나타나는 최초 array 를 찾아서 배송지 정보 추출
                            if ($index !== false && isset($details[$index + 2]) && $details[$index + 2] == $target) {
                                $parsedData->deliveryCode = $details[$index];
                                $parsedData->regionName =   $details[$index - 1];
                                break;
                            }
                        }
                    } elseif ($parsedData->msgFrom == "GSCT04:DC") {
                        $parsedData->deliveryName = "GS25물류센터";
                        $parsedData->deliveryCode = $details[5];
                        $parsedData->regionName =   $details[6];
                    }

                    // debug
                    // if ($parsedData->msgId == "12014_240482243") {
                    //     error_log("details: " . print_r($details, true), 3,  $this->logFilePath);
                    // }

                    // "L000001" 앞 5자리를 주문상품수로 추출
                    $productCountPos = strpos($data, 'L000001') - 5;
                    $orderProductCount = intval(substr($data, $productCountPos, 5));
                    $parsedData->orderProductCount = $orderProductCount;    // 주문 상품수
                }

                // 상품 데이터 시작점 찾기
                $productStartPos = strpos($data, 'L000001');
                $productData = substr($data, $productStartPos + 7);  // 'L0000011  '부터 문장 끝까지

                // 상품 데이터를 안전하게 분할하여 배열에 저장
                $productEntries = explode('L', $productData); // 'L'을 기준으로 데이터를 분할
                $orderedProducts = [];  // 주문 상품 배열

                foreach ($productEntries as $entry) {
                    if (trim($entry) != '') {
                        $entry = 'L' . $entry; // 분할된 각 항목 앞에 'L'을 다시 추가
                        if (strlen($entry) > 6) { // 최소한 'L000001' 다음 문자가 있어야 유효한 데이터
                            $orderedProducts[] = $entry;
                        }
                    }
                }

                // 주문 상품 파싱
                // error_log("주문 orderedProducts: " . print_r($orderedProducts, true), 3,  $this->logFilePath);

                foreach ($orderedProducts as $productData) {
                    $productObj = new stdClass();

                    // $parts = explode(" ", $productData);  // 상품 데이터를 스페이스로 분할
                    $parts = preg_split('/\s+/', trim($productData));

                    $productObj->productCode = $parts[1];  // 상품코드
                    // $singleProductCode = $parts[2];  // 단품코드

                    // 상품명 추출 로직
                    $productNameStarted = false;  // 상품명 추출 시작 플래그
                    $productName = "";  // 초기화
                    $remainingIndex = 0;  // 상품명이 끝난 다음 인덱스를 저장할 변수

                    foreach ($parts as $index => $part) {
                        if ($index > 2 && !$productNameStarted) {
                            $productNameStarted = true;  // 단품코드 다음부터 상품명 추출 시작
                        }
                        if ($productNameStarted && strpos($part, 'EA') !== false) {
                            $remainingIndex = $index;  // 'EA' 문자열과 일치하는 배열값 인덱스 앞 인덱스까지 상품명으로 취급
                            break;
                        }
                        if ($productNameStarted) {
                            $productName .= $part . " ";  // 상품명 구성
                        }
                    }
                    $productObj->productName = trim($productName);  // 상품명의 앞뒤 공백 제거

                    // 나머지 데이터 추출
                    $remainingDataString = substr($productData, strpos($productData, $parts[$remainingIndex]));
                    $remainingData = preg_split('/\s+/', trim($remainingDataString));

                    // 첫상품에서 배송일자 추출
                    if (!isset($parsedData->deliveryDate) && isset($remainingData[12])) {
                        $parsedData->deliveryDate = substr($remainingData[12], 0, 8);
                    }

                    // 수량 추출
                    $productObj->quantity = intval($remainingData[1]);

                    // 발주단가
                    $orderAmount =  substr($remainingData[5], 0, 18);
                    $productObj->orderAmount = intval($orderAmount);

                    // 발주금액
                    $orderPrice =  substr($remainingData[5], 18, 13);
                    $productObj->orderPrice = intval($orderPrice);

                    // 주문상품 배열에 추가
                    if (!isset($parsedData->orderedProducts)) {
                        $parsedData->orderedProducts = [];
                    }
                    $parsedData->orderedProducts[] = $productObj;
                }

                // 객체를 배열로 변환
                $parsedDataArray = (array)$parsedData;
                return $parsedDataArray;
            }
        }
    }
}
