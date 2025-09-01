class CodeInClass {
  // apt 전월세 deposit -----------------------------------------------
  constructor({
    col = [
      "번호",
      "입고일자",
      "입력자",
      "BL번호",
      "바코드1",
      "바코드2",
      "중량(kg)",
      "생산일",
      "입력일시",
    ],
    col2 = [
      "no",
      "increate_dt",
      "display_name",
      "lc_no",
      "barcode_one",
      "barcode_two",
      "weight",
      "prod_date",
      "create_dt",
    ],
    col3 = ["c", "c", "c", "c", "l", "l", "c", "c", "c"],
    colw = ["5%", "7%", "7%", "7%", "20%", "23%", "7%", "7%", "15%"],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    // 전역 변수를 클래스 프로퍼티로 저장
    this.barcodeIn_srv = window.barcodeIn_srv;
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

        // 클릭 이벤트 처리:: row 선택 색깔 초록색 
        tr.onclick = function () {
          // 이전에 선택된 행의 스타일 제거
          const selectedRows = tbody.getElementsByClassName('selected-row');
          for (let row of selectedRows) {
            row.classList.remove('selected-row');
          }
          // 현재 클릭된 행에 스타일 적용
          this.classList.add('selected-row');
        };

        let tabCells = tr.insertCell(-1);

        // 데이터표시
        tabCells.innerHTML = items[i][this.col2[0]];

        tabCells.setAttribute("style", "text-align:center;");
        for (let j = 1; j < this.col.length; j++) {
          let tabCell = tr.insertCell(-1);

          // 정렬하기
          tabCell.innerHTML = items[i][this.col2[j]];
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
          tabCell.innerHTML = CodeInClass.setLink(items, i, j, tabCell);
        } // column
      } catch (ex) {
        console.log(ex.message);
      }
      // 중량 합계 구하기
      totalWeight += parseFloat(items[i]["weight"]);
    }

    // FINALLY ADD THE NEWLY CREATED TABLE WITH JSON DATA TO A CONTAINER.
    let divContainer = document.getElementById("tables");
    //divContainer.innerHTML = "";
    divContainer.appendChild(table1);

    // 갯수(Box), 총중량(kg)표시
    document.getElementById("count").value = items.length + " (Box)";
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
      case 1: // 입력일자
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

      case 7: // 입력일시
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

    barcodeList = [];
    //if (items.length>1) items.sort(custonSort);
    document.getElementById("tables").innerHTML = "";

    idx = 0;
    var licn = ""; // HTML 문자열을 저장할 변수 초기화
    for (var i = 0; i < items.length; i++) {
      idx = idx + 1;

      let newItem = {
        no: idx, // 가정: idx를 번호로 사용
        lc_no: items[i]["lc_no"],
        createDt: items[i]["increate_dt"],
        barcode1: items[i]["barcode_one"],
        weight: parseFloat(items[i]["weight"]),
        barcode2: items[i]["barcode_two"].replace(/[^a-zA-Z0-9 ]/g, ""),
        date: items[i]["prod_date"],
      };
      barcodeList.push(newItem);
      const getToday = getCurrentDateTime();
      licn += `<li class="list-item" id ="item-no${idx}">
                <a href="javascript:void(0);" class="full-link" onclick="openButton(${idx})">
                    <div class="content">
                        <span class="item">[${items[i]["no"]}] BL#::${items[i]["lc_no"]
        } [입력일] ${items[i]["increate_dt"]}</span>
                        <div class="item-details">
                            Code1: ${items[i]["barcode_one"]}<br>
                            Code2: ${items[i]["barcode_two"].replace(
          /[^a-zA-Z0-9 ]/g,
          ""
        )}<br>
                            중량: ${items[i]["weight"]}, 생산일: ${items[i]["prod_date"]
        }, (※DT: ${items[i]["create_dt"]})
                        </div>
                    </div>
                    <div class="button-container" id="link${idx}" style="display:none;">
                        <button type="button" class="del-btn" onclick='delBarcode("${newItem.barcode1
        }","${newItem.barcode2}")'>삭제</button>
                    </div>
                </a>
             </li>`;
    }

    // if (lic !== "") licn = lic + licn;  //지우고 조회
    document.getElementById("tables").innerHTML = licn;

    // 총건수(Box), 총중량 업데이트
    document.getElementById("count").value = items.length + " (Box)";
    const totalWeight = items.reduce(
      (sum, item) => sum + parseFloat(item.weight),
      0
    );
    // 총중량
    document.getElementById("total-weight").value =
      totalWeight.toFixed(1) + " (kg)";
  }
}

