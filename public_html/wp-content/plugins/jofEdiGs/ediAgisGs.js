function ediRcv_srv_job() {
  // 서버의 PHP 스크립트를 실행합니다.
  move();
  jQuery.ajax({
    url: "/wp-content/plugins/jofEdiGs/ediRcvGs_srv_job.php",
    type: "GET",
    success: function (data) {
      console.log("EDI 재적용 스크립트 실행 완료:", data);

      // 데이터 처리가 완료된 후 주문 조회를 자동으로 다시 실행합니다.
      setTimeout(function () {
        fetchOrders();
      }, 500);
    },
    error: function (xhr, status, error) {
      console.error("EDI 재적용 스크립트 실행 중 오류 발생:", error);
    },
  });
}

function showOrders() {
  var orderView = document.getElementById("orderView");
  var orderViewGSCT04 = document.getElementById("orderViewGSCT04");
  var codeView = document.getElementById("codeView");
  if (orderView && codeView) {
    orderView.style.display = "block";
    orderViewGSCT04.style.display = "none";
    codeView.style.display = "none";
  }
}

function showOrdersGSCT04() {
  var orderView = document.getElementById("orderView");
  var orderViewGSCT04 = document.getElementById("orderViewGSCT04");
  var codeView = document.getElementById("codeView");
  if (orderViewGSCT04 && codeView) {
    orderViewGSCT04.style.display = "block";
    orderView.style.display = "none";
    codeView.style.display = "none";
  }
}

function showCodeManagement() {
  var orderView = document.getElementById("orderView");
  var orderViewGSCT04 = document.getElementById("orderViewGSCT04");
  var codeView = document.getElementById("codeView");
  if (orderView && codeView) {
    codeView.style.display = "block";
    orderView.style.display = "none";
    orderViewGSCT04.style.display = "none";
  }

// 사용자가 편집자 또는 관리자인 경우에만 fetchDataForCode() 함수 실행
  console.log(user_roles);
  console.log(user_roles === "editor" || user_roles === "administrator");
  if (user_roles.includes("editor") || user_roles.includes("administrator")) {
    // 사용자가 편집자 또는 관리자인 경우 실행할 코드
    fetchDataForCode();
  }
}

