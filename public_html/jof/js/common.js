// common.js	2019/02/23
// <script src="/js/common.js?version=20190203"></script>

//  재고조회 서버 주소
if (typeof barcodeOut_srv === 'undefined') {
    var barcodeOut_srv = '/wp-content/plugins/barcodeOut/barcodeOut_srv.php';
}
String.prototype.string = function (len) {
  var s = "",
    i = 0;
  while (i++ < len) {
    s += this;
  }
  return s;
};
String.prototype.zf = function (len) {
  return "0".string(len - this.length) + this;
};
Number.prototype.zf = function (len) {
  return this.toString().zf(len);
};

function number_format(amount) {
  if (amount == 0) return 0;

  var reg = /(^[+-]?\d+)(\d{3})/;
  var n = amount + "";

  while (reg.test(n)) n = n.replace(reg, "$1" + "," + "$2");

  return n;
}

function dateAddDel(sDate, nNum, type) {
  var yy = eval(sDate.substr(0, 4)); //, 10);
  var mm = eval(sDate.substr(5, 2)); //parseInt(sDate.substr(5, 2), 10);
  var dd = eval(sDate.substr(8)); //parseInt(sDate.substr(8), 10);
  var d = new Date();
  if (type == "d") {
    d = new Date(yy, mm - 1, dd + nNum);
  } else if (type == "w") {
    nNum = 7 * nNum;
    d = new Date(yy, mm - 1, dd + nNum);
  } else if (type == "m") {
    d = new Date(yy, mm - 1 + nNum, dd);
  } else if (type == "y") {
    d = new Date(yy + nNum, mm - 1, dd);
  }

  yy = d.getFullYear();
  mm = d.getMonth() + 1;
  mm = mm < 10 ? "0" + mm : mm;
  dd = d.getDate();
  dd = dd < 10 ? "0" + dd : dd;

  return "" + yy + "-" + mm + "-" + dd;
}

function getToday(sep) {
  var today = new Date();
  var dd = today.getDate();
  var mm = today.getMonth() + 1; //January is 0!
  var yyyy = today.getFullYear();

  if (dd < 10) {
    dd = "0" + dd;
  }

  if (mm < 10) {
    mm = "0" + mm;
  }

  today = yyyy + sep + mm + sep + dd;
  return today;
}
function getTodayTime(sep) {
  var sep2 = ":";
  var today = new Date();
  var dd = today.getDate();
  var mm = today.getMonth() + 1; //January is 0!
  var yyyy = today.getFullYear();
  var hh = today.getHours();
  var mi = today.getMinutes();
  var ss = today.getSeconds();

  if (dd < 10) {
    dd = "0" + dd;
  }

  if (mm < 10) {
    mm = "0" + mm;
  }
  if (hh < 10) {
    hh = "0" + hh;
  }

  if (mi < 10) {
    mi = "0" + mi;
  }
  if (ss < 10) {
    ss = "0" + ss;
  }

  today = yyyy + sep + mm + sep + dd + " " + hh + sep2 + mi + sep2 + ss;
  return today;
}

