// 수입통관 페이지:: 엑셀 업로드
class PurchaseImpClass {
  constructor() {
    this.columns = [
      "선택",
      "번호",
      "통관일",
      "제품명",
      "B/L번호",
      "수량",
      "중량",
      // "항공",
      // "도착일시",
      // "생산일",
      "비고",
      "생성일시",
      "수정일시",
      "담당자",
    ];
    this.colWidths = [
      "5%", // 체크박스
      "5%", // 번호
      "9%", // 통관일
      "15%", // 제품명
      "13%", // B/L번호
      "5%", // 수량(박스)
      "8%", // 중량(kg)
      // "5%", // 항공사
      // "10%", // 도착일시
      // "7%", // 생산일
      "13%", // 비고
      "10%", // 생성일시
      "10%", // 수정일시
      "7%", // 담당자
    ];
    this.dbColumns = [
      "check",
      "row_num",
      "import_dt",
      "spec",
      "awb",
      "vol",
      "weight",
      // "airline",
      // "arrival_dt",
      // "prod_date",
      "remarks",
      "create_dt",
      "modify_dt",
      "modify_name",
    ];
    this.alignments = ["c", "c", "c", "l", "c", "r", "r", "c", "c", "c", "c"];
    this.data = [];
    this.selectedRow = null;
    this.purchase_srv = window.purchase_srv || '/wp-content/plugins/purchase/purchase_srv.php';

    // DOM이 완전히 로드된 후 초기화
    document.addEventListener("DOMContentLoaded", () => {
      this.initializeEventListeners();
      this.setDefaultDates();
      this.loadData();
    });
  }

  // 날짜 기본값 설정 메서드
  setDefaultDates() {
    const today = new Date();
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(today.getMonth() - 1);

    // 날짜를 YYYY-MM-DD 형식으로 변환
    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    };

    // 시작일과 종료일 설정
    const startDateInput = document.getElementById("start-date-imp");
    const endDateInput = document.getElementById("end-date-imp");