function fetchOrders() {
  move();
  const arrivalDate = document.getElementById("arrivalDate").value;
  const isMobile = window.innerWidth <= 768; // 모바일 기기 확인

  const url = `/wp-content/plugins/jofEdiGs/ediAgisGs_srv.php?category=order&date=${arrivalDate}`;
  console.log("url=" + url);

  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      const totalCount = data.response.body.items.filter(item => 
        item.총수량 && 
        item.총수량 !== '0' && 
        item.총수량 !== ''
      ).length;
      

        document.getElementById("orderList").textContent = `주문목록` + ` (총 ${totalCount}개의 상품)`;

        if (isMobile) {
          // 모바일용 카드 스타일 레이아웃
          let output = '<div class="mobile-cards">';
          const items = data.response.body.items;

          items.forEach((item, index) => {
            console.log("총수량=" + item.총수량);

            if (item.총수량 !== '0' && item.총수량 !== '') {
              const backgroundColor = item.product_name.includes("연어") ? '#ffff00' : 'white';

              output += `
                <div class="card" style="margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px; background-color: ${backgroundColor}">
                  <div style="padding: 10px; background-color: #1E73BE; color: white; border-radius: 5px 5px 0 0;">
                    <div style="font-weight: bold; font-size: 16px;">${item.product_name}</div>
                    <div style="font-size: 14px;">${item.product_code}</div>
                  </div>
                  
                  <div style="padding: 10px;">
                    <!-- 주요 정보 박스 -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin-bottom: 10px; background-color: #f5f5f5; padding: 8px; border-radius: 4px;">
                      <div style="text-align: center;">
                        <div style="font-size: 12px; color: #666;">박스</div>
                        <div style="font-weight: bold;">${item.박스 || '-'}</div>
                      </div>
                      <div style="text-align: center;">
                        <div style="font-size: 12px; color: #666;">입수</div>
                        <div style="font-weight: bold;">${item.입수 || '-'}</div>
                      </div>
                      <div style="text-align: center;">
                        <div style="font-size: 12px; color: #666;">총수량</div>
                        <div style="font-weight: bold; color: red;">${item.총수량 || '-'}</div>
                      </div>
                    </div>

                    <!-- 나머지 정보들 -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">`;

              // 나머지 컬럼들 표시 (박스, 입수, 총수량 제외)
              Object.entries(item).forEach(([key, value]) => {
                if (!['product_name', 'product_code', '박스', '입수', '총수량'].includes(key) && value !== '0' && value !== '') {
                  output += `
                    <div style="background-color: #ffffff; padding: 5px; border: 1px solid #eee;">
                      <div style="font-size: 12px; color: #666;">${key}</div>
                      <div style="font-weight: bold; text-align: right;">${value}</div>
                    </div>`;
                }
              });
            }

            output += `
                  </div>
                </div>
              </div>`;
          });

          output += '</div>';
          document.getElementById("resultsContainer").innerHTML = output;
        } else {
          	// --------------------------
        // 컬럼 헤더 추가
        // --------------------------
        let output =
          '<table class="fixed-header" border="1" style="font-size: 13px; width: 100%; table-layout: fixed;"><thead><tr style="background-color: #d3d3d3; height: 40px;">';
        
        const keys = Object.keys(data.response.body.items[0]);
        const remainingWidth = `${(100 - ((150 * 2) / document.documentElement.clientWidth * 100)) / (keys.length - 2)}%`;  // 남은 너비를 균등 분배

        keys.forEach((key) => {
          if (key === "product_name" || key === "제품명") {
            output += `<th style="width: 200px;">제품명</th>`;
          } else if (key === "product_code") {
            output += `<th style="width: 130px;">제품코드</th>`;
          } else {
            output += `<th style="width: ${remainingWidth};">${key}</th>`;
          }
        });
        output += "</tr></thead><tbody>";

        // --------------------------
        // 데이터 행 추가
        // --------------------------
        const items = data.response.body.items;
        let rowColor = true;
        items.forEach((item) => {
          let color = item.product_name.includes("연어") ? "yellow" : (rowColor ? "white" : "#f2f2f2");
          
          output += `<tr style="background-color: ${color}">`;

          Object.keys(item).forEach((key) => {
            if (item[key].endsWith(".0")) {
              item[key] = item[key].slice(0, -2);
            }
            if (item[key] !== "0" && item[key] !== "") {
              if (key === "product_name" || key === "제품명") {
                output += `<td style="font-weight:bold; text-align: left;">${item[key]}</td>`;
              } else if (key === "총수량" && item[key] !== "0" && item[key] !== "") {
                output += `<td style="color:red; text-align: right;">${item[key]}</td>`;
              } else if (key === "product_code") {
                output += `<td style="text-align: center;">${item[key]}</td>`;
              } else {
                output += `<td style="text-align: right;">${item[key]}</td>`;
              }
            } else {
              output += `<td></td>`;
            }
          });
          output += "</tr>";
          rowColor = !rowColor;
        });
        output += "</tbody></table>";

        document.getElementById("resultsContainer").innerHTML = output;
        }

        window.hasDataLoaded = true;
    })
    .catch((error) => {
      console.error("Error fetching orders:", error);
    })
    .finally(() => {
      move_stop();
    });
}

