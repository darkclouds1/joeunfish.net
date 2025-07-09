#!/bin/bash

# ✅ STEP 1: 설정값 정의
# 워드프레스 루트 디렉토리
DOC_ROOT="/home/joeunfish.net/public_html"

# 자동으로 PHP 실행 사용자 감지
PHP_USER=$(ps aux | grep '[l]sphp' | awk '{print $1}' | head -n 1)

# 사용자가 없을 경우 수동 지정 (필요 시 아래 라인 사용)
PHP_USER="your_php_user"

# 검증
if [ -z "$PHP_USER" ]; then
  echo "❌ PHP 실행 사용자를 찾을 수 없습니다. 수동 지정 필요 (스크립트 상단 확인)."
  exit 1
fi

echo "✅ PHP 실행 사용자 감지됨: $PHP_USER"
echo "📁 대상 디렉토리: $DOC_ROOT"

# ✅ STEP 2: 디렉토리 및 파일 소유자 변경
echo "⏳ 소유자 변경 중..."
chown -R $PHP_USER:$PHP_USER "$DOC_ROOT"

# ✅ STEP 3: 디렉토리/파일 권한 정리
echo "⏳ 디렉토리 권한 755, 파일 권한 644 적용 중..."
find "$DOC_ROOT" -type d -exec chmod 755 {} \;
find "$DOC_ROOT" -type f -exec chmod 644 {} \;

# ✅ STEP 4: .log 파일은 쓰기 가능하게 664로 설정
echo "⏳ .log 파일 권한 664로 설정 중..."
find "$DOC_ROOT" -type f -name "*.log" -exec chmod 664 {} \;

# ✅ STEP 5: 확인용 출력
echo "✅ 완료. 설정된 예시:"
ls -l "$DOC_ROOT" | head -n 10
