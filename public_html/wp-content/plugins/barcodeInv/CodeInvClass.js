class CodeInvClass {
  constructor({
    col = [
      "NO.", "납품일자", "생산자", "LC번호", "점번호#", "원번호#", "색깔", "Barcode", "제조일자", "소비기한", "중량", "상품명", "출고", "수정일시"],
    col2 = ["no", "increate_dt", "display_name", "lc_no", "store_no", "origin_store_code", "pack_color", "barcode_out", "manu_date", "expi_date", "weight", "prod_nm", "invoice_yn", "modify_dt"],
    col3 = ["c", "c", "c", "c", "c", "c", "c", "c", "c", "c", "c", "c", "c", "c"],
    colw = ["5%", "7%", "6%", "5%", "5%", "5%", "6%", "14%", "7%", "7%", "4%", "10%", "3%", "11%"],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;

    // 전역 변수를 클래스 프로퍼티로 저장
    this.barcodeInv_srv = window.barcodeInv_srv;

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

        if (items[i][this.col2[5]] == "001") {
          tr.style.backgroundColor = "orange";
        }

        // tabCells.innerHTML = idx;
        tabCells.innerHTML = items[i][this.col2[0]];

        // 정렬하기
        tabCells.setAttribute("style", "text-align:center;");
        for (let j = 1; j < this.col.length; j++) {
          let tabCell = tr.insertCell(-1);

          tabCell.innerHTML = items[i][this.col2[j]];

          // 상품명 포맷팅
          if (this.col2[j] === 'prod_nm') {
            // console.log("ln140::prod_nm=" + items[i][this.col2[j]]);
            let prodName = items[i][this.col2[j]];
            if (prodName.includes('연어(스테이크용)벌크')) {
              tabCell.innerHTML = '스테이크(벌크)';
            } else if (prodName.includes('연어(스테이크)')) {
              tabCell.innerHTML = '스테이크';
            }
          }

          // 정렬하기
          let attr = "";
          if (this.col3[j] == "c") attr += "text-align:center;";
          else if (this.col3[j] == "l") attr += "text-align:left;";
          else if (this.col3[j] == "r") attr += "text-align:right;";
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
    let data = JSON.parse(datas);
    let items = data.response.body.items;

    barcodeList = [];
    //if (items.length>1) items.sort(custonSort);
    document.getElementById("tables").innerHTML = "";

    idx = 0;
    var licn = ""; // HTML 문자열을 저장할 변수 초기화
    for (var i = 0; i < items.length; i++) {
      idx = idx + 1;

      newItem = {
        no: idx,
        increate_dt: items[i]["increate_dt"],
        lc_no: items[i]["lc_no"],
        store_no: items[i]["store_no"],
        doc_no: items[i]["doc_no"],
        pack_color: items[i]["pack_color"],
        barcode_out: items[i]["barcode_out"],
        manu_date: items[i]["manu_date"],
        expi_date: items[i]["expi_date"],
        weight: parseFloat(items[i]["weight"]),
        prod_code: items[i]["prod_code"],
        prod_nm: items[i]["prod_nm"],
        invoice_yn: items[i]["invoice_yn"],
      };

      barcodeList.push(newItem);
      const getToday = getCurrentDateTime();
      licn += `<li class="list-item" id ="item-no${idx}">
                <a href="javascript:void(0);" class="full-link" onclick="openButton(${idx})">
                    <div class="content">
                        <span class="item">[${items[i]["no"]}] BL#: ${items[i]["lc_no"]}, 납품일: ${items[i]["increate_dt"]}, 납품점: ${newItem.store_no}, PO#: ${items[i]["doc_no"]}, [출고] ${items[i]["invoice_yn"]}</span>
                        <div class="item-details">
                         ${newItem.pack_color} , barcode: ${newItem.barcode_out}, 제조일자: ${newItem.manu_date}, 소비기한: ${newItem.expi_date}, 중량:${newItem.weight}, ${items[i]["prod_nm"]}, (※MDT: ${items[i]["modify_dt"]})
                        </div>
                    </div>
                    <div class="button-container" id="link${idx}" style="display:none;">
                        <button type="button" class="del-btn" onclick='delBarcodeOut("${newItem.barcode_out}")'>삭제</button>
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

  // -----------------------------------------------------------------
  // 입력시작::목록에 바코드 보여주고 성공이면 DB 저장
  // - category 1i:입력, 1s:조회, 2:조회, 3:삭제 ('3u':재고로 업데이트)
  // - inptuArray 6개 입력 field: 납품일자, BL#, barcode1,2, 중량, 생산일
  // - 1u:점포명업데이트 => 출고 상태에서 해당 점포에 추가함.
  // -----------------------------------------------------------------
  ajax_barcodeOut(inputArray, category) {
    if (category == 1) category = "1u"; //출고시점은 '1u'
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
    const nextNo = Number(maxNo) + 1; // 최대값에 1 더하기

    // const formattedTotalItems = totalItems.toString().padStart(3, "0");

    const increate_dt = inputArray[0];
    const lc_no = inputArray[1];
    const store_no = inputArray[2];
    const doc_no = inputArray[3];
    const barcode_out = inputArray[4];
    const manu_date = inputArray[5];
    const expi_date = inputArray[6];
    const weight = parseFloat(inputArray[7]);
    const prod_code = inputArray[8];
    const prod_nm = inputArray[9]; // 출고시점 업데이트는 무의미
    const invoice_yn = "n"; // 출고시점은 무조건 'n'

    newItem = {
      no: nextNo,
      increate_dt: increate_dt,
      lc_no: lc_no,
      store_no: store_no,
      doc_no: doc_no,
      barcode_out: barcode_out,
      manu_date: manu_date,
      expi_date: expi_date,
      weight: weight,
      prod_code: prod_code,
      prod_nm: prod_nm,
      invoice_yn: invoice_yn,
    };

    // console.log(barcodeList);

    // barcode1, 2 합쳐서 중복 있는지 체크
    const isDuplicate = barcodeList.some(
      (item) => item.barcode_out === newItem.barcode_out
    );

    // ------------------------------------------
    // barcode_out 중복이면 alert
    // ------------------------------------------
    if (isDuplicate) {
      console.log("이미 입력한 바코드 입니다!");
      alert("이미 입력한 바코드 입니다!");
      document.getElementById("barcodeOutput").focus();
      return false;
    }

    // --------------------------------------------------
    // [DB 저장:조회 post data 생성]
    // category: 1:입력, 2:조회, inputArray 6개 항목 전달
    // --------------------------------------------------
    //  barcode 입력 시 table 삭제 필요없음.
    // document.getElementById("tables").innerHTML = "";

    parm =
      "&increate_dt=" +
      increate_dt +
      "&lc_no=" +
      lc_no +
      "&store_no=" +
      store_no +
      "&doc_no=" +
      doc_no +
      "&barcode_out=" +
      barcode_out +
      "&manu_date=" +
      manu_date +
      "&expi_date=" +
      expi_date +
      "&weight=" +
      weight +
      "&prod_code=" +
      prod_code +
      "&prod_nm=" +
      prod_nm +
      "&user_id=" +
      user_id;

    // -------------------------------
    // category: 1u: 출고시점 업데이트
    // -------------------------------
    const server =
      "/wp-content/plugins/barcodeInv/barcodeOutInv_srv.php?category=" + category + parm;
    console.log("[ln375::CodeInvClass] server=" + server);
    getAjax(server, this.recvBarcodeOut);
  }

  // ------------------------------------
  // 바코드 입력 및 목록에서 삭제 처리
  // 입력: category '1':입력, '2':조회
  // 삭제: category 'd'
  // ------------------------------------
  recvBarcodeOut(response) {
    move_stop();

    // debug
    // console.log(response);
    // return;

    // 서버 응답을 JSON 객체로 변환
    var responseObj = JSON.parse(response);

    if (responseObj.success) {
      // --------------------------------------
      // barcode 입력
      // --------------------------------------
      const rtn = responseObj.category;
      // 서버 SQL 실행후 rtn => 출고에서 '1u': 납품점 변경 사용
      if (rtn === "1i" || rtn === "1u") {
        barcodeList.push(newItem);
        // 임시: 상품코드 560770='연어필렛', 651343='연어(스테이크)'
        const displayText = `
        <div class="content">
            <span class="item">[${newItem.no}] BL#: ${newItem.lc_no}, 납품일: ${newItem.increate_dt}, 납품점: ${newItem.store_no}, PO#: ${newItem.doc_no}, [출고] ${newItem.invoice_yn}</span>
          }</span>
            <div class="item-details">
            ${newItem.pack_color}, barcode: ${newItem.barcode_out}<br>
            제조일자: ${newItem.manu_date}, 소비기한: ${newItem.expi_date}, 중량:${newItem.weight.toFixed(1)}, ${newItem.prod_nm}
            </div>
        </div>
        `;

        // 삭제 button, style과 class를 기존 CSS에 맞게 조정한 delBtn
        const delBtn = `
        <div class="button-container" style="display:none;" id="link${parseInt(
          newItem.no
        )}">
            <center>
                <button type="button" class="del-btn" onclick='delBarcodeOut("${newItem.barcode_out
          }")'>삭제</button>
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

        const tablesContainer = document.getElementById("tables");
        tablesContainer.innerHTML = newListItem + tablesContainer.innerHTML;

        // 총건수(Box), 총중량 표시
        document.getElementById("count").value = barcodeList.length + " (Box)";
        const totalWeight = barcodeList.reduce(
          (sum, item) => sum + item.weight,
          0
        );
        document.getElementById("total-weight").value =
          totalWeight.toFixed(1) + " (kg)";

        // 성공 시 필요한 추가 작업 수행
        document.getElementById("totalrec").innerHTML =
          '<font size="3em">[' + platForm + "]total record=" + barcodeList.length;

        // 재고조회
        fetchInventoryData();

        return true;
      }

      // -----------------------------------------------------------
      // Success 삭제결과::목록에서 barcode 삭제 category = 'd'
      //  - 목록에서 삭제하고 전역 array::barcodeList item도 삭제
      // -----------------------------------------------------------
      if (responseObj.category === "invt") {
        // 알림 표시
        // alert(responseObj.barcode_out + " ==> barcode를 삭제했습니다.");

        // barcodeList에서 항목 삭제
        const index = barcodeList.findIndex(
          (item) => item.barcode_out === responseObj.barcode_out
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
          document.getElementById("count").value =
            barcodeList.length + " (Box)";
          const totalWeight = barcodeList.reduce(
            (sum, item) => sum + parseFloat(item.weight),
            0
          );
          document.getElementById("total-weight").value =
            totalWeight.toFixed(1) + " (kg)";

          document.getElementById("totalrec").innerHTML =
            '<font size="3em">[' + platForm + "]total record=" + barcodeList.length;

          // 재고조회
          fetchInventoryData();
        }
      }
    } else {
      // DB 중복!
      // alert("실패: " + responseObj.message);
      document.getElementById("barcodeOutput").focus();
      return false;
      // 실패 시 필요한 추가 작업 수행
    }
  }
} // CodeInvClass -end

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

