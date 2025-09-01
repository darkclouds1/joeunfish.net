// 일자별 BL# 입고,출고,생산,판매 현황 조회

class subulBlDayClass {
  constructor({
    col = ["구분", "B/L번호", "기준일", "AWB번호", "제품명", "BOX", "중량", "수율", "판매처"],
    col2 = ["구분", "BL번호", "기준일자", "AWB번호", "제품명", "BOX", "weight", "수율", "판매처"],
    col3 = ["l", "c", "c", "c", "l", "r", "r", "r", "l"],
    colw = ["10%", "10%", "10%", "15%", "15%", "10%", "10%", "10%", "10%"],
  } = {}) {
    this.col = col;
    this.col2 = col2;
    this.col3 = col3;
    this.colw = colw;
    this.table1 = null; // 테이블 프로퍼티 추가
  }

  makeTableHead() {

    console.log("ln19::makeTableHead");

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

    if ((document.getElementById("tables-bl").innerHTML = "")) {
      document.getElementById("tables-bl").innerHTML = this.table1.outerHTML;
      idx = 0;
    }
  }

  // 생산 바코드출고 makeTable
  makeTableBody(datas) {

    console.log("ln56::makeTableBody");
    // console.log("datas=" + datas);
    // ADD JSON DATA TO THE TABLE AS ROWS.
    // let result = JSON.parse(datas);
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
        idx = idx + 1;
        let tr = tbody.insertRow(-1);

        // type_data = '원료입고' 이면 row 항상 대각선 스크래치 style 표시
        if (items[i][this.col2[0]] === '원료입고') {
          tr.classList.add('scratch-row');
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

        for (let j = 0; j < this.col.length; j++) {
          let tabCell = tr.insertCell(-1);

          // date 형식 변환 - 4/22(화) 형식으로 변환
          if (this.col2[j] === "date" || this.col2[j] === "기준일자") {
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

          tabCell.innerHTML = items[i][this.col2[j]] || "";

          // data_type column에 '원료입고'가 포함되어 있으면 노란색으로 표시
          if (items[i][this.col2[0]].includes("원료입고")) {
            tr.style.backgroundColor = "#d9d9d9";  // 진한 회색으로 변경
          }
          if (items[i][this.col2[0]].includes("원료출고")) {
            tr.style.backgroundColor = "#f2f2f2";  // 더 연한 회색으로 변경
          }
          if (items[i][this.col2[0]].includes("생산내역")) {
            tr.style.backgroundColor = "#c0daf2";
          }

          // if (items[i][this.col2[0]].includes("판매")) {
          //   tr.style.backgroundColor = "#c0daf2";
          // }

          // 정렬하기
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

          tabCell.setAttribute("style", attr);

          // // 셀에 링크 추가
          // tabCell.innerHTML = CodeInvClass.setLink(items, i, j, tabCell);
        } // column
      } catch (ex) {
        console.log(ex.message);
      }
    }

    let divContainer = document.getElementById("tables-bl");

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
    // 데이터 파싱 및 구조 확인
    let data = (typeof datas === "string") ? JSON.parse(datas) : datas;
    let items = [];
    if (Array.isArray(data)) {
      items = data;
    } else if (data && data.response && data.response.body && Array.isArray(data.response.body.items)) {
      items = data.response.body.items;
    } else if (Array.isArray(data.items)) {
      items = data.items;
    } else {
      alert("데이터 형식이 올바르지 않습니다.");
      return;
    }

    // 컨테이너 준비
    const container = document.getElementById("tables-bl");
    if (!container) {
      alert('"tables-bl" id를 가진 엘리먼트가 없습니다. HTML 구조를 확인하세요.');
      return;
    }
    container.innerHTML = "";

    // BL번호별로 그룹화 (1~7번 구분만)
    const blGroups = {};
    items.forEach(item => {
      const gubun = item["구분"] || "";
      if (/^[1-7]\./.test(gubun)) {
        const blNo = item["BL번호"] || "기타";
        if (!blGroups[blNo]) blGroups[blNo] = [];
        blGroups[blNo].push(item);
      }
    });

    // 1~7번 구분별로 그룹핑 함수
    function groupByGubun(items) {
      const gubunMap = {};
      items.forEach(item => {
        const gubun = item["구분"] || "";
        if (!gubunMap[gubun]) gubunMap[gubun] = [];
        gubunMap[gubun].push(item);
      });
      return gubunMap;
    }

    // 1~7번 구분 순서
    const order = [
      "1.원료입고", "2.원료출고", "3.생산내역", "4.판매내역",
      "5.생산내역(출금)", "6.판매내역(출금)", "7.재고내역"
    ];

    // BL번호별 카드(1~7번 구분)
    Object.entries(blGroups).forEach(([blNo, groupItems]) => {
      const card = document.createElement('div');
      card.className = 'mobile-card collapsed'; // CoDay와 동일하게 닫힌 상태로 시작
      card.style.marginBottom = "8px";
      card.style.borderRadius = "6px";
      card.style.boxShadow = "0 1px 3px #eee";
      card.style.padding = "4px 6px";
      card.style.background = "#fafbfc";

      // 카드 헤더
      const header = document.createElement('div');
      header.className = 'card-header';
      header.style.padding = "6px 8px";
      header.style.cursor = "pointer";
      header.style.background = "#f0f0f0";
      header.style.fontWeight = "bold";
      header.style.fontSize = "1em";
      header.style.display = "flex";
      header.style.justifyContent = "space-between";
      header.style.alignItems = "center";
      header.innerHTML = `
        <span>BL번호: ${blNo}</span>
        <span class="click-indicator"><span class="arrow">▼</span></span>
      `;
      card.appendChild(header);

      // 카드 내용
      const content = document.createElement('div');
      content.className = 'card-content';
      content.style.padding = "0";
      content.style.background = "#fff";
      content.style.display = "none"; // 닫힌 상태

      // 1~7번 구분별로 그룹핑
      const gubunMap = groupByGubun(groupItems);

      order.forEach(gubun => {
        const count = gubunMap[gubun] ? gubunMap[gubun].length : 0;
        if (!gubunMap[gubun]) return;
        const section = document.createElement('div');
        section.className = 'gubun-section';
        section.style.margin = "2px 0";
        section.style.borderBottom = "1px solid #eee";
        section.style.padding = "2px 0";

        // 구분별 헤드 색상 지정
        let headBg = "#f9f9f9";
        if (gubun === "1.원료입고") headBg = "#d9d9d9";
        else if (gubun === "2.원료출고") headBg = "#f2f2f2";
        else if (gubun === "3.생산내역" || gubun === "5.생산내역(출금)") headBg = "#c0daf2";
        else if (gubun === "4.판매내역" || gubun === "6.판매내역(출금)" || gubun === "7.재고내역") headBg = "#fff";

        // 헤더 생성
        const head = document.createElement('div');
        head.className = 'gubun-head';
        head.style.display = "flex";
        head.style.justifyContent = "space-between";
        head.style.alignItems = "center";
        head.style.cursor = "pointer";
        head.style.padding = "4px 0";
        head.style.background = headBg;

        if (["1.원료입고", "2.원료출고", "7.재고내역"].includes(gubun)) {
          const item = gubunMap[gubun][0];
          head.innerHTML = `
            <span><b>${gubun}</b> | 제품명: ${item["제품명"] || ""} | BOX: ${item["BOX"] || ""} | 중량: ${item["weight"] || ""}</span>
            <span class="click-indicator"><span class="arrow">▼</span></span>
          `;

          // 상세(더보기)
          const detail = document.createElement('div');
          detail.className = 'gubun-detail';
          detail.style.display = "none";
          detail.style.background = "#f6f8fa";
          detail.style.padding = "6px 8px";
          detail.innerHTML = `
            <div>구분: ${item["구분"] || ""}</div>
            <div>기준일자: ${item["기준일자"] || ""}</div>
            <div>AWB번호: ${item["AWB번호"] || ""}</div>
            <div>제품명: ${item["제품명"] || ""}</div>
            <div>BOX: ${item["BOX"] || ""}</div>
            <div>중량: ${item["weight"] || ""}</div>
            <div>수율: ${item["수율"] || ""}</div>
            <div>판매처: ${item["판매처"] || ""}</div>
          `;
          section.appendChild(head);
          section.appendChild(detail);

        } else {
          head.innerHTML = `
            <span><b>${gubun}</b> (${count}건)</span>
            <span class="click-indicator"><span class="arrow">▼</span></span>
          `;

          // 리스트
          const list = document.createElement('div');
          list.className = 'gubun-list';
          list.style.display = "none";
          list.style.background = "#f6f8fa";
          list.style.padding = "6px 8px";

          gubunMap[gubun].forEach(item => {
            const row = document.createElement('div');
            row.className = 'gubun-list-row';
            row.style.borderBottom = "1px solid #eee";
            row.style.padding = "4px 0";

            if (gubun === "3.생산내역" || gubun === "5.생산내역(출금)") {
              row.innerHTML = `
                <span>${item["제품명"] || ""}</span> | 
                <span>BOX: ${item["BOX"] || ""}</span> | 
                <span>중량: ${item["weight"] || ""}</span> | 
                <span>수율: ${item["수율"] || ""}</span>
              `;
            } else if (gubun === "4.판매내역" || gubun === "6.판매내역(출금)") {
              row.innerHTML = `
                <span>${item["제품명"] || ""}</span> | 
                <span>BOX: ${item["BOX"] || ""}</span> | 
                <span>중량: ${item["weight"] || ""}</span> | 
                <span>수율: ${item["수율"] || ""}</span> | 
                <span>${item["판매처"] || ""}</span>
              `;
            }
            list.appendChild(row);
          });
          section.appendChild(head);
          section.appendChild(list);
        }

        content.appendChild(section);
      });

      card.appendChild(content);

      // 카드 헤더 클릭 시 카드 전체 토글 (CoDay와 동일하게)
      header.addEventListener('click', function () {
        card.classList.toggle('collapsed');
        if (card.classList.contains('collapsed')) {
          content.style.display = 'none';
          card.querySelectorAll('.arrow').forEach(arrow => arrow.textContent = '▼');
          card.querySelectorAll('.gubun-detail, .gubun-list').forEach(el => el.style.display = 'none');
        } else {
          content.style.display = 'block';
          card.querySelectorAll('.arrow').forEach(arrow => arrow.textContent = '▼');
          card.querySelectorAll('.gubun-detail, .gubun-list').forEach(el => el.style.display = 'none');
        }
      });

      // 각 구분별 헤더 클릭 시 해당 상세/리스트만 토글 (CoDay와 동일)
      content.querySelectorAll('.gubun-head').forEach(head => {
        head.addEventListener('click', function (e) {
          e.stopPropagation(); // 카드 전체 토글 방지
          const detail = head.parentElement.querySelector('.gubun-detail');
          const list = head.parentElement.querySelector('.gubun-list');
          const arrow = head.querySelector('.arrow');
          if (detail) {
            if (detail.style.display === 'none') {
              detail.style.display = 'block';
              if (arrow) arrow.textContent = '▲';
            } else {
              detail.style.display = 'none';
              if (arrow) arrow.textContent = '▼';
            }
          }
          if (list) {
            if (list.style.display === 'none') {
              list.style.display = 'block';
              if (arrow) arrow.textContent = '▲';
            } else {
              list.style.display = 'none';
              if (arrow) arrow.textContent = '▼';

            }
          }
        });
      });

      container.appendChild(card);
    });
  }

