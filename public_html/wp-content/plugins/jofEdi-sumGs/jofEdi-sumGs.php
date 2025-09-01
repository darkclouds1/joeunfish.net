<?php
/*
Plugin Name: (GS)주문 요약 그리드
Description: 납품점, 주문수량, 제품명, 제품코드를 그리드로 표시합니다.
Version: 1.0
Author: Your Name
*/

function orderGs_summary_shortcode()
{
    $api_url = admin_url('admin-ajax.php?action=orderGs_summary&category=orderSumGs');
    ob_start();
?>
    <script>
        function updateOrderDate() {
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            var days = ['일', '월', '화', '수', '목', '금', '토'];
            var formattedDate = tomorrow.getFullYear() + '년 ' +
                (tomorrow.getMonth() + 1) + '월 ' +
                tomorrow.getDate() + '일 ' +
                days[tomorrow.getDay()] + '요일';
            // order-date-picker에 내일 날짜 설정
            var datePicker = document.getElementById('orderGs-date-picker');
            datePicker.value = tomorrow.toISOString().split('T')[0];
        }
        document.addEventListener('DOMContentLoaded', updateOrderDate);
    </script>

    <button id="show-orderGs-summary" class="gs-buttons" style="margin-left: 30px; width: 100px; height: 40px; font-size: 15px;">(GS)주문</button>
    납품일<input type="date" id="orderGs-date-picker" style="height: 40px; margin-left: 5px; font-size: 15px;">
    <div id="orderGs-summary-container" style="display: none;">
        <div id="orderGs-summary-grid" class="ag-theme-alpine" style="height: 400px;"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
    <link rel="stylesheet" type="text/css" href="/jof/css/jofMain.css" />

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ln43::[orderGs_summary] DOMContentLoaded 이벤트 발생');
            const button = document.getElementById('show-orderGs-summary');
            const container = document.getElementById('orderGs-summary-container');
            const gridDiv = document.getElementById('orderGs-summary-grid');
            const datePicker = document.getElementById('orderGs-date-picker');

            let gridApi = null;

            if (button) {
                button.addEventListener('click', function() {
                    console.log('버튼 클릭됨');
                    if (container.style.display === 'none') {
                        container.style.display = 'block';
                        if (!gridApi) {
                            console.log('그리드 초기화 시작');
                            initializeGrid();
                        } else {
                            console.log('그리드 데이터 새로고침');
                            refreshGridData();
                        }
                    } else {
                        container.style.display = 'none';
                    }
                });
            } else {
                console.error('버튼 요소를 찾을 수 없음');
            }

            // button.click();

            function initializeGrid() {

                const selectedDate = datePicker.value;
                const fullApiUrl = '<?php echo $api_url; ?>&date=' + selectedDate;

                fetch(fullApiUrl)
                    .then(response => response.json())
                    .then(data => {
                        // console.log('받은 데이터:', data);

                        if (!data.response || !data.response.body || !Array.isArray(data.response.body.items)) {
                            console.error('예상치 못한 데이터 구조:', data);
                            return;
                        }

                        const rowData = data.response.body.items;

                        // console.log('처리된 rowData:', rowData);

                        const columnDefs = [{
                                headerName: "납품점 ",
                                field: "납품점",
                                sortable: true,
                                filter: true,
                                minWidth: 140,
                                cellStyle: {
                                    textAlign: 'left'
                                }
                            },
                            {
                                headerName: "주문수량",
                                field: "주문수량",
                                sortable: true,
                                filter: true,
                                minWidth: 80,
                                cellStyle: {
                                    color: 'red',
                                    textAlign: 'right'
                                }
                            },
                            {
                                headerName: "제품명",
                                field: "제품명",
                                sortable: true,
                                filter: true,
                                minWidth: 150,
                                cellStyle: {
                                    textAlign: 'left'
                                }
                            },
                            {
                                headerName: "제품코드",
                                field: "제품코드",
                                sortable: true,
                                filter: true,
                                minWidth: 100,
                                cellStyle: {
                                    textAlign: 'center'
                                }
                            },
                        ];

                        const gridOptions = {
                            columnDefs: columnDefs,
                            rowData: rowData,
                            defaultColDef: {
                                resizable: true
                            },
                            onGridReady: function(params) {
                                gridApi = params.api;
                                // 그리드가 준비되면 sizeColumnsToFit 호출
                                setTimeout(() => {
                                    adjustGridSize();
                                }, 0);
                            },
                            suppressScrollOnNewData: true,
                            domLayout: 'autoHeight',
                            theme: 'legacy'
                        };

                        gridApi = agGrid.createGrid(gridDiv, gridOptions);
                        console.log('그리드 생성 완료');
                    })
                    .catch(error => {
                        console.error('데이터 로드 중 오류 발생:', error);
                    });
            }

            function refreshGridData() {
                // API URL에 선택된 날짜 추가
                const selectedDate = datePicker.value;
                const fullApiUrl = '<?php echo $api_url; ?>&date=' + selectedDate;

                fetch(fullApiUrl)
                    .then(response => response.json())
                    .then(data => {
                        // console.log('새로운 데이터:', data);
                        if (!data.response || !data.response.body || !Array.isArray(data.response.body.items)) {
                            console.error('예상치 못한 데이터 구조:', data);
                            return;
                        }
                        const rowData = data.response.body.items;
                        gridApi.setGridOption('rowData', rowData);
                        // 데이터 갱신 후 그리드 크기 재조정
                        setTimeout(() => {
                            adjustGridSize();
                        }, 0);
                    })
                    .catch(error => {
                        console.error('데이터 새로고침 중 오류 발생:', error);
                    });
            }

            // 날짜 선택기 변경 이벤트 리스너 추가
            datePicker.addEventListener('change', function() {
                if (gridApi) {
                    refreshGridData();
                } else {
                    initializeGrid();
                }
            });

            function adjustGridSize() {
                // gridApi가 없으면 실행하지 않음
                if (!gridApi || !gridDiv) return;

                try {
                    const rowCount = gridApi.getDisplayedRowCount();
                    const gridHeight = Math.max(rowCount * 25 + 50, 400); // 최소 높이 400px 보장
                    gridDiv.style.height = `${gridHeight}px`;

                    // 모바일 여부 확인
                    const isMobileView = window.innerWidth <= 768;

                    // 그리드 옵션 설정
                    gridApi.setGridOption('domLayout', 'autoHeight');
                    gridApi.setGridOption('suppressHorizontalScroll', !isMobileView);

                    // 컬럼 크기 조정
                    setTimeout(() => {
                        gridApi.sizeColumnsToFit();
                    }, 100);
                } catch (error) {
                    console.error('그리드 크기 조정 중 오류:', error);
                }
            }

            // 화면 크기 변경 시 그리드 크기 조정
            window.addEventListener('resize', adjustGridSize);
            // container.style.display = 'block';
            // initializeGrid();
        });
    </script>
