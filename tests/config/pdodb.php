<?
$config['orm']=array(
	'connection' => 'sqlite:data/orm.data'
);

$config['default']=array(
	'connection' => 'sqlite::memory:'
);

$config['baddriver']=array(
	'connection' => 'rubbish:driver'
);

$config['noconnect']=array(
	'connection' => 'mysql:rubbishserver'
);
?>