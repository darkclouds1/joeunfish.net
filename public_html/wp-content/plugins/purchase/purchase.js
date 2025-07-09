// 매입(이동) 메뉴
class PurchaseClass {
  constructor() {
    this.columns = [
      "선택",
      "매입번호",
      "매입일",
      "업체명",
      "Box 수",
      "kg",
      // "Lot 명",
      "자재명",
      "비고",
      "단가",
      "재고",
      "이동량",
      "생성일시",
      "담당자",
      "이동"
    ];
    this.colWidths = [
      "5%", // 선택
      "5%", // 번호
      "7%", // 매입일
      "10%", // 업체명
      "6%", // Box 수
      "6%", // Weight(kg)
      // "13%", // Lot 명
      "10%", // 자재명
      "10%", // 비고
      "6%", // 단가
      "6%", // 현재고
      "6%", // 이동량
      "10%", // 생성일시
      "5%", // 담당자
      "6%", // 이동
    ];
    this.dbColumns = [
      "check",
      "serial_no",
      "input_dt",
      "vendor_nm",
      "box",
      "weight",
      // "lot_nm",
      "mat_nm",
      "remarks",
      "unitPrice",
      "qty_cur",
      "qty_in",
      "create_dt",
      "modify_name",
      "이동",
    ];
    this.alignments = ["c", "c", "c", "l", "r", "r", "l", "l", "r", "r", "r", "c", "c", "c"];
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
    const startDateInput = document.getElementById("start-date");
    const endDateInput = document.getElementById("end-date");

    if (startDateInput) {
      startDateInput.value = formatDate(oneMonthAgo);
    }
    if (endDateInput) {
      endDateInput.value = formatDate(today);
    }
  }