<?php
    return ob_get_clean();
}
// 숏코드 등록
add_shortcode('orderGs_summary', 'orderGs_summary_shortcode');

function orderGs_summary()
{
    $category = $_GET['category'];
    $date = $_GET['date'];

    // WordPress 설치 디렉토리의 상대 경로 사용
    $api_url = "/wp-content/plugins/jofEdi/ediAgis_srv.php";
    $api_url = add_query_arg(array(
        'category' => $category,
        'date' => $date
    ), $api_url);

    // 전체 URL 구성
    $full_api_url = home_url($api_url);

    error_log('API URL: ' . $full_api_url);

    $args = array(
        'sslverify' => false
    );
    $response = wp_remote_get($full_api_url, $args);

    if (is_wp_error($response)) {
        error_log('API 요청 실패: ' . $response->get_error_message());
        wp_send_json_error('API 요청 실패: ' . $response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        error_log('API 응답 코드 오류: ' . $response_code);
        wp_send_json_error('API 응답 코드 오류: ' . $response_code);
        return;
    }

    $body = wp_remote_retrieve_body($response);
    error_log('API 응답: ' . $body);


    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON 디코딩 실패: ' . json_last_error_msg());
        wp_send_json_error('데이터 처리 실패');
    }

    wp_send_json($data);
}
add_action('wp_ajax_orderGs_summary', 'orderGs_summary');
add_action('wp_ajax_nopriv_orderGs_summary', 'orderGs_summary');
