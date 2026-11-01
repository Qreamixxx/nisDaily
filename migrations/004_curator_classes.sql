-- Which classes a curator looks after.
-- A curator with NO rows here is a "head curator": all classes, plus lesson times, classes and other curators.
-- A curator with rows here only sees and manages students (and their parents) in those classes.
CREATE TABLE curator_classes (
    curator_id INT NOT NULL,
    class_id   INT NOT NULL,
    PRIMARY KEY (curator_id, class_id),
    FOREIGN KEY (curator_id) REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (class_id)   REFERENCES classes(id) ON DELETE CASCADE
);
