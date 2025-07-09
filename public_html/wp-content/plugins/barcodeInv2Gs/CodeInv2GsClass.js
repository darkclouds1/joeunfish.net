class CodeInv2GsClass {
  constructor({
    col = [
      "no",
      "품명",
      "상품코드",
      "원산지",
      "단위",
      "수량",
      "단가",
      "금액",
      "박스",
    ],
    col2 = [
      "no",
      "품명",
      "상품코드",
      "원산지",
      "단위",
      "수량",
      "단가",
      "금액",
      "박스",
    ],
    col3 = ["c", "c", "c", "c", "c", "r", "n", "n", "n"],
    colw = ["5%", "15%", "15%", "10%", "10%", "10%", "10%", "10%", "10%"],
    barcodeList = [],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    this.barcodeList = barcodeList;
    this.updateBarcodeList = this.updateBarcodeList.bind(this);
  }
  // Apt :: 더보기 헤드 구성
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
    for (var i = 0; i < this.col.length; i++) {
      var th = document.createElement("th"); // TABLE HEADER.
      th.innerHTML = this.col[i] +'<a onclick="sortTD (' + i + ')">▲</a><a onclick="reverseTD (' + i + ')">▼</a>';
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
    // JSON 데이터 파싱
    let cleanData = datas.replace(/[\u0000-\u001F\u007F-\u009F]/g, "");
    let data = JSON.parse(cleanData);
    let items = data.response.body.items;
    let rtnCnt = items.length;

    console.log(rtnCnt);

    // 데이터 없음 처리
    if (items.length == 0) {
      alert("데이터가 없습니다. 조회 방법을 확인하세요.");
      return rtnCnt;
    }

    // 테이블 생성
    let table1 = document.createElement("table");
    table1.setAttribute("class", "type10 type11");
    table1.setAttribute("id", "specData");
    table1.setAttribute("style", "width:100%");

    // 헤더 생성
    let header = table1.createTHead();
    let trHead = header.insertRow(-1);
    for (let i = 0; i < this.col.length; i++) {
      let th = document.createElement("th");
      th.innerHTML = this.col[i];
      th.setAttribute("style", "width:" + this.colw[i] + ";");
      trHead.appendChild(th);
    }

    // 바디 생성
    let tbody = table1.createTBody();
    let totalQuantity = 0; // 수량 합계 변수 추가
    for (let i = 0; i < items.length; i++) {
      let tr = tbody.insertRow(-1);
      for (let j = 0; j < this.col2.length; j++) {
        let tabCell = tr.insertCell(-1);
        let cellValue = items[i][this.col2[j]] ?? "";

        // 정렬 스타일
        let attr = "";
        if (this.col3[j] == "c") attr += "text-align:center;";
        else if (this.col3[j] == "l") attr += "text-align:left;";
        else if (this.col3[j] == "r") attr += "text-align:right;";
        else if (this.col3[j] == "n") attr += "text-align:right;";

        // 숫자(n) 타입이면 3자리마다 콤마 추가
        if (this.col3[j] == "n" && !isNaN(cellValue) && cellValue !== "") {
          cellValue = Number(cellValue).toLocaleString();
        }

        tabCell.innerHTML = cellValue;
        tabCell.setAttribute("style", attr);

        // 수량 합계 계산 (수량 컬럼일 때)
        if (this.col2[j] === "수량") {
          let qty = parseFloat(items[i][this.col2[j]]);
          if (!isNaN(qty)) totalQuantity += qty;
        }
      }
    }

    // 합계 row 추가
    let sumTr = tbody.insertRow(-1);
    // 합계 row의 배경색을 노란색으로 지정
    sumTr.style.backgroundColor = "yellow";
    // 첫 번째 row의 단가를 사용 (여러 row의 단가가 다르면 평균 등으로 바꿀 수 있음)
    let unitPrice = 0;
    if (items.length > 0 && items[0]["단가"]) {
      unitPrice = parseFloat(items[0]["단가"]) || 0;
    }
    for (let j = 0; j < this.col2.length; j++) {
      let sumCell = sumTr.insertCell(-1);
      let attr = "";
      if (this.col3[j] == "c") attr += "text-align:center;";
      else if (this.col3[j] == "l") attr += "text-align:left;";
      else if (this.col3[j] == "r") attr += "text-align:right;";
      else if (this.col3[j] == "n") attr += "text-align:right;";

      let value = "";
      if (j === 0) {
        value = "합계";
      } else if (this.col2[j] === "수량") {
        value = totalQuantity;
      } else if (this.col2[j] === "단가") {
        value = unitPrice;
      } else if (this.col2[j] === "금액") {
        value = totalQuantity * unitPrice;
      } else {
        value = "";
      }

      // col3이 n(숫자)일 때 3자리마다 콤마 추가
      if (this.col3[j] == "n" && !isNaN(value) && value !== "") {
        value = Number(value).toLocaleString();
      }

      sumCell.innerHTML = value;
      sumCell.setAttribute("style", attr);
    }

    // 기존 테이블 삭제 후 새로 추가
    let divContainer = document.getElementById("tables");
    divContainer.innerHTML = "";
    divContainer.appendChild(table1);
    idx = rtnCnt;   // total record=
    return rtnCnt;
  }

  // --------------------------
  // barcodeIn- mobile
  // --------------------------
  makeTabletr2Mobile(datas, store_no) {
    // console.log(store_no);

    let cleanData = datas.replace(/[\u0000-\u001F\u007F-\u009F]/g, "");
    let data = JSON.parse(cleanData);
    items = data.response.body.items;

    this.barcodeList = [];

    //if (items.length>1) items.sort(custonSort);
    document.getElementById("tables").innerHTML = "";
    idx = 0;
    var licn = ""; // HTML 문자열을 저장할 변수 초기화
    for (var i = 0; i < items.length; i++) {
      idx = idx + 1;

      let newItem = {
        no: idx,
        supply_dt: items[i]["납품일"],
        dest_code: items[i]["점포코드"],
        product_nm: items[i]["품명"],
        product_code: items[i]["상품코드"],
        origin: items[i]["원산지"],
        unit: items[i]["단위"],
        quantity: items[i]["수량"],
        unit_price: items[i]["단가"],
        amount: items[i]["금액"],
        box: items[i]["박스"],
        quantity_new: items[i]["수량추가"],
      };

      this.barcodeList.push(newItem);

      licn += `<li class="list-item" id="item-no${idx}" style="border: 1px solid #ccc; padding: 3px; margin-bottom: 10px;">
              <div class="content">
                  <span class="item">[${idx}] <span style="color: #000;">[납품점] ${items[i]["점포코드"]}</span> 
                          <span style="color: #000;">[품명] ${items[i]["품명"]}</span> 
                          <span style="color: #000;">[상품코드] ${items[i]["상품코드"]}</span> 
                          [원산지] ${items[i]["원산지"]} [단위] ${items[i]["단위"]} [단가] ${items[i]["단가"]} [금액] ${items[i]["금액"]} [박스] ${items[i]["박스"]}</span>
                  <div class="item-details">
                        [입점수량] <span style="color: red; display: inline-block; width: 50px;">${items[i]["수량"]}</span> 
                        [수량추가] <input type="text" id="quantity-new-${idx}" style="width: 15ch; height: 1.7em;" value="${items[i]["수량추가"]}" onblur="updateBarcodeList(${idx}, 'quantity_new', this.value)" maxlength="5" oninput="this.value = this.value.replace(/[^0-9-]/g, '')">
                        <div class="item-spacing" style="margin-right: 3px;"></div>                      </div>
              </div>
            </li>`;
      document.getElementById("tables").innerHTML = licn;

      // 이벤트 리스너 추가
      this.barcodeList.forEach((item, i) => {
        let idx = i + 1;
        let quantityElement = document.getElementById(`quantity-new-${idx}`);
        if (quantityElement) {
          // 기존 이벤트 리스너 제거
          quantityElement.onblur = null;
          quantityElement.addEventListener("blur", (e) =>
            this.updateBarcodeList(idx, "quantity_new", e.target.value)
          );
        }
      });
    }
    // console.log(barcodeList);
  }

  // 입점수량 추가
  updateBarcodeList(idx, key, value) {

    // console.log("업데이트 중 오류 발생: ");
    if (!this.barcodeList[idx - 1]) {
      console.error(`Invalid index: ${idx}`);
      return;
    }

    // 기존 값과 비교
    if (this.barcodeList[idx - 1][key] == value) {
      console.log("기존 값과 동일합니다.");
    } else {
      this.barcodeList[idx - 1][key] = value;

      // db 업데이트
      console.log(
        `/wp-content/plugins/barcodeInv2Gs/barcodeInv2Gs_srv.php?category=update&key=${key}&value=${value}&user_id=${user_id}`
      );

      fetch(
        `/wp-content/plugins/barcodeInv2Gs/barcodeInv2Gs_srv.php?category=update&user_id=${user_id}`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            key: key,
            value: value,
            barcodeList: this.barcodeList[idx - 1], // barcodeList를 포함하여 전송
          }), // JSON 데이터 전송
        }
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            console.log("업데이트 성공: " + data.message);
          } else {
            alert("업데이트 실패: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("업데이트 중 오류 발생...: " + error.message);
        });
    }
  }
} // class end

