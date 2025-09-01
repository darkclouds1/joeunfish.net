class FrozenInvtClass {
    constructor({
        col = [
            "No.", "매입처", "구매일자", "자재명", "자재종류", "LOT명",
            "매입(Box)", "창고재고", "공장재고", "합계", "창고명"
        ],
        col2 = [
            "no", "vendor_nm", "input_dt_formatted", "mat_nm", "mat_type", "lot_nm",
            "box", "qty_cur", "stock_qty", "total_qty", "warehouse_nm"
        ],
        col3 = [
            "c", "l", "c", "l", "l", "l",
            "r", "r", "r", "r", "c"
        ],
        colw = [
            "3%", "7%", "8%", "8%", "8%", "18%",
            "7%", "7%", "7%", "7%", "10%"
        ],
        hiddenColumns = [],
    } = {}) {
        this.col = col;
        this.col2 = col2;
        this.col3 = col3;
        this.colw = colw;
        this.hiddenColumns = hiddenColumns;
        this.tableId = "specData"; // "frozenSpecData" -> "specData" 로 변경
        this.selectedRow = null; // 선택된 행을 추적하기 위한 속성 추가
    }

    loadData() {
        // debugger; // 이 줄을 추가합니다.
        const matCd = document.getElementById('mat-cd-frozen').value;
        let category = 'frozen_s'; // 's'는 서버에서 조회를 의미
        let server = `/wp-content/plugins/octopusInvt/octopusInvt_srv.php?category=${category}&mat_cd=${matCd}`;
        
        console.log("[frozenInvt_srv::조회]" + server);
        move(); // 로딩 시작
        getAjax(server, this.recvData.bind(this));
    }

    recvData(data) {
        move_stop();

        try {
            let jsData = JSON.parse(data);
            if (jsData.response.header.resultCode !== "00") {
                throw new Error("Failed to fetch data.");
            }
            const items = jsData.response.body.items;
            const totalCount = jsData.response.body.totalCount;

            if (totalCount == 0) {
                alert("조회할 냉동재고 데이터가 없습니다.");
                document.getElementById("tables").innerHTML = "";
                document.getElementById("totalrec").innerHTML = `[${platForm}:${navigator.platform}] total record=0`;
                return;
            }

            if (platForm === 'mobile') {
                this.makeTableBodyMobile(items, totalCount);
            } else {
                this.makeTableHead();
                this.makeTableBody(items, totalCount);
            }
            document.getElementById("totalrec").innerHTML = `[${platForm}:${navigator.platform}] total record=${totalCount}`;

        } catch (e) {
            console.error(e);
            alert("데이터 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.");
            document.getElementById("tables").innerHTML = "";
            document.getElementById("totalrec").innerHTML = "";
        }
        move_stop();
    }

    makeTableHead() {
        let table = document.createElement("table");
        table.setAttribute("class", "type10 type11");
        table.setAttribute("id", this.tableId);
        table.setAttribute("style", "width:100%; table-layout: fixed; text-align: center;");

        const header = table.createTHead();
        const tr = header.insertRow(-1);

        for (let i = 0; i < this.col.length; i++) {
            let th = document.createElement("th");
            
            // 'No.' 컬럼(첫 번째 열)을 제외하고 정렬 링크 추가
            if (i === 0) {
                th.innerHTML = this.col[i];
            } else {
                th.innerHTML = `${this.col[i]}
                  <a href="#" onclick="sortTD(${i}); return false;" style="color: white;">▲</a><a href="#" onclick="reverseTD(${i}); return false;" style="color: white;">▼</a>`;
            }

            th.setAttribute("style", "width:" + this.colw[i] + ";");
            if (this.hiddenColumns.includes(i)) {
                th.style.display = "none";
            }
            tr.appendChild(th);
        }
        
        // 이 부분을 수정합니다.
        const tablesDiv = document.getElementById("tables");
        tablesDiv.innerHTML = ""; // 기존 내용을 비우고
        tablesDiv.appendChild(table); // 새로운 table 요소를 자식으로 추가합니다.
    }

    makeTableBody(items, totalCount) {
        const table = document.getElementById(this.tableId);
        if (!table) return;

        let tableBody = table.querySelector("tbody");
        if(tableBody) {
            tableBody.innerHTML = "";
        } else {
            tableBody = table.createTBody();
        }

        items.forEach((item, index) => {
            const row = tableBody.insertRow(-1);
            row.style.height = "35px";

            // 클릭 이벤트
            row.addEventListener("click", () => {
                if (this.selectedRow) {
                    this.selectedRow.classList.remove("selected-row");
                }
                row.classList.add("selected-row");
                this.selectedRow = row;
            });

            let displayItem = { ...item, no: index + 1 };

            for (let j = 0; j < this.col2.length; j++) {
                let cell = row.insertCell(-1);
                cell.style.verticalAlign = "middle";

                if (this.hiddenColumns.includes(j)) {
                    cell.style.display = "none";
                }

                const fieldName = this.col2[j];
                let cellValue = displayItem[fieldName] || '';

                // 숫자 필드에 콤마 추가
                const numericFields = ["box", "qty_cur", "stock_qty", "total_qty"];
                if (numericFields.includes(fieldName) && cellValue) {
                    cellValue = parseInt(String(cellValue).replace(/,/g, ''), 10).toLocaleString();
                }

                // '합계','자재명' 컬럼(total_qty)에 CSS 클래스 추가
                if (fieldName === 'total_qty' || fieldName === 'mat_nm') {
                    cell.classList.add('total-qty-cell');
                    cell.style.color = "red";
                    cell.style.fontWeight = "bold";
                }

                cell.innerHTML = cellValue;
                
                let align = this.col3[j];
                if (align === "c") cell.style.textAlign = "center";
                else if (align === "l") cell.style.textAlign = "left";
                else if (align === "r") cell.style.textAlign = "right";
            }
        });

        // 하단의 합계 행을 표시하는 로직 삭제
    }

    makeTableBodyMobile(items, totalCount) {
        let container = document.getElementById("tables");
        if (!container) {
            console.error("Container #tables not found");
            return;
        }
        container.innerHTML = ""; // Clear previous content

        // Dynamic styles for mobile cards
        if (!document.getElementById('mobile-inventory-style-frozen')) {
            const styleElement = document.createElement('style');
            styleElement.id = 'mobile-inventory-style-frozen';
            styleElement.textContent = `
                .inventory-box { margin: 4px; padding: 8px; background: #fff; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid #ddd; }
                .prod-title { font-size: 15px; font-weight: bold; color: #333; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
                .prod-title .vendor-name { color: blue; }
                .input-dt { font-size: 13px; color: red; }
                .inventory-grid { display: grid; grid-template-columns: 1fr; gap: 1px; margin-top: 6px; }
                .inventory-item { padding: 4px 8px; background: #f8f9fa; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
                .inventory-item .label { font-weight: bold; color: #444; font-size: 12px; }
                .inventory-item .value { font-size: 13px; text-align: right; font-weight: bold; }
                .sum-display { margin-top: 8px; padding-top: 8px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: bold; }
                .sum-display .value { color: red; }
                .lot-name-item { justify-content: center; font-weight: bold; color: #555; background: #e9ecef; margin-top: 6px; padding: 4px 8px; border-radius: 5px; text-align: center; }
            `;
            document.head.appendChild(styleElement);
        }

        if (!items || items.length === 0) {
            container.innerHTML = `<div class="inventory-box" style="text-align: center; padding: 20px; color: #666;">데이터가 없습니다.</div>`;
            return;
        }

        items.forEach((item, index) => {
            const box = document.createElement('div');
            box.className = 'inventory-box';

            const formatNumber = (value) => Number(value || 0).toLocaleString();

            const gridItems = [];
            if (item.mat_type) {
                gridItems.push({ label: '자재종류', value: item.mat_type });
            }
            gridItems.push(
                { label: '매입(Box)', value: formatNumber(item.box) },
                { label: '창고재고', value: formatNumber(item.qty_cur) },
                { label: '창고명', value: item.warehouse_nm },
                { label: '재고', value: formatNumber(item.stock_qty) }
            );

            let lotNmHtml = '';
            if (item.lot_nm) {
                lotNmHtml = `<div class="inventory-item lot-name-item"><span>${item.lot_nm}</span></div>`;
            }

            let gridHtml = gridItems.map(gridItem => `
                <div class="inventory-item">
                    <span class="label">${gridItem.label}</span>
                    <span class="value">${gridItem.value}</span>
                </div>
            `).join('');
            
            const totalQtyDisplay = formatNumber(item.total_qty);

            box.innerHTML = `
                <div class="prod-title">
                    <span class="vendor-name">${item.vendor_nm} (${item.mat_nm}${item.mat_type ? `|${item.mat_type}` : ''})</span>
                    <span class="input-dt">${item.input_dt_formatted}</span>
                </div>
                ${lotNmHtml}
                <div class="inventory-grid">${gridHtml}</div>
                <div class="sum-display">
                    <span class="label">재고합계</span>
                    <span class="value">${totalQtyDisplay}</span>
                </div>
            `;
            container.appendChild(box);
        });
    }
}