  // -----------------------------------------------------------------
  // 조회시작::목록에 바코드 보여주고 성공이면 DB 저장
  // - category=search_BlDay, 
  // - 조회조건: 일자, BL# - Bl#은 전체 조회 가능
  // -----------------------------------------------------------------
  loadData() {
    setTimeout(function () {
      move();
    }, 100);

    const startDate = document.getElementById("startDate-bl").value;
    const param = `start_date=${startDate}`;
    const url = subul_bl_srv + "?category=search_BlDay&" + param;

    // 테이블 컨테이너 초기화
    const tableContainer = document.getElementById("tables-bl");
    tableContainer.innerHTML = "";

    console.log("ln249::url=" + url);
    fetch(url)
      .then((response) => {
        if (!response.ok) {
          throw new Error("네트워크 응답이 실패했습니다.");
        }
        return response.json();
      })
      .then((result) => {
        // console.log("ln243::response=" + JSON.stringify(result));
        try {
          if (result.status === 'success') {
            this.data = result.data;
            const tableContainer = document.getElementById("tables-bl");
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
              this.makeTableMobile(this.data);
            }
          }
        } catch (e) {
          console.error("응답 처리 오류:", e);
          const tableContainer = document.getElementById("tables-bl");
          const errorMessage = document.createElement("div");
          errorMessage.textContent = e.message;
          errorMessage.classList.add('no-data-message');
          tableContainer.appendChild(errorMessage);
          throw e;
        }
      })
      .catch((error) => {
        console.error("데이터 로드 중 오류 발생:", error);

        const tableContainer = document.getElementById("tables-bl");
        const errorMessage = document.createElement("div");
        errorMessage.textContent = error.message;
        errorMessage.classList.add('no-data-message');
        tableContainer.appendChild(errorMessage);
      })
      .finally(() => {
        move_stop();
      });
  }

} // subulBlDayClass -end

