class subulPdClass {
  constructor() {
    this.data = [];
    this.table1 = null; // 테이블 DOM 요소를 저장할 프로퍼티
    this.platForm = 'computer'; // 기본값 설정

    // 주간 네비게이션 UI 요소
    this.prevWeekBtn = null;
    this.nextWeekBtn = null;
    this.currentWeekEl = null;
  }

  // ISO 8601 표준에 따른 연간 주차 계산 함수
  getWeekNumber(d) {
    // 날짜 객체를 복사하여 원본을 수정하지 않도록 함
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    // 주의 목요일을 기준으로 주차를 계산함 (ISO 8601 표준)
    // 일요일(0)을 7로 처리하여 계산을 용이하게 함
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    // 연초(1월 1일)를 기준으로 몇 주가 지났는지 계산
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
  }

  // 주차 표시 업데이트
  updateWeekDisplay() {
    const fromDateEl = document.getElementById('from_date_pd');
    if (!fromDateEl || !this.currentWeekEl) return;

    const fromDate = new Date(fromDateEl.value);
    // fromDate는 일요일이므로, 주차 계산의 기준이 되는 월요일로 변경 (+1일)
    const mondayDate = new Date(fromDate);
    mondayDate.setUTCDate(mondayDate.getUTCDate() + 1);

    const month = mondayDate.getUTCMonth() + 1;
    const week = this.getWeekNumber(mondayDate);
    this.currentWeekEl.textContent = `${month}월 ${week}주`;
  }

  // 주차 이동
  navigateWeek(days) {
    const fromDateEl = document.getElementById('from_date_pd');
    const toDateEl = document.getElementById('to_date_pd');
    if (!fromDateEl || !toDateEl) return;

    const fromDate = new Date(fromDateEl.value);
    fromDate.setUTCDate(fromDate.getUTCDate() + days);

    const newToDate = new Date(fromDate);
    newToDate.setUTCDate(newToDate.getUTCDate() + 6);

    fromDateEl.value = fromDate.toISOString().slice(0, 10);
    toDateEl.value = newToDate.toISOString().slice(0, 10);

    this.loadData();
  }

  // 데스크톱용 테이블 헤더 생성
  makeTableHead() {
    this.table1 = document.createElement("table");
    this.table1.id = "pd-table";
    this.table1.classList.add("subul-pd-table"); // 클래스 추가

    if (this.table1.querySelector('thead')) {
      return;
    }

    const header = this.table1.createTHead();
    const tr = header.insertRow(-1);

    // 데이터에서 날짜 키를 동적으로 추출하고 정렬
    const dates = Object.keys(this.data[0]).filter(key => key !== '구분' && key !== '합계').sort();
    
    // 열 순서: 비고 (주차 정보), 날짜들, 합계
    const th_remarks = document.createElement("th");
    if (dates.length > 0) {
        const firstDate = new Date(dates[0]); // dates[0]은 항상 일요일
        
        // 월요일을 기준으로 주차를 계산하기 위해 날짜를 하루 뒤로 설정
        const mondayDate = new Date(firstDate);
        mondayDate.setUTCDate(mondayDate.getUTCDate() + 1);

        const month = mondayDate.getUTCMonth() + 1; // 월요일이 속한 월
        const weekNumber = this.getWeekNumber(mondayDate); // 월요일 기준 주차
        th_remarks.textContent = `${month}월 ${weekNumber}주차`;
    } else {
        th_remarks.textContent = "비고";
    }
    tr.appendChild(th_remarks);

    const days = ['일', '월', '화', '수', '목', '금', '토'];

    dates.forEach(dateStr => {
        const th = document.createElement("th");
        const date = new Date(dateStr);
        // UTC 기준으로 날짜를 계산하여 시간대 문제 방지
        const month = date.getUTCMonth() + 1;
        const day = date.getUTCDate();
        const dayOfWeek = days[date.getUTCDay()];
        th.textContent = `${month}/${day}(${dayOfWeek})`;
        tr.appendChild(th);
    });
    
    const th_total = document.createElement("th");
    th_total.textContent = "합계";
    tr.appendChild(th_total);
  }

