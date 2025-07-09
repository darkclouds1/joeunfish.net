class ascInvt3Class {
  constructor({
    col = [
      "s_no",
      "납품일자",
      "상품코드",
      "상품명",
      "ASC명",
      "기초재고",
      "더조은",
      "원가",
      "출고",
      "원료재고",
      "실사용",
      "여량",
      "원료원가",
      "판매가",
      "판매총액",
      "수율",
    ],
    col2 = [
      "serial_no",
      "increate_dt",
      "prod_code",
      "prod_nm",
      "asc_nm",
      "init_stock",
      "in_luxury",
      "unit_cost",
      "out_qty",
      "raw_stock",
      "realuse_qty",
      "remain_qty",
      "raw_cost",
      "sell_price",
      "total_sales",
      "yield_rate",
    ],
    col3 = [
      "c", // serial_no (중앙 정렬)
      "c", // invent_dt (중앙 정렬)
      "c", // prod_code (중앙 정렬)
      "c", // prod_nm (중앙 정렬)
      "c", // asc_nm (중앙 정렬)
      "r", // init_stock (숫자, 오른쪽 정렬)
      "r", // in_luxury (숫자, 오른쪽 정렬)
      "r", // cost (숫자, 오른쪽 정렬)
      "r", // out_qty (숫자, 오른쪽 정렬)
      "r", // row_stock (숫자, 오른쪽 정렬)
      "r", // real_qty (숫자, 오른쪽 정렬)
      "r", // remain_qty (숫자, 오른쪽 정렬)
      "r", // raw_cost (숫자, 오른쪽 정렬)
      "r", // sale_price (숫자, 오른쪽 정렬)
      "r", // total_price (숫자, 오른쪽 정렬)
      "r", // yield_rate (숫자, 오른쪽 정렬)
    ],
    colw = [
      "4%", // serial_no
      "7%", // invent_dt
      "10%", // prod_code
      "8%", // prod_nm
      "5%", // asc_nm
      "5%", // init_stock
      "5%", // in_luxury
      "6%", // cost
      "5%", // out_qty
      "5%", // row_stock
      "5%", // real_qty
      "5%", // remain_qty
      "6%", // raw_cost
      "6%", // sale_price
      "7%", // total_price
      "8%", // yield_rate
    ],
    hiddenColumns = [0], // 0, 1 히든 컬럼
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    this.hiddenColumns = hiddenColumns;
    // this.updateInventory = this.updateInventory.bind(this);
    this.updateInventoryInput = this.updateInventoryInput.bind(this);
  }

  // 헤드 구성
  makeTableHead() {
    var head = document.getElementById("tables").innerHTML;
    if (head != "") return; // 제목란이 있으면.. ajaxmore
    idx = 0;
    table1 = document.createElement("table");
    table1.setAttribute("class", "type10 type11");
    table1.setAttribute("id", "specData");
    table1.setAttribute("style", "width:100%"); //'width', '700px');

    var header = table1.createTHead();
    var tr = header.insertRow(-1); // TABLE ROW.

    // sort
    for (var i = 0; i < this.col.length; i++) {
      var th = document.createElement("th"); // TABLE HEADER.
      // 소트 sort  문자
      th.innerHTML = this.col[i];
      // +
      // '<a onclick="sortTD (' +
      // i +
      // ')">▲</a><a onclick="reverseTD (' +
      // i +
      // ')">▼</a>';
      th.setAttribute("style", "width:" + this.colw[i] + ";");

      // 히든 컬럼 숨김
      // if (this.hiddenColumns.includes(i)) {
      //   th.style.display = "none";
      // }

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

    // 데이터 없음
    if (items.length == 0) {
      alert("데이터가 없습니다. 조회 방법을 확인하세요.");
      return rtnCnt;
    }

    // 먼저 각 상품코드의 마지막 행 인덱스를 찾습니다.
    let currentProdCode = null;
    let lastRowIndexForProdCode = {};
    let lastNonSumIndex = -1;

    for (let i = 0; i < items.length; i++) {
      // SUM이 아닌 경우에만 처리
      if (items[i]["serial_no"] !== "SUM") {
        if (items[i]["prod_code"] !== currentProdCode) {
          if (currentProdCode !== null) {
            lastRowIndexForProdCode[currentProdCode] = lastNonSumIndex;
          }
          currentProdCode = items[i]["prod_code"];
        }
        lastNonSumIndex = i;
      }
    }

    // 마지막 상품코드의 마지막 행 처리
    if (currentProdCode !== null) {
      lastRowIndexForProdCode[currentProdCode] = lastNonSumIndex;
    }
    currentProdCode = null;

    barcodeList = [];
    let tbody = table1.createTBody();

    for (let i = 0; i < items.length; i++) {
      try {
        // 번호
        idx = idx + 1;

        let tr = tbody.insertRow(-1);
        tr.setAttribute(
          "style",
          "height: 40px; text-align: center; vertical-align: middle;"
        );
        // tabCells.innerHTML = idx;

        if (items[i]["asc_nm"] === "합계") {
          tr.style.fontWeight = "bold";
          tr.style.backgroundColor = "yellow";
        }

        let newItem = {
          serial_no: items[i]["serial_no"],
          increate_dt: items[i]["increate_dt"], // invent_dt => increate_dt
          prod_code: items[i]["prod_code"],
          prod_nm: items[i]["prod_nm"],
          asc_nm: items[i]["asc_nm"],
          init_stock: items[i]["init_stock"],
          in_luxury: items[i]["in_luxury"],
          in_cheong: items[i]["in_cheong"],
          unit_cost: items[i]["unit_cost"], // 변경 없음
          out_qty: items[i]["out_qty"],
          raw_stock: items[i]["raw_stock"], // row_stock => raw_stock
          realuse_qty: items[i]["realuse_qty"], // real_qty => realuse_qty
          remain_qty: items[i]["remain_qty"],
          raw_cost: items[i]["raw_cost"],
          sell_price: items[i]["sell_price"], // sale_price => sell_price
          total_sales: items[i]["total_sales"], // total_price => total_sales
          yield_rate: items[i]["yield_rate"],
        };
        barcodeList.push(newItem);

        for (let j = 0; j < this.col2.length; j++) {
          let tabCell = tr.insertCell(-1);
          tabCell.setAttribute("style", "height: 40px; vertical-align: middle;");

          // 히든 컬럼 숨김
          // if (this.hiddenColumns.includes(j)) {
          //   tabCell.style.display = "none";
          // }

          // 마지막 생산일과 그 전날까지 수정 가능
          const yesterday = new Date(today);
          yesterday.setDate(yesterday.getDate() - 1);

          // yyyy-mm-dd 형식으로 변환
          const formatDate = (date) => {
            const year = date.getFullYear();
            const month = ("0" + (date.getMonth() + 1)).slice(-2);
            const day = ("0" + date.getDate()).slice(-2);
            return `${year}-${month}-${day}`;
          };

          // Date 객체가 아닌 경우를 대비하여 Date 객체로 변환
          const formattedYesterday = formatDate(new Date(yesterday));
          const formattedToday = formatDate(new Date(today));

          if (
            (items[i]["serial_no"] === "" || items[i]["serial_no"] !== "SUM") &&
            (gIncreate_dt >= formattedToday || gIncreate_dt === formattedYesterday)
          ) {
            if (
              this.col[j] === "더조은" ||
              // this.col[j] === "청해진" ||
              this.col[j] === "원가" ||
              this.col[j] === "출고" ||
              this.col[j] === "원료재고" ||
              (this.col[j] === "판매가" && i === lastRowIndexForProdCode[items[i]["prod_code"]])
            ) {
              if (this.col[j] === "출고" || this.col[j] === "원료재고") {
                const col2JSON = JSON.stringify(this.col2).replace(/"/g, "&quot;");
                const itemsJSON = JSON.stringify(items).replace(/"/g, "&quot;");

                tabCell.innerHTML = `<input type="text" value="${items[i][this.col2[j]].replace(/,/g, "")}" 
                 style="width: 100%; text-align: right; padding: 3px; font-size: 13px;" 
                  oninput="updateInventoryInput(event, ${i}, '${col2JSON}', '${itemsJSON}', ${j})"
                  onblur="updateInventoryInput(event, ${i}, '${col2JSON}', '${itemsJSON}', ${j})"
                onfocus="this.select()">`; // 여기에 onfocus 이벤트를 추가했습니다.
              } else {
                tabCell.innerHTML = `<input type="text" value="${items[i][this.col2[j]].replace(/,/g, "")}" 
                style="width: 100%; text-align: right; padding: 3px; font-size: 13px;" onfocus="this.select()">`;
              }
            } else {
              tabCell.innerHTML = items[i][this.col2[j]];
            }
            // 과거데이터 수정불가
          } else {
            tabCell.innerHTML = items[i][this.col2[j]];
          }

          // 정렬하기
          let attr = "";
          if (this.col3[j] == "c") {
            attr += "text-align: center; vertical-align: middle;";
          } else if (this.col3[j] == "l") {
            attr += "text-align: left; vertical-align: middle;";
          } else if (this.col3[j] == "r") {
            attr += "text-align:right; vertical-align: middle;";
          } else if (this.col3[j] == "d") {
            attr += "text-align:center; vertical-align: middle;";
            if (tabCell != null && tabCell.innerHTML.length > 10) tabCell.innerHTML = tabCell.innerHTML.substr(0, 10);
          }
          tabCell.setAttribute("style", attr);

          // 셀에 링크 추가
          tabCell.innerHTML = this.constructor.setLink(items, i, j, tabCell);
        }
      } catch (ex) {
        console.log(ex.message);
      }
    }
    // FINALLY ADD THE NEWLY CREATED TABLE WITH JSON DATA TO A CONTAINER.
    let divContainer = document.getElementById("tables");
    //divContainer.innerHTML = "";
    divContainer.appendChild(table1);
    // console.log("barcodeList", barcodeList);
    return rtnCnt;
  }

  // 링크 만들기
  static setLink(items, i, j, cell) {
    let today = getToday("-");
    // 0:번호, 1:일련번호
    switch (j) {
      case 1: // 납품일자
        break;
      default:
        break;
    } // switch
    return cell.innerHTML;
  }

  // Grid 에서 [출고]와 [원료재고] 수정 시 계산
  updateInventoryInput(event, idx, col2Str, itemsStr, j) {
    // console.log("updateInventoryInput idx=" + idx + ", j=" + j);
    const init_stock =
      parseFloat(
        $("#specData tr")
          .eq(idx + 1)
          .find("td")
          .eq(5)
          .text()
          .replace(/[^0-9.]/g, "")
      ) || 0;
    const in_luxury =
      Number(
        $("#specData tr")
          .eq(idx + 1)
          .find("td")
          .eq(6)
          .find("input")
          .val()
      ) || 0;
    const out_qty =
      Number(
        $("#specData tr")
          .eq(idx + 1)
          .find("td")
          .eq(8)
          .find("input")
          .val()
      ) || 0;
    const remain_qty =
      Number(
        $("#specData tr")
          .eq(idx + 1)
          .find("td")
          .eq(9)
          .find("input")
          .val()
      ) || 0;

    // 원료재고 = 기초재고 + 더조은  - 출고
    const remain_qty_new = init_stock + in_luxury - out_qty;
    // console.log("remain_qty_new=", remain_qty_new);

    // 실사용 = 기초재고 + 더조은  - 원료재고
    let realuse_qty = init_stock + in_luxury - remain_qty;
    // console.log("realuse_qty=", realuse_qty);

    switch (j) {
      case 8: // 출고::원료재고를 수정하면 아래 순서대로 계산
        $("#specData tr")
          .eq(idx + 1)
          .find("td")
          .eq(9)
          .find("input")
          .val(remain_qty_new)
          .trigger("change");
        break;
      case 9: //원료재고
        // 실사용 = 기초재고 + 명품 + 청해진 - 원료재고
        $("#specData tr")
          .eq(idx + 1)
          .find("td")
          .eq(10)
          .html(realuse_qty);

        // 여량 = 출고 - 실사용
        $("#specData tr")
          .eq(idx + 1)
          .find("td")
          .eq(11)
          .html(out_qty - realuse_qty);

        break;
      default:
        break;
    }
  }

  // 저장
  saveInventory() {
    const increate_dt = document.getElementById("createDate").value; // 화면상의 생산일자 가져오기
    const data = {
      barcodeList: barcodeList,
    };

    const url =
      "/wp-content/plugins/ascInvt3/ascInvt3_srv.php?category=saveInvt&increate_dt=" +
      increate_dt +
      "&user_id=" +
      user_id;
    console.log("[ascInvt3_srv.php]", url);

    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    })
      .then((response) => response.json())

      .then((result) => {
        console.log("[ascInvt3_srv.php] result:", result);

        if (result.success) {
          alert("저장이 성공적으로 완료되었습니다.");
          // 조회 버튼 클릭 이벤트 트리거
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
  // barcodeIn- mobile
  // --------------------------
   makeTabletr2Mobile(datas) {
    let cleanData = datas.replace(/[\u0000-\u001F\u007F-\u009F]/g, "");
    let data = JSON.parse(cleanData);
    const items = data.response.body.items;

    // 컨테이너 준비
    let container = document.getElementById("tables");
    if (!container) {
        $('body').append('<div id="tables"></div>');
        container = document.getElementById("tables");
    }
    container.innerHTML = "";

    // 스타일이 한 번만 추가되도록 설정
    if (!document.getElementById('mobile-inventory-style')) {
        const styleElement = document.createElement('style');
        styleElement.id = 'mobile-inventory-style';
        styleElement.textContent = `
            .inventory-box {
                margin: 8px;
                padding: 12px;
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                border: 1px solid #ddd;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .inventory-box.selected {
                background: #e3f2fd;
                border: 1px solid #2196F3;
                box-shadow: 0 4px 8px rgba(33,150,243,0.2);
            }
            .inventory-box.sum-row {
                background: #fff9c4;
                border: 1px solid #fbc02d;
            }
            .prod-title {
                font-size: 16px;
                font-weight: bold;
                color: #333;
                margin-bottom: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .asc-name {
                font-size: 14px;
                color: #666;
                padding: 2px 8px;
                border-radius: 4px;
                background: #f5f5f5;
            }
            .inventory-details {
                font-size: 14px;
                color: #555;
                line-height: 1.5;
            }
            .inventory-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 3px;
                margin: 10px 0;
            }
            .inventory-item {
                padding: 6px;
                background: #f8f9fa;
                border-radius: 5px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .label {
                font-weight: bold;
                color: #444;
            }
            .value {
                color: #333;
                text-align: right;
            }
            .serial-no {
                position: absolute;
                top: 8px;
                right: 8px;
                font-size: 12px;
                color: #888;
            }
        `;
        document.head.appendChild(styleElement);
    }

    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="inventory-box">
                <div style="text-align: center; padding: 20px; color: #666;">
                    데이터가 없습니다. 조회 방법을 확인하세요.
                </div>
            </div>
        `;
        return;
    }

    idx = items.length; // 총 건수 표시 

    items.forEach((item, idx) => {
        const box = document.createElement('div');
        box.className = 'inventory-box';
        if (item.serial_no === "SUM") {
            box.classList.add('sum-row');
        }
        box.dataset.index = idx;

        // 클릭 이벤트 추가
        box.addEventListener('click', (e) => {
            document.querySelectorAll('.inventory-box.selected').forEach(box => {
                box.classList.remove('selected');
            });
            box.classList.add('selected');
        });

        // 숫자 포맷팅 함수
        const formatNumber = (num) => {
            if (num === undefined || num === null) return '-';
            return Number(num).toLocaleString('ko-KR');
        };

        box.innerHTML = item.serial_no === "SUM" ? 
    `
    <div class="prod-title">
        <span>[${item.prod_code}] ${item.prod_nm}</span>
        <span class="asc-name">${item.asc_nm}</span>
    </div>
    <div class="inventory-details">
        <div class="inventory-grid">
            <div class="inventory-item">
                <span class="label">실사용</span>
                <span class="value">${item.realuse_qty}</span>
            </div>
            <div class="inventory-item">
                <span class="label">여량</span>
                <span class="value">${item.remain_qty}</span>
            </div>
            <div class="inventory-item">
                <span class="label">원료원가</span>
                <span class="value">${item.raw_cost}</span>
            </div>
            <div class="inventory-item">
                <span class="label">판매가</span>
                <span class="value">${item.sell_price}</span>
            </div>
            <div class="inventory-item">
                <span class="label">판매총액</span>
                <span class="value">${item.total_sales}</span>
            </div>
            <div class="inventory-item">
                <span class="label" style="color: red;">수율</span>
                <span class="value" style="color: red;">${item.yield_rate}</span>
            </div>
        </div>
    </div>
    ${item.serial_no ? `<div class="serial-no">No.${item.serial_no}</div>` : ''}
    ` : 
    `
    <div class="prod-title">
        <span>[${item.prod_code}] ${item.prod_nm}</span>
        <span class="asc-name">${item.asc_nm}</span>
    </div>
    <div class="inventory-details">
        <div class="inventory-grid">
            <div class="inventory-item">
                <span class="label">기초재고</span>
                <span class="value">${formatNumber(item.init_stock)}</span>
            </div>
            <div class="inventory-item">
                <span class="label">더조은</span>
                <span class="value">${formatNumber(item.in_luxury)}</span>
            </div>
            <div class="inventory-item">
                <span class="label"></span>
                <span class="value"></span>
            </div>
            <div class="inventory-item">
                <span class="label">원가</span>
                <span class="value">${item.unit_cost}</span>
            </div>
            <div class="inventory-item">
                <span class="label">출고</span>
                <span class="value">${formatNumber(item.out_qty)}</span>
            </div>
            <div class="inventory-item">
                <span class="label">원료재고</span>
                <span class="value">${formatNumber(item.raw_stock)}</span>
            </div>
            <div class="inventory-item">
                <span class="label">실사용</span>
                <span class="value">${formatNumber(item.realuse_qty)}</span>
            </div>
            <div class="inventory-item">
                <span class="label">여량</span>
                <span class="value">${formatNumber(item.remain_qty)}</span>
            </div>
            <div class="inventory-item">
                <span class="label">원료원가</span>
                <span class="value">${item.raw_cost}</span>
            </div>
            <div class="inventory-item">
                <span class="label">판매가</span>
                <span class="value">${item.sell_price}</span>
            </div>
                

        </div>
    </div>
    ${item.serial_no ? `<div class="serial-no">No.${item.serial_no}</div>` : ''}
    `;

        container.appendChild(box);
    });
  }

}

// updateInventoryInput 함수를 전역 함수에서 클래스 메서드로 변경
function updateInventoryInput(event, i, col2Str, itemsStr, j) {
  const instance = new ascInvt3Class();
  instance.updateInventoryInput(event, i, col2Str, itemsStr, j);
}

// barcodeList 에 저장
function saveBarcodeList() {
  const table = document.getElementById("specData");
  const rows = table.rows;
  // console.log("rows:",  rows);

  for (let i = 1; i < rows.length; i++) {
    // 첫 번째 행은 헤더이므로 건너뜀
    const cells = rows[i].cells;

    if (cells[0].innerText === "SUM") continue;

    const cIn_luxury = cells[6].querySelector("input").value; // 더조은 입고
    // const cIn_cheong = cells[7].querySelector("input").value; // 청해진입고
    const cUnit_cost = cells[7].querySelector("input").value; // 원가
    const cOut_qty = cells[8].querySelector("input").value; // 출고수량
    const cRaw_stock = cells[9].querySelector("input").value; // 원료재고
    // input이 아닌 일반 td 일 경우
    const cRealuse_qty = cells[10].innerText; // 실사용량
    const cRemain_qty = cells[11].innerText; // 여량

    // 판매가 처리
    const sellPriceInput = cells[13].querySelector("input");
    const cSell_price = sellPriceInput ? sellPriceInput.value : "";

    barcodeList[i - 1]["in_luxury"] = cIn_luxury;
    // barcodeList[i - 1]["in_cheong"] = cIn_cheong;
    barcodeList[i - 1]["unit_cost"] = cUnit_cost;
    barcodeList[i - 1]["out_qty"] = cOut_qty;
    barcodeList[i - 1]["raw_stock"] = cRaw_stock;
    barcodeList[i - 1]["realuse_qty"] = cRealuse_qty;
    barcodeList[i - 1]["remain_qty"] = cRemain_qty;
    barcodeList[i - 1]["sell_price"] = cSell_price;
  }

  // "SUM" 항목 제거
  barcodeList = barcodeList.filter((item) => item.serial_no !== "SUM");
  barcodeList.forEach((item) => {
    delete item.serial_no;
  });

  codeOutClass.saveInventory();
}
