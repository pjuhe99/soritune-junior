# 소리튠 주니어 영어학교 관리 시스템

## 📋 시스템 개요

소리튠 주니어 영어학교의 학생 관리, 보상 시스템, 출석 체크, 과제 관리를 위한 통합 웹 애플리케이션입니다.

- **도메인**: https://j.soritune.com
- **서버 경로**: `/var/www/html/_______site_SORITUNECOM_J`
- **PHP 버전**: 8.x
- **데이터베이스**: MySQL/MariaDB

---

## 👥 사용자 역할

### 1. **학생** 👦👧
- QR 코드 스캔으로 출석
- 본인 보상 카드 확인
- 획득한 코인 확인

### 2. **부모님** 👨‍👩‍👧‍👦
- 전화번호로 간편 로그인
- 자녀 학습 현황 조회
- 보상 카드 및 체크리스트 확인

### 3. **관리쌤** 👩‍🏫
- 전화번호로 로그인 (부모와 동일)
- 담당 반 과제율 확인
- 생활미션 체크리스트 작성
- 학생별 카드 현황 관리
- 전체 반 과제율 랭킹 확인

### 4. **코치쌤** 🎓
- ID/PW 로그인
- 반 학생 관리
- 체크리스트 작성 (자동 카드 부여)
- QR 출석 세션 생성/관리
- 학생 프로필 조회 및 카드 수동 조정
- 전체 반 과제율 랭킹 확인

### 5. **시스템 관리자** ⚙️
- 전체 시스템 관리
- 학생/반/관리자 CRUD
- 설정 관리
- 감사 로그 조회
- 대행 로그인 기능

---

## 🌐 접속 경로

| 역할 | URL | 비고 |
|------|-----|------|
| 학생 | `https://j.soritune.com/` | QR 스캔 또는 전화번호 뒷 4자리 |
| 부모/관리쌤 | `https://j.soritune.com/admin/` | 전화번호 로그인 |
| 코치쌤 | `https://j.soritune.com/coach/` | ID/PW 로그인 |
| 시스템 관리자 | `https://j.soritune.com/system/` | ID/PW 또는 IP 자동 로그인 |

---

## 🔐 IP 기반 자동 로그인

### 허용된 IP 목록
시스템 관리자로 자동 로그인되는 IP 주소:

```
175.215.116.18
183.99.255.241
211.229.75.148
121.134.227.161
211.234.180.217
14.52.219.236
```

### IP 추가 방법

```php
cd /var/www/html/_______site_SORITUNECOM_J
php -r "
require_once 'public_html/config.php';
\$db = getDB();

\$newIp = '새로운.IP.주소.여기';
\$stmt = \$db->prepare('SELECT setting_value FROM junior_settings WHERE setting_key = ?');
\$stmt->execute(['system_auto_login_ips']);
\$current = \$stmt->fetchColumn();

\$ipList = array_filter(array_map('trim', explode(',', \$current ?: '')));
if (!in_array(\$newIp, \$ipList)) {
    \$ipList[] = \$newIp;
    \$newValue = implode(',', \$ipList);

    \$stmt = \$db->prepare('UPDATE junior_settings SET setting_value = ? WHERE setting_key = ?');
    \$stmt->execute([\$newValue, 'system_auto_login_ips']);

    echo 'IP 추가 완료: ' . \$newValue . PHP_EOL;
}
"
```

---

## 🎯 주요 기능

### 📊 과제율 관리
- **반별 과제 완료율 표시** (코치쌤/관리쌤)
  - 80% 이상: 🟢 초록색
  - 50-80%: 🟠 주황색
  - 50% 미만: 🔴 빨간색
- **전체 반 과제율 랭킹** (날짜별 조회 가능)
  - 1위: 🥇 금메달
  - 2위: 🥈 은메달
  - 3위: 🥉 동메달

### ✅ 생활미션 체크리스트
5가지 항목으로 구성:
1. **줌출석** → 열정왕 카드 +1
2. **자세왕** → 바른자세왕 카드 +1
3. **소리과제** → 꾸준왕 카드 +1
4. **밴드미션** → 미션왕 카드 +1
5. **리더왕** → 리더왕 카드 +1

### 🎴 보상 카드 시스템
| 카드 | 코인 가치 | 색상 | 획득 방법 |
|------|----------|------|----------|
| 꾸준왕 | 3 | 🟢 #4CAF50 | 소리과제 완료 |
| 리더왕 | 2 | 🔵 #2196F3 | 리더왕 체크 |
| 미션왕 | 1 | 🟠 #FF9800 | 밴드미션 완료 |
| 바른자세왕 | 1 | 🟣 #9C27B0 | 자세왕 체크 |
| 열정왕 | 1 | 🔴 #F44336 | 줌출석 |

### 📱 QR 출석 시스템
- 코치쌤이 QR 세션 생성
- 학생이 QR 스캔으로 출석
- 실시간 출석 현황 확인
- 본반/타반 구분 표시
- 수동 출석 추가/제거 가능

---

## 🗄️ 데이터베이스 구조

### 주요 테이블

```
junior_students              # 학생 정보
junior_classes              # 반 정보
junior_admins               # 코치쌤/관리쌤/부모 계정
junior_system_admins        # 시스템 관리자 계정
junior_reward_types         # 보상 카드 종류
junior_student_rewards      # 학생별 카드 보유 현황
junior_daily_checklist      # 일일 체크리스트
junior_weekly_summary       # 주간 요약
junior_qr_sessions          # QR 출석 세션
junior_qr_attendance        # QR 출석 기록
junior_settings             # 시스템 설정
junior_edit_audit_log       # 감사 로그
```

