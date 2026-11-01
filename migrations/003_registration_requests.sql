-- Self-registration: students and parents can ask for an account; a curator approves it.
-- Nothing is stored in `users` until approval, so an unapproved request can never log in or see data.
CREATE TABLE registration_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    iin           CHAR(12)     NOT NULL UNIQUE,
    full_name     VARCHAR(100) NOT NULL,
    role          VARCHAR(10)  NOT NULL CHECK (role IN ('student', 'parent')),
    class_name    VARCHAR(20)  NULL,
    child_iin     CHAR(12)     NULL,
    lang          CHAR(2)      NOT NULL DEFAULT 'en',
    password_hash VARCHAR(255) NOT NULL,
    ip            VARCHAR(45)  NOT NULL,
    created_at    DATETIME     NOT NULL
);
CREATE INDEX idx_registration_ip ON registration_requests (ip, created_at);
