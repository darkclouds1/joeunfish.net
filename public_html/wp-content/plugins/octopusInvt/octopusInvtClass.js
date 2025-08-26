class octopusInvtClass {
  constructor({
    col = [
      "No.",
      "매입처",
      "구매일자",
      "대",
      "4미",
      "5미",
      "6미",
      "7미",
      "8미",
      "9미",
      "10-15미",
      "16-20미",
      "21-25미",
      "26-30미",
      "창고명",
      "비고",
    ],
    col2 = [
      "serial_no",
      "vendor_nm",
      "formatted_input_dt",
      "stock_large",
      "stock_4mi",
      "stock_5mi",
      "stock_6mi",
      "stock_7mi",
      "stock_8mi",
      "stock_9mi",
      "stock_10_15mi",
      "stock_16_20mi",
      "stock_21_25mi",
      "stock_26_30mi",
      "warehouse_nm",
      "remark",
    ],
    col3 = [
      "c", "l", "c", "r", "r", "r", "r", "r", "r", "r", "r", "r", "r", "r", "l", "l",
    ],
    colw = [
      "3%", "5%", "5%", "5%", "5%", "5%", "5%", "5%", "5%", "5%", "5%", "5%", "5%", "5%", "6%", "10%",
    ],
    hiddenColumns = [], // 숨길 컬럼이 있다면 여기에 인덱스 추가 (e.g., [0])
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    this.hiddenColumns = hiddenColumns;
  }

  // 헤드 구성
  makeTableHead() {
    var head = document.getElementById("tables").innerHTML;
    if (head != "") return; // 제목란이 있으면.. ajaxmore
    idx = 0;
    table1 = document.createElement("table");
    table1.setAttribute("class", "type10 type11");
    table1.setAttribute("id", "specData");
    table1.setAttribute("style", "width:100%");

    var header = table1.createTHead();
    var tr = header.insertRow(-1);

    for (var i = 0; i < this.col.length; i++) {
      var th = document.createElement("th");
      th.innerHTML = this.col[i];
      th.setAttribute("style", "width:" + this.colw[i] + ";");
      if (this.hiddenColumns.includes(i)) {
        th.style.display = "none";
      }
      tr.appendChild(th);
    }

    if ((document.getElementById("tables").innerHTML = "")) {
      document.getElementById("tables").innerHTML = table1.outerHTML;
      idx = 0;
    }
  }

  // makeTable
  makeTabletr(datas) {
    let cleanData = datas.replace(/[\u0000-\u001F\u007F-\u009F]/g, "");
    let data = JSON.parse(cleanData);
    items = data.response.body.items;
    rtnCnt = items.length;

    if (items.length == 0) {
      alert("데이터가 없습니다. 매입 데이터를 먼저 확인하세요.");
      return rtnCnt;
    }
    
    inventoryList = []; // inventoryList 초기화
    let tbody = table1.createTBody();

    for (let i = 0; i < items.length; i++) {
      try {
        idx = idx + 1;

        let tr = tbody.insertRow(-1);
        tr.setAttribute(
          "style",
          "height: 40px; text-align: center; vertical-align: middle;"
        );
        
        // 원본 데이터를 inventoryList에 저장
        inventoryList.push({...items[i]});

        // 화면 표시용 데이터 생성
        let displayItem = { ...items[i] };
        displayItem.serial_no = idx; // 화면에는 1부터 시작하는 번호 표시

        for (let j = 0; j < this.col2.length; j++) {
          let tabCell = tr.insertCell(-1);
          tabCell.setAttribute("style", "height: 40px; vertical-align: middle;");
          
          if (this.hiddenColumns.includes(j)) {
            tabCell.style.display = "none";
          }

          const fieldName = this.col2[j];
          // 입력 필드가 필요한 컬럼들
          if (fieldName.startsWith('stock_') || fieldName === 'warehouse_nm' || fieldName === 'remark') {
            const inputType = (fieldName.startsWith('stock_')) ? 'number' : 'text';
            const value = displayItem[fieldName] !== null ? displayItem[fieldName] : '';
            const textAlign = (inputType === 'number') ? 'right' : 'left';
            tabCell.innerHTML = `<input type="text" value="${value}" 
              style="width: 100%; text-align: ${textAlign}; padding: 3px; font-size: 13px;" 
              onfocus="this.select()">`;
          } else {
            tabCell.innerHTML = displayItem[fieldName];
          }

          // 정렬
          let attr = "";
          if (this.col3[j] == "c") attr += "text-align: center;";
          else if (this.col3[j] == "l") attr += "text-align: left;";
          else if (this.col3[j] == "r") attr += "text-align:right;";
          tabCell.setAttribute("style", attr + "vertical-align: middle;");
        }
      } catch (ex) {
        console.log(ex.message);
      }
    }
    let divContainer = document.getElementById("tables");
    divContainer.innerHTML = ""; // 기존 테이블 초기화
    divContainer.appendChild(table1);
    
    return rtnCnt;
  }

  // 저장
  saveInventoryData() {
    const increate_dt = document.getElementById("createDate").value;
    const data = {
      inventoryList: inventoryList, // 전역 변수 inventoryList 사용
    };

    const url =
      "/wp-content/plugins/octopusInvt/octopusInvt_srv.php?category=saveInvt&increate_dt=" +
      increate_dt +
      "&user_id=" +
      user_id;
    console.log("[octopusInvt_srv.php save]", url);
    console.log("data:", data);

    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    })
      .then((response) => response.json())
      .then((result) => {
        console.log("[octopusInvt_srv.php] result:", result);

        if (result.success) {
          alert("저장이 성공적으로 완료되었습니다.");
          document.getElementById("actionBtn").click();
        } else {
          alert("저장에 실패했습니다. 다시 시도해주세요.");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("저장 중 오류가 발생했습니다.");
      });
  }
  
  // --------------------------
  // octopusInvt- mobile
  // --------------------------
  makeTabletr2Mobile(datas) {
    let cleanData = datas.replace(/[\u0000-\u001F\u007F-\u009F]/g, "");
    let data = JSON.parse(cleanData);
    const items = data.response.body.items;

    let container = document.getElementById("tables");
    if (!container) {
        $('body').append('<div id="tables"></div>');
        container = document.getElementById("tables");
    }
    container.innerHTML = "";

    // 스타일 동적 추가
    if (!document.getElementById('mobile-inventory-style')) {
        const styleElement = document.createElement('style');
        styleElement.id = 'mobile-inventory-style';
        styleElement.textContent = `
            .inventory-box { margin: 8px; padding: 12px; background: #fff; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid #ddd; }
            .prod-title { font-size: 16px; font-weight: bold; color: #333; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
            .input-dt { font-size: 14px; color: #666; }
            .inventory-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 10px; }
            .inventory-item { padding: 8px; background: #f8f9fa; border-radius: 5px; }
            .inventory-item .label { font-weight: bold; color: #444; display: block; margin-bottom: 4px; font-size: 13px; }
            .inventory-item .value { color: #333; font-size: 14px; text-align: right; }
        `;
        document.head.appendChild(styleElement);
    }

    if (!items || items.length === 0) {
        container.innerHTML = `<div class="inventory-box"><div style="text-align: center; padding: 20px; color: #666;">데이터가 없습니다.</div></div>`;
        return;
    }
    idx = items.length;

    items.forEach((item, index) => {
        const box = document.createElement('div');
        box.className = 'inventory-box';
        box.dataset.index = index;

        const stockItems = [
            { label: '대', value: item.stock_large },
            { label: '4미', value: item.stock_4mi },
            { label: '5미', value: item.stock_5mi },
            { label: '6미', value: item.stock_6mi },
            { label: '7미', value: item.stock_7mi },
            { label: '8미', value: item.stock_8mi },
            { label: '9미', value: item.stock_9mi },
            { label: '10-15미', value: item.stock_10_15mi },
            { label: '16-20미', value: item.stock_16_20mi },
            { label: '21-25미', value: item.stock_21_25mi },
            { label: '26-30미', value: item.stock_26_30mi },
        ];

        let gridHtml = '';
        stockItems.forEach(stock => {
            gridHtml += `<div class="inventory-item"><span class="label">${stock.label}</span><span class="value">${Number(stock.value).toLocaleString()}</span></div>`;
        });
        
        box.innerHTML = `
            <div class="prod-title">
                <span>${item.vendor_nm}</span>
                <span class="input-dt">${item.formatted_input_dt}</span>
            </div>
            <div class="inventory-grid">${gridHtml}</div>
        `;
        container.appendChild(box);
    });
  }
}