// 엑셀 변환
function tableToExcelBl(
  tableId = "tables-bl",
  name = "생산일자 別 BL현황",
  filename = "joeunfish"
) {
  console.log("ln485::tableToExcelBl");

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

  let tableElement = document.getElementById(tableId).querySelector("table");
  if (!tableElement) {
    alert(
      "조회된 테이블이 없습니다. 조회 후 다운하시기 바랍니다.:: " + tableId
    );
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
  const date = new Date(document.getElementById("startDate-bl").value);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const weekDay = ['일', '월', '화', '수', '목', '금', '토'][date.getDay()];
  const formattedDate = `${date.getFullYear()}-${month}-${day}(${weekDay})`;

  // 워크시트 이름과 파일명에 사용
  name = name + "_" + formattedDate;

  var ctx = {
    worksheet: formattedDate,  // 워크시트 탭 이름을 날짜 형식으로 설정
    table: clonedTable.innerHTML
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

function fetchBlNumbers(date) {
  url = subul_bl_srv + "?category=bl&start_date=" + date;
  console.log("ln428::url=" + url);
  fetch(url)
    .then(response => response.json())
    .then(data => {
      // BL 번호 목록을 표시할 select 엘리먼트
      const blSelect = document.getElementById('select-bl');
      if (blSelect) {
        blSelect.innerHTML = ''; // 기존 옵션 초기화

        // 첫 번째 옵션으로 "선택" 추가
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'B/L번호';
        blSelect.appendChild(defaultOption);

        // 서버에서 받아온 BL 번호들을 옵션으로 추가
        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.bl_no;
          option.textContent = item.bl_no;
          blSelect.appendChild(option);
        });

        // 첫 번째 BL 번호가 있다면 자동 선택
        if (data.length > 0) {
          blSelect.value = data[0].bl_no;
        }

      }
    })
    .catch(error => {
      console.error('BL 번호를 가져오는 중 오류 발생:', error);
    });
}

// bl번호 수동입력 추가되는 JavaScript 함수들
function handleSelect(select) {
  if (select.value === "") {
    let input = prompt("BL번호를 입력하세요 (4자리):", "");
    if (input) {
      // 숫자만 4자리로 제한
      input = input.replace(/[^0-9]/g, '').slice(0, 4);
      if (input) {
        // 새로운 option 추가
        let newOption = new Option(input, input);
        select.add(newOption);
        select.value = input;
      }
    }
  }
}

// -------------------------------------  
// 페이지 로드
// -------------------------------------  
document.addEventListener('DOMContentLoaded', function () {

  // 클래스가 없으면 생성
  if (typeof subulBlDay === 'undefined') {
    console.log("ln445::subulBlDay 클래스가 없습니다. 생성합니다.");
    subulBlDay = new subulBlDayClass();
  }

  // 오늘 날짜 가져오기
  const formattedDate = new Date().toISOString().split('T')[0];
  console.log("ln415::formattedDate=" + formattedDate);

  // startdate-bl input에 오늘 날짜 설정
  const startDateInput = document.getElementById('startDate-bl');
  if (startDateInput) {
    startDateInput.value = formattedDate;
  }


  // 생산일자 변경되면 해당 BL# 번호 가져오기
  const productionDateInput = document.getElementById('startDate-bl');
  if (productionDateInput) {

    // 엔터키 입력 시 조회 버튼 클릭
    productionDateInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        const searchBtn = document.getElementById('searchBtn-bl');
        if (searchBtn) {
          searchBtn.click();
        }
      }
    });
  }

  // 조회 버튼 클릭 이벤트 처리
  const searchBtn = document.getElementById('searchBtn-bl');
  if (searchBtn) {
    searchBtn.addEventListener('click', function () {
      subulBlDay.loadData();
    });
  }

  // 출력 버튼 클릭 이벤트 처리
  const printBtn = document.getElementById('printBtn-bl');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      const startDate = document.getElementById('startDate-bl').value;
      // const blNumber = document.getElementById('select-bl').value;

      alert("개발중입니다. 출력 기능은 추후 추가됩니다.");
      return;
      // summaryPrint(startDate, blNumber);

    });
  }

  // 엑셀 버튼 클릭 이벤트 처리 - 컴퓨터에서만 표시
  const excelBtn = document.getElementById('excel-bl');
  if (excelBtn && platForm === 'computer') {
    excelBtn.addEventListener('click', function () {
      console.log("ln681::excelBtn 클릭");
      tableToExcelBl();
    });
  } else if (excelBtn) {
    excelBtn.style.display = 'none';
  }

});