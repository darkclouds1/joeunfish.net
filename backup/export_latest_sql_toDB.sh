#!/bin/bash

# webhosting (.website) 변수 설정
DB_NAME="u753849441_fw9gm"
DB_USER="u753849441_cASrk"
DB_PASS="w3m69P21!@"
DB_HOST="45.130.228.170"  # 원격 데이터베이스 IP 주소

# -----------------------------------------------
#  backup_t_tables.sh 에서 백업한 파일 경로 설정
BACKUP_DIR="/home/joeunfish.net/backup/bkdb_0712"
# -----------------------------------------------

# 모든 백업 .sql 파일 찾기
SQL_FILES=$(find "$BACKUP_DIR" -name "*.sql" -type f -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-)

# SQL 파일이 존재하는지 확인
if [ -z "$SQL_FILES" ]; then
    echo "Error: No SQL files found in $BACKUP_DIR"
    exit 1 
fi

# 각 SQL 파일을 webhosting 데이터베이스에 원복 
for FILE in $SQL_FILES; do  
    # 임시 파일 생성 (트리거 정의 제거)
    TEMP_FILE=$(mktemp)
    
    # 트리거 정의를 제거하고 데이터만 추출
    sed '/^\/\*!50003 CREATE.*TRIGGER/,/^\/\*!50003$/d' "$FILE" > "$TEMP_FILE"
    
    # joeeunfish.online - 원격 호스트 지정
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < "$TEMP_FILE"

    # 실행 결과 확인
    if [ $? -eq 0 ]; then
        echo "Export of $FILE successful!"
    else
        echo "Error: Export of $FILE failed."
        rm -f "$TEMP_FILE"
        exit 1
    fi
    
    # 임시 파일 삭제
    rm -f "$TEMP_FILE"
done
echo "All SQL files have been exported into $DB_NAME"


