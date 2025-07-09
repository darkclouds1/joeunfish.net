#!/bin/bash

# 백업 로그 관리 스크립트
BACKUP_DIR="/home/joeunfish.net/backup/backup_db"
BACKUP_LOG="$BACKUP_DIR/backup_log.txt"
BACKUP_DETAIL_LOG="$BACKUP_DIR/backup_detail.log"

# 1. 백업 로그 확인
show_backup_log() {
    echo "=== 백업 로그 확인 ==="
    if [ -f "$BACKUP_LOG" ]; then
        echo "총 백업 횟수: $(wc -l < "$BACKUP_LOG")"
        echo "최근 5회 백업:"
        tail -n 5 "$BACKUP_LOG" | nl
        echo ""
        echo "마지막 백업: $(tail -n 1 "$BACKUP_LOG")"
    else
        echo "백업 로그 파일이 없습니다."
    fi
}

# 2. 상세 백업 로그 확인
show_detail_log() {
    echo "=== 상세 백업 로그 확인 ==="
    if [ -f "$BACKUP_DETAIL_LOG" ]; then
        echo "최근 10개 백업 상세 정보:"
        tail -n 50 "$BACKUP_DETAIL_LOG"
    else
        echo "상세 백업 로그 파일이 없습니다."
    fi
}

# 3. 백업 로그 정리 (30일 이상 된 로그 삭제)
cleanup_old_logs() {
    echo "=== 오래된 백업 로그 정리 ==="
    if [ -f "$BACKUP_LOG" ]; then
        # 30일 이상 된 백업 로그 삭제
        while IFS= read -r line; do
            backup_date=$(echo "$line" | cut -d'_' -f1)
            backup_date_formatted=$(date -d "${backup_date:0:4}-${backup_date:4:2}-${backup_date:6:2}" +%s)
            current_date=$(date +%s)
            days_diff=$(( (current_date - backup_date_formatted) / 86400 ))
            
            if [ $days_diff -gt 30 ]; then
                echo "삭제: $line (${days_diff}일 전)"
                # 해당 백업 파일들도 삭제
                find "$BACKUP_DIR" -name "*_${line}.sql" -delete
            fi
        done < "$BACKUP_LOG" > "$BACKUP_DIR/temp_log.txt"
        
        mv "$BACKUP_DIR/temp_log.txt" "$BACKUP_LOG"
        echo "30일 이상 된 백업 로그가 정리되었습니다."
    fi
}

# 4. 백업 통계 보기
show_backup_stats() {
    echo "=== 백업 통계 ==="
    if [ -f "$BACKUP_LOG" ]; then
        total_backups=$(wc -l < "$BACKUP_LOG")
        first_backup=$(head -n 1 "$BACKUP_LOG")
        last_backup=$(tail -n 1 "$BACKUP_LOG")
        
        echo "총 백업 횟수: $total_backups"
        echo "첫 번째 백업: $first_backup"
        echo "마지막 백업: $last_backup"
        
        # 백업 파일 크기 확인
        total_size=$(find "$BACKUP_DIR" -name "incremental_*.sql" -exec du -ch {} + | tail -n 1 | cut -f1)
        echo "총 백업 파일 크기: $total_size"
    fi
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
}

# 6. 전체 정리 (로그 + 파일)
cleanup_all() {
    echo "=== 전체 백업 정리 ==="
    
    # 백업 파일 삭제
    delete_backup_files
    
    # 로그 파일 정리
    if [ -f "$BACKUP_LOG" ]; then
        echo "" > "$BACKUP_LOG"
        echo "백업 로그가 초기화되었습니다."
    fi
    
    if [ -f "$BACKUP_DETAIL_LOG" ]; then
        echo "" > "$BACKUP_DETAIL_LOG"
        echo "상세 백업 로그가 초기화되었습니다."
    fi
    
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
        echo "  show        - 백업 로그 확인"
        echo "  detail      - 상세 백업 로그 확인"
        echo "  cleanup     - 오래된 로그 정리 (30일 이상)"
        echo "  delete      - 모든 백업 파일 삭제"
        echo "  cleanup-all - 전체 정리 (파일 + 로그)"
        echo "  stats       - 백업 통계 보기"
        ;;
esac 