#!/bin/bash

# ─────────────────────────────
# 설정
# DB_NAME="joeu_fish"
# DB_USER="u753849441_cASrk"
# DB_PASS="w3m69P21!@"
# DB_HOST="localhost"  # joeunfish.in DB IP

DB_NAME="u753849441_fw9gm"
DB_USER="u753849441_cASrk"
DB_PASS="w3m69P21!@"
DB_HOST="localhost"  # joeunfish.in DB IP

BACKUP_DIR="/home/joeunfish.net/backup/bkdb_0708"

echo "Starting t~ 테이블 복원..."
echo "복원 대상 디렉토리: $BACKUP_DIR"

# 백업 디렉토리가 존재하는지 확인
if [ ! -d "$BACKUP_DIR" ]; then
    echo "❌ 백업 디렉토리를 찾을 수 없습니다: $BACKUP_DIR"
    exit 1
fi

# ─────────────────────────────
# t로 시작하는 SQL 파일 목록 가져오기
SQL_FILES=$(find "$BACKUP_DIR" -name "t*.sql" -type f)

if [ -z "$SQL_FILES" ]; then
    echo "❌ 백업된 t~ 테이블 파일을 찾을 수 없습니다."
    exit 1
fi

# ─────────────────────────────
# 각 SQL 파일 복원
for FILE in $SQL_FILES; do
    # 파일명에서 테이블명 추출
    BASENAME=$(basename "$FILE")
    TABLE_NAME="${BASENAME%.sql}"
    
    echo "Restoring: $FILE → $TABLE_NAME"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$FILE"
    
    if [ $? -eq 0 ]; then
        echo "✅ $TABLE_NAME 복원 완료"
    else
        echo "❌ $TABLE_NAME 복원 실패"
    fi
done

echo "모든 t~ 테이블 복원이 완료되었습니다."
