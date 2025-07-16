#!/bin/bash

# ─────────────────────────────
# t~ 및 wp_users, wp_usermeta 테이블 백업
# ─────────────────────────────
# 설정
DB_NAME="joeu_fish"
DB_USER="u753849441_cASrk"
DB_PASS="w3m69P21!@"
DB_HOST="localhost"  # joeunfish.in DB IP

# 백업 디렉토리 설정 
BACKUP_DIR="/home/joeunfish.net/backup/bkdb_0712"
mkdir -p "$BACKUP_DIR"  # 백업 디렉토리 생성

echo "Starting t~ 테이블 백업..."
echo "백업 대상 디렉토리: $BACKUP_DIR"

# ─────────────────────────────
# t로 시작하는 테이블 목록 가져오기
TABLES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" \
  -e "SHOW TABLES LIKE 't%';" -s --skip-column-names)
# ─────────────────────────────
# 각 테이블 백업
for TABLE in $TABLES; do 
    FILE="$BACKUP_DIR/${TABLE}.sql"
    echo "Backing up: $TABLE → $FILE" 
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" "$TABLE" > "$FILE"
    
    if [ $? -eq 0 ]; then 
        echo "✅ $TABLE 백업 완료"
    else
        echo "❌ $TABLE 백업 실패"
    fi
done

# ─────────────────────────────
# wp_users 
# ─────────────────────────────
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" wp_users > "$FILE"
# wp_usermeta 테이블을 백업 (전체 데이터)
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" wp_usermeta > "$FILE"

echo "모든 t~ 테이블 백업이 완료되었습니다."
