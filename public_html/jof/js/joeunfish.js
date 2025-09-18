var rtnCnt = 0; // 가져온 Data갯수
var idx = 0; // tables Row 갯수
var parm = ""; // kwd

function timeout(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

//  저장 항목4개: barcode1, 중량, barcode2, date
var totalWeight = 0;
var barcodeList = []; // 목록 데이터를 저장할 배열

/* -------------------------------------------------------------------------
  new Version of get ajax : 20180728 HMJ
-------------------------------------------------------------------------- */
function getAjax(server, client) {
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
      client(this.responseText);
    } else if (this.status == 504) {
      move_stop();
      alert("Time-out Error...");
      return;
    }
  };

  // URLSearchParams를 사용하여 POST 요청에 포함할 데이터를 생성합니다.
  // 이는 'application/x-www-form-urlencoded' 형식의 데이터를 생성합니다.
  var params = new URLSearchParams();
  params.append("category", category);
  // 기타 필요한 파라미터를 여기에 추가합니다.
  // 예: params.append('key', 'value');

  // POST 메소드를 사용하고, 비동기로 설정합니다.
  xhttp.open("POST", server, true);

  // 적절한 헤더를 설정합니다. 폼 데이터를 전송한다고 서버에 알려줍니다.
  xhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

  // 준비된 데이터를 전송합니다.
  xhttp.send(params.toString());
}

function openButton(idx) {
  if (document.getElementById("link" + idx).style.display == "inline") {
    document.getElementById("link" + idx).style.display = "none";
  } else document.getElementById("link" + idx).style.display = "inline";
}

// li 선택 시 색깔
function toggleSelection(liElement) {
  // 'li-selected' 클래스의 존재 여부를 확인하고, 있으면 제거하고 없으면 추가
  liElement.classList.toggle("li-selected");
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

function playBeep() {
  var context = new (window.AudioContext || window.webkitAudioContext)();
  var oscillator = context.createOscillator();
  oscillator.type = "sine"; // 사인파
  oscillator.frequency.setValueAtTime(440, context.currentTime); // 440Hz 주파수 설정
  oscillator.connect(context.destination);
  oscillator.start();
  oscillator.stop(context.currentTime + 1); // 1초 후에 정지
}

function playErrorSound() {
  var audio = new Audio("/jof/esset/error-sound3.mp3"); // MP3 파일 경로
  audio.play();
}

// 할일 체크
function handleCheck(checkbox) {
  if (checkbox.checked) {
    console.log("체크됨");
    // 체크됐을 때의 동작
  } else {
    console.log("체크 해제됨");
    // 체크 해제됐을 때의 동작
  }
}

// 할일 추가
function addTask() {
  var task = document.getElementById("taskInput").value;
  var taskList = document.getElementById("taskList");
  taskList.appendChild(task);
}

// 할일 삭제
function deleteTask() {
  var taskList = document.getElementById("taskList");
  taskList.removeChild(taskList.lastChild);
}

