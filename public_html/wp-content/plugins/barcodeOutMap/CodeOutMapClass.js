class CodeOutMapClass { 
  constructor({
    col = [
      "NO.",
      "입고일자",
      "입력자",
      "LC번호",
      "납품점#",
      "색깔",
      "Barcode",
      "제조일자",
      "소비기한",
      "중량(kg)",
      "상품명",
      "출고여부",
      "생산일자",
    ],
    col2 = [
      "no",
      "increate_dt",
      "display_name",
      "lc_no",
      "store_no",
      "pack_color",
      "barcode_out",
      "manu_date",
      "expi_date",
      "weight",
      "prod_nm",
      "invoice_yn",
      "create_dt",
    ],
    col3 = ["c", "c", "c", "c", "c", "c", "l", "c", "c", "c", "l", "c", "c"],
    colw = [
      "5%",
      "6%",
      "6%",
      "6%",
      "6%",
      "6%",
      "15%",
      "8%",
      "8%",
      "7%",
      "8%",
      "5%",
      "12%",
    ],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;

    // 전역 변수를 클래스 프로퍼티로 저장
    this.barcodeOutMap_srv = window.barcodeOutMap_srv;
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

        // 첫 번째 열에는 정렬 링크를 추가하지 않음
      if (i === 0 || i === 1 || i === 2 ) {
        th.innerHTML = this.col[i];
      } else {
        th.innerHTML = this.col[i] + '<a onclick="sortTD (' + i +')">▲</a><a onclick="reverseTD (' + i + ')">▼</a>';
      }

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
        let tabCells = tr.insertCell(-1);

        // tabCells.innerHTML = idx;
        tabCells.innerHTML = items[i][this.col2[0]];

        tabCells.setAttribute("style", "text-align:center;");
        for (let j = 1; j < this.col.length; j++) {
          let tabCell = tr.insertCell(-1);

          // 클릭 이벤트 처리:: row 선택 색깔 초록색
          tr.onclick = function() {
            // 이전에 선택된 행의 스타일 제거
            const selectedRows = tbody.getElementsByClassName('selected-row');
            for (let row of selectedRows) {
              row.classList.remove('selected-row');
            }
            // 현재 클릭된 행에 스타일 적용
            this.classList.add('selected-row');
          };

          // 데이터표시
          tabCell.innerHTML = items[i][this.col2[j]];

          if (this.col2[j] === 'store_no') {
            let storeNo = items[i][this.col2[j]];
            // "코스트코홀세일"을 제거하고 표시
            if (storeNo.includes('코스트코홀세일')) {
              storeNo = storeNo.replace('코스트코홀세일', '');
            }
            if (storeNo.includes('연어필렛완출점')) {
              storeNo = storeNo.replace('연어필렛완출점', '');
            }
            tabCell.innerHTML = storeNo;
          }

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

          // store_no가 '원재료'이면 row 바탕색을 노란색으로 설정
          if (items[i]["store_no"] === "원재료") {
            tr.style.backgroundColor = "yellow";
          }

          // store_no가 '원재료'이면 row 바탕색을 노란색으로 설정
          if (items[i]["store_no"] === "완출점") {
            tr.style.backgroundColor = "lightblue";
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
          tabCell.innerHTML = CodeOutMapClass.setLink(items, i, j, tabCell);
        } // column
      } catch (ex) {
        console.log(ex.message);
      }

      // 중량 합계 구하기
      let itemWeight = parseFloat(items[i]["weight"]);
      if (!isNaN(itemWeight)) {
        totalWeight += itemWeight;
      } else {
        console.error("중량 데이터가 유효하지 않습니다: ", items[i]["weight"]);
      }
      // totalWeight += parseFloat(items[i]["weight"]).toFixed(1);
    }

    // FINALLY ADD THE NEWLY CREATED TABLE WITH JSON DATA TO A CONTAINER.
    let divContainer = document.getElementById("tables");
    //divContainer.innerHTML = "";
    divContainer.appendChild(table1);

    // 갯수(Box), 총중량(kg)표시
    document.getElementById("count").value = items.length + " (Box)";
    // ${items[i]["weight"]} => 합계

    // totalWeight 값을 숫자로 강제 변환하기
    var totalWeightNum = Number(totalWeight);
    // 숫자로 변환된 값에 toFixed() 적용하기
    document.getElementById("total-weight").value =
      totalWeightNum.toFixed(1) + " (kg)";

    return rtnCnt;
  }

  // 링크 만들기
  static setLink(items, i, j, cell) {
    let today = getToday("-");
    // 0:번호, 1:일련번호
    switch (j) {
      case 1: // 입고일자자자
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

      case 7: // 입력일자
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
        pack_color: items[i]["pack_color"],
        barcode_out: items[i]["barcode_out"],
        manu_date: items[i]["manu_date"],
        expi_date: items[i]["expi_date"],
        weight: parseFloat(items[i]["weight"]),
        prod_nm: items[i]["prod_nm"],
        invoice_yn: items[i]["invoice_yn"],
      };

      barcodeList.push(newItem);
      const getToday = getCurrentDateTime();
      licn += `<li class="list-item" id ="item-no${idx}">
                <a href="javascript:void(0);" class="full-link" onclick="openButton(${idx})">
                    <div class="content">
                        <span class="item">[${items[i]["no"]}]BL#:${items[i]["lc_no"]}, ${newItem.store_no},  ${items[i]["prod_nm"]}, ${newItem.weight} kg</span>
                        <div class="item-details">
                        ${newItem.pack_color}, ${newItem.barcode_out} 제조:${items[i]["create_dt"]} 납기:${items[i]["increate_dt"]}  소비기한:${newItem.expi_date} [출고:${items[i]["invoice_yn"]}]
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

    // totalWeight 값을 숫자로 강제 변환하기
    var totalWeightNum = Number(totalWeight);
    // 숫자로 변환된 값에 toFixed() 적용하기
    document.getElementById("total-weight").value =
      totalWeightNum.toFixed(1) + " (kg)";
  }

  // -----------------------------------------------------------------
  // 저장(입력)시작::목록에 바코드 보여주고 성공이면 DB 저장
  // - category 1i:입력, 1s:조회, 2:조회
  // - inptuArray 6개 입력 field: 입고일자자, BL#, barcode1,2, 중량, 생산일
  // -----------------------------------------------------------------
  ajax_barcodeOut(inputArray, category) {
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
    const pack_color = inputArray[3];
    const barcode_out = inputArray[4];
    const manu_date = inputArray[5];
    const expi_date = inputArray[6];
    // const weight = Number(parseFloat(inputArray[7]).toFixed(1));  // 소수점 1자리로 정확히 고정
    const weight = Math.round(parseFloat(inputArray[7]) * 10) / 10;
    const prod_code = inputArray[8];
    const prod_nm = inputArray[9];

    newItem = {
      no: nextNo,
      increate_dt: increate_dt,
      lc_no: lc_no,
      store_no: store_no,
      pack_color: pack_color,
      barcode_out: barcode_out,
      manu_date: manu_date,
      expi_date: expi_date,
      weight: weight,
      prod_code: prod_code,
      prod_nm: prod_nm,
    };

    // barcode1, 2 합쳐서 중복 있는지 체크
    const isDuplicate = barcodeList.some(
      (item) => item.barcode_out === newItem.barcode_out
    );

    // ------------------------------------------
    // barcode_out 중복이면 alert
    // ------------------------------------------
    if (isDuplicate) {
      console.log("이미 입력한 제품(바코드) 입니다!");
      alert("이미 입력한 제품(바코드) 입니다!");
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
      "&pack_color=" +
      pack_color +
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

    // ----------------------------
    // 입력: return true;
    // 조회: return 조회 Data
    // ----------------------------
    const server = barcodeOut_srv + "?category=" + category + parm;
    console.log("[ln400::ajax_barcodeOut]" + server);
    getAjax(server, codeOutMapClass.recvBarcodeOut);
  }

  // ------------------------------------
  // 바코드 입력 및 목록에서 삭제 처리
  // 입력: category '1':입력, '2':조회
  // 삭제: category 'd'
  // ------------------------------------
  recvBarcodeOut(response) {
    move_stop();

    // 서버 응답을 JSON 객체로 변환
    var responseObj = JSON.parse(response);

    if (responseObj.success) {
      // --------------------------------------
      // barcode 입력
      // --------------------------------------
      if (responseObj.category === "1i") {
        barcodeList.push(newItem);

        // 임시: 상품코드 560770='연어필렛', 651343='연어(스테이크)'
        const displayText = `
        <div class="content">
            <span class="item">[${newItem.no}] BL#::${
          newItem.lc_no
        } [입고일자] ${newItem.increate_dt}</span>
            <div class="item-details">
              점번호#: ${newItem.store_no}, 색깔:${newItem.pack_color} ,
              barcode: ${newItem.barcode_out}<br>
              제조일자: ${newItem.manu_date}, 소비기한: ${newItem.expi_date}, 
              중량:${newItem.weight.toFixed(1)}, 상품명:${newItem.prod_nm} (※DB: )
            </div>
        </div>
        `;

        // 삭제 button, style과 class를 기존 CSS에 맞게 조정한 delBtn
        const delBtn = `
          <button type="button" class="del-btn" style="display:none;" id="link${parseInt(newItem.no)}" onclick='delBarcodeOut("${newItem.barcode_out}")'>삭제</button>
        `;

        // 삭제 Key값 id를 포함한 newListItem
        const newListItem = `
          <li class="list-item" id="item-no${newItem.no}">
            <a href="javascript:void(0);" class="full-link" onclick="event.stopPropagation(); openButton(${parseInt(newItem.no)});">
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
        const totalWeight = barcodeList.reduce((sum, item) => sum + Number(item.weight), 0);

        // 숫자로 변환된 값에 toFixed() 적용하기
        document.getElementById("total-weight").value =   totalWeight.toFixed(1) + " (kg)";

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

          // totalWeight 값을 숫자로 강제 변환하기
          var totalWeightNum = Number(totalWeight);
          // 숫자로 변환된 값에 toFixed() 적용하기
          document.getElementById("total-weight").value =
            totalWeightNum.toFixed(1) + " (kg)";

            document.getElementById("totalrec").innerHTML =
            '<font size="3em">[' + platForm +  "]total record=" + barcodeList.length;

        }
      }
    } else {
      // DB 중복!
      alert(
        "중복 제품: " +
          responseObj.message +
          "\n문의사항은 해당 에러메시지를 전산실에 전달하세요."
      );
      document.getElementById("barcodeOutput").focus();
      return false;
      // 실패 시 필요한 추가 작업 수행
    }
  }
} // CodeOutMapClass