// -------------------------------------------
// 삭제시작::출고에서는 재고(inventory)로 뺌
// -------------------------------------------
function delBarcodeOut(barcode1) {
  const store_no = document.getElementById("storeNo").value;
  if (
    store_no === "000" ||
    store_no === "001" ||
    store_no === "002" ||
    store_no === "003" ||
    store_no === "004"
  ) {
    alert("삭제는 조은점 재고로 전환합니다. 이미 재고 상태입니다.");
    return;
  }

  // 출고여부확인::해당 바코드에 해당하는 목록을 찾아서 출고여부를 확인
  const index = barcodeList.findIndex((item) => item.barcode_out === barcode1);
  if (index !== -1) {
    // 출고한 제품은 삭제 불가
    if (
      barcodeList[index].invoice_yn === "y"
      // && barcodeList[index].increate_dt !==
      // new Date(new Date().setDate(new Date().getDate() + 1))
      //   .toISOString()
      //   .split("T")[0]
    ) {
      alert("이미 출고한 제품입니다.");
      return;
    }

    // 목록에서 '삭제' 버튼:: 001 ~ 004 인 경우 origin_store_code 로 이동, 그 외는 조은점(000) 으로 이동
    const param = "&barcode_out=" + barcode1;
    const server = "/wp-content/plugins/barcodeInv/barcodeOutInv_srv.php?category=invt" + param;

    console.log("[ln649::delBarcodeOut]" + server);
    getAjax(server, codeOutClass.recvBarcodeOut);
  } else {
    alert(
      barcode1 +
      " => 이 납품점에서 삭제할 수 있는 바코드가 없습니다. 제품 및 바코드를 다시 확인하세요."
    );
    document.getElementById("barcodeOutput").focus();
  }
}

