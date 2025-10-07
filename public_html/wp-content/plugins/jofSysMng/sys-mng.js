// 시스템 작업관리
class SysMngClass {
  constructor() {
    this.columns = [
      "선택",
      "번호",
      "입력일시",
      "요청일시",
      "제목",
      "내용",
      "작업상세",
      "작업 시작일시",
      "작업 종료일시",
      "유/무상",
      "담당자",
      "수정일시"
    ];
    this.colWidths = [
      "3%",   // 선택
      "3%",   // 번호
      "8%",   // 입력일시
      "8%",   // 요청일시
      "10%",  // 제목
      "12%",  // 내용
      "24%",  // 작업상세 (2배 크게)
      "8%",   // 작업 시작일시
      "8%",   // 작업 종료일시
      "5%",   // 유/무상
      "5%",   // 담당자
      "6%"    // 수정일시
    ];
    this.dbColumns = [
      "check",
      "serial_no",
      "increate_dt",
      "request_dt",
      "title",
      "content",
      "work_detail",
      "start_dt",
      "end_dt",
      "cost_type",
      "modify_name",
      "modify_dt"
    ];
    this.alignments = ["c", "c", "c", "c", "l", "l", "l", "c", "c", "c", "c", "c"];
    this.data = [];
    this.selectedRow = null;
    this.sys_mng_srv = window.sys_mng_srv || '/wp-content/plugins/jofSysMng/sys-mng_srv.php';

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

    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    };

    const startDateInput = document.getElementById("start-date-sys");
    const endDateInput = document.getElementById("end-date-sys");

