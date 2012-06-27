CREATE TABLE "user" ("user_id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" TEXT NOT NULL);
CREATE TABLE "blog" ("blog_id" INTEGER PRIMARY KEY AUTOINCREMENT, "title" TEXT NOT NULL, "the_blog_user" INTEGER NOT NULL);
CREATE INDEX "blog_the_user_0" ON "blog" ("the_blog_user" ASC);
CREATE TABLE "blog_tag" ("blog_tag_id" INTEGER PRIMARY KEY AUTOINCREMENT, "the_blog_id" INTEGER NOT NULL, "the_tag_id" INTEGER NOT NULL);
CREATE INDEX "blog_tag_the_blog_0" ON "blog_tag" ("the_blog_id" ASC);
CREATE INDEX "blog_tag_tag_0" ON "blog_tag" ("the_tag_id" ASC);
CREATE TABLE "comment" ("comment_id" INTEGER PRIMARY KEY AUTOINCREMENT, "title" TEXT NOT NULL, "the_comment_user" INTEGER NOT NULL, "blog_id" INTEGER NOT NULL);
CREATE INDEX "comment_user_0" ON "comment" ("the_comment_user" ASC);
CREATE INDEX "comment_blog_0" ON "comment" ("blog_id" ASC);
CREATE TABLE "tag" ("tag_id" INTEGER PRIMARY KEY AUTOINCREMENT, "tag" TEXT NOT NULL);
CREATE TABLE "profile" ("profile_id" INTEGER PRIMARY KEY AUTOINCREMENT, "user_id" INTEGER, "age" INTEGER NOT NULL, "fav_cheese" TEXT NOT NULL);
CREATE INDEX "profile_user_0" ON "profile" ("user_id" ASC);
CREATE TABLE "multidep" ("multidep_id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" TEXT NOT NULL);
CREATE TABLE "tree" ("tree_id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" TEXT NOT NULL, "parent_id" INTEGER NOT NULL);
CREATE INDEX "tree_parent_0" ON "tree" ("parent_id" ASC);
CREATE TABLE "tag_x_user" ("tag_x_user_id" INTEGER PRIMARY KEY AUTOINCREMENT, "l_user_id" INTEGER NOT NULL, "r_tag_id" INTEGER NOT NULL);
CREATE INDEX "tag_x_user_lhs_0" ON "tag_x_user" ("l_user_id" ASC);
CREATE INDEX "tag_x_user_rhs_0" ON "tag_x_user" ("r_tag_id" ASC);
CREATE TABLE "comment_x_tag" ("comment_x_tag_id" INTEGER PRIMARY KEY AUTOINCREMENT, "l_comment_id" INTEGER NOT NULL, "r_tag_id" INTEGER NOT NULL);
CREATE INDEX "comment_x_tag_lhs_0" ON "comment_x_tag" ("l_comment_id" ASC);
CREATE INDEX "comment_x_tag_rhs_0" ON "comment_x_tag" ("r_tag_id" ASC);
CREATE TABLE "multidep_x_multidep" ("multidep_x_multidep_id" INTEGER PRIMARY KEY AUTOINCREMENT, "l_multidep_id" INTEGER NOT NULL, "r_multidep_id" INTEGER NOT NULL);
CREATE INDEX "multidep_x_multidep_lhs_0" ON "multidep_x_multidep" ("l_multidep_id" ASC);
CREATE INDEX "multidep_x_multidep_rhs_0" ON "multidep_x_multidep" ("r_multidep_id" ASC);
