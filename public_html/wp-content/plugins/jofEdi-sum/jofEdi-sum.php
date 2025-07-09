<?php
/*
Plugin Name: (CO)주문 요약 그리드
Description: 제품별 서울소계, 지방소계, 총합계를 그리드로 표시합니다.
Version: 1.1
Author: Your Name
*/

function order_summary_shortcode()
{
    $api_url = admin_url('admin-ajax.php?action=order_summary&category=order');
    $modify_dt_url = admin_url('admin-ajax.php?action=order_summary&category=modify_dt');
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
            var datePicker = document.getElementById('order-date-picker');
            datePicker.value = tomorrow.toISOString().split('T')[0];
        }
        document.addEventListener('DOMContentLoaded', updateOrderDate);

        // 최종 수정파일 표시
        function updateModifyDt() {
            fetch('<?php echo $modify_dt_url; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        var modifyDtElement = document.getElementById('modify-dt');
                        modifyDtElement.textContent = '최종 수정파일: ' + data.modify_dt;
                    } else {
                        console.error('최종 수정파일 가져오기 실패:', data.message);
                    }
                })
                .catch(error => {
                    console.error('최종 수정파일 로드 중 오류 발생:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('ln47::[order_summary] DOMContentLoaded 이벤트 발생');
            updateOrderDate();
            updateModifyDt();

            // fetch('<?php echo $api_url; ?>')
            //     .then(response => response.json())
            //     .then(data => {
            //         // console.log('받은 데이터:', data);
            //         // 여기에 데이터를 처리하는 추가 로직을 넣을 수 있습니다.
            //     })
            //     .catch(error => {
            //         console.error('데이터 로드 중 오류 발생:', error);
            //     });
        });
    </script>

    <div><span id="modify-dt" style="margin-left: 40px; font-size: 14px;"></span></div>
    <button id="show-order-summary" class="co-buttons" style="margin-left: 30px; width: 100px; height: 40px; font-size: 15px;">(CO)주문</button>
    납품일<input type="date" id="order-date-picker" style="height: 40px; margin-left: 5px; font-size: 15px;">

    <div id="order-summary-container" style="display: none;">
        <div id="order-summary-grid" class="ag-theme-alpine" style="height: 400px;"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
    <link rel="stylesheet" type="text/css" href="/jof/css/jofMain.css" />

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded 이벤트 발생');
            const button = document.getElementById('show-order-summary');
            const container = document.getElementById('order-summary-container');
            const gridDiv = document.getElementById('order-summary-grid');
            const datePicker = document.getElementById('order-date-picker');

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
            }

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
                                headerName: "제품명",
                                field: "product_name",
                                sortable: true,
                                filter: true,
                                minWidth: 180,
                                cellStyle: {
                                    textAlign: 'left'
                                }
                            },
                            {
                                headerName: "총합계",
                                field: "총합계",
                                sortable: true,
                                filter: true,
                                minWidth: 100,
                                cellStyle: {
                                    color: 'red',
                                    textAlign: 'right'
                                }
                            },
                            {
                                headerName: "서울소계",
                                field: "서울소계",
                                sortable: true,
                                filter: true,
                                minWidth: 100,
                                cellStyle: {
                                    textAlign: 'right'
                                }
                            },
                            {
                                headerName: "지방소계",
                                field: "지방소계",
                                sortable: true,
                                filter: true,
                                minWidth: 100,
                                cellStyle: {
                                    textAlign: 'right'
                                }
                            },
                        ];

                        const gridOptions = {
                            columnDefs: columnDefs,
                            rowData: rowData,
                            defaultColDef: {
                                resizable: true
                            },
                            getRowStyle: params => {
                                if (params.data.product_name === '연어필렛' || params.data.product_name === '연어(스테이크용)' || params.data.product_name === '연어(스테이크용)벌크') {
                                    return {
                                        background: 'yellow'
                                    };
                                }
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
                        console.log('새로운 데이터:', data);
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

            // initializeGrid();
            // container.style.display = 'block';

        });
    </script>
<?php
    return ob_get_clean();
}
// 숏코드 등록
add_shortcode('order_summary', 'order_summary_shortcode');

function order_summary()
{
    $category = $_GET['category'];
    $date = $_GET['date'];

    $logFilePath = $_SERVER['DOCUMENT_ROOT'] . "/logfile.log";

    // WordPress 설치 디렉토리의 상대 경로 사용
    $api_url = "/wp-content/plugins/jofEdi/ediAgis_srv.php";
    $api_url = add_query_arg(array(
        'category' => $category,
        'date' => $date
    ), $api_url);

    // 전체 URL 구성
    $full_api_url = home_url($api_url);

    error_log('API URL: ' . $full_api_url, 3, $logFilePath);

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
add_action('wp_ajax_order_summary', 'order_summary');
add_action('wp_ajax_nopriv_order_summary', 'order_summary');