// yyyy-mm-dd HH:MM:SS
function getCurrentDateTime() {
  const now = new Date();
  const year = now.getFullYear();
  const month = (now.getMonth() + 1).toString().padStart(2, "0"); // getMonth()는 0부터 시작
  const day = now.getDate().toString().padStart(2, "0");
  const hours = now.getHours().toString().padStart(2, "0");
  const minutes = now.getMinutes().toString().padStart(2, "0");
  const seconds = now.getSeconds().toString().padStart(2, "0");

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

/* -------------------------------------------------------------------------
  new Version of get ajax : 20180728 HMJ
-------------------------------------------------------------------------- */
function getAjax(server, client) {
  console.log("[commonjs::getAjax]server=" + server);

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

  // POST 메소드를 사용하고, 비동기로 설정합니다.
  xhttp.open("POST", server, true);

  // 적절한 헤더를 설정합니다. 폼 데이터를 전송한다고 서버에 알려줍니다.
  xhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

  // 준비된 데이터를 전송합니다.
  xhttp.send(params.toString());
}

// progress bar
function move() {
  var loading = document.getElementById("loading");
  if (!loading) {
    loading = document.createElement("div");
    loading.id = "loading";
    loading.style.position = "fixed";
    loading.style.top = "0";
    loading.style.left = "0";
    loading.style.width = "100vw";
    loading.style.height = "100vh";
    loading.style.background = "rgba(0,0,0,0.3)";
    loading.style.display = "flex";
    loading.style.justifyContent = "center";
    loading.style.alignItems = "center";
    loading.style.zIndex = "9999";
    loading.style.pointerEvents = "none";
    loading.innerHTML = `
      <div style='background:#fff;padding:20px 40px;border-radius:8px;font-size:20px;display:flex;flex-direction:column;align-items:center;color:#222;'>
        <img src="/img/loading/0.gif" alt="로딩중">
        로딩중...
      </div>
    `;
    document.body.appendChild(loading);
  }
  loading.style.display = "flex";
}

function move_stop() {
  var loading = document.getElementById("loading");
  if (loading) {
    loading.style.display = "none";
  }
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

// ------------------------
// head sorting
// ------------------------
var sortreplace = null;
function setSort() {
  const insTable = document.getElementById("specData");
  sortreplace = replacement(insTable);
  console.log("Sorting set");
}

function setSortId(tableId) {
  const insTable = document.getElementById(tableId);
  sortreplace = replacement(insTable);
  console.log("Sorting Id set");
}

function sortTD(index) {
  console.log("Sorting ascending on column:", index);
  const table = document.getElementById("specData");
  if (!table) {
    console.error("Table not found");
    return;
  }

  const sorter = replacement(table);
  if (!sorter || !sorter.ascending) {
    console.error("Sorter not initialized or ascending method missing");
    return;
  }
  sorter.ascending(index);
}

function reverseTD(index) {
  console.log("Sorting descending on column:", index);
  const table = document.getElementById("specData");
  if (!table) {
    console.error("Table not found");
    return;
  }

  const sorter = replacement(table);
  if (!sorter || !sorter.descending) {
    console.error("Sorter not initialized or descending method missing");
    return;
  }

  sorter.descending(index);
}

// 재고조회
function fetchInventoryData() {
  $.ajax({
    url: barcodeOut_srv + "?category=c3",
    type: "GET",
    dataType: "json",
    success: function (data) {
      // console.log(data);
      var formattedResult = "[재고]연어:" + data.cnt_fillets + ", 스테이크:" +  data.cnt_steaks + ", 벌크:" + data.cnt_steaks_bulk + ", 완출:" +  data.cnt_wanchul;
      $("#joeun_cnt").val(formattedResult);
    },
    error: function (xhr, status, error) {
      console.error("An error occurred: " + error);
    },
  });
}

// 키보드 이벤트 바인딩
function bindTableEvents() {
  console.log("Binding table events");
  $(document)
    .off("keydown", "#specData input")
    .on("keydown", "#specData input", function (e) {
      console.log("Keydown event triggered");
      if (e.key === "Enter") {
        e.preventDefault();
        var $inputs = $("#specData").find("input");
        var nextIndex = $inputs.index(this) + 1;
        if (nextIndex < $inputs.length) {
          $inputs.eq(nextIndex).focus();
        } else {
          $inputs.eq(0).focus();
        }
      }
    });
}

// bl번호 수동입력 추가되는 JavaScript 함수들
function handleSelect(select) {
    if (select.value === "") {
        let input = prompt("BL번호를 입력하세요 (4자리 숫자 또는 4자리+영문자 B/E):", "");
        if (input) {
            // 영문자와 숫자만 추출, 대문자로 변환
            input = input.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();

            // 4자리 숫자 또는 4자리 숫자+B/E만 허용
            if (/^\d{4}$/.test(input) || /^\d{4}[BE]$/.test(input)) {
                let newOption = new Option(input, input);
                select.add(newOption);
                select.value = input;
            } else {
                alert("형식이 올바르지 않습니다. 4자리 숫자 또는 4자리 숫자+영문자 B/E만 입력하세요. 예) 1234, 1234B, 1234E");
            }
        }
    }
}

