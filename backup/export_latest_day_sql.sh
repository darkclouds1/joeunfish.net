#!/bin/bash

# KVM 서버에서 시작일자 지정 백업하여 webhosting으로 복원하는 스크립트
# 시작일부터 현재까지의 데이터를 백업하여 타겟에 복원

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

# 시작일자 설정
# 기본값: 하루 전 날짜로 자동 설정
# 수동 설정 시 아래 주석을 해제하고 원하는 날짜 입력 (YYYY-MM-DD 형식)
# START_DATE="2025-07-15"
# START_DATE_FORMAT="20250715"

START_DATE=$(date -d "-1 days" +%Y-%m-%d)
START_DATE_FORMAT=$(date -d "-2 days" +%Y%m%d)

# 백업 디렉토리 설정
BACKUP_DIR="/home/joeunfish.net/backup/net_backup_${START_DATE_FORMAT}"

# 백업 디렉토리 생성
mkdir -p "$BACKUP_DIR"

# logDB에 로그 기록 함수
log_to_db() {
    local pg=$1
    local rmrk=$2
    
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "INSERT INTO logdb (pg, id, ip, rmrk) VALUES ('$pg', 'system', '127.0.0.1', '$rmrk')" 2>/dev/null
}

# 실행 전 확인
echo "=== 시작일자 지정 백업 시작 ==="
echo "소스: $SOURCE_HOST/$SOURCE_DB"
echo "타겟: $TARGET_HOST/$TARGET_DB"
echo "시작일자: $START_DATE"
echo "백업 디렉토리: $BACKUP_DIR"
echo ""

# 백업 시작 로그
log_to_db "backup" "시작일자 지정 백업 시작: $START_DATE"

# t로 시작하는 테이블 목록 가져오기
echo "테이블 목록 확인 중..."
TABLES=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
    -e "SHOW TABLES LIKE 't%'" | grep -v "Tables_in")

echo "테이블 목록: $TABLES"

# 백업 성공/실패 카운터
SUCCESS_COUNT=0
FAILED_COUNT=0
FAILED_TABLES=""
BACKUP_COUNT=0

echo "시작일자 지정 백업 시작..."

IFS=$'\n'
for TABLE in $TABLES; do
    echo "백업 중: [$TABLE]"
    
    # 테이블별 백업 조건 설정
    WHERE_CONDITION=""
    DELETE_CONDITION=""
    
    case $TABLE in
        "tascinvt"|"tascinvt3"|"tbarcodein"|"tbarcodeinv"|"tbarcodeinv_m"|"tbarcodeout"|"tdayprod"|"tdayprod_lot"|"tevent"|"tgscodeout"|"tgscodeout_invent"|"tpurchase_in")
            WHERE_CONDITION="date(increate_dt) >= '$START_DATE'"
            DELETE_CONDITION="date(increate_dt) >= '$START_DATE'"
            ;;
        "tdayprod_mst"|"tdayprodmat_mst"|"torderproducts"|"torderproducts_add")
            WHERE_CONDITION="date(create_dt) >= '$START_DATE'"
            DELETE_CONDITION="date(create_dt) >= '$START_DATE'"
            ;;
        "tprodinvt")
            WHERE_CONDITION="date(invt_dt) >= '$START_DATE'"
            DELETE_CONDITION="date(invt_dt) >= '$START_DATE'"
            ;;
        "tpurchase_imp")
            WHERE_CONDITION="date(import_dt) >= '$START_DATE'"
            DELETE_CONDITION="date(import_dt) >= '$START_DATE'"
            ;;
        "tcomcode"|"tcomcodegs")
            # 코드 테이블은 전체 백업
            WHERE_CONDITION=""
            DELETE_CONDITION=""
            ;;
        "torders")
            WHERE_CONDITION="order_dt >= '$START_DATE_FORMAT'"
            DELETE_CONDITION="order_dt >= '$START_DATE_FORMAT'"
            ;;
        "tpurchase")
            WHERE_CONDITION="date(input_dt) >= '$START_DATE'"
            DELETE_CONDITION="date(input_dt) >= '$START_DATE'"
            ;;
        *)
            # 날짜 컬럼이 없는 테이블은 전체 백업
            WHERE_CONDITION=""
            DELETE_CONDITION=""
            ;;
    esac
    
    # 백업할 레코드 수 확인
    if [ -n "$WHERE_CONDITION" ]; then
        BACKUP_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
            -e "SELECT COUNT(*) FROM $TABLE WHERE $WHERE_CONDITION" -s --skip-column-names 2>/dev/null || echo "0")
    else
        BACKUP_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
            -e "SELECT COUNT(*) FROM $TABLE" -s --skip-column-names 2>/dev/null || echo "0")
    fi
    
    if [ "$BACKUP_ROWS" -eq 0 ]; then
        echo "  ✓ $TABLE: 백업할 데이터 없음 (건너뜀)"
        log_to_db "backup_${TABLE}" "$TABLE: 백업할 데이터 없음 (SKIPPED)"
        continue
    fi
    
    echo "  📊 $TABLE: $BACKUP_ROWS개 레코드 백업"
    ((BACKUP_COUNT++))
    
    # 백업 실행
    BACKUP_FILE="$BACKUP_DIR/${TABLE}_${START_DATE_FORMAT}.sql"
    
    if [ -n "$WHERE_CONDITION" ]; then
        # 조건부 백업
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --single-transaction \
            --skip-triggers \
            --replace \
            --where="$WHERE_CONDITION" \
            "$SOURCE_DB" "$TABLE" > "$BACKUP_FILE" 2>/dev/null
    else
        # 전체 백업 (코드 테이블 또는 날짜 컬럼 없는 테이블)
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --single-transaction \
            --skip-triggers \
            --replace \
            "$SOURCE_DB" "$TABLE" > "$BACKUP_FILE" 2>/dev/null
    fi
    
    # 백업 결과 확인
    if [ $? -eq 0 ] && [ -s "$BACKUP_FILE" ]; then
        file_size=$(du -h "$BACKUP_FILE" | cut -f1)
        echo "  ✅ $TABLE 백업 완료: $(basename "$BACKUP_FILE") ($file_size)"
        log_to_db "backup_${TABLE}" "$TABLE: 백업 성공 ($BACKUP_ROWS rows, $file_size)"
        ((SUCCESS_COUNT++))
    else
        echo "  ❌ $TABLE 백업 실패"
        log_to_db "backup_${TABLE}" "$TABLE: 백업 실패"
        ((FAILED_COUNT++))
        FAILED_TABLES="$FAILED_TABLES $TABLE"
        rm -f "$BACKUP_FILE"
    fi
