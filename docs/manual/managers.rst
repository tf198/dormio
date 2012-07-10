Dormio Managers
===============

Field specifiers
----------------

All ``Dormio_Query``/``Dormio_Manager`` methods accept complex field names that span
table relationships, adding JOINs as required.  They are constructed by using the local field name
(not database column) with a double underscore denoting a relation.  Using the *Blog* entity from the
examples the following are all valid:

comments
author\_\_username
author\_\_profile\_\_age
comments\_\_author\_\_display_name

Methods
-------

:filter($field, $op, $value):
   Adds a WHERE clause where ``$op`` is any valid SQL comparison [=  >  <  >= <=  IN  LIKE].
   Multiple calls to ``filter()`` will be ANDed together.
   ``$blogs->filter('title', 'LIKE', 'Test%')->filter('author__profile__age', '>', 18);``
   SELECT ... WHERE 'title' LIKE ? AND t3.age > ?
   
:filterBind($field, $op, &$value):
   Same as ``filter()`` but value is passed by reference.
   
:where($condition, $params):
   Create more complex WHERE clauses than a standard ``filter()``.  Field names should be
   enclosed in curly brackets and you should use question marks as placeholders.  ``$params`` should
   be an array with the same number of elements as the number of placeholders.
   ``$blogs->where('({title} IS NOT NULL OR COUNT({comments}) > ?)', array('26'));``
   
:orderBy($field1 [, $field2 ...]):
   Add an ORDER BY clause.  Prefix the field with a minus for DESC
   ``$blogs->orderBy('title', '-author__display_name');``
   
:limit($size [, $offset]):
   Add a LIMIT clause.  Note: some drivers do not support OFFSET
   
:with($field [,$field]):
   Select related table fields.  Note that you pass the local field name for the related entity, not the
   entity name itself.
   ``$blogs->with('author')``
   
:distinct():
   Make the query DISTINCT
   
:fields($field1 [, $field2]):
   Add extra fields (probably from related entities) to the SELECT.
   
:selectField($field):
   Add a field to the SELECT statement, expanding fields inside curly brackets
   ``$blogs->selectField('COUNT({comments}) AS comment_count);``
   
:func:($func, $field):
   Adds an SQL function as a SELECT field.  This runs one function per row in contrast to
   the aggregation functions below which apply to the entire queryset.  So to get a count of the number of
   comments for each blog (as *author__profile__age__count*):
   ``$blogs->func('COUNT', 'author__profile__age');``
   
Aggregation
-----------

All ``Dormio_Manager`` querysets have a ``getAggregator()`` method which returns a ``Dormio_Aggregator`` object.  When you call
the ``run()`` method on an Aggregator it returns an array of aggregate values for the underlying query.
::
	<?php
    $blogs = $dormio->getManger('Blog')
    $info = $blogs->getAggregator()->max('author__profile__age')->min('author__profile__age')->count();
    var_dump($info->run());
    ?>

:count([$field=pk] [,$distinct]):
   Number of records returned.  Note that the ``Dormio_Manager`` object has a ``count()`` method anyway so this is only needed
   if performing other aggregation methods at the same time.
   
:sum($field):
   Total of all fields
   
:max($field):
   Maximum value
   
:min($field):
   Minimum value

:avg($field):
   Average (mean) value
   