    if (startDateInput) {
      startDateInput.value = formatDate(oneMonthAgo);
    }
    if (endDateInput) {
      endDateInput.value = formatDate(today);
    }
  }

  makeTableHead() {
    const table = document.createElement("table");
    table.id = "sysMngData";
    table.style.width = "100%";

    const thead = document.createElement("thead");
    thead.style.backgroundColor = "#000";
    thead.style.color = "#fff";

    const headerRow = document.createElement("tr");

    this.columns.forEach((col, i) => {
      const th = document.createElement("th");
      th.innerHTML = col;
      th.style.width = this.colWidths[i];
      headerRow.appendChild(th);
    });

    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = document.createElement("tbody");
    table.appendChild(tbody);

    document.getElementById("tables").innerHTML = "";
    document.getElementById("tables").appendChild(table);
  }

  loadData() {
    const startDateInput = document.getElementById("start-date-sys");
    const endDateInput = document.getElementById("end-date-sys");
    const searchKeyword = document.getElementById("search-keyword").value;

    const startDate = startDateInput ? startDateInput.value : this.getDefaultStartDate();
    const endDate = endDateInput ? endDateInput.value : this.getDefaultEndDate();

    const url = this.sys_mng_srv + `?category=search&start_date=${startDate}&end_date=${endDate}&search_keyword=${encodeURIComponent(searchKeyword)}`;
    console.log(url);
    
    fetch(url)
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          this.data = result.data;
          const tableContainer = document.getElementById("tables");
          tableContainer.innerHTML = "";

          if (platForm === "computer") {
            if (this.data.length > 0) {
              this.makeTableHead();
              this.makeTableBody(this.data);
            } else {
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
    const tbody = document.querySelector("#sysMngData tbody");
    
    tbody.innerHTML = "";
    if (!tbody) return;
    
    let isAlternateColor = false;

    data.forEach((item) => {
      const row = tbody.insertRow();
      isAlternateColor = !isAlternateColor;

      if (isAlternateColor) {
        row.classList.add("alternate-color-1");
      } else {
        row.classList.add("alternate-color-2");
      }

      // 체크박스 셀
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

        // 날짜시간 필드의 경우 16자리만 표시 (YYYY-MM-DD HH:MM)
        if (["increate_dt", "request_dt", "start_dt", "end_dt", "modify_dt"].includes(this.dbColumns[i]) && value.length > 16) {
          value = value.substr(0, 16);
        }

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
  }

  makeTableMobile(data) {
    const container = document.getElementById("tables");
    container.innerHTML = "";

    data.forEach((item) => {
      const section = document.createElement("div");
      section.className = "mobile-sys-mng-section";
      section.style.cssText = "margin: 10px 0; padding: 10px; background-color: #f5f5f5; border-radius: 8px;";

      // 체크박스와 번호
      const headerDiv = document.createElement("div");
      headerDiv.style.cssText = "display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;";

      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.dataset.serialNo = item.serial_no;

      headerDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                ${checkbox.outerHTML}
                <span style="font-weight: bold;">No. ${item.serial_no}</span>
            </div>
            <span>${item.increate_dt.substr(0, 16)}</span>
        `;

      section.appendChild(headerDiv);

      // 입력 필드들
      const fields = [
        { label: "요청일시", value: item.request_dt ? item.request_dt.substr(0, 16) : "" },
        { label: "제목", value: item.title },
        { label: "내용", value: item.content },
        { label: "작업상세", value: item.work_detail },
        { label: "작업 시작일시", value: item.start_dt ? item.start_dt.substr(0, 16) : "" },
        { label: "작업 종료일시", value: item.end_dt ? item.end_dt.substr(0, 16) : "" },
        { label: "유/무상", value: item.cost_type },
        { label: "담당자", value: item.modify_name }
      ];

      fields.forEach((field) => {
        const fieldDiv = document.createElement("div");
        fieldDiv.style.cssText = "margin: 5px 0;";
        fieldDiv.innerHTML = `<span style="font-weight: bold;">${field.label}:</span> ${field.value || ""}`;
        section.appendChild(fieldDiv);
      });

      // 클릭 이벤트 리스너 추가
      section.addEventListener("click", () => {
        this.displaySelectedRecordInInputs(item);
      });

      container.appendChild(section);
    });
  }

  // 선택된 레코드 정보를 입력 필드에 표시하는 함수
  displaySelectedRecordInInputs(item) {
    document.getElementById("increate-dt").value = item.increate_dt ? item.increate_dt.replace(" ", "T").substr(0, 16) : "";
    document.getElementById("request-dt").value = item.request_dt ? item.request_dt.replace(" ", "T").substr(0, 16) : "";
    document.getElementById("title").value = item.title || "";
    document.getElementById("sys-content").value = item.content || "";
    document.getElementById("work-detail").value = item.work_detail || "";
    document.getElementById("start-dt").value = item.start_dt ? item.start_dt.replace(" ", "T").substr(0, 16) : "";
    document.getElementById("end-dt").value = item.end_dt ? item.end_dt.replace(" ", "T").substr(0, 16) : "";
    document.getElementById("cost-type").value = item.cost_type || "";
  }

  handleRowClick(row, item) {
    // 모든 행의 선택 스타일 제거
    document.querySelectorAll("#sysMngData tbody tr").forEach((r) => {
      r.classList.remove("selected-row");
    });

    // 클릭한 행에 선택 스타일 추가
    row.classList.add("selected-row");

    // 입력 필드에 값 설정
    this.displaySelectedRecordInInputs(item);
    // 현재 선택된 행의 데이터 저장
    this.selectedRow = item;
  }

  // 데이터 저장
  saveData() {
    const contentElement = document.getElementById("sys-content");
    const contentValue = contentElement ? contentElement.value : "";
    
    const increate_dt = document.getElementById("increate-dt").value.replace("T", " ");
    const request_dt = document.getElementById("request-dt").value.replace("T", " ");
    const title = document.getElementById("title").value;
    const content = contentValue;
    const work_detail = document.getElementById("work-detail").value;
    const start_dt = document.getElementById("start-dt").value.replace("T", " ");
    const end_dt = document.getElementById("end-dt").value.replace("T", " ");
    const cost_type = document.getElementById("cost-type").value;
    
    const data = {
      increate_dt,
      request_dt,
      title,
      content,
      work_detail,
      start_dt,
      end_dt,
      cost_type
    };

    if (!title) {
      alert("제목을 입력해주세요.");
      return;
    }

    const url = this.sys_mng_srv + `?category=save&user_id=${user_id}`;
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
          this.loadData();
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
    const contentElement = document.getElementById("sys-content");
    const contentValue = contentElement ? contentElement.value : "";
    
    const data = {
      increate_dt: document.getElementById("increate-dt").value.replace("T", " "),
      request_dt: document.getElementById("request-dt").value.replace("T", " "),
      title: document.getElementById("title").value,
      content: contentValue,
      work_detail: document.getElementById("work-detail").value,
      start_dt: document.getElementById("start-dt").value.replace("T", " "),
      end_dt: document.getElementById("end-dt").value.replace("T", " "),
      cost_type: document.getElementById("cost-type").value,
      serial_no: this.selectedRow.serial_no,
    };

    if (!data.title || !data.serial_no) {
      alert("제목을 입력하고 수정할 항목을 선택해주세요.");
      return;
    }

    const url = this.sys_mng_srv + `?category=update&user_id=${user_id}`;
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
          this.loadData();
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
    const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
    const serialNos = Array.from(checkboxes).map((cb) => cb.dataset.serialNo);

    if (serialNos.length === 0) {
      alert("삭제할 항목을 선택해주세요.");
      return;
    }

    if (!confirm(`선택한 ${serialNos.length}개의 항목을 삭제하시겠습니까?`)) {
      return;
    }

    const data = { serial_nos: serialNos };
    
    const url = this.sys_mng_srv + "?category=delete";
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
          alert("삭제되었습니다.");
          this.loadData();
        } else {
          alert("삭제 실패: " + (result.error || "알 수 없는 오류"));
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("서버 통신 중 오류가 발생했습니다.");
      });
  }

  // 이벤트 리스너 초기화
  initializeEventListeners() {
    const searchButton = document.getElementById("search-btn-sys");
    const insertButton = document.getElementById("insert-btn-sys");
    const updateButton = document.getElementById("update-btn-sys");
    const deleteButton = document.getElementById("delete-btn-sys");

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
}

window.sysMngClass = new SysMngClass();
