class DayProdClass {
  constructor() {
    this.columns = [
     '원료명', 
     '입고LOT명',
     '사용량',
     '원료단위',
     'mBox',
     '소모량',
     '완제품명',
     'pBox',
     '생산량',
     '생산율',
     '저장',
     '저장일시',
     '담당자'
    ];
    this.colWidths = [
      '10%', '18%', '5%', '7%', '5%',
      '5%', '10%', '5%', '5%', '7%',
      '5%', '10%', '8%'
    ];

    this.data = {
            prod_cd: '',
            prod_qty_unit: '',  // pBox
            prod_qty: '',       // 생산량
            yield_rate: '',     // 생산율
            materials: []
        };
    this.handleRowClick = this.handleRowClick.bind(this);
    this.addEventListeners = this.addEventListeners.bind(this);

    this.selectedRow = null;
    this.orderedInputs = [];

    // 메서드 바인딩
    this.createSelectBox = this.createSelectBox.bind(this);
    this.createInputCell = this.createInputCell.bind(this);
    this.handleInputEnter = this.handleInputEnter.bind(this);
    this.getOrderedInputs = this.getOrderedInputs.bind(this);
    this.makeTableBody = this.makeTableBody.bind(this);
    this.fSaveData = this.fSaveData.bind(this);
    this.isFirstMaterial = this.isFirstMaterial.bind(this);
    this.createNumberInput = this.createNumberInput.bind(this);
    this.dayProd_srv = window.dayProd_srv || '/wp-content/plugins/dayProd/dayProd_srv.php';
    // 생성자에서 초기화 실행
    this.init();
  }

  init() {
    console.log('DayProdClass initializing...');

    if (!$('#tables').length) {
      $('body').append('<div id="tables"></div>');
      console.log('Created tables container');
    }

  }

  getOrderedInputs(container) {
    if (!container) {
      return [];
    }

    // const table = $(container).find('table');
    const table = $(container).is('table')
      ? $(container)
      : $(container).find('table');

    if (!table.length) {
      console.warn('Table not found in container');
      return [];
    }

    const allInputs = Array.from(table.find('input[data-type]'));
    if (allInputs.length === 0) {
      console.warn('No input fields found in the table');
      return [];
    }

    return allInputs;
  }

  makeTableHead() {
    const table = document.createElement('table');
    table.id = 'tables';
    table.className = 'type10';
    table.style.width = '100%';

    const thead = document.createElement('thead');

    thead.style.backgroundColor = '#000'; // thead의 배경색을 검은색으로 설정
    thead.style.color = '#fff'; // thead의 글자색을 흰색으로 설정

    const headerRow = document.createElement('tr');

    this.columns.forEach((col, i) => {
      const th = document.createElement('th');
      th.innerHTML = col;
      th.style.width = this.colWidths[i];
      headerRow.appendChild(th);
    });

    thead.appendChild(headerRow);
    table.appendChild(thead);

    // tbody 생성
    const tbody = document.createElement('tbody');
    table.appendChild(tbody);

    document.getElementById('tables').innerHTML = '';
    document.getElementById('tables').appendChild(table);
  }

