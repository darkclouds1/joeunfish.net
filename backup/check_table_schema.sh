#!/bin/bash

# 데이터베이스 테이블 스키마 확인 스크립트

# KVM 서버 (소스)
SOURCE_DB="joeu_fish"
SOURCE_USER="u753849441_cASrk"
SOURCE_PASS="w3m69P21!@"
SOURCE_HOST="localhost"

echo "=== 테이블 스키마 확인 ==="
echo "데이터베이스: $SOURCE_DB"
echo ""

# t로 시작하는 테이블 목록 가져오기
TABLES=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" -e "SHOW TABLES LIKE 't%'" | grep -v "Tables_in")

echo "t로 시작하는 테이블 목록:"
echo "$TABLES"
echo ""

# 각 테이블의 컬럼 정보 확인
for TABLE in $TABLES; do
    echo "=== $TABLE 테이블 구조 ==="
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" -e "DESCRIBE $TABLE"
    echo ""
done

# 날짜 관련 컬럼이 있는지 확인
echo "=== 날짜 관련 컬럼 확인 ==="
for TABLE in $TABLES; do
    echo "테이블: $TABLE"
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" -e "SHOW COLUMNS FROM $TABLE LIKE '%dt%' OR SHOW COLUMNS FROM $TABLE LIKE '%date%' OR SHOW COLUMNS FROM $TABLE LIKE '%time%'" 2>/dev/null || echo "  날짜 관련 컬럼 없음"
    echo ""
done 