// 이벤트 리스너가 클래스 메서드를 참조할 수 있도록 전역 함수로 정의
function updateBarcodeList(idx, key, value) {
  const instance = new CodeInv2GsClass();
  instance.updateBarcodeList(idx, key, value);
}

// 엑셀 변환
function tableToExcelInv(
  tableId = "specData",
  name = "joeunfish",
  filename = "joeunfish"
) {
  let uri =
      "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,",
    template =
      '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head><body><table>{table}</table></body></html>',
    base64 = function (s) {
      return window.btoa(unescape(encodeURIComponent(s)));
    },
    format = function (s, c) {
      return s.replace(/{(\w+)}/g, function (m, p) {
        return c[p];
      });
    };
  let tableElement = document.getElementById(tableId);
  if (!tableElement) {
    alert(
      "조회된 테이블이 없습니다. 조회 후 다운하시기 바랍니다.:: " + tableId
    );
    return;
  }
  // 테이블의 첫 번째 행(보통 헤더)을 제외하고 복제
  let clonedTable = tableElement.cloneNode(true);
  if (clonedTable.tHead) {
    // 테이블 헤더가 있는 경우
    // clonedTable.deleteRow(0);
    Array.from(clonedTable.tHead.querySelectorAll("th")).forEach((th) => {
      th.innerHTML = th.innerHTML.replace(/▲|▼/g, ""); // '▲'와 '▼' 제거
    });
  }
  // 모든 셀을 순회하며 데이터를 텍스트로 변환
  Array.from(clonedTable.querySelectorAll("td")).forEach((td, index) => {
    // 6번째 컬럼(td)의 값을 텍스트로 변환, index는 0부터 시작
    if (index % clonedTable.rows[0].cells.length === 6) {
      td.textContent = `'${td.textContent}`;
    } else {
      td.textContent = td.textContent;
    }
  });
  // 엑셀 파일 생성
  const createDate = document.getElementById("createDate").value;
  // let lcNumber = document.getElementById("lcNumber").value;
  let storeNoElement = document.getElementById("storeNo");
  const store_no = storeNoElement.value; // 선택된 옵션의 value
  let selectedOptionText =
    storeNoElement.options[storeNoElement.selectedIndex].text; // 선택된 옵션의 전체 텍스트
  let store_nm = selectedOptionText.substring(0, 3); // 선택된 텍스트의 앞 세 자리
  // if (lcNumber === "") lcNumber = "0000";
  if (store_no === "") store_nm = "전체";
  name = createDate + "_" + store_nm;
  var ctx = {
    worksheet: name || "Worksheet",
    table: clonedTable.innerHTML,
  };
  var link = document.createElement("a");
  link.download = filename + "_" + name + ".xls";
  link.href = uri + base64(format(template, ctx));
  document.body.appendChild(link); // Firefox에서 필요함
  link.click();
  document.body.removeChild(link); // 생성된 링크 요소 제거
  alert(
    "filename= '" +
      filename +
      "_" +
      name +
      "'로 다운로드 폴더에 저장되었습니다."
  );
}