// GS25물류센터터
function fetchOrdersGSCT04() {
  move();
  const arrivalDate = document.getElementById("arrivalDateGSCT04").value;
  const isMobile = window.innerWidth <= 768; // 모바일 기기 확인

  const url = `/wp-content/plugins/jofEdiGs/ediAgisGs_srv.php?category=orderGSCT04&date=${arrivalDate}`;
  console.log("url=" + url);

  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      if (data.result === "success") {
        const totalCount = data.data.length;
        if (totalCount === 0) {
          document.getElementById("orderListGSCT04").textContent = `주문목록` + ` (데이터가 없습니다)`;
          document.getElementById("resultsContainerGSCT04").innerHTML = "";
          return;
        }

        document.getElementById("orderListGSCT04").textContent = `주문목록`;
        document.getElementById("orderListGSCT04").textContent += ` (총 ${totalCount}개의 상품)`;

        if (isMobile || true) {
          // 모바일용 카드 스타일 레이아웃
          let output = '<div class="mobile-cards">';
          const items = data.data;

          items.forEach((item) => {
            const backgroundColor = (item.product_name === "연어필렛" || item.product_name === "연어(스테이크용)") ? '#ffff00' : 'white';

            // name, code, 총수량 제외 실제 표시 컬럼 개수 계산
            const displayedColumnCount = Object.entries(item).filter(
              ([key, value]) =>
                !['product_name', 'product_code', '총수량'].includes(key) && value !== '0' && value !== ''
            ).length;

            output += `
              <div class="card" style="margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px; background-color: ${backgroundColor}">
                <div style="padding: 10px; background-color: #1E73BE; color: white; border-radius: 5px 5px 0 0;">
                  <div style="font-weight: bold; font-size: 16px;">${item.product_name}</div>
                  <div style="font-size: 14px;">${item.product_code}</div>
                </div>
                
                <div style="padding: 10px;">
                  <!-- 주요 정보 박스 -->
                  <div style="display: flex; gap: 10px; margin-bottom: 10px; background-color: #f5f5f5; padding: 8px; border-radius: 4px;">
                    <div style="flex: 1; text-align: center; display: flex; flex-direction: column; justify-content: center;">
                      <div style="font-size: 12px; color: #666;">총합계</div>
                      <div style="font-weight: bold; color: red;">${item.총수량 !== '0' && item.총수량 !== '' ? item.총수량 : '-'}</div>
                    </div>
                    <div style="flex: 1; text-align: center; display: flex; flex-direction: column; justify-content: center;">
                      <div style="font-size: 15px; font-weight: bold; color: #1E73BE;">납품처: 총 ${displayedColumnCount}개</div>
                    </div>
                  </div>

                  <!-- 나머지 정보들 -->
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">
              `;

            // 나머지 컬럼들 표시 (product_name, product_code, 총수량 제외)
            Object.entries(item).forEach(([key, value]) => {
              if (!['product_name', 'product_code', '총수량'].includes(key) && value !== '0' && value !== '') {
                if (value.endsWith('.0')) {
                  value = value.slice(0, -2);
                }
                output += `
                  <div style="background-color: #ffffff; padding: 5px; border: 1px solid #eee;">
                    <div style="font-size: 12px; color: #666;">${key}</div>
                    <div style="font-weight: bold; text-align: right;">${value}</div>
                  </div>`;
              }
            });

            output += `
                </div>
              </div>
            </div>
          `;
          });

          output += '</div>';
          document.getElementById("resultsContainerGSCT04").innerHTML = output;

        // 사용하지 않음:: 데스크탑 테이블은 납품점이 매우 많아서 모바일 테이블과 동일하게 출력 - by dc 2025.06.03 
        } else { 
          // ... existing desktop table code ...
          let output =
            '<table class="fixed-header" border="1" style="font-size: 13px; width: 100%; table-layout: fixed;"><thead><tr style="background-color: #d3d3d3; height: 60px;">';
          
          // ... rest of the existing desktop code ...
          Object.keys(data.data[0]).forEach((key) => {
            if (key === "product_name") {
              const dateOptions = {
                year: "numeric",
                month: "long",
                day: "numeric",
                weekday: "long",
              };
              output += `<th style="width: 130px;">제품명</th>`;
            } else if (key === "product_code") {
              output += `<th style="width: 130px;">제품코드</th>`;
            } else {
              output += `<th>${key}</th>`;
            }
          });
          output += "</tr></thead><tbody>";

          const items = data.data;
          let rowColor = true;

          items.forEach((item) => {
            let color = (item.product_name === "연어필렛" || item.product_name === "연어(스테이크용)") ? "yellow" : (rowColor ? "white" : "#f2f2f2");
            output += `<tr style="background-color: ${color}">`;

            Object.keys(item).forEach((key) => {
              if (item[key].endsWith(".0")) {
                item[key] = item[key].slice(0, -2);
              }

              if (item[key] !== "0") {
                if (key === "product_name") {
                  output += `<td style="font-weight:bold; text-align: center;">${item[key]}</td>`;
                } else if (key === "총합계") {
                  output += `<td style="color:red; text-align: right;">${item[key]}</td>`;
                } else if (key === "product_code") {
                  output += `<td style="text-align: center;">${item[key]}</td>`;
                } else {
                  output += `<td style="text-align: right;">${item[key]}</td>`;
                }
              } else {
                output += `<td></td>`;
              }
            });
            output += "</tr>";
            rowColor = !rowColor;
          });
          output += "</tbody></table>";

          document.getElementById("resultsContainerGSCT04").innerHTML = output;
        }
        window.hasDataLoaded = true;
      } else {
        console.error("Unexpected data structure:", data);
      }
    })
    .catch((error) => {
      console.error("Error fetching orders:", error);
    })
    .finally(() => {
      move_stop();
    });
}

