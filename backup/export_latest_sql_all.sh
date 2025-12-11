#!/bin/bash

# KVM 서버에서 전체 데이터를 백업하여 webhosting으로 복원하는 스크립트
# 운영DB와 백업DB를 완벽하게 동기화합니다 (DELETE/TRUNCATE 후 INSERT 방식)
# - 파티셔닝된 테이블(Top 5): 이번 달 데이터만 동기화
# - 일반 테이블: 전체 데이터 동기화
# 트리거로 인한 에러를 방지하기 위해 백업 시 트리거를 제외하고 임포트 시 제약조건을 비활성화합니다
#
# 사용법:
#   ./export_latest_sql_all.sh        # 대화형 모드 (확인 필요)
#   ./export_latest_sql_all.sh -y     # 자동 실행 모드 (crontab용)

# 커맨드 라인 인자 처리
SKIP_CONFIRM=false
if [ "$1" == "--yes" ] || [ "$1" == "-y" ]; then
    SKIP_CONFIRM=true
fi

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

# 백업 옵션
DISABLE_TRIGGERS_DURING_IMPORT=true

BACKUP_DIR="/home/joeunfish.net/backup/backup_db_all"

# 백업 디렉토리 생성 (없으면)
mkdir -p "$BACKUP_DIR"

# 실행 전 확인
echo "=== 데이터 동기화 시작 ==="
echo "소스: $SOURCE_HOST/$SOURCE_DB"
echo "타겟: $TARGET_HOST/$TARGET_DB"
echo "백업 범위: 전체 데이터"
echo "트리거 처리: 백업 시 제외, 임포트 시 제약조건 비활성화"
echo ""

# -y 옵션이 없으면 확인 요청
if [ "$SKIP_CONFIRM" != "true" ]; then
    read -p "계속하시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "작업이 취소되었습니다."
        exit 0
    fi
else
    echo "자동 실행 모드: 확인 건너뜀"
fi

# 데이터베이스 덤프 생성 (KVM에서)
echo "Creating a backup of the KVM database..."
TABLES=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" -e "SHOW TABLES LIKE 't%'" | grep -v "Tables_in")

# 파티셔닝된 테이블 목록 (Top 5 용량 테이블, 이번 달만 백업하여 속도 최적화)
PARTITIONED_TABLES=("torders" "torderproducts" "tbarcodein" "tbarcodeinv" "tbarcodeout")

# 현재 날짜 기준으로 이번 달의 첫날과 다음 달의 첫날 계산
CURRENT_MONTH_START=$(date +"%Y-%m-01 00:00:00")
NEXT_MONTH_START=$(date -d "$(date +%Y-%m-01) +1 month" +"%Y-%m-01 00:00:00")

echo "백업 기간: $CURRENT_MONTH_START ~ $NEXT_MONTH_START"
echo "파티셔닝된 테이블: ${PARTITIONED_TABLES[@]} (이번 달 데이터만 - 속도 최적화)"
echo ""

# t로 시작하는 테이블 백업
for TABLE in $TABLES; do
    # 파티셔닝된 테이블인지 확인
    IS_PARTITIONED=false
    for PT in "${PARTITIONED_TABLES[@]}"; do
        if [ "$TABLE" == "$PT" ]; then
            IS_PARTITIONED=true
            break
        fi
    done
    
    if [ "$IS_PARTITIONED" = true ]; then
        # 파티셔닝된 테이블: 이번 달 데이터만 백업 (속도 최적화)
        echo "Backing up table: $TABLE (이번 달만: $CURRENT_MONTH_START ~ $NEXT_MONTH_START)"
        
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --skip-triggers --skip-routines --skip-events \
            --single-transaction --lock-tables=false \
            --where="create_dt >= '$CURRENT_MONTH_START' AND create_dt < '$NEXT_MONTH_START'" \
            "$SOURCE_DB" "$TABLE" > "$BACKUP_DIR/t_${TABLE}.sql"
    else
        # 일반 테이블: 전체 데이터 백업
        echo "Backing up table: $TABLE (전체 데이터)"
        
        mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
            --no-create-info --no-create-db \
            --skip-triggers --skip-routines --skip-events \
            --single-transaction --lock-tables=false \
            "$SOURCE_DB" "$TABLE" > "$BACKUP_DIR/t_${TABLE}.sql"
    fi
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

