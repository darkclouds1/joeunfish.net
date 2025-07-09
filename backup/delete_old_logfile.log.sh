#!/bin/bash

# 검색할 최상위 디렉토리 설정
SEARCH_DIR="/"

# 삭제할 파일명 설정 (배열로 변경)
TARGET_FILES=("logfile.log" "cron.log")

# 각 대상 파일에 대해 삭제 작업 수행
for target in "${TARGET_FILES[@]}"; do
    # 로그 출력
    echo "[$(date)]: 시작 - $target 파일 삭제"
    
    # 파일 찾고 삭제
    find "$SEARCH_DIR" -type f -name "$target" -exec rm -f {} \;
    
    # 삭제 완료 메시지
    echo "[$(date)]: 완료 - $target 파일 모두 삭제됨"
done