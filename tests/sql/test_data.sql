INSERT INTO user VALUES (1, 'Andy');
INSERT INTO user VALUES (2, 'Bob');

INSERT INTO blog VALUES (1, 'Andy Blog 1', 1);
INSERT INTO blog VALUES (2, 'Andy Blog 2', 1);
INSERT INTO blog VALUES (3, 'Bob Blog 1', 2);

INSERT INTO comment VALUES (1, 'Andy Comment 1 on Andy Blog 1', 1, 1);
INSERT INTO comment VALUES (2, 'Bob Comment 1 on Andy Blog 1', 2, 1);
INSERT INTO comment VALUES (3, 'Andy Comment 1 on Bob Blog 1', 1, 3);

INSERT INTO profile VALUES (1, 1, 23);
INSERT INTO profile VALUES (2, 2, 46);

INSERT INTO tag VALUES (1, 'Red');
INSERT INTO tag VALUES (2, 'Orange');
INSERT INTO tag VALUES (3, 'Yellow');
INSERT INTO tag VALUES (4, 'Green');
INSERT INTO tag VALUES (5, 'Blue');
INSERT INTO tag VALUES (6, 'Indigo');
INSERT INTO tag VALUES (7, 'Violet');

INSERT INTO blog_tag VALUES (1, 1, 3);
INSERT INTO blog_tag VALUES (2, 1, 6);
INSERT INTO blog_tag VALUES (3, 2, 4);

INSERT INTO comment_tag VALUES (1, 2, 2);
INSERT INTO comment_tag VALUES (2, 1, 4);
INSERT INTO comment_tag VALUES (3, 3, 4);
INSERT INTO comment_tag VALUES (4, 2, 7);