// 삭제시작
function delBarcodeOut(barcode1) {
  const store_no = document.getElementById("storeNo").value;
  // if (store_no == "조은점" || store_no == "000") {
  //   alert(
  //     "점번호가 없거나, 조은점(재고)는 삭제할 수 없습니다. 전산실에 문의하세요."
  //   );
  //   return;
  // }
  if (confirm(barcode1 + " => 영구 삭제합니다.")) {
    // 사용자가 '확인'을 클릭했을 때 실행할 코드
    // category='d'
    param = "&barcode_out=" + barcode1 + "&user_id=" + user_id;
    server = "/wp-content/plugins/barcodeOut/barcodeOut_srv.php?category=d" + param;
    console.log("[delBarcodeOut]" + server);

    getAjax(server, codeOutMapClass.recvBarcodeOut);
  } else {
    // 사용자가 '취소'를 클릭했을 때 실행할 코드
    console.log("삭제를 취소합니다.");
  }
}

// 엑셀 변환
function tableToExcel(
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
    // 5번째 컬럼(td)의 값을 텍스트로 변환
    if (index % clonedTable.rows[0].cells.length === 4) {
      // index는 0부터 시작하므로 5번째 컬럼의 인덱스는 4입니다.
      td.textContent = `'${td.textContent}`;
    } else {
      td.textContent = td.textContent;
    }
  });

  // 엑셀 파일 생성
  const createDate = document.getElementById("createDate").value;
  let lcNumber = document.getElementById("lcNumber").value;
  if (lcNumber === "") lcNumber = "0000";
  name = createDate + "_" + lcNumber;

  var ctx = {
    worksheet: name || "Worksheet",
    table: clonedTable.innerHTML,
  };

  var link = document.createElement("a");
  link.download = filename + "_" + dToDate + "_" + lcNumber + ".xls";
  link.href = uri + base64(format(template, ctx));
  document.body.appendChild(link); // Firefox에서 필요함
  link.click();
  document.body.removeChild(link); // 생성된 링크 요소 제거
  alert("filename= '" + filename + "'로 다운로드 폴더에 저장되었습니다.");
}