// Invoice 출력
function invoice2GsPrint(deliveryDate, storeCode) {
  // 납품점 코드(store_no)와 납품일자 확인
  if (!storeCode.trim() || !deliveryDate) {
    alert("납품일자와 납품점 코드를 모두 입력해 주세요.");
    return;
  }

  const url = `/wp-content/plugins/barcodeInv2Gs/invoice2GsPrint_srv.php?deliveryDate=${encodeURIComponent(deliveryDate)}&storeCode=${encodeURIComponent(storeCode)}`;
  console.log(url);
  $.ajax({
    url: url,
    type: "GET",
    beforeSend: function () {
      console.log("송장 인쇄 요청을 서버로 전송 중입니다.");
    },
    success: function (response) {
      // console.log(response);
      try {
        const printWindow = window.open("", "_blank");
        if (printWindow) {
          printWindow.document.write(response);
          printWindow.document.close(); // 문서 작성 완료
          printWindow.focus();

          // 프린트 다이얼로그가 닫힐 때까지 기다린 후 창을 닫음
          printWindow.onafterprint = function () {
            // printWindow.close();
          };

          printWindow.print();
        } else {
          alert("팝업 차단을 해제하고 다시 시도해 주세요.");
        }
      } catch (e) {
        console.error("Error processing response: " + e.message);
        alert("응답 처리 중 오류가 발생했습니다. 다시 시도해 주세요.");
      }
    },
    error: function (xhr, status, error) {
      console.error("송장 출력 중 오류가 발생했습니다: " + error);
      alert("지금은 주문출력할 수 없습니다. 개발중입니다.");
    },
  });
}

