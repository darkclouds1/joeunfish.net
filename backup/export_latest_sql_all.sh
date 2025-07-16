#!/bin/bash

# KVM 서버에서 전체 데이터를 백업하여 webhosting으로 복원하는 스크립트
# 기존 데이터는 절대 삭제되지 않습니다 (INSERT IGNORE 사용)

# KVM 서버 (소스)
SOURCE_DB="joeu_fish"
SOURCE_USER="u753849441_cASrk"
SOURCE_PASS="w3m69P21!@"
SOURCE_HOST="localhost"

# webhosting (타겟)
TARGET_DB="u753849441_fw9gm"
TARGET_USER="u753849441_cASrk"
TARGET_PASS="w3m69P21!@"
TARGET_HOST="45.130.228.170"

BACKUP_DIR="/home/joeunfish.net/backup/backup_db_all"

# 백업 디렉토리 생성 (없으면)
mkdir -p "$BACKUP_DIR"

# 실행 전 확인
echo "=== 데이터 동기화 시작 ==="
echo "소스: $SOURCE_HOST/$SOURCE_DB"
echo "타겟: $TARGET_HOST/$TARGET_DB"
echo "백업 범위: 전체 데이터"
echo ""
read -p "계속하시겠습니까? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "작업이 취소되었습니다."
    exit 0
fi

# 데이터베이스 덤프 생성 (KVM에서)
echo "Creating a backup of the KVM database..."
TABLES=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" -e "SHOW TABLES LIKE 't%'" | grep -v "Tables_in")

# t로 시작하는 테이블 백업 (전체 데이터)
for TABLE in $TABLES; do
    echo "Backing up table: $TABLE (전체 데이터)"
    
    # 전체 테이블 백업 (데이터만)
    mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
        --no-create-info --no-create-db \
        --triggers --routines --events \
        "$SOURCE_DB" "$TABLE" > "$BACKUP_DIR/t_${TABLE}.sql"
done

# wp_users 테이블을 백업 (전체 데이터)
# mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
#     --no-create-info --no-create-db \
#     --triggers --routines --events \
#     "$SOURCE_DB" wp_users > "$BACKUP_DIR/wp_users.sql"

# wp_usermeta 테이블을 백업 (전체 데이터)
# mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
#     --no-create-info --no-create-db \
#     --triggers --routines --events \
#     "$SOURCE_DB" wp_usermeta > "$BACKUP_DIR/wp_usermeta.sql"

if [ $? -eq 0 ]; then
    echo "KVM database backup successful"
else
    echo "Error: KVM database backup failed."
    exit 1
fi

# 모든 백업 .sql 파일 찾기
SQL_FILES=$(find "$BACKUP_DIR" -name "*.sql" -type f -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-)

# SQL 파일이 존재하는지 확인
if [ -z "$SQL_FILES" ]; then
    echo "Error: No SQL files found in $BACKUP_DIR"
    exit 1
fi

# 각 SQL 파일을 webhosting 복원
for FILE in $SQL_FILES; do
    echo "Importing $FILE into webhosting database..."
    
    # EVENT, TRIGGER 관련 권한 문제 및 불완전한 SQL 구문 제거
    TEMP_FILE="${FILE}.temp"
    
    # awk를 사용하여 더 정확한 라인 제거 및 INSERT IGNORE로 변경
    awk '
    BEGIN { skip = 0; in_trigger = 0; in_incomplete_query = 0 }
    
    # DROP TABLE 구문 완전 제거 (기존 데이터 보호)
    /^DROP TABLE/ { skip = 1; next }
    /^\/\*!40000 DROP TABLE/ { skip = 1; next }
    
    # CREATE TABLE 구문을 CREATE TABLE IF NOT EXISTS로 변경
    /^CREATE TABLE/ { 
        gsub(/^CREATE TABLE/, "CREATE TABLE IF NOT EXISTS")
        print
        next
    }
    
    # EVENT 관련 라인 건너뛰기
    /^\/\*!50106 CREATE.*EVENT/ { skip = 1; next }
    /^\/\*!50106 DROP.*EVENT/ { skip = 1; next }
    
    # DEFINER 라인 건너뛰기
    /^\/\*!50017 DEFINER=/ { skip = 1; next }
    
    # TRIGGER 블록 시작/끝 감지
    /^\/\*!50003 CREATE.*TRIGGER/ { in_trigger = 1; skip = 1; next }
    /^\/\*!50003$/ { if (in_trigger) { in_trigger = 0; skip = 0 } }
    /^END \*\/$/ { if (in_trigger) { in_trigger = 0; skip = 0 } }
    /^END$/ { if (in_trigger) { in_trigger = 0; skip = 0 } }
    
    # 불완전한 쿼리 블록 감지
    /^FROM wp_users u$/ { in_incomplete_query = 1; skip = 1; next }
    /^AND u\.user_registered < NOW() - INTERVAL 10 MINUTE/ { 
        if (in_incomplete_query) { 
            in_incomplete_query = 0; 
            skip = 0 
        }
    }
    
    # INSERT INTO를 INSERT IGNORE INTO로 변경하여 중복 방지
    /^INSERT INTO/ { 
        gsub(/^INSERT INTO/, "INSERT IGNORE INTO")
        print
        next
    }
    
    # 출력 조건
    !skip && !in_trigger && !in_incomplete_query { print }
    ' "$FILE" > "$TEMP_FILE"
    
    # webhosting 데이터베이스에 임포트 (중복 데이터 방지)
    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$TEMP_FILE"

    # 임시 파일 삭제
    rm -f "$TEMP_FILE"

    # 실행 결과 확인
    if [ $? -eq 0 ]; then
        echo "Import of $FILE to webhosting successful!"
    else
        echo "Error: Import of $FILE to webhosting failed."
        exit 1
    fi
done

echo ""
echo "=== 데이터 동기화 완료 ==="
echo "모든 SQL 파일이 KVM에서 추출되어 webhosting 데이터베이스로 안전하게 임포트되었습니다."
echo "기존 데이터는 보존되었으며, 중복 데이터만 무시되었습니다."