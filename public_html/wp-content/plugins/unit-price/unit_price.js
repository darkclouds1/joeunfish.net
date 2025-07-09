jQuery(document).ready(function ($) {
  var $grid = $("#unit-price-grid");
  var $matGrid = $("#material-grid");
  var isDataLoaded = false;

  loadUnitPrices();

  $("#check-unit-price").on("click", function () {
      $matGrid.hide();
      $grid.show();
  });

  $("#check-material").on("click", function () {
    $grid.hide();
    loadMatPrices();
    $matGrid.show();
  });

  // 단가 관리
  function loadUnitPrices() {
    setTimeout(function() {
        move();
    }, 200);

    var url =
      "/wp-content/plugins/unit-price/unit_price_srv.php?category=price";
    console.log("[ln21]::url= " + url);
    $.ajax({
      url: url,
      type: "POST",
      success: function (response) {
        if (response.success) {
          displayGrid(response.data);
          isDataLoaded = true;
          $grid.show();
        } else {
          alert("데이터를 가져오는데 실패했습니다.");
        }
      },
      error: function () {
        alert("서버 오류가 발생했습니다.");
      },
    });

    setTimeout(() => {
        move_stop();
    }, 0);

  }

  // 재료 단가 관리
  function loadMatPrices() {

    var url =
      "/wp-content/plugins/unit-price/unit_price_srv.php?category=matprice";
    console.log("[ln21]::url= " + url);
    $.ajax({
      url: url,
      type: "POST",
      success: function (response) {
        if (response.success) {
          displayMatGrid(response.data);
          isDataLoaded = true;
          $matGrid.show();
        } else {
          alert("데이터를 가져오는데 실패했습니다.");
        }
      },
      error: function () {
        alert("서버 오류가 발생했습니다.");
      },
    });

  }


  function displayGrid(data) {
    var today = new Date().toISOString().split("T")[0];
    var html = "";
    var isMobile = window.innerWidth < 768;

    if (!isMobile) {
      html +=
        "<style>" +
        ".upc-table { width: 100% !important; border-collapse: collapse !important; table-layout: fixed !important; }" +
        ".upc-table th, .upc-table td { border: 1px solid #ddd !important; padding: 8px !important; height: 40px !important; }" +
        ".upc-table th { background-color: #1a1a1a !important; color: white !important; }" +
        ".upc-table td { word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; vertical-align: middle !important; }" +
        ".upc-table tr { height: 40px !important; }" +
        ".upc-ingredients { display: block !important; max-width: 100% !important; word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; white-space: normal !important; }" +
        "</style>";

      html +=
        "<table class='upc-table'>" +
        "<thead>" +
        "<tr>" +
        "<th style='width: 5%;'>순번</th>" +
        "<th style='width: 10%;'>제품코드</th>" +
        "<th style='width: 20%;'>제품명</th>" +
        "<th style='width: 40%;'>원재료 및 함량</th>" +
        "<th style='width: 10%;'>단가</th>" +
        "<th style='width: 15%;'>공급일</th>" +
        "</tr>" +
        "</thead>" +
        "<tbody>";

      $.each(data, function (i, item) {
        var rowColor = "";
        var yellowBoxCodes = ["0200000651343", "2000000560770", '2000000668592'];
        if (yellowBoxCodes.includes(item["제품코드"])) {
          rowColor = "background-color: yellow;";
        } 

        html +=
          "<tr style='" +
          rowColor +
          "' class='clickable-row' data-original-style='" +
          rowColor +
          "'>";
        html +=
          "<td style='text-align: center !important;'>" + (i + 1) + "</td>";
        html +=
          "<td style='text-align: center !important;'>" +
          item["제품코드"] +
          "</td>";
        html +=
          "<td style='text-align: left !important;'><div style='word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; white-space: normal !important;'>" +
          item["제품명"] +
          "</div></td>";
        html +=
          "<td style='text-align: left !important;'><div class='upc-ingredients' style='word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; white-space: normal !important;'>" +
          item["원재료"] +
          "</div></td>";
        
        // HTML 생성 부분
        html +=
            "<td style='text-align: right !important; color: red; cursor: pointer;' class='price-history-trigger' " +
            "data-prod-cd='" + item["제품코드"] + "' " +
            "data-prod-name='" + item["제품명"] + "' " +  // 제품명 속성 추가
            ">" +
            numberWithCommas(item["단가"]) +
            "</td>";
          
        html +=
          "<td style='text-align: center !important;'>" +
          item["공급일"] +
          "</td>";
        html += "</tr>";
      });

      html += "</tbody></table>";
    } else {
      // 모바일 버전

      html += "<div class='mobile-grid'>";
      $.each(data, function (i, item) {
        var baseColor = i % 2 === 0 ? '#ffffff' : '#f0f0f0';  // 기본 줄무늬 색상
        var rowColor = baseColor;

        // 특수 조건 (노란색 박스, 지난 날짜) 처리
        var yellowBoxCodes = ["0200000651343", "2000000560770", '2000000668592'];
        if (yellowBoxCodes.includes(item["제품코드"])) {
            rowColor = "yellow";
        } else if (item["공급일"] < today) {
            rowColor = "#f0f0f0";
        }

        html += `<div class='product-box clickable-row' 
                      data-original-style='background-color: ${rowColor}' 
                      style='background-color: ${rowColor}'>`;
        
        html +=
          "<div class='product-info'><span>순번: " +
          (i + 1) +
          "</span><span>코드: " +
          item["제품코드"] +
          "</span><span>공급일: " +
          item["공급일"] +
          "</span></div>";
        html +=
          "<div class='product-info' style='color: blue;'>" +
          item["제품명"] +
          "</div>";
        
        // 단가 클릭 시 단가 이력 표시
        html +=
            "<div class='product-info price-history-trigger' " +
            "data-prod-cd='" + item["제품코드"] + "' " +
            "data-prod-name='" + item["제품명"] + "' " +
            "style='color: red; cursor: pointer;'>" +
            "<span>단가: " + numberWithCommas(item["단가"]) + "</span>" +
            "</div>";

        html +=
          "<div class='product-info'><span>원재료: " +
          item["원재료"] +
          "</span></div>";
        html += "</div>";
      });
      html += "</div>";
      
      // CSS 스타일 추가
      html += `
      <style>
          .mobile-grid .product-box {
              padding: 10px;
              border-bottom: 1px solid #ddd;
              transition: background-color 0.3s;
          }
          .mobile-grid .product-box.selected {
              background-color: #BBF731 !important;
          }
          .mobile-grid .product-box:hover {
              background-color: #E0F7FA;
          }
      </style>
      `;

    }


    $grid.html(html);

    // 모바일에서 전화 링크 방지
    if (isMobile) {
      $(".mobile-price").each(function () {
        $(this).attr("style", "color: red; text-decoration: none;");
        $(this).parent().attr("style", "pointer-events: none;");
      });
    }

    // 마우스 오버 및 클릭 이벤트 추가
    $(".clickable-row").on({
      mouseenter: function () {
        $(this).css("background-color", "#E0F7FA");
      },
      mouseleave: function () {
        if (!$(this).hasClass("selected")) {
          $(this).attr("style", $(this).data("original-style"));
        }
      },
      click: function () {
        $(".clickable-row")
          .removeClass("selected")
          .each(function () {
            $(this).attr("style", $(this).data("original-style"));
          });
        $(this).addClass("selected");
        var originalStyle = $(this).data("original-style");
        $(this).attr("style", originalStyle + " background-color: #BBF731;");
      },
    });

    setTimeout(() => {
        move_stop();
    }, 0);

    addPriceHistoryListeners();
  }

  // 재료 단가 관리
  function displayMatGrid(data) {
    var today = new Date().toISOString().split("T")[0];
    var html = "";
    var isMobile = window.innerWidth < 768;

    if (!isMobile) {
      html +=
        "<style>" +
        ".upc-table { width: 100% !important; border-collapse: collapse !important; table-layout: fixed !important; }" +
        ".upc-table th, .upc-table td { border: 1px solid #ddd !important; padding: 8px !important; height: 40px !important; }" +
        ".upc-table th { background-color: #1a1a1a !important; color: white !important; }" +
        ".upc-table td { word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; vertical-align: middle !important; }" +
        ".upc-table tr { height: 40px !important; }" +
        ".upc-ingredients { display: block !important; max-width: 100% !important; word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; white-space: normal !important; }" +
        "</style>";

      html +=
        "<table class='upc-table'>" +
        "<thead>" +
        "<tr>" +
        "<th style='width: 5%;'>순번</th>" +
        "<th style='width: 10%;'>자재코드</th>" +
        "<th style='width: 20%;'>자재명</th>" +
        "<th style='width: 40%;'>lot명</th>" +
        "<th style='width: 10%;'>단가</th>" +
        "<th style='width: 15%;'>매입일일</th>" +
        "</tr>" +
        "</thead>" +
        "<tbody>";

      $.each(data, function (i, item) {
        var rowColor = "";
        var yellowBoxCodes = ["M020", "M021", "M022", "M023", "M024"];
        if (yellowBoxCodes.includes(item["자재코드"])) {
          rowColor = "background-color: yellow;";
        } 

        html +=
          "<tr style='" +
          rowColor +
          "' class='clickable-row' data-original-style='" +
          rowColor +
          "'>";
        html +=
          "<td style='text-align: center !important;'>" + (i + 1) + "</td>";
        html +=
          "<td style='text-align: center !important;'>" +
          item["자재코드"] +
          "</td>";
        html +=
          "<td style='text-align: left !important;'><div style='word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; white-space: normal !important;'>" +
          item["자재명"] +
          "</div></td>";
        html +=
          "<td style='text-align: left !important;'><div class='upc-ingredients' style='word-break: break-word !important; word-wrap: break-word !important; overflow-wrap: break-word !important; white-space: normal !important;'>" +
          item["lot명"] +
          "</div></td>";
        
        // HTML 생성 부분
        html +=
            "<td style='text-align: right !important; color: red; cursor: pointer;' class='price-history-trigger' " +
            "data-mat-cd='" + item["자재코드"] + "' " +
            "data-mat-name='" + item["자재명"] + "' " +  // 제품명 속성 추가
            ">" +
            numberWithCommas(item["자재단가"]) +
            "</td>";
          
        html +=
          "<td style='text-align: center !important;'>" +
          item["매입일"] +
          "</td>";
        html += "</tr>";
      });

      html += "</tbody></table>";
    } else {
      // 모바일 버전

      html += "<div class='mobile-grid'>";
      $.each(data, function (i, item) {
        var baseColor = i % 2 === 0 ? '#ffffff' : '#f0f0f0';  // 기본 줄무늬 색상
        var rowColor = baseColor;

        // 특수 조건 (노란색 박스, 지난 날짜) 처리
        var yellowBoxCodes = ["M020", "M021", "M022", "M023", "M024"];
        if (yellowBoxCodes.includes(item["자재코드"])) {
            rowColor = "yellow";
        } else if (item["매입일"] < today) {
            rowColor = "#f0f0f0";
        }

        html += `<div class='product-box clickable-row' 
                      data-original-style='background-color: ${rowColor}' 
                      style='background-color: ${rowColor}'>`;
        
        html +=
          "<div class='product-info'><span>순번: " +
          (i + 1) +
          "</span><span>코드: " +
          item["자재코드"] +
          "</span><span>매입일: " +
          item["매입일"] +
          "</span></div>";
        html +=
          "<div class='product-info' style='color: blue;'>" +
          item["자재명"] +
          "</div>";
        
        // 단가 클릭 시 단가 이력 표시
        html +=
            "<div class='product-info price-history-trigger' " +
            "data-mat-cd='" + item["자재코드"] + "' " +
            "data-mat-name='" + item["자재명"] + "' " +
            "style='color: red; cursor: pointer;'>" +
            "<span>단가: " + numberWithCommas(item["자재단가"]) + "</span>" +
            "</div>";

        html +=
          "<div class='product-info'><span>Lot명: " +
          item["lot명"] +
          "</span></div>";
        html += "</div>";
      });
      html += "</div>";
      
      // CSS 스타일 추가
      html += `
      <style>
          .mobile-grid .product-box {
              padding: 10px;
              border-bottom: 1px solid #ddd;
              transition: background-color 0.3s;
          }
          .mobile-grid .product-box.selected {
              background-color: #BBF731 !important;
          }
          .mobile-grid .product-box:hover {
              background-color: #E0F7FA;
          }
      </style>
      `;

    }

    $matGrid.html(html);

    // 모바일에서 전화 링크 방지
    if (isMobile) {
      $(".mobile-price").each(function () {
        $(this).attr("style", "color: red; text-decoration: none;");
        $(this).parent().attr("style", "pointer-events: none;");
      });
    }

    // 마우스 오버 및 클릭 이벤트 추가
    $(".clickable-row").on({
      mouseenter: function () {
        $(this).css("background-color", "#E0F7FA");
      },
      mouseleave: function () {
        if (!$(this).hasClass("selected")) {
          $(this).attr("style", $(this).data("original-style"));
        }
      },
      click: function () {
        $(".clickable-row")
          .removeClass("selected")
          .each(function () {
            $(this).attr("style", $(this).data("original-style"));
          });
        $(this).addClass("selected");
        var originalStyle = $(this).data("original-style");
        $(this).attr("style", originalStyle + " background-color: #BBF731;");
      },
    });

    addMatPriceHistoryListeners();

    setTimeout(() => {
        move_stop();
    }, 0);

  }


  function numberWithCommas(x) {
    if (x === null || x === undefined) {
        return ''; // null 또는 undefined인 경우 빈 문자열 반환
    }
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  // 납품 단가 클릭 이벤트 리스너 추가 함수
  function addPriceHistoryListeners() {
      document.querySelectorAll('.price-history-trigger').forEach(priceCell => {
          priceCell.addEventListener('click', function(e) {
              e.stopPropagation();
              const prodCd = this.getAttribute('data-prod-cd');
              const prodName = this.getAttribute('data-prod-name'); // 제품명 속성 추가
              
              if (!prodCd) {
                  console.error('제품코드를 찾을 수 없습니다');
                  return;
              }
              
              priceHistory.showHistory(prodCd, prodName);
          });
      });
  }

    // 재료 단가 클릭 이벤트 리스너 추가 함수
  function addMatPriceHistoryListeners() {
      document.querySelectorAll('.mat-price-history-trigger').forEach(priceCell => {
          priceCell.addEventListener('click', function(e) {
              e.stopPropagation();
              const matCd = this.getAttribute('data-mat-cd');
              const matName = this.getAttribute('data-mat-name'); // 제품명 속성 추가
              
              if (!matCd) {
                  console.error('자재코드를 찾을 수 없습니다');
                  return;
              }
              
              MatPriceHistory.showHistory(matCd, matName);
          });
      });
  }

});