// 주문 출력
function orderPrint(deliveryDate, storeCode) {
  // 납품점 코드(store_no)와 납품일자 확인
  if (!deliveryDate) {
    alert("납품일자와 납품점 코드를 모두 입력해 주세요.");
    return;
  }
  const url = `/jof/datas/orderPrintGs_srv.php?deliveryDate=${encodeURIComponent(
    deliveryDate
  )}&storeCode=${encodeURIComponent(storeCode)}`;
  console.log(url);
  $.ajax({
    url: url,
    type: "GET",
    beforeSend: function () {
      console.log("송장 인쇄 요청을 서버로 전송 중입니다.");
    },
    success: function (response) {
      // console.log(response);
      const printWindow = window.open("", "_blank");
      if (printWindow) {
        printWindow.document.write(`
          <html>
            <head>
              <style>
                @page {
                  size: A4 landscape;
                  margin: 10mm;
                }
                body {
                  font-family: Arial, sans-serif;
                  font-size: 12px;
                }
                table {
                  width: 100%;
                  border-collapse: collapse;
                }
                th, td {
                  border: 1px solid black;
                  padding: 8px;
                  text-align: center;
                }
                th {
                  background-color: #f2f2f2;
                }
              </style>
            </head>
            <body>
              ${response}
            </body>
          </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        // printWindow.close(); // 주석을 제거하여 창을 닫습니다.
      } else {
        alert("팝업 차단을 해제하고 다시 시도해 주세요.");
      }
    },
    error: function (xhr, status, error) {
      console.error("송장 출력 중 오류가 발생했습니다: " + error);
      alert("송장을 출력할 수 없습니다. 다시 시도해 주세요.");
    },
  });
}

function summaryPrint(deliveryDate) {
  if (!deliveryDate) {
    alert("납품일자와 납품점 코드를 모두 입력해 주세요.");
    return;
  }
  const url = `/jof/datas/summaryPrint_srv.php?category=summaryPrint&deliveryDate=${encodeURIComponent(
    deliveryDate
  )}`;
  console.log(url);
  $.ajax({
    url: url,
    type: "GET",
    beforeSend: function () {
      console.log("송장 인쇄 요청을 서버로 전송 중입니다.");
    },
    success: function (response) {
      // console.log(response);
      const printWindow = window.open("", "_blank");
      if (printWindow) {
        printWindow.document.write(response);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        // printWindow.close(); // 주석을 제거하여 창을 닫습니다.
      } else {
        alert("팝업 차단을 해제하고 다시 시도해 주세요.");
      }
    },
    error: function (xhr, status, error) {
      console.error("납품 총괄 출력 중 오류가 발생했습니다: " + error);
      alert("납품 총괄표를 출력할 수 없습니다. 다시 시도해 주세요.");
    },
  });
}
function updateWeights(idx, value) {
  // 기존 값과 비교
  let oldValue = items[idx - 1]["weights"] || "";
  if (value === oldValue) return;
  // 값 정리 (쉼표로 구분된 숫자 목록으로 가정)
  let weights = value
    .split(",")
    .map((w) => parseFloat(w.trim()))
    .filter((w) => !isNaN(w));
  // 정리된 값으로 텍스트 박스 업데이트
  let cleanValue = weights.join(", ");
  document.getElementById(`weights-${idx}`).value = cleanValue;
  // 합계 계산
  let total = weights.reduce((sum, w) => sum + w, 0);
  // 입점수량 업데이트
  document.getElementById(`quantity-${idx}`).value = total.toFixed(1);
  // items 배열 업데이트
  items[idx - 1]["weights"] = cleanValue;
  items[idx - 1]["quantity"] = total.toFixed(1);
  // updateItem 함수 호출 (기존 함수라고 가정)
  updateItem(idx, "weights", total.toFixed(1));
}

function toggleInput(idx, key) {
  const checkbox = document.getElementById(`${key}-checkbox-${idx}`);
  const input = document.getElementById(`${key}-${idx}`);

  if (checkbox.checked) {
    // 체크된 경우 입점수량을 null로 설정하고 기존 수량을 저장
    input.dataset.originalValue = input.value;
    input.value = "";
    items[idx - 1][key] = null;
  } else {
    // 체크 해제된 경우 저장된 수량을 복원
    input.value =
      input.dataset.originalValue || items[idx - 1]["quantity"] || "";
  }
}
