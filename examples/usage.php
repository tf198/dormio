<?
/**
* This is a runable example
*   php examples/usage.php
*/
$example_path = dirname(__FILE__);

// you may need to change this depending on your system
require_once($example_path . '/../classes/dormio/autoload.php');
Dormio_Autoload::register();

require_once($example_path . '/models.php');

$pdo = new PDO('sqlite::memory:');
// quickly set up the schemas and load some data
foreach(file($example_path . '/setup.sql') as $sql) $pdo->exec($sql);

// create our factory
$dormio = new Dormio_Factory($pdo);

// get a blog
$blog = $dormio->get('Blog', 2);
echo "  {$blog->body}\n";

// get the related comments
echo "\nComments for '{$blog->title}'\n";
foreach($blog->comments as $comment) {
  echo "  {$comment->body}\n";
}

// get only the comments by Bob (alternate related syntax)
echo "\nComments for '{$blog->title}' by 'bob'\n";
foreach($blog->comment_set->filter('author__username', '=', 'bob') as $comment) {
  echo "  {$comment->body}\n";
}

// create a new comment about the blog
$comment = $dormio->get('Comment');
$comment->author = 1;
$comment->body = "Andy likes commenting on his own posts";
$blog->comments->add($comment);
// we forgot to save it but add does that for us
echo "\nNew comment has primary key {$comment->ident()}\n\n";

// managers are reusable
$comments = $dormio->manager('Comment');

// list the last 3 comments and their authors efficiently - requires single query
$set = $comments->with('author')->orderBy('-pk')->limit(3);
printf("  %-50s %-10s\n", 'Comment', 'Author');
foreach($set as $comment) {
  printf("  %-50s %-10s\n", $comment->body, $comment->author->username);
}

// complicated WHERE clause spanning multiple tables
// we should get all the comments for Andy's blogs as he likes red.
echo "\nComments on people who like red\n";
$set = $comments->filter('blog__author__profile_set__fav_colour', 'IN', array('red', 'green'));
foreach($set as $comment) {
  echo "  {$comment->body}\n";
}

// aggregate functions
$stats = $blog->comments->aggregate()->count()->run();
echo "\nBlog has {$stats['pk_count']} comments\n";
$stats = $dormio->manager('Profile')->aggregate()->max('age')->run();
echo "\nOldest contributer is {$stats['age_max']}\n";

?>