  // 데스크톱용 테이블 바디 생성
  makeTableBody(datas) {
    if (!datas || datas.length === 0) {
      return 0;
    }

    const tbody = this.table1.createTBody();
    const dates = Object.keys(datas[0]).filter(key => key !== '구분' && key !== '합계').sort();

    datas.forEach(rowData => {
      const row = tbody.insertRow();
      
      if (rowData['구분'] === '예상판매') {
        row.classList.add('expected-sale');
      }
      if (rowData['구분'] === '실제판매') {
        row.classList.add('actual-sale');
        row.style.backgroundColor = 'yellow';
      }

      // 1. 비고 (구분)
      const cellGuBun = row.insertCell();
      cellGuBun.textContent = rowData['구분'];

      // 2. 날짜별 데이터
      dates.forEach((date, index) => {
        const cell = row.insertCell();
        const value = rowData[date];
        const isNumeric = value !== null && value !== undefined && !isNaN(value) && String(value).trim() !== '' && value !== '-';
        
        cell.textContent = isNumeric ? Number(value).toLocaleString() : '-';
        cell.style.textAlign = 'right';

        // '생산재고' 행의 숫자 셀에만 편집 기능 추가
        if (rowData['구분'] === '생산재고' && isNumeric) {
          cell.classList.add('editable'); // 스타일링을 위한 클래스
          cell.addEventListener('click', () => this.makeCellEditable(cell, date, 'saengsan'));
        }

        // '통관재고' 행의 일요일(첫번째) 숫자 셀에만 편집 기능 추가
        if (rowData['구분'] === '통관재고' && isNumeric && index === 0) {
            cell.classList.add('editable');
            cell.addEventListener('click', () => this.makeCellEditable(cell, date, 'tongwan'));
        }

        // '원료판매' 행의 모든 셀에 편집 기능 추가 (숫자이거나 '-'인 경우)
        if (rowData['구분'] === '원료판매') {
            cell.classList.add('editable');
            cell.addEventListener('click', () => this.makeCellEditable(cell, date, 'rowsale'));
        }
      });

      // 3. 합계
      const cellTotal = row.insertCell();
      cellTotal.textContent = Number(rowData['합계']) ? Number(rowData['합계']).toLocaleString() : '-';
      cellTotal.style.textAlign = 'right';
    });

    const divContainer = document.getElementById("tables-pd");
    divContainer.innerHTML = ""; // 이전 내용 초기화
    divContainer.appendChild(this.table1);

    return datas.length;
  }