  makeTableBody(data) {

    // tables div 안에 있는 table 요소를 찾음
    const tablesDiv = document.getElementById('tables');
    const table = tablesDiv.querySelector('table');
    if (!table) return; // 테이블이 없으면 종료

    // 기존 tbody가 있다면 제거
    const existingTbody = table.querySelector('tbody');
    if (existingTbody) {
      existingTbody.remove();
    }

    // 새로운 tbody 생성
    const tbody = document.createElement('tbody');
    table.appendChild(tbody);

    let currentProdCd = null;
    let itemCount = 0;
    let isAlternateColor = false; // 색상 변경을 위한 플래그

    data.forEach((item) => {
      itemCount++;
      const row = tbody.insertRow();
      const isNewProduct = currentProdCd !== item.prod_cd;
      currentProdCd = item.prod_cd;

      // 새로운 제품일 경우 색상 변경
      if (isNewProduct) {
        isAlternateColor = !isAlternateColor;
      }

      // 제품별로 배경색 설정
      if (isAlternateColor) {
        row.classList.add('alternate-color-1');
      } else {
        row.classList.add('alternate-color-2');
      }
      // 연어필렛인 경우 행 배경색을 노란색으로 설정
      if (item['완제품명'] === '연어필렛') {
        row.classList.add('salmon-row');
      }

      // -----------------------------------------------
      // 확정 여부가 n이거나 null인 경우에 input 필드 추가
      // -----------------------------------------------
      if (item['확정여부'] === 'n' || item['확정여부'] === null) {

        // 1.원료명
        const matNameCell = row.insertCell();
        matNameCell.style.textAlign = 'right'; // 오른쪽 정렬
        const matNameText = document.createTextNode(item['원료명'] + ' ');
        const addButton = document.createElement('span');
        addButton.textContent = '✔️+';
        addButton.style.cursor = 'pointer';
        addButton.style.color = 'blue'; // 클릭 가능하다는 시각적 표시

        matNameCell.appendChild(matNameText);
        matNameCell.appendChild(addButton);

        // ------------------------------------------
        // 클릭 이벤트 추가:: 자재 추가
        // ------------------------------------------
        addButton.addEventListener(
          'click',
          (() => {
            // 두 번째 셀의 텍스트가 "" 또는 "재고량을 입력"인 경우 클릭 이벤트 스킵
            const secondCellText = row.cells[1].textContent.trim(); 
            if (secondCellText === '' || secondCellText === '재고량을 입력' || secondCellText === '이동재고 없음') {
                return; // 클릭 이벤트 스킵
            }
            
            // 첫 번째 셀에 '✔️-' 추가
            const newRow = table.insertRow(row.rowIndex + 1);
            newRow.dataset.prodCd = item.prod_cd;
            newRow.dataset.matCd = item.mat_cd;
            newRow.dataset.invYn = item.inv_yn;

            const removeCell = newRow.insertCell();
            removeCell.style.textAlign = 'right'; // 오른쪽 정렬
            const removeButton = document.createElement('span');
            removeButton.textContent = '✔️-';
            removeButton.style.cursor = 'pointer';
            removeButton.style.color = 'red'; // 클릭 가능하다는 시각적 표시
            removeCell.appendChild(removeButton);

            // 클릭 시 행 삭제
            removeButton.addEventListener('click', () => {
              table.deleteRow(newRow.rowIndex);
            });

            // 자재입고명 (입고 LOT명) Select Box 생성
            createLotinSelectBox(newRow, '입고LOT명', item.prod_cd, item.mat_cd);

            // 사용량 입력
            const matUseQtyInput = this.createInputCell(
              newRow,
              '사용량',    // 타입
              '',         // 사용량
              'right',    // 정렬
              item.prod_cd, // prodCd
              item.mat_cd,  // matCd
              item.inv_yn   // inv_yn
            );
            matUseQtyInput.dataset.type = '사용량';

          }).bind(this)
        ); // this를 바인딩

        // 2.입고LOT명, lotin_id 
        const matUseDataCell = row.insertCell(); // 두 번째 셀 생성
        matUseDataCell.style.textAlign = 'left';
        if (item.lotin_id === null && item.inv_yn === 'Y') {
          matUseDataCell.textContent = '재고량을 입력';
          matUseDataCell.dataset.type = '입고LOT명';
        } else if (item.lotin_id === null && item.inv_yn === '') {
          // matUseDataCell.textContent = '클릭하여 선택';
          matUseDataCell.textContent = '';
          matUseDataCell.dataset.type = '입고LOT명';
        } else {
          // matUseDataCell.textContent = item.lotin_id;
          matUseDataCell.textContent = item['입고LOT명'];
          matUseDataCell.dataset.lotinId = item['lotin_id']; // 
          matUseDataCell.dataset.type = '입고LOT명';
        }

        // 클릭 이벤트 추가
        matUseDataCell.addEventListener('click', () => {
          // 이미 select box가 생성되어 있으면 중복 생성 방지
          if (matUseDataCell.querySelector('select') || matUseDataCell.textContent === '재고량을 입력') {
            return;
          }

          // td 자체에도 data-type 속성 추가
          matUseDataCell.dataset.type = '입고LOT명';
          const matUseDateSelect = this.createSelectBox(
            row,
            '입고LOT명',
            item.lotin_id,    // 선택된 값
            'center',
            item.prod_cd,     // prodCd
            item.mat_cd,       // matCd
            item.inv_yn        // Y= 재고로 관리
          );
          matUseDateSelect.className = 'lot-select';  // 클래스 추가
          matUseDateSelect.dataset.type = '입고LOT명';

          // 기존 텍스트 제거하고 select box 추가
          matUseDataCell.textContent = '';
          matUseDataCell.appendChild(matUseDateSelect);          
        });

        // 3.사용량
        const matUseQtyInput = this.createInputCell(
          row,
          '사용량',         // 타입
          item['사용량'],   // 사용량
          'right',         // 정렬
          item.prod_cd,    // prodCd
          item.mat_cd,     // matCd
          item.inv_yn      // inv_yn
        );
        matUseQtyInput.dataset.type = '사용량';
        
        // 4.원료단위
        const matUnitCell = row.insertCell();
        matUnitCell.textContent = item['원료단위'];
        matUnitCell.style.textAlign = 'center';
        
        // 첫번째 원료인 경우
        const isFirstMat = this.isFirstMaterial(item.prod_cd, item.mat_cd, row.rowIndex);
        if (isFirstMat) {
          // 소모량(Box)
          const matQtyBoxInput = this.createInputCell(
            row,
            'mBox',
            item['mBox'],
            'right',
            item.prod_cd, // prodCd 추가
            item.mat_cd   // matCd 추가
          );
          matQtyBoxInput.dataset.type = 'mBox';

          // 소모량
          const matQtyInput = this.createInputCell(
            row,
            '소모량',
            item['소모량'],
            'right',
            item.prod_cd, // prodCd 추가
            item.mat_cd // matCd 추가
          );
          matQtyInput.dataset.type = '소모량';
        }

      // -----------------------------------
      // 확정: text로 표시
      // -----------------------------------  
      } else {
        // 1. 원료명
          const matNameCell = row.insertCell();
          matNameCell.textContent = item['원료명'];
          applyCommonCellStyle(matNameCell, 'left');

          // 2. 입고LOT명
          const matBuyDateCell = row.insertCell();
          const lotName = item['입고LOT명'];
          matBuyDateCell.textContent = lotName;
          applyCommonCellStyle(matBuyDateCell, 'left');

          // 3. 사용량
          const matBuyQtyCell = row.insertCell();
          matBuyQtyCell.textContent = item['사용량'];
          applyCommonCellStyle(matBuyQtyCell, 'center');

          // 4. 원료단위
          const matUnitCell = row.insertCell();
          matUnitCell.textContent = item['원료단위'];
          applyCommonCellStyle(matUnitCell, 'center');

          // 첫번째 원료인 경우만 표시
          const isFirstMat = this.isFirstMaterial(item.prod_cd, item.mat_cd, row.rowIndex);
          if (isFirstMat) {
            // 5. mBox
            const matQtyBoxCell = row.insertCell();
            matQtyBoxCell.textContent = item['mBox'];
            applyCommonCellStyle(matQtyBoxCell, 'right');

            // 6. 소모량(kg)
            const matQtyCell = row.insertCell();
            matQtyCell.textContent = item['소모량'];
            applyCommonCellStyle(matQtyCell, 'right');
          }
      }

      // 완제품명 (첫 번째 행에만 표시)
      const prodNameCell = row.insertCell();
      if (isNewProduct) {
        prodNameCell.textContent = item['완제품명'];
        prodNameCell.style.textAlign = 'left';
        prodNameCell.setAttribute(
          'rowspan',
          data.filter((d) => d.prod_cd === item.prod_cd).length
        );
      } else {
        prodNameCell.style.display = 'none';
      }

      // pBox, 생산량, 생산율
      if (isNewProduct) {
        if (item['확정여부'] === 'n' || item['확정여부'] === null) {
          // 생산량(Box)
          const matQtyInput = this.createInputCell(
            row,
            'pBox',
            item['pBox'],
            'right',
            item.prod_cd, // prodCd 추가
            null // matCd 추가
          );
          matQtyInput.dataset.type = 'pBox';

          const prodQtyInput = this.createInputCell(
            row,
            '생산량',
            item['생산량'],
            'right',
            item.prod_cd, // prodCd 추가
            null // matCd 추가
          );
          prodQtyInput.dataset.type = '생산량';

          // 생산율
          const yieldRateInput = this.createInputCell(
            row,
            '생산율',
            item['생산율'],
            'right',
            item.prod_cd, // prodCd 추가
            null // matCd 추가
          );
          yieldRateInput.dataset.type = '생산율';
        } else {
          // pBox
          const prodQtyBoxInput = row.insertCell();
          prodQtyBoxInput.textContent = item['pBox'];
          prodQtyBoxInput.style.paddingRight = '10px';
          prodQtyBoxInput.style.textAlign = 'right';

          // 생산량
          const prodQtyInput = row.insertCell();
          prodQtyInput.textContent = item['생산량'];
          prodQtyInput.style.paddingRight = '10px';
          prodQtyInput.style.textAlign = 'right';

          // 생산율
          const yieldRateInput = row.insertCell();
          yieldRateInput.textContent = item['생산율'];
          yieldRateInput.style.paddingRight = '10px';
          yieldRateInput.style.textAlign = 'right';
        }
      } else {
        // pBox
        const prodQtyBoxInput = row.insertCell();
        prodQtyBoxInput.style.display = 'none';
        // 생산량
        const prodQtyInput = row.insertCell();
        prodQtyInput.style.display = 'none';
        // 생산율
        const yieldRateCell = row.insertCell();
        yieldRateCell.style.display = 'none';
      }

      // ------------------
      // 저장 버튼
      // ------------------
      const saveCell = row.insertCell();
      if (isNewProduct) {
        const saveBtn = document.createElement('button');
        saveBtn.textContent = '저장';
        saveBtn.className = 'save-btn';

        // 확정여부에 따라 버튼 활성화/비활성화
        if (item['확정여부'] === 'n' || item['확정여부'] === null) {
          saveBtn.disabled = false; // 활성화
          saveBtn.style.opacity = '1';
          saveBtn.style.cursor = 'pointer';
        } else {
          saveBtn.disabled = true; // 비활성화
          saveBtn.style.opacity = '0.5';
          saveBtn.style.cursor = 'not-allowed';
        }

        saveBtn.onclick = (e) => {
          // 확정여부 한번 더 체크
          if (item['확정여부'] === 'y') {
            alert('확정된 데이터는 수정할 수 없습니다.');
            return;
          }

          e.stopPropagation();

          // 저장할 제품 데이터 구조 생성
          try {
            const saveData = this.saveProductData(item.prod_cd);
            console.log('[436]saveData= ', saveData);

            this.fSaveData(saveData);
          } catch (error) {
              console.error('저장 실패:', error);
          }

        };

        saveCell.appendChild(saveBtn);
        saveCell.setAttribute(
          'rowspan',
          data.filter((d) => d.prod_cd === item.prod_cd).length
        );
      } else {
        saveCell.style.display = 'none';
      }

      // 저장일시
      const saveTimeCell = row.insertCell();
      if (isNewProduct) {
        saveTimeCell.textContent = item['저장일시'] || '';
        saveTimeCell.style.textAlign = 'center';
        saveTimeCell.setAttribute(
          'rowspan',
          data.filter((d) => d.prod_cd === item.prod_cd).length
        );
      } else {
        saveTimeCell.style.display = 'none';
      }

      // 담당자
      const userCell = row.insertCell();
      if (isNewProduct) {
        userCell.textContent = item['담당자'] || '';
        userCell.style.textAlign = 'center';
        userCell.setAttribute(
          'rowspan',
          data.filter((d) => d.prod_cd === item.prod_cd).length
        );
      } else {
        userCell.style.display = 'none';
      }

      // 이벤트 리스너 추가 부분 수정
      row.addEventListener('click', (e) => {
        // row의 dataset에서 직접 prod_cd와 mat_cd를 가져옴
        const rowData = {
          prod_cd: row.dataset.prodCd,
          mat_cd: row.dataset.matCd,
        };
        this.handleRowClick(row, rowData);
      });

      // Store prod_cd and mat_cd as data attributes
      row.dataset.prodCd = item.prod_cd;
      row.dataset.matCd = item.mat_cd;
      row.dataset.invYn = item.inv_yn;

      // input 필드에 이벤트 리스너 추가
      row.querySelectorAll('input').forEach((input) => {
        input.addEventListener('click', (e) => {
          e.stopPropagation(); // row 클릭 이벤트 전파 중지
        });
        input.addEventListener('focus', (e) => {
          e.stopPropagation(); // row 클릭 이벤트 전파 중지
        });
      });

      // 이벤트 리스너
      const inputs = row.querySelectorAll('input');
      if (inputs.length > 0) {
        inputs.forEach((input) => {
          // 클릭과 포커스 이벤트
          input.onclick = (e) => e.stopPropagation();
          input.onfocus = (e) => e.stopPropagation();
        });
      }

      // 생산량 입력 필드에 이벤트 리스너
      if (isNewProduct) {
        const prodInput = row.querySelector('input[data-type="생산량"]');
        if (prodInput) {
          prodInput.addEventListener('keydown', (e) => {
            this.handleInputEnter(e, prodInput, table);
          });
        }
      }
    });
  }

