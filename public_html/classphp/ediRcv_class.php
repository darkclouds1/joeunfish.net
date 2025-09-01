<?PHP
if (!class_exists('OrderParserClass')) {
    class OrderParserClass
    {
        private $logFilePath; 

        public function setOrders($orders) {
            $this->orders = $orders; // orders 속성 업데이트
        }

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
                // 코스트코 발주서 메시지만 추출
                return preg_match('/^\s*MSGTYPE:(ORDERS|ORDCHG)/', $msg) && preg_match('/FROM:COST01:DC/', $msg);
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
            $parsedData = new stdClass();
            $pattern = '/MSGTYPE:(\w{6})\s*FROM:(\w+:\w{2})\s*MSGID:(\d{14})\s*.*?L000001/s';
            preg_match($pattern, $data, $matches);
            if (!empty($matches)) {
                // 기본 정보 설정
                $parsedData->msgType = $matches[1];
                $parsedData->msgFrom = $matches[2];
                $parsedData->msgId = $matches[3];
                // 발주일자, 납품일자 등 추가 정보 추출
                preg_match('/BODY:(.*?)L000001/', $data, $bodyMatches);
                if (!empty($bodyMatches)) {
                    // 빈공백으로 배열 만듬
                    $details = preg_split('/\s+/', trim($bodyMatches[1]));
                    $parsedData->orderDate = $details[2];       // 발주일자
                    $parsedData->deliveryDate = $details[3];    // 납품일자
                    $parsedData->orderDate2 = $details[4];      // 발주일자2(발주변경 시 최종데이터)
                    $parsedData->orderer = $details[7];         // 주문자
                    // 납품처명이 "코스트코홀세일"인 경우의 처리 로직
                    $costcoIndex = array_search("코스트코홀세일", $details);
                    if ($costcoIndex !== false) {
                        $parsedData->deliveryName = "코스트코홀세일";
                        $parsedData->deliveryCode = $details[$costcoIndex - 1];
                        if ($parsedData->deliveryCode == '059') {
                            $parsedData->regionName = '대구점2';
                        } else {
                            $parsedData->regionName = $details[$costcoIndex + 1];
                        }
                    }
                    // "L000001" 앞 5자리를 주문상품수로 추출
                    $productCountPos = strpos($data, 'L000001') - 5;
                    $orderProductCount = intval(substr($data, $productCountPos, 5));
                    $parsedData->orderProductCount = $orderProductCount;    // 주문 상품수
                }
                // 상품 데이터 시작점 찾기
                $productStartPos = strpos($data, 'L000001');
                $productData = substr($data, $productStartPos + 10);  // 'L0000011  '부터 문장 끝까지
                // 상품 데이터를 안전하게 분할하여 배열에 저장
                $productEntries = explode('L00', $productData); // 'L'을 기준으로 데이터를 분할


                $orderedProducts = [];  // 주문 상품 배열
                foreach ($productEntries as $entry) {
                    if (trim($entry) != '') {
                        $entry = 'L00' . $entry; // 분할된 각 항목 앞에 'L'을 다시 추가
                        if (strlen($entry) > 7) { // 최소한 'L000001' 다음 문자가 있어야 유효한 데이터
                            $orderedProducts[] = $entry;
                        }
                    }
                }
                // 첫번재열에 "L0000011  " 추가 
                if (!empty($orderedProducts)) {
                    $orderedProducts[0] = str_replace("L00", "L0000011  ", $orderedProducts[0]);
                }

                // 주문 상품 파싱
                // error_log("주문 orderedProducts: " . print_r($orderedProducts, true), 3,  $this->logFilePath);

                foreach ($orderedProducts as $productData) {
                    $productObj = new stdClass();
                    // $parts = explode(" ", $productData);  // 상품 데이터를 스페이스로 분할
                    $parts = preg_split('/\s+/', trim($productData));

                    // debug
                    // error_log("ln95::productData: " . print_r($productData, true), 3, $this->logFilePath);
                    // error_log("ln96::parts: " . print_r($parts, true), 3, $this->logFilePath);

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
                        if ($productNameStarted && preg_match('/^\d{15}EA$/', $part)) {
                            $remainingIndex = $index + 1;  // 상품명 종료 후 나머지 데이터 시작 인덱스
                            break;  // "~EA"로 끝나는 18자리 단어를 만나면 상품명 추출 종료
                        }
                        if ($productNameStarted) {
                            $productName .= $part . " ";  // 상품명 구성
                        }
                    }
                    $productObj->productName = trim($productName);  // 상품명의 앞뒤 공백 제거
                    // 나머지 데이터 추출
                    $remainingDataString = substr($productData, strpos($productData, $parts[$remainingIndex]) - 17);
                    $remainingData = preg_split('/\s+/', trim($remainingDataString));

                    // error_log("ln119::remainingData: " . print_r($remainingData, true), 3, $this->logFilePath);

                    // 수량 추출
                    if (preg_match('/(\d+)EA/', $remainingData[0], $matches)) {
                        $productObj->quantity = intval($matches[1]);  // 'EA' 앞의 숫자를 정수로 변환
                    } else {
                        $productObj->quantity = 0;  // 일치하는 패턴이 없으면, 0으로 설정
                        // error_log("remainingDataString: " . print_r($remainingDataString, true), 3, $this->logFilePath);
                        // error_log("remainingData: " . print_r($remainingData, true), 3, $this->logFilePath);
                    }

                    // debug -- 꽃게
                    // if ($parsedData->msgId == '2000000561024') {
                    //     error_log("remainingDataString: " . print_r($remainingDataString, true), 3, $this->logFilePath);
                    //     error_log("remainingData: " . print_r($remainingData, true), 3, $this->logFilePath);
                    // }

                    // 발주단가
                    $orderAmount =  substr($remainingData[4], 0, 18);
                    $productObj->orderAmount = intval($orderAmount);
                    // 발주금액
                    $orderPrice =  substr($remainingData[4], 18, 13);
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
