#!/bin/bash

# FTP 서버 정보 설정
FTP_HOST="45.130.228.170"
FTP_USER="u753849441"
FTP_PASS= "RKVm9/hGG=e9P1s*"
REMOTE_DIR="/home/u753849441/domains/joeunfish.in/public_html/ecommerce_java/recv"
LOCAL_DIR="/home/joeunfish.net/public_html/ecommerce_java/recv"

# 디렉토리 존재 여부 확인
if [ ! -d "$LOCAL_DIR" ]; then
  echo "ERROR: Directory $LOCAL_DIR does not exist"
  exit 1
fi

# 최신 .dat 파일 찾기 (파일명 기준으로 정렬)
# LATEST_FILE=$(find "$LOCAL_DIR" -name "*.dat" -type f | sort | tail -1)

# 최신 .dat 파일 찾기 (파일 크기가 0보다 큰 파일 중에서 파일명 기준으로 정렬)
LATEST_FILE=$(find "$LOCAL_DIR" -name "*.dat" -type f -size +0c | sort | tail -1)

if [ -z "$LATEST_FILE" ]; then
  echo "No .dat files found in $LOCAL_DIR"
  exit 1
fi

# 파일명만 추출
FILENAME=$(basename "$LATEST_FILE")

echo "Uploading: $FILENAME"

# 파일 업로드
ftp -inv $FTP_HOST << EOF
user $FTP_USER $FTP_PASS
cd $REMOTE_DIR
put $LATEST_FILE $FILENAME
bye
EOF

echo "File $FILENAME uploaded successfully."