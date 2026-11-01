-- Reference data every installation needs: the subject list and a default bell schedule.
-- The curator can change lesson times later on the "Lesson times" page.

INSERT INTO subjects (slug, name) VALUES ('informatics', 'Informatics');
INSERT INTO subjects (slug, name) VALUES ('math', 'Math');
INSERT INTO subjects (slug, name) VALUES ('kazakh', 'Kazakh Language');
INSERT INTO subjects (slug, name) VALUES ('physics', 'Physics');
INSERT INTO subjects (slug, name) VALUES ('geography', 'Geography');
INSERT INTO subjects (slug, name) VALUES ('chemistry', 'Chemistry');
INSERT INTO subjects (slug, name) VALUES ('biology', 'Biology');
INSERT INTO subjects (slug, name) VALUES ('pe', 'Physical Education');

INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (1, '08:00', '08:40');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (2, '08:50', '09:30');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (3, '09:40', '10:20');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (4, '10:35', '11:15');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (5, '11:30', '12:10');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (6, '12:25', '13:05');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (7, '13:15', '13:55');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (8, '14:05', '14:45');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (9, '14:55', '15:35');
INSERT INTO bell_schedule (lesson_order, starts_at, ends_at) VALUES (10, '15:45', '16:25');
