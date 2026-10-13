-- Create student_competencies table if it doesn't exist
CREATE TABLE IF NOT EXISTS `student_competencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_email` varchar(255) NOT NULL,
  `competency_name` varchar(255) NOT NULL,
  `proficiency_level` enum('Beginner','Intermediate','Advanced') NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_competency` (`student_email`, `competency_name`),
  KEY `idx_student_email` (`student_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
