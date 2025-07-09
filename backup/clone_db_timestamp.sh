#!/bin/bash

# MySQL 접속 정보
USER="'u753849441_cASrk"
PASS="w3m69p21!@"
HOST="localhost"

# 원본 DB 이름
SRC_DB="joeu_fish"

# 현재 날짜와 시간 (예: 20250706_1530)
NOW=$(date +"%Y%m%d_%H%M")

# 대상 DB 이름
DST_DB="joeu_fish_$NOW"

# 1. 대상 DB 생성
mysql -u$USER -p$PASS -h$HOST -e "CREATE DATABASE \`$DST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 2. 원본 DB 덤프 & 복원
mysqldump -u$USER -p$PASS -h$HOST --routines --triggers --single-transaction "$SRC_DB" | mysql -u$USER -p$PASS -h$HOST "$DST_DB"

echo "✅ Database '$SRC_DB' has been cloned to '$DST_DB'"
