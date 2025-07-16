#!/bin/bash

# === DB 접속 정보 ===
TARGET_DB="u753849441_fw9gm"
TARGET_USER="u753849441_cASrk"
TARGET_PASS="w3m69P21!@"
TARGET_HOST="45.130.228.170"

# === 트리거 백업 폴더 경로 ===
TRIGGER_BACKUP_DIR="/home/joeunfish.net/backup/backup_trigger"

# 폴더가 없으면 생성
mkdir -p "$TRIGGER_BACKUP_DIR"

# === 공통 파일명 태그 ===
DATE_TAG=$(date +"%Y%m%d_%H%M%S")
TRIGGER_BACKUP_FILE="$TRIGGER_BACKUP_DIR/trigger_backup_${TARGET_DB}_${DATE_TAG}.sql"
TRIGGER_DROP_FILE="$TRIGGER_BACKUP_DIR/drop_triggers_${TARGET_DB}_${DATE_TAG}.sql"

# === 실행 옵션 ===
ACTION=$1

# === 도움말 출력 함수 ===
print_usage() {
  echo ""
  echo "사용법: $0 [옵션]"
  echo ""
  echo "옵션 목록:"
  echo "  save    : 현재 트리거들을 SQL 파일로 백업"
  echo "  del     : DB에 존재하는 모든 트리거를 삭제"
  echo "  create  : 가장 최근의 트리거 백업 파일로 복원"
  echo ""
  echo "예시:"
  echo "  $0 save     # 트리거 백업"
  echo "  $0 del      # 트리거 전체 삭제"
  echo "  $0 create   # 트리거 복원"
  echo ""
}

# === 옵션이 없을 경우 사용법 출력 ===
if [[ -z "$ACTION" ]]; then
  print_usage
  exit 0
fi

# === 명령 실행 ===
case "$ACTION" in
  save)
    echo "📦 트리거 백업 중..."
    mysqldump -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" \
      --triggers --no-create-info --no-data --no-create-db \
      "$TARGET_DB" > "$TRIGGER_BACKUP_FILE"
    if [[ $? -eq 0 ]]; then
      echo "✅ 백업 완료: $TRIGGER_BACKUP_FILE"
    else
      echo "❌ 트리거 백업 실패"
    fi
    ;;

  del)
    echo "🧨 트리거 삭제 SQL 생성..."
    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" -N -e "
      SELECT CONCAT('DROP TRIGGER IF EXISTS \`', TRIGGER_NAME, '\`;')
      FROM information_schema.TRIGGERS
      WHERE TRIGGER_SCHEMA = '$TARGET_DB';" > "$TRIGGER_DROP_FILE"
    
    if [[ -s "$TRIGGER_DROP_FILE" ]]; then
      echo "🚀 삭제 실행 중..."
      mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$TRIGGER_DROP_FILE"
      echo "✅ 트리거 삭제 완료"
    else
      echo "⚠ 삭제할 트리거가 없습니다."
    fi
    ;;

  create)
    echo "🔁 가장 최근의 트리거 백업을 복원 중..."
    LATEST_FILE=$(ls -t "$TRIGGER_BACKUP_DIR"/trigger_backup_${TARGET_DB}_*.sql 2>/dev/null | head -n 1)

    if [[ -z "$LATEST_FILE" ]]; then
      echo "❌ 복원할 백업 파일이 없습니다."
      exit 1
    fi

    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$LATEST_FILE"
    echo "✅ 트리거 복원 완료: $LATEST_FILE"
    ;;

  *)
    echo "❗ 지원하지 않는 명령: $ACTION"
    print_usage
    exit 1
    ;;
esac
