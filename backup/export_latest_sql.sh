#!/bin/bash

# KVM 서버에서 증분 백업하여 webhosting으로 복원하는 최적화된 스크립트
# 병렬 처리, 연결 최적화, 불필요한 쿼리 제거로 성능 대폭 개선

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

# 최초 백업 시간 설정
INITIAL_BACKUP_TIME="20250713_000000"

# 병렬 처리 설정
MAX_PARALLEL=4
TEMP_DIR="/tmp/backup_$$"

# 백업 디렉토리 생성
mkdir -p "$BACKUP_DIR" "$TEMP_DIR"

# 전역 변수
SUCCESS_COUNT=0
FAILED_COUNT=0
SKIPPED_COUNT=0
FAILED_TABLES=""
CHANGED_COUNT=0

# 실행 시작 시간 기록
START_TIME=$(date +%s)

echo "=== 최적화된 증분 백업 시작 ==="
echo "소스: $SOURCE_HOST/$SOURCE_DB"
echo "타겟: $TARGET_HOST/$TARGET_DB"
echo "백업 시간: $TIMESTAMP"
echo "병렬 처리: 최대 $MAX_PARALLEL개 동시 작업"
echo ""

# MySQL 연결 테스트 함수
test_mysql_connection() {
    local host=$1
    local user=$2
    local pass=$3
    local db=$4
    
    mysql -h "$host" -u "$user" -p"$pass" -D "$db" -e "SELECT 1" >/dev/null 2>&1
    return $?
}

# 연결 테스트
echo "데이터베이스 연결 테스트 중..."
if ! test_mysql_connection "$SOURCE_HOST" "$SOURCE_USER" "$SOURCE_PASS" "$SOURCE_DB"; then
    echo "❌ 소스 DB 연결 실패: $SOURCE_HOST/$SOURCE_DB"
    exit 1
fi

if ! test_mysql_connection "$TARGET_HOST" "$TARGET_USER" "$TARGET_PASS" "$TARGET_DB"; then
    echo "❌ 타겟 DB 연결 실패: $TARGET_HOST/$TARGET_DB"
    exit 1
fi

echo "✅ 데이터베이스 연결 성공"

# logDB에 로그 기록 함수 (최적화)
log_to_db() {
    local pg=$1
    local rmrk=$2
    
    # 백그라운드에서 로그 기록 (블로킹 방지)
    (
        mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
            -e "INSERT INTO logdb (pg, id, ip, rmrk) VALUES ('$pg', 'system', '127.0.0.1', '$rmrk')" 2>/dev/null
    ) &
}

# 마지막 백업 시간 조회 (캐싱)
declare -A LAST_BACKUP_CACHE
get_last_backup_time() {
    if [ -z "${LAST_BACKUP_CACHE[backup]}" ]; then
        local last_backup=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
            -e "SELECT dt FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 1" -s --skip-column-names 2>/dev/null)
        
        if [ -n "$last_backup" ]; then
            LAST_BACKUP_CACHE[backup]="$last_backup"
        else
            LAST_BACKUP_CACHE[backup]="$INITIAL_BACKUP_TIME"
        fi
    fi
    echo "${LAST_BACKUP_CACHE[backup]}"
}

get_last_restore_time() {
    local table_name=$1
    if [ -z "${LAST_BACKUP_CACHE[$table_name]}" ]; then
        local last_restore=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
            -e "SELECT dt FROM logdb WHERE pg = 'backup_${table_name}' AND rmrk LIKE '%복원 성공%' ORDER BY dt DESC LIMIT 1" -s --skip-column-names 2>/dev/null)
        if [ -n "$last_restore" ]; then
            LAST_BACKUP_CACHE[$table_name]="$last_restore"
        else
            LAST_BACKUP_CACHE[$table_name]="$INITIAL_BACKUP_TIME"
        fi
    fi
    echo "${LAST_BACKUP_CACHE[$table_name]}"
}

# 테이블 목록 가져오기 (한 번만 실행)
echo "테이블 목록 확인 중..."
TABLES=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
    -e "SHOW TABLES LIKE 't%'" | grep -v "Tables_in")

echo "총 테이블 수: $(echo $TABLES | wc -w)"
echo ""

# 백업 시작 로그
log_to_db "backup" "최적화된 백업 시작: $TIMESTAMP"

# 파티셔닝된 테이블 목록 (최근 마지막 달만 백업)
PARTITIONED_TABLES=("torders" "torderproducts" "tbarcodein" "tbarcodeinv" "tbarcodeout")

