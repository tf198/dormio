INSERT INTO user VALUES (1, 'andy', 'secret', 'Andy Andrews');
INSERT INTO user VALUES (2, 'bob', 'asb123', 'Bobby Brown');
INSERT INTO user VALUES (3, 'charles', 'himom', 'Charlie Chalk');
INSERT INTO blog VALUES (1, 'Andy Blog 1', 'My first blog', 1);
INSERT INTO blog VALUES (2, 'Andy Blog 2', 'My second entry', 1);
INSERT INTO blog VALUES (3, 'Bob Blog 1', 'Bob is a blogger', 2);
INSERT INTO comment VALUES (1, 1, 'Andy Comment 1 on Andy Blog 1', 1);
INSERT INTO comment VALUES (2, 1, 'Bob Comment 1 on Andy Blog 1', 2);
INSERT INTO comment VALUES (3, 3, 'Andy Comment 1 on Bob Blog 1', 1);
INSERT INTO comment VALUES (4, 2, 'Andy Comment 1 on Andy Blog 2', 1);
INSERT INTO comment VALUES (5, 2, 'Bob Comment 1 on Andy Blog 2', 2);
INSERT INTO comment VALUES (6, 3, 'Andy Comment 2 on Bob Blog 1', 1);
INSERT INTO profile VALUES (1, 1, 'Edam', 23);
INSERT INTO profile VALUES (2, 2, 'Stilton', 46);
INSERT INTO tag VALUES (1, 'Red');
INSERT INTO tag VALUES (2, 'Orange');
INSERT INTO tag VALUES (3, 'Yellow');
INSERT INTO tag VALUES (4, 'Green');
INSERT INTO blog_x_tag VALUES (1, 1, 2);
INSERT INTO blog_x_tag VALUES (2, 1, 3);
INSERT INTO blog_x_tag VALUES (3, 2, 1);
INSERT INTO blog_x_tag VALUES (4, 3, 2);
INSERT INTO fieldtest VALUES (1, 'Hello, world', 2, 3.14159, 1340632800, 'secret', '192.168.56.3', 2, 1);