  // 모바일용 카드 뷰 생성
  makeTableMobile(datas) {
    const container = document.getElementById("tables-pd");
    if (!container) {
      console.error("Element with id 'tables-pd' not found");
      return;
    }
    container.innerHTML = "";

    const from_date = document.getElementById('from_date_pd').value;
    const to_date = document.getElementById('to_date_pd').value;

    // 날짜 목록 생성 (타임존 문제 방지)
    const dateRange = [];
    let currentDate = new Date(from_date);
    const endDate = new Date(to_date);

    while (currentDate <= endDate) {
      dateRange.push(currentDate.toISOString().slice(0, 10));
      currentDate.setUTCDate(currentDate.getUTCDate() + 1);
    }

    // 각 카테고리별로 카드 생성
    datas.forEach((categoryData, index) => {
      const category = categoryData['구분'];
      const card = document.createElement('div');
      // 카드를 기본적으로 접힌 상태로 설정 (expanded 클래스 제거)
      card.className = 'mobile-card'; 

      const header = document.createElement('div');
      header.className = 'card-header';

      // 예상판매, 실제판매 헤더 배경색 설정
      if (category === '예상판매') {
        header.style.backgroundColor = 'peachpuff';
      } else if (category === '실제판매') {
        header.style.backgroundColor = 'yellow';
      }

      const total = categoryData['합계'] !== undefined ? categoryData['합계'] : 0;
      // 숫자에 콤마 추가 (e.g., 1832 -> 1,832)
      const formattedTotal = new Intl.NumberFormat('ko-KR').format(total);

      header.innerHTML = `
        <span class="header-title">${category}</span>
        <span class="header-total">${formattedTotal}</span>
        <span class="arrow">▼</span>
      `;
      card.appendChild(header);

      const content = document.createElement('div');
      content.className = 'card-content';
      
      dateRange.forEach((date, index) => {
        const value = categoryData[date] !== undefined && categoryData[date] !== null ? categoryData[date] : 0;
        
        const dateObj = new Date(date);
        const month = dateObj.getUTCMonth() + 1;
        const day = dateObj.getUTCDate();
        const weekDay = ['일', '월', '화', '수', '목', '금', '토'][dateObj.getUTCDay()];
        const formattedDate = `${month}/${day}(${weekDay}):`;

        const dataRow = document.createElement('div');
        dataRow.className = 'data-row-pd';
        
        // 0이거나 NaN인 경우 '-'로 표시
        const isZeroOrNaN = value === 0 || value === '0' || isNaN(value) || value === null || value === undefined;
        const valueClass = isZeroOrNaN ? 'pd-value-label zero-value' : 'pd-value-label';
        const formattedValue = isZeroOrNaN ? '-' : new Intl.NumberFormat('ko-KR').format(value);

        const valueSpan = document.createElement('span');
        valueSpan.className = valueClass;
        valueSpan.textContent = formattedValue;

        dataRow.innerHTML = `<span class="pd-date-label">${formattedDate}</span>`;
        dataRow.appendChild(valueSpan);

        // '생산재고' 항목의 숫자 값에만 편집 기능 추가
        const isNumeric = !isZeroOrNaN && value !== '-' && !isNaN(value) && String(value).trim() !== '';
        if (category === '생산재고' && isNumeric) {
          dataRow.classList.add('editable'); // 스타일링을 위한 클래스
          valueSpan.addEventListener('click', () => this.makeCellEditable(valueSpan, date, 'saengsan'));
        }
        
        // '통관재고' 항목의 일요일(첫번째) 숫자 값에만 편집 기능 추가
        if (category === '통관재고' && isNumeric && index === 0) {
            dataRow.classList.add('editable');
            valueSpan.addEventListener('click', () => this.makeCellEditable(valueSpan, date, 'tongwan'));
        }

        // '원료판매' 항목의 모든 값에 편집 기능 추가 (숫자이거나 0인 경우)
        if (category === '원료판매') {
            dataRow.classList.add('editable');
            valueSpan.addEventListener('click', () => this.makeCellEditable(valueSpan, date, 'rowsale'));
        }

        content.appendChild(dataRow);
      });

      card.appendChild(content);

      // 토글 이벤트 추가
      header.addEventListener('click', () => {
        card.classList.toggle('expanded');
        const arrow = header.querySelector('.arrow');
        if (card.classList.contains('expanded')) {
          arrow.innerHTML = '▲';
        } else {
          arrow.innerHTML = '▼';
        }
      });

      container.appendChild(card);
    });
  }

