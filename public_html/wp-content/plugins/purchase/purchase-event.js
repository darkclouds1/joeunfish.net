// --------------------------------------------------------------------------------------
// 개인판매 :: -by DC정순장 2025-03-18
// 개인판매(물고기아저씨, 연어처럼 등) 
// 완출, GS점은 생산 시 (invoice_yn=n) 로 되어 있고, 따로 출고가 없으므로 여기서 출고 처리함.
// 생산점: 기타점은 생산하여 기타점으로만 출고, 그 외는 생산한 중량에서 선택한 판매점으로 출고 처리
// 판매점: 수불에서 출고 표시 용도
// 비고: 개인판매는 store_no 에 입력
// --------------------------------------------------------------------------------------
class PurchaseEventClass {
  constructor() {
    this.columns = [
      "선택",
      "sNo.",
      "입력일",
      "대분류",
      "소분류",
      // "행사명",
      "행사설명",
      "시작일",
      "종료일",
      "상품명",
      "단가",
      "목표량",
      "실적량",
      "변경일시",
      "담당자"
    ];
    this.colWidths = [
      "4%",   // 선택  
      "4%",   // 행사번호  
      "8%",   // 입력일  
      "8%",   // 대분류  
      "8%",   // 소분류  
      // "11%",  // 행사명  
      "17%",  // 행사설명  
      "7%",   // 시작일  
      "7%",   // 종료일  
      "13%",   // 상품명  
      "4%",   // 단가  
      "4%",   // 목표수량  
      "4%",   // 실적수량  
      "10%",   // 변경일시  
      "4%"    // 담당자  
    ];
    this.dbColumns = [
      "check",
      "serial_no",
      "increate_dt",
      "major_nm",
      "code_nm",
      // "event_name",
      "event_desc",
      "start_date", 
      "end_date",
      "prod_nm",
      "unit_price",
      "target_qty",
      "result_qty",
      "modify_dt",
      "modify_nm",
    ];
    this.alignments = ['c', 'r','c','l','l','l','c','c','l','r','r','r','c','c'];
    this.data = [];
    this.selectedRow = null;
    this.table = null;     // 인스턴스 변수로 선언
    this.productList = []; // 상품목록 데이터를 클래스 변수로 저장

    // purchase_srv 기본값 설정
    this.purchase_srv = window.purchase_srv || '/wp-content/plugins/purchase/purchase_srv.php';
    // this.barcodeIn_srv = window.barcodeIn_srv || '/wp-content/plugins/barcodeIn/barcodeIn_srv.php';

    // DOM이 완전히 로드된 후 초기화
    document.addEventListener("DOMContentLoaded", () => {
      this.initializeEventListeners();
      this.setDefaultDates();
      this.loadProductList();   // 상품목록 먼저 로드
      this.loadData();
      this.makeTableHead();
    });

  }

// 상품목록 로드 메서드 추가
  async loadProductList() {
    try {
      const url = this.purchase_srv + '?category=getEventProdList';
      const response = await fetch(url);
      const data = await response.json();
      
      if (!data.data) {
        console.error('상품목록 데이터가 없습니다');
        return;
      }

      this.productList = data.data;
      
      // DOM 조작 최적화
      const prodCodeSelect = document.getElementById('prod-code-event');
      if (!prodCodeSelect) return;

      // DocumentFragment 사용하여 DOM 조작 최적화
      const fragment = document.createDocumentFragment();
      this.productList.forEach(item => {
        const option = document.createElement('option');
        option.value = item.prod_code;
        option.textContent = `${item.prod_code}(${item.prod_name})`;
        fragment.appendChild(option);
      });
      
      prodCodeSelect.appendChild(fragment);
      
    } catch (error) {
      console.error('상품목록 로드 중 오류 발생:', error);
    }
  }

  // 날짜 기본값 설정 메서드
  setDefaultDates() {
    const today = new Date();
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(today.getFullYear() - 1);
    oneYearAgo.setMonth(0); // 1월 (0부터 시작)
    oneYearAgo.setDate(1);  // 1일


    // 날짜를 YYYY-MM-DD 형식으로 변환
    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    };

