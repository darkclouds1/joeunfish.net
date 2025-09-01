/* 제품 및 재료마스터 Table 관련 함수 -by dc 2025-01-21
 제품마스터 : tdayprod_mst
 자재마스터 : tdayprodmat_mst
 자재구매내역: tdayprod_lotbuy (본사구매)
 자재재고내역: tdayprod_lotstock (본사구매 에서 => 입고)
 자재소모내역: tdayprod_lotuse (본사입고 에서 => 소모)
*/

// 제품마스터 화면 표시
function showTMst() {
    if (!userCan.edit) {
        alert('접근 권한이 없습니다.');
        return;
    }
    document.getElementById('dayProdView').style.display = 'none';
    document.getElementById('tMstView').style.display = 'block';
    document.getElementById('tMatMstView').style.display = 'none';

    // title 요소 체크 추가
    const titleElement = document.getElementById('title');
    if (titleElement) {
        titleElement.innerHTML = '&nbsp;&nbsp; 생산일지::제품마스터';
    }
    fetchDataForTMst();
}

function showTMat_Mst() {
    if (!userCan.edit) {
        alert('접근 권한이 없습니다.');
        return;
    }
    document.getElementById('dayProdView').style.display = 'none';
    document.getElementById('tMstView').style.display = 'none';
    document.getElementById('tMatMstView').style.display = 'block';

    // title div 직접 수정
    const titleElement = document.getElementById('title');
    if (titleElement) {
        titleElement.innerHTML = '&nbsp;&nbsp; 생산일지::자재마스터';
    }
    fetchDataForTMatMst();
}

// --------------------------
// 제품마스터 조회 함수
// --------------------------
function fetchDataForTMst() {
  const url = '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMstSearch';
  console.log('[ln49::dayProd_srv::url] ' + url);

  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      const tMstView = document.getElementById(
        'tMstView'
      );
      tMstView.style.position = 'relative';
      tMstView.style.overflow = 'visible';

      let output = '<div style="overflow: auto;">';
      output += '<table border="1" style="font-size: 12px; width: 100%;">';
      output += '<thead>';
      output += '<tr style="background-color: #d3d3d3;">';

      // 테이블 헤더 생성
      Object.keys(data.items[0]).forEach((key) => {
        output += `<th>${key}</th>`;
      });
      output += `<th>수정</th><th>삭제</th><th>추가</th>`;
      output += '</tr></thead><tbody>';


      window.tMstItems = data.items;
      let rowColor = true;

      // 데이터 행 생성
      tMstItems.forEach((item, index) => {
        let color = rowColor ? 'white' : '#f2f2f2';
        output += `<tr id="tMst-row-${index}" 
                      class="tMst-row" 
                      style="background-color: ${color}"
                      onclick="selectTMstRow(${index})">`;

        Object.keys(item).forEach((key) => {
            output += `<td style="text-align: left;">${item[key]}</td>`;
        });

        output += `<td><a href="#" onclick="event.stopPropagation(); editTMstRow(${index}); return false;">수정</a></td>`;
        output += `<td><a href="#" onclick="event.stopPropagation(); deleteTMstRow(${index}); return false;">삭제</a></td>`;
        output += `<td><a href="#" onclick="event.stopPropagation(); addTMstRowBelow(${index}); return false;">추가</a></td>`;
        output += '</tr>';
        rowColor = !rowColor;
    });

      output += '</tbody></table>';
      tMstView.innerHTML = output;
    })
    .catch((error) => {
      console.error('제품마스터 조회 중 오류 발생:', error);
    });
}

// 제품마스터 전역 변수 및 함수로 선언
window.selectedTMstRow = null;
window.selectTMstRow = function(index) {
    // 이전에 선택된 행의 스타일 제거
    if (window.selectedTMstRow !== null) {
        const prevRow = document.getElementById(`tMst-row-${window.selectedTMstRow}`);
        if (prevRow) {
            prevRow.classList.remove('selected-row');
        }
    }

    // 새로운 행 선택
    const newRow = document.getElementById(`tMst-row-${index}`);
    if (newRow) {
        newRow.classList.add('selected-row');
        window.selectedTMstRow = index;
    }
};

