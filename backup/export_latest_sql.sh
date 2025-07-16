#!/bin/bash

# KVM 서버에서 증분 백업하여 webhosting으로 복원하는 스크립트
# 마지막 백업 이후 변경된 데이터만 백업하여 효율적으로 동기화

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

BACKUP_DIR="/home/joeunfish.net/backup/backup_db"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# 최초 백업 시간 설정 (2025년 7월 12일 16:00)
INITIAL_BACKUP_TIME="20250713_000000"

# 백업 디렉토리 생성
mkdir -p "$BACKUP_DIR"

# logDB에 로그 기록 함수
log_to_db() {
    local pg=$1
    local rmrk=$2
    
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "INSERT INTO logdb (pg, id, ip, rmrk) VALUES ('$pg', 'system', '127.0.0.1', '$rmrk')" 2>/dev/null
}

# 마지막 백업 시간 조회 (logdb 테이블에서)
get_last_backup_time() {
    local last_backup=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT dt FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 1" -s --skip-column-names 2>/dev/null)
    
    if [ -n "$last_backup" ]; then
        echo "$last_backup"
    else
        echo "$INITIAL_BACKUP_TIME"
    fi
}

get_last_restore_time() {
    local table_name=$1
    local last_restore=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT dt FROM logdb WHERE pg = 'backup_${table_name}' AND rmrk LIKE '%복원 성공%' ORDER BY dt DESC LIMIT 1" -s --skip-column-names 2>/dev/null)
    if [ -n "$last_restore" ]; then
        echo "$last_restore"
    else
        echo "$INITIAL_BACKUP_TIME"
    fi
}

# 실행 전 확인 (cronjob용 - 자동 실행)
echo "=== 증분 백업 시작 ==="
echo "소스: $SOURCE_HOST/$SOURCE_DB"
echo "타겟: $TARGET_HOST/$TARGET_DB"
echo "백업 시간: $TIMESTAMP"
echo ""

# 백업 시작 로그
log_to_db "backup" "백업 시작: $TIMESTAMP"

# 마지막 백업 시간 확인
LAST_BACKUP=$(get_last_backup_time)
echo "마지막 백업 시간: $LAST_BACKUP"

# t로 시작하는 테이블 목록 가져오기
echo "테이블 목록 확인 중..."
TABLES=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
    -e "SHOW TABLES LIKE 't%'" | grep -v "Tables_in")

echo "테이블 목록: $TABLES"

# 백업 성공/실패 카운터
SUCCESS_COUNT=0
FAILED_COUNT=0
FAILED_TABLES=""
CHANGED_COUNT=0

echo "증분 백업 시작..."

IFS=$'\n'
for TABLE in $TABLES; do
    echo "백업 중: [$TABLE]"
    LAST_BACKUP=$(get_last_restore_time "$TABLE")
    # 테이블의 변경사항 확인
    CHANGED_ROWS=0
    WHERE_CONDITION=""
    
    # 마지막 백업 이후 변경된 레코드 수 확인
    case $TABLE in
        "tascinvt"|"tascinvt3"|"tbarcodein"|"tbarcodeinv"|"tbarcodeinv_m"|"tbarcodeout"|"tdayprod"|"tdayprod_lot"|"tevent"|"tgscodeout"|"tgscodeout_invent"|"tpurchase_in")
            CHANGED_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
                -e "SELECT COUNT(*) FROM $TABLE WHERE increate_dt > '$LAST_BACKUP'" -s --skip-column-names 2>/dev/null || echo "0")
            WHERE_CONDITION="increate_dt > '$LAST_BACKUP'"
            ;;
        "tdayprod_mst"|"tdayprodmat_mst"|"torderproducts"|"torderproducts_add"|"tpurchase")
            CHANGED_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
                -e "SELECT COUNT(*) FROM $TABLE WHERE create_dt > '$LAST_BACKUP'" -s --skip-column-names 2>/dev/null || echo "0")
            WHERE_CONDITION="create_dt > '$LAST_BACKUP'"
            ;;
        "tprodinvt")
            CHANGED_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
                -e "SELECT COUNT(*) FROM $TABLE WHERE invt_dt > '$LAST_BACKUP'" -s --skip-column-names 2>/dev/null || echo "0")
            WHERE_CONDITION="invt_dt > '$LAST_BACKUP'"
            ;;
        "tpurchase_imp")
            CHANGED_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
                -e "SELECT COUNT(*) FROM $TABLE WHERE import_dt > '$LAST_BACKUP'" -s --skip-column-names 2>/dev/null || echo "0")
            WHERE_CONDITION="import_dt > '$LAST_BACKUP'"
            ;;
        "tcomcode"|"tcomcodegs")
            # 코드 테이블은 항상 전체 백업 (변경사항 추적 어려움)
            CHANGED_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
                -e "SELECT COUNT(*) FROM $TABLE" -s --skip-column-names 2>/dev/null || echo "0")
            WHERE_CONDITION=""
            ;;
        "torders")
            LAST_BACKUP_DATE=${LAST_BACKUP:0:8}
            CHANGED_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
                -e "SELECT COUNT(*) FROM $TABLE WHERE order_dt > '$LAST_BACKUP_DATE'" -s --skip-column-names 2>/dev/null || echo "0")
            WHERE_CONDITION="order_dt > '$LAST_BACKUP_DATE'"
            ;;
        *)
            # 날짜 컬럼이 없는 테이블은 전체 백업
            CHANGED_ROWS=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
                -e "SELECT COUNT(*) FROM $TABLE" -s --skip-column-names 2>/dev/null || echo "0")
            WHERE_CONDITION=""
            ;;
    esac
    
    if [ "$CHANGED_ROWS" -eq 0 ]; then
        echo "  ✓ $TABLE: 변경사항 없음 (건너뜀)"
        log_to_db "backup_${TABLE}" "$TABLE: 변경사항 없음 (SKIPPED)"
        continue
    fi
    
    echo "  📊 $TABLE: $CHANGED_ROWS개 레코드 변경됨"
    ((CHANGED_COUNT++))
    
    # 증분 백업 실행
    BACKUP_FILE="$BACKUP_DIR/incremental_${TABLE}_${TIMESTAMP}.sql"
    
    if [ -n "$WHERE_CONDITION" ]; then
        # 조건부 백업
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --single-transaction \
            --skip-triggers \
            --where="$WHERE_CONDITION" \
            "$SOURCE_DB" "$TABLE" > "$BACKUP_FILE" 2>/dev/null
    else
        # 전체 백업 (코드 테이블 또는 날짜 컬럼 없는 테이블)
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --single-transaction \
            --skip-triggers \
            "$SOURCE_DB" "$TABLE" > "$BACKUP_FILE" 2>/dev/null
    fi
    
    # 백업 결과 확인
    if [ $? -eq 0 ] && [ -s "$BACKUP_FILE" ]; then
        file_size=$(du -h "$BACKUP_FILE" | cut -f1)
        echo "  ✅ $TABLE 백업 완료: $(basename "$BACKUP_FILE") ($file_size)"
        log_to_db "backup_${TABLE}" "$TABLE: 백업 성공 ($CHANGED_ROWS rows, $file_size)"
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
echo "변경된 테이블: $CHANGED_COUNT"
echo "백업 성공: $SUCCESS_COUNT"
echo "백업 실패: $FAILED_COUNT"

