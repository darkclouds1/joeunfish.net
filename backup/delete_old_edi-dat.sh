#!/bin/bash

# 삭제 대상 디렉토리 경로 설정
TARGET_DIR="/home/joeunfish.net/public_html/ecommerce_java/recv"

# 3일 이전 파일 삭제
find "$TARGET_DIR" -type f -mtime +1 -exec rm -f {} \;

# 로그 출력
echo "$(date): $TARGET_DIR 디렉토리에서 1일 이전 파일 삭제 완료"
