class PriceHistory {
    constructor() {
        this.panel = null;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    init() {
        this.addStyles();
        this.createPanel();
    }

    createPanel() {
        if (this.panel) {
            this.panel.remove();
        }

        this.panel = document.createElement('div');
        this.panel.className = 'price-history-panel';
        this.panel.style.display = 'none';
        document.body.appendChild(this.panel);
    }

    async showHistory(prodCd) {
        try {
            if (!prodCd) {
                throw new Error('제품코드가 필요합니다');
            }

            console.log('조회할 제품코드:', prodCd);
            
            const url = `/wp-content/plugins/unit-price/unit_price_srv.php?category=old-price&product_code=${encodeURIComponent(prodCd)}`;
            console.log('요청 URL:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            console.log('서버 응답 데이터:', data);

            let historyData;
            if (Array.isArray(data)) {
                historyData = data;
            } else if (data.data && Array.isArray(data.data)) {
                historyData = data.data;
            } else if (typeof data === 'object') {
                historyData = [data];
            } else {
                throw new Error('지원하지 않는 데이터 형식입니다');
            }

            this.displayHistory(historyData);
        } catch (error) {
            console.error('단가 이력 조회 실패:', error);
            alert('단가 이력을 불러오는데 실패했습니다: ' + error.message);
        }
    }

    displayHistory(data) {
        const prodName = data[0]?.['제품명'] || data[0]?.PROD_NAME || data[0]?.prod_name || '';

        let html = `
            <div class="price-history-header">
                <div class="price-history-title">${prodName}</div>
                <button class="price-history-close">닫기</button>
            </div>
            <table class="price-history-table">
                <thead>
                    <tr>
                        <th>공급일</th>
                        <th>단가</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(item => {
            const date = item.supply_date || item.SUPPLY_DATE || item.date || item['공급일'];
            const price = item.price || item.PRICE || item['단가'];

            html += `
                <tr class="price-history-row">
                    <td>${date || '-'}</td>
                    <td>${this.numberWithCommas(price || 0)}원</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        this.panel.innerHTML = html;
        this.showPanel();

        // 패널이 생성된 후에 이벤트 리스너 설정
        this.initEventListeners();

        // 각 행에 클릭 이벤트 추가
        const rows = this.panel.querySelectorAll('.price-history-row');
        rows.forEach(row => {
            row.addEventListener('click', () => {
                // 모든 행의 선택 스타일 제거
                rows.forEach(r => r.classList.remove('selected-row'));
                // 클릭된 행에 선택 스타일 추가
                row.classList.add('selected-row');
            });
        });
    }


    initEventListeners() {
        const closeButton = this.panel.querySelector('.price-history-close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                console.log('닫기 버튼 클릭');
                this.hidePanel();
            });
        }
    }

    showPanel() {
        if (this.panel) {
            this.panel.style.display = 'block';
        }
    }

    hidePanel() {
        console.log('패널 숨기기');
        if (this.panel) {
            this.panel.style.display = 'none';
        }
    }

    numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .price-history-panel {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border: 1px solid #ccc;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                padding: 15px;
                z-index: 1000;
                min-width: 300px;
                max-height: 400px;
                overflow-y: auto;
                border-radius: 4px;
                display: none;
            }
            .price-history-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .price-history-title {
                font-size: 13px;
                font-weight: bold;
                color: #333;
            }
            .price-history-close {
                background-color: #000000;
                color: white;
                border: none;
                padding: 5px 10px;
                font-size: 12px;
                cursor: pointer;
                border-radius: 4px;
                height: auto;
            }
            .price-history-close:hover {
                background-color:rgb(245, 85, 85);
            }
            .price-history-table {
                width: 100%;
                border-collapse: collapse;
            }
            .price-history-table th,
            .price-history-table td {
                padding: 8px;
                border: 1px solid #ddd;
                text-align: center;
                font-size: 13px;
            }
            .price-history-table th {
                background: #f5f5f5;
                text-align: center;
            }
            .price-cell {
                cursor: pointer;
                color: red;
                text-align: right;
            }
            .price-cell:hover {
                background-color: #f0f0f0;
            }
            .selected-row {
                background-color:rgb(108, 240, 112); /* 초록색 배경 */
            }
        `;
        document.head.appendChild(style);
    }
}

// 전역 인스턴스 생성
const priceHistory = new PriceHistory();