# 현재 달의 시작일과 다음 달의 시작일 계산 (전역 변수로 한 번만 계산)
CURRENT_MONTH_START=$(date +"%Y-%m-01 00:00:00")
NEXT_MONTH_START=$(date -d "$(date +%Y-%m-01) +1 month" +"%Y-%m-01 00:00:00")

# WHERE 조건 설정 함수
get_where_condition() {
    local table=$1
    local last_backup=$2
    
    # 파티셔닝된 테이블인지 확인
    for PT in "${PARTITIONED_TABLES[@]}"; do
        if [ "$table" == "$PT" ]; then
            # 파티셔닝된 테이블: 최근 마지막 달만 + 증분 조건 결합
            echo "create_dt >= '$CURRENT_MONTH_START' AND create_dt < '$NEXT_MONTH_START' AND create_dt > '$last_backup'"
            return
        fi
    done
    
    # 일반 테이블의 기존 로직
    case $table in
        "tascinvt"|"tascinvt3"|"tbarcodeinv_m"|"tdayprod"|"tdayprod_lot"|"tevent"|"tgscodeout"|"tgscodeout_invent"|"tpurchase_in")
            echo "increate_dt > '$last_backup'"
            ;;
        "tdayprod_mst"|"tdayprodmat_mst"|"torderproducts_add"|"tpurchase")
            echo "create_dt > '$last_backup'"
            ;;
        "tprodinvt")
            echo "invt_dt > '$last_backup'"
            ;;
        "tpurchase_imp")
            echo "import_dt > '$last_backup'"
            ;;
        "tcomcode"|"tcomcodegs")
            # 코드 테이블은 전체 백업
            echo ""
            ;;
        *)
            # 날짜 컬럼이 없는 테이블은 전체 백업
            echo ""
            ;;
    esac
}

# 병렬 백업 함수
backup_table_parallel() {
    local table=$1
    local timestamp=$2
    local result_file="$TEMP_DIR/result_$table"
    
    echo "백업 중: [$table]" > "$result_file"
    
    # 마지막 백업 시간 조회
    local last_backup=$(get_last_restore_time "$table")
    local backup_file="$BACKUP_DIR/incremental_${table}_${timestamp}.sql"
    
    # WHERE 조건 설정
    local where_condition=$(get_where_condition "$table" "$last_backup")
    
    # 백업 실행 (COUNT 쿼리 제거 - 직접 백업 후 파일 크기로 판단)
    if [ -n "$where_condition" ]; then
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --single-transaction --skip-triggers \
            --where="$where_condition" \
            "$SOURCE_DB" "$table" > "$backup_file" 2>/dev/null
    else
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --single-transaction --skip-triggers \
            "$SOURCE_DB" "$table" > "$backup_file" 2>/dev/null
    fi
    
    # 결과 확인
    if [ $? -eq 0 ] && [ -s "$backup_file" ]; then
        local file_size=$(du -h "$backup_file" | cut -f1)
        echo "  ✅ $table 백업 완료: $(basename "$backup_file") ($file_size)" >> "$result_file"
        log_to_db "backup_${table}" "$table: 백업 성공 ($file_size)"
        echo "SUCCESS:$table:$file_size" >> "$result_file"
    else
        echo "  ✓ $table: 변경사항 없음 (건너뜀)" >> "$result_file"
        log_to_db "backup_${table}" "$table: 변경사항 없음 (SKIPPED)"
        echo "SKIPPED:$table" >> "$result_file"
        rm -f "$backup_file"
    fi
}

# 병렬 백업 실행
echo "=== 병렬 백업 시작 ==="
pids=()
table_count=0