// 출고확정
function finInv() {
  // console.log(barcodeList);

  const increate_dt = document.getElementById("createDate").value; // PO#
  const store_no = document.getElementById("storeNo").value;
  const doc_no = document.getElementById("docNo").value; // PO#

  // console.log("ln667::doc_no=" + doc_no);

  const rtnvalue = fInvoice_Yn(increate_dt, store_no, doc_no);

  if (rtnvalue == true) {
    // 기출고
    alert(
      "출고일=" +
      increate_dt +
      ", 납품점=" +
      store_no +
      ", PO#=" +
      doc_no +
      " => 기출고된 상태입니다."
    );
    return;
  }

  if (store_no === "조은점") {
    alert("조은점(000)#재고는 출고확정 대상이 아닙니다.");
    return;
  }

  // const dept_no = document.getElementById("department").value; // 부서
  let inputValue = document.getElementById("count").value; // Box갯수
  const total_box = parseInt(
    inputValue.match(/\d+/) ? inputValue.match(/\d+/)[0] : 0,
    10
  );
  inputValue = document.getElementById("total-weight").value; // 총중량
  const total_weight = parseFloat(
    inputValue.match(/\d+\.\d+/) ? inputValue.match(/\d+\.\d+/)[0] : 0.0
  );

  // barcodeList를 json 타입으로 파라미터 전달
  const finText = $("#finBtn").text();
  const category =
    $("#finBtn").text() === "출고확정" ? "finInv" : "finInvCancel";

  if (barcodeList.length > 0) {
    const data = {
      category: category,
      increate_dt: increate_dt,
      store_no: decodeURIComponent(store_no),
      doc_no: doc_no,
      total_box: total_box,
      total_weight: total_weight,
      user_id: user_id,
      barcode_out: barcodeList,
    };

    const serverURL = barcodeInv_srv;
    console.log("[ln720::finInv::" + finText + "]", serverURL);

    var result = confirm("[" + finText + "] 하시겠습니까?");
    if (!result) {
      alert("[" + finText + "] 취소하었습니다.");
      return;
    }

    // fetch API를 사용하여 POST 요청 보내기
    fetch(serverURL, {
      method: "POST", // HTTP 메소드 지정
      headers: {
        "Content-Type": "application/json", // 콘텐츠 타입을 JSON으로 지정
      },
      body: JSON.stringify(data), // 전체 데이터를 JSON 문자열로 변환하여 전송
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.json();
      }) // 응답을 JSON 형식으로 변환
      .then((data) => {
        // console.log(data);
        if (data.success) {
          alert(
            "정상 처리하였습니다. INVOICE출력은 PC에서 진행하시기 바랍니다."
          );
          document.getElementById("tables").innerHTML = "";
        } else {
          alert("::ln725::이미 출고한 제품입니다.");
        }
      }) // 서버 응답 출력
      .catch((error) => console.error("Error:", error)); // 오류 처리
  }
}

