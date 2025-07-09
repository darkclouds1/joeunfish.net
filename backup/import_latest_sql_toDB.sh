#!/bin/bash

# webhosting (.website) 변수 설정
DB_NAME="u753849441_fw9gm"
DB_USER="u753849441_cASrk"
DB_PASS="w3m69P21!@"
DB_HOST="130.228.170"  # 원격 데이터베이스 IP 주소

# -----------------------------------------------
#  backup_t_tables.sh 에서 백업한 파일 경로 설정
BACKUP_DIR="/home/joeunfish.net/backup/bkdb_0708"
# -----------------------------------------------

# 모든 백업 .sql 파일 찾기
SQL_FILES=$(find "$BACKUP_DIR" -name "*.sql" -type f -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-)

# SQL 파일이 존재하는지 확인
if [ -z "$SQL_FILES" ]; then
    echo "Error: No SQL files found in $BACKUP_DIR"
    exit 1
fi

# 각 SQL 파일을 데이터베이스에 임포트
for FILE in $SQL_FILES; do
    echo "Importing $FILE into databases..."
    # joeeunfish.online
    mysql -u $DB_USER -p$DB_PASS $DB_NAME < "$FILE"

    # 실행 결과 확인
    if [ $? -eq 0 ]; then
        echo "Import of $FILE successful!"
    else
        echo "Error: Import of $FILE failed."
        exit 1
    fi
done
echo "All SQL files have been imported into $DB_NAME"