  loadData() {
    const startDateInput = document.getElementById("start-date");
    const endDateInput = document.getElementById("end-date");

    // 날짜 입력 필드가 없으면 기본값 사용
    const startDate = startDateInput
      ? startDateInput.value
      : this.getDefaultStartDate();
    const endDate = endDateInput
      ? endDateInput.value
      : this.getDefaultEndDate();

    const url = this.purchase_srv + `?category=search&start_date=${startDate}&end_date=${endDate}`;
    console.log(url);
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

  makeTableHead() {
    console.log("[ln94::makeTableHead]");

    const table = document.createElement("table");
    table.id = "specData";
    table.style.width = "100%";

    const thead = document.createElement("thead");
    thead.style.backgroundColor = "#000"; // thead의 배경색을 검은색으로 설정
    thead.style.color = "#fff"; // thead의 글자색을 흰색으로 설정

    const headerRow = document.createElement("tr");

    this.columns.forEach((col, i) => {
      const th = document.createElement("th");

      if (i === 0 || i === 1 || i === 12 || i === 13) {
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
    table.appendChild(thead);

    const tableContainer = document.getElementById("tables");
    tableContainer.innerHTML = ""; // Clear existing content
    tableContainer.appendChild(table); // Append the table to the container
  }

  makeTableBody(data) {
    console.log("[ln178::makeTableBody]");

    const table = document.getElementById("specData");
    if (!table) return;

    const tbody = table.createTBody();
    let isAlternateColor = false; // 배경색 초기값 설정

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
        checkbox.dataset.serialNo = item.serial_no;
        checkCell.appendChild(checkbox);
        checkCell.style.textAlign = "center";

        // 나머지 컬럼들
        for (let i = 1; i < this.columns.length; i++) {
            const cell = row.insertCell();
            
            if (this.columns[i] === "이동") {
                // console.log("이동 버튼 생성");
                const btn = document.createElement("button");
                btn.textContent = "이동";
                btn.style.cssText = `
                    padding: 4px 8px;
                    background-color: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    display: inline-block;
                    margin: 2px;
                    width: 90% !important;
                    height: 30px !important;
                    font-size: 13px;
                `;
                
                // 버튼 클릭 이벤트
                btn.onclick = (e) => {
                  e.stopPropagation(); // 행 클릭 이벤트 전파 방지
                  this.savePurchase_in(item);
                };
                
                cell.style.textAlign = "center";
                cell.appendChild(btn);
            } else {
                let value = item[this.dbColumns[i]] || "";

                // 날짜 필드의 경우 10자리만 표시
                if (this.dbColumns[i] === "input_dt" && value.length > 10) {
                    value = value.substr(0, 10);
                }

                // 자재명인 경우 빨간색으로 표시
                if (this.dbColumns[i] === "mat_nm") {
                    cell.style.color = "red";
                }
                            
                // 현재고 및 이동량은 소수점 2자리까지 표시하되, 소수점 이하가 0이면 표시하지 않음
                if ( this.dbColumns[i] === 'weight' || this.dbColumns[i] === "qty_cur" || this.dbColumns[i] === "qty_in") {
                    let numValue = parseFloat(value);
                    if (!isNaN(numValue)) {
                        value = numValue.toFixed(2);
                        if (value.split('.')[1] === '00') {
                            value = value.split('.')[0];
                        }
                    }
                }
              
                // 생성일자는 10자리만 표시
                if (this.dbColumns[i] === "input_dt" && value.length > 10) {
                    value = value.substr(0, 10);
                }
              
                if (this.dbColumns[i] === "create_dt") {
                    value = value.substr(0, 16);
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
        {
          label: "자재명",
          type: "mat_nm",
          value: item.mat_nm,
          readonly: true,
        },
        {
          label: "업체명",
          type: "vendor_nm",
          value: item.vendor_nm,
          readonly: true,
        },
        { label: "Box 수", type: "box", value: item.box, readonly: true },
        {
          label: "Weight",
          type: "weight",
          value: item.weight,
          readonly: true,
        },
        { label: "Lot 명", type: "lot_no", value: item.lot_nm, readonly: true },
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
      createDiv.textContent = item.create_dt || "";
      section.appendChild(createDiv);

      // 클릭 이벤트 리스너 추가
      section.addEventListener("click", () => {
        console.log(`Section clicked: ${item.serial_no}`);
        this.displaySelectedRecordInInputs(item);
      });

      container.appendChild(section);
    });
  }

  // 선택된 레코드 정보를 입력 필드에 표시하는 함수
  displaySelectedRecordInInputs(item) {
    const formattedDate = item.input_dt ? item.input_dt.substr(0, 10) : "";
    
    document.getElementById("input-dt").value = formattedDate;

    console.log("[ln375::displaySelectedRecordInInputs]item=" + item.mat_cd);
    document.getElementById('mat-cd').value = item.mat_cd || "";
    document.getElementById("vendor-nm").value = item.vendor_nm || "";
    document.getElementById("box").value = item.box || "";
    document.getElementById("weight").value = item.weight || "";
    document.getElementById("remarks").value = item.remarks || "";
  }

  handleRowClick(row, item) {
    // 이전 선택된 행의 스타일 제거
    console.log("[ln389::handleRowClick]item=" + item.mat_cd);

    const prevSelected = document.querySelector(".selected-row");
    if (prevSelected) {
      prevSelected.classList.remove("selected-row");
    }

    // 현재 행에 선택 스타일 적용
    row.classList.add("selected-row");

    // 입력 필드에 값 설정
    document.getElementById("input-dt").value = item.input_dt?.substr(0, 10) || "";
    document.getElementById("mat-cd").value = item.mat_cd || "";
    document.getElementById("vendor-nm").value = item.vendor_nm || "";
    document.getElementById("box").value = item.box || "";
    document.getElementById("weight").value = item.weight || "";
    document.getElementById("remarks").value = item.remarks || "";
    document.getElementById("unitPrice").value = item.unitPrice || "";
    // 현재 선택된 행의 데이터 저장
    this.selectedRow = item;
  }

  // 데이터 저장
  saveData() {
    // 저장할 데이터 찾기
    const input_dt = document.getElementById("input-dt").value;
    const mat_cd = document.getElementById("mat-cd").value;
    const vendor_nm = document.getElementById("vendor-nm").value;
    const box = document.getElementById("box").value;
    const weight = document.getElementById("weight").value;
    const remarks = document.getElementById("remarks").value;
    const unitPrice = document.getElementById("unitPrice").value;
    const data = { input_dt, mat_cd, vendor_nm, box, weight, remarks, unitPrice };

    const missingFields = [];

    if (input_dt === "") missingFields.push("입력 날짜");
    if (mat_cd === "") missingFields.push("자재 코드");
    if (vendor_nm === "") missingFields.push("업체명");
    if (box === "") missingFields.push("Box 수");
    if (weight === "") missingFields.push("중량");
    if (unitPrice === "") missingFields.push("단가");

    if (missingFields.length > 0) {
      alert(`다음 항목을 입력해주세요: ${missingFields.join(", ")}`);
      return;
    }
    const url = this.purchase_srv + `?category=save&user_id=${user_id}`;
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
      input_dt: document.getElementById("input-dt").value,
      mat_cd: document.getElementById("mat-cd").value,
      vendor_nm: document.getElementById("vendor-nm").value,
      box: document.getElementById("box").value,
      weight: document.getElementById("weight").value,
      remarks: document.getElementById("remarks").value,
      unitPrice: document.getElementById("unitPrice").value,
      serial_no: this.selectedRow.serial_no,
    };

    console.log(data);

    // 필수 데이터가 입력되었는지 확인
    if (
      !data.input_dt ||
      !data.mat_cd ||
      !data.unitPrice ||
      !data.vendor_nm ||
      !data.box ||
      !data.weight ||
      !data.serial_no
    ) {
      alert("모든 필수 항목을 입력해주세요.");
      return;
    }

    // 서버에 업데이트 요청
    const url = this.purchase_srv + `?category=update&user_id=${user_id}`;
    console.log(url);

    fetch(
      url,
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

    const url = this.purchase_srv + "?category=delete";
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
      case "vendor_nm":
        item.vendor_nm = value;
        break;
      case "box":
        item.box = value === "" ? null : parseInt(value);
        break;
      case "weight":
        item.weight = value === "" ? null : parseFloat(value);
        break;
      case "lot_no":
        item.lot_no = value;
        break;
      case "unitPrice":
        item.unitPrice = value === "" ? null : parseInt(value);
        break;
      case "remarks":
        item.remarks = value;
        break;
    }

    // 숫자 필드의 경우 입력값 정리
    if (type === "box" || type === "weight" || type === "unitPrice") {
      input.value = value === "" ? "" : parseFloat(value) || 0;
    }
  }

  // 이동 데이터 저장
  savePurchase_in(item) {
    // console.log("savePurchase_in", item);

    // 이동 데이터를 입력받기 위한 예시
    const qtyIn = prompt("이동량을 입력하세요:");
    if (qtyIn === null || qtyIn.trim() === "") {
        alert("이동량을 입력해야 합니다.");
        return;
    }

    const qtyInNumber = parseFloat(qtyIn);
    const qtyCurNumber = parseFloat(item.qty_cur);

    if (qtyInNumber > qtyCurNumber) {
        alert("이동량이 현재 잔량보다 클 수 없습니다.");
        return;
    }

    const data = {
      tpurchase_serial_no: item.serial_no,
      qty_in: parseFloat(qtyIn),
      create_id: user_id // 사용자 ID를 적절히 설정
    };

    // 서버로 데이터 전송
    const url = this.purchase_srv + '?category=insert-in'; 
    console.log("insert-in url= " + url);

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
          alert("이동 데이터가 저장되었습니다.");
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

  // 이벤트 리스너 초기화
  initializeEventListeners() {
    console.log("initializeEventListeners");
    // 옵셔널 체이닝으로 안전하게 처리
    const searchButton = document.getElementById("search-btn");
    const insertButton = document.getElementById("insert-btn");
    const updateButton = document.getElementById("update-btn");
    const deleteButton = document.getElementById("delete-btn");

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

  // 매입,자재:: 자재명명 목록
  fetchMaterialList() {
      const url = purchase_srv + '?category=getMatCd';
      console.log('url=' + url);
      fetch(url)
          .then(response => {
              if (!response.ok) throw new Error('Network response was not ok');
              return response.json();
          })
          .then(response => {
              if (response.status === 'error') throw new Error(response.message);

              const selectElements = [
                  document.getElementById('mat-cd'),
              ];

              selectElements.forEach(selectElement => {
                  // 기존 옵션 제거 (첫 번째 옵션 '자재명' 유지)
                  while (selectElement.options.length > 1) selectElement.remove(1);

                  // 옵션 추가
                  response.data?.forEach(item => {
                      const option = document.createElement('option');
                      option.value = item.mat_cd;
                      option.textContent = `${item.mat_cd} - ${item.mat_nm}`;
                      selectElement.appendChild(option);
                  });
              });
          })
          .catch(error => console.error('자재 목록 조회 중 오류 발생:', error));
  }

} // PurchaseClass end

window.purchaseClass = new PurchaseClass();