#!/bin/bash

# 데이터베이스 복구 스크립트
# 백업 파일에서 데이터를 복원합니다

# webhosting (타겟) - 복구할 데이터베이스
TARGET_DB="u753849441_fw9gm"
TARGET_USER="u753849441_cASrk"
TARGET_PASS="w3m69P21!@"
TARGET_HOST="45.130.228.170"

BACKUP_DIR="/home/joeunfish.net/backup/backup_db"

echo "=== 데이터베이스 복구 시작 ==="
echo "복구 대상: $TARGET_DB"
echo "백업 디렉토리: $BACKUP_DIR"

# 백업 파일 확인
if [ ! -f "$BACKUP_DIR/bk/backup_all.sql.bk" ]; then
    echo "오류: 전체 백업 파일을 찾을 수 없습니다: $BACKUP_DIR/bk/backup_all.sql.bk"
    exit 1
fi

echo "전체 백업 파일을 찾았습니다. 복구를 시작합니다..."

# 1단계: 전체 백업에서 복구 (권한 문제 해결, wp_users와 wp_usermeta 포함)
echo "1단계: 전체 백업에서 복구 중..."
TEMP_ALL_FILE="$BACKUP_DIR/bk/backup_all_clean.sql"

# 전체 백업 파일에서 권한 문제가 되는 부분 제거
awk '
BEGIN { skip = 0; in_trigger = 0; in_incomplete_query = 0; in_insert = 0; in_wp_table = 0 }

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

# wp_ 테이블 관련 처리 (wp_users와 wp_usermeta는 제외)
/^CREATE TABLE `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }
/^INSERT INTO `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }
/^DROP TABLE IF EXISTS `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }
/^LOCK TABLES `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }

# 다른 wp_ 테이블들은 제외
/^CREATE TABLE `wp_/ { in_wp_table = 1; skip = 1; next }
/^INSERT INTO `wp_/ { in_wp_table = 1; skip = 1; next }
/^DROP TABLE IF EXISTS `wp_/ { in_wp_table = 1; skip = 1; next }
/^LOCK TABLES `wp_/ { in_wp_table = 1; skip = 1; next }
/^UNLOCK TABLES;/ { if (in_wp_table) { in_wp_table = 0; skip = 0 } }

# wp_ 테이블 블록이 끝나는 지점 감지
/^\/\*!40101 SET/ { if (in_wp_table) { in_wp_table = 0; skip = 0 } }
/^\/\*!40014 SET/ { if (in_wp_table) { in_wp_table = 0; skip = 0 } }

# 불완전한 쿼리 블록 감지
/^FROM wp_users u$/ { in_incomplete_query = 1; skip = 1; next }
/^AND u\.user_registered < NOW() - INTERVAL 10 MINUTE/ { 
    if (in_incomplete_query) { 
        in_incomplete_query = 0; 
        skip = 0 
    }
}

# INSERT 문 처리
/^INSERT INTO/ { 
    in_insert = 1
    gsub(/^INSERT INTO/, "INSERT IGNORE INTO")
    print
    next
}

# INSERT 문이 끝나는 지점 감지 (세미콜론)
/;$/ { 
    if (in_insert) { 
        in_insert = 0 
    }
}

# 출력 조건
!skip && !in_trigger && !in_incomplete_query && !in_wp_table { print }
' "$BACKUP_DIR/bk/backup_all.sql.bk" > "$TEMP_ALL_FILE"

# 정리된 전체 백업으로 복구
mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$TEMP_ALL_FILE"

if [ $? -eq 0 ]; then
    echo "전체 백업 복구 완료!"
else
    echo "오류: 전체 백업 복구 실패"
    rm -f "$TEMP_ALL_FILE"
    exit 1
fi

# 임시 파일 삭제
rm -f "$TEMP_ALL_FILE"

# 2단계: 최신 데이터 업데이트 (wp_users와 wp_usermeta 포함)
echo "2단계: 최신 데이터 업데이트 중..."

# 최근 백업 파일들로 업데이트
for FILE in "$BACKUP_DIR"/t_*.sql; do
    if [ -f "$FILE" ]; then
        echo "업데이트 중: $(basename "$FILE")"
        
        # 임시 파일 생성 (에러 제거)
        TEMP_FILE="${FILE}.temp"
        
        # awk를 사용하여 에러 제거
        awk '
        BEGIN { skip = 0; in_trigger = 0; in_incomplete_query = 0; in_insert = 0; in_wp_table = 0 }
        
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
        
        # wp_ 테이블 관련 처리 (wp_users와 wp_usermeta는 제외)
        /^CREATE TABLE `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }
        /^INSERT INTO `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }
        /^DROP TABLE IF EXISTS `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }
        /^LOCK TABLES `wp_(users|usermeta)`/ { in_wp_table = 0; skip = 0; print; next }
        
        # 다른 wp_ 테이블들은 제외
        /^CREATE TABLE `wp_/ { in_wp_table = 1; skip = 1; next }
        /^INSERT INTO `wp_/ { in_wp_table = 1; skip = 1; next }
        /^DROP TABLE IF EXISTS `wp_/ { in_wp_table = 1; skip = 1; next }
        /^LOCK TABLES `wp_/ { in_wp_table = 1; skip = 1; next }
        /^UNLOCK TABLES;/ { if (in_wp_table) { in_wp_table = 0; skip = 0 } }
        
        # wp_ 테이블 블록이 끝나는 지점 감지
        /^\/\*!40101 SET/ { if (in_wp_table) { in_wp_table = 0; skip = 0 } }
        /^\/\*!40014 SET/ { if (in_wp_table) { in_wp_table = 0; skip = 0 } }
        
        # 불완전한 쿼리 블록 감지
        /^FROM wp_users u$/ { in_incomplete_query = 1; skip = 1; next }
        /^AND u\.user_registered < NOW() - INTERVAL 10 MINUTE/ { 
            if (in_incomplete_query) { 
                in_incomplete_query = 0; 
                skip = 0 
            }
        }
        
        # INSERT 문 처리
        /^INSERT INTO/ { 
            in_insert = 1
            gsub(/^INSERT INTO/, "INSERT IGNORE INTO")
            print
            next
        }
        
        # INSERT 문이 끝나는 지점 감지 (세미콜론)
        /;$/ { 
            if (in_insert) { 
                in_insert = 0 
            }
        }
        
        # 출력 조건
        !skip && !in_trigger && !in_incomplete_query && !in_wp_table { print }
        ' "$FILE" > "$TEMP_FILE"
        
        # 복구 실행
        mysql -h "$TARGET_HOST" -u "$TARGET_USER" -p"$TARGET_PASS" "$TARGET_DB" < "$TEMP_FILE"
        
        if [ $? -eq 0 ]; then
            echo "  ✓ $(basename "$FILE") 업데이트 완료"
        else
            echo "  ✗ $(basename "$FILE") 업데이트 실패"
        fi
        
        # 임시 파일 삭제
        rm -f "$TEMP_FILE"
    fi
done

# 3단계: WordPress 사용자 데이터 업데이트 (제거됨)
echo "3단계: WordPress 사용자 데이터 업데이트는 제외되었습니다."

echo "=== 데이터베이스 복구 완료 ==="
echo "복구가 성공적으로 완료되었습니다!"
echo "WordPress 테이블 중 wp_users와 wp_usermeta는 포함되었습니다."
echo "다른 wp_ 테이블들은 제외되었습니다."
echo "데이터베이스를 확인해보세요." 