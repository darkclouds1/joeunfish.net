class ascInvtSumClass {
  constructor({
    col = [
      "점명",
      "ITEM #",
      "상품명",
      "단위",
      "발주수량",
      "입점수량",
      "유통기한",
      "원산지",
      "박스",
    ],
    col2 = [
      "store_name",
      "item_code",
      "product_name",
      "unit",
      "order_quantity",
      "received_quantity",
      "expiration_date",
      "country_origin",
      "box_count",
    ],
    col3 = ["c", "c", "c", "c", "c", "c", "c", "c", "c"],
    colw = ["15%", "15%", "15%", "5%", "5%", "10%", "10%", "10%", "10%"],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
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

  // 생산 바코드출고 makeTable
  makeTabletr(datas) {
    let totalWeight = 0;
    let totalBoxCount = 0; // totalBoxCount 초기화
    // ADD JSON DATA TO THE TABLE AS ROWS.
    let data = JSON.parse(datas);
    items = data.response.body.items;
    rtnCnt = items.length;

    // 데이터 없음
    if (items.length == 0) {
      alert("데이터가 없습니다. 조회 방법을 확인하세요.");
      return rtnCnt;
    }

    // 총중량(kg) 계산
    totalWeight = 0;

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

          if (j === 0 && items[i][this.col2[0]] == "대구점2") {
            tabCell.innerHTML = "혁신점";
          } else {
            tabCell.innerHTML = items[i][this.col2[j]];
          }

          if (j === 2) {
            tabCell.innerHTML = items[i][this.col2[j]].replace(
              "연어（노르웨이） 매입품목 1KG",
              "연어필렛"
            );
          } else {
            tabCell.innerHTML = items[i][this.col2[j]];
          }

          let attr = "";
          if (this.col3[j] == "c") attr += "text-align:center;";
          else if (this.col3[j] == "l") attr += "text-align:left;";
          else if (this.col3[j] == "r") {
            attr += "text-align:right;";
            if (tabCell != null) tabCell.innerHTML = tabCell.innerHTML.format();
          } else if (this.col3[j] == "d") {
            // date
            attr += "text-align:center;";
            if (tabCell != null && tabCell.innerHTML.length > 10)
              tabCell.innerHTML = tabCell.innerHTML.substr(0, 10);
          }

          tabCell.setAttribute("style", attr);

          // 셀에 링크 추가
          tabCell.innerHTML = CodeInvSumClass.setLink(items, i, j, tabCell);
        } // column
      } catch (ex) {
        console.log(ex.message);
      }
      // 중량 합계 구하기
      totalWeight += parseFloat(items[i]["received_quantity"]);
      totalBoxCount += parseFloat(items[i]["box_count"]);
    }

    // FINALLY ADD THE NEWLY CREATED TABLE WITH JSON DATA TO A CONTAINER.
    let divContainer = document.getElementById("tables");
    //divContainer.innerHTML = "";
    divContainer.appendChild(table1);

    // 갯수(Box), 총중량(kg)표시
    document.getElementById("count").value = totalBoxCount + " (Box)";
    // ${items[i]["weight"]} => 합계
    document.getElementById("total-weight").value =
      totalWeight.toFixed(1) + " (kg)";

    return rtnCnt;
  }

  // 링크 만들기
  static setLink(items, i, j, cell) {
    let today = getToday("-");
    // 0:번호, 1:일련번호
    switch (j) {
      case 1: // 납품일자
        break;

      case 2: // LC번호
        // cell.innerHTML =
        //   "<a onclick='viewAptKwd(\"" +
        //   cell.innerText +
        //   "\")'>" +
        //   cell.innerHTML +
        //   '&nbsp&nbsp <i class="bi bi-search-heart"></i></a>';
        break;

      case 3: // barcode1
        break;

      case 4: // barcode2
        break;

      case 5: //  중량
        break;

      case 6: // 생산일
        break;

      case 7: // 납품일자
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
    let data = JSON.parse(datas);
    let items = data.response.body.items;

    let totalWeight = 0;
    let totalBoxCount = 0; // totalBoxCount 초기화

    barcodeList = [];
    //if (items.length>1) items.sort(custonSort);
    document.getElementById("tables").innerHTML = "";

    idx = 0;
    var licn = ""; // HTML 문자열을 저장할 변수 초기화
    for (var i = 0; i < items.length; i++) {
      idx = idx + 1;

      newItem = {
        no: idx,
        increate_dt: items[i]["supply_dt"],
        store_code: items[i]["store_code"],
        store_name: items[i]["store_name"],
        item_code: items[i]["item_code"],
        product_name: items[i]["product_name"].replace(
          "연어（노르웨이） 매입품목 1KG",
          "연어필렛"
        ),
        unit: items[i]["unit"],
        order_quantity: items[i]["order_quantity"],
        received_quantity: items[i]["received_quantity"],
        expiration_date: items[i]["expiration_date"],
        country_origin: items[i]["country_origin"],
        box_count: items[i]["box_count"],
      };

      barcodeList.push(newItem);
      const getToday = getCurrentDateTime();
      licn += `<li class="list-item" id ="item-no${idx}">
                <a href="javascript:void(0);" class="full-link" onclick="openButton(${idx})">
                    <div class="content">
                        <span class="item">[${items[i]["no"]}] [납품일자] ${items[i]["supply_dt"]} [점명] ${neItem.store_name} </span>
                        <div class="item-details">
                        [ITEM#] ${newItem.item_code} [상품명] ${items[i]["product_name"]} [단위] ${newItem.unit} [수량] ${newItem.received_quantity} [유통기한] ${newItem.expiration_date} [원산지] ${newItem.country_origin} [박스] ${newItem.box_count}
                        </div>
                    </div>
                    <div class="button-container" id="link${idx}" style="display:none;">
                        <button type="button" class="del-btn" onclick='delBarcodeOut("${newItem.barcode_out}")'>삭제</button>
                    </div>
                </a>
             </li>`;

      // 중량 합계 구하기
      totalWeight += parseFloat(items[i]["received_quantity"]);
      totalBoxCount += parseFloat(items[i]["box_count"]);
    }

    // if (lic !== "") licn = lic + licn;  //지우고 조회
    document.getElementById("tables").innerHTML = licn;

    // 갯수(Box), 총중량(kg)표시
    document.getElementById("count").value = totalBoxCount += parseFloat(
      items[i]["box_count"]
    );
    +" (Box)";
    // ${items[i]["weight"]} => 합계
    document.getElementById("total-weight").value =
      totalWeight.toFixed(1) + " (kg)";
  }
} // CodeInvSumClass -end