// 테이블 데이터를 읽어 inventoryList 업데이트 후 저장 함수 호출
function saveInventoryList() {
  const table = document.getElementById("specData");
  if (!table) {
      console.error("테이블을 찾을 수 없습니다.");
      return;
  }
  const rows = table.rows;

  for (let i = 1; i < rows.length; i++) { // i=0은 헤더
    const cells = rows[i].cells;
    const dataIndex = i - 1;

    // inventoryList에서 해당 행의 원본 데이터 객체를 찾음
    if (inventoryList[dataIndex]) {
      inventoryList[dataIndex].stock_large   = cells[3].querySelector('input').value;
      inventoryList[dataIndex].stock_4mi      = cells[4].querySelector('input').value;
      inventoryList[dataIndex].stock_5mi      = cells[5].querySelector('input').value;
      inventoryList[dataIndex].stock_6mi      = cells[6].querySelector('input').value;
      inventoryList[dataIndex].stock_7mi      = cells[7].querySelector('input').value;
      inventoryList[dataIndex].stock_8mi      = cells[8].querySelector('input').value;
      inventoryList[dataIndex].stock_9mi      = cells[9].querySelector('input').value;
      inventoryList[dataIndex].stock_10_15mi  = cells[10].querySelector('input').value;
      inventoryList[dataIndex].stock_16_20mi  = cells[11].querySelector('input').value;
      inventoryList[dataIndex].stock_21_25mi  = cells[12].querySelector('input').value;
      inventoryList[dataIndex].stock_26_30mi  = cells[13].querySelector('input').value;
      inventoryList[dataIndex].warehouse_nm = cells[14].querySelector('input').value;
      inventoryList[dataIndex].remark       = cells[15].querySelector('input').value;
    }
  }

  codeOutClass.saveInventoryData();
}