// actionBtn 조회 시작
function actionBtn_Search() {
  const category = document.getElementById("category").value; // 1:입력, 2:조회
  const createDate = document.getElementById("createDate").value;
  const lcNumber = document.getElementById("lcNumber").value;
  const barcode1 = document.getElementById("barcodeInput1").value;
  const barcode2 = document.getElementById("barcodeInput2").value;

  if (category === "3") return; // 삭제는 조회 없음.

  // category=1 (입력모드) 일때는 lcNumber 확인
  if (category === "1" && lcNumber === "") {
    alert("BL# 4자리를 입력하세요.");
    return false;
  }
  const rtn = searchAjaxKwd(category, createDate, lcNumber, barcode1, barcode2);
  return rtn;
}

// 조회시작:: BL# 입력|확인
//  - category=1 BL#:조회, 2:조회
function searchAjaxKwd(category, createDate, lcNumber, barcode1, barcode2) {
  // ----------------------------
  // 입력: return true;
  // 조회: return 조회 Data
  // ----------------------------
  let param =
    "&createDate=" +
    createDate +
    "&lcNumber=" +
    lcNumber +
    "&barcode1=" +
    barcode1 +
    "&barcode2=" +
    barcode2;
  const server = this.barcodeIn_srv + "?category=" + category + param;
  console.log("[search_srv]" + server);

  if (category == 2) move(); // 조회 일 때 loding...
  getAjax(server, recvData);
}

