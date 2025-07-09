#!/bin/bash

# ============================ 
# 설정
# ============================
SOURCE_DIR="/home/joeunfish.net/public_html/ecommerce_java/recv"

# 최신 파일 찾기 (파일명 기준으로 정렬)
LATEST_FILE=$(find "$SOURCE_DIR" -name "*.dat" -type f | sort | tail -1)
echo "LATEST_FILE: $LATEST_FILE"

# FTP 서버 정보
FTP_HOST='45.130.228.170'
FTP_USER='u753849441'
FTP_PASS='w3m69P21!@'
REMOTE_PATH="/domains/joeunfish.website/public_html/ecommerce_java/recv"

# ============================
# lftp를 통한 업로드 (자동 재시도 포함)
# ============================
MAX_RETRIES=3 
DELAY_SECONDS=5
attempt=1 
transfer_success=false

while [ $attempt -le $MAX_RETRIES ]; do
    echo "Attempt $attempt: Uploading $LATEST_FILE..."
    lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" << EOF
set ftp:ssl-allow no
cd $REMOTE_PATH
rm -f .in.$LATEST_FILE
rm -f .in.$LATEST_FILE.
rm -f $LATEST_FILE
put "$SOURCE_DIR/$LATEST_FILE"
bye
EOF

    if [ $? -eq 0 ]; then
        echo "✅ File $LATEST_FILE successfully transferred via lftp (Attempt $attempt)"
        transfer_success=true
        break
    else
        echo "❌ Failed to transfer $LATEST_FILE on attempt $attempt"
        if [ $attempt -lt $MAX_RETRIES ]; then
            echo "Retrying in $DELAY_SECONDS seconds..."
            sleep $DELAY_SECONDS
        fi
        attempt=$(( attempt + 1 ))
    fi
done

if [ "$transfer_success" = false ]; then
    echo "❌ Failed to transfer $LATEST_FILE after $MAX_RETRIES attempts."
fi

# ============================
# 1일 초과 파일 자동 삭제
# ============================
THREE_DAYS_AGO=$(date -d "1 days ago" +%s)

find "$SOURCE_DIR" -type f | while read -r file; do
    FILE_MOD_TIME=$(stat -c %Y "$file")
    if [ "$FILE_MOD_TIME" -lt "$THREE_DAYS_AGO" ]; then
        echo "Deleting old file: $file"
        rm -f "$file"
    fi
done

echo "🧹 Cleanup complete. Only recent files are retained."
