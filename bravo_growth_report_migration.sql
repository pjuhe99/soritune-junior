-- ============================================
-- BRAVO 성장리포트 DB 마이그레이션
-- ============================================

-- 1. 코멘트 템플릿 테이블 생성
CREATE TABLE IF NOT EXISTS junior_bravo_comment_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  comment_type ENUM('excellent','growing','support') NOT NULL,
  template_text TEXT NOT NULL,
  sort_order TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 9개 템플릿 INSERT
INSERT INTO junior_bravo_comment_templates (comment_type, template_text, sort_order) VALUES
-- excellent (우수)
('excellent', '어휘 이해력이 뛰어나고 문장 읽기가 유창합니다.', 1),
('excellent', '문장 구성력과 패턴 인식이 정확합니다.', 2),
('excellent', '퀴즈 정확도가 높고 자신감 있는 발화를 보여줍니다.', 3),
-- growing (성장)
('growing', '어휘 인식 능력과 유창성이 꾸준히 향상되고 있습니다.', 1),
('growing', '문장 패턴에 대한 이해가 형성되는 단계입니다.', 2),
('growing', '블록 구성 능력과 발화 시도가 증가하고 있습니다.', 3),
-- support (보완)
('support', '핵심 어휘에 대한 반복 학습이 필요합니다.', 1),
('support', '문장 패턴 드릴에 집중이 필요합니다.', 2),
('support', '따라 읽기와 반복 듣기를 병행하면 좋겠습니다.', 3);

-- 3. junior_bravo_submissions 테이블에 컬럼 추가
ALTER TABLE junior_bravo_submissions
  ADD COLUMN comment_type ENUM('excellent','growing','support') NULL AFTER coach_result,
  ADD COLUMN comment_text TEXT NULL AFTER comment_type,
  ADD COLUMN report_token VARCHAR(64) NULL AFTER comment_text,
  ADD UNIQUE INDEX idx_report_token (report_token);
