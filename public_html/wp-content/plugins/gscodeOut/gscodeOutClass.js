class gscodeOutClass {
  constructor({
    col = [
      "no",
      "납품일",
      "점포명",
      "상품코드",
      "상품명",
      "주문량",
      "추가량",
      "재료Kg",
      "생산량",
      "생산Kg",
      "수율",
      "전재고",
      "재고량",
      "비고",
    ],
    col2 = [
      "no",
      "supply_dt",
      "store_no",
      "prod_code",
      "prod_nm",
      "quantity",
      "quantity_new",
      "weight_in",
      "prod_quantity",
      "weight_out",
      "prod_yield",
      "before_invent_quantity",
      "invent_quantity",
      "invent_note",
    ],
    col3 = ["c", "c", "c", "c", "l", "r", "r", "r", "r", "r", "r", "r", "l"],
    colw = [
      "3%",
      "6%",
      "13%",
      "10%",
      "8%",
      "5%",
      "5%",
      "5%",
      "5%",
      "5%",
      "5%",
      "5%",
      "5%",
      "20%",
    ],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    this.updateInventory = this.updateInventory.bind(this);
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
    let invent_dt = "";

    // 데이터 없음
    if (items.length == 0) {
      alert("데이터가 없습니다. 조회 방법을 확인하세요.");
      return rtnCnt;
    }

    // console.log(datas);

    barcodeList = [];
    let tbody = table1.createTBody();

    for (let i = 0; i < items.length; i++) {
      try {
        // 번호
        idx = idx + 1;

        let tr = tbody.insertRow(-1);

        let tabCells = tr.insertCell(-1);

        tabCells.innerHTML = idx;
        tr.setAttribute("style", "height: 35px;text-align:center;");

        let newItem = {
          no: idx,
          supply_dt: items[i]["supply_dt"], // 납품일
          store_code: items[i]["store_code"], // 점포코드
          store_no: items[i]["store_no"], // 점포명
          prod_nm: items[i]["prod_nm"], // 상품명
          prod_code: items[i]["prod_code"], // 상품코드
          quantity: items[i]["quantity"], // 주문수량
          quantity_new: items[i]["quantity_new"], // 추가수량
          weight_in: items[i]["weight_in"], // 입고중량
          prod_quantity: items[i]["prod_quantity"], // 생산수량
          weight_out: items[i]["weight_out"], // 출고중량
          prod_yield: items[i]["prod_yield"], // 생산요율
          before_invent_quantity: items[i]["before_invent_quantity"], // 전일재고
          origin_invent_quantity: items[i]["origin_invent_quantity"], // 재고테이블 저장용 수량
          invent_quantity: items[i]["invent_quantity"], // 재고수량
          invent_note: items[i]["invent_note"], // 조정사유
        };
        barcodeList.push(newItem);

        for (let j = 1; j < this.col2.length; j++) {
          let tabCell = tr.insertCell(-1);
          tabCell.setAttribute("style", "height: 35px;");

          // globalCreateDate와 invent_dt 비교
          invent_dt = items[i]["invent_dt"].substring(0, 10);

          // 최종재고일 이후만 수정 가능
          if (gIncreate_dt >= invent_dt) {
            if (
              this.col[j] === "재료Kg" ||
              this.col[j] === "생산량" ||
              this.col[j] === "재고량"
            ) {
              if (this.col[j] === "생산량") {
                tabCell.innerHTML = `<input type="text" value="${items[i][
                  this.col2[j]
                ].replace(/,/g, "")}" 
                style="width: 100%; text-align: right; padding: 3px; font-size: 13px;" 
                oninput="updateInventoryInput(event, ${i})">`;
              } else {
                // 재료중량,재고수량
                tabCell.innerHTML = `<input type="text" value="${items[i][
                  this.col2[j]
                ].replace(
                  /,/g,
                  ""
                )}" style="width: 100%; text-align: right; padding: 3px; font-size: 13px;" >`;
              }
            } else if (this.col[j] === "비고") {
              if (
                items[i][this.col2[j]] === "" ||
                items[i][this.col2[j]] === null
              ) {
                tabCell.innerHTML = `<input type="text" value="${items[i]["invent_dt"].substring(0, 10)}" style="width: 100%; padding: 3px; font-size: 13px;">`;
              } else {
                tabCell.innerHTML = `<input type="text" value="${items[i][this.col2[j]]
                  }" style="width: 100%; padding: 3px; font-size: 13px;">`;
              }
            } else {
              tabCell.innerHTML = items[i][this.col2[j]];
            }
          } else {
            // 수정 불가능한 경우
            tabCell.innerHTML = items[i][this.col2[j]];
          }

          // 정렬하기
          let attr = "";
          if (this.col3[j] == "c") {
            attr += "text-align: center;";
          } else if (this.col3[j] == "l") {
            attr += "text-align: left;";
          } else if (this.col3[j] == "r") {
            attr += "text-align:right;";
          } else if (this.col3[j] == "d") {
            attr += "text-align:center;";
            if (tabCell != null && tabCell.innerHTML.length > 10)
              tabCell.innerHTML = tabCell.innerHTML.substr(0, 10);
          }
          tabCell.setAttribute("style", attr);
          // 셀에 링크 추가
          tabCell.innerHTML = this.constructor.setLink(items, i, j, tabCell);
        } // column

      } catch (ex) {
        console.log(ex.message);
      }
    }
    // FINALLY ADD THE NEWLY CREATED TABLE WITH JSON DATA TO A CONTAINER.
    let divContainer = document.getElementById("tables");
    divContainer.appendChild(table1);

    // 이벤트 위임 방식으로 클릭 이벤트 바인딩
    $(document).off('click', 'table.type10 tbody tr').on('click', 'table.type10 tbody tr', function () {
      // 모든 tr에서 selected-row 제거
      document.querySelectorAll('.type10 tbody tr').forEach(tr => {
        tr.classList.remove('selected-row');
      });
      // 클릭한 tr에만 selected-row 추가
      $(this).addClass('selected-row');
      console.log('선택된 행:', $(this).index());
    });

    if (gIncreate_dt >= invent_dt) {
      this.updateInventoryInput_init();
    }
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

  // Grid 에서 생산수량 계산
  updateInventoryInput(event, idx) {
    console.log("updateInventoryInput");

    // 입력값 처리 및 숫자 변환
    const inputValue = event.target.value.replace(/,/g, "") || "0";
    const cProd_quantity = parseFloat(inputValue) || 0; // 일일 생산량

    // 입점수량 처리
    const rawQuantity = items[idx][this.col2[5]]?.replace(/,/g, "") || "0";
    const cQuantity = parseFloat(rawQuantity) || 0;

    // 추가수량 처리
    const rawQuantityNew =
      items[idx][this.col2[6]]?.replace(/[,\s]/g, "") || "0";
    const cQuantity_new = parseFloat(rawQuantityNew) || 0;
    console.log("cQuantity_new", cQuantity_new);

    // 재고수량 처리
    const rawInventory =
      items[idx][this.col2[11]]?.replace(/[,\s]/g, "") || "0";
    let invent_Quantity = parseFloat(rawInventory) || 0;

    // 생산중량 계산
    const prodName = items[idx][this.col2[4]] || ""; // 상품명
    let weightOutKg = 0;

    // 수정된 정규식 패턴으로 무게 추출
    const tempStr = prodName.split("-")[1]?.trim() || "";
    if (tempStr) {
      let weight = 0;
      let weightStr = "";

      if (tempStr.includes("kg")) {
        weightStr = tempStr.split("kg")[0].trim().split("(")[0].trim();
        weight = parseFloat(weightStr) || 0;
        weightOutKg = weight * cProd_quantity;
      } else if (tempStr.includes("g")) {
        weightStr = tempStr.split("g")[0].trim().split("(")[0].trim();
        weight = parseFloat(weightStr) || 0;
        weightOutKg = (weight * cProd_quantity) / 1000;
      }
    }

    // 출고중량 업데이트
    try {
      const updatetable = document.getElementById("specData");
      if (updatetable) {
        const updateRow = updatetable.rows[idx + 1];
        if (updateRow) {
          const weightOutCell = updateRow.cells[9];
          if (weightOutCell) {
            weightOutCell.textContent = weightOutKg.toFixed(2);
          }
        }
      }
    } catch (error) {
      console.error("출고중량 업데이트 중 에러:", error);
    }

    // 재고수량 계산
    // 모든 값이 숫자임을 보장
    const newInventQuantity =
      (cProd_quantity || 0) -
      (cQuantity || 0) -
      (cQuantity_new || 0) +
      (invent_Quantity || 0);

    // 계산 결과 로깅
    console.log("cProd_quantity", cProd_quantity);
    console.log("cQuantity", cQuantity);
    console.log("cQuantity_new", cQuantity_new);
    console.log("current invent_Quantity", invent_Quantity);
    console.log("new invent_Quantity", newInventQuantity);

    // 재고수량 업데이트
    try {
      const table = document.getElementById("specData");
      if (table) {
        const row = table.rows[idx + 1];
        if (row) {
          const cell = row.cells[12];
          if (cell) {
            const inputElement = cell.querySelector("input");
            if (inputElement) {
              inputElement.value = isNaN(newInventQuantity)
                ? 0
                : newInventQuantity;
            }
          }
        }
      }
    } catch (error) {
      console.error("재고량 업데이트 중 에러:", error);
    }
  }

  // 생산중량 초기화 :: 생산중량==0 인 경우만 진행
  updateInventoryInput_init() {
    const table = document.getElementById("specData");
    const rows = table.rows;

    // 생산중량이 0.0인 행만 처리
    const rowsWithZeroWeight = Array.from(rows)
      .slice(1)
      .filter((row) => {
        const weightOutCell = row.cells[9];
        return parseFloat(weightOutCell.textContent) === 0;
      });

    rowsWithZeroWeight.forEach((row) => {
      const cQuantity = parseFloat(row.cells[5].textContent.replace(/,/g, "")); // 입점수량
      const cQuantity_new = parseFloat(
        row.cells[6].textContent.replace(/,/g, "")
      ); // 추가수량

      // 생산수량 추출
      let cProd_quantity = 0;
      const prodQuantityCell = row.cells[8];
      const prodQuantityInput = prodQuantityCell.querySelector("input");
      if (prodQuantityInput) {
        cProd_quantity = parseFloat(prodQuantityInput.value.replace(/,/g, ""));
      } else {
        cProd_quantity = parseFloat(
          prodQuantityCell.textContent.replace(/,/g, "")
        );
      }

      // 생산중량 계산
      const prodName = row.cells[4].textContent; // 상품명
      // 수정된 정규식: 하이픈(-) 이후의 숫자와 단위를 찾고, 괄호나 다른 문자가 있어도 매칭
      const weightMatch = prodName.match(
        /-\s*(\d+(?:\.\d+)?)\s*(kg|g)(?:\s*\(|$)/i
      );

      let weightOutKg = 0;
      if (weightMatch) {
        const weight = parseFloat(weightMatch[1]); // 숫자 부분 (예: 400)
        const unit = weightMatch[2].toLowerCase(); // 단위 부분 (예: g)

        if (unit === "kg") {
          weightOutKg = weight * cProd_quantity;
        } else if (unit === "g") {
          weightOutKg = (weight * cProd_quantity) / 1000;
        }
      }

      // 출고중량 계산 및 업데이트
      const weightOutCell = row.cells[9]; // 생산중량 셀
      weightOutCell.textContent = weightOutKg.toFixed(2); // 소수점 둘째자리까지 표시
    });
  }

  // 저장
  saveInventory() {
    const increate_dt = document.getElementById("createDate").value; // 화면상의 생산일자 가져오기
    const data = {
      barcodeList: barcodeList,
    };

    const url =
      "/wp-content/plugins/gscodeOut/gscodeOut_srv.php?category=saveInvt&increate_dt=" +
      increate_dt +
      "&user_id=" +
      user_id;
    console.log("[gscodeOut_srv.php]", url);

    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    })
      .then((response) => response.json())
      .then((result) => {
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
  // mobile
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
    if (!document.getElementById('mobile-table-style')) {
      const styleElement = document.createElement('style');
      styleElement.id = 'mobile-table-style';
      styleElement.textContent = `
              .product-box {
                  margin: 8px;
                  padding: 12px;
                  background: #fff;
                  border-radius: 10px;
                  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                  border: 1px solid #ddd;
                  cursor: pointer;
                  transition: all 0.3s ease;
              }
              .product-box.selected {
                  background: #e3f2fd;
                  border: 1px solid #2196F3;
                  box-shadow: 0 4px 8px rgba(33,150,243,0.2);
              }
              .prod-name {
                  font-size: 17px;
                  font-weight: bold;
                  color: #2196F3;
                  margin-bottom: 8px;
              }
              .prod-details {
                  font-size: 13px;
                  color: #555;
                  margin-bottom: 6px;
                  line-height: 1.4em;
              }
              .prod-footer {
                  font-size: 0.85em;
                  color: #888;
                  margin-top: 10px;
                  border-top: 1px solid #eee;
                  padding-top: 6px;
              }
              .label {
                  font-weight: bold;
                  color: #444;
              }
          `;
      document.head.appendChild(styleElement);
    }

    if (!items || items.length === 0) {
      container.innerHTML = `
              <div class="no-data-box">
                  <div class="no-data-message">데이터 없음</div>
              </div>
          `;
      return;
    }

    items.forEach((item, idx) => {
      const index = idx + 1;
      const box = document.createElement('div');
      box.className = 'product-box';
      box.dataset.index = index;

      // 클릭 이벤트 추가
      box.addEventListener('click', (e) => {
        // 이전 선택된 카드의 선택 상태 제거
        document.querySelectorAll('.product-box.selected').forEach(box => {
          box.classList.remove('selected');
        });
        // 현재 카드 선택
        box.classList.add('selected');
      });

      box.innerHTML = `
                <div class="prod-name">[${index}] ${item.store_no} - <span style="color:red;">${item.prod_nm}</span></div>
                <hr style="border: 1px solid #ddd; margin: 5px 0;">
                <div class="prod-details">
                    <div><span class="label">납품일:</span> ${item.supply_dt || '-'}</div>
                    <div><span class="label">주문량:</span> ${item.quantity || '0'} / <span class="label">추가량:</span> ${item.quantity_new || '0'}</div>
                    <div><span class="label">생산량:</span> ${item.prod_quantity || '-'}</div>
                    <div><span class="label">입고Kg:</span> ${item.weight_in || '-'} / <span class="label">출고Kg:</span> ${item.weight_out || '-'}</div>
                    <div><span class="label" style="color:red; font-weight:bold; font-size:15px;">요율: ${item.prod_yield || '-'}%</span></div>
                    <div><span class="label">재고량:</span> ${item.invent_quantity || '0'}</div>
                    <div><span class="label">비고:</span> ${item.invent_note || '-'}</div>
                </div>
                <div class="prod-footer">점포코드: ${item.store_code || '-'} / 상품코드: ${item.prod_code || '-'}</div>
            `;

      container.appendChild(box);
    });
    idx = items.length;
  }


  // 모바일에서 생산수량 계산(모바일생략)
  updateInventory(idx) {
    const cProd_quantity = parseFloat(
      document.getElementById(`total-${idx}`).value.replace(/,/g, "")
    );

    const cQuantity = parseFloat(
      items[idx - 1][this.col2[5]].replace(/,/g, "")
    ); // 주문수량
    const cQuantity_new = parseFloat(
      items[idx - 1][this.col2[6]].replace(/,/g, "")
    ); // 추가수량
    let invent_Quantity = parseFloat(
      items[idx - 1][this.col2[11]].replace(/,/g, "")
    ); // 재고수량
    // let reason = document.getElementById(`reason-${idx}`).value;

    // 재고수량 = 생산수량 - 주문수량 - 추가수량 + (기존)재고수량
    invent_Quantity =
      cProd_quantity - cQuantity - cQuantity_new + invent_Quantity;

    document.getElementById(`prev_invent_quantity-${idx}`).value =
      invent_Quantity;
  }
} // class end

// 이벤트 리스너가 클래스 메서드를 참조할 수 있도록 전역 함수로 정의
function updateInventory(idx) {
  const instance = new gscodeOutClass();
  instance.updateInventory(idx);
}

// updateInventoryInput 함수를 전역 함수에서 클래스 메서드로 변경
function updateInventoryInput(event, i, col2Str, itemsStr, j) {
  const instance = new gscodeOutClass();
  instance.updateInventoryInput(event, i, col2Str, itemsStr, j);
}

// barcodeList 에 저장
function saveBarcodeList() {
  // 모바일 모드 일때
  // const listItems = document.querySelectorAll(".list-item");
  // if (listItems.length > 0) {
  //   saveMobileBarcodeList();
  //   return;
  // }

  // console.log("saveBarcodeList");
  const table = document.getElementById("specData");
  const rows = table.rows;

  for (let i = 1; i < rows.length; i++) {
    // 첫 번째 행은 헤더이므로 건너뜀
    const cells = rows[i].cells;
    const weightInInput = cells[7].querySelector("input"); // 입고중량
    const prodQuantityInput = cells[8].querySelector("input"); // 생산수량
    const weightOut = parseFloat(cells[9].textContent); // 출고중량
    const prodYield = parseFloat(cells[10].textContent); // 생산수율
    const inventQuantityInput = cells[12].querySelector("input"); //재고수량
    const reasonInput = cells[13].querySelector("input"); // 조정사유

    if (weightInInput) {
      const weightIn = parseFloat(weightInInput.value);
      barcodeList[i - 1]["weight_in"] = weightIn; // 입고중량
    }
    barcodeList[i - 1]["weight_out"] = weightOut; // 출고중량
    barcodeList[i - 1]["prod_yield"] = prodYield; // 생산수율

    if (prodQuantityInput && inventQuantityInput && reasonInput) {
      const prodQuantity = parseFloat(prodQuantityInput.value); // 생산수량
      const inventQuantity = parseFloat(inventQuantityInput.value); // 재고수량
      const reason = reasonInput.value; // 조정사유
      barcodeList[i - 1]["prod_quantity"] = prodQuantity;
      barcodeList[i - 1]["invent_quantity"] = inventQuantity;
      barcodeList[i - 1]["invent_note"] = reason;
    }
  }

  codeOutClass.saveInventory();
  console.log("Updated barcodeList:", barcodeList);
}

// 모바일은 생략
function saveMobileBarcodeList() {
  const listItems = document.querySelectorAll(".list-item");

  listItems.forEach((item, index) => {
    const prodQuantityInput = item.querySelector(`#total-${index + 1}`);
    const inventQuantityInput = item.querySelector(
      `#prev_invent_quantity-${index + 1}`
    );
    const reasonInput = item.querySelector(`#reason-${index + 1}`);

    if (prodQuantityInput && inventQuantityInput && reasonInput) {
      const prodQuantity = parseFloat(prodQuantityInput.value);
      const inventQuantity = parseFloat(inventQuantityInput.value);
      const reason = reasonInput.value;

      barcodeList[index]["prod_quantity"] = prodQuantity;
      barcodeList[index]["invent_quantity"] = inventQuantity;
      barcodeList[index]["invent_note"] = reason;
    }
  });

  codeOutClass.saveInventory();
  console.log("Updated barcodeList:", barcodeList);
}