// 스타일 추가
const style = document.createElement('style');
style.textContent = `
    .selected-row {
        background-color: #BBF731 !important;
        transition: background-color 0.2s;
    }
    .tMst-row:hover {
        background-color: #f5f5f5;
        cursor: pointer;
    }
`;
document.head.appendChild(style);

// 제품마스터 행 수정 함수
window.editTMstRow = function (index) {
  const tableRow = document.getElementById(`tMst-row-${index}`);
  const cells = tableRow.querySelectorAll(
    'td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))'
  );

  cells.forEach((cell, idx) => {
    const key = Object.keys(tMstItems[index])[idx];
    const text = cell.innerText;
    const cellWidth = cell.offsetWidth;
    if (key !== 'id') {
      // ID 필드는 수정 불가
      cell.innerHTML = `<input type='text' name='${key}' value='${text}' style='width: ${cellWidth}px; font-size: 12px;'>`;
    }
  });

  // 버튼 변경
  const editButton = tableRow.querySelector('td:nth-last-child(3) a');
  editButton.innerText = '저장';
  editButton.onclick = function () {
    saveTMstRow(index);
    return false;
  };

  const deleteButton = tableRow.querySelector('td:nth-last-child(2) a');
  deleteButton.innerText = '취소';
  deleteButton.onclick = function () {
    cancelTMstEditRow(index);
    return false;
  };
};

// 제품마스터 행 추가
window.addTMstRowBelow = function (index) {
  const table = document
    .getElementById('tMstView')
    .querySelector('table');
  const newRow = table.insertRow(index + 2);

  const item = tMstItems[index];
  newRow.innerHTML = `
    <td><input type='text' name='prod_cd' style='width: 100px; font-size: 12px; padding: 5px 5px;'></td>
    <td><input type='text' name='prod_nm' style='width: 150px; font-size: 12px; padding: 5px 5px;'></td>
    <td><input type='text' name='unit' style='width: 80px; font-size: 12px; padding: 5px 5px;'></td>
    <td><input type='number' name='order_num' style='width: 80px; font-size: 12px; padding: 5px 5px;'></td>
    <td><select name='use_yn' style='width: 60px; font-size: 12px; padding: 5px 5px;'>
            <option value="Y">Y</option>
            <option value="N">N</option>
        </select></td>
    <td></td>
    <td><a href="#" onclick='saveTMstNewRow(this); return false;'>저장</a></td>
    <td><a href="#" onclick='cancelTMstRow(this); return false;'>취소</a></td>
`;
};

// 제품마스터 새로운 행 저장
window.saveTMstNewRow = function (element) {
  const row = element.closest('tr');
  const inputs = row.querySelectorAll('input, select');

  const data = {
    prod_cd: inputs[0].value,
    prod_nm: inputs[1].value,
    unit: inputs[2].value,
    order_num: inputs[3].value,
    use_yn: inputs[4].value,
    create_id: user_id,
  };

  if (!data.prod_cd || !data.prod_nm || !data.unit || !data.order_num) {
    alert('필수 항목을 모두 입력해주세요.');
    return;
  }

  const url = '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMstInsert';
  console.log('[dayProd_srv::url] ' + url);

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then((result) => {
      if (result.success) {
        alert('저장되었습니다.');
        fetchDataForTMst(); // 테이블 새로고침
      } else {
        alert('저장 실패: ' + (result.error || '알 수 없는 오류'));
      }
    })
    .catch((error) => {
      console.error('Error:', error);
      alert('저장 중 오류가 발생했습니다.');
    });
};

// 제품마스터 저장
window.saveTMstRow = function (index) {
  const tableRow = document.getElementById(`tMst-row-${index}`);
  const inputs = tableRow.querySelectorAll('input, select');
  const prod_cd = tMstItems[index].prod_cd; // 기존 prod_cd 보관

  const data = {
    prod_cd: prod_cd,
    prod_nm: inputs[1].value,
    unit: inputs[2].value,
    order_num: inputs[3].value,
    use_yn: inputs[4].value,
    modify_id: user_id,
  };

  const url = '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMstUpdate';
  console.log('[dayProd_srv::url] ' + url);

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        alert('수정되었습니다.');
        fetchDataForTMst(); // 테이블 새로고침
      } else {
        alert('수정 실패: ' + (result.error || '알 수 없는 오류'));
      }
    });
};