### 데이터베이스 접속 정보

```bash
# 파일 위치: /var/www/html/_______site_SORITUNECOM_J/.db_credentials
cat /var/www/html/_______site_SORITUNECOM_J/.db_credentials
```

---

## 🔧 API 엔드포인트

### 학생 API (`/api/student.php`)
- `login` - QR 또는 전화번호 뒷 4자리 로그인
- `check_session` - 세션 확인
- `my_rewards` - 본인 카드 조회

### 관리자 API (`/api/admin.php`)
- `phone_login` - 전화번호 로그인 (부모/관리쌤)
- `teacher_dashboard` - 관리쌤 담당 반 + 과제율
- `teacher_class_detail` - 반 상세 + 체크리스트
- `teacher_save_checklist` - 체크리스트 저장
- `my_students` - 연결된 학생 목록
- `student_dashboard` - 학생 상세 정보

### 코치 API (`/api/coach.php`)
- `login` - ID/PW 로그인
- `dashboard` - 반 현황 + 과제율
- `checklist_load` - 체크리스트 조회
- `checklist_save` - 체크리스트 저장
- `class_assignment_ranking` - 전체 반 과제율 랭킹
- `create_qr` - QR 세션 생성
- `qr_full_status` - QR 출석 현황
- `student_profile` - 학생 프로필
- `edit_reward` - 카드 수동 조정

### 시스템 관리자 API (`/api/system.php`)
- `login` - ID/PW 로그인
- `check_ip_auto_login` - IP 자동 로그인 가능 여부
- `ip_auto_login` - IP 기반 자동 로그인
- `students` - 학생 관리 (CRUD)
- `admins` - 관리자 관리 (CRUD)
- `classes` - 반 관리
- `settings` - 시스템 설정
- `audit_log` - 감사 로그
- `impersonate_coach` - 코치쌤 대행 로그인
- `impersonate_admin` - 관리쌤/부모 대행 로그인
- `impersonate_student` - 학생 대행 로그인

---

## 🚀 배포 및 운영

### 파일 권한 설정

```bash
# Apache 사용자로 권한 설정
chown -R apache:apache /var/www/html/_______site_SORITUNECOM_J/public_html
chmod -R 755 /var/www/html/_______site_SORITUNECOM_J/public_html

# 로그 디렉토리 쓰기 권한
chmod 775 /var/www/html/_______site_SORITUNECOM_J/logs
```

### 로그 확인

```bash
# PHP 에러 로그
tail -f /var/www/html/_______site_SORITUNECOM_J/logs/php_error.log

# 시스템 로그 (감사 로그는 DB에 저장)
# 시스템 관리자 페이지 > 감사 로그 탭에서 조회
```

### 캐시 클리어

브라우저 캐시를 우회하기 위해 CSS/JS 버전 변경:
```php
# /public_html/admin/index.php, /coach/index.php 등
# ?v=20260214b → ?v=20260214c 로 변경
```

---

## 🔍 문제 해결

### 로그인이 안 될 때

1. **세션 확인**
   ```bash
   # PHP 세션 디렉토리 확인
   ls -la /var/lib/php/session/
   ```

2. **데이터베이스 연결 확인**
   ```bash
   cd /var/www/html/_______site_SORITUNECOM_J
   php -r "require_once 'public_html/config.php'; var_dump(getDB());"
   ```

3. **로그 확인**
   ```bash
   tail -f /var/www/html/_______site_SORITUNECOM_J/logs/php_error.log
   ```

### IP 자동 로그인이 안 될 때

1. **현재 IP 확인**
   ```bash
   curl -s ifconfig.me
   ```

2. **설정 확인**
   ```bash
   cd /var/www/html/_______site_SORITUNECOM_J
   php -r "
   require_once 'public_html/config.php';
   echo getSetting('system_auto_login_ips', '') . PHP_EOL;
   "
   ```

### 체크리스트가 저장되지 않을 때

1. **감사 로그 확인** (시스템 관리자 페이지)
2. **DB 트랜잭션 롤백 확인**
3. **PHP 에러 로그 확인**

---

## 📞 관리자 연락처

- **시스템 문의**: 시스템 관리자
- **기능 개선 요청**: 개발팀
- **긴급 장애**: 즉시 연락

---

## 📝 변경 이력

### 2026-02-14
- ✅ IP 기반 자동 로그인 추가 (14.52.219.236)
- ✅ 관리쌤/부모 로그인 통합 (전화번호만)
- ✅ 관리쌤 대시보드 개선 (과제율, 생활미션, 카드 현황)
- ✅ 코치쌤 대시보드에 과제율 표시 추가
- ✅ 전체 반 과제율 랭킹 기능 추가

### 이전 버전
- 학생 QR 출석 시스템
- 보상 카드 시스템
- 핑거프린트 자동 로그인
- 시스템 관리자 대행 로그인

---

## 🔒 보안 주의사항

1. **데이터베이스 자격증명** `.db_credentials` 파일 보호
2. **세션 보안** HTTPS 필수
3. **IP 자동 로그인** 신뢰할 수 있는 IP만 추가
4. **감사 로그** 정기적으로 검토
5. **백업** 정기적인 DB 백업 필수

---

## 📚 참고 자료

- PHP 공식 문서: https://www.php.net/
- MariaDB 문서: https://mariadb.org/documentation/
- Composer 의존성: `/var/www/html/_______site_SORITUNECOM_J/composer.json`

---

**마지막 업데이트**: 2026-02-14
**버전**: 2.0