done
unset IFS

echo ""
echo "=== 백업 결과 요약 ==="
echo "총 테이블: $(echo $TABLES | wc -w)"
echo "백업 대상 테이블: $BACKUP_COUNT"
echo "백업 성공: $SUCCESS_COUNT"
echo "백업 실패: $FAILED_COUNT"

if [ $FAILED_COUNT -gt 0 ]; then
    echo "실패한 테이블: $FAILED_TABLES"
fi

# 성공한 백업이 하나라도 있는지 확인
if [ $SUCCESS_COUNT -eq 0 ]; then
    echo "백업할 데이터가 없거나 모든 백업이 실패했습니다."
    log_to_db "backup" "백업 실패: 백업할 데이터 없음"
    exit 0
fi

# 백업 파일들 찾기
BACKUP_FILES=$(find "$BACKUP_DIR" -name "*_${START_DATE_FORMAT}.sql" -type f)

if [ -z "$BACKUP_FILES" ]; then
    echo "백업할 파일이 없습니다."
    log_to_db "backup" "백업 실패: 백업할 파일 없음"
    exit 0
fi

echo ""
echo "=== webhosting 데이터베이스로 복원 시작 ==="

# 복원 성공/실패 카운터
RESTORE_SUCCESS=0
RESTORE_FAILED=0

# 각 백업 파일을 타겟 DB에 복원
for FILE in $BACKUP_FILES; do
    TABLE_NAME=$(basename "$FILE" | cut -d'_' -f1)
    echo "복원 중: $(basename "$FILE")"
    
    # 타겟 DB에서 시작일자 이후 데이터 삭제
    if [ -n "$DELETE_CONDITION" ]; then
        echo "  🗑️  $TABLE_NAME 테이블에서 시작일자 이후 데이터 삭제 중..."
        mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" \
            -e "DELETE FROM $TABLE_NAME WHERE $DELETE_CONDITION" 2>/dev/null
    else
        echo "  🗑️  $TABLE_NAME 테이블 전체 데이터 삭제 중..."
        mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" \
            -e "DELETE FROM $TABLE_NAME" 2>/dev/null
    fi
    
    # 복원 전: 트리거와 외래키 제약조건 비활성화
    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" \
        -e "SET FOREIGN_KEY_CHECKS = 0; SET UNIQUE_CHECKS = 0; SET AUTOCOMMIT = 0;" 2>/dev/null
    
    # 백업 파일을 타겟 DB에 복원
    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$FILE"
    
    # 복원 후: 설정 복원
    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" \
        -e "SET FOREIGN_KEY_CHECKS = 1; SET UNIQUE_CHECKS = 1; COMMIT;" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo "  ✅ $(basename "$FILE") 복원 완료"
        log_to_db "backup_${TABLE_NAME}" "$(basename "$FILE"): 복원 성공"
        ((RESTORE_SUCCESS++))
    else
        echo "  ❌ $(basename "$FILE") 복원 실패"
        log_to_db "backup_${TABLE_NAME}" "$(basename "$FILE"): 복원 실패"
        ((RESTORE_FAILED++))
    fi
done

# 백업 완료 로그 기록
if [ $RESTORE_FAILED -eq 0 ]; then
    log_to_db "backup" "백업 완료: $SUCCESS_COUNT개 테이블 성공, $RESTORE_SUCCESS개 파일 복원 성공"
else
    log_to_db "backup" "백업 실패: $RESTORE_FAILED개 파일 복원 실패"
fi

echo ""
echo "=== 시작일자 지정 백업 완료 ==="
echo "백업 성공: $SUCCESS_COUNT 테이블"
echo "복원 성공: $RESTORE_SUCCESS 파일"
echo "복원 실패: $RESTORE_FAILED 파일"

if [ $RESTORE_FAILED -gt 0 ]; then
    echo "⚠️  일부 복원이 실패했습니다. 로그를 확인하세요."
fi

echo "✅ 시작일자 지정 백업이 완료되었습니다."
echo "백업결과 확인: SELECT * FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 10;"
