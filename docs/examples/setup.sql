CREATE TABLE blog (blog_id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(30), body TEXT, author_id INTEGER);
CREATE TABLE comment (comment_id INTEGER PRIMARY KEY AUTOINCREMENT, blog_id INTEGER, body TEXT, author_id INTEGER);
CREATE TABLE user (user_id INTEGER PRIMARY KEY AUTOINCREMENT, username VARCHAR(30), password VARCHAR(30));
CREATE TABLE profile (profile_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, fav_colour VARCHAR(30), age INTEGER);
INSERT INTO user VALUES (1, 'andy', 'secret');
INSERT INTO user VALUES (2, 'bob', 'abc123');
INSERT INTO user VALUES (3, 'charles', 'charles');
INSERT INTO blog VALUES (1, 'Andy Blog 1', 'My first blog', 1);
INSERT INTO blog VALUES (2, 'Andy Blog 2', 'My second entry', 1);
INSERT INTO blog VALUES (3, 'Bob Blog 1', 'Bob is a blogger', 2);
INSERT INTO comment VALUES (1, 1, 'Andy Comment 1 on Andy Blog 1', 1);
INSERT INTO comment VALUES (2, 1, 'Bob Comment 1 on Andy Blog 1', 2);
INSERT INTO comment VALUES (3, 3, 'Andy Comment 1 on Bob Blog 1', 1);
INSERT INTO comment VALUES (4, 2, 'Andy Comment 1 on Andy Blog 2', 1);
INSERT INTO comment VALUES (5, 2, 'Bob Comment 1 on Andy Blog 2', 2);
INSERT INTO comment VALUES (6, 3, 'Andy Comment 2 on Bob Blog 1', 1);
INSERT INTO profile VALUES (1, 1, 'red', 23);
INSERT INTO profile VALUES (2, 2, 'black', 46);