function recvData(data) {
  move_stop();
  // console.log(data);

  try {
    // 헤더정보
    let jsData = JSON.parse(data);
    // console.log(jsData.response.header);

    items = jsData.response.body.items;
    totalCount = jsData.response.body.totalCount;
    resultCode = jsData.response.header.resultCode;
  } catch (e) {
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
      '<font size="3em">[' + platForm + "]total record=" + idx;
    return false;
  }

  document.getElementById("tables").innerHTML = "";
  document.getElementById("totalrec").innerHTML = "";

  // 조회결과
  try {
    if (platForm == "computer") {
      codeInClass.makeTableHead();
      codeInClass.makeTabletr(data);
      if (table1.rows.length > 2) setSort();
    } else {
      // 모바일 조회 데이터
      codeInClass.makeTabletr2Mobile(data);
    }

    document.getElementById("totalrec").innerHTML =
      '<font size="3em">[' + platForm + "]total record=" + idx;
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

// 목록에서 삭제
function delBarcode(barcode1, barcode2) {
  if (confirm(barcode1 + " 영구 삭제합니다.")) {
    // 사용자가 '확인'을 클릭했을 때 실행할 코드, category='d'
    param = "&barcode1=" + barcode1 + "&barcode2=" + barcode2;
    server = this.barcodeIn_srv + "?category=d" + param;
    console.log("[delBarcode]" + server);

    getAjax(server, recvBarcodeIn);
  } else {
    // 사용자가 '취소'를 클릭했을 때 실행할 코드
    console.log("삭제를 취소합니다.");
  }
}

//  barcodeList[]에 있는지 확인 후 목록에서 삭제
function delBarcode3(barcode1, barcode2) {
  // 출고여부확인::해당 바코드에 해당하는 목록을 찾아서 출고여부를 확인
  const index = barcodeList.findIndex(
    (item) =>
      item.barcode1 == barcode1 &&
      item.barcode2 == barcode2.replace(/[^a-zA-Z0-9 ]/g, "")
  );

  if (index !== -1) {
    // 사용자가 '확인'을 클릭했을 때 실행할 코드, category='d'
    param = "&barcode1=" + barcode1 + "&barcode2=" + barcode2;
    server = this.barcodeIn_srv + "?category=d" + param;
    console.log("[delBarcode]" + server);

    getAjax(server, recvBarcodeIn);
  } else {
    alert(
      barcode1 +
      ", " +
      barcode2 +
      " => 이 BL#번호에서 삭제할 수 있는 바코드가 없습니다. BL#번호로 조회해서 확인하세요."
    );
  }
  document.getElementById("barcodeInput1").value = "";
  document.getElementById("barcodeInput2").value = "";
  document.getElementById("barcodeInput1").focus();
}

// ------------------------------------
// 바코드 입력 및 목록에서 삭제 처리
// 입력: category 'i':입력, '1':입력조회, '2':조회,
// 삭제: category 'd'
// ------------------------------------
function recvBarcodeIn(response) {
  move_stop();

  // 서버 응답을 JSON 객체로 변환
  var responseObj = JSON.parse(response);

  if (responseObj.success) {
    // --------------------------------------
    // barcode 입력성공
    // --------------------------------------
    if (responseObj.category === "i") {
      // 번호 + 목록을 포맷에 맞게 수정한 displayText
      const displayText = `
        <div class="content">
            <span class="item">[${newItem.no}] BL#::${newItem.lc_no} [입력일] ${newItem.createDt}</span>
            <div class="item-details">
                Code1: ${newItem.barcode1}<br>
                Code2: ${newItem.barcode2}<br>
                중량: ${newItem.weight}, 생산일: ${newItem.date}, (※DB:   )
            </div>
        </div>
        `;

      // 삭제 button, style과 class를 기존 CSS에 맞게 조정한 delBtn
      const delBtn = `
        <div class="button-container" style="display:none;" id="link${parseInt(
        newItem.no
      )}">
            <center>
                <button type="button" class="del-btn" onclick='delBarcode("${newItem.barcode1
        }","${newItem.barcode2}")'>삭제</button>
            </center>
        </div>
        `;

      // 삭제 Key값 id를 포함한 newListItem
      const newListItem = `
        <li class="list-item" id="item-no${newItem.no}">
            <a href="javascript:void(0);" class="full-link" onclick="event.stopPropagation(); openButton(${parseInt(
        newItem.no
      )});">
                ${displayText}
                ${delBtn}
            </a>
        </li>
        `;

      // newItem을 listContainer에 추가
      // listContainer.innerHTML = newListItem + listContainer.innerHTML;

      // barcodeList가 비어있지 않고, 마지막 아이템의 lc_no와 새 아이템의 lc_no가 다르면 리스트 초기화
      const tablesContainer = document.getElementById("tables");
      if (
        barcodeList.length > 0 &&
        barcodeList[barcodeList.length - 1].lc_no !== newItem.lc_no
      ) {
        barcodeList = []; // barcodeList 초기화
        tablesContainer.innerHTML = ""; // 목록도 없앰
      }
      barcodeList.push(newItem);
      tablesContainer.innerHTML = newListItem + tablesContainer.innerHTML;

      // 총건수(Box), 총중량 표시
      document.getElementById("count").value = barcodeList.length + " (Box)";
      const totalWeight = barcodeList.reduce(
        (sum, item) => sum + item.weight,
        0
      );
      document.getElementById("total-weight").value =
        totalWeight.toFixed(2) + " (kg)";

      // 성공 시 필요한 추가 작업 수행
      document.getElementById("totalrec").innerHTML =
        '<font size="3em">[' + platForm +  "]total record=" + barcodeList.length;

      return true;
    }

    // -----------------------------------------------------------
    // Success 삭제결과::목록에서 barcode 삭제 category = 'd'
    //  - 목록에서 삭제하고 전역 array::barcodeList item도 삭제
    // -----------------------------------------------------------
    if (responseObj.category === "d") {
      // barcodeList에서 항목 삭제
      const index = barcodeList.findIndex(
        (item) =>
          item.barcode1 === responseObj.barcode1 &&
          item.barcode2 === responseObj.barcode2
      );

      if (index !== -1) {
        const itemNo = barcodeList[index].no;
        barcodeList.splice(index, 1); // 배열에서 항목 제거
        // HTML 목록에서도 해당 항목을 제거
        const itemToRemove = document.getElementById(`item-no${itemNo}`);
        if (itemToRemove && itemToRemove.parentNode) {
          itemToRemove.parentNode.removeChild(itemToRemove);
        }

        // 총건수(Box), 총중량 업데이트
        document.getElementById("count").value = barcodeList.length + " (Box)";
        const totalWeight = barcodeList.reduce(
          (sum, item) => sum + parseFloat(item.weight),
          0
        );
        document.getElementById("total-weight").value =
          totalWeight.toFixed(2) + " (kg)";

        document.getElementById("totalrec").innerHTML =
          '<font size="3em">[' + platForm +  "]total record=" + barcodeList.length;
      }
    }
  } else {
    // 중복 에러

    playErrorSound(); // 에러 사운드 재생
    setTimeout(function () {
      // playErrorSound(); // 에러 사운드 재생
      alert("실패: " + responseObj.message);
      document.getElementById("barcodeInput1").focus();
    }, 1000); // 1초 대기 후 실행

    document.getElementById("barcodeInput1").focus();
    return false;
    // 실패 시 필요한 추가 작업 수행
  }
}

function insertBtn_Click() {
  if (manualInput == false) {
    console.log("수기입력 클릭");

    // category 입고로 변경
    document.getElementById("category").value = "1";

    // 중량 입력 필드 활성화
    document.getElementById("weightInput").readOnly = false;
    document.getElementById("productionDate").readOnly = false;
    document.getElementById("productionDate").value = getTodayDate();

    //생산일 배경색 변경
    document.getElementById("productionDate").style.backgroundColor = "#fff";
    document.getElementById("insertBtn").textContent = "저장";
    manualInput = true;
    return;
  }

  console.log("저장 클릭");
  const createDate = document.getElementById("createDate").value;
  const lcNumber = document.getElementById("lcNumber").value;
  const barcode1 = document.getElementById("barcodeInput1").value;
  const weightInput = document.getElementById("weightInput").value;
  const barcode2 = document.getElementById("barcodeInput2").value;
  const productionDate = document.getElementById("productionDate").value;

  // BL#, 중량, 생산일 모두 입력 확인
  if (lcNumber === "" || weightInput === "" || productionDate === "") {
    alert("BL#, 중량, 생산일을 모두 입력하세요.");
    if (lcNumber === "") {
      document.getElementById("lcNumber").focus();
    } else if (weightInput === "") {
      document.getElementById("weightInput").focus();
    } else if (productionDate === "") {
      document.getElementById("productionDate").focus();
    }
    return;
  }

  // productionDate 포맷 확인
  if (productionDate.length !== 10) {
    alert("생산일 포맷이 올바르지 않습니다. 예)2024-12-25");
    document.getElementById("productionDate").focus();
    return;
  } else if (productionDate.length === 10) {
    // YYYY-MM-DD 포맷 검증을 위한 정규식
    const dateFormatRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateFormatRegex.test(productionDate)) {
      alert("생산일 포맷이 올바르지 않습니다. 예)2024-12-25");
      return;
    }

    // 유효한 날짜인지 확인
    const date = new Date(productionDate);
    if (isNaN(date.getTime())) {
      alert("유효하지 않은 날짜입니다. 예)2024-12-25");
      return;
    }
  }

  const inputList =
    createDate +
    "," +
    lcNumber +
    "," +
    barcode1 +
    "," +
    weightInput +
    "," +
    barcode2 +
    "," +
    productionDate;

  // Array[barcode1, 중량, barcode2, 생산일]
  const listArray = inputList.split(",");
  document.getElementById("barcodeInput1").focus();

  // console.log(listArray);
  // 입력시작::DB 저장 및 중복 체크 return false;
  newItem = [];
  if (!ajax_barcodeIn(listArray)) {
    document.getElementById("barcodeInput1").value = "";
    document.getElementById("barcodeInput2").value = "";
    document.getElementById("weightInput").value = "";
    document.getElementById("weightInput").focus();
  }
}
// -----------------------------------------------------------------
// 입력시작::목록에 바코드 보여주고 성공이면 DB 저장
// - category 1:입력, 2:조회
// - inptuArray 6개 입력 field: 입력일, BL#, barcode1,2, 중량, 생산일
// -----------------------------------------------------------------
var newItem = [];
function ajax_barcodeIn(inputArray, category = "i") {
  // -----------------------
  // 목록 표시::mobile 형태
  // -----------------------
  const listContainer = document.getElementById("listContainer");
  // const listItems = listContainer.querySelectorAll("li");

  // no 속성의 최대값 찾기
  const maxNo = barcodeList.reduce(
    (max, item) => (item.no > max ? item.no : max),
    0
  );
  const totalItems = Number(maxNo) + 1; // 최대값에 1 더하기

  // const totalItems = barcodeList.length + 1;
  // const formattedTotalItems = totalItems.toString().padStart(3, "0");

  const createDate = inputArray[0];
  const lcNumber = inputArray[1];
  const barcode1 = inputArray[2];
  const inputWeight = parseFloat(inputArray[3]);
  const barcode2 = inputArray[4];
  const productionDate = inputArray[5];

  newItem = {
    no: totalItems,
    lc_no: lcNumber,
    createDt: createDate,
    barcode1: barcode1,
    weight: inputWeight,
    barcode2: barcode2,
    date: productionDate,
  };

  // barcode1, 2 합쳐서 중복 있는지 체크
  const isDuplicate = barcodeList.some(
    (item) =>
      item.barcode1 + item.barcode2 === newItem.barcode1 + newItem.barcode2
  );

  // ------------------------------------------
  // barcode1, 2 두개 중 1개라도 중복이면 alert
  // ------------------------------------------
  if (isDuplicate) {
    // 수기입력이 아닌 경우만 중복 체크
    if (!manualInput) {
      alert("이미 입력한 바코드 입니다!");
      document.getElementById("barcodeInput1").focus();
      return false; // 중복된 항목이면 여기서 함수 종료
    }
  }

  // --------------------------------------------------
  // [DB 저장:조회 post data 생성]
  // category: 1:입력, 2:조회, inputArray 6개 항목 전달
  // --------------------------------------------------

  const param =
    "&manualInput=" +
    manualInput +
    "&createDate=" +
    createDate +
    "&lcNumber=" +
    lcNumber +
    "&barcode1=" +
    barcode1 +
    "&inputWeight=" +
    inputWeight +
    "&barcode2=" +
    barcode2 +
    "&productionDate=" +
    productionDate +
    "&user_id=" +
    user_id;

  // ----------------------------
  // 입력: return true;
  // 조회: return 조회 Data
  // ----------------------------
  // server = "/jof/datas/barcodeIn_srv.php?category=" + category + param;
  server = this.barcodeIn_srv + "?category=" + category + param;
  console.log("[ajax_barcodeIn]" + server);

  getAjax(server, recvBarcodeIn);
}

function moveToNextField(event) {
  if (event.key === "Enter" || event.keyCode === 13) {
    event.preventDefault(); // 기본 Enter 동작 차단

    const nextElement = event.target.nextElementSibling; // 다음 형제 요소 찾기
    if (nextElement && nextElement.tagName === "INPUT") {
      nextElement.focus(); // 다음 input 요소로 포커스 이동
    }
  }
}

// onCategoryChange 함수는 카테고리가 변경될 때 호출됩니다.
function onCategoryChange() {
  // console.log("onCategoryChange");

  if (manualInput === true) {
    manualInput = false;
    document.getElementById("insertBtn").textContent = "수기입력";

    // 중량 및 생산일 요소를 가져와서 readonly로 설정
    const weightInput = document.querySelector("#weightInput");
    const productionDateInput = document.querySelector("#productionDateInput");

    if (weightInput) {
      weightInput.readOnly = true;
    }

    if (productionDateInput) {
      productionDateInput.readOnly = true;
      productionDateInput.style.backgroundColor = "#f0f0f0";
    }
  }
}

// 오늘 날짜를 YYYY-MM-DD 형식으로 가져오는 함수
function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = (today.getMonth() + 1).toString().padStart(2, '0'); // 월은 0부터 시작하므로 +1
  const day = today.getDate().toString().padStart(2, '0');
  return `${year}-${month}-${day}`;
}