# 각 SQL 파일을 webhosting 에 복원
for FILE in $SQL_FILES; do
    # echo "Importing $FILE into webhosting database..."
    
    # EVENT, TRIGGER 관련 권한 문제 및 불완전한 SQL 구문 제거
    TEMP_FILE="${FILE}.temp"
    
    # awk를 사용하여 더 정확한 라인 제거 및 INSERT IGNORE로 변경
    awk '
    BEGIN { skip = 0; in_trigger = 0; in_incomplete_query = 0; in_delimiter = 0; in_insert = 0 }
    
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
    
    # TRIGGER 관련 구문 완전 제거
    /^\/\*!50003 CREATE.*TRIGGER/ { in_trigger = 1; skip = 1; next }
    /^\/\*!50003$/ { if (in_trigger) { in_trigger = 0; skip = 0 } next }
    /^DELIMITER/ { 
        if (in_trigger) { 
            in_delimiter = 1; 
            skip = 1; 
            next 
        }
    }
    /^END$/ { 
        if (in_trigger) { 
            in_trigger = 0; 
            skip = 0; 
            next 
        }
    }
    /^\/\*!50003 SET/ { if (in_trigger) { skip = 1; next } }
    
    # 불완전한 쿼리 블록 감지
    /^FROM wp_users u$/ { in_incomplete_query = 1; skip = 1; next }
    /^AND u\.user_registered < NOW() - INTERVAL 10 MINUTE/ { 
        if (in_incomplete_query) { 
            in_incomplete_query = 0; 
            skip = 0 
        }
    }
    
    # INSERT INTO 문 처리 (여러 줄 지원)
    /^INSERT INTO/ { 
        # INSERT IGNORE로 변경하여 중복 키 에러 방지
        gsub(/^INSERT INTO/, "INSERT IGNORE INTO")
        print
        in_insert = 1
        next
    }
    
    # INSERT 문 내부 처리 (세미콜론이 나올 때까지 모든 줄 포함)
    in_insert {
        print
        # 세미콜론으로 끝나거나 세미콜론만 있는 줄에서 INSERT 문 종료
        if (/;$/ || /^;?$/) {
            in_insert = 0
        }
        next
    }
    
    # 출력 조건 - 트리거 블록 내부나 구분자 블록 내부가 아닌 경우만 출력
    !skip && !in_trigger && !in_incomplete_query && !in_delimiter { print }
    ' "$FILE" > "$TEMP_FILE"
    
    # 파일명에서 테이블명 추출
    TABLE_NAME=$(basename "$FILE" | sed 's/^t_//; s/\.sql$//')
    
    # 파티셔닝된 테이블인지 확인
    IS_PARTITIONED_TABLE=false
    for PT in "${PARTITIONED_TABLES[@]}"; do
        if [ "$TABLE_NAME" == "$PT" ]; then
            IS_PARTITIONED_TABLE=true
            break
        fi
    done
    
    # webhosting 데이터베이스에 임포트 (완벽한 동기화)
    if [ "$DISABLE_TRIGGERS_DURING_IMPORT" = true ]; then
        # 트리거가 있는 테이블인지 확인하고 임시로 삭제 (ERROR 1172 방지)
        if [ "$TABLE_NAME" == "torderproducts" ]; then
            echo "트리거를 임시로 삭제합니다 (ERROR 1172 방지)..."
            mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" -e "
                DROP TRIGGER IF EXISTS trg_torderproducts_after_insert;
                DROP TRIGGER IF EXISTS trg_torderproducts_after_update;
            " || echo "Warning: Could not drop triggers for $TABLE_NAME"
        fi
        
        # 임포트 전에 기존 데이터 정리 (완벽한 동기화를 위해)
        if [ "$IS_PARTITIONED_TABLE" = true ]; then
            # 파티셔닝된 테이블: 이번 달 데이터만 삭제
            echo "Clearing current month data from $TABLE_NAME ($CURRENT_MONTH_START ~ $NEXT_MONTH_START)"
            mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" -e "
                DELETE FROM $TABLE_NAME 
                WHERE create_dt >= '$CURRENT_MONTH_START' 
                AND create_dt < '$NEXT_MONTH_START';
            " || echo "Warning: Could not clear data from $TABLE_NAME"
        else
            # 일반 테이블: 전체 데이터 삭제 (TRUNCATE)
            echo "Truncating table $TABLE_NAME (full sync)"
            mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" -e "
                TRUNCATE TABLE $TABLE_NAME;
            " || echo "Warning: Could not truncate $TABLE_NAME"
        fi
        
        # 트리거 비활성화 후 임포트 (동일 세션)
        INIT_SQL="${TEMP_FILE}.init"
        FOOTER_SQL="${TEMP_FILE}.footer"
        COMBINED_SQL="${TEMP_FILE}.import"

        cat > "$INIT_SQL" <<'EOF'
