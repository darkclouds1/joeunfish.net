<?php
/**
 * 공통 네비게이션 메뉴 컴포넌트
 * 모든 플러그인 페이지에서 사용
 */

function renderJofNavigation() {
    ?>
    <!-- 메뉴 버튼 - form-menu 우측에 동적으로 삽입됨 -->
    <button id="jof-menu-toggle" class="jof-floating-menu-btn" title="전체 메뉴" style="display: none;">
        <svg class="menu-icon-svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <rect y="2" width="20" height="2" fill="white"/>
            <rect y="9" width="20" height="2" fill="white"/>
            <rect y="16" width="20" height="2" fill="white"/>
        </svg>
        <span class="menu-label">메뉴</span>
    </button>

    <!-- 슬라이드 메뉴 패널 -->
    <div id="jof-slide-menu" class="jof-slide-menu">
        <div class="jof-menu-header">
            <h2>조은수산 메뉴</h2>
            <button id="jof-menu-close" class="jof-close-btn">✕</button>
        </div>
        
        <div class="jof-menu-content">
            <!-- 메인 -->
            <div class="jof-menu-item" onclick="location.href='/menu/'">
                <span class="menu-icon">🏠</span>
                <span class="menu-text">메인 메뉴</span>
            </div>

            <!-- 입출고 그룹 -->
            <div class="jof-menu-group">
                <div class="jof-menu-group-title">📦 입출고 관리</div>
                <div class="jof-menu-item" onclick="location.href='/purchase/'">
                    <span class="menu-text">통관|매입</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/barcodein/'">
                    <span class="menu-text">1층입고</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/barcodeinv/'">
                    <span class="menu-text">1층출고(연어)</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/barcodeout/'">
                    <span class="menu-text">3층생산</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/barcodeoutmap/'">
                    <span class="menu-text">생산(MAP)</span>
                </div>
            </div>

            <!-- CO 그룹 -->
            <div class="jof-menu-group">
                <div class="jof-menu-group-title">🏪 COSTCO</div>
                <div class="jof-menu-item" onclick="location.href='/ediagis/'">
                    <span class="menu-text">(CO)주문관리</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/barcodeinv2/'">
                    <span class="menu-text">(CO)상품출고</span>
                </div>
                <!-- <div class="jof-menu-item" onclick="location.href='/orderSummary-month/'">
                    <span class="menu-text">(CO)납기통계</span>
                </div> -->
            </div>

            <!-- GS 그룹 -->
            <div class="jof-menu-group">
                <div class="jof-menu-group-title">🏪 GS리테일</div>
                <div class="jof-menu-item" onclick="location.href='/ediagisgs/'">
                    <span class="menu-text">(GS)주문관리</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/barcodeinv2gs/'">
                    <span class="menu-text">(GS)상품출고</span>
                </div>
                <!-- <div class="jof-menu-item" onclick="location.href='/orderSummary-month-gs/'">
                    <span class="menu-text">(GS)납기통계</span>
                </div> -->
            </div>

            <!-- 재고 그룹 -->
            <div class="jof-menu-group">
                <div class="jof-menu-group-title">📊 재고/생산 관리</div>
                <div class="jof-menu-item" onclick="location.href='/octopusInvt/'">
                    <span class="menu-text">냉동자재(재고)</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/ascInvt/'">
                    <span class="menu-text">ASC수불</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/ascinvt-3/'">
                    <span class="menu-text">ASC더조은</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/dayprod/'">
                    <span class="menu-text">일일생산</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/unitPrice/'">
                    <span class="menu-text">단가확인</span>
                </div>
            </div>

            <!-- 통계/분석 그룹 -->
            <!-- <div class="jof-menu-group">
                <div class="jof-menu-group-title">📈 통계/분석</div>
                <div class="jof-menu-item" onclick="location.href='/orderSummary/'">
                    <span class="menu-text">주문통계</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/predicinv/'">
                    <span class="menu-text">주문예측</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/dashboard/'">
                    <span class="menu-text">📊 대시보드</span>
                </div>
            </div> -->

            <!-- 수불 그룹 -->
            <div class="jof-menu-group">
                <div class="jof-menu-group-title">📋 수불관리</div>
                <div class="jof-menu-item" onclick="location.href='/subul/'">
                    <span class="menu-text">수불관리</span>
                </div>
                <div class="jof-menu-item" onclick="location.href='/subul-trace/'">
                    <span class="menu-text">추적(연어필렛)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 오버레이 배경 -->
    <div id="jof-menu-overlay" class="jof-menu-overlay"></div>

    <style>
        /* form-menu 공통 스타일 */
        .form-menu {
            color: white;
            padding: 5px 5px;
            text-align: center;
            font-size: 17px;
            background-color: #2c80cf;
            border-radius: 8px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            margin-top: 5px;
            margin-bottom: 5px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        /* search-row에 메뉴 버튼 배치 */
        .search-row {
            position: relative !important;
        }
        
        /* 메뉴 버튼 - search-row 우측에 배치 */
        button.jof-floating-menu-btn,
        #jof-menu-toggle.jof-floating-menu-btn {
            /* position은 JavaScript로 동적 설정 */
            padding: 8px 14px !important;
            background: rgba(44, 128, 207, 0.9) !important;
            backdrop-filter: blur(3px) !important;
            -webkit-backdrop-filter: blur(3px) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            border-radius: 6px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;
            cursor: pointer !important;
            display: none !important; /* 기본적으로 숨김 */
            flex-direction: row !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 7px !important;
            transition: all 0.3s ease !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
            min-width: fit-content !important;
            width: fit-content !important;
            max-width: fit-content !important;
            height: auto !important;
            min-height: auto !important;
            max-height: none !important;
            flex-shrink: 0 !important;
            z-index: 10 !important;
            font-size: 14px !important;
            line-height: 1.2 !important;
            vertical-align: middle !important;
            text-transform: none !important;
            letter-spacing: 0.3px !important;
            font-weight: 600 !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        
        /* 위치가 설정된 후 표시 */
        button.jof-floating-menu-btn.jof-positioned,
        #jof-menu-toggle.jof-floating-menu-btn.jof-positioned {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* search-row 내부에 있을 때 - 우측에 절대 위치 */
        .search-row button.jof-floating-menu-btn,
        .search-row #jof-menu-toggle.jof-floating-menu-btn {
            position: absolute !important;
            right: 10px !important;
            margin: 0 !important;
        }
        
        /* search-row가 없을 때 폴백 (우측 하단) */
        button.jof-floating-menu-btn.jof-fallback-position,
        #jof-menu-toggle.jof-floating-menu-btn.jof-fallback-position {
            position: fixed !important;
            bottom: 20px !important;
            right: 20px !important;
            z-index: 9998 !important;
            float: none !important;
            margin: 0 !important;
        }
        
        /* fallback position에서도 positioned 클래스가 추가되면 표시 */
        button.jof-floating-menu-btn.jof-fallback-position.jof-positioned,
        #jof-menu-toggle.jof-floating-menu-btn.jof-fallback-position.jof-positioned {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .menu-icon-svg {
            width: 18px !important;
            height: 18px !important;
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            flex-shrink: 0 !important;
            min-width: 18px !important;
            min-height: 18px !important;
        }

        .menu-label {
            font-size: 14px !important;
            font-weight: 600 !important;
            letter-spacing: 0.3px !important;
            text-transform: none !important;
            line-height: 1.2 !important;
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            white-space: nowrap !important;
            color: white !important;
        }

        button.jof-floating-menu-btn:hover,
        #jof-menu-toggle.jof-floating-menu-btn:hover {
            background: rgba(44, 128, 207, 1) !important;
            backdrop-filter: blur(5px) !important;
            -webkit-backdrop-filter: blur(5px) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            /* transform 제거 - 렉 현상 방지 */
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
            color: white !important;
            width: fit-content !important;
        }

        button.jof-floating-menu-btn:active,
        #jof-menu-toggle.jof-floating-menu-btn:active {
            background: rgba(44, 128, 207, 0.8) !important;
            backdrop-filter: blur(4px) !important;
            -webkit-backdrop-filter: blur(4px) !important;
            /* transform 제거 - 렉 현상 방지 */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25) !important;
            color: white !important;
            width: fit-content !important;
        }

        /* 슬라이드 메뉴 */
        .jof-slide-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 300px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            overflow-y: auto;
            /* transition 단축으로 성능 향상 */
            transition: right 0.25s ease-out;
        }

        .jof-slide-menu.active {
            right: 0;
        }

        /* 메뉴 헤더 */
        .jof-menu-header {
            background: #2c3e50;
            color: white;
            padding: 12px 15px;  /* 20px -> 12px 15px로 줄임 */
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .jof-menu-header h2 {
            margin: 0;
            font-size: 17px;  /* 18px -> 17px */
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .jof-close-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-weight: 300;
            /* transition 제거 - 렉 방지 */
        }

        .jof-close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* 메뉴 컨텐츠 */
        .jof-menu-content {
            padding: 8px 0;  /* 15px 0 -> 8px 0으로 줄임 */
        }

        /* 메뉴 그룹 */
        .jof-menu-group {
            margin-bottom: 4px;  /* 8px -> 4px로 줄임 */
        }

        .jof-menu-group-title {
            padding: 8px 20px;  /* 10px 20px -> 8px 20px */
            font-weight: 600;
            font-size: 12px;
            color: #2c3e50;
            background: #ecf0f1;
            border-left: 3px solid #3498db;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* 메뉴 아이템 */
        .jof-menu-item {
            padding: 10px 20px 10px 35px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 3px solid transparent;
            position: relative;
            /* 모든 will-change와 transition 제거 - 렉 방지 */
        }

        .jof-menu-item::before {
            content: '›';
            position: absolute;
            left: 20px;
            color: #95a5a6;
            font-size: 18px;
            font-weight: bold;
        }

        .jof-menu-item:hover {
            background: #fffde7;
            border-left-color: #fbc02d;
        }

        .jof-menu-item:hover::before {
            color: #f57f17;
        }
        
        .jof-menu-item:hover .menu-text {
            color: #f57f17;
            font-weight: 600;
        }

        .jof-menu-item:active {
            background: #e9ecef;
        }

        /* 현재 활성화된 메뉴 - 노란색 강조 */
        .jof-menu-item.active {
            background: #fff9c4 !important;
            border-left-color: #fbc02d !important;
            border-left-width: 4px !important;
        }

        .jof-menu-item.active::before {
            color: #f57f17 !important;
            font-weight: bold;
        }

        .jof-menu-item.active .menu-text {
            color: #f57f17 !important;
            font-weight: 700 !important;
        }

        .menu-icon {
            font-size: 18px;
        }

        .menu-text {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
        }

        /* 오버레이 */
        .jof-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9997;
            display: none;
            transition: opacity 0.3s ease;
        }

        .jof-menu-overlay.active {
            display: block;
        }

        /* 모바일 최적화 */
        @media (max-width: 768px) {
            /* form-menu 모바일 레이아웃 */
            .form-menu {
                flex-wrap: nowrap !important;  /* wrap -> nowrap */
                gap: 5px !important;
                padding: 3px 5px !important;  /* 8px -> 3px 5px */
            }
            
            .form-menu > div {
                font-size: 13px;
            }
            
            .form-menu #entry-title {
                font-size: 14px !important;  /* 모바일에서는 더 작게 */
            }
            
            /* 모바일에서 네비 메뉴 완전히 숨김 */
            button.jof-floating-menu-btn,
            #jof-menu-toggle.jof-floating-menu-btn,
            button.jof-floating-menu-btn.jof-positioned,
            #jof-menu-toggle.jof-floating-menu-btn.jof-positioned,
            button.jof-floating-menu-btn.jof-fallback-position,
            #jof-menu-toggle.jof-floating-menu-btn.jof-fallback-position {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }
            
            /* 모바일에서 메뉴 공간 숨김 */
            .search-row-menu-space {
                display: none !important;
            }

            .jof-slide-menu {
                width: 85vw;
                max-width: 320px;
                right: -100%;
            }

            .jof-slide-menu.active {
                right: 0;
            }
        }

        /* 스크롤바 스타일링 */
        .jof-slide-menu::-webkit-scrollbar {
            width: 8px;
        }

        .jof-slide-menu::-webkit-scrollbar-track {
            background: #ecf0f1;
        }

        .jof-slide-menu::-webkit-scrollbar-thumb {
            background: #95a5a6;
            border-radius: 4px;
        }

        .jof-slide-menu::-webkit-scrollbar-thumb:hover {
            background: #7f8c8d;
        }

        /* 메뉴 오픈 애니메이션 개선 */
        .jof-slide-menu {
            box-shadow: 3px 0 20px rgba(0, 0, 0, 0.2);
        }
    </style>

    <script>
        (function() {
            const menuToggle = document.getElementById('jof-menu-toggle');
            const menuClose = document.getElementById('jof-menu-close');
            const slideMenu = document.getElementById('jof-slide-menu');
            const overlay = document.getElementById('jof-menu-overlay');

            // search-row를 찾아서 버튼을 우측에 삽입
            function positionMenuButton() {
                // 모바일 체크 - 768px 이하면 버튼 삽입하지 않음
                const isMobile = window.matchMedia("(max-width: 768px)").matches;
                if (isMobile) {
                    return; // 모바일이면 아예 실행하지 않음
                }
                
                const searchRow = document.querySelector('.search-row');
                
                if (searchRow) {
                    // search-row가 있으면 그 안에 삽입
                    searchRow.appendChild(menuToggle);
                    menuToggle.style.position = 'absolute';
                    menuToggle.style.right = '10px';
                    // 위치 설정 후 표시
                    setTimeout(() => {
                        menuToggle.classList.add('jof-positioned');
                    }, 0);
                } else {
                    // search-row가 없으면 우측 하단에 고정 (폴백)
                    document.body.appendChild(menuToggle);
                    menuToggle.classList.add('jof-fallback-position');
                    // 위치 설정 후 표시
                    setTimeout(() => {
                        menuToggle.classList.add('jof-positioned');
                    }, 0);
                }
            }

            // DOM 로드 후 실행
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', positionMenuButton);
            } else {
                positionMenuButton();
            }

            function openMenu() {
                slideMenu.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden'; // 배경 스크롤 방지
                
                // 현재 페이지 감지 및 메뉴 강조
                highlightCurrentMenu();
            }
            
            // 현재 페이지에 해당하는 메뉴를 노란색으로 강조
            function highlightCurrentMenu() {
                const currentPath = window.location.pathname;
                const menuItems = document.querySelectorAll('.jof-menu-item');
                
                menuItems.forEach(item => {
                    // 기존 active 클래스 제거
                    item.classList.remove('active');
                    
                    // onclick 속성에서 URL 추출
                    const onclickAttr = item.getAttribute('onclick');
                    if (onclickAttr) {
                        const urlMatch = onclickAttr.match(/location\.href=['"]([^'"]+)['"]/);
                        if (urlMatch) {
                            const menuPath = urlMatch[1];
                            // 현재 경로와 메뉴 경로가 일치하면 active 클래스 추가
                            if (currentPath === menuPath || currentPath.startsWith(menuPath)) {
                                item.classList.add('active');
                            }
                        }
                    }
                });
            }

            function closeMenu() {
                slideMenu.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = ''; // 배경 스크롤 복원
            }

            // 메뉴 열기
            menuToggle.addEventListener('click', openMenu);

            // 메뉴 닫기
            menuClose.addEventListener('click', closeMenu);
            overlay.addEventListener('click', closeMenu);

            // ESC 키로 메뉴 닫기
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && slideMenu.classList.contains('active')) {
                    closeMenu();
                }
            });
        })();
    </script>
    <?php
}