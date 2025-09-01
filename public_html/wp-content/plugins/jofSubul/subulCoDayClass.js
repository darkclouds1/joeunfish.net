// 일자별 BL# 입고,출고,생산,판매 현황 조회

class subulCoDayClass {
  constructor({
    col = ["구분", "B/L번호", "awb", "입|출고일<br>(생산|납품일)", "BOX", "무게(kg)", "제품", "수율", "판매점"],
    col2 = ["data_type", "bl_no", "awb", "date", "vol", "weight", "prod_nm", "yield_percent", "store_no"],
    col3 = ["l", "c", "c", "d", "r", "r", "l", "c", "l"],
    colw = ["10%", "8%", "13%", "8%", "8%", "8%", "18%", "8%", "19%"],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    this.table1 = null; // 테이블 프로퍼티 추가
    // this.barcodeInv_srv = window.barcodeInv_srv;
  }

  makeTableHead() {

    let idx = 0;
    this.table1 = document.createElement("table");
    this.table1.setAttribute("id", "specData");
    this.table1.setAttribute("style", "width:100%");

    // 테이블 헤드가 있으면 테이블 헤드 생성은 스킵
    if (this.table1.querySelector('thead')) {
      return;
    }

    var header = this.table1.createTHead();
    var tr = header.insertRow(-1); // TABLE ROW.

    for (var i = 0; i < this.col.length; i++) {
      var th = document.createElement("th"); // TABLE HEADER.

      // 헤더 sort 설정
      th.innerHTML = this.col[i];
      if (i === -1) {
        th.innerHTML += '<a onclick="sortTD (' + i + ')">▲</a><a onclick="reverseTD (' + i + ')">▼</a>';
      }

      th.setAttribute("style", "width:" + this.colw[i] + ";");
      tr.appendChild(th);
    }

    if ((document.getElementById("tables-co").innerHTML = "")) {
      document.getElementById("tables-co").innerHTML = this.table1.outerHTML;
      idx = 0;
    }
  }

  // 생산 바코드출고 makeTable
  makeTableBody(datas) {
    // console.log("ln56::datas=" + JSON.stringify(datas));

    // BL번호 존재 여부 맵 미리 생성
    this.blNoExistsMap = {};
    const productionRecords = this.data.filter(item => item[this.col2[0]] === '생산내역');

    // 모든 생산내역의 BL번호를 맵에 기록
    productionRecords.forEach(record => {
      if (record['bl_no']) {
        this.blNoExistsMap[record['bl_no']] = true;
      }
    });

    let items = datas;
    rtnCnt = items.length;

    // 데이터 없음
    if (items.length == 0) {
      alert("데이터가 없습니다. 조회 방법을 확인하세요.");
      return rtnCnt;
    }

    let idx = 0;
    let tbody = this.table1.createTBody();
    for (let i = 0; i < items.length; i++) {
      try {
        // // box_count 가 0이면 표시 안함
        // if (items[i]["vol"] == 0) {
        //   continue;
        // }

        idx = idx + 1;
        let tr = tbody.insertRow(-1);

        // type_data = '원료입고' 이면 row 항상 대각선 사선 스크래치로 표시
        if (items[i][this.col2[0]] === '원료입고') {
          tr.style.backgroundColor = "#d9d9d9";
        }

        if (items[i][this.col2[0]] === '원료출고') {
          tr.style.backgroundColor = "#f2f2f2";
        }

        // data_type column에 '합계'가 포함되어 있으면 노란색으로 표시
        if (items[i].data_type && items[i].data_type.includes("생산소계")) {
          tr.style.backgroundColor = "#ffff66";
        }

        // data_type column에 '기타합계'가 포함되어 있으면 노란색으로 표시
        if (items[i].data_type && items[i].data_type.includes("합계")) {
          tr.style.backgroundColor = "#ffff66";
        }

        // data_type column에 '합계'가 포함되어 있으면 노란색으로 표시
        if (items[i].data_type && items[i].data_type.includes("납품소계")) {
          tr.style.backgroundColor = "#c0daf2";
        }

        // 완제품에 스테이크 포함되어 있으면 연한 분홍색
        if ((items[i].data_type && items[i].data_type.includes("납품내역")) && (items[i].prod_nm && items[i].prod_nm.includes("스테이크"))) {
          tr.style.backgroundColor = "#f2f2f2";  // 연한 회색
        }

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
        tabCells.innerHTML = items[i][this.col2[0]];

        // cell data
        tabCells.setAttribute("style", "text-align:center;");


        for (let j = 1; j < this.col.length; j++) {
          let tabCell = tr.insertCell(-1);

          // date 형식 변환 - 4/22(화) 형식으로 변환
          if (this.col2[j] === "date") {
            const rawDate = items[i][this.col2[j]];
            if (!rawDate) {
              items[i][this.col2[j]] = '';
            } else {
              const date = new Date(rawDate);
              const month = date.getMonth() + 1;
              const day = date.getDate();
              const weekday = date.toLocaleDateString('ko-KR', { weekday: 'short' }); // 예: '화'
              items[i][this.col2[j]] = `${month}/${day}(${weekday})`;
            }
          }

          // 데이터 표시
          if (this.col2[j] === "store_no") {
            // 판매점 이름에서 '점' 제거
            const storeName = items[i][this.col2[j]] || "";
            tabCell.innerHTML = storeName.replace(/점\s*(?=\(|$)/g, '');
          } else {
            tabCell.innerHTML = items[i][this.col2[j]] || "";
          }

          // ---------------------
          // 문자, 숫자, 날짜 정렬
          // ---------------------
          let attr = "";
          if (this.col3[j] == "c") attr += "text-align:center;";
          else if (this.col3[j] == "l") attr += "text-align:left;";
          else if (this.col3[j] == "r") attr += "text-align:right;";
          else if (this.col3[j] == "d") {
            // date
            attr += "text-align:center;";
            if (tabCell != null && tabCell.innerHTML.length > 10)
              tabCell.innerHTML = tabCell.innerHTML.substring(0, 10);
          }

          // 원료출고이고 BL번호가 없는 경우의 스타일 처리
          if (items[i][this.col2[0]] === '원료출고' && j > 0 && !this.blNoExistsMap[items[i]['bl_no']]) {
            attr += "color: red !important;";
          }

          // 기존 스타일 가져오기
          const existingStyle = tabCell.getAttribute("style") || "";
          // 스타일 끝에 세미콜론이 없다면 추가
          const normalizedStyle = existingStyle.endsWith(';') ? existingStyle : existingStyle + ';';
          // 새로운 스타일 추가
          tabCell.setAttribute("style", normalizedStyle + attr);


          // 셀에 링크 추가
          // tabCell.innerHTML = CodeInvClass.setLink(items, i, j, tabCell);

        } // column
      } catch (ex) {
        console.log(ex.message);
      }
    }

    let divContainer = document.getElementById("tables-co");

    divContainer.appendChild(this.table1);

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
  // mobile
  // --------------------------
  makeTableMobile(datas) {
    const items = Array.isArray(datas) ? datas : [];
    const container = document.getElementById("tables-co");
    if (!container) {
      console.error("Element with id 'tables-co' not found");
      return;
    }
    container.innerHTML = "";

    // 기타판매 존재 여부 확인
    const hasEtcSales = items.some(item => item && item.data_type === '기타판매');

    // 생산내역의 BL# 목록 생성
    const productionBlNumbers = new Set();
    items.forEach(item => {
      if (item && item.data_type === '생산내역' && item.bl_no) {
        productionBlNumbers.add(item.bl_no);
      }
    });

    // data_type별로 그룹화
    const groupedData = items.reduce((acc, item) => {
      if (!item || !item.data_type) return acc;

      const type = item.data_type;

      // 기타합계 처리: box가 0인 경우만 제외 (기타판매 존재 여부와 관계없이)
      if (type === '기타합계' && (!item.vol || item.vol === 0)) {
        return acc;
      }

      if (!acc[type]) acc[type] = [];

      if (type === '납품내역') {
        const prodGroup = item.prod_nm || '기타';
        if (!acc[type][prodGroup]) {
          acc[type][prodGroup] = [];
        }
        acc[type][prodGroup].push(item);
      } else {
        acc[type].push(item);
      }
      return acc;
    }, {});

    // 데이터 표시 함수 수정
    const createDataItem = (label, value, type = '', className = '') => {
      if (value == null || value === '' || value === 'null') return null;

      // 날짜 포맷팅 함수
      const formatDate = (dateStr) => {
        const date = new Date(dateStr);
        const month = date.getMonth() + 1;
        const day = date.getDate();
        const weekDay = ['일', '월', '화', '수', '목', '금', '토'][date.getDay()];
        return `${month}/${day}(${weekDay}`; // 괄호 하나만 사용
      };

      // 생산내역, 생산소계, 원료출고, 원료입고, 납품소계, 납품내역의 특별 처리
      if (['생산내역', '생산소계', '원료출고', '원료입고', '납품소계', '납품내역'].includes(type)) {
        if (label === 'BL#') return `<span class="data-value ${type === '원료출고' && !productionBlNumbers.has(value) ? 'highlight-red bl-no' : ''}">BL#:${value}</span>`;
        if (label === '제품') return `<span class="data-value">${value}</span>`;
        if (label === 'Box') return `<span class="data-value"> ${value}(${type === '원료출고' ? 'box' : 'Box'})</span>`;
        if (label === 'kg') return `<span class="data-value"> ${value}(kg)</span>`;
        if (label === '수율') return `<span class="data-value"> 수율(${value})</span>`;
        if (label === '통관일') {
          return `<span class="data-value">통관(${formatDate(value)})</span>`;
        }
        if (label === '판매점') {
          const storeName = value.replace(/점\s*(?=\(|$)/g, '');
          return `<span class="data-value">${storeName}</span>`;
        }
        if (label === '판매점코드') return `<span class="data-value">(${value})</span>`;
      }

      return `
        <div class="data-item ${className}">
          <span class="data-label">${label}:</span>
          <span class="data-value">${value}</span>
        </div>
      `;
    };

    // 레코드 생성 함수 추가 (코드 재사용을 위해)
    const createRecordRow = (item, idx, type) => {
      const recordContainer = document.createElement('div');
      recordContainer.className = 'record-container';

      const mainRow = document.createElement('div');
      mainRow.className = type === '납품내역' ? 'data-row delivery-record' : 'data-row';

      let rowContent = `<span class="record-number">${idx + 1}.</span>`;

      // 타입별 처리
      if (type === '원료입고') {
        rowContent += createDataItem('통관일', item.date, type);
        rowContent += ' ';
        if (item.bl_no) {
          rowContent += createDataItem('BL#', item.bl_no, type);
          rowContent += ' ';
        }
        rowContent += createDataItem('제품', item.prod_nm, type);
        rowContent += createDataItem('Box', item.vol, type);
        rowContent += createDataItem('kg', item.weight, type);
      } else if (type === '납품소계') {
        rowContent += createDataItem('제품', item.prod_nm, type);
        rowContent += createDataItem('Box', item.vol, type);
        rowContent += createDataItem('kg', item.weight, type);
      } else if (type === '납품내역') {
        rowContent += createDataItem('판매점', item.store_no, type);
        if (item.store_code && item.store_code !== 'null') {
          rowContent += createDataItem('판매점코드', item.store_code, type);
        }
        rowContent += createDataItem('Box', item.vol, type);
        rowContent += createDataItem('kg', item.weight, type);
      } else if (['생산내역', '생산소계', '원료출고'].includes(type)) {
        if (item.bl_no) {
          rowContent += createDataItem('BL#', item.bl_no, type);
          rowContent += ' ';
        }
        rowContent += createDataItem('제품', item.prod_nm, type);
        rowContent += createDataItem('Box', item.vol, type);
        rowContent += createDataItem('kg', item.weight, type);
        if (type !== '원료출고' && item.yield_percent) {
          rowContent += createDataItem('수율', item.yield_percent, type);
        }
      } else if (type === '기타판매' || type === '기타합계') {
        // 기타판매와 기타합계 처리 추가
        rowContent += createDataItem('제품', item.prod_nm, type);
        rowContent += createDataItem('Box', item.vol, type);
        rowContent += createDataItem('kg', item.weight, type);
        if (type === '기타판매' && item.store_no) {
          rowContent += ' ';
          rowContent += createDataItem('판매점', item.store_no, type);
        }
      }

      mainRow.innerHTML = rowContent;
      recordContainer.appendChild(mainRow);
      return recordContainer;
    };

    // 각 그룹별로 카드 생성
    Object.entries(groupedData).forEach(([type, items]) => {
      const card = document.createElement('div');
      card.className = 'mobile-card';

      // 초기 상태 설정
      if (type === '납품내역' || type === '원료입고' || type === '원료출고') {
        card.className += ' collapsed';
      }

      // 배경색 설정
      if (type === '원료입고') card.style.backgroundColor = '#d9d9d9';
      else if (type === '원료출고') card.style.backgroundColor = '#f2f2f2';
      else if (type.includes('생산소계') || type === '기타합계') card.style.backgroundColor = '#ffff66';
      else if (type.includes('납품소계')) card.style.backgroundColor = '#c0daf2';
      else if (type === '납품내역' && items[0]?.prod_nm?.includes('스테이크')) {
        card.style.backgroundColor = '#f2f2f2';
      }

      // 헤더 생성
      const header = document.createElement('div');
      header.className = 'card-header';

      // 날짜 가져오기
      let dateStr = '';
      if (items.length > 0) {
        const firstItem = Array.isArray(items) ? items[0] : Object.values(items)[0][0];
        if (firstItem && firstItem.date) {
          const date = new Date(firstItem.date);
          const month = date.getMonth() + 1;
          const day = date.getDate();
          const weekDay = ['일', '월', '화', '수', '목', '금', '토'][date.getDay()];
          dateStr = `(${month}/${day}(${weekDay}))`.replace('))', ')'); // 중복 괄호 제거
        }
      }

      header.innerHTML = `
        <div class="header-main">
          ${type}
          <span class="header-date">${dateStr}</span>
        </div>
        <span class="click-indicator">
          <span class="arrow">▼</span>
        </span>
      `;
      card.appendChild(header);

      const content = document.createElement('div');
      content.className = 'card-content';
      if (type === '납품내역' || type === '원료입고' || type === '원료출고') {
        content.style.display = 'none';
      }

      // 타입별 내용 생성
      if (type === '납품소계') {
        // 납품소계 특별 처리
        items.forEach((item, idx) => {
          const recordContainer = createRecordRow(item, idx, type);
          content.appendChild(recordContainer);
        });
      } else if (type === '납품내역') {
        // 납품내역 처리 (기존 코드)
        Object.entries(items).forEach(([prodGroup, groupItems]) => {
          const groupContainer = document.createElement('div');
          groupContainer.className = 'product-group';

          // 제품 그룹 헤더 추가
          const groupHeader = document.createElement('div');
          groupHeader.className = 'product-group-header';
          groupHeader.textContent = prodGroup;
          groupContainer.appendChild(groupHeader);

          // 그룹 내 아이템 표시
          groupItems.forEach((item, idx) => {
            const recordContainer = createRecordRow(item, idx, type);
            groupContainer.appendChild(recordContainer);
          });

          content.appendChild(groupContainer);
        });
      } else {
        // 다른 타입 처리
        if (Array.isArray(items)) {
          items.forEach((item, idx) => {
            const recordContainer = createRecordRow(item, idx, type);
            content.appendChild(recordContainer);
          });
        }
      }

      card.appendChild(content);

      // 모든 카드에 토글 이벤트 추가
      header.addEventListener('click', () => {
        card.classList.toggle('collapsed');
        content.style.display = card.classList.contains('collapsed') ? 'none' : 'block';
        const arrow = header.querySelector('.arrow');
        if (card.classList.contains('collapsed')) {
          arrow.textContent = '▼';
        } else {
          arrow.textContent = '▲';
        }
      });

      // 카드가 닫힌 상태에서 ▼, 열린 상태에서 ▲로 초기화
      if (card.classList.contains('collapsed')) {
        header.querySelector('.arrow').textContent = '▼';
      } else {
        header.querySelector('.arrow').textContent = '▲';
      }

      container.appendChild(card);
    });
  }

  // -----------------------------------------------------------------
  // 조회시작::목록에 바코드 보여주고 성공이면 DB 저장
  // - category=search_BlDay, 
  // - 조회조건: 일자, BL# - Bl#은 전체 조회 가능
  // -----------------------------------------------------------------
  loadData() {

    console.log("ln470::loadData");
    setTimeout(function () {
      move();
    }, 100);

    const startDate = document.getElementById("startDate-co").value;

    const param = `start_date=${startDate}`;
    const url = subul_co_srv + "?category=search_CoDay&" + param;

    // 테이블 컨테이너 초기화
    const tableContainer = document.getElementById("tables-co");
    tableContainer.innerHTML = "";

    console.log("ln222::url=" + url);
    fetch(url)
      .then((response) => {
        if (!response.ok) {
          throw new Error("네트워크 응답이 실패했습니다.");
        }
        return response.json();
      })
      .then((result) => {
        try {
          if (result.status === 'success') {
            this.data = result.data;
            const tableContainer = document.getElementById("tables-co");
            tableContainer.innerHTML = ""; // 기존 내용을 초기화

            if (platForm === "computer") {
              if (this.data.length > 0) {
                this.makeTableHead();
                this.makeTableBody(this.data);
              } else {
                const noDataMessage = document.createElement("div");
                noDataMessage.textContent = "데이터가 없습니다";
                noDataMessage.classList.add('no-data-message');
                tableContainer.appendChild(noDataMessage);
              }
            } else {
              if (this.data.length > 0) {
                this.makeTableMobile(this.data);
              } else {
                const noDataMessage = document.createElement("div");
                noDataMessage.textContent = "데이터가 없습니다";
                noDataMessage.classList.add('no-data-message');
                tableContainer.appendChild(noDataMessage);
              }
            }
          }
        } catch (e) {
          console.error("응답 처리 오류:", e);
          const tableContainer = document.getElementById("tables-co");
          const errorMessage = document.createElement("div");
          errorMessage.textContent = e.message;
          errorMessage.classList.add('no-data-message');
          tableContainer.appendChild(errorMessage);
          throw e;
        }
      })
      .catch((error) => {
        console.error("데이터 로드 중 오류 발생:", error);
        const tableContainer = document.getElementById("tables-co");
        const errorMessage = document.createElement("div");
        errorMessage.textContent = error.message;
        errorMessage.classList.add('no-data-message');
        tableContainer.appendChild(errorMessage);
      })
      .finally(() => {
        move_stop();
      });
  }
}

// 화면 엑셀 변환
function tableToExcelCo(
  tableId = "specData",
  name = "subulCoDay",
  filename = "joeunfish_Costco_수불"
) {
  let uri = "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,",
    template =
      '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines>1</x:DisplayGridlines></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/><style>table { border-collapse: collapse; } th { border: 0.5pt solid black; } td { background-color: transparent !important; } .text-cell { mso-number-format: "@"; }</style></head><body><table>{table}</table></body></html>',
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
    alert("조회된 테이블이 없습니다. 조회 후 다운하시기 바랍니다.:: " + tableId);
    return;
  }

  // 테이블 복제 및 헤더 처리
  let clonedTable = tableElement.cloneNode(true);
  if (clonedTable.tHead) {
    Array.from(clonedTable.tHead.querySelectorAll("th")).forEach((th) => {
      th.innerHTML = th.innerHTML.replace(/▲|▼/g, "");
      th.style.border = "0.5pt solid black";
    });
  }

  // 셀 데이터 및 스타일 처리
  Array.from(clonedTable.querySelectorAll("tr")).forEach((tr) => {
    const backgroundColor = tr.style.backgroundColor;
    tr.style.backgroundColor = 'transparent'; // 행의 배경색 초기화

    Array.from(tr.cells).forEach((cell, cellIndex) => {
      // 모든 셀의 배경색과 테두리를 초기화
      cell.style.backgroundColor = 'transparent';
      cell.style.border = 'none';

      // A1부터 I열까지만 테두리와 배경색 적용
      if (cellIndex <= 8) {
        cell.style.border = "0.5pt solid black";
        if (backgroundColor) {
          cell.style.backgroundColor = backgroundColor;
        }
      }

      // B/L번호(2번째 열), BOX(5번째 열), 수율(8번째 열)을 텍스트 모드로 설정
      if (cellIndex === 1 || cellIndex === 4 || cellIndex === 7) {
        cell.className = 'text-cell';
      }
    });
  });

  // 날짜 형식 변환 및 파일명 생성
  const date = new Date(document.getElementById("startDate-co").value);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const weekDay = ['일', '월', '화', '수', '목', '금', '토'][date.getDay()];
  const formattedDate = `${date.getFullYear()}-${month}-${day}(${weekDay})`;

  // 워크시트 이름과 파일명에 사용
  name = formattedDate;

  var ctx = {
    worksheet: formattedDate,  // 워크시트 탭 이름을 날짜 형식으로 설정
    table: clonedTable.innerHTML
  };

  var link = document.createElement("a");
  link.download = filename + "_" + formattedDate + ".xls";
  link.href = uri + base64(format(template, ctx));
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  alert(
    "filename= '" +
    filename +
    "_" +
    formattedDate +
    "'로 다운로드 폴더에 저장되었습니다."
  );
}

// 일지 엑셀 변환
function tableToExcelCoDay(deliveryDate) {
  // 날짜 형식 변환
  const date = new Date(document.getElementById("startDate-co").value);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const weekDay = ['일', '월', '화', '수', '목', '금', '토'][date.getDay()];
  const formattedDate = `${date.getFullYear()}-${month}-${day}(${weekDay})`;

  // 로딩 표시
  const loadingDiv = document.createElement('div');
  loadingDiv.id = 'excel-loading';
  loadingDiv.innerHTML = '엑셀 파일 생성 중...';
  document.body.appendChild(loadingDiv);

  // 요청 데이터 생성
  const requestData = {
    deliveryDate: deliveryDate,
    formattedDate: formattedDate
  };

  // AJAX 요청 실행
  const url = subul_co_srv_day + "?category=search_CoDay&deliveryDate=" + encodeURIComponent(deliveryDate) + "&print=true&remove_store_suffix=true";
  console.log("ln703::url=" + url);
  $.ajax({
    url: url,
    type: 'POST',
    data: JSON.stringify(requestData),
    contentType: 'application/json',
    xhrFields: {
      responseType: 'blob' // 응답을 blob으로 받도록 설정
    },
    success: function (response) {
      // 로딩 제거
      document.body.removeChild(loadingDiv);

      // blob URL 생성
      const blob = new Blob([response], { type: 'application/vnd.ms-excel' });
      const url = window.URL.createObjectURL(blob);

      // 파일 다운로드 링크 생성 및 클릭
      const link = document.createElement('a');
      link.href = url;
      link.download = `업무일지_${formattedDate}.xls`; // 파일명 설정
      document.body.appendChild(link);
      link.click();

      // 정리
      window.URL.revokeObjectURL(url);
      document.body.removeChild(link);

      alert(`파일이 다운로드 폴더에 저장되었습니다: 업무일지_${formattedDate}.xls`);
    },
    error: function (xhr, status, error) {
      // 로딩 제거
      document.body.removeChild(loadingDiv);
      alert('서버 요청 중 오류가 발생했습니다: ' + error);
    },
    complete: function () {
      // 항상 로딩 제거
      const loadingDiv = document.getElementById('excel-loading');
      if (loadingDiv) {
        document.body.removeChild(loadingDiv);
      }
    }
  });
}

// CO 생산 및 출고 내역 출력
function printCoDay(deliveryDate) {
  if (!deliveryDate) {
    alert("납품일자를 입력해 주세요.");
    return;
  }

  // 출력 없이 엑셀 다운로드 실행
  // downloadExcelAfterPrint(deliveryDate);
  // return;

  const url = subul_co_srv + "?category=search_CoDay&start_date=" + encodeURIComponent(deliveryDate) + "&print=true&remove_store_suffix=true";
  console.log("ln909::url=" + url);

  $.ajax({
    url: url,
    type: "GET",
    beforeSend: function () {
      console.log("인쇄 요청을 서버로 전송 중입니다.");
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
        // setTimeout(function() {
        //   downloadExcelAfterPrint(deliveryDate);
        // }, 2000); // 2초 후 엑셀 다운로드 실행

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

// 출력 후 엑셀 다운로드 함수 추가
function downloadExcelAfterPrint(deliveryDate) {
  console.log("ln759::downloadExcelAfterPrint");
  // 날짜 형식 변환
  const date = new Date(deliveryDate);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const weekDay = ['일', '월', '화', '수', '목', '금', '토'][date.getDay()];
  const formattedDate = `${date.getFullYear()}-${month}-${day}(${weekDay})`;

  // 로딩 표시
  const loadingDiv = document.createElement('div');
  loadingDiv.id = 'excel-loading';
  loadingDiv.innerHTML = '엑셀 파일 생성 중...';
  document.body.appendChild(loadingDiv);

  // 엑셀 다운로드 URL - 변수명 수정
  const url = subul_co_srv + "?category=search_CoDay&start_date=" + encodeURIComponent(deliveryDate) + "&excel=true";
  console.log("ln775::엑셀 다운로드 URL: " + url);

  // fetch를 사용하여 blob 응답 처리 - 변수명 수정
  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error('네트워크 응답이 실패했습니다. 상태: ' + response.status);
      }
      return response.blob();
    })
    .then(blob => {
      // 로딩 제거
      document.body.removeChild(loadingDiv);

      // blob URL 생성
      const blobUrl = window.URL.createObjectURL(blob);

      // 파일 다운로드 링크 생성 및 클릭
      const link = document.createElement('a');
      link.href = blobUrl;
      link.download = `CO_수불현황_${formattedDate}.xls`; // 파일명 설정
      document.body.appendChild(link);
      link.click();

      // 정리
      window.URL.revokeObjectURL(blobUrl);
      document.body.removeChild(link);

      console.log(`엑셀 파일이 다운로드되었습니다: CO_수불현황_${formattedDate}.xls`);
    })
    .catch(error => {
      // 로딩 제거
      const loadingDiv = document.getElementById('excel-loading');
      if (loadingDiv) {
        document.body.removeChild(loadingDiv);
      }
      console.error('엑셀 다운로드 중 오류가 발생했습니다: ' + error);
      alert('엑셀 파일 다운로드 중 오류가 발생했습니다: ' + error.message);
    });
}

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', function () {
  // 클래스가 없으면 생성
  if (typeof subulCoDay === 'undefined') {
    console.log("ln445::subulCoDay 클래스가 없습니다. 생성합니다.");
    subulCoDay = new subulCoDayClass();
  }

  // 이벤트 리스너 등록만 여기서 수행
  const startDate_co = document.getElementById("startDate-co");
  const searchBtn_co = document.getElementById("searchBtn-co");

  startDate_co.addEventListener("keypress", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      searchBtn_co.click();
    }
  });

  // 엑셀 양식 업로드::CO
  const excelFormBtn = document.getElementById("excel-form-co");
  const excelInput = document.getElementById("excel-upload-input-co");

  if (excelFormBtn && excelInput) {
    excelFormBtn.addEventListener("click", function () {
      excelInput.value = ""; // 이전 선택 초기화
      excelInput.click();
    });

    excelInput.addEventListener("change", function () {
      if (excelInput.files.length > 0) {
        const formData = new FormData();
        formData.append("action", "upload_excel_form");
        formData.append("excel_file", excelInput.files[0]);

        fetch("/wp-admin/admin-ajax.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin"
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert("업무일지 엑셀 파일이 업로드되었습니다.");
            } else {
              alert("업로드 실패: " + (data.data || "알 수 없는 오류"));
            }
          })
          .catch(err => {
            alert("업로드 중 오류 발생");
          });
      }
    });
  }

  // startdate-co input에 날짜 변경 이벤트 리스너 추가
  const startDateInput = document.getElementById('startDate-co');
  if (startDateInput) {
    // 날짜 변경 이벤트 리스너
    startDateInput.addEventListener('change', function () {
      subulCoDay.loadData();
    });
  }

  // 조회 버튼 클릭 이벤트 처리
  const searchBtn = document.getElementById('searchBtn-co');
  if (searchBtn) {
    searchBtn.addEventListener('click', function () {
      subulCoDay.loadData();
    });
  }

  // 출력 버튼 클릭 이벤트 처리 - 컴퓨터에서만 표시
  const printBtn = document.getElementById('printBtn-co');
  if (printBtn && platForm === 'computer') {
    printBtn.addEventListener('click', function () {
      const startDate = document.getElementById('startDate-co').value;
      printCoDay(startDate);
    });
  } else if (printBtn) {
    printBtn.style.display = 'none';
  }

  // 엑셀 버튼 클릭 이벤트 처리 - 컴퓨터에서만 표시
  const excelBtn = document.getElementById('excel-co');
  if (excelBtn && platForm === 'computer') {
    excelBtn.addEventListener('click', function () {
      // alert("엑셀 다운로드 기능을 개발중입니다.");
      // return;
      tableToExcelCo();
    });
  } else if (excelBtn) {
    excelBtn.style.display = 'none';
  }

  // 일지 버튼 클릭 이벤트 처리 - 컴퓨터에서만 표시
  const excelDayBtn = document.getElementById('excel-day');
  if (excelDayBtn && platForm === 'computer') {
    excelDayBtn.addEventListener('click', function () {
      const deliveryDate = document.getElementById('startDate-co').value;
      tableToExcelCoDay(deliveryDate);
    });
  } else if (excelDayBtn) {
    excelDayBtn.style.display = 'none';
  }

  // 모바일과 컴퓨터 모두에서 최초 로드 시 데이터 조회 실행
  setTimeout(() => {
    subulCoDay.loadData();
    console.log("CO::페이지 로드 완료 - 데이터 조회 실행");
  }, 100);
});

