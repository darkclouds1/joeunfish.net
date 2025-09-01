// 할일 추가 버튼 클릭 이벤트
function addNewTask() {
    const taskHtml = `
    <div class="task-item">
      <div class="task-input-panel"> <!-- task-input-panel 클래스 추가 -->
        <div class="task-input-content">                 
          <input type="text"                      
            class="task-title-input"                      
            placeholder="제목"                      
            maxlength="100"
            onkeydown="if(event.key === 'Enter'){ event.preventDefault(); handleTitleKeydown(event, this);}">
          <textarea                      
            class="task-content-input"                     
            placeholder="내용"                     
            maxlength="500"                     
            rows="10"></textarea>
          <div class="task-footer-line">
            <input type="date" class="task-due-date">
          </div>
          <div class="task-footer-line">
            <button class="cancel-btn" onclick="cancelTaskInput(this)">취소</button>
            <button class="save-btn" onclick="event.preventDefault(); saveNewTask(this)">저장</button>
          </div>             
        </div>         
      </div>
    </div>`; 

  const taskList = document.getElementById("taskList");
  taskList.insertAdjacentHTML("afterbegin", taskHtml);

  // 현재 일시에서 1개월 후를 task-due-date input에 설정
  const dueDateInput = taskList.querySelector(".task-due-date");
  const now = new Date();
  now.setMonth(now.getMonth() + 1); // 1개월 추가

  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  dueDateInput.value = `${year}-${month}-${day}`;
}

function handleTitleKeydown(event, input) {
  if (event.key === 'Enter') {
    event.preventDefault(); // 기본 동작 방지
    const contentInput = input.closest('.task-input-content').querySelector('.task-content-input');
    contentInput.focus(); // 내용 입력으로 커서 이동
  }
}

async function saveNewTask(btn) {
  const panel = btn.closest(".task-input-panel");
  if (!panel) {
    console.error("Panel not found. Check the DOM structure.");
    return;
  }

  const titleInput = panel.querySelector(".task-title-input");
  const contentInput = panel.querySelector(".task-content-input");
  const dueDateInput = panel.querySelector(".task-due-date");

  // 제목 필수 입력 체크
  if (!titleInput.value.trim()) {
    alert("제목을 입력해주세요.");
    titleInput.focus();
    return;
  }

  const taskData = {
    title: titleInput.value,
    content: contentInput.value,
    due_date: dueDateInput.value,
    finish_yn: "n",
  };

  try {
    const url =
      "/wp-content/plugins/jofMain/tasks_srv.php?category=save&user_id=" +
      user_id;
    console.log("ln279:url=" + url);

    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(taskData),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    if (result.success) {
      console.log("저장되었습니다.");
      // 패널을 유지하면서 보기 모드로 전환
      // switchToViewMode(panel, result.serial_no, taskData);
      loadTasks();
    } else {
      console.log("저장 실패: " + result.error);
    }
  } catch (error) {
    console.error("저장 중 오류 발생:", error);
  }
}

function cancelTaskInput(btn) {
  const panel = btn.closest(".task-input-panel");
  panel.remove(); // 패널을 제거하지 않고, 초기 상태로 되돌리기
}
// 취소 버튼 클릭 시 편집 모드에서 보기 모드로 전환
function cancelTaskInput(btn) {
  const taskItem = btn.closest(".task-item");
  const serialNo = taskItem.getAttribute("data-serial");
  // 기존 내용을 유지하며 보기 모드로 전환
  switchToViewMode(taskItem, serialNo);
}