    // 시작일과 종료일 설정
    const startDateInput = document.getElementById("start-date-event");
    const endDateInput = document.getElementById("end-date-event");

    if (startDateInput) {
      startDateInput.value = formatDate(oneYearAgo);
    }
    if (endDateInput) {
      endDateInput.value = formatDate(today);
    }
  }

  makeTableHead() {
    // Create the table element
    this.table = document.createElement("table");
    this.table.id = "specData";
    this.table.style.width = "100%";

    const thead = document.createElement("thead");
    thead.style.backgroundColor = "#000"; // Set the background color of the thead
    thead.style.color = "#fff"; // Set the text color of the thead

    const headerRow = document.createElement("tr");

    this.columns.forEach((col, i) => {
      const th = document.createElement("th");

      if (i === 0 || i === 1 || i === 7 || i === 8 || i === 10 || i === 11 || i === 12 || i === 13 || i === 14) {
        // 첫 번째 열에는 정렬 링크를 추가하지 않음
        th.innerHTML = col;
      } else {
        th.innerHTML = `${col}
          <a href="#" onclick="sortTD(${i}); return false;" style="color: white;">▲</a><a href="#" onclick="reverseTD(${i}); return false;" style="color: white;">▼</a>`;
      }
      
      th.style.width = this.colWidths[i];
      headerRow.appendChild(th);
    });

    thead.appendChild(headerRow);
    this.table.appendChild(thead); // Use this.table instead of table

    const tableContainer = document.getElementById("tables");
    tableContainer.innerHTML = ""; // Clear existing content
    tableContainer.appendChild(this.table); // Append the table to the container
  }

  loadData() {
    const startDateInput = document.getElementById("start-date-event");
    const endDateInput = document.getElementById("end-date-event");

    // 날짜 입력 필드가 없으면 기본값 사용
    const startDate = startDateInput.value; 
    const endDate = endDateInput.value;

    const url = this.purchase_srv + `?category=search-event&start_date=${startDate}&end_date=${endDate}`;
    console.log("[ln135::loadData]url=" + url);

      // 테이블 컨테이너 초기화
    const tableContainer = document.getElementById("tables");
    tableContainer.innerHTML = "";


    fetch(url)
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          this.data = result.data;
          const tableContainer = document.getElementById("tables");
          tableContainer.innerHTML = ""; // 기존 내용을 초기화

          if (platForm === "computer" || platForm === "mobile") {
            if (this.data.length > 0) {
              this.makeTableHead();
              this.makeTableBody(this.data);
            } else {
              // 데이터가 없을 때 메시지 표시
              const noDataMessage = document.createElement("div");
              noDataMessage.textContent = "데이터가 없습니다";
              noDataMessage.style.textAlign = "center";
              noDataMessage.style.padding = "20px";
              noDataMessage.style.backgroundColor = "#f5f5f5";
              noDataMessage.style.border = "1px solid #ddd";
              noDataMessage.style.borderRadius = "4px";
              tableContainer.appendChild(noDataMessage);
            }
          } else {
            // 모바일 화면은 개발 미정
            this.makeTableMobile(this.data);
          }
        }
      })
      .catch((error) => {
        console.error("데이터 로드 중 오류 발생:", error);
      });
  }

  makeTableBody(data) {
    // console.log(data);
    const table = document.getElementById("specData");
    if (!table) return;
    let isAlternateColor = false; // 배경색 초기값 설정

    const tbody = table.createTBody();
    data.forEach((item) => {
        const row = tbody.insertRow();
        // 행마다 색상 변경
        isAlternateColor = !isAlternateColor;
        if (isAlternateColor) {
            row.classList.add("alternate-color-1");
        } else {
            row.classList.add("alternate-color-2");
        }

        // 체크박스 셀 (첫 번째 컬럼)
        const checkCell = row.insertCell();
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.dataset.serial_no = item.serial_no;
        checkCell.appendChild(checkbox);
        checkCell.style.textAlign = "center";

        // 나머지 컬럼들
        for (let i = 1; i < this.columns.length; i++) {
            const cell = row.insertCell();
                let value = item[this.dbColumns[i]] || "";
                            
                // 숫자 필드의 경우 우측 정렬
                if (this.alignments[i] === "r") {
                    cell.style.textAlign = "right";
                } else if (this.alignments[i] === "l") {
                    cell.style.textAlign = "left";
                } else {
                    cell.style.textAlign = "center";
                }

                cell.textContent = value;
            }
        
        // 행 클릭 이벤트 리스너 추가
        row.addEventListener("click", () => this.handleRowClick(row, item));
    });

    // Initialize sorting once
    if (!table.dataset.sorted) {
      const sorter = replacement(table);
      sorter.setChildren();
      table.dataset.sorted = true; // Mark as sorted
    }

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
            <span>${item.input_dt}</span>
        `;

      section.appendChild(headerDiv);

      // 입력 필드들
      const fields = [
        { label: "입력일", type: "increate_dt", value: item.increate_dt, readonly: true },
        { label: "대분류", type: "major_nm", value: item.major_nm, readonly: true },
        { label: "소분류", type: "code_nm", value: item.code_nm, readonly: true },
        { label: "행사명", type: "event_name", value: item.event_name, readonly: true },
        { label: "행사설명", type: "event_desc", value: item.event_desc, readonly: true },
        { label: "시작일", type: "start_date", value: item.start_date, readonly: true },
        { label: "종료일", type: "end_date", value: item.end_date, readonly: true },
        { label: "상품코드", type: "prod_code", value: item.prod_code, readonly: true },
        { label: "상품명", type: "prod_name", value: item.prod_name, readonly: true },
        { label: "단가", type: "unit_price", value: item.unit_price, readonly: true },
        { label: "목표수량", type: "target_qty", value: item.target_qty, readonly: true },
        { label: "실적수량", type: "result_qty", value: item.result_qty, readonly: true },
      ];

      console.log("[ln300::makeTableMobile]fields=" + fields);


      fields.forEach((field) => {
        const fieldDiv = document.createElement("div");
        fieldDiv.style.cssText =
          "display: flex; margin: 5px 0; align-items: center;";

        const input = document.createElement("input");
        input.type =
          field.type === "box" || field.type === "weight" ? "number" : "text";
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
      createDiv.textContent = item.increate_dt || "";
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

  // mobile:: 선택된 레코드 정보를 입력 필드에 표시하는 함수
  displaySelectedRecordInInputs(item) {
    const formattedDate = item.increate_dt ? item.increate_dt.substr(0, 10) : "";
    
    document.getElementById("increate-dt-event").value = formattedDate;
    document.getElementById("major-code-event").value = item.major_nm || "";
    document.getElementById("code-event").value = item.code_nm || "";
    document.getElementById("event-name").value = item.event_name || "";
    document.getElementById("event-desc").value = item.event_desc || "";
    document.getElementById("e-start-date").value = item.start_date || "";
    document.getElementById("e-end-date").value = item.end_date || "";
    document.getElementById("prod-code-event").value = item.prod_code || "";
    document.getElementById("unit-price-event").value = item.unit_price || "";
    document.getElementById("target-qty-event").value = item.target_qty || "";
    document.getElementById("result-qty-event").value = item.result_qty || "";
  }

  async handleRowClick(row, item) {
    // 이전 선택된 행의 스타일 제거
    const prevSelected = document.querySelector(".selected-row");
    if (prevSelected) {
      prevSelected.classList.remove("selected-row");
    }

    // 현재 행에 선택 스타일 적용
    row.classList.add("selected-row");

    // major_code 설정 및 소분류 목록 로딩
    document.getElementById("major-code-event").value = item.major_code || "";
    // fetchEventCode를 Promise로 감싸서 완료될 때까지 기다림
    if (item.major_code) {
        await new Promise((resolve) => {
            const url = purchase_srv + '?category=getEventCode&code=' + item.major_code;
            fetch(url)
                .then(response => response.json())
                .then(response => {
                    const codeSelect = document.getElementById('code-event');
                    // 기존 옵션 제거
                    while (codeSelect.options.length > 1) codeSelect.remove(1);
                    // 새 옵션 추가
                    response.data?.forEach(codeItem => {
                        const option = document.createElement('option');
                        option.value = codeItem.code;
                        option.textContent = `${codeItem.code}(${codeItem.codeNm})`;
                        codeSelect.appendChild(option);
                    });
                    resolve();
                });
        });
    }

    // 입력 필드에 값 설정
    document.getElementById("increate-dt-event").value = item.increate_dt?.substr(0, 10) || "";  // 생성일시 수정 가능
    document.getElementById("code-event").value = item.code || "";
    document.getElementById("event-name").value = item.event_name || "";
    document.getElementById("event-desc").value = item.event_desc || "";
    document.getElementById("e-start-date").value = item.start_date || "";
    document.getElementById("e-end-date").value = item.end_date || "";
    document.getElementById("prod-code-event").value = item.prod_code || "";
    document.getElementById("unit-price-event").value = item.unit_price || "";
    document.getElementById("target-qty-event").value = item.target_qty || "";
    document.getElementById("result-qty-event").value = item.result_qty || "";

    // 현재 선택된 행의 데이터 저장
    this.selectedRow = item;
  }

  saveData() {
    // 저장할 데이터 찾기
    const increate_dt = document.getElementById("increate-dt-event").value;
    const major_code  = document.getElementById("major-code-event").value;
    const code        = document.getElementById("code-event").value;
    const event_name = document.getElementById("event-name").value;
    const event_desc = document.getElementById("event-desc").value;
    const start_date = document.getElementById("e-start-date").value;
    const end_date   = document.getElementById("e-end-date").value;
    const prod_code_element = document.getElementById("prod-code-event");
    const prod_code = prod_code_element.value;
    // 상품명
    const selectedOption = prod_code_element.options[prod_code_element.selectedIndex];
    const prod_name = selectedOption ? selectedOption.textContent.match(/\((.*?)\)/)[1] : "";
    const unit_price = document.getElementById("unit-price-event").value;
    const target_qty = document.getElementById("target-qty-event").value;
    const result_qty = document.getElementById("result-qty-event").value;

    const data = { increate_dt, major_code, code, event_name, event_desc, start_date, end_date, prod_code, prod_name, unit_price, target_qty, result_qty };

    console.log("[ln427::saveData]data=" + JSON.stringify(data));

    // 필수 항목 검사
    const missingFields = [];
    if (increate_dt === "") missingFields.push("입력날짜");
    if (major_code === "") missingFields.push("대분류");
    if (code === "") missingFields.push("소분류");
    if (event_name === "") missingFields.push("행사명");
    if (event_desc === "") missingFields.push("행사설명");
    if (start_date === "") missingFields.push("시작일");
    if (end_date === "") missingFields.push("종료일");
    if (prod_code === "") missingFields.push("상품코드");
    if (unit_price === "") missingFields.push("단가");

    if (missingFields.length > 0) {
      alert(`다음 항목을 입력해주세요: ${missingFields.join(", ")}`);
      return;
    }

    // 서버로 데이터 전송
    const url = this.purchase_srv + `?category=save-event&user_id=${user_id}`; 
    console.log("save-event url= " + url);  

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
          this.loadData();
        } else {
            alert("저장 실패: " + (result.error || "알 수 없는 오류"));
        }
    })
    .catch(error => {
        console.error("Error:", error); 
        alert("서버 통신 중 오류가 발생했습니다.");
    });

  }

  // 개인판매 수정 -by 2025-04-07 darkcloud
  updateData() {
    // 업데이트할 데이터 준비
    const increate_dt = document.getElementById("increate-dt-event").value;
    const major_code  = document.getElementById("major-code-event").value;
    const code        = document.getElementById("code-event").value;
    const event_name = document.getElementById("event-name").value;
    const event_desc = document.getElementById("event-desc").value;
    const start_date = document.getElementById("e-start-date").value;
    const end_date   = document.getElementById("e-end-date").value;
    const prod_code_element = document.getElementById("prod-code-event");
    const prod_code = prod_code_element.value;
    // 상품명
    const selectedOption = prod_code_element.options[prod_code_element.selectedIndex];
    const prod_name = selectedOption ? selectedOption.textContent.match(/\((.*?)\)/)[1] : "";
    const unit_price = document.getElementById("unit-price-event").value;
    const target_qty = document.getElementById("target-qty-event").value;
    const result_qty = document.getElementById("result-qty-event").value;

    const data = {
      increate_dt: increate_dt,
      major_code: major_code,
      code: code,
      event_name: event_name,
      event_desc: event_desc,
      start_date: start_date,
      end_date: end_date,
      prod_code: prod_code,
      prod_name: prod_name,
      unit_price: unit_price,
      target_qty: target_qty,
      result_qty: result_qty,
      serial_no: this.selectedRow.serial_no
    };

    console.log("[ln503::updateData]data=" + JSON.stringify(data));

    if ( !data.increate_dt || !data.major_code || !data.code || !data.event_name || !data.event_desc || !data.start_date || !data.end_date || !data.prod_code || !data.unit_price || !data.serial_no ) {

      alert("모든 필수 항목을 입력해주세요.");

      // 비어있는 첫 번째 필드에 포커스 설정
      if (!data.increate_dt) {
        document.getElementById("increate-dt").focus();
      } else if (!data.major_code) {
        document.getElementById("major-code").focus();
      } else if (!data.code) {
        document.getElementById("code").focus();
      } else if (!data.event_name) {
        document.getElementById("event-name").focus();
      } else if (!data.event_desc) {
        document.getElementById("event-desc").focus();
      } else if (!data.start_date) {
        document.getElementById("start-date").focus();
      } else if (!data.end_date) {
        document.getElementById("end-date").focus();
      } else if (!data.prod_code) {
        document.getElementById("prod-code").focus();
      } 
    } 

    // 서버에 업데이트 요청
    const url = this.purchase_srv + `?category=update-event&user_id=${user_id}`;
    console.log(url);

    fetch(url, 
    {
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
          alert(result.message);
          this.loadData(); // 테이블 새로고침
        } else {
          alert("업데이트 실패: " + (result.error || "알 수 없는 오류"));
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("서버 통신 중 오류가 발생했습니다." + error.message);
      });
  }

  // 데이터 삭제
  deleteData() {
    // 선택된 체크박스 찾기
    const checkboxes = document.querySelectorAll(
      'input[type="checkbox"]:checked'
    );
    const serial_nos = Array.from(checkboxes).map((cb) => cb.dataset.serial_no);

    if (serial_nos.length === 0) {
      alert("삭제할 항목을 선택해주세요.");
      return;
    }

    if (!confirm(`선택한 ${serial_nos.length}개의 항목을 삭제하시겠습니까?`)) {
      return;
    }

    const url = this.purchase_srv + `?category=delete-event`;
    console.log("[ln433::delete-event]url= " + url);

    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ serial_nos: serial_nos }),
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          alert(result.message);
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
      case "increate_dt":
        item.increate_dt = value;
        break;
      case "major_code":
        item.major_code = value;
        break;
      case "code":
        item.code = value;
        break;
      case "event_name":
        item.event_name = value;
        break;
      case "event_desc":
        item.event_desc = value;
        break;
      case "start_date":
        item.start_date = value;
        break;
      case "end_date":
        item.end_date = value;
        break;
      case "prod_code":
        item.prod_code = value;
        break;
      case "unit_price":
        item.unit_price = value;
        break;
      case "target_qty":
        item.target_qty = value;
        break;
      case "result_qty":
        item.result_qty = value;
        break;
    }

    // 숫자 필드의 경우 입력값 정리
    if ( type === "unit_price" || type === "target_qty" || type === "result_qty" ) {
      input.value = value === "" ? "" : parseFloat(value) || 0;
    }
  }

  // 이벤트 리스너 초기화
  initializeEventListeners() {
    console.log("[ln692]PurchaseEventClass :: initializeEventListeners");
    // 옵셔널 체이닝으로 안전하게 처리
    const searchButton = document.getElementById("search-btn-event");
    const insertButton = document.getElementById("insert-btn-event");
    const updateButton = document.getElementById("update-btn-event");
    const deleteButton = document.getElementById("delete-btn-event");

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
} // PurchaseEventClass end

window.purchaseEventClass = new PurchaseEventClass();


// 파라미터: code (000=대분류조회, 그 외=소분류조회)
function fetchEventCode(code) {
    const url = purchase_srv + '?category=getEventCode&code=' + code;
    console.log('ln696::fetchEventCode url=' + url);
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(response => {
            if (response.status === 'error') throw new Error(response.message);

            const selectElements = [
                document.getElementById('major-code-event'),  //대분류
                document.getElementById('code-event')         //소분류
            ];

            if (code !== '000') {
                // 기존 옵션 제거 (첫 번째 옵션 '자재명' 유지)
                while (selectElements[1].options.length > 1) selectElements[1].remove(1);
            }
      
            selectElements.forEach(selectElement => {
                // 기존 옵션 제거 (첫 번째 옵션 '자재명' 유지)
                // while (selectElement.options.length > 1) selectElement.remove(1);

                response.data?.forEach(item => {
                    if (selectElement.id === 'major-code-event' && code === '000') {
                            // 행사 대분류 코드
                            const option = document.createElement('option');
                            option.value = item.code;
                            option.textContent = `${item.code}(${item.codeNm})`;
                            selectElement.appendChild(option);
                    } else if (selectElement.id === 'code-event' && code !== '000') {
                            // 행사 소분류 코드
                            const option = document.createElement('option');
                            option.value = item.code;
                            option.textContent = `${item.code}(${item.codeNm})`;
                            selectElement.appendChild(option);
                    }
                });
            });
        })
        .catch(error => console.error('대분류, 소분류 목록 조회 중 오류 발생:', error));
}

// ------------------------------------------------------
// 대분류 선택 시 소분류 코드 조회 change 이벤트
// 소분류 선택 시 행사명 필드에 코드명 추가 change 이벤트
// ------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    console.log('ln712::document.addEventListener' + '대분류, 소분류 목록 조회');

    // 대분류 선택 시 소분류 코드 조회 change 이벤트
    const majorCodeSelect = document.getElementById('major-code-event');
    const codeSelect = document.getElementById('code-event');

    if (majorCodeSelect) {
        majorCodeSelect.addEventListener('change', function() {
          const majorCode = this.value;
          console.log('ln715::majorCode=' + majorCode);
          if (majorCode !== '') {
            majorCode.innerHTML = '<option value="">행사#선택</option>';
            fetchEventCode(majorCode);
          } else {
            codeSelect.innerHTML = '<option value="">기획#선택</option>';
          }
      });
    }

    // 소분류 선택 시 행사명 필드에 코드명 추가
    if (codeSelect) {
      codeSelect.addEventListener('change', function() {
        const code = this.value;
        console.log('ln720::code=' + code);
        
        // 선택된 옵션의 텍스트에서 코드명 추출
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.textContent) {
          const codeNmMatch = selectedOption.textContent.match(/\((.*?)\)/);
          if (codeNmMatch && codeNmMatch[1]) {
            // 추출된 코드명을 행사명 필드에 설정
            const eventNameInput = document.getElementById('event-name');
            if (eventNameInput) {
              eventNameInput.value = codeNmMatch[1];
            }
          }
        }
      });
    }
});