  // createInputCell을 화살표 함수로 정의하여 바인딩 문제 해결
  createInputCell = (row, type, value, align, prodCd, matCd) => {
    const cell = row.insertCell();
    const input = document.createElement('input');

    if (Number(value) === 0) {
      value = '';
    }

    switch (type) {
      case '입고LOT명':
      case '사용량':
      case 'mBox':
      case '소모량':
      case 'pBox':
      case '생산량':
        this.createNumberInput(type, input, value);
        break;
      
      default:
        input.type = 'text';
        input.value = value || '';
    }

    input.className = 'form-control';
    cell.style.textAlign = align;

    input.dataset.type = type;
    input.dataset.prodCd = prodCd;
    input.dataset.matCd = matCd;

    cell.appendChild(input);

    // Enter 키 이벤트 리스너
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        const container = row.closest('table');
        if (container) {
          this.handleInputEnter(e, input, container);
        }
      }
    });

    // Focus 이벤트 리스너
    input.addEventListener('focus', (e) => {
      e.stopPropagation();
      e.target.select();
      this.handleRowClick(row, { prod_cd: prodCd, mat_cd: matCd });
    });

    return input;
  };

  // 숫자 입력 필드 생성 함수
  createNumberInput(type, input, value) {
    const config = {
        '사용량': { maxInteger: 5, decimals: 1, max: 99999.9 },
        'mBox': { maxInteger: 3, decimals: 1, max: 99999.9 },
        '소모량': { maxInteger: 5, decimals: 1, max: 99999.9 },
        'pBox': { maxInteger: 5, decimals: 1, max: 99999.9 },
        '생산량': { maxInteger: 6, decimals: 1, max: 999999.9 },
        '생산율': { maxInteger: 4, decimals: 2, max: 999.99 },
    };
  
    input.type = 'text';
    input.value = (value === 0) ? '' : value || '';
    input.inputMode = 'decimal';
    
    const settings = config[type];

    input.addEventListener('keypress', (e) => {
        if (!/[\d.]/.test(e.key)) {
            e.preventDefault();
            return;
        }
        
        if (e.key === '.' && e.target.value.includes('.')) {
            e.preventDefault();
            return;
        }
    });

    input.addEventListener('input', (e) => {
        let val = e.target.value;
        
        // 숫자와 소수점 외 문자 제거
        val = val.replace(/[^\d.]/g, '');
        
        // 맨 앞 소수점 입력시 '0.' 추가
        if (val.startsWith('.')) {
            val = '0' + val;
        }
        
        if (val.includes('.')) {
            const parts = val.split('.');
            if (parts[0].length > settings.maxInteger) {
                parts[0] = parts[0].slice(0, settings.maxInteger);
            }
            if (parts[1].length > settings.decimals) {
                parts[1] = parts[1].slice(0, settings.decimals);
            }
            val = parts.join('.');
        } else if (val.length > settings.maxInteger) {
            val = val.slice(0, settings.maxInteger);
        }
        
        const numVal = parseFloat(val);
        if (!isNaN(numVal) && numVal > settings.max) {
            val = settings.max.toString();
        }
        
        e.target.value = val;
    });

    input.addEventListener('blur', (e) => {
        let val = e.target.value;
        if (val === '') return;
        
        let numVal = parseFloat(val);
        if (isNaN(numVal)) return;
        
        // 소수점 이하가 0이면 정수로 표시
        if (numVal === 0) {
            e.target.value = ''; // 값이 0이면 빈 문자열로 설정
        } else if (Number.isInteger(numVal)) {
            e.target.value = Math.floor(numVal).toString();
        } else {
            e.target.value = numVal.toFixed(settings.decimals);
        }
    });
  }

   createSelectBox(row, type, selectedValue, align, prodCd, matCd) {
    const cell = row.cells[1];
    const select = document.createElement('select');
    select.className = 'form-control';
    select.style.textAlign = align;
    select.dataset.type = type;
    select.dataset.prodCd = prodCd;
    select.dataset.matCd = matCd;

    // 입고LOT명 데이터 가져오기
    const url = this.dayProd_srv + `?category=lotin_nm&mat_cd=${matCd}`;
    console.log(url);

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.lotin_list.length > 0) {
                data.lotin_list.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.lotin_id; // 값: lotin_id
                    option.textContent = item.lotin_nm; // 표시: lotin_nm
                    if (item.lotin_id === selectedValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                cell.appendChild(select);
            } else {
                // 데이터가 없을 경우 텍스트 표시
                cell.textContent = "이동재고 없음";
            }
        })
        .catch(error => {
            console.error("Error:", error);
            cell.textContent = "이동재고 없음"; // 에러 발생 시에도 텍스트 표시
        });

    return select;
  }

  // 현재 원료의 첫 등장 여부를 확인하는 함수
  isFirstMaterial(prodCd, matCd, currentRowIndex) {
      const rows = document.querySelectorAll('tbody tr');
      
      // 현재 행 이전의 행들을 검사
      for (let i = 0; i < currentRowIndex; i++) {
          const row = rows[i];
          const rowProdCd = row.dataset.prodCd;
          const rowMatCd = row.dataset.matCd;
          
          // 같은 완제품 내에서 같은 원료코드가 이미 존재하면 false
          if (rowProdCd === prodCd && rowMatCd === matCd) {
              return false;
      }
    }
    return true;
  }

  handleInputEnter(e, input, container) {
    if (e.key === 'Enter') {
      e.preventDefault();
      e.stopPropagation();

      this.orderedInputs = this.getOrderedInputs(container);
      if (this.orderedInputs.length === 0) return;

      const currentIndex = this.orderedInputs.indexOf(input);
      const currentProdCd = input.dataset.prodCd;

      // '소모량'인 경우 특별 처리
      if (input.dataset.type === '소모량') {
          // 현재 행 이후의 같은 prod_cd를 가진 행에서 업체명 입력 필드 찾기
          const nextQtyUseInput = this.orderedInputs.find(
              (input, index) =>
                  index > currentIndex &&
                  input.dataset.type === '사용량' &&
                  input.dataset.prodCd === currentProdCd
          );

          if (nextQtyUseInput) {
              // 다음 행에 같은 prod_cd를 가진 업체명이 있으면 그곳으로 이동
              setTimeout(() => {
                  nextQtyUseInput.focus();
                  nextQtyUseInput.select();
              }, 100);
              return;
          } else {
              // 같은 prod_cd의 pBox로 이동
              const pBoxInput = this.orderedInputs.find(
                  (input) =>
                      input.dataset.type === 'pBox' &&
                      input.dataset.prodCd === currentProdCd
              );

              if (pBoxInput) {
                  setTimeout(() => {
                      pBoxInput.focus();
                      pBoxInput.select();
                  }, 100);
                  return;
              }
          }
      }

      // '사용량'인 경우 특별 처리
      if (input.dataset.type === '사용량') {
        // 다음 mBox 입력 필드 확인
        const nextMBoxInput = this.orderedInputs.find(
          (input, index) =>
            index > currentIndex &&
            input.dataset.type === 'mBox' &&
            input.dataset.prodCd === currentProdCd // 같은 prod_cd인 경우만 확인
        );

        if (nextMBoxInput) {
          // mBox가 존재하면 일반적인 경우처럼 다음 입력으로
          const nextInput = this.orderedInputs[currentIndex + 1];
          setTimeout(() => {
            nextInput.focus();
            nextInput.select();
          }, 100);
        } else {
          // mBox가 없으면 같은 prod_cd의 다음 업체명으로
          const nextQtyUseInput = this.orderedInputs.find(
            (input, index) =>
              index > currentIndex && input.dataset.type === '사용량'
          );

          // 다음 행의 prod_cd가 다른 경우 pBox로 이동
          if (
            nextQtyUseInput &&
            nextQtyUseInput.dataset.prodCd !== currentProdCd
          ) {
            const pBoxInput = this.orderedInputs.find(
              (input, index) =>
                input.dataset.type === 'pBox' &&
                input.dataset.prodCd === currentProdCd
            );

            if (pBoxInput) {
              setTimeout(() => {
                pBoxInput.focus();
                pBoxInput.select();
              }, 100);
              return;
            }
          }

          // 다음 행의 prod_cd가 같거나 pBox를 찾지 못한 경우 업체명으로 이동
          if (nextQtyUseInput) {
            setTimeout(() => {
              nextQtyUseInput.focus();
              nextQtyUseInput.select();
            }, 100);
          }
        }
        return;
      }

      // ---------------------------------------
      // 현재 입력이 '생산율'인 경우 특별 처리
      // ---------------------------------------
      if (input.dataset.type === '생산율') {
        // 기존의 'input' 이벤트 리스너 등록
        input.addEventListener('input', (e) => {
            const value = e.target.value;
            if (value === '') return;
            
            if (!/^\d*\.?\d*$/.test(value)) {
                e.target.value = value.replace(/[^\d.]/g, '');
            }
        });
        return;
      }

      // 일반적인 경우
      if (currentIndex >= 0 && currentIndex < this.orderedInputs.length - 1) {
        const nextInput = this.orderedInputs[currentIndex + 1];
        setTimeout(() => {
          nextInput.focus();
          nextInput.select();
        }, 100);
      }
    }
  }

  isNumeric(value) {
    return !isNaN(value) && value !== '';
  }