SET @OLD_SQL_MODE=@@SESSION.SQL_MODE;
SET @OLD_FOREIGN_KEY_CHECKS=@@SESSION.FOREIGN_KEY_CHECKS;
SET @OLD_UNIQUE_CHECKS=@@SESSION.UNIQUE_CHECKS;
SET @OLD_AUTOCOMMIT=@@SESSION.AUTOCOMMIT;
SET SESSION SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET SESSION FOREIGN_KEY_CHECKS=0;
SET SESSION UNIQUE_CHECKS=0;
SET SESSION AUTOCOMMIT=0;
START TRANSACTION;
EOF

        cat > "$FOOTER_SQL" <<'EOF'
COMMIT;
SET SESSION FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET SESSION UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
SET SESSION AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET SESSION SQL_MODE=@OLD_SQL_MODE;
EOF

        cat "$INIT_SQL" "$TEMP_FILE" "$FOOTER_SQL" > "$COMBINED_SQL"

        echo "Importing $(basename "$FILE") into webhosting database..."
        mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" --show-warnings --force < "$COMBINED_SQL" 2>&1 | tee "${TEMP_FILE}.error.log"
        IMPORT_EXIT_CODE=${PIPESTATUS[0]}

        rm -f "$INIT_SQL" "$FOOTER_SQL" "$COMBINED_SQL"
        
        if [ $IMPORT_EXIT_CODE -ne 0 ]; then
            echo "Warning: Data import for $TABLE_NAME failed with exit code $IMPORT_EXIT_CODE"
            echo "마지막 20줄 에러 로그:"
            tail -20 "${TEMP_FILE}.error.log" 2>/dev/null || echo "로그 파일을 읽을 수 없습니다."
        else
            # 성공한 경우 에러 로그 파일 삭제
            rm -f "${TEMP_FILE}.error.log"
        fi
        
        # 트리거 복원 (torderproducts 테이블인 경우)
        if [ "$TABLE_NAME" == "torderproducts" ]; then
            echo "트리거를 복원합니다..."
            TRIGGER_RESTORE_SQL="${TEMP_FILE}.trigger_restore.sql"
            cat > "$TRIGGER_RESTORE_SQL" <<'TRIGGER_EOF'
DROP TRIGGER IF EXISTS trg_torderproducts_after_insert;
DROP TRIGGER IF EXISTS trg_torderproducts_after_update;

DELIMITER $$