// 할일 목록 로드 함수
async function loadTasks() {
  console.log("ln107:user_id=" + user_id);

  try {
    url =
      "/wp-content/plugins/jofMain/tasks_srv.php?category=search&user_id=" +
      user_id;
    console.log("ln108:url=" + url);

    const response = await fetch(url);
    const result = await response.json();

    if (result.success) {
      const taskList = document.getElementById("taskList");
      taskList.innerHTML = ""; // 기존 목록 초기화

      result.data.forEach((task) => {
        const taskContent = (task.content || "").replace(/\n/g, "<br>"); // 줄바꿈을 <br>로 변환
        const dueDate = task.due_date ? new Date(task.due_date) : null;
        const formattedDate = dueDate ? `${dueDate.getFullYear()}-${String(dueDate.getMonth() + 1).padStart(2, '0')}-${String(dueDate.getDate()).padStart(2, '0')}` : "";

        const taskHtml = `
        <div class="task-item ${
          task.finish_yn === "y" ? "finished" : ""
        }" data-serial="${task.serial_no}">
            <div class="task-title-line">
                <input type="checkbox" ${
                  task.finish_yn === "y" ? "checked" : ""
                } 
                        onchange="updateTaskStatus(this, ${task.serial_no})">
                <span class="title ${
                  task.finish_yn === "y" ? "finished-text" : ""
                }"
                        onclick="switchToEditMode(this, ${task.serial_no})"
                        style="cursor: pointer;">${task.title}</span>
                ${
                  task.finish_yn === "y"
                    ? `<button type="button" class="delete-btn" onclick="handleDelete(${task.serial_no})">삭제</button>`
                    : ""
                }
            </div>
            <div class="${task.finish_yn === "y" ? "finished-text" : ""}">
                <div class="task-description">${taskContent}</div>
                <div class="task-footer">기한: ${formattedDate}
                    <span class="modify-info">${task.modify_name}</span>
                </div>
            </div>
        </div>
        <hr style="border: 1px solid #ddd; margin: 10px 0;">
    `;
        taskList.insertAdjacentHTML("beforeend", taskHtml);
      });
    } else {
      console.error("할일 목록 로드 실패:", result.error);
    }
  } catch (error) {
    console.error("할일 목록 로드 중 오류:", error);
  }
}

// 삭제 처리 함수
function handleDelete(serialNo) {
  console.log("Delete button clicked:", serialNo); // 디버깅용
  if (confirm("이 할일을 삭제하시겠습니까?")) {
    url =
      "/wp-content/plugins/jofMain/tasks_srv.php?category=delete&user_id=" +
      user_id;
    console.log("ln170:url=" + url);

    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        serial_no: serialNo,
        delete_yn: "y",
      }),
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          loadTasks();
        } else {
          alert("삭제 실패: " + result.error);
        }
      })
      .catch((error) => {
        alert("삭제 중 오류 발생: " + error);
      });
  }
}

// 할일 상태 업데이트 함수
async function updateTaskStatus(checkbox, serialNo) {
  url =
    "/wp-content/plugins/jofMain/tasks_srv.php?category=updatefinish&user_id=" +
    user_id;
  console.log("ln162:url=" + url);

  try {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        serial_no: serialNo,
        finish_yn: checkbox.checked ? "y" : "n",
      }),
    });

    const result = await response.json();
    if (result.success) {
      loadTasks(); // 목록 새로고침
    } else {
      alert("상태 변경 실패: " + result.error);
      checkbox.checked = !checkbox.checked; // 체크박스 상태 원복
    }
  } catch (error) {
    alert("상태 변경 중 오류 발생: " + error);
    checkbox.checked = !checkbox.checked; // 체크박스 상태 원복
  }
}

// 날짜 포맷 변환 함수
function formatDateForInput(dateStr) {
  if (!dateStr) return "";
  try {
    // 먼저 현재 형식(예: "2024. 12. 13. 오후 3:53:00")을 파싱
    const parts = dateStr.split(".");
    if (parts.length < 3) return ""; // 유효한 날짜 형식이 아닐 경우

    const year = parts[0].trim();
    const month = parts[1].trim().padStart(2, "0");
    const day = parts[2].trim().padStart(2, "0");

    // 시간 부분 처리
    let hours = "00",
      minutes = "00";
    if (parts[3]) {
      const timeParts = parts[3].trim().split(" ");
      if (timeParts.length >= 2) {
        const timeValues = timeParts[1].split(":");
        hours = parseInt(timeValues[0]);
        minutes = timeValues[1];

        // 오후인 경우 12시간 추가
        if (timeParts[0] === "오후" && hours < 12) {
          hours += 12;
        }
        // 오전 12시는 00시로 변환
        if (timeParts[0] === "오전" && hours === 12) {
          hours = 0;
        }

        hours = hours.toString().padStart(2, "0");
      }
    }

    return `${year}-${month}-${day}T${hours}:${minutes}`;
  } catch (error) {
    console.error("날짜 변환 오류:", error);
    return "";
  }
}

