#!/bin/bash

# 삭제 대상 디렉토리 경로 설정
TARGET_DIR="/home/joeunfish.net/public_html/ecommerce_java/recv"

# 파일 사이즈가 0인 파일들 중에서 가장 최근 파일 찾기
LATEST_EMPTY_FILE=$(find "$TARGET_DIR" -type f -size 0 -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)

# 파일 사이즈가 0인 모든 파일 삭제 (가장 최근 파일 제외)
if [ -n "$LATEST_EMPTY_FILE" ]; then
    find "$TARGET_DIR" -type f -size 0 ! -path "$LATEST_EMPTY_FILE" -exec rm -f {} \;
    echo "$(date): $TARGET_DIR 디렉토리에서 0바이트 파일 삭제 완료 (최신 파일 제외: $LATEST_EMPTY_FILE)"
else
    echo "$(date): $TARGET_DIR 디렉토리에서 삭제할 0바이트 파일이 없습니다"
fi