if [ $FAILED_COUNT -gt 0 ]; then
    echo "실패한 테이블: $FAILED_TABLES"
fi

# 성공한 백업이 하나라도 있는지 확인
if [ $SUCCESS_COUNT -eq 0 ]; then
    echo "변경된 데이터가 없거나 모든 백업이 실패했습니다."
    log_to_db "backup" "백업 실패: 변경된 데이터 없음"
    exit 0
fi

# 증분 백업 파일들 찾기
INCREMENTAL_FILES=$(find "$BACKUP_DIR" -name "incremental_*_${TIMESTAMP}.sql" -type f)

if [ -z "$INCREMENTAL_FILES" ]; then
    echo "백업할 변경사항이 없습니다."
    log_to_db "backup" "백업 실패: 백업할 변경사항 없음"
    exit 0
fi

echo ""
echo "=== webhosting 데이터베이스로 복원 시작 ==="

# 복원 성공/실패 카운터
RESTORE_SUCCESS=0
RESTORE_FAILED=0

# 각 증분 백업 파일을 타겟 DB에 복원
for FILE in $INCREMENTAL_FILES; do
    echo "복원 중: $(basename "$FILE")"
    
    # INSERT INTO를 REPLACE INTO로 변경하여 중복 시 덮어쓰기
    TEMP_FILE="${FILE}.temp"
    
    sed 's/^INSERT INTO/REPLACE INTO/' "$FILE" > "$TEMP_FILE"
    
    # 타겟 DB에 복원
    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$TEMP_FILE"
    
    if [ $? -eq 0 ]; then
        echo "  ✅ $(basename "$FILE") 복원 완료"
        log_to_db "backup_$(basename "$FILE" | cut -d'_' -f2)" "$(basename "$FILE"): 복원 성공"
        ((RESTORE_SUCCESS++))
        # 성공한 파일은 보관 (나중에 정리)
        mv "$TEMP_FILE" "${FILE}.restored"
    else
        echo "  ❌ $(basename "$FILE") 복원 실패"
        log_to_db "backup_$(basename "$FILE" | cut -d'_' -f2)" "$(basename "$FILE"): 복원 실패"
        ((RESTORE_FAILED++))
        rm -f "$TEMP_FILE"
    fi
done

# 백업 완료 로그 기록
if [ $RESTORE_FAILED -eq 0 ]; then
    log_to_db "backup" "백업 완료: $SUCCESS_COUNT개 테이블 성공, $RESTORE_SUCCESS개 파일 복원 성공"
else
    log_to_db "backup" "백업 실패: $RESTORE_FAILED개 파일 복원 실패"
fi

echo ""
echo "=== 증분 백업 완료 ==="
echo "백업 성공: $SUCCESS_COUNT 테이블"
echo "복원 성공: $RESTORE_SUCCESS 파일"
echo "복원 실패: $RESTORE_FAILED 파일"

if [ $RESTORE_FAILED -gt 0 ]; then
    echo "⚠️  일부 복원이 실패했습니다. 로그를 확인하세요."
fi

echo "✅ 증분 백업이 완료되었습니다."
echo "백업결과 확인: SELECT * FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 10;"