async function saveEditTask(btn, serialNo) {
  const panel = btn.closest(".task-input-content");
  const titleInput = panel.querySelector(".task-title-input");
  const contentInput = panel.querySelector(".task-content-input");
  const dueDateInput = panel.querySelector(".task-due-date");

  const taskData = {
    serial_no: serialNo,
    title: titleInput.value,
    content: contentInput.value,
    due_date: dueDateInput.value,
    finish_yn: panel.closest(".task-item")?.getAttribute("data-finish") || "n",
  };
  console.log("ln277:taskData=" + JSON.stringify(taskData));

  const url =
    "/wp-content/plugins/jofMain/tasks_srv.php?category=update&user_id=" +
    user_id;
  console.log("ln279:url=" + url);

  try {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(taskData),
    });

    const result = await response.json();
    if (result.success) {
      console.log("저장되었습니다.");
      // 저장된 내용을 반영하여 보기 모드로 전환
      switchToViewMode(panel.closest(".task-item"), serialNo, taskData);
    } else {
      console.log("저장 실패: " + result.error);
    }
  } catch (error) {
    console.log("저장 중 오류 발생: " + error);
  }
}

function switchToViewMode(taskItem, serialNo, taskData) {
  if (!taskData) {
    console.error("taskData is undefined");
    return;
  }

  const title = taskData.title;
  const content = taskData.content.replace(/\n/g, "<br>");
  const dueDate = taskData.due_date;

  const viewHtml = `
    <div class="task-title-line" style="margin-bottom: 5px;">
        <input type="checkbox" 
                onchange="updateTaskStatus(this, ${serialNo})">
        <span class="title"
                onclick="switchToEditMode(this, ${serialNo})"
                style="cursor: pointer; font-weight: bold;">${title}</span>
    </div>
    <div class="task-content" style="margin-top: 5px;">
        <div class="task-description">${content}</div>
    </div>
    <div class="task-footer" style="margin-top: 10px;">기한: ${dueDate}
        <span class="modify-info"></span>
    </div>
    <hr style="border: 1px solid #ddd; margin: 10px 0;">
  `;

  taskItem.innerHTML = viewHtml;
}

function switchToEditMode(titleSpan, serialNo) {
  const taskItem = titleSpan.closest(".task-item");
  const currentContent = taskItem.querySelector(".task-description").innerHTML.replace(/<br>/g, "\n");
  const footerText = taskItem.querySelector(".task-footer").textContent;

  // 날짜 파싱 및 포맷팅
  let formattedDate = "";
  try {
    const dateMatch = footerText.match(/기한:\s*([\d-]+)/);
    if (dateMatch && dateMatch[1]) {
      formattedDate = dateMatch[1].trim();
    }
  } catch (e) {
    console.error("날짜 파싱 오류:", e);
    formattedDate = "";
  }

  const editHtml = `
          <div class="task-input-content">
              <input type="text" 
                    class="task-title-input" 
                    placeholder="제목" 
                    maxlength="100" 
                    value="${titleSpan.textContent}"
                    onkeydown="if(event.key === 'Enter'){ event.preventDefault(); handleTitleKeydown(event, this);}">            
              <textarea class="task-content-input"
                        placeholder="내용"
                        maxlength="500"
                        rows="10">${currentContent}</textarea>
              <div class="task-footer-line">                     
                  <input type="date" class="task-due-date" value="${formattedDate}">
              </div>
              <div class="task-footer-line">
                  <button class="cancel-btn" onclick="cancelTaskInput(this)">취소</button>
                  <button class="save-btn" onclick="event.preventDefault(); saveEditTask(this, ${serialNo})">저장</button>
              </div>           
          </div> `;

  taskItem.innerHTML = editHtml;
}