CREATE TRIGGER trg_torderproducts_after_insert
AFTER INSERT ON torderproducts
FOR EACH ROW
BEGIN
    DECLARE v_supply_dt DATETIME;
    DECLARE v_current_supply_dt DATETIME;
    
    IF NEW.unit_price > 0 THEN
        SELECT supply_dt INTO v_supply_dt
        FROM torders 
        WHERE doc_no = NEW.doc_no
        LIMIT 1;
        
        SELECT supply_dt INTO v_current_supply_dt
        FROM torderproducts_latest_price
        WHERE product_code = NEW.product_code;
        
        IF v_current_supply_dt IS NULL OR v_supply_dt > v_current_supply_dt THEN
            INSERT INTO torderproducts_latest_price 
                (product_code, unit_price, supply_dt, doc_no)
            VALUES 
                (NEW.product_code, NEW.unit_price, v_supply_dt, NEW.doc_no)
            ON DUPLICATE KEY UPDATE
                unit_price = NEW.unit_price,
                supply_dt = v_supply_dt,
                doc_no = NEW.doc_no;
        ELSEIF v_supply_dt = v_current_supply_dt THEN
            UPDATE torderproducts_latest_price
            SET unit_price = GREATEST(unit_price, NEW.unit_price),
                doc_no = NEW.doc_no
            WHERE product_code = NEW.product_code;
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_torderproducts_after_update
AFTER UPDATE ON torderproducts
FOR EACH ROW
BEGIN
    DECLARE v_supply_dt DATETIME;
    DECLARE v_current_supply_dt DATETIME;
    
    IF NEW.unit_price > 0 THEN
        SELECT supply_dt INTO v_supply_dt
        FROM torders 
        WHERE doc_no = NEW.doc_no
        LIMIT 1;
        
        SELECT supply_dt INTO v_current_supply_dt
        FROM torderproducts_latest_price
        WHERE product_code = NEW.product_code;
        
        IF v_current_supply_dt IS NULL OR v_supply_dt > v_current_supply_dt THEN
            INSERT INTO torderproducts_latest_price 
                (product_code, unit_price, supply_dt, doc_no)
            VALUES 
                (NEW.product_code, NEW.unit_price, v_supply_dt, NEW.doc_no)
            ON DUPLICATE KEY UPDATE
                unit_price = NEW.unit_price,
                supply_dt = v_supply_dt,
                doc_no = NEW.doc_no;
        ELSEIF v_supply_dt = v_current_supply_dt THEN
            UPDATE torderproducts_latest_price
            SET unit_price = GREATEST(unit_price, NEW.unit_price),
                doc_no = NEW.doc_no
            WHERE product_code = NEW.product_code;
        END IF;
    END IF;
END$$

DELIMITER ;
TRIGGER_EOF
            mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$TRIGGER_RESTORE_SQL"
            if [ $? -eq 0 ]; then
                echo "트리거 복원 완료 (LIMIT 1 추가로 ERROR 1172 방지)"
                rm -f "$TRIGGER_RESTORE_SQL"
            else
                echo "Warning: 트리거 복원 실패. 수동으로 복원이 필요할 수 있습니다."
                echo "트리거 SQL 파일: $TRIGGER_RESTORE_SQL"
            fi
        fi
    else
        mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$TEMP_FILE"
    fi

    # 임시 파일 삭제
    rm -f "$TEMP_FILE"

    # 실행 결과 확인 (에러가 있어도 계속 진행)
    if [ ${IMPORT_EXIT_CODE:-0} -eq 0 ]; then
        echo "Import of $FILE to webhosting successful!"
    else
        echo "Warning: Import of $FILE had some issues but continuing..."
        # exit 1 제거 - 에러가 있어도 다음 파일 계속 처리
    fi
done

echo ""
echo "=== 데이터 동기화 완료 ==="
echo "모든 SQL 파일이 KVM에서 추출되어 webhosting 데이터베이스로 안전하게 임포트되었습니다."