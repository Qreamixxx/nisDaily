-- Nis Daily schema, version 1 of the v3 design.
-- Rules of thumb used here:
--   * every table has a numeric id; the IIN is just a unique login column
--   * people are never deleted (is_active = 0), so history stays intact
--   * the database itself refuses double bookings (UNIQUE keys), not just the PHP code

CREATE TABLE subjects (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40)  NOT NULL UNIQUE,
    name VARCHAR(60)  NOT NULL
);

CREATE TABLE classes (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
);

-- One row per person who can log in. Role-specific data lives in the profile tables below.
CREATE TABLE users (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    iin                  CHAR(12)     NOT NULL UNIQUE,
    full_name            VARCHAR(100) NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 VARCHAR(10)  NOT NULL CHECK (role IN ('student', 'parent', 'teacher', 'curator')),
    lang                 CHAR(2)      NOT NULL DEFAULT 'en',
    is_active            TINYINT      NOT NULL DEFAULT 1,
    must_change_password TINYINT      NOT NULL DEFAULT 0,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE students (
    user_id  INT PRIMARY KEY,
    class_id INT NULL,
    FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

CREATE TABLE teachers (
    user_id INT PRIMARY KEY,
    room    VARCHAR(20) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- A teacher can teach several subjects.
CREATE TABLE teacher_subjects (
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (teacher_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- A parent can have several children, and a child several parents.
CREATE TABLE parent_student (
    parent_id  INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (parent_id, student_id),
    FOREIGN KEY (parent_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Clock times for "lesson 1..10". The curator can edit these.
CREATE TABLE bell_schedule (
    lesson_order TINYINT PRIMARY KEY,
    starts_at    CHAR(5) NOT NULL,
    ends_at      CHAR(5) NOT NULL
);

-- A time a teacher offers an extra lesson, on a real date.
CREATE TABLE slots (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT     NOT NULL,
    subject_id   INT     NOT NULL,
    lesson_date  DATE    NOT NULL,
    lesson_order TINYINT NOT NULL,
    capacity     TINYINT NOT NULL DEFAULT 1 CHECK (capacity BETWEEN 1 AND 30),
    room         VARCHAR(20) NULL,
    closed_at    TIMESTAMP NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (teacher_id, lesson_date, lesson_order),
    FOREIGN KEY (teacher_id)   REFERENCES users(id)                  ON DELETE RESTRICT,
    FOREIGN KEY (subject_id)   REFERENCES subjects(id)               ON DELETE RESTRICT,
    FOREIGN KEY (lesson_order) REFERENCES bell_schedule(lesson_order) ON DELETE RESTRICT
);

-- A student's booking. Cancelled bookings are kept (is_active becomes NULL), never deleted.
-- is_active is 1 for live bookings and NULL for cancelled ones. UNIQUE keys ignore NULLs, so a
-- student can re-book after cancelling, but can never hold two live bookings at the same time.
-- lesson_date / lesson_order are copied from the slot so the database can enforce "no clashes".
CREATE TABLE appointments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    slot_id      INT     NOT NULL,
    student_id   INT     NOT NULL,
    lesson_date  DATE    NOT NULL,
    lesson_order TINYINT NOT NULL,
    status       VARCHAR(10) NOT NULL DEFAULT 'booked' CHECK (status IN ('booked', 'attended', 'no_show', 'cancelled')),
    is_active    TINYINT NULL DEFAULT 1,
    cancelled_by INT NULL,
    cancelled_at TIMESTAMP NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (slot_id, student_id, is_active),
    UNIQUE (student_id, lesson_date, lesson_order, is_active),
    FOREIGN KEY (slot_id)      REFERENCES slots(id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id)   REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE exams (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    name       VARCHAR(60) NOT NULL,
    exam_date  DATE NOT NULL,
    max_score  DECIMAL(5,1) NOT NULL DEFAULT 100 CHECK (max_score > 0),
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
);

CREATE TABLE grades (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    exam_id    INT NOT NULL,
    student_id INT NOT NULL,
    score      DECIMAL(5,1) NOT NULL CHECK (score >= 0),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (exam_id, student_id),
    FOREIGN KEY (exam_id)    REFERENCES exams(id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- In-app notifications. We store a translation key + values, so each person reads them in their own language.
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    message_key VARCHAR(60) NOT NULL,
    params      TEXT NULL,
    link        VARCHAR(120) NULL,
    is_read     TINYINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Failed logins, for rate limiting.
CREATE TABLE login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    iin          VARCHAR(20) NOT NULL,
    ip           VARCHAR(45) NOT NULL,
    attempted_at DATETIME    NOT NULL
);

CREATE INDEX idx_slots_subject_date   ON slots (subject_id, lesson_date);
CREATE INDEX idx_appointments_student ON appointments (student_id, lesson_date);
CREATE INDEX idx_notifications_user   ON notifications (user_id, is_read);
CREATE INDEX idx_attempts_iin         ON login_attempts (iin, attempted_at);
CREATE INDEX idx_attempts_ip          ON login_attempts (ip, attempted_at);