// 제품마스터 수정 취소
window.cancelTMstEditRow = function (index) {
  const tableRow = document.getElementById(`tMst-row-${index}`);
  const cells = tableRow.querySelectorAll(
    'td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))'
  );

  cells.forEach((cell, idx) => {
    cell.innerHTML = tMstItems[index][Object.keys(tMstItems[index])[idx]];
  });

  // 버튼 원래대로 복구
  const editButton = tableRow.querySelector('td:nth-last-child(3) a');
  editButton.innerText = '수정';
  editButton.onclick = function () {
    editTMstRow(index);
    return false;
  };

  const deleteButton = tableRow.querySelector('td:nth-last-child(2) a');
  deleteButton.innerText = '삭제';
  deleteButton.onclick = function () {
    deleteTMstRow(index);
    return false;
  };
};

// 제품마스터 삭제
window.deleteTMstRow = function (index) {
  if (!confirm('이 항목을 삭제하시겠습니까?')) {
    return;
  }

  const prod_cd = tMstItems[index].prod_cd;

  const url = '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMstDelete';
  console.log('[dayProd_srv::url] ' + url);

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ prod_cd: prod_cd }),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        alert('삭제되었습니다.');
        fetchDataForTMst(); // 테이블 새로고침
      } else {
        alert('삭제 실패: ' + (result.error || '알 수 없는 오류'));
      }
    });
};

// 제품마스터 취소
window.cancelTMstRow = function (element) {
  const row = element.closest('tr');
  row.remove();
};

// 자재마스터 선택 row 색깔 바꾸기
// 전역 변수로 선언 (기존 selectedTMstRow와 함께)
window.selectedTMatMstRow = null;

// 전역 함수로 선언
window.selectTMatMstRow = function(index) {
    // 이전에 선택된 행의 스타일 제거
    if (window.selectedTMatMstRow !== null) {
        const prevRow = document.getElementById(`tMatMst-row-${window.selectedTMatMstRow}`);
        if (prevRow) {
            prevRow.classList.remove('selected-row');
        }
    }

    // 새로운 행 선택
    const newRow = document.getElementById(`tMatMst-row-${index}`);
    if (newRow) {
        newRow.classList.add('selected-row');
        window.selectedTMatMstRow = index;
    }
};


// 자재마스터 컬럼 정의
window.tMatMstVisibleColumns = ['prod_cd', 'prod_nm', 'mat_cd', 'mat_nm', 'unit', 'order_num', 'inv_yn','temp_code', 'use_yn'];

// 자재마스터 데이터 조회
function fetchDataForTMatMst() { 
    const url = '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMatMstSearch';
    console.log('[dayProd_srv::url] ' + url);

    fetch(url)
        .then((response) => response.json())
        .then((data) => {
            const tMatMstView = document.getElementById('tMatMstView');
            tMatMstView.style.position = 'relative';
            tMatMstView.style.overflow = 'visible';

            let output = '<div style="overflow: auto;">';
            output += '<table border="1" style="font-size: 12px; width: 100%;">';
            output += '<thead>';
            output += '<tr style="background-color: #d3d3d3;">';

            window.tMatMstVisibleColumns.forEach((key) => {
                output += `<th>${key}</th>`;
            });
            output += `<th>수정</th><th>삭제</th><th>추가</th>`;
            output += '</tr></thead><tbody>';

            window.tMatMstItems = data.items;
            let rowColor = true;

            // 데이터 행 생성
            tMatMstItems.forEach((item, index) => {
                let color = rowColor ? 'white' : '#f2f2f2';
                output += `<tr id="tMatMst-row-${index}" 
                              class="tMst-row" 
                              style="background-color: ${color}"
                              onclick="selectTMatMstRow(${index})">`;

                window.tMatMstVisibleColumns.forEach((key) => {
                    output += `<td style="text-align: left;">${item[key]}</td>`;
                });

                output += `<td><a href="#" onclick="event.stopPropagation(); editTMatMstRow(${index}); return false;">수정</a></td>`;
                output += `<td><a href="#" onclick="event.stopPropagation(); deleteTMatMstRow('${item.prod_cd}', '${item.mat_cd}'); return false;">삭제</a></td>`;
                output += `<td><a href="#" onclick="event.stopPropagation(); addTMatMstRowBelow(${index}); return false;">추가</a></td>`;
                output += '</tr>';
                rowColor = !rowColor;
            });

            output += '</tbody></table>';
            tMatMstView.innerHTML = output;
        })
        .catch((error) => {
            console.error('자재마스터 조회 중 오류 발생:', error);
        });
}