// 기출고 여부 확인::출고=true='y'
function fInvoice_Yn(increate_dt, store_code, doc_no) {
  const param =
    "&increate_dt=" +
    increate_dt +
    "&store_code=" +
    store_code +
    "&doc_no=" +
    doc_no;
  // console.log("ln764::param=" + param);
  $.ajax({
    url: "/wp-content/plugins/barcodeInv/barcodeOutInv_srv.php?category=invYn" + param,
    type: "GET",
    dataType: "json",
    success: function (data) {
      //data:: 생산일(increate_dt), 납품점(store_no), 기출고여부(invoice_yn)

      const invoice_yn = data.invoice_yn;
      gInvoiceYn = invoice_yn;

      console.log("ln775::invoice_yn=" + invoice_yn);

      const button = $("#finBtn");

      const tomorrow = new Date(); // 새로운 Date 객체 생성
      tomorrow.setDate(tomorrow.getDate() + 1); // 내일 날짜로 설정
      const formattedTomorrow = tomorrow.toISOString().split("T")[0]; // ISO 형식으로 변환

      // 출고확정이더라도 날짜가 오늘인 경우 버튼 활성화
      if (invoice_yn == "y") {
        console.log("ln783::isEditorOrAbove=" + isEditorOrAbove);

        if (formattedTomorrow <= increate_dt || isEditorOrAbove) {
          button.prop("disabled", false);
          button.text("확정취소");
          button.css("background-color", "");
          button.css("cursor", "pointer");
        } else {
          button.prop("disabled", true);
          button.text("출고확정");
          button.css("background-color", "gray");
          button.css("cursor", "not-allowed");
        }
        return true;
      } else if (invoice_yn == "n") {
        button.prop("disabled", false); // 버튼을 활성화
        button.text("출고확정"); // 버튼 텍스트를 '확정취소'로 변경
        button.css("background-color", ""); // 버튼 배경색을 기본값으로 변경
        button.css("cursor", ""); // 커서를 기본값으로 변경
        return false;
      }
    },
    error: function (xhr, status, error) {
      console.error("An error occurred: " + error);
      return true;
    },
  });
}