    if (startDateInput) {
      startDateInput.value = formatDate(oneMonthAgo);
    }
    if (endDateInput) {
      const today = new Date();
      const sevenDaysLater = new Date();
      sevenDaysLater.setDate(today.getDate() + 7);

      endDateInput.value = formatDate(sevenDaysLater);
    }
  }

  makeTableHead() {
    console.log("[ln96::makeTableHead]");
    // tables div가 있는지 확인하고 없으면 생성
    const table = document.createElement("table");
    table.id = "specData";
    table.style.width = "100%";

    const thead = document.createElement("thead");
    thead.style.backgroundColor = "#000"; // thead의 배경색을 검은색으로 설정
    thead.style.color = "#fff"; // thead의 글자색을 흰색으로 설정

    const headerRow = document.createElement("tr");

    this.columns.forEach((col, i) => {
      const th = document.createElement("th");
      th.innerHTML = col;
      th.style.width = this.colWidths[i];
      headerRow.appendChild(th);
    });

    thead.appendChild(headerRow);
    table.appendChild(thead);

    // tbody 생성
    const tbody = document.createElement("tbody");
    table.appendChild(tbody);

    document.getElementById("tables").innerHTML = "";
    document.getElementById("tables").appendChild(table);
  }

  loadData() {
    const startDateInput = document.getElementById("start-date-imp");
    const endDateInput = document.getElementById("end-date-imp");

    // 날짜 입력 필드가 없으면 기본값 사용
    const startDate = startDateInput
      ? startDateInput.value
      : this.getDefaultStartDate();
    const endDate = endDateInput
      ? endDateInput.value
      : this.getDefaultEndDate();

    const url = this.purchase_srv + `?category=search-imp&start_date=${startDate}&end_date=${endDate}`;
    console.log(url);
    
    fetch(url)
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          this.data = result.data;
          const tableContainer = document.getElementById("tables");
          tableContainer.innerHTML = ""; // 기존 내용을 초기화

          if (platForm === "computer") {
            if (this.data.length > 0) {
              this.makeTableHead();
              this.makeTableBody(this.data);
            } else {
              // 데이터가 없을 때 메시지 표시
              const noDataMessage = document.createElement("div");
              noDataMessage.textContent = "데이터가 없습니다";
              noDataMessage.style.textAlign = "center";
              noDataMessage.style.padding = "20px";
              tableContainer.appendChild(noDataMessage);
            }
          } else {
            this.makeTableMobile(this.data);
          }
        }
      })
      .catch((error) => {
        console.error("데이터 로드 중 오류 발생:", error);
      });
  }

  makeTableBody(data) {
    const tbody = document.querySelector("#specData tbody");
    
    tbody.innerHTML = ""; // 기존 내용 초기화
    if (!tbody) return;
    
    let currentProdCd = null;
    let itemCount = 0;
    let isAlternateColor = false; // 색상 변경을 위한 플래그

    data.forEach((item) => {
      itemCount++;
      const row = tbody.insertRow();
      const isNewProduct = currentProdCd !== item.row_num;
      currentProdCd = item.row_num;

      // 새로운 제품일 경우 색상 변경
      if (isNewProduct) {
        isAlternateColor = !isAlternateColor;
      }

      // 제품별로 배경색 설정
      if (isAlternateColor) {
        row.classList.add("alternate-color-1");
      } else {
        row.classList.add("alternate-color-2");
      }

      // 체크박스 셀 (첫 번째 컬럼)
      const checkCell = row.insertCell();
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.dataset.serialNo = item.serial_no;
      checkCell.appendChild(checkbox);
      checkCell.style.textAlign = "center";

      // 나머지 컬럼들
      for (let i = 1; i < this.columns.length; i++) {
        const cell = row.insertCell();
        let value = item[this.dbColumns[i]] || "";

        // 날짜 필드의 경우 10자리만 표시
        if (
          ["import_dt", "prod_date"].includes(this.dbColumns[i]) &&
          value.length > 10
        ) {
          value = value.substr(0, 10);
        }

        // 숫자 필드의 경우 우측 정렬
        if (this.alignments[i] === "r") {
          cell.style.textAlign = "right";
        } else if (this.alignments[i] === "l") {
          cell.style.textAlign = "left";
        } else {
          cell.style.textAlign = "center";
        }

        if ( this.dbColumns[i] === 'weight' ) {
          let numValue = parseFloat(value);
          if (!isNaN(numValue)) {
              value = numValue.toFixed(2);
              if (value.split('.')[1] === '00') {
                  value = value.split('.')[0];
              }
          }
        }
        
        // 생성일자는 10자리만 표시
        if (this.dbColumns[i] === "create_dt" || this.dbColumns[i] === "modify_dt") {
            value = value.substr(0, 16);
        }

        cell.textContent = value;
      }

      // 행 클릭 이벤트 리스너 추가
      row.addEventListener("click", () => this.handleRowClick(row, item));
    });
  }

  makeTableMobile(data) {
    const container = document.getElementById("tables");
    container.innerHTML = "";

    data.forEach((item) => {
      const section = document.createElement("div");
      section.className = "mobile-purchase-section";
      section.style.cssText =
        "margin: 10px 0; padding: 10px; background-color: #f5f5f5; border-radius: 8px;";

      // 체크박스와 번호
      const headerDiv = document.createElement("div");
      headerDiv.style.cssText =
        "display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;";

      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.dataset.serialNo = item.serial_no;

      headerDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                ${checkbox.outerHTML}
                <span style="font-weight: bold;">No. ${item.serial_no}</span>
            </div>
            <span>${item.import_dt.substr(0, 10)}</span>
        `;

      section.appendChild(headerDiv);

      // 입력 필드들
      const fields = [
        { label: "제품명", type: "spec", value: item.spec, readonly: true },
        { label: "수량(박스)", type: "vol", value: item.vol, readonly: true },
        {
          label: "중량(kg)",
          type: "weight",
          value: item.weight,
          readonly: true,
        },
        // {
        //   label: "항공사",
        //   type: "airline",
        //   value: item.airline,
        //   readonly: true,
        // },
        { label: "B/L번호", type: "awb", value: item.awb, readonly: true },
        // {
        //   label: "도착일시",
        //   type: "arrival_dt",
        //   value: item.arrival_dt,
        //   readonly: true,
        // },
        // {
        //   label: "생산일",
        //   type: "prod_date",
        //   value: item.prod_date,
        //   readonly: true,
        // },
        {
          label: "비고",
          type: "remarks",
          value: item.remarks,
          readonly: true,
        },
      ];

      fields.forEach((field) => {
        const fieldDiv = document.createElement("div");
        fieldDiv.style.cssText =
          "display: flex; margin: 5px 0; align-items: center;";

        const input = document.createElement("input");
        input.type =
          field.type === "vol" || field.type === "weight" ? "number" : "text";
        input.value = field.value || "";
        input.style.cssText = "flex: 1; padding: 5px;";
        input.dataset.type = field.type;
        input.dataset.serialNo = item.serial_no;
        if (field.readonly) {
          input.setAttribute("readonly", "readonly");
        }

        fieldDiv.innerHTML = `
                <span style="width: 80px; font-weight: bold;">${field.label}:</span>
            `;
        fieldDiv.appendChild(input);

        section.appendChild(fieldDiv);
      });

      // 생성일시
      const createDiv = document.createElement("div");
      createDiv.style.cssText =
        "margin-top: 10px; font-size: 0.9em; color: #666;";
      createDiv.textContent = item.create_dt || "";
      section.appendChild(createDiv);

      // 클릭 이벤트 리스너 추가
      section.addEventListener("click", () => {
        console.log(`Section clicked: ${item.serial_no}`);
        console.log(item);

        this.displaySelectedRecordInInputs(item);
      });

      container.appendChild(section);
    });
  }

  // 선택된 레코드 정보를 입력 필드에 표시하는 함수
  displaySelectedRecordInInputs(item) {
    const formattedDate = item.import_dt ? item.import_dt.substr(0, 10) : "";
    document.getElementById("import-dt").value = formattedDate;
    document.getElementById("spec").value = item.spec || "";
    document.getElementById("vol").value = item.vol || "";
    document.getElementById("weight-imp").value = item.weight || "";
    // document.getElementById("airline").value = item.airline || "";
    document.getElementById("awb").value = item.awb || "";
    // // document.getElementById("arrival-dt").value = item.arrival_dt
    //   ? item.arrival_dt.replace(" ", "T").substr(0, 16)
    //   : "";
    // document.getElementById("prod-date").value = item.prod_date
    //   ? item.prod_date.substr(0, 10)
    //   : "";
    document.getElementById("remarks-imp").value = item.remarks || "";
  }

  handleRowClick(row, item) {
    // 모든 행의 선택 스타일 제거
    document.querySelectorAll("#specData tbody tr").forEach((r) => {
      r.classList.remove("selected-row"); // 기존 클래스 제거
    });

    // 클릭한 행에 선택 스타일 추가
    row.classList.add("selected-row"); // 선택된 행에 클래스 추가

    // 입력 필드에 값 설정
    this.displaySelectedRecordInInputs(item);
    // 현재 선택된 행의 데이터 저장
    this.selectedRow = item;
  }

  // 데이터 저장
  saveData() {
    // 저장할 데이터 찾기
    const import_dt = document.getElementById("import-dt").value;
    const spec = document.getElementById("spec").value;
    const vol = document.getElementById("vol").value;
    const weight = document.getElementById("weight-imp").value;
    // const airline = document.getElementById("airline").value;
    const awb = document.getElementById("awb").value;
    // const arrival_dt = document.getElementById("arrival-dt").value;
    // const prod_date = document.getElementById("prod-date").value;
    const remarks = document.getElementById("remarks-imp").value;
    const data = {
      import_dt,
      spec,
      vol,
      weight,
      // airline,
      awb,
      // arrival_dt,
      // prod_date,
      remarks,
    };

    console.log(data);

    if (!import_dt || !spec || !vol || !awb) {
      alert("저장할 데이터를 입력해주세요.");
      return;
    }

    const url = this.purchase_srv + `?category=save-imp&user_id=${user_id}`;
    console.log(url);

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
          alert("저장되었습니다.");
          this.loadData(); // 테이블 새로고침
        } else {
          alert("저장 실패: " + (result.error || "알 수 없는 오류"));
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("서버 통신 중 오류가 발생했습니다.");
      });
  }

  updateData() {
    // 업데이트할 데이터 준비
    const data = {
      import_dt: document.getElementById("import-dt").value,
      spec: document.getElementById("spec").value,
      vol: document.getElementById("vol").value,
      weight: document.getElementById("weight-imp").value,
      // airline: document.getElementById("airline").value,
      awb: document.getElementById("awb").value,
      // arrival_dt: document.getElementById("arrival-dt").value,
      // prod_date: document.getElementById("prod-date").value,
      remarks: document.getElementById("remarks-imp").value,
      serial_no: this.selectedRow.serial_no,
    };

    console.log(data);

    // 필수 데이터가 입력되었는지 확인
    if (
      !data.import_dt ||
      !data.spec ||
      !data.vol ||
      // !data.weight ||
      !data.serial_no
    ) {
      alert("모든 필수 항목을 입력해주세요.");
      return;
    }

    // 서버에 업데이트 요청
    const url = this.purchase_srv + `?category=update-imp&user_id=${user_id}`;
    console.log(url);

    fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      }
    )
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          alert("업데이트되었습니다.");
          this.loadData(); // 테이블 새로고침
        } else {
          alert("업데이트 실패: " + (result.error || "알 수 없는 오류"));
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("서버 통신 중 오류가 발생했습니다.");
      });
  }

  // 데이터 삭제
  deleteData() {
    // 선택된 체크박스 찾기
    const checkboxes = document.querySelectorAll(
      'input[type="checkbox"]:checked'
    );
    const serialNos = Array.from(checkboxes).map((cb) => cb.dataset.serialNo);

    if (serialNos.length === 0) {
      alert("삭제할 항목을 선택해주세요.");
      return;
    }

    if (!confirm(`선택한 ${serialNos.length}개의 항목을 삭제하시겠습니까?`)) {
      return;
    }

    const url = this.purchase_srv + "?category=delete-imp";
    console.log("delete url= " + url);
    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ serial_nos: serialNos }),
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          alert("삭제되었습니다.");
          this.loadData(); // 테이블 새로고침
        } else {
          alert("삭제 실패: " + (result.error || "알 수 없는 오류"));
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("서버 통신 중 오류가 발생했습니다.");
      });
  }

  // 입력값 업데이트
  updateValue(input) {
    const serialNo = input.dataset.serialNo;
    const type = input.dataset.type;
    const value = input.value;

    // 데이터 배열에서 해당 항목 찾기
    const item = this.data.find((d) => d.serial_no.toString() === serialNo);
    if (!item) return;

    // 타입에 따라 적절한 필드 업데이트
    switch (type) {
      case "spec":
        item.spec = value;
        break;
      case "vol":
        item.vol = value === "" ? null : parseInt(value);
        break;
      case "weight":
        item.weight = value === "" ? null : parseFloat(value);
        break;
      // case "airline":
      //   item.airline = value;
      //   break;
      case "awb":
        item.awb = value;
        break;
      // case "arrival_dt":
      //   item.arrival_dt = value;
      //   break;
      // case "prod_date":
      //   item.prod_date = value;
      //   break;
      case "remarks":
        item.remarks = value;
        break;
    }

    // 숫자 필드의 경우 입력값 정리
    if (type === "vol" || type === "weight") {
      input.value = value === "" ? "" : parseFloat(value) || 0;
    }
  }

  // 이벤트 리스너 초기화
  initializeEventListeners() {
    console.log("initializeEventListeners");
    // 옵셔널 체이닝으로 안전하게 처리
    const searchButton = document.getElementById("search-btn-imp");
    const insertButton = document.getElementById("insert-btn-imp");
    const updateButton = document.getElementById("update-btn-imp");
    const deleteButton = document.getElementById("delete-btn-imp");

    if (searchButton) {
      searchButton.addEventListener("click", () => this.loadData());
    }

    if (insertButton) {
      insertButton.addEventListener("click", () => this.saveData());
    }

    if (updateButton) {
      updateButton.addEventListener("click", () => this.updateData());
    }

    if (deleteButton) {
      deleteButton.addEventListener("click", () => this.deleteData());
    }
  }
} // PurchaseImpClass end

window.purchaseImpClass = new PurchaseImpClass();

// ----------------------
// 엑셀 업로드::통관매입 
// ----------------------
function handleExcelUpload() {
   const file = document.getElementById('excelFile').files[0];
   const reader = new FileReader();

   reader.onload = (e) => { // 화살표 함수로 변경하여 this 컨텍스트 유지
       const data = new Uint8Array(e.target.result);
       const workbook = XLSX.read(data, {type: 'array'});
       const worksheet = workbook.Sheets[workbook.SheetNames[0]];
       
       let importData = [];
       let rowNum = 2; // 헤더를 제외하고 2번째 행부터 시작

      while (worksheet['B' + rowNum] || getMergedCellValue(worksheet, 'B' + rowNum)) {
          
        const importDtValue = getMergedCellValue(worksheet, 'B' + rowNum);
        const prodDateValue = getMergedCellValue(worksheet, 'I' + rowNum);
        
        console.log(`Row ${rowNum}: import_dt = ${importDtValue}, prod_date = ${prodDateValue}`); // 디버깅 로그 추가

        importData.push({
              import_dt: excelDateToJSDate(importDtValue), 
              spec: getMergedCellValue(worksheet, 'D' + rowNum) || '',
              vol: getMergedCellValue(worksheet, 'E' + rowNum) || '',
              airline: getMergedCellValue(worksheet, 'F' + rowNum) || '',
              awb: getMergedCellValue(worksheet, 'G' + rowNum) || '',
              prod_date: excelDateToJSDate(prodDateValue)
          });
          rowNum++;
      }

      const url = this.purchase_srv + '?category=upload-imp&user_id=' + user_id;
      console.log(url);
      fetch(url, {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json',
          },
          body: JSON.stringify({ importData: importData })
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
            alert('데이터가 성공적으로 업로드되었습니다.');
            window.location.reload();

            // 조회 버튼을 프로그래밍적으로 클릭
            // const searchButton = document.getElementById('search-btn-imp');
            // if (searchButton) {
            //     // 조회 버튼을 프로그래밍적으로 클릭
            //     searchButton.click();
            // }

          } else {
              alert('업로드 중 오류가 발생했습니다: ' + data.error);
          }
      });
   };
  reader.readAsArrayBuffer(file);
   
}

function getMergedCellValue(worksheet, address) {
   // 병합 셀 정보 확인
   const merges = worksheet['!merges'] || [];
   for (let merge of merges) {
       if (isInRange(address, merge)) {
           // 병합 영역의 첫 번째 셀 주소 가져오기
           const firstCell = XLSX.utils.encode_cell({ r: merge.s.r, c: merge.s.c });
           return worksheet[firstCell]?.v || '';
       }
   }
   return worksheet[address]?.v || '';
}

function isInRange(address, merge) {
   const cell = XLSX.utils.decode_cell(address);
   return cell.r >= merge.s.r && 
          cell.r <= merge.e.r && 
          cell.c >= merge.s.c && 
          cell.c <= merge.e.c;
}

// 날짜 형식 변환 함수
function formatDate(dateString) {
    if (!dateString) {
        return ''; // 유효하지 않은 입력에 대한 기본값 반환
    }
    if (typeof dateString !== 'string') {
        dateString = String(dateString); // 문자열로 변환
    }
    
    // 이미 YYYY-MM-DD 형식인지 확인
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
        return dateString; // 이미 올바른 형식이면 그대로 반환
    }
    
    const parts = dateString.split('/');
    if (parts.length !== 3) {
        return ''; // 예상치 못한 형식에 대한 기본값 반환
    }
    
    const [month, day, year] = parts;
    if (!month || !day || !year) {
        return ''; // 유효하지 않은 날짜 구성 요소에 대한 기본값 반환
    }
    
    return `20${year.padStart(2, '0')}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
}

// 엑셀 날짜를 YYYY-MM-DD 형식으로 변환하는 함수
function excelDateToJSDate(excelDate) {
    const date = new Date((excelDate - 25569) * 86400 * 1000);
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
}

 //  송장내역 엑셀 다운로드
function impExcel() {

    const startDate = document.getElementById('start-date-imp').value;
    const endDate = document.getElementById('end-date-imp').value;

    const formattedDate = new Date(new Date().getTime() + (9 * 60 * 60 * 1000)).toISOString().replace(/[-:T]/g, '').slice(0, 14);
    const filename = `송장내역-${formattedDate}.xls`;
    console.log("filename: " + filename);

    // 서버의 PHP 스크립트를 실행하여 엑셀 파일을 생성하고 다운로드합니다. 
    const serverUrl = `/wp-content/plugins/purchase/impExcel_srv.php?category=impExcel&startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}`;
    console.log('서버 파라미터 포함한 url:', serverUrl);

    fetch(serverUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('네트워크 응답이 올바르지 않습니다.');
            }
            return response.blob().then(blob => ({
                blob,
                response
            }));
        })
        .then(({
            blob,
            response
        }) => {
            // Blob을 사용하여 다운로드 링크를 생성하고 자동으로 클릭하여 파일을 다운로드합니다.
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(error => {
            console.error('다운로드 중 오류가 발생했습니다:', error);
            alert('다운로드 중 오류가 발생했습니다.');
        });
}