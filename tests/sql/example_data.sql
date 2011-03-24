INSERT INTO user VALUES (1, 'andy', 'secret');
INSERT INTO user VALUES (2, 'bob', 'abc123');
INSERT INTO user VALUES (3, 'charles', 'charles');

INSERT INTO blog VALUES (1, 'Andy Blog 1', 'My first blog', 1);
INSERT INTO blog VALUES (2, 'Andy Blog 2', 'My second entry', 1);
INSERT INTO blog VALUES (3, 'Bob Blog 1', 'Bob is a blogger', 2);

INSERT INTO comment VALUES (1, 'Andy Comment 1 on Andy Blog 1', 1, 1);
INSERT INTO comment VALUES (2, 'Bob Comment 1 on Andy Blog 1', 2, 1);
INSERT INTO comment VALUES (3, 'Andy Comment 1 on Bob Blog 1', 1, 3);

INSERT INTO profile VALUES (1, 1, 'red', 23);
INSERT INTO profile VALUES (2, 2, 'black', 46);