// actionBtn 조회 시작
function summarySearch(deliveryDate) {
  const increate_dt = document.getElementById("createDate").value;
  if (!deliveryDate) {
    alert("납품일자를 입력해 주세요.");
    return;
  }

  const url = `/jof/datas/summaryPrint_srv.php?category=summarySearch&deliveryDate=${encodeURIComponent(
    deliveryDate
  )}`;
  console.log(url);

  move();
  getAjax(url, recvSummaryData);
}

function recvSummaryData(data) {
  move_stop();
  // console.log(data);

  try {
    // 헤더정보
    let jsData = JSON.parse(data);
    console.log(jsData.response.header);

    items = jsData.response.body.items;
    totalCount = jsData.response.body.totalCount;
    resultCode = jsData.response.header.resultCode;
  } catch (e) {
    console.log(e.error);
    console.log(e.message);
    alert(
      "시스템 작업 중이거나 장애가 있습니다. 잠시 후 다시 사용하시기 바랍니다."
    );
    return false;
  }
  // 데이터 없음
  if (totalCount == 0 || data === "") {
    alert("등록된 데이터가 없습니다.");
    // if (lic !== "") {
    //   licn = lic + licn;
    // }
    barcodeList = [];
    licn = "";
    idx = 0;
    document.getElementById("tables").innerHTML = licn;
    document.getElementById("totalrec").innerHTML =
      '<font size="3em">[' +
      platForm +
      ":" +
      navigator.platform +
      "::" +
      "]total record=" +
      idx;
    return false;
  }

  document.getElementById("tables").innerHTML = "";
  document.getElementById("totalrec").innerHTML = "";

  // 조회결과
  try {
    if (platForm == "computer") {
      codeSumClass.makeTableHead();
      codeSumClass.makeTabletr(data);
      if (table1.rows.length > 2) setSort();
    } else {
      // 모바일 조회 데이터
      codeInClass.makeTabletr2Mobile(data);
    }

    document.getElementById("totalrec").innerHTML =
      '<font size="3em">[' +
      platForm +
      ":" +
      navigator.platform +
      "::" +
      "]total record=" +
      idx;
  } catch (e) {
    alert(
      "ln338::시스템 장애 입니다. 잠시 후 재시도 하시기 바랍니다." + e.message
    );
    return false;
  }

  // -------------------------------------------------------
  //  메시지 표시
  // -------------------------------------------------------
  const ment = "\nBL#: " + idx + "건 조회하였습니다.\n";
  document.getElementById("notification-container").innerHTML =
    "<p><font color='white'>" + ment + "</font></p>";
  showNotification();

  return true;
}
