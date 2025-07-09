// --------------------------------------------------------------------------------------
// 개인판매 :: -by DC정순장 2025-03-18
// 개인판매(물고기아저씨, 연어처럼 등) 
// 완출, GS점은 생산 시 (invoice_yn=n) 로 되어 있고, 따로 출고가 없으므로 여기서 출고 처리함.
// 생산점: 기타점은 생산하여 기타점으로만 출고, 그 외는 생산한 중량에서 선택한 판매점으로 출고 처리
// 판매점: 수불에서 출고 표시 용도
// 비고: 개인판매는 store_no 에 입력
// --------------------------------------------------------------------------------------
class PurchaseOutClass {
  constructor() {
    this.columns = [
      "선택",
      "판매번호",
      "판매일",
      "B/L번호",
      "점번호",
      "판매점",
      "비고",
      "중량",
      "상품코드",
      "상품명",
      "출고여부",
      "생성일",
      "담당자"
    ];
    this.colWidths = [
     "5%",  // 선택
      "7%",  // 판매번호
      "7%",  // 판매일
      "7%", // B/L번호
      "7%",  // 출고점코드
      "7%",  // 판매점코드
      "12%",  // 비고
      "5%",  // 중량
      "10%", // 상품코드
      "10%",  // 상품명
      "5%",  // 츨고여부
      "12%", // 생성일시
      "6%",  // 담당자
    ];
    this.dbColumns = [
      "check",
      "serial_no",
      "increate_dt",
      "lc_no",
      "origin_store_code",
      "store_code",
      "store_no", // 생산에서 판매점이지만, 개임판매 시 비고란
      "weight",
      "prod_code",
      "prod_nm",
      "invoice_yn",
      "modify_dt",
      "create_name",
    ];
    this.alignments = ["c", "c", "c", "c",  "c", "c", "l", "c", "c", "c", "c", "c"];
    this.data = [];
    this.selectedRow = null;
    this.table = null; // 인스턴스 변수로 선언

    // purchase_srv 기본값 설정
    this.purchase_srv = window.purchase_srv || '/wp-content/plugins/purchase/purchase_srv.php';
    // this.barcodeIn_srv = window.barcodeIn_srv || '/wp-content/plugins/barcodeIn/barcodeIn_srv.php';

    // DOM이 완전히 로드된 후 초기화
    document.addEventListener("DOMContentLoaded", () => {
      this.initializeEventListeners();
      this.setDefaultDates();
      this.loadData();
      this.makeTableHead();
    });

  }

  // 날짜 기본값 설정 메서드
  setDefaultDates() {
    const today = new Date();
    const oneWeekAgo = new Date();
    oneWeekAgo.setDate(today.getDate() - 7);

    // 날짜를 YYYY-MM-DD 형식으로 변환
    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    };

    // 시작일과 종료일 설정
    const startDateInput = document.getElementById("start-date-out");
    const endDateInput = document.getElementById("end-date-out");

