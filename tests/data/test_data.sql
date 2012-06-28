INSERT INTO user VALUES (1, 'Andy');
INSERT INTO user VALUES (2, 'Bob');
INSERT INTO user VALUES (3, 'Charles');

INSERT INTO blog VALUES (1, 'Andy Blog 1', 1);
INSERT INTO blog VALUES (2, 'Andy Blog 2', 1);
INSERT INTO blog VALUES (3, 'Bob Blog 1', 2);

INSERT INTO comment VALUES (1, 'Andy Comment 1 on Andy Blog 1', 1, 1);
INSERT INTO comment VALUES (2, 'Bob Comment 1 on Andy Blog 1', 2, 1);
INSERT INTO comment VALUES (3, 'Andy Comment 1 on Bob Blog 1', 1, 3);

INSERT INTO profile VALUES (1, 2, 23, 'Edam');
INSERT INTO profile VALUES (2, 1, 46, 'Stilton');

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

INSERT INTO comment_x_tag VALUES (1, 2, 2);
INSERT INTO comment_x_tag VALUES (2, 1, 4);
INSERT INTO comment_x_tag VALUES (3, 3, 4);
INSERT INTO comment_x_tag VALUES (4, 2, 7);
INSERT INTO comment_x_tag VALUES (5, 1, 5);

INSERT INTO multidep VALUES (1, 'Core');
INSERT INTO multidep VALUES (2, 'Blog');
INSERT INTO multidep VALUES (3, 'Comment');
INSERT INTO multidep VALUES (4, 'Tags');

INSERT INTO multidep_x_multidep VALUES (1, 2, 1);
INSERT INTO multidep_x_multidep VALUES (2, 3, 2);
INSERT INTO multidep_x_multidep VALUES (3, 4, 2);

INSERT INTO Tree VALUES (1, "Parent", 0);
INSERT INTO Tree VALUES (2, "Animals", 1);
INSERT INTO Tree VALUES (3, "Cat", 2);
INSERT INTO Tree VALUES (4, "Dog", 2);
INSERT INTO Tree VALUES (5, "Vehicles", 1);
INSERT INTO Tree VALUES (6, "Car", 5);
INSERT INTO Tree VALUES (7, "Bike", 5);
