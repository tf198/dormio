<?
$sql = <<< END_SQL
CREATE TABLE "dormio_migration" ("dormio_migration_id" INTEGER PRIMARY KEY AUTOINCREMENT, "module" TEXT, "model" TEXT, "file" TEXT, "applied" INTEGER, "schema" TEXT)
END_SQL;

foreach(explode("\n", $sql) as $line) {
  if($line!='' and $line{0}!='#') $pdo->exec($line);
}

// Need to return a serialized version of the schema
return 'a:4:{s:5:"table";s:16:"dormio_migration";s:7:"version";i:1;s:7:"indexes";a:0:{}s:7:"columns";a:6:{s:2:"pk";a:3:{s:4:"type";s:5:"ident";s:10:"db_column";s:19:"dormio_migration_id";s:8:"is_field";b:1;}s:6:"module";a:4:{s:4:"type";s:6:"string";s:7:"verbose";s:6:"Module";s:10:"db_column";s:6:"module";s:8:"is_field";b:1;}s:5:"model";a:4:{s:4:"type";s:6:"string";s:7:"verbose";s:5:"Model";s:10:"db_column";s:5:"model";s:8:"is_field";b:1;}s:4:"file";a:4:{s:4:"type";s:6:"string";s:7:"verbose";s:4:"File";s:10:"db_column";s:4:"file";s:8:"is_field";b:1;}s:7:"applied";a:4:{s:4:"type";s:9:"timestamp";s:7:"verbose";s:7:"Applied";s:10:"db_column";s:7:"applied";s:8:"is_field";b:1;}s:6:"schema";a:5:{s:4:"type";s:4:"text";s:4:"null";b:1;s:7:"verbose";s:6:"Schema";s:10:"db_column";s:6:"schema";s:8:"is_field";b:1;}}}';
?>
