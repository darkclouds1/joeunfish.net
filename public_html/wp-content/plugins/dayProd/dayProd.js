function showDayProd() {
  document.getElementById('dayProdView').style.display = 'block';
  document.getElementById('tMstView').style.display = 'none';
  document.getElementById('tMatMstView').style.display = 'none';

  // 타이틀 변경
  document.getElementById('title').querySelector('p').innerHTML = '&nbsp;&nbsp; 생산일지::일일생산';
}

function createLotinSelectBox(row, label, prodCd, matCd) {
    const cell = document.createElement("td");
    cell.style.textAlign = "center";

    // Select 요소 생성
    const select = document.createElement("select");
    select.dataset.type = label;
    select.dataset.prodCd = prodCd;
    select.dataset.matCd = matCd;
    select.className = 'lot-select';  // 클래스 추가

    // 기본 옵션 추가
    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "입고|LOT명";
    select.appendChild(defaultOption);

    // 서버에서 데이터 가져오기
    const url = '/wp-content/plugins/dayProd/dayProd_srv.php?category=lotin_nm&mat_cd=' + matCd;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.lotin_list.forEach(item => {
                    const option = document.createElement("option");
                    option.value = item.lotin_id; // 값: lotin_id
                    option.textContent = item.lotin_nm; // 표시: lotin_nm
                    select.appendChild(option);
                });
            } else {
                console.error("데이터를 가져오는 데 실패했습니다.");
            }
        })
        .catch(error => console.error("Error:", error));

    cell.appendChild(select);
    row.appendChild(cell);
}