function downloadExcel() {
  if (!window.hasDataLoaded) {
    alert("조회 후 엑셀다운로드 하시기 바랍니다.");
    return;
  }
  const table = document.querySelector("#resultsContainer table");
  const workbook = XLSX.utils.table_to_book(table, {
    sheet: "Sheet1",
  });

  const ws = workbook.Sheets.Sheet1;
  XLSX.utils.sheet_add_aoa(ws, [[""]], {
    origin: -1,
  }); // 빈 행 추가

  // 제품코드와 제품명을 문자로 저장
  const range = XLSX.utils.decode_range(ws["!ref"]);
  for (let R = range.s.r; R <= range.e.r; ++R) {
    for (let C = range.s.c; C <= range.e.c; ++C) {
      const cell_address = {
        c: C,
        r: R,
      };
      const cell_ref = XLSX.utils.encode_cell(cell_address);
      const cell = ws[cell_ref];
      if (cell && (C === 0 || C === 1)) {
        // Assuming 제품코드 is in column 0 and 제품명 is in column 1
        cell.t = "s"; // 데이터 타입을 문자열로 설정
        cell.z = "@"; // Set cell format to text
      }
    }
  }

  // 컬럼 width를 데이터가 전부 보이게 자동 조정
  const colWidths = [];
  for (let C = range.s.c; C <= range.e.c; ++C) {
    let maxWidth = 10; // 기본 최소 너비 설정
    for (let R = range.s.r; R <= range.e.r; ++R) {
      const cell_address = {
        c: C,
        r: R,
      };
      const cell_ref = XLSX.utils.encode_cell(cell_address);
      const cell = ws[cell_ref];
      if (cell && cell.v) {
        const cellValue = cell.v.toString();
        maxWidth = Math.max(maxWidth, cellValue.length);
      }
    }
    colWidths.push({
      wch: maxWidth,
    });
  }
  ws["!cols"] = colWidths;

  const wbout = XLSX.write(workbook, {
    bookType: "xlsx",
    bookSST: true,
    type: "binary",
  });

  function s2ab(s) {
    const buf = new ArrayBuffer(s.length);
    const view = new Uint8Array(buf);
    for (let i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xff;
    return buf;
  }

  saveAs(
    new Blob([s2ab(wbout)], {
      type: "application/octet-stream",
    }),
    "주문목록_" + document.getElementById("arrivalDate").value + ".xlsx"
  );
}

function fetchDataForCode() {
  fetch("/wp-content/plugins/jofEdiGs/ediAgisGs_srv.php?category=comSearch")
    .then((response) => response.json())
    .then((data) => {
      // console.log('코드조회 데이터:', data);
      const codeResultsContainer = document.getElementById("codeResultsContainer");
      codeResultsContainer.style.position = "relative"; // 컨테이너를 relative로 설정
      codeResultsContainer.style.overflow = "visible"; // 스크롤 가능하게 설정을 visible로 변경

      let output = '<div style="overflow: auto;">'; // 스크롤을 위한 div 추가
      output += '<table border="1" style="font-size: 12px; width: 100%;">';
      output += "<thead>"; // 헤더 고정
      output += "<tr>";
      Object.keys(data.response.body.items[0]).forEach((key) => {
        output += `<th>${key}</th>`;
      });
      output += `<th>수정</th>`;
      output += `<th>삭제</th>`;
      output += `<th>추가</th>`;
      output += "</tr></thead><tbody >";

      window.items = data.response.body.items;

      let rowColor = true;

      items.forEach((item, index) => {
        let color = rowColor ? "white" : "#f2f2f2";
        output += `<tr id="row-${index}" style="background-color: ${color}">`;

        Object.keys(item).forEach((key) => {
          output += `<td style="text-align: left;">${item[key]}</td>`;
        });

        output += `<td><a href="#" onclick="editRow(${index}); return false;">수정</a></td>`;
        output += `<td><a href="#" onclick="deleteRow(${index}); return false;">삭제</a></td>`;
        output += `<td><a href="#" onclick="addRowBelow(${index}); return false;">추가</a></td>`;
        output += "</tr>";
        rowColor = !rowColor;
      });

      output += "</tbody></table>";
      codeResultsContainer.innerHTML = output;
    })
    .catch((error) => {
      console.error("코드조회 중 오류 발생:", error);
    });
}

window.addRowBelow = function (index) {
  const table = document.getElementById("codeResultsContainer").querySelector("table");
  const newRow = table.insertRow(index + 2);

  const item = items[index];
  let output = `<tr style="background-color: white">`;

  Object.keys(item).forEach((key) => {
    if (key === "순번") {
      output += `<td></td>`;
    } else {
      const cell = document.getElementById(`row-${index}`).querySelectorAll("td")[Object.keys(item).indexOf(key)];
      const cellWidth = cell.offsetWidth;
      const cellStyle = window.getComputedStyle(cell);
      const padding = parseInt(cellStyle.paddingLeft) + parseInt(cellStyle.paddingRight);
      const adjustedWidth = cellWidth - padding;
      output += `<td><input type='text' name='${key}' value='${item[key]}' style='width: ${adjustedWidth}px; font-size: 12px; margin: 0; padding: 2px;'></td>`;
    }
  });

  // 저장 및 취소 링크를 추가합니다.
  output += `<td><a href="#" onclick='saveNewRow(this); return false;'>저장</a></td>`;
  output += `<td><a href="#" onclick='cancelRow(this); return false;'>취소</a></td>`;
  output += `<td></td>`; // 이 부분이 누락되어 있었습니다.
  output += `</tr>`;

  newRow.innerHTML = output;
};

window.cancelRow = function (element) {
  const row = element.closest("tr");
  row.remove();
};

window.editRow = function (index) {
  console.log("수정 모드:", index);
  const tableRow = document.getElementById(`row-${index}`);
  const cells = tableRow.querySelectorAll("td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))");

  cells.forEach((cell, idx) => {
    const key = Object.keys(items[index])[idx];
    const text = cell.innerText;
    const cellWidth = cell.offsetWidth;
    if (key !== "순번") {
      cell.innerHTML = `<input type='text' name='${key}' value='${text}' style='width: ${cellWidth}px; font-size: 12px;'>`;
    }
  });

  const editButton = tableRow.querySelector("td:nth-last-child(3) a");
  editButton.innerText = "저장";
  editButton.onclick = function () {
    saveRow(index);
  };

  const cancelButton = tableRow.querySelector("td:nth-last-child(2) a");
  cancelButton.innerText = "취소";
  cancelButton.onclick = function () {
    cancelEditRow(index);
  };

  const addButton = tableRow.querySelector("td:last-child a");
  addButton.style.display = "none"; // 수정 중에는 추가 버튼을 숨깁니다.
};

window.cancelEditRow = function (index) {
  const tableRow = document.getElementById(`row-${index}`);
  const cells = tableRow.querySelectorAll("td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))");

  cells.forEach((cell, idx) => {
    const key = Object.keys(items[index])[idx];
    cell.innerHTML = items[index][key];
  });

  const editButton = tableRow.querySelector("td:nth-last-child(3) a");
  editButton.innerText = "수정";
  editButton.onclick = function () {
    editRow(index);
  };

  const deleteButton = tableRow.querySelector("td:nth-last-child(2) a");
  deleteButton.innerText = "삭제";
  deleteButton.onclick = function () {
    deleteRow(index);
  };

  const addButton = tableRow.querySelector("td:last-child a");
  addButton.style.display = "inline"; // 취소 후 추가 버튼을 다시 표시합니다.
};

window.saveRow = function (index) {
  const tableRow = document.querySelector(`#row-${index}`);
  const inputs = tableRow.querySelectorAll("input");
  const firstCellText = tableRow.querySelector("td:first-child").innerText;
  const data = [firstCellText, ...Array.from(inputs).map((input) => input.value)];

  fetch("/wp-content/plugins/jofEdiGs/ediAgisGs_srv.php?category=update&user_id=" + encodeURIComponent(user_id), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((json) => {
      if (json.result === "success") {
        alert("데이터가 성공적으로 저장되었습니다.");
      } else {
        alert("저장 중 오류가 발생했습니다: " + json.message);
      }
    })
    .then((json) => console.log("서버 응답:", json));

  inputs.forEach((input, idx) => {
    const cell = input.parentElement;
    cell.innerText = input.value;
  });

  const saveButton = tableRow.querySelector("td:nth-last-child(3) a");
  saveButton.innerText = "수정";
  saveButton.onclick = function () {
    editRow(index);
  };

  const cancelButton = tableRow.querySelector("td:nth-last-child(2) a");
  cancelButton.innerText = "삭제";
  cancelButton.onclick = function () {
    deleteRow(index);
  };

  const addButton = tableRow.querySelector("td:last-child a");
  addButton.style.display = "inline"; // 저장 후 추가 버튼을 다시 표시합니다.
};

window.saveNewRow = function (element) {
  const row = element.closest("tr");
  const inputs = row.querySelectorAll("input");
  const data = [];

  data.push(inputs[0].value); // 그룹코드
  data.push(inputs[1].value); // 그룹코드명
  data.push(inputs[2].value); // 코드
  data.push(inputs[3].value); // 코드 설명
  data.push(inputs[4].value); // 단위
  data.push(inputs[5].value); // 세율
  data.push(inputs[6].value); // 원산지
  data.push(inputs[7].value); // 순서
  data.push(inputs[8].value); // 사용여부
  data.push(inputs[9].value); // 유통기한
  data.push(inputs[10].value); // 상품/납품처코드

  // 여기서 fetch 요청을 보내고 처리합니다.
  // console.log(data); // 데이터 확인을 위한 로그

  fetch("/wp-content/plugins/jofEdiGs/ediAgisGs_srv.php?category=insert&user_id=" + encodeURIComponent(user_id), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      data: data,
    }),
  })
    .then((response) => response.json())
    .then((json) => {
      if (json.result === "success") {
        alert("데이터가 성공적으로 저장되었습니다.");
        fetchDataForCode(); // 저장 후 주문조회를 실행합니다.
      } else {
        alert("저장 중 오류가 발생했습니다: " + json.message);
      }
    })
    .then((json) => console.log("서버 응답:", json));

  inputs.forEach((input, idx) => {
    const cell = input.parentElement;
    cell.innerText = input.value;
  });

  const saveButton = row.querySelector("td:nth-last-child(3) a");
  saveButton.innerText = "수정";
  saveButton.onclick = function () {
    editRow(rowId);
  };

  const cancelButton = row.querySelector("td:nth-last-child(2) a");
  cancelButton.innerText = "삭제";
  cancelButton.onclick = function () {
    deleteRow(rowId);
  };

  const addButton = row.querySelector("td:last-child a");
  // addButton.style.display = "inline"; // 저장 후 추가 버튼을 다시 표시합니다.
};

window.deleteRow = function (index) {
  if (
    confirm("해당 코드를 삭제하시겠습니까? 삭제 후에는 복구가 불가능합니다.")
  ) {
    const tableRow = document.getElementById(`row-${index}`);
    const firstColumn = tableRow.querySelector("td:first-child");
    const serialNo = firstColumn.innerText;

    fetch(
      `/wp-content/plugins/jofEdiGs/ediAgisGs_srv.php?category=delete&serial_no=${encodeURIComponent(
        serialNo
      )}`
    )
      .then((response) => response.json())
      .then((json) => {
        if (json.result === "success") {
          tableRow.remove();
          alert("삭제가 성공적으로 완료되었습니다.");
        } else {
          alert("삭제 중 오류가 발생했습니다: " + json.message);
        }
      })
      .catch((error) => {
        console.error("오류 발생:", error);
        alert("서버와의 통신 중 오류가 발생했습니다.");
      });
  }
  move_stop();
};

function addCodeManagement() {
  document.getElementById("addCodeForm").style.display = "block";
}

function cancelCodeForm() {
  document.getElementById("addCodeForm").style.display = "none";
}
