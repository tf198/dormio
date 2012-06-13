<?
/**
* This is a runable example
*   > php docs/examples/usage.php
* @package Dormio/Examples
* @filesource
*/

/**
* This just registers the autoloader and creates an example database in memory
* @example setup.php
*/ 
$pdo = include('setup.php');

$entities = include('entities.php');
$config = new Dormio_Config;
$config->addEntities($entities);

$dormio = new Dormio($pdo, $config);

// Can map onto any object you want
$blog = new stdClass();
$blog->title = "Hello, World";

$dormio->save($blog, 'Blog');

$blog->title = "New title";
$dormio->save($blog, 'Blog');

// get a blog
$blog = $dormio->getObject('Blog', 2);
echo "\nBlog 2\n";
echo "  {$blog->body}\n";

$blogs = $dormio->getManager('Blog');
echo "\nAll Blogs\n";
foreach($blogs as $row) {
	echo "  {$row->title}\n";
}

// get the related comments
echo "\nComments for '{$blog->title}'\n";
foreach($blog->comments as $comment) {
  echo "  {$comment->body}\n";
}

// get only the comments by Bob
echo "\nComments for '{$blog->title}' by 'bob'\n";
foreach($blog->comments->filter('author__username', '=', 'bob') as $comment) {
	echo "  {$comment->body}\n";
}

echo "\nTags for '{$blog->title}'\n";
foreach($blog->tags as $tag) {
	echo "  {$tag->tag}\n";
}

echo "\nBlogs tagged as Orange\n";
$tags = $dormio->getManager('Tag');
$tag = $tags->filter('tag', '=', 'Orange')->findOne();
foreach($tag->blog_set as $blog) {
	echo "  {$blog->title}\n";
}



// create a new comment about the blog
$comment = $dormio->getObject('Comment');
$comment->author = 1;
$comment->body = "Andy likes commenting on his own posts";
$blog->comments->add($comment);
// we forgot to save it but add does that for us
echo "\nNew comment has primary key {$comment->pk}\n";
foreach($blog->comments as $comment) {
	echo "  {$comment->body}\n";
}

$tag = $dormio->getManager('Tag')->filter('tag', '=', 'Green')->findOne();
//$blog->tags->add($tag);

// managers are reusable
$comments = $dormio->getManager('Comment');

// list the last 3 comments and their authors efficiently - requires single query
echo "\nLast 3 comments with their author\n";
$set = $comments->with('author')->orderBy('-pk')->limit(3);
foreach($set as $comment) {
  printf("  %-50s %-10s\n", $comment->body, $comment->author->display_name);
}

// complicated WHERE clause spanning multiple tables
// we should get all the comments for Andy's blogs as he likes red.
echo "\nComments about people who like stilton or brie\n";
$set = $comments->filter('blog__author__profile_set__fav_cheese', 'IN', array('Stilton', 'Brie'));
foreach($set as $comment) {
  echo "  {$comment->body}\n";
}

// aggregate functions
$stats = $blog->comments->getAggregator()->count()->run();
echo "\nBlog has {$stats['pk.count']} comments\n";
$stats = $dormio->getManager('Profile')->getAggregator()->max('age')->run();
echo "\nOldest contributer is {$stats['age.max']}\n";

//var_dump(get_included_files());

return 42; // for our auto testing
?>