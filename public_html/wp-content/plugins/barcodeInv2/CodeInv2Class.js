class CodeInvClass {
  constructor({
    col = [
      "no",
      "납품일",
      "납품점",
      "PO#",
      "ITEM#",
      "상품명",
      "단위",
      "입점수량",
      "개별중량",
      "소비기한",
      "원산지",
      "단가",
      "금액",
    ],
    col2 = [
      "no",
      "supply_dt",
      "region",
      "PO",
      "ITEM",
      "product_name",
      "unit",
      "quantity",
      "weights",
      "expi_date",
      "country_origin",
      "unit_price",
      "total_price",
    ],
    col3 = ["c", "c", "c", "c", "c", "l", "c", "c", "c", "c", "c", "r", "r"],
    colw = [
      "3%",
      "7%",
      "10%",
      "10%",
      "7%",
      "13%",
      "5%",
      "5%",
      "10%",
      "7%",
      "7%",
      "7%",
      "7%",
    ],
    barcodeList = [],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    this.barcodeList = barcodeList;
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
      // check 찜에 사용
      if (this.col[i] == "check")
        th.innerHTML =
          '<input type="checkbox" onclick="javascript:CheckAll(\'' +
          chkid +
          "')\">";
      // 소트 sort  문자 안보이게
      switch (i) {
        case 1: //
          break;
        default:
      }
      th.innerHTML =
        this.col[i] +
        '<a onclick="sortTD (' +
        i +
        ')">▲</a><a onclick="reverseTD (' +
        i +
        ')">▼</a>';
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
    // ADD JSON DATA TO THE TABLE AS ROWS.
    let cleanData = datas.replace(/[\u0000-\u001F\u007F-\u009F]/g, "");
    let data = JSON.parse(cleanData);
    items = data.response.body.items;
    rtnCnt = items.length;
    // 데이터 없음
    if (items.length == 0) {
      alert("데이터가 없습니다. 조회 방법을 확인하세요.");
      return rtnCnt;
    }
    let tbody = table1.createTBody();
    for (let i = 0; i < items.length; i++) {
      try {
        idx = idx + 1;
        let tr = tbody.insertRow(-1);
        let tabCells = tr.insertCell(-1);
        // tabCells.innerHTML = idx;
        tabCells.innerHTML = items[i][this.col2[0]];
        tabCells.setAttribute("style", "text-align:center;");
        for (let j = 1; j < this.col.length; j++) {
          let tabCell = tr.insertCell(-1);
          tabCell.innerHTML = items[i][this.col2[j]];
          if (j == 5) {
            let product_name = items[i][this.col2[j]];
            tabCell.innerHTML = product_name;
          }
          // 정렬하기
          let attr = "";
          if (this.col3[j] == "c") attr += "text-align:center;";
          else if (this.col3[j] == "l") attr += "text-align:left;";
          else if (this.col3[j] == "r") attr += "text-align:right;";
          // if (tabCell != null) tabCell.innerHTML = tabCell.innerHTML.format();
          else if (this.col3[j] == "d") {
            // date
            attr += "text-align:center;";
            if (tabCell != null && tabCell.innerHTML.length > 10)
              tabCell.innerHTML = tabCell.innerHTML.substr(0, 10);
          }
          tabCell.setAttribute("style", attr);
          // 셀에 링크 추가
          tabCell.innerHTML = CodeInvClass.setLink(items, i, j, tabCell);
        } // column
      } catch (ex) {
        console.log(ex.message);
      }
    }
    // FINALLY ADD THE NEWLY CREATED TABLE WITH JSON DATA TO A CONTAINER.
    let divContainer = document.getElementById("tables");
    //divContainer.innerHTML = "";
    divContainer.appendChild(table1);
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
  // --------------------------
  // barcodeIn- mobile
  // --------------------------
  makeTabletr2Mobile(datas) {
    // console.log(datas);
    let cleanData = datas.replace(/[\u0000-\u001F\u007F-\u009F]/g, "");
    let data = JSON.parse(cleanData);
    items = data.response.body.items;
    barcodeList = [];
    //if (items.length>1) items.sort(custonSort);
    document.getElementById("tables").innerHTML = "";
    idx = 0;
    var licn = ""; // HTML 문자열을 저장할 변수 초기화

    for (var i = 0; i < items.length; i++) {
      idx = idx + 1;
      // 납품일이 월요일이면 소비기한을 전체 -1day로 변경
      let supplyDate = new Date(items[i]["납품일"]);
      if (supplyDate.getDay() === 1) {
        // 월요일 체크
        items[i]["소비기한"] = new Date(items[i]["소비기한"]);
        items[i]["소비기한"].setDate(items[i]["소비기한"].getDate() - 1);
        items[i]["소비기한"] = items[i]["소비기한"].toISOString().split("T")[0]; // 날짜 형식으로 변환
      }
      newItem = {
        no: idx,
        supply_dt: items[i]["supply_dt"],
        region: items[i]["region"],
        po: items[i]["PO"],
        product_code: items[i]["ITEM"],
        product_nm: items[i]["product_name"],
        unit: items[i]["unit"],
        quantity: parseFloat(items[i]["quantity"]),
        expi_date: items[i]["expi_date"],
        country: items[i]["country_origin"],
        unit_price: parseFloat(items[i]["unit_price"]),
        total_price: parseFloat(items[i]["total_price"]),
        weights: items[i]["weights"],
        temp_code: items[i]["temp_code"],
      };
      barcodeList.push(newItem);

      // 납품점 목록을 배열로 정의
      const specialRegions = ["대구", "혁신", "부산", "울산", "김해"];

      // 납품점이 특정 목록에 포함되는지 확인하는 함수
      function isSpecialRegionWithTempCode(region, tempCode) {
        return (
          specialRegions.some((specialRegion) =>
            region.includes(specialRegion)
          ) && tempCode === "F"
        );
      }

      // specialRegions 배열과 isSpecialRegion 함수는 이전과 동일합니다.

      // product_nm: items[i]["product_name"],
      // 품명에 "데친문어" (BP: Boiled Octopus) 가 있는 경우는 개별중량을 표시함
      // 품명에 "MAP" 가 있는 경우는 개별중량을 표시함 -by darkcloud 25/6/10 
      let bBP = false;
      if (
        items[i]["product_name"].includes("데친문어") ||
        items[i]["product_name"].includes("MAP")  
      ) {
        bBP = true;
      }

      // 데친문어 추가 (-by 먹구름름 24/9/4)
      if (bBP) {
        licn += `<li class="list-item" id="item-no${idx}" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
                  <div class="content">
                      <span class="item" style="color: red;">[${idx}] [납품점] ${items[i]["region"]
          } [PO#] ${items[i]["PO"]} 
                                [ITEM#] ${items[i]["ITEM"]} [상품명] ${items[i]["product_name"]
          } [단위] ${items[i]["unit"]} </span>
                <div class="item-details">
                [입점수량] <input type="text" id="quantity-${idx}" style="width: 15ch; height: 1.7em;" value="${items[i]["quantity"]
          }" readonly>
                <input type="checkbox" id="quantity-checkbox-${idx}" onchange="toggleInput(${idx}, 'quantity')">입점수량 미표기</br> 
                <div class="item-spacing" style="margin-right: 10px;"></div>
                [소비기한] <input type="date" id="expi_date-${idx}" value="${items[i]["expi_date"]
          }" ${isSpecialRegionWithTempCode(items[i]["region"], items[i]["temp_code"])
            ? 'data-original="' + items[i]["expi_date"] + '"'
            : ""
          }>
                <input type="checkbox" id="expi_date-checkbox-${idx}" onchange="toggleInput(${idx}, 'expi_date')" ${isSpecialRegionWithTempCode(items[i]["region"], items[i]["temp_code"])
            ? "checked"
            : ""
          }>소비기한 미표기
                  <div class="item-spacing" style="margin-right: 10px;"></div>
                  [개별중량] <input type="text" id="weights-${idx}" value="${items[i]["weights"]
          }" style="width: 70ch; height: 1.7em;"> 
                  <button type="button" style="margin-left: 10px; width: 100px; height: 2.3em;">저장</button>
                  </div>
                </div>
                </div>
            </li>`;
      } else if (
        items[i]["ITEM"] === "590654" ||
        items[i]["ITEM"] === "661212"
      ) {
        licn += `<li class="list-item" id ="item-no${idx}" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
            <div class="content">
                <span class="item" style="color: blue;">[${idx}] [납품점] ${items[i]["region"]
          } [PO#] ${items[i]["PO"]} 
                [ITEM#] ${items[i]["ITEM"]} [상품명] ${items[i]["product_name"]
          } [단위] ${items[i]["unit"]} </span>
                <div class="item-details">
                [입점수량] <input type="text" id="quantity-${idx}" style="width: 15ch; height: 1.7em;" value="${items[i]["quantity"]
          }" maxlength="5">
                <input type="checkbox" id="quantity-checkbox-${idx}" onchange="toggleInput(${idx}, 'quantity')">입점수량 미표기</br>
                <div class="item-spacing" style="margin-right: 10px;"></div>
                [소비기한] <input type="date" id="expi_date-${idx}" value="${items[i]["expi_date"]
          }" ${isSpecialRegionWithTempCode(items[i]["region"], items[i]["temp_code"])
            ? 'data-original="' + items[i]["expi_date"] + '"'
            : ""
          }>
      <input type="checkbox" id="expi_date-checkbox-${idx}" onchange="toggleInput(${idx}, 'expi_date')" ${isSpecialRegionWithTempCode(items[i]["region"], items[i]["temp_code"])
            ? "checked"
            : ""
          }>소비기한 미표기
            </div>
    </li>`;
      } else {
        licn += `<li class="list-item" id ="item-no${idx}" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
            <div class="content">
                <span class="item">[${idx}] [납품점] ${items[i]["region"]
          } [PO#] ${items[i]["PO"]} 
                [ITEM#] ${items[i]["ITEM"]} [상품명] ${items[i]["product_name"]} [단위] ${items[i]["unit"]} </span>
                <div class="item-details">
                [입점수량] <input type="text" id="quantity-${idx}" style="width: 15ch; height: 1.7em;" value="${items[i]["quantity"]
          }" maxlength="5"> 
                <input type="checkbox" id="quantity-checkbox-${idx}" onchange="toggleInput(${idx}, 'quantity')">입점수량 미표기</br> 
                <div class="item-spacing" style="margin-right: 10px;"></div>
                [소비기한] <input type="date" id="expi_date-${idx}" value="${items[i]["expi_date"]
          }" ${isSpecialRegionWithTempCode(items[i]["region"], items[i]["temp_code"])
            ? 'data-original="' + items[i]["expi_date"] + '"'
            : ""
          }>
      <input type="checkbox" id="expi_date-checkbox-${idx}" onchange="toggleInput(${idx}, 'expi_date')" ${isSpecialRegionWithTempCode(items[i]["region"], items[i]["temp_code"])
            ? "checked"
            : ""
          }>소비기한 미표기
            </div>
    </li>`;
      }
    }

    // if (lic !== "") licn = lic + licn;  //지우고 조회
    document.getElementById("tables").innerHTML = licn;
    // 이벤트 리스너 추가
    barcodeList.forEach((item, i) => {
      let idx = i + 1;
      // 입점수량
      let quantityElement = document.getElementById(`quantity-${idx}`);
      if (quantityElement) {
        quantityElement.addEventListener("blur", (e) =>
          this.updateBarcodeList(idx, "quantity", e.target.value)
        );
      }
      // 소비기한
      let expiDayElement = document.getElementById(`expi_date-${idx}`);
      if (expiDayElement) {
        expiDayElement.addEventListener("blur", (e) =>
          this.updateBarcodeList(idx, "expi_date", e.target.value)
        );
      }

      // 개별중량
      let weightsElement = document.getElementById(`weights-${idx}`);
      if (weightsElement) {
        weightsElement.addEventListener("blur", (e) =>
          this.updateWeights(idx, "weights", e.target.value)
        );
      }
    });
  }

  // 입점수량, 소비기한 外
  updateBarcodeList(idx, key, value) {

    // 기존 값과 비교
    if (barcodeList[idx - 1][key] == value) {
      return;
    }

    barcodeList[idx - 1][key] = value;
    console.log(
      "idx:" + idx + ", key:" + key + ", value:" + barcodeList[idx - 1][key]
    );

    // db 업데이트
    // return;
    console.log(
      "[ln376::]url=" +
      `/wp-content/plugins/barcodeInv2/barcodeInv2_srv.php?category=update&key=${key}&value=${value}`
    );
    fetch(
      `/wp-content/plugins/barcodeInv2/barcodeInv2_srv.php?category=update`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          key: key,
          value: value,
          barcodeList: barcodeList[idx - 1], // barcodeList를 포함하여 전송
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
        alert("업데이트 중 오류 발생: " + error.message);
      });
  }
  // 개별중량
  updateWeights(idx, key, value) {
    // 기존 값과 비교
    let oldValue = barcodeList[idx - 1]["weights"] || "";
    if (value === oldValue) return;
    // 값 정리 (캐리지 리턴값 제거, 소숫점 포함된 숫자값 정리 및 낮은 순으로 정렬)
    let cleanValue = "";
    if (value !== "") {
      let weights = value
        .split(/[\r\n,]+/)
        .map((w) => {
          let trimmed = w.trim();
          if (trimmed.includes(" ")) {
            return trimmed
              .split(/\s+/)
              .map((subW) => parseFloat(subW))
              .filter((subW) => !isNaN(subW));
          } else {
            return parseFloat(trimmed);
          }
        })
        .flat()
        .filter((w) => !isNaN(w))
        .sort((a, b) => a - b);
      // 정리된 값으로 텍스트 박스 업데이트
      cleanValue = weights.join(", ");
      // 합계 계산
    }

    // barcodeList 업데이트 하기전에 저장여부 확인
    console.log("cleanValue:" + cleanValue);
    console.log("oldValue:" + oldValue);
    if (cleanValue.trim() === "") {
      let delete_yn = confirm("개별중량을 삭제하시겠습니까?");
      if (!delete_yn) {
        document.getElementById(`weights-${idx}`).value = oldValue;
        return;
      }
    }

    document.getElementById(`weights-${idx}`).value = cleanValue;
    // 입점수량 업데이트
    let total = cleanValue
      .split(/[\s,]+/)
      .map((w) => parseFloat(w))
      .filter((w) => !isNaN(w))
      .reduce((sum, w) => sum + w, 0);
    total = Math.round(total * 100) / 100;
    if (cleanValue !== "") {
      document.getElementById(`quantity-${idx}`).value = total.toFixed(1);
      barcodeList[idx - 1]["quantity"] = total.toFixed(1);
    } else {
      document.getElementById(`quantity-${idx}`).value = "";
      barcodeList[idx - 1]["quantity"] = null;
    }
    barcodeList[idx - 1][key] = cleanValue;
    console.log("key:" + key + ", value:" + barcodeList[idx - 1][key]);
    // return;
    console.log(
      "[ln464::]url=" +
      `/wp-content/plugins/barcodeInv2/barcodeInv2_srv.php?category=weightsUpdate&key=${key}&value=${cleanValue}&user_id=${user_id}`
    );
    // db 업데이트
    fetch(
      `/wp-content/plugins/barcodeInv2/barcodeInv2_srv.php?category=weightsUpdate&user_id=${user_id}`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          key: key,
          value: cleanValue,
          barcodeList: barcodeList[idx - 1], // barcodeList를 포함하여 전송
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
        alert("업데이트 중 오류 발생: " + error.message);
      });
  }
} //  class end
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
function invoice2Print(deliveryDate, storeCode, params) {
  // 납품점 코드(store_no)와 납품일자 확인
  console.log("[ln573::]deliveryDate=" + deliveryDate);
  console.log("[ln574::]storeCode=" + storeCode);

  if (!storeCode.trim() || !deliveryDate) {
    alert("납품일자와 납품점 코드를 모두 입력해 주세요.");
    return;
  }
  const paramsArray = params.map((param) => ({
    poNumber: param.poNumber,
    itemNumber: param.itemNumber,
    quantityChecked: param.quantityChecked,
    expiDateChecked: param.expiDateChecked,
  }));

  const requestData = {
    deliveryDate: deliveryDate,
    storeCode: storeCode,
    params: paramsArray,
  };

  $.ajax({
    url:
      "/jof/datas/invoice2Print_srv.php?deliveryDate=" +
      deliveryDate +
      "&storeCode=" +
      storeCode,
    type: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    contentType: "application/json",
    data: JSON.stringify(requestData),
    beforeSend: function () {
      console.log("송장 인쇄 요청을 서버로 전송 중입니다.");
    },
    success: function (response) {
      const printWindow = window.open("", "_blank");
      if (printWindow) {
        printWindow.document.write(response);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
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

// 주문 출력
function orderPrint(deliveryDate) {
  // 납품점 코드(store_no)와 납품일자 확인
  if (!deliveryDate) {
    alert("납품일자와 납품점 코드를 모두 입력해 주세요.");
    return;
  }
  const url = `/jof/datas/orderPrint_srv.php?deliveryDate=${encodeURIComponent(
    deliveryDate
  )}`;
  console.log("[ln637::]url=" + url);
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
  console.log("[ln701::]url=" + url);
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

function toggleInput(idx, type) {
  const input = document.getElementById(`${type}-${idx}`);
  const checkbox = document.getElementById(`${type}-checkbox-${idx}`);

  if (checkbox.checked) {
    if (type === "expi_date" && input.hasAttribute("data-original")) {
      input.setAttribute("data-temp", input.value);
      input.value = "";
    } else {
      input.setAttribute("data-temp", input.value);
      input.value = "";
    }
    input.readOnly = true;
  } else {
    if (input.hasAttribute("data-temp")) {
      input.value = input.getAttribute("data-temp");
      input.removeAttribute("data-temp");
    } else if (type === "expi_date" && input.hasAttribute("data-original")) {
      input.value = input.getAttribute("data-original");
    }
    input.readOnly = false;
  }
}