    if (startDateInput) {
      startDateInput.value = formatDate(oneWeekAgo);
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

      if (i === 0 || i === 1 || i === 4 || i === 9 ) {
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
    const startDateInput = document.getElementById("start-date-out");
    const endDateInput = document.getElementById("end-date-out");

    // 날짜 입력 필드가 없으면 기본값 사용
    const startDate = startDateInput ? startDateInput.value : this.getDefaultStartDate();
    const endDate = endDateInput ? endDateInput.value : this.getDefaultEndDate();

    const url = this.purchase_srv + `?category=search-out&start_date=${startDate}&end_date=${endDate}`;
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

                // 자재명인 경우 빨간색으로 표시
                if (this.dbColumns[i] === "mat_nm") {
                    cell.style.color = "red";
                }
              
                // 중량 소수점 1자리까지 표시하되, 소수점 이하가 0이면 표시하지 않음
                 if (this.dbColumns[i] === "weight") {
                    let numValue = parseFloat(value);
                    if (!isNaN(numValue)) {
                        value = numValue.toFixed(1);
                        if (value.split('.')[1] === '0') {
                            value = value.split('.')[0];
                        }
                    }
                }
              
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
        { label: "판매일", type: "increate_dt", value: item.increate_dt, readonly: true },
        { label: "B/L번호", type: "lc_no", value: item.lc_no, readonly: true },
        { label: "점번호", type: "origin_store_code", value: item.origin_store_code, readonly: true },
        { label: "판매점", type: "store_code", value: item.store_code, readonly: true },
        { label: "비고", type: "store_no", value: item.store_no, readonly: true },
        { label: "중량", type: "weight-out", value: item.weight, readonly: true },
        { label: "상품코드", type: "prod_code", value: item.prod_code, readonly: true },
        { label: "상품명", type: "prod_nm", value: item.prod_nm, readonly: true },
        { label: "출고여부", type: "invoice_yn", value: item.invoice_yn, readonly: true },
        { label: "생성일시", type: "modify_dt", value: item.modify_dt, readonly: true },
        { label: "담당자", type: "create_name", value: item.create_name, readonly: true },
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

  // 선택된 레코드 정보를 입력 필드에 표시하는 함수
  displaySelectedRecordInInputs(item) {
    const formattedDate = item.increate_dt ? item.increate_dt.substr(0, 10) : "";
    
    document.getElementById("increate-dt").value = formattedDate;
    document.getElementById("prod-code").value = item.prod_code || "";
    document.getElementById('lc-no').value = item.lc_no || "";
    document.getElementById("store-no").value = item.store_no || "";
    document.getElementById("weight-out").value = item.weight || "";

  }

  handleRowClick(row, item) {
    // 이전 선택된 행의 스타일 제거
    const prevSelected = document.querySelector(".selected-row");
    if (prevSelected) {
      prevSelected.classList.remove("selected-row");
    }

    // 현재 행에 선택 스타일 적용
    row.classList.add("selected-row");

    // 입력 필드에 값 설정
    document.getElementById("create-dt-out").value = item.increate_dt?.substr(0, 10) || "";  // 생성일시 수정 가능
    document.getElementById("prod-code").value = item.prod_code || "";
    document.getElementById("lc-no").value = item.lc_no || "";
    document.getElementById("origin-store-code").value = item.origin_store_code || "";
    document.getElementById("store-code").value = item.store_code || "";
    document.getElementById("store-no").value = item.store_no || "";
    document.getElementById("weight-out").value = item.weight || "";

    // 현재 선택된 행의 데이터 저장
    this.selectedRow = item;
  }

  saveData() {
    // 저장할 데이터 찾기
    const increate_dt = document.getElementById("create-dt-out").value;
    const prod_code   = document.getElementById("prod-code").value;
    const prod_nm     = document.getElementById("prod-code").selectedOptions[0].text;
    const lc_no       = document.getElementById("lc-no").value;
    const origin_store_code = document.getElementById("origin-store-code").value;
    const store_code  = document.getElementById("store-code").value;
    const store_no    = document.getElementById("store-no").value;
    const weight      = document.getElementById("weight-out").value;
    const data = { increate_dt, prod_code, prod_nm, lc_no, origin_store_code, store_code, store_no, weight };

    // 필수 항목 검사
    const missingFields = [];
    if (increate_dt === "") missingFields.push("입력 날짜");
    if (prod_code === "")   missingFields.push("상품 코드");
    if (prod_nm === "")     missingFields.push("상품명");
    if (lc_no === "")       missingFields.push("B/L번호");
    if (origin_store_code === "") missingFields.push("점번호");
    if (store_code === "")    missingFields.push("판매점");
    if (store_no === "")    missingFields.push("판매점");
    if (weight === "")  missingFields.push("중량");

    if (missingFields.length > 0) {
      alert(`다음 항목을 입력해주세요: ${missingFields.join(", ")}`);
      return;
    }

    // 서버로 데이터 전송
    const url = this.purchase_srv + `?category=save-out&user_id=${user_id}`; 
    console.log("save-out url= " + url);

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
          // alert("개인판매가 생산/출고데이터에 저장되었습니다.");
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

  // 개인판매 수정 -by 2025-03-17 darkcloud
  updateData() {
    // 업데이트할 데이터 준비
    const increate_dt = document.getElementById("create-dt-out").value; // 판매일
    const prod_code   = document.getElementById("prod-code").value; // 상품코드
    const prod_nm     = document.getElementById("prod-code").selectedOptions[0].text; // 상품명
    const lc_no       = document.getElementById("lc-no").value; // B/L번호
    const origin_store_code = document.getElementById("origin-store-code").value; // 점번호
    const store_code  = document.getElementById("store-code").value; // 판매점
    const store_no    = document.getElementById("store-no").value; // 판매점
    const weight_out  = document.getElementById("weight-out").value;  // 중량 

    const data = {
      increate_dt: increate_dt,
      prod_code: prod_code,
      prod_nm: prod_nm,
      lc_no: lc_no,
      origin_store_code: origin_store_code,
      store_code: store_code,
      store_no: store_no,
      weight: weight_out,
      serial_no: this.selectedRow.serial_no
    };

    // console.log("[ln600::updateData]data=" + data);

    if ( !data.increate_dt || !data.prod_code || !data.prod_nm || !data.lc_no || !data.origin_store_code || !data.store_code || !data.store_no || !data.weight || !data.serial_no ) {

      alert("모든 필수 항목을 입력해주세요.");

      // 비어있는 첫 번째 필드에 포커스 설정
      if (!data.increate_dt) {
        document.getElementById("create-dt-out").focus();
      } else if (!data.prod_code) {
        document.getElementById("prod-code").focus();
      } else if (!data.lc_no) {
        document.getElementById("lc-no").focus();
      } else if (!data.origin_store_code) {
        document.getElementById("origin-store-code").focus();
      } else if (!data.store_code) {
        document.getElementById("store-code").focus();
      } else if (!data.store_no) {
        document.getElementById("store-no").focus();
      } else if (!data.weight) {
        document.getElementById("weight-out").focus();
      }
      return;
    } 

    // 서버에 업데이트 요청
    const url = this.purchase_srv + `?category=update-out&user_id=${user_id}`;
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

    const url = this.purchase_srv + `?category=delete-out`;
    console.log("[ln433::delete-out]url= " + url);

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
      case "prod_code":
        item.prod_code = value;
        break;
      case "lc_no":
        item.lc_no = value;
        break;
      case "origin_store_code":
        item.origin_store_code = value;
        break;
      case "store_code":
        item.store_code = value;
        break;
      case "store_no":
        item.store_no = value;
        break;
      case "weight-out":
        item.weight = value;
        break;
    }

    // 숫자 필드의 경우 입력값 정리
    if ( type === "weight") {
      input.value = value === "" ? "" : parseFloat(value) || 0;
    }
  }

  // 이벤트 리스너 초기화
  initializeEventListeners() {
    console.log("[ln600]PurchaseOutClass :: initializeEventListeners");
    // 옵셔널 체이닝으로 안전하게 처리
    const searchButton = document.getElementById("search-btn-out");
    const insertButton = document.getElementById("insert-btn-out");
    const updateButton = document.getElementById("update-btn-out");
    const deleteButton = document.getElementById("delete-btn-out");

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
} // PurchaseClass end

window.purchaseOutClass = new PurchaseOutClass();
window.purchase_srv = '/wp-content/plugins/purchase/purchase_srv.php';
window.barcodeIn_srv = '/wp-content/plugins/barcodeIn/barcodeIn_srv.php';

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

// BL# 가져오기::DB 입력일(increate_dt), BL#(lc_no)
$(document).ready(function() {
  const url = barcodeIn_srv + '?category=lc';
  console.log("[ln627::purchase-out.js]url=" + url);      

  $.ajax({
      url: url,
      type: 'GET',
      dataType: 'json',
      success: function(data) {
          var selectElement = $('#lc-no');
          data.forEach(function(item) {
              selectElement.append($('<option>', {
                  value: item.bl_no,
                  text: item.bl_no + item.import_dt
              }));
          });
      },
      error: function(xhr, status, error) {
          console.error("An error occurred: " + error);
      }
  });
});

// 점번호, 판매점 목록 조회
function fetchStoreList() {
    const url = purchase_srv + '?category=getStoreCd';
    console.log('url=' + url);
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(response => {
            if (response.status === 'error') throw new Error(response.message);

            const selectElements = [
                document.getElementById('origin-store-code'), //점번호 - 
                document.getElementById('store-code') //판매점
            ];

            selectElements.forEach(selectElement => {
                // 기존 옵션 제거 (첫 번째 옵션 '자재명' 유지)
                while (selectElement.options.length > 1) selectElement.remove(1);

                // 옵션 추가
                response.data?.forEach(item => {
                    if (selectElement.id === 'origin-store-code') {
                        // 점번호용 옵션 (000, 002, 003, 004 제외)
                        if (item.code !== '000' && item.code !== '003' && item.code !== '004' && item.code !== '007') {
                            const option = document.createElement('option');
                            option.value = item.code;
                            option.textContent = `${item.code}(${item.codeNm})`;
                            selectElement.appendChild(option);
                        }
                        // 판매점용 옵션 (000, 002, 003, 004 제외)
                    } else if (selectElement.id === 'store-code') {
                        if (item.code !== '000' && item.code !== '002' && item.code !== '003' && item.code !== '004') {
                            const option = document.createElement('option');
                            option.value = item.code;
                            option.textContent = `${item.code}(${item.codeNm})`;
                            selectElement.appendChild(option);
                        }
                    }
                });
            });
        })
        .catch(error => console.error('납품점, 출고점 목록 조회 중 오류 발생:', error));
}