// 자재마스터 새로운 행 추가
window.addTMatMstRowBelow = function (index) {
  const table = document
    .getElementById('tMatMstView')
    .querySelector('table');
  const newRow = table.insertRow(index + 2);

  newRow.innerHTML = `
        <td><input type='text' name='prod_cd' style='width: 100px; font-size: 12px; padding: 5px 5px;'></td>
        <td style='width: 150px; font-size: 12px; padding: 5px 5px;'></td>
        <td><input type='text' name='mat_cd' style='width: 100px; font-size: 12px; padding: 5px 5px;'></td>
        <td><input type='text' name='mat_nm' style='width: 150px; font-size: 12px; padding: 5px 5px;'></td>
        <td><input type='text' name='unit' style='width: 80px; font-size: 12px; padding: 5px 5px;'></td>
        <td><input type='number' name='order_num' style='width: 80px; font-size: 12px; padding: 5px 5px;'></td>
        <td><select name='inv_yn' style='width: 60px; font-size: 12px; padding: 5px 5px;'>
                <option value="Y">Y</option>
                <option value="N">N</option>
            </select></td>
        <td><input type='text' name='temp_code' style='width: 80px; font-size: 12px; padding: 5px 5px;'></td>
        <td><select name='use_yn' style='width: 60px; font-size: 12px; padding: 5px 5px;'>
                <option value="Y">Y</option>
                <option value="N">N</option>
            </select></td>
        <td><a href="#" onclick='saveTMatMstNewRow(this); return false;'>저장</a></td>
        <td><a href="#" onclick='cancelTMatMstRow(this); return false;'>취소</a></td>
    `;
};

// 자재마스터 새로운 행 저장
window.saveTMatMstNewRow = function (element) {
  const row = element.closest('tr');
  const inputs = row.querySelectorAll('input, select');

  const data = {
    prod_cd: inputs[0].value,
    mat_cd: inputs[1].value,
    mat_nm: inputs[2].value,
    unit: inputs[3].value,
    order_num: parseInt(inputs[4].value),
    inv_yn: inputs[5].value,
    temp_code: inputs[6].value,
    use_yn: inputs[7].value,
    create_id: user_id,
  };

  if (
    !data.prod_cd ||
    !data.mat_cd ||
    !data.mat_nm ||
    !data.unit ||
    !data.inv_yn ||
    typeof data.order_num !== 'number'
  ) {
    alert('모든 필수 항목을 입력해주세요.');
    return;
  }

  const url =
    '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMatMstInsert';
  console.log('[dayProd_srv::url] ' + url);

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  })
    .then((response) => {
      if (!response.ok) {
        return response.json().then((err) => {
          throw new Error(err.error || '서버 오류가 발생했습니다.');
        });
      }
      return response.json();
    })
    .then((result) => {
      if (result.success) {
        alert('저장되었습니다.');
        fetchDataForTMatMst();
      } else {
        alert('저장 실패: ' + (result.error || '알 수 없는 오류'));
      }
    })
    .catch((error) => {
      console.error('Error:', error);
      alert(error.message); // 서버에서 전달된 에러 메시지 표시
    });
};