// ----------------------------------------------
// 모바일 화면 표시:: prod_yn = 'y' 인 경우만 표시
// ----------------------------------------------
  makeTabletr2Mobile(data) {
    // tables div가 없으면 생성
    if (!$('#tables').length) {
        $('body').append('<div id="tables"></div>');
    }
    const container = document.getElementById('tables');
    container.innerHTML = '';

    // 스타일 먼저 추가
    const styleElement = document.createElement('style');
    styleElement.textContent = `
        .product-box {
            margin: 5px;
            padding: 5px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgb(160, 158, 158);
            border: 2px solid transparent; /* 투명한 테두리 추가 */
            transition: all 0.3s ease; /* 부드러운 효과 */
            cursor: pointer; /* 클릭 가능함을 표시 */
        }
        .product-box.selected {
            border-color:rgb(255, 148, 148); /* 선택 시 파란색 테두리 */
            box-shadow: 0 0 5px rgba(33, 150, 243, 0.3); /* 글로우 효과 */
            background-color: #f8f9ff; /* 살짝 밝은 배경 */
        }
        .prod-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .prod-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #2196F3;
            margin-bottom: 5px;
        }
        .no-data-message {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
            background: #f9f9f9;
            border-radius: 4px;
            margin: 10px 0;
        }
        .prod-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.9em;
            color: #666;
        }
        .prod-details span {
            background: #f5f5f5;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .material-item {
            padding: 8px;
            margin: 5px 0;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .material-name {
            font-weight: 500;
            color: #333;
        }
        .material-details {
            font-size: 0.85em;
            color: #666;
            margin-top: 3px;
        }
        .prod-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            font-size: 0.8em;
            color: #888;
        }
        .no-data-box {
            margin: 20px;
            padding: 30px;
            text-align: center;
            background: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    `;
    document.head.appendChild(styleElement);

    // 데이터가 없는 경우 처리
    if (!data || data.length === 0) {
        container.innerHTML = `
            <div class="no-data-box">
                <div class="no-data-message">데이터 없음</div>
            </div>
        `;
        return;
    }

    let currentProdCd = null;
    data.forEach((item) => {
        const isNewProduct = currentProdCd !== item.prod_cd;
        currentProdCd = item.prod_cd;

        if (isNewProduct) {
            const prodBox = document.createElement('div');
            prodBox.className = 'product-box';
            prodBox.dataset.prodCd = item.prod_cd; // 제품 코드 저장

            // 클릭 이벤트 추가
            prodBox.addEventListener('click', (e) => {
              // 이전 선택 제거
              document.querySelectorAll('.product-box.selected').forEach(box => {
                  box.classList.remove('selected');
              });
              // 현재 항목 선택
              prodBox.classList.add('selected');
              
              // 기존의 handleRowClick 함수 호출 (선택적)
              if (this.handleRowClick) {
                  this.handleRowClick(prodBox, {
                      prod_cd: item.prod_cd,
                      mat_cd: item.mat_cd
                  });
              }
            });

            if (item['생산량'] > 0) {
                // 확정된 데이터 표시
                prodBox.innerHTML = `
                    <div class="prod-header">
                        <div class="prod-name">${item['완제품명']}</div>
                        <div class="prod-details">
                            <span>생산Box: ${item['pBox'] || '-'}</span>
                            <span>생산량: ${item['생산량'] || '-'}</span>
                            <span style="color:red; font-size:15px;">생산율: ${item['생산율'] || '-'}%</span>
                        </div>
                    </div>
                    <div class="materials-container"></div>
                    <div class="prod-footer">
                        <span>저장: ${item['저장일시'] || '-'}</span>
                        <span>담당: ${item['담당자'] || '-'}</span>
                    </div>
                `;
            } else {
                // 미확정 데이터 표시
                prodBox.innerHTML = `
                    <div class="prod-header">
                        <div class="prod-name">${item['완제품명']}</div>
                        <div class="no-data-message">데이터 없음</div>
                    </div>
                `;
            }
            container.appendChild(prodBox);
        }

        // 확정된 데이터의 경우에만 원료 정보 추가 :: 둘다 표시
        if (item['생산량'] > 0) {
            const materialsContainer = container.querySelector(`div.product-box:last-child .materials-container`);
            if (materialsContainer) {  // null 체크 추가
                const materialItem = document.createElement('div');
                materialItem.className = 'material-item';
                materialItem.innerHTML = `
                    <div class="material-name">${item['원료명']}</div>
                    <div class="material-details">
                        <div>입고LOT: ${item['입고LOT명'] || '-'}</div>
                        <div>
                            ${item['입고LOT명'] === '재고량을 입력' ? '재고량' : '사용량'}: ${item['사용량'] || '-'} ${item['원료단위'] || ''}
                            ${item['mBox'] ? ` | 사용Box: ${item['mBox']}` : ''}
                            ${item['소모량'] ? ` | 소모량: ${item['소모량']}` : ''}
                        </div>
                    </div>
                `;
                materialsContainer.appendChild(materialItem);
            }
        }
    });
}

  // --------------------------------
  // 저장할 제품 데이터 구조 생성
  // --------------------------------
  async saveProductData(prodCd) {
    const table = document.querySelector('#tables table');
    const rows = Array.from(table.querySelectorAll('tbody tr'));

    // 기본 데이터 구조 초기화
    const saveData = {
        prod_cd: prodCd,    // 제품 코드
        prod_qty_unit: '',  // 생산량 단위
        prod_qty: '',       // 생산량
        yield_rate: '',     // 생산율
        materials: []       // 자재 데이터 배열
    };

    // 현재 처리 중인 material 객체
    let currentMaterial = null;

    // 모든 행을 순회하면서 데이터 수집
    rows.forEach((row) => {
      // 현재 행이 해당 제품 코드의 행인지 확인
        if (row.dataset.prodCd !== prodCd) return;

        const rowMatCd = row.dataset.matCd;
        const rowInvYn = row.dataset.invYn ? row.dataset.invYn.toUpperCase() : '';
        // console.log('[[1014]dayProdClass.js]rowInvYn= ', rowInvYn);
      
        const inputs = row.querySelectorAll('input');
        // const selects = row.querySelectorAll('select');

        // 제품 레벨 데이터 수집
        inputs.forEach(input => {
            const type = input.dataset.type;
            const value = input.value;

            if (type === 'pBox') saveData.prod_qty_unit = value;
            if (type === '생산량') saveData.prod_qty = value;
            if (type === '생산율') saveData.yield_rate = value;
        });

        // 자재 데이터 처리
        if (rowMatCd) {
            // 현재 material 찾기 또는 새로 생성
            if (!currentMaterial || currentMaterial.mat_cd !== rowMatCd) {
                currentMaterial = saveData.materials.find(m => m.mat_cd === rowMatCd);
                if (!currentMaterial) {
                    currentMaterial = {
                        mat_cd: rowMatCd,
                        mat_qty_unit: '',  // mBox
                        mat_qty: '',       // 소모량
                        lots: []           // lot 데이터 배열
                    };
                    saveData.materials.push(currentMaterial);
                }
            }

            // material 데이터 업데이트
            inputs.forEach(input => {
                const type = input.dataset.type;
                const value = input.value;

                if (type === 'mBox') currentMaterial.mat_qty_unit = value;
                if (type === '소모량') currentMaterial.mat_qty = value;
            });

          // --------------------------------
          // lotin_id 데이터 처리:: rowInvYn = Y 인 경우 lotin_id 이 없어도 저장
          // --------------------------------
          let currentLot = currentMaterial.lots.find(lot => lot.row === row.rowIndex);
          const lotData = (rowInvYn === 'Y') ? '' : this.getLotData(row);

          // 사용량을 먼저 확인
          let qtyUse = '';
          inputs.forEach(input => {
              if (input.dataset.type === '사용량') {
                qtyUse = input.value;
              }
          });

          // console.log('[1067]lotData= ', lotData);
          // console.log('[1068]qtyUse= ', qtyUse);

          // lotData가 없더라도 사용량이 있으면 currentLot 생성
          if (lotData || qtyUse) {
              currentLot = {
                  row: row.rowIndex,
                  lotin_id: lotData ? lotData.lotinId : null, // lotData가 없으면 null
                  qty_use: qtyUse,
                  rowInvYn: rowInvYn // rowInvYn 값을 currentLot에 저장
              };

              // currentLot이 이미 존재하지 않는 경우에만 추가
            if (!currentMaterial.lots.some(lot => lot.row === row.rowIndex)) {
                // console.log('[1080]currentLot= ', currentLot);
                currentMaterial.lots.push(currentLot);
            }
          }
        }
    });

    // console.log('[1087]saveData= ', saveData);

    // 유효한 lotin 데이터만 필터링:: tdayprodmat_mst inv_yn = Y 인 경우 lotin_id 이 없어도 저장
    saveData.materials.forEach(material => {
        material.lots = material.lots.filter(lot => 
            (lot.lotin_id || lot.rowInvYn === 'Y') && lot.qty_use 
        );
    });

    return saveData;
}

  // lotin_id 찾기
  getLotData(row) {
    if (!row) {
        console.error('행이 전달되지 않았습니다.');
        return null;
    }

    try {
        // td[data-type] 또는 select[data-type] 찾기
        const lotCell = row.querySelector('td[data-type="입고LOT명"], td:has(select[data-type="입고LOT명"])');
        
        if (!lotCell) {
            console.log('셀 검색 상태:', {
                allCells: Array.from(row.cells).map(cell => ({
                    html: cell.innerHTML,
                    hasSelect: !!cell.querySelector('select'),
                    selectType: cell.querySelector('select')?.dataset.type
                }))
            });
            throw new Error('입고LOT명 셀을 찾을 수 없습니다.');
        }

        // select 요소 확인
        const lotSelect = lotCell.querySelector('select.lot-select');
        if (lotSelect) {
            const selectedValue = lotSelect.value;
            const selectedText = lotSelect.options[lotSelect.selectedIndex]?.text;
            
            return {
                lotName: selectedText || '',
                lotinId: selectedValue || ''
            };
        }

        // dataset.lotinId 확인
        if (lotCell.dataset.lotinId) {
            return {
                lotName: lotCell.textContent,
                lotinId: lotCell.dataset.lotinId
            };
        }
        
        throw new Error('유효한 LOT 정보를 찾을 수 없습니다.');

    } catch (error) {
        console.error('getLotData 오류:', error.message, {
            rowHTML: row.innerHTML,
            rowIndex: row.rowIndex
        });
        return null;
    }
  }

  // -------------------------------
  // 업데이트된 입력값을 DB 저장
  // -------------------------------
  async fSaveData(saveData) {

    const actualData = await saveData;  // Promise를 실제 데이터로 변환
    const date = document.getElementById('createDate').value;
    const url = this.dayProd_srv + '?category=save&increate_dt=' + date + '&user_id=' + user_id;   
    console.log('[ln1182::dayProdClass.js]URL= ', url);
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(actualData)
        });

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(JSON.stringify(errorData));
        }

        const result = await response.json();
        if (result.success === false) {
            // 실패 응답 처리
            const errorMessage = result.error || '저장에 실패했습니다.';
            alert(errorMessage);
            throw new Error(errorMessage);
      } else {
        alert('[' + result.prod_name + '] 저장되었습니다.');
      }

      return result;

    } catch (e) {
      console.error('저장 중 오류 발생:', e.message);
    }
  }

  // 조회 데이터 로드
  loadData() {
    const date = document.getElementById('createDate').value;
    // console.log('조회일자:' + date);
    // move();

    const url = this.dayProd_srv + `?category=search&increate_dt=${date}`;
    console.log('[ln1221::dayProdClass.js]url= ' + url);

    fetch(url)
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          this.data = result.data;
          if (platForm == 'computer') {
            this.makeTableHead();
            this.makeTableBody(this.data);
          } else {
            this.makeTabletr2Mobile(this.data);
          }
        }
      })
      .catch((error) => {
        console.error(
          '에러 발생: 데이터를 불러오는 중 오류가 발생했습니다.',
          error
        );
      });
    // move_stop();
  }

  deleteData() {
    const date = document.getElementById('createDate').value;

    if (!confirm(`${date} 날짜의 모든 데이터를 삭제하시겠습니까?`)) {
      return;
    }

    const url = this.dayProd_srv + `?category=delete&increate_dt=${date}`;
    console.log('[ln1253::dayProdClass.js]url= ' + url);

    fetch(url)
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          alert('삭제되었습니다.');
          this.loadData(); // 데이터 다시 로드
        } else {
          alert('삭제 실패: ' + (result.error || '알 수 없는 오류'));
        }
      })
      .catch((error) => {
        console.error('Error:', error);
        alert('삭제 중 오류가 발생했습니다.');
      });
  }

  handleRowClick(row, item) {
    // 이전에 선택된 row가 있다면 선택 해제
    if (this.selectedRow) {
      // 같은 제품의 row를 클릭한 경우는 선택 해제하지 않음
      if (this.selectedRow.dataset.prodCd !== row.dataset.prodCd) {
        this.selectedRow.classList.remove('selected');

        // 같은 제품 코드를 가진 모든 row의 선택 해제
        const rows = document.querySelectorAll(
          `tr[data-prod-cd="${this.selectedRow.dataset.prodCd}"]`
        );
        rows.forEach((r) => r.classList.remove('selected'));
      } else {
        // 같은 제품의 row인 경우 선택 상태 유지
        return;
      }
    }

    // 새로운 row가 이전에 선택된 row와 다르면 선택
    if (this.selectedRow !== row) {
      // 같은 제품 코드를 가진 모든 row를 선택
      const rows = document.querySelectorAll(
        `tr[data-prod-cd="${row.dataset.prodCd}"]`
      );
      rows.forEach((r) => r.classList.add('selected'));

      this.selectedRow = row;
    } else {
      // 같은 row를 다시 클릭하면 선택 해제
      this.selectedRow = null;
    }
  }

  // 이벤트 리스너 추가
  addEventListeners(input, row, item, container) {
    // 클릭 시 처리
    input.addEventListener('focus', (e) => {
      e.stopPropagation();
      e.target.select();
      // 색깔 변경
      this.handleRowClick(row, item);
    });

    // 엔터 입력 시 처리
    input.addEventListener('keydown', (e) => {
      this.handleInputEnter(e, input, container);
    });
  }

  // 선택된 row 데이터 반환 메서드
  getSelectedData() {
    if (this.selectedRow) {
      const prodCd = this.selectedRow.dataset.prodCd;
      return this.data.filter((item) => item.prod_cd === prodCd);
    }
    return null;
  }

  // 생산확정
  finProd() {
    // console.log('ln1218::finProd()');

    const increate_dt = document.getElementById('createDate').value;
    if (!increate_dt) {
      alert('날짜를 선택해주세요.');
      return;
    }

    if (!confirm('선택한 날짜의 생산을 확정하시겠습니까?')) {
      return;
    }

    const url = this.dayProd_srv + `?category=finProd&increate_dt=${increate_dt}`;
    console.log('[ln1344::dayProdClass.js]url= ' + url);

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          alert('생산이 확정되었습니다.');
          // 필요한 경우 테이블 새로고침
          this.loadData();
        } else {
          alert('생산확정 실패: ' + (result.error || '알 수 없는 오류'));
        }
      })
      .catch((error) => {
        console.error('Error:', error);
        alert('서버 통신 중 오류가 발생했습니다.');
      });
  }

  // 확정취소
  finCancel() {
    const increate_dt = document.getElementById('createDate').value;
    if (!increate_dt) {
      alert('날짜를 선택해주세요.');
      return;
    }

    if (!confirm('선택한 날짜의 생산확정을 취소하시겠습니까?')) {
      return;
    }

    const url = this.dayProd_srv + `?category=finCancel&increate_dt=${increate_dt}`;
    console.log('[ln1381::dayProdClass.js]url= ' + url);

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          alert('생산확정이 취소되었습니다.');
          // 필요한 경우 테이블 새로고침
          this.loadData();
        } else {
          alert('확정취소 실패: ' + (result.error || '알 수 없는 오류'));
        }
      })
      .catch((error) => {
        console.error('Error:', error);
        alert('서버 통신 중 오류가 발생했습니다.');
      });
  }
} // class end

// 전역 인스턴스 생성
const dayProd = new DayProdClass();

// 공통 스타일을 적용하는 함수
function applyCommonCellStyle(cell, align = 'left') {
    cell.style.height = '35px';
    cell.style.lineHeight = '35px';
    cell.style.boxSizing = 'border-box';
    cell.style.verticalAlign = 'middle';
    cell.style.textAlign = align;
    cell.style.padding = '0 3px';
}