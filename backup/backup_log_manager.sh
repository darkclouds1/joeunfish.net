#!/bin/bash

# 백업 로그 관리 스크립트 (logDB 테이블 기반)
SOURCE_DB="joeu_fish"
SOURCE_USER="u753849441_cASrk"
SOURCE_PASS="w3m69P21!@"
SOURCE_HOST="localhost"

BACKUP_DIR="/home/joeunfish.net/backup/backup_db"

# logDB에 로그 기록 함수
log_to_db() {
    local pg=$1
    local rmrk=$2
    
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "INSERT INTO logdb (pg, id, ip, rmrk) VALUES ('$pg', 'system', '127.0.0.1', '$rmrk')" 2>/dev/null
}

# 1. 백업 로그 확인
show_backup_log() {
    echo "=== 백업 로그 확인 ==="
    echo "최근 10회 백업:"
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT dt, rmrk FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 10" 2>/dev/null
    
    echo ""
    echo "총 백업 횟수:"
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT COUNT(*) as '총 백업 횟수' FROM logdb WHERE pg = 'backup'" 2>/dev/null
}

# 2. 상세 백업 로그 확인
show_detail_log() {
    echo "=== 상세 백업 로그 확인 ==="
    echo "최근 20개 백업 상세 정보:"
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT dt, rmrk FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 20" 2>/dev/null
}

# 3. 백업 로그 정리 (30일 이상 된 로그 삭제)
cleanup_old_logs() {
    echo "=== 오래된 백업 로그 정리 ==="
    
    # 30일 이상 된 백업 로그 삭제
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "DELETE FROM logdb WHERE pg = 'backup' AND dt < DATE_SUB(NOW(), INTERVAL 30 DAY)" 2>/dev/null
    
    deleted_count=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT ROW_COUNT()" -s --skip-column-names 2>/dev/null)
    
    echo "30일 이상 된 백업 로그 $deleted_count개가 정리되었습니다."
    
    # 해당 백업 파일들도 삭제
    find "$BACKUP_DIR" -name "*.sql" -mtime +30 -delete
    echo "30일 이상 된 백업 파일들도 삭제되었습니다."
    
    # 정리 완료 로그
    log_to_db "backup" "오래된 로그 정리: $deleted_count개 로그 삭제"
}

# 4. 백업 통계 보기
show_backup_stats() {
    echo "=== 백업 통계 ==="
    
    echo "총 백업 횟수:"
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT COUNT(*) as '총 백업 횟수' FROM logdb WHERE pg = 'backup'" 2>/dev/null
    
    echo ""
    echo "첫 번째 백업:"
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT dt, rmrk FROM logdb WHERE pg = 'backup' ORDER BY dt ASC LIMIT 1" 2>/dev/null
    
    echo ""
    echo "마지막 백업:"
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT dt, rmrk FROM logdb WHERE pg = 'backup' ORDER BY dt DESC LIMIT 1" 2>/dev/null
    
    echo ""
    echo "백업 파일 크기:"
    total_size=$(find "$BACKUP_DIR" -name "incremental_*.sql" -exec du -ch {} + | tail -n 1 | cut -f1)
    echo "총 백업 파일 크기: $total_size"
}

# 5. 백업 파일 삭제
delete_backup_files() {
    echo "=== 백업 파일 삭제 ==="
    
    # 삭제할 파일 목록 확인
    echo "삭제될 파일들:"
    find "$BACKUP_DIR" -name "incremental_*.sql" -o -name "*.restored" | while read file; do
        size=$(du -h "$file" | cut -f1)
        echo "  $(basename "$file") ($size)"
    done
    
    # 사용자 확인
    read -p "정말로 모든 백업 파일을 삭제하시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "삭제가 취소되었습니다."
        return
    fi
    
    # 백업 파일 삭제
    deleted_count=0
    total_size_before=$(find "$BACKUP_DIR" -name "incremental_*.sql" -o -name "*.restored" -exec du -ch {} + | tail -n 1 | cut -f1)
    
    find "$BACKUP_DIR" -name "incremental_*.sql" -o -name "*.restored" | while read file; do
        rm -f "$file"
        ((deleted_count++))
        echo "  삭제됨: $(basename "$file")"
    done
    
    echo ""
    echo "✅ 백업 파일 삭제 완료"
    echo "삭제된 파일 수: $deleted_count"
    echo "절약된 공간: $total_size_before"
    
    # 삭제 로그 기록
    log_to_db "backup" "백업 파일 삭제: $deleted_count개 파일, $total_size_before"
}

# 6. 전체 정리 (로그 + 파일)
cleanup_all() {
    echo "=== 전체 백업 정리 ==="
    
    # 백업 파일 삭제
    delete_backup_files
    
    # 로그 파일 정리 (최근 7일만 유지)
    mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "DELETE FROM logdb WHERE pg = 'backup' AND dt < DATE_SUB(NOW(), INTERVAL 7 DAY)" 2>/dev/null
    
    deleted_logs=$(mysql -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" -D "$SOURCE_DB" \
        -e "SELECT ROW_COUNT()" -s --skip-column-names 2>/dev/null)
    
    echo "7일 이상 된 백업 로그 $deleted_logs개가 정리되었습니다."
    
    # 정리 완료 로그
    log_to_db "backup" "전체 백업 정리 완료: $deleted_logs개 로그 삭제"
    
    echo "✅ 전체 백업 정리가 완료되었습니다."
}

# 메뉴 표시
case "$1" in
    "show")
        show_backup_log
        ;;
    "detail")
        show_detail_log
        ;;
    "cleanup")
        cleanup_old_logs
        ;;
    "delete")
        delete_backup_files
        ;;
    "cleanup-all")
        cleanup_all
        ;;
    "stats")
        show_backup_stats
        ;;
    *)
        echo "사용법: $0 {show|detail|cleanup|delete|cleanup-all|stats}"
        echo "  show        - 백업 로그 확인 (logDB)"
        echo "  detail      - 상세 백업 로그 확인 (logDB)"
        echo "  cleanup     - 오래된 로그 정리 (30일 이상)"
        echo "  delete      - 모든 백업 파일 삭제"
        echo "  cleanup-all - 전체 정리 (파일 + 로그)"
        echo "  stats       - 백업 통계 보기 (logDB)"
        ;;
esac 