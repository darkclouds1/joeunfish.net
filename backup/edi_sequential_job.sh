#!/bin/bash -l

# 환경 변수를 명확하게 설정하기 위해 기존 PATH를 초기화하고 필요한 경로만 추가합니다.
export PATH=/usr/local/bin:/usr/bin:/bin

# Java 애플리케이션과 PHP 스크립트 순차 실행 스크립트
# 1. Java 애플리케이션으로 데이터 다운로드
# 2. 데이터 다운로드 완료 후 PHP 스크립트들 실행

EC_HOME="/home/joeunfish.net/public_html/ecommerce_java"

# 로그 함수
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1"
}

# 시작 로그
log_message "=== EDI 순차 작업 시작 ==="

# 1단계: Java 애플리케이션 실행 (데이터 다운로드)
log_message "1단계: Java 애플리케이션 실행 중..."
cd "$EC_HOME"

# Java 홈 디렉토리 설정
export JAVA_HOME=/usr/lib/jvm/java-11-openjdk
export PATH=$JAVA_HOME/bin:$PATH

# 파라미터에 따라 프로퍼티 파일 결정
if [ "$1" = "A" ]; then
    PROPERTIES_FILE="$EC_HOME/ecommerce_rcv_A.properties"
    log_message "A 파라미터로 실행(미수신 + 기수신): $PROPERTIES_FILE"
else
    PROPERTIES_FILE="$EC_HOME/ecommerce_rcv.properties"
    log_message "기본 파라미터로 실행(미수신): $PROPERTIES_FILE"
fi

# Java 애플리케이션 실행
java -Xms128m -Xmx256m -Xss512k -XX:MaxMetaspaceSize=128m -Djava.class.path=$EC_HOME:$EC_HOME/bin:$EC_HOME/lib/log4j-1.2.8.jar:$EC_HOME/lib/ojdbc14.jar:$EC_HOME/lib/commons-io-1.4.jar net.dacom.CommMain "$PROPERTIES_FILE"

# Java 실행 결과 확인
if [ $? -eq 0 ]; then
    log_message "Java 애플리케이션 실행 완료."

    # 로그 파일을 확인하여 데이터 수신 여부 판단
    LOG_DIR="$EC_HOME/log"
    # 시스템 로케일과 무관하게 영문 요일 약어(e.g., Mon, Tue)를 얻기 위해 LC_TIME 설정
    DAY_OF_WEEK=$(LC_TIME=en_US.UTF-8 date +'%a')
    LOG_FILE="$LOG_DIR/ecommerce_${DAY_OF_WEEK}.log"

    if [ -f "$LOG_FILE" ]; then
        # 로그 파일의 마지막 'result : ' 라인에 '005'가 포함되어 있다면 데이터가 없는 것으로 간주
        # 'A' 파라미터가 아닐 경우에만 빈 파일 생성 로직을 실행
        if [ "$1" != "A" ] && grep "result : " "$LOG_FILE" | tail -n 1 | grep -q "005"; then
            log_message "로그 확인: 수신된 데이터 없음 (result: 005). 빈 파일을 생성합니다."
            RECV_DIR="$EC_HOME/recv"
            mkdir -p "$RECV_DIR"
            touch "$RECV_DIR/edi_$(date +'%Y%m%d%H%M%S').dat"
        else
            log_message "로그 확인: 새로운 데이터가 수신되었습니다."
        fi
    else
        log_message "경고: 로그 파일($LOG_FILE)을 찾을 수 없어 데이터 수신 여부를 확인할 수 없습니다."
    fi

    log_message "후속 작업을 백그라운드에서 실행합니다."
    # 아래 괄호 안의 작업들을 백그라운드로 실행하고, 메인 스크립트는 즉시 종료됩니다.
    # 백그라운드 작업의 로그는 /dev/null로 보내져서, shell_exec가 기다리지 않도록 합니다.
    (
        sleep 30
        /usr/bin/php /home/joeunfish.net/public_html/wp-content/plugins/jofEdi/ediRcv_srv_job.php
        /usr/bin/php /home/joeunfish.net/public_html/wp-content/plugins/jofEdiGs/ediRcvGs_srv_job.php
    ) > /dev/null 2>&1 &
else
    log_message "오류: Java 애플리케이션 실행 실패"
    exit 1
fi