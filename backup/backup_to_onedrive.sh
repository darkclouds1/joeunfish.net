#!/bin/bash

# 스크립트 시작 부분에 다음 코드 추가
export HOME=/home/joeunfish.online
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

LOG_FILE="$HOME/backup/backup_script.log"
RCLONE_CONFIG="/home/joeunfish.online/.config/rclone/rclone.conf"

# 로그 디렉토리 생성
mkdir -p "$(dirname "$LOG_FILE")"
 
# 로그 함수
log() { 
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE" 
} 
# 디버깅 정보 추가
log "rclone version: $(rclone --version)"
log "RCLONE_CONFIG: $RCLONE_CONFIG"
log "Script started"
log "Current user: $(whoami)"
log "Current HOME: $HOME"
log "Current PATH: $PATH"

# rclone.conf 파일 찾기
for config_path in "/home/joeunfish.online/.config/rclone/rclone.conf" "/root/.config/rclone/rclone.conf"
do
    if [ -f "$config_path" ]; then
        RCLONE_CONFIG="$config_path"
        break
    fi
done

if [ -z "$RCLONE_CONFIG" ]; then 
    echo "Error: rclone.conf file not found"
    exit 1
fi

echo "Using rclone config: $RCLONE_CONFIG"

# 백업할 로컬 폴더
SOURCE_DIR="$HOME/backup/backup_db"

# OneDrive의 대상 폴더 (rclone 설정에서 지정한 이름:폴더경로)
DEST_DIR="new_onedrive:joeu_fish_backup"

#================================================
# [t_tables_backup_.sql] local => OneDrive:: .sql
#================================================
log "▷ Starting backup of *.sql & all files to OneDrive"
if rclone sync "$SOURCE_DIR" "$DEST_DIR" --config "$RCLONE_CONFIG" --include "*.sql" --include "*" --auto-confirm 2>> "$LOG_FILE"; then
    log "Backup of *.sql & all files to OneDrive completed successfully"
else
    log "Error: Backup of *.sql & all files to OneDrive failed"
    log "rclone error output: $(tail -n 5 "$LOG_FILE")"
    exit 1
fi
log "t-tables_backup_.sql & all files process completed"


#===========================================
# [edi_dat] Local => onedrive로 복사
#===========================================
# LATEST_EDI_FILE=$(find "$HOME/ecommerce_java/recv" -name "edi_*.dat" -type f -printf '%T+ %p\n' | sort -r | head -n 1 | awk '{print $2}')

# if [ -n "$LATEST_EDI_FILE" ]; then
#     log "▷ Uploading latest EDI file to OneDrive: $LATEST_EDI_FILE"
#     if rclone copy "$LATEST_EDI_FILE" "$DEST_DIR" --config "$RCLONE_CONFIG" --auto-confirm 2>> "$LOG_FILE"; then
#         log "Latest EDI file uploaded to OneDrive successfully"
#     else
#         log "Error: Failed to upload latest EDI file to OneDrive"
#         log "rclone error output: $(tail -n 5 "$LOG_FILE")"
#         exit 1
#     fi
# else
#     log "Error: No edi_*.dat file found"
#     exit 1
# fi

# 5시간 이전의 *.sql 파일 삭제
# find "$SOURCE_DIR" -name "*.sql" -type f -mmin +300 -delete
# log "Old *.sql and *.dat files deleted"

log "All processes completed successfully"