// 조은점(재고)=inventory 데이터 가져오기::callback 함수로 해야함
// - 제조일자, 소비기한, 중량, 상품코드, 상품명, 출고여부='n'인것만
function barcode_invt(barcode_out, callback) {
  const url_param =
    "/wp-content/plugins/barcodeInv/barcodeOutInv_srv.php?category=c4&barcode_out=" + barcode_out;
  $.ajax({
    url: url_param,
    type: "GET", // HTTP 요청 방식
    dataType: "json", // 응답 데이터 타입
    success: function (data) {
      try {
        // console.log("url_param=" + url_param);

        items = data.response.body.items;
        totalCount = data.response.body.totalCount;
        resultCode = data.response.header.resultCode;

        var prod_nm = items[0]["prod_nm"];
        callback(prod_nm); // 콜백 함수 호출하여 결과 전달
      } catch (e) {
        console.log(e.message);
        alert(
          "재고에 없는 제품입니다. 납품점 및 재고가 있는지 확인하시기 바랍니다."
        );
        callback(false); // 오류 발생 시에도 콜백 함수 호출
      }
    },
    error: function (xhr, status, error) {
      console.error("An error occurred: " + error);
      callback(false); // 오류 발생 시에도 콜백 함수 호출
    },
  });
}

function invoicePrint(storeCode, deliveryDate, doc_no) {
  // 납품점 코드(store_no)와 납품일자 확인
  if (!storeCode.trim() || !deliveryDate || !doc_no) {
    alert("납품일자, 납품점, PO# 모두 입력해 주세요.");
    return;
  }

  const url = `/jof/datas/invoicePrint_srv.php?deliveryDate=${encodeURIComponent(
    deliveryDate
  )}&storeCode=${encodeURIComponent(storeCode)}&doc_no=${encodeURIComponent(
    doc_no
  )}`;

  console.log("ln861::url=" + url);

  $.ajax({
    url: url,
    type: "GET",
    beforeSend: function () {
      console.log("송장 인쇄 요청을 서버로 전송 중입니다.");
    },
    success: function (response) {
      // console.log(response);
      try {
        const jsonResponse = JSON.parse(response);
        if (jsonResponse.error) {
          alert(jsonResponse.error);
          return;
        }
      } catch (e) {
        console.error("JSON 파싱 중 오류가 발생했습니다. " + e.message);
      }

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

  console.log("ln909::url=" + url);

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
}// bl번호 수동입력 추가되는 JavaScript 함수들