// 자재마스터 수정
window.editTMatMstRow = function (index) {
  const tableRow = document.getElementById(`tMatMst-row-${index}`);
  const cells = tableRow.querySelectorAll(
    'td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))'
  );

  cells.forEach((cell, idx) => {
    const key = window.tMatMstVisibleColumns[idx];
    const text = cell.innerText;
    const cellWidth = cell.offsetWidth;
    if (key !== 'prod_cd' && key !== 'prod_nm' && key !== 'mat_cd') {
      if (key === 'inv_yn') {
        cell.innerHTML = `<select name='${key}' style='width: 60px; font-size: 12px; padding: 5px 5px;'>
                    <option value="Y" ${
                      text === 'Y' ? 'selected' : ''
                    }>Y</option>
                    <option value="N" ${
                      text === 'N' ? 'selected' : ''
                    }>N</option>
                </select>`;
      } else if (key === 'use_yn') {
        cell.innerHTML = `<select name='${key}' style='width: 60px; font-size: 12px; padding: 5px 5px;'>
                    <option value="Y" ${
                      text === 'Y' ? 'selected' : ''
                    }>Y</option>
                    <option value="N" ${
                      text === 'N' ? 'selected' : ''
                    }>N</option>
                </select>`;
      } else {
        cell.innerHTML = `<input type='${
          key === 'order_num' ? 'number' : 'text'
        }' 
                                 name='${key}' value='${text}' 
                                 style='width: ${cellWidth}px; font-size: 12px; padding: 5px 5px;'>`;
      }
    }
  });

  const editButton = tableRow.querySelector('td:nth-last-child(3) a');
  editButton.innerText = '저장';
  editButton.onclick = function () {
    saveTMatMstRow(index);
    return false;
  };

  const deleteButton = tableRow.querySelector('td:nth-last-child(2) a');
  deleteButton.innerText = '취소';
  deleteButton.onclick = function () {
    cancelTMatMstEditRow(index);
    return false;
  };
};

// 자재마스터 수정 저장
window.saveTMatMstRow = function (index) {
  const tableRow = document.getElementById(`tMatMst-row-${index}`);
  const inputs = tableRow.querySelectorAll('input, select');
  const originalItem = tMatMstItems[index];

  const data = {
    prod_cd: originalItem.prod_cd, // 원래 키 값 유지
    mat_cd: originalItem.mat_cd, // 원래 키 값 유지
    mat_nm: inputs[0].value,
    unit: inputs[1].value,
    order_num: parseInt(inputs[2].value),
    inv_yn: inputs[3].value,
    temp_code: inputs[4].value,
    use_yn: inputs[5].value,
    modify_id: user_id,
  };

  const url =
    '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMatMstUpdate';
  console.log('[ln589::dayProd_srv::url] ' + url);
  console.log('data: ' + JSON.stringify(data));

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        alert('수정되었습니다.');
        fetchDataForTMatMst();
      } else {
        alert('수정 실패: ' + (result.error || '알 수 없는 오류'));
      }
    });
};

// 수정 취소
window.cancelTMatMstEditRow = function (index) {
  const tableRow = document.getElementById(`tMatMst-row-${index}`);
  const cells = tableRow.querySelectorAll(
    'td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))'
  );

  cells.forEach((cell, idx) => {
    const key = window.tMatMstVisibleColumns[idx];
    cell.innerHTML = tMatMstItems[index][key];
  });

  const editButton = tableRow.querySelector('td:nth-last-child(3) a');
  editButton.innerText = '수정';
  editButton.onclick = function () {
    editTMatMstRow(index);
    return false;
  };

  const deleteButton = tableRow.querySelector('td:nth-last-child(2) a');
  deleteButton.innerText = '삭제';
  deleteButton.onclick = function () {
    deleteTMatMstRow(tMatMstItems[index].prod_cd, tMatMstItems[index].mat_cd);
    return false;
  };
};

// 자재마스터 행 삭제
window.deleteTMatMstRow = function (prod_cd, mat_cd) {
  if (!confirm('이 항목을 삭제하시겠습니까?')) {
    return;
  }

  const url =
    '/wp-content/plugins/dayProd/dayProd_srv.php?category=tMatMstDelete';
  console.log('[dayProd_srv::url] ' + url);

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ prod_cd: prod_cd, mat_cd: mat_cd }),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        alert('삭제되었습니다.');
        fetchDataForTMatMst();
      } else {
        alert('삭제 실패: ' + (result.error || '알 수 없는 오류'));
      }
    });
};

// 자재마스터 추가/수정 취소
window.cancelTMatMstRow = function (element) {
  const row = element.closest('tr');
  row.remove();
};