  // 셀을 편집 가능한 input으로 변경하는 함수
  makeCellEditable(cell, date, stockType) {
    if (cell.querySelector('input')) {
        return; // 이미 편집 중이면 중복 실행 방지
    }

    const originalValue = cell.textContent.replace(/,/g, '').trim();
    const numValue = originalValue === '-' ? '0' : originalValue;
    cell.innerHTML = ''; // 셀 내용 초기화

    const input = document.createElement('input');
    input.type = 'number';
    input.value = numValue;
    input.className = 'editable-input'; // 스타일링을 위한 클래스

    const saveOrRevert = () => {
      const newValue = input.value;
      if (newValue !== numValue && newValue.trim() !== '') {
        if (stockType === 'saengsan') {
            this.updateStock(date, newValue); // 값이 변경되었으면 저장
        } else if (stockType === 'tongwan') {
            this.updateTongwanStock(date, newValue);
        } else if (stockType === 'rowsale') {
            this.updateRowSale(date, newValue);
        }
      } else {
        cell.textContent = numValue === '0' ? '-' : Number(numValue).toLocaleString(); // 아니면 원래 값으로 복원
      }
    };

    input.addEventListener('blur', saveOrRevert);

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            input.blur(); // Enter 키 입력 시 저장
        } else if (e.key === 'Escape') {
            input.removeEventListener('blur', saveOrRevert); // Escape 키 입력 시 blur 이벤트 없이 복원
            cell.textContent = numValue === '0' ? '-' : Number(numValue).toLocaleString();
        }
    });

    cell.appendChild(input);
    input.focus();
  }

  // 서버로 통관재고 데이터를 업데이트하는 함수
  updateTongwanStock(date, value) {
      move(); // 로딩 시작

      const url = `${window.subul_pd_srv}`;
      const params = new URLSearchParams({
          category: 'update_tongwan_stock',
          invt_date: date,
          stock_quantity: value
      });

      fetch(url, {
          method: 'POST',
          headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: params
      })
      .then(response => response.json())
      .then(result => {
          if (result.status !== 'success') {
              alert('통관재고 업데이트 실패: ' + result.message);
          }
          this.loadData(); // 성공/실패 여부와 관계없이 최신 데이터로 화면 갱신
      })
      .catch(error => {
          console.error('Error updating tongwan stock:', error);
          alert('통관재고 업데이트 중 오류 발생');
          this.loadData(); // 오류 발생 시에도 최신 데이터로 화면 갱신
      });
  }

  // 서버로 생산재고 데이터를 업데이트하는 함수
  updateStock(date, value) {
    move(); // 로딩 시작

    const url = `${window.subul_pd_srv}`;
    const params = new URLSearchParams({
        category: 'update_saengsan_stock',
        invt_date: date,
        cnt_fillets: value
    });

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(result => {
        if (result.status !== 'success') {
            alert('재고 업데이트 실패: ' + result.message);
        }
        this.loadData(); // 성공/실패 여부와 관계없이 최신 데이터로 화면 갱신
    })
    .catch(error => {
        console.error('Error updating stock:', error);
        alert('재고 업데이트 중 오류 발생');
        this.loadData(); // 오류 발생 시에도 최신 데이터로 화면 갱신
    });
  }

  // 서버로 원료판매 데이터를 업데이트하는 함수
  updateRowSale(date, value) {
    move(); // 로딩 시작

    const url = `${window.subul_pd_srv}`;
    const params = new URLSearchParams({
        category: 'update_row_sale',
        invt_date: date,
        row_sale: value
    });

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(result => {
        if (result.status !== 'success') {
            alert('원료판매 업데이트 실패: ' + result.message);
        }
        this.loadData(); // 성공/실패 여부와 관계없이 최신 데이터로 화면 갱신
    })
    .catch(error => {
        console.error('Error updating row sale:', error);
        alert('원료판매 업데이트 중 오류 발생');
        this.loadData(); // 오류 발생 시에도 최신 데이터로 화면 갱신
    });
  }

  init() {
    const fromDateEl = document.getElementById('from_date_pd');
    const toDateEl = document.getElementById('to_date_pd');
    const searchBtn = document.getElementById('searchBtn-pd');
    const predictBtn = document.getElementById('predictBtn-pd');
    const predictBtnMobile = document.getElementById('predictBtn-pd-mobile');

    // 주간 네비게이션 요소
    this.prevWeekBtn = document.getElementById('prev-week-pd');
    this.nextWeekBtn = document.getElementById('next-week-pd');
    this.currentWeekEl = document.getElementById('current-week-pd');

    if (!fromDateEl || !toDateEl || !searchBtn || !this.prevWeekBtn || !this.nextWeekBtn || !this.currentWeekEl) {
      console.error('수불추적관리: 필수 UI 요소를 찾을 수 없습니다. HTML 구조를 확인하세요.');
      return;
    }

    // from_date_pd 요소가 변경되면 해당 주의 일요일을 찾아 시작일로 설정
    fromDateEl.addEventListener('change', () => {
        const selectedDate = new Date(fromDateEl.value);
        
        // 선택된 날짜의 요일 (0=일요일, 1=월요일, ..., 6=토요일)
        const dayOfWeek = selectedDate.getUTCDay();
        
        // 선택된 날짜에서 요일만큼 날짜를 빼서 해당 주의 일요일을 계산
        const sundayOfSelectedWeek = new Date(selectedDate);
        sundayOfSelectedWeek.setUTCDate(selectedDate.getUTCDate() - dayOfWeek);

        // 시작일(일요일)과 종료일(+10일) 계산
        const startDate = sundayOfSelectedWeek;
        const endDate = new Date(startDate);
        endDate.setUTCDate(startDate.getUTCDate() + 6);
        
        // 계산된 날짜를 yyyy-mm-dd 형식으로 변환하여 input 필드에 설정
        fromDateEl.value = startDate.toISOString().slice(0, 10);
        toDateEl.value = endDate.toISOString().slice(0, 10);

        // 날짜 변경 후 자동으로 데이터 조회 실행
        this.loadData();
    });

    // 조회 버튼 이벤트 리스너
    searchBtn.addEventListener('click', () => {
      this.loadData();
    });
    
    // 예측 실행 버튼 이벤트 리스너 (데스크톱)
    if (predictBtn) {
        predictBtn.addEventListener('click', () => {
            this.runPrediction();
        });
    }
    
    // 예측 실행 버튼 이벤트 리스너 (모바일)
    if (predictBtnMobile) {
        predictBtnMobile.addEventListener('click', () => {
            this.runPredictionMobile();
        });
    }

    // 주간 네비게이션 버튼 이벤트 리스너
    this.prevWeekBtn.addEventListener('click', () => this.navigateWeek(-7));
    this.nextWeekBtn.addEventListener('click', () => this.navigateWeek(7));

    // 최초 데이터 로드
    this.loadData();
  }

  // 수입량 예측 실행 (데스크톱)
  runPrediction() {
    const weeks = document.getElementById('predict-weeks').value;
    const days = document.getElementById('safety-stock-days').value;
    const nextWeekImportPlan = document.getElementById('next-week-import-plan').value;
    const resultDiv = document.getElementById('prediction-result');
    
    this.executePrediction(weeks, days, nextWeekImportPlan, resultDiv);
  }

  // 수입량 예측 실행 (모바일)
  runPredictionMobile() {
    const weeks = document.getElementById('predict-weeks-mobile').value;
    const days = document.getElementById('safety-stock-days-mobile').value;
    const nextWeekImportPlan = document.getElementById('next-week-import-plan-mobile').value;
    const resultDiv = document.getElementById('prediction-result');
    
    this.executePrediction(weeks, days, nextWeekImportPlan, resultDiv);
  }

  // 공통 예측 실행 로직
  executePrediction(weeks, days, nextWeekImportPlan, resultDiv) {

    if (!weeks || !days || parseInt(weeks, 10) <= 0) {
        alert('유효한 예측 기준(주)과 안전 재고(일)를 입력하세요.');
        return;
    }

    move(); // 로딩 시작
    resultDiv.style.display = 'none'; // 이전 결과 숨기기

    const url = `${window.subul_pd_srv}`;
    const params = new URLSearchParams({
        category: 'run_prediction',
        predict_weeks: weeks,
        safety_stock_days: days,
        next_week_import_plan: nextWeekImportPlan || 0
    });

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            const data = result.data;

            const surplusText = (data.surplus_stock && data.surplus_stock > 0)
                ? `<span style="color: blue; font-weight: normal; font-size: 0.9em; margin-left: 10px;">
                       (예상 초과 재고: ${data.surplus_stock.toLocaleString()} Box)
                   </span>`
                : '';

            const formulaText = `
                <span style="font-weight: normal; font-size: 0.9em; margin-left: 10px;">
                    (계산식: G - F)
                </span>`;

            const resultTitle = `
                <div style="background-color: #eef7ff; border-left: 5px solid #007bff; padding: 15px; margin-bottom: 10px; border-radius: 4px;">
                    <span style="font-size: 1.1em; color: #333; display: block;"><strong>차차주::수입 발주 권장량</strong></span>
                    <span style="font-size: 2.2em; color: #0056b3; font-weight: 900; line-height: 1.2;">
                        ${data.predicted_import_needed.toLocaleString()} <span style="font-size: 0.5em; font-weight: normal; color: #555;">Box</span>
                    </span>
                    <div style="margin-top: 5px;">
                        ${surplusText}
                        ${formulaText}
                    </div>
                </div>`;

            const summaryText = `
                <p style="font-size: 0.9em; margin-top: 5px; color: #555; margin-bottom: 15px;">
                    요약: 차차주에 필요한 물량(G)을 확보하기 위해, 차주 말에 남을 것으로 예상되는 재고량(F)을 제외한 권장 발주량입니다. (G)는 주간 평균 사용량과 안전재고를 더한 값이며, (F)는 (C)지난 주말 통관재고와 금주/차주 통관 계획량에서 2주치 사용량을 뺀 값입니다.
                </p>
            `;

            resultDiv.innerHTML = `
                ${resultTitle}
                ${summaryText}
                <ul>
                    <li>(A) ${weeks}주 평균 주간 실제작업량: ${data.avg_weekly_workload.toLocaleString()} Box</li>
                    <li>(B) ${days}일 안전 재고: ${data.safety_stock.toLocaleString()} Box <span style="font-size: 0.8em; color: #777; margin-left: 5px;">(A의 일평균 작업량)</span></li>
                    <li>(C) 지난 주말 통관재고: ${data.stock_at_end_of_last_week.toLocaleString()} Box</li>
                    <li>(D) 금주 통관 계획량: ${data.incoming_this_week.toLocaleString()} Box</li>
                    <li>(E) 차주 통관 계획량: ${data.incoming_next_week.toLocaleString()} Box</li>
                    <li style="border-top: 1px solid #ccc; padding-top: 8px; margin-top: 8px;">(F) 차주 말 예상 재고 (C+D+E - 2*A): ${data.projected_stock_end_of_next_week.toLocaleString()} Box</li>
                    <li>(G) 주간 총 필요량 (A+B): ${data.total_needed_for_week_after_next.toLocaleString()} Box</li>
                </ul>
            `;
            resultDiv.style.display = 'block';
            
            // DB에서 조회한 차주 통관 계획량을 input 필드에 표시
            this.updateNextWeekImportPlanInput(data.incoming_next_week_db);
        } else {
            alert('예측 실패: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error running prediction:', error);
        alert('예측 실행 중 오류 발생');
    })
    .finally(() => {
        move_stop();
    });
  }

  // DB에서 조회한 차주 통관 계획량을 input 필드에 업데이트
  updateNextWeekImportPlanInput(dbValue) {
    const desktopInput = document.getElementById('next-week-import-plan');
    const mobileInput = document.getElementById('next-week-import-plan-mobile');
    
    if (desktopInput && dbValue > 0) {
      desktopInput.value = dbValue;
    }
    
    if (mobileInput && dbValue > 0) {
      mobileInput.value = dbValue;
    }
  }

  // 서버에서 데이터 로드
  loadData() {
    const fromDateEl = document.getElementById('from_date_pd');
    const toDateEl = document.getElementById('to_date_pd');

    if (!fromDateEl || !toDateEl) {
      console.error("loadData: 날짜 입력 요소를 찾을 수 없습니다.");
      const tableContainer = document.getElementById("tables-pd");
      if (tableContainer) {
        tableContainer.innerHTML = `<div class="error-message">오류: 날짜 입력 필드를 찾을 수 없습니다. 페이지를 새로고침하거나 관리자에게 문의하세요.</div>`;
      }
      return;
    }

    this.updateWeekDisplay();

    move();

    const from_date = fromDateEl.value;
    const to_date = toDateEl.value;
    const url = `${window.subul_pd_srv}?category=search_pd&from_date=${from_date}&to_date=${to_date}`;

    const tableContainer = document.getElementById("tables-pd");
    tableContainer.innerHTML = "";

    fetch(url)
      .then(response => {
        if (!response.ok) throw new Error("네트워크 응답이 실패했습니다.");
        return response.json();
      })
      .then(result => {
        if (result.status === 'success') {
          this.data = result.data;
          const tableContainer = document.getElementById("tables-pd");
          if (!tableContainer) {
            console.error("결과 테이블 컨테이너 'tables-pd'를 찾을 수 없습니다.");
            return;
          }
          tableContainer.innerHTML = ""; // 기존 내용을 초기화

          // 화면 너비에 따라 모바일/데스크톱 뷰를 결정 (CSS breakpoint: 768px)
          if (window.innerWidth <= 768) {
            this.makeTableMobile(this.data);
          } else {
            this.makeTableHead(); // 데스크톱 테이블 헤더 생성
            this.makeTableBody(this.data);
          }

        } else {
          alert(result.message || '데이터 로드에 실패했습니다.');
          throw new Error(result.message || "데이터 로드 실패");
        }
      })
      .catch(error => {
        console.error("데이터 로드 중 오류 발생:", error);
        tableContainer.innerHTML = `<div class='no-data-message'>오류: ${error.message}</div>`;
      })
      .finally(() => {
        move_stop();
      });
  }
}