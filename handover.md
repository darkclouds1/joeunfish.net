# 프로젝트 인계 및 서버 이전 가이드 (Joeun Fish)

이 문서는 '조은수산(Joeun Fish)' 시스템의 프로젝트 인계 및 서버 이전을 위한 기술 문서입니다.

## 1. 프로젝트 개요

- **프로젝트명**: Joeun Fish (조은수산) ERP 및 재고관리 시스템
- **웹사이트**: https://joeunfish.net
- **기반 시스템**: WordPress (Custom Plugins 활용)
- **주요 기능**: 입출고 관리, 바코드 시스템, 생산 관리, EDI 주문 연동 (GS리테일 등), 재고 추적

## 2. 서버 환경 정보

현재 운영 중인 서버의 사양 및 환경 정보입니다.

- **OS**: AlmaLinux 8.10 (Cerulean Leopard)
- **Architecture**: x86-64
- **접속 정보 (SSH)**
  - IP: `93.127.200.220`
  - User: `root`
  - _비밀번호는 별도 전달 필요_
- **웹 서버 경로**: `/home/u753849441/domains/joeunfish.net/public_html` (추정 경로, 실제 경로는 FTP/SSH 접속 후 확인 필요)
- **데이터베이스 (DB)**
  - DB Name: `joeu_fish`
  - DB User: `u753849441_cASrk`
  - _비밀번호는 `wp-config.php` 파일 참조_

## 3. 프로젝트 기술 스택 및 구조

### 3.1. 핵심 기술

- **Language**: PHP 7.4+ (권장)
- **Database**: MySQL / MariaDB (utf8mb4)
- **CMS**: WordPress (테마: GeneratePress)

### 3.2. 주요 커스텀 플러그인

`wp-content/plugins/` 디렉토리 내에 위치한 `jof` 접두사 등을 가진 커스텀 플러그인이 핵심 로직을 담당합니다.

|        플러그인명         | 기능 요약                             | 비고                                    |
| :-----------------------: | :------------------------------------ | :-------------------------------------- |
|        **jofMain**        | 메인 대시보드 및 공지사항 관리        | 시스템 진입점 (`shortcode`, `tasks.js`) |
|       **jofSubul**        | 수불(입출고) 관리                     | 재고 변동 내역 추적                     |
| **ascInvt / octopusInvt** | ASC 인증 제품 및 문어 재고 관리       | 특수 품목 재고                          |
|    **barcodeIn / Out**    | 바코드 기반 1층 입고/출고 및 3층 생산 | PDA/스캐너 연동 UI 추정                 |
|        **dayProd**        | 일일 생산 관리                        |                                         |
|       **purchase**        | 매입/통관 관리                        | 업체 매입 내역                          |
|   **ediRcv (classphp)**   | EDI 주문 수신 및 처리                 | `classphp` 디렉토리 내 별도 클래스 사용 |

### 3.3. 별도 라이브러리 (Non-Plugin)

WordPress 플러그인 구조 외부에 별도의 PHP 클래스 파일이 존재합니다. 이 파일들도 반드시 함께 백업/이전되어야 합니다.

- **위치**: `public_html/classphp/`
- **파일**:
  - `dbConn_fish.php`: 커스텀 DB 연결 클래스 (WP `$wpdb` 외 별도 연결 사용 가능성 있음)
  - `ediRcv_class.php`, `ediRcvGs_class.php`: EDI 주문 연동 로직

## 4. 서버 이전 (Migration) 가이드

서버 이전을 위해서는 **데이터베이스(DB)**와 **웹 파일(File)** 두 가지를 모두 이전해야 합니다.

### 단계 1: 데이터 백업 (기존 서버)

1.  **웹 파일 백업**: `public_html` 폴더 전체를 압축합니다.
    ```bash
    # 예시
    tar -czvf joeunfish_files_backup.tar.gz /path/to/public_html
    ```
2.  **DB 백업**: `mysqldump`를 사용하여 전체 DB를 추출합니다.
    ```bash
    # DB명: joeu_fish
    mysqldump -u u753849441_cASrk -p joeu_fish > joeunfish_db_backup.sql
    ```

### 단계 2: 신규 서버 환경 구성

1.  **LAMP/LEMP 스택 설치**: Apache/Nginx, PHP, MySQL/MariaDB 설치.
2.  **PHP 설정**: `wp-config.php` 등의 설정을 참고하여 필요한 PHP 모듈 설치 (mysqli, curl, gd, mbstring 등).
3.  **DB 생성**: 기존과 동일한 이름(`joeu_fish`) 권장 (필수는 아님).

### 단계 3: 데이터 복원 (신규 서버)

1.  **파일 업로드 및 압축 해제**: 백업한 `tar.gz` 파일을 웹 루트에 풀고, 권한을 조정합니다.
    ```bash
    tar -xzvf joeunfish_files_backup.tar.gz
    chown -R apache:apache /var/www/html/  # (서버 환경에 맞게 조정)
    ```
2.  **DB Import**:
    ```bash
    mysql -u [새로운DB유저] -p [새DB명] < joeunfish_db_backup.sql
    ```
3.  **중요: 별도 클래스 확인**: `public_html/classphp/` 폴더가 누락되지 않았는지 재확인합니다.

### 단계 4: 설정 변경 (`wp-config.php`)

`wp-config.php` 파일을 열어 신규 서버 정보로 수정합니다.

```php
// 신규 DB 정보 입력
define('DB_NAME', 'new_db_name');
define('DB_USER', 'new_db_user');
define('DB_PASSWORD', 'new_db_password');
define('DB_HOST', 'localhost');

// 도메인이 변경되는 경우 (동일하면 수정 불필요)
define('WP_HOME', 'https://new-domain.com');
define('WP_SITEURL', 'https://new-domain.com');
```

> **주의 1**: `classphp/dbConn_fish.php` 내부에도 DB 접속 정보가 하드코딩 되어 있을 수 있습니다. 해당 파일도 반드시 열어서 DB 정보를 수정해야 합니다.

### 단계 5: 도메인 DNS 변경

모든 작업이 완료되고 테스트가 끝나면, 도메인의 A 레코드를 신규 서버 IP(`93.127.200.220` → `New IP`)로 변경합니다.

## 5. 유지보수 참고사항

- **공지사항 시스템**: `jofMain` 플러그인의 `tasks.js`를 통해 구동됩니다.
- **재고 데이터 무결성**: 수불(Subul) 테이블과 현재 재고(Inventory) 테이블 간의 데이터 정합성을 주기적으로 확인해야 합니다.
- **백업**: 자동 백업 솔루션 도입을 권장합니다.
