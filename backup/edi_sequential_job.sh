#!/bin/bash

# Java 애플리케이션과 PHP 스크립트 순차 실행 스크립트
# 1. Java 애플리케이션으로 데이터 다운로드
# 2. 데이터 다운로드 완료 후 PHP 스크립트들 실행

# 로그 파일 설정
LOG_FILE="/var/log/edi_sequential_job.log"
EC_HOME="/home/joeunfish.net/public_html/ecommerce_java"

# 로그 함수
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# 시작 로그
log_message "=== EDI 순차 작업 시작 ==="

# 1단계: Java 애플리케이션 실행 (데이터 다운로드)
log_message "1단계: Java 애플리케이션 실행 중..."
cd "$EC_HOME"

# Java 홈 디렉토리 설정
export JAVA_HOME=/usr/lib/jvm/java-11-openjdk
export PATH=$JAVA_HOME/bin:$PATH

# Java 애플리케이션 실행
java -Djava.class.path=$EC_HOME:$EC_HOME/bin:$EC_HOME/lib/log4j-1.2.8.jar:$EC_HOME/lib/ojdbc14.jar:$EC_HOME/lib/commons-io-1.4.jar net.dacom.CommMain $EC_HOME/ecommerce_rcv.properties

# Java 실행 결과 확인
if [ $? -eq 0 ]; then
    log_message "Java 애플리케이션 실행 완료"
else
    log_message "오류: Java 애플리케이션 실행 실패"
    exit 1
fi

# 2단계: 잠시 대기 (데이터 처리 완료 확인)
log_message "2단계: 데이터 처리 완료 대기 중..."
sleep 30

# 3단계: PHP 스크립트들 실행
log_message "3단계: PHP 스크립트들 실행 중..."

# 첫 번째 PHP 스크립트 실행
log_message "jofEdi PHP 스크립트 실행 중..."
/usr/bin/php /home/joeunfish.net/public_html/wp-content/plugins/jofEdi/ediRcv_srv_job.php
if [ $? -eq 0 ]; then
    log_message "jofEdi PHP 스크립트 실행 완료"
else
    log_message "오류: jofEdi PHP 스크립트 실행 실패"
fi

# 두 번째 PHP 스크립트 실행
log_message "jofEdiGs PHP 스크립트 실행 중..."
/usr/bin/php /home/joeunfish.net/public_html/wp-content/plugins/jofEdiGs/ediRcvGs_srv_job.php
if [ $? -eq 0 ]; then
    log_message "jofEdiGs PHP 스크립트 실행 완료"
else
    log_message "오류: jofEdiGs PHP 스크립트 실행 실패"
fi

# 완료 로그
log_message "=== EDI 순차 작업 완료 ==="
log_message ""