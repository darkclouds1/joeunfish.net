#!/bin/bash

# 변수 설정
DB_NAME="u753849441_fw9gm"
DB_USER="u753849441_cASrk"
DB_PASS="w3m69P21!@"
DB_HOST="45.130.228.170"  # 원격 데이터베이스 IP 주소

BACKUP_DIR="/home/joeunfish.net/backup/backup_db"
# DUMP_FILE="$BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S).sql"

# 데이터베이스 덤프 생성
# echo "Creating a backup of the current database..."
# TABLES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "SHOW TABLES LIKE 't%'" | grep -o 't[a-zA-Z0-9_]*')

# t로 시작하는 테이블 백업
# for TABLE in $TABLES; do
#     mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" "$TABLE" > "$BACKUP_DIR/t_${TABLE}.sql"
# done

# wp_users 테이블을 백업
# mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" wp_users > "$BACKUP_DIR/wp_users.sql"
# mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" wp_usermeta > "$BACKUP_DIR/wp_usermeta.sql"
# mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" wp_options > "$BACKUP_DIR/wp_options.sql"

# if [ $? -eq 0 ]; then
#     echo "Database backup successful: $DUMP_FILE"
# else
#     echo "Error: Database backup failed."
#     exit 1
# fi

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
    mysql -u joeu_fish -p'w3m69p21!@' joeu_fish < "$FILE"

    # 실행 결과 확인
    if [ $? -eq 0 ]; then
        echo "Import of $FILE successful!"
    else
        echo "Error: Import of $FILE failed."
        exit 1
    fi
done
echo "All SQL files have been imported into databases."


