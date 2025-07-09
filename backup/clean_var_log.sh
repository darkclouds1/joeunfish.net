#!/bin/bash

# 📌 로그 디렉토리
LOG_DIR="/var/log"

# 🔹 삭제 대상 파일 패턴 정의
find "$LOG_DIR" \
    \( -name "*.log-*" -o -name "*.gz" -o -name "*-20*" \) \
    -type f -mtime +7 -exec rm -f {} \;

# 🔹 빈 파일 삭제 (단, 중요한 시스템 로그 제외)
find "$LOG_DIR" -type f -size 0 -not -name "messages" -not -name "secure" -not -name "cron" -exec rm -f {} \;

# 🔹 정리 후 공간 확인
echo "🧹 /var/log 정리 완료. 디스크 사용량:"
du -sh "$LOG_DIR"