for TABLE in $TABLES; do
    # 최대 병렬 수 제한
    while [ ${#pids[@]} -ge $MAX_PARALLEL ]; do
        for i in "${!pids[@]}"; do
            if ! kill -0 "${pids[$i]}" 2>/dev/null; then
                unset pids[$i]
            fi
        done
        pids=("${pids[@]}")  # 배열 재정렬
        sleep 0.1
    done
    
    # 백그라운드에서 백업 실행
    backup_table_parallel "$TABLE" "$TIMESTAMP" &
    pids+=($!)
    ((table_count++))
    
    # 진행률 표시
    echo -n "."
    if [ $((table_count % 20)) -eq 0 ]; then
        echo " ($table_count/$(echo $TABLES | wc -w))"
    fi
done

echo ""
echo "모든 백업 작업 시작 완료. 완료 대기 중..."

# 모든 백그라운드 작업 완료 대기
for pid in "${pids[@]}"; do
    wait $pid
done

# 결과 수집
echo "=== 백업 결과 수집 ==="
for result_file in "$TEMP_DIR"/result_*; do
    if [ -f "$result_file" ]; then
        cat "$result_file"
        echo ""
        
        # 결과 파싱
        local result_line=$(grep "^SUCCESS:\|^SKIPPED:" "$result_file")
        if [[ "$result_line" == "SUCCESS:"* ]]; then
            ((SUCCESS_COUNT++))
            ((CHANGED_COUNT++))
        elif [[ "$result_line" == "SKIPPED:"* ]]; then
            ((SKIPPED_COUNT++))
        else
            ((FAILED_COUNT++))
            local table_name=$(basename "$result_file" | sed 's/result_//')
            FAILED_TABLES="$FAILED_TABLES $table_name"
        fi
    fi
done

# 임시 파일 정리
rm -rf "$TEMP_DIR"

echo ""
echo "=== 백업 결과 요약 ==="
echo "총 테이블: $(echo $TABLES | wc -w)"
echo "백업 성공: $SUCCESS_COUNT"
echo "변경사항 없음: $SKIPPED_COUNT"
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

# 병렬 복원 함수
restore_file_parallel() {
    local file=$1
    local result_file="$TEMP_DIR/restore_$(basename "$file")"
    
    echo "복원 중: $(basename "$file")" > "$result_file"
    
    # INSERT INTO를 INSERT IGNORE INTO로 변경하여 중복 시 무시
    local temp_file="${file}.temp"
    
    sed 's/^INSERT INTO/INSERT IGNORE INTO/' "$file" > "$temp_file"
    
    # 타겟 DB에 복원
    mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$temp_file"
    
    if [ $? -eq 0 ]; then
        echo "  ✅ $(basename "$file") 복원 완료" >> "$result_file"
        local table_name=$(basename "$file" | cut -d'_' -f2)
        log_to_db "backup_${table_name}" "$(basename "$file"): 복원 성공"
        echo "SUCCESS:$(basename "$file")" >> "$result_file"
        # 성공한 파일은 보관 (나중에 정리)
        mv "$temp_file" "${file}.restored"
    else
        echo "  ❌ $(basename "$file") 복원 실패" >> "$result_file"
        local table_name=$(basename "$file" | cut -d'_' -f2)
        log_to_db "backup_${table_name}" "$(basename "$file"): 복원 실패"
        echo "FAILED:$(basename "$file")" >> "$result_file"
        rm -f "$temp_file"
    fi
}

# 병렬 복원 실행
mkdir -p "$TEMP_DIR"
pids=()

for FILE in $INCREMENTAL_FILES; do
    # 최대 병렬 수 제한
    while [ ${#pids[@]} -ge $MAX_PARALLEL ]; do
        for i in "${!pids[@]}"; do
            if ! kill -0 "${pids[$i]}" 2>/dev/null; then
                unset pids[$i]
            fi
        done
        pids=("${pids[@]}")
        sleep 0.1
    done
    
    restore_file_parallel "$FILE" &
    pids+=($!)
done

# 모든 복원 작업 완료 대기
for pid in "${pids[@]}"; do
    wait $pid
done

# 복원 결과 수집
for result_file in "$TEMP_DIR"/restore_*; do
    if [ -f "$result_file" ]; then
        cat "$result_file"
        
        local result_line=$(grep "^SUCCESS:\|^FAILED:" "$result_file")
        if [[ "$result_line" == "SUCCESS:"* ]]; then
            ((RESTORE_SUCCESS++))
        else
            ((RESTORE_FAILED++))
        fi
    fi
done

# 임시 파일 정리
rm -rf "$TEMP_DIR"

# 백업 완료 로그 기록
if [ $RESTORE_FAILED -eq 0 ]; then
    log_to_db "backup" "최적화된 백업 완료: $SUCCESS_COUNT개 테이블 성공, $RESTORE_SUCCESS개 파일 복원 성공"
else
    log_to_db "backup" "백업 실패: $RESTORE_FAILED개 파일 복원 실패"
fi

# 실행 시간 계산
END_TIME=$(date +%s)
EXECUTION_TIME=$((END_TIME - START_TIME))

echo ""
echo "=== 최적화된 증분 백업 완료 ==="
echo "실행 시간: ${EXECUTION_TIME}초"
echo "백업 성공: $SUCCESS_COUNT 테이블"
echo "복원 성공: $RESTORE_SUCCESS 파일"
echo "복원 실패: $RESTORE_FAILED 파일"

if [ $RESTORE_FAILED -gt 0 ]; then
    echo "⚠️  일부 복원이 실패했습니다. 로그를 확인하세요."
fi

echo "✅ 최적화된 증분 백업이 완료되었습니다."
echo "백업결과 확인: